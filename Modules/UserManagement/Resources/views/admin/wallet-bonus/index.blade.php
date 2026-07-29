@extends('adminmodule::layouts.master')

@section('title', translate('Wallet Bonus'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-20 text-capitalize">{{ translate('wallet_bonus') }}</h2>

            <div class="card mb-3">
                <form action="{{ route('admin.wallet-bonus.store') }}" method="post">
                    @csrf
                    <div class="card-body">
                        <h3 class="mb-20 text-dark">{{ translate('wallet_Bonus_Setup') }}</h3>
                        <div class="row g-lg-4 g-3">
                            @include('usermanagement::admin.wallet-bonus.partial._form')
                            <div class="col-12 d-flex justify-content-end align-items-end mt-3 gap-2">
                                <button type="reset" class="btn btn-secondary"
                                   tabindex="9">{{ translate('Reset') }}</button>
                                <button type="submit" class="btn btn-primary"
                                        tabindex="10">{{ translate('Submit') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <h2 class="fs-22 mb-3 text-capitalize">{{ translate('Bonus_List') }}</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="" class="search-form search-form_style-two" method="GET">
                            <input type="hidden" name="" value="" tabindex="11">
                            <div class="input-group search-form__input_group cmn_focus">
                                <span class="search-form__icon">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" class="theme-input-style search-form__input"
                                       value="{{request()->get('search')}}" name="search" id="search"
                                       placeholder="{{translate('search_here_by_title')}}" tabindex="11">
                            </div>
                            <button type="submit" class="btn btn-primary cmn_focus"
                                    tabindex="12">{{ translate('search') }}</button>
                        </form>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary"
                                        data-bs-toggle="dropdown" tabindex="13">
                                    <i class="bi bi-download"></i>
                                    {{ translate('download') }}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li><a class="dropdown-item"
                                           href="{{route('admin.wallet-bonus.export')}}?search={{request()->get('search') ?? ""}}&&file=excel">{{translate('excel')}}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-borderless mb-0 align-middle table-hover text-nowrap">
                            <thead class="table-light align-middle text-capitalize">
                            <tr>
                                <th class="fw-semibold fs-14">{{ translate('SL') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Bonus Title') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Bonus Info') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Bonus Amount') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Started On') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Expires On') }}</th>
                                <th class="fw-semibold fs-14">{{ translate('Status') }}</th>
                                <th class="fw-semibold text-center fs-14">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($walletBonuses as $key => $walletBonus)
                                <tr>
                                    <td class="fs-14 fw-semibold text-dark">{{ $walletBonuses->firstItem() + $key }}</td>
                                    <td>
                                        <div class="max-w-240 min-w-170 fs-14 text-wrap">
                                            {{ $walletBonus->name }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <div class="max-w-240 min-w-170 fs-14 text-wrap">
                                                {{ translate('Minimum Add Amount') }} -
                                                <span>{{ set_currency_symbol($walletBonus->min_add_amount) }}</span>
                                            </div>
                                            @if($walletBonus->max_bonus_amount > 0)
                                                <div class="max-w-240 min-w-170 fs-14 text-wrap">
                                                    {{ translate('Maximum Bonus ') }}-
                                                    <span>{{ set_currency_symbol($walletBonus->max_bonus_amount) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="fs-14">{{ setSymbol($walletBonus->amount_type, $walletBonus->bonus_amount) }}</td>
                                    <td class="fs-14">{{ $walletBonus->start_date->format('d F Y') }}</td>
                                    <td class="fs-14">{{ $walletBonus->end_date->format('d F Y') }}</td>
                                    <td class="">
                                        <label class="switcher">
                                            <input class="switcher_input wallet_bonus_status_change"
                                                   type="checkbox"
                                                   id="{{ $walletBonus->id }}"
                                                   data-url="{{ route('admin.wallet-bonus.status') }}"
                                                   data-icon="{{ $walletBonus->is_active == 1 ? dynamicAsset('public/assets/admin-module/img/svg/bonus-off.svg') : dynamicAsset('public/assets/admin-module/img/svg/bonus-on.svg')}}"
                                                   data-title="{{$walletBonus->is_active == 1 ? translate('Are you sure to turn off the bonus') : translate('Are you sure to turn on the bonus') }}?"
                                                   data-sub-title="{{$walletBonus->is_active == 1 ? translate('When you turn off the bonus, this bonus offer will be hidden for all customers .') : translate('When you turn on the bonus, customer will receive the bonus after add fund to their wallet .') }}"
                                                   data-confirm-btn="{{ translate('Yes') }}"
                                                   data-cancel-btn="{{ translate('no') }}"
                                                   data-action-button-class="{{ $walletBonus->is_active == 1  ? 'btn-danger' : 'btn-primary' }}"
                                                {{ $walletBonus->is_active == 1 ? "checked": ""  }}
                                            >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td class="action text-center">
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <a href="javascript:"
                                               class="btn btn-outline-primary btn-action edit-wallet-bonus"
                                               data-route="{{ route('admin.wallet-bonus.edit', $walletBonus->id) }}"
                                               >
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>

                                            <a
                                                data-url="{{ route('admin.wallet-bonus.delete', ['id'=>$walletBonus->id]) }}"
                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                data-title="{{ translate('Do you want to delete this bonus')."?" }}"
                                                data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                data-confirm-btn="{{translate("Yes, Delete")}}"
                                                data-cancel-btn="{{translate("Not Now")}}"
                                                class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-button"
                                                data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14">
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
                    <div class="d-flex justify-content-end">
                        {!! $walletBonuses->withQueryString()->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit_wallet_bonus_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="card-body">
                    <h3 class="mb-20 text-dark">{{ translate('Edit_Wallet_Bonus') }}</h3>
                    <div id="edit-wallet-bonus-data">

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function () {
            initAmountTypeSection($('#amount_type'), $('#maximum_bonus'));

            $(document).on('click', '.edit-wallet-bonus', function () {
                $('#edit_wallet_bonus_modal').modal('show');
                $('#edit-wallet-bonus-data').empty();
                let url = $(this).data('route');
                $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        $('#edit-wallet-bonus-data').html(data);
                        initModalContent($('#edit_wallet_bonus_modal'));
                        manageDigitAfterDecimal();
                    }
                })
            });

            $(document).on('submit', '#edit-wallet-bonus-form', function (e) {
                e.preventDefault();
                let $form = $(this);
                let url = $form.data('route');
                let data = $form.serialize();
                $.ajax({
                    url: url,
                    method: 'PUT',
                    data: data,
                    dataType: 'json',
                    success: function (data) {
                        if (data?.errors) {
                            toastr.error(data.errors[0].message);
                        } else {
                            toastr.success(data);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    }
                })
            })
        });

        function initModalContent(modal) {
            modal.find(".character-count-field").each(function () {
                initialCharacterCount($(this));
            });
            modal.find('.character-count-field').on('keyup change', function () {
                initialCharacterCount($(this));
            });

            const amountType = modal.find('#amount_type');
            const maxBonus = modal.find('#maximum_bonus');
            initAmountTypeSection(amountType, maxBonus);
        }

        function initAmountTypeSection(amountType, maxBonus) {
            if (!amountType.length || !maxBonus.length) return;
            amountTypeCheck(amountType, maxBonus);

            amountType.off('change').on('change', function () {
                amountTypeCheck(amountType, maxBonus);
            });
        }

        function initialCharacterCount(item) {
            let str = item.val();
            let maxCharacterCount = item.data('max-character');
            let characterCount = str.length;
            if (characterCount > maxCharacterCount) {
                item.val(str.substring(0, maxCharacterCount));
                characterCount = maxCharacterCount;
            }
            item.closest('.character-count').find('span').text(characterCount + '/' + maxCharacterCount);
        }

        function amountTypeCheck(amountType, maxBonus) {
            if (amountType.val() == 'amount') {
                maxBonus.attr({ disabled: true }).val('');
                $("#bonus_amount_label").text("{{translate('Bonus Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                $("#bonus_amount").attr("placeholder", "Ex: 500");
            } else {
                maxBonus.removeAttr("disabled");
                $("#bonus_amount_label").text("{{translate('Bonus Percent ')}}(%)");
                $("#bonus_amount").attr("placeholder", "Ex: 50%");
            }
        }

        function manageDigitAfterDecimal() {
            document.querySelectorAll('input[data-decimal]').forEach(input => {
                input.addEventListener('input', function() {
                    let decimal = this.dataset.decimal;
                    this.value = this.value.replace(/[^0-9.]/g,'');
                    let parts = this.value.split('.');
                    if(parts.length > 2){
                        this.value = parts[0] + '.' + parts[1];
                    }
                    if(parts[1] && parts[1].length > decimal){
                        this.value = parts[0] + '.' + parts[1].slice(0, decimal);
                    }
                });
            });
        }
    </script>

    <script>
        setEndDateFromStartDate('start_date', 'end_date');
    </script>

@endpush
