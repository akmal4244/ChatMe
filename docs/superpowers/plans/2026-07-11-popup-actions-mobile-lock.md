# Popup Actions and Mobile Zoom Lock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add dashboard chatbot deletion, consistent custom confirmation and toast popups, and a fully locked mobile viewport that does not auto-zoom while chatting.

**Architecture:** Keep authorization and mutations in the existing Laravel controllers, and centralize browser behavior in shared Blade UI infrastructure. A shared toast partial serves guest and authenticated layouts, while the authenticated layout owns the existing accessible confirmation dialog and a delegated `data-confirm-*` form contract. Mobile locking is enforced by both layout viewport metadata and 16px mobile form controls; the embeddable widget prevents input auto-zoom without changing a customer site's viewport metadata.

**Tech Stack:** Laravel 12, Blade, vanilla JavaScript, Tailwind/Vite CSS, Node test runner, PHPUnit, Laravel Pint, Codex in-app Browser.

## Global Constraints

- Use Bahasa Melayu Malaysia for every user-facing label and message.
- Use custom ChatMe dialogs and remove `window.confirm()` from application actions.
- Keep field validation messages inline while showing one popup summary.
- Lock ChatMe pages with `minimum-scale=1`, `maximum-scale=1` and `user-scalable=no`.
- Keep every mobile `input`, `textarea` and `select` at 16px or larger.
- Never modify the host page viewport metadata from `public/widget.js`.
- Preserve server-side authorization, CSRF, subscription logic and ToyyibPay behavior.
- Work in `C:\Users\User\Documents\Codex\2026-07-10\se\work\ChatMe\.worktrees\bahasa-melayu-homepage-chatbot`.

## File Map

- Create `resources/views/partials/toasts.blade.php`: shared toast container, safely serialized JSON notification payload and global toast controller.
- Modify `resources/views/layouts/app.blade.php`: locked viewport, shared toast partial, accessible delegated confirmation controller and removal of legacy inline flash markup.
- Modify `resources/views/layouts/guest.blade.php`: locked viewport and shared toast partial.
- Modify `resources/css/app.css`: toast stack states, responsive positioning, 16px mobile controls and touch behavior.
- Modify `public/css/app.css`: generated production stylesheet from `npm run build`.
- Modify `public/widget.js`: 16px mobile message input and dynamic viewport-safe chat window height.
- Modify `resources/views/dashboard.blade.php`: delete chatbot form and icon in the action column.
- Modify `resources/views/chatbots/index.blade.php`: declarative custom delete confirmation.
- Modify `resources/views/knowledge/index.blade.php`: declarative custom delete confirmation.
- Modify `resources/views/admin/users.blade.php`: declarative role-change confirmation.
- Modify `resources/views/chatbots/embed.blade.php`: declarative API-key confirmation and toast copy feedback.
- Modify `tests/Feature/LightThemeTest.php`: locked viewport, shared toast and mobile control assertions.
- Modify `tests/Feature/ManagementFormAccessibilityTest.php`: dashboard delete action and confirmation contract assertions.
- Modify `tests/js/widget-security.test.js`: widget mobile font and viewport regression assertions.

---

### Task 1: Lock Mobile Viewport and Prevent Input Auto-Zoom

**Files:**
- Modify: `tests/Feature/LightThemeTest.php`
- Modify: `tests/js/widget-security.test.js`
- Modify: `resources/views/layouts/app.blade.php:5`
- Modify: `resources/views/layouts/guest.blade.php:5`
- Modify: `resources/css/app.css`
- Modify: `public/widget.js:24`
- Modify: `public/css/app.css` through the build command

**Interfaces:**
- Produces: both layouts expose the exact viewport policy `width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover`.
- Produces: widget CSS contains a mobile media rule that sets `#chatme-input` to `font-size:16px` without creating or changing a viewport meta element.

- [ ] **Step 1: Write failing layout and widget tests**

Add to `LightThemeTest`:

```php
public function test_mobile_viewport_is_locked_and_form_controls_do_not_trigger_auto_zoom(): void
{
    $viewport = 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';

    foreach (['guest', 'app'] as $layout) {
        $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
        $this->assertStringContainsString('content="'.$viewport.'"', $source);
    }

    $css = file_get_contents(resource_path('css/app.css'));
    $this->assertStringContainsString('touch-action: manipulation', $css);
    $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\).*?(?:input|\.input).*?font-size:\s*16px/s', $css);
}
```

Add to `tests/js/widget-security.test.js`:

