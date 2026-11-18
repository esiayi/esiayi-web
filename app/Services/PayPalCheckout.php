<?php

namespace App\Services;

use App\Models\PaypalPayment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal PayPal Checkout client (Orders v2 REST API), replacing the abandoned
 * paypal/rest-api-sdk-php, which used the deprecated v1 Payments API and blocked
 * the Laravel upgrade.
 *
 * Flow: createOrder() -> send the customer to the approval URL -> PayPal returns to
 * our return URL with ?token={order id} -> captureOrder().
 */
class PayPalCheckout
{
    public function __construct(private PaypalPayment $settings)
    {
    }

    public static function fromSettings(): self
    {
        return new self(PaypalPayment::firstOrFail());
    }

    public function currency(): string
    {
        return strtoupper((string) $this->settings->currency_code);
    }

    /**
     * Create an order for $amount (in the PayPal currency) and return its id and the URL
     * the customer must visit to approve it.
     *
     * @return array{id: string, approve_url: string}
     */
    public function createOrder(float $amount, string $returnUrl, string $cancelUrl, string $description): array
    {
        $unit = [
            'amount' => [
                'currency_code' => $this->currency(),
                'value' => number_format($amount, 2, '.', ''),
            ],
        ];
        if ($description !== '') {
            $unit['description'] = mb_substr($description, 0, 127);
        }

        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$unit],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ],
            ],
        ]);

        $approveUrl = collect($response->json('links', []))
            ->firstWhere(fn ($link) => in_array($link['rel'] ?? null, ['payer-action', 'approve']))['href'] ?? null;

        if (!$response->successful() || !$response->json('id') || !$approveUrl) {
            throw new RuntimeException('PayPal order could not be created: '.$response->status());
        }

        return ['id' => $response->json('id'), 'approve_url' => $approveUrl];
    }

    /**
     * The order's status and amount, read before capturing so the amount can be checked
     * while no money has moved yet.
     *
     * @return array{status: string, amount: string, currency: string}
     */
    public function getOrder(string $orderId): array
    {
        $response = $this->client()->get('/v2/checkout/orders/'.rawurlencode($orderId));

        if (!$response->successful() || !$response->json('status')) {
            throw new RuntimeException('PayPal order could not be read: '.$response->status());
        }

        return [
            'status' => $response->json('status'),
            'amount' => (string) $response->json('purchase_units.0.amount.value'),
            'currency' => strtoupper((string) $response->json('purchase_units.0.amount.currency_code')),
        ];
    }

    /**
     * Capture an approved order. A capture can be COMPLETED, or PENDING when PayPal holds
     * the funds (e.g. for review); both mean the customer has paid. If the order was
     * already captured (a retry), the existing capture is returned.
     *
     * @return array{capture_id: string, status: string, amount: string, currency: string}
     */
    public function captureOrder(string $orderId): array
    {
        $response = $this->client()
            ->withBody('{}', 'application/json')
            ->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture');

        if (!$response->successful() && $response->json('details.0.issue') === 'ORDER_ALREADY_CAPTURED') {
            $response = $this->client()->get('/v2/checkout/orders/'.rawurlencode($orderId));
        }

        $capture = $response->json('purchase_units.0.payments.captures.0');

        if (!$response->successful() || !in_array($capture['status'] ?? null, ['COMPLETED', 'PENDING'], true)) {
            throw new RuntimeException('PayPal order was not captured: '.$response->status());
        }

        return [
            'capture_id' => $capture['id'],
            'status' => $capture['status'],
            'amount' => (string) $capture['amount']['value'],
            'currency' => strtoupper($capture['amount']['currency_code']),
        ];
    }

    /**
     * Refund a capture in full. Used only if a capture turns out not to match the booking.
     */
    public function refundCapture(string $captureId): bool
    {
        return $this->client()
            ->withBody('{}', 'application/json')
            ->post('/v2/payments/captures/'.rawurlencode($captureId).'/refund')
            ->successful();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30);
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $this->settings->client_id, (string) $this->settings->secret_id)
            ->timeout(30)
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$response->successful() || !$response->json('access_token')) {
            throw new RuntimeException('PayPal authentication failed: '.$response->status());
        }

        return $response->json('access_token');
    }

    private function baseUrl(): string
    {
        return strtolower((string) $this->settings->account_mode) === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
