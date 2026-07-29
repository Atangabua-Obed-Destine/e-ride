@extends('adminmodule::layouts.master')

@section('title', translate('Payment_Methods'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('3rd_party') }}</h2>
            @include('businessmanagement::admin.configuration.partials._third_party_inline_menu')
            @php($maxSize = readableUploadMaxFileSize('image'))
            <div class="main-content">
                <div class="payment-heading">

                </div>

                <!-- Tab Content -->
                <div class="row">
                    @foreach ($dataValues as $gateway)
                        <div class="col-md-6 mb-30">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="page-title">{{ ucwords(str_replace('_', ' ', $gateway->key_name)) }}</h4>
                                </div>
                                <div class="card-body p-30">
                                    <form
                                        action="{{ route('admin.business.configuration.third-party.payment-method.update') }}"
                                        method="POST" id="{{ $gateway->key_name }}-form" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')
                                        <div class="discount-type">
                                            <div class="d-flex align-items-center gap-4 gap-xl-5 mb-30">
                                                <div class="custom-radio">
                                                    <input type="radio" id="{{ $gateway->key_name }}-active"
                                                           name="status" value="1"
                                                        {{ $gateway->is_active == 1 ? 'checked' : '' }}>
                                                    <label for="{{ $gateway->key_name }}-active">Active</label>
                                                </div>
                                                <div class="custom-radio">
                                                    <input type="radio" id="{{ $gateway->key_name }}-inactive"
                                                           name="status" value="0"
                                                        {{ $gateway->is_active == 1 ? '' : 'checked' }}>
                                                    <label for="{{ $gateway->key_name }}-inactive">Inactive</label>
                                                </div>
                                            </div>

                                            <input name="gateway" value="{{ $gateway->key_name }}" class="d-none">
                                            <div class="  mb-30 mt-30">
                                                <select class="js-select form-control theme-input-style w-100"
                                                        name="mode">
                                                    <option
                                                        value="live" {{ $gateway->mode == 'live' ? 'selected' : '' }}>
                                                        Live
                                                    </option>
                                                    <option
                                                        value="test" {{ $gateway->mode == 'test' ? 'selected' : '' }}>
                                                        Test
                                                    </option>
                                                </select>
                                            </div>

                                            @php($supportedCountry=$dataValues->where('key_name',$gateway->key_name)->first()->live_values)
                                            @if (isset($supportedCountry['supported_country']))
                                                @php($supportedCountry = $supportedCountry['supported_country'])
                                                <label for="{{$gateway->key_name}}-title"
                                                       class="form-label">{{translate('supported_country')}} *</label>
                                                <select class="js-select form-control theme-input-style w-100"
                                                        name="supported_country">
                                                    <option
                                                        value="egypt" {{$supportedCountry=='egypt'?'selected':''}}>{{ translate('Egypt') }}</option>
                                                    <option
                                                        value="PAK" {{$supportedCountry=='PAK'?'selected':''}}>{{ translate('Pakistan') }}</option>
                                                    <option
                                                        value="KSA" {{$supportedCountry=='KSA'?'selected':''}}>{{ translate('Saudi Arabia') }}</option>
                                                    <option
                                                        value="oman" {{$supportedCountry=='oman'?'selected':''}}>{{ translate('Oman') }}</option>
                                                    <option
                                                        value="UAE" {{$supportedCountry=='UAE'?'selected':''}}>{{ translate('UAE') }}</option>
                                                </select>
                                            @endif
                                        </div>

                                        @php($skip = ['gateway', 'mode', 'status', 'supported_country','gateway_title'])
                                        @foreach ($dataValues->where('key_name', $gateway->key_name)->first()->live_values as $key => $value)
                                            @if (!in_array($key, $skip))
                                                <div class="  mb-30 mt-30">
                                                    <label for="exampleFormControlInput1"
                                                           class="form-label">{{ ucwords(str_replace('_', ' ', $key)) }}
                                                        *</label>
                                                    <input type="text" class="form-control"
                                                           name="{{ $key }}"
                                                           placeholder="{{ ucwords(str_replace('_', ' ', $key)) }} *"
                                                           value="{{ env('APP_MODE') == 'demo' ? '' : $value }}">
                                                </div>
                                            @endif
                                        @endforeach
                                        @php($additionalData = $gateway['additional_data'] != null ? json_decode($gateway['additional_data']) : null)
                                        <div class="  mb-30 mt-30">
                                            <label for="exampleFormControlInput1" class="form-label">Gateway
                                                Title *</label>
                                            <input type="text" class="form-control" name="gateway_title"
                                                   placeholder="Gateway Title"
                                                   value="{{ $additionalData != null ? $additionalData->gateway_title : '' }}">
                                        </div>
                                        <div class="mb-30 mt-30">
                                            <div class="d-flex flex-column justify-content-around gap-3">
                                                <h5 class="text-center">Gateway Image</h5>

                                                <div class="d-flex justify-content-center">
                                                    <div class="upload-file">
                                                        <input type="file" name="gateway_image"
                                                               class="upload-file__input"
                                                               accept=".png" data-max-upload-size="{{ $maxSize }}">

                                                        <div class="upload-file__img w-auto h-auto">
                                                            <img width="150"
                                                                 src="{{ onErrorImage(
                                                                        $additionalData?->gateway_image,
                                                                        dynamicStorage('storage/app/public/payment_modules/gateway_image') . '/' . $additionalData?->gateway_image,
                                                                        dynamicAsset('public/assets/admin-module/img/media/upload-file.png'),
                                                                        'payment_modules/gateway_image/',
                                                                    ) }}"
                                                                 alt="">
                                                        </div>
                                                    </div>
                                                </div>

                                                <p class="opacity-75 mx-auto max-w220">
                                                    {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => '.png' , 'imageSize' => $maxSize]) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary demo_check">
                                                Update
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <!-- End Tab Content -->
            </div>


        </div>
    </div>
    <!-- End Main Content -->
@endsection


@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/business-management/configuration/payment-method.js') }}">
    </script>
@endpush
