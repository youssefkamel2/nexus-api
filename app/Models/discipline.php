<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\SecureIdTrait;

class Discipline extends Model
{
    use HasFactory, SecureIdTrait;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_photo',
        'show_on_home',
        'order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_on_home' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the services associated with this discipline.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'discipline_service', 'discipline_id', 'service_id')
                    ->withTimestamps();
    }

    /**
     * Get the projects associated with this discipline.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'discipline_project', 'discipline_id', 'project_id')
                    ->withTimestamps();
    }

    /**
     * Get the sections for this discipline.
     */
    public function sections()
    {
        return $this->hasMany(DisciplineSection::class)->orderBy('order');
    }

    /**
     * Scope a query to only include disciplines shown on home.
     */
    public function scopeShowOnHome($query)
    {
        return $query->where('show_on_home', true);
    }

    /**
     * Scope a query to order disciplines by order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
