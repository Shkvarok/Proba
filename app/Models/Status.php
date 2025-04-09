<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
    ];

    /**
     * Scope для фільтрації за типом статусу
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}