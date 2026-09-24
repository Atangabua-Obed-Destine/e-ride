<div class="card-header d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
    <div class="w-0 flex-grow-1">
        <h5 class="mb-2 fs-18 text-capitalize">{{ translate('Chatting_Setup') }}</h5>
        <div class="fs-14">
            {{ translate('When OFF the Chatting Setup driver can’t see any Chatting option.') }}
        </div>
    </div>
    <label class="switcher cmn_focus rounded-pill">
        <input class="switcher_input collapsible-card-switcher update-business-setting"
               id="chattingSetupStatus" tabindex="1"
               type="checkbox"
               name="chatting_setup_status"
               data-name="chatting_setup_status"
               data-type="{{CHATTING_SETTINGS}}"
               data-url="{{route('admin.business.setup.update-business-setting')}}"
               data-icon=" {{($settings->firstWhere('key_name', 'chatting_setup_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/chatting-off.png') : dynamicAsset('public/assets/admin-module/img/chatting-on.png')}}"
               data-title="{{translate('Are you sure')}}?"
               data-sub-title="{{($settings->firstWhere('key_name', 'chatting_setup_status')->value?? 0) == 1 ? translate('Do you want to turn OFF Chatting Option for driver & admin')."? ": translate('Do you want to turn ON Chatting Option for driver & admin')."? "}}"
               data-confirm-btn="{{($settings->firstWhere('key_name', 'chatting_setup_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
            {{($settings->firstWhere('key_name', 'chatting_setup_status')->value?? 0) == 1? 'checked' : ''}}
        >
        <span class="switcher_control"></span>
    </label>
</div>

<ul class="nav d-inline-flex nav--tabs bg-transparent nav--tabs__style2 px-3 pt-2 p-1 rounded bg-white">
    <li class="nav-item text-capitalize">
        <a href="{{route('admin.business.setup.chatting-setup.index',DRIVER)}}" class="nav-link {{Request::is('admin/business/setup/chatting-setup/driver') ? 'active' : ''}}">{{translate("Driver")}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.chatting-setup.index',SUPPORT)}}" class="nav-link {{Request::is('admin/business/setup/chatting-setup/support') ? 'active' : ''}}" >{{translate("Support Center")}}</a>
    </li>
</ul>
