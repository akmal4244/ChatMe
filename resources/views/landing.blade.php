@extends('layouts.guest')
@section('title', 'ChatMe — Cipta Chatbot AI Custom')

@section('content')

{{-- ═══════════════════════════════════════════════════════════
   CHATME — ETHEREAL GLASS (high-end-visual-design)
   Vibe: Ethereal Glass / Vantablack + Radial Mesh
   Layout: Asymmetrical Bento
   ═══════════════════════════════════════════════════════════ --}}

{{-- Film grain overlay --}}
<div style="position:fixed;inset:0;z-index:50;pointer-events:none;opacity:0.035;background-image:url('data:image/svg+xml,<svg viewBox=\'0 0 256 256\' xmlns=\'http://www.w3.org/2000/svg\'><filter id=\'n\'><feTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\' stitchTiles=\'stitch\'/></filter><rect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/></svg>');background-size:256px 256px;"></div>

{{-- Radial mesh orbs --}}
<div style="position:fixed;inset:0;z-index:0;pointer-events:none;">
    <div style="position:absolute;top:-30%;left:-10%;width:700px;height:700px;background:radial-gradient(circle,rgba(99,102,241,0.12) 0%,transparent 60%);"></div>
    <div style="position:absolute;bottom:-20%;right:-5%;width:600px;height:600px;background:radial-gradient(circle,rgba(16,185,129,0.08) 0%,transparent 60%);"></div>
    <div style="position:absolute;top:40%;left:50%;width:500px;height:500px;background:radial-gradient(circle,rgba(139,92,246,0.06) 0%,transparent 60%);transform:translate(-50%,-50%);"></div>
</div>

{{-- ══ FLUID ISLAND NAV ═════════════════════════════════════ --}}
<nav style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:100;width:auto;max-width:calc(100vw - 32px);">
    <div style="background:rgba(255,255,255,0.04);backdrop-filter:blur(40px);-webkit-backdrop-filter:blur(40px);border:1px solid rgba(255,255,255,0.08);border-radius:999px;padding:8px 8px 8px 20px;display:flex;align-items:center;gap:8px;box-shadow:0 1px 0 rgba(255,255,255,0.04) inset,0 8px 32px rgba(0,0,0,0.32);">
        <a href="/" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
            <img src="{{ asset('akmal3d.png') }}" alt="ChatMe" style="width:24px;height:24px;border-radius:4px;">
            <span style="font-family:'Newsreader',serif;font-size:15px;font-weight:600;color:#fff;letter-spacing:-0.02em;">ChatMe</span>
        </a>
        <div style="width:1px;height:20px;background:rgba(255,255,255,0.08);margin:0 8px;"></div>
        <a href="#ciri" style="color:rgba(255,255,255,0.5);font-size:13px;font-weight:500;text-decoration:none;padding:6px 12px;border-radius:999px;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);">Ciri</a>
        <a href="#harga" style="color:rgba(255,255,255,0.5);font-size:13px;font-weight:500;text-decoration:none;padding:6px 12px;border-radius:999px;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);">Harga</a>
        <a href="{{ route('login') }}" style="color:rgba(255,255,255,0.5);font-size:13px;font-weight:500;text-decoration:none;padding:6px 12px;border-radius:999px;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);">Log Masuk</a>
        <a href="{{ route('register') }}" class="group" style="background:#fff;color:#050505;font-size:13px;font-weight:600;text-decoration:none;padding:8px 18px;border-radius:999px;display:inline-flex;align-items:center;gap:6px;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);">
            Daftar
            <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                <i class="ph ph-arrow-right" style="font-size:11px;color:#050505;"></i>
            </span>
        </a>
    </div>
</nav>

