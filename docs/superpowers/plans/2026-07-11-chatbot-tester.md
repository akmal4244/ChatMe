# Chatbot Tester Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an owner-authorized popup that lets users test active or inactive chatbots from both chatbot action lists without consuming quota or writing chat logs.

**Architecture:** Extract the deterministic response matching logic into a shared service used by the existing public API and a new authenticated test endpoint. Render one reusable Blade popup per list page and populate it from escaped trigger attributes, with delegated JavaScript handling focus, request state, errors and temporary messages.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, vanilla JavaScript, Tailwind/custom CSS, PHPUnit, Node test runner.

## Global Constraints

- The endpoint is owner/admin authorized and CSRF protected.
- Owner testing works for inactive chatbots.
- Test messages never write chat logs or consume monthly quota.
- The response matcher behavior is preserved; semantic matching improvements remain out of scope.
- User-controlled content is rendered with `textContent`, never `innerHTML`.
- Popup fits 320px, 390px, tablet and desktop viewports.
- No database migration or new dependency is allowed.

---

### Task 1: Shared Chatbot Response Matcher

**Files:**
- Create: `app/Services/ChatbotResponseMatcher.php`
- Modify: `app/Http/Controllers/ApiController.php`
- Test: `tests/Feature/ChatbotTesterTest.php`

**Interfaces:**
- Produces: `ChatbotResponseMatcher::respond(Chatbot $chatbot, string $message): string`
- Consumed by: `ApiController::chat()` and Task 2's `ChatbotTestController`

- [ ] **Step 1: Write a failing parity test**

Create `ChatbotTesterTest` with `RefreshDatabase`, a user/chatbot/knowledge factory helper, and a test resolving `ChatbotResponseMatcher` from the container:

```php
public function test_shared_matcher_returns_the_existing_best_match_and_fallback(): void
{
    $chatbot = $this->chatbotWithKnowledge();
    $matcher = app(ChatbotResponseMatcher::class);

    $this->assertSame('Kami buka setiap hari.', $matcher->respond($chatbot, 'waktu operasi'));
    $this->assertNotSame('', $matcher->respond($chatbot, 'soalan yang tiada padanan'));
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
php artisan test --filter=test_shared_matcher_returns_the_existing_best_match_and_fallback
```

Expected: FAIL because `App\Services\ChatbotResponseMatcher` does not exist.

- [ ] **Step 3: Move matching logic into the service**

Create the service with the current `findBestMatch()` and fallback logic, exposed through `respond()`. Inject it into `ApiController::chat()` and replace the private method call without changing quota, transaction or logging behavior.

- [ ] **Step 4: Run focused API and matcher tests**

Run:

```powershell
php artisan test --filter='ChatbotTesterTest|PlanLimitTest|WidgetApiSecurityTest'
```

Expected: matcher test passes and all existing public API behavior remains green.

- [ ] **Step 5: Commit**

```powershell
git add app/Services/ChatbotResponseMatcher.php app/Http/Controllers/ApiController.php tests/Feature/ChatbotTesterTest.php
git commit -m "refactor: share chatbot response matcher"
```

---

### Task 2: Authorized Owner Test Endpoint

**Files:**
- Create: `app/Http/Controllers/ChatbotTestController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/ChatbotTesterTest.php`

**Interfaces:**
- Consumes: `ChatbotResponseMatcher::respond(Chatbot $chatbot, string $message): string`
- Produces: named route `chatbots.test-message`, JSON `{ response: string }`

- [ ] **Step 1: Write failing endpoint tests**

Add separate tests proving:

```php
public function test_owner_can_test_an_inactive_chatbot_without_logs_or_quota(): void
public function test_other_user_cannot_test_the_chatbot(): void
public function test_admin_can_test_a_managed_chatbot(): void
public function test_test_message_requires_one_to_one_thousand_characters(): void
public function test_guest_cannot_use_the_test_endpoint(): void
```

The owner test must set `is_active=false`, use a plan with zero monthly messages, assert the response, and assert `chat_logs` remains empty.

- [ ] **Step 2: Run endpoint tests and verify RED**

Run:

```powershell
php artisan test --filter=ChatbotTesterTest
```

Expected: FAIL because route `chatbots.test-message` is undefined.

- [ ] **Step 3: Implement the single-action controller and route**

Controller contract:

```php
public function __invoke(
    Request $request,
    Chatbot $chatbot,
    ChatbotResponseMatcher $matcher,
): JsonResponse {
    Gate::authorize('view', $chatbot);
    $validated = $request->validate([
        'message' => ['required', 'string', 'max:1000'],
    ]);

    return response()->json([
        'response' => $matcher->respond($chatbot, trim($validated['message'])),
    ]);
}
```

Register the route inside the existing authenticated group:

```php
Route::post('/chatbots/{chatbot}/test-message', ChatbotTestController::class)
    ->name('chatbots.test-message');
```

- [ ] **Step 4: Run authorization and validation tests**

Run:

```powershell
php artisan test --filter='ChatbotTesterTest|ChatbotAuthorizationTest'
```

Expected: all pass, with no database chat log writes.

- [ ] **Step 5: Commit**

