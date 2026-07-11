# Reka Bentuk Fungsi Uji Chatbot

**Tarikh:** 11 Julai 2026  
**Status:** Diluluskan melalui arahan pengguna untuk melaksanakan fungsi Uji Chatbot  
**Skop:** Pengguna berautentikasi menguji chatbot milik sendiri daripada tindakan chatbot

## Matlamat

Tambah tindakan `Uji chatbot` pada setiap chatbot di Papan Pemuka dan halaman Chatbot Saya. Tindakan ini membuka popup sembang yang menggunakan soal jawab dan tetapan chatbot sebenar tanpa memerlukan kod pemasangan pada laman web luar.

## Pendekatan yang dipertimbangkan

1. **Popup owner-only dengan endpoint berautentikasi — dipilih.** Pengguna kekal pada senarai chatbot, chatbot tidak aktif masih boleh diuji, dan ujian tidak bergantung pada domain whitelist.
2. **Halaman ujian khusus.** Mudah dipautkan tetapi menambah navigasi dan memisahkan pengguna daripada senarai chatbot.
3. **Guna terus API widget awam.** Paling sedikit kod UI, tetapi ujian akan gagal untuk chatbot tidak aktif atau domain yang tidak dibenarkan serta boleh menghabiskan kuota dan mencemarkan chat log.

## Tingkah laku pengguna

- Setiap baris chatbot mempunyai ikon sembang dengan tooltip dan `aria-label` yang menyebut nama chatbot.
- Ikon tersedia pada Papan Pemuka dan halaman Chatbot Saya.
- Klik ikon membuka dialog modal bertajuk `Uji <nama chatbot>`.
- Dialog memaparkan avatar, nama paparan, mesej alu-aluan, sejarah mesej sementara, input dan butang hantar.
- Label `Mod ujian — mesej tidak dikira dalam kuota` menerangkan skop ujian.
- Butang `Kosongkan chat` menghapus sejarah pada browser sahaja dan mengembalikan mesej alu-aluan.
- Butang tutup, klik backdrop dan kekunci Escape menutup dialog.
- Dialog mengurus fokus: fokus masuk ke input ketika dibuka dan kembali ke ikon asal apabila ditutup.
- Satu popup digunakan semula bagi semua chatbot; tiada dialog pendua bagi setiap baris.

## Seni bina

### Pemadan respons bersama

Logik pemadanan jawapan dikeluarkan daripada `ApiController` ke servis fokus, `ChatbotResponseMatcher`.

- Input: model `Chatbot` dan mesej pengguna yang telah divalidasi.
- Output: satu rentetan jawapan.
- API widget awam dan endpoint ujian pemilik menggunakan servis yang sama supaya hasil ujian sama dengan hasil sebenar.
- Refactor ini mesti mengekalkan tingkah laku pemadanan semasa; pembaikan ketepatan algoritma daripada audit QA ialah kerja berasingan.

### Endpoint ujian pemilik

Endpoint baharu:

```text
POST /chatbots/{chatbot}/test-message
route: chatbots.test-message
middleware: web, auth
```

Controller satu tindakan akan:

1. mengesahkan pengguna memiliki chatbot atau merupakan pentadbir melalui policy `view`;
2. menerima `message` sebagai string wajib maksimum 1,000 aksara;
3. memanggil `ChatbotResponseMatcher` walaupun chatbot tidak aktif;
4. mengembalikan JSON `{ "response": "..." }`;
5. tidak menulis `chat_logs`, tidak menyemak kuota mesej dan tidak menyemak domain whitelist.

Permintaan tanpa hak menerima `403`; permintaan tanpa login menerima redirect/JSON authentication standard Laravel; validation JSON menerima `422`.

### Popup frontend

Partial Blade bersama dimuatkan hanya pada halaman yang menyediakan tindakan ujian. Data per chatbot disimpan pada atribut `data-*` yang di-escape oleh Blade:

- URL endpoint ujian;
- nama chatbot dan nama paparan;
- URL avatar;
- mesej alu-aluan;
- warna utama.

JavaScript menggunakan `fetch` dengan `POST`, token CSRF dan header `Accept: application/json`. Penghantaran dikunci semasa request aktif bagi menghalang double-submit. Respons ditambah menggunakan `textContent`, bukan `innerHTML`.

Jika request gagal:

- mesej pengguna kekal dalam sejarah;
- bubble ralat mesra pengguna dipaparkan dalam dialog;
- `window.showToast` memaparkan popup error di bahagian atas;
- input dan butang diaktifkan semula.

## Data dan kuota

- Tiada jadual atau migration baharu.
- Sejarah ujian hanya wujud dalam DOM sehingga dialog dikosongkan, halaman direfresh atau pengguna memilih chatbot lain.
- Mesej ujian tidak masuk ke analitik, chat log atau had bulanan.
- Menukar chatbot semasa dialog digunakan mengosongkan sejarah chatbot sebelumnya.

## Keselamatan

- Endpoint berada dalam kumpulan `auth` dan dilindungi CSRF.
- Authorization dilakukan pada server; atribut frontend bukan sumber kebenaran.
- API key tidak didedahkan oleh fungsi ujian.
- Input maksimum 1,000 aksara dan output dirender sebagai teks.
- Tiada domain whitelist bypass pada API awam; pengecualian domain hanya berlaku pada endpoint owner-only.
- Tiada side effect database daripada mesej ujian.

## Accessibility dan responsive

- Dialog menggunakan `role="dialog"`, `aria-modal="true"`, tajuk dan penerangan berhubung.
- Semua butang mempunyai nama boleh akses dan state loading yang jelas.
- Senarai mesej menggunakan `role="log"` dan `aria-live="polite"`.
- Input kekal sekurang-kurangnya 16px pada mobile untuk mengelakkan auto-zoom.
- Dialog muat pada viewport 320px hingga desktop dan mesej panjang boleh wrap tanpa overflow mendatar.
- `prefers-reduced-motion` sedia ada dihormati.

## Ujian penerimaan

### Backend

- Pemilik boleh menguji chatbot aktif dan tidak aktif.
- Pengguna lain menerima `403` dan tiada data ditulis.
- Pentadbir boleh menguji chatbot yang diurusnya mengikut policy sedia ada.
- Mesej kosong dan melebihi 1,000 aksara menerima `422`.
- Ujian tidak menambah `chat_logs` atau penggunaan kuota.
- API widget awam masih menggunakan pemadan yang sama dan semua ujian sedia ada kekal lulus.

### Frontend

- Ikon ujian wujud pada kedua-dua senarai chatbot dengan label yang betul.
- Popup membuka chatbot yang dipilih, menghantar mesej dan memaparkan respons.
- Double-submit disekat.
- Kosongkan chat memulihkan mesej alu-aluan.
- Error menggunakan bubble dialog dan toast global.
- Fokus, Escape dan penutupan modal berfungsi.
- Tiada clipping pada desktop, tablet, 390px dan 320px.

## Di luar skop

- Membetulkan algoritma padanan semantik yang dikenal pasti dalam audit QA.
- Menyimpan sejarah ujian.
- Streaming respons atau integrasi LLM baharu.
- Mengubah kuota pelan, chat log awam atau domain whitelist.
- Menambah session countdown atau perubahan sistem pembayaran.

