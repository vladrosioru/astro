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

    public function test_gif_upload_keeps_its_animation_instead_of_being_flattened_to_png(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        // A real GIF blob (not UploadedFile::fake()->image(), which only fakes
        // JPEG-shaped bytes). GD can't read/write multi-frame GIF at all, so
        // routing any GIF through Intervention's GD-based resize/encode
        // pipeline always flattens animation to one frame — proven here by
        // asserting the stored file is byte-for-byte identical to what was
        // uploaded and keeps its .gif extension, meaning it was never
        // decoded/re-encoded at all (which is what preserves every frame).
        ob_start();
        imagegif(imagecreatetruecolor(2, 2));
        $gifBytes = ob_get_clean();

        $response = $this->actingAs($admin)->post('/admin/attachments', [
            'upload' => UploadedFile::fake()->createWithContent('animated.gif', $gifBytes),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $media = Media::first();
        $this->assertStringEndsWith('.gif', $media->path);
        $this->assertSame($gifBytes, Storage::disk('public')->get($media->path));
    }
}
