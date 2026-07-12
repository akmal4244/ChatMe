# Runbook Production ChatMe

Runbook ini melengkapkan [reka bentuk deployment/rollback](../superpowers/specs/2026-07-12-cpanel-deployment-rollback-design.md). Ia tidak menggantikan backup atau restore drill dalam [backup-restore.md](backup-restore.md).

## Laluan dan invariant semasa

```bash
APP_ROOT=/home2/akmalmar/public_html/chatme.akmalmarvis.com
STATE_ROOT=/home2/akmalmar/deploy-state/chatme
BACKUP_ROOT=/home2/akmalmar/secure-backups/chatme
```

- `STATE_ROOT` dan setiap backup mesti berada di luar project/public web root.
- Target deployment mesti full lowercase Git SHA 40 aksara.
- Working tree mesti bersih.
- Target mesti forward descendant daripada HEAD dan boleh dicapai oleh approved remote release ref.
- Deployment dan rollback berkongsi non-blocking lock.
- Jangan jalankan seeder umum, pembayaran sebenar, `migrate:rollback`, reset Git destruktif atau database restore sebagai sebahagian deployment.

## Pra-deployment

1. Sahkan change set telah melalui semua quality gates dalam `README.md`.
2. Semak kapasiti hosting dan kestabilan SSH. Jika NPROC hampir had, HTTP 503, `Unable to fork` atau SSH reset berlaku, hentikan deployment.
3. Sahkan tree dan release semasa:

```bash
cd "$APP_ROOT"
git status --short
git rev-parse HEAD
php artisan migrate:status
php artisan schedule:list
```

`git status --short` mesti kosong. Jangan teruskan jika SHA atau migration state tidak dijangka.

4. Cipta backup dan verify mengikut [backup-restore.md](backup-restore.md). Simpan path backup tepat yang dikeluarkan oleh script.

## Deployment exact SHA

Tetapkan nilai yang telah diluluskan, bukan SHA atau branch pilihan sendiri:

```bash
TARGET_SHA=0123456789abcdef0123456789abcdef01234567
RELEASE_REF=main
BACKUP=/home2/akmalmar/secure-backups/chatme/chatme-YYYYMMDDTHHMMSSZ-xxxxxxxx

php scripts/ops/deploy.php \
  --target-sha="$TARGET_SHA" \
  --project-root="$APP_ROOT" \
  --state-root="$STATE_ROOT" \
  --backup="$BACKUP" \
  --web-root="$APP_ROOT" \
  --remote=origin \
  --release-ref="$RELEASE_REF" \
  --actor="$USER" \
  --max-processes=80
```

Script menjalankan preflight selepas lock, masuk maintenance, switch ke exact detached SHA, memasang dependency production, membina frontend jika `package-lock.json` wujud, menjalankan migrasi forward, membina cache, menguji release secara dalaman dan sentiasa cuba `artisan up`. Success mengeluarkan satu baris JSON dengan `deployment_id`, previous SHA, target SHA dan status. Simpan deployment ID untuk rollback.

Jika script gagal, jangan jalankan arahan raw secara rawak. Baca event bertandatangan di `STATE_ROOT/deployments/<deployment_id>`, sahkan sama ada automatic recovery dan `maintenance_up` berjaya, kemudian ikut [incident-response.md](incident-response.md).

## Post-deployment

```bash
git rev-parse HEAD
git status --short
php artisan migrate:status
php artisan route:list
php artisan schedule:list
curl --fail --silent --show-error https://chatme.akmalmarvis.com/up
curl --fail --silent --show-error https://chatme.akmalmarvis.com/health
```

Jika queue worker berterusan digunakan:

```bash
php artisan queue:restart
```

Smoke test tanpa pembayaran sebenar:

1. homepage dan chatbot rasmi;
2. daftar/log masuk, pengesahan e-mel, reset kata laluan dan profil;
3. dashboard, knowledge import, serta create/edit/test/delete hanya chatbot QA sementara milik akaun ujian—jangan ubah atau padam chatbot pelanggan;
4. widget pada origin yang dibenarkan, termasuk expiry/refresh ticket;
5. halaman pelan dan penciptaan checkout hanya dalam sandbox yang diluluskan;
6. panel pentadbir;
7. log baharu `ERROR` atau `CRITICAL` sejak deployment.

Production hanya dianggap selari apabila HEAD sama dengan `TARGET_SHA`, health lulus, migration selesai dan smoke test lulus.

## Rollback code-only

Rollback menerima deployment ID daripada deployment berjaya, bukan SHA bebas:

```bash
DEPLOYMENT_ID=YYYYMMDDTHHMMSSZ-xxxxxxxx

php scripts/ops/rollback.php \
  --deployment-id="$DEPLOYMENT_ID" \
  --project-root="$APP_ROOT" \
  --state-root="$STATE_ROOT" \
  --web-root="$APP_ROOT" \
  --actor="$USER" \
  --max-processes=80
```