{{-- ══ HERO ══════════════════════════════════════════════════ --}}
<section style="position:relative;z-index:1;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:160px 24px 100px;text-align:center;">
    <div style="max-width:780px;margin:0 auto;">

        {{-- Eyebrow badge --}}
        <div class="reveal" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:999px;padding:5px 16px;font-size:10px;font-weight:600;color:rgba(255,255,255,0.45);letter-spacing:0.15em;text-transform:uppercase;margin-bottom:36px;">
            <span style="width:6px;height:6px;border-radius:999px;background:#22C55E;box-shadow:0 0 8px rgba(34,197,94,0.5);"></span>
            Platform SaaS Buatan Malaysia
        </div>

        <h1 class="reveal" style="font-family:'Newsreader',serif;font-size:clamp(44px,8vw,72px);font-weight:400;color:#fff;line-height:1.06;letter-spacing:-0.04em;margin-bottom:28px;text-wrap:balance;">
            Cipta chatbot AI<br>untuk laman web anda.
        </h1>

        <p class="reveal" style="font-size:17px;color:rgba(255,255,255,0.4);line-height:1.75;max-width:520px;margin:0 auto 44px;text-wrap:balance;">
            Latih dengan pengetahuan sendiri, sesuaikan ikut jenama, dan benamkan di mana-mana laman web dengan satu baris kod.
        </p>

        <div class="reveal" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ route('register') }}" class="group" style="background:#fff;color:#050505;padding:14px 28px;border-radius:999px;font-size:15px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);">
                Mulakan Percuma
                <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                    <i class="ph ph-arrow-right" style="font-size:12px;"></i>
                </span>
            </a>
            <a href="#ciri" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.7);padding:14px 28px;border-radius:999px;font-size:15px;font-weight:500;text-decoration:none;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);">
                Lihat Ciri
            </a>
        </div>
    </div>
</section>

{{-- ══ CODE PREVIEW — Double-Bezel ═══════════════════════════ --}}
<section style="position:relative;z-index:1;padding:0 24px 100px;">
    <div class="reveal" style="max-width:680px;margin:0 auto;">
        {{-- Outer shell --}}
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:12px;">
            {{-- Inner core --}}
            <div style="background:#0A0A0A;border:1px solid rgba(255,255,255,0.05);border-radius:20px;overflow:hidden;box-shadow:inset 0 1px 0 rgba(255,255,255,0.04);">
                <div style="display:flex;gap:7px;padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.04);">
                    <div style="width:11px;height:11px;border-radius:999px;background:#FF5F57;"></div>
                    <div style="width:11px;height:11px;border-radius:999px;background:#FFBD2E;"></div>
                    <div style="width:11px;height:11px;border-radius:999px;background:#28CA41;"></div>
                </div>
                <pre style="padding:28px 24px;margin:0;overflow-x:auto;"><code style="font-family:'JetBrains Mono',monospace;font-size:13px;color:rgba(255,255,255,0.3);line-height:1.9;">
&lt;script src="<span style="color:rgba(99,102,241,0.6);">https://chatme.akmalmarvis.com/widget/</span><span style="color:rgba(255,255,255,0.5);">cm_••••••••</span><span style="color:rgba(99,102,241,0.6);">.js</span>"&gt;&lt;/script&gt;</code></pre>
            </div>
        </div>
        <p style="text-align:center;font-size:13px;color:rgba(255,255,255,0.25);margin-top:20px;">Satu baris. Siap dipasang.</p>
    </div>
</section>

