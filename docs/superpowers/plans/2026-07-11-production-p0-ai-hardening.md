# ChatMe Production P0 AI and Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the production audit's four High-severity findings plus registration session fixation by shipping accurate retrieval, grounded Cloudflare Qwen3 fallback, paid entitlement enforcement and authentication throttling.

**Architecture:** A shared `ChatbotResponseService` orchestrates deterministic retrieval and a server-only `AiAnswerProvider`. Exact and high-confidence knowledge matches stay local; uncertain matches call Cloudflare once when AI is enabled and candidates exist, and every provider failure returns a stable local fallback. Widget, owner tester and paid developer API all use this service while retaining their distinct authorization, quota and logging rules.

**Tech Stack:** PHP 8.2, Laravel 12, Blade, vanilla JavaScript, Laravel HTTP client/cache/rate limiter, PHPUnit 11, Node test runner, Cloudflare Workers AI `@cf/qwen/qwen3-30b-a3b-fp8`.

## Global Constraints

- Production baseline is commit `d0f6345deda2e25c325c7cad53591dd83ba07f35`; the approved design commit is `4234338`.
- The application must remain usable when Cloudflare is disabled, unavailable or rate-limited.
- Cloudflare receives at most three active knowledge candidates and no chat history, IP address, user agent, owner identity or subscription data.
- Exactly one Cloudflare HTTP attempt is allowed per visitor message; timeout is eight seconds.
- Provider credentials must never enter HTML, JavaScript, logs, fixtures or repository history.
- Owner test mode writes no chat logs and consumes no monthly quota.
- Public widget and paid developer API each consume quota once and atomically write one user and one bot log.
- The local runtime has no `intl`; every test must pass without `Normalizer`. Production applies NFKC when `Normalizer` exists.
- All user-visible copy is Bahasa Melayu Malaysia.
- Every code task follows red-green-refactor TDD and ends in a focused commit.
- P0 completion is not completion of the full production-level objective; P1/P2 audit work remains.

---

## File map

**New response pipeline files**

- `app/ValueObjects/KnowledgeMatchResult.php` — ranked deterministic result and confidence band.
- `app/ValueObjects/ChatbotResponse.php` — final answer plus internal source/score/latency metadata.
- `app/ValueObjects/AiProviderResult.php` — successful provider answer and latency.
- `app/Contracts/AiAnswerProvider.php` — provider-neutral grounded-answer interface.
- `app/Services/ChatbotKnowledgeMatcher.php` — normalization, scoring and candidate ranking.
- `app/Services/ChatbotResponseService.php` — deterministic/AI/fallback orchestration.
- `app/Services/Ai/CloudflareWorkersAiProvider.php` — one-attempt Cloudflare HTTP integration and circuit breaker.

**New API/auth files**

- `app/Http/Controllers/DeveloperApiController.php` — paid bearer-token chat endpoint.
- `app/Http/Controllers/DeveloperTokenController.php` — generate/rotate developer tokens.
- `app/Http/Middleware/AuthenticateDeveloperToken.php` — hash lookup and server-side entitlement check.
- `app/Http/Requests/LoginRequest.php` — failed-login limiter and authentication.

**New migrations/tests**

- `database/migrations/2026_07_11_000001_add_ai_and_developer_token_fields_to_chatbots_table.php`.
- `tests/Fixtures/chatbot_query_corpus.php`.
- `tests/Unit/ChatbotKnowledgeMatcherTest.php`.
- `tests/Unit/CloudflareWorkersAiProviderTest.php`.
- `tests/Unit/ChatbotResponseServiceTest.php`.
- `tests/Feature/ChatbotAiIntegrationTest.php`.
- `tests/Feature/PlanEntitlementTest.php`.
- `tests/Feature/DeveloperApiTest.php`.
- `tests/Feature/AuthenticationHardeningTest.php`.

**Existing files changed**

- `app/Models/Chatbot.php`, `app/Providers/AppServiceProvider.php`.
- `app/Http/Controllers/ApiController.php`, `ChatbotController.php`, `ChatbotTestController.php`, `WidgetController.php`.
- `app/Http/Middleware/Cors.php`, `bootstrap/app.php`, `routes/api.php`, `routes/web.php`.
- `config/services.php`, `.env.example`, `database/seeders/HomepageChatbotSeeder.php`.
- `resources/views/chatbots/create.blade.php`, `edit.blade.php`, `embed.blade.php`.
- `public/widget.js`, `tests/js/widget-security.test.js`, plus affected feature tests.

---

### Task 1: Replace the answer-only matcher with ranked deterministic retrieval

**Files:**

- Create: `app/ValueObjects/KnowledgeMatchResult.php`
- Create: `app/Services/ChatbotKnowledgeMatcher.php`
- Create: `tests/Fixtures/chatbot_query_corpus.php`
- Create: `tests/Unit/ChatbotKnowledgeMatcherTest.php`
- Delete after consumers migrate in Task 4: `app/Services/ChatbotResponseMatcher.php`

