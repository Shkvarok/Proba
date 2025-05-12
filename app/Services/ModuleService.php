<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;

class ModuleService
{
    protected $courseService;
    
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }
    
    public function getAllModulesByCourseId(int $courseId): Collection
    {
        return Module::where('course_id', $courseId)
            ->orderBy('position')
            ->get();
    }

    public function getModuleById(int $id): ?Module
    {
        return Module::with('lessons')->findOrFail($id);
    }

public function createModule(array $data): Module
{
    return \DB::transaction(function () use ($data) {
        $courseId = $data['course_id'];
        
        // Визначаємо максимальну позицію серед модулів курсу
        $maxPosition = Module::where('course_id', $courseId)->max('position') ?? 0;
        
        // Якщо позиція не вказана, додаємо в кінець
        if (!isset($data['position'])) {
            $data['position'] = $maxPosition + 1;
        } else {
            $newPosition = (int)$data['position'];
            
            // Якщо вказана позиція, яка вже зайнята - зрушуємо інші модулі
            if ($newPosition <= $maxPosition) {
                Module::where('course_id', $courseId)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');
            }
            // Якщо вказана позиція більша за максимальну - встановлюємо в кінець
            else if ($newPosition > $maxPosition + 1) {
                $data['position'] = $maxPosition + 1;
            }
        }
        
        return Module::create($data);
    });
}

    public function updateModule(int $id, array $data): Module
    {
        return \DB::transaction(function () use ($id, $data) {
            $module = Module::findOrFail($id);
            $courseId = $module->course_id;
            $oldPosition = $module->position;
            
            // Якщо позиція не змінюється, просто оновлюємо модуль
            if (!isset($data['position']) || $data['position'] == $oldPosition) {
                $module->update($data);
                return $module;
            }
            
            $newPosition = (int)$data['position'];
            
            // Перевірка, чи існує модуль з такою позицією в цьому курсі
            $existingModule = Module::where('course_id', $courseId)
                ->where('position', $newPosition)
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingModule) {
                // Зміщення модулів, якщо нова позиція вже зайнята
                
                // Якщо переміщаємо вниз (збільшуємо позицію)
                if ($newPosition > $oldPosition) {
                    // Зрушуємо всі модулі між старою і новою позицією вниз (зменшуємо їхню позицію)
                    Module::where('course_id', $courseId)
                        ->where('position', '>', $oldPosition)
                        ->where('position', '<=', $newPosition)
                        ->decrement('position');
                } 
                // Якщо переміщаємо вгору (зменшуємо позицію)
                else if ($newPosition < $oldPosition) {
                    // Зрушуємо всі модулі між новою і старою позицією вгору (збільшуємо їхню позицію)
                    Module::where('course_id', $courseId)
                        ->where('position', '>=', $newPosition)
                        ->where('position', '<', $oldPosition)
                        ->increment('position');
                }
            }
            
            // Встановлюємо нову позицію для модуля, який ми оновлюємо
            $module->position = $newPosition;
            $module->update($data);
            
            return $module;
        });
    }

    public function deleteModule(int $id): bool
    {
        return \DB::transaction(function () use ($id) {
            $module = Module::findOrFail($id);
            $courseId = $module->course_id;
            $position = $module->position;
            
            // Видаляємо модуль
            $deleted = $module->delete();
            
            if ($deleted) {
                // Перенумеровуємо позиції інших модулів
                Module::where('course_id', $courseId)
                    ->where('position', '>', $position)
                    ->decrement('position');
            }
            
            return $deleted;
        });
    }

/**
 * Оновлює позиції модулів
 * 
 * @param array $positions Масив з позиціями модулів
 * @return array Масив з результатами операції
 */
