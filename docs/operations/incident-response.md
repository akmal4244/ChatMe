# Incident Response Asas ChatMe

Dokumen ini ialah panduan awal untuk menstabilkan production. Ia tidak memberi kuasa untuk memadam data pelanggan, membuat pembayaran, menurunkan schema atau menimpa database.

## Keutamaan

- **P0:** seluruh production tidak boleh digunakan, data/integriti pembayaran berisiko, akses tanpa kebenaran atau secret production terdedah.
- **P1:** fungsi utama rosak untuk ramai pengguna, pembayaran/verification/widget gagal secara meluas, atau kapasiti hampir menyebabkan P0.
- **P2:** degradasi terhad dengan workaround selamat.

Untuk P0/P1, lantik seorang incident owner, catat masa Asia/Kuala_Lumpur dan UTC, bekukan deployment lain, dan simpan bukti sebelum perubahan.

## Pemeriksaan lima minit pertama

```bash
cd /home2/akmalmar/public_html/chatme.akmalmarvis.com
date -u
git rev-parse HEAD
git status --short
curl --silent --show-error --include https://chatme.akmalmarvis.com/up
curl --silent --show-error --include https://chatme.akmalmarvis.com/health
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

Jangan jalankan arahan yang mencetak `.env`, config cache, credential atau payload pelanggan. Rekod status/headers, SHA, check health yang gagal dan perubahan terakhir. Simpan log berkaitan dalam stor restricted, bukan issue awam.

## HTTP 503, NPROC atau SSH tidak stabil

Tanda biasa ialah `Unable to fork`, HTTP 503, SSH reset atau process count menghampiri had CloudLinux.

```bash
ps -u "$USER" -o pid,ppid,stat,etime,cmd
```

1. Hentikan deployment baharu sebelum maintenance.
2. Kenal pasti proses stale/berulang dan pemiliknya; jangan kill proses tidak dikenali secara pukal.
3. Semak Resource Usage cPanel, disk dan inode.
4. Jika process limit sudah tepu, eskalasi kepada hosting dengan masa, domain, bukti NPROC dan sampel proses yang telah diredact.
5. Selepas kapasiti pulih, sahkan `/up`, `/health`, SSH dan cron sebelum menyambung deployment.

Jangan bina loop restart yang bersaing dengan CloudLinux. Worker mesti dikawal satu process manager dan scheduler satu cron seminit.

## Readiness `/health` gagal

Respons health hanya mendedahkan nama check dan `ok`, `disabled` atau `failed`.

- `database`: hentikan write berisiko, semak sambungan dan migration; jangan cuba restore ke database live.
- `queue`: semak backend queue dan `php artisan queue:failed`; restart worker hanya selepas backend stabil.
- `storage`: semak path, permission, disk dan inode tanpa membuka akses awam.
- `payments`: semak kewujudan konfigurasi ToyyibPay dan padanan sandbox/production tanpa mencetak secret.
- `ai`: jika AI diaktifkan tetapi credential tiada, betulkan provisioning atau matikan AI secara selamat.

Selepas perubahan config:

```bash
php artisan optimize:clear
php artisan config:cache
```

Kemudian uji semula `/health` dan flow berkaitan.

## Insiden Cloudflare AI

AI bukan dependency wajib untuk jawapan deterministik. Untuk authentication error, rate limit, timeout atau kegagalan berulang:

1. Tetapkan `CHATME_AI_ENABLED` kepada false melalui konfigurasi hosting.
2. Bina semula config cache.
3. Sahkan soalan berkeyakinan tinggi masih dijawab daripada knowledge dan soalan lain mendapat fallback tempatan.
4. Semak log kategori `Cloudflare AI request failed` tanpa token atau payload penuh.
5. Rotate token jika ada petunjuk pendedahan; hidupkan semula hanya selepas ujian terkawal.

Provider mempunyai circuit breaker selepas lima kegagalan berturut-turut dan tempoh buka lima minit. Jangan cuba mengatasinya dengan retry loop agresif.

## Insiden ToyyibPay atau langganan

1. Jangan menanda order `paid` secara manual.
2. Rekod UUID order, bill code yang selamat, status tempatan dan masa; jangan simpan secret/payload penuh dalam tiket.
3. Bandingkan transaksi melalui dashboard provider yang sepadan dengan sandbox atau production.
4. Gunakan reconciliation sedia ada hanya melalui order milik pengguna dan endpoint authenticated.
5. Jika callback disyaki palsu, preserve log, semak hash/reference/amount dan rotate secret melalui provider sebelum kemas kini config.
6. Jangan buat pembayaran sebenar sebagai diagnostik. Gunakan suite ToyyibPay dan sandbox terkawal.

Activation idempotent dan memerlukan bukti provider yang sepadan. Sebarang pembetulan data manual memerlukan pelan perubahan berasingan, backup dan audit trail.

## Deployment atau rollback gagal

1. Catat `deployment_id` dan jangan padam `STATE_ROOT` atau lock file secara paksa ketika proses hidup.
2. Semak event bertandatangan untuk fasa gagal, automatic recovery dan `maintenance_up`.
3. Sahkan keadaan semasa:

```bash
git rev-parse HEAD
git status --short
php artisan migrate:status
curl --silent --show-error --include https://chatme.akmalmarvis.com/up
curl --silent --show-error --include https://chatme.akmalmarvis.com/health
```

4. Jika aplikasi masih maintenance selepas proses deployment tamat, incident owner mesti memahami fasa code/dependency/schema semasa sebelum menjalankan `php artisan up`.
5. Gunakan rollback script hanya untuk deployment ID berjaya dan backup yang masih verified. Jangan gunakan SHA bebas, `migrate:rollback` atau restore database live.
6. Jika code lama tidak serasi dengan schema baharu, roll forward ke release dibetulkan atau ikut recovery database berasingan.

Rujuk [production-runbook.md](production-runbook.md) dan [backup-restore.md](backup-restore.md).

## Pendedahan secret atau akaun

1. Revoke/rotate credential terdedah terlebih dahulu: token Cloudflare, ToyyibPay, SMTP, database, admin atau deploy key yang berkaitan.
2. Tamatkan session dan token pembangun melalui flow password yang disediakan jika akaun pengguna terjejas.
3. Preserve bukti dan cari skop tanpa menyalin secret ke output.
4. Jalankan Gitleaks pada sejarah penuh dan working tree.
5. Jika secret pernah masuk Git, rotation tetap wajib walaupun commit dibuang.
6. Semak access log, authorization denial dan perubahan peranan; identiti sistem tidak boleh dipindah melalui UI biasa.

## Recovery data

Jangan restore terus ke database production. Ikut [backup-restore.md](backup-restore.md): verify recovery point, restore ke database/path baharu, jalankan drill dan smoke test, kemudian dapatkan kelulusan cutover. Kekalkan database/storage lama read-only sepanjang rollback window.

## Penutupan insiden

Insiden hanya ditutup selepas:

- `/up` dan `/health` stabil;
- SHA, migration, scheduler dan queue disahkan;
- flow terjejas lulus smoke test tanpa caj sebenar;
- tiada error baharu berkaitan;
- credential terdedah telah dirotasi;
- backup selepas pemulihan verified;
- timeline, punca akar, impak, tindakan pembetulan dan pemilik susulan direkodkan.