```powershell
git add app/Http/Controllers/ChatbotTestController.php routes/web.php tests/Feature/ChatbotTesterTest.php
git commit -m "feat: add authorized chatbot test endpoint"
```

---

### Task 3: Chatbot Tester Actions and Popup

**Files:**
- Create: `resources/views/partials/chatbot-tester.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/chatbots/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/ManagementFormAccessibilityTest.php`
- Modify: `tests/js/widget-security.test.js`

**Interfaces:**
- Consumes: route `chatbots.test-message`
- Produces: `[data-chatbot-test]` action contract and `#chatbot-tester-modal`

- [ ] **Step 1: Write failing rendered-contract tests**

Add feature assertions that owner responses for `/dashboard` and `/chatbots` contain:

```text
data-chatbot-test
aria-label="Uji chatbot <name>"
data-test-url="<route>"
id="chatbot-tester-modal"
Mod ujian — mesej tidak dikira dalam kuota
```

Add source assertions for delegated click handling, CSRF fetch, request lock, `textContent`, `window.showToast`, Escape and focus restoration. Add a Node test asserting the popup CSS contains a `320px`-safe width and a `16px` mobile input.

- [ ] **Step 2: Run UI contract tests and verify RED**

Run:

```powershell
php artisan test --filter=ManagementFormAccessibilityTest
npm test
```

Expected: new assertions fail because the action and popup do not exist.

- [ ] **Step 3: Add action buttons to both list pages**

Use a real button with icon `ph-chat-circle-dots`, title and accessible label. Populate escaped `data-test-*` attributes for route, names, welcome message, avatar and primary color. Include the shared partial once after the list content.

- [ ] **Step 4: Build the accessible popup partial**

Implement:

- modal backdrop and labelled dialog;
- header identity and mode label;
- `role="log"` temporary message list;
- form, 1,000-character input and send button;
- clear and close controls;
- delegated open handler;
- `fetch` with CSRF and `Accept: application/json`;
- one-request-at-a-time lock;
- message bubbles created with DOM methods and `textContent`;
- inline error plus global toast;
- focus entry/return, Escape and backdrop close;
- clear/reset and chatbot-switch reset.

- [ ] **Step 5: Add responsive light-theme CSS**

Add focused `.chatbot-tester-*` styles. The dialog must use:

```css
width: min(430px, calc(100vw - 24px));
max-height: min(680px, calc(100dvh - 24px));
```

At `max-width: 640px`, set the tester input to `font-size: 16px` and keep all controls reachable above safe-area insets.

- [ ] **Step 6: Run focused UI tests and build**

Run:

```powershell
php artisan test --filter='ManagementFormAccessibilityTest|ChatbotTesterTest|LightThemeTest'
npm test
npm run build
```

Expected: all tests and build pass.

- [ ] **Step 7: Commit**

```powershell
git add resources/views/partials/chatbot-tester.blade.php resources/views/dashboard.blade.php resources/views/chatbots/index.blade.php resources/css/app.css public/css/app.css tests/Feature/ManagementFormAccessibilityTest.php tests/js/widget-security.test.js
git commit -m "feat: add chatbot test popup"
```

---

### Task 4: Full Verification, Browser QA and Production Deployment

**Files:**
- Verify only; change files only if a regression test first proves a discovered defect.

**Interfaces:**
- Consumes all prior tasks.
- Produces production commit parity and QA evidence.

- [ ] **Step 1: Run the complete verification suite**

```powershell
php artisan test
npm test
vendor\bin\pint --test
npm run build
npm audit --audit-level=high
composer validate --strict
composer audit --no-interaction
git diff --check
```

Expected: zero failures, zero known high-severity dependency advisories and clean diff checks.

- [ ] **Step 2: Run local or production browser QA**

Test this flow on desktop and mobile:

```text
login -> Chatbot Saya -> click Uji chatbot -> send message -> response appears
-> clear chat -> welcome restored -> close -> focus returns
```

Also verify inactive chatbot testing, no console errors, no horizontal overflow, 16px mobile input and popup toast on simulated API error where practical.

- [ ] **Step 3: Push the verified commit to GitHub main**

Use the configured HTTPS fallback when global SSH rewriting is unavailable, then confirm the remote `main` SHA equals local `HEAD`.

- [ ] **Step 4: Deploy safely to production**

Verify the production worktree is clean, enter maintenance mode, fast-forward to GitHub `main`, run migrations, clear/cache Laravel optimization, and always return the application to live mode.

- [ ] **Step 5: Verify production**

Confirm:

- local, GitHub and production SHAs match;
- production worktree is clean and has no pending migrations;
- homepage, login and authenticated test endpoint routing respond correctly;
- live CSS and rendered chatbot action contain the tester feature;
- browser desktop/mobile flow passes with no console errors.

---

## Plan Self-Review

- Spec coverage: owner/admin authorization, inactive bots, no logs/quota, shared matcher, responsive popup, errors and focus are covered.
- Placeholder scan: no unfinished markers or deferred implementation steps.
- Interface consistency: both controllers consume `ChatbotResponseMatcher::respond()` and the frontend consumes the named `chatbots.test-message` route.
- Scope: semantic matching fixes, sessions and payment changes remain excluded as required.
