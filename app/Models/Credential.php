<?php

namespace App\Models;

use App\Enums\CredentialType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'type',
        'program_id',
        'offering_id',
        'serial',
        'qr_token',
        'language',
        'signatory_name',
        'signatory_title',
        'file_url',
        'issued_at',
        'revoked_at',
    ];

    protected $casts = [
        'type' => CredentialType::class,
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }

    public function verifyUrl(): string
    {
        return url('/verify/'.$this->qr_token);
    }
}
