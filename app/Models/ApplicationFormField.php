<?php

namespace App\Models;

use App\Enums\FormFieldType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFormField extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'form_id',
        'label',
        'type',
        'required',
        'order',
        'options',
        'allowed_file_types',
        'admin_note',
    ];

    protected $casts = [
        'type' => FormFieldType::class,
        'required' => 'boolean',
        'order' => 'integer',
        'options' => 'array',
        'allowed_file_types' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'form_id');
    }
}
