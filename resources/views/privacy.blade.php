@extends('layouts.guest')

@section('title', 'Dasar Privasi — ChatMe')

@section('content')
<article class="legal-page legal-article" aria-labelledby="privacy-heading">
        <header class="legal-header">
            <a href="{{ route('landing') }}" class="back-link">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Kembali ke ChatMe
            </a>
            <h1 id="privacy-heading">Dasar Privasi</h1>
            <p>Dikemas kini pada <time datetime="2026-07-10">10 Julai 2026</time></p>
        </header>

        <div class="legal-content">
            <p>ChatMe mengendalikan platform chatbot di chatme.akmalmarvis.com. Dasar ini menerangkan data yang kami kumpul, tujuan penggunaannya dan pilihan anda.</p>

            <section aria-labelledby="privacy-data">
                <h2 id="privacy-data">Data yang kami kumpul</h2>
                <p>Kami mengumpul maklumat yang anda berikan secara langsung, termasuk nama, alamat e-mel, nombor telefon untuk bil, serta kandungan chatbot dan pengetahuan yang anda masukkan atau import. Kami juga boleh merekodkan data teknikal seperti alamat IP, jenis pelayar dan aktiviti penggunaan untuk keselamatan serta operasi perkhidmatan.</p>
            </section>

            <section aria-labelledby="privacy-use">
                <h2 id="privacy-use">Cara data digunakan</h2>
                <p>Data digunakan untuk menyediakan dan melindungi akaun anda, memproses fungsi chatbot, mengurus langganan, menyelesaikan masalah, menambah baik produk dan memenuhi kewajipan undang-undang.</p>
            </section>

            <section aria-labelledby="privacy-payment">
                <h2 id="privacy-payment">Pembayaran</h2>
                <p>Pembayaran {{ config('services.toyyibpay.dnqr_enabled') ? 'FPX atau DuitNow QR' : 'FPX' }} diproses oleh ToyyibPay. ChatMe menyimpan rujukan pesanan, jumlah, pelan dan status pembayaran untuk mengurus akses langganan, tetapi tidak menyimpan kata laluan atau kelayakan perbankan anda.</p>
            </section>

            <section aria-labelledby="privacy-protection">
                <h2 id="privacy-protection">Perlindungan dan perkongsian data</h2>
                <p>Kami menggunakan kawalan teknikal dan organisasi yang munasabah untuk melindungi data. Data hanya dikongsi dengan penyedia yang diperlukan untuk mengendalikan perkhidmatan, atau apabila diwajibkan oleh undang-undang.</p>
            </section>

            <section aria-labelledby="privacy-rights">
                <h2 id="privacy-rights">Pilihan anda</h2>
                <p>Anda boleh meminta akses, pembetulan atau pemadaman data peribadi anda, tertakluk pada rekod yang perlu disimpan bagi tujuan keselamatan, pembayaran atau undang-undang.</p>
            </section>

            <section aria-labelledby="privacy-contact">
                <h2 id="privacy-contact">Hubungi kami</h2>
                <p>Untuk pertanyaan privasi, e-mel <a href="mailto:akmal4244@gmail.com">akmal4244@gmail.com</a>.</p>
            </section>
        </div>
</article>
@endsection
