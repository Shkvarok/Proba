<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * @var ProfileService
     */
    protected $profileService;

    /**
     * ProfileController constructor.
     *
     * @param ProfileService $profileService
     */
    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * Оновлення аватара користувача.
     *
     * @param Request $request
     * @return JsonResponse|UserResource
     */
    public function updateAvatar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB = 5120KB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Помилка валідації',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $updatedUser = $this->profileService->updateAvatar($user, $request->file('avatar'));
            
            return response()->json([
                'success' => true,
                'message' => 'Аватар успішно оновлено',
                'user' => new UserResource($updatedUser)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при оновленні аватара: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Видалення аватара користувача.
     *
     * @param Request $request
     * @return JsonResponse|UserResource
     */
    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();
            $updatedUser = $this->profileService->deleteAvatar($user);
            
            return response()->json([
                'success' => true,
                'message' => 'Аватар успішно видалено',
                'user' => new UserResource($updatedUser)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при видаленні аватара: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримання інформації про профіль користувача.
     *
     * @param Request $request
     * @return JsonResponse|UserResource
     */
    public function getProfile(Request $request)
    {
        try {
            return new UserResource($request->user()->load('role', 'country'));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні профілю: ' . $e->getMessage()
            ], 500);
        }
    }
}