<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FileUploadService
{
    /**
     * Upload course image - Windows compatible version
     */
    public function uploadCourseImage(UploadedFile $file, string $directory = 'course-covers'): string
    {
        try {
            // Створюємо директорію якщо не існує
            $this->ensureDirectoryExists($directory);
            
            // Генеруємо унікальне ім'я файлу
            $filename = $this->generateUniqueFilename($file);
            $fullPath = $directory . '/' . $filename;
            
            \Log::info('Attempting to upload course image:', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'target_path' => $fullPath,
                'storage_path' => storage_path('app/public/' . $fullPath)
            ]);
            
            // Метод 1: Спробувати простий Laravel store
            try {
                $storedPath = $file->storeAs($directory, $filename, 'public');
                
                if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                    \Log::info('File stored successfully using storeAs', [
                        'stored_path' => $storedPath,
                        'full_path' => storage_path('app/public/' . $storedPath),
                        'file_exists' => file_exists(storage_path('app/public/' . $storedPath)),
                        'file_size' => Storage::disk('public')->size($storedPath)
                    ]);
                    return $storedPath;
                }
            } catch (Exception $e) {
                \Log::warning('storeAs method failed: ' . $e->getMessage(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Метод 2: Ручне копіювання файлу
            try {
                $file->move(storage_path('app/public/' . $directory), $filename);
                \Log::info('File moved successfully using move method', [
                    'target_path' => $fullPath,
                    'file_exists' => file_exists(storage_path('app/public/' . $fullPath))
                ]);
                return $fullPath;
            } catch (Exception $e) {
                \Log::error('Both file upload methods failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            \Log::error('Failed to upload course image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        $path = storage_path('app/public/' . $directory);
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Ensure directory exists for file
     */
    protected function ensureDirectoryExistsForFile(string $filePath): void
    {
        $directory = dirname($filePath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return time() . '_' . Str::random(10) . '.' . $extension;
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $path): bool
    {
        try {
            // Спробувати Laravel Storage
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }
            
            // Спробувати пряме видалення
            $fullPath = storage_path('app/public/' . $path);
            if (file_exists($fullPath)) {
                return unlink($fullPath);
            }
            
            return true; // Файл вже не існує
        } catch (Exception $e) {
            \Log::error('Error deleting file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    /**
     * Validate file
     */
    public function validateFile(UploadedFile $file, array $allowedMimes = null, int $maxSizeKB = 2048): bool
    {
        // Перевірка валідності
        if (!$file->isValid()) {
            return false;
        }
        
        // Перевірка MIME типу
        if ($allowedMimes && !in_array($file->getMimeType(), $allowedMimes)) {
            return false;
        }
        
        // Перевірка розміру
        if ($file->getSize() > $maxSizeKB * 1024) {
            return false;
        }
        
        return true;
    }

    /**
     * Get file info for debugging
     */
    public function getFileInfo(string $path): array
    {
        $fullPath = storage_path('app/public/' . $path);
        
        return [
            'path' => $path,
            'full_path' => $fullPath,
            'exists_storage' => Storage::disk('public')->exists($path),
            'exists_filesystem' => file_exists($fullPath),
            'size_storage' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null,
            'size_filesystem' => file_exists($fullPath) ? filesize($fullPath) : null,
            'url' => Storage::disk('public')->url($path),
            'directory_exists' => is_dir(dirname($fullPath)),
            'directory_writable' => is_writable(dirname($fullPath))
        ];
    }
}