@extends('busmanagement::partner.layout')

@section('title', translate('Payments'))

@section('content')
<div class="d-flex flex-wrap gap-3 align-items-start">
    <form method="POST" action="{{ route('bus-partner.payments.fapshi') }}" class="b-card p-3" style="flex:1 1 380px;min-width:0" autocomplete="off">
        @csrf
        <div class="d-flex align-items-center mb-2">
            <h1 class="b-h flex-grow-1">{{ translate('Your Fapshi account') }}</h1>
            @if($busAgency->fapshi_ok_at)
                <span class="b-tag ok"><i class="bi bi-check-lg"></i>{{ ($fapshi['mode'] ?? 'test') === 'live' ? translate('Connected (live)') : translate('Connected (test)') }}</span>
            @else
                <span class="b-tag warn">{{ translate('Not connected') }}</span>
            @endif
        </div>
        <p class="b-hint">{{ translate('Passengers pay straight into your own Fapshi account. E-Ride never holds your money. Online booking stays closed until this connection works.') }}</p>
        @if(session('fapshi_error'))<div class="alert alert-danger small">{{ session('fapshi_error') }}</div>@endif
        <div class="mb-2"><label class="form-label small fw-semibold" for="api_user">{{ translate('API user') }}</label><input id="api_user" name="api_user" class="form-control" required maxlength="120" value="{{ old('api_user', $fapshi['api_user'] ?? '') }}" autocomplete="off"></div>
        <div class="mb-2"><label class="form-label small fw-semibold" for="api_key">{{ translate('API key') }}</label><input id="api_key" type="password" name="api_key" class="form-control" maxlength="200" autocomplete="new-password" placeholder="{{ !empty($fapshi['api_key']) ? translate('Saved. Type a new one to change it.') : '' }}"></div>
        <div class="mb-2"><label class="form-label small fw-semibold" for="webhook_secret">{{ translate('Webhook secret') }}</label><input id="webhook_secret" type="password" name="webhook_secret" class="form-control" maxlength="200" autocomplete="new-password" placeholder="{{ !empty($fapshi['webhook_secret']) ? translate('Saved. Type a new one to change it.') : '' }}"></div>
        <div class="mb-3"><span class="form-label small fw-semibold d-block">{{ translate('Mode') }}</span>
            <div class="seg"><input type="radio" name="mode" id="m_test" value="test" @checked(old('mode', $fapshi['mode'] ?? 'test') === 'test')><label for="m_test">{{ translate('Test (sandbox)') }}</label><input type="radio" name="mode" id="m_live" value="live" @checked(old('mode', $fapshi['mode'] ?? 'test') === 'live')><label for="m_live">{{ translate('Live') }}</label></div></div>
        <div class="rounded-3 p-2 mb-3" style="background:#f4f6f6">
            <div class="small fw-bold">{{ translate('Paste this address in your Fapshi dashboard, under Webhooks') }}</div>
            <div class="d-flex align-items-center gap-2"><code class="small text-break flex-grow-1" id="webhookUrl">{{ $webhookUrl }}</code><button type="button" class="btn btn-soft btn-sm" id="copyHook">{{ translate('Copy') }}</button></div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-dark">{{ translate('Save and test') }}</button>
            @if(!empty($fapshi['api_user']))<button type="button" class="btn btn-soft" id="testOnly">{{ translate('Test again') }}</button>@endif
            <span class="b-hint">{{ $busAgency->fapshi_ok_at ? translate('Last good test') . ': ' . $busAgency->fapshi_ok_at->diffForHumans() : '' }}</span>
        </div>
        <p class="b-hint mt-3 mb-0">{{ translate('Your keys are stored encrypted and only used to start and check the payments of your passengers. Only managers see this page.') }}</p>
    </form>

    <div style="flex:1.3 1 460px;min-width:0" class="d-flex flex-column gap-3">
        @if($paymentStatus === 'success')<div class="alert alert-success mb-0">{{ translate('Thank you. Your payment to E-Ride was received.') }}</div>@endif
        @if($paymentStatus === 'fail')<div class="alert alert-danger mb-0">{{ translate('The payment to E-Ride did not go through. Nothing was taken. Try again.') }}</div>@endif
        @if($refundsDue)
            <a href="{{ route('bus-partner.today') }}" class="alert alert-warning mb-0 text-decoration-none d-block">{{ translate(key: '{n} refund(s) to send to passengers. Open Today.', replace: ['n' => $refundsDue]) }}</a>
        @endif
        <div class="b-card p-3">
            <h2 class="b-h mb-1">{{ translate('E-Ride commission') }}</h2>
            <p class="b-hint">{{ translate(key: '{app}% of the fare on tickets sold in the E-Ride app, {counter}% on tickets sold at your counter. On the 1st of each month you get a statement listing every ticket. Pay it by day {day} from this page.',
                replace: ['app' => rtrim(rtrim(number_format($busAgency->commission_app_pct, 2), '0'), '.'), 'counter' => rtrim(rtrim(number_format($busAgency->commission_counter_pct, 2), '0'), '.'),
                    'day' => \Modules\BusManagement\Lib\BusSettings::all()['statement_due_day']]) }}</p>
            <div class="rounded-3 p-3" style="background:#f4f6f6">
                <div class="small fw-bold mb-1">{{ translate(key: '{month} so far', replace: ['month' => now()->translatedFormat('F')]) }}</div>
                <div class="d-flex flex-wrap gap-4">
                    <div><div class="b-hint">{{ translate('App tickets') }}</div><b>{{ $thisMonth['app']['tickets'] }}</b> · {{ getCurrencyFormat($thisMonth['app']['commission']) }}</div>
                    <div><div class="b-hint">{{ translate('Counter tickets') }}</div><b>{{ $thisMonth['counter']['tickets'] }}</b> · {{ getCurrencyFormat($thisMonth['counter']['commission']) }}</div>
                    <div><div class="b-hint">{{ translate('Commission') }}</div><b class="fs-5">{{ getCurrencyFormat($thisMonth['total']) }}</b></div>
                </div>
            </div>
            <p class="b-hint mt-2 mb-0">{{ translate('Refunded money does not count. Counted by the date of the departure.') }}</p>
        </div>
        <div class="b-card p-3">
            <h2 class="b-h mb-2">{{ translate('Statements') }}</h2>
            @forelse($statements as $s)
                @php $late = $s->status === 'due' && $s->due_on->lt(today()); @endphp
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-top">
                    <div class="flex-grow-1" style="min-width:150px"><b>{{ $s->month->translatedFormat('F Y') }}</b>
                        <div class="b-hint">{{ translate(key: '{app} app and {counter} counter tickets', replace: ['app' => $s->app_tickets, 'counter' => $s->counter_tickets]) }}</div></div>
                    <b>{{ getCurrencyFormat($s->total) }}</b>
                    @if($s->status === 'paid')
                        <span class="b-tag ok">{{ translate('Paid') }}</span>
                    @else
                        <span class="b-tag {{ $late ? 'bad' : 'warn' }}">{{ $late ? translate(key: 'Late since {date}', replace: ['date' => $s->due_on->translatedFormat('j M')]) : translate(key: 'Due {date}', replace: ['date' => $s->due_on->translatedFormat('j M')]) }}</span>
                    @endif
                    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.payments.statement', $s->id) }}">{{ translate('Details') }}</a>
                    @if($s->status === 'due')
                        @if($s->company_statement_id)
                            <span class="b-tag mute">{{ translate('Paid by your head office') }}</span>
                        @else
                        <form method="POST" action="{{ route('bus-partner.payments.statement.pay', $s->id) }}" class="m-0">@csrf<button class="btn btn-primary btn-sm">{{ translate('Pay with Mobile Money') }}</button></form>
                        @endif
                    @endif
                </div>
            @empty
                <div class="b-hint">{{ translate('Your first statement arrives on the 1st of next month.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
document.getElementById('copyHook').addEventListener('click', function () {
    navigator.clipboard.writeText(document.getElementById('webhookUrl').textContent.trim()).then(function () { busToast(@json(translate('Copied.'))); });
});
var t = document.getElementById('testOnly');
if (t) t.addEventListener('click', function () {
    t.disabled = true;
    busPost(@json(route('bus-partner.payments.test'))).then(function (d) { busToast(d.message); if (d.ok) setTimeout(function () { location.reload(); }, 1200); })
        .catch(function () { busToast(@json(translate('Could not check. Try again.'))); }).finally(function () { t.disabled = false; });
});
</script>
@endpush
