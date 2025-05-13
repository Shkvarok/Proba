<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonLecture;
use App\Models\LessonTest;
use App\Models\LessonExtraMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LessonService
{
    public function getAllLessonsByModuleId(int $moduleId): Collection
    {
        return Lesson::where('module_id', $moduleId)
            ->orderBy('position')
            ->get();
    }

    public function getLessonById(int $id): ?Lesson
    {
        return Lesson::with(['lecture', 'test', 'extraMaterial'])->findOrFail($id);
    }

 public function createLesson(array $data): Lesson
{
    return DB::transaction(function () use ($data) {
        $moduleId = $data['module_id'];
        
        // Визначаємо максимальну позицію серед уроків модуля
        $maxPosition = Lesson::where('module_id', $moduleId)->max('position') ?? 0;
        
        // Якщо позиція не вказана, додаємо в кінець
        if (!isset($data['position'])) {
            $data['position'] = $maxPosition + 1;
        } else {
            $newPosition = (int)$data['position'];
            
            // Якщо вказана позиція, яка вже зайнята - зрушуємо інші уроки
            if ($newPosition <= $maxPosition) {
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');
            }
            // Якщо вказана позиція більша за максимальну - встановлюємо в кінець
            else if ($newPosition > $maxPosition + 1) {
                $data['position'] = $maxPosition + 1;
            }
        }
        
        // Створюємо урок
        $lesson = Lesson::create([
            'module_id' => $moduleId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'position' => $data['position'],
            'status' => $data['status'] ?? 'active',
        ]);
        
        // Створюємо відповідні деталі для різних типів уроків
        switch ($data['type']) {
            case 'lecture':
                $lectureData = [
                    'lesson_id' => $lesson->id,
                    'duration_minutes' => $data['duration_minutes'] ?? null,
                ];
                
                // Визначаємо тип контенту лекції (текст або файл)
                if (isset($data['content'])) {
                    $lectureData['content'] = $data['content'];
                    $lectureData['content_type'] = 'text';
                } elseif (isset($data['file_path'])) {
                    $lectureData['file_path'] = $data['file_path'];
                    $lectureData['file_type'] = $data['file_type'] ?? null;
                    $lectureData['file_name'] = $data['file_name'] ?? null;
                    $lectureData['content_type'] = 'file';
                }
                
                LessonLecture::create($lectureData);
                break;
            
            case 'test':
                $testData = [
                    'lesson_id' => $lesson->id,
                    'source_type' => $data['source_type'] ?? 'url', // За замовчуванням URL
                    'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                    'passing_score' => $data['passing_score'] ?? null,
                ];
                
                if ($testData['source_type'] === 'url' && isset($data['external_url'])) {
                    $testData['external_url'] = $data['external_url'];
                }
                
                LessonTest::create($testData);
                break;
            
           case 'extra_material':
                $materialData = [
                    'lesson_id' => $lesson->id,
                    'material_type' => $data['material_type'] ?? 'text',
                ];
                
                switch ($materialData['material_type']) {
                    case 'text':
                        $materialData['content'] = $data['material_content'] ?? null;
                        break;
                    case 'url':
                        $materialData['url'] = $data['material_url'] ?? null;
                        break;
                    case 'file':
                    case 'image':
                    case 'video':
                        // Перевіряємо різні можливі варіанти ключів
                        if (isset($data['material_file_path'])) {
                            $materialData['file_path'] = $data['material_file_path'];
                            $materialData['file_type'] = $data['material_file_type'] ?? null;
                            $materialData['file_name'] = $data['material_file_name'] ?? null;
                        } elseif (isset($data['file_path'])) {
                            $materialData['file_path'] = $data['file_path'];
                            $materialData['file_type'] = $data['file_type'] ?? null;
                            $materialData['file_name'] = $data['file_name'] ?? null;
                        }
                        break;
                }
                
                LessonExtraMaterial::create($materialData);
                break;
        }
        
        return $lesson->fresh(['lecture', 'test', 'extraMaterial']);
    });
}

/**
 * Пряме оновлення уроку - простіший метод для надійного оновлення
 *
 * @param int $id
 * @param array $data
 * @return Lesson
 */
public function directUpdateLesson(int $id, array $data): Lesson
{
    return DB::transaction(function () use ($id, $data) {
        // Отримуємо урок
        $lesson = Lesson::findOrFail($id);
        
        // Логуємо отримані дані
        \Illuminate\Support\Facades\Log::info('Дані для оновлення уроку:', $data);
        
        // Оновлюємо базові дані уроку
        if (isset($data['title'])) $lesson->title = $data['title'];
        if (isset($data['description'])) $lesson->description = $data['description'];
        if (isset($data['status'])) $lesson->status = $data['status'];
        
        // Обробка позиції, якщо вона змінюється
        if (isset($data['position']) && $data['position'] != $lesson->position) {
            $oldPosition = $lesson->position;
            $newPosition = $data['position'];
            $moduleId = $lesson->module_id;
            
            // Визначаємо максимальну позицію
            $maxPosition = Lesson::where('module_id', $moduleId)->count();
            
            // Обмежуємо позицію максимальним значенням
            if ($newPosition > $maxPosition) {
                $newPosition = $maxPosition;
            }
            
            // Зміщуємо інші уроки
            if ($newPosition > $oldPosition) {
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>', $oldPosition)
                    ->where('position', '<=', $newPosition)
                    ->decrement('position');
            } else {
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>=', $newPosition)
                    ->where('position', '<', $oldPosition)
                    ->increment('position');
            }
            
            $lesson->position = $newPosition;
        }
        
        // Зберігаємо оновлений урок
        $lesson->save();
        
        // Оновлюємо пов'язані дані залежно від типу уроку
        if ($lesson->type === 'lecture' && isset($data['lecture_data'])) {
            $lecture = $lesson->lecture ?? LessonLecture::create(['lesson_id' => $lesson->id]);
            
            // Оновлюємо дані лекції
            foreach ($data['lecture_data'] as $key => $value) {
                $lecture->{$key} = $value;
            }
            
            $lecture->save();
        }
        
        // Інші типи уроків...
        
        // Повертаємо свіжі дані уроку
        return $lesson->fresh(['lecture', 'test', 'extraMaterial']);
    });
}
    /**
 * Оновлення уроку та його деталей
 *
 * @param int $id
 * @param array $data
 * @return Lesson
 */
public function updateLesson(int $id, array $data): Lesson
{
    return DB::transaction(function () use ($id, $data) {
        $lesson = Lesson::findOrFail($id);
        $moduleId = $lesson->module_id;
        $oldPosition = $lesson->position;
        
        // Логування вхідних даних
        \Illuminate\Support\Facades\Log::debug('Вхідні дані для оновлення уроку:', $data);
        
        // Якщо змінюється позиція, коригуємо інші уроки
        if (isset($data['position']) && $data['position'] != $oldPosition) {
            $newPosition = (int)$data['position'];
            
            // Обмежуємо позицію кількістю уроків у модулі
            $maxPosition = Lesson::where('module_id', $moduleId)->count();
            if ($newPosition > $maxPosition) {
                $newPosition = $maxPosition;
            }
            
            // Зміщуємо інші уроки
            if ($newPosition > $oldPosition) {
                // Переміщення вниз - зменшуємо позицію уроків між старою і новою
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>', $oldPosition)
                    ->where('position', '<=', $newPosition)
                    ->decrement('position');
            } else {
                // Переміщення вгору - збільшуємо позицію уроків між новою і старою
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>=', $newPosition)
                    ->where('position', '<', $oldPosition)
                    ->increment('position');
            }
            
            $data['position'] = $newPosition;
        }
        
        // Оновлюємо базову інформацію про урок
        $lesson->update([
            'title' => $data['title'] ?? $lesson->title,
            'description' => $data['description'] ?? $lesson->description,
            'position' => $data['position'] ?? $lesson->position,
            'status' => $data['status'] ?? $lesson->status,
        ]);
        
        // Оновлюємо деталі в залежності від типу уроку
        switch ($lesson->type) {
            case 'lecture':
                $lecture = $lesson->lecture ?? LessonLecture::create(['lesson_id' => $lesson->id]);
                
                $lectureData = [
                    'duration_minutes' => $data['duration_minutes'] ?? $lecture->duration_minutes,
                ];
                
                // Визначаємо тип контенту
                if (isset($data['content_type'])) {
                    $lectureData['content_type'] = $data['content_type'];
                }
                
                // Оновлення контенту відповідно до типу
                if (isset($data['content_type']) && $data['content_type'] === 'text') {
                    // Якщо текстовий контент
                    $lectureData['content'] = $data['content'] ?? $lecture->content;
                    $lectureData['file_path'] = null;
                    $lectureData['file_type'] = null;
                    $lectureData['file_name'] = null;
                } elseif (isset($data['content_type']) && $data['content_type'] === 'file') {
                    // Якщо файловий контент
                    $lectureData['content'] = null;
                    $lectureData['file_path'] = $data['file_path'] ?? $lecture->file_path;
                    $lectureData['file_type'] = $data['file_type'] ?? $lecture->file_type;
                    $lectureData['file_name'] = $data['file_name'] ?? $lecture->file_name;
                } else {
                    // Інакше зберігаємо те, що є
                    if (isset($data['content'])) {
                        $lectureData['content'] = $data['content'];
                        $lectureData['content_type'] = 'text';
                    }
                    
                    if (isset($data['file_path'])) {
                        $lectureData['file_path'] = $data['file_path'];
                        $lectureData['file_type'] = $data['file_type'] ?? null;
                        $lectureData['file_name'] = $data['file_name'] ?? null;
                        $lectureData['content_type'] = 'file';
                    }
                }
                
                // Логування даних лекції перед оновленням
                \Illuminate\Support\Facades\Log::debug('Дані для оновлення лекції:', $lectureData);
                
                $lecture->update($lectureData);
                break;
            
            case 'test':
                $test = $lesson->test ?? LessonTest::create(['lesson_id' => $lesson->id]);
                
                $testData = [
                    'source_type' => $data['source_type'] ?? $test->source_type,
                    'time_limit_minutes' => $data['time_limit_minutes'] ?? $test->time_limit_minutes,
                    'passing_score' => $data['passing_score'] ?? $test->passing_score,
                ];
                
                // Оновлення URL тесту, якщо потрібно
                if (isset($data['external_url'])) {
                    $testData['external_url'] = $data['external_url'];
                }
                
                $test->update($testData);
                break;
            
            case 'extra_material':
                $material = $lesson->extraMaterial ?? LessonExtraMaterial::create(['lesson_id' => $lesson->id]);
                
                $materialData = [
                    'material_type' => $data['material_type'] ?? $material->material_type,
                ];
                
                // Оновлення в залежності від типу матеріалу
                $materialType = $data['material_type'] ?? $material->material_type;
                
                switch ($materialType) {
                    case 'text':
                        $materialData['content'] = $data['material_content'] ?? $material->content;
                        // Очищаємо дані файлу і URL при зміні типу
                        $materialData['file_path'] = null;
                        $materialData['file_type'] = null;
                        $materialData['file_name'] = null;
                        $materialData['url'] = null;
                        break;
                        
                    case 'url':
                        $materialData['url'] = $data['material_url'] ?? $material->url;
                        // Очищаємо дані файлу і контенту при зміні типу
                        $materialData['file_path'] = null;
                        $materialData['file_type'] = null;
                        $materialData['file_name'] = null;
                        $materialData['content'] = null;
                        break;
                        
                    case 'file':
                    case 'image':
                    case 'video':
                        if (isset($data['file_path'])) {
                            $materialData['file_path'] = $data['file_path'];
                            $materialData['file_type'] = $data['file_type'] ?? null;
                            $materialData['file_name'] = $data['file_name'] ?? null;
                            // Очищаємо контент і URL при зміні типу
                            $materialData['content'] = null;
                            $materialData['url'] = null;
                        }
                        break;
                }
                
                $material->update($materialData);
                break;
        }
        
        return $lesson->fresh(['lecture', 'test', 'extraMaterial']);
    });
}

    public function deleteLesson(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $lesson = Lesson::findOrFail($id);
            $moduleId = $lesson->module_id;
            $position = $lesson->position;
            
            // Видаляємо урок
            $deleted = $lesson->delete();
            
            if ($deleted) {
                // Перенумеровуємо позиції інших уроків
                Lesson::where('module_id', $moduleId)
                    ->where('position', '>', $position)
                    ->decrement('position');
            }
            
            return $deleted;
        });
    }

    public function updateLessonPositions(array $positions): array
    {
        return DB::transaction(function () use ($positions) {
            // Спочатку знаходимо всі уроки, які ми будемо оновлювати
            $lessonIds = array_column($positions, 'id');
            $lessons = Lesson::whereIn('id', $lessonIds)->get()->keyBy('id');
            
            if ($lessons->isEmpty()) {
                return [
                    'success' => false,
                    'adjusted' => false,
                    'message' => 'Не знайдено уроків для оновлення'
                ];
            }
            
            // Перевіряємо, чи всі уроки з одного модуля
            $moduleIds = $lessons->pluck('module_id')->unique();
            if ($moduleIds->count() > 1) {
                throw new \Exception('Уроки повинні належати до одного модуля');
            }
            
            $moduleId = $moduleIds->first();
            
            // Отримуємо всі уроки модуля для забезпечення послідовності
            $allModuleLessons = Lesson::where('module_id', $moduleId)
                ->orderBy('position')
                ->get();
            
            // Якщо передано лише один урок, обробимо його окремо
            if (count($positions) === 1) {
                $position = $positions[0];
                $lessonId = $position['id'];
                $newPosition = $position['position'];
                
                $lesson = $lessons[$lessonId];
                $oldPosition = $lesson->position;
                
                // Перевіряємо, чи нова позиція вписується в допустимий діапазон
                $maxAllowedPosition = $allModuleLessons->count();
                
                // Якщо позиція більша за кількість уроків, коригуємо її
                if ($newPosition > $maxAllowedPosition) {
                    $newPosition = $maxAllowedPosition;
                    // Зберігаємо інформацію про коригування
                    $wasAdjusted = true;
                } else {
                    $wasAdjusted = false;
                }
                
                // Якщо позиція не змінюється, нічого не робимо
                if ($oldPosition == $newPosition) {
                    return [
                        'success' => true,
                        'adjusted' => false,
                        'message' => null
                    ];
                }
                
                // Зміщення інших уроків, щоб звільнити нову позицію
                if ($newPosition > $oldPosition) {
                    // Переміщення вниз - зменшуємо позицію уроків між старою і новою
                    Lesson::where('module_id', $moduleId)
                        ->where('position', '>', $oldPosition)
                        ->where('position', '<=', $newPosition)
                        ->decrement('position');
                } else {
                    // Переміщення вгору - збільшуємо позицію уроків між новою і старою
                    Lesson::where('module_id', $moduleId)
                        ->where('position', '>=', $newPosition)
                        ->where('position', '<', $oldPosition)
                        ->increment('position');
                }
                
                // Оновлюємо позицію поточного уроку
                Lesson::where('id', $lessonId)->update(['position' => $newPosition]);
                
                return [
                    'success' => true,
                    'adjusted' => $wasAdjusted,
                    'message' => $wasAdjusted ? "Позицію скориговано до {$newPosition}, щоб уникнути пропусків" : null
                ];
            }
            
            // Для багатьох уроків - забезпечуємо послідовність позицій
            
            // Спочатку сортуємо за запитаною позицією
            usort($positions, function($a, $b) {
                return $a['position'] <=> $b['position'];
            });
            
            // Перенумеровуємо, починаючи з 1, без пропусків
            $wasAdjusted = false;
            $adjustedPositions = [];
            
            for ($i = 0; $i < count($positions); $i++) {
                $correctPosition = $i + 1;
                
                // Якщо запитана позиція не дорівнює правильній послідовній позиції
                if ($positions[$i]['position'] != $correctPosition) {
                    $wasAdjusted = true;
                    $adjustedPositions[] = [
                        'id' => $positions[$i]['id'],
                        'requested' => $positions[$i]['position'],
                        'assigned' => $correctPosition
                    ];
                    $positions[$i]['position'] = $correctPosition;
                }
            }
            
            // Тимчасово робимо всі позиції негативними
            Lesson::where('module_id', $moduleId)
                ->update(['position' => DB::raw('position * -1')]);
            
            // Оновлюємо з новими послідовними позиціями
            foreach ($positions as $position) {
                Lesson::where('id', $position['id'])
                    ->update(['position' => $position['position']]);
            }
            
            // Знаходимо уроки, які не були явно вказані
            $updatedIds = array_column($positions, 'id');
            $remainingLessons = Lesson::where('module_id', $moduleId)
                ->whereNotIn('id', $updatedIds)
                ->orderBy('position') // Сортуємо за старою (негативною) позицією
                ->get();
            
            // Знаходимо максимальну вже встановлену позицію
            $maxPosition = Lesson::where('module_id', $moduleId)
                ->where('position', '>', 0)
                ->max('position') ?? 0;
            
            // Перенумеровуємо решту уроків, починаючи з наступної позиції
            $nextPosition = $maxPosition + 1;
            foreach ($remainingLessons as $lesson) {
                Lesson::where('id', $lesson->id)
                    ->update(['position' => $nextPosition++]);
            }
            
            // Переконуємося, що всі уроки модуля мають послідовні позиції
            $this->ensureSequentialPositions($moduleId);
            
            return [
                'success' => true,
                'adjusted' => $wasAdjusted,
                'adjustedPositions' => $adjustedPositions,
                'message' => $wasAdjusted ? "Позиції деяких уроків було скориговано для забезпечення послідовності" : null
            ];
        });
    }

    /**
     * Забезпечує послідовні позиції для всіх уроків модуля
     */
    private function ensureSequentialPositions(int $moduleId): void
    {
        // Отримуємо всі уроки модуля, відсортовані за поточною позицією
        $lessons = Lesson::where('module_id', $moduleId)
            ->orderBy('position')
            ->get();
        
        // Перевіряємо, чи є пропуски або дублікати в позиціях
        $hasGaps = false;
        $expectedPosition = 1;
        
        foreach ($lessons as $lesson) {
            if ($lesson->position != $expectedPosition) {
                $hasGaps = true;
                break;
            }
            $expectedPosition++;
        }
        
        // Якщо є пропуски, перенумеровуємо всі уроки
        if ($hasGaps) {
            $position = 1;
            foreach ($lessons as $lesson) {
                Lesson::where('id', $lesson->id)
                    ->update(['position' => $position++]);
            }
        }
    }

    // Метод для перевірки, чи може користувач редагувати урок
    public function canUserManageLesson(int $userId, int $lessonId): bool
    {
        $lesson = Lesson::findOrFail($lessonId);
        $module = $lesson->module;
        $course = $module->course;
        
        // Перевіряємо, чи користувач є автором курсу, до якого належить урок,
        // або адміністратором/супер-адміністратором
        return $course->instructor_id === $userId || auth()->user()->isAdmin();
    }
}