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
                <p>Anda bertanggungjawab terhadap maklumat yang diberikan kepada chatbot dan jawapan yang disiarkan. Jangan gunakan ChatMe untuk perkara yang menyalahi undang-undang, menipu, mengganggu, melanggar hak pihak lain atau menjejaskan keselamatan sistem.</p>
            </section>

            <section aria-labelledby="terms-subscription">
                <h2 id="terms-subscription">Langganan dan pembayaran</h2>
                <p>Setiap pembayaran pelan berbayar memberikan akses selama satu bulan bermula selepas pembayaran disahkan. Pembayaran dibuat melalui ToyyibPay menggunakan {{ config('services.toyyibpay.dnqr_enabled') ? 'FPX atau DuitNow QR' : 'FPX' }}.</p>
                <p>Tiada potongan automatik daripada akaun bank anda. Untuk meneruskan pelan berbayar, anda perlu membuat pembayaran baharu bagi bulan seterusnya. Jika tidak diperbaharui, akaun akan kembali kepada pelan Free selepas tempoh berbayar tamat.</p>
                <p>Pelan Enterprise tidak mempunyai had mesej bulanan, namun tertakluk pada had penggunaan saksama yang dipaparkan pada halaman pelan: sehingga {{ number_format((int) config('chatme.chatbots.absolute_limit', 50)) }} chatbot, {{ number_format((int) config('chatme.knowledge.absolute_limit', 5000)) }} soal jawab bagi setiap chatbot dan {{ number_format((int) config('chatme.messaging.limits.owner_daily', 5000)) }} mesej sehari bagi setiap akaun. Had ini melindungi kestabilan dan keselamatan perkhidmatan untuk semua pelanggan.</p>
            </section>

            <section aria-labelledby="terms-availability">
                <h2 id="terms-availability">Ketersediaan perkhidmatan</h2>
                <p>Kami berusaha memastikan ChatMe tersedia dan selamat, tetapi penyelenggaraan, gangguan rangkaian atau perkhidmatan pihak ketiga boleh menjejaskan akses dari semasa ke semasa.</p>
            </section>

            <section aria-labelledby="terms-liability">
                <h2 id="terms-liability">Had tanggungjawab</h2>
                <p>Kami menyediakan ChatMe sebaik mungkin setakat yang dibenarkan undang-undang. Sila semak jawapan chatbot sebelum menggunakannya untuk membuat keputusan penting.</p>
            </section>

            <section aria-labelledby="terms-contact">
                <h2 id="terms-contact">Hubungi kami</h2>
                <p>Untuk pertanyaan tentang terma ini, e-mel <a href="mailto:hello@akmalmarvis.com">hello@akmalmarvis.com</a>.</p>
            </section>
        </div>
</article>
@endsection
