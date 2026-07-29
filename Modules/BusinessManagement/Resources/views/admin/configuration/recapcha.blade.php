@extends('adminmodule::layouts.master')

@section('title', translate('Recaptcha'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('3rd_party')}}</h2>
            @include('businessmanagement::admin.configuration.partials._third_party_inline_menu')

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                        <h5 class="text-capitalize">{{translate('google_recaptcha_information')}}</h5>
                        <a target="_blank" href="https://www.google.com/recaptcha/admin/create" tabindex="1"
                           class="btn btn-outline-primary text-capitalize">
                            {{translate('credential_setup_page')}}
                        </a>
                    </div>
                    <div class="badge badge-info rounded d-flex mb-3 p-3">
                        <div class="text-start">
                            <h5 class="text-primary mb-2">{{ translate('V3 Version is available now. Must setup for ReCAPTCHA V3') }}</h5>
                            <p class="text--info">{{ translate('You must setup for V3 version and active the status. Otherwise the default reCAPTCHA will be displayed automatically') }}</p>
                        </div>
                    </div>


                    <form action="{{route('admin.business.configuration.third-party.recaptcha.update')}}" method="post"
                          id="recaptcha_form">
                        @csrf
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-3">{{translate('status')}}</h6>
                                <div class="d-flex align-items-center gap-4 gap-xl-5 mb-30">
                                    <div class="custom-radio cmn_focus rounded-pill pe-1">
                                        <input type="radio" id="senang_pay-active" name="status" tabindex="2"
                                               value="1" {{($setting['status'] ?? 0) == 1? 'checked' : ''}}>
                                        <label for="senang_pay-active">{{translate('active')}}</label>
                                    </div>
                                    <div class="custom-radio cmn_focus rounded-pill pe-1">
                                        <input type="radio" id="senang_pay-inactive" name="status" tabindex="2"
                                               value="0" {{($setting['status'] ?? 0) == 0? 'checked' : ''}}>
                                        <label for="senang_pay-inactive">{{translate('inactive')}}</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 align-it">
                                <div class="mb-4">
                                    <label for="site_key" class="mb-2">{{translate('site_key')}}</label>
                                    <input required type="text" name="site_key" value="{{env('APP_MODE') == 'demo' ? "":$setting['site_key']??''}}"
                                           class="form-control" id="site_key" placeholder="Site Key" tabindex="3">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="secret_key" class="mb-2">{{translate('secret_key')}}</label>
                                    <input required type="text" name="secret_key" value="{{env('APP_MODE') == 'demo' ? "": $setting['secret_key']??''}}"
                                           class="form-control" id="secret_key" placeholder="Secret Key" tabindex="4">
                                </div>
                            </div>
                            <div class="col-12">
                                <h5 class="mb-3">Instructions</h5>
                                <ol class="d-flex flex-column text-dark gap-1">
                                    <li>{{translate('Go to the Credentials page')}}
                                        (<a
                                            href="https://www.google.com/recaptcha/admin/create"
                                            target="_blank"><b>{{translate('Click_Here')}}</b></a>)
                                    </li>
                                    <li>{{translate('Add a ')}}
                                        <b>{{translate('label')}}</b> {{translate('(Ex: Test Label)')}}
                                    </li>
                                    <li>
                                        {{translate('Select reCAPTCHA v3 as ')}}
                                        <b>{{translate('reCAPTCHA Type')}}</b>
                                    </li>
                                    <li>
                                        {{translate('Add')}}
                                        <b>{{translate('domain')}}</b>
                                        {{translate('(For ex: demo.6amtech.com)')}}
                                    </li>
                                    <li>
                                        {{translate('Press')}}
                                        <b>{{translate('Submit')}}</b>
                                    </li>
                                    <li>{{translate('Copy')}} <b>Site
                                            Key</b> {{translate('and')}} <b>Secret
                                            Key</b>, {{translate('paste in the input filed and')}}
                                        <b>Save</b>.
                                    </li>
                                </ol>
                                <div class="d-flex justify-content-end">
                                    <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}" class="btn btn-primary call-demo" tabindex="5">{{translate('save')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection


@push('script')

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#recaptcha_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>

@endpush
