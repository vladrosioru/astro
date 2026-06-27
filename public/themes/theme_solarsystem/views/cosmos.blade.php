{{-- Shared cosmos backdrop: rendered once by the layout, behind every page.
     Starfield + nebula + twinkle (no solar system, no parallax/3D movement).
     The .twinkle node is populated by public/js/solarsystem.js. --}}
<div class="cosmos" aria-hidden="true">
    <div class="bg-base"></div>
    <div class="bg-layer"><div class="stars stars1"></div></div>
    <div class="bg-layer"><div class="stars stars2"></div></div>
    <div class="nebula"></div>
    <div class="twinkle"></div>
</div>
