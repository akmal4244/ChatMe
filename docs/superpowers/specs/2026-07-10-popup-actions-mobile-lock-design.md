# Reka Bentuk Popup Tindakan, Notifikasi Global dan Kuncian Zoom Mobile ChatMe

## Matlamat

ChatMe mesti memberi maklum balas tindakan yang konsisten seperti aplikasi mobile. Lajur `Tindakan` pada papan pemuka perlu mempunyai pilihan padam chatbot, semua tindakan berisiko perlu menggunakan popup pengesahan ChatMe, semua mesej berjaya, gagal dan makluman perlu muncul sebagai toast popup, dan paparan ChatMe pada browser mobile perlu kekal pada skala tetap tanpa pinch zoom atau auto-zoom ketika pengguna menaip.

## Keputusan reka bentuk

- Gunakan satu pengurus popup dan toast global dalam layout aplikasi; jangan cipta pelaksanaan berasingan bagi setiap halaman.
- Gunakan popup ChatMe tersuai dan hentikan penggunaan `window.confirm()` untuk tindakan aplikasi.
- Kekalkan ralat validasi bersebelahan medan supaya pengguna tahu ruangan yang perlu dibetulkan, sambil memaparkan satu toast ringkasan.
- Kunci zoom pada semua halaman ChatMe kerana pengguna memilih pengalaman aplikasi mobile sepenuhnya, walaupun pilihan ini mengurangkan kebolehan pengguna membesarkan paparan secara manual.
- Jangan ubah meta viewport laman web pihak lain yang memasang widget ChatMe.

## Skop tindakan

### Padam chatbot pada papan pemuka

- Tambah ikon tong sampah merah dalam lajur `Tindakan` pada setiap baris chatbot di papan pemuka.
- Ikon menggunakan route pemadaman chatbot sedia ada, token CSRF dan kaedah `DELETE`.
- Label boleh akses mesti menyebut nama chatbot, contohnya `Padam chatbot Sokongan Pelanggan`.
- Pengesahan server-side pemilik atau pentadbir kekal menjadi kawalan keselamatan sebenar; popup hanya melindungi pengguna daripada klik tidak sengaja.

### Tindakan yang memerlukan pengesahan

Sistem popup global digunakan untuk:

- memadam chatbot dari papan pemuka atau senarai chatbot;
- memadam soal jawab;
- menjana semula kunci API;
- memberi atau menarik balik peranan pentadbir;
- log keluar; dan
- tindakan kekal lain yang ditambah kemudian melalui kontrak atribut data yang sama.

Setiap borang berisiko menyatakan tajuk, penerangan, teks butang pengesahan, jenis visual dan nama item melalui atribut `data-confirm-*`. Kod global memintas penghantaran pertama, membuka modal dan hanya meneruskan selepas pengguna mengesahkan.

## Popup pengesahan

- Popup padam chatbot memaparkan tajuk `Padam chatbot?`.
- Penerangan menyebut nama chatbot dan menjelaskan bahawa soal jawab serta sejarah sembang berkaitan akan dipadam dan tindakan tidak boleh dibatalkan.
- Butang disusun sebagai `Batal` dan `Padam chatbot`; butang padam menggunakan gaya bahaya merah.
- `Batal`, klik pada latar popup dan kekunci `Escape` menutup popup tanpa menghantar borang.
- Fokus masuk ke butang `Batal` apabila popup dibuka dan kembali ke pencetus asal apabila ditutup.
- Fokus kekal terperangkap dalam popup ketika ia dibuka.
- Klik berganda pada butang pengesahan disekat. Borang dihantar sekali sahaja menggunakan penanda pengesahan dalaman yang mengelakkan gelung acara `submit`.
- Kandungan dinamik dimasukkan menggunakan `textContent` atau serialisasi selamat; nama pengguna atau chatbot tidak boleh dimasukkan sebagai HTML mentah.

## Notifikasi toast global

### Sumber notifikasi

Toast perlu meliputi:

