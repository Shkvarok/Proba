<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestMediaService
{
    /**
     * Дозволені типи зображень
     */
    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
    ];

    /**
     * Дозволені типи відео
     */
    const ALLOWED_VIDEO_TYPES = [
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/webm'
    ];

    /**
     * Максимальний розмір файлу в байтах (50MB)
     */
    const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * Завантажити медіафайл для питання
     */
    public function uploadQuestionMedia(UploadedFile $file): array
    {
        $this->validateFile($file);
        
        $mediaType = $this->getMediaType($file);
        $fileName = $this->generateFileName($file);
        $storagePath = "tests/questions/{$mediaType}s/" . date('Y/m');
        
        $filePath = $file->storeAs($storagePath, $fileName, 'public');
        
        return [
            'media_type' => $mediaType,
            'media_path' => $filePath,
            'media_original_name' => $file->getClientOriginalName(),
            'media_size' => $file->getSize(),
            'media_mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * Завантажити медіафайл для відповіді
     */
    public function uploadAnswerMedia(UploadedFile $file): array
    {
        $this->validateFile($file);
        
        $mediaType = $this->getMediaType($file);
        $fileName = $this->generateFileName($file);
        $storagePath = "tests/answers/{$mediaType}s/" . date('Y/m');
        
        $filePath = $file->storeAs($storagePath, $fileName, 'public');
        
        return [
            'media_type' => $mediaType,
            'media_path' => $filePath,
            'media_original_name' => $file->getClientOriginalName(),
            'media_size' => $file->getSize(),
            'media_mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * Видалити медіафайл
     */
    public function deleteMedia(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }

        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }

    /**
     * Валідація файлу
     */
    private function validateFile(UploadedFile $file): void
    {
        // Перевірка розміру файлу
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Файл занадто великий. Максимальний розмір: 50MB');
        }

        // Перевірка типу файлу
        $mimeType = $file->getMimeType();
        $allowedTypes = array_merge(self::ALLOWED_IMAGE_TYPES, self::ALLOWED_VIDEO_TYPES);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new \InvalidArgumentException('Недозволений тип файлу. Дозволені: зображення (JPEG, PNG, GIF, WebP) та відео (MP4, AVI, MOV, WMV, WebM)');
        }

        // Перевірка на валідність файлу
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Файл пошкоджений або не завантажився правильно');
        }
    }

    /**
     * Визначити тип медіафайлу
     */
    private function getMediaType(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();
        
        if (in_array($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return 'image';
        }
        
        if (in_array($mimeType, self::ALLOWED_VIDEO_TYPES)) {
            return 'video';
        }
        
        throw new \InvalidArgumentException('Невизначений тип медіафайлу');
    }

    /**
     * Згенерувати унікальне ім'я файлу
     */
    private function generateFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y_m_d_H_i_s');
        $random = Str::random(8);
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Отримати інформацію про файл
     */
    public function getFileInfo(string $filePath): ?array
    {
        if (!Storage::disk('public')->exists($filePath)) {
            return null;
        }

        return [
            'size' => Storage::disk('public')->size($filePath),
            'last_modified' => Storage::disk('public')->lastModified($filePath),
            'url' => Storage::disk('public')->url($filePath),
            'exists' => true,
        ];
    }

    /**
     * Перевірити, чи існує файл
     */
    public function fileExists(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }

        return Storage::disk('public')->exists($filePath);
    }

    /**
     * Скопіювати медіафайл (для дублювання тестів)
     */
    public function copyMedia(?string $originalPath, string $newDirectory = ''): ?array
    {
        if (!$originalPath || !$this->fileExists($originalPath)) {
            return null;
        }

        $originalInfo = pathinfo($originalPath);
        $extension = $originalInfo['extension'] ?? 'tmp';
        $timestamp = now()->format('Y_m_d_H_i_s');
        $random = Str::random(8);
        $newFileName = "{$timestamp}_{$random}.{$extension}";
        
        $newPath = $newDirectory ? 
            $newDirectory . '/' . $newFileName :
            $originalInfo['dirname'] . '/' . $newFileName;

        if (Storage::disk('public')->copy($originalPath, $newPath)) {
            $fileInfo = $this->getFileInfo($newPath);
            
            return [
                'media_path' => $newPath,
                'media_size' => $fileInfo['size'],
            ];
        }

        return null;
    }

    /**
     * Отримати статистику використання медіафайлів
     */
    public function getMediaStats(): array
    {
        $questionMediaCount = \App\Models\TestQuestion::whereNotNull('media_path')->count();
        $answerMediaCount = \App\Models\QuestionAnswer::whereNotNull('media_path')->count();
        
        $totalSize = \App\Models\TestQuestion::whereNotNull('media_path')->sum('media_size') +
                    \App\Models\QuestionAnswer::whereNotNull('media_path')->sum('media_size');

        return [
            'question_media_count' => $questionMediaCount,
            'answer_media_count' => $answerMediaCount,
            'total_media_count' => $questionMediaCount + $answerMediaCount,
            'total_size_mb' => round($totalSize / (1024 * 1024), 2),
            'average_file_size_kb' => $questionMediaCount + $answerMediaCount > 0 ? 
                round($totalSize / (($questionMediaCount + $answerMediaCount) * 1024), 2) : 0,
        ];
    }
}