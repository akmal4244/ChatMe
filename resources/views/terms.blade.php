@extends('layouts.guest')

@section('title', 'Terma Perkhidmatan — ChatMe')

@section('content')
<article class="legal-page legal-article" aria-labelledby="terms-heading">
        <header class="legal-header">
            <a href="{{ route('landing') }}" class="back-link">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Kembali ke ChatMe
            </a>
            <h1 id="terms-heading">Terma Perkhidmatan</h1>
            <p>Dikemas kini pada <time datetime="2026-07-10">10 Julai 2026</time></p>
        </header>

        <div class="legal-content">
            <p>Dengan mendaftar atau menggunakan ChatMe, anda bersetuju dengan terma berikut. Jika anda tidak bersetuju, sila hentikan penggunaan perkhidmatan.</p>

            <section aria-labelledby="terms-account">
                <h2 id="terms-account">Akaun</h2>
                <p>Anda mesti memberikan maklumat yang tepat dan bertanggungjawab menjaga kerahsiaan kata laluan serta semua aktiviti pada akaun anda. Pengguna mestilah berumur sekurang-kurangnya 18 tahun.</p>
            </section>

            <section aria-labelledby="terms-use">
                <h2 id="terms-use">Penggunaan yang dibenarkan</h2>
                <p>Anda bertanggungjawab terhadap kandungan yang dimasukkan atau diimport dan jawapan chatbot yang diterbitkan. Jangan gunakan ChatMe untuk kandungan yang menyalahi undang-undang, menipu, mengganggu, melanggar hak pihak lain atau menjejaskan keselamatan sistem.</p>
            </section>

            <section aria-labelledby="terms-subscription">
                <h2 id="terms-subscription">Langganan dan pembayaran</h2>
                <p>Setiap pembayaran pelan berbayar memberikan akses selama satu bulan bermula apabila pembayaran disahkan oleh server. Pembayaran dibuat melalui ToyyibPay menggunakan {{ config('services.toyyibpay.dnqr_enabled') ? 'FPX atau DuitNow QR' : 'FPX' }}.</p>
                <p>Pembaharuan adalah manual dan bukan auto-debit atau potongan automatik daripada akaun bank. Untuk meneruskan pelan berbayar, anda perlu membuat pembayaran baharu bagi bulan seterusnya. Jika tidak diperbaharui, akses akan kembali kepada pelan Free selepas tempoh berbayar tamat.</p>
            </section>

            <section aria-labelledby="terms-availability">
                <h2 id="terms-availability">Ketersediaan perkhidmatan</h2>
                <p>Kami berusaha memastikan ChatMe tersedia dan selamat, tetapi penyelenggaraan, gangguan rangkaian atau perkhidmatan pihak ketiga boleh menjejaskan akses dari semasa ke semasa.</p>
            </section>

            <section aria-labelledby="terms-liability">
                <h2 id="terms-liability">Had tanggungjawab</h2>
                <p>ChatMe disediakan “sebagaimana adanya” setakat yang dibenarkan undang-undang. Anda perlu menyemak jawapan chatbot sebelum bergantung padanya untuk keputusan penting.</p>
            </section>

            <section aria-labelledby="terms-contact">
                <h2 id="terms-contact">Hubungi kami</h2>
                <p>Untuk pertanyaan tentang terma ini, e-mel <a href="mailto:akmal4244@gmail.com">akmal4244@gmail.com</a>.</p>
            </section>
        </div>
</article>
@endsection
