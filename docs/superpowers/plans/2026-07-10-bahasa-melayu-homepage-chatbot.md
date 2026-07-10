# Bahasa Melayu Malaysia dan Chatbot Homepage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Menjadikan semua teks sistem ChatMe mudah difahami dalam Bahasa Melayu Malaysia serta memasang chatbot rasmi ChatMe yang tepat dan berfungsi pada homepage.

**Architecture:** Locale Laravel ditetapkan kepada ms dengan fail bahasa tempatan dan ujian regresi. Salinan antaramuka dikemas kini pada Blade, pengawal, API dan widget tanpa mengubah kontrak fungsi. Chatbot homepage menggunakan rekod rasmi sedia ada melalui slug tetap, kandungan kanonik 33 soal jawab dan seeder idempoten yang mengekalkan API key serta sejarah sembang.

**Tech Stack:** Laravel 12, PHP 8.2, Blade, Eloquent, SQLite untuk ujian, MySQL production, JavaScript tanpa rangka kerja, Vite, PHPUnit 11.

## Global Constraints

- Gunakan Bahasa Melayu Malaysia mesra-neutral dengan kata ganti anda.
- Kekalkan ChatMe, ToyyibPay, FPX, DuitNow QR, API, JSON, HTML, URL, Free, Pro dan Enterprise.
- Jangan terjemah kandungan ciptaan pengguna secara automatik.
- Jangan ubah token protokol ToyyibPay, kod sebab dalaman, status HTTP atau kunci JSON.
- Jangan hardcode API key chatbot homepage dalam repo.
- Homepage menggunakan ChatMe Assistant sedia ada, slug chatme-homepage, domain chatme.akmalmarvis.com dan tidak auto-buka.
- Semua perubahan tingkah laku mesti melalui kitaran ujian gagal, implementasi minimum dan ujian lulus.

---

## File Map

- Create lang/ms/validation.php: mesej validasi dan nama medan.
- Create lang/ms/auth.php: mesej autentikasi.
- Create lang/ms/passwords.php: mesej penetapan semula kata laluan.
- Create lang/ms/pagination.php: label pagination.
- Create lang/ms/chatme.php: mesej API, widget dan tindakan tersuai.
- Create tests/Feature/MalayLocaleTest.php: locale, validasi, autentikasi, pagination dan ralat HTTP.
- Create tests/Feature/MalayCopyTest.php: gerbang salinan Blade dan istilah dilarang.
- Create tests/Feature/HomepageChatbotTest.php: seeder, slug, domain dan pemuatan widget homepage.
- Create database/data/homepage_chatbot_knowledge.php: tepat 33 soal jawab rasmi.
- Create database/seeders/HomepageChatbotSeeder.php: seeder idempoten yang mengekalkan API key dan log.
- Create database/migrations/2026_07_10_000004_localize_chatbot_defaults.php: backfill tepat nilai lalai Inggeris.
- Create resources/views/errors/403.blade.php, 419.blade.php, 429.blade.php, 500.blade.php dan 503.blade.php.
- Modify config/app.php, config/chatme.php, .env.example dan phpunit.xml.
- Modify bootstrap/app.php untuk respons JSON API yang selamat dalam BM.
- Modify app/Http/Controllers/AuthController.php, ApiController.php, ChatbotController.php, KnowledgeController.php, AdminController.php, LandingController.php dan SubscriptionController.php.
- Modify app/Support/MalaysianPhone.php dan app/Services/ToyyibPay/ToyyibPayClient.php.
- Modify database/seeders/DatabaseSeeder.php dan delete database/seeders/DietKnowledgeSeeder.php.
- Modify database/migrations/2024_01_01_000005_create_chatbots_table.php untuk pemasangan baharu.
- Modify resources/views/landing.blade.php, layouts/guest.blade.php, layouts/app.blade.php, auth/login.blade.php, auth/register.blade.php, dashboard.blade.php, onboarding.blade.php, privacy.blade.php, terms.blade.php, subscription/plans.blade.php, subscription/result.blade.php, chatbots/create.blade.php, chatbots/edit.blade.php, chatbots/embed.blade.php, chatbots/index.blade.php, knowledge/create.blade.php, knowledge/edit.blade.php, knowledge/index.blade.php, admin/dashboard.blade.php, admin/users.blade.php dan admin/chatbots.blade.php.
- Modify public/widget.js dan ujian tests/js/widget-security.test.js.

---

### Task 1: Locale Bahasa Melayu dan mesej rangka kerja

**Files:**
- Create: tests/Feature/MalayLocaleTest.php
- Create: lang/ms/validation.php
- Create: lang/ms/auth.php
- Create: lang/ms/passwords.php
- Create: lang/ms/pagination.php
- Create: lang/ms/chatme.php
- Modify: config/app.php
- Modify: .env.example
- Modify: phpunit.xml
- Modify: app/Http/Controllers/AuthController.php

**Interfaces:**
- Produces: app()->getLocale() === 'ms', fail validasi BM, auth.failed BM dan pagination BM.
- Consumes: konfigurasi Laravel sedia ada dan AuthController.

- [ ] **Step 1: Write the failing locale tests**

