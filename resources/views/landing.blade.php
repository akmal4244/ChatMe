@extends('layouts.guest')
@section('title', 'ChatMe — Cipta Chatbot untuk Laman Web Anda')

@section('content')
@php
    $pricingPlans = $plans ?? collect();
    $currentPlanId = auth()->check() ? auth()->user()->currentPlan()?->id : null;
    $paymentChannels = config('services.toyyibpay.dnqr_enabled') ? 'FPX / DuitNow QR' : 'FPX';
@endphp

<header class="site-header landing-header" data-site-header>
    <div class="landing-shell site-header__inner">
        <a href="{{ route('landing') }}" class="site-brand" aria-label="ChatMe — halaman utama">
            <img src="{{ asset('akmal3d.png') }}" alt="" class="site-brand__logo" width="32" height="32" aria-hidden="true">
            <span class="site-brand__name">ChatMe</span>
        </a>

        <button
            type="button"
            class="site-nav__toggle"
            aria-expanded="false"
            aria-controls="primary-navigation"
            data-nav-toggle
        >
            <span class="sr-only" data-nav-label>Buka menu</span>
            <span class="site-nav__toggle-lines" aria-hidden="true">
                <span></span>
                <span></span>
            </span>
        </button>

        <nav id="primary-navigation" class="site-nav" aria-label="Navigasi utama" data-nav-menu>
            <div class="site-nav__links">
                <a href="#ciri" class="site-nav__link">Ciri</a>
                <a href="#harga" class="site-nav__link">Harga</a>
            </div>

            <div class="site-nav__actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="button button--primary button--compact">Buka papan pemuka</a>
                @else
                    <a href="{{ route('login') }}" class="button button--text button--compact">Log masuk</a>
                    <a href="{{ route('register') }}" class="button button--primary button--compact">Daftar percuma</a>
                @endauth
            </div>
        </nav>
    </div>
</header>

