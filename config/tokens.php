<?php

// Single source of truth for all design tokens. Every Blade/CSS reference
// uses var(--<key>); never a raw literal. SiteSetting.branding overrides these.
return [
    'defaults' => [
        'color-primary' => '#2563eb',
        'color-accent' => '#7c3aed',
        'color-bg' => '#ffffff',
        'color-bg-alt' => '#f5f5f7',
        'color-fg' => '#111827',
        'color-heading' => '#111827',
        'color-muted' => '#6b7280',
        'font-base' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'font-heading' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'font-display' => "system-ui, -apple-system, 'Segoe UI', Roboto, serif",
        'space-unit' => '0.25rem',
        'radius' => '0.5rem',
        'shadow' => '0 1px 3px rgba(0,0,0,0.1)',
        'container-width' => '64rem',
        'nav-height' => '4rem',
        'hero-overlay' => 'rgba(0,0,0,0)',
    ],
];
