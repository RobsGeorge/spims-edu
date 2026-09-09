<?php

namespace Database\Factories;

use App\Models\HelpCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpCategory>
 */
class HelpCategoryFactory extends Factory
{
    protected $model = HelpCategory::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
