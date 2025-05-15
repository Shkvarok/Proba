<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Subscription;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_id',
        'entity_type',
        'entity_id',
    ];

    /**
     * Користувач, який здійснив платіж
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Підписка на курс, пов'язана з платежем
     */
    public function enrollment(): HasOne
    {
        return $this->hasOne(CourseEnrollment::class);
    }

    /**
     * Динамічне отримання пов'язаної сутності
     */
    public function entity()
    {
        if ($this->entity_type === 'course') {
            return $this->belongsTo(Course::class, 'entity_id');
        }
            
        return null;
    }
}