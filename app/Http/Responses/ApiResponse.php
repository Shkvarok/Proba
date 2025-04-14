<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    /**
     * Успішна відповідь з даними
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Успішно', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof ResourceCollection) {
            // Якщо це колекція ресурсів, вона вже має свій формат
            $responseData = $data->response()->getData(true);
            if (isset($responseData['data'])) {
                $response['data'] = $responseData['data'];
            }
            if (isset($responseData['meta'])) {
                $response['meta'] = $responseData['meta'];
            }
            if (isset($responseData['links'])) {
                $response['links'] = $responseData['links'];
            }
        } elseif ($data instanceof JsonResource) {
            // Якщо це один ресурс
            $responseData = $data->response()->getData(true);
            if (isset($responseData['data'])) {
                $response['data'] = $responseData['data'];
            }
        } elseif ($data !== null) {
            // Для звичайних даних
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Відповідь з помилкою
     *
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return JsonResponse
     */
    public static function error(string $message = 'Помилка', int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * 404 Not Found
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Ресурс не знайдено'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * 403 Forbidden
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'У вас немає прав для виконання цієї дії'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * 401 Unauthorized
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Необхідна авторизація'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * 422 Validation Error
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(array $errors, string $message = 'Помилка валідації даних'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * 500 Server Error
     *
     * @param string $message
     * @param \Exception|null $exception
     * @return JsonResponse
     */
    public static function serverError(string $message = 'Помилка сервера', \Exception $exception = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($exception && config('app.debug')) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];
        }

        return response()->json($response, 500);
    }
}