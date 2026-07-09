# ChatMe ToyyibPay Subscription and Light Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to execute each implementation task with a fresh worker and a separate spec-compliance review.

**Goal:** Ship secure ToyyibPay monthly renewals for ChatMe's paid plans and replace the dark interface with an accessible, coherent light theme, then publish and verify it in production.

**Architecture:** Keep the Laravel 12 Blade monolith. Isolate provider details in a ToyyibPay client, record every payment attempt in `payment_orders`, and funnel verified callbacks and reconciliation through one transactional activation service. Reuse one CSS-token light design system across public, auth, application, admin, knowledge, and subscription views.

**Tech stack:** PHP 8.2, Laravel 12.63, PHPUnit 11, Blade, Tailwind CSS 4, Vite 7, JavaScript `node:test`, ToyyibPay Bill API.

**Design source:** `docs/superpowers/specs/2026-07-10-toyyibpay-light-theme-design.md`

## Global constraints

- Use a failing regression test before each behaviour change.
- Never print or commit ToyyibPay, cPanel, database, application, or admin secrets.
- Never trust price, amount, order state, Bill code, status, or redirect URL from the browser.
- Never activate access from ToyyibPay's return URL parameters alone.
- All successful activation paths must call the same idempotent transaction service.
- Do not place a real customer charge during verification.
- Preserve PHP 8.2 and the live URL `https://chatme.akmalmarvis.com`.

---

### Task 1: Payment persistence and subscription entitlement

**Files:**

- Create: `database/migrations/2026_07_10_000001_add_payment_fields_to_subscriptions_table.php`
- Create: `database/migrations/2026_07_10_000002_create_payment_orders_table.php`
- Create: `app/Models/PaymentOrder.php`
- Create: `app/Services/Payments/PaymentActivationService.php`
- Modify: `app/Models/Subscription.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Plan.php`
- Test: `tests/Feature/PaymentActivationTest.php`

**Interfaces:**

- `PaymentActivationService::activate(PaymentOrder $order, string $transactionReference, ?CarbonInterface $paidAt = null): Subscription`
- `PaymentOrder::STATUS_CREATING|PENDING|PAID|FAILED|EXPIRED`
- `User::paymentOrders(): HasMany`
- `PaymentOrder::getRouteKeyName(): string` returns `external_reference`
- `Plan::priceInCents(): int` converts the decimal-string cast without trusting browser or floating-point input

- [ ] Write failing tests for first payment, same-plan extension, plan switch, duplicate callback, two distinct successful orders, and expired/cancelled entitlement exclusion.
- [ ] Run `php artisan test --compact tests/Feature/PaymentActivationTest.php`; confirm intended failures.
- [ ] Add schema/models and a transaction that locks the order, user, and relevant subscription rows.
- [ ] Ensure paid-order and entitlement updates commit together, and duplicate success returns without extending twice.
- [ ] Re-run the targeted test and migration rollback/forward test; expect green.

### Task 2: ToyyibPay client and normalization

**Files:**

- Create: `app/Services/ToyyibPay/ToyyibPayClient.php`
- Create: `app/Services/ToyyibPay/ToyyibPayException.php`
- Create: `app/Support/MalaysianPhone.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Unit/ToyyibPayClientTest.php`
- Test: `tests/Unit/MalaysianPhoneTest.php`

**Interfaces:**

- `ToyyibPayClient::createBill(PaymentOrder $order, User $user, string $phone, string $returnUrl, string $callbackUrl): string`
- `ToyyibPayClient::getBillTransactions(string $billCode): array`
- `ToyyibPayClient::verifyCallbackHash(array $payload): bool`
- `ToyyibPayClient::duitNowQrEnabled(): bool`
- `MalaysianPhone::normalize(string $value): string`

- [ ] Write failing tests for the exact fixed-price form payload, bounded Bill strings, FPX/DNQR owner-charge fields, URL construction, valid/invalid hash, timeout, malformed JSON, and phone formats.
- [ ] Run the two unit test files and confirm red.
- [ ] Implement with Laravel HTTP form requests, explicit timeouts, strict response shape checks, sanitized exceptions, and no retry on Bill creation.
- [ ] Re-run the targeted tests; expect green.

