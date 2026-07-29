@extends('adminmodule::layouts.master')

@section('title', translate('Languages'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Title -->
        <h2 class="fs-22 mb-4 text-capitalize">{{ translate('system_settings') }}</h2>
        <!-- End Page Title -->

        <!-- Inlile Menu -->
        <div class="mb-3">
            @include('businessmanagement::admin.system-settings.partials._system-settings-inline')
        </div>
        <!-- End Inlile Menu -->

        <!-- End Page Header -->
        <div class="card border-0 mb-4">
            <div class="card-body collapsible-card-body">
                <form action="{{route('admin.business.languages.update-default-status')}}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h5 class="mb-2">{{ translate('System_Default_Language') }}</h5>
                            <div class="fs-12">
                                {{ translate('Here you select a default language for you full system.') }}
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="floating-form-group mt-1">
                                <label for="" class="floating-form-label">{{ translate('Select_Language') }}</label>
                                <select class="js-select form-select cmn_focus" id="defaultLanguage" name="code" required tabindex="1">
                                    @foreach($language->value as $key => $data)
                                        <option
                                            value="{{$data['code']}}" {{ array_key_exists('default', $data) && $data['default'] ? 'selected' : '' }}>{{ $allLanguages->firstWhere('code', $data['code'])['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit"
                                class="btn btn-primary text-uppercase btn-lg" tabindex="2">{{ translate('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header">
                <div class="alert alert-danger mb-3" role="alert">
                    <i class="bi bi-info-circle-fill"></i>
                    {{ translate('changing_some_settings_will_take_time_to_show_effect_please_clear_session_or_wait_for_60_minutes_else_browse_from_incognito_mode') }}
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-10 justify-content-between mb-3">
                    <h2 class="fs-18 mb-3 text-capitalize">{{ translate('language_list') }}</h2>

                    <a href="{{ route('admin.vehicle.create') }}" type="button" data-bs-toggle="modal" tabindex="3"
                       data-bs-target="#lang-modal" class="btn btn-primary text-capitalize cmn_focus">
                        <i class="bi bi-plus fs-16"></i> {{ translate('add_new_language') }}
                    </a>
                </div>
                <div class="table-responsive datatable-custom" id="table-div">
                    <table id="datatable" class="table table-borderless align-middle">
                        <thead class="table-light align-middle">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th class="text-center">{{ translate('language_name') }}</th>
                            <th class="text-center">{{ translate('short_code') }}</th>
                            <th class="text-center">{{ translate('direction') }}</th>
                            <th class="text-center">{{ translate('status') }}</th>
                            <th class="text-center">{{ translate('action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($language)
                            @foreach ($language->value as $key => $data)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td class="text-center">{{ $allLanguages->firstWhere('code', $data['code'])['name'] }}</td>
                                    <td class="text-center">{{ $data['code'] }}</td>
                                    <td class="text-center">{{ $data['direction'] }}</td>
                                    <td class="text-center">
                                        <label class="switcher mx-auto">
                                            <input type="checkbox"
                                                   data-url="{{ route('admin.business.languages.update-status') }}"
                                                   data-code="{{ $data['code'] }}"
                                                   class="switcher_input language-status-change status_{{ $data['code'] }}"
                                                {{ $data['status'] == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <a class="btn btn-outline-primary btn-action d-flex"
                                               href="{{ route('admin.business.languages.translate', [$data['code']]) }}">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/svg/translate.svg') }}"
                                                    alt="" class="svg">
                                            </a>
                                            @if ($data['code'] != 'en')
                                                <div class="dropdown d-flex justify-content-center">
                                                    <button type="button" class="btn btn-outline-primary btn-action d-flex"
                                                            id="dropdownMenuButton" data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        <img
                                                            src="{{ dynamicAsset('public/assets/admin-module/img/svg/settings.svg') }}"
                                                            alt="" class="svg">
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                        @if ($data['code'] != 'en')
                                                            <li>
                                                                <a class="dropdown-item" data-bs-toggle="modal"
                                                                   data-bs-target="#lang-modal-update-{{ $data['code'] }}">{{ translate('update') }}</a>
                                                            </li>
                                                            @if ($data['default'])
                                                            @else
                                                                <li>
                                                                    <a href="javascript:"
                                                                       data-url="{{ route('admin.business.languages.delete', [$data['code']]) }}"
                                                                       data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                                       data-title="{{ translate('Are you sure to delete this Language') }}?"
                                                                       data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                                       data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                                       data-cancel-btn="{{ translate('Not Now') }}"
                                                                       class="dropdown-item delete-button">{{ translate('delete') }}</a>
                                                                </li>
                                                            @endif
                                                        @endif
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="lang-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ translate('new_language') }}</h5>
                    <div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <form action="{{ route('admin.business.languages.add-new') }}" method="post"
                      style="text-align: {{ Session::get('direction') === 'rtl' ? 'right' : 'left' }};"
                      id="language_form">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="form-group">
                                    <label for="message-text" class="col-form-label">{{ translate('language') }}
                                        :</label>
                                    <select id="message-text" name="code" class="form-control js-select-modal">
                                        @foreach (LANGUAGES as $lang)
                                            <option value="{{ $lang['code'] }}">{{ $lang['name'] }}
                                                - {{ $lang['nativeName'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="name" id="lang_name">
                                </div>
                            </div>
                            <div class="col-12 mb-4">
                                <div class="form-group">
                                    <label class="col-form-label">{{ translate('direction') }} :</label>
                                    <select class="form-control" name="direction">
                                        <option value="ltr">{{ translate('LTR') }}</option>
                                        <option value="rtl">{{ translate('RTL') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary cmn_reset" data-bs-dismiss="modal"
                                aria-label="Close">{{ translate('close') }}</button>
                        <button type="submit" class="btn btn-primary cmn_focus">{{ translate('Add') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if ($language)
        @foreach ($language->value as $key => $data)
            <div class="modal fade" id="lang-modal-update-{{ $data['code'] }}" tabindex="-1" role="dialog"
                 aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ translate('new_language') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>
                        <form action="{{ route('admin.business.languages.update') }}" method="post">
                            @csrf
                            <input type="hidden" name="code" value="{{ $data['code'] }}">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="lang_code"
                                                   class="col-form-label">{{ translate('language') }}</label>
                                            <select name="code" id="lang_code" class="form-control js-select2-custom"
                                                    disabled>
                                                @foreach (LANGUAGES as $lang)
                                                    <option value="{{ $lang['code'] }}"
                                                        {{ $data['code'] == $lang['code'] ? 'selected' : '' }}>
                                                        {{ $lang['name'] }} - {{ $lang['nativeName'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="col-form-label">{{ translate('direction') }} :</label>
                                            <select class="form-control" name="direction">
                                                <option value="ltr"
                                                    {{ isset($data['direction']) ? ($data['direction'] == 'ltr' ? 'selected' : '') : '' }}>
                                                    LTR
                                                </option>
                                                <option value="rtl"
                                                    {{ isset($data['direction']) ? ($data['direction'] == 'rtl' ? 'selected' : '') : '' }}>
                                                    RTL
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary cmn_reset" data-bs-dismiss="modal"
                                        aria-label="Close">{{ translate('close') }}</button>

                                <button type="submit" class="btn btn-primary cmn_focus">{{ translate('update') }} <i
                                        class="fa fa-plus"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/business-management/language/index.js') }}"></script>
@endpush
