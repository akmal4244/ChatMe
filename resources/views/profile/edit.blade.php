@extends('layouts.app')

@section('title', 'Profil Akaun')
@section('page-title', 'Profil akaun')

@section('content')
<section class="profile-page" aria-labelledby="profile-heading">
    <header class="page-header profile-page__header">
        <div>
            <p class="eyebrow">Tetapan akaun</p>
            <h1 id="profile-heading">Profil akaun</h1>
            <p>Kemas kini maklumat peribadi dan keselamatan akaun anda.</p>
        </div>
    </header>

    @if(! $user->hasVerifiedEmail())
        <section class="profile-verification panel" aria-labelledby="profile-verification-heading">
            <div>
                <h2 id="profile-verification-heading">E-mel anda belum disahkan</h2>
                <p>Sahkan e-mel untuk mengakses papan pemuka, chatbot dan pelan langganan.</p>
            </div>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="button button-secondary">Hantar semula e-mel pengesahan</button>
            </form>
        </section>
    @endif

    <div class="profile-grid">
        <section class="profile-card panel" aria-labelledby="profile-details-heading">
            <div class="profile-card__header">
                <h2 id="profile-details-heading">Maklumat profil</h2>
                <p>Perubahan e-mel memerlukan pengesahan semula.</p>
            </div>

            <form id="profile-form" method="POST" action="{{ route('profile.update') }}" class="auth-form" data-submit-loading>
                @csrf
                @method('PATCH')

                <div class="form-field">
                    <label for="profile-name">Nama penuh</label>
                    <input id="profile-name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" @error('name') aria-describedby="profile-name-error" aria-invalid="true" @enderror required>
                    @error('name')<p id="profile-name-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="profile-email">E-mel</label>
                    <p id="profile-email-hint" class="field-hint">Kami akan meminta kata laluan semasa dan menghantar pautan baharu jika alamat ini berubah.</p>
                    <input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" inputmode="email" aria-describedby="profile-email-hint{{ $errors->has('email') ? ' profile-email-error' : '' }}" @error('email') aria-invalid="true" @enderror required>
                    @error('email')<p id="profile-email-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="profile-current-password">Kata laluan semasa <span class="field-optional">(diperlukan jika menukar e-mel)</span></label>
                    <input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" @error('current_password') aria-describedby="profile-current-password-error" aria-invalid="true" @enderror>
                    @error('current_password')<p id="profile-current-password-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="profile-company">Syarikat <span class="field-optional">(pilihan)</span></label>
                    <input id="profile-company" name="company" type="text" value="{{ old('company', $user->company) }}" autocomplete="organization" @error('company') aria-describedby="profile-company-error" aria-invalid="true" @enderror>
                    @error('company')<p id="profile-company-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="profile-website">Laman web <span class="field-optional">(pilihan)</span></label>
                    <p id="profile-website-hint" class="field-hint">Gunakan alamat penuh bermula dengan http:// atau https://.</p>
                    <input id="profile-website" name="website" type="url" value="{{ old('website', $user->website) }}" autocomplete="url" inputmode="url" aria-describedby="profile-website-hint{{ $errors->has('website') ? ' profile-website-error' : '' }}" @error('website') aria-invalid="true" @enderror>
                    @error('website')<p id="profile-website-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="button button-primary">Simpan profil</button>
            </form>
        </section>

        <section class="profile-card panel" aria-labelledby="password-details-heading">
            <div class="profile-card__header">
                <h2 id="password-details-heading">Tukar kata laluan</h2>
                <p>Gunakan kata laluan yang unik dan sukar diteka.</p>
            </div>

            <form id="password-form" method="POST" action="{{ route('profile.password.update') }}" class="auth-form" data-submit-loading>
                @csrf
                @method('PUT')

                <div class="form-field">
                    <label for="password-current">Kata laluan semasa</label>
                    <input id="password-current" name="current_password" type="password" autocomplete="current-password" @error('current_password') aria-describedby="password-current-error" aria-invalid="true" @enderror required>
                    @error('current_password')<p id="password-current-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="password-new">Kata laluan baharu</label>
                    <p id="password-new-hint" class="field-hint">Gunakan sekurang-kurangnya 8 aksara.</p>
                    <input id="password-new" name="password" type="password" autocomplete="new-password" aria-describedby="password-new-hint{{ $errors->has('password') ? ' password-new-error' : '' }}" @error('password') aria-invalid="true" @enderror required>
                    @error('password')<p id="password-new-error" class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-field">
                    <label for="password-confirmation">Sahkan kata laluan baharu</label>
                    <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>

                <button type="submit" class="button button-primary">Kemas kini kata laluan</button>
            </form>
        </section>
    </div>
</section>
@endsection