### Task 3: Authenticated paid checkout

**Files:**

- Modify: `app/Http/Controllers/SubscriptionController.php`
- Modify: `routes/web.php`
- Create: `resources/views/subscription/checkout-error.blade.php` only if the normal plan screen cannot express configuration/provider failure clearly
- Test: `tests/Feature/ToyyibPayCheckoutTest.php`

**Interfaces:**

- `POST /subscription/{plan}/checkout`, route name `subscription.checkout`
- Request field: `phone`

- [ ] Write failing tests for unauthenticated access, inactive/free/zero-price rejection, valid order and provider payload, redirect to the configured ToyyibPay host, invalid phone, missing configuration, malformed provider response, and server-calculated amount.
- [ ] Run the targeted test and confirm the broken Stripe stub fails it.
- [ ] Create the `creating` order before the provider call, persist the Bill code and pending status, and redirect only to `{configured-base}/{bill-code}`.
- [ ] Mark a failed creation safely and show a Malay retry message without leaking provider details.
- [ ] Re-run the targeted test; expect green.

### Task 4: Verified callback and idempotency

**Files:**

- Create: `app/Http/Controllers/ToyyibPayCallbackController.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/ToyyibPayCallbackTest.php`

**Interfaces:**

- `POST /payments/toyyibpay/callback`, route name `payments.toyyibpay.callback`

- [ ] Write failing tests for invalid hash, unknown order, Bill mismatch, amount mismatch, statuses 1/2/3, repeated success, success-after-pending, pending-after-success, and sensitive-log redaction.
- [ ] Run the targeted test and confirm red.
- [ ] Exempt only the callback path from CSRF, add a generous provider-safe throttle, validate bounded fields, use `hash_equals`, and pass verified success to `PaymentActivationService`.
- [ ] Return a small deterministic response and never mutate on invalid input.
- [ ] Re-run the targeted test; expect green.

### Task 5: Return page and server-side reconciliation

**Files:**

- Modify: `app/Http/Controllers/SubscriptionController.php`
- Modify: `routes/web.php`
- Create: `resources/views/subscription/result.blade.php`
- Test: `tests/Feature/ToyyibPayReturnTest.php`

**Interfaces:**

- `GET /subscription/orders/{paymentOrder}/return`, route name `subscription.return`
- `POST /subscription/orders/{paymentOrder}/reconcile`, route name `subscription.reconcile`

- [ ] Write failing tests for order ownership, a forged successful return query, pending/paid/failed rendering, matching successful reconciliation, provider mismatch, and repeated reconciliation.
- [ ] Run the targeted test and confirm red.
- [ ] Render local state only; reconcile via the provider's transaction API and require matching Bill, external reference, amount, and successful status before activation.
- [ ] Throttle manual refresh and preserve an already-paid state when later provider rows are pending/failed.
- [ ] Re-run the targeted test; expect green.

### Task 6: Plans, pricing, and legacy billing cleanup

**Files:**

- Create: `database/migrations/2026_07_10_000003_deactivate_legacy_lifetime_plan.php`
- Modify: `database/seeders/PlanSeeder.php`
- Modify: `app/Http/Controllers/LandingController.php`
- Modify: `app/Http/Controllers/SubscriptionController.php`
- Delete: `app/Http/Controllers/WebhookController.php`
- Modify: `resources/views/landing.blade.php`
- Modify: `resources/views/subscription/plans.blade.php`
- Modify: `resources/views/subscription/success.blade.php` (remove or redirect obsolete state)
- Test: `tests/Feature/SubscriptionPlanTest.php`

**Interfaces:**

- Visible plans: active `free`, `pro`, `enterprise`; any other zero-price plan is excluded.
- Unlimited limit (`-1`) renders `Tanpa had`.

