# ChatMe Account Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete ChatMe password recovery, e-mail verification, profile/password management, safe session expiry UX, account-menu focus behavior, and removal of the unused broken Sanctum route without locking existing production users.

**Architecture:** Keep Laravel's official password broker, `MustVerifyEmail` contract, signed verification request, notifications, validation rules, and middleware as the security boundary. Split reset, verification, and profile responsibilities into small controllers and requests; use a forward-only data migration for pre-rollout users and a small service for database-session revocation. Preserve the current Blade/CSP/toast architecture while adding a server-issued session deadline header and a persistent top-toast countdown.

**Tech Stack:** PHP 8.2+, Laravel 12, Eloquent, Laravel notifications/password broker/signed URLs/rate limiting, Blade, vanilla JavaScript, Vite CSS, PHPUnit 11, Laravel Pint.

## Global Constraints

- Primary language is Bahasa Melayu Malaysia; use “anda”, “tidak”, “e-mel”, “papan pemuka”, “gambar profil”, “pelan langganan”, “pangkalan pengetahuan”, and “mesej bulanan”.
- Keep technical terms ChatMe, API, JSON, HTML, ToyyibPay, FPX, DuitNow QR unchanged.
- Preserve the explicit mobile viewport lock and keep all mobile form controls at a computed minimum of 16px.
- Use Laravel 12 contracts, password broker, notifications, signed URLs, and middleware; do not invent account tokens or signatures.
- Existing users must remain verified after rollout through a forward-only migration whose `down()` method does not clear `email_verified_at`.
- Never log a full e-mail address, reset token, verification URL, password, session token, or stack trace.
- Do not touch production, payment code, backup scripts, backup documentation, or real customer data.
- Do not commit; end each task with a diff/test checkpoint only.
- Follow strict RED → GREEN → REFACTOR: every behavior-changing production edit must be preceded by a focused test observed failing for the expected reason.

---

## File Structure

- `app/Http/Controllers/Auth/PasswordResetLinkController.php`: render and submit neutral reset-link requests.
- `app/Http/Controllers/Auth/NewPasswordController.php`: render token form and perform broker-backed resets.
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php`: show instructions to unverified users.
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`: resend verification with safe failure handling.
- `app/Http/Controllers/Auth/VerifyEmailController.php`: fulfill Laravel's signed `EmailVerificationRequest`.
- `app/Http/Controllers/ProfileController.php`: render profile, update current user, resend after e-mail change, and update password.
- `app/Http/Requests/ProfileUpdateRequest.php`: normalize and validate only the current user's profile fields.
- `app/Http/Requests/PasswordUpdateRequest.php`: enforce current password, confirmation, and the registration password policy.
- `app/Http/Middleware/AddSessionExpiryHeader.php`: attach a fresh authenticated idle deadline to valid network responses.
- `app/Services/AccountSessionService.php`: revoke other database-backed sessions without touching the current session.
- `app/Support/AccountNotificationFailureLogger.php`: emit structured, redacted delivery-failure logs.
- `resources/views/auth/forgot-password.blade.php`, `reset-password.blade.php`, `verify-email.blade.php`: accessible BM account forms/states.
- `resources/views/profile/edit.blade.php`: profile and password forms for verified or unverified authenticated users.
- `database/migrations/2026_07_12_000001_mark_existing_users_as_verified.php`: one-way rollout compatibility update.
- `tests/Feature/PasswordResetFlowTest.php`, `EmailVerificationFlowTest.php`, `ProfileManagementTest.php`, `AccountSessionUxTest.php`, `AccountRouteProtectionTest.php`: behavior-level regressions.
- `tests/Unit/AccountSessionServiceTest.php`: database-session revocation boundary.

---

### Task 1: Password Reset and Localized Account Notifications

