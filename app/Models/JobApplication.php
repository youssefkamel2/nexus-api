<?php

namespace App\Models;

use App\Traits\SecureIdTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory, SecureIdTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'linkedin_profile',
        'portfolio_website',
        'cover_letter',
        'resume_path',
        'portfolio_path',
        'additional_documents',
        'years_of_experience',
        'current_position',
        'current_company',
        'expected_salary',
        'availability',
        'willing_to_relocate',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'willing_to_relocate' => 'boolean',
        'expected_salary' => 'decimal:2',
        'additional_documents' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the job this application belongs to
     */
    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the admin who reviewed this application
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the full name of the applicant
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Scope a query to only include pending applications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include reviewed applications.
     */
    public function scopeReviewed($query)
    {
        return $query->whereNotNull('reviewed_at');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Mark application as reviewed
     */
    public function markAsReviewed($reviewerId, $status = null, $notes = null)
    {
        $this->update([
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId,
            'status' => $status ?? $this->status,
            'admin_notes' => $notes ?? $this->admin_notes,
        ]);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'reviewing' => 'blue',
            'shortlisted' => 'purple',
            'interview' => 'indigo',
            'hired' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }
}