```js
test('widget avoids mobile input zoom without changing the host viewport', () => {
  assert.match(source, /@media\(max-width:640px\)\{#chatme-input\{font-size:16px\}/);
  assert.match(source, /100dvh/);
  assert.doesNotMatch(source, /createElement\(['"]meta['"]\)|querySelector\(['"]meta\[name=[^)]*viewport/);
});
```

- [ ] **Step 2: Run tests and verify the expected failures**

Run:

```powershell
php artisan test --filter=test_mobile_viewport_is_locked_and_form_controls_do_not_trigger_auto_zoom
npm test
```

Expected: the PHP test fails because both meta tags omit the lock tokens; the Node test fails because the widget input is 13.5px and does not use `100dvh`.

- [ ] **Step 3: Implement the minimal viewport and mobile-control rules**

Use the exact meta tag in both layouts:

```html
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
```

Add scoped mobile rules in `resources/css/app.css`:

```css
button, a, input, textarea, select { touch-action: manipulation; }

@media (max-width: 640px) {
    input, textarea, select, .input, .form-control,
    .form-field input, .form-field textarea, .form-field select,
    .checkout-form input, .checkout-form textarea {
        font-size: 16px;
    }
}
```

In `public/widget.js`, keep desktop sizing and add this rule to the injected CSS string:

```css
@media(max-width:640px){#chatme-input{font-size:16px}#chatme-window{height:min(520px,calc(100dvh - 100px))}}
```

- [ ] **Step 4: Run targeted tests and build the production CSS**

Run:

```powershell
php artisan test --filter=test_mobile_viewport_is_locked_and_form_controls_do_not_trigger_auto_zoom
npm test
npm run build
```

Expected: tests pass, Vite exits 0 and `public/css/app.css` contains the generated mobile rule.

- [ ] **Step 5: Commit the mobile lock**

```powershell
git add resources/views/layouts/app.blade.php resources/views/layouts/guest.blade.php resources/css/app.css public/css/app.css public/widget.js tests/Feature/LightThemeTest.php tests/js/widget-security.test.js
git commit -m "fix: lock mobile viewport and prevent input zoom"
```

---

### Task 2: Add Shared Global Toast Notifications

**Files:**
- Create: `resources/views/partials/toasts.blade.php`
- Modify: `resources/views/layouts/app.blade.php:118-134,297-324`
- Modify: `resources/views/layouts/guest.blade.php`
- Modify: `resources/css/app.css:274-298`
- Modify: `tests/Feature/LightThemeTest.php`

**Interfaces:**
- Produces: `window.showToast(message: string, type: 'success'|'error'|'info'): HTMLElement|null`.
- Consumes: Laravel session keys `success`, `error`, `info` and the shared validation error bag.
- Produces: `#toast-container` once per rendered page and a safely serialized `#initial-notifications` JSON payload.

- [ ] **Step 1: Write failing rendered-notification tests**

Add to `LightThemeTest`:

```php
public function test_both_layouts_load_the_shared_popup_notification_system(): void
{
    foreach (['guest', 'app'] as $layout) {
        $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
        $this->assertStringContainsString("@include('partials.toasts')", $source);
    }

    $partial = file_get_contents(resource_path('views/partials/toasts.blade.php'));
    $this->assertStringContainsString('id="initial-notifications"', $partial);
    $this->assertStringContainsString('window.showToast', $partial);
    $this->assertStringContainsString("toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status')", $partial);
    $this->assertStringContainsString('aria-label="Tutup notifikasi"', $partial);
}

public function test_session_and_validation_feedback_render_as_popup_data(): void
{
    $this->withSession(['success' => 'Chatbot berjaya dikemas kini.'])
        ->get('/login')->assertOk()
        ->assertSee('Chatbot berjaya dikemas kini.', false)
        ->assertSee('id="initial-notifications"', false);

    $this->withViewErrors(['email' => 'Alamat e-mel diperlukan.'])
        ->get('/login')->assertOk()
        ->assertSee('Sila semak medan yang bertanda sebelum meneruskan.', false);
}
```

- [ ] **Step 2: Verify the toast tests fail**

Run:

```powershell
php artisan test --filter='test_both_layouts_load_the_shared_popup_notification_system|test_session_and_validation_feedback_render_as_popup_data'
```

Expected: FAIL because the shared partial and popup data do not exist.

- [ ] **Step 3: Create the shared toast partial**

Create `resources/views/partials/toasts.blade.php` with safely assembled initial data:

