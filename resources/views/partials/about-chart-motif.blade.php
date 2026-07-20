{{-- Mini natal-chart wheel motif (static SVG, token-driven) — reused above the
     "Discover" and "Your Core" section eyebrows on the About page. --}}
<div class="about-chart-motif" aria-hidden="true">
    <svg class="about-chart-motif__svg" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
        {{-- Fire trine: Aries–Leo–Sagittarius --}}
        <polygon class="about-chart-motif__aspect" points="120,52 179,154 61,154" />
        {{-- Earth trine: Taurus–Virgo–Capricorn --}}
        <polygon class="about-chart-motif__aspect" points="154,61 154,179 52,120" />
        {{-- Air trine: Gemini–Libra–Aquarius --}}
        <polygon class="about-chart-motif__aspect" points="179,86 120,188 61,86" />
        {{-- Water trine: Cancer–Scorpio–Pisces --}}
        <polygon class="about-chart-motif__aspect" points="188,120 86,179 86,61" />

        <circle class="about-chart-motif__ring about-chart-motif__ring--inner" cx="120" cy="120" r="68" />
        <circle class="about-chart-motif__ring about-chart-motif__ring--outer" cx="120" cy="120" r="100" />

        @foreach ([0,30,60,90,120,150,180,210,240,270,300,330] as $deg)
            @php
                $rad = deg2rad($deg - 90);
                $x1 = 120 + 68 * cos($rad); $y1 = 120 + 68 * sin($rad);
                $x2 = 120 + 100 * cos($rad); $y2 = 120 + 100 * sin($rad);
            @endphp
            <line class="about-chart-motif__spoke" x1="{{ round($x1) }}" y1="{{ round($y1) }}" x2="{{ round($x2) }}" y2="{{ round($y2) }}" />
        @endforeach

        @foreach (['♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓'] as $i => $glyph)
            @php
                $rad = deg2rad($i * 30 - 90);
                $x = 120 + 84 * cos($rad); $y = 120 + 84 * sin($rad);
            @endphp
            <text class="about-chart-motif__glyph" x="{{ round($x) }}" y="{{ round($y) }}">{{ $glyph }}&#xFE0E;</text>
        @endforeach

        <circle class="about-chart-motif__sun" cx="120" cy="120" r="6" />
    </svg>
</div>
