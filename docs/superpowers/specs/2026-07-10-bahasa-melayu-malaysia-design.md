# Reka Bentuk Penyelarasan Bahasa Melayu Malaysia ChatMe

## Matlamat

Semua teks sistem yang dilihat pengguna ChatMe mesti menggunakan Bahasa Melayu Malaysia yang mudah difahami, mesra dan sesuai untuk pengguna di Kuala Lumpur. Pelaksanaan mesti meliputi halaman awam, akaun, papan pemuka, borang pengurusan, pentadbir, langganan ToyyibPay, widget, respons API dan mesej ralat rangka kerja.

## Gaya bahasa

- Gunakan nada mesra dan neutral dengan kata ganti `anda`.
- Utamakan ayat pendek, aktif dan terus kepada tindakan pengguna.
- Gunakan `tidak`, `mahu`, `baharu`, `kemas kini`, `e-mel` dan bentuk Bahasa Melayu Malaysia yang konsisten.
- Gunakan gaya ayat pada tajuk dan label; jangan meniru huruf besar gaya tajuk bahasa Inggeris.
- Elakkan jargon jika maksud yang lebih mudah tersedia.
- Kekalkan nama jenama dan istilah teknikal yang memang diperlukan: ChatMe, ToyyibPay, FPX, DuitNow QR, API, JSON, HTML, URL, Free, Pro dan Enterprise.
- Terangkan istilah teknikal pada kali ia diperlukan, terutama kod pemasangan, JSON dan API.

## Peta istilah utama

| Teks atau istilah lama | Teks standard |
| --- | --- |
| Dashboard | Papan pemuka |
| Knowledge base / item pengetahuan | Soal jawab chatbot / soal jawab |
| Embed / kod benam | Pasang di laman web / kod pemasangan |
| Avatar | Gambar profil |
| Placeholder | Teks petunjuk dalam kotak mesej |
| System prompt | Cara chatbot perlu menjawab |
| Domain whitelist | Laman web yang dibenarkan |
| Admin | Pentadbir |
| Email | E-mel |
| Server | Nyatakan pihak sebenar seperti ToyyibPay, atau gunakan pelayan hanya jika benar-benar perlu |
| Prorata | Nilai baki tempoh semasa digunakan sebagai kredit untuk pelan baharu |
| Online | Sedia membantu |
| Powered by | Disediakan oleh |

## Skop perubahan

### 1. Locale dan mesej rangka kerja

- Tetapkan `APP_LOCALE=ms`, `APP_FALLBACK_LOCALE=ms` dan `APP_FAKER_LOCALE=ms_MY` pada contoh persekitaran, ujian dan production.
- Tukar nilai lalai `config/app.php` kepada `ms`, `ms` dan `ms_MY` supaya pemasangan baharu tidak kembali ke bahasa Inggeris apabila pemboleh ubah persekitaran tiada.
- Sediakan `lang/ms/validation.php`, `lang/ms/auth.php`, `lang/ms/passwords.php` dan `lang/ms/pagination.php`.
- Peta nama medan kepada label mudah seperti `alamat e-mel`, `kata laluan`, `nama chatbot`, `mesej alu-aluan`, `soalan`, `jawapan`, `nombor telefon` dan `data JSON`.
- Pastikan mesej validasi web dan JSON 422 menggunakan Bahasa Melayu.

### 2. Salinan antaramuka

- Semak dan betulkan semua fail Blade dalam `resources/views`.
- Selaraskan halaman utama, privasi, terma, log masuk, pendaftaran, papan pemuka, onboarding, pengurusan chatbot, soal jawab, pentadbir dan langganan.
- Tukar arahan teknikal kepada langkah yang boleh dilakukan pengguna.
- Tambah keadaan kosong yang jelas pada jadual pentadbir.
- Perjelas pengesahan tindakan kekal seperti memadam chatbot, memadam soal jawab, menukar peranan pentadbir dan menjana semula kunci API.
- Paparkan ralat medan secara jelas pada semua borang, termasuk halaman tambah dan sunting soal jawab.

### 3. Widget dan API

- Tukar teks lalai widget kepada `Pembantu ChatMe`, `Helo! Bagaimana saya boleh membantu anda?`, `Taip mesej anda...`, `Sedia membantu` dan `Disediakan oleh ChatMe`.
- Selaraskan empat jawapan fallback chatbot kepada gaya `anda` yang mesra dan neutral.
- Tukar respons API pengguna seperti sekatan domain dan had mesej bulanan kepada Bahasa Melayu tanpa mengubah kunci JSON, status HTTP atau kawalan kuota.
- Sediakan respons JSON selamat dalam Bahasa Melayu untuk ralat HTTP API biasa tanpa mendedahkan mesej pengecualian dalaman.
- Kekalkan rentetan kawalan dalaman JavaScript dan kod sebab penyedia jika ia tidak dipaparkan kepada pengguna.

