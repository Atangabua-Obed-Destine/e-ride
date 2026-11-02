@extends('adminmodule::layouts.master')

@section('title', translate('Partner_Stores'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Partner_Stores') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Restaurants and shops customers can order from. Each store has its own partner login at') }} <a href="{{ route('partner.login') }}" target="_blank">{{ route('partner.login') }}</a></p>
                </div>
                @can('store_add')
                    <a href="{{ route('admin.store.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ translate('Add_store') }}</a>
                @endcan
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    @foreach(['all' => translate('all'), 'active' => translate('active'), 'inactive' => translate('inactive')] as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $filters['status'] === $key ? 'active' : '' }}" href="{{ route('admin.store.index', ['status' => $key, 'search' => $filters['search']]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
                <form action="{{ route('admin.store.index') }}" method="GET" class="search-form search-form_style-two">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <div class="input-group search-form__input_group">
                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" value="{{ $filters['search'] }}" class="theme-input-style search-form__input" placeholder="{{ translate('Search_by_name_or_phone') }}">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                </form>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th>{{ translate('Store') }}</th>
                                <th>{{ translate('Zone') }}</th>
                                <th>{{ translate('Partner_login') }}</th>
                                <th class="text-center">{{ translate('Menu_items') }}</th>
                                <th class="text-center">{{ translate('Delivered') }}</th>
                                <th class="text-center">{{ translate('Now') }}</th>
                                <th class="text-center">{{ translate('Active') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($stores as $store)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $store->logoUrl() ?? dynamicAsset('public/assets/admin-module/img/media/upload-file.png') }}" width="40" height="40" class="rounded object-fit-cover" alt="">
                                            <div>
                                                <div class="fw-semibold">{{ $store->name }}</div>
                                                <div class="fs-12 text-muted">{{ translate($store->type) }} · {{ $store->phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($store->zone)
                                            {{ $store->zone->name }}
                                        @else
                                            <span class="badge bg-danger">{{ translate('Outside_all_zones') }}</span>
                                        @endif
                                    </td>
                                    <td class="fs-12">{{ $store->owner?->email }}</td>
                                    <td class="text-center">{{ $store->items_count }}</td>
                                    <td class="text-center">{{ $store->delivered_orders_count }}</td>
                                    <td class="text-center">
                                        @if($store->isOpenNow())
                                            <span class="badge bg-success">{{ translate('Open') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ translate('Closed') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('admin.store.status', $store) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="is_active" value="{{ $store->is_active ? 0 : 1 }}">
                                            <label class="switcher mx-auto">
                                                <input class="switcher_input" type="checkbox" onchange="this.form.submit()" {{ $store->is_active ? 'checked' : '' }} @cannot('store_edit') disabled @endcannot>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            @can('store_edit')
                                                <a href="{{ route('admin.store.menu', $store) }}" class="btn btn-outline-primary btn-action" title="{{ translate('Menu') }}"><i class="bi bi-card-list"></i></a>
                                                <a href="{{ route('admin.store.edit', $store) }}" class="btn btn-outline-info btn-action" title="{{ translate('edit') }}"><i class="bi bi-pencil-fill"></i></a>
                                            @endcan
                                            <a href="{{ route('admin.store.payouts', $store) }}" class="btn btn-outline-success btn-action" title="{{ translate('Payouts') }}"><i class="bi bi-cash-coin"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-5">
                                            <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="" width="100">
                                            <p class="text-center mb-0">{{ translate('No stores yet. Add ROMZ or Candy Shop to start.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $stores->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
