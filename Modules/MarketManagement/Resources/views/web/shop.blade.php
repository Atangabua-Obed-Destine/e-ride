@extends('marketmanagement::web._layout')

@section('title', $shop->name)
@section('description', trim(($shop->area_label ? $shop->area_label . ' · ' : '') . ($shop->about ?: $t['market'])))
@section('og_url', $url)
@if($ogImage)
@section('og_image', $ogImage)
@endif

@section('body')
<div class="bar">
    <a class="brand" href="{{ $url }}">E-RIDE <span>MARKET</span></a>
    <a class="pill" href="{{ $openApp }}">{{ $t['open_app'] }}</a>
</div>
<div style="padding:16px;display:flex;gap:14px;align-items:center">
    @if($shop->logoUrl())
        <img src="{{ $shop->logoUrl() }}" alt="" style="width:72px;height:72px;border-radius:20px;object-fit:cover;background:#EEF0F0">
    @else
        <div aria-hidden="true" style="width:72px;height:72px;border-radius:20px;background:#FDECEC;color:#E02020;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900">{{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}</div>
    @endif
    <div style="min-width:0">
        <h1 style="margin:0;font-size:20px;line-height:1.25">{{ $shop->name }}</h1>
        @if($shop->isVerified())<div class="badge">&#10003; {{ $t['verified'] }}</div>@endif
        <div class="muted">{{ trim(($shop->area_label ?: '') . ($shop->area_label ? ' · ' : '') . str_replace(':date', $shop->created_at->translatedFormat('M Y'), $t['since'])) }}</div>
    </div>
</div>
@if($shop->about)
    <p style="margin:0 16px 4px;font-size:14px">{{ $shop->about }}</p>
@endif
@if($shop->inVenue() && $shop->venue)
    {{-- In a market: the shed, the shopfront to look for, and the way there in the app. --}}
    <div style="margin:12px 16px;border:1px solid #F3D9B8;background:#FFF8F0;border-radius:8px;overflow:hidden">
        @if($shop->shopfrontUrl())<img src="{{ $shop->shopfrontUrl() }}" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block">@endif
        <div style="padding:12px">
            <div style="font-weight:800;font-size:16px">{{ $shop->venue->name }}</div>
            <div style="color:#B45309;font-weight:700">{{ $shop->shedLabel() }}</div>
            @if($shop->isVerified())<div class="badge">&#10003; {{ $t['shed_checked'] }}</div>@endif
            @if($shop->address_note)<div class="muted" style="margin-top:4px">{{ $shop->address_note }}</div>@endif
            <a class="btn" href="{{ $openApp }}" style="margin:10px 0 0;background:#B45309;height:46px;border-radius:6px;font-size:15px">{{ $t['shed_walk'] }}</a>
        </div>
    </div>
@endif
@if($shop->sellsList())
    <div class="chips" style="padding:4px 16px 8px">@foreach($shop->sellsList() as $s)<span class="chip">{{ $s }}</span>@endforeach</div>
@endif
<div class="notice">{{ $t['scam_notice'] }}</div>
@if($total > 1)
{{-- Search in this shop (filters as you type; "Search" also asks the server, for shops with more than 60 items),
     its categories, the order, and "hide sold out". The choices stay in the link, so it can be shared. --}}
@php($keep = fn(array $change) => $url . '?' . http_build_query(array_filter(array_merge(['q' => $q, 'cat' => $cat, 'sort' => $sort === 'new' ? null : $sort, 'stock' => $inStock ? 1 : null], $change), fn($v) => $v !== null && $v !== '')))
<form method="get" action="{{ $url }}" class="shop-search" role="search" id="shop-form">
    <input id="shop-q" type="search" name="q" value="{{ $q }}" placeholder="{{ $t['search_shop'] }}" aria-label="{{ $t['search_shop'] }}" autocomplete="off" maxlength="80">
    @if($cat)<input type="hidden" name="cat" value="{{ $cat }}">@endif
    <button type="submit">{{ $t['search'] }}</button>
    <div class="shop-tools">
        <select name="sort" aria-label="{{ $t['sort_new'] }}" onchange="this.form.submit()">
            @foreach(['new', 'low', 'high'] as $s)<option value="{{ $s }}" {{ $sort === $s ? 'selected' : '' }}>{{ $t['sort_' . $s] }}</option>@endforeach
        </select>
        <label><input type="checkbox" name="stock" value="1" {{ $inStock ? 'checked' : '' }} onchange="this.form.submit()"> {{ $t['in_stock'] }}</label>
    </div>
</form>
@if(count($categories) > 1)
<nav class="shop-cats" aria-label="{{ $t['all'] }}">
    <a href="{{ $keep(['cat' => null]) }}" class="{{ $cat ? '' : 'on' }}">{{ $t['all'] }} <span>{{ $total }}</span></a>
    @foreach($categories as $key => $n)
        <a href="{{ $keep(['cat' => $key]) }}" class="{{ $cat === $key ? 'on' : '' }}">{{ $t['cat_' . $key] ?? ucfirst($key) }} <span>{{ $n }}</span></a>
    @endforeach
</nav>
@endif
@endif
<div id="shop-count" style="padding:8px 16px;font-weight:700"
     data-one="{{ $t['one_item'] }}" data-many="{{ $t['items'] }}" data-found="{{ $t['found'] }}">{{ $filtered ? str_replace(':n', $items->count(), $t['found']) : ($total === 1 ? $t['one_item'] : str_replace(':n', $total, $t['items'])) }}</div>
<p id="shop-none" class="muted" style="padding:0 16px 24px;{{ $filtered && $items->isEmpty() ? '' : 'display:none' }}" data-text="{{ $t['no_match'] }}">{{ $q !== '' ? str_replace(':q', $q, $t['no_match']) : $t['no_filter_match'] }}
    @if($filtered)<br><a href="{{ $url }}">{{ $t['show_all'] }}</a>@endif</p>
@if($items->isEmpty() && !$filtered)
    <p class="muted" style="padding:0 16px 24px">{{ $t['no_items'] }}</p>
@elseif($items->isNotEmpty())
<div class="grid" id="shop-grid">
    @foreach($items as $it)
        @php($c = $it->cover())
        <a class="card" href="{{ \Modules\MarketManagement\Lib\MarketLinks::item($it) }}" data-s="{{ mb_strtolower($it->title . ' ' . $it->tags_search . ' ' . $it->category) }}">
            @if($c)<img src="{{ $c->url() }}" alt="{{ $it->title }}" loading="lazy">@else<div class="ph"></div>@endif
            <div class="in">
                <div class="t">{{ $it->title }}</div>
                <div class="price">{{ $it->price !== null ? getCurrencyFormat($it->price) : $t['ask_price'] }}</div>
                @if($it->status === 'sold_out')<div class="muted">{{ $t['sold_out'] }}</div>@endif
            </div>
        </a>
    @endforeach
</div>
@endif
@if($total > 1)
<style>
.shop-search{display:flex;gap:8px;padding:4px 16px 0}
.shop-search input{flex:1;min-width:0;border:1px solid #D5DBDA;border-radius:6px;padding:10px 12px;font-size:15px;background:#fff;color:#1D2D2B}
.shop-search input:focus{outline:2px solid #E02020;outline-offset:-1px}
.shop-search{flex-wrap:wrap}
.shop-search button{border:0;border-radius:6px;background:#E02020;color:#fff;font-weight:700;padding:0 14px;font-size:14px}
.shop-tools{flex-basis:100%;display:flex;gap:12px;align-items:center;font-size:13px;color:#1D2D2B}
.shop-tools select{border:1px solid #D5DBDA;border-radius:6px;padding:7px 8px;font-size:13px;background:#fff;color:#1D2D2B}
.shop-tools label{display:flex;gap:6px;align-items:center}
.shop-cats{display:flex;gap:8px;overflow-x:auto;padding:10px 16px 2px;scrollbar-width:none}
.shop-cats::-webkit-scrollbar{display:none}
.shop-cats a{flex:none;border:1px solid #D5DBDA;border-radius:6px;padding:6px 10px;font-size:13px;color:#1D2D2B;text-decoration:none;background:#fff}
.shop-cats a span{color:#7A8785;font-size:12px}
.shop-cats a.on{background:#1D2D2B;border-color:#1D2D2B;color:#fff}
.shop-cats a.on span{color:#cfd6d5}
</style>
<script>
(function () {
    var input = document.getElementById('shop-q'), grid = document.getElementById('shop-grid'),
        count = document.getElementById('shop-count'), none = document.getElementById('shop-none');
    if (!input || !grid) return;
    var cards = grid.querySelectorAll('.card'), fold = function (s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    };
    input.addEventListener('input', function () {
        var words = fold(input.value).split(/\s+/).filter(Boolean), shown = 0;
        cards.forEach(function (c) {
            var text = fold(c.getAttribute('data-s')), hit = words.every(function (w) { return text.indexOf(w) !== -1; });
            c.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        count.textContent = words.length ? count.dataset.found.replace(':n', shown)
            : (cards.length === 1 ? count.dataset.one : count.dataset.many.replace(':n', cards.length));
        none.style.display = words.length && !shown ? '' : 'none';
        if (words.length && !shown) none.firstChild.textContent = none.dataset.text.replace(':q', input.value.trim());
    });
})();
</script>
@endif
<a class="btn" href="{{ $openApp }}">{{ $t['chat'] }}</a>
<div class="foot"><span class="muted">{{ $t['no_phone'] }}</span><a href="{{ $openApp }}">{{ $t['report'] }}</a></div>
@endsection
