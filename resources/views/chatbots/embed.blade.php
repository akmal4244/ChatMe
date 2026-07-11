@extends('layouts.app')
@section('page-title', 'Kod pemasangan')
@section('title', 'Pasang di laman web — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-neutral-950 mb-2">Kod pemasangan: {{ $chatbot->name }}</h1>
    <p class="text-neutral-600 mb-6">Salin kod ini dan tampalkannya ke dalam kod laman web anda, sebelum &lt;/body&gt;.</p>

    <section class="card p-5 sm:p-6 mb-6" aria-labelledby="embed-code-heading">
        <div class="flex items-center justify-between gap-4 mb-3">
            <h2 id="embed-code-heading" class="font-semibold text-neutral-950">Kod pemasangan</h2>
            <button type="button" class="btn btn-secondary btn-sm" data-copy-target="#embed-code">Salin</button>
        </div>
        <pre class="overflow-x-auto rounded-lg bg-neutral-100 border border-neutral-200 p-4 text-sm font-mono leading-relaxed text-neutral-900"><code id="embed-code">&lt;script src="{{ url('/widget/' . $chatbot->api_key . '.js') }}"&gt;&lt;/script&gt;</code></pre>
    </section>

    <p id="copy-feedback" class="sr-only" role="status" aria-live="polite"></p>

    <section class="card p-5 sm:p-6 mb-6" aria-labelledby="api-key-heading">
        <h2 id="api-key-heading" class="font-semibold text-neutral-950 mb-4">Kunci API</h2>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-3">
            <code id="api-key" class="flex-1 overflow-x-auto rounded-lg bg-neutral-100 border border-neutral-200 px-4 py-3 text-sm font-mono text-neutral-900">{{ $chatbot->api_key }}</code>
            <button type="button" class="btn btn-secondary btn-sm self-start sm:self-auto" data-copy-target="#api-key">Salin</button>
        </div>
        <form action="{{ route('chatbots.regenerate-key', $chatbot) }}" method="POST" class="inline"
              data-confirm-title="Jana semula kunci API?"
              data-confirm-description="Kunci lama akan berhenti berfungsi serta-merta dan kod pemasangan di laman web perlu dikemas kini."
              data-confirm-text="Jana semula kunci"
              data-confirm-type="danger">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">Jana semula kunci</button>
        </form>
    </section>

    <section class="card p-5 sm:p-6 mb-6" aria-labelledby="developer-api-heading">
        <h2 id="developer-api-heading" class="font-semibold text-neutral-950 mb-2">API pembangun</h2>
        @if($apiAccess)
            <p class="text-sm text-neutral-600 mb-4">Gunakan token rahsia ini pada endpoint <code>POST /api/v1/chat</code>. Jangan masukkan token ke dalam JavaScript atau laman awam.</p>

            @if(session('developer_token'))
                <div class="alert alert-warning mb-4" role="alert">
                    <strong>Token ini hanya dipaparkan sekali.</strong> Salin dan simpan sekarang sebelum meninggalkan halaman ini.
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                    <code id="developer-token" class="flex-1 overflow-x-auto rounded-lg bg-neutral-100 border border-neutral-200 px-4 py-3 text-sm font-mono text-neutral-900">{{ session('developer_token') }}</code>
                    <button type="button" class="btn btn-secondary btn-sm self-start sm:self-auto" data-copy-target="#developer-token">Salin token</button>
                </div>
            @elseif($chatbot->developer_api_token_prefix)
                <p class="text-sm text-neutral-600 mb-4">Token aktif: <code>{{ $chatbot->developer_api_token_prefix }}••••••••</code></p>
            @else
                <p class="text-sm text-neutral-600 mb-4">Belum ada token API pembangun untuk chatbot ini.</p>
            @endif

            <form action="{{ route('chatbots.developer-token', $chatbot) }}" method="POST" class="inline"
                  @if($chatbot->developer_api_token_hash)
                  data-confirm-title="Jana semula token API pembangun?"
                  data-confirm-description="Token lama akan berhenti berfungsi serta-merta."
                  data-confirm-text="Jana semula token"
                  data-confirm-type="danger"
                  @endif>
                @csrf
                <button type="submit" class="btn {{ $chatbot->developer_api_token_hash ? 'btn-danger' : 'btn-primary' }} btn-sm">
                    {{ $chatbot->developer_api_token_hash ? 'Jana semula token API pembangun' : 'Jana token API pembangun' }}
                </button>
            </form>
        @else
            <p class="text-sm text-neutral-600 mb-4">Pelan anda tidak mempunyai akses API pembangun.</p>
            <a href="{{ route('subscription.plans') }}" class="btn btn-secondary btn-sm">Lihat pelan dengan akses API</a>
        @endif
    </section>

    <section class="card p-5 sm:p-6" aria-labelledby="preview-heading">
        <h2 id="preview-heading" class="font-semibold text-neutral-950 mb-3">Pratonton</h2>
        <p class="text-sm text-neutral-600 mb-4">Beginilah rupa chatbot anda di laman web:</p>
        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 flex items-center gap-3">
            <img src="{{ $chatbot->resolvedAvatarUrl() }}" alt="Avatar {{ $chatbot->bot_name }}" class="w-12 h-12 rounded-full object-cover ring-1 ring-neutral-200">
            <div class="min-w-0">
                <p class="font-semibold text-neutral-950">{{ $chatbot->bot_name }}</p>
                <p class="text-sm text-neutral-600">{{ $chatbot->welcome_message }}</p>
            </div>
            <span class="ml-auto w-3 h-3 shrink-0 rounded-full shadow-sm" role="img" aria-label="Warna utama: {{ $chatbot->primary_color }}" data-color-swatch="{{ $chatbot->primary_color }}"></span>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const feedback = document.getElementById('copy-feedback');

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.querySelector(button.dataset.copyTarget);
            if (!target) return;

            try {
                await navigator.clipboard.writeText(target.textContent.trim());
                feedback.textContent = 'Teks berjaya disalin.';
                window.showToast('Teks berjaya disalin.', 'success');
                const originalLabel = button.textContent;
                button.textContent = 'Disalin';
                window.setTimeout(() => { button.textContent = originalLabel; }, 1500);
            } catch (error) {
                feedback.textContent = 'Teks tidak dapat disalin. Sila salin secara manual.';
                window.showToast('Teks tidak dapat disalin. Sila salin secara manual.', 'error');
            }
        });
    });

    const swatch = document.querySelector('[data-color-swatch]');
    if (swatch && /^#[0-9a-f]{6}$/i.test(swatch.dataset.colorSwatch)) {
        swatch.style.backgroundColor = swatch.dataset.colorSwatch;
    }
});
</script>
@endsection