{{-- ══ FEATURES — Asymmetrical Bento ═════════════════════════ --}}
<section id="ciri" style="position:relative;z-index:1;padding:0 24px 120px;">
    <div style="max-width:1100px;margin:0 auto;">

        {{-- Section header --}}
        <div style="text-align:center;margin-bottom:80px;">
            <div class="reveal" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:999px;padding:4px 14px;font-size:10px;font-weight:600;color:rgba(255,255,255,0.4);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:24px;">
                Ciri Utama
            </div>
            <h2 class="reveal" style="font-family:'Newsreader',serif;font-size:clamp(34px,5vw,48px);font-weight:400;color:#fff;letter-spacing:-0.035em;line-height:1.12;margin-bottom:18px;">
                Lengkap untuk bisnes anda
            </h2>
            <p class="reveal" style="font-size:16px;color:rgba(255,255,255,0.35);max-width:480px;margin:0 auto;">
                Semua yang diperlukan untuk melancarkan chatbot AI custom di laman web.
            </p>
        </div>

        {{-- Bento Grid — Asymmetrical --}}
        <div class="reveal-stagger" style="display:grid;grid-template-columns:repeat(12,1fr);gap:16px;">

            {{-- Feature 1 — large card (8 col) --}}
            <div style="grid-column:span 8;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:36px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-brain" style="font-size:20px;color:rgba(165,180,252,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Pengetahuan Sendiri</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;max-width:440px;">
                        Muat naik dokumen, FAQ, atau tulis sendiri pengetahuan untuk chatbot anda. Skop jawapan terkawal sepenuhnya — chatbot hanya jawab berdasarkan data anda.
                    </p>
                </div>
            </div>

            {{-- Feature 2 — small card (4 col) --}}
            <div style="grid-column:span 4;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-paint-brush" style="font-size:20px;color:rgba(52,211,153,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Sesuaikan Jenama</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;">
                        Nama, warna, avatar — semuanya ikut identiti jenama anda.
                    </p>
                </div>
            </div>

            {{-- Feature 3 — small card (4 col) --}}
            <div style="grid-column:span 4;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(139,92,246,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-code" style="font-size:20px;color:rgba(196,167,255,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Satu Baris Kod</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;">
                        Benamkan di mana-mana laman web dengan satu tag &lt;script&gt;.
                    </p>
                </div>
            </div>

            {{-- Feature 4 — large card (8 col) --}}
            <div style="grid-column:span 8;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:36px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-chart-bar" style="font-size:20px;color:rgba(252,211,77,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Analitik Ringkas</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;max-width:440px;">
                        Pantau jumlah perbualan, mesej, dan prestasi chatbot dari papan pemuka yang bersih dan intuitif.
                    </p>
                </div>
            </div>

            {{-- Feature 5 — small (4 col) --}}
            <div style="grid-column:span 4;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-globe" style="font-size:20px;color:rgba(147,197,253,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Bahasa Melayu</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;">
                        UI, chatbot, dan dokumentasi dalam BM.
                    </p>
                </div>
            </div>

            {{-- Feature 6 — small (4 col) --}}
            <div style="grid-column:span 4;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-shield-check" style="font-size:20px;color:rgba(252,165,165,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">API Terbuka</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;">
                        Integrasi REST API — dokumentasi lengkap.
                    </p>
                </div>
            </div>

            {{-- Feature 7 — small (4 col) --}}
            <div style="grid-column:span 4;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="width:40px;height:40px;border-radius:14px;background:rgba(236,72,153,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
                        <i class="ph ph-rocket-launch" style="font-size:20px;color:rgba(249,168,212,0.9);"></i>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;color:#fff;margin-bottom:10px;">Persediaan Pantas</h3>
                    <p style="font-size:14px;color:rgba(255,255,255,0.35);line-height:1.75;">
                        Dari daftar ke chatbot live dalam kurang 2 minit.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══ PRICING — Double-Bezel Cards ══════════════════════════ --}}
