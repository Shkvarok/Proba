<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class ProfileService
{
    /**
     * Оновлення аватара користувача.
     *
     * @param User $user
     * @param UploadedFile $avatar
     * @return User
     * @throws Exception
     */
    public function updateAvatar(User $user, UploadedFile $avatar): User
    {
        try {
            // Видаляємо старий аватар, якщо він існує
            if ($user->avatar) {
                $this->deleteAvatarFile($user->avatar);
            }
            
            // Завантажуємо новий аватар
            $avatarPath = $this->uploadAvatar($avatar);
            
            // Оновлюємо користувача
            $user->avatar = $avatarPath;
            $user->save();
            
            return $user;
        } catch (Exception $e) {
            throw new Exception('Не вдалося оновити аватар користувача: ' . $e->getMessage());
        }
    }

    /**
     * Видалення аватара користувача.
     *
     * @param User $user
     * @return User
     * @throws Exception
     */
    public function deleteAvatar(User $user): User
    {
        try {
            // Видаляємо файл аватара, якщо він існує
            if ($user->avatar) {
                $this->deleteAvatarFile($user->avatar);
                
                // Оновлюємо користувача
                $user->avatar = null;
                $user->save();
            }
            
            return $user;
        } catch (Exception $e) {
            throw new Exception('Не вдалося видалити аватар користувача: ' . $e->getMessage());
        }
    }

    /**
     * Завантаження файлу аватара.
     *
     * @param UploadedFile $avatar
     * @return string
     */
    protected function uploadAvatar(UploadedFile $avatar): string
    {
        // Генеруємо унікальне ім'я файлу
        $filename = 'avatar_' . time() . '_' . Str::random(10) . '.' . $avatar->getClientOriginalExtension();
        
        // Зберігаємо в папці courses, як було вказано в завданні
        $path = $avatar->storeAs('courses', $filename, 'public');
        
        return $path;
    }

    /**
     * Видалення файлу аватара.
     *
     * @param string $path
     * @return bool
     */
    protected function deleteAvatarFile(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }
}