**Interfaces:**

- Consumes: `Chatbot` and a validated 1–1,000 character message.
- Produces: `ChatbotKnowledgeMatcher::match(Chatbot $chatbot, string $message): KnowledgeMatchResult`.
- `KnowledgeMatchResult` exposes `winner`, `candidates`, `score`, `confidence`, `isHighConfidence()` and `hasCandidates()`.

- [ ] **Step 1: Add the 50-case production regression corpus**

Create a fixture that defines the active knowledge and exactly these 50 queries:

```php
<?php

return [
    'knowledge' => [
        'pricing' => ['question' => 'Berapakah harga pelan ChatMe?', 'answer' => 'Free RM0, Pro RM49 dan Enterprise RM149 sebulan.', 'tags' => 'harga,pelan,langganan'],
        'payment' => ['question' => 'Bagaimanakah cara pembayaran?', 'answer' => 'Bayaran dibuat melalui ToyyibPay menggunakan FPX atau DuitNow QR.', 'tags' => 'bayar,toyyibpay,fpx,duitnow'],
        'intro' => ['question' => 'Apakah ChatMe?', 'answer' => 'ChatMe ialah sistem chatbot berasaskan soal jawab.', 'tags' => 'chatme,pengenalan,chatbot'],
        'install' => ['question' => 'Bagaimanakah cara memasang chatbot?', 'answer' => 'Salin kod pemasangan dan letakkannya sebelum penutup body laman web.', 'tags' => 'pasang,embed,widget,wordpress'],
        'features' => ['question' => 'Bolehkah ubah warna, avatar, nama dan domain serta apakah had pelan?', 'answer' => 'Anda boleh mengubah nama, warna, avatar, domain dan soal jawab chatbot.', 'tags' => 'warna,avatar,nama,domain,had,branding,pro,mesej,soal jawab'],
    ],
    'cases' => [
        ['pricing', 'Berapa harga pelan ChatMe?'],
        ['pricing', 'berapakah harga plan chatme'],
        ['pricing', 'harga langganan chatme berapa'],
        ['pricing', 'nak tahu harga pakej'],
        ['pricing', 'berapa bayaran sebulan'],
        ['pricing', 'caj bulanan pro'],
        ['pricing', 'harga pro'],
        ['pricing', 'pro rm berapa'],
        ['pricing', 'harga enterprise'],
        ['pricing', 'enterprise berapa sebulan'],
        ['pricing', 'pelan percuma ada ke'],
        ['pricing', 'free plan berapa'],
        ['pricing', 'caj untuk free'],
        ['pricing', 'boleh bagi senarai harga?'],
        ['pricing', 'berapa kos guna chatme'],
        ['payment', 'cara bayar'],
        ['payment', 'pembayaran guna apa'],
        ['payment', 'boleh bayar fpx'],
        ['payment', 'ada duitnow qr'],
        ['payment', 'bayaran auto ke'],
        ['payment', 'potong akaun automatik?'],
        ['payment', 'renew setiap bulan macam mana'],
        ['payment', 'pembayaran melalui toyyibpay'],
        ['intro', 'apa itu chatme'],
        ['intro', 'chatme ni apa'],
        ['intro', 'fungsi chatme'],
        ['intro', 'chatbot ni buat apa'],
        ['intro', 'kegunaan sistem chatme'],
        ['intro', 'siapa chatme'],
        ['intro', 'terangkan chatme'],
        ['install', 'cara pasang'],
        ['install', 'macam mana embed'],
        ['install', 'kod pemasangan dekat mana'],
        ['install', 'nak letak chatbot di website'],
        ['install', 'install widget'],
        ['install', 'integrasi laman web'],
        ['install', 'boleh pasang kat wordpress'],
        ['features', 'boleh ubah warna'],
        ['features', 'tukar avatar'],
        ['features', 'nama bot boleh edit'],
        ['features', 'boleh hadkan domain'],
        ['features', 'berapa chatbot pelan pro'],
        ['features', 'had mesej free'],
        ['features', 'berapa soal jawab boleh simpan'],
        ['features', 'buang branding pelan mana'],
        [null, 'cuaca kuala lumpur'],
        [null, 'keputusan bola malam tadi'],
        [null, 'resepi nasi lemak'],
        [null, 'siapa presiden negara itu'],
        [null, 'beli tiket wayang'],
    ],
];
```

- [ ] **Step 2: Write failing matcher tests**

```php
public function test_fifty_malay_variants_select_the_expected_intent_or_no_match(): void
{
    $fixture = require base_path('tests/Fixtures/chatbot_query_corpus.php');
    $chatbot = $this->chatbotWithFixture($fixture['knowledge']);
    $matcher = app(ChatbotKnowledgeMatcher::class);

    foreach ($fixture['cases'] as [$expected, $query]) {
        $result = $matcher->match($chatbot, $query);
        $expected === null
            ? $this->assertFalse($result->hasCandidates(), $query)
            : $this->assertSame($fixture['knowledge'][$expected]['answer'], $result->winner?->answer, $query);
    }
}

public function test_empty_and_short_tags_never_match_every_message(): void
{
    $chatbot = $this->chatbotWithKnowledge([
        ['question' => 'Pengenalan', 'answer' => 'Salah', 'tags' => ' ,a,,'],
        ['question' => 'Harga pelan', 'answer' => 'Betul', 'tags' => 'harga,pelan'],
    ]);

    $this->assertSame('Betul', app(ChatbotKnowledgeMatcher::class)
        ->match($chatbot, 'Berapa harga pelan?')->winner?->answer);
}
```