```blade
@php
    $initialNotifications = collect([
        session('success') ? ['message' => session('success'), 'type' => 'success'] : null,
        session('error') ? ['message' => session('error'), 'type' => 'error'] : null,
        session('info') ? ['message' => session('info'), 'type' => 'info'] : null,
        $errors->any() ? ['message' => 'Sila semak medan yang bertanda sebelum meneruskan.', 'type' => 'error'] : null,
    ])->filter()->values()->all();
@endphp
<div id="toast-container" aria-live="polite" aria-atomic="false"></div>
<template id="toast-template">
    <div class="toast" data-toast>
        <i class="toast-icon ph" aria-hidden="true"></i>
        <span class="toast-message"></span>
        <button type="button" class="toast-close" aria-label="Tutup notifikasi">&times;</button>
    </div>
</template>
<script id="initial-notifications" type="application/json">{!! json_encode($initialNotifications, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
(() => {
    const container = document.getElementById('toast-container');
    const template = document.getElementById('toast-template');
    const durations = { success: 4000, info: 5000, error: 7000 };
    const icons = { success: 'ph-check-circle', info: 'ph-info', error: 'ph-x-circle' };

    window.showToast = (message, type = 'success') => {
        const text = String(message || '').trim();
        const normalizedType = ['success', 'error', 'info'].includes(type) ? type : 'info';
        if (!text || !container || !template) return null;
        const duplicate = [...container.querySelectorAll('[data-toast]')]
            .find((toast) => toast.dataset.message === text && toast.dataset.type === normalizedType);
        if (duplicate) return duplicate;

        const toast = template.content.firstElementChild.cloneNode(true);
        toast.classList.add(`toast-${normalizedType}`);
        toast.dataset.message = text;
        toast.dataset.type = normalizedType;
        toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
        toast.querySelector('.toast-icon').classList.add(icons[normalizedType]);
        toast.querySelector('.toast-message').textContent = text;
        const remove = () => toast.remove();
        let timer = window.setTimeout(remove, durations[normalizedType]);
        const pause = () => window.clearTimeout(timer);
        const resume = () => { timer = window.setTimeout(remove, durations[normalizedType]); };
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', resume);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', resume);
        toast.querySelector('.toast-close').addEventListener('click', remove);
        container.appendChild(toast);
        return toast;
    };

    JSON.parse(document.getElementById('initial-notifications')?.textContent || '[]')
        .forEach(({message, type}) => window.showToast(message, type));
})();
</script>
```

- [ ] **Step 4: Include the partial and remove legacy flash markup**

Include `@include('partials.toasts')` once near the end of both layouts. Remove `.flash-region`, its three server-message blocks, the duplicate `#toast-container`, the legacy `.flash-close` listener and the old `window.showToast` definition from `app.blade.php`.

Update CSS so the container is a responsive fixed stack and each toast has icon, message and close button. Retain `.flash` CSS only if another source still uses it; otherwise remove it.

- [ ] **Step 5: Run tests, build and commit**

```powershell
php artisan test --filter='LightThemeTest'
npm run build
git diff --check
git add resources/views/partials/toasts.blade.php resources/views/layouts/app.blade.php resources/views/layouts/guest.blade.php resources/css/app.css public/css/app.css tests/Feature/LightThemeTest.php
git commit -m "feat: add global popup notifications"
```

Expected: LightThemeTest passes, build exits 0 and the worktree contains only later-task changes.

---

### Task 3: Add Declarative Confirmation Dialogs and Dashboard Delete

**Files:**
- Modify: `tests/Feature/ManagementFormAccessibilityTest.php`
- Modify: `resources/views/layouts/app.blade.php:147-155,244-315`
- Modify: `resources/views/dashboard.blade.php:62-76`
- Modify: `resources/views/chatbots/index.blade.php:39-57`
- Modify: `resources/views/knowledge/index.blade.php:55-67`
- Modify: `resources/views/admin/users.blade.php:27-36`
- Modify: `resources/views/chatbots/embed.blade.php`

**Interfaces:**
- Consumes: form attributes `data-confirm-title`, `data-confirm-description`, `data-confirm-text`, and `data-confirm-type`.
- Produces: a delegated submit handler that cancels the first submit, opens `#confirm-modal`, then submits the same form exactly once after approval.
- Produces: all delete chatbot surfaces use `route('chatbots.destroy', $bot)` with CSRF and `DELETE`.

- [ ] **Step 1: Write failing dashboard-delete and confirmation-contract tests**

Extend `test_management_table_actions_use_named_icons()` to assert the dashboard contains:

