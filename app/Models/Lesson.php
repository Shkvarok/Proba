<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'description',
        'type',
        'position',
        'status',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lecture(): HasOne
    {
        return $this->hasOne(LessonLecture::class);
    }

    public function test(): HasOne
    {
        return $this->hasOne(LessonTest::class);
    }

    public function extraMaterial(): HasOne
    {
        return $this->hasOne(LessonExtraMaterial::class);
    }

    // Метод для отримання пов'язаних даних в залежності від типу уроку
    public function details()
    {
        return match($this->type) {
            'lecture' => $this->lecture,
            'test' => $this->test,
            'extra_material' => $this->extraMaterial,
            default => null,
        };
    }

    // Метод для отримання курсу через модуль
    public function course()
    {
        return $this->module->course;
    }
}