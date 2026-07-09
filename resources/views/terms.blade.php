@extends('layouts.guest')
@section('title', 'Terma Perkhidmatan — ChatMe')
@section('content')
<div class="max-w-3xl py-8">
    <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Terma Perkhidmatan</h1>
    <p class="text-sm text-white/25 mb-8">Dikemaskini terakhir: {{ date('d/m/Y') }}</p>
    <div class="prose prose-neutral max-w-none space-y-6 text-white/40 leading-relaxed">
        <p>Dengan menggunakan ChatMe, anda bersetuju dengan terma berikut. Jika tidak bersetuju, sila hentikan penggunaan perkhidmatan.</p>
        <h2 class="text-xl font-bold text-white mt-8">Akaun</h2>
        <p>Anda bertanggungjawab menjaga kerahsiaan kata laluan. Anda mesti berumur sekurang-kurangnya 18 tahun untuk menggunakan perkhidmatan ini.</p>
        <h2 class="text-xl font-bold text-white mt-8">Langganan & Pembayaran</h2>
        <p>Pelan berbayar dikenakan caj bulanan. Pembatalan boleh dibuat bila-bila masa melalui papan pemuka.</p>
        <h2 class="text-xl font-bold text-white mt-8">Had Tanggungjawab</h2>
        <p>ChatMe disediakan "sebagaimana adanya". Kami tidak bertanggungjawab atas sebarang kerosakan langsung atau tidak langsung daripada penggunaan perkhidmatan.</p>
    </div>
</div>
@endsection