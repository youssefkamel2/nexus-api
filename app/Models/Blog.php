<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SecureIdTrait;

class Blog extends Model
{
    use HasFactory, SecureIdTrait;

    protected $fillable = [
        'title',
        'slug',
        'cover_photo',
        'category',
        'content',
        'mark_as_hero',
        'is_active',
        'created_by',
        'tags',
        'headings',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailLogs()
    {
        return $this->hasMany(NewsletterEmailLog::class);
    }

    public function faqs()
    {
        return $this->hasMany(BlogFaq::class);
    }

    public function setTagsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['tags'] = implode(',', $value);
        } else {
            $this->attributes['tags'] = $value;
        }
    }

    public function getTagsAttribute($value)
    {
        if (!$value)
            return [];
        return array_filter(array_map('trim', explode(',', $value)));
    }

    public function setHeadingsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['headings'] = json_encode($value);
        } else {
            $this->attributes['headings'] = $value;
        }
    }

    public function getHeadingsAttribute($value)
    {
        if (!$value)
            return [];
        return json_decode($value, true) ?: [];
    }

    public function getCoverPhotoUrlAttribute()
    {
        return $this->cover_photo ? asset('storage/' . $this->cover_photo) : null;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeHero($query)
    {
        return $query->where('mark_as_hero', true);
    }
}