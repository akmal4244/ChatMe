# ChatMe Security Stabilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove confirmed cross-tenant and data-exposure vulnerabilities, repair broken public/user workflows, and deploy a tested build to `chatme.akmalmarvis.com`.

**Architecture:** Keep the Laravel 12 monolith and current Blade/widget delivery model. Add model-policy authorization at tenant boundaries, centralize origin validation and quota checks, make the existing JSON textarea the import contract, and remove nonfunctional billing actions until a real payment gateway is configured.

**Tech Stack:** PHP 8.2, Laravel 12.63, PHPUnit 11, Blade, Tailwind CSS 4, Vite 7, JavaScript `node:test`.

## Global Constraints

- Preserve the production URL `https://chatme.akmalmarvis.com`.
- Never commit production database dumps, server logs, plaintext credentials, or generated secrets.
- Only a chatbot owner or an administrator may read or mutate that chatbot and its knowledge items.
- Every production-code bug fix must be preceded by a failing regression test and followed by a green targeted test.
- Do not create a working paid checkout or charge a customer in this stabilization change.
- Keep compatibility with PHP 8.2 and Laravel 12.

---

### Task 1: Tenant and widget security

**Files:**
- Create: `app/Policies/ChatbotPolicy.php`
- Modify: `app/Http/Controllers/ChatbotController.php`
- Modify: `app/Http/Controllers/KnowledgeController.php`
- Modify: `app/Http/Controllers/ApiController.php`
- Modify: `public/widget.js`
- Test: `tests/Feature/ChatbotAuthorizationTest.php`
- Test: `tests/Feature/WidgetApiSecurityTest.php`
- Test: `tests/js/widget-security.test.js`

**Interfaces:**
- `ChatbotPolicy::view|update|delete(User $user, Chatbot $chatbot): bool`
- `ApiController::isOriginAllowed(Request $request, Chatbot $chatbot): bool`

- [x] Write tests proving cross-tenant CRUD, nested-item mutation, lookalike origins, chat-origin bypass, and widget `innerHTML` interpolation fail.
- [x] Run `php artisan test --compact tests/Feature/ChatbotAuthorizationTest.php tests/Feature/WidgetApiSecurityTest.php` and `npm test`; confirm the intended failures.
- [x] Add owner/admin policy checks, nested item ownership checks, exact/subdomain hostname matching, and safe widget DOM assignments.
- [x] Re-run both commands and confirm all targeted tests pass.

### Task 2: Credential and repository hygiene

**Files:**
- Create: `config/chatme.php`
- Modify: `database/seeders/AdminSeeder.php`
- Modify: `.env.example`
- Modify: `.gitignore`
- Delete: `error_log`
- Delete: `public/error_log`
- Delete: `storage/backup_production_20260709_115411.sql`
- Test: `tests/Feature/AdminSeederSecurityTest.php`

**Interfaces:**
- `config('chatme.admin.name|email|password')`
- `AdminSeeder` skips provisioning unless name, email, and password are all explicitly configured.

- [x] Write tests proving no default admin is created and configured credentials are hashed.
- [x] Run the test and confirm both cases fail against the original hardcoded seeder.
- [x] Replace hardcoded identity/password with environment-backed config, remove leaked artifacts, and add ignore rules.
- [x] Run `php artisan test --compact tests/Feature/AdminSeederSecurityTest.php` and confirm both tests pass.

### Task 3: JSON knowledge import and knowledge quota

**Files:**
- Modify: `app/Http/Controllers/KnowledgeController.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/KnowledgeImportTest.php`

**Interfaces:**
- Request field: `json_data`, a JSON list of objects containing `question`, `answer`, optional `category`, and optional `tags`.
- `User::canAddKnowledgeItems(Chatbot $chatbot, int $count = 1): bool`

- [ ] Add failing tests for valid JSON, malformed JSON with zero partial writes, and an over-limit import.
- [ ] Run `php artisan test --compact tests/Feature/KnowledgeImportTest.php`; expect failures caused by the current file-upload contract.
- [ ] Decode with `JSON_THROW_ON_ERROR`, validate the complete array, check the active plan limit before writes, and create rows inside one database transaction.
- [ ] Re-run the targeted test; expect all cases to pass.

### Task 4: Chatbot and monthly-message quotas

**Files:**
- Modify: `app/Http/Controllers/ChatbotController.php`
- Modify: `app/Http/Controllers/ApiController.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/PlanLimitTest.php`

**Interfaces:**
- Existing `User::canCreateChatbot(): bool` gates chatbot creation.
- `User::canSendChatMessage(): bool` counts user-role messages from the current calendar month and treats `-1` as unlimited.

- [ ] Add failing tests for a second free-plan chatbot and a message beyond a one-message test plan.
- [ ] Run `php artisan test --compact tests/Feature/PlanLimitTest.php`; expect both over-limit requests to succeed incorrectly.
- [ ] Reject chatbot creation with validation feedback and reject over-limit API chat with HTTP 429 before any log row is written.
- [ ] Re-run the targeted test; expect all cases to pass.

### Task 5: Public accessibility and route smoke fixes

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Test: `tests/Feature/AccessibilityTest.php`

**Interfaces:**
- Every skip link targets an existing `main#main-content`.
- Authentication labels use matching `for` and control `id` attributes.

- [ ] Add failing response-markup tests for the skip target and auth label/control pairs.
- [ ] Run `php artisan test --compact tests/Feature/AccessibilityTest.php`; expect failures on the missing attributes.
- [ ] Add the semantic main target and explicit form associations without changing layout or copy.
- [ ] Re-run the targeted test; expect all cases to pass.

### Task 6: Billing containment and pricing sentinels

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/landing.blade.php`
- Modify: `resources/views/subscription/plans.blade.php`
- Modify: `app/Http/Controllers/SubscriptionController.php`
- Test: `tests/Feature/SubscriptionSafetyTest.php`

**Interfaces:**
- No public route invokes unavailable Cashier methods.
- `-1` plan limits render as `Tanpa had`.
- Paid plans provide a contact action; this change does not charge customers.

- [ ] Add failing tests for the current GET-to-POST CTA, missing success action, and negative quota text.
- [ ] Run `php artisan test --compact tests/Feature/SubscriptionSafetyTest.php`; expect failures.
- [ ] Remove the broken subscribe/success endpoints, route authenticated CTAs safely to the plans page or contact email, and render `-1` as unlimited.
- [ ] Re-run the targeted test; expect all cases to pass.

### Task 7: Verification, secure publication, and deployment

**Files:**
- Verify all changed files and production state; no additional application interface.

- [ ] Run `php artisan test`, `npm test`, `npm run build`, PHP lint, `composer validate --strict`, and `composer audit --locked`.
- [ ] Review the complete diff for secrets and unrelated changes.
- [ ] Commit on `fix/security-stabilization`, publish the reviewed commit, remove sensitive artifacts from public Git history, and synchronize production safely.
- [ ] Invalidate exposed sessions and rotate the exposed admin/cPanel credentials without printing them.
- [ ] Verify GitHub `main`, production HEAD, public routes, raw leaked-file URLs, server logs, and the live security behaviors.
