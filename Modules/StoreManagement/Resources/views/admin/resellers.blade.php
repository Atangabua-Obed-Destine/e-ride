@extends('adminmodule::layouts.master')

@section('title', translate('Resellers'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 1100px">
            <h2 class="fs-22 mb-1">{{ translate('Resellers') }}</h2>
            <p class="fs-12 text-muted mb-3">{{ translate('People who share partner stores\' links with their code. They earn the store\'s rate on the items of the orders they bring, once delivered. Send what is owed by Mobile Money, then record it here.') }}
                <a href="{{ route('admin.store-setting.edit') }}">{{ translate('Settings') }}</a></p>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="card mb-3"><div class="card-body">
                <h5 class="mb-2">{{ translate('To pay') }}</h5>
                @if($owed->isEmpty())
                    <p class="fs-12 text-muted mb-0">{{ translate('Nothing is owed to resellers.') }}</p>
                @else
                    <div class="table-responsive"><table class="table table-borderless align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th>{{ translate('Reseller') }}</th><th>{{ translate('Mobile Money') }}</th><th>{{ translate('Orders') }}</th><th>{{ translate('Owed') }}</th><th></th>
                        </tr></thead>
                        <tbody>
                        @foreach($owed as $row)
                            @php($r = $row['reseller'])
                            <tr>
                                <td>{{ trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? '')) }}<div class="fs-11 text-muted">{{ translate('Code') }} {{ $r->code }}</div></td>
                                <td>{{ $r->momo_phone }}<div class="fs-11 text-muted">{{ $r->momo_name }}</div></td>
                                <td>{{ $row['orders'] }}</td>
                                <td class="fw-bold">{{ getCurrencyFormat($row['owed']) }}
                                    @if($row['owed'] < $minPayout)<div class="fs-11 text-muted">{{ translate('Below the minimum') }}</div>@endif</td>
                                <td>
                                    <form action="{{ route('admin.store-reseller.pay', $r->id) }}" method="POST" class="d-flex gap-2"
                                          onsubmit="return confirm('{{ translate('Record this payment as sent?') }}')">@csrf
                                        <input type="text" name="reference" class="form-control form-control-sm" placeholder="{{ translate('Transaction reference') }}" style="max-width: 180px">
                                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">{{ translate('Paid') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div></div>

            <div class="row g-3">
                <div class="col-lg-6"><div class="card h-100"><div class="card-body">
                    <h5 class="mb-2">{{ translate('Stores that pay resellers') }}</h5>
                    @forelse($stores as $s)
                        <div class="d-flex justify-content-between border-bottom py-1 fs-13"><span>{{ $s->name }}</span><span class="fw-semibold">{{ rtrim(rtrim(number_format((float)$s->reseller_pct, 2), '0'), '.') }}%</span></div>
                    @empty
                        <p class="fs-12 text-muted mb-0">{{ translate('No store pays resellers yet. A store sets its rate in the partner portal, on its store page.') }}</p>
                    @endforelse
                </div></div></div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-body">
                    <h5 class="mb-2">{{ translate('Payments made') }}</h5>
                    @forelse($payouts as $p)
                        <div class="d-flex justify-content-between border-bottom py-1 fs-13">
                            <span>{{ $p->paid_at?->format('d M Y') }} · {{ $p->reseller?->momo_name }} · {{ $p->orders }} {{ translate('orders') }}@if($p->reference) · {{ $p->reference }}@endif</span>
                            <span class="fw-semibold">{{ getCurrencyFormat($p->amount) }}</span>
                        </div>
                    @empty
                        <p class="fs-12 text-muted mb-0">{{ translate('No payment yet.') }}</p>
                    @endforelse
                </div></div></div>
            </div>

            <div class="card mt-3"><div class="card-body">
                <h5 class="mb-2">{{ translate('All resellers') }}</h5>
                @forelse($resellers as $r)
                    <div class="d-flex flex-wrap align-items-center gap-2 border-bottom py-2 fs-13">
                        <span class="flex-grow-1">{{ trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? '')) }} · {{ $r->code }} · {{ $r->momo_phone }} · {{ $r->taps }} {{ translate('taps') }}
                            @if($r->status === 'paused')<span class="badge bg-warning text-dark">{{ translate('Paused') }}</span>@endif</span>
                        <form action="{{ route('admin.store-reseller.status', $r->id) }}" method="POST" class="d-flex gap-2">@csrf
                            @if($r->status === 'active')
                                <input type="hidden" name="status" value="paused">
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ translate('Why') }}" style="max-width: 160px">
                                <button class="btn btn-sm btn-outline-danger">{{ translate('Pause') }}</button>
                            @else
                                <input type="hidden" name="status" value="active">
                                <button class="btn btn-sm btn-outline-primary">{{ translate('Allow again') }}</button>
                            @endif
                        </form>
                    </div>
                @empty
                    <p class="fs-12 text-muted mb-0">{{ translate('Nobody has joined yet. Customers join in the app, under Sell for shops.') }}</p>
                @endforelse
            </div></div>
        </div>
    </div>
@endsection
