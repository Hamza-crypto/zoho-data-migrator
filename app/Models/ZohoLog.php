<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoLog extends Model
{
    protected $fillable = [
        'module',
        'internal_id',
        'zoho_record_id',
        'payload',
        'response',
        'success',
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
        'success'  => 'boolean',
    ];
}