<section id="harga" style="position:relative;z-index:1;padding:0 24px 120px;">
    <div style="max-width:1060px;margin:0 auto;">

        <div style="text-align:center;margin-bottom:80px;">
            <div class="reveal" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:999px;padding:4px 14px;font-size:10px;font-weight:600;color:rgba(255,255,255,0.4);letter-spacing:0.12em;text-transform:uppercase;margin-bottom:24px;">
                Harga
            </div>
            <h2 class="reveal" style="font-family:'Newsreader',serif;font-size:clamp(34px,5vw,48px);font-weight:400;color:#fff;letter-spacing:-0.035em;line-height:1.12;margin-bottom:18px;">
                Mudah dan berpatutan
            </h2>
            <p class="reveal" style="font-size:16px;color:rgba(255,255,255,0.35);">Pelan sesuai untuk setiap saiz bisnes.</p>
        </div>

        <div class="reveal-stagger" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;align-items:start;">

            @php
                $pricingPlans = $plans ?? collect([]);
            @endphp

            @forelse($pricingPlans as $plan)
            <div style="background:rgba(255,255,255,0.03);border:1px solid {{ $loop->index === 1 ? 'rgba(255,255,255,0.2)' : 'rgba(255,255,255,0.06)' }};border-radius:28px;padding:{{ $loop->index === 1 ? '12px' : '10px' }};">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:{{ $loop->index === 1 ? '20px' : '22px' }};padding:36px 32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    @if($loop->index === 1)
                    <div style="display:inline-flex;align-items:center;gap:4px;background:#fff;color:#050505;font-size:10px;font-weight:600;padding:4px 12px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase;margin-bottom:20px;">
                        Popular
                    </div>
                    @endif
                    <h3 style="font-size:20px;font-weight:600;color:#fff;margin-bottom:4px;">{{ $plan->name }}</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.3);margin-bottom:28px;">{{ $plan->description ?? '' }}</p>

                    <div style="margin-bottom:28px;">
                        <span style="font-family:'Newsreader',serif;font-size:44px;font-weight:400;color:#fff;">RM{{ number_format($plan->price, 0) }}</span>
                        <span style="font-size:14px;color:rgba(255,255,255,0.35);">/bulan</span>
                    </div>

                    <ul style="list-style:none;margin-bottom:32px;display:flex;flex-direction:column;gap:14px;">
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);">
                            <i class="ph ph-check" style="color:rgba(52,211,153,0.8);font-size:15px;"></i>
                            {{ $plan->chatbots_limit ?? 1 }} chatbot
                        </li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);">
                            <i class="ph ph-check" style="color:rgba(52,211,153,0.8);font-size:15px;"></i>
                            {{ $plan->knowledge_limit ?? 50 }} item pengetahuan
                        </li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);">
                            <i class="ph ph-check" style="color:rgba(52,211,153,0.8);font-size:15px;"></i>
                            {{ $plan->messages_limit ? number_format($plan->messages_limit) . ' mesej' : 'Mesej tanpa had' }}/bulan
                        </li>
                    </ul>

                    @auth
                    <a href="{{ route('subscription.subscribe', $plan) }}" class="group" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);{{ $loop->index === 1 ? 'background:#fff;color:#050505;' : 'background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);' }}">
                        {{ auth()->user()->subscription?->plan_id === $plan->id ? 'Pelan Semasa' : 'Pilih Pelan' }}
                        @if($loop->index === 1)
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                            <i class="ph ph-arrow-right" style="font-size:10px;"></i>
                        </span>
                        @endif
                    </a>
                    @else
                    <a href="{{ route('register') }}" class="group" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);{{ $loop->index === 1 ? 'background:#fff;color:#050505;' : 'background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);' }}">
                        Mulakan
                        @if($loop->index === 1)
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                            <i class="ph ph-arrow-right" style="font-size:10px;"></i>
                        </span>
                        @endif
                    </a>
                    @endauth
                </div>
            </div>
            @empty
            {{-- Fallback plans --}}
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:22px;padding:36px 32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <h3 style="font-size:20px;font-weight:600;color:#fff;margin-bottom:4px;">Percuma</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.3);margin-bottom:28px;">Untuk cuba-cuba.</p>
                    <div style="margin-bottom:28px;"><span style="font-family:'Newsreader',serif;font-size:44px;font-weight:400;color:#fff;">RM0</span><span style="font-size:14px;color:rgba(255,255,255,0.35);">/bulan</span></div>
                    <ul style="list-style:none;margin-bottom:32px;display:flex;flex-direction:column;gap:14px;">
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>1 chatbot</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>50 pengetahuan</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>500 mesej/bulan</li>
                    </ul>
                    <a href="{{ route('register') }}" style="display:block;text-align:center;padding:13px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);">Mulakan Percuma</a>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.2);border-radius:28px;padding:12px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:20px;padding:36px 32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <div style="display:inline-flex;align-items:center;gap:4px;background:#fff;color:#050505;font-size:10px;font-weight:600;padding:4px 12px;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase;margin-bottom:20px;">Popular</div>
                    <h3 style="font-size:20px;font-weight:600;color:#fff;margin-bottom:4px;">Pro</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.3);margin-bottom:28px;">Untuk bisnes serius.</p>
                    <div style="margin-bottom:28px;"><span style="font-family:'Newsreader',serif;font-size:44px;font-weight:400;color:#fff;">RM49</span><span style="font-size:14px;color:rgba(255,255,255,0.35);">/bulan</span></div>
                    <ul style="list-style:none;margin-bottom:32px;display:flex;flex-direction:column;gap:14px;">
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>5 chatbot</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>500 pengetahuan</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>5,000 mesej/bulan</li>
                    </ul>
                    <a href="{{ route('register') }}" class="group" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;background:#fff;color:#050505;">
                        Langgan Pro
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:rgba(0,0,0,0.06);"><i class="ph ph-arrow-right" style="font-size:10px;"></i></span>
                    </a>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:28px;padding:10px;">
                <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:22px;padding:36px 32px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
                    <h3 style="font-size:20px;font-weight:600;color:#fff;margin-bottom:4px;">Enterprise</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,0.3);margin-bottom:28px;">Skala besar.</p>
                    <div style="margin-bottom:28px;"><span style="font-family:'Newsreader',serif;font-size:44px;font-weight:400;color:#fff;">RM199</span><span style="font-size:14px;color:rgba(255,255,255,0.35);">/bulan</span></div>
                    <ul style="list-style:none;margin-bottom:32px;display:flex;flex-direction:column;gap:14px;">
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>Tanpa had chatbot</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>Tanpa had pengetahuan</li>
                        <li style="display:flex;align-items:center;gap:10px;font-size:13px;color:rgba(255,255,255,0.5);"><i class="ph ph-check" style="color:rgba(52,211,153,0.8);"></i>Tanpa had mesej</li>
                    </ul>
                    <a href="{{ route('register') }}" style="display:block;text-align:center;padding:13px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);">Hubungi Kami</a>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</section>