- [ ] **Step 3: Run the tests and verify the expected red state**

Run: `php artisan test tests/Unit/ChatbotKnowledgeMatcherTest.php`

Expected: FAIL because `ChatbotKnowledgeMatcher` and `KnowledgeMatchResult` do not exist.

- [ ] **Step 4: Implement the value object and deterministic matcher**

```php
final readonly class KnowledgeMatchResult
{
    public function __construct(
        public ?KnowledgeItem $winner,
        public Collection $candidates,
        public float $score,
        public string $confidence,
    ) {}

    public function isHighConfidence(): bool { return $this->confidence === 'high'; }
    public function hasCandidates(): bool { return $this->candidates->isNotEmpty(); }
}
```

Implement normalization and bounded scoring with these exact thresholds:

```php
private const HIGH_CONFIDENCE = 0.72;
private const UNCERTAIN_CONFIDENCE = 0.20;

private function normalize(string $text): string
{
    if (class_exists(\Normalizer::class)) {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
    }

    $text = mb_strtolower(str_replace(['’', '‘', '`'], "'", $text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
    $tokens = array_map(
        fn (string $token): string => mb_strlen($token) > 5 && str_ends_with($token, 'kah')
            ? mb_substr($token, 0, -3)
            : $token,
        preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [],
    );

    return implode(' ', $tokens);
}
```

Use `1.0` for normalized exact questions; `0.86 + min(0.08, tokenCount * 0.01)` for full-phrase containment; otherwise use `(0.55 * questionCoverage) + (0.35 * queryCoverage) + min(0.10, tagBonus)`. Remove only explicit stop words from overlap scoring, including `chatme` and `chatbot`, and sort ties by more specific normalized question then ascending ID.

- [ ] **Step 5: Run matcher tests and the existing chatbot tests**

Run: `php artisan test tests/Unit/ChatbotKnowledgeMatcherTest.php tests/Feature/ChatbotTesterTest.php tests/Feature/HomepageChatbotTest.php`

Expected: new matcher tests PASS and all existing tester/homepage tests PASS because the old matcher remains available until Task 4.

- [ ] **Step 6: Commit the retrieval unit**

```bash
git add app/ValueObjects/KnowledgeMatchResult.php app/Services/ChatbotKnowledgeMatcher.php tests/Fixtures/chatbot_query_corpus.php tests/Unit/ChatbotKnowledgeMatcherTest.php
git commit -m "fix: improve chatbot knowledge retrieval"
```

---

### Task 2: Add stable fallback settings and honest AI prompt UI

**Files:**

- Create: `database/migrations/2026_07_11_000001_add_ai_and_developer_token_fields_to_chatbots_table.php`
- Modify: `app/Models/Chatbot.php`
- Modify: `app/Http/Controllers/ChatbotController.php`
- Modify: `resources/views/chatbots/create.blade.php`
- Modify: `resources/views/chatbots/edit.blade.php`
- Modify: `database/seeders/HomepageChatbotSeeder.php`
- Test: `tests/Feature/ChatbotSettingsTest.php`

**Interfaces:**

- Produces: `Chatbot::fallbackResponse(): string` and nullable token columns used by Task 6.
- Preserves: existing `system_prompt` data.

- [ ] **Step 1: Write failing persistence, validation and copy tests**

Test that create/update accept `system_prompt` up to 1,000 and `fallback_message` up to 500, reject one character over each limit, render “Gaya jawapan AI”, and no longer render the unrestricted old label.

```php
$this->actingAs($user)->post(route('chatbots.store'), [
    'name' => 'AI Bot',
    'system_prompt' => 'Jawab dengan nada profesional dan mesra.',
    'fallback_message' => 'Maaf, maklumat itu belum tersedia.',
])->assertSessionHasNoErrors();

