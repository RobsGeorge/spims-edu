<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Week extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'offering_id',
        'number',
        'title',
        'unlock_date',
        'order',
    ];

    protected $casts = [
        'number' => 'integer',
        'order' => 'integer',
        'unlock_date' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class)->orderBy('order');
    }
}
