# Mobile app: card payments with Stripe PaymentIntents

The app used to send the card number, expiry and CVC to `POST /api/pay-with-stripe/{slug}`, and the server turned them into a Stripe token. That puts card data on our server, cannot do 3-D Secure (which most UK cards require), and Stripe rejects it on most accounts. It is now **off by default** (`STRIPE_ALLOW_RAW_CARD_API=false`). Card details should only ever go to Stripe's SDK on the device.

## New flow

1. **Create the PaymentIntent.** `POST /api/stripe/payment-intent/{slug}` (JWT auth), with the same booking fields as today:

   | field | |
   |---|---|
   | `name`, `phone`, `address` | required |
   | `email`, `post_code`, `order_note` | optional |
   | `date` | `dd-mm-yyyy` or `yyyy-mm-dd` |
   | `schedule_time_slot` | appointment schedule id |
   | `ids[]`, `quantities[]` | chosen add-ons and quantities (prices are worked out on the server; `prices[]` is ignored) |

   Response:

   ```json
   { "payment_intent_id": "pi_...", "client_secret": "pi_..._secret_...", "publishable_key": "pk_live_...", "amount": 17000, "currency": "gbp" }
   ```

   `amount` is in pence. Show it to the customer; it is what they will be charged.

2. **Confirm on the device** with the official Stripe SDK (`flutter_stripe` / `@stripe/stripe-react-native` / Stripe iOS & Android), using `publishable_key` and `client_secret`, e.g. PaymentSheet. The SDK collects the card and handles 3-D Secure.

3. **Place the order.** When the SDK reports success, `POST /api/pay-with-stripe/{slug}` with **the same booking fields** plus `payment_intent_id`. No card fields.

   The server checks with Stripe that the intent succeeded, was paid in full, is for this customer, service, date and slot, and has not been used before. Then it creates the booking (`{"message": "Your order has been placed..."}`). Otherwise it returns `403 {"error": "Payment faild"}`.

## Errors

| status | meaning |
|---|---|
| 422 | validation error (`errors` lists the fields), or `{"message": "Please update the app to pay by card."}` when an old app version posts card details |
| 403 | service not found, slot already booked, card payments disabled, or payment not verified |

## Rollout

Ship the app update first. If the current app's card payments work today (only possible when the Stripe account allows raw card data), set `STRIPE_ALLOW_RAW_CARD_API=true` on the server until most users have updated, then remove it.
