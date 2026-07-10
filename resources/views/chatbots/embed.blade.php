@extends('layouts.app')
@section('page-title', 'Kod Benam')
@section('title', 'Benam — ' . $chatbot->name)
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold text-neutral-950 mb-2">Benam: {{ $chatbot->name }}</h1>
    <p class="text-neutral-600 mb-6">Salin kod ini dan tampal ke dalam HTML laman web anda, sebelum tag &lt;/body&gt;.</p>

    <section class="card p-5 sm:p-6 mb-6" aria-labelledby="embed-code-heading">
        <div class="flex items-center justify-between gap-4 mb-3">
            <h2 id="embed-code-heading" class="font-semibold text-neutral-950">Kod benam</h2>
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
        <form action="{{ route('chatbots.regenerate-key', $chatbot) }}" method="POST" class="inline" data-confirm="Jana semula kunci API? Kunci lama akan berhenti berfungsi.">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">Jana Semula Kunci</button>
        </form>
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
                const originalLabel = button.textContent;
                button.textContent = 'Disalin';
                window.setTimeout(() => { button.textContent = originalLabel; }, 1500);
            } catch (error) {
                feedback.textContent = 'Teks tidak dapat disalin. Sila salin secara manual.';
            }
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    const swatch = document.querySelector('[data-color-swatch]');
    if (swatch && /^#[0-9a-f]{6}$/i.test(swatch.dataset.colorSwatch)) {
        swatch.style.backgroundColor = swatch.dataset.colorSwatch;
    }
});
</script>
@endsection
