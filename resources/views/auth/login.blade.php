@extends('layouts.guest')
@section('title', 'Log Masuk — ChatMe')
@section('content')

{{-- Film grain --}}
<div style="position:fixed;inset:0;z-index:50;pointer-events:none;opacity:0.035;background-image:url('data:image/svg+xml,<svg viewBox=\'0 0 256 256\' xmlns=\'http://www.w3.org/2000/svg\'><filter id=\'n\'><feTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\' stitchTiles=\'stitch\'/></filter><rect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/></svg>');"></div>

{{-- Orb --}}
<div style="position:fixed;inset:0;z-index:0;pointer-events:none;">
    <div style="position:absolute;top:50%;left:50%;width:800px;height:800px;background:radial-gradient(circle,rgba(99,102,241,0.07) 0%,transparent 60%);transform:translate(-50%,-50%);"></div>
</div>

<div style="position:relative;z-index:1;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="width:100%;max-width:380px;">
        <div style="text-align:center;margin-bottom:36px;">
            <a href="/" style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;margin-bottom:24px;">
                <img src="{{ asset('akmal3d.png') }}" alt="ChatMe" style="width:32px;height:32px;border-radius:6px;">
                <span style="font-family:'Newsreader',serif;font-size:20px;font-weight:500;color:#fff;letter-spacing:-0.02em;">ChatMe</span>
            </a>
            <h1 style="font-family:'Newsreader',serif;font-size:28px;font-weight:400;color:#fff;letter-spacing:-0.03em;margin-bottom:6px;">Selamat kembali</h1>
            <p style="font-size:14px;color:rgba(255,255,255,0.35);">Log masuk ke akaun anda.</p>
        </div>

        {{-- Double-Bezel form card --}}
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:24px;padding:10px;">
            <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);border-radius:16px;padding:32px 28px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);">

                @if($errors->any())
                <div style="margin-bottom:20px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.15);padding:12px 16px;border-radius:12px;font-size:13px;color:rgba(252,165,165,0.9);">
                    {{ $errors->first() }}
                </div>
                @endif

                <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:18px;">
                    @csrf
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,0.4);margin-bottom:8px;letter-spacing:0.02em;">E-mel</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:12px 16px;font-size:14px;color:#fff;outline:none;font-family:inherit;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);"
                            placeholder="nama@example.com" autofocus
                            onfocus="this.style.borderColor='rgba(255,255,255,0.25)';this.style.background='rgba(255,255,255,0.06)'"
                            onblur="this.style.borderColor='rgba(255,255,255,0.08)';this.style.background='rgba(255,255,255,0.04)'">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,0.4);margin-bottom:8px;letter-spacing:0.02em;">Kata Laluan</label>
                        <input type="password" name="password" required
                            style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:12px 16px;font-size:14px;color:#fff;outline:none;font-family:inherit;transition:all 0.3s cubic-bezier(0.32,0.72,0,1);"
                            placeholder="••••••••"
                            onfocus="this.style.borderColor='rgba(255,255,255,0.25)';this.style.background='rgba(255,255,255,0.06)'"
                            onblur="this.style.borderColor='rgba(255,255,255,0.08)';this.style.background='rgba(255,255,255,0.04)'">
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,0.4);cursor:pointer;">
                        <input type="checkbox" name="remember" style="accent-color:#fff;width:16px;height:16px;border-radius:4px;"> Ingat saya
                    </label>
                    <button type="submit" class="group" style="width:100%;background:#fff;color:#050505;padding:13px;border-radius:999px;font-size:14px;font-weight:600;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.4s cubic-bezier(0.32,0.72,0,1);">
                        Log Masuk
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:rgba(0,0,0,0.06);transition:all 0.4s cubic-bezier(0.32,0.72,0,1);" class="group-hover:translate-x-0.5 group-hover:scale-105">
                            <i class="ph ph-arrow-right" style="font-size:10px;"></i>
                        </span>
                    </button>
                </form>

                <p style="text-align:center;font-size:13px;color:rgba(255,255,255,0.25);margin-top:24px;">
                    Tiada akaun? <a href="{{ route('register') }}" style="color:rgba(255,255,255,0.6);font-weight:500;text-decoration:none;">Daftar</a>
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
