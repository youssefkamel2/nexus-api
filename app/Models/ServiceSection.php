<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceSection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_id',
        'content',
        'image',
        'caption',
        'order',
    ];

    /**
     * Get the service that owns the section.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
