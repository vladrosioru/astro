<?php

// Admin Database page: content backups, and prod -> dev restore on the dev
// subdomain. See docs/superpowers/specs/2026-07-24-admin-database-page-design.md.
return [

    /*
     * Fail-closed restore gate. Both boxes deploy the same artifact with
     * APP_ENV=production (.github/scripts/make-env.sh:14), so the framework
     * cannot tell dev from prod. This flag is the only thing that can, and a
     * missing value means disabled.
     */
    'restore_enabled' => (bool) env('DB_RESTORE_ENABLED', false),

    /*
     * Absolute origin that dev rewrites media paths to, e.g.
     * https://astrotherapia.com. Media URLs are stored root-relative
     * (AttachmentController.php:38-41), so prod content copied to dev would
     * otherwise point at files dev does not have. Null means no rewrite.
     */
    'media_fallback_url' => env('MEDIA_FALLBACK_URL'),

    // Backups kept on disk; older files are pruned after each new backup.
    'retention' => 10,

    /*
     * Content tables only. users/sessions/cache/jobs are deliberately excluded
     * so a mistaken restore cannot damage accounts or log anyone out.
     *
     * Order matters: parents first. Inserts follow this order; deletes use the
     * reverse, so post_translations goes before its parent posts, and authors
     * (referenced by posts.author_id) is inserted first and deleted last.
     */
    'tables' => ['authors', 'posts', 'post_translations', 'media', 'site_settings'],
];
