@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('E-Ride Market') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Items go live at once. This queue shows what the filters stopped and what buyers reported, scams first. Buyers and sellers arrange payment themselves: E-Ride holds no money here.') }}</p>
                </div>
                @if(!$settings['enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings') }}</span>
                @endif
            </div>
            @include('marketmanagement::admin._nav', ['active' => 'moderation'])

            <div class="row g-2 mb-3">
                @foreach([['Open reports', $stats['open']], ['Stopped by a banned word', $stats['blocked']], ['Hidden while checked', $stats['hidden']], ['Live items', $stats['live']], ['Open shops', $stats['shops']]] as [$label, $value])
                    <div class="col-6 col-md"><div class="card h-100"><div class="card-body p-3">
                        <div class="fs-12 text-muted">{{ translate($label) }}</div>
                        <div class="fs-20 fw-bold">{{ $value }}</div>
                    </div></div></div>
                @endforeach
            </div>

            <ul class="nav nav--tabs p-1 rounded bg-white mb-3">
                @foreach(\Modules\MarketManagement\Http\Controllers\Web\Admin\MarketAdminController::TABS as $key => $label)
                    <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.market.moderation', ['tab' => $key]) }}">{{ translate($label) }}</a></li>
                @endforeach
            </ul>

            <div class="card mb-3"><div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle fs-13">
                        <thead class="table-light"><tr>
                            <th>{{ translate('Item') }}</th><th>{{ translate('Shop') }}</th><th>{{ translate('Why it is here') }}</th><th>{{ translate('State') }}</th><th class="text-end">{{ translate('Decide') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse($items as $item)
                            @php($itemReports = $reports->get($item->id, collect()))
                            @php($cover = $item->cover())
                            <tr>
                                <td style="min-width: 220px">
                                    <div class="d-flex gap-2 align-items-center">
                                        @if($cover)<img src="{{ $cover->url() }}" alt="" width="44" height="44" class="rounded" style="object-fit: cover">@endif
                                        <div>
                                            <a href="{{ \Modules\MarketManagement\Lib\MarketLinks::item($item) }}" target="_blank" rel="noopener" class="fw-semibold">{{ $item->title }}</a>
                                            <div class="fs-11 text-muted">{{ translate($item->condition) }} · {{ $item->price !== null ? set_currency_symbol($item->price) : translate('Ask the seller') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $item->shop?->name }}
                                    <div class="fs-11 text-muted">{{ $item->shop?->isVerified() ? translate('Verified') : translate('Not verified') }} · {{ trim($item->shop?->owner?->first_name . ' ' . $item->shop?->owner?->last_name) }}</div></td>
                                <td style="max-width: 320px">
                                    @if($item->blocked_word)<div><b>{{ translate('Banned word') }}:</b> "{{ $item->blocked_word }}"</div>@endif
                                    @foreach($itemReports->take(3) as $report)
                                        <div class="{{ $report->reason === 'scam' ? 'text-danger fw-semibold' : '' }}">{{ translate('market_reason_' . $report->reason) }}@if($report->note) <span class="text-muted fw-normal">"{{ \Illuminate\Support\Str::limit($report->note, 90) }}"</span>@endif</div>
                                    @endforeach
                                    @if($itemReports->count() > 3)<div class="fs-11 text-muted">+{{ $itemReports->count() - 3 }}</div>@endif
                                </td>
                                <td><span class="badge badge-{{ in_array($item->status, ['live', 'sold_out']) ? 'success' : ($item->status === 'hidden' ? 'warning' : 'danger') }}">{{ translate('market_status_' . $item->status) }}</span></td>
                                <td class="text-end text-nowrap">
                                    @can('place_edit')
                                        <form action="{{ route('admin.market.decide', $item->id) }}" method="POST" class="d-inline-flex gap-1 flex-wrap justify-content-end">@csrf
                                            <button name="decision" value="kept" class="btn btn-sm btn-outline-secondary">{{ $item->status === 'blocked' ? translate('Allow') : translate('Keep') }}</button>
                                            <button name="decision" value="hidden" class="btn btn-sm btn-outline-secondary">{{ translate('Hide') }}</button>
                                            <button name="decision" value="removed" class="btn btn-sm btn-outline-danger">{{ translate('Remove') }}</button>
                                            <button name="decision" value="closed" class="btn btn-sm btn-danger" onclick="return confirm('{{ translate('Close this shop? All its items disappear and its owner cannot open another.') }}')">{{ translate('Close shop') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('Nothing waiting.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $items->links() }}
            </div></div>

            @if($shopReports->isNotEmpty())
                <div class="card"><div class="card-body">
                    <h5 class="mb-3">{{ translate('Reports about whole shops') }}</h5>
                    <table class="table table-borderless align-middle fs-13 mb-0">
                        @foreach($shopReports as $report)
                            <tr>
                                <td>{{ $report->shop?->name }}</td>
                                <td class="{{ $report->reason === 'scam' ? 'text-danger fw-semibold' : '' }}">{{ translate('market_reason_' . $report->reason) }} @if($report->note)<span class="text-muted fw-normal">"{{ \Illuminate\Support\Str::limit($report->note, 90) }}"</span>@endif</td>
                                <td class="text-end text-nowrap">
                                    @can('place_edit')
                                        <form action="{{ route('admin.market.decide-shop', $report->shop_id) }}" method="POST" class="d-inline-flex gap-1">@csrf
                                            <button name="decision" value="kept" class="btn btn-sm btn-outline-secondary">{{ translate('Keep') }}</button>
                                            <button name="decision" value="closed" class="btn btn-sm btn-danger" onclick="return confirm('{{ translate('Close this shop? All its items disappear and its owner cannot open another.') }}')">{{ translate('Close shop') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div></div>
            @endif
        </div>
    </div>
@endsection
