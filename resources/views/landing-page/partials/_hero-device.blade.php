{{--
    Hero visual: the E-Ride app itself, mid-trip. A phone drawn in HTML/CSS
    with an inline-SVG map: the car follows the route while the drop-off card
    counts the distance down (driven by the small script in index.blade.php).
    Shown when no Intro Section background image is uploaded.

    Everything is decorative (aria-hidden); the headline carries the message.
--}}
<div class="er-stage" aria-hidden="true" data-er-trip>

    {{-- floating: driver ETA --}}
    <div class="er-glass er-glass--eta">
        <span class="er-live"><span></span></span>
        <div>
            <strong><span data-er-eta>3</span> min</strong>
            <small>{{ translate('Your driver is on the way') }}</small>
        </div>
    </div>

    <div class="er-phone">
        <div class="er-phone-notch"></div>
        <div class="er-phone-screen">

            {{-- map --}}
            <svg class="er-phone-map" viewBox="0 0 300 430" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <linearGradient id="erHeroRouteGrad" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="#ff6b6b"/>
                        <stop offset="100%" style="stop-color: var(--er-red, #e02020)"/>
                    </linearGradient>
                    <radialGradient id="erPickupGlow">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity=".35"/>
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
                    </radialGradient>
                </defs>

                <rect width="300" height="430" fill="#141418"/>

                {{-- hills: soft contour lines --}}
                <g fill="none" stroke="#1c1c22" stroke-width="1.2">
                    <path d="M-20 70 C 60 40, 120 110, 200 80 S 320 40, 340 70"/>
                    <path d="M-20 96 C 60 66, 120 136, 200 106 S 320 66, 340 96"/>
                    <path d="M-20 330 C 50 300, 140 360, 210 330 S 320 300, 340 320"/>
                    <path d="M-20 356 C 50 326, 140 386, 210 356 S 320 326, 340 346"/>
                </g>

                {{-- streets: winding, like a hillside town --}}
                <g fill="none" stroke="#202027" stroke-linecap="round">
                    <path d="M-10 250 C 60 240, 90 200, 150 196 S 260 210, 320 170" stroke-width="13"/>
                    <path d="M40 440 C 60 360, 30 300, 70 250 S 110 120, 90 -10" stroke-width="11"/>
                    <path d="M200 440 C 190 380, 230 330, 220 270 S 240 120, 280 -10" stroke-width="11"/>
                    <path d="M-10 140 C 80 150, 160 120, 330 130" stroke-width="7"/>
                    <path d="M-10 390 C 90 380, 170 400, 330 370" stroke-width="7"/>
                </g>

                {{-- the trip --}}
                <path id="erHeroRoute" class="er-trip-route"
                      d="M70 372 C 66 330, 52 296, 72 256 S 132 200, 160 198 S 222 214, 226 168 S 238 108, 236 86"
                      fill="none" stroke="url(#erHeroRouteGrad)" stroke-width="5" stroke-linecap="round"/>

                {{-- pickup --}}
                <circle cx="70" cy="372" r="22" fill="url(#erPickupGlow)"/>
                <circle cx="70" cy="372" r="7" fill="#0b0b0d" stroke="#ffffff" stroke-width="3.5"/>

                {{-- destination --}}
                <g transform="translate(236 86)">
                    <circle r="16" style="fill: var(--er-red, #e02020)" opacity=".18" class="er-dest-ring"/>
                    <path d="M0 -26c8 0 14 6 14 14 0 10-14 22-14 22s-14-12-14-22c0-8 6-14 14-14Z" style="fill: var(--er-red, #e02020)"/>
                    <circle cy="-12" r="5" fill="#ffffff"/>
                </g>

                {{-- the car (top-down), following the route --}}
                <g class="er-trip-car">
                    <g transform="rotate(90)">
                        <rect x="-8" y="-14" width="16" height="28" rx="6" fill="#ffffff"/>
                        <rect x="-6" y="-7" width="12" height="7" rx="2" fill="#2a2a32"/>
                        <rect x="-6" y="5" width="12" height="4" rx="1.5" fill="#2a2a32"/>
                    </g>
                    <animateMotion dur="16s" repeatCount="indefinite" rotate="auto"
                                   keyPoints="0;1;1" keyTimes="0;0.88;1" calcMode="spline"
                                   keySplines=".45 0 .55 1; 0 0 1 1">
                        <mpath href="#erHeroRoute"/>
                    </animateMotion>
                </g>
            </svg>

            {{-- status bar of the app --}}
            <div class="er-app-top">
                <span class="er-app-chip"><span class="er-app-chip-dot"></span>{{ translate('Trip ongoing') }}</span>
            </div>

            {{-- bottom sheet --}}
            <div class="er-app-sheet">
                <div class="er-app-handle"></div>

                <div class="er-app-arrive">
                    <span class="er-app-flag">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 21V4h9l.5 2H20v9h-6.5l-.5-2H7v8H5Z"/></svg>
                    </span>
                    <div class="er-app-arrive-text">
                        <small>{{ translate('Distance to drop-off') }}</small>
                        <strong><span data-er-km>2.4 km</span> &middot; <span data-er-min>6</span> min</strong>
                    </div>
                </div>
                <div class="er-app-bar"><span data-er-progress></span></div>

                <div class="er-app-driver">
                    <span class="er-app-avatar">ER</span>
                    <div class="er-app-driver-text">
                        <strong>{{ translate('Your driver') }}</strong>
                        <small>{{ translate('Toyota Corolla') }} &middot; {{ translate('Standard') }}</small>
                    </div>
                    <span class="er-app-plate">NW 482 AB</span>
                </div>
            </div>
        </div>
    </div>

    {{-- floating: upfront fare --}}
    <div class="er-glass er-glass--fare">
        <small>{{ translate('Upfront fare') }}</small>
        <strong>FCFA 1,282</strong>
        <span>{{ translate('Know the price before you ride') }}</span>
    </div>
</div>
