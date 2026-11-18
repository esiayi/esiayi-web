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
        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => mb_substr($description, 0, 127),
                'amount' => [
                    'currency_code' => $this->currency(),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
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
     * Capture an approved order. Returns the completed capture's id, amount and currency.
     *
     * @return array{capture_id: string, amount: string, currency: string}
     */
    public function captureOrder(string $orderId): array
    {
        $response = $this->client()
            ->withBody('{}', 'application/json')
            ->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture');

        $capture = $response->json('purchase_units.0.payments.captures.0');

        if (!$response->successful() || $response->json('status') !== 'COMPLETED' || ($capture['status'] ?? null) !== 'COMPLETED') {
            throw new RuntimeException('PayPal order was not captured: '.$response->status());
        }

        return [
            'capture_id' => $capture['id'],
            'amount' => $capture['amount']['value'],
            'currency' => strtoupper($capture['amount']['currency_code']),
        ];
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