$this->assertDatabaseHas('chatbots', [
    'name' => 'AI Bot',
    'fallback_message' => 'Maaf, maklumat itu belum tersedia.',
]);
```

- [ ] **Step 2: Run the focused tests and verify failure**

Run: `php artisan test tests/Feature/ChatbotSettingsTest.php`

Expected: FAIL because the column and UI do not exist and `system_prompt` still allows 5,000 characters.

- [ ] **Step 3: Add the backward-compatible schema**

```php
Schema::table('chatbots', function (Blueprint $table): void {
    $table->text('fallback_message')->nullable()->after('system_prompt');
    $table->char('developer_api_token_hash', 64)->nullable()->unique()->after('api_key');
    $table->string('developer_api_token_prefix', 20)->nullable()->after('developer_api_token_hash');
});
```

The `down()` removes only these three new columns. Do not alter or delete `system_prompt`.

- [ ] **Step 4: Implement stable fallback and validation**

Add `fallback_message` to `$fillable` and:

```php
public function fallbackResponse(): string
{
    return filled($this->fallback_message)
        ? trim((string) $this->fallback_message)
        : 'Maaf, saya belum menemui jawapan yang tepat. Cuba gunakan perkataan lain.';
}
```

Use `['nullable', 'string', 'max:1000']` for `system_prompt` and `['nullable', 'string', 'max:500']` for `fallback_message` in create/update. Update both forms with the approved label and help text: “Arahan ini mengawal nada jawapan AI. Fakta tetap terhad kepada soal jawab aktif.”

- [ ] **Step 5: Update the homepage seeder**

Set a concise style prompt and stable fallback:

```php
'system_prompt' => 'Gunakan Bahasa Melayu Malaysia yang jelas, sopan dan ringkas.',
'fallback_message' => 'Maaf, maklumat itu belum tersedia. Cuba tanya tentang pelan, pembayaran atau fungsi ChatMe.',
```

- [ ] **Step 6: Run settings, seeder and migration tests**

Run: `php artisan test tests/Feature/ChatbotSettingsTest.php tests/Feature/HomepageChatbotTest.php tests/Feature/LegacySubscriptionMigrationTest.php`

Expected: PASS.

- [ ] **Step 7: Commit the settings unit**

```bash
git add database/migrations/2026_07_11_000001_add_ai_and_developer_token_fields_to_chatbots_table.php app/Models/Chatbot.php app/Http/Controllers/ChatbotController.php resources/views/chatbots/create.blade.php resources/views/chatbots/edit.blade.php database/seeders/HomepageChatbotSeeder.php tests/Feature/ChatbotSettingsTest.php
git commit -m "feat: add grounded chatbot response settings"
```

---

### Task 3: Implement the Cloudflare Qwen3 provider boundary

**Files:**

- Create: `app/Contracts/AiAnswerProvider.php`
- Create: `app/ValueObjects/AiProviderResult.php`
- Create: `app/Services/Ai/CloudflareWorkersAiProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `tests/Unit/CloudflareWorkersAiProviderTest.php`

**Interfaces:**

- `AiAnswerProvider::answer(Chatbot $chatbot, string $message, Collection $candidates): ?AiProviderResult`.
- `AiProviderResult` contains public readonly `answer: string` and `latencyMs: int`.
- Returning `null` means safe local fallback; exceptions do not cross the provider boundary.

- [ ] **Step 1: Write failing provider contract tests**

Cover disabled configuration, success, sentinel, timeout, 429, 500, malformed JSON, no secret in logs, at most three candidates and circuit opening after five failures.

```php
Http::fake(['api.cloudflare.com/*' => Http::response([
    'success' => true,
    'result' => ['response' => 'Jawapan berpijak pada knowledge.'],
])]);

$result = app(AiAnswerProvider::class)->answer($chatbot, 'Soalan?', $candidates);

$this->assertSame('Jawapan berpijak pada knowledge.', $result?->answer);
Http::assertSentCount(1);
Http::assertSent(fn (Request $request) =>
    count($request['messages']) === 2
    && str_contains($request['messages'][0]['content'], 'Gunakan hanya konteks')
    && ! str_contains(json_encode($request->data()), $chatbot->user->email)
);
```

- [ ] **Step 2: Run tests and verify red state**

Run: `php artisan test tests/Unit/CloudflareWorkersAiProviderTest.php`

Expected: FAIL because the contract/provider do not exist.

- [ ] **Step 3: Add exact server-only configuration**

```php
'cloudflare_ai' => [
    'enabled' => filter_var(env('CHATME_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'token' => env('CLOUDFLARE_AI_TOKEN'),
    'model' => env('CLOUDFLARE_AI_MODEL', '@cf/qwen/qwen3-30b-a3b-fp8'),
    'timeout' => (int) env('CLOUDFLARE_AI_TIMEOUT', 8),
    'max_tokens' => (int) env('CLOUDFLARE_AI_MAX_TOKENS', 220),
],
```

Mirror these keys in `.env.example` with disabled/blank safe defaults.

- [ ] **Step 4: Bind the interface and implement the provider**

Bind in `AppServiceProvider::register()`:

```php
$this->app->bind(AiAnswerProvider::class, CloudflareWorkersAiProvider::class);
```

The provider must build this payload and make exactly one request:

```php
$payload = [
    'messages' => [
        ['role' => 'system', 'content' => $this->systemInstruction($chatbot, $candidates)],
        ['role' => 'user', 'content' => $message],
    ],
    'max_tokens' => config('services.cloudflare_ai.max_tokens'),
    'temperature' => 0.2,
];

$response = Http::withToken($token)
    ->acceptJson()
    ->timeout($timeout)
    ->post("https://api.cloudflare.com/client/v4/accounts/{$account}/ai/run/{$model}", $payload);
```

