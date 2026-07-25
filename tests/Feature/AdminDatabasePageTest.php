<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Database\BackupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDatabasePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_dashboard_links_to_the_database_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Database');
    }

    public function test_database_page_renders_for_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('Backups');
    }

    public function test_guests_cannot_access_the_database_page(): void
    {
        $this->get('/admin/database')->assertRedirect('/admin/login');
    }

    public function test_admin_can_create_a_backup(): void
    {
        Storage::fake('backups');

        $this->actingAs($this->admin())
            ->post('/admin/database/backup')
            ->assertRedirect('/admin/database');

        $this->assertCount(1, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_the_page_lists_existing_backups(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/backup-20260101-000000-example.com-manual.sql.gz', 'x');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('backup-20260101-000000-example.com-manual.sql.gz');
    }

    public function test_backups_are_listed_in_a_table(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/backup-20260101-000000-example.com-manual.sql.gz', 'x');

        $this->actingAs($this->admin())
            ->get('/admin/database')
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSeeInOrder(['Backup', 'Origin', 'Size', 'backup-20260101-000000-example.com-manual.sql.gz']);
    }

    public function test_admin_can_download_a_backup(): void
    {
        Storage::fake('backups');
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');

        $this->actingAs($this->admin())
            ->get('/admin/database/backup/'.$name)
            ->assertOk()
            ->assertDownload($name);
    }

    public function test_download_rejects_a_path_traversal_attempt(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/database/backup/..%2F..%2F.env')
            ->assertNotFound();
    }

    public function test_admin_can_delete_a_backup(): void
    {
        Storage::fake('backups');
        $name = 'backup-20260101-000000-example.com-manual.sql.gz';
        Storage::disk('backups')->put(BackupRepository::DIRECTORY.'/'.$name, 'x');

        $this->actingAs($this->admin())
            ->delete('/admin/database/backup/'.$name)
            ->assertRedirect('/admin/database');

        $this->assertCount(0, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_creating_a_backup_prunes_beyond_the_retention_limit(): void
    {
        Storage::fake('backups');
        config(['database_admin.retention' => 2]);

        foreach (['20260101', '20260102', '20260103'] as $day) {
            Storage::disk('backups')->put(BackupRepository::DIRECTORY."/backup-{$day}-000000-example.com-manual.sql.gz", 'x');
        }

        $this->actingAs($this->admin())->post('/admin/database/backup');

        $this->assertCount(2, Storage::disk('backups')->files(BackupRepository::DIRECTORY));
    }

    public function test_guests_cannot_create_a_backup(): void
    {
        $this->post('/admin/database/backup')->assertRedirect('/admin/login');
    }
}
