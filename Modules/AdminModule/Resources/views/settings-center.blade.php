@extends('adminmodule::layouts.master')

@section('title', translate('Settings center'))

@section('content')
    @php
        $states = collect($groups)->flatMap(fn($g) => $g['cards'])->pluck('state')->filter();
        $ok = $states->where(0, 'on')->count();
        $todo = $states->filter(fn($s) => in_array($s[0], ['missing', 'off'], true))->count();
        $chip = ['on' => 'is-on', 'off' => 'is-off', 'missing' => 'is-missing'];
    @endphp
    <div class="main-content">
        <div class="container-fluid ed-sc">
            <div class="ed-sc-head">
                <div>
                    <h1>{{ translate('Settings center') }}</h1>
                    <p>{{ translate('Every setting of E-Ride in one place: what it changes, when you would change it, and whether it is set up. Open a card to change it.') }}</p>
                </div>
                <div class="ed-sc-sum">
                    <span class="ed-sc-pill is-on"><i class="bi bi-check-circle"></i>{{ translate(key: '{count} set up', replace: ['count' => $ok]) }}</span>
                    @if($todo)<span class="ed-sc-pill is-missing"><i class="bi bi-exclamation-circle"></i>{{ translate(key: '{count} to look at', replace: ['count' => $todo]) }}</span>@endif
                </div>
            </div>

            <div class="ed-sc-tools">
                <div class="ed-sc-search">
                    <i class="bi bi-search"></i>
                    <input type="search" id="ed-sc-q" placeholder="{{ translate('Find a setting: fares, Mobile Money, SMS, market, bus…') }}" autocomplete="off" aria-label="{{ translate('Find a setting') }}">
                </div>
                <nav class="ed-sc-jump" aria-label="{{ translate('Groups') }}">
                    @foreach($groups as $g)<a href="#sc-{{ $g['key'] }}">{{ translate($g['title']) }}</a>@endforeach
                </nav>
            </div>

            @foreach($groups as $g)
                <section class="ed-sc-group" id="sc-{{ $g['key'] }}" data-sc-group>
                    <h2>{{ translate($g['title']) }}</h2>
                    <p class="ed-sc-gabout">{{ translate($g['about']) }}</p>
                    <div class="ed-sc-grid">
                        @foreach($g['cards'] as $card)
                            <a class="ed-sc-card {{ !empty($card['danger']) ? 'is-danger' : '' }}" href="{{ $card['url'] }}"
                               data-sc-words="{{ strtolower(translate($card['title']) . ' ' . translate($card['about']) . ' ' . translate($g['title'])) }}">
                                <div class="ed-sc-card-top">
                                    <span class="ed-sc-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                                    <b>{{ translate($card['title']) }}</b>
                                    @if($card['state'])
                                        <span class="ed-sc-pill {{ $chip[$card['state'][0]] ?? '' }}">{{ translate($card['state'][1]) }}</span>
                                    @endif
                                </div>
                                <p>{{ translate($card['about']) }}</p>
                                @if(!empty($card['when']))<small><i class="bi bi-clock-history"></i>{{ translate($card['when']) }}</small>@endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <p class="ed-sc-none d-none">{{ translate('No setting matches. Try another word, or press Ctrl + K to search every page.') }}</p>
        </div>
    </div>
@endsection

@push('script')
<script>
    (function () {
        var q = document.getElementById('ed-sc-q'), none = document.querySelector('.ed-sc-none');
        if (!q) return;
        q.addEventListener('input', function () {
            var words = q.value.toLowerCase().trim().split(/\s+/).filter(Boolean), any = false;
            document.querySelectorAll('[data-sc-group]').forEach(function (g) {
                var n = 0;
                g.querySelectorAll('.ed-sc-card').forEach(function (c) {
                    var t = c.dataset.scWords, hit = words.every(function (w) { return t.indexOf(w) !== -1; });
                    c.hidden = !hit; if (hit) n++;
                });
                g.hidden = n === 0; if (n) any = true;
            });
            none.classList.toggle('d-none', any);
        });
    })();
</script>
@endpush
