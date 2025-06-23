<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup 
                            {--days=7 : Кількість днів для збереження логів (за замовчуванням 7)}
                            {--force : Примусово очистити всі логи без підтвердження}
                            {--dry-run : Показати що буде видалено без фактичного видалення}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очищення старих логів з storage/logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = Carbon::now()->subDays($days);
        $logsPath = storage_path('logs');
        
        $this->info("🔍 Починаю очищення логів старіших за {$days} днів...");
        $this->info("📅 Дата відсічення: {$cutoffDate->format('Y-m-d H:i:s')}");
        
        if ($dryRun) {
            $this->warn("🔍 РЕЖИМ ПЕРЕДПЕРЕГЛЯДУ - файли не будуть видалені");
        }
        
        if (!$force && !$dryRun) {
            if (!$this->confirm("Ви впевнені, що хочете видалити логи старіші за {$days} днів?")) {
                $this->info("❌ Операцію скасовано");
                return 0;
            }
        }
        
        $deletedFiles = [];
        $deletedSize = 0;
        $skippedFiles = [];
        
        // Отримуємо всі файли логів
        $logFiles = File::glob($logsPath . '/*.log');
        
        foreach ($logFiles as $logFile) {
            $fileName = basename($logFile);
            $fileTime = Carbon::createFromTimestamp(File::lastModified($logFile));
            $fileSize = File::size($logFile);
            
            // Пропускаємо .gitignore та інші системні файли
            if ($fileName === '.gitignore' || $fileName === 'gitignore') {
                $skippedFiles[] = $fileName;
                continue;
            }
            
            if ($fileTime->lt($cutoffDate)) {
                $deletedFiles[] = [
                    'name' => $fileName,
                    'size' => $this->formatBytes($fileSize),
                    'modified' => $fileTime->format('Y-m-d H:i:s')
                ];
                $deletedSize += $fileSize;
                
                if (!$dryRun) {
                    File::delete($logFile);
                    $this->line("🗑️  Видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                } else {
                    $this->line("🔍 Буде видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                }
            } else {
                $skippedFiles[] = $fileName;
            }
        }
        
        // Підсумки
        $this->newLine();
        $this->info("📊 РЕЗУЛЬТАТИ ОЧИЩЕННЯ:");
        $this->info("✅ Видалено файлів: " . count($deletedFiles));
        $this->info("💾 Звільнено місця: " . $this->formatBytes($deletedSize));
        $this->info("⏭️  Пропущено файлів: " . count($skippedFiles));
        
        if (!empty($deletedFiles)) {
            $this->newLine();
            $this->info("📋 Список видалених файлів:");
            foreach ($deletedFiles as $file) {
                $this->line("   • {$file['name']} ({$file['size']}) - {$file['modified']}");
            }
        }
        
        if (!empty($skippedFiles)) {
            $this->newLine();
            $this->info("📋 Список пропущених файлів:");
            foreach ($skippedFiles as $file) {
                $this->line("   • {$file}");
            }
        }
        
        // Логуємо операцію
        if (!$dryRun) {
            Log::info("Logs cleanup completed", [
                'deleted_files' => count($deletedFiles),
                'freed_space' => $this->formatBytes($deletedSize),
                'cutoff_days' => $days,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
            ]);
        }
        
        $this->newLine();
        $this->info("🎉 Очищення логів завершено!");
        
        return 0;
    }
    
    /**
     * Форматування розміру файлу в читабельний вигляд
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
} 