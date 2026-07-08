{{-- Compact, non-interactive reuse of the Home hero's orbiting-planets motif —
     no nav/title/copy, just the solar system, for use as a section divider on
     inner pages (e.g. @includeIf('theme::solar-motif') on pages/about.blade.php).
     Sizing/positioning overrides live under .stage--motif in css/hero.css. --}}
<div class="stage stage--motif" aria-hidden="true">
    <div class="solar-wrap" data-parallax="solar">
        <div class="plane">
            <div class="orbit orbit-1"><div class="anchor"><div class="planet planet-mercury"></div></div></div>
            <div class="orbit orbit-2"><div class="anchor"><div class="planet planet-venus"></div></div></div>
            <div class="orbit orbit-3"><div class="anchor"><div class="planet planet-earth"></div></div></div>
            <div class="orbit orbit-4"><div class="anchor"><div class="planet planet-mars"></div></div></div>
            <div class="orbit orbit-5"><div class="anchor"><div class="planet planet-saturn">
                <span class="saturn-ring"></span><span class="saturn-body"></span>
            </div></div></div>
        </div>
        <div class="sun"></div>
    </div>
</div>
