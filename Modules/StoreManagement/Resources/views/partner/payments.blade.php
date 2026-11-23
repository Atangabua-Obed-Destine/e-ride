@extends('storemanagement::partner.layout')

@section('title', translate('Mobile Money'))

@section('content')
    @php($own = $brand->takesOwnPayments())
    <h1 class="fs-4 fw-bold mb-1">{{ translate('Mobile Money') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">
        {{ $own ? translate('Customers pay straight into your Fapshi account: food, goods and delivery. Each week E-Ride sends a statement of what you owe it for its riders.')
                : translate('Customers pay E-Ride, and E-Ride pays you out. Connect your own Fapshi account to receive the money directly.') }}
    </p>
    @if(session('fapshi_error'))<div class="alert alert-danger" role="alert">{{ session('fapshi_error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @if($brand->statement_paused_at)
        <div class="alert alert-warning" role="alert">{{ translate('App orders are paused because an E-Ride statement is late. Pay it below and orders come back by themselves.') }}</div>
    @endif

    @if($isBranch)
        <div class="p-card p-3">{{ translate(key: 'Mobile Money is managed by the owner of {brand}.', replace: ['brand' => $brand->name]) }}</div>
    @else
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="p-card p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h2 class="fs-6 fw-bold m-0">{{ translate('Your Fapshi account') }}</h2>
                        @if($brand->fapshi_ok_at)
                            <span class="badge text-bg-success">{{ translate('Connected') }}</span>
                        @elseif($keys)
                            <span class="badge text-bg-warning">{{ translate('Not tested') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ translate('Not connected') }}</span>
                        @endif
                    </div>
                    @if(!$enabled)
                        <p class="text-muted mb-0" style="font-size:14px">{{ translate('E-Ride has not opened this yet. Customers pay E-Ride, and E-Ride pays you out.') }}</p>
                    @else
                        <ol class="ps-3 text-muted" style="font-size:13px">
                            <li>{{ translate('In your Fapshi dashboard, open your service and copy its API user and API key.') }}</li>
                            <li>{{ translate('Set the webhook to the address below, with a secret of your choice, and paste the same secret here.') }}</li>
                            <li>{{ translate('Save, then Test the connection. Switch on when it says Connected.') }}</li>
                        </ol>
                        <form method="POST" action="{{ route('partner.payments.keys') }}" class="d-grid gap-2" autocomplete="off">@csrf
                            <label class="form-label mb-0" for="f-user" style="font-size:13px">{{ translate('API user') }}</label>
                            <input id="f-user" name="api_user" class="form-control" maxlength="120" required value="{{ old('api_user', $keys['api_user'] ?? '') }}">
                            <label class="form-label mb-0" for="f-key" style="font-size:13px">{{ translate('API key') }}</label>
                            <input id="f-key" name="api_key" type="password" class="form-control" maxlength="200" placeholder="{{ $keys ? translate('Saved. Leave empty to keep it.') : '' }}">
                            <label class="form-label mb-0" for="f-secret" style="font-size:13px">{{ translate('Webhook secret') }}</label>
                            <input id="f-secret" name="webhook_secret" type="password" class="form-control" maxlength="200" placeholder="{{ !empty($keys['webhook_secret']) ? translate('Saved. Leave empty to keep it.') : '' }}">
                            <label class="form-label mb-0" for="f-mode" style="font-size:13px">{{ translate('Mode') }}</label>
                            <select id="f-mode" name="mode" class="form-select">
                                <option value="live" @selected(($keys['mode'] ?? 'live') === 'live')>{{ translate('Live (real money)') }}</option>
                                <option value="test" @selected(($keys['mode'] ?? '') === 'test')>{{ translate('Test (sandbox)') }}</option>
                            </select>
                            <button type="submit" class="btn btn-dark" style="border-radius:6px">{{ translate('Save') }}</button>
                        </form>
                        @if($webhookUrl)
                            <div class="mt-3" style="font-size:13px">
                                <div class="fw-semibold">{{ translate('Webhook address for Fapshi') }}</div>
                                <div class="input-group input-group-sm mt-1">
                                    <input class="form-control" value="{{ $webhookUrl }}" readonly id="wh" aria-label="{{ translate('Webhook address for Fapshi') }}">
                                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('wh').value); window.partnerToast(@js(translate('Copied')))">{{ translate('Copy') }}</button>
                                </div>
                            </div>
                        @endif
                        @if($keys)
                            <form method="POST" action="{{ route('partner.payments.test') }}" class="mt-3">@csrf
                                <button type="submit" class="btn btn-outline-primary w-100" style="border-radius:6px">{{ translate('Test the connection') }}</button>
                            </form>
                        @endif
                        @if($brand->fapshi_ok_at)
                            <form method="POST" action="{{ route('partner.payments.toggle') }}" class="mt-3 d-flex align-items-center gap-2">@csrf
                                <input type="hidden" name="on" value="{{ $brand->own_payments ? 0 : 1 }}">
                                <div class="flex-grow-1" style="font-size:14px">
                                    <div class="fw-semibold">{{ translate('Customers pay my Fapshi') }}</div>
                                    <div class="text-muted" style="font-size:12px">{{ $brand->own_payments ? translate('On: from the next order, the money goes straight to you.') : translate('Off: customers pay E-Ride, and E-Ride pays you out.') }}</div>
                                </div>
                                <button type="submit" class="btn {{ $brand->own_payments ? 'btn-outline-danger' : 'btn-success' }}" style="border-radius:6px">{{ $brand->own_payments ? translate('Switch off') : translate('Switch on') }}</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <div class="col-lg-6">
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-1">{{ translate('E-Ride statements') }}</h2>
                    <p class="text-muted" style="font-size:13px">{{ translate(key: 'Every Monday: what you owe E-Ride for the orders of the past week paid into your account (delivery fees of E-Ride riders, resellers). App orders pause after {days} days late.', replace: ['days' => $pauseDays]) }}</p>
                    @forelse($statements as $st)
                        <div class="d-flex align-items-center gap-2 py-2 border-top" style="font-size:14px">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $st->period_from->format('d M') }} – {{ $st->period_to->format('d M Y') }}</div>
                                <div class="text-muted" style="font-size:12px">
                                    {{ translate(key: '{n} orders', replace: ['n' => $st->orders_count]) }} · {{ translate('Delivery fees') }} {{ getCurrencyFormat($st->delivery_fees) }}@if($st->other > 0) · {{ translate('Other') }} {{ getCurrencyFormat($st->other) }}@endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">{{ getCurrencyFormat($st->amount) }}</div>
                                @if($st->status === \Modules\StoreManagement\Entities\StoreStatement::PAID)
                                    <span class="badge text-bg-success">{{ translate('Paid') }}</span>
                                @else
                                    <form method="POST" action="{{ route('partner.payments.statement.pay', $st->id) }}" class="m-0">@csrf
                                        <button type="submit" class="btn btn-sm {{ $st->isLate() ? 'btn-danger' : 'btn-primary' }}" style="border-radius:6px">{{ translate('Pay by Mobile Money') }}</button>
                                    </form>
                                    <div class="text-muted" style="font-size:11px">{{ translate(key: 'Due {date}', replace: ['date' => $st->due_at->format('d M')]) }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0" style="font-size:14px">{{ translate('No statement yet.') }}</p>
                    @endforelse
                </div>

                <div class="p-card p-3">
                    <h2 class="fs-6 fw-bold mb-1">{{ translate('Money to send back to customers') }}</h2>
                    <p class="text-muted" style="font-size:13px">{{ translate('Orders paid into your account that were cancelled, or paid twice. Send the amount back by Mobile Money, then write the reference here.') }}</p>
                    @forelse($sendBack as $o)
                        <form method="POST" action="{{ route('partner.payments.sent-back', $o->id) }}" class="d-flex flex-wrap align-items-center gap-2 py-2 border-top" style="font-size:14px">@csrf
                            <div class="flex-grow-1" style="min-width:160px">
                                <div class="fw-semibold">#{{ $o->ref_id }} · {{ getCurrencyFormat($o->shop_refund_due) }}</div>
                                <div class="text-muted" style="font-size:12px">{{ $o->customer?->first_name }} · {{ $o->customer?->phone }}</div>
                            </div>
                            <input name="reference" class="form-control form-control-sm" style="max-width:160px" maxlength="80" required placeholder="{{ translate('Reference') }}" aria-label="{{ translate('Reference') }}">
                            <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius:6px">{{ translate('Sent') }}</button>
                        </form>
                    @empty
                        <p class="text-muted mb-0" style="font-size:14px">{{ translate('Nothing to send back.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endsection
