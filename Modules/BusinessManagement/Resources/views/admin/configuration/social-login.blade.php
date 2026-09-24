@extends('adminmodule::layouts.master')

@section('title', translate('Social Media Login'))

@section('content')
    @php
        $isDemo = env('APP_MODE') == 'demo';
        $providers = [
            [
                'key' => 'apple_login',
                'name' => translate('Apple Login'),
                'value' => $apple,
                'modal' => 'appleInstructionModal',
                'icon' => '<i class="bi bi-apple" style="font-size: 24px;"></i>',
            ],
        ];
    @endphp

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('3rd_party') }}</h2>
            @include('businessmanagement::admin.configuration.partials._third_party_inline_menu')

            <div class="card">
                <div class="card-body">
                    <div class="mb-20">
                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Social Media Login') }}</h4>
                        <p class="fs-14 mb-0">{{ translate('You can use following login from Customer Login page after active check Social Media Login.') }}</p>
                    </div>

                    @foreach ($providers as $provider)
                        @php
                            $value = $provider['value'];
                            $isActive = ($value['status'] ?? 0) == 1;
                            $isApple = $provider['key'] === 'apple_login';
                        @endphp
                        <div class="bg-F6F6F6 rounded p-xl-4 p-3 mb-3">
                            <form action="{{ route('admin.business.configuration.third-party.social-login.update') }}"
                                  method="POST" id="{{ $provider['key'] }}-form" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="name" value="{{ $provider['key'] }}">
                                <div class="collapsible-card-body">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="d-flex mt-1">{!! $provider['icon'] !!}</span>
                                            <div>
                                                <h5 class="fs-16 mb-1">{{ $provider['name'] }}</h5>
                                                <p class="fs-13 mb-1 opacity-75">
                                                    {{ translate('Use {provider} login as your customer Social Media Login turn the switch & setup the required files.', ['provider' => $provider['name']]) }}
                                                </p>
                                                <a href="javascript:" class="fw-semibold text-info text-decoration-underline fs-13"
                                                   data-bs-toggle="modal" data-bs-target="#{{ $provider['modal'] }}">
                                                    {{ translate('Get Credential Setup') }}
                                                </a>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <a href="javascript:" class="text-info fw-semibold fs-13 d-flex align-items-center gap-1 view-btn">
                                                <span class="text-underline">{{ translate('View') }}</span>
                                                <i class="{{ $isActive ? 'tio-arrow-upward' : 'tio-arrow-downward' }}"></i>
                                            </a>
                                            <label class="switcher rounded-pill mb-0">
                                                <input class="switcher_input social-status-switcher" type="checkbox" name="status" value="1"
                                                       {{ $isActive ? 'checked' : '' }} {{ $isDemo ? 'disabled' : '' }}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="collapsible-card-content" style="{{ $isActive ? '' : 'display: none;' }}">
                                        <div class="bg-white rounded p-lg-4 p-3 mt-3">
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('Store Bundle ID') }}
                                                    <i class="bi bi-info-circle-fill text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('Enter your iOS app Bundle ID from your Apple developer account.') }}"></i>
                                                </label>
                                                <input type="text" class="form-control" name="bundle_id"
                                                       placeholder="{{ translate('Ex: Bundle ID') }}"
                                                       value="{{ $isDemo ? '------' : ($value['bundle_id'] ?? '') }}" {{ $isDemo ? 'disabled' : '' }}>
                                            </div>
                                            @if ($isApple)
                                                <div class="mb-3">
                                                    <label class="form-label">{{ translate('Team id') }}
                                                        <i class="bi bi-info-circle-fill text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip"
                                                           data-bs-title="{{ translate('Enter your Apple Developer Team ID from your Apple developer account.') }}"></i>
                                                    </label>
                                                    <input type="text" class="form-control" name="team_id"
                                                           placeholder="{{ translate('Ex: Team id') }}"
                                                           value="{{ $isDemo ? '------' : ($value['team_id'] ?? '') }}" {{ $isDemo ? 'disabled' : '' }}>
                                                </div>
                                            @endif
                                            <div class="{{ $isApple ? 'mb-3' : 'mb-0' }}">
                                                <label class="form-label">{{ translate('Store Client Secret Key') }}
                                                    <i class="bi bi-info-circle-fill text-muted ms-1 cursor-pointer" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ $isApple ? translate('Enter the Key ID of your Apple service key.') : translate('Enter the Client Secret generated for your OAuth application.') }}"></i>
                                                </label>
                                                <input type="text" class="form-control" name="client_secret"
                                                       placeholder="{{ $isApple ? translate('Ex: Key ID') : translate('Ex: Client secret key') }}"
                                                       value="{{ $isDemo ? '------' : ($value['client_secret'] ?? '') }}" {{ $isDemo ? 'disabled' : '' }}>
                                            </div>
                                            @if ($isApple)
                                                <div class="mb-0 mt-3">
                                                    <label class="form-label">{{ translate('Choose updated file') }}</label>
                                                    <input type="file" class="form-control" name="service_file" accept=".p8" {{ $isDemo ? 'disabled' : '' }}>
                                                    @if (!empty($value['service_file']))
                                                        <p class="fs-12 text-success mt-2 mb-0">
                                                            <i class="bi bi-file-earmark-check-fill me-1"></i>{{ translate('A key file has been uploaded.') }} ({{ $value['service_file'] }})
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button type="reset" class="btn btn-secondary cmn_focus">{{ translate('Reset') }}</button>
                                            <button type="{{ $isDemo ? 'button' : 'submit' }}"
                                                    class="btn btn-primary cmn_focus call-demo">{{ translate('Save') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="appleInstructionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="text-center mb-3">
                        <i class="bi bi-apple" style="font-size: 42px;"></i>
                        <h5 class="mt-3">{{ translate('Apple API Setup Instructions') }}</h5>
                    </div>
                    <ol class="d-flex text-dark flex-column gap-2 ps-3">
                        <li>{{ translate('Sign in to your Apple Developer account and open Certificates, Identifiers & Profiles.') }}
                            (<a href="https://developer.apple.com/account" target="_blank">{{ translate('Click here') }}</a>)
                        </li>
                        <li>{{ translate('Copy your Team ID from the top-right of the Membership page and paste it into the Team id field.') }}</li>
                        <li>{{ translate('Open Identifiers, register or select an App ID, and enable the Sign in with Apple capability.') }}</li>
                        <li>{{ translate('Copy the Bundle ID of that App ID and paste it into the Store Bundle ID field.') }}</li>
                        <li>{{ translate('Open Keys, create a new key with Sign in with Apple enabled, and copy its Key ID into the Store Client Secret Key field.') }}</li>
                        <li>{{ translate('Download the AuthKey file (.p8), upload it in the Choose updated file field, and store it safely as it can be downloaded only once.') }}</li>
                        <li>{{ translate('Turn on the switch and click Save.') }}</li>
                    </ol>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-primary cmn_focus min-w-120" data-bs-dismiss="modal">{{ translate('Got It') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        $(document).on('change', '.social-status-switcher', function () {
            if (this.checked) {
                let $body = $(this).closest('.collapsible-card-body');
                $body.find('.collapsible-card-content').slideDown();
                $body.find('.view-btn i').removeClass('tio-arrow-downward').addClass('tio-arrow-upward');
            }
        });
    </script>
@endpush
