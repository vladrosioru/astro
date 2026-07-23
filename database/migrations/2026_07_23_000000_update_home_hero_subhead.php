<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_SUBHEAD = 'When the planets align, so do the patterns within you. Read the map you were born under and move with intention.';

    private const NEW_SUBHEAD = 'Your birth chart is the key to help you understand why you think, feel, and choose the way you do — so you can make your next decision with clarity, not guesswork.';

    public function up(): void
    {
        DB::table('site_settings')->whereNotNull('hero')->get()->each(function ($row) {
            $hero = json_decode($row->hero, true);

            if (($hero['subhead'] ?? null) === self::OLD_SUBHEAD) {
                $hero['subhead'] = self::NEW_SUBHEAD;

                DB::table('site_settings')->where('id', $row->id)->update([
                    'hero' => json_encode($hero),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('site_settings')->whereNotNull('hero')->get()->each(function ($row) {
            $hero = json_decode($row->hero, true);

            if (($hero['subhead'] ?? null) === self::NEW_SUBHEAD) {
                $hero['subhead'] = self::OLD_SUBHEAD;

                DB::table('site_settings')->where('id', $row->id)->update([
                    'hero' => json_encode($hero),
                ]);
            }
        });
    }
};
