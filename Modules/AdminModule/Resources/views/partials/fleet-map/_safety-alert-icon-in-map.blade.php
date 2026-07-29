@if($safetyAlertCount)
    <div class="ripple-effect bg-white circle-24 shadow-lg position-absolute map-guard-btn cursor-pointer" id="safetyAlertMapIcon">
        <a href="{{ $safetyAlertLatestUserRoute }}" class="show-safety-alert-user-details" data-user-id="{{ $safetyAlertUserId }}">
            <div class="circle-24 bg-white" style="--size: 50px;">
                <img class="svg" src="{{dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg')}}" alt="">
            </div>
        </a>
    </div>

    <div class="ripple-effect bg-danger circle-24 shadow-lg position-absolute map-guard-btn cursor-pointer d-none" id="newSafetyAlertMapIcon">
        <a href="{{ $safetyAlertLatestUserRoute }}" class="show-safety-alert-user-details" data-user-id="{{ $safetyAlertUserId }}">
            <div class="circle-24 bg-danger" style="--size: 50px">
                <img class="svg" src="{{dynamicAsset('public/assets/admin-module/img/svg/shield-white.svg')}}" alt="">
            </div>
        </a>
    </div>
@endif