- [ ] Write failing tests for exact visible plans/prices, hidden Lifetime plan, correct unlimited copy, checkout forms on paid plans, free-plan behaviour, current-plan state, and absence of Stripe/Cashier calls and routes.
- [ ] Run the targeted test and confirm failures.
- [ ] Normalize seeded plans, deactivate the legacy plan in production, remove dead Stripe code, and point all paid calls-to-action at the ToyyibPay checkout.
- [ ] Re-run the targeted test; expect green.

### Task 7: Visual concept and shared light-theme foundation

**Files:**

- Generate: concept image outside the deployable app, used only as a reference
- Modify: `resources/css/app.css`
- Modify: `resources/views/layouts/guest.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/LightThemeTest.php`
- Test: `tests/Feature/AccessibilityTest.php`

**Interfaces:**

- CSS variables from the approved design specification.
- Every layout exposes `main#main-content`, visible focus styles, and reduced-motion handling.

- [ ] Generate one light ChatMe concept from the approved tokens and extract only reusable visual principles.
- [ ] Write failing markup tests for light theme tokens, skip targets, accessible mobile navigation, auth labels, and removal of the dark page canvas.
- [ ] Run the targeted tests and confirm red.
- [ ] Implement the shared canvas, surfaces, typography, navigation, buttons, fields, tables, alerts, focus states, and responsive shell.
- [ ] Re-run the targeted tests and build CSS; expect green.

### Task 8: Apply the light system to every screen

**Files:**

- Modify: `resources/views/landing.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/onboarding.blade.php`
- Modify: `resources/views/privacy.blade.php`
- Modify: `resources/views/terms.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/auth/*.blade.php`
- Modify: `resources/views/admin/*.blade.php`
- Modify: `resources/views/chatbots/*.blade.php`
- Modify: `resources/views/knowledge/*.blade.php`
- Modify: `resources/views/subscription/*.blade.php`
- Modify: `resources/views/errors/404.blade.php`
- Test: `tests/Feature/LightThemeCoverageTest.php`

- [ ] Add a coverage test that renders every reachable Blade screen and rejects legacy dark-canvas tokens/classes or unlabelled controls.
- [ ] Run it and confirm red.
- [ ] Convert each screen to the shared light surfaces and components without changing functional routes or escaping rules.
- [ ] Verify tables/forms/cards at 320, 768, and 1440 CSS pixels and add no horizontal overflow.
- [ ] Re-run the coverage tests and Vite build; expect green.

### Task 9: Full local verification and security review

**Files:** all changed files, no new interface.

- [ ] Run `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint --test`, PHP lint on application/config/routes/migrations/tests, `composer validate --strict`, and `composer audit --locked`.
- [ ] Run a secret scan against the complete branch and confirm the ToyyibPay/cPanel/admin values are absent.
- [ ] Run browser QA for landing, login/register, dashboard, chatbot, knowledge, plans, checkout, and result states at desktop/mobile widths; inspect console and failed network requests.
- [ ] Conduct a fresh payment/security code review and fix every Critical or Important issue.

### Task 10: Production account configuration, publication, and deployment

**Files:** production `.env`, database, release tree, GitHub branch state.

- [ ] Read the ToyyibPay secret from the supplied attachment without printing it; check DuitNow QR status server-to-server.
- [ ] Create or verify one `ChatMe Subscriptions` category and store only its code plus the secret in production `.env`.
- [ ] Push the reviewed sanitized history with an explicit lease, verify GitHub `main`, and deploy a backed-up release to the hosting document root.
- [ ] Run production migrations, seed plan definitions, optimize caches, and build/link assets as required.
- [ ] Rotate exposed application/admin/hosting access where safely supported and invalidate old sessions without printing new credentials.
- [ ] Verify `/up`, landing, auth, protected routes, pricing, checkout-to-hosted-Bill redirect, callback rejection for invalid signatures, log health, database schema, and production commit.
- [ ] Confirm no real charge was made and no sensitive artifact is reachable over HTTP or Git history.
