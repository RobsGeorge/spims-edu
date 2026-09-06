<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'course_id',
        'locale',
        'title',
        'body',
        'background_path',
        'signature_path',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isGlobal(): bool
    {
        return $this->course_id === null;
    }
}
