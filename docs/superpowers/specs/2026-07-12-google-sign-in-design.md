# Reka Bentuk Google Sign-In ChatMe

**Status:** Diluluskan untuk ditulis pada 12 Julai 2026

**Skop:** Log masuk dan pendaftaran pengguna biasa melalui Google pada aplikasi web ChatMe

**Production callback:** `https://chatme.akmalmarvis.com/auth/google/callback`

## Matlamat

ChatMe akan menawarkan butang **Teruskan dengan Google** pada halaman log masuk dan daftar. Aliran menggunakan OAuth 2.0 authorization-code secara server-side melalui Laravel Socialite. Pengguna biasa boleh mencipta akaun baharu atau memaut akaun ChatMe sedia ada berdasarkan e-mel Google yang disahkan. Kata laluan ChatMe sedia ada terus boleh digunakan.

Google Sign-In mesti mematuhi kawalan keselamatan, Bahasa Melayu Malaysia, session deadline, tenant isolation dan production readiness yang sudah digunakan oleh ChatMe.

## Bukan Matlamat

- Tiada Google One Tap atau JavaScript Google Identity Services.
- Tiada akses Google Drive, Calendar, Contacts atau API Google lain.
- Tiada refresh token, access token atau avatar Google disimpan.
- Tiada pemautan Google kepada akaun pentadbir atau akaun sistem.
- Tiada UI untuk menyahpaut identiti Google dalam release ini.
- Tiada provider sosial selain Google.

## Pendekatan Yang Dinilai

### 1. Laravel Socialite server-side — dipilih

Socialite menyediakan redirect dan pertukaran authorization code melalui backend, menggunakan session `state` untuk perlindungan CSRF dan mempunyai fake provider rasmi untuk ujian. Pendekatan ini tidak menambah skrip pihak ketiga kepada CSP ChatMe dan sesuai untuk desktop serta browser mudah alih.

### 2. Google Identity Services JavaScript

Pendekatan ini boleh menyediakan One Tap, tetapi memerlukan skrip luar, CSP tambahan dan validasi ID token yang dihantar dari browser. Manfaat tersebut tidak diperlukan untuk skop log masuk asas.

### 3. OpenID Connect manual

Pendekatan manual mengurangkan satu dependency tetapi menambah kod sensitif untuk state, pertukaran code, validasi token dan pengendalian provider. Risiko penyelenggaraan lebih tinggi tanpa kelebihan yang diperlukan.

## Dependency Dan Konfigurasi

- Tambah `laravel/socialite` versi stabil yang serasi dengan Laravel 12 melalui Composer lockfile.
- Tambah konfigurasi `services.google` dengan:
  - `GOOGLE_AUTH_ENABLED`;
  - `GOOGLE_CLIENT_ID`;
  - `GOOGLE_CLIENT_SECRET`;
  - `GOOGLE_REDIRECT_URI`.
- Nilai production tidak boleh berada dalam Git, output CI, log atau command line.
- Butang Google hanya dipaparkan apabila feature flag hidup dan ketiga-tiga nilai OAuth lengkap.
- Route redirect/callback gagal dengan mesej BM selamat apabila konfigurasi tidak lengkap.
- `/health` melaporkan `google_auth` sebagai `disabled`, `ok` atau `failed` tanpa mendedahkan nilai credential.

Google Cloud mesti menggunakan OAuth client jenis **Web application**, authorized domain `akmalmarvis.com` dan redirect URI HTTPS yang sepadan tepat dengan callback production. Skop hanya `openid`, `email` dan `profile`; offline access tidak diminta.

## Model Data

Migrasi forward-only menambah pada jadual `users`:

- `google_sub VARCHAR(255) NULL` dengan unique constraint;
- `google_linked_at TIMESTAMP NULL`;
- menjadikan `password` nullable untuk akaun Google-only.

`google_sub` ialah identifier kekal daripada Google. E-mel hanya digunakan untuk pemautan kali pertama selepas `email_verified` disahkan; e-mel tidak menjadi identifier Google.

Model `User`:

- menyembunyikan `google_sub` daripada serialization bersama password/token;
- cast `google_linked_at` kepada datetime;
- menyediakan `hasLocalPassword(): bool` untuk paparan dan kawalan akaun;
- tidak mass-assign `google_sub` melalui request pengguna.

Akaun Google-only menyimpan `password = null`. Profil menyediakan butang authenticated yang menghantar pautan reset ke e-mel akaun sendiri melalui password broker sedia ada; pengguna tidak memasukkan atau memilih alamat penerima. Endpoint ini rate-limited dan menggunakan logging kegagalan notifikasi yang telah diredact. Tindakan sensitif yang memerlukan `current_password` kekal fail-closed sehingga kata laluan tempatan ditetapkan, dan UI profil menerangkan langkah tersebut.

