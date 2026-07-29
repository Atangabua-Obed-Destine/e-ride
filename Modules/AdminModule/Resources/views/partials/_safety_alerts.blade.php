<a href="{{ $route }}" class="count-btn d-block show-safety-alert-user-details" data-user-id="{{ $safetyAlertUserId }}">
    <div class="bg-primary d-flex px-1 circle-24 max-content header-guard-hover position-relative" style="--size: 20px">
        @if($safetyAlerts->count() != 0)
            <span class="fw-semibold fs-12 text-white ">{{ $safetyAlerts->count() . translate(' Safety Alert Arrived') }}</span>
        @endif

        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
            <g clip-path="url(#clip0_5834_52140)">
                <path
                    d="M9.2905 1.07L6.158 0.0254983C6.05544 -0.00866221 5.94457 -0.00866221 5.842 0.0254983L2.709 1.07C2.21105 1.23552 1.77791 1.55375 1.47112 1.97947C1.16433 2.40519 0.999484 2.91675 1 3.4415V6C1 9.7815 5.6 11.87 5.797 11.957C5.86091 11.9854 5.93007 12.0001 6 12.0001C6.06993 12.0001 6.13909 11.9854 6.203 11.957C6.4 11.87 11 9.7815 11 6V3.4415C11.0005 2.91669 10.8356 2.40508 10.5287 1.97935C10.2218 1.55363 9.78855 1.23544 9.2905 1.07Z"
                    fill="white"/>
                <path
                    d="M6.00053 9.81827C5.69929 9.81827 5.45508 9.57406 5.45508 9.27282V5.45463C5.45508 5.15339 5.69929 4.90918 6.00053 4.90918C6.30178 4.90918 6.54599 5.15339 6.54599 5.45463V9.27282C6.54599 9.57406 6.30178 9.81827 6.00053 9.81827Z"
                    fill="#FF2F2F"/>
                <path
                    d="M5.46897 3.47154C5.61679 3.61771 5.79397 3.6908 6.00049 3.6908C6.20701 3.6908 6.3831 3.61771 6.52875 3.47154C6.67658 3.32305 6.75049 3.14555 6.75049 2.93906C6.75049 2.73488 6.67658 2.55971 6.52875 2.41353C6.3831 2.26504 6.20701 2.1908 6.00049 2.1908C5.79397 2.1908 5.61679 2.26504 5.46897 2.41353C5.32331 2.55971 5.25049 2.73488 5.25049 2.93906C5.25049 3.14555 5.32331 3.32305 5.46897 3.47154Z"
                    fill="#FF2F2F"/>
            </g>
            <defs>
                <clipPath id="clip0_5834_52140">
                    <rect width="12" height="12" fill="white"/>
                </clipPath>
            </defs>
        </svg>
    </div>
    @if($safetyAlerts->count() != 0)
        <span class="count">{{$safetyAlerts->count()}}</span>
    @endif
</a>
