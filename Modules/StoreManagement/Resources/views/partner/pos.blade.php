@extends('storemanagement::partner.layout')

@section('title', translate('POS Innova'))

@section('content')
    <a href="{{ route('partner.store') }}" class="text-muted text-decoration-none" style="font-size:13px"><i class="bi bi-arrow-left"></i> {{ translate('Store') }}</a>
    <h1 class="fs-4 fw-bold mb-1 mt-1">{{ translate('POS Innova') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">{{ translate('Link the POS Innova of each branch. Its stock then decides what customers can order on E-Ride, and E-Ride orders show on the POS screen for the salesperson to ring up as usual.') }}</p>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($kitchens->count() > 1)
        <div class="d-flex flex-wrap gap-2 mb-3" role="tablist">
            @foreach($kitchens as $k)
                <a href="{{ route('partner.pos', ['kitchen' => $k->id]) }}" class="btn btn-sm {{ $k->id === $kitchen->id ? 'btn-dark' : 'btn-outline-dark' }}" style="border-radius:6px">{{ $k->branch_name ?: $k->name }}</a>
            @endforeach
        </div>
    @endif

    @if($newKey)
        <div class="p-card p-3 mb-3" style="border:2px solid #198754">
            <h2 class="fs-6 fw-bold mb-1">{{ translate('Your key for this branch') }}</h2>
            <p class="text-muted mb-2" style="font-size:13px">{{ translate('Copy it now: E-Ride shows it only once. In POS Innova, open Settings, E-Ride, and paste the address and the key.') }}</p>
            <div class="fs-12 fw-semibold">{{ translate('Address') }}</div>
            <div class="input-group input-group-sm mb-2">
                <input class="form-control" value="{{ $apiUrl }}" readonly id="pos-url" aria-label="{{ translate('Address') }}">
                <button class="btn btn-outline-dark" type="button" data-copy="#pos-url">{{ translate('Copy') }}</button>
            </div>
            <div class="fs-12 fw-semibold">{{ translate('Key') }}</div>
            <div class="input-group input-group-sm">
                <input class="form-control font-monospace" value="{{ $newKey }}" readonly id="pos-key" aria-label="{{ translate('Key') }}">
                <button class="btn btn-outline-dark" type="button" data-copy="#pos-key">{{ translate('Copy') }}</button>
            </div>
        </div>
    @endif

    <div class="p-card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="fs-6 fw-bold m-0">{{ $kitchen->branch_name ?: $kitchen->name }}</h2>
            @if(!$link)
                <span class="badge text-bg-secondary">{{ translate('Not linked') }}</span>
            @elseif(!$link->linked_at)
                <span class="badge text-bg-warning">{{ translate('Waiting for the POS') }}</span>
            @elseif($link->isConnected())
                <span class="badge text-bg-success">{{ translate('Connected') }}</span>
            @else
                <span class="badge text-bg-danger">{{ translate('Not heard from') }}</span>
            @endif
        </div>
        @if($link)
            <div class="text-muted" style="font-size:13px">
                <div>{{ translate('Key') }}: <span class="font-monospace">{{ $link->key_hint }}…</span></div>
                @if($link->pos_location_name)<div>{{ translate('POS location') }}: {{ $link->pos_location_name }}</div>@endif
                @if($link->last_seen_at)<div>{{ translate('Last heard from') }}: {{ $link->last_seen_at->diffForHumans() }}</div>@endif
                @if($link->last_catalog_at)<div>{{ translate('Products updated') }}: {{ $link->last_catalog_at->diffForHumans() }}</div>@endif
                @if($counts && $counts['all'] > 0)<div>{{ translate(key: '{linked} of {all} products linked to your menu', replace: ['linked' => $counts['linked'], 'all' => $counts['all']]) }}</div>@endif
                <div>{{ $link->qty_display === 'exact' ? translate('Customers see how many are left.') : translate('Customers see only whether a product is available.') }}</div>
            </div>
            @if($link->linked_at && !$link->isConnected())
                <div class="alert alert-warning mt-2 mb-0" style="font-size:13px">{{ translate('E-Ride has not heard from this POS for a while. Check that the POS computer is on and has internet. Until then, E-Ride orders do not reach the POS screen: watch this portal.') }}</div>
            @endif
            <div class="d-flex flex-wrap gap-2 mt-3">
                <form method="POST" action="{{ route('partner.pos.link') }}" onsubmit="return confirm(@js(translate('A new key stops the current one from working.')))">@csrf
                    <input type="hidden" name="kitchen" value="{{ $kitchen->id }}">
                    <button class="btn btn-outline-dark btn-sm" style="border-radius:6px">{{ translate('New key') }}</button>
                </form>
                <form method="POST" action="{{ route('partner.pos.revoke', $link->id) }}" onsubmit="return confirm(@js(translate('Unlinked, this POS stops updating your menu.')))">@csrf
                    <button class="btn btn-outline-danger btn-sm" style="border-radius:6px">{{ translate('Unlink') }}</button>
                </form>
            </div>
        @else
            <ol class="ps-3 text-muted" style="font-size:13px">
                <li>{{ translate('Press Link a POS: E-Ride gives you an address and a key for this branch.') }}</li>
                <li>{{ translate('In POS Innova at this branch, open Settings, E-Ride, and paste them.') }}</li>
                <li>{{ translate('The products of the POS appear here. Link each one to a dish or item on your menu.') }}</li>
            </ol>
            <form method="POST" action="{{ route('partner.pos.link') }}">@csrf
                <input type="hidden" name="kitchen" value="{{ $kitchen->id }}">
                <button class="btn btn-dark" style="border-radius:6px">{{ translate('Link a POS') }}</button>
            </form>
        @endif
    </div>

    @if($link && $counts && $counts['all'] > 0)
        <div class="p-card p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h2 class="fs-6 fw-bold m-0">{{ translate('Products from the POS') }}</h2>
                @php($nSuggest = $suggest->filter()->count())
                @if($nSuggest)
                    <form method="POST" action="{{ route('partner.pos.link-suggested', $link->id) }}">@csrf
                        <button class="btn btn-dark btn-sm" style="border-radius:6px">{{ translate(key: 'Link the {n} with the same name', replace: ['n' => $nSuggest]) }}</button>
                    </form>
                @endif
            </div>
            <p class="text-muted" style="font-size:13px">{{ translate('A linked product follows the POS: when its stock runs out, customers cannot order it on E-Ride. A POS product reaches customers only once it is linked to your menu or added to it.') }}</p>
            <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                <input type="hidden" name="kitchen" value="{{ $kitchen->id }}">
                <select name="show" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()" aria-label="{{ translate('Show') }}">
                    <option value="unlinked" @selected($filter === 'unlinked')>{{ translate('Not linked') }} ({{ $counts['all'] - $counts['linked'] }})</option>
                    <option value="linked" @selected($filter === 'linked')>{{ translate('Linked') }} ({{ $counts['linked'] }})</option>
                    <option value="all" @selected($filter === 'all')>{{ translate('All') }} ({{ $counts['all'] }})</option>
                </select>
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" style="max-width:240px" placeholder="{{ translate('Search a name or SKU') }}">
                <button class="btn btn-outline-dark btn-sm" style="border-radius:6px">{{ translate('Search') }}</button>
            </form>

            @forelse($items as $p)
                @php($hint = $suggest[$p->id] ?? null)
                <div class="d-flex flex-wrap align-items-center gap-3 py-2" style="border-top:1px solid #eee">
                    @if($img = $images[$p->image_sha1] ?? null)
                        <img src="{{ $img }}" alt="" width="48" height="48" style="object-fit:cover;border-radius:6px">
                    @else
                        <div style="width:48px;height:48px;border-radius:6px;background:#f2f2f2" class="d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>
                    @endif
                    <div class="flex-grow-1" style="min-width:160px">
                        <div class="fw-semibold" style="font-size:14px">{{ $p->fullName() }}</div>
                        <div class="text-muted fs-12">
                            {{ number_format($p->price) }} FCFA{{ $p->unit && !in_array(strtolower($p->unit), ['piece', 'pieces', 'pc', 'pcs', 'unit', 'units', 'each', 'item'], true) ? ' / ' . $p->unit : '' }}
                            @if($p->sku) · {{ $p->sku }}@endif
                            ·
                            @if(!$p->track_stock)
                                {{ translate('Stock not kept') }}
                            @elseif($p->sellable())
                                <span class="text-success">{{ translate(key: '{n} in stock', replace: ['n' => rtrim(rtrim(number_format((float)$p->qty_available, 2, '.', ''), '0'), '.')]) }}</span>
                            @else
                                <span class="text-danger">{{ translate('Out of stock') }}</span>
                            @endif
                        </div>
                        @if($p->menuItem)
                            <div class="fs-12 mt-1"><i class="bi bi-link-45deg"></i> {{ $p->menuItem->name }}</div>
                        @endif
                    </div>
                    @if($p->store_item_id)
                        <form method="POST" action="{{ route('partner.pos.item.link', $p->id) }}">@csrf
                            <button class="btn btn-outline-secondary btn-sm" style="border-radius:6px">{{ translate('Unlink') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('partner.pos.item.link', $p->id) }}" class="d-flex gap-1">@csrf
                            <select name="menu_item_id" class="form-select form-select-sm" style="max-width:200px" required aria-label="{{ translate('Menu item') }}">
                                <option value="">{{ translate('Choose on your menu') }}</option>
                                @foreach($menu as $m)
                                    <option value="{{ $m->id }}" @selected($hint === $m->id)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-dark btn-sm" style="border-radius:6px">{{ translate('Link') }}</button>
                        </form>
                        <form method="POST" action="{{ route('partner.pos.item.create', $p->id) }}" class="d-flex gap-1">@csrf
                            @if($categories->count())
                                <select name="category_id" class="form-select form-select-sm" style="max-width:150px" aria-label="{{ translate('Category') }}">
                                    @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            @endif
                            <button class="btn btn-outline-dark btn-sm" style="border-radius:6px">{{ translate('Add to menu') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0" style="font-size:14px">{{ translate('Nothing here.') }}</p>
            @endforelse
            <div class="mt-2">{!! $items->links() !!}</div>
        </div>
    @elseif($link && $link->linked_at)
        <div class="p-card p-3 text-muted" style="font-size:14px">{{ translate('The POS has not sent its products yet. They arrive a few minutes after it connects.') }}</div>
    @endif

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (b) {
            b.addEventListener('click', function () {
                var i = document.querySelector(b.getAttribute('data-copy'));
                i.select();
                (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
                    b.textContent = @json(translate('Copied'));
                });
            });
        });
    </script>
@endsection