## Komponen

### `GoogleIdentity`

Value object immutable yang membawa hanya:

- `subject`;
- `email` yang telah dinormalisasi;
- `name` yang telah dibataskan;
- `emailVerified`.

Access token dan raw provider payload tidak masuk ke service domain atau log.

### `GoogleAccountService`

Service ini menerima `GoogleIdentity` dan memulangkan pengguna ChatMe atau exception domain selamat. Ia memiliki semua peraturan lookup, pemautan, penciptaan dan concurrency; controller tidak membuat query akaun secara terus.

### `GoogleAuthController`

- `redirect()` memulakan Socialite Google stateful flow dengan skop minimum dan `prompt=select_account`.
- `callback()` memetakan provider user kepada `GoogleIdentity`, memanggil service, menjalankan `Auth::login`, menjana semula session ID dan mengalihkan ke intended dashboard.
- Pembatalan, invalid state, response provider tidak lengkap, timeout atau exception lain dipetakan kepada popup BM generik pada halaman login.

## Aliran Dan Peraturan Pemautan

1. Tetamu memilih **Teruskan dengan Google**.
2. ChatMe mencipta OAuth `state` dalam session dan redirect ke Google melalui HTTPS.
3. Callback hanya diterima apabila `state` sah dan authorization code berjaya ditukar oleh Socialite.
4. Provider mesti mengembalikan `sub`, e-mel, nama dan tanda e-mel verified. Nilai kosong, terlalu panjang atau e-mel tidak verified ditolak.
5. Di dalam database transaction dengan retry deadlock:
   - cari `google_sub` dan lock row;
   - jika ditemui, sahkan dahulu akaun itu bukan admin, sistem atau reserved, kemudian gunakan akaun tersebut tanpa menukar e-mel/nama secara automatik;
   - jika belum ditemui, cari e-mel normalized dan lock row;
   - jika akaun biasa ditemui dan `google_sub` masih kosong, pautkan subject dan set `google_linked_at`;
   - jika akaun itu sudah dipaut kepada subject lain, tolak;
   - jika tiada akaun dan e-mel bukan reserved, cipta pengguna verified dengan `password = null` dan subject tersebut.
6. Akaun dengan `is_admin = true`, `system_role` terisi, atau e-mel reserved homepage/admin sentiasa ditolak daripada pemautan dan login Google.
7. Selepas transaction berjaya, ChatMe login pengguna, regenerate session dan redirect ke `dashboard` atau intended route.

Unique constraint pada `users.email` dan `users.google_sub`, transaction serta recovery selepas duplicate-key memastikan dua callback serentak tidak mencipta dua pengguna atau memaut satu subject kepada dua akaun.

## Session, Rate Limit Dan Logging

- Kedua-dua route kekal di bawah middleware `guest`.
- OAuth mesti stateful; `stateless()` dilarang.
- Redirect dan callback mempunyai named limiter berasaskan IP. Callback mempunyai cap lebih ketat untuk penciptaan/pemautan akaun.
- Session ID dijana semula selepas login bagi menghalang session fixation.
- Session deadline ChatMe bermula melalui flow authenticated sedia ada.
- Log kegagalan hanya menyimpan kategori, request ID, provider, IP/hash e-mel atau hash subject yang sesuai; authorization code, access token, raw payload dan client secret dilarang.

## UI Dan Bahasa

Halaman `login` dan `register` memaparkan:

- butang accessible **Teruskan dengan Google**;
- divider **atau teruskan dengan e-mel**;
- focus state, keyboard activation dan saiz sentuhan minimum 44px;
- susun atur tanpa overflow pada 320px, 390px, tablet dan desktop.

Tiada logo/avatar dimuat daripada URL Google. Ikon Google disediakan sebagai aset lokal yang dibenarkan atau butang teks tanpa imej luar. Semua mesej menggunakan Bahasa Melayu Malaysia dan popup global sedia ada.

Profil pengguna Google-only memaparkan bahawa kata laluan tempatan belum ditetapkan dan menyediakan butang **Hantar pautan tetapkan kata laluan**. POST authenticated itu sentiasa menggunakan e-mel pengguna semasa, bukan input request. Mesej login kata laluan kekal neutral supaya kewujudan akaun atau jenis provider tidak boleh dienumerasi.

## Pengendalian Ralat

