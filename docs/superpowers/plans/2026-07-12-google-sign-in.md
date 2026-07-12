# Google Sign-In Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan Google Sign-In production-grade untuk pengguna biasa ChatMe tanpa melemahkan login tempatan, session, identiti sistem atau tenant isolation.

**Architecture:** Laravel Socialite menjalankan OAuth authorization-code secara stateful. Controller hanya menukar provider response kepada `GoogleIdentity`; `GoogleAccountService` mengawal validation, pemautan dan create dalam transaction. `google_sub` ialah identifier provider unik, credential kekal di environment, dan token Google tidak disimpan.

**Tech Stack:** Laravel 12.63, PHP 8.2, Laravel Socialite 5.28, Blade, Vite, PHPUnit 11, MySQL/MariaDB production, SQLite test.

## Global Constraints

- Bahasa pengguna mesti Bahasa Melayu Malaysia profesional dan menggunakan “anda” serta “tidak”.
- OAuth production callback tepat ialah `https://chatme.akmalmarvis.com/auth/google/callback`.
- Hanya skop `openid email profile`; tiada offline access, access token persistence atau avatar remote.
- Google `sub` menjadi identifier; e-mel hanya untuk pemautan pertama selepas verified.
- Akaun `is_admin`, `system_role` atau e-mel reserved tidak boleh dipaut/login melalui Google.
- Tiada secret/token/raw provider payload dalam Git, log, browser storage atau output command.
- Semua production code mesti didahului ujian yang gagal kerana behavior belum wujud.
- Jangan buat pembayaran ToyyibPay sebenar atau memadam data pelanggan.
- Deployment mesti exact merge SHA selepas backup baru, CI hijau dan MySQL disposable gate lulus.

---

## File Structure

- `app/Services/GoogleAuthConfiguration.php`: satu sumber kebenaran untuk enabled/ready/health status.
- `app/ValueObjects/GoogleIdentity.php`: identiti provider yang telah dinormalisasi dan divalidasi.
- `app/Exceptions/GoogleAuthenticationException.php`: kegagalan domain berkod tanpa data sensitif.
- `app/Support/ReservedAccountEmail.php`: semakan e-mel sistem yang dikongsi registration/profile/Google.
- `app/Services/GoogleAccountService.php`: resolve/link/create pengguna secara atomik.
- `app/Http/Controllers/Auth/GoogleAuthController.php`: redirect dan callback OAuth sahaja.
- `app/Http/Controllers/Auth/AuthenticatedPasswordSetupLinkController.php`: hantar reset link ke e-mel pengguna login sahaja.
- `database/migrations/2026_07_12_130000_add_google_auth_fields_to_users_table.php`: kolum Google dan password nullable, forward-compatible.
- `tests/Unit/GoogleAuthConfigurationTest.php`: flag/config readiness.
- `tests/Feature/GoogleUserModelTest.php`: schema, model, local password behavior.
- `tests/Feature/GoogleAccountServiceTest.php`: validation/link/create/conflict/privileged identity.
- `tests/Feature/GoogleAuthFlowTest.php`: Socialite redirect/callback/session/error/rate-limit.
- `tests/Feature/GoogleAuthUxTest.php`: login/register/profile/mobile/localization/CSP contract.
- `tests/Feature/GooglePasswordSetupTest.php`: authenticated own-email password link.

---

### Task 1: Install Socialite and centralize Google configuration

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `.env.example`
- Modify: `config/services.php`
- Create: `app/Services/GoogleAuthConfiguration.php`
- Test: `tests/Unit/GoogleAuthConfigurationTest.php`

**Interfaces:**
- Produces: `GoogleAuthConfiguration::isEnabled(): bool`, `isReady(): bool`, `status(): string`.
- Later tasks must not duplicate credential completeness logic.

- [ ] **Step 1: Install the official locked dependency**

Run:

```powershell
composer require laravel/socialite:^5.28 --no-interaction
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
```

Expected: Socialite resolves to `5.28.x`, lockfile updates, validation exits 0 and no security advisory is reported.

- [ ] **Step 2: Write the failing configuration test**

Create tests that express all three states:

