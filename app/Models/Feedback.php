<?php

namespace App\Models;

use App\Traits\SecureIdTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory, SecureIdTrait;

    protected $fillable = [
        'name',
        'title',
        'message',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // public function getImageAttribute($value)
    // {
    //     return $value ? env('APP_URL') . '/storage/' . $value : null;
    // }

    /**
     * Scope a query to only include active feedback.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