**Files:**
- Create: `tests/Feature/PasswordResetFlowTest.php`
- Create: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `app/Support/AccountNotificationFailureLogger.php`
- Create: `resources/views/auth/forgot-password.blade.php`
- Create: `resources/views/auth/reset-password.blade.php`
- Modify: `app/Models/User.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `lang/ms/validation.php`

**Interfaces:**
- Consumes: `Password::sendResetLink(array{email:string}): string` and `Password::reset(array, Closure): string`.
- Produces: named routes `password.request`, `password.email`, `password.reset`, and `password.update`.
- Produces: `AccountNotificationFailureLogger::report(string $event, string $email, Throwable $exception): void`.
- Produces: `User::sendPasswordResetNotification($token)` using Laravel's `ResetPassword` notification with locale `ms`.

- [ ] **Step 1: Write the reset-flow tests before creating routes or controllers**

Create `PasswordResetFlowTest` with `RefreshDatabase` and these concrete cases:

```php
public function test_known_account_receives_a_malay_reset_notification(): void
{
    Notification::fake();
    $user = User::factory()->create(['email' => 'pemilik@example.test']);

    $this->post(route('password.email'), ['email' => ' PEMILIK@example.test '])
        ->assertRedirect()
        ->assertSessionHas('success', 'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.');

    Notification::assertSentTo($user, ResetPassword::class, fn (ResetPassword $notification): bool => $notification->locale === 'ms');
}

public function test_unknown_account_gets_the_same_neutral_response(): void
{
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'tiada@example.test'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Jika akaun dengan e-mel tersebut wujud, pautan penetapan semula kata laluan akan dihantar.');

    Notification::assertNothingSent();
}