```php
public function test_google_auth_is_disabled_when_the_feature_flag_is_off(): void
{
    config()->set('services.google.enabled', false);

    $configuration = app(GoogleAuthConfiguration::class);

    $this->assertFalse($configuration->isEnabled());
    $this->assertFalse($configuration->isReady());
    $this->assertSame('disabled', $configuration->status());
}

public function test_enabled_google_auth_requires_every_oauth_value(): void
{
    config()->set('services.google', [
        'enabled' => true,
        'client_id' => 'client-id',
        'client_secret' => null,
        'redirect' => 'https://chatme.test/auth/google/callback',
    ]);

    $configuration = app(GoogleAuthConfiguration::class);

    $this->assertTrue($configuration->isEnabled());
    $this->assertFalse($configuration->isReady());
    $this->assertSame('failed', $configuration->status());
}

public function test_complete_google_auth_configuration_is_ready(): void
{
    config()->set('services.google', [
        'enabled' => true,
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => 'https://chatme.test/auth/google/callback',
    ]);

    $configuration = app(GoogleAuthConfiguration::class);

    $this->assertTrue($configuration->isReady());
    $this->assertSame('ok', $configuration->status());
}
```

- [ ] **Step 3: Run the test and verify RED**

Run:

```powershell
php artisan test tests\Unit\GoogleAuthConfigurationTest.php
```

Expected: FAIL because `GoogleAuthConfiguration` does not exist.

- [ ] **Step 4: Implement minimal configuration behavior**

Add to `config/services.php`:

```php
'google' => [
    'enabled' => filter_var(env('GOOGLE_AUTH_ENABLED', false), FILTER_VALIDATE_BOOL),
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
],
```

Add names only, never values, to `.env.example`:

```dotenv
GOOGLE_AUTH_ENABLED=false
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=/auth/google/callback
```

Implement:

```php
final class GoogleAuthConfiguration
{
    public function isEnabled(): bool
    {
        return (bool) config('services.google.enabled');
    }

    public function isReady(): bool
    {
        return $this->isEnabled()
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function status(): string
    {
        return ! $this->isEnabled() ? 'disabled' : ($this->isReady() ? 'ok' : 'failed');
    }
}
```

- [ ] **Step 5: Verify GREEN and commit**

Run:

```powershell
php artisan test tests\Unit\GoogleAuthConfigurationTest.php
php vendor\bin\pint --test
git diff --check
```

Expected: all exit 0.

Commit:

```powershell
git add composer.json composer.lock .env.example config/services.php app/Services/GoogleAuthConfiguration.php tests/Unit/GoogleAuthConfigurationTest.php
git commit -m "build: add Google OAuth configuration"
```

---

### Task 2: Add Google identity schema and nullable local passwords

**Files:**
- Create: `database/migrations/2026_07_12_130000_add_google_auth_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/GoogleUserModelTest.php`
- Test: `tests/Feature/PasswordResetFlowTest.php`

**Interfaces:**
- Produces: nullable unique `users.google_sub`, nullable `google_linked_at`, `User::hasLocalPassword(): bool`.
- `google_sub` is hidden and never fillable.

- [ ] **Step 1: Write failing schema/model tests**

```php
public function test_google_subject_is_unique_hidden_and_not_mass_assignable(): void
{
    $first = User::factory()->create();
    $first->forceFill(['google_sub' => 'google-sub-1'])->save();

    $this->assertArrayNotHasKey('google_sub', $first->fresh()->toArray());

    $this->expectException(QueryException::class);
    User::factory()->create()->forceFill(['google_sub' => 'google-sub-1'])->save();
}

public function test_google_only_user_has_no_local_password(): void
{
    $user = User::factory()->create(['password' => null]);

    $this->assertFalse($user->hasLocalPassword());
    $this->post('/login', ['email' => $user->email, 'password' => 'anything'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
}

public function test_existing_password_users_remain_local_password_users(): void
{
    $user = User::factory()->create(['password' => 'password']);

    $this->assertTrue($user->hasLocalPassword());
}
```

Extend password reset coverage to prove a null password becomes a valid local password and still rotates sessions/tokens.

- [ ] **Step 2: Run and verify RED**

Run:

```powershell
php artisan test tests\Feature\GoogleUserModelTest.php tests\Feature\PasswordResetFlowTest.php
```

Expected: FAIL because columns/method/password nullability do not exist.

- [ ] **Step 3: Implement the forward-compatible migration and model**

