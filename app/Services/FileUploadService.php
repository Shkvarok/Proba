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
            
            // Метод 1: Спробувати простий Laravel store
            try {
                $storedPath = $file->storeAs($directory, $filename, 'public');
                
                if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                    \Log::info('File stored successfully using storeAs', [
                        'stored_path' => $storedPath,
                        'full_path' => storage_path('app/public/' . $storedPath)
                    ]);
                    return $storedPath;
                }
            } catch (Exception $e) {
                \Log::warning('storeAs method failed: ' . $e->getMessage());
            }
            
            // Метод 2: Ручне копіювання файлу
            try {
                $fileContent = file_get_contents($file->getPathname());
                $saved = Storage::disk('public')->put($fullPath, $fileContent);
                
                if ($saved && Storage::disk('public')->exists($fullPath)) {
                    \Log::info('File stored successfully using manual copy', [
                        'path' => $fullPath,
                        'size' => Storage::disk('public')->size($fullPath)
                    ]);
                    return $fullPath;
                }
            } catch (Exception $e) {
                \Log::warning('Manual copy method failed: ' . $e->getMessage());
            }
            
            // Метод 3: Пряме збереження в файлову систему
            try {
                $destinationPath = storage_path('app/public/' . $fullPath);
                $this->ensureDirectoryExistsForFile($destinationPath);
                
                if (move_uploaded_file($file->getPathname(), $destinationPath)) {
                    \Log::info('File stored successfully using move_uploaded_file', [
                        'destination' => $destinationPath,
                        'exists' => file_exists($destinationPath)
                    ]);
                    return $fullPath;
                }
            } catch (Exception $e) {
                \Log::warning('move_uploaded_file method failed: ' . $e->getMessage());
            }
            
            throw new Exception('Всі методи збереження файлу завершились невдачею');
            
        } catch (Exception $e) {
            \Log::error('FileUploadService error: ' . $e->getMessage());
            throw new Exception('Помилка при завантаженні файлу: ' . $e->getMessage());
        }
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $directory): void
    {
        $fullPath = storage_path('app/public/' . $directory);
        
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$fullPath}");
            }
            \Log::info('Directory created: ' . $fullPath);
        }
    }

    /**
     * Ensure directory exists for specific file
     */
    private function ensureDirectoryExistsForFile(string $filePath): void
    {
        $directory = dirname($filePath);
        
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception("Не вдалося створити директорію: {$directory}");
            }
        }
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Очищення імені файлу для Windows
        $cleanBasename = preg_replace('/[^a-zA-Z0-9-_]/', '-', $basename);
        $cleanBasename = trim($cleanBasename, '-');
        $cleanBasename = substr($cleanBasename, 0, 30); // Обмежуємо довжину
        
        // Генерація унікального суфіксу
        $timestamp = time();
        $randomString = Str::random(8);
        
        return $cleanBasename . '_' . $timestamp . '_' . $randomString . '.' . $extension;
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