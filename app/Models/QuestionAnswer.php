<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuestionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_question_id',
        'answer_text',
        'is_correct',
        'position',
        'media_type',
        'media_path',
        'media_original_name',
        'media_size',
        'media_mime_type',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'position' => 'integer',
        'media_size' => 'integer',
    ];

    /**
     * Питання, до якого належить ця відповідь
     */
    public function testQuestion(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class);
    }

    /**
     * Отримати URL медіафайлу відповіді
     */
    public function getMediaUrl(): ?string
    {
        if (!$this->media_path) {
            return null;
        }

        return Storage::disk('public')->url($this->media_path);
    }

    /**
     * Перевірити, чи має відповідь медіафайл
     */
    public function hasMedia(): bool
    {
        return !empty($this->media_path) && !empty($this->media_type);
    }

    /**
     * Видалити медіафайл відповіді
     */
    public function deleteMedia(): bool
    {
        if ($this->media_path && Storage::disk('public')->exists($this->media_path)) {
            Storage::disk('public')->delete($this->media_path);
        }

        $this->update([
            'media_type' => null,
            'media_path' => null,
            'media_original_name' => null,
            'media_size' => null,
            'media_mime_type' => null,
        ]);

        return true;
    }
}