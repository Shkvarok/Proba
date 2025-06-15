<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandleMultipartFormData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Обробляємо тільки POST/PUT запити з multipart/form-data
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && 
            str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            
            Log::info('HandleMultipartFormData middleware triggered', [
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'has_files' => $request->hasFile('cover_image'),
                'has_method_field' => $request->has('_method'),
                'all_data' => $request->except(['cover_image'])
            ]);
            
            // Якщо є _method=PUT, симулюємо PUT запит
            if ($request->has('_method') && strtoupper($request->input('_method')) === 'PUT') {
                $request->setMethod('PUT');
                // НЕ видаляємо _method тут, нехай CourseRequest його обробить
                Log::info('Method spoofing detected - changing to PUT');
            }
            
            // Конвертуємо строкові булеві значення
            $booleanFields = ['is_published'];
            foreach ($booleanFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if (is_string($value)) {
                        if (in_array(strtolower($value), ['true', '1', 'on', 'yes'])) {
                            $request->merge([$field => true]);
                        } elseif (in_array(strtolower($value), ['false', '0', 'off', 'no', ''])) {
                            $request->merge([$field => false]);
                        }
                    }
                }
            }
            
            // Конвертуємо числові поля
            $numericFields = ['price', 'discount_price', 'category_id', 'level_id', 'instructor_id'];
            foreach ($numericFields as $field) {
                if ($request->has($field) && $request->input($field) !== '') {
                    $value = $request->input($field);
                    if (is_string($value) && is_numeric($value)) {
                        $request->merge([$field => strpos($value, '.') !== false ? (float)$value : (int)$value]);
                    }
                }
            }
            
            // Очищаємо порожні рядки (конвертуємо в null)
            $fieldsToClean = ['description', 'requirements', 'what_you_learn', 'promo_video_url', 'meta_title', 'meta_description'];
            foreach ($fieldsToClean as $field) {
                if ($request->has($field) && trim($request->input($field)) === '') {
                    $request->merge([$field => null]);
                }
            }
            
            Log::info('HandleMultipartFormData processed request', [
                'processed_data' => $request->except(['cover_image']),
                'method_after_processing' => $request->method()
            ]);
        }

        return $next($request);
    }
}