<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFieldValue extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'field_id',
        'value',
        'file_url',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApplicationFormField::class, 'field_id');
    }
}