Map `__CHATME_NO_ANSWER__`, blank output, non-2xx, exceptions and invalid JSON to `null`. Log only `chatbot_id`, safe status category and latency. Implement the cache lock/counters `chatme:ai:consecutive-failures` and `chatme:ai:circuit-open` with a five-failure threshold and five-minute open period.

- [ ] **Step 5: Run provider tests**

Run: `php artisan test tests/Unit/CloudflareWorkersAiProviderTest.php`

Expected: PASS with `Http::assertSentCount(1)` on every failure case that reaches the provider and zero requests when disabled/circuit-open.

- [ ] **Step 6: Commit the provider unit**

```bash
git add app/Contracts/AiAnswerProvider.php app/ValueObjects/AiProviderResult.php app/Services/Ai config/services.php .env.example app/Providers/AppServiceProvider.php tests/Unit/CloudflareWorkersAiProviderTest.php
git commit -m "feat: add grounded Cloudflare AI provider"
```

---

### Task 4: Orchestrate deterministic and AI answers without holding a database lock

**Files:**

- Create: `app/ValueObjects/ChatbotResponse.php`
- Create: `app/Services/ChatbotResponseService.php`
- Modify: `app/Http/Controllers/ApiController.php`
- Modify: `app/Http/Controllers/ChatbotTestController.php`
- Delete: `app/Services/ChatbotResponseMatcher.php`
- Modify: `tests/Feature/ChatbotTesterTest.php`
- Modify: `tests/Feature/PlanLimitTest.php`
- Create: `tests/Unit/ChatbotResponseServiceTest.php`
- Create: `tests/Feature/ChatbotAiIntegrationTest.php`

**Interfaces:**

- Produces: `ChatbotResponseService::respond(Chatbot $chatbot, string $message, bool $allowAi = true): ChatbotResponse`.
- Public controller computes the response before opening the quota/log transaction.

- [ ] **Step 1: Write failing orchestration tests**

```php
public function test_high_confidence_answer_does_not_call_ai(): void
{
    $provider = Mockery::mock(AiAnswerProvider::class);
    $provider->shouldNotReceive('answer');
    $this->app->instance(AiAnswerProvider::class, $provider);

    $response = app(ChatbotResponseService::class)->respond($chatbot, 'waktu operasi');

    $this->assertSame('deterministic', $response->source);
    $this->assertSame('Kami buka setiap hari.', $response->answer);
}

public function test_uncertain_answer_uses_ai(): void
{
    $provider = Mockery::mock(AiAnswerProvider::class);
    $provider->shouldReceive('answer')->once()
        ->andReturn(new AiProviderResult('Jawapan AI.', 120));
    $this->app->instance(AiAnswerProvider::class, $provider);

    $response = app(ChatbotResponseService::class)
        ->respond($chatbot, 'Boleh jelaskan pakej yang sesuai?');

    $this->assertSame('cloudflare', $response->source);
    $this->assertSame('Jawapan AI.', $response->answer);
}

public function test_provider_failure_uses_stable_fallback(): void
{
    $provider = Mockery::mock(AiAnswerProvider::class);
    $provider->shouldReceive('answer')->once()->andReturnNull();
    $this->app->instance(AiAnswerProvider::class, $provider);

    $response = app(ChatbotResponseService::class)
        ->respond($chatbot, 'Boleh jelaskan pakej yang sesuai?');

    $this->assertSame('fallback', $response->source);
    $this->assertSame($chatbot->fallbackResponse(), $response->answer);
}
```

- [ ] **Step 2: Run focused tests and verify failure**

Run: `php artisan test tests/Unit/ChatbotResponseServiceTest.php tests/Feature/ChatbotAiIntegrationTest.php`

Expected: FAIL because the service/value object do not exist.

- [ ] **Step 3: Implement response orchestration**

```php
public function respond(Chatbot $chatbot, string $message, bool $allowAi = true): ChatbotResponse
{
    $match = $this->matcher->match($chatbot, $message);

    if ($match->isHighConfidence()) {
        return new ChatbotResponse($match->winner->answer, 'deterministic', $match->score);
    }

    if ($allowAi && $match->hasCandidates()) {
        $ai = $this->provider->answer($chatbot, $message, $match->candidates->take(3));
        if ($ai !== null) {
            return new ChatbotResponse($ai->answer, 'cloudflare', $match->score, $ai->latencyMs);
        }
    }

    return new ChatbotResponse($chatbot->fallbackResponse(), 'fallback', $match->score);
}
```

- [ ] **Step 4: Move the public provider call outside the transaction**

`ApiController::chat` must:

1. perform the existing fast pre-check;
2. validate/trim input;
3. call `ChatbotResponseService` outside a transaction;
4. enter the transaction, lock owner, recheck quota and atomically write both logs; and
5. return 429 without logs if the locked recheck fails.

