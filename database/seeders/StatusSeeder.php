<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            // Статуси для відгуків
            [
                'code' => 'pending',
                'name' => 'На розгляді',
                'type' => 'review',
                'description' => 'Відгук очікує на модерацію'
            ],
            [
                'code' => 'approved',
                'name' => 'Схвалено',
                'type' => 'review',
                'description' => 'Відгук схвалено модератором'
            ],
            [
                'code' => 'rejected',
                'name' => 'Відхилено',
                'type' => 'review',
                'description' => 'Відгук відхилено модератором'
            ],
            
            // Статуси для коментарів
            [
                'code' => 'pending',
                'name' => 'На розгляді',
                'type' => 'comment',
                'description' => 'Коментар очікує на модерацію'
            ],
            [
                'code' => 'approved',
                'name' => 'Схвалено',
                'type' => 'comment',
                'description' => 'Коментар схвалено модератором'
            ],
            [
                'code' => 'rejected',
                'name' => 'Відхилено',
                'type' => 'comment',
                'description' => 'Коментар відхилено модератором'
            ],
            
            // Статуси для платежів
            [
                'code' => 'pending',
                'name' => 'Очікує оплати',
                'type' => 'payment',
                'description' => 'Платіж створено, але не підтверджено'
            ],
            [
                'code' => 'completed',
                'name' => 'Завершено',
                'type' => 'payment',
                'description' => 'Платіж успішно завершено'
            ],
            [
                'code' => 'failed',
                'name' => 'Помилка',
                'type' => 'payment',
                'description' => 'Платіж не виконано через помилку'
            ],
            [
                'code' => 'refunded',
                'name' => 'Повернуто',
                'type' => 'payment',
                'description' => 'Гроші повернуто клієнту'
            ],
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }
    }
}