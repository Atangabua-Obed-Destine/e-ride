{{--
    Hero backdrop: the hills of the city as faint contour lines, and one
    E-Ride route sweeping across the page toward the phone. The route draws
    itself once; a soft light then travels along it now and then.
    Purely decorative.
--}}
<div class="er-backdrop" aria-hidden="true">
    <svg class="er-backdrop-svg" viewBox="0 0 1440 760" preserveAspectRatio="xMidYMid slice">
        <defs>
            <linearGradient id="erSweep" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" style="stop-color: var(--er-red, #e02020); stop-opacity: 0"/>
                <stop offset="35%" style="stop-color: var(--er-red, #e02020); stop-opacity: .35"/>
                <stop offset="100%" style="stop-color: var(--er-red, #e02020); stop-opacity: .9"/>
            </linearGradient>
            <radialGradient id="erComet">
                <stop offset="0%" stop-color="#ffffff" stop-opacity="1"/>
                <stop offset="35%" stop-color="#ff8a8a" stop-opacity=".75"/>
                <stop offset="100%" stop-color="#e02020" stop-opacity="0"/>
            </radialGradient>
        </defs>

        {{-- contour lines --}}
        <g class="er-contours" fill="none" stroke="#ffffff" stroke-opacity=".045" stroke-width="1">
            <path d="M-40 520 C 160 440, 340 600, 560 520 S 900 420, 1120 500 S 1380 600, 1500 540"/>
            <path d="M-40 560 C 160 480, 340 640, 560 560 S 900 460, 1120 540 S 1380 640, 1500 580"/>
            <path d="M-40 600 C 160 520, 340 680, 560 600 S 900 500, 1120 580 S 1380 680, 1500 620"/>
            <path d="M-40 640 C 160 560, 340 720, 560 640 S 900 540, 1120 620 S 1380 720, 1500 660"/>
            <path d="M-40 120 C 200 60, 420 180, 700 110 S 1100 40, 1500 120"/>
            <path d="M-40 160 C 200 100, 420 220, 700 150 S 1100 80, 1500 160"/>
            <path d="M-40 200 C 200 140, 420 260, 700 190 S 1100 120, 1500 200"/>
            <path d="M860 330 C 940 270, 1080 270, 1150 330 S 1240 450, 1150 480 S 940 470, 880 420 S 820 370, 860 330Z"/>
            <path d="M900 340 C 960 300, 1060 300, 1110 345 S 1170 430, 1110 450 S 960 445, 915 410 S 875 370, 900 340Z"/>
        </g>

        {{-- the sweeping route --}}
        <path id="erSweepPath" class="er-sweep"
              d="M-60 700 C 220 690, 360 600, 520 610 S 760 690, 900 560 S 1040 330, 1190 300 S 1400 250, 1520 180"
              fill="none" stroke="url(#erSweep)" stroke-width="2.5" stroke-linecap="round"/>

        {{-- the light that travels along it --}}
        <circle class="er-comet" r="14" fill="url(#erComet)">
            <animateMotion dur="7s" begin="2.4s" repeatCount="indefinite"
                           keyPoints="0;1;1" keyTimes="0;0.7;1" calcMode="spline"
                           keySplines=".5 0 .5 1; 0 0 1 1">
                <mpath href="#erSweepPath"/>
            </animateMotion>
        </circle>
    </svg>
</div>