public function test_valid_token_resets_password_and_rotates_remember_token(): void
{
    $user = User::factory()->create(['password' => 'PasswordLama123!', 'remember_token' => 'token-lama']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'PasswordBaharu123!',
        'password_confirmation' => 'PasswordBaharu123!',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Kata laluan anda berjaya ditetapkan semula. Sila log masuk.');

    $user->refresh();
    $this->assertTrue(Hash::check('PasswordBaharu123!', $user->password));
    $this->assertNotSame('token-lama', $user->remember_token);
}

public function test_invalid_and_expired_tokens_are_rejected_with_the_same_malay_error(): void
{
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $this->travel(61)->minutes();

    foreach (['token-salah', $token] as $candidate) {
        $this->post(route('password.update'), [
            'token' => $candidate,
            'email' => $user->email,
            'password' => 'PasswordBaharu123!',
            'password_confirmation' => 'PasswordBaharu123!',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);
    }
}
```

Also assert the forgot/reset forms have programmatic labels, `autocomplete="email"` / `autocomplete="new-password"`, linked validation errors, and the login page contains `route('password.request')`.

- [ ] **Step 2: Run the reset tests and record RED**

Run:

```powershell
php artisan test tests/Feature/PasswordResetFlowTest.php --compact
```

Expected: FAIL because `password.email`, `password.reset`, and `password.update` do not exist.

- [ ] **Step 3: Add the redacted delivery-failure logger and BM notification builders**

Create the logger exactly around redacted context:

```php
final class AccountNotificationFailureLogger
{
    public function report(string $event, string $email, Throwable $exception): void
    {
        Log::error($event, [
            'email_hash' => hash('sha256', Str::lower(trim($email))),
            'exception_type' => $exception::class,
        ]);
    }
}
```

In `User`, override the official reset notification method:

```php
public function sendPasswordResetNotification($token): void
{
    $this->notify((new ResetPassword($token))->locale('ms'));
}
```

In `AppServiceProvider::boot()`, configure `ResetPassword::createUrlUsing()` and `toMailUsing()` so the action targets `password.reset` with token/e-mail and the subject, lines, action label, and expiry text are entirely BM. Do not enable the verification contract or verification mail until Task 2's failing tests exist.

- [ ] **Step 4: Implement the neutral request and broker-backed reset controllers**

`PasswordResetLinkController::store()` must normalize the e-mail, validate it, call the broker, catch delivery exceptions through `AccountNotificationFailureLogger`, and return the same success string for existing and absent accounts. A delivery exception returns only `E-mel tidak dapat dihantar sekarang. Sila cuba semula sebentar lagi.` and never includes provider details.

`NewPasswordController::store()` must validate `token`, `email`, `password`, confirmation, and `Password::defaults()`, then call the broker with this reset callback:

```php
function (User $user, string $password): void {
    $user->forceFill([
        'password' => Hash::make($password),
        'remember_token' => Str::random(60),
    ])->save();

    event(new PasswordReset($user));
}
```

Map every non-success broker status to `__('passwords.token')` to avoid leaking account state.

- [ ] **Step 5: Add guest routes, accessible BM views, and login affordance**

Within the existing `guest` group add:

```php
Route::get('/lupa-kata-laluan', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/lupa-kata-laluan', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:password-reset')->name('password.email');
Route::get('/tetap-semula-kata-laluan/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/tetap-semula-kata-laluan', [NewPasswordController::class, 'store'])
    ->middleware('throttle:password-reset')->name('password.update');
```

Each form uses the guest layout, an explicit heading, labels, old input, linked errors, CSRF, appropriate autocomplete, and BM buttons. Add `Lupa kata laluan?` beside the login password field. Add validation attributes for `token` and `current_password`.

- [ ] **Step 6: Add a named password-reset limiter and verify GREEN**

Register `password-reset` in `AppServiceProvider` as `Limit::perMinute(5)` keyed by normalized e-mail hash and IP, with a BM form response that preserves only e-mail input. Run:

```powershell
php artisan test tests/Feature/PasswordResetFlowTest.php tests/Feature/AuthenticationHardeningTest.php tests/Feature/MalayLocaleTest.php --compact
```

Expected: all reset cases and existing authentication hardening tests pass.

- [ ] **Step 7: Review without committing**

```powershell
git diff --check
git diff -- app/Http/Controllers/Auth app/Models/User.php app/Providers/AppServiceProvider.php app/Support routes/web.php resources/views/auth lang/ms tests/Feature/PasswordResetFlowTest.php
```

Expected: no whitespace errors, no secrets, and only Task 1 account changes.

---

### Task 2: E-mail Verification, Forward-Only Rollout, and Verified Route Topology

**Files:**
- Create: `tests/Feature/EmailVerificationFlowTest.php`
- Create: `tests/Feature/AccountLifecycleMigrationTest.php`
- Create: `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- Create: `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- Create: `app/Http/Controllers/Auth/VerifyEmailController.php`
- Create: `resources/views/auth/verify-email.blade.php`
- Create: `database/migrations/2026_07_12_000001_mark_existing_users_as_verified.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Models/User.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `database/seeders/AdminSeeder.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/AuthenticationHardeningTest.php`
- Modify: `tests/Feature/AdminSeederSecurityTest.php`

**Interfaces:**
- Produces: `verification.notice`, `verification.send`, and signed `verification.verify` routes.
- Consumes: framework `EmailVerificationRequest::fulfill()` authorization for current ID/e-mail hash.
- Produces: an outer `auth` group containing logout/verification and an inner `verified` group containing SaaS routes; Task 4 later layers `session.deadline` onto both authenticated groups.
- Produces: migration `up()` that stamps only pre-existing null `email_verified_at`; `down()` is intentionally a no-op.

- [ ] **Step 1: Write registration, signed-link, resend, gating, and migration tests**

`EmailVerificationFlowTest` must include:

```php
public function test_registration_creates_an_unverified_user_sends_malay_mail_and_regenerates_session(): void
{
    Notification::fake();
    Session::start();
    $before = Session::getId();

    $this->post('/register', [
        'name' => 'Pengguna Baharu',
        'email' => 'baharu@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'baharu@example.test')->firstOrFail();
    $this->assertFalse($user->hasVerifiedEmail());
    $this->assertAuthenticatedAs($user);
    $this->assertNotSame($before, Session::getId());
    Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification): bool => $notification->locale === 'ms');
}

public function test_unverified_user_is_redirected_from_dashboard_but_can_open_verification_and_logout(): void
{
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->get(route('verification.notice'))->assertOk();
    $this->post(route('logout'))->assertRedirect(route('landing'));
}

public function test_valid_signed_link_verifies_only_the_current_user(): void
{
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
    $this->assertTrue($user->fresh()->hasVerifiedEmail());
}
```

Add separate assertions that wrong hash, another user's ID, and an expired signed URL return 403 without verification; resend sends a BM notification and the seventh request is throttled; dashboard/chatbot/knowledge/subscription/admin routes contain `verified`, while logout/verification/profile do not.

`AccountLifecycleMigrationTest` creates an unverified user after test migrations, requires the new migration instance, calls `up()`, asserts it becomes verified, calls `down()`, and asserts the timestamp remains.

- [ ] **Step 2: Run verification tests and record RED**

```powershell
php artisan test tests/Feature/EmailVerificationFlowTest.php tests/Feature/AccountLifecycleMigrationTest.php --compact
```

Expected: FAIL because verification routes/controllers/middleware and the rollout migration do not exist.

- [ ] **Step 3: Implement the forward-only migration and trusted admin state**

Create:

```php
return new class extends Migration {
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Forward-only: clearing verification can lock existing accounts.
    }
};
```

Update `AdminSeeder` so the explicitly configured trusted administrator is created with `email_verified_at => now()`, and extend its feature test to assert `hasVerifiedEmail()`.

- [ ] **Step 4: Dispatch registration and implement verification controllers**

After `User::create()`, dispatch `Registered`, catch only delivery exceptions to a safe session error/redacted log, authenticate, regenerate the session, and redirect to `verification.notice`.

`VerifyEmailController::__invoke(EmailVerificationRequest $request)` returns dashboard immediately when already verified, otherwise calls `$request->fulfill()` and redirects with `E-mel anda berjaya disahkan.`.

`EmailVerificationNotificationController::store(Request $request)` returns dashboard for verified users; otherwise calls `$request->user()->sendEmailVerificationNotification()`, catches delivery failures through the redacted logger, and returns a general success popup.

- [ ] **Step 5: Add routes and verified topology**

Structure `routes/web.php` as:

```php
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/sahkan-e-mel', EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('/sahkan-e-mel/hantar', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification')->name('verification.send');
    Route::get('/sahkan-e-mel/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])->name('verification.verify');

    Route::middleware('verified')->group(function () {
        // Existing dashboard, onboarding, chatbot, knowledge, and subscription routes.
    });
});
```

Keep admin routes outside that closure with `['auth', 'verified', 'admin']`. Task 4 adds `session.deadline` only after its middleware has its own failing tests.

- [ ] **Step 6: Add the accessible verification notice and resend limiter**

The app-layout notice must explain that access is paused until verification, show the masked destination only as escaped current-user text, offer POST resend, profile, and logout actions, and render generic success/error through the shared toast partial.

Register `verification` as `Limit::perMinute(6)` keyed by authenticated user ID plus IP with BM error `Terlalu banyak permintaan pengesahan. Sila cuba semula dalam satu minit.`.

- [ ] **Step 7: Verify GREEN and review without committing**

```powershell
php artisan test tests/Feature/EmailVerificationFlowTest.php tests/Feature/AccountLifecycleMigrationTest.php tests/Feature/AuthenticationHardeningTest.php tests/Feature/AdminSeederSecurityTest.php --compact
git diff --check
```

Expected: new account, legacy migration, signed-link, throttle, and route-gating tests pass with zero failures.

---

### Task 3: Current-User Profile, Re-verification, Password Change, and Other-Session Revocation

**Files:**
- Create: `tests/Feature/ProfileManagementTest.php`
- Create: `tests/Unit/AccountSessionServiceTest.php`
- Create: `app/Http/Requests/ProfileUpdateRequest.php`
- Create: `app/Http/Requests/PasswordUpdateRequest.php`
- Create: `app/Http/Controllers/ProfileController.php`
- Create: `app/Services/AccountSessionService.php`
- Create: `resources/views/profile/edit.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `lang/ms/validation.php`

**Interfaces:**
- Produces: `profile.edit`, `profile.update`, and `profile.password.update` under `auth` but not `verified`; Task 4 later adds `session.deadline`.
- Consumes: only `$request->user()`; accepts no user ID route/input.
- Produces: `AccountSessionService::revokeOtherDatabaseSessions(User $user, string $currentSessionId): int`.

- [ ] **Step 1: Write profile and password tests**

Cover these exact behaviors:

```php
public function test_profile_update_changes_only_the_authenticated_user(): void
{
    Notification::fake();
    $user = User::factory()->create(['email' => 'asal@example.test']);
    $other = User::factory()->create(['email' => 'lain@example.test']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Nama Baharu',
        'email' => 'baharu@example.test',
        'company' => 'Syarikat Baharu',
        'website' => 'https://example.test',
        'user_id' => $other->id,
    ])->assertRedirect(route('profile.edit'));

    $user->refresh();
    $this->assertSame('Nama Baharu', $user->name);
    $this->assertNull($user->email_verified_at);
    $this->assertSame('lain@example.test', $other->fresh()->email);
    Notification::assertSentTo($user, VerifyEmail::class);
}
```

Also test duplicate e-mail rejection, unchanged e-mail preserving verification, invalid/non-HTTP website rejection, unverified-user profile access, e-mail change blocking dashboard, wrong current password rejection, confirmed password update, remember-token rotation, current-session ID regeneration, and authenticated state preservation.

`AccountSessionServiceTest` inserts two `sessions` rows for the user and one for another user, configures the database session driver/table, invokes the service with one current ID, and asserts only the same user's other row is deleted. A second test configures `array` and asserts no database row is changed.

- [ ] **Step 2: Run profile tests and record RED**

```powershell
php artisan test tests/Feature/ProfileManagementTest.php tests/Unit/AccountSessionServiceTest.php --compact
```

Expected: FAIL because profile routes, request classes, view, and account-session service do not exist.

- [ ] **Step 3: Implement narrowly scoped request validation**

`ProfileUpdateRequest` normalizes trimmed/lowercase e-mail and validates:

```php
return [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->getKey())],
    'company' => ['nullable', 'string', 'max:255'],
    'website' => ['nullable', 'url:http,https', 'max:255'],
];
```

`PasswordUpdateRequest` authorizes authenticated users and validates `current_password` with `current_password:web`, then a confirmed `Password::defaults()` password. Add BM attributes `kata laluan semasa` and `kata laluan baharu`.

- [ ] **Step 4: Implement profile update and re-verification**

`ProfileController::update()` fills only validated fields on `$request->user()`. If `isDirty('email')`, set `email_verified_at` to null before save, then send the official verification notification. A delivery exception preserves the profile update but returns a safe error toast and redacted log. An unchanged e-mail returns `Profil anda berjaya dikemas kini.` without sending mail.

- [ ] **Step 5: Implement password/session security**

`AccountSessionService` deletes other sessions only when `config('session.driver') === 'database'`:

```php
return DB::table(config('session.table', 'sessions'))
    ->where('user_id', $user->getKey())
    ->where('id', '!=', $currentSessionId)
    ->delete();
