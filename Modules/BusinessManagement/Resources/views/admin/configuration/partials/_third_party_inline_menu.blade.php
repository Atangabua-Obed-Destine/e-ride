<div class="position-relative nav--tab-wrapper mb-20">
    <ul class="nav d-flex gap-3 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.payment-method.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/payment-method') ? 'active' : ''}}
            ">{{translate('payment_methods')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.sms-gateway.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/sms-gateway') ? 'active' : ''}}
            ">{{translate('SMS_gateways')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.firebase-otp.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/firebase-otp') ? 'active' : ''}}
            ">{{translate('firebase OTP')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.email-config.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/email-config') ? 'active' : ''}}
            ">{{translate('email_config')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.google-map.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/google-map') ? 'active' : ''}}
            ">{{translate('google_map_API')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.third-party.recaptcha.index')}}" class="text-capitalize nav-link
                {{Request::is('admin/business/configuration/third-party/recaptcha') ? 'active' : ''}}
            ">{{translate('reCaptcha')}}</a>
        </li>
    </ul>
    <div class="nav--tab__prev">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="nav--tab__next">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