<div class="landing-page">
    <section class="landing-hero" aria-labelledby="landing-title">
        <div class="landing-shell landing-hero__grid">
            <div class="landing-hero__copy">
                <h1 id="landing-title" class="landing-hero__title">Cipta chatbot untuk laman web anda.</h1>
                <p class="landing-hero__description">
                    Masukkan maklumat anda sendiri, sesuaikan rupa chatbot mengikut jenama anda,
                    kemudian pasangkannya pada laman web dengan satu baris kod.
                </p>

                <div class="landing-hero__actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="button button--primary button--large">Buka papan pemuka</a>
                    @else
                        <a href="{{ route('register') }}" class="button button--primary button--large">Mulakan percuma</a>
                    @endauth
                    <a href="#ciri" class="button button--secondary button--large">Lihat cara ia berfungsi</a>
                </div>

                <p class="landing-hero__assurance">Pelan Free tidak memerlukan kad atau bayaran.</p>
            </div>

            <figure class="embed-preview" aria-labelledby="embed-preview-title">
                <div class="embed-preview__topbar">
                    <span class="embed-preview__traffic-lights" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span id="embed-preview-title" class="embed-preview__label">Kod pemasangan ChatMe</span>
                </div>
                <pre class="embed-preview__code" tabindex="0"><code>&lt;script src="https://chatme.akmalmarvis.com/widget/KUNCI-API.js"&gt;&lt;/script&gt;</code></pre>
                <figcaption class="embed-preview__caption">Salin kod unik chatbot, kemudian tampalkannya pada laman web anda.</figcaption>
            </figure>
        </div>
    </section>

    <section id="ciri" class="landing-section features-section" aria-labelledby="features-title">
        <div class="landing-shell">
            <header class="section-heading">
                <h2 id="features-title" class="section-heading__title">Semua yang anda perlukan untuk chatbot laman web anda</h2>
                <p class="section-heading__description">
                    Tambah maklumat, sesuaikan rupa dan pasang chatbot tanpa perlu mengubah struktur laman web anda.
                </p>
            </header>

            <div class="feature-grid">
                <article class="feature-card feature-card--wide">
                    <span class="feature-card__icon" aria-hidden="true"><i class="ph ph-books"></i></span>
                    <h3 class="feature-card__title">Maklumat yang anda tentukan</h3>
                    <p class="feature-card__description">
                        Tambah soalan dan jawapan satu demi satu, susun dengan kategori dan tag, atau tampal senarai dalam format JSON.
                    </p>
                </article>

                <article class="feature-card">
                    <span class="feature-card__icon" aria-hidden="true"><i class="ph ph-paint-brush"></i></span>
                    <h3 class="feature-card__title">Rupa chatbot mengikut jenama</h3>
                    <p class="feature-card__description">
                        Tetapkan nama chatbot, warna, gambar profil, mesej alu-aluan dan kedudukannya pada skrin.
                    </p>
                </article>

                <article class="feature-card">
                    <span class="feature-card__icon" aria-hidden="true"><i class="ph ph-code"></i></span>
                    <h3 class="feature-card__title">Pasang dengan satu kod ringkas</h3>
                    <p class="feature-card__description">
                        Salin kod pemasangan unik dan tampalkannya pada laman web anda.
                    </p>
                </article>

                <article class="feature-card feature-card--wide">
                    <span class="feature-card__icon" aria-hidden="true"><i class="ph ph-shield-check"></i></span>
                    <h3 class="feature-card__title">Kawal tempat chatbot digunakan</h3>
                    <p class="feature-card__description">
                        Tentukan laman web yang dibenarkan, hidupkan atau matikan chatbot, dan tukar kunci API apabila perlu.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section id="harga" class="landing-section pricing-section" aria-labelledby="pricing-title">
        <div class="landing-shell">
            <header class="section-heading section-heading--centered">
                <h2 id="pricing-title" class="section-heading__title">Pilih pelan mengikut penggunaan anda</h2>
                <p class="section-heading__description">
                    Mulakan dengan pelan Free. Pelan berbayar perlu diperbaharui setiap bulan melalui ToyyibPay.
                </p>
            </header>

            <p class="pricing-payment-note">
                Bayar pelan Pro dan Enterprise melalui {{ $paymentChannels }}. Pelan perlu diperbaharui secara manual
                setiap bulan; tiada potongan automatik daripada akaun bank.
            </p>

            <div class="pricing-grid">
                @forelse($pricingPlans as $plan)
                    @php
                        $isPaid = (float) $plan->price > 0;
                        $isCurrent = $currentPlanId === $plan->id;
                        $isFeatured = $plan->slug === 'pro';
                    @endphp

                    <article
                        class="pricing-card{{ $isFeatured ? ' pricing-card--featured' : '' }}{{ $isCurrent ? ' pricing-card--current' : '' }}"
                        aria-labelledby="plan-{{ $plan->id }}-title"
                    >
                        <div class="pricing-card__header">
                            <h3 id="plan-{{ $plan->id }}-title" class="pricing-card__name">{{ $plan->name }}</h3>
                            @if($isCurrent)
                                <span class="pricing-card__current" aria-label="Ini pelan semasa anda">Pelan semasa</span>
                            @endif
                        </div>

                        @if(filled($plan->description))
                            <p class="pricing-card__description">{{ $plan->description }}</p>
                        @endif

                        <p class="pricing-card__price">
                            <span class="pricing-card__amount">RM{{ number_format((float) $plan->price, 0) }}</span>
                            <span class="pricing-card__period">/bulan</span>
                        </p>

                        <ul class="pricing-card__features">
                            <li>
                                <i class="ph ph-check" aria-hidden="true"></i>
                                {{ $plan->chatbot_limit === -1 ? 'Chatbot tanpa had' : 'Sehingga '.number_format($plan->chatbot_limit).' chatbot' }}
                            </li>
                            <li>
                                <i class="ph ph-check" aria-hidden="true"></i>
                                {{ $plan->knowledge_limit === -1 ? 'Soal jawab tanpa had' : 'Sehingga '.number_format($plan->knowledge_limit).' soal jawab' }}
                            </li>
                            <li>
                                <i class="ph ph-check" aria-hidden="true"></i>
                                {{ $plan->monthly_messages === -1 ? 'Mesej tanpa had' : 'Sehingga '.number_format($plan->monthly_messages).' mesej sebulan' }}
                            </li>
                        </ul>

                        <p class="pricing-card__payment-copy">
                            {{ $isPaid ? 'Perbaharui secara manual setiap bulan melalui '.$paymentChannels.'.' : 'Tiada bayaran diperlukan.' }}
                        </p>

                        @auth
                            <a
                                href="{{ route('subscription.plans') }}"
                                class="button {{ $isFeatured ? 'button--primary' : 'button--secondary' }} button--block"
                                @if($isCurrent) aria-current="true" @endif
                            >
                                @if($isCurrent && $isPaid)
                                    Perbaharui pelan
                                @elseif($isCurrent)
                                    Pelan semasa
                                @else
                                    Lihat pelan
                                @endif
                            </a>
                        @else
                            <a
                                href="{{ route('register') }}"
                                class="button {{ $isFeatured ? 'button--primary' : 'button--secondary' }} button--block"
                            >
                                {{ $isPaid ? 'Daftar dan pilih pelan' : 'Mulakan percuma' }}
                            </a>
                        @endauth
                    </article>
                @empty
                    <p class="pricing-empty" role="status">Tiada pelan tersedia buat masa ini. Sila cuba lagi kemudian.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="landing-section landing-cta" aria-labelledby="cta-title">
        <div class="landing-shell">
            <div class="landing-cta__panel">
                <div class="landing-cta__copy">
                    <h2 id="cta-title" class="landing-cta__title">Sudah bersedia untuk membina chatbot anda?</h2>
                    <p class="landing-cta__description">
                        Daftar dengan pelan Free, masukkan maklumat untuk chatbot anda dan pasangkannya apabila anda bersedia.
                    </p>
                </div>
                @auth
                    <a href="{{ route('dashboard') }}" class="button button--primary button--large">Buka papan pemuka</a>
                @else
                    <a href="{{ route('register') }}" class="button button--primary button--large">Daftar percuma</a>
                @endauth
            </div>
        </div>
    </section>