{{-- ══ CTA — Dark Minimal ═══════════════════════════════════ --}}
<section style="position:relative;z-index:1;padding:0 24px 120px;">
    <div class="reveal" style="max-width:680px;margin:0 auto;text-align:center;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:36px;padding:12px;">
        <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:28px;padding:64px 40px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">
            <h2 style="font-family:'Newsreader',serif;font-size:clamp(30px,4vw,40px);font-weight:400;color:#fff;letter-spacing:-0.035em;line-height:1.15;margin-bottom:16px;">
                Sedia untuk bermula?
            </h2>
            <p style="font-size:16px;color:rgba(255,255,255,0.35);line-height:1.7;margin-bottom:36px;max-width:420px;margin-left:auto;margin-right:auto;">
                Cipta chatbot AI pertama anda dalam masa kurang dari 2 minit. Tiada kad kredit diperlukan.
            </p>
            <a href="{{ route('register') }}" class="group" style="display:inline-flex;align-items:center;gap:8px;background:#fff;color:#050505;padding:15px 32px;border-radius:999px;font-size:15px;font-weight:600;text-decoration:none;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);">
                Daftar Percuma
                <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                    <i class="ph ph-arrow-right" style="font-size:12px;"></i>
                </span>
            </a>
        </div>
    </div>
</section>

{{-- ══ FOOTER ════════════════════════════════════════════════ --}}
<footer style="position:relative;z-index:1;padding:48px 24px;border-top:1px solid rgba(255,255,255,0.04);">
    <div style="max-width:1100px;margin:0 auto;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:20px;">
        <div style="font-size:13px;color:rgba(255,255,255,0.2);">
            &copy; {{ date('Y') }} ChatMe &mdash; Kuala Lumpur, Malaysia
        </div>
        <div style="display:flex;gap:28px;">
            <a href="{{ route('privacy') }}" style="font-size:13px;color:rgba(255,255,255,0.3);text-decoration:none;transition:color 0.3s;">Privasi</a>
            <a href="{{ route('terms') }}" style="font-size:13px;color:rgba(255,255,255,0.3);text-decoration:none;transition:color 0.3s;">Terma</a>
            <a href="mailto:hello@akmalmarvis.com" style="font-size:13px;color:rgba(255,255,255,0.3);text-decoration:none;transition:color 0.3s;">Hubungi</a>
        </div>
    </div>
</footer>

{{-- Widget --}}
<script src="{{ asset('widget/cm_Hx7ZlDQ7eNyLnFrQDX66KQjBRA10WozJ.js') }}" defer></script>

{{-- ══ SCROLL ENTRY ANIMATIONS ═══════════════════════════════ --}}
<script>
(function() {
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.style.transition = 'opacity 0.9s cubic-bezier(0.32,0.72,0,1), transform 0.9s cubic-bezier(0.32,0.72,0,1), filter 0.9s cubic-bezier(0.32,0.72,0,1)';
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.filter = 'blur(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });

    document.querySelectorAll('.reveal, .reveal-stagger > *').forEach(function(el) {
        var isStagger = el.parentElement && el.parentElement.classList.contains('reveal-stagger');
        if (!isStagger) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(24px)';
            el.style.filter = 'blur(4px)';
        }
        observer.observe(el);
    });

    // Stagger children
    document.querySelectorAll('.reveal-stagger').forEach(function(container) {
        var children = container.children;
        for (var i = 0; i < children.length; i++) {
            children[i].style.opacity = '0';
            children[i].style.transform = 'translateY(24px)';
            children[i].style.filter = 'blur(4px)';
            children[i].style.transitionDelay = (i * 60) + 'ms';
        }
    });
})();
</script>

@endsection
