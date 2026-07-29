@extends('adminmodule::layouts.master')

@section('title', translate('Customer_Wallet'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            @can('user_add')
                <div class="d-flex justify-content-between gap-3 align-items-center mb-4">
                    <h2 class="fs-22 text-capitalize">{{translate('add_fund')}}</h2>
                </div>

                <div class=" gap-3 align-items-center mb-4">

                    <div class="card">
                        <div class="card-body">
                            <form action="{{route('admin.customer.wallet.store')}}" method="post"
                                  enctype="multipart/form-data"
                                  id="formSubmit">
                                @csrf
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="customer" class="mb-2">{{translate('customer')}}</label>
                                            <select name="customer_id" id="customer" class="js-select-customer cmn_focus"
                                                    required tabindex="1">
                                                <option  selected disabled>-- {{translate('select_Customer')}}
                                                    --
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="amount" class="mb-2">{{translate('amount')}}</label>
                                            <input type="number" name="amount" value="{{old('amount')}}" id="amount"
                                                   class="form-control" step=".01" max="9999999" placeholder="Ex: 100"
                                                   required tabindex="2">
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="mb-4">
                                            <label for="points4" class="mb-2">{{translate('reference')}}
                                                ({{translate('optional')}})</label>
                                            <div class="character-count">
                                            <textarea name="reference" class="form-control character-count-field" tabindex="3"
                                                      maxlength="800" data-max-character="800" id="reference" cols="30"
                                                      rows="4">{{old('reference')}}</textarea>
                                                <span>{{translate('0/800')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-3 mt-3">
                                        <button class="btn btn-primary cmn_focus" id="addWallet"
                                                type="submit" tabindex="4">{{translate('save')}}</button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            @endcan
            <div class="d-flex justify-content-between gap-3 align-items-center mb-4">
                <h2 class="fs-22 text-capitalize">{{translate('wallet_transaction_report')}}</h2>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-primary text-uppercase mb-4">{{translate('filter_data')}}</h6>
                    <form method="GET" action="{{ url()->full() }}" >
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="mb-4">
                                    <label for="customer1" class="mb-2">{{translate('customer')}}</label>
                                    <select id="customer1" class="js-select-customer_2 cmn_focus" name="user_id" tabindex="5">
                                        <option selected disabled>-- {{translate('select_Customer')}} --</option>
                                        @if(request()->get('user_id') && request()->get('user_id') == 'all')
                                            <option value="all" selected>{{ translate('All Customer') }}</option>
                                        @endif
                                        @if (request()->get('user_id') && $customer_info = Modules\UserManagement\Entities\User::find(request()->get('user_id')))
                                            <option value="{{$customer_info->id}}"
                                                    selected>{{$customer_info?->first_name.' '.$customer_info?->last_name}}
                                                ({{$customer_info->phone}})
                                            </option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="mb-4">
                                    <label for="dateRange" class="mb-2">{{translate('date_range')}}</label>
                                    <select name="data" id="dateRange" class="js-select cmn_focus" tabindex="5">
                                        <option value="0" disabled selected>{{translate('Date_Range')}}</option>
                                        <option
                                            value="{{ALL_TIME}}" {{!empty(request()->has('data')) && request()->input('data')==ALL_TIME?'selected':''}}>{{translate(ALL_TIME)}}</option>
                                        <option
                                            value="{{THIS_WEEK}}" {{!empty(request()->has('data')) && request()->input('data')==THIS_WEEK?'selected':''}}>{{translate(THIS_WEEK)}}</option>
                                        <option
                                            value="{{LAST_WEEK}}" {{!empty(request()->has('data')) && request()->input('data')==LAST_WEEK?'selected':''}}>{{translate(LAST_WEEK)}}</option>
                                        <option
                                            value="{{THIS_MONTH}}" {{!empty(request()->has('data')) && request()->input('data')==THIS_MONTH?'selected':''}}>{{translate(THIS_MONTH)}}</option>
                                        <option
                                            value="{{LAST_MONTH}}" {{!empty(request()->has('data')) && request()->input('data')==LAST_MONTH?'selected':''}}>{{translate(LAST_MONTH)}}</option>
                                        <option
                                            value="{{CUSTOM_DATE}}" {{!empty(request()->has('data')) && request()->input('data')==CUSTOM_DATE?'selected':''}}>{{translate(CUSTOM_DATE)}}</option>
                                    </select>
                                </div>
                            </div>

                            <div
                                class="col-sm-6 {{!empty(request()->has('data')) && request()->input('data')==CUSTOM_DATE?'':'d-none'}}"
                                id="fromFilterDiv">
                                <label for="from">{{translate('From')}}</label>
                                <input type="date" class="form-control" id="from" name="start"
                                       value="{{!empty(request()->has('data')) && request()->input('data')==CUSTOM_DATE ?request()->input('start'):''}}" tabindex="6">
                            </div>
                            <div
                                class="col-sm-6 {{!empty(request()->has('data')) && request()->input('data')==CUSTOM_DATE?'':'d-none'}}"
                                id="toFilterDiv">
                                <label for="to">{{translate('To')}}</label>
                                <input type="date" class="form-control" id="to" name="end"
                                       value="{{!empty(request()->has('data')) && request()->input('data')==CUSTOM_DATE ?request()->input('end'):''}}" tabindex="7">
                            </div>

                        </div>
                        <div class="d-flex justify-content-end gap-3 mt-3">
                            <button class="btn btn-primary cmn_focus date-range-submit" tabindex="8"
                                    type="submit">{{translate('filter')}}</button>
                        </div>
                    </form>

                </div>
            </div>

            <div class="tab-content mt-3">
                <div class="tab-pane fade active show" id="all-tab-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                <form action="javascript:;" method="GET" class="search-form search-form_style-two">
                                    <div class="input-group search-form__input_group">
                                            <span class="search-form__icon">
                                                <i class="bi bi-search"></i>
                                            </span>
                                        <input type="search" name="search" value="{{ request()->get('search') }}"
                                               id="search"
                                               class="theme-input-style search-form__input"
                                               placeholder="{{translate('Search_here_by_Transaction_Id')}}" tabindex="9">
                                    </div>
                                    <button type="submit" class="btn btn-primary search-submit" tabindex="10"
                                            data-url="{{ url()->full() }}">{{translate('search')}}</button>
                                </form>
                                @can('user_export')
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-outline-primary" tabindex="11"
                                                    data-bs-toggle="dropdown">
                                                <i class="bi bi-download"></i>
                                                {{translate('download')}}
                                                <i class="bi bi-caret-down-fill"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="{{ route('admin.customer.wallet.export', ['user_id' => request('user_id'), 'data' => request('data'), 'start' => request('start'), 'end' => request('end'), 'search' => request('search'), 'file' => 'excel']) }}">
                                                        {{ translate('excel') }}
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <div class="table-responsive mt-3">
                                <table class="table table-borderless align-middle table-hover text-nowrap">
                                    <thead class="table-light align-middle text-capitalize">
                                    <tr>
                                        <th class="sl">{{translate('SL')}}</th>
                                        <th class="text-center">{{translate('customer_name')}}</th>
                                        <th class="text-center">{{translate('customer_phone')}}</th>
                                        <th class="text-center">{{translate('transaction_id')}}</th>
                                        <th class="text-center">{{translate('reference')}}</th>
                                        <th class="text-center">{{translate('transaction_date')}}</th>
                                        <th class="text-center">{{translate('debit')}}</th>
                                        <th class="text-center">{{translate('credit')}}</th>
                                        <th class="text-center">{{translate('balance')}}</th>
                                        <th class="text-center">{{translate('added_bonus')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse ($transactions as $key => $transaction)
                                        <tr>
                                            <td class="sl">{{$key + $transactions->firstItem()}}</td>
                                            <td class="text-center">{{$transaction?->user ? $transaction?->user?->first_name .' ' . $transaction?->user?->last_name : "N/A"}}</td>
                                            <td class="text-center">{{$transaction?->user ? $transaction?->user?->phone : "N/A"}}</td>
                                            <td class="text-center">{{$transaction->id}}</td>
                                            <td class="text-center ">{{\Illuminate\Support\Str::limit($transaction->reference,30)}}</td>
                                            <td class="text-center">{{$transaction->created_at}}</td>
                                            <td class="text-center">{{set_currency_symbol($transaction->debit)}}</td>
                                            <td class="text-center">{{set_currency_symbol($transaction->credit)}}</td>
                                            <td class="text-center">{{set_currency_symbol($transaction->balance)}}</td>
                                            <td class="text-center">{{set_currency_symbol($transaction->added_bonus)}}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12">
                                                <div
                                                    class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                                    <img
                                                        src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                        alt="" width="100">
                                                    <p class="text-center">{{translate('no_data_available')}}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse

                                    </tbody>
                                </table>
                            </div>

                            <div
                                class="table-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2">

                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-end gap-3 gap-sm-4">
                                    {!! $transactions->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/js/user-management/customer/wallet/index.js')}}"></script>

    <script>
        "use strict";


        $('.js-select-customer').select2({
            placeholder: "Select Customer",
            allowClear: false,
            ajax: {
                url: '{{route('admin.customer.get-all-ajax')}}',
                data: function (params) {
                    return {
                        search: params.term, // search term
                        page: params.page,
                        all_customer: 1
                    };
                },
                processResults: function (data) {

                    return {
                        results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });

        $('.js-select-customer_2').select2({
            placeholder: "Select Customer",
            allowClear: false,
            ajax: {
                url: '{{route('admin.customer.get-all-ajax')}}',
                data: function (params) {
                    return {
                        search: params.term, // search term
                        page: params.page,
                        all_customer: 1
                    };
                },
                processResults: function (data) {

                    return {
                        results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });

        // Select a specific customer if the 'customer_id' query parameter is present in the URL
        let customerId = @json(request('user_id')); // convert PHP array to JavaScript object
        if (customerId) {
            $('.js-select-customer_2').val(customerId).trigger('change');
        }
    </script>
@endpush

