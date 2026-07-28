<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'is_rtl',
        'enabled',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'enabled' => 'boolean',
    ];
}
