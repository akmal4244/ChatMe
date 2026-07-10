# ChatMe ToyyibPay Subscription and Light Theme Design

**Status:** Approved for implementation on 2026-07-10

**Goal:** Replace the broken Stripe-shaped subscription stubs with a secure ToyyibPay checkout and monthly renewal flow, while converting every customer-facing and authenticated screen to one coherent, accessible light theme.

## Product Decisions

- Free remains available without payment.
- Pro (RM49/month) and Enterprise (RM149/month) are paid one month at a time.
- A successful payment grants or extends exactly one calendar month of access.
- ToyyibPay's currently documented Bill API is a one-time payment API. Direct Debit is still marked "Coming Soon", so the application must describe this as monthly renewal, not automatic bank deduction.
- The checkout enables FPX and DuitNow QR. Transaction charges are borne by the bill owner.
- A return-page query string is informational only. It can never activate a subscription.
- Only a verified server callback or a successful server-to-server reconciliation may activate access.
- The legacy `lifetime` zero-price plan is made inactive. Only the `free` slug may be presented as a free plan.

Official references:

- <https://toyyibpay.com/apireference/>
- <https://www.toyyibpay.com/solutions/>

## Payment Architecture

### Configuration

`config/services.php` exposes a `toyyibpay` section backed by environment variables:

- `TOYYIBPAY_BASE_URL` (default production URL; tests override it)
- `TOYYIBPAY_SANDBOX` (must be enabled to use the official `dev.toyyibpay.com` host)
- `TOYYIBPAY_SECRET_KEY`
- `TOYYIBPAY_CATEGORY_CODE`
- `TOYYIBPAY_DNQR_ENABLED`
- `TOYYIBPAY_TIMEOUT`

Secrets and category identifiers remain outside Git. A missing secret or category code disables paid checkout with a user-safe message and a server-side log entry. Application logs must never include the secret, callback hash, or complete provider payload.

### Payment orders

Create a `payment_orders` table as the immutable payment intent and callback idempotency boundary:

| Field | Purpose |
| --- | --- |
| `id` | Internal primary key |
| `user_id`, `plan_id` | Purchased account and plan |
| `subscription_id` | Resulting entitlement, nullable until a verified success |
| `external_reference` | Random UUID, unique, sent as ToyyibPay `order_id` |
| `bill_code` | ToyyibPay Bill code, nullable until Bill creation, unique when present |
| `provider` | `toyyibpay` |
| `amount_cents` | Server-calculated fixed amount in cents |
| `status` | `creating`, `pending`, `paid`, `failed`, or `expired` |
| `transaction_reference` | Provider payment reference, nullable, unique when present |
| `failure_reason` | Sanitized, bounded reason for support diagnostics |
| `paid_at` | First verified successful payment time |
| timestamps | Audit timing |

The client never supplies price, amount, callback URL, Bill code, or status. All values come from the selected active plan and trusted server configuration.

### Subscriptions

Extend `subscriptions` without deleting legacy columns so existing installations can migrate safely:

- `provider` nullable (`toyyibpay` for new paid terms, `system` for free/admin provisioning)
- `provider_reference` nullable and unique when present
- `status` with `active`, `inactive`, `expired`, and `cancelled`
- `starts_at` nullable

The migration audits legacy state before access is evaluated: only `active` Stripe rows and unexpired `trialing` rows are backfilled as active; unknown or cancelled rows fail closed. A legacy active Stripe row without an end date receives a one-month transition term. The exact historical pairing `plan.slug = lifetime` plus `stripe_status = lifetime` is grandfathered as an explicit `legacy_lifetime` entitlement while the plan itself becomes inactive for new sales. `activeSubscription()` then requires explicit `status = active`, a recorded start time, and a future end time for every paid plan. A zero-priced active Free or grandfathered Lifetime entitlement may remain open-ended.

For each first successful paid order, the activation service captures one activation timestamp, then runs in a database transaction and row-locks the payment order, the user row, and the user's eligible subscription rows in that order. Locking the user row serializes two different successful orders even when no subscription row exists yet. Provider timestamps may be recorded as payment evidence, but entitlement time always starts or extends from the trusted server activation time:

1. If the order is already `paid`, return its linked subscription without adding time.
2. For the same plan, preserve the latest active end date. For a different plan, convert every remaining second into destination-plan seconds using integer-only value proration: `remaining_seconds * source_monthly_cents / destination_order_cents`.
3. Add one calendar month with no overflow to the selected plan plus any prorated credit, expire the old paid term, and store the ToyyibPay reference on the resulting entitlement. This preserves paid value without allowing cheaper-plan time to become equal-duration access on a more expensive plan.
4. Mark the payment order paid in the same transaction.

This makes repeated callbacks and callback/reconciliation races idempotent.

## ToyyibPay Client Contract

The gateway class owns all provider-specific field names and response parsing.

### Create Bill

POST form data to `/index.php/api/createBill` with:

- fixed price (`billPriceSetting=1`) and payer info required (`billPayorInfo=1`)
- `billAmount` from `amount_cents`
- sanitized Bill name (maximum 30 allowed characters) and description (maximum 100)
- HTTPS return and callback URLs generated by Laravel
- UUID `billExternalReferenceNo`
- normalized Malaysian payer phone, account name, and email
- FPX (`billPaymentChannel=0`)
- three-day expiry
- DuitNow QR enabled only when production activation has been confirmed
- `chargeDuitNowQR=0`; owner absorbs charges

Bill creation is not automatically retried because the API does not expose an idempotency key and a retry could create duplicate Bills. Timeouts and malformed responses leave the order failed with a safe retry action for the user.

