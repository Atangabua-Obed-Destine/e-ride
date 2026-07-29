@extends('adminmodule::layouts.master')

@section('title', translate('AI Setup'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="fs-20 fw-bold mb-0 text-capitalize">{{translate('AI Setup')}}</h2>
                <a href="#howItWork-offcanvas" data-bs-toggle="offcanvas"
                   class="text-primary fw-medium fs-12 d-flex gap-1 align-items-center">{{ translate('How it Works') }}
                    <i class="bi bi-info-circle"></i></a>
            </div>
            <form action="{{ route('admin.business.configuration.ai-setup.update') }}" method="POST">
                @csrf
                <input type="hidden" name="ai_name" value="OpenAI">
                <div class="card card-body h-100">
                    <div class="d-flex gap-2 justify-content-between align-items-center">
                        <div>
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('AI Configuration')}}
                            </h5>
                            <p class="mb-0">{{ translate('Configure OpenAI credentials to activate intelligent features across the platform') }}</p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <label class="switcher rounded-pill cmn_focus">
                                <input class="switcher_input collapsible-card-switcher"
                                       id="" type="checkbox" name="status"
                                       {{ $aiSetting->status ?? 0 == 1 ? 'checked' : '' }}
                                >
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="">
                                        <label for="" class="mb-2">
                                            {{ translate('OpenAI Api Key') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               value="{{ env('APP_MODE') == 'demo' ? '' : ($aiSetting->api_key ?? '') }}" class="form-control"
                                               name="api_key"
                                               placeholder="{{ translate('Enter API key') }}"
                                               required
                                        >
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="">
                                        <label for="" class="mb-2">
                                            {{ translate('OpenAI Organization ID') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               value="{{ env('APP_MODE') == 'demo' ? '' : ($aiSetting->organization_id ?? '')}}" class="form-control"
                                               name="organization_id"
                                               placeholder="{{ translate('Enter Secret key') }}"
                                               required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-4">
                            <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                    tabindex="2">{{ translate('reset') }}</button>
                            <button type="{{ env('APP_MODE') == 'demo' ? 'button' : 'submit' }}" class="btn btn-primary min-w-120 cmn_focus call-demo"
                                    tabindex="3">{{ translate('save') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->

    {{-- How Verification Work Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="howItWork-offcanvas" style="--bs-offcanvas-width: 490px">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <h4 class="offcanvas-title flex-grow-1 text-center">
                    {{ translate('How AI Configuration Works') }}
                </h4>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <h6 class="mb-3">{{ translate('AI Configuration connects your system with OpenAI services.') }}
                    {{ translate('Once enabled, AI features such as content suggestions, smart responses, data analysis, or automation tools become available across the platform.') }}
                </h6>
                <div class="d-flex flex-column gap-20">
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-semibold mb-3">{{ translate('How to Configure AI Setup') }}</h5>
                        <div class="bg-white rounded p-lg-4 p-3">
                            <ul class="fs-14 text-dark d-flex flex-column gap-2 mb-0 ps-18px">
                                <li>{{ translate('Allow the option to activate AI services.') }}</li>
                                <li>{{ translate('Add your valid OpenAI API Key to authorize AI requests from the system.') }}</li>
                                <li>{{ translate('The system captures a live facial image and verifies it against the driver’s stored profile photo.') }}</li>
                                <li>{{ translate('Enter your OpenAI Organization ID to associate usage with the correct OpenAI account.') }}</li>
                                <li>{{ translate('Save to apply the setup and start using AI-powered features.') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-semibold mb-3">{{ translate('Note') }}</h5>
                        <div class="bg-white rounded p-lg-4 p-3">
                           <p>{{ translate('Ensure the API Key and Organization ID are valid and active.') }} {{ translate('Keep your API credentials secure and do not share them publicly.') }}
                           </p>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
                <button type="button" class="btn btn-primary fw-semibold"
                        data-bs-dismiss="offcanvas">
                    {{translate('Okay, Got It') }}
                </button>
            </div>
        </form>
    </div>

@endsection
