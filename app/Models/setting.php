<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SecureIdTrait;

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
        'portfolio',
        'image',
    ];

    protected $casts = [
        'image' => 'string',
    ];

    // public function getImageAttribute($value)
    // {
    //     return $value ? env('APP_URL') . '/storage/' . $value : null;
    // }

}
