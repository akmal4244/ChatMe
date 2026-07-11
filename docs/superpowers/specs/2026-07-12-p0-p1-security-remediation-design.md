# Reka Bentuk Pemulihan Keselamatan P0/P1 ChatMe

**Status:** Diluluskan melalui spesifikasi production-grade dan kebenaran penuh pemilik pada 12 Julai 2026.

## Skop

Reka bentuk ini menutup lima kelas risiko yang dibuktikan melalui ujian: seeder homepage memusnahkan data pelanggan, identiti sistem/admin boleh dipra-tuntut, panggilan AI berlaku sebelum kuota ditempah, API widget awam boleh menyusutkan kuota secara automatik, dan token developer mentah disimpan dalam sesi database.

Tiada data pelanggan dipadam, tiada pembayaran sebenar dibuat dan deployment tidak bermula sebelum backup serta restore drill lulus.

## Identiti sistem yang gagal-tertutup

Tambah `system_role` nullable dan unik pada `users` serta `chatbots`. Nilai `homepage_owner`, `homepage_chatbot` dan `primary_admin` menjadi penanda dalaman yang tidak boleh diisi melalui mass assignment, form atau API.

Seeder homepage hanya boleh menggunakan chatbot bertanda `homepage_chatbot`. Audit production membuktikan bot legacy rasmi kini dimiliki akaun admin biasa dan belum mempunyai owner/entitlement sistem. Oleh itu rollout legacy tidak boleh meneka rekod berdasarkan nama, admin atau e-mel. Operator mesti menetapkan `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID` secara eksplisit selepas backup; seeder mengunci ID itu dan memerlukan slug rasmi yang tepat. Tanpa ID eksplisit, slug rasmi yang belum bertanda menyebabkan exception dan rollback.

Pengguna sistem homepage tidak memerlukan kuasa admin. Seeder mencipta owner khusus dengan `is_admin=false`, password rawak yang tidak dipaparkan, dan entitlement sistem yang khusus. Ketika adoption eksplisit, hanya `user_id` bot ID yang diluluskan dipindahkan kepada owner sistem; API key, log, knowledge dan ID bot kekal. Chatbot lain milik admin tidak disentuh.

Pendaftaran menolak e-mel dalaman homepage serta e-mel admin yang dikonfigurasi secara case-insensitive. `AdminSeeder` hanya mencipta `primary_admin` baharu apabila tiada collision. Rerun pada rekod bertanda adalah idempotent dan tidak menukar password; rekod tidak bertanda dengan e-mel sama menyebabkan kegagalan, bukan promotion. Operasi reset admin yang disengajakan mesti menggunakan command berasingan yang memerlukan confirmation dan memadam semua sesi pengguna itu.

## Rekonsiliasi knowledge tanpa pemusnahan

Tambah `source_key` nullable pada `knowledge_items` dengan unique gabungan `(chatbot_id, source_key)`. Tiga puluh tiga item rasmi mendapat key stabil seperti `homepage:001`.

Seeder melakukan upsert hanya pada item bertanda. Semasa adoption legacy eksplisit, item dengan soalan rasmi yang sama boleh ditanda dan dikemas kini; item lain kekal. Seeder hanya boleh membuang source key rasmi yang telah dikeluarkan daripada dataset, dan tidak pernah memanggil `knowledgeItems()->delete()` secara menyeluruh. Chat log, API key dan knowledge buatan pengguna kekal utuh.

## Tempahan kuota sebelum provider

Tambah jadual `message_quota_reservations` dan service tunggal yang mengurus tiga fasa:

1. Dalam transaction dan `lockForUpdate` owner, kira mesej bulan semasa bersama reservation belum luput. Jika had tersedia, cipta reservation rawak berjangka pendek; jika tidak, pulangkan 429 tanpa panggilan provider.
2. Selepas transaction commit, jalankan padanan/Cloudflare. Provider tidak pernah dipanggil sambil lock database dipegang.
3. Dalam transaction kedua, kunci reservation, tulis pasangan user/bot log secara atomik, kemudian hapus reservation. Jika penulisan gagal, kedua-dua log rollback dan reservation dilepaskan. Reservation orphan luput secara automatik dan dibersihkan scheduler.

Pelan unlimited masih menggunakan transaction atomik untuk dua log tetapi tidak memerlukan reservation kuota. Ujian MySQL dua sambungan diperlukan sebelum production untuk membuktikan dua request serentak tidak melepasi slot terakhir dan request yang ditolak tidak memanggil Cloudflare.

Owner tester kekal boleh menguji AI sebenar tetapi penggunaan provider dihadkan berasingan kepada 20 panggilan sehari bagi setiap pengguna. Selepas had tester AI, padanan deterministic/fallback masih berfungsi dan UI memaparkan notis selamat.