Migration `up()`:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('password')->nullable()->change();
    $table->string('google_sub', 255)->nullable()->unique()->after('email_verified_at');
    $table->timestamp('google_linked_at')->nullable()->after('google_sub');
});
```

Keep `down()` intentionally forward-only so rollback never drops identity links or invents passwords:

```php
public function down(): void
{
    // Forward-only: dropping provider links or fabricating passwords can lock users out.
}
```

Update `User`:

```php
protected $hidden = ['password', 'remember_token', 'google_sub'];

protected function casts(): array
{
    return [
        // existing casts...
        'google_linked_at' => 'datetime',
    ];
}

public function hasLocalPassword(): bool
{
    return filled($this->getRawOriginal('password'));
}
```

Do not add `google_sub` or `google_linked_at` to `$fillable`.

- [ ] **Step 4: Verify GREEN and migration portability**

Run:

```powershell
php artisan test tests\Feature\GoogleUserModelTest.php tests\Feature\PasswordResetFlowTest.php tests\Feature\AuthenticationHardeningTest.php
php artisan migrate:fresh --seed --force
```

Expected: all tests pass; fresh SQLite migration/seed exits 0.

- [ ] **Step 5: Commit**

```powershell
git add database/migrations/2026_07_12_130000_add_google_auth_fields_to_users_table.php app/Models/User.php tests/Feature/GoogleUserModelTest.php tests/Feature/PasswordResetFlowTest.php
git commit -m "feat: add Google identity fields"
```

---

### Task 3: Implement atomic identity validation, linking and creation

**Files:**
- Create: `app/ValueObjects/GoogleIdentity.php`
- Create: `app/Exceptions/GoogleAuthenticationException.php`
- Create: `app/Support/ReservedAccountEmail.php`
- Create: `app/Services/GoogleAccountService.php`
- Modify: `app/Rules/AccountEmailAvailability.php`
- Test: `tests/Feature/GoogleAccountServiceTest.php`
- Test: `tests/Feature/AuthenticationHardeningTest.php`

**Interfaces:**
- Produces: `GoogleIdentity::fromProvider(mixed $subject, mixed $email, mixed $name, mixed $verified): self`.
- Produces: `GoogleAccountService::resolve(GoogleIdentity $identity): User`.
- Produces: `GoogleAuthenticationException::reason(): string` with fixed non-sensitive reason codes.

- [ ] **Step 1: Write failing identity/service tests**

Cover each behavior with a separate named test:

```php
public function test_verified_google_identity_creates_one_verified_google_only_user(): void
{
    $identity = GoogleIdentity::fromProvider('sub-123', ' USER@Example.test ', 'Nama Google', true);

    $user = app(GoogleAccountService::class)->resolve($identity);

    $this->assertSame('user@example.test', $user->email);
    $this->assertSame('sub-123', $user->getRawOriginal('google_sub'));
    $this->assertNotNull($user->email_verified_at);
    $this->assertNull($user->getRawOriginal('password'));
    $this->assertDatabaseCount('users', 1);
}

public function test_verified_email_links_an_existing_ordinary_user_without_changing_profile_or_password(): void
{
    $user = User::factory()->unverified()->create([
        'email' => 'owner@example.test',
        'name' => 'Nama Tempatan',
        'password' => 'local-password',
    ]);

    $resolved = app(GoogleAccountService::class)->resolve(
        GoogleIdentity::fromProvider('sub-link', 'OWNER@example.test', 'Nama Provider', true),
    );

    $this->assertTrue($resolved->is($user));
    $this->assertSame('Nama Tempatan', $resolved->name);
    $this->assertTrue(Hash::check('local-password', (string) $resolved->password));
    $this->assertTrue($resolved->hasVerifiedEmail());
}
```

Also test: subject-first login after provider email changes, unverified/blank/oversized/malformed fields, subject conflict, e-mail conflict, duplicate retry, `is_admin`, every `system_role`, homepage reserved e-mail and configured admin e-mail. Assert no mutation on every rejection.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\GoogleAccountServiceTest.php
```

Expected: FAIL because value object, exception, support and service classes are absent.

- [ ] **Step 3: Implement the minimal immutable identity and fixed exception**

`GoogleIdentity::fromProvider` must:

