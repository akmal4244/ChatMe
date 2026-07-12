# ChatMe

ChatMe ialah SaaS chatbot berbilang penyewa berasaskan Laravel 12 dan PHP 8.2. Pemilik akaun boleh membina chatbot, mengurus pangkalan pengetahuan, menguji jawapan, memasang widget pada laman web dan melanggan pelan berbayar melalui ToyyibPay. Jawapan berkeyakinan tinggi datang terus daripada pengetahuan pemilik; Cloudflare Workers AI hanya digunakan sebagai fallback terkawal apabila diaktifkan.

Antara kawalan production yang telah dibina ialah pengesahan e-mel, reset kata laluan, session deadline, token API pembangun sekali papar, pemisahan data mengikut pemilik, widget ticket terikat origin/IP/session, tempahan kuota atomik dan health check tanpa mendedahkan credential.

## Keperluan

- PHP 8.2 atau lebih baharu dengan extension yang diperlukan Laravel;
- Composer 2;
- Node.js dan npm;
- SQLite untuk pembangunan pantas, atau MySQL/MariaDB untuk persekitaran yang dikonfigurasi;
- Git untuk quality gate dan deployment exact-SHA.

## Setup lokal

Jangan timpa `.env` yang sudah mengandungi konfigurasi kerja. Untuk checkout baharu di PowerShell:

```powershell
Copy-Item .env.example .env
New-Item -ItemType File -Force database/database.sqlite
composer run setup
php artisan db:seed
```

Pada Bash:

```bash
cp .env.example .env
touch database/database.sqlite
composer run setup
php artisan db:seed
```

`composer run setup` memasang dependency PHP, menjana `APP_KEY`, menjalankan migrasi, memasang dependency frontend dan membina aset. Seeder umum sesuai untuk lokal: ia menyediakan pelan dan chatbot rasmi homepage. Jangan jalankan seeder umum semasa deployment production; gunakan prosedur eksplisit dalam [runbook production](docs/operations/production-runbook.md).

Jalankan server, queue listener, log viewer dan Vite serentak:

```bash
composer run dev
```

Pilihan berasingan untuk debugging:

```bash
php artisan serve
npm run dev
```

## Konfigurasi persekitaran

Salin nama pemboleh ubah daripada `.env.example`. Jangan commit `.env`, token, password, dump pangkalan data atau output provider.

| Kumpulan | Nama pemboleh ubah |
|---|---|
| Aplikasi | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `CHATME_TIMEZONE`, `APP_MAINTENANCE_DRIVER`, `BCRYPT_ROUNDS` |
| Pangkalan data | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Session/cache/queue/storage | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `CACHE_STORE`, `CACHE_PREFIX`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `BROADCAST_CONNECTION` |
| Log | `LOG_CHANNEL`, `LOG_STACK`, `LOG_DAILY_DAYS`, `LOG_LEVEL` |
| E-mel | `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Pentadbir | `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` |
| Chatbot homepage | `CHATME_HOMEPAGE_CHATBOT_SLUG`, `CHATME_HOMEPAGE_CHATBOT_DOMAINS`, `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID` |
| ToyyibPay | `TOYYIBPAY_BASE_URL`, `TOYYIBPAY_SANDBOX`, `TOYYIBPAY_SECRET_KEY`, `TOYYIBPAY_CATEGORY_CODE`, `TOYYIBPAY_DNQR_ENABLED`, `TOYYIBPAY_TIMEOUT` |
| Cloudflare AI | `CHATME_AI_ENABLED`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_AI_TOKEN`, `CLOUDFLARE_AI_MODEL`, `CLOUDFLARE_AI_TIMEOUT`, `CLOUDFLARE_AI_MAX_TOKENS` |
| Had akaun/pengetahuan | `CHATME_CHATBOT_ABSOLUTE_LIMIT`, `CHATME_CHATBOT_CREATIONS_PER_HOUR`, `CHATME_KNOWLEDGE_ABSOLUTE_LIMIT`, `CHATME_KNOWLEDGE_MATCH_CANDIDATE_LIMIT` |
| Kuota/widget | `CHATME_QUOTA_RESERVATION_TTL_SECONDS`, `CHATME_TESTER_DAILY_AI_LIMIT`, `CHATME_WIDGET_TICKET_TTL_SECONDS`, `CHATME_WIDGET_BOOTSTRAP_PER_MINUTE`, `CHATME_WIDGET_INGRESS_IP_PER_MINUTE`, `CHATME_WIDGET_INGRESS_BOT_PER_MINUTE`, `CHATME_WIDGET_TICKET_PER_MINUTE`, `CHATME_WIDGET_CHATBOT_IP_PER_MINUTE`, `CHATME_WIDGET_BOT_PER_MINUTE`, `CHATME_WIDGET_BOT_DAILY_UNLIMITED`, `CHATME_OWNER_MESSAGE_PER_MINUTE`, `CHATME_OWNER_MESSAGE_DAILY` |
| API pembangun | `CHATME_DEVELOPER_API_IP_PER_MINUTE`, `CHATME_DEVELOPER_API_TOKEN_PER_MINUTE`, `CHATME_DEVELOPER_API_TOKEN_DAILY` |