| Keadaan | Tingkah laku |
|---|---|
| Pengguna membatalkan Google | Kembali ke login dengan popup `Log masuk Google dibatalkan.` |
| State salah/luput | Kembali ke login dengan popup generik; tiada login/mutasi |
| Credential/config tiada | Route gagal selamat; health `failed` jika flag hidup |
| E-mel tidak verified | Tolak dengan mesej BM generik; tiada pengguna dicipta |
| Subject/e-mel bercanggah | Tolak dan log kategori konflik tanpa identifier mentah |
| Akaun admin/sistem/reserved | Tolak; local password login kekal tersedia |
| Provider timeout/HTTP gagal | Kembali ke login; tiada mutasi dan tiada detail provider |
| Duplicate callback serentak | Hanya satu create/link; callback lain membaca hasil sama atau gagal neutral |

## Ujian TDD

Ujian ditulis dan disaksikan gagal sebelum setiap implementation:

1. redirect menggunakan driver Google stateful, skop minimum dan account chooser;
2. akaun baharu dicipta verified dengan subject unik, password null dan session regenerated;
3. akaun biasa sedia ada dipaut melalui e-mel verified tanpa menukar password/data profil;
4. login berikutnya menggunakan `google_sub` walaupun e-mel provider berubah;
5. e-mel unverified, subject kosong, e-mel kosong dan nama invalid ditolak;
6. admin, system identity dan reserved e-mel tidak boleh dipaut;
7. subject sama tidak boleh dipaut ke dua e-mel dan satu e-mel tidak boleh dipaut ke dua subject;
8. invalid state, pembatalan dan provider exception tidak mencipta session/pengguna;
9. callback serentak pada MySQL menghasilkan tepat satu pengguna/link;
10. rate limit menghasilkan 429 BM tanpa mutasi;
11. password login/reset sedia ada kekal berfungsi dan Google-only local login gagal neutral;
12. pengguna Google-only boleh meminta pautan tetapkan kata laluan hanya untuk e-mel sendiri, dengan limiter dan kegagalan notifikasi selamat;
13. health/config/UI/localization/mobile/CSP contract lulus;
14. serialization dan log tidak mengandungi subject mentah, token atau secret.

Semua quality gate release asal diulang: full PHP tests, JavaScript tests, Pint, Larastan, Composer validate/audit, npm audit/build, diff check, Gitleaks, CI dan disposable MySQL concurrency gate.

## Deployment Dan Rollback

1. Provision Google OAuth client melalui Google Cloud Console; jangan tampal secret dalam chat atau Git.
2. Simpan credential melalui `.env` production menggunakan saluran SSH/SFTP restricted dan permission sedia ada.
3. Kekalkan `GOOGLE_AUTH_ENABLED=false` sehingga code, migration dan config cache berjaya.
4. Cipta backup production baharu dan off-host encrypted kerana backup sebelum permintaan Google tidak meliputi release baharu.
5. Deploy exact merge SHA dengan tooling deployment ChatMe.
6. Jalankan migrasi, cache config, health dan smoke test local login terlebih dahulu.
7. Hidupkan Google auth, cache semula config dan sahkan redirect/callback menggunakan akaun QA biasa; jangan paut akaun admin/sistem.
8. Semak session, user/link count, log `ERROR`/`CRITICAL` dan HTTP route utama.

Rollback code menggunakan deployment ID seperti runbook. Schema tambahan kekal forward-compatible; code lama mengabaikan kolum Google. Jika Google provider bermasalah, matikan `GOOGLE_AUTH_ENABLED`, cache semula config dan kekalkan login e-mel/kata laluan tanpa rollback database.

## Acceptance Criteria

- Pengguna biasa baharu boleh mendaftar dan masuk melalui Google.
- Akaun biasa sedia ada dengan e-mel Google verified dipaut tepat sekali.
- Admin, sistem dan reserved identity tidak boleh menggunakan auto-link Google.
- Google `sub`, bukan e-mel, menjadi identifier provider selepas pemautan.
- Tiada token Google atau secret disimpan/log/commit/dihantar ke browser selain protocol yang diperlukan.
- Invalid state, e-mel unverified, konflik dan provider failure tidak mencipta atau mengubah akaun.
- Dua callback serentak tidak mencipta duplicate user/link.
- Session ID berubah selepas login dan semua session deadline sedia ada kekal.
- Login/reset kata laluan, tenant isolation, ToyyibPay, quota dan widget tidak regresi.
- UI BM accessible dan responsif dari 320px hingga desktop.
- Health dan runbook merangkumi Google auth.
- Semua local/CI/MySQL gates lulus pada exact SHA.
- Production mempunyai OAuth credential restricted, exact SHA, migration lengkap, health lulus dan tiada error release baharu.