```php
$subject = trim((string) $subject);
$email = Str::lower(trim((string) $email));
$name = trim((string) $name);

if ($subject === '' || strlen($subject) > 255 || preg_match('/^[\x20-\x7E]+$/', $subject) !== 1) {
    throw GoogleAuthenticationException::invalidIdentity();
}
if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $verified !== true) {
    throw GoogleAuthenticationException::unverifiedEmail();
}
if ($name === '' || mb_strlen($name) > 255) {
    throw GoogleAuthenticationException::invalidIdentity();
}
```

Exception reasons are exactly `invalid_identity`, `unverified_email`, `reserved_identity`, `identity_conflict`.

- [ ] **Step 4: Centralize reserved e-mail checks**

Implement `ReservedAccountEmail::contains(string $email): bool` using normalized comparison against `homepage-bot@chatme.invalid` and `config('chatme.admin.email')`. Replace the duplicated array in `AccountEmailAvailability` and rerun its registration/profile tests.

- [ ] **Step 5: Implement transactional resolution**

The service algorithm must be:

```php
public function resolve(GoogleIdentity $identity): User
{
    try {
        return DB::transaction(fn (): User => $this->resolveLocked($identity), 3);
    } catch (QueryException $exception) {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }

        return DB::transaction(fn (): User => $this->recoverDuplicate($identity), 3);
    }
}
```

`resolveLocked` locks subject first, then normalized e-mail. Before returning/linking it rejects `is_admin`, filled `system_role`, reserved e-mail, and a different existing subject. New users are created with `email_verified_at = now()`, `password = null`, `google_linked_at = now()`. `recoverDuplicate` returns only a row whose e-mail and subject both equal the identity; otherwise it throws `identity_conflict`.

- [ ] **Step 6: Verify GREEN and regression**

```powershell
php artisan test tests\Feature\GoogleAccountServiceTest.php tests\Feature\AuthenticationHardeningTest.php tests\Feature\ProfileManagementTest.php tests\Feature\SystemIdentityProtectionTest.php
php vendor\bin\pint --test
composer analyse
```

Expected: all pass and PHPStan reports 0 errors.

- [ ] **Step 7: Commit**

```powershell
git add app/ValueObjects/GoogleIdentity.php app/Exceptions/GoogleAuthenticationException.php app/Support/ReservedAccountEmail.php app/Services/GoogleAccountService.php app/Rules/AccountEmailAvailability.php tests/Feature/GoogleAccountServiceTest.php tests/Feature/AuthenticationHardeningTest.php
git commit -m "feat: resolve Google accounts atomically"
```

---

### Task 4: Add stateful Socialite redirect/callback, session rotation and rate limits

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleAuthController.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/GoogleAuthFlowTest.php`

**Interfaces:**
- Consumes: `GoogleAuthConfiguration`, `GoogleIdentity`, `GoogleAccountService`.
- Produces routes: `auth.google.redirect` and `auth.google.callback`.

- [ ] **Step 1: Write failing redirect and callback tests with Socialite fake**

```php
public function test_google_redirect_is_stateful_and_uses_only_identity_scopes(): void
{
    $this->readyGoogleConfiguration();
    $response = $this->get(route('auth.google.redirect'))->assertRedirect();
    $target = (string) $response->headers->get('Location');
    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    $this->assertSame('accounts.google.com', parse_url($target, PHP_URL_HOST));
    $this->assertSame('select_account', $query['prompt'] ?? null);
    $this->assertEqualsCanonicalizing(
        ['openid', 'email', 'profile'],
        explode(' ', (string) ($query['scope'] ?? '')),
    );
    $this->assertNotEmpty($query['state'] ?? null);
    $this->assertSame($query['state'], $response->getSession()->get('state'));
}

public function test_successful_google_callback_logs_in_and_regenerates_the_session(): void
{
    $this->readyGoogleConfiguration();
    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'sub-session',
        'name' => 'Pengguna Google',
        'email' => 'google@example.test',
        'verified_email' => true,
    ]));
    Session::start();
    $before = Session::getId();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertNotSame($before, Session::getId());
}
```

Add tests for disabled/incomplete config, access denied, invalid state, provider exception, safe logging, guest-only middleware and 429 BM with no user/session mutation.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\GoogleAuthFlowTest.php
```

Expected: FAIL because routes/controller/limiter do not exist.