public function updateModulePositions(array $positions): array
{
    return \DB::transaction(function () use ($positions) {
        // Спочатку знаходимо всі модулі, які ми будемо оновлювати
        $moduleIds = array_column($positions, 'id');
        $modules = Module::whereIn('id', $moduleIds)->get()->keyBy('id');
        
        if ($modules->isEmpty()) {
            return [
                'success' => false,
                'adjusted' => false,
                'message' => 'Не знайдено модулів для оновлення'
            ];
        }
        
        // Перевіряємо, чи всі модулі з одного курсу
        $courseIds = $modules->pluck('course_id')->unique();
        if ($courseIds->count() > 1) {
            throw new \Exception('Модулі повинні належати до одного курсу');
        }
        
        $courseId = $courseIds->first();
        
        // Отримуємо всі модулі курсу для забезпечення послідовності
        $allCourseModules = Module::where('course_id', $courseId)
            ->orderBy('position')
            ->get();
        
        // Якщо передано лише один модуль, обробимо його окремо
        if (count($positions) === 1) {
            $position = $positions[0];
            $moduleId = $position['id'];
            $newPosition = $position['position'];
            
            $module = $modules[$moduleId];
            $oldPosition = $module->position;
            
            // Перевіряємо, чи нова позиція вписується в допустимий діапазон
            $maxAllowedPosition = $allCourseModules->count();
            
            // Якщо позиція більша за кількість модулів, коригуємо її
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
            
            // Зміщення інших модулів, щоб звільнити нову позицію
            if ($newPosition > $oldPosition) {
                // Переміщення вниз - зменшуємо позицію модулів між старою і новою
                Module::where('course_id', $courseId)
                    ->where('position', '>', $oldPosition)
                    ->where('position', '<=', $newPosition)
                    ->decrement('position');
            } else {
                // Переміщення вгору - збільшуємо позицію модулів між новою і старою
                Module::where('course_id', $courseId)
                    ->where('position', '>=', $newPosition)
                    ->where('position', '<', $oldPosition)
                    ->increment('position');
            }
            
            // Оновлюємо позицію поточного модуля
            Module::where('id', $moduleId)->update(['position' => $newPosition]);
            
            return [
                'success' => true,
                'adjusted' => $wasAdjusted,
                'message' => $wasAdjusted ? "Позицію скориговано до {$newPosition}, щоб уникнути пропусків" : null
            ];
        }
        
        // Для багатьох модулів - забезпечуємо послідовність позицій
        
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
        Module::where('course_id', $courseId)
            ->update(['position' => \DB::raw('position * -1')]);
        
        // Оновлюємо з новими послідовними позиціями
        foreach ($positions as $position) {
            Module::where('id', $position['id'])
                ->update(['position' => $position['position']]);
        }
        
        // Знаходимо модулі, які не були явно вказані
        $updatedIds = array_column($positions, 'id');
        $remainingModules = Module::where('course_id', $courseId)
            ->whereNotIn('id', $updatedIds)
            ->orderBy('position') // Сортуємо за старою (негативною) позицією
            ->get();
        
        // Знаходимо максимальну вже встановлену позицію
        $maxPosition = Module::where('course_id', $courseId)
            ->where('position', '>', 0)
            ->max('position') ?? 0;
        
        // Перенумеровуємо решту модулів, починаючи з наступної позиції
        $nextPosition = $maxPosition + 1;
        foreach ($remainingModules as $module) {
            Module::where('id', $module->id)
                ->update(['position' => $nextPosition++]);
        }
        
        // Переконуємося, що всі модулі курсу мають послідовні позиції
        $this->ensureSequentialPositions($courseId);
        
        return [
            'success' => true,
            'adjusted' => $wasAdjusted,
            'adjustedPositions' => $adjustedPositions,
            'message' => $wasAdjusted ? "Позиції деяких модулів було скориговано для забезпечення послідовності" : null
        ];
    });
}
/**
 * Забезпечує послідовні позиції для всіх модулів курсу
 */
private function ensureSequentialPositions(int $courseId): void
{
    // Отримуємо всі модулі курсу, відсортовані за поточною позицією
    $modules = Module::where('course_id', $courseId)
        ->orderBy('position')
        ->get();
    
    // Перевіряємо, чи є пропуски або дублікати в позиціях
    $hasGaps = false;
    $expectedPosition = 1;
    
    foreach ($modules as $module) {
        if ($module->position != $expectedPosition) {
            $hasGaps = true;
            break;
        }
        $expectedPosition++;
    }
    
    // Якщо є пропуски, перенумеровуємо всі модулі
    if ($hasGaps) {
        $position = 1;
        foreach ($modules as $module) {
            Module::where('id', $module->id)
                ->update(['position' => $position++]);
        }
    }
}

    // Метод для перевірки, чи може користувач редагувати модуль
    public function canUserManageModule(int $userId, int $moduleId): bool
    {
        $module = Module::findOrFail($moduleId);
        $course = $module->course;
        
        // Перевіряємо, чи користувач є автором курсу, до якого належить модуль,
        // або адміністратором/супер-адміністратором
        return $course->instructor_id === $userId || auth()->user()->isAdmin();
    }

        /**
     * Перевірка, чи може користувач керувати курсом
     */
    public function canUserManageCourse(int $userId, int $courseId): bool
    {
        // Використовуємо метод з CourseService
        return $this->courseService->canUserManageCourse($userId, $courseId);
    }
}