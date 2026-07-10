@extends('layouts.app')

@section('title', 'Status pembayaran')
@section('page-title', 'Status pembayaran')

@section('content')
@php
    $isPaid = $paymentOrder->status === \App\Models\PaymentOrder::STATUS_PAID;
    $isFailed = $paymentOrder->status === \App\Models\PaymentOrder::STATUS_FAILED;
    $statusClass = $isPaid ? 'is-success' : ($isFailed ? 'is-error' : 'is-pending');
    $statusIcon = $isPaid ? 'ph-check-circle' : ($isFailed ? 'ph-x-circle' : 'ph-clock');
    $statusTitle = $isPaid ? 'Pembayaran berjaya' : ($isFailed ? 'Pembayaran tidak berjaya' : 'Menunggu pembayaran');
    $ringgit = intdiv($paymentOrder->amount_cents, 100).'.'.str_pad((string) ($paymentOrder->amount_cents % 100), 2, '0', STR_PAD_LEFT);
@endphp

<section class="payment-result-page" aria-labelledby="payment-status-heading">
    <article class="card payment-result-card">
        <div class="status-icon {{ $statusClass }}" aria-hidden="true">
            <i class="ph {{ $statusIcon }}"></i>
        </div>

        <header class="payment-result-header" role="status">
            <h1 id="payment-status-heading">{{ $statusTitle }}</h1>
            @if($isPaid)
                <p>Langganan {{ $paymentOrder->plan->name }} anda telah diaktifkan selama satu bulan.</p>
            @elseif($isFailed)
                <p>Kami akan mengemas kini status sebaik sahaja pengesahan diterima daripada ToyyibPay.</p>
            @else
                <p>Kami akan mengemas kini status sebaik sahaja pengesahan diterima daripada ToyyibPay.</p>
            @endif
        </header>

        @if($errors->has('payment'))
            <div class="alert alert-error" role="alert">{{ $errors->first('payment') }}</div>
        @endif

        <dl class="payment-details">
            <div>
                <dt>Pelan</dt>
                <dd>{{ $paymentOrder->plan->name }}</dd>
            </div>
            <div>
                <dt>Jumlah</dt>
                <dd>RM{{ $ringgit }}</dd>
            </div>
            <div class="payment-reference">
                <dt>Rujukan pesanan</dt>
                <dd><code>{{ $paymentOrder->external_reference }}</code></dd>
            </div>
            @if($isPaid && $paymentOrder->subscription?->ends_at)
                <div class="payment-access-period">
                    <dt>Akses sehingga</dt>
                    <dd>
                        <time datetime="{{ $paymentOrder->subscription->ends_at->toIso8601String() }}">
                            {{ $paymentOrder->subscription->ends_at->locale('ms')->translatedFormat('j F Y, H:i') }}
                        </time>
                    </dd>
                </div>
            @endif
        </dl>

        <div class="action-row">
            @if(! $isPaid)
                <form method="POST" action="{{ route('subscription.reconcile', $paymentOrder) }}">
                    @csrf
                    <button class="button button-primary" type="submit">
                        <i class="ph ph-arrows-clockwise" aria-hidden="true"></i>
                        Semak semula
                    </button>
                </form>

                @if($isFailed)
                    <a class="button button-secondary" href="{{ route('subscription.plans') }}">Cuba bayar semula</a>
                @endif
            @endif

            <a class="button button-secondary" href="{{ route('dashboard') }}">Ke papan pemuka</a>
        </div>
    </article>
</section>
@endsection
