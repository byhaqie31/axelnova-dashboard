<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'excerpt' => fake()->paragraph(2),
            'sections' => BlogPost::normaliseSections([
                ['heading' => 'Notice what keeps repeating', 'body_md' => fake()->paragraph(3)],
                ['heading' => 'Keep the human part', 'body_md' => fake()->paragraph(3)],
            ]),
            'cover_image_url' => null,
            'cover_image_alt' => null,
            'category' => 'Systems',
            'format' => 'article',
            'tags' => ['portal', 'whatsapp'],
            'reading_minutes' => 2,
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'created_by' => User::factory()->founder(),
        ];
    }

    /** Publicly visible, published at `$at` (default now). */
    public function published(?string $at = null): static
    {
        return $this->state([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $at ?? now(),
        ]);
    }
}