```php
->assertSee('aria-label="Padam chatbot Chatbot Ikon"', false)
->assertSee('data-confirm-title="Padam chatbot?"', false)
->assertSee('data-confirm-text="Padam chatbot"', false)
->assertSee('class="ph ph-trash"', false);
```

Replace the native-confirm expectation with:

```php
public function test_risky_management_actions_use_the_shared_confirmation_contract(): void
{
    $sources = implode("\n", array_map('file_get_contents', [
        resource_path('views/dashboard.blade.php'),
        resource_path('views/chatbots/index.blade.php'),
        resource_path('views/knowledge/index.blade.php'),
        resource_path('views/admin/users.blade.php'),
        resource_path('views/chatbots/embed.blade.php'),
    ]));

    $this->assertStringNotContainsString('window.confirm(', $sources);
    $this->assertStringNotContainsString('onsubmit="return confirm(', $sources);
    $this->assertGreaterThanOrEqual(5, substr_count($sources, 'data-confirm-title='));

    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $this->assertStringContainsString("document.addEventListener('submit'", $layout);
    $this->assertStringContainsString("form.matches('form[data-confirm-title]')", $layout);
    $this->assertStringContainsString("form.dataset.confirmed = 'true'", $layout);
    $this->assertStringContainsString('form.requestSubmit()', $layout);
}
```

- [ ] **Step 2: Run the management tests and confirm failure**

```powershell
php artisan test --filter='test_management_table_actions_use_named_icons|test_risky_management_actions_use_the_shared_confirmation_contract'
```

Expected: FAIL because the dashboard has no delete form and risky forms still use native confirmation.

- [ ] **Step 3: Add the dashboard delete form**

Append this form to the dashboard action group:

```blade
<form action="{{ route('chatbots.destroy', $bot) }}" method="POST" class="inline-flex"
      data-confirm-title="Padam chatbot?"
      data-confirm-description="Padam chatbot {{ $bot->name }}? Semua soal jawab dan sejarah sembang berkaitan akan dipadam. Tindakan ini tidak boleh dibatalkan."
      data-confirm-text="Padam chatbot"
      data-confirm-type="danger">
    @csrf
    @method('DELETE')
    <button type="submit" class="table-action table-action-danger" aria-label="Padam chatbot {{ $bot->name }}" title="Padam chatbot">
        <i class="ph ph-trash" aria-hidden="true"></i>
    </button>
</form>
```

- [ ] **Step 4: Implement delegated modal confirmation**

Update the existing `window.sahkan` controller so it accepts an `onConfirm` callback, disables the confirmation button immediately, closes safely and restores the button state for the next action.

Add the delegated handler:

```js
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm-title]')) return;
    if (form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
    }

    event.preventDefault();
    window.sahkan({
        title: form.dataset.confirmTitle,
        desc: form.dataset.confirmDescription,
        confirmText: form.dataset.confirmText,
        type: form.dataset.confirmType,
        onConfirm: () => {
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        },
    });
});
```

Keep the existing focus trap, Escape handling and backdrop close behavior. Focus the cancel button first rather than the destructive button.

- [ ] **Step 5: Migrate all risky forms**

Remove native `onsubmit` handlers and the page-specific `form[data-confirm]` listener. Add the four `data-confirm-*` fields to chatbot deletion, knowledge deletion, API-key regeneration and administrator role-change forms. Use safely escaped Blade attributes and item-specific Bahasa Melayu descriptions.

- [ ] **Step 6: Run management and authorization tests, then commit**

```powershell
php artisan test --filter='ManagementFormAccessibilityTest|ChatbotAuthorizationTest'
git diff --check
git add resources/views/layouts/app.blade.php resources/views/dashboard.blade.php resources/views/chatbots/index.blade.php resources/views/knowledge/index.blade.php resources/views/admin/users.blade.php resources/views/chatbots/embed.blade.php tests/Feature/ManagementFormAccessibilityTest.php
git commit -m "feat: add shared action confirmations"
```

Expected: tests pass and no `window.confirm()` remains in application views.

---

### Task 4: Route Client-Side Feedback Through Toasts

**Files:**
- Modify: `resources/views/chatbots/embed.blade.php:51-62`
- Modify: `tests/Feature/ManagementFormAccessibilityTest.php`
- Modify: `tests/Feature/LightThemeTest.php`

**Interfaces:**
- Consumes: `window.showToast(message, type)` from Task 2.
- Produces: copy success and failure feedback through the global popup while preserving the existing screen-reader feedback region.

- [ ] **Step 1: Write the failing client-feedback assertion**

Add:

