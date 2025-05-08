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
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
        
    /**
     * Перевірити чи користувач має якусь із ролей
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return in_array($this->role->name, $roleNames);
    }
    
    /**
     * Перевірити чи користувач має певний дозвіл
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->role->hasPermission($permissionSlug);
    }
    
    /**
     * Отримати повне ім'я користувача з урахуванням прізвища
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }
    
    /**
     * Перевірити чи користувач є адміністратором
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }
    
    /**
     * Оскільки name тепер є окремим полем, цей мутатор більше не потрібен
     * але якщо ви хочете зберегти зворотну сумісність з кодом, який використовує
     * $user->name, але при цьому зберігати ім'я в полі first_name, 
     * ви можете залишити цей метод
     */
    /*
    public function getNameAttribute()
    {
        return $this->attributes['name'] ?? '';
    }
    */
}