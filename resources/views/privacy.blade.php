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
            <p>ChatMe menyediakan perkhidmatan chatbot di chatme.akmalmarvis.com. Dasar ini menerangkan maklumat yang kami kumpul, sebab maklumat itu digunakan dan pilihan yang anda ada.</p>

            <section aria-labelledby="privacy-data">
                <h2 id="privacy-data">Data yang kami kumpul</h2>
                <p>Kami mengumpul maklumat yang anda berikan, termasuk nama, alamat e-mel dan maklumat yang anda masukkan ke dalam chatbot. Kami juga boleh merekodkan alamat IP, jenis pelayar dan aktiviti penggunaan untuk menjaga keselamatan dan memastikan perkhidmatan berjalan dengan baik.</p>
            </section>

            <section aria-labelledby="privacy-use">
                <h2 id="privacy-use">Cara data digunakan</h2>
                <p>Maklumat ini digunakan untuk menyediakan dan melindungi akaun anda, menjalankan chatbot, mengurus langganan, menyelesaikan masalah, menambah baik ChatMe dan mematuhi undang-undang.</p>
            </section>

            <section aria-labelledby="privacy-payment">
                <h2 id="privacy-payment">Pembayaran</h2>
                <p>Pembayaran {{ config('services.toyyibpay.dnqr_enabled') ? 'FPX atau DuitNow QR' : 'FPX' }} diuruskan oleh ToyyibPay. ChatMe menyimpan nombor rujukan, jumlah, pelan dan status pembayaran untuk mengurus langganan anda. Kami tidak menyimpan kata laluan perbankan anda.</p>
            </section>

            <section aria-labelledby="privacy-protection">
                <h2 id="privacy-protection">Perlindungan dan perkongsian data</h2>
                <p>Kami mengambil langkah yang munasabah untuk melindungi maklumat anda. Maklumat hanya dikongsi dengan pihak yang membantu kami menyediakan perkhidmatan, atau apabila undang-undang mewajibkannya.</p>
            </section>

            <section aria-labelledby="privacy-rights">
                <h2 id="privacy-rights">Pilihan anda</h2>
                <p>Anda boleh meminta untuk melihat, membetulkan atau memadamkan maklumat peribadi anda. Sesetengah rekod mungkin perlu disimpan untuk tujuan keselamatan, pembayaran atau undang-undang.</p>
            </section>

            <section aria-labelledby="privacy-contact">
                <h2 id="privacy-contact">Hubungi kami</h2>
                <p>Untuk pertanyaan privasi, e-mel <a href="mailto:hello@akmalmarvis.com">hello@akmalmarvis.com</a>.</p>
            </section>
        </div>
</article>
@endsection
