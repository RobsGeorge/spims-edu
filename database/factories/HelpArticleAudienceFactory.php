<?php

namespace Database\Factories;

use App\Enums\RoleType;
use App\Models\HelpArticle;
use App\Models\HelpArticleAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpArticleAudience>
 */
class HelpArticleAudienceFactory extends Factory
{
    protected $model = HelpArticleAudience::class;

    public function definition(): array
    {
        return [
            'article_id' => HelpArticle::factory(),
            'role' => RoleType::Student,
        ];
    }
}
