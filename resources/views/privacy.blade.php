@extends('layouts.guest')
@section('title', 'Dasar Privasi — ChatMe')
@section('content')
<div class="max-w-3xl py-8">
    <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Dasar Privasi</h1>
    <p class="text-sm text-white/25 mb-8">Dikemaskini terakhir: {{ date('d/m/Y') }}</p>
    <div class="prose prose-neutral max-w-none space-y-6 text-white/40 leading-relaxed">
        <p>ChatMe ("kami", "kita") mengendalikan platform chatbot di chatme.akmalmarvis.com. Halaman ini memaklumkan anda tentang dasar kami mengenai pengumpulan, penggunaan, dan pendedahan data peribadi.</p>
        <h2 class="text-xl font-bold text-white mt-8">Data yang Dikumpul</h2>
        <p>Kami mengumpul maklumat yang anda berikan secara langsung: nama, alamat emel, dan kandungan chatbot. Kami juga mengumpul data penggunaan secara automatik: alamat IP, jenis pelayar, dan halaman yang dilawati.</p>
        <h2 class="text-xl font-bold text-white mt-8">Penggunaan Data</h2>
        <p>Data digunakan untuk: menyediakan perkhidmatan chatbot, komunikasi berkaitan akaun, penambahbaikan produk, dan pematuhan undang-undang.</p>
        <h2 class="text-xl font-bold text-white mt-8">Hubungi</h2>
        <p>Sebarang pertanyaan: <a href="mailto:akmal4244@gmail.com" class="text-white hover:underline">akmal4244@gmail.com</a></p>
    </div>
</div>
@endsection