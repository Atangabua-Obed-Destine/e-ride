{{-- Region / city / branch filter (and the period on Reports), as a GET form. --}}
<form method="GET" class="ho-filters mb-3" aria-label="{{ translate('Filter') }}">
    @if(count($regions) > 1)
        <select name="region" class="form-select" aria-label="{{ translate('Region') }}" onchange="this.form.submit()">
            <option value="">{{ translate('All regions') }}</option>
            @foreach($regions as $k => $label)<option value="{{ $k }}" @selected(($picked['region'] ?? '') === $k)>{{ $label }}</option>@endforeach
        </select>
    @endif
    @if(count($cities) > 1)
        <select name="city" class="form-select" aria-label="{{ translate('City') }}" onchange="this.form.submit()">
            <option value="">{{ translate('All cities') }}</option>
            @foreach($cities as $c)<option value="{{ $c }}" @selected(($picked['city'] ?? '') === $c)>{{ $c }}</option>@endforeach
        </select>
    @endif
    @if($branches->count() > 1)
        <select name="branch" class="form-select" aria-label="{{ translate('Branch') }}" onchange="this.form.submit()">
            <option value="">{{ translate('All branches') }}</option>
            @foreach($branches as $b)<option value="{{ $b->id }}" @selected(($picked['branch'] ?? '') === (string)$b->id)>{{ $b->branchLabel() }}</option>@endforeach
        </select>
    @endif
    @isset($from)
        <label class="small fw-semibold" for="fFrom">{{ translate('From') }}</label>
        <input id="fFrom" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
        <label class="small fw-semibold" for="fTo">{{ translate('to') }}</label>
        <input id="fTo" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
        <button class="btn btn-dark btn-sm">{{ translate('Show') }}</button>
        <span class="d-flex gap-1 flex-wrap">
            @foreach([[translate('Today'), today(), today()], [translate('This week'), today()->startOfWeek(), today()], [translate('This month'), today()->startOfMonth(), today()], [translate('Last month'), today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()]] as [$l, $f, $t])
                <a class="btn btn-soft btn-sm" href="{{ request()->fullUrlWithQuery(['from' => $f->toDateString(), 'to' => $t->toDateString()]) }}">{{ $l }}</a>
            @endforeach
        </span>
    @endisset
</form>