Untuk production, gunakan URL HTTPS kanonik, `APP_DEBUG=false`, session database yang dienkripsi, cache/queue database atau backend production yang diluluskan, dan SMTP sebenar. Pendaftaran, pengesahan e-mel dan reset kata laluan tidak lengkap secara operasi jika e-mel masih menggunakan mailer `log`.

Selepas mengubah `.env`, bina semula cache konfigurasi hanya setelah nilainya disemak:

```bash
php artisan optimize:clear
php artisan config:cache
```

## Route operasi utama

| Tujuan | Method dan path |
|---|---|
| Liveness Laravel | `GET /up` |
| Readiness ChatMe | `GET /health` |
| Harga awam | `GET /pricing` |
| Callback ToyyibPay | `POST /payments/toyyibpay/callback` |
| Skrip widget | `GET /widget/{api_key}.js` |
| Konfigurasi/ticket widget | `GET /api/chatbots/{api_key}/config` |
| Chat widget | `POST /api/chatbots/{api_key}/chat` |
| API pembangun | `POST /api/v1/chat` dengan bearer token pembangun |

`/up` hanya membuktikan aplikasi Laravel boleh menjawab. `/health` menyemak aplikasi, pangkalan data, queue, storage, konfigurasi pembayaran dan konfigurasi AI. AI yang sengaja dimatikan dilaporkan sebagai `disabled`; konfigurasi ToyyibPay yang tiada menyebabkan readiness gagal.

## Quality gates

Jalankan semuanya sebelum release:

```bash
composer validate --strict --no-check-publish
composer test
npm test
composer analyse
php vendor/bin/pint --test
npm run build
composer audit
npm audit
git diff --check
```

CI mengulang full PHP/JavaScript tests, Pint, production build, dependency audits dan Larastan level 5 tanpa baseline/ignore. Ia turut menjalankan Gitleaks terhadap sejarah penuh dan sumber Git. Jika Gitleaks tersedia secara lokal, jalankan arahan berikut daripada checkout bersih yang belum mempunyai `vendor` atau `node_modules`:

```bash
gitleaks git . --log-opts="--all" --no-banner --no-color --redact=100 --timeout=300
gitleaks dir . --no-banner --no-color --redact=100 --timeout=300
```

Imbasan `dir` terhadap direktori pembangunan yang sudah memasang dependency boleh melaporkan fixture/vendor pihak ketiga yang bukan sebahagian daripada sumber ChatMe. Untuk release, imbas checkout bersih atau arkib tepat daripada `git archive`, di samping imbasan sejarah `gitleaks git`.

Jangan tandakan release lulus jika mana-mana gate gagal atau working tree release tidak bersih.

## QA ToyyibPay

Harga dan jumlah bayaran datang daripada rekod pelan di server, bukan input browser. Checkout menggunakan kunci idempotensi; callback dan reconciliation menyemak order, bill code, reference, jumlah serta status provider sebelum mengaktifkan langganan. Pembaharuan ialah pembayaran bulanan baharu, bukan potongan bank automatik.

Ujian kontrak tanpa transaksi sebenar:

```bash
php artisan test tests/Unit/ToyyibPayClientTest.php tests/Feature/ToyyibPayCheckoutTest.php tests/Feature/ToyyibPayCallbackTest.php tests/Feature/ToyyibPayReturnTest.php tests/Feature/PaymentActivationTest.php
```

Untuk QA hujung-ke-hujung, gunakan akaun dan kategori sandbox yang berasingan, hidupkan `TOYYIBPAY_SANDBOX`, dan pastikan `APP_URL` boleh dicapai melalui HTTPS. Callback yang dihantar kepada provider ialah `${APP_URL}/payments/toyyibpay/callback`; return URL mengandungi UUID order. Jangan gunakan bill production, credential production atau wang sebenar untuk smoke test. Halaman return tidak mempercayai query “success”; reconciliation POST mendapatkan status daripada provider.

## Cloudflare Workers AI

Cloudflare AI adalah pilihan tambahan. Kekalkan `CHATME_AI_ENABLED` dimatikan sehingga account ID, token least-privilege dan model telah diprovisyen serta diuji. ChatMe cuba padanan pengetahuan deterministik terlebih dahulu. AI hanya menerima sehingga tiga calon pengetahuan aktif; jika provider gagal, timeout, memberi respons tidak sah atau circuit breaker terbuka selepas kegagalan berturut-turut, ChatMe menggunakan fallback tempatan.