```

`ProfileController::updatePassword()` captures the current session ID, sets the new hash and a random remember token, saves, calls the service, then calls `$request->session()->regenerate()` and returns `Kata laluan anda berjaya dikemas kini. Sesi lain telah ditamatkan apabila disokong.`.

- [ ] **Step 6: Build the profile UI and account navigation**

Create separate accessible profile and password forms with unique IDs, linked error descriptions, correct autocomplete values (`name`, `email`, `organization`, `url`, `current-password`, `new-password`), clear BM hints, and disabled-state-ready submit buttons. If unverified, show a verification status panel and POST resend button.

Add `Profil akaun` links to the sidebar and account dropdown; highlight `profile.*`. Keep the existing zoom lock and rely on the existing mobile 16px input rule.

- [ ] **Step 7: Verify GREEN and review without committing**

```powershell
php artisan test tests/Feature/ProfileManagementTest.php tests/Unit/AccountSessionServiceTest.php tests/Feature/LightThemeTest.php --compact
git diff --check
```

Expected: all profile ownership, re-verification, password, session, layout, and accessibility assertions pass.

---

### Task 4: Session Deadline/Countdown, Top Toasts, Escape Focus, and `/api/user` Removal

**Files:**
- Create: `tests/Feature/AccountSessionUxTest.php`
- Create: `tests/Feature/AccountRouteProtectionTest.php`
- Create: `app/Http/Middleware/AddSessionExpiryHeader.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/partials/toasts.blade.php`
- Modify: `resources/views/errors/419.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/LightThemeTest.php`
- Modify: `tests/Feature/ManagementFormAccessibilityTest.php`

**Interfaces:**
- Produces: response header `X-Session-Expires-At` containing a Unix timestamp.
- Produces: `window.showToast(message, type, { duration })`, where `duration: 0` keeps the toast until explicitly removed.
- Consumes: `#session-expiry-config` data attributes `expiresAt`, `warningSeconds`, `loginUrl`, and `headerName`.
- Removes: unused `GET /api/user` and its nonexistent `auth:sanctum` guard dependency.

