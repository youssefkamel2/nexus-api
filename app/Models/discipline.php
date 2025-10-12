<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discipline extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
