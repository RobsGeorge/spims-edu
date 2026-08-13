<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentItemCompletion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'enrollment_id',
        'content_item_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