### Callback verification

The public callback is POST-only, CSRF-exempt, rate-limited, and validates a bounded set of strings. Before any database mutation it compares hashes with `hash_equals` using ToyyibPay's documented formula:

`md5(secret + status + order_id + refno + "ok")`

The handler then requires all of these:

- a known `external_reference`
- exact `bill_code`
- exact expected amount after normalizing ToyyibPay's decimal Ringgit value to cents
- a valid supported status (`1` success, `2` pending, `3` failure)

A verified success invokes the activation service. Pending/failure updates never remove time already granted by a prior success. Invalid callbacks receive a non-success response and are logged without sensitive fields.

### Return and reconciliation

The authenticated return route binds the order by its opaque `external_reference` rather than its numeric primary key, then enforces ownership. It displays the local state and may ask `getBillTransactions` for a server-side status refresh. A matching successful provider transaction must match Bill code, external reference, and amount before it invokes the same activation service. The return query's `status_id` is never itself trusted.

## HTTP and User Flow

- `GET /subscription/plans` — active public plans and current entitlement.
- `POST /subscription/{plan}/checkout` — authenticated; validates phone, creates the order and Bill, then redirects to ToyyibPay.
- `POST /payments/toyyibpay/callback` — public provider callback.
- `GET /subscription/orders/{paymentOrder}/return` — authenticated payment-result screen, ownership enforced.
- `POST /subscription/orders/{paymentOrder}/reconcile` — authenticated manual refresh, ownership and throttling enforced.

Free-plan selection never goes through ToyyibPay. Existing paid access is not silently shortened by choosing Free; plan downgrade is deferred until the paid term ends and is explained in the UI.

## Validation and Failure Behaviour

- Only active paid plans with a positive server-side price can start checkout.
- Malaysian phone input accepts common local or `+60` forms and is normalized before sending.
- Creating a duplicate pending order is allowed only as an explicit retry; every attempt gets a new UUID and Bill.
- Provider/network errors return to the plan screen with a generic Malay message and a retry affordance.
- Provider failure/pending responses never produce an active subscription.
- Database exceptions roll back both order and subscription mutations.
- All redirect targets are generated locally or from the configured ToyyibPay base URL; no request-provided redirect is used. The client allows only `toyyibpay.com` in production mode or `dev.toyyibpay.com` when sandbox mode is explicit, and never follows provider redirects for API requests.

## Light Theme Design System

The visual direction is calm, editorial, and product-focused rather than glossy dark glass.

### Tokens

| Role | Value |
| --- | --- |
| Page canvas | `#F7F6F1` |
| Primary surface | `#FFFFFF` |
| Secondary surface | `#F1EFEA` |
| Primary text | `#171717` |
| Muted text | `#67655F` |
| Subtle text | `#858179` |
| Border | `#E2DED5` |
| Primary accent | `#4F46E5` |
| Accent hover | `#4338CA` |
| Accent wash | `#EEF2FF` |
| Success | `#16845B` |
| Danger | `#B93838` |

- `Plus Jakarta Sans` remains the UI typeface; `Newsreader` is reserved for large marketing headings.
- Cards are solid white with subtle one-pixel borders and restrained shadows.
- Buttons have strong focus rings, a minimum 44-pixel target on primary actions, and no low-contrast text.
- Motion is limited to short opacity/transform transitions and respects `prefers-reduced-motion`.

### Screen treatment

- Landing: warm-white canvas, indigo hero action, editorial headline, three clear pricing cards, and payment-channel copy derived from the same FPX/DuitNow capability flag used by checkout.
- Guest auth: centered white card, persistent labels, visible validation, and an existing `main#main-content` skip target.
- Application shell: white sidebar/topbar, indigo active state, charcoal content, warm-neutral page background.
- Dashboard/admin/forms/tables: consistent surface, border, focus, success, and danger tokens; no remaining white-on-dark utility combinations.
- Subscription result: explicit paid/pending/failed states and a clear dashboard or retry action.
- Mobile: collapsible navigation, single-column price cards, no horizontal overflow at 320 CSS pixels.

## Accessibility and Security Acceptance Criteria

- Every page has a visible-on-focus skip link targeting `main#main-content`.
- Every form control has a programmatic label and errors are associated or adjacent.
- Keyboard focus is visible on links, buttons, inputs, menu controls, and pricing actions.
- Normal text/background combinations target WCAG AA contrast.
- All user/provider strings are rendered escaped; no callback reason is output as raw HTML.
- Paid access cannot be created by a forged return URL, invalid callback hash, wrong Bill, wrong amount, repeated callback, or concurrent callback/reconciliation.

## Verification Strategy

1. Feature tests for plan visibility, checkout payload, config failure, phone normalization, callback hash, Bill/order/amount mismatch, every status, idempotency, concurrency-safe extension, plan switch, ownership, and untrusted return data.
2. Unit tests for hash verification, amount normalization, phone normalization, and subscription date arithmetic.
3. Full Laravel suite, JavaScript widget tests, Vite production build, PHP lint, Composer validation, and dependency audit.
4. Browser QA of landing, auth, dashboard, chatbot, knowledge, subscription and result screens at desktop and mobile widths, including console errors and keyboard focus.
5. Production smoke checks for migrations, `/up`, public routes, authenticated checkout creation, callback route behaviour, HTTPS URLs, and absence of secrets in logs/source.

No real customer charge is performed during deployment verification. The production account's DuitNow QR status and category configuration are checked server-to-server, and checkout is verified up to the ToyyibPay-hosted Bill page.
