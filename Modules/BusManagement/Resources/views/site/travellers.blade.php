@extends('busmanagement::site._layout')

@php($at = \Illuminate\Support\Carbon::parse($info['leaves_at']))
@section('title', $t('who_travels'))

@push('head')
<style>
.opt{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1.5px solid #D5DCDB;border-radius:12px;background:#fff;cursor:pointer}
.opt input{width:20px;height:20px;accent-color:#E02020;flex-shrink:0}
.opt:has(input:checked){border-color:#E02020;background:#FFF5F5}
</style>
@endpush

@section('body')
<div class="page">
    <div class="sec" style="background:#fff;border-bottom:1px solid #E8ECEB;padding:12px 16px">
        <div class="row" style="gap:12px">
            <a class="chip" href="{{ route('bus.web.departure', ['id' => $departure->id]) }}" aria-label="{{ $t('back') }}">‹</a>
            <div class="col" style="gap:0">
                <h1 style="font-size:18px">{{ $t('who_travels') }}</h1>
                <span class="xs muted">{{ $t('signed_in_as', ['name' => $me->full_name ?: $me->phone]) }}</span>
            </div>
        </div>
    </div>
    <div style="padding:12px 16px 0">
        <div class="card row" style="gap:10px;background:#F4F6F6;border:0">
            <div class="col" style="gap:0"><span class="sm b">{{ $t->when($at) }} · {{ count($seats) === 1 ? $t('seat_n', ['seat' => $seats[0]]) : $t('seats_n', ['seats' => implode(', ', $seats)]) }}</span>
                <span class="xs muted">{{ $departure->agency->displayName() }} · {{ $info['from'] }} → {{ $info['to'] }} · {{ $t->classLabel($info['class']) }}</span></div>
        </div>
    </div>

    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('bus.web.request', ['id' => $departure->id]) }}" class="sec col" style="gap:14px" id="req">
        @csrf
        @foreach($seats as $i => $seat)
            <div><label class="lbl" for="n{{ $seat }}">{{ $t('seat_n', ['seat' => $seat]) }} · {{ $t('traveller_name') }}</label>
                <input class="in" id="n{{ $seat }}" name="names[{{ $seat }}]" required minlength="2" maxlength="120" autocomplete="{{ $i === 0 ? 'name' : 'off' }}" value="{{ old('names.' . $seat, $i === 0 ? $me->full_name : '') }}"></div>
        @endforeach
        <div><label class="lbl" for="drop">{{ $t('get_off') }}</label>
            <select class="in" id="drop" name="drop_point_id" required>
                @foreach($info['drop_points'] as $p)
                    <option value="{{ $p['id'] }}" data-fare="{{ (int)$p['fare'] }}" @selected(old('drop_point_id') === $p['id'])>{{ $info['to'] }}, {{ $p['name'] }} · {{ $t->money($p['fare']) }}</option>
                @endforeach
            </select></div>
        <div class="xs muted">{{ $rules['free_bags'] === 1 ? $t('bags_included', ['n' => 1]) : $t('bags_included_n', ['n' => $rules['free_bags']]) }}</div>

        @if($askIdNumber)
            <div><label class="lbl" for="idn">{{ $t('id_number') }}</label><input class="in" id="idn" name="booker_id_number" required minlength="5" maxlength="40" value="{{ old('booker_id_number') }}"><span class="xs muted">{{ $t('id_number_hint') }}</span></div>
        @endif

        @if($channels)
        <fieldset style="border:0;padding:0;margin:4px 0 0" class="col">
            <legend class="b" style="font-size:14px;margin-bottom:8px">{{ $t('updates_on') }}</legend>
            @if(in_array('whatsapp', $channels, true))
            <label class="opt"><input type="radio" name="notify" value="whatsapp" @checked(old('notify', $channels[0]) === 'whatsapp')><span class="col" style="gap:1px"><span class="sm b">{{ $t('whatsapp') }} · {{ $me->phone }}</span><span class="xs muted">{{ $t('whatsapp_hint') }}</span></span></label>
            @endif
            @if(in_array('sms', $channels, true))
            <label class="opt"><input type="radio" name="notify" value="sms" @checked(old('notify', $channels[0]) === 'sms')><span class="col" style="gap:1px"><span class="sm b">{{ $t('sms') }} · {{ $me->phone }}</span><span class="xs muted">{{ $t('sms_hint') }}</span></span></label>
            @endif
        </fieldset>
        @else
            <div class="xs muted">{{ $t('follow_here') }}</div>
        @endif

        <div class="row" style="justify-content:space-between;margin-top:6px"><span class="sm muted">{{ $t('total_for', ['n' => count($seats)]) }}</span><span class="b" style="font-size:19px" id="total"></span></div>
        <div class="xs muted">{{ $info['instant'] ? $t('instant_hint', ['pay' => $settings['pay_minutes']]) : $t('nothing_yet', ['answer' => $settings['answer_minutes'], 'pay' => $settings['pay_minutes']]) }}</div>
        <button class="btn" type="submit" id="send">{{ $t('send_request') }}</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var drop = document.getElementById('drop'), seats = {{ count($seats) }};
    function total() {
        var fare = parseInt(drop.options[drop.selectedIndex].dataset.fare || '0', 10) * seats;
        document.getElementById('total').textContent = fare.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }
    drop.addEventListener('change', total); total();
    document.getElementById('req').addEventListener('submit', function () { document.getElementById('send').disabled = true; });
})();
</script>
@endpush