Pass only `$chatbot`, `$sessionId`, `$userMessage`, and the already-computed answer into the transaction. Never call Cloudflare while the owner row is locked.

- [ ] **Step 5: Migrate owner tester and delete the old matcher**

Inject `ChatbotResponseService` into `ChatbotTestController`, return only `['response' => $response->answer]`, keep authorization/validation, and confirm no quota/log writes. Delete `ChatbotResponseMatcher` only after `rg "ChatbotResponseMatcher"` finds no consumers.

- [ ] **Step 6: Run atomicity, quota and AI integration tests**

Run: `php artisan test tests/Unit/ChatbotResponseServiceTest.php tests/Feature/ChatbotAiIntegrationTest.php tests/Feature/ChatbotTesterTest.php tests/Feature/PlanLimitTest.php tests/Feature/WidgetApiSecurityTest.php`

Expected: PASS; existing concurrent quota and rollback assertions remain authoritative.

- [ ] **Step 7: Commit the shared response pipeline**

```bash
git add app/ValueObjects/ChatbotResponse.php app/Services/ChatbotResponseService.php app/Http/Controllers/ApiController.php app/Http/Controllers/ChatbotTestController.php tests/Unit/ChatbotResponseServiceTest.php tests/Feature/ChatbotAiIntegrationTest.php tests/Feature/ChatbotTesterTest.php tests/Feature/PlanLimitTest.php
git rm app/Services/ChatbotResponseMatcher.php
git commit -m "feat: add resilient hybrid chatbot responses"
```

---

### Task 5: Enforce Enterprise remove-branding on the server

**Files:**

- Modify: `app/Http/Controllers/WidgetController.php`
- Modify: `public/widget.js`
- Create: `tests/Feature/PlanEntitlementTest.php`
- Modify: `tests/js/widget-security.test.js`

**Interfaces:**

- Produces server config `showBranding: bool` derived from the active current plan.
- Client defaults branding to shown when the field is absent.

- [ ] **Step 1: Write failing entitlement tests**

Create Free, Pro, active Enterprise and expired Enterprise owners. Assert widget script config contains `"showBranding":false` only for active Enterprise. Assert `Cache-Control` is `no-store` so entitlement changes are immediate.

Add JavaScript contract assertions:

```js
assert.match(source, /config\.showBranding !== false/);
assert.match(source, /brandEl\.hidden/);
assert.doesNotMatch(source, /innerHTML[^;]*showBranding/);
```

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/PlanEntitlementTest.php; npm test`

Expected: FAIL because branding is unconditional.

- [ ] **Step 3: Implement server-derived branding**

In `WidgetController`:

```php
'showBranding' => ! (bool) $chatbot->user->currentPlan()?->remove_branding,
```

Return `Cache-Control: no-store, private` rather than the current one-hour public cache.

In `widget.js`, capture `brandEl`, default safely and hide the entire element:

```js
config.showBranding = config.showBranding !== false;
var brandEl = document.getElementById('chatme-brand');
brandEl.hidden = !config.showBranding;
```

- [ ] **Step 4: Run PHP and JavaScript entitlement tests**

Run: `php artisan test tests/Feature/PlanEntitlementTest.php tests/Feature/SubscriptionPlanTest.php; npm test`

Expected: PASS.

- [ ] **Step 5: Commit branding enforcement**

```bash
git add app/Http/Controllers/WidgetController.php public/widget.js tests/Feature/PlanEntitlementTest.php tests/js/widget-security.test.js
git commit -m "feat: enforce widget branding entitlement"
```

---

### Task 6: Add a separate paid developer API token and endpoint

**Files:**

- Create: `app/Http/Middleware/AuthenticateDeveloperToken.php`
- Create: `app/Http/Controllers/DeveloperApiController.php`
- Create: `app/Http/Controllers/DeveloperTokenController.php`
- Modify: `app/Models/Chatbot.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/Cors.php`
- Modify: `resources/views/chatbots/embed.blade.php`
- Create: `tests/Feature/DeveloperApiTest.php`
- Modify: `tests/Feature/WidgetApiSecurityTest.php`

**Interfaces:**

- `Chatbot::rotateDeveloperApiToken(): string` returns the raw token once and stores only hash/prefix.
- Middleware sets request attribute `developer_chatbot`.
- `POST /api/v1/chat` accepts bearer token and `{message, session_id?}`.

- [ ] **Step 1: Write failing token and API tests**

Cover active Pro/Enterprise success, Free/expired denial, missing/invalid token generic 401, inactive bot denial, quota 429, hash-only persistence, immediate rotation invalidation, endpoint rate limit and no wildcard developer CORS.

```php
$raw = $chatbot->rotateDeveloperApiToken();
$this->assertStringStartsWith('cm_live_', $raw);
$this->assertSame(hash('sha256', $raw), $chatbot->fresh()->developer_api_token_hash);
$this->assertDatabaseMissing('chatbots', ['developer_api_token_hash' => $raw]);

