<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use App\Models\HelpArticleLocale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpArticleLocale>
 */
class HelpArticleLocaleFactory extends Factory
{
    protected $model = HelpArticleLocale::class;

    public function definition(): array
    {
        return [
            'article_id' => HelpArticle::factory(),
            'locale' => 'en',
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(10),
            'body_markdown' => "## Overview\n\n".fake()->paragraphs(2, true),
        ];
    }
}