- mesej sesi server `success`, `error` dan `info`;
- penciptaan, kemas kini, pemadaman dan perubahan status chatbot;
- penambahan, kemas kini, import dan pemadaman soal jawab;
- perubahan pelan, pembayaran dan status langganan;
- perubahan peranan pentadbir;
- penjanaan semula kunci API;
- kejayaan atau kegagalan menyalin kod dan teks; dan
- ringkasan kegagalan validasi borang.

### Tingkah laku toast

- Toast muncul sebagai timbunan popup di penjuru skrin yang selamat dan berpindah ke kedudukan mesra mobile pada skrin kecil.
- Jenis `success`, `error` dan `info` mempunyai ikon, warna dan label Bahasa Melayu yang berbeza.
- Mesej `success` dan `info` menggunakan `role="status"`; mesej `error` menggunakan `role="alert"`.
- Setiap toast mempunyai butang tutup yang boleh dicapai papan kekunci.
- Toast berjaya ditutup automatik selepas 4 saat, makluman selepas 5 saat dan ralat selepas 7 saat. Pemasa berhenti ketika penuding atau fokus berada pada toast.
- Beberapa toast boleh dipaparkan tanpa menindih satu sama lain, tetapi mesej sama yang dicetuskan berturut-turut akan digabungkan supaya pengguna tidak dibanjiri popup.
- Ralat medan kekal di tempat asal. Toast validasi hanya menerangkan bahawa borang belum dapat dihantar dan mengarahkan pengguna menyemak medan bertanda.

## Aliran data

1. Controller Laravel menyimpan mesej sesi seperti biasa dan mengalih pengguna ke halaman sasaran.
2. Layout menukar mesej sesi kepada data JSON selamat tanpa menghasilkan HTML mesej secara terus.
3. Pengurus notifikasi membaca data tersebut selepas DOM tersedia dan memanggil fungsi toast global.
4. Tindakan JavaScript pada halaman memanggil fungsi global yang sama untuk maklum balas segera.
5. Untuk tindakan berisiko, borang menghantar niat kepada pengurus popup terlebih dahulu. Hanya keputusan pengguna `Teruskan` membenarkan penghantaran sebenar ke server.
6. Server memproses tindakan, menguatkuasakan kebenaran dan memulangkan mesej sesi yang dipaparkan sebagai toast pada halaman seterusnya.

## Kuncian zoom dan pengalaman mobile

- Layout aplikasi dan tetamu menggunakan meta viewport `width=device-width`, `initial-scale=1`, `minimum-scale=1`, `maximum-scale=1`, `user-scalable=no` dan `viewport-fit=cover`.
- Kawalan sentuh aplikasi menggunakan `touch-action: manipulation` apabila sesuai untuk mengelakkan zoom dwiklik tanpa menghalang tatal biasa.
- Semua `input`, `textarea` dan `select` pada mobile mempunyai saiz tulisan sekurang-kurangnya `16px` supaya Safari iPhone tidak melakukan auto-zoom ketika fokus.
- Input mesej widget ChatMe menggunakan `16px` pada mobile. Saiz desktop boleh kekal lebih padat jika media query memastikan mobile tidak turun di bawah `16px`.
- Tinggi tetingkap widget menggunakan unit viewport dinamik yang mengambil kira papan kekunci mobile dan kawasan selamat supaya kepala, mesej dan kotak input tidak ditolak keluar skrin.
- Pertukaran orientasi tidak boleh menghasilkan limpahan mendatar atau meninggalkan popup di luar viewport.
- Skrip widget tidak akan menambah atau mengubah meta viewport pada laman web pelanggan. Pada laman luar, ChatMe hanya mencegah auto-zoom input melalui saiz tulisan `16px`; keputusan membenarkan atau mengunci pinch zoom kekal milik pemilik laman tersebut.

## Pengendalian ralat

- Jika tindakan server gagal, data tidak dianggap berjaya dan controller memulangkan mesej `error` yang selamat untuk toast.
- Jika popup tidak mempunyai borang sasaran yang sah, tindakan dibatalkan dan toast ralat umum dipaparkan; tiada penghantaran dibuat.
- Jika JavaScript tidak tersedia, borang berisiko kekal boleh dihantar oleh browser mengikut semantik HTML. Perlindungan kebenaran dan validasi server masih wajib.
- Mesej ralat tidak mendedahkan pengecualian, respons penyedia pembayaran, token, kunci API atau data sensitif.
- Kegagalan menyalin kandungan tidak memadam kandungan asal dan menghasilkan arahan salin manual yang jelas.

