@extends('storemanagement::partner.layout')

@section('title', translate('My riders'))

@push('head')
<style>
    .r-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
    .r-row + .r-row { border-top: 1px solid var(--line); }
    .r-pic { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: #ececf1; flex: none; display: flex; align-items: center; justify-content: center; color: #9a9aa6; font-size: 22px; }
    .r-name { font-weight: 700; line-height: 1.2; }
    .r-meta { font-size: 12px; color: var(--muted); }
    .r-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #c8c8d0; margin-right: 4px; }
    .r-dot.on { background: var(--ok); }
    .mode { display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--line); border-radius: 12px; padding: 12px; cursor: pointer; }
    .mode + .mode { margin-top: 8px; }
    .mode.is-on { border-color: var(--red); background: var(--red-soft); }
    .mode input { margin-top: 3px; }
    .mode b { display: block; }
    .mode span { font-size: 13px; color: var(--muted); }
</style>
@endpush

@section('content')
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('partner.store') }}" class="btn btn-sm btn-light" aria-label="{{ translate('Back') }}"><i class="bi bi-arrow-left"></i></a>
        <h1 class="fs-4 fw-bold m-0">{{ translate('My riders') }}</h1>
    </div>
    <p class="text-muted">{{ translate('Choose the E-Ride riders you trust for your deliveries. They are offered your orders before the other riders.') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="p-card p-3 mb-3">
                <h2 class="fs-6 fw-bold mb-3">{{ translate('Who is called for a delivery') }}</h2>
                <form action="{{ route('partner.riders.mode') }}" method="POST" data-mode-form>
                    @csrf
                    @php($mode = $store->riders_mode ?: 'all')
                    @foreach([
                        'all' => [translate('Every rider nearby'), translate('The way it works without a list.')],
                        'first' => [translate('My riders first, then everyone'), translate(key: 'Your riders are called first. If none of them takes the order within about {seconds} seconds, every rider nearby is called.', replace: ['seconds' => $firstSeconds])],
                        'only' => [translate('Only my riders'), translate('Nobody else is called. If none of your riders is free, the order waits until you open it to every rider on the Orders page.')],
                    ] as $value => $text)
                        <label class="mode {{ $mode === $value ? 'is-on' : '' }}">
                            <input type="radio" name="riders_mode" value="{{ $value }}" {{ $mode === $value ? 'checked' : '' }}>
                            <div><b>{{ $text[0] }}</b><span>{{ $text[1] }}</span></div>
                        </label>
                    @endforeach
                    <button type="submit" class="btn btn-primary w-100 mt-3" style="min-height:46px">{{ translate('Save') }}</button>
                </form>
                @if($mode !== 'all' && count($riders) === 0)
                    <div class="alert alert-warning mt-3 mb-0">{{ translate('Your list is empty, so every rider nearby is still called. Add a rider below.') }}</div>
                @endif
            </div>

            <div class="p-card p-3 mb-3">
                <h2 class="fs-6 fw-bold mb-1">{{ translate('Add a rider by phone number') }}</h2>
                <p class="r-meta mb-2">{{ translate('The number the rider uses on E-Ride. Ask the rider if you are not sure.') }}</p>
                <form action="{{ route('partner.riders.add') }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="tel" name="phone" class="form-control" required maxlength="25" placeholder="6 71 00 00 00" value="{{ old('phone') }}" aria-label="{{ translate('Phone number') }}">
                    <button type="submit" class="btn btn-dark" style="white-space:nowrap">{{ translate('Add') }}</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="p-card p-3 mb-3">
                <h2 class="fs-6 fw-bold mb-1">{{ translate('Your riders') }} <span class="text-muted fw-normal">{{ count($riders) }}/{{ $max }}</span></h2>
                @forelse($riders as $r)
                    <div class="r-row">
                        @if($r['image'])<img class="r-pic" src="{{ $r['image'] }}" alt="">@else<span class="r-pic"><i class="bi bi-person"></i></span>@endif
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="r-name text-truncate">{{ $r['name'] }}</div>
                            <div class="r-meta">
                                <span class="r-dot {{ $r['online'] ? 'on' : '' }}"></span>{{ $r['online'] ? translate('Online') : translate('Offline') }}
                                @if($r['rating']) · <i class="bi bi-star-fill"></i> {{ $r['rating'] }}@endif
                                · {{ translate('Deliveries for you') }}: {{ $r['deliveries'] }}
                            </div>
                            <a class="r-meta" href="tel:{{ $r['phone'] }}">{{ $r['phone'] }}</a>
                        </div>
                        <form action="{{ route('partner.riders.remove', ['driver' => $r['driver_id']]) }}" method="POST" class="m-0"
                              onsubmit="return confirm(@json(translate('Remove this rider from your list?')))">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ translate('Remove') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted mb-0 mt-2">{{ translate('No riders yet. Add the riders you trust: they will be told.') }}</p>
                @endforelse
            </div>

            @if(count($candidates))
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-1">{{ translate('Riders who delivered for you') }}</h2>
                    @foreach($candidates as $r)
                        <div class="r-row">
                            @if($r['image'])<img class="r-pic" src="{{ $r['image'] }}" alt="">@else<span class="r-pic"><i class="bi bi-person"></i></span>@endif
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="r-name text-truncate">{{ $r['name'] }}</div>
                                <div class="r-meta">
                                    @if($r['rating'])<i class="bi bi-star-fill"></i> {{ $r['rating'] }} · @endif
                                    {{ translate('Deliveries for you') }}: {{ $r['deliveries'] }}
                                </div>
                            </div>
                            <form action="{{ route('partner.riders.add') }}" method="POST" class="m-0">
                                @csrf
                                <input type="hidden" name="driver_id" value="{{ $r['driver_id'] }}">
                                <button type="submit" class="btn btn-sm btn-dark">{{ translate('Add') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script')
<script>
    document.querySelectorAll('[data-mode-form] input[type=radio]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('[data-mode-form] .mode').forEach(function (l) { l.classList.toggle('is-on', l.contains(input) && input.checked); });
        });
    });
</script>
@endpush