$this->withToken($raw)->postJson('/api/v1/chat', [
    'message' => 'waktu operasi',
    'session_id' => 'developer-session',
])->assertOk()->assertJsonPath('response', 'Kami buka setiap hari.');
```

- [ ] **Step 2: Run the tests and verify red state**

Run: `php artisan test tests/Feature/DeveloperApiTest.php`

Expected: FAIL because token methods, routes and middleware do not exist.

- [ ] **Step 3: Implement token generation and one-time display**

```php
public function rotateDeveloperApiToken(): string
{
    $raw = 'cm_live_'.Str::random(48);
    $this->forceFill([
        'developer_api_token_hash' => hash('sha256', $raw),
        'developer_api_token_prefix' => substr($raw, 0, 16),
    ])->save();

    return $raw;
}
```

`DeveloperTokenController` authorizes update, checks `currentPlan()?->api_access`, rotates, and redirects with `developer_token` plus a success toast. The embed view shows the raw token only from `session('developer_token')`, otherwise only the stored prefix and rotation action.

- [ ] **Step 4: Implement bearer middleware and paid endpoint**

Middleware hashes the bearer token, loads active chatbot by hash, rejects absent/invalid token with the same generic Malay 401, rejects missing entitlement with generic 403, and attaches the chatbot.

Register alias `developer.token` in `bootstrap/app.php`. Add a named limiter in `AppServiceProvider` keyed by token hash plus IP. Route:

```php
Route::post('/v1/chat', DeveloperApiController::class)
    ->middleware(['developer.token', 'throttle:developer-api'])
    ->name('api.developer.chat');
```

The controller follows the same response-before-lock and atomic quota/log transaction pattern as `ApiController`.

- [ ] **Step 5: Restrict CORS to public widget routes**

Update `Cors` so wildcard headers and OPTIONS behavior apply only to `api.chat` and `api.widget.config`. Developer API responses must not contain `Access-Control-Allow-Origin: *`.

- [ ] **Step 6: Run developer API, quota and security tests**

Run: `php artisan test tests/Feature/DeveloperApiTest.php tests/Feature/WidgetApiSecurityTest.php tests/Feature/PlanLimitTest.php tests/Feature/PlanEntitlementTest.php`

Expected: PASS.

- [ ] **Step 7: Commit paid API enforcement**

```bash
git add app/Http/Middleware/AuthenticateDeveloperToken.php app/Http/Controllers/DeveloperApiController.php app/Http/Controllers/DeveloperTokenController.php app/Models/Chatbot.php bootstrap/app.php routes/api.php routes/web.php app/Http/Middleware/Cors.php resources/views/chatbots/embed.blade.php app/Providers/AppServiceProvider.php tests/Feature/DeveloperApiTest.php tests/Feature/WidgetApiSecurityTest.php
git commit -m "feat: enforce paid developer API access"
```

---

### Task 7: Rate-limit authentication and regenerate registration sessions

**Files:**

- Create: `app/Http/Requests/LoginRequest.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AuthenticationHardeningTest.php`

**Interfaces:**

- `LoginRequest::authenticate(): void` authenticates or throws validation/throttle errors.
- Named limiter `registration` allows three POST attempts per hour per IP.

- [ ] **Step 1: Write failing behavioral tests**

Cover five failed logins allowed, sixth throttled with Malay wait text and `Retry-After`, a different email key not sharing the email/IP bucket, successful login clearing failures, fourth registration throttled, and changed encrypted session cookie after successful registration.

```php
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
}

$this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
    ->assertRedirect()
    ->assertSessionHasErrors('email')
    ->assertHeader('Retry-After');
```

For registration session fixation, first GET `/register`, capture the configured session cookie, POST registration with that cookie, then assert the response's session cookie value differs.

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/AuthenticationHardeningTest.php`

Expected: FAIL because login/register are unlimited and registration does not regenerate the session.

- [ ] **Step 3: Implement `LoginRequest` using Laravel's limiter pattern**

```php
public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
        RateLimiter::hit($this->throttleKey(), 60);
        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    RateLimiter::clear($this->throttleKey());
}

public function throttleKey(): string
{
    return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
}
```

`ensureIsNotRateLimited()` checks five attempts and throws `HttpResponseException` containing `back()->withErrors(['email' => $message])->onlyInput('email')->withHeaders(['Retry-After' => $seconds])`. The Malay message includes the remaining seconds/minutes, and the browser returns to the login form instead of showing an empty 429 page.

- [ ] **Step 4: Implement registration limiter and session regeneration**

Configure:

```php
RateLimiter::for('registration', fn (Request $request) =>
    Limit::perHour(3)
        ->by($request->ip() ?: 'unknown')
        ->response(fn (Request $request, array $headers) => back()
            ->withErrors(['email' => 'Terlalu banyak percubaan pendaftaran. Sila cuba semula kemudian.'])
            ->withInput($request->except('password', 'password_confirmation'))
            ->withHeaders($headers))
);
```