Tambah kelas berikut dengan RefreshDatabase:

    public function test_application_and_fallback_locale_are_malay(): void
    {
        $this->assertSame('ms', app()->getLocale());
        $this->assertSame('ms', config('app.fallback_locale'));
        $this->assertSame('ms_MY', config('app.faker_locale'));
    }

    public function test_validation_messages_and_attributes_are_malay(): void
    {
        $validator = validator(
            ['email' => 'bukan-emel', 'password' => 'rahsia', 'password_confirmation' => 'berbeza'],
            ['name' => ['required'], 'email' => ['required', 'email'], 'password' => ['confirmed']]
        );

        $this->assertSame('Ruangan nama wajib diisi.', $validator->errors()->first('name'));
        $this->assertSame('Ruangan alamat e-mel mestilah alamat e-mel yang sah.', $validator->errors()->first('email'));
        $this->assertSame('Pengesahan kata laluan tidak sepadan.', $validator->errors()->first('password'));
    }

    public function test_failed_login_message_is_malay(): void
    {
        $this->post('/login', ['email' => 'tiada@example.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'E-mel atau kata laluan yang dimasukkan tidak sepadan dengan rekod kami.']);
    }

    public function test_pagination_labels_are_malay(): void
    {
        $this->assertSame('Sebelumnya', __('pagination.previous'));
        $this->assertSame('Seterusnya', __('pagination.next'));
    }

- [ ] **Step 2: Verify the tests fail for the current English locale**

Run:

    php artisan test tests/Feature/MalayLocaleTest.php

Expected: FAIL kerana locale en, fail validasi Inggeris dan auth gagal masih menggunakan ayat Inggeris.

- [ ] **Step 3: Add the Malay locale files and configuration**

Set config/app.php defaults:

    'locale' => env('APP_LOCALE', 'ms'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ms'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ms_MY'),

Set .env.example and phpunit.xml:

    APP_NAME=ChatMe
    APP_LOCALE=ms
    APP_FALLBACK_LOCALE=ms
    APP_FAKER_LOCALE=ms_MY

lang/ms/auth.php:

    return [
        'failed' => 'E-mel atau kata laluan yang dimasukkan tidak sepadan dengan rekod kami.',
        'password' => 'Kata laluan yang dimasukkan tidak betul.',
        'throttle' => 'Terlalu banyak percubaan log masuk. Sila cuba lagi dalam :seconds saat.',
    ];

lang/ms/pagination.php:

    return ['previous' => 'Sebelumnya', 'next' => 'Seterusnya'];

lang/ms/passwords.php:

    return [
        'reset' => 'Kata laluan anda telah ditetapkan semula.',
        'sent' => 'Pautan penetapan semula kata laluan telah dihantar.',
        'throttled' => 'Sila tunggu sebelum mencuba lagi.',
        'token' => 'Token penetapan semula kata laluan ini tidak sah.',
        'user' => 'Kami tidak menemui pengguna dengan alamat e-mel tersebut.',
    ];

lang/ms/validation.php menggunakan array berikut bagi semua rule yang dipanggil oleh aplikasi:

    return [
        'array' => 'Ruangan :attribute mestilah dalam bentuk senarai.',
        'boolean' => 'Ruangan :attribute mestilah benar atau palsu.',
        'confirmed' => 'Pengesahan :attribute tidak sepadan.',
        'email' => 'Ruangan :attribute mestilah alamat e-mel yang sah.',
        'in' => 'Nilai :attribute yang dipilih tidak sah.',
        'integer' => 'Ruangan :attribute mestilah nombor bulat.',
        'json' => 'Ruangan :attribute mestilah data JSON yang sah.',
        'max' => [
            'array' => 'Ruangan :attribute tidak boleh mempunyai lebih daripada :max item.',
            'file' => 'Fail :attribute tidak boleh melebihi :max kilobait.',
            'numeric' => 'Nilai :attribute tidak boleh melebihi :max.',
            'string' => 'Ruangan :attribute tidak boleh melebihi :max aksara.',
        ],
        'min' => [
            'array' => 'Ruangan :attribute mesti mempunyai sekurang-kurangnya :min item.',
            'file' => 'Fail :attribute mestilah sekurang-kurangnya :min kilobait.',
            'numeric' => 'Nilai :attribute mestilah sekurang-kurangnya :min.',
            'string' => 'Ruangan :attribute mestilah sekurang-kurangnya :min aksara.',
        ],
        'numeric' => 'Ruangan :attribute mestilah nombor.',
        'regex' => 'Format ruangan :attribute tidak sah.',
        'required' => 'Ruangan :attribute wajib diisi.',
        'size' => [
            'array' => 'Ruangan :attribute mesti mengandungi :size item.',
            'file' => 'Fail :attribute mestilah bersaiz :size kilobait.',
            'numeric' => 'Nilai :attribute mestilah :size.',
            'string' => 'Ruangan :attribute mestilah :size aksara.',
        ],
        'string' => 'Ruangan :attribute mestilah teks.',
        'unique' => ':attribute ini telah digunakan.',
        'url' => 'Ruangan :attribute mestilah pautan yang sah.',
        'uuid' => 'Ruangan :attribute mestilah UUID yang sah.',
        'custom' => [],
        'attributes' => [

    'name' => 'nama',
    'email' => 'alamat e-mel',
    'password' => 'kata laluan',
    'password_confirmation' => 'pengesahan kata laluan',
    'company' => 'syarikat',
    'website' => 'laman web',
    'message' => 'mesej',
    'session_id' => 'ID sesi',
    'avatar_url' => 'pautan gambar profil',
    'primary_color' => 'warna utama',
    'secondary_color' => 'warna latar',
    'position' => 'kedudukan',
    'welcome_message' => 'mesej alu-aluan',
    'placeholder_text' => 'teks petunjuk',
    'bot_name' => 'nama yang dipaparkan',
    'system_prompt' => 'cara chatbot perlu menjawab',
    'domain_whitelist' => 'laman web yang dibenarkan',
    'question' => 'soalan',
    'answer' => 'jawapan',
    'category' => 'kategori',
    'tags' => 'tag',
    'json_data' => 'data JSON',
    'phone' => 'nombor telefon',
            'plan' => 'pelan',
        ],
    ];

AuthController mesti menggunakan __('auth.failed') dan tidak menyimpan ayat Inggeris literal.

- [ ] **Step 4: Verify locale tests pass**

Run:

    php artisan test tests/Feature/MalayLocaleTest.php

Expected: 4 tests pass dan tiada ayat Inggeris pada output pengguna.

- [ ] **Step 5: Commit locale foundation**

    git add .env.example phpunit.xml config/app.php lang/ms app/Http/Controllers/AuthController.php tests/Feature/MalayLocaleTest.php
    git commit -m "feat: make Bahasa Melayu the application locale"

---

### Task 2: Salinan halaman awam, akaun dan ralat

**Files:**
- Create: tests/Feature/MalayCopyTest.php
- Create: resources/views/errors/403.blade.php
- Create: resources/views/errors/419.blade.php
- Create: resources/views/errors/429.blade.php
- Create: resources/views/errors/500.blade.php
- Create: resources/views/errors/503.blade.php
- Modify: resources/views/landing.blade.php
- Modify: resources/views/layouts/guest.blade.php
- Modify: resources/views/auth/login.blade.php
- Modify: resources/views/auth/register.blade.php
- Modify: resources/views/privacy.blade.php
- Modify: resources/views/terms.blade.php
- Modify: resources/views/onboarding.blade.php

**Interfaces:**
- Produces: halaman awam dan halaman ralat BM mesra-neutral.
- Consumes: locale Task 1 dan pelan harga sebenar.

- [ ] **Step 1: Write failing public-copy tests**

MalayCopyTest perlu merender /, /login, /register, /privacy dan /terms selepas PlanSeeder, kemudian menyatukan HTML dan membuat assertion berikut:

    foreach (['Buka dashboard', 'Platform SaaS', 'Kod benam', 'server', 'auto-debit', 'Teks Placeholder'] as $forbidden) {
        $this->assertStringNotContainsString($forbidden, $html);
    }

    foreach (['Buka papan pemuka', 'Kod pemasangan ChatMe', 'Platform chatbot buatan Malaysia', 'tiada potongan automatik'] as $required) {
        $this->assertStringContainsString($required, $html);
    }

Tambah data provider bagi 403, 404, 419, 429, 500 dan 503 yang merender view errors.<status> dan mengesahkan html lang=ms, satu h1 BM, penerangan BM dan pautan pemulihan.

- [ ] **Step 2: Verify public-copy tests fail**

Run:

    php artisan test tests/Feature/MalayCopyTest.php

Expected: FAIL pada dashboard, SaaS, kod benam, server, auto-debit dan halaman ralat yang belum wujud.

- [ ] **Step 3: Replace public copy with the approved standard**

Gunakan ayat tepat berikut pada landing:

    Masukkan maklumat anda sendiri, sesuaikan rupa chatbot mengikut jenama anda, kemudian pasangkannya pada laman web dengan satu baris kod.
    Kod pemasangan ChatMe
    Semua yang anda perlukan untuk chatbot laman web anda
    Maklumat yang anda tentukan
    Rupa chatbot mengikut jenama
    Pasang dengan satu kod ringkas
    Kawal tempat chatbot digunakan
    Mulakan dengan pelan Free. Pelan berbayar perlu diperbaharui setiap bulan melalui ToyyibPay.
    Pelan perlu diperbaharui secara manual setiap bulan; tiada potongan automatik daripada akaun bank.

Gunakan salinan tepat berikut pada halaman lain:

    layouts/guest: Platform chatbot buatan Malaysia.
    layouts/guest: Bina chatbot menggunakan maklumat anda sendiri dan pasangkannya pada laman web anda.
    auth/login: Teruskan log masuk pada peranti ini
    auth/login: Belum ada akaun?
    auth/register: Sila semak dan betulkan ruangan berikut.
    privacy: Kami menggunakan nombor telefon bagi urusan pembayaran serta menyimpan kandungan dan bahan rujukan chatbot yang anda masukkan.
    privacy: Data digunakan untuk menjalankan fungsi chatbot, mengurus langganan, melindungi akaun dan memenuhi kewajipan undang-undang.
    privacy: ChatMe tidak menyimpan maklumat log masuk atau butiran keselamatan perbankan anda.
    privacy: Kami menggunakan langkah keselamatan teknikal dan pengurusan yang sewajarnya.
    privacy: Namun, kami mungkin perlu menyimpan sesetengah rekod untuk tujuan keselamatan, pembayaran atau undang-undang.
    terms: Anda bertanggungjawab terhadap maklumat yang anda masukkan atau import, serta jawapan yang diberikan oleh chatbot.
    terms: Setiap pembayaran pelan berbayar memberikan akses selama satu bulan selepas pembayaran disahkan oleh ToyyibPay.
    terms: Pelan tidak diperbaharui secara automatik dan tiada debit automatik daripada akaun bank.
    terms: Jika anda tidak memperbaharui pelan, akaun anda akan kembali menggunakan pelan Free selepas tempoh berbayar tamat.
    terms: ChatMe disediakan dalam keadaan sedia ada, setakat yang dibenarkan oleh undang-undang.
    onboarding: Mari cipta chatbot AI pertama anda. Proses ini biasanya mengambil masa kira-kira 2 minit.
    onboarding: Ke papan pemuka

Setiap halaman ralat menggunakan layouts.guest, kod status, h1 dan tindakan berikut:

    403: Akses tidak dibenarkan / Kembali ke halaman utama
    419: Sesi telah tamat / Log masuk semula
    429: Terlalu banyak permintaan / Cuba lagi sebentar lagi
    500: Sistem menghadapi masalah / Kembali ke halaman utama
    503: Sistem sedang diselenggara / Cuba lagi kemudian

- [ ] **Step 4: Verify public-copy tests pass**

Run:

    php artisan test tests/Feature/MalayCopyTest.php tests/Feature/LightThemeTest.php

Expected: semua ujian lulus.

- [ ] **Step 5: Commit public copy**

    git add resources/views/landing.blade.php resources/views/layouts/guest.blade.php resources/views/auth resources/views/privacy.blade.php resources/views/terms.blade.php resources/views/onboarding.blade.php resources/views/errors tests/Feature/MalayCopyTest.php
    git commit -m "fix: simplify public Bahasa Melayu copy"

---

### Task 3: Salinan aplikasi, borang dan pentadbir

**Files:**
- Modify: resources/views/layouts/app.blade.php
- Modify: resources/views/dashboard.blade.php
- Modify: resources/views/chatbots/*.blade.php
- Modify: resources/views/knowledge/*.blade.php
- Modify: resources/views/admin/*.blade.php
- Modify: app/Http/Controllers/ChatbotController.php
- Modify: app/Http/Controllers/KnowledgeController.php
- Modify: app/Http/Controllers/AdminController.php
- Modify: tests/Feature/ManagementFormAccessibilityTest.php
- Modify: tests/Feature/KnowledgeImportTest.php
- Modify: tests/Feature/LightThemeTest.php

**Interfaces:**
- Produces: istilah papan pemuka, soal jawab, kod pemasangan dan pentadbir yang konsisten.
- Consumes: mesej validasi BM Task 1.

- [ ] **Step 1: Add failing application-copy assertions**

Tambah assertion sumber/HTML bahawa teks berikut tidak wujud:

    Papan Pemuka
    Chatbot Baru
    Edit Chatbot
    Teks Placeholder
    URL Avatar
    Senarai Putih Domain
    Kod Benam
    Pangkalan Pengetahuan
    Item Pengetahuan
    Buang Admin
    Jadikan Admin
    N/A

Tambah assertion teks pengganti:

    Papan pemuka
    Cipta chatbot baharu
    Sunting chatbot
    Teks petunjuk dalam kotak mesej
    Pautan gambar profil
    Laman web yang dibenarkan
    Kod pemasangan
    Soal jawab chatbot
    Pentadbir
    Tiada maklumat

Tambah ujian bahawa borang knowledge/create dan knowledge/edit mengandungi aria-invalid, aria-describedby dan field-error bagi question dan answer.

- [ ] **Step 2: Verify application-copy assertions fail**

Run:

    php artisan test tests/Feature/ManagementFormAccessibilityTest.php tests/Feature/KnowledgeImportTest.php tests/Feature/LightThemeTest.php

Expected: FAIL pada istilah lama dan pautan ralat borang yang belum ada.

- [ ] **Step 3: Implement the exact application terminology**

Gunakan sentence case pada tajuk. Tukar:

    Chatbot Anda -> Chatbot saya
    + Chatbot Baru -> + Cipta chatbot baharu
    Pengetahuan -> Soal jawab
    Benam -> Pasang di laman web
    Edit Chatbot -> Sunting chatbot
    Nama Paparan Bot -> Nama yang dipaparkan kepada pengunjung
    Teks Placeholder -> Teks petunjuk dalam kotak mesej
    URL Avatar -> Pautan gambar profil
    Posisi -> Kedudukan pada skrin
    Arahan Sistem -> Cara chatbot perlu menjawab
    Senarai Putih Domain -> Laman web yang dibenarkan
    Pangkalan Pengetahuan -> Soal jawab chatbot
    Import Pengetahuan -> Import soal jawab
    Item Pengetahuan -> Soal jawab
    Admin -> Pentadbir
    Email -> E-mel
    N/A -> Tiada maklumat

Gunakan salinan pengesahan dan keadaan kosong tepat berikut:

    Padam chatbot ":name"? Tindakan ini tidak boleh dibatalkan.
    Padam soal jawab ini? Tindakan ini tidak boleh dibatalkan.
    Jana semula kunci API? Kunci lama akan berhenti berfungsi serta-merta dan kod pemasangan di laman web perlu dikemas kini.
    Tarik balik peranan pentadbir :name? Pengguna ini tidak lagi boleh mengakses panel pentadbir.
    Jadikan :name sebagai pentadbir? Pengguna ini akan mendapat akses ke panel pentadbir.
    Adakah anda pasti mahu log keluar? Sesi anda akan ditamatkan.
    Sahkan tindakan
    Adakah anda pasti mahu meneruskan?
    Teruskan
    Belum ada pengguna berdaftar.
    Belum ada chatbot dicipta.
    Tiada pengguna ditemui.
    Tiada chatbot ditemui.

Tambah @error pada question, answer, category dan tags dalam knowledge/create serta knowledge/edit. Setiap input menggunakan aria-invalid=true dan aria-describedby kepada ID field-error unik apabila ralat wujud.

Controller messages:

    Anda telah mencapai had chatbot bagi pelan semasa.
    Penampilan chatbot berjaya dikemas kini.
    Soal jawab berjaya ditambah.
    Soal jawab berjaya dikemas kini.
    Soal jawab berjaya dipadam.
    Import ini melebihi had soal jawab pelan anda.
    :count soal jawab berjaya diimport.
    Anda tidak boleh menukar peranan pentadbir anda sendiri.

- [ ] **Step 4: Verify application tests pass**

Run:

    php artisan test tests/Feature/ManagementFormAccessibilityTest.php tests/Feature/KnowledgeImportTest.php tests/Feature/LightThemeTest.php

Expected: semua ujian lulus.

- [ ] **Step 5: Commit application copy**

    git add resources/views/layouts/app.blade.php resources/views/dashboard.blade.php resources/views/chatbots resources/views/knowledge resources/views/admin app/Http/Controllers/ChatbotController.php app/Http/Controllers/KnowledgeController.php app/Http/Controllers/AdminController.php tests/Feature
    git commit -m "fix: standardize application Bahasa Melayu copy"

---

### Task 4: API, widget dan ralat JSON

**Files:**
- Modify: app/Http/Controllers/ApiController.php
- Modify: app/Support/MalaysianPhone.php
- Modify: app/Services/ToyyibPay/ToyyibPayClient.php
- Modify: bootstrap/app.php
- Modify: public/widget.js
- Modify: tests/Feature/WidgetApiSecurityTest.php
- Modify: tests/Feature/PlanLimitTest.php
- Modify: tests/js/widget-security.test.js
- Test: tests/Feature/MalayLocaleTest.php

**Interfaces:**
- Produces: JSON error BM, fallback chatbot BM dan widget BM.
- Preserves: key error, HTTP 403/422/429/500, CORS, throttling dan keselamatan innerHTML.

- [ ] **Step 1: Change tests to the approved Malay API contract**

Ubah jangkaan:

    Domain not allowed -> Domain ini tidak dibenarkan.
    Monthly message limit reached -> Had mesej bulanan telah dicapai.

Tambah ujian widget JS:

    assert.match(source, /Pembantu ChatMe/);
    assert.match(source, /Helo! Bagaimana saya boleh membantu anda\?/);
    assert.match(source, /Taip mesej anda\.\.\./);
    assert.match(source, /Sedia membantu/);
    assert.match(source, /Disediakan oleh/);
    assert.doesNotMatch(source, /Powered by|Type your message|>Online</);

Tambah ujian API 404 dan 500 dalam production mode memulangkan key error dengan mesej BM selamat tanpa exception class, stack trace atau path.

- [ ] **Step 2: Verify the new API/widget tests fail**

Run:

    php artisan test tests/Feature/WidgetApiSecurityTest.php tests/Feature/PlanLimitTest.php tests/Feature/MalayLocaleTest.php
    npm test

Expected: FAIL pada rentetan English semasa.

- [ ] **Step 3: Implement Malay API and widget copy**

ApiController errors:

    return response()->json(['error' => __('chatme.api.domain_forbidden')], 403);
    return response()->json(['error' => __('chatme.api.monthly_limit')], 429);

Empat fallback:

    Maaf, saya belum pasti jawapannya. Cuba tanya dengan cara lain atau berikan maklumat yang lebih khusus.
    Soalan yang bagus! Boleh berikan sedikit lagi maklumat supaya saya dapat membantu?
    Saya sedia membantu. Boleh jelaskan dengan lebih lanjut perkara yang anda ingin tahu?
    Maaf, saya belum menemui jawapan yang tepat. Cuba gunakan perkataan lain.

Widget defaults:

    config.botName = config.botName || 'Pembantu ChatMe';
    config.welcomeMessage = config.welcomeMessage || 'Helo! Bagaimana saya boleh membantu anda?';
    config.placeholderText = config.placeholderText || 'Taip mesej anda...';
    status: Sedia membantu
    branding: Disediakan oleh ChatMe

ToyyibPay bill fallback ialah Pelan ChatMe. MalaysianPhone exception ialah Nombor telefon mudah alih Malaysia yang sah diperlukan.

bootstrap/app.php render hanya API HttpException/Throwable kepada:

    404 -> Sumber yang diminta tidak ditemui.
    419 -> Sesi telah tamat. Sila muat semula halaman.
    429 -> Terlalu banyak permintaan. Sila cuba lagi sebentar lagi.
    default 500 -> Sistem menghadapi masalah. Sila cuba lagi sebentar lagi.

- [ ] **Step 4: Verify API/widget tests pass**

Run:

    php artisan test tests/Feature/WidgetApiSecurityTest.php tests/Feature/PlanLimitTest.php tests/Feature/MalayLocaleTest.php
    npm test

Expected: semua ujian lulus dan keselamatan widget kekal hijau.

- [ ] **Step 5: Commit API/widget copy**

    git add app/Http/Controllers/ApiController.php app/Support/MalaysianPhone.php app/Services/ToyyibPay/ToyyibPayClient.php bootstrap/app.php public/widget.js lang/ms/chatme.php tests
    git commit -m "fix: localize widget and API responses"

---

### Task 5: Langganan, pembayaran dan tarikh Melayu

**Files:**
- Modify: resources/views/subscription/plans.blade.php
- Modify: resources/views/subscription/result.blade.php
- Modify: app/Http/Controllers/SubscriptionController.php
- Modify: tests/Feature/SubscriptionPlanTest.php
- Modify: tests/Feature/ToyyibPayReturnTest.php

**Interfaces:**
- Produces: salinan pembayaran yang menerangkan pembaharuan manual dan status dengan jelas.
- Preserves: harga server-side, checkout, callback, reconciliation dan proration integer.

- [ ] **Step 1: Write failing subscription-copy tests**

Gantikan expectation lama dengan:

    Pembaharuan dibuat secara manual setiap bulan; tiada potongan automatik daripada akaun bank.
    Nilai bagi baki tempoh pelan semasa akan digunakan sebagai kredit untuk pelan baharu.
    Akaun anda akan kembali kepada pelan Free selepas akses berbayar tamat.
    Perbaharui untuk sebulan
    Langgan melalui FPX
    Pembayaran tidak berjaya
    Kami akan mengemas kini status sebaik sahaja pengesahan diterima daripada ToyyibPay.

Freeze tarikh pada Disember dan assert output mengandungi Disember, bukan Dec.

- [ ] **Step 2: Verify subscription-copy tests fail**

Run:

    php artisan test tests/Feature/SubscriptionPlanTest.php tests/Feature/ToyyibPayReturnTest.php

Expected: FAIL pada prorata, server, belum berjaya dan bulan English.

- [ ] **Step 3: Implement subscription copy without changing logic**

Gunakan ayat tepat daripada Step 1. Gunakan translatedFormat('j F Y, H:i') untuk tarikh akses. Gantikan Cuba pembayaran baharu dengan Cuba bayar semula. Kekalkan FPX/DuitNow QR dinamik, harga dan semua syarat Blade.

- [ ] **Step 4: Verify payment tests**

Run:

    php artisan test tests/Feature/SubscriptionPlanTest.php tests/Feature/ToyyibPayReturnTest.php tests/Feature/PaymentActivationTest.php tests/Feature/ToyyibPayCallbackTest.php tests/Feature/ToyyibPayCheckoutTest.php

Expected: semua ujian lulus, termasuk logik pembayaran.

- [ ] **Step 5: Commit subscription copy**

    git add resources/views/subscription app/Http/Controllers/SubscriptionController.php tests/Feature/SubscriptionPlanTest.php tests/Feature/ToyyibPayReturnTest.php
    git commit -m "fix: clarify Malay subscription and payment copy"

---

### Task 6: Chatbot rasmi dan widget homepage

**Files:**
- Create: database/data/homepage_chatbot_knowledge.php
- Create: database/seeders/HomepageChatbotSeeder.php
- Create: tests/Feature/HomepageChatbotTest.php
- Modify: database/seeders/DatabaseSeeder.php
- Delete: database/seeders/DietKnowledgeSeeder.php
- Modify: config/chatme.php
- Modify: .env.example
- Modify: app/Http/Controllers/LandingController.php
- Modify: resources/views/landing.blade.php

**Interfaces:**
- Produces: Chatbot slug chatme-homepage, tepat 33 knowledge items, domain whitelist dan homepageChatbot.
- Preserves: API key, chatbot ID, owner ID dan chat_logs bagi rekod ChatMe Assistant sedia ada.

- [ ] **Step 1: Write failing seeder and homepage tests**

HomepageChatbotTest mesti:

1. Seed PlanSeeder dan cipta admin serta ChatMe Assistant dengan API key tetap TEST_KEY, satu knowledge item lama dan satu chat log.
2. Jalankan HomepageChatbotSeeder dua kali.
3. Assert satu chatbot sahaja mempunyai slug chatme-homepage, API key TEST_KEY, owner sama, 33 knowledge items, domain chatme.akmalmarvis.com dan chat log kekal.
4. Assert semua 33 item aktif dan tiada regex perkataan rojak berikut pada gabungan question dan answer; tags boleh menyimpan sinonim carian:

    /\b(?:tak|nak|je|ni|tu|lepas tu|website|setup|support|custom|coding|client|ready|upgrade|plan)\b/i

5. GET / dan assert response mengandungi URL widget dengan API key model tetapi fail sumber landing.blade.php tidak mengandungi TEST_KEY atau sebarang cm_ literal.
6. Nyahaktifkan bot, GET /, assert halaman 200 dan URL widget tiada.
7. Origin chatme.akmalmarvis.com diterima; attacker.test ditolak.

- [ ] **Step 2: Verify homepage tests fail**

Run:

    php artisan test tests/Feature/HomepageChatbotTest.php

Expected: FAIL kerana seeder, data dan widget homepage belum wujud.

- [ ] **Step 3: Add canonical configuration and seeder**

config/chatme.php:

    'homepage_chatbot' => [
        'slug' => env('CHATME_HOMEPAGE_CHATBOT_SLUG', 'chatme-homepage'),
        'allowed_domains' => env('CHATME_HOMEPAGE_CHATBOT_DOMAINS', 'chatme.akmalmarvis.com'),
    ],

HomepageChatbotSeeder transaction:

    $chatbot = Chatbot::query()
        ->where('slug', $slug)
        ->orWhere(function ($query): void {
            $query->where('name', 'ChatMe Assistant')
                ->whereHas('user', fn ($user) => $user->where('is_admin', true));
        })
        ->lockForUpdate()
        ->first();

Jika tiada, cipta pengguna sistem dengan e-mel homepage-bot@chatme.invalid dan kata laluan rawak 64 aksara yang tidak dipaparkan. Cipta langganan Enterprise provider system, provider_reference homepage-chatbot-system, status active, starts_at now dan ends_at now()->addYears(100); gunakan firstOrCreate supaya rerun tidak memanjangkan tempoh. Kemudian cipta chatbot baharu. Jika chatbot rasmi sedia ada ditemui, kekalkan user_id, id dan api_key serta jangan ubah langganan pemilik. Update medan kanonik, delete knowledgeItems chatbot rasmi sahaja dan createMany data 33 item. Jangan delete chatLogs.

DatabaseSeeder memanggil PlanSeeder dan HomepageChatbotSeeder. DietKnowledgeSeeder dibuang supaya pemasangan baharu tidak memasukkan demo Inggeris.

database/data/homepage_chatbot_knowledge.php memulangkan tepat 33 rekod berikut. Setiap baris menggunakan urutan question, answer, category dan tags:

1.

    Helo
    Helo! Selamat datang ke ChatMe. Saya Pembantu ChatMe dari Kuala Lumpur. Anda boleh bertanya tentang harga, cara menggunakan ChatMe atau cara memasang chatbot pada laman web.
    Umum
    helo,hai,hi,hello

2.

    Apa itu ChatMe?
    ChatMe ialah platform chatbot buatan Malaysia yang membantu anda membina chatbot untuk laman web. Anda boleh menambah soal jawab sendiri, menyesuaikan rupa chatbot dan memasangnya menggunakan satu kod ringkas.
    Perihal
    chatme,tentang,pengenalan,chatbot

3.

    Bagaimana ChatMe berfungsi?
    Daftar akaun, cipta chatbot, tambah soalan berserta jawapannya, sesuaikan rupa, kemudian salin kod pemasangan ke laman web anda. Chatbot akan menggunakan maklumat yang anda masukkan untuk menjawab soalan pelawat.
    Cara guna
    cara,guna,langkah,berfungsi

4.

    Berapakah harga ChatMe?
    ChatMe mempunyai tiga pelan: Free pada RM0, Pro pada RM49 sebulan dan Enterprise pada RM149 sebulan. Pelan berbayar perlu diperbaharui secara manual setiap bulan melalui ToyyibPay; tiada potongan automatik daripada akaun bank.
    Harga
    harga,kos,bayaran,free,pro,enterprise

5.

    Adakah ChatMe mempunyai pelan percuma?
    Ya. Pelan Free berharga RM0 dan tidak memerlukan kad atau bayaran. Pelan ini menyediakan satu chatbot, sehingga 50 soal jawab dan sehingga 500 mesej sebulan.
    Harga
    percuma,free,cuba,kad,bayaran

6.

    Bagaimana saya mencipta chatbot?
    Selepas log masuk, buka Papan pemuka dan pilih Cipta chatbot baharu. Isi nama chatbot, nama yang dipaparkan, mesej alu-aluan dan tetapan lain, kemudian simpan.
    Cara guna
    cipta,bina,chatbot,baharu

7.

    Bagaimana saya menambah soal jawab?
    Buka chatbot anda dan pilih Soal jawab. Tambah soalan yang mungkin ditanya oleh pelawat berserta jawapannya. Anda juga boleh menambah kategori dan tag untuk membantu padanan.
    Cara guna
    soal jawab,maklumat,tambah,latih

8.

    Bagaimana saya memasang ChatMe pada laman web?
    Buka chatbot anda dan pilih Pasang di laman web. Salin kod pemasangan, kemudian tampalkannya sebelum baris penutup body dalam fail HTML. Jika anda tidak mengurus kod laman web sendiri, berikan kod tersebut kepada pembangun laman web.
    Pemasangan
    pasang,kod,html,script,embed

9.

    Bolehkah saya mengubah rupa chatbot?
    Ya. Anda boleh menukar nama yang dipaparkan, gambar profil, warna utama, mesej alu-aluan, teks petunjuk dan kedudukan chatbot pada skrin.
    Penyesuaian
    rupa,warna,gambar,profil,kedudukan

10.

    Apakah kunci API?
    Kunci API ialah pengecam unik yang digunakan oleh kod pemasangan untuk memuatkan chatbot anda. Jika kunci dijana semula, kod pemasangan lama akan berhenti berfungsi dan perlu dikemas kini.
    Keselamatan
    api,kunci,token,keselamatan

11.

    ChatMe dibangunkan di mana?
    ChatMe dibangunkan dan diuruskan dari Kuala Lumpur, Malaysia. Produk ini direka untuk memudahkan perniagaan dan organisasi memasang chatbot pada laman web mereka.
    Perihal
    kuala lumpur,malaysia,tempatan,asal

12.

    Mengapakah saya patut memilih ChatMe?
    ChatMe menawarkan harga dalam Ringgit Malaysia, pelan Free, antaramuka Bahasa Melayu dan pemasangan menggunakan satu kod ringkas. Anda juga mengawal sendiri soal jawab yang digunakan oleh chatbot.
    Perihal
    kelebihan,pilih,ringgit,mudah

13.

    Siapakah yang sesuai menggunakan ChatMe?
    ChatMe sesuai untuk perniagaan kecil, kedai dalam talian, agensi, restoran, institusi pendidikan, pembangun laman web dan organisasi yang mahu menjawab soalan pelawat secara automatik.
    Perihal
    sesuai,perniagaan,agensi,pendidikan

14.

    Bagaimanakah ChatMe melindungi data saya?
    ChatMe menggunakan langkah keselamatan teknikal dan pengurusan yang sewajarnya. Pembayaran diproses oleh ToyyibPay dan ChatMe tidak menyimpan maklumat log masuk atau butiran keselamatan perbankan anda. Sila rujuk halaman Privasi untuk maklumat lanjut.
    Keselamatan
    data,privasi,selamat,toyyibpay

15.

    Bagaimana saya menukar pelan?
    Buka Pelan langganan, pilih Pro atau Enterprise, masukkan nombor telefon Malaysia yang sah dan teruskan ke ToyyibPay. Pelan baharu hanya diaktifkan selepas pembayaran disahkan.
    Harga
    tukar,naik taraf,pro,enterprise

16.

    Adakah langganan diperbaharui secara automatik?
    Tidak. Pembaharuan dibuat secara manual setiap bulan melalui ToyyibPay. Tiada debit automatik atau potongan automatik daripada akaun bank.
    Pembayaran
    pembaharuan,manual,auto debit,toyyibpay

17.

    Apakah yang berlaku apabila had mesej dicapai?
    Chatbot tidak dapat menerima mesej baharu apabila had bulanan pelan telah dicapai. Had dikira semula pada bulan berikutnya, atau anda boleh memilih pelan dengan had yang lebih tinggi.
    Harga
    had,mesej,kuota,bulanan

18.

    Bolehkah chatbot digunakan pada beberapa laman web?
    Ya, mengikut had chatbot pelan anda. Anda boleh menyenaraikan domain yang dibenarkan untuk mengawal laman web yang boleh menggunakan setiap chatbot.
    Pemasangan
    domain,laman web,banyak,whitelist

19.

    Bagaimana saya menyahaktifkan chatbot?
    Buka halaman Sunting chatbot, kosongkan pilihan Aktifkan chatbot dan simpan. Chatbot boleh diaktifkan semula kemudian tanpa memadam soal jawabnya.
    Cara guna
    nyahaktif,aktif,matikan,hidupkan

20.

    Bagaimana saya memadam chatbot?
    Buka senarai Chatbot saya dan pilih Padam pada chatbot berkenaan. Tindakan ini tidak boleh dibatalkan serta akan memadam soal jawab dan sejarah sembang chatbot tersebut.
    Cara guna
    padam,buang,chatbot

21.

    Bolehkah saya mengimport soal jawab?
    Ya. Buka Soal jawab chatbot dan pilih Import soal jawab JSON. Setiap rekod JSON mesti mempunyai question untuk soalan dan answer untuk jawapan.
    Cara guna
    import,json,soal jawab

22.

    Platform laman web apakah yang serasi dengan ChatMe?
    ChatMe boleh digunakan pada laman web yang membenarkan kod JavaScript, termasuk WordPress, Shopify, Wix dan laman HTML tersuai. Cara memasang kod mungkin berbeza mengikut platform.
    Pemasangan
    wordpress,shopify,wix,html,javascript

23.

    Bagaimana saya menghubungi sokongan ChatMe?
    Untuk bantuan, hantar e-mel kepada hello@akmalmarvis.com. Sertakan penerangan masalah dan nama chatbot supaya kami dapat membantu dengan lebih cepat.
    Sokongan
    sokongan,bantuan,hubungi,email

24.

    Berapa lama masa yang diperlukan untuk menyediakan chatbot?
    Masa bergantung pada jumlah soal jawab dan akses kepada kod laman web anda. Chatbot asas biasanya boleh disediakan dengan cepat selepas soal jawab dan kod pemasangan tersedia.
    Cara guna
    masa,sedia,cepat,pemasangan

25.

    Bolehkah ChatMe menggunakan bahasa selain Bahasa Melayu?
    Ya. Chatbot boleh menggunakan bahasa yang anda masukkan dalam soal jawabnya. Anda boleh menyediakan kandungan dalam Bahasa Melayu, Inggeris, Mandarin, Tamil atau bahasa lain.
    Perihal
    bahasa,melayu,english,mandarin,tamil

26.

    Apakah perbezaan pelan Pro dan Enterprise?
    Pro menyediakan sehingga lima chatbot, 500 soal jawab dan 10,000 mesej sebulan serta akses API. Enterprise menyediakan chatbot, soal jawab dan mesej tanpa had, akses API dan pilihan untuk menyembunyikan jenama ChatMe.
    Harga
    pro,enterprise,beza,banding

27.

    Assalamualaikum
    Waalaikumsalam. Selamat datang ke ChatMe. Apakah yang ingin anda ketahui tentang platform chatbot kami?
    Umum
    assalamualaikum,salam

28.

    Selamat pagi
    Selamat pagi! Apakah yang boleh saya bantu tentang ChatMe hari ini?
    Umum
    pagi,selamat pagi,morning

29.

    Selamat petang
    Selamat petang! Anda boleh bertanya tentang harga, cara menggunakan ChatMe atau pemasangan chatbot.
    Umum
    petang,selamat petang,evening

30.

    Selamat malam
    Selamat malam! Apakah yang boleh saya bantu tentang ChatMe?
    Umum
    malam,selamat malam,night

31.

    Terima kasih
    Sama-sama. Jika ada soalan lain tentang ChatMe, saya sedia membantu.
    Umum
    terima kasih,thanks,tq

32.

    Adakah ChatMe mahal?
    Anda boleh bermula dengan pelan Free pada RM0. Pelan Pro berharga RM49 sebulan dan Enterprise RM149 sebulan. Pilih pelan berdasarkan jumlah chatbot, soal jawab dan mesej yang anda perlukan.
    Harga
    mahal,murah,harga,bajet

33.

    Bagaimana saya mendaftar?
    Buka halaman Daftar percuma, isi nama, alamat e-mel dan kata laluan, kemudian pilih Cipta akaun. Selepas log masuk, anda boleh mencipta chatbot pertama anda.
    Cara guna
    daftar,akaun,register,sign up

- [ ] **Step 4: Load the widget conditionally on the homepage**

LandingController:

    $homepageChatbot = Chatbot::query()
        ->where('slug', config('chatme.homepage_chatbot.slug'))
        ->where('is_active', true)
        ->first();

    return view('landing', compact('plans', 'homepageChatbot'));

landing.blade.php selepas skrip navigasi:

    @if($homepageChatbot)
        <script defer src="{{ route('widget.script', ['chatbot' => $homepageChatbot->api_key]) }}"></script>
    @endif

Jangan panggil toggle dan jangan auto-buka.

- [ ] **Step 5: Verify homepage tests pass**

Run:

    php artisan test tests/Feature/HomepageChatbotTest.php tests/Feature/WidgetApiSecurityTest.php
    npm test

Expected: semua ujian lulus.

- [ ] **Step 6: Commit homepage chatbot**

    git add config/chatme.php .env.example database/data database/seeders app/Http/Controllers/LandingController.php resources/views/landing.blade.php tests/Feature/HomepageChatbotTest.php
    git rm database/seeders/DietKnowledgeSeeder.php
    git commit -m "feat: add official ChatMe chatbot to homepage"

---

### Task 7: Backfill nilai lalai Inggeris tanpa menyentuh kandungan pengguna

**Files:**
- Create: database/migrations/2026_07_10_000004_localize_chatbot_defaults.php
- Modify: database/migrations/2024_01_01_000005_create_chatbots_table.php
- Create: tests/Feature/MalayDefaultMigrationTest.php

**Interfaces:**
- Produces: default BM bagi pemasangan baharu dan exact-match backfill bagi production.
- Preserves: semua nilai tersuai pengguna.

- [ ] **Step 1: Write failing migration test**

Cipta dua chatbot: satu menggunakan exact English defaults dan satu dengan teks tersuai. Jalankan migration up. Assert chatbot pertama menjadi:

    Helo! Bagaimana saya boleh membantu anda?
    Taip mesej anda...

Assert chatbot tersuai tidak berubah. Jalankan down dan assert ia tidak mengembalikan teks Inggeris.

- [ ] **Step 2: Verify migration test fails**

Run:

    php artisan test tests/Feature/MalayDefaultMigrationTest.php

Expected: FAIL kerana migration belum wujud.

- [ ] **Step 3: Implement exact-match forward-only backfill**

Migration up hanya update:

    Hello! How can I help you today?
    Hello! How can I help you?
    Type your message...

Migration down tidak mengubah data. Tukar default migration asal kepada teks BM untuk RefreshDatabase dan pemasangan baharu.

- [ ] **Step 4: Verify migration test and full schema**

Run:

    php artisan test tests/Feature/MalayDefaultMigrationTest.php
    php artisan migrate:fresh --seed --env=testing --force

Expected: migration test lulus; seed menghasilkan tiga pelan, satu chatbot homepage dan 33 soal jawab.

- [ ] **Step 5: Commit data localization**

    git add database/migrations tests/Feature/MalayDefaultMigrationTest.php
    git commit -m "fix: localize legacy chatbot defaults safely"

---

### Task 8: Audit akhir, build dan deployment production

**Files:**
- Modify only if deterministic build refreshes public/css/app.css or public/build assets.
- No source changes are allowed after the final verification pass without restarting verification.

**Interfaces:**
- Consumes: Tasks 1-7.
- Produces: verified commit on origin/main and production.

- [ ] **Step 1: Run the complete local verification**

Run sequentially:

    php artisan optimize:clear
    php artisan test
    vendor/bin/pint --test
    git diff --check
    npm test
    npm audit --audit-level=high
    composer validate --strict
    composer audit --no-interaction
    npm run build

Expected: all pass, PHP count is at least the prior 160 tests plus new tests, JS count is at least prior 2 plus new assertions, and audits report no vulnerabilities/advisories.

- [ ] **Step 2: Run the static Malay audit**

Search user-facing files for exact forbidden text:

    rg -n "Buka dashboard|Platform SaaS|Kod benam|Teks Placeholder|URL Avatar|Senarai Putih Domain|Pangkalan Pengetahuan|Item Pengetahuan|Powered by|>Online<|Domain not allowed|Monthly message limit reached|The provided credentials|dikreditkan secara prorata|pengesahan server|parameter pulangan pelayar" resources/views app/Http public/widget.js lang database/data

Expected: no matches except test fixtures that explicitly assert absence.

- [ ] **Step 3: Commit deterministic build output if changed**

    git status --short
    git add public/css/app.css public/build
    git commit -m "build: refresh localized production assets"

Skip the commit only when git status is already clean.

- [ ] **Step 4: Create a production backup**

Create a timestamped server backup containing source archive, env snapshot, database dump, release metadata and SHA256SUMS. Verify every checksum before deployment.

- [ ] **Step 5: Push and deploy the exact commit**

Push the verified branch commit to origin/main with an exact lease. On production, fetch the commit, verify SHA, install production dependencies if lockfiles changed, run:

    php artisan down
    php artisan migrate --force
    php artisan db:seed --class=HomepageChatbotSeeder --force
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan up

Set production APP_LOCALE=ms, APP_FALLBACK_LOCALE=ms, APP_FAKER_LOCALE=ms_MY, CHATME_HOMEPAGE_CHATBOT_SLUG=chatme-homepage dan CHATME_HOMEPAGE_CHATBOT_DOMAINS=chatme.akmalmarvis.com before config cache.

- [ ] **Step 6: Browser QA**

Use the in-app Browser first. Verify /, /login, /register, /pricing, /privacy, /terms, authenticated dashboard, chatbot form, knowledge form and admin views. Test 320x900, 390x844 and 1440x900. For every viewport verify no horizontal overflow, no clipped text, no English validation message, console warn/error empty and homepage bubble visible but closed.

Open the homepage chatbot, submit Apa itu ChatMe?, verify a BM answer, close with the button and Escape, and verify forbidden origin returns 403 without new chat logs.

- [ ] **Step 7: Production completion audit**

Verify:

    local HEAD == origin/main == production HEAD
    worktrees clean
    APP_ENV=production
    APP_DEBUG=false
    locale=ms
    migrations pending=0
    homepage chatbot slug=chatme-homepage
    homepage chatbot active=true
    homepage chatbot knowledge count=33
    domain whitelist=chatme.akmalmarvis.com
    owner plan monthly_messages is -1 or at least 100000
    live routes return expected status
    homepage contains widget script
    real chat endpoint returns BM response
    Laravel ERROR/CRITICAL count since deploy is 0
    backup checksums still validate

- [ ] **Step 8: Mark the goal complete**

Only after all evidence above passes, update the active goal to complete and report commit SHA, tests, browser QA, production checks and backup path.
