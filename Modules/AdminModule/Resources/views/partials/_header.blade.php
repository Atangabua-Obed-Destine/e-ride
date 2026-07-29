<header class="header fixed-top">
    <div class="container-fluid">
        <div class="row align-items-center justify-content-between">
            <div class="col-2">
                <!-- Header Menu -->
                <div class="header-toogle-menu d-xl-none">
                    <button class="toggle-menu-button aside-toggle border-0 bg-transparent p-0 dark-color">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                </div>
                <!-- End Header Menu -->
            </div>
            <div class="col-10">
                <!-- Header Right -->
                <div class="header-right">
                    <ul class="nav justify-content-end align-items-center gap-30">
                        <li class="d-none d-md-block">
                        </li>
                        @if(externalConfig('activation_mode')?->value ?? 0)
                            <li>
                                <form method="POST"
                                      action="{{url(externalConfig( 'mart_base_url')?->value ."/external-login-from-drivemond")}}"
                                      target="_blank">
                                    @csrf
                                    <input type="hidden" name="drivemond_token"
                                           value="{{externalConfig('system_self_token')?->value ?? null}}">
                                    <input type="hidden" name="drivemond_base_url" value="{{url('/')}}">
                                    <input type="hidden" name="mart_token"
                                           value="{{externalConfig('mart_token')?->value ?? null}}">
                                    <button type="submit" class="btn btn-primary gap-2 cl-mart">
                                        <img
                                            src="{{externalConfig('mart_business_logo')?->value?? dynamicAsset('public/assets/admin-module/img/mart-icon.png')}}"
                                            alt="" width="15" height="15">
                                        {{ (externalConfig('mart_business_name')?->value ?? "6amMart").' '.translate('Admin Panel')}}
                                    </button>
                                </form>
                            </li>
                        @endif
                        <li>
                            <!-- Safety Notification -->
                            <div class="safety-alert" id="safetyAlert">

                            </div>
                            <!-- End Safety Notification -->
                        </li>
                        <li>
                            <!-- Header Localization -->
                            <div class="messages">
                                @php($languages = businessConfig(SYSTEM_LANGUAGE)?->value ?? [['code' => 'en']])
                                <a href="#" class="header-icon count-btn" data-bs-toggle="dropdown">
                                    <img class="dark-support" loading="lazy"
                                         src="{{ dynamicAsset('public/assets/admin-module/img/language.png') }}" height="24px"
                                         width="24px" alt="language">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li class="d-flex flex-column gap-2">
                                        @forelse($languages as $language)
                                            <a href="{{ route('lang', [$language['code']]) }}"
                                               class="dropdown-item media gap-3 align-items-center {{session()->get('locale')== $language['code'] ? "active" : ''}}">
                                                <div class="media-body ">
                                                    <span class="card-text fz-12">{{ $language['code'] }}</span>
                                                </div>
                                            </a>
                                        @empty
                                        @endforelse
                                    </li>
                                </ul>
                            </div>
                            <!-- End Main Header Localization -->
                        </li>
                        <li>
                            <!-- Notification -->
                            <div class="notification" id="notification">

                            </div>
                            <!-- End Notification -->
                        </li>
                        <li>
                            <!-- User -->
                            <div class="user mt-n1">
                                <a href="#" class="avatar avatar-sm rounded-circle" data-bs-toggle="dropdown">
                                    <img src="{{ onErrorImage(
                                        auth()->user()?->profile_image,
                                        dynamicStorage('storage/app/public/employee/profile') . '/' . auth()->user()->profile_image,
                                        dynamicAsset('public/assets/admin-module/img/user.png'),
                                        'employee/profile/',
                                    ) }}"
                                         loading="lazy" class="fit-object dark-support rounded-circle" alt="">
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a href="{{ route('admin.settings') }}"
                                       class="dropdown-item-text media gap-3 align-items-center">
                                        <div class="avatar avatar-sm rounded-circle">

                                            <img class="fit-object dark-support rounded-circle" loading="lazy"
                                                 src="{{ onErrorImage(
                                                    auth()->user()?->profile_image,
                                                    dynamicStorage('storage/app/public/employee/profile') . '/' . auth()->user()->profile_image,
                                                    dynamicAsset('public/assets/admin-module/img/user.png'),
                                                    'employee/profile/',
                                                ) }}"
                                                 alt="admin_image">
                                        </div>
                                        <div class="media-body ">
                                            <h6 class="card-title">{{ auth()->user()?->first_name }}</h6>
                                            <span class="card-text">{{ auth()->user()?->email }}</span>
                                        </div>
                                    </a>
                                    <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                        <span class="text-truncate"
                                              title="Settings">{{ translate('settings') }}</span>
                                    </a>
                                    <a class="dropdown-item" href="{{ route('admin.auth.logout') }}">
                                        <span class="text-truncate text-capitalize"
                                              title="Sign Out">{{ translate('sign_out') }}</span>
                                    </a>
                                </div>
                            </div>
                            <!-- End User -->
                        </li>
                    </ul>
                </div>
                <!-- End Header Right -->
            </div>
        </div>
    </div>
</header>
