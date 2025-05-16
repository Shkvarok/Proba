<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'country_id',
        'phone_number',
        'email',
        'password',
        'name',
        'last_name',
        'avatar',
        'role_id',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    
    /**
     * Отримати країну користувача
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    
    /**
     * Отримати роль користувача
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
    
    /**
     * Перевірити чи користувач має певну роль
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }
    
    /**
     * Перевірити чи користувач має якусь із ролей
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->role && in_array($this->role->name, $roleNames);
    }
    
    
    /**
     * Отримати повне ім'я користувача з урахуванням прізвища
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->name ?? '';
        $lastName = $this->last_name ?? '';
        
        return trim($name . ' ' . $lastName);
    }
    
    /**
     * Перевірити чи користувач є адміністратором
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Підписки користувача на курси
     */
    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Платежі користувача
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Доступні курси користувача
     */
    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
            ->withPivot(['enrolled_at', 'expires_at', 'enrollment_type', 'is_active'])
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->wherePivot('expires_at', null)
                      ->orWherePivot('expires_at', '>', now());
            });
    }
    /**
 * Отримати відгуки користувача
 */
public function reviews()
{
    return $this->hasMany(Review::class);
}

/**
 * Отримати коментарі користувача до відгуків
 */
public function reviewComments()
{
    return $this->hasMany(ReviewComment::class);
}
}