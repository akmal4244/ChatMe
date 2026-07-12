# Reka Bentuk Deployment dan Rollback cPanel ChatMe

**Status:** Diluluskan melalui spesifikasi production-grade dan kebenaran penuh pemilik pada 12 Julai 2026.

## Keputusan seni bina

Production kini menggunakan checkout Git terus di `/home2/akmalmar/public_html/chatme.akmalmarvis.com`, dan document root cPanel bergantung pada susun atur itu. Menukar terus kepada release symlink akan memperluas risiko ketika akaun baru pulih daripada NPROC 100/100. Release ini menggunakan deployment in-place yang terkawal dalam maintenance window, exact SHA dan rollback code-only. Migrasi kepada release directory atomik boleh dibuat kemudian sebagai perubahan hosting berasingan.

## Pra-syarat gagal-tertutup

Tool deployment menerima target SHA penuh 40 aksara, lokasi app, state root di luar web root dan path backup terverifikasi. Ia menolak:

- working tree yang tidak bersih;
- backup dalam project/public root;
- backup yang checksum gagal;
- manifest backup yang release SHA tidak sama dengan HEAD semasa;
- target yang tiada pada remote release yang diluluskan;
- downgrade melalui deploy biasa;
- deployment serentak.

Lock bukan blocking disimpan di `/home2/akmalmar/deploy-state/chatme`. State record mode 0600 merekod deployment ID, previous SHA, target SHA, masa UTC, backup path dan keputusan setiap fasa tanpa credential.

## Urutan deployment

1. Ambil lock dan ulang pra-syarat selepas lock.
2. `git fetch --prune origin` dan sahkan target ialah commit yang dicapai oleh remote branch release yang ditetapkan.
3. Rekod previous SHA dan backup terverifikasi.
4. Jalankan `php artisan down --retry=30 --refresh=15`.
5. Tukar checkout bersih kepada target exact SHA menggunakan detached checkout/switch yang tidak memadam fail untracked secara paksa.
6. Jalankan `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
7. Jika `package-lock.json` wujud, jalankan `npm ci` dan `npm run build`.
8. Kosongkan cache stale, jalankan `php artisan migrate --force`, kemudian bina config/route/view cache.
9. Jalankan pemeriksaan CLI: exact SHA, `migrate:status`, route cache dan health controller melalui kernel/command tanpa request luaran.
10. `php artisan up` dalam blok `finally`, kemudian lepaskan lock.

Seeder umum tidak pernah dijalankan. Adoption bot homepage ialah langkah operator berasingan yang memerlukan backup, ID legacy eksplisit dan ujian dry-run sendiri.

Jika mana-mana fasa selepas pertukaran SHA gagal, tool merekod status `failed`, cuba mengembalikan code ke previous SHA yang direkodkan, memulihkan dependencies/cache untuk code itu, dan sentiasa menjalankan `artisan up`. Ia tidak menjalankan `migrate:rollback` atau menulis semula database daripada backup secara automatik.

## Rollback code-only

Rollback menerima deployment ID, bukan SHA bebas. Ia membaca previous/target SHA daripada state record yang ditandatangani checksum, memerlukan HEAD semasa sama dengan target record, working tree bersih dan backup asal masih lulus verification.

Dalam maintenance window, rollback menukar checkout kepada previous SHA, memasang dependency/build yang sepadan dan membina semula cache. Schema/data forward-compatible kekal. Jika code lama tidak serasi dengan schema baharu, operator berhenti dan memilih roll-forward; database restore hanya melalui incident recovery berasingan ke database baharu.

Rollback sentiasa menjalankan `artisan up` dalam `finally`. State append-only merekod siapa/masa/deployment ID serta keputusan, tanpa e-mel, token atau secret.

## P0 incident dan kapasiti

Sebelum deployment, process count mesti di bawah threshold dan SSH mesti stabil. Deployment tidak restart `threadsme` atau `pc-multi-agent` jika NPROC normal. Jika `Unable to fork`, HTTP 503 atau SSH reset muncul semula, deployment berhenti sebelum maintenance dan insiden hosting dieskalasi; script tidak cuba melawan CloudLinux dengan loop proses.

## Ujian dan bukti

Fixture Git/Laravel palsu membuktikan:

- dirty tree, backup mismatch, invalid SHA, remote SHA tidak diluluskan dan lock aktif ditolak sebelum maintenance;
- kegagalan composer, build, migration dan cache semuanya mengaktifkan cleanup `artisan up`;
- deployment berjaya merekod previous/target exact SHA;
- rollback menolak SHA arbitrari atau state diubah;
- rollback tidak pernah memanggil `migrate:rollback` atau restore database;
- secret tidak muncul dalam stdout, stderr atau state.

Production smoke selepas tool lulus tetap wajib: homepage, login, dashboard, chatbot, widget, checkout tanpa caj, `/up`, `/health`, migration status, SHA dan delta log ERROR/CRITICAL.
