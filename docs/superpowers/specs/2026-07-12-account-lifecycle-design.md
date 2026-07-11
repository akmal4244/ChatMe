# Reka Bentuk Kitar Hayat Akaun dan Sesi ChatMe

**Status:** Diluluskan melalui spesifikasi production-grade dan kebenaran penuh pemilik pada 12 Julai 2026.

## Matlamat

Lengkapkan fungsi akaun yang masih tiada tanpa mengunci pengguna production sedia ada: pengesahan e-mel, lupa/tetap semula kata laluan, pengurusan profil dan kata laluan, serta amaran sesi yang jelas dalam Bahasa Melayu Malaysia.

## Pendekatan dipilih

Gunakan kontrak dan broker rasmi Laravel 12, bukannya membina token atau tandatangan tersendiri. Controller kecil berasingan mengurus pautan reset, pengesahan e-mel dan profil. Route operasi SaaS dilindungi oleh `auth` serta `verified`; route verifikasi, profil dan log keluar kekal boleh dicapai oleh pengguna yang telah login tetapi belum disahkan.

Pendekatan alternatif yang ditolak:

1. Controller auth tunggal yang besar — lebih cepat pada awalnya tetapi sukar diaudit dan diuji.
2. Verifikasi e-mel buatan sendiri — menambah risiko token, expiry dan signed URL yang tidak perlu.

## Keserasian pengguna sedia ada

Migration forward-only menandakan akaun yang wujud sebelum rollout sebagai telah disahkan. Ini mengelakkan dua akaun production sedia ada daripada terkunci apabila middleware `verified` diaktifkan. Akaun yang didaftarkan selepas rollout menerima e-mel pengesahan dan kekal pada halaman arahan verifikasi sehingga pautan sah digunakan.

Rollback kod tidak memadam `email_verified_at`. Migration tidak mempunyai operasi `down()` yang mengosongkan status verifikasi kerana tindakan itu boleh mengunci akaun dan merosakkan data.

## Aliran e-mel dan kata laluan

- Pendaftaran mencetuskan event `Registered`, meregenerasi sesi, lalu mengarahkan pengguna ke notis verifikasi.
- Pengguna boleh meminta pautan reset tanpa mendedahkan sama ada e-mel wujud. Endpoint dihadhadkan; token menggunakan broker Laravel, expiry 60 minit dan throttle 60 saat.
- Pautan verifikasi menggunakan URL bertandatangan dan throttle. Permintaan hantar semula memberi respons umum serta popup di bahagian atas.
- E-mel yang dihantar menggunakan locale `ms`; kegagalan mailer menghasilkan mesej selamat dan log berstruktur tanpa alamat penuh, token atau stack trace kepada pengguna.

## Profil dan keselamatan kata laluan

Halaman profil membenarkan perubahan nama, e-mel, syarikat dan laman web. E-mel mesti unik; perubahan e-mel mengosongkan `email_verified_at`, menghantar verifikasi baharu dan menghalang route SaaS sehingga disahkan. Perubahan kata laluan memerlukan kata laluan semasa, confirmation, peraturan panjang yang sama dengan pendaftaran, hash Laravel, regenerasi sesi dan pembatalan sesi lain apabila session driver menyokongnya.

Tiada fungsi padam akaun dalam tranche ini kerana spesifikasi tidak menentukan retention, pembayaran, pemilikan chatbot atau pemadaman audit yang selamat.

## Sesi dan notifikasi

Lifetime idle production kekal standard 120 minit dan dikawal oleh `SESSION_LIFETIME`. Layout authenticated menerima masa tamat daripada server dan memaparkan popup di bahagian atas apabila baki sesi lima minit. Aktiviti bernetwork yang sah menyegerakkan semula deadline. Apabila tamat, UI tidak memalsukan keadaan login: pengguna diarahkan ke login dengan mesej “Sesi anda telah tamat. Sila log masuk semula.”

Semua success, info, validation dan error menggunakan komponen popup sedia ada di bahagian atas. Kandungan menggunakan `textContent`, role `status`/`alert`, close button dan tidak memaparkan data sensitif.

Keputusan produk terdahulu untuk mengunci zoom mobile dikekalkan. Saiz input minimum 16px tetap digunakan supaya iOS tidak melakukan auto-zoom ketika fokus.

## Authorization dan route

- Public/guest: login, daftar, lupa kata laluan, reset kata laluan.
- `auth`: log keluar, notis/hantar/terima verifikasi, profil dan perubahan kata laluan.
- `auth,verified`: dashboard, onboarding, chatbot, knowledge, langganan dan route admin sedia ada.
- Signed verification URL mesti sepadan dengan ID dan hash pengguna yang sedang login.
- Semua update profil beroperasi hanya pada `$request->user()`; tiada user ID daripada input.

## Ujian penerimaan

Ujian feature mesti membuktikan:

- pautan reset dihantar untuk akaun sah, respons tidak mendedahkan akaun tidak wujud, token salah/luput ditolak, dan kata laluan boleh ditetapkan semula;
- akaun baharu belum disahkan tidak boleh mencapai dashboard, signed link yang sah mengesahkan, link salah/luput ditolak, hantar semula dihadhadkan, dan akaun lama kekal boleh masuk selepas migration;
- profil hanya mengubah pengguna semasa, e-mel duplicate ditolak, perubahan e-mel memerlukan verifikasi semula, kata laluan semasa wajib dan sesi diregenerasi;
- route sensitif memerlukan `verified` tanpa menjejaskan logout/verifikasi/profil;
- copy dan validation Bahasa Melayu konsisten, popup berada di atas, dan tiada token/e-mel penuh dalam log;
- amaran lima minit dan aliran 419/session-expired berfungsi dengan masa ujian terkawal.

Browser QA merangkumi keyboard/focus, 320px, 390px, tablet dan desktop. Tiada pembayaran sebenar atau data production digunakan dalam ujian.

## Deployment dan rollback

Sebelum deploy, sahkan mailer production melalui e-mel QA tanpa memaparkan credential, backup DB, dan rekod kiraan pengguna belum disahkan. Jalankan migration sebelum mengaktifkan route cache baharu, kemudian smoke test login akaun lama, pendaftaran QA, verifikasi dan reset.

Rollback aplikasi mengembalikan release sebelumnya tanpa menjalankan `migrate:rollback`; data verifikasi yang telah ditulis kekal serasi dengan kod lama. Jika mailer gagal, route login akaun lama masih boleh dipulihkan melalui rollback release dan bukannya memadam status pengguna.