Uji integrasi tanpa panggilan provider sebenar:

```bash
php artisan test tests/Unit/CloudflareWorkersAiProviderTest.php tests/Feature/ChatbotAiIntegrationTest.php tests/Feature/ChatbotTesterTest.php tests/Feature/HealthCheckTest.php
```

Token Cloudflare tidak boleh diletakkan dalam JavaScript, kod widget, GitHub Actions output atau log. Untuk insiden provider, matikan `CHATME_AI_ENABLED`, bina semula config cache dan sahkan jawapan deterministik/fallback masih berfungsi.

## Queue dan scheduler

Konfigurasi lalai projek menggunakan queue, cache dan session berasaskan database. Jika queued jobs digunakan, jalankan worker melalui process manager hosting dengan arahan Laravel sebenar:

```bash
php artisan queue:work
```

Selepas release code, minta worker sedia ada memuatkan code baharu:

```bash
php artisan queue:restart
```

Scheduler mesti dipanggil setiap minit dengan `php artisan schedule:run`. Jadual semasa membersihkan failed jobs dan batches selepas tujuh hari serta tempahan kuota mesej luput setiap lima minit. Lihat arahan cron dan pemeriksaan dalam [runbook production](docs/operations/production-runbook.md).

## Had penggunaan saksama

Pelan Enterprise tidak mempunyai kuota mesej bulanan, tetapi masih tertakluk pada kawalan kestabilan: sehingga 50 chatbot bagi setiap akaun, 5,000 item pengetahuan bagi setiap chatbot, serta 5,000 mesej sehari dan 600 mesej seminit merentas widget/API bagi setiap pemilik. Had ini melindungi penyewa lain dan tidak boleh dianggap sebagai kuota langganan tambahan.

Penggunaan bulanan pelan terhad disimpan dalam ledger `message_usages` selain log chatbot. Pemadaman chatbot atau log tidak memulihkan kuota yang telah digunakan. Migrasi release membina nilai awal ledger daripada log bulan semasa; selepas deployment, semak migrasi selesai sebelum membuka trafik.

## Operasi production

- [Runbook deployment, rollback, scheduler dan pentadbir](docs/operations/production-runbook.md)
- [Backup, verification dan restore drill](docs/operations/backup-restore.md)
- [Incident response asas](docs/operations/incident-response.md)
- [Reka bentuk deployment/rollback yang diluluskan](docs/superpowers/specs/2026-07-12-cpanel-deployment-rollback-design.md)

Deployment menggunakan backup terverifikasi, exact Git SHA, approved remote ref, lock bukan blocking dan state di luar web root. Rollback hanya menerima deployment ID yang direkodkan dan tidak pernah menjalankan `migrate:rollback` atau menimpa database secara automatik.

## Operasi pentadbir

Provisioning pentadbir dan adoption chatbot homepage gagal-tertutup jika identiti reserved telah diambil atau ID legacy bercanggah. Jangan masukkan password pentadbir dalam command line. Tetapkan pemboleh ubah melalui stor rahsia hosting, jalankan seeder kelas yang tepat, kemudian keluarkan credential bootstrap daripada konfigurasi selepas akaun disahkan. Langkah penuh berada dalam runbook production.

Panel `/admin` hanya tersedia kepada pengguna yang authenticated, verified dan mempunyai peranan pentadbir. Akaun/chatbot sistem bertanda tidak boleh ditukar melalui tindakan pengurusan biasa.

## Troubleshooting ringkas

```bash
php artisan migrate:status
php artisan route:list
php artisan schedule:list
php artisan queue:failed
```

- `/up` gagal: semak PHP/LiteSpeed, release semasa dan log aplikasi.
- `/health` gagal: baca nama check yang gagal; jangan paparkan `.env` atau config bercredential.
- E-mel tidak tiba: semak SMTP dan log delivery; `/health` tidak menguji penghantaran e-mel.
- Widget 401: ticket/session telah tamat atau binding origin/IP berubah; muat semula widget dan semak domain whitelist.
- Widget 403: origin tidak dibenarkan oleh domain whitelist.
- AI fallback berterusan: semak flag AI, readiness, circuit breaker dan log kategori provider tanpa mencetak token.
- Checkout gagal: semak readiness pembayaran, status order tempatan dan dashboard sandbox/production yang sepadan.
- HTTP 503 atau `Unable to fork`: hentikan deployment dan ikut prosedur kapasiti dalam [incident response](docs/operations/incident-response.md).

## Keselamatan

Laporkan kelemahan melalui saluran peribadi pemilik sistem. Jangan buka issue awam yang mengandungi credential, data pelanggan, payload pembayaran, token reset, token API atau dump pangkalan data. Jika secret pernah muncul dalam chat/log/Git, anggap ia terdedah: revoke/rotate dahulu, kemudian bersihkan punca dan jalankan semula Gitleaks.
