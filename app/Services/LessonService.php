<?php

namespace App\Services;

use App\Models\Lesson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonService
{
    /**
     * Зберігає PDF-файл для лекції
     *
     * @param UploadedFile $file
     * @return string|null Шлях до збереженого файлу
     */
    public function saveLectureFile(UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        // Генеруємо унікальне ім'я файлу
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Зберігаємо файл
        $path = $file->storeAs('lessons/files', $fileName, 'public');
        
        return $path;
    }
    
    /**
     * Зберігає відео-файл для уроку
     *
     * @param UploadedFile $file
     * @return string|null Шлях до збереженого файлу
     */
    public function saveVideoFile(UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        // Генеруємо унікальне ім'я файлу
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Зберігаємо файл
        $path = $file->storeAs('lessons/videos', $fileName, 'public');
        
        return $path;
    }
    
    /**
     * Видаляє файл уроку
     *
     * @param string|null $filePath
     * @return bool
     */
    public function deleteFile(?string $filePath): bool
    {
        if (!$filePath) {
            return true;
        }
        
        return Storage::disk('public')->delete($filePath);
    }
    
    /**
     * Оновлює порядок уроків у курсі
     *
     * @param int $courseId
     * @param array $lessonOrders Масив з id уроків та їх порядком
     * @return bool
     */
    public function updateLessonsOrder(int $courseId, array $lessonOrders): bool
    {
        foreach ($lessonOrders as $item) {
            if (isset($item['id']) && isset($item['order'])) {
                Lesson::where('id', $item['id'])
                    ->where('course_id', $courseId)
                    ->update(['order' => $item['order']]);
            }
        }
        
        return true;
    }
    
    /**
     * Визначає тип відео-посилання (YouTube чи локальне)
     *
     * @param string $url
     * @return string 'youtube' або 'local'
     */
    public function getVideoType(string $url): string
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        }
        
        return 'local';
    }
    
    /**
     * Отримує YouTube ID з посилання
     *
     * @param string $url
     * @return string|null
     */
    public function getYoutubeId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Перевіряє, чи є у користувача доступ до уроку
     *
     * @param int $lessonId
     * @param int $userId
     * @return bool
     */
    public function hasAccess(int $lessonId, int $userId): bool
    {
        $lesson = Lesson::with('course')->findOrFail($lessonId);
        
        // Якщо урок безкоштовний - дозволяємо доступ
        if ($lesson->is_free) {
            return true;
        }
        
        // Якщо курс безкоштовний - дозволяємо доступ
        if ($lesson->course && $lesson->course->price == 0) {
            return true;
        }
        
        // Перевіряємо, чи користувач є адміністратором
        $user = \App\Models\User::find($userId);
        if ($user && $user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }
        
        // Перевіряємо, чи користувач є інструктором цього курсу
        if ($lesson->course && $lesson->course->instructor_id === $userId) {
            return true;
        }
        
        // Перевіряємо, чи має користувач доступ до курсу
        return \App\Models\StudentCourse::where('user_id', $userId)
            ->where('course_id', $lesson->course_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}