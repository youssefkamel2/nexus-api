<?php

namespace App\Models;

use App\Traits\SecureIdTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory, SecureIdTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'location',
        'type',
        'key_responsibilities',
        'preferred_qualifications',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the author of the job
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all applications for this job
     */
    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get pending applications for this job
     */
    public function pendingApplications()
    {
        return $this->hasMany(JobApplication::class)->where('status', 'pending');
    }

    /**
     * Scope a query to only include active jobs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to get job by slug.
     */
    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope a query to filter by job type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by location.
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', '%' . $location . '%');
    }

    /**
     * Update applications count
     */
    public function updateApplicationsCount()
    {
        $this->applications_count = $this->applications()->count();
        $this->save();
    }
}
