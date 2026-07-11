# Bukti Backup Pra-Deployment — 12 Julai 2026

## Sumber

- Production app: `/home2/akmalmar/public_html/chatme.akmalmarvis.com`
- Release SHA: `d0f6345deda2e25c325c7cad53591dd83ba07f35`
- Working tree production: bersih, 0 perubahan
- Database driver: MySQL/MariaDB

## Backup

- Lokasi: `/home2/akmalmar/secure-backups/chatme/chatme-20260711T172307Z-ccd62ed4`
- Lokasi berada di luar `public_html` dan di luar project root.
- Saiz pada hosting: 2,064 KB.
- Permission check: 0 direktori selain mode `0700`; 0 fail selain mode `0600`.
- `verify.php`: exit `0`, status `verified`, 7 fail ber-checksum.
- Kandungan wajib: dump database, storage private/public, release SHA, migration state, manifest dan `SHA256SUMS`.

Nilai credential, `.env`, password database, APP_KEY, token ToyyibPay dan token API tidak dicetak atau dimasukkan ke manifest.

## Restore drill MySQL

Restore dijalankan ke database dan direktori disposable, bukan production. Tool memverifikasi checksum backup sebelum import dan checksum storage selepas pemulihan.

Kiraan selepas import:

| Jadual | Baris |
|---|---:|
| users | 2 |
| chatbots | 3 |
| knowledge_items | 100 |
| subscriptions | 2 |
| payment_orders | 1 |
| chat_logs | 366 |

- Release SHA yang dipulihkan: `d0f6345deda2e25c325c7cad53591dd83ba07f35`.
- Storage files dilaporkan oleh drill: 4.
- Pemeriksaan integriti cPanel MySQL: lulus.
- Database disposable dipadam selepas bukti diperoleh.
- Direktori restore disposable dipadam selepas bukti diperoleh.
- Semakan akhir: 0 database dengan prefix khusus restore drill tertinggal.

Tiga database kosong yang terhasil semasa pembetulan parser status UAPI telah dikenal pasti melalui prefix khusus dan dipadam sebelum drill sebenar. Ia tidak pernah menerima import. Semakan akhir 0 memastikan tiada resource ujian tertinggal.

## Had semasa

Salinan kedua telah dimuat turun ke PC operator dan disimpan sebagai:

- `C:\Users\User\.codex\secure-backups\ChatMe\chatme-20260711T172307Z-ccd62ed4.tar.gz.aesgcm`
- kunci terbungkus: fail pasangan `.key.dpapi`;
- metadata bukan rahsia: fail pasangan `.offhost.json`.

Archive menggunakan AES-256-GCM. Kunci rawak 256-bit dilindungi oleh Windows DPAPI `CurrentUser`; decrypt dan inventori archive telah diuji selepas penulisan. ACL direktori hanya memberi kawalan penuh kepada akaun Windows semasa. Semakan selepas proses menunjukkan `RawStagingCount=0`, jadi tiada salinan mentah tertinggal dalam direktori sementara.

Backup hosting dan off-host ini tidak akan dipadam sehingga release baharu stabil dan recovery point pengganti telah disahkan. Retention automatik serta salinan immutable pada provider ketiga masih perlu disediakan sebelum penutupan production-readiness penuh.
