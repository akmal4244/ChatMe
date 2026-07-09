@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('page-title', 'Pelan Langganan')
@section('title', 'Pelan Harga')

@section('content')
@php
    $activeSubscription = auth()->check() ? auth()->user()->activeSubscription() : null;
    $currentPlan = auth()->check() ? auth()->user()->currentPlan() : null;
@endphp

<section class="subscription-page" aria-labelledby="subscription-heading">
    <header class="subscription-header">
        <p class="eyebrow">Langganan ChatMe</p>
        <h1 id="subscription-heading">Pelan yang jelas, bayaran yang selamat</h1>
        <p>Pilih pelan bulanan anda dan bayar melalui FPX / DuitNow QR menggunakan ToyyibPay.</p>
        <p class="subscription-renewal-note">Pembaharuan bulanan; bukan potongan automatik daripada akaun bank.</p>
    </header>

    @if($errors->has('payment'))
        <div class="alert alert-error" role="alert">{{ $errors->first('payment') }}</div>
    @endif

    <div class="plan-grid">
        @forelse($plans as $plan)
            @php
                $isFree = $plan->slug === 'free';
                $isCurrent = $currentPlan?->id === $plan->id;
                $isCurrentPaid = $isCurrent && ! $isFree && $activeSubscription !== null;
                $phoneId = 'phone-'.$plan->id;
            @endphp

            <article class="plan-card {{ $plan->slug === 'pro' ? 'plan-card-featured' : '' }}" aria-labelledby="plan-{{ $plan->id }}-name">
                @if($plan->slug === 'pro')
                    <span class="plan-badge">Paling popular</span>
                @endif

                @if($isCurrent)
                    <span class="current-plan-badge">Pelan semasa</span>
                @endif

                <h2 id="plan-{{ $plan->id }}-name">{{ $plan->name }}</h2>
                <p class="plan-price">
                    @if($isFree)
                        <span>Percuma</span>
                    @else
                        <span>RM{{ number_format((float) $plan->price, 0) }}</span><small>/bulan</small>
                    @endif
                </p>

                <ul class="plan-features">
                    <li>
                        <span aria-hidden="true">&#10003;</span>
                        {{ $plan->chatbot_limit === -1 ? 'Tanpa had chatbot' : number_format($plan->chatbot_limit).' chatbot' }}
                    </li>
                    <li>
                        <span aria-hidden="true">&#10003;</span>
                        {{ $plan->knowledge_limit === -1 ? 'Tanpa had pengetahuan' : number_format($plan->knowledge_limit).' item pengetahuan' }}
                    </li>
                    <li>
                        <span aria-hidden="true">&#10003;</span>
                        {{ $plan->monthly_messages === -1 ? 'Tanpa had mesej' : number_format($plan->monthly_messages).' mesej sebulan' }}
                    </li>
                    <li class="{{ $plan->api_access ? '' : 'plan-feature-muted' }}">
                        <span aria-hidden="true">{{ $plan->api_access ? '✓' : '—' }}</span>
                        Akses API
                    </li>
                    <li class="{{ $plan->remove_branding ? '' : 'plan-feature-muted' }}">
                        <span aria-hidden="true">{{ $plan->remove_branding ? '✓' : '—' }}</span>
                        Buang penjenamaan ChatMe
                    </li>
                </ul>

                @auth
                    @if($isFree)
                        @if($activeSubscription && $activeSubscription->plan_id !== $plan->id)
                            <p class="plan-status-note">Free akan kembali selepas akses berbayar tamat.</p>
                        @else
                            <button type="button" class="button button-secondary button-full" disabled>
                                {{ $isCurrent ? 'Pelan semasa' : 'Termasuk secara automatik' }}
                            </button>
                        @endif
                    @else
                        <form action="{{ route('subscription.checkout', $plan) }}" method="POST" class="checkout-form">
                            @csrf
                            <label for="{{ $phoneId }}">Nombor telefon mudah alih</label>
                            <p id="{{ $phoneId }}-hint" class="field-hint">Digunakan untuk bil ToyyibPay dan pengesahan pembayaran.</p>
                            <input
                                id="{{ $phoneId }}"
                                name="phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                value="{{ old('phone') }}"
                                placeholder="0123456789"
                                aria-describedby="{{ $phoneId }}-hint{{ $errors->has('phone') ? ' '.$phoneId.'-error' : '' }}"
                                required
                            >
                            @if($errors->has('phone'))
                                <p id="{{ $phoneId }}-error" class="field-error" role="alert">{{ $errors->first('phone') }}</p>
                            @endif
                            <button type="submit" class="button button-primary button-full">
                                {{ $isCurrentPaid ? 'Perbaharui sebulan' : 'Langgan dengan FPX / DuitNow QR' }}
                            </button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="button {{ $plan->slug === 'pro' ? 'button-primary' : 'button-secondary' }} button-full">
                        {{ $isFree ? 'Daftar percuma' : 'Daftar untuk melanggan' }}
                    </a>
                @endauth
            </article>
        @empty
            <p class="empty-state">Pelan belum tersedia. Sila cuba semula sebentar lagi.</p>
        @endforelse
    </div>
</section>
@endsection