- [ ] **Step 1: Write server/session/UI contract tests**

Freeze time and assert a verified dashboard response has both a header and DOM timestamp exactly `now() + SESSION_LIFETIME`. Assert the layout contains warning threshold `300`, uses `.textContent`, wraps same-origin `fetch` responses to read `X-Session-Expires-At`, and redirects to `login?session_expired=1` at zero.

Assert `/login?session_expired=1` renders the popup text `Sesi anda telah tamat. Sila log masuk semula.` and the 419 page links to that URL. Extend the accessibility test to require Escape calls `closeUserMenu(true)` and that the function restores `userButton.focus()`.

In `AccountRouteProtectionTest`, assert `GET /api/user` returns the localized 404 JSON and no route URI equals `api/user`.

- [ ] **Step 2: Run session/route tests and record RED**

```powershell
php artisan test tests/Feature/AccountSessionUxTest.php tests/Feature/AccountRouteProtectionTest.php --compact
```

Expected: FAIL because the session header/config/countdown do not exist and `/api/user` still invokes an undefined Sanctum guard.

- [ ] **Step 3: Implement the deadline middleware and alias**

`AddSessionExpiryHeader::handle()` must call downstream first, then for an authenticated request add:

```php
$response->headers->set(
    'X-Session-Expires-At',
    (string) now()->addMinutes((int) config('session.lifetime'))->timestamp,
);
```