Attach `throttle:registration` only to POST `/register`. Change login controller type to `LoginRequest`, call `authenticate()`, then regenerate. After `Auth::login($user)` in `register`, call `$request->session()->regenerate()` before redirect.

- [ ] **Step 5: Run auth and existing flow tests**

Run: `php artisan test tests/Feature/AuthenticationHardeningTest.php tests/Feature/ExampleTest.php tests/Feature/MalayCopyTest.php`

Expected: PASS.

- [ ] **Step 6: Commit authentication hardening**

```bash
git add app/Http/Requests/LoginRequest.php app/Http/Controllers/AuthController.php app/Providers/AppServiceProvider.php routes/web.php tests/Feature/AuthenticationHardeningTest.php
git commit -m "fix: harden authentication sessions and limits"
```

---

### Task 8: Full regression, browser QA, production configuration and deployment

**Files:**

- Modify only if verification exposes a proven defect; any fix requires a new failing regression test and focused commit.
- Update: `docs/superpowers/specs/2026-07-11-production-p0-ai-hardening-design.md` only if implementation evidence requires an explicit, approved clarification.

**Interfaces:**

- Deployment consumes `CLOUDFLARE_ACCOUNT_ID` and scoped `CLOUDFLARE_AI_TOKEN` from production `.env`.
- Rollback switch is `CHATME_AI_ENABLED=false` plus configuration recache.

- [ ] **Step 1: Run all local gates from a clean environment**

```powershell
php artisan test
npm test
vendor\bin\pint --test
npm run build
composer validate --strict
composer audit --no-interaction
npm audit --audit-level=high
git diff --check
git status --short
```

Expected: all exit 0; no uncommitted generated or source changes remain after committing the build asset if this repository tracks it.

- [ ] **Step 2: Run browser QA on an isolated SQLite database**

Verify desktop and 390×844 mobile:

- owner tester exact/high-confidence answer;
- owner tester uncertain query returning the stable fallback with AI disabled locally; provider success is covered by `Http::fake` in the automated suite and by a real non-sensitive smoke test after production credentials are provisioned;
- stable fallback with AI disabled;
- no quota/log writes from owner tester;
- Free widget branding present and active Enterprise branding absent;
- developer token shown once, copied and rotation confirmation displayed;
- login throttle/top toast; and
- no horizontal overflow, input auto-zoom, console error or inaccessible dialog focus.

- [ ] **Step 3: Verify the deployment preconditions**

Fetch GitHub `main` through HTTPS with `GIT_CONFIG_GLOBAL=NUL`; confirm remote `main` is an ancestor of local HEAD. Confirm local worktree clean. Confirm production is on `main`, clean and still at the expected pre-deploy SHA. Abort rather than overwrite any unexpected server change.

- [ ] **Step 4: Provision Cloudflare credentials safely**

The account owner creates a scoped Workers AI token and supplies the account ID/token through the secure local credential path. Do not print either value. Add/update the non-secret production `.env` keys exactly as follows:

```dotenv
CHATME_AI_ENABLED=false
CLOUDFLARE_AI_MODEL=@cf/qwen/qwen3-30b-a3b-fp8
CLOUDFLARE_AI_TIMEOUT=8
CLOUDFLARE_AI_MAX_TOKENS=220
```

Set `CLOUDFLARE_ACCOUNT_ID` and `CLOUDFLARE_AI_TOKEN` from the secure credential source through SFTP/SSH without printing their values. Neither secret is written into a command transcript or committed file.

- [ ] **Step 5: Push and deploy with AI disabled**

Push verified HEAD to GitHub `main`, verify the remote SHA, enter Laravel maintenance mode, fast-forward production, run `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan optimize`, and always execute `php artisan up` in a `finally` path.

- [ ] **Step 6: Run deterministic production smoke tests**

Confirm:

- homepage/login return 200;
- “Berapa harga pelan ChatMe?” returns the pricing answer;
- owner/test and entitlement feature tests remain green on production code;
- Free/Enterprise widget config has expected `showBranding` values;
- invalid developer token is generic and protected;
- login/register routes expose limiter middleware/behavior; and
- production is clean with no pending migrations.

- [ ] **Step 7: Enable and verify Cloudflare Qwen3**

Set `CHATME_AI_ENABLED=true`, then run `php artisan config:clear` and `php artisan config:cache` as separate commands. Send one non-sensitive uncertain test question through the owner tester or a protected server-side smoke command. Confirm a grounded answer, latency under the widget's 15-second ceiling and no credential/message content in Laravel logs.

Then temporarily disable AI and repeat the same query to prove deterministic fallback; re-enable AI and rebuild config cache.

- [ ] **Step 8: Verify release parity and document evidence**

Confirm local HEAD, GitHub `main` and production HEAD are identical; production worktree clean; no pending migration; homepage/login 200; Cloudflare success and disabled fallback both verified. Record exact test counts, browser viewports and release SHA in the final handoff.

Do not mark the full production-level goal complete. Update the broader plan to the next required tranche: checkout idempotency, session warning, password reset/email verification, search/filter, CSP and operational controls.
