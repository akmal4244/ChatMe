@extends('layouts.app')

@section('title', 'Status Pembayaran')
@section('page-title', 'Status Pembayaran')

@section('content')
@php
    $isPaid = $paymentOrder->status === \App\Models\PaymentOrder::STATUS_PAID;
    $isFailed = $paymentOrder->status === \App\Models\PaymentOrder::STATUS_FAILED;
    $ringgit = intdiv($paymentOrder->amount_cents, 100).'.'.str_pad((string) ($paymentOrder->amount_cents % 100), 2, '0', STR_PAD_LEFT);
@endphp

<div style="max-width:680px;margin:0 auto;">
    <div class="card" style="padding:32px;">
        <div style="width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;{{ $isPaid ? 'background:rgba(52,211,153,.12);color:#34d399;' : ($isFailed ? 'background:rgba(239,68,68,.12);color:#f87171;' : 'background:rgba(99,102,241,.12);color:#a5b4fc;') }}">
            <i class="ph {{ $isPaid ? 'ph-check-circle' : ($isFailed ? 'ph-x-circle' : 'ph-clock') }}" style="font-size:26px;"></i>
        </div>

        <h1 style="font-family:'Newsreader',serif;font-size:30px;font-weight:500;margin-bottom:8px;">
            {{ $isPaid ? 'Pembayaran berjaya' : ($isFailed ? 'Pembayaran belum berjaya' : 'Menunggu pembayaran') }}
        </h1>
        <p style="color:rgba(255,255,255,.45);margin-bottom:28px;">
            @if($isPaid)
                Langganan {{ $paymentOrder->plan->name }} anda telah diaktifkan.
            @elseif($isFailed)
                ToyyibPay belum mengesahkan pembayaran ini. Tiada caj berjaya akan mengaktifkan langganan tanpa pengesahan server.
            @else
                Kami masih menunggu pengesahan server ToyyibPay. Halaman ini tidak mempercayai parameter pulangan pelayar.
            @endif
        </p>

        @if($errors->has('payment'))
            <div class="flash flash-error" style="margin:0 0 20px;">{{ $errors->first('payment') }}</div>
        @endif

        <dl style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px;border:1px solid rgba(255,255,255,.06);border-radius:14px;margin-bottom:24px;">
            <div><dt class="label">Pelan</dt><dd>{{ $paymentOrder->plan->name }}</dd></div>
            <div><dt class="label">Jumlah</dt><dd>RM{{ $ringgit }}</dd></div>
            <div style="grid-column:1/-1;"><dt class="label">Rujukan pesanan</dt><dd style="font-family:'JetBrains Mono',monospace;font-size:12px;overflow-wrap:anywhere;">{{ $paymentOrder->external_reference }}</dd></div>
            @if($isPaid && $paymentOrder->subscription?->ends_at)
                <div style="grid-column:1/-1;"><dt class="label">Akses sehingga</dt><dd>{{ $paymentOrder->subscription->ends_at->format('d M Y, H:i') }}</dd></div>
            @endif
        </dl>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            @if(! $isPaid)
                <form method="POST" action="{{ route('subscription.reconcile', $paymentOrder) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="ph ph-arrows-clockwise"></i> Semak semula</button>
                </form>
                @if($isFailed)
                    <a class="btn btn-secondary" href="{{ route('subscription.plans') }}">Cuba pembayaran baharu</a>
                @endif
            @endif
            <a class="btn btn-secondary" href="{{ route('dashboard') }}">Ke papan pemuka</a>
        </div>
    </div>
</div>
@endsection
