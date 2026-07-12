@extends('layouts.app')
@section('page-title', 'Token API pembangun')
@section('title', 'Token API pembangun — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-neutral-950 mb-2">Token API pembangun berjaya dijana</h1>
    <p class="text-neutral-600 mb-6">Token untuk {{ $chatbot->name }} sedia digunakan pada integrasi server-ke-server.</p>

    <section class="card p-5 sm:p-6 mb-6" aria-labelledby="one-time-token-heading">
        <div class="alert alert-warning mb-4" role="alert">
            <strong id="one-time-token-heading">Token ini hanya dipaparkan sekali.</strong>
            Salin dan simpan sekarang sebelum meninggalkan halaman ini.
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <code id="developer-token" class="flex-1 overflow-x-auto rounded-lg bg-neutral-100 border border-neutral-200 px-4 py-3 text-sm font-mono text-neutral-900">{{ $rawToken }}</code>
            <button type="button" class="btn btn-primary btn-sm self-start sm:self-auto" id="copy-developer-token">
                <i class="ph ph-copy" aria-hidden="true"></i>
                Salin token
            </button>
        </div>

        <p class="text-sm text-neutral-600 mt-4">Jangan masukkan token ini ke dalam JavaScript, aplikasi mudah alih atau repositori awam.</p>
    </section>

    <a href="{{ route('chatbots.embed', $chatbot) }}" class="btn btn-secondary">
        <i class="ph ph-arrow-left" aria-hidden="true"></i>
        Kembali ke pemasangan
    </a>
</div>

<script nonce="{{ Vite::cspNonce() }}">
document.addEventListener('DOMContentLoaded', () => {
    const copyButton = document.getElementById('copy-developer-token');
    const token = document.getElementById('developer-token');
    window.showToast?.('Token API pembangun berjaya dijana. Simpan token ini sekarang.', 'success');

    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(token?.textContent.trim() || '');
            window.showToast?.('Token berjaya disalin.', 'success');
        } catch (error) {
            window.showToast?.('Token tidak dapat disalin. Sila salin secara manual.', 'error');
        }
    });
});
</script>
@endsection
