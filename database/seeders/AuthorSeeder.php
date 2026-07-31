<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        $author = Author::updateOrCreate(
            ['name' => 'Andrei | AstroTherapia'],
            [
                'description' => 'Associate Member of Faculty of Astrological Studies - London, UK',
                'picture' => 'img/logo-nav.png',
            ],
        );

        Post::whereNull('author_id')->update(['author_id' => $author->id]);
    }
}
