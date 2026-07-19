<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Set once, the first time a post is published, and never cleared
            // again (unlike published_at, which resets to null when a post is
            // sent back to draft). Used to permanently lock slug regeneration
            // once a post has ever gone live.
            $table->timestamp('first_published_at')->nullable()->after('published_at');
        });

        // Backfill posts that were already published before this column
        // existed, so they're correctly treated as "ever published" too.
        DB::table('posts')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update(['first_published_at' => DB::raw('published_at')]);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('first_published_at');
        });
    }
};
