<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use App\Models\HelpMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HelpMedia>
 */
class HelpMediaFactory extends Factory
{
    protected $model = HelpMedia::class;

    public function definition(): array
    {
        return [
            'article_id' => HelpArticle::factory(),
            'path' => 'help-media/system/'.Str::ulid().'.png',
            'alt' => fake()->optional()->sentence(3),
        ];
    }
}
