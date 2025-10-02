<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class setting extends Model
{
    use HasFactory, SecureIdTrait;

    protected $fillable = [
        'our_mission',
        'our_vision',
        'years',
        'projects',
        'clients',
        'engineers',
        'image',
    ];

    protected $casts = [
        'image' => 'string',
    ];

    public function getImageAttribute($value)
    {
        return asset('storage/' . $value);
    }

}
