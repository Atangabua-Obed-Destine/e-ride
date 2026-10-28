{{--
    Hero illustration: a ride in progress on a city map.
    Pure inline SVG, so it needs no upload, scales to any width and stays
    crisp on phones. Colours follow the admin brand colour via currentColor
    on the wrapper (see .er-art in eride.css).

    If an Intro Section background image is uploaded, index.blade.php shows
    that photo instead of this.
--}}
<div class="er-art" aria-hidden="true">
    <svg viewBox="0 0 440 420" width="100%" height="100%" role="img" aria-label="{{ translate('A ride on its way across the city') }}">
        <defs>
            <linearGradient id="erSky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#1b1b21"/>
                <stop offset="100%" stop-color="#121216"/>
            </linearGradient>
            <linearGradient id="erRoute" x1="0" y1="1" x2="1" y2="0">
                <stop offset="0%" stop-color="#ff6b6b"/>
                <stop offset="100%" stop-color="#e02020"/>
            </linearGradient>
        </defs>

        {{-- map plate --}}
        <rect x="0" y="0" width="440" height="420" rx="26" fill="url(#erSky)"/>

        {{-- streets --}}
        <g stroke="#232329" stroke-width="16" stroke-linecap="round">
            <path d="M-20 96 H460"/>
            <path d="M-20 232 H460"/>
            <path d="M-20 350 H460"/>
            <path d="M92 -20 V440"/>
            <path d="M268 -20 V440"/>
        </g>
        <g stroke="#1d1d23" stroke-width="7" stroke-linecap="round">
            <path d="M-20 164 H460"/>
            <path d="M180 -20 V440"/>
            <path d="M356 -20 V440"/>
        </g>

        {{-- blocks --}}
        <g fill="#1a1a20">
            <rect x="20" y="24" width="52" height="52" rx="9"/>
            <rect x="112" y="24" width="48" height="52" rx="9"/>
            <rect x="200" y="116" width="48" height="36" rx="8"/>
            <rect x="288" y="252" width="52" height="78" rx="9"/>
            <rect x="20" y="252" width="52" height="78" rx="9"/>
            <rect x="376" y="116" width="46" height="36" rx="8"/>
        </g>

        {{-- the route --}}
        <path d="M70 330 C 70 268, 180 268, 180 196 S 300 128, 300 70"
              fill="none" stroke="url(#erRoute)" stroke-width="7" stroke-linecap="round"
              stroke-dasharray="620" stroke-dashoffset="620" class="er-route"/>

        {{-- pickup --}}
        <circle cx="70" cy="330" r="13" fill="#0b0b0d" stroke="#ffffff" stroke-width="5"/>

        {{-- destination pin --}}
        <g class="er-pin">
            <path d="M300 44c10 0 18 8 18 18 0 13-18 30-18 30s-18-17-18-30c0-10 8-18 18-18Z" fill="#e02020"/>
            <circle cx="300" cy="62" r="6.5" fill="#ffffff"/>
        </g>

        {{-- the car, mid route --}}
        <g class="er-car">
            <rect x="152" y="178" width="58" height="34" rx="12" fill="#ffffff"/>
            <path d="M162 178c3-9 8-13 17-13h12c9 0 14 4 17 13" fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round"/>
            <circle cx="166" cy="212" r="7" fill="#2a2a32"/>
            <circle cx="196" cy="212" r="7" fill="#2a2a32"/>
            <rect x="160" y="186" width="42" height="11" rx="5" fill="#c9ccd4"/>
        </g>

        {{-- status chip --}}
        <g class="er-chip">
            <rect x="24" y="356" width="246" height="44" rx="22" fill="#ffffff"/>
            <circle cx="50" cy="378" r="11" fill="#e02020"/>
            <path d="M45 378h10M50 373v10" stroke="#ffffff" stroke-width="2.6" stroke-linecap="round"/>
            <text x="72" y="383" font-family="'Plus Jakarta Sans', system-ui, sans-serif"
                  font-size="15" font-weight="700" fill="#17171c">{{ translate('Driver on the way') }}</text>
        </g>
    </svg>
</div>
