@extends('layouts.app')
@section('page-title', 'Pelan Langganan')
@section('title', 'Pelan Harga')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-white">Pelan Harga</h1>
    <p class="text-white/25 text-sm">Pilih pelan yang sesuai dengan keperluan anda.</p>
</div>
<div class="grid md:grid-cols-3 gap-6 max-w-5xl">
    @php $plans = \App\Models\Plan::where('is_active', true)->get(); 
       $currentPlanId = auth()->check() ? auth()->user()->activeSubscription()?->plan_id : null;
    @endphp
    @foreach($plans as $plan)
    <div class="bg-white/[0.03] rounded-lg p-8 border {{ $plan->slug === 'pro' ? 'border-brand-500 ring-2 ring-brand-500 shadow-xl' : 'border-white/[0.06]' }} relative">
        @if($plan->slug === 'pro')<div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white text-[#050505] text-xs font-bold px-3 py-1 rounded-full">PALING POPULAR</div>@endif
        <h3 class="text-lg font-bold text-white mt-2">{{ $plan->name }}</h3>
        <div class="mt-5 mb-6"><span class="text-white/40xl font-extrabold text-white">{{ $plan->price == 0 ? 'Percuma' : 'RM'.$plan->price }}</span><span class="text-white/25 text-sm">/bulan</span></div>
        <ul class="space-y-3 mb-8 text-sm text-white/40">
            <li class="flex items-center gap-2"><span class="text-emerald-500">&#10003;</span> {{ $plan->chatbot_limit >= 999 ? 'Tiada had chatbot' : $plan->chatbot_limit . ' chatbot' }}</li>
            <li class="flex items-center gap-2"><span class="text-emerald-500">&#10003;</span> {{ $plan->knowledge_limit >= 99999 ? 'Tiada had pengetahuan' : number_format($plan->knowledge_limit) . ' item' }}</li>
            <li class="flex items-center gap-2"><span class="text-emerald-500">&#10003;</span> {{ $plan->monthly_messages >= 999999 ? 'Tiada had mesej' : number_format($plan->monthly_messages) . ' mesej/bln' }}</li>
            <li class="flex items-center gap-2">{!! $plan->api_access ? '<span class="text-emerald-500">&#10003;</span> Akses API' : '<span class="text-white/15">&#10007;</span> <span class="text-white/25">Akses API</span>' !!}</li>
            <li class="flex items-center gap-2">{!! $plan->remove_branding ? '<span class="text-emerald-500">&#10003;</span> Buang penjenamaan' : '<span class="text-white/15">&#10007;</span> <span class="text-white/25">Buang penjenamaan</span>' !!}</li>
        </ul>
        @if(auth()->check())
        <form action="{{ route('subscription.subscribe', $plan) }}" method="POST">
            @csrf
            <button class="w-full {{ $plan->slug === 'pro' ? 'bg-white hover:bg-white/90 text-white' : 'bg-white/[0.03] hover:bg-white/[0.04] text-white' }} py-3 rounded-lg font-semibold text-sm transition">
                {{ $currentPlanId === $plan->id ? 'Pelan Semasa' : ($plan->price == 0 ? 'Tukar ke Percuma' : 'Langgan') }}
            </button>
        </form>
        @else
        <a href="{{ route('register') }}" class="block w-full text-center {{ $plan->slug === 'pro' ? 'bg-white hover:bg-white/90 text-white' : 'bg-white/[0.03] hover:bg-white/[0.04] text-white' }} py-3 rounded-lg font-semibold text-sm transition">
            {{ $plan->price == 0 ? 'Daftar Percuma' : 'Mulakan Percubaan' }}
        </a>
        @endif
    </div>
    @endforeach
</div>
@endsection