Rollback menolak state diubah, HEAD yang tidak sama dengan target record, backup tidak sah dan deployment yang telah berjaya di-rollback. Ia memulihkan code/dependency/cache sahaja. Ia tidak menurunkan schema dan tidak restore database. Jika code lama tidak serasi dengan schema forward, pilih roll-forward atau proses recovery database berasingan dalam [backup-restore.md](backup-restore.md).

## Scheduler dan queue

Cron cPanel mesti memanggil scheduler sekali seminit dari app root:

```cron
* * * * * cd /home2/akmalmar/public_html/chatme.akmalmarvis.com && php artisan schedule:run >> /dev/null 2>&1
```

Sahkan path PHP melalui cPanel sebelum menyimpan cron; jika `php` tiada dalam cron `PATH`, gantikannya dengan absolute PHP binary yang ditetapkan hosting. Semak jadual sebenar:

```bash
php artisan schedule:list
```

Jadual repo menjalankan:

- `queue:prune-failed --hours=168` setiap hari 02:10;
- `queue:prune-batches --hours=168 --unfinished=168 --cancelled=168` setiap hari 02:20;
- `chatme:prune-message-quota-reservations` setiap lima minit.

Jika queued jobs mula digunakan, jalankan `php artisan queue:work` melalui process manager yang dipantau. Jangan cipta loop worker melalui cron yang boleh menaikkan NPROC tanpa had. Gunakan:

```bash
php artisan queue:failed
php artisan queue:restart
```

untuk semakan failed jobs dan reload worker selepas release.

## Provisioning pentadbir

`AdminSeeder` hanya berjalan apabila `ADMIN_NAME`, `ADMIN_EMAIL` dan `ADMIN_PASSWORD` tersedia. Ia mencipta identiti `primary_admin` baharu dan gagal jika e-mel telah diambil oleh akaun tidak bertanda; ia tidak merampas akaun dan tidak reset password pentadbir sedia ada.

1. Letakkan tiga pemboleh ubah melalui stor rahsia hosting, bukan command line atau Git.
2. Pastikan config cache membaca nilai yang dimaksudkan.
3. Jalankan kelas tepat:

```bash
php artisan db:seed --class=AdminSeeder --force
```

4. Log masuk dan sahkan identiti/peranan.
5. Keluarkan `ADMIN_PASSWORD` bootstrap daripada konfigurasi, kemudian bina semula config cache.

Perubahan peranan pengguna biasa dibuat melalui panel `/admin/users`. Peranan akaun sistem tidak boleh ditukar melalui panel.

## Adoption chatbot homepage

Ini bukan langkah automatik deployment.

1. Cipta dan verify backup.
2. Dapatkan ID tepat chatbot production legacy yang mempunyai slug rasmi.
3. Tetapkan `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID` melalui konfigurasi operator.
4. Kosongkan config cache dan jalankan:

```bash
php artisan db:seed --class=HomepageChatbotSeeder --force
```

5. Sahkan bot mempunyai system role rasmi, pemilik sistem bukan admin, API key/log/custom knowledge kekal, token pembangun lama telah dibatalkan dan 33 knowledge item bertanda wujud.
6. Keluarkan `CHATME_HOMEPAGE_LEGACY_CHATBOT_ID`, bina semula config cache dan smoke test homepage/widget.

Seeder gagal-tertutup jika reserved owner, entitlement, system role, slug atau ID legacy bercanggah. Jangan tukar ID semata-mata untuk memaksa seeder lulus.

## Semakan had penggunaan selepas migrasi

Migrasi `message_usages` membina ledger bulan semasa daripada log sedia ada supaya pemadaman chatbot tidak boleh mengembalikan kuota. Migrasi harga pula menyimpan snapshot `unit_price_cents` bagi langganan bukan sistem supaya perubahan katalog tidak mengubah nilai kredit proration lampau.

Selepas migrasi production, sahkan kedua-dua migration telah bertanda `Ran` dan buat semakan agregat sahaja; jangan cetak mesej pelanggan atau credential:

```bash
php artisan migrate:status
php artisan tinker
```

Dalam sesi Tinker, bandingkan jumlah baris ledger bulan semasa dengan bilangan pemilik yang mempunyai log bulan semasa, dan pastikan langganan berbayar mempunyai `unit_price_cents`. Langganan sistem boleh kekal tanpa harga dan tanpa tarikh tamat.

## Pemeriksaan berkala

```bash
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
git status --short
git rev-parse HEAD
```

- Pastikan cron scheduler mempunyai rekod kejayaan terkini.
- Pantau `/up`, `/health`, penggunaan disk, NPROC dan error log.
- Pastikan backup harian verified, salinan off-host encrypted tersedia dan restore drill terkini lulus.
- Semak credential provider mengikut polisi rotation tanpa mencetak nilainya.
