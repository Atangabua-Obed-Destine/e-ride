@extends('busmanagement::site._layout')

@section('title', $mode === 'register' ? $t('create_account') : ($mode === 'code' ? $t('code_title') : $t('sign_in')))

@section('body')
<div class="page">
    <div class="sec" style="padding-bottom:0"><h1 style="font-size:22px">{{ $t('account') }}</h1></div>

    @if($mode !== 'code')
        <nav class="row" style="margin:14px 16px 0;padding:4px;border-radius:12px;background:#E9EDEC;gap:4px" aria-label="{{ $t('account') }}">
            <a href="{{ route('bus.web.login') }}" class="b" style="flex:1;text-align:center;padding:10px;border-radius:10px;text-decoration:none;color:#1D2D2B;{{ $mode === 'login' ? 'background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08)' : 'color:#5F6B6A' }}" @if($mode === 'login') aria-current="page" @endif>{{ $t('sign_in') }}</a>
            <a href="{{ route('bus.web.register') }}" class="b" style="flex:1;text-align:center;padding:10px;border-radius:10px;text-decoration:none;color:#1D2D2B;{{ $mode === 'register' ? 'background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08)' : 'color:#5F6B6A' }}" @if($mode === 'register') aria-current="page" @endif>{{ $t('create_account') }}</a>
        </nav>
    @endif

    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

    @if($mode === 'login')
        <form method="POST" action="{{ route('bus.web.login.submit') }}" class="sec col" style="gap:14px">
            @csrf
            <div><label class="lbl" for="phone">{{ $t('phone') }}</label>
                <div class="prefix"><span>+237</span><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required placeholder="6 77 12 34 56"></div></div>
            <div><label class="lbl" for="password">{{ $t('password') }}</label><input class="in" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <a class="sm b" href="{{ route('bus.web.app', ['to' => 'bh']) }}">{{ $t('forgot') }}</a>
            <button class="btn" type="submit">{{ $t('sign_in_continue') }}</button>
        </form>
    @elseif($mode === 'register')
        @if(!$web['signup'])
            <div class="sec col" style="gap:12px">
                <div class="warn">{{ $t('signup_in_app') }}</div>
                <a class="btn dark" href="{{ route('bus.web.app', ['to' => 'bh']) }}">{{ $t('get_app') }}</a>
            </div>
        @else
            <form method="POST" action="{{ route('bus.web.register.submit') }}" class="sec col" style="gap:14px">
                @csrf
                <div><label class="lbl" for="name">{{ $t('full_name') }}</label><input class="in" id="name" name="name" autocomplete="name" value="{{ old('name') }}" required minlength="3" maxlength="120"></div>
                <div><label class="lbl" for="phone">{{ $t('phone') }}</label>
                    <div class="prefix"><span>+237</span><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required placeholder="6 77 12 34 56"></div></div>
                <div><label class="lbl" for="password">{{ $t('password') }}</label><input class="in" id="password" name="password" type="password" autocomplete="new-password" required minlength="8"><span class="xs muted">{{ $t('password_hint') }}</span></div>
                <p class="xs muted" style="margin:0">{{ $t('same_account') }}</p>
                <button class="btn" type="submit">{{ $t('create_continue') }}</button>
            </form>
        @endif
    @else
        <form method="POST" action="{{ route('bus.web.register.verify') }}" class="sec col" style="gap:14px">
            @csrf
            <h2>{{ $t('code_title') }}</h2>
            <p class="sm muted" style="margin:0">{{ $t('code_sent', ['phone' => $codePhone]) }}</p>
            <div><label class="lbl" for="code">{{ $t('code') }}</label><input class="in" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required style="letter-spacing:.3em;font-weight:700"></div>
            <button class="btn" type="submit">{{ $t('verify_create') }}</button>
        </form>
    @endif
</div>
@endsection
