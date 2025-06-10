<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class TestMediaFile implements Rule
{
    private $maxSize;
    private $allowedTypes;
    private $errorMessage;

    /**
     * Create a new rule instance.
     *
     * @param int $maxSizeMB Максимальний розмір в MB
     */
    public function __construct($maxSizeMB = 50)
    {
        $this->maxSize = $maxSizeMB * 1024 * 1024; // Конвертуємо в байти
        
        $this->allowedTypes = array_merge(
            config('tests.media.allowed_image_types', [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'
            ]),
            config('tests.media.allowed_video_types', [
                'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/webm'
            ])
        );
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $this->errorMessage = 'Файл не завантажено правильно';
            return false;
        }

        // Перевірка валідності файлу
        if (!$value->isValid()) {
            $this->errorMessage = 'Файл пошкоджений або не завантажився правильно';
            return false;
        }

        // Перевірка розміру
        if ($value->getSize() > $this->maxSize) {
            $sizeMB = round($this->maxSize / (1024 * 1024));
            $this->errorMessage = "Файл занадто великий. Максимальний розмір: {$sizeMB}MB";
            return false;
        }

        // Перевірка типу файлу
        $mimeType = $value->getMimeType();
        if (!in_array($mimeType, $this->allowedTypes)) {
            $this->errorMessage = 'Недозволений тип файлу. Дозволені: зображення (JPEG, PNG, GIF, WebP) та відео (MP4, AVI, MOV, WMV, WebM)';
            return false;
        }

        // Додаткова перевірка для зображень
        if (in_array($mimeType, config('tests.media.allowed_image_types', []))) {
            $imageInfo = @getimagesize($value->getPathname());
            if (!$imageInfo) {
                $this->errorMessage = 'Файл не є валідним зображенням';
                return false;
            }

            // Перевірка розмірів зображення (опціонально)
            [$width, $height] = $imageInfo;
            if ($width > 4000 || $height > 4000) {
                $this->errorMessage = 'Розмір зображення занадто великий. Максимум: 4000x4000 пікселів';
                return false;
            }

            if ($width < 50 || $height < 50) {
                $this->errorMessage = 'Розмір зображення занадто маленький. Мінімум: 50x50 пікселів';
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->errorMessage ?? 'Медіафайл не пройшов валідацію';
    }
}