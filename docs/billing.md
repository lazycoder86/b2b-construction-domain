# Billing & entitlements guide

The **organization** is the Stripe customer (Laravel Cashier `Billable` on `App\Models\Organization`).
App code never checks plan names. It asks what the organization is _entitled_ to.

## Checking entitlements

```php
$organization->entitlements()->allows('csv_export');   // feature on/off
$organization->entitlements()->limit('seats');         // int, or null = unlimited
$organization->entitlements()->hasRoomFor('projects', $used);
```

- Routes: `->middleware('feature:csv_export')`. Without the feature, browsers are redirected to
  billing with a toast; JSON clients get `402`.
- Frontend (UI hints only): `useFeature('csv_export')`, or `currentOrganization.billing`.
- Seats (members + pending invitations) are enforced on invite and accept.

Plans, features, limits and prices are defined in **`config/billing.php`**. Rename the example
features to fit the product.

## How the plan is decided (`EntitlementResolver`)

1. `organizations.entitlement_overrides.plan`: set by sales/support (status `custom`).
2. A valid Cashier subscription whose Stripe price matches a plan in config.
    - `past_due` keeps access while Stripe retries the payment.
    - A cancelled subscription keeps access until the period ends (status `cancelling`).
    - `unpaid`, `incomplete` and ended subscriptions grant nothing.
3. App-managed trial: new organizations get `billing.trial.plan` for `billing.trial.days`, with no
   card needed (`trial_ends_at`, Cashier's "generic trial").
4. `billing.default_plan` (free).

`entitlement_overrides.features` / `.limits` are then added on top, e.g.
`{"features": ["webhooks"], "limits": {"seats": 50}}`.

The resolver reads Cashier's local tables only, so checks never call Stripe. Stripe API calls go
through `App\Contracts\Billing\BillingGateway`, whose implementation is `CashierBillingGateway`.
Tests swap in `Tests\Fixtures\FakeBillingGateway`.

## Owner/admin permissions

- `billing.view` (owners, admins): billing page, banner, Billing nav item.
- `billing.manage` (owners only): subscribe, change plan, customer portal.

## Lifecycle

| Event                               | What happens                                                                                                     |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Create organization                 | Trial starts (`BILLING_TRIAL_DAYS`, 0 disables)                                                                  |
| 3 days before trial end             | `billing:send-trial-reminders` (daily at 09:00) emails owners once                                               |
| Trial ends, no subscription         | Falls back to the free plan (no job needed). Existing members stay, new invites are blocked above the seat limit |
| Owner subscribes                    | Stripe Checkout, then the `customer.subscription.created` webhook mirrors the subscription and ends the trial    |
| Plan change                         | `subscription()->swap()` (prorated)                                                                              |
| Payment fails                       | `invoice.payment_failed`: owners are emailed, a banner shows, Stripe retries                                     |
| Stripe gives up / cancellation ends | Subscription invalid, so the org is back on the free plan                                                        |
| Per-seat plan (`per_seat: true`)    | Membership changes queue `SyncSubscriptionSeats`, which keeps the quantity equal to the member count             |
| Account deletion                    | Blocked while a solo organization still has a subscription                                                       |

## Webhooks

`POST /stripe/webhook` uses `App\Http\Controllers\StripeWebhookController` (it extends Cashier's controller):

- **Signature verification** when `STRIPE_WEBHOOK_SECRET` is set. Always set it outside local dev.
- **Idempotent**: event ids are stored in `stripe_events`, so a redelivered event is acknowledged without
  being processed again. If processing fails, the id is removed so Stripe's retry gets processed.
- Usage metering placeholder: `App\Support\Billing\UsageMeter::record($org, 'api_calls', 1)`,
  stored in `usage_records`, ready to report to Stripe meters later.

## Going live: setup checklist

1. Create products and **recurring prices** in Stripe (monthly + yearly for each paid plan).
   Put the price ids in `.env` (`STRIPE_PRICE_*`).
2. Set `STRIPE_KEY`, `STRIPE_SECRET`.
3. Create the webhook: `php artisan cashier:webhook` (registers the events in `config/cashier.php`,
   including `invoice.payment_failed`). Copy the signing secret to `STRIPE_WEBHOOK_SECRET`.
   Locally: `stripe listen --forward-to localhost:8000/stripe/webhook`.
4. Stripe dashboard:
    - Configure the **Customer Portal** (payment methods, invoices, cancellation).
    - Configure **Smart Retries / dunning** (Billing → Revenue recovery), with "cancel" or
      "mark unpaid" after the final retry.
5. Run a **queue worker** (emails, seat sync) and the **scheduler** (`php artisan schedule:run` every minute).
6. Optional: `Cashier::calculateTaxes()` with Stripe Tax, and set a currency with `CASHIER_CURRENCY`.
