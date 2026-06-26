<?php

// Named token sets. Applied to SiteSetting.branding by `php artisan app:apply-theme`.
// This is the same :root override path the future admin Theme record will use.
return [
    'mystik' => [
        'color-bg'      => '#0a0a0f',
        'color-bg-alt'  => '#14141f',
        'color-fg'      => '#e8e6f0',
        'color-heading' => '#f0a23c',
        'color-primary' => '#f0a23c',
        'color-accent'  => '#c9962f',
        'color-muted'   => '#9a96ad',
        'font-display'  => "'Cinzel', serif",
        'font-base'     => "'EB Garamond', serif",
        'nav-height'    => '4.5rem',
        'hero-overlay'  => 'rgba(0,0,0,0.55)',
    ],
    'solarsystem' => [
        'color-bg'      => '#05060c',
        'color-bg-alt'  => '#0b1426',
        'color-fg'      => '#aab6c8',
        'color-heading' => '#f2f7fd',
        'color-muted'   => '#9aa6b8',
        'color-primary' => '#9dc1e6',
        'color-accent'  => '#dcebfb',
        'font-display'  => "'Cinzel', serif",
        'font-heading'  => "'Cormorant Garamond', serif",
        'font-base'     => "'Jost', system-ui, sans-serif",
        'nav-height'    => '4.5rem',
        'hero-overlay'  => 'rgba(0,0,0,0.45)',
    ],
];
