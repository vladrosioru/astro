<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\SocialImageService;
use Illuminate\Console\Command;

class GenerateSocialImagesCommand extends Command
{
    protected $signature = 'posts:generate-social-images {--force : Regenerate for all posts even if they already have a social image}';

    protected $description = 'Generate 1200x630 social share images for posts';

    public function handle(SocialImageService $service): int
    {
        $query = Post::query();
        if (! $this->option('force')) {
            $query->whereNull('social_image');
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('No posts need social image generation.');

            return self::SUCCESS;
        }

        $this->info("Generating social images for {$posts->count()} posts...");

        foreach ($posts as $post) {
            $url = $service->generateForPost($post->featured_image);
            $post->update(['social_image' => $url]);
            $this->line("  ✓ Post #{$post->id}: {$url}");
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
