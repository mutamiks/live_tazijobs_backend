<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UgandaLocation extends Model
{
    protected $fillable = ['district', 'county', 'subcounty', 'parish', 'village', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