## Perlindungan API widget awam

`Origin` ialah petunjuk browser/CORS, bukan credential. Respons CORS memantulkan hanya origin yang tepat dibenarkan dan menambah `Vary: Origin`; wildcard dibuang.

Config endpoint mengeluarkan tiket widget tersulit dan berjangka 10 minit yang terikat kepada chatbot, origin tepat, session ID rawak dan hash IP. Chat endpoint memerlukan tiket itu serta padanan semua binding. Widget memperbaharui tiket melalui config apabila luput. Tiket tidak dimasukkan dalam URL atau log.

Abuse dikawal bebas daripada Origin melalui limiter berlapis:

- bootstrap: per chatbot dan IP;
- chat: per tiket, per chatbot+IP dan per chatbot global;
- had harian plan-aware sebagai circuit breaker supaya bot Free tidak boleh kehilangan seluruh kuota bulanan dalam beberapa minit;
- log berstruktur apabila tiket salah, origin salah, limiter global dicapai atau pola session luar biasa berlaku.

Tiket tidak menjadikan browser awam sebagai client rahsia; ia mengecilkan replay dan mengikat request kepada sesi. Untuk integrasi server-to-server, hanya Developer API bertoken digunakan. Dokumentasi dan label UI tidak akan mendakwa domain whitelist sebagai authorization mutlak.

## Token developer dan sesi

POST penjanaan token memulangkan halaman one-time secara terus melalui HTTPS dengan header `Cache-Control: no-store, private` dan `Pragma: no-cache`. Raw token tidak diflash, tidak disimpan dalam cache/session, tidak dilog dan tidak muncul dalam URL. Halaman embed biasa hanya menunjukkan prefix serta tarikh rotation.

Tetapkan `SESSION_ENCRYPT=true` sebagai baseline production. Deployment memadam sesi lama selepas backup supaya payload plaintext terdahulu tidak kekal dan pengguna log masuk semula. Hash token developer kekal dalam database; jika audit session menemui token lama, token bot berkenaan dirotasi sebelum go-live tanpa memaparkan nilainya.

## Error, logging dan localization

Semua respons manusia menggunakan Bahasa Melayu Malaysia. Token protokol sedia ada dikekalkan. Log hanya menyimpan ID dalaman, channel, sebab umum, limiter dan request ID; tiada password, raw token, tiket widget, mesej penuh, alamat e-mel penuh atau secret provider.

Collision seeder menggagalkan operasi dengan exception eksplisit kepada operator tetapi tidak dipaparkan kepada pengguna web. API production memulangkan 401/403/429 generik mengikut keadaan tanpa stack trace.

## Ujian wajib

- Seeder pada bot admin pelanggan tidak mengubah bot atau satu pun knowledge.
- Preclaim e-mel homepage/admin gagal tanpa promotion, entitlement atau session preservation.
- Adoption legacy tanpa ID eksplisit gagal; ID+slug tepat berjaya, memelihara bot/log/API key/knowledge, dan rerun idempotent.
- Request kuota terakhir serentak menghasilkan tepat satu reservation/provider call/pasangan log.
- Kegagalan bot-log rollback kedua-dua log dan melepaskan reservation.
- Tester AI berhenti memanggil provider selepas had harian tetapi deterministic masih berjaya.
- Widget tanpa/tiket palsu/luput/origin-IP-session tidak sepadan ditolak; exact CORS lulus; global limiter berfungsi walaupun IP diedarkan dalam simulasi.
- Raw developer token tidak muncul dalam session/cache/log/URL dan respons mempunyai `no-store`.
- Dedicated secret scan serta PHPUnit, JavaScript, Pint, Composer/npm audit dan build semuanya lulus.

## Deployment dan rollback

Migration hanya menambah column/table/index nullable atau baharu. Adoption marker dilakukan dalam transaction dan gagal-tertutup. Sebelum seeding, backup/restore drill mesti lulus dan query read-only mesti merekod ID, slug, kiraan log/knowledge serta pemilik legacy tanpa mendedahkan e-mel. ID itu kemudian ditetapkan sebagai `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID` hanya untuk rollout; selepas marker berjaya, konfigurasi legacy dibuang. Seeder dijalankan khusus, bukan sebagai kesan sampingan deployment umum.

Rollback release tidak menjalankan `migrate:rollback`; column baharu serasi dengan kod lama. `CHATME_AI_ENABLED=false` kekal kill switch provider. Jika limiter atau tiket menyebabkan regresi, rollback code memulihkan release lama sementara database dan backup kekal utuh.