### 4. Langganan dan pembayaran

- Jelaskan bahawa pembaharuan pelan dibuat secara manual setiap bulan dan bukan debit automatik.
- Terangkan pertukaran pelan tanpa istilah `prorata`.
- Bezakan dengan jelas status `menunggu`, `tidak berjaya` dan `berjaya`.
- Gantikan penerangan dalaman seperti `parameter pulangan pelayar` dengan arahan berguna kepada pengguna.
- Paparkan tarikh menggunakan locale Melayu supaya nama bulan tidak keluar dalam bahasa Inggeris.
- Jangan terjemah token protokol ToyyibPay `OK`, `INVALID`, `CONFLICT`, `ERROR` dan `UNAVAILABLE`, kod sebab dalaman atau nama medan penyedia.

### 5. Teks lalai dan data sedia ada

- Tukar nilai lalai pada skema dan seeder untuk pemasangan baharu.
- Tambah migrasi data yang hanya menukar nilai lama apabila ia sepadan tepat dengan teks lalai Inggeris ChatMe.
- Jangan terjemah nama chatbot, arahan, soal jawab atau kandungan lain yang telah diubah atau dicipta pengguna.
- Jangan mengubah nilai protokol, slug, status pangkalan data atau logik pembayaran.

### 6. Halaman ralat

- Kekalkan halaman 404 yang sedia ada dan sediakan halaman Bahasa Melayu yang seragam untuk 403, 419, 429, 500 dan 503.
- Setiap halaman menerangkan masalah secara ringkas dan memberi tindakan pemulihan yang sesuai.
- Production kekal dengan `APP_DEBUG=false` supaya butiran teknikal tidak terdedah.

## Kaedah pelaksanaan

1. Tambah ujian regresi yang membuktikan locale semasa masih `en` dan rentetan Inggeris masih wujud.
2. Jalankan ujian terpilih dan sahkan ia gagal atas sebab yang dijangka.
3. Tambah pek bahasa, salinan antaramuka, respons API, widget, halaman ralat dan migrasi data minimum.
4. Kemas kini ujian sedia ada yang mengikat rentetan lama tanpa mengubah tujuan keselamatan atau logiknya.
5. Jalankan suite PHP, ujian JavaScript, Pint, audit dependency dan build production.
6. Jalankan audit statik untuk rentetan pengguna yang dilarang dan pemeriksaan browser pada 320px, 390px dan 1440px.
7. Sandarkan production, deploy commit yang disahkan, jalankan migrasi dan cache semula konfigurasi.
8. Sahkan locale, halaman, widget, API, log dan SHA production selepas deploy.

## Batasan yang disengajakan

- Tiada penukar bahasa atau antaramuka dwibahasa ditambah.
- Kandungan ciptaan pengguna tidak diterjemah secara automatik.
- Nama pelan Free, Pro dan Enterprise tidak ditukar kerana ia ialah nama produk.
- Log dalaman dan pengecualian pembangun boleh kekal dalam bahasa Inggeris selagi tidak dipaparkan kepada pengguna.
- Tiada perubahan dibuat pada pengiraan langganan, pengesahan callback, kuota atau kawalan kebenaran.

## Kriteria penerimaan

- `app()->getLocale()` dan locale production ialah `ms`; fallback ialah `ms`.
- Mesej `required`, `email`, `confirmed`, `unique`, `max`, `url`, `in`, `regex`, `uuid`, `size` dan had kata laluan yang digunakan aplikasi dipaparkan dalam Bahasa Melayu.
- Tiada rentetan pengguna utama yang masih menggunakan `dashboard`, `server`, `placeholder`, `Powered by`, `Online`, `Domain not allowed`, `Monthly message limit reached` atau mesej log masuk Inggeris.
- Semua halaman awam, akaun, aplikasi dan pentadbir mempunyai salinan yang konsisten serta mudah difahami.
- Widget menggunakan teks lalai Bahasa Melayu dan masih selamat pada skrin kecil.
- API mengekalkan kontrak JSON dan status HTTP sambil memulangkan mesej Bahasa Melayu.
- Rekod tersuai pengguna tidak berubah; hanya nilai lalai Inggeris yang sepadan tepat dibetulkan.
- Semua ujian dan audit lulus, worktree bersih, GitHub dan production berada pada commit yang sama, migrasi tiada yang tertangguh dan log production tiada ralat baharu.
