<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Database\BackupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['app.url' => 'https://example.com']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<string, int>|null  $rows */
    private function putBackup(string $name, ?array $rows = ['authors' => 0, 'posts' => 41, 'post_translations' => 82, 'media' => 210, 'site_settings' => 1]): string
    {
        $header = "-- Content backup\n-- created: 2026-01-01T09:30:00+00:00\n";

        if ($rows !== null) {
            $header .= '-- rows: '.collect($rows)->map(fn (int $n, string $table) => "{$table}={$n}")->implode(', ')."\n";
        }

        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, (string) gzencode($header));

        return $name;
    }

    public function test_the_confirmation_page_shows_backup_counts_beside_live_ones(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee($name)
            ->assertSeeInOrder(['Table', 'In backup', 'Live now'])
            ->assertSeeInOrder(['posts', '41']);
    }

    public function test_the_confirmation_page_asks_for_the_host(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('to confirm');
    }

    public function test_a_backup_from_another_host_cannot_be_confirmed(): void
    {
        $name = $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertNotFound();
    }

    public function test_a_missing_backup_cannot_be_confirmed(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/backup-20260101-000000-example.com-manual.sql.gz')
            ->assertNotFound();
    }

    public function test_the_confirmation_page_rejects_a_traversal_attempt(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/restore/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_guests_cannot_reach_the_confirmation_page(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->get('/admin/database/restore/'.$name)->assertRedirect('/admin/login');
    }

    public function test_a_backup_without_row_counts_says_so_and_stays_restorable(): void
    {
        $name = $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz', null);

        $this->actingAs($this->admin())
            ->get('/admin/database/restore/'.$name)
            ->assertOk()
            ->assertSee('Row counts are unavailable');
    }

    public function test_the_listing_offers_restore_for_an_own_host_backup(): void
    {
        $this->putBackup('backup-20260101-000000-example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Restore');
    }

    public function test_the_listing_does_not_offer_restore_for_a_foreign_backup(): void
    {
        $this->putBackup('backup-20260101-000000-dev.example.com-manual.sql.gz');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertDontSee('Restore');
    }
}
