<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpCategory extends Model
{
    /** @use HasFactory<\Database\Factories\HelpCategoryFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'slug',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_published' => 'boolean',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class, 'category_id');
    }
}
