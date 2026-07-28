<?php

namespace App\Models;

use App\Enums\OfferingStaffRole;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingStaff extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'offering_staff';

    protected $fillable = [
        'offering_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => OfferingStaffRole::class,
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
