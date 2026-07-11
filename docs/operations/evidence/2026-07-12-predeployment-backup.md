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

Backup ini ialah salinan terhad dalam akaun hosting yang sama. Salinan off-host terenkripsi dan retention automatik masih perlu disediakan sebelum penutupan production-readiness penuh. Backup ini tidak akan dipadam sehingga release baharu stabil dan recovery point pengganti telah disahkan.
