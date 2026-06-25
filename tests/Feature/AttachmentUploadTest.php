<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/attachments', [
            'file' => UploadedFile::fake()->image('photo.jpg', 2000, 1000),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertSame(1, Media::count());
        Storage::disk('public')->assertExists(Media::first()->path);

        // URL must be root-relative (portable across host/port/domain), not absolute.
        $url = $response->json('url');
        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('http', $url);
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post('/admin/attachments', [])->assertRedirect('/admin/login');
    }

    public function test_accepts_ckeditor_upload_field(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/attachments', [
            'upload' => UploadedFile::fake()->image('p.jpg', 800, 600),
        ])->assertOk()->assertJsonStructure(['url']);

        $this->assertSame(1, Media::count());
    }
}
