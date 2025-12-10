<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineSection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discipline_id',
        'content',
        'image',
        'caption',
        'order',
    ];

    /**
     * Get the discipline that owns the section.
     */
    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }
}