```php
public function test_embed_copy_feedback_uses_global_popup_notifications(): void
{
    $source = file_get_contents(resource_path('views/chatbots/embed.blade.php'));
    $this->assertStringContainsString("window.showToast('Teks berjaya disalin.', 'success')", $source);
    $this->assertStringContainsString("window.showToast('Teks tidak dapat disalin. Sila salin secara manual.', 'error')", $source);
}
```

- [ ] **Step 2: Run and verify failure**

```powershell
php artisan test --filter=test_embed_copy_feedback_uses_global_popup_notifications
```

Expected: FAIL because feedback only updates inline text.

- [ ] **Step 3: Add toast calls without removing accessible inline status**

On copy success:

```js
feedback.textContent = 'Teks berjaya disalin.';
window.showToast('Teks berjaya disalin.', 'success');
```

On failure:

```js
feedback.textContent = 'Teks tidak dapat disalin. Sila salin secara manual.';
window.showToast('Teks tidak dapat disalin. Sila salin secara manual.', 'error');
```

- [ ] **Step 4: Run targeted tests and commit**

```powershell
php artisan test --filter='test_embed_copy_feedback_uses_global_popup_notifications|ManagementFormAccessibilityTest'
git add resources/views/chatbots/embed.blade.php tests/Feature/ManagementFormAccessibilityTest.php
git commit -m "feat: show client feedback as popup notifications"
```

---

### Task 5: Full Verification, Browser QA and Production Deployment

**Files:**
- Verify all changed files
- No new production behavior beyond Tasks 1-4

**Interfaces:**
- Verifies: local HEAD, GitHub `main`, production HEAD and production `origin/main` are identical.
- Verifies: production homepage and authenticated app surfaces load the new hashed assets and popup contracts.

- [ ] **Step 1: Run the complete automated verification suite**

```powershell
php artisan test
npm test
vendor\bin\pint --test
npm run build
git diff --check
npm audit --audit-level=high
composer validate --strict
composer audit --no-interaction
```

Expected: all commands exit 0, PHP has zero failures, Node has zero failures and both audits report no advisories at their configured threshold.

- [ ] **Step 2: Inspect the final diff and worktree**

```powershell
git status --short
git diff --stat origin/main...HEAD
git log --oneline --decorate -8
```

Expected: only intentional commits are ahead of production and the worktree is clean.

- [ ] **Step 3: Run local/browser interaction QA**

Using the in-app Browser, verify this exact flow at desktop, 390x844 and 320x900:

1. Homepage loads with no framework overlay or console warning/error.
2. Chatbot opens, input computed font size is 16px on mobile and the widget stays within viewport.
3. Authenticated dashboard delete icon is visible and uniquely labelled.
4. Clicking delete opens the ChatMe dialog without submitting.
5. `Batal`, backdrop and `Escape` each close without deletion and restore focus.
6. Confirmation submits once against disposable local test data and produces a success toast.
7. Toast close, automatic timeout, error styling and responsive stacking work.
8. Browser DOM confirms the viewport meta includes `user-scalable=no` and `maximum-scale=1`.

Capture desktop and mobile screenshots outside the repository.

- [ ] **Step 4: Push the verified fast-forward commit to GitHub main**

Because this PC's global SSH rewrite points at a missing key, use the proven HTTPS bypass:

```powershell
$env:GIT_CONFIG_GLOBAL='NUL'
git push https://github.com/akmal4244/ChatMe.git HEAD:main
git ls-remote https://github.com/akmal4244/ChatMe.git refs/heads/main
```

Expected: the reported remote SHA equals local HEAD.

- [ ] **Step 5: Deploy to production**

Use the cPanel SSH credentials from the supplied hosting attachment without printing the password. On `/home2/akmalmar/public_html/chatme.akmalmarvis.com`:

```bash
test -z "$(git status --porcelain)"
php artisan down --render="errors::503" --retry=30
git fetch origin main
git merge --ff-only origin/main
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up
```

Always run `php artisan up` in cleanup if a deployment command fails after maintenance mode begins.

- [ ] **Step 6: Verify production state and live behavior**

Check:

```bash
git rev-parse HEAD
git rev-parse origin/main
git status --porcelain
php artisan migrate:status
```

Then repeat the public mobile Browser checks on `https://chatme.akmalmarvis.com/`, confirm the widget asset returns HTTP 200, the viewport is locked, input focus does not change scale and console logs remain clean.

- [ ] **Step 7: Preserve the worktree and report evidence**

Keep the user-requested worktree and feature branch in place. Report commit SHA, test totals, viewport measurements, screenshots, GitHub/production parity and any browser limitation without claiming unverified behavior.