Alias it as `session.deadline` in `bootstrap/app.php` and use it on all authenticated account/SaaS/admin groups.

- [ ] **Step 4: Add persistent top-toast countdown and safe refresh**

Move desktop `#toast-container` from the bottom to `top: calc(20px + env(safe-area-inset-top))`. Extend `showToast` so a finite positive duration uses a timer while `{ duration: 0 }` creates a persistent, closable toast.

Render escaped session configuration in `app.blade.php` and add an inline nonce script that:

1. stores the server deadline in milliseconds;
2. once remaining time is at most 300 seconds, creates one persistent info toast;
3. updates only `.toast-message.textContent` each second with `Sesi anda akan tamat dalam mm:ss.`;
4. redirects with `window.location.replace(loginUrl)` at zero;
5. wraps `window.fetch` and, only after an `ok` response with a numeric deadline header, resets the deadline and removes the stale warning toast.

Do not alter the mobile viewport tag. Keep the existing 16px mobile input selector and its regression test.

- [ ] **Step 5: Restore account-menu focus and expose expiry copy**

Change `closeUserMenu(restoreFocus = false)` to remember whether it was open, hide it, update `aria-expanded`, then call `userButton?.focus()` only when `restoreFocus && wasOpen`. Document click calls `closeUserMenu(false)`; Escape calls `closeUserMenu(true)`.

