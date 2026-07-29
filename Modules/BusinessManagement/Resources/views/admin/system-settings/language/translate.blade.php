@extends('adminmodule::layouts.master')

@section('title', translate('Languages'))


@section('content')
    <div class="content container-fluid">
        <h2 class="fs-22 mb-4 text-capitalize">{{translate('language_content_table')}}</h2>

        <div class="row __mt-20">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                            <div>
                                <form action="javascript:;" method="GET"
                                      class="search-form search-form_style-two ms-auto">
                                    <div class="input-group search-form__input_group">
                                        <span class="search-form__icon">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="search" name="search" id="search"
                                               value="{{request()->get('search')}}"
                                               class="theme-input-style search-form__input"
                                               placeholder="{{ translate('Search_Here') }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary search-submit"
                                            data-url="{{ url()->current() }}">{{ translate('search') }}</button>
                                </form>
                            </div>
                            @if ($lang !== 'en')
                                <button class="btn btn-primary cmn_focus"
                                        id="translate-confirm-btn">{{ translate('Translate_All') }}</button>
                            @endif
                            {{--                            <button type="button" class="btn btn-primary cmn_focus" data-bs-toggle="modal" data-bs-target="#translate-confirm-modal">--}}
                            {{--                                Translate All--}}
                            {{--                            </button>--}}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive language-table">
                            <table class="table table-bordered border-DEE2E6">
                                <thead>
                                <tr>
                                    <th>{{translate('SL')}}</th>
                                    <th>{{translate('Current_Value')}}</th>
                                    <th>{{translate('Translated_Value')}}</th>
                                    <th>{{translate('auto_translate')}}</th>
                                    <th>{{translate('update')}}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php($count=0)
                                @forelse($translateData as $key => $value)
                                    @php($count++)
                                    <tr id="lang-{{$count}}">
                                        <td>{{ $translateData->firstItem() + ($count-1) }}</td>
                                        <td class="fs-14">
                                            @php($key_view=str_replace( array("_"), ' ', $key))
                                            <input type="text" name="key[]"
                                                   value="{{$key}}" hidden>
                                            <label>{{$key_view}}</label>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control h-40px __w-350px fs-14"
                                                   name="value[]"
                                                   id="value-{{$count}}"
                                                   value="{{$value}}">
                                        </td>
                                        <td>
                                            <button type="button" data-key="{{ $key }}" data-id="{{ $count }}"
                                                    class="btn bg-primary-light text-primary border-0 m-auto btn-block auto_translate justify-content-center h-40px">
                                                <i class="bi bi-translate"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <button type="button" data-key="{{ $key }}" data-count="{{ $count }}"
                                                    class="btn btn-primary btn-block update-lang justify-content-center h-40px">
                                                <i class="bi bi-sd-card-fill"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
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
                        @if(count($translateData) !== 0)
                            <hr>
                        @endif
                        <div class="page-area">
                            {!! $translateData->links() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade language-complete-modal" id="translate-confirm-modal">
        <div class="modal-dialog modal-dialog-centered max-w-450px">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="py-5">
                        <div class="mb-4">
                            <img src="{{dynamicAsset('public/assets/admin-module/img/language-complete.png')}}" alt="">
                        </div>
                        <h4 class="mb-3">{{ translate('Are you sure ?') }}</h4>
                        <p class="mb-4 text-9EADC1 max-w-362px mx-auto">
                            {{ translate('You_want_to_auto_translate_all.') }}
                        </p>
                        <div class="d-flex justify-content-center gap-3 pt-1">

                            <button type="button" class="btn btn-secondary cmn_reset"
                                    data-bs-dismiss="modal">{{ translate('Cancel') }}</button>
                            <button type="button" class="btn btn-primary auto_translate_all"
                                    data-bs-dismiss="modal">{{ translate('Yes,_Translate_All') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade language-complete-modal" id="complete-modal" data-bs-backdrop="static"
         data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered max-w-450px">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" id="closeOnComplete" class="btn-close shadow-none location_reload"  data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="py-5">
                        <div class="mb-4">
                            <img src="{{dynamicAsset('public/assets/admin-module/img/language-complete.png')}}" alt="">
                        </div>
                        <h4 class="mb-3">{{ translate('Your_file_has_been_successfully_translated') }}</h4>
                        <p class="mb-4 text-9EADC1 max-w-362px mx-auto">
                            {{ translate('Your all selected items that you wanted to translate those successfully translated.') }}
                        </p>
                        {{-- <div class="d-flex justify-content-center gap-3 pt-1">
                            <form action="{{request()->url()}}">
                                <button type="submit" class="btn btn-primary cmn_focus">{{ translate('Okay') }}</button>
                            </form>
                        </div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade language-warning-modal" id="warning-modal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <img src="{{dynamicAsset('public/assets/admin-module/img/invalid-icon.png')}}" alt="">
                        <div class="w-0 flex-grow-1">
                            <h3 class="mb-2">{{ translate('Warning!') }}</h3>
                            <p>
                                {{ translate('Translating_in_progress._Are_you_sure,_want_to_close_this_tab?_If_you_close_the_tab,_the_progress_made_will_be_saved_but_remaining_content_will_not_be_translated..') }}
                            </p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-secondary cmn_reset" id="close-tab"
                                data-bs-dismiss="modal">{{ translate('Yes,_Close') }}</button>
                        <button type="button" class="btn btn-primary cmn_focus" id="cancel-tab"
                                data-bs-dismiss="modal">{{ translate('No,_Continue') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade language-complete-modal " id="translating-modal" data-bs-backdrop="static"
         data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <?php
            $translationTime = '<span id="time-data">' . translate('few hours')  . '</span>';
        ?>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close shadow-none location_reload"  data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="pb-4 px-sm-2">
                        <div class="progress-circle-container mb-4">
                            <img width="100" src="{{dynamicAsset('public/assets/admin-module/img/loader-icon.gif')}}" alt="">
                        </div>
                        <h4 class="mb-2">{!! translate(key: 'Translating_may_take_up_to_{time}', replace: ['time' => $translationTime]) !!} </h4>
                        <p class="mb-4">
                            {{ translate('Please_wait_&_don’t_close/terminate_your_tab_or_browser') }}
                        </p>
                        <div class="max-w-215px mx-auto">
                            <div class="d-flex flex-wrap mb-1 justify-content-between font-semibold text--title">
                                <span>{{ translate('In_Progress') }}</span>
                                <span class="translating-modal-success-rate">0%</span>
                            </div>
                            <div class="progress mb-4 h-5px">
                                <div class="progress-bar bg-success rounded-pill translating-modal-success-bar"
                                     style="width: 0.4%"></div>
                            </div>
                        </div>
                        <p>{{ translate('If you close now, the progress made so far will be saved. The remaining content will not be translated.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" value="0" id="translating-count">

@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/business-management/language/translate.js') }}"></script>

    <script>

        function update_lang(key, value) {
            @if (env('APP_MODE')!='demo')
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{route('admin.business.languages.translate-submit',[$lang])}}",
                method: 'POST',
                data: {
                    key: key,
                    value: value
                },
                beforeSend: function () {
                    $('#loading').removeClass('d-none');
                },
                success: function (response) {
                    toastr.success('{{DEFAULT_UPDATE_200['message']}}');
                },
                complete: function () {
                    $('#loading').addClass('d-none');
                },
            });
            @else
            call_demo();
            @endif

        }

        function auto_translate(key, id) {
            @if (env('APP_MODE')!='demo')
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{route('admin.business.languages.auto-translate',[$lang])}}",
                method: 'POST',
                data: {
                    key: key
                },
                beforeSend: function () {
                    $('#loading').removeClass('d-none');
                },
                success: function (response) {
                    toastr.success('{{DEFAULT_UPDATE_200['message']}}');
                    $('#value-' + id).val(response.translated_data);
                },
                complete: function () {
                    $('#loading').addClass('d-none');
                },
            });
            @else
            call_demo();
            @endif
        }

        function auto_translate_all() {
            var total_count;

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{route('admin.business.languages.auto-translate-all',[$lang])}}",
                method: 'get',
                data: {
                    translating_count: $('#translating-count').val(),
                },
                beforeSend: function () {
                    if (!$('#warning-modal').hasClass('show')) {
                        $('#translating-modal').addClass('prevent-close');
                        $('#translating-modal').modal('show');
                    }
                },
                success: function (response) {
                    console.log(response);

                    if (response.data === 'data_prepared') {
                        if (!$('#warning-modal').hasClass('show'))
                        {
                            $('#translating-modal').modal('show')
                        }
                        $('#translating-count').val(response.total)
                        auto_translate_all();
                    } else if (response.data === 'translating' && response.status === 'pending') {
                        if ($('#translating-count').val() == 0) {
                            $('#translating-count').val(response.total)
                        }

                        $('.translating-modal-success-rate').html(response.percentage + '%');
                        $('.translating-modal-success-bar').attr('style', 'width:' + response.percentage + '%');


                        if (response.hours > 0) {
                            $('#time-data').html(response.hours + ' {{ translate('hours') }} ' + response.minutes + ' {{ translate('min') }}');
                        }
                        if (response.minutes > 0 && response.hours <= 0) {
                            $('#time-data').html(response.minutes + ' {{ translate('min') }} ' + response.seconds + ' {{ translate('seconds') }}');
                        }
                        if (response.seconds > 0 && response.minutes <= 0) {
                            $('#time-data').html(response.seconds + ' {{ translate('seconds') }}');
                        }

                        auto_translate_all();
                        if (!$('#warning-modal').hasClass('show'))
                        {
                            $('#translating-modal').modal('show')
                        }
                    } else if ((response.data === 'translating' && response.status === 'done') || response.data === 'success' || response.data === 'error') {
                        $('#translating-modal').removeClass('prevent-close')
                        $('#translating-modal').modal('hide')
                        $('#translating-count').val(0)
                        if (response.data !== 'error') {
                            $('#complete-modal').modal('show');
                            if ($('#warning-modal').hasClass('show'))
                            {
                                $('#warning-modal').modal('hide');
                            }
                        } else {
                            toastr.error(response.message);
                        }
                    }
                },
                complete: function () {
                },
            });
        }

        $(document).ready(function () {
            let isReloading = false;

            $(document).on('click', '#translate-confirm-btn', function () {
                $('#translate-confirm-modal').modal('show');
            });

            $(document).on('click', '.auto_translate_all', function () {
                auto_translate_all();
            });

            $(document).on('click', '.location_reload', function (event) {
                if ($('#translating-modal').hasClass('prevent-close')) {
                    event.preventDefault();
                    $('#warning-modal').modal('show');

                    $('#translating-modal').modal('hide');
                    $('#complete-modal').modal('hide');
                }
            });

            $(document).on('click', '#closeOnComplete', function () {
                location.reload();
            })

            window.addEventListener('beforeunload', function (event) {
                if ($('#translating-modal').hasClass('prevent-close') && !isReloading) {
                    event.preventDefault();
                    event.returnValue = '';
                    return '';
                }
            });

            $(document).on('click', '#close-tab', function () {
                $('#translating-modal').removeClass('prevent-close');
                isReloading = true;
                window.location.reload();
            });

            $(document).on('click', '#cancel-tab', function () {
                $('#warning-modal').modal('hide');
                if ($('#translating-modal').hasClass('prevent-close')){
                    $('#translating-modal').modal('show');
                }
            });
        });

    </script>


@endpush