## Kebolehcapaian

- Semua ikon tindakan mempunyai `aria-label` dan tooltip Bahasa Melayu.
- Popup menggunakan `role="dialog"`, `aria-modal="true"`, tajuk dan penerangan yang dipautkan secara programatik.
- Pengurusan fokus, kekunci `Escape`, perangkap `Tab` dan pemulangan fokus mengikuti corak modal sedia ada.
- Toast tidak mengambil fokus secara automatik dan tidak mengganggu pengguna yang sedang menaip.
- Kuncian zoom penuh ialah keputusan produk yang diluluskan pengguna. Saiz teks, sasaran sentuh dan kontras perlu kekal cukup jelas kerana pembesaran manual dinyahaktifkan.

## Kaedah pelaksanaan dan ujian

1. Tambah ujian regresi yang gagal bagi ikon padam papan pemuka, kontrak atribut popup, penghapusan `window.confirm()`, data toast global, meta viewport terkunci dan input mobile `16px`.
2. Laksanakan pengurus popup dan toast global minimum menggunakan struktur modal serta bekas toast sedia ada.
3. Migrasikan tindakan berisiko kepada atribut `data-confirm-*` dan tambah borang padam pada papan pemuka.
4. Tukar mesej sesi serta maklum balas salin kepada toast global tanpa mengubah logik controller atau pembayaran.
5. Kemas kini viewport, gaya kawalan mobile dan widget tanpa mengubah viewport laman luar.
6. Jalankan keseluruhan suite PHP, ujian JavaScript, Pint, build production dan audit dependency.
7. Jalankan QA browser pada desktop, 390px dan 320px: buka dan batal popup, sahkan penghantaran sekali sahaja pada data ujian, periksa toast, fokus, `Escape`, limpahan, konsol serta fokus input chatbot.
8. Deploy commit yang disahkan, bina semula cache Laravel dan pastikan GitHub `main`, production dan worktree menggunakan SHA yang sama.

## Batasan yang disengajakan

- Tiada sistem push notification, e-mel atau notifikasi tersimpan dalam pangkalan data ditambah.
- Toast bukan pengganti ralat medan atau log audit.
- Tiada undo pemadaman ditambah; pengguna dilindungi melalui pengesahan sebelum tindakan.
- Tiada perubahan dibuat pada kawalan kebenaran, transaksi pangkalan data, logik langganan atau ToyyibPay.
- ChatMe tidak mengambil alih tetapan zoom laman web pihak lain yang memasang widget.

## Kriteria penerimaan

- Papan pemuka dan senarai chatbot sama-sama memaparkan ikon padam chatbot dalam lajur `Tindakan`.
- Mengaktifkan padam tidak menghantar permintaan sebelum popup disahkan.
- Popup menyebut nama chatbot, kesan pemadaman dan sifat tindakan yang tidak boleh dibatalkan.
- Membatalkan melalui butang, latar atau `Escape` tidak memadam data dan memulangkan fokus.
- Pengesahan menghantar borang tepat sekali; klik berganda tidak menghasilkan permintaan berganda.
- Tiada tindakan aplikasi yang masih menggunakan `window.confirm()`.
- Semua mesej sesi `success`, `error` dan `info` serta tindakan salin menggunakan toast popup global.
- Ralat validasi kekal dipautkan kepada medan dan menghasilkan satu toast ringkasan sahaja.
- Semua halaman ChatMe mengunci pinch zoom dan mengekalkan skala apabila input difokuskan pada mobile.
- Input widget sekurang-kurangnya `16px` pada mobile, tetingkap sembang kekal dalam viewport semasa papan kekunci dibuka dan tiada limpahan mendatar pada 320px.
- Widget tidak mengubah meta viewport laman web pihak lain.
- Ujian automatik, QA browser, pemeriksaan keselamatan dan deployment production semuanya lulus tanpa ralat konsol baharu.
