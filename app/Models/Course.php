<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'instructor_id',
        'price',
        'discount_price',
        'discount_expires_at',
        'level_id',
        'language',
        'cover_image',
        'promo_video_url',
        'requirements',
        'what_you_learn',
        'is_published',
        'meta_title',
        'meta_description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'discount_expires_at' => 'datetime',
        'is_published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the category that owns the course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the instructor that owns the course.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the level that owns the course.
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * Scope a query to only include published courses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to include courses with expired discounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithValidDiscount($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('discount_expires_at')
              ->orWhere('discount_expires_at', '>', now());
        })->whereNotNull('discount_price');
    }

    /**
     * Determine if the course is on discount.
     *
     * @return bool
     */
    public function isOnDiscount(): bool
    {
        if (is_null($this->discount_price)) {
            return false;
        }

        if (is_null($this->discount_expires_at)) {
            return true;
        }

        return $this->discount_expires_at > now();
    }

    /**
     * Get the current price (considering discount).
     *
     * @return float
     */
    public function getCurrentPrice(): float
    {
        if ($this->isOnDiscount()) {
            return (float) $this->discount_price;
        }

        return (float) $this->price;
    }
}