- [ ] **Step 3: Implement routes and named limiter**

Inside the existing guest group:

```php
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:google-auth')
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:google-auth')
    ->name('auth.google.callback');
```

The limiter returns a per-minute 30 IP cap for both routes and an additional per-hour 10 IP cap for callback, with popup text `Terlalu banyak percubaan log masuk Google. Sila cuba semula kemudian.`

- [ ] **Step 4: Implement minimal controller behavior**

Redirect:

```php
if (! $this->configuration->isReady()) {
    return redirect()->route('login')
        ->with('error', 'Log masuk Google tidak tersedia buat sementara waktu.');
}

return Socialite::driver('google')
    ->setScopes(['openid', 'email', 'profile'])
    ->with(['prompt' => 'select_account', 'hl' => 'ms'])
    ->redirect();
```

Callback maps `getId()`, `getEmail()`, `getName()` and strict raw `verified_email === true`. It must never call `stateless()`. On success:

```php
Auth::login($user);
$request->session()->regenerate();

return redirect()->intended(route('dashboard'));
```

Catch `InvalidStateException`, domain exception and provider `Throwable` separately, log only a fixed category/exception type/request ID, then redirect to login with a BM popup. Handle `error=access_denied` as cancellation before provider lookup.

- [ ] **Step 5: Verify GREEN and commit**

```powershell
php artisan test tests\Feature\GoogleAuthFlowTest.php tests\Feature\AccountSessionUxTest.php tests\Feature\AuthenticationHardeningTest.php
php vendor\bin\pint --test
composer analyse
git diff --check
```

Commit:

```powershell
git add app/Http/Controllers/Auth/GoogleAuthController.php routes/web.php app/Providers/AppServiceProvider.php tests/Feature/GoogleAuthFlowTest.php
git commit -m "feat: add stateful Google login flow"
```

---

### Task 5: Let Google-only users securely set a local password

**Files:**
- Create: `app/Http/Controllers/Auth/AuthenticatedPasswordSetupLinkController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/profile/edit.blade.php`
- Test: `tests/Feature/GooglePasswordSetupTest.php`

**Interfaces:**
- Produces route: `profile.password.setup-link` as authenticated POST.
- Consumes only `$request->user()->email`; request body cannot choose an address.

- [ ] **Step 1: Write failing own-email and provider-failure tests**

```php
public function test_google_only_user_can_send_a_password_setup_link_only_to_their_own_email(): void
{
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@example.test', 'password' => null]);

    $this->actingAs($user)->post(route('profile.password.setup-link'), [
        'email' => 'attacker@example.test',
    ])->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success');

    Notification::assertSentTo($user, ResetPassword::class);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'attacker@example.test']);
}
```

Add guest rejection, local-password no-op, limiter response, notification exception redaction and profile rendering tests.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\GooglePasswordSetupTest.php
```

Expected: FAIL because controller/route/profile state do not exist.

- [ ] **Step 3: Implement authenticated reset-link delivery**

The invokable controller:

```php
if ($request->user()->hasLocalPassword()) {
    return back()->with('info', 'Akaun anda sudah mempunyai kata laluan tempatan.');
}

$status = Password::sendResetLink(['email' => (string) $request->user()->email]);

return $status === Password::RESET_LINK_SENT
    ? back()->with('success', 'Pautan tetapkan kata laluan telah dihantar ke e-mel anda.')
    : back()->with('error', 'Pautan tidak dapat dihantar. Sila cuba semula.');
```

Catch notification exceptions through `AccountNotificationFailureLogger` without e-mail/raw message leakage. Add route under authenticated session middleware with `throttle:password-reset`.

Render either the existing current-password form or a Google-only explanatory panel/button; never render a fake current-password input for a null-password user.

- [ ] **Step 4: Verify GREEN and commit**

```powershell
php artisan test tests\Feature\GooglePasswordSetupTest.php tests\Feature\ProfileManagementTest.php tests\Feature\PasswordResetFlowTest.php
php vendor\bin\pint --test
```

Commit:

```powershell
git add app/Http/Controllers/Auth/AuthenticatedPasswordSetupLinkController.php routes/web.php resources/views/profile/edit.blade.php tests/Feature/GooglePasswordSetupTest.php
git commit -m "feat: add Google password setup flow"
```

---

### Task 6: Add accessible auth UI, health readiness and operator documentation

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Http/Controllers/HealthController.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/css/app.css`
- Modify: `public/css/app.css` through `npm run build`
- Modify: `tests/Feature/HealthCheckTest.php`
- Create: `tests/Feature/GoogleAuthUxTest.php`
- Modify: `README.md`
- Modify: `docs/operations/production-runbook.md`
- Modify: `resources/views/privacy.blade.php`
- Modify: `resources/views/terms.blade.php`

**Interfaces:**
- Auth views receive `googleAuthAvailable` from `GoogleAuthConfiguration::isReady()`.
- Health adds `checks.google_auth` with `disabled|ok|failed` only.

- [ ] **Step 1: Write failing UI/health contracts**

```php
public function test_google_button_is_visible_only_when_configuration_is_ready(): void
{
    $this->readyGoogleConfiguration();

    foreach (['/login', '/register'] as $path) {
        $this->get($path)->assertOk()
            ->assertSeeText('Teruskan dengan Google')
            ->assertSeeText('atau teruskan dengan e-mel')
            ->assertSee('href="'.route('auth.google.redirect').'"', false);
    }

    config()->set('services.google.client_secret', null);
    $this->get('/login')->assertDontSeeText('Teruskan dengan Google');
}
```

Extend health exact JSON to include `google_auth`. Add source-contract assertions for 44px target, 16px mobile input rule, focus state, no external Google script/avatar, and no horizontal overflow at 320px.

- [ ] **Step 2: Run and verify RED**

```powershell
php artisan test tests\Feature\GoogleAuthUxTest.php tests\Feature\HealthCheckTest.php
```

Expected: FAIL because view data/button/check do not exist.

- [ ] **Step 3: Implement UI and health**

Inject `GoogleAuthConfiguration` into `AuthController` and pass:

```php
return view('auth.login', ['googleAuthAvailable' => $this->googleAuth->isReady()]);
```

Use the same for register. Place a semantic link before the e-mail form:

```blade
@if($googleAuthAvailable)
    <a class="google-auth-button" href="{{ route('auth.google.redirect') }}">
        <span aria-hidden="true" class="google-auth-button__mark">G</span>
        <span>Teruskan dengan Google</span>
    </a>
    <div class="auth-divider" role="separator"><span>atau teruskan dengan e-mel</span></div>
@endif
```

Styles must use local CSS variables, `min-height:44px`, visible `:focus-visible`, responsive width and no remote asset. Health calls `$this->googleAuth->status()` and never returns credentials.

Document exact Google Cloud client type/domain/callback, environment names, enable-after-smoke rule and disable switch. Privacy/terms mention identity fields received from Google and no provider token retention.

- [ ] **Step 4: Build and verify GREEN**

```powershell
php artisan test tests\Feature\GoogleAuthUxTest.php tests\Feature\HealthCheckTest.php tests\Feature\MalayLocaleTest.php tests\Feature\MalayCopyTest.php tests\Feature\SecurityHeadersTest.php
npm test
npm run build
git diff --check
```

Expected: PHP/JS tests and build exit 0; synced CSS is updated.

- [ ] **Step 5: Commit**

```powershell
git add app/Http/Controllers/AuthController.php app/Http/Controllers/HealthController.php resources/views/auth/login.blade.php resources/views/auth/register.blade.php resources/css/app.css public/css/app.css tests/Feature/HealthCheckTest.php tests/Feature/GoogleAuthUxTest.php README.md docs/operations/production-runbook.md resources/views/privacy.blade.php resources/views/terms.blade.php
git commit -m "feat: present Google login safely"
```

---

### Task 7: Prove real MySQL concurrency and run every release gate

**Files:**
- Create temporarily outside Git: `tmp/chatme_google_mysql_setup.php`
- Create temporarily outside Git: `tmp/chatme_google_mysql_worker.php`
- Create temporarily outside Git: `tmp/chatme_google_mysql_verify.php`
- Create temporarily outside Git: `tmp/run_chatme_google_mysql_gate.py`
- Evidence outside Git: `tmp/chatme-google-mysql-gate-evidence.json`

**Interfaces:**
- Two workers each call `GoogleAccountService::resolve()` through distinct MySQL connections with the same subject/e-mail.
- Verifier requires exactly one user, one link, no duplicate and both worker outcomes resolve the same user ID.

