<?php

namespace Database\Factories;

use App\Enums\HelpArticleStatus;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    protected $model = HelpArticle::class;

    public function definition(): array
    {
        return [
            'category_id' => HelpCategory::factory(),
            'slug' => fake()->unique()->slug(3),
            'status' => HelpArticleStatus::Draft,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_public' => false,
            'published_at' => null,
            'updated_by_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => HelpArticleStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => HelpArticleStatus::Archived,
            'published_at' => now()->subDay(),
        ]);
    }

    public function publicGuest(): static
    {
        return $this->state(fn (): array => ['is_public' => true]);
    }
}