</div>

<footer class="site-footer landing-footer">
    <div class="landing-shell site-footer__inner">
        <p class="site-footer__copyright">&copy; {{ date('Y') }} ChatMe — Kuala Lumpur, Malaysia</p>
        <nav class="site-footer__nav" aria-label="Pautan undang-undang dan sokongan">
            <a href="{{ route('privacy') }}">Privasi</a>
            <a href="{{ route('terms') }}">Terma</a>
            <a href="mailto:hello@akmalmarvis.com">Hubungi</a>
        </nav>
    </div>
</footer>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('[data-nav-toggle]');
    const menu = document.querySelector('[data-nav-menu]');
    const label = document.querySelector('[data-nav-label]');

    if (!toggle || !menu) {
        return;
    }

    const setMenuState = function (isOpen) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menu.classList.toggle('is-open', isOpen);

        if (label) {
            label.textContent = isOpen ? 'Tutup menu' : 'Buka menu';
        }
    };

    toggle.addEventListener('click', function () {
        setMenuState(toggle.getAttribute('aria-expanded') !== 'true');
    });

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setMenuState(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setMenuState(false);
            toggle.focus();
        }
    });
});
</script>
@if($homepageChatbot)
<script defer src="{{ route('widget.script', ['chatbot' => $homepageChatbot->api_key]) }}"></script>
@endif
@endpush