- [ ] **Step 1: Run the disposable MySQL migration and two-worker gate**

The runner must create a unique cPanel DB/user, upload the current clean source archive without `.git`, `.env`, `vendor` or `node_modules`, run a locked `composer install --no-dev --prefer-dist --no-interaction --no-progress` inside the temporary root, migrate fresh, execute two synchronized workers, write redacted JSON evidence, and always delete DB/user/files in `finally`. It must not reuse the old production `vendor` because that release predates Socialite.

Expected evidence:

```json
{
  "status": "passed",
  "workers": 2,
  "distinct_mysql_connections": 2,
  "users": 1,
  "google_links": 1,
  "same_user_id": true,
  "production_database_touched": false,
  "cleanup": {
    "database_deleted": true,
    "database_user_deleted": true,
    "remote_test_root_removed": true,
    "errors": []
  }
}
```

- [ ] **Step 2: Run complete local quality gates**

```powershell
composer validate --strict --no-check-publish
composer test
npm test
composer analyse
php vendor\bin\pint --test
npm run build
composer audit --locked --no-interaction
npm audit --audit-level=high
git diff --check
gitleaks git . --log-opts="--all" --no-banner --no-color --redact=100 --timeout=300
```

Expected: 0 failures, 0 PHPStan errors, 0 dependency vulnerabilities, 0 leaks and clean working tree after committing any generated CSS/lockfile.

- [ ] **Step 3: Review, commit any final test-only correction, and push branch**

Use explicit paths and a terse commit if a final regression fixture changed. Push `feat/google-signin`, open a ready PR to `main`, include exact test/evidence counts, wait for every PR CI job, merge with expected head SHA, then wait for push CI on the merge SHA.

---

### Task 8: Provision Google Cloud safely and deploy exact SHA

**Files/state:**
- Google Cloud Console OAuth client (external restricted state)
- Production `.env` via SFTP (not Git)
- Fresh remote backup and encrypted off-host copy
- Production Git HEAD and deploy-state record

**Interfaces:**
- Authorized redirect URI: `https://chatme.akmalmarvis.com/auth/google/callback`.
- OAuth application type: Web application.
- Authorized domain: `akmalmarvis.com`.

- [ ] **Step 1: Provision the OAuth client without exposing secrets**

Use the signed-in Google Cloud browser session if available. Configure consent/app branding for ChatMe, add only identity scopes and exact redirect URI. Transfer client ID/secret directly from the browser/secure local channel to production SFTP; never echo or paste it into chat, Git, shell arguments or logs.

- [ ] **Step 2: Create a new verified recovery point**

Run the production backup/verify tooling against the current live SHA, create the AES-256-GCM off-host copy protected by Windows DPAPI, and record only backup path, release SHA, file count and verification status.

- [ ] **Step 3: Deploy while Google remains disabled**

Set `GOOGLE_AUTH_ENABLED=false`, install the remaining approved production env values, deploy the exact green merge SHA through `scripts/ops/deploy.php`, run forward migrations/config cache/internal health, and verify local password login routes plus `/`, `/up`, `/health`.

- [ ] **Step 4: Enable and smoke test Google Sign-In**

Set `GOOGLE_AUTH_ENABLED=true` by atomic SFTP env update, run `php artisan optimize:clear` and `php artisan config:cache`, then verify:

- `/health` returns `google_auth: ok`;
- login/register show the Google button at 320px, 390px, tablet and 1440px;
- redirect host is Google and callback URI is exact;
- a QA ordinary account can create/login/link once;
- admin/system auto-link is rejected using automated tests, not a destructive live attempt;
- session deadline and logout work;
- no new release-related `ERROR`/`CRITICAL` log appears.

- [ ] **Step 5: Final production audit and cleanup**

Verify production HEAD equals GitHub `main`, all migrations are `Ran`, scheduler/cron remains valid, tree is clean, customer data counts are not unexpectedly reduced, temporary QA data/resources and remote ops files created by this release are removed with exact-path guards, and the previous deployment ID remains usable for code rollback.

If Google fails, set only `GOOGLE_AUTH_ENABLED=false`, rebuild config cache and confirm local login remains available; do not roll back schema or restore the database automatically.