Let `AuthController::showLogin(Request $request)` flash `Sesi anda telah tamat. Sila log masuk semula.` when `session_expired=1`. Update the 419 login link to include that query parameter.

- [ ] **Step 6: Remove the unused broken API scaffold**

Delete the `auth:sanctum` `/api/user` route and the now-unused `Request` import from `routes/api.php`. Do not add Sanctum or replace it with a new profile API; the application already uses the dedicated developer-token endpoint and no consumer requires this scaffold.

- [ ] **Step 7: Verify GREEN, build CSS, and review without committing**

```powershell
php artisan test tests/Feature/AccountSessionUxTest.php tests/Feature/AccountRouteProtectionTest.php tests/Feature/LightThemeTest.php tests/Feature/ManagementFormAccessibilityTest.php --compact
npm test
npm run build
git diff --check
```

Expected: session deadline, countdown contract, top toast, focus restoration, 419/login flow, and 404 API route tests pass; Vite regenerates `public/css/app.css`.

---

### Task 5: Full Verification and Local Browser QA

**Files:**
- Verify all Task 1–4 files.
- Do not modify production, payment, backup, or deployment files.

**Interfaces:**
- Verifies: account lifecycle routes, notifications, signed URLs, verified middleware, profile ownership, session handling, BM copy, browser focus/responsive behavior, and absence of the Sanctum scaffold.

- [ ] **Step 1: Run fresh automated quality gates**

```powershell
php artisan test
npm test
vendor\bin\pint --test
npm run build
git diff --check
```

Record exit status, PHP test/assertion totals, Node test totals, Pint output, and build output. Any failure must be reproduced with a focused test before fixing production code.

- [ ] **Step 2: Inspect scope and sensitive output**

```powershell
git status --short
git diff --stat
git diff -- routes app/Http app/Models/User.php app/Providers/AppServiceProvider.php app/Services app/Support resources/views resources/css public/css lang/ms database/migrations database/seeders tests
```

Confirm no payment, backup, production credential, `.env`, or unrelated concurrent file is included. Search changed account code for full e-mail/token logging patterns and verify only `email_hash` plus `exception_type` are logged.

- [ ] **Step 3: Run local Browser QA with disposable data**

The flow under test is: register → verification notice/resend → signed verification → dashboard → profile/e-mail re-verification → password update → session warning/expiry → login.

Use an isolated temporary SQLite database outside the repository and the in-app Browser. Check desktop, tablet, 390px, and 320px when the Browser supports those exact widths; report any effective-width limitation instead of claiming coverage.

For each relevant view verify page identity, meaningful DOM, no framework overlay, console error/warning health, screenshot evidence, no horizontal overflow, programmatic labels, and one target interaction. Specifically prove account-menu Escape restores focus, the persistent countdown appears at five minutes, a valid response refreshes its deadline, expiry redirects with a BM top toast, and mobile inputs compute to at least 16px while zoom remains locked.

- [ ] **Step 4: Perform final plan/spec self-check and report uncommitted results**

Re-read `docs/superpowers/specs/2026-07-12-account-lifecycle-design.md` and map every acceptance requirement to a passing test or Browser observation. Report RED commands/results, GREEN commands/results, exact files changed, remaining risk, and the concurrent untracked backup work separately. Do not commit, deploy, push, or touch production.
