<?php

namespace App\Console\Commands;

use App\Models\TestQuestion;
use App\Models\QuestionAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTestMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tests:cleanup-media {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned test media files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Початок очищення медіафайлів тестів...');
        
        if ($isDryRun) {
            $this->warn('РЕЖИМ ТЕСТУВАННЯ: файли не будуть видалені');
        }

        $deletedCount = 0;
        $deletedSize = 0;

        // Отримуємо всі файли в директоріях тестів
        $questionFiles = Storage::disk('public')->allFiles('tests/questions');
        $answerFiles = Storage::disk('public')->allFiles('tests/answers');
        
        $this->info('Знайдено файлів питань: ' . count($questionFiles));
        $this->info('Знайдено файлів відповідей: ' . count($answerFiles));

        // Отримуємо всі шляхи файлів з БД
        $questionPaths = TestQuestion::whereNotNull('media_path')->pluck('media_path')->toArray();
        $answerPaths = QuestionAnswer::whereNotNull('media_path')->pluck('media_path')->toArray();
        
        $allDbPaths = array_merge($questionPaths, $answerPaths);
        
        $this->info('Файлів в БД (питання): ' . count($questionPaths));
        $this->info('Файлів в БД (відповіді): ' . count($answerPaths));

        $progressBar = $this->output->createProgressBar(count($questionFiles) + count($answerFiles));
        $progressBar->start();

        // Перевіряємо файли питань
        foreach ($questionFiles as $file) {
            $progressBar->advance();
            
            if (!in_array($file, $allDbPaths)) {
                $size = Storage::disk('public')->size($file);
                
                if (!$isDryRun) {
                    Storage::disk('public')->delete($file);
                }
                
                $deletedCount++;
                $deletedSize += $size;
                
                if ($isDryRun) {
                    $this->line("\nБуде видалено: {$file} (" . $this->formatBytes($size) . ")");
                }
            }
        }

        // Перевіряємо файли відповідей
        foreach ($answerFiles as $file) {
            $progressBar->advance();
            
            if (!in_array($file, $allDbPaths)) {
                $size = Storage::disk('public')->size($file);
                
                if (!$isDryRun) {
                    Storage::disk('public')->delete($file);
                }
                
                $deletedCount++;
                $deletedSize += $size;
                
                if ($isDryRun) {
                    $this->line("\nБуде видалено: {$file} (" . $this->formatBytes($size) . ")");
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("Буде видалено файлів: {$deletedCount}");
            $this->info("Буде звільнено місця: " . $this->formatBytes($deletedSize));
            $this->warn('Для фактичного видалення запустіть команду без параметра --dry-run');
        } else {
            $this->info("Видалено файлів: {$deletedCount}");
            $this->info("Звільнено місця: " . $this->formatBytes($deletedSize));
        }

        // Додаткова перевірка - файли в БД, які не існують фізично
        $this->info("\nПеревірка файлів з БД, які не існують фізично...");
        
        $missingFiles = 0;
        
        foreach ($allDbPaths as $dbPath) {
            if (!Storage::disk('public')->exists($dbPath)) {
                $missingFiles++;
                $this->warn("Файл не існує: {$dbPath}");
            }
        }
        
        if ($missingFiles > 0) {
            $this->error("Знайдено {$missingFiles} записів у БД без відповідних файлів");
            $this->info("Рекомендується очистити ці записи вручну або створити команду для цього");
        } else {
            $this->info("Всі файли з БД існують фізично");
        }

        $this->info('Очищення завершено!');
        
        return self::SUCCESS;
    }

    /**
     * Форматувати розмір файлу в читабельний вигляд
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}