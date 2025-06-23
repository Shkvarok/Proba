<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogsMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:maintenance 
                            {--strategy=age : Стратегія очищення (age/size/combined)}
                            {--days=7 : Кількість днів для збереження (для стратегії age)}
                            {--max-size=100 : Максимальний розмір в MB (для стратегії size)}
                            {--compress : Стиснути старі логи замість видалення}
                            {--dry-run : Показати що буде зроблено без фактичних змін}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обслуговування логів з різними стратегіями очищення';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $strategy = $this->option('strategy');
        $days = (int) $this->option('days');
        $maxSize = (int) $this->option('max-size');
        $compress = $this->option('compress');
        $dryRun = $this->option('dry-run');
        
        $logsPath = storage_path('logs');
        
        $this->info("🔧 Починаю обслуговування логів...");
        $this->info("📋 Стратегія: {$strategy}");
        
        if ($dryRun) {
            $this->warn("🔍 РЕЖИМ ПЕРЕДПЕРЕГЛЯДУ - зміни не будуть застосовані");
        }
        
        switch ($strategy) {
            case 'age':
                $this->cleanupByAge($days, $compress, $dryRun);
                break;
            case 'size':
                $this->cleanupBySize($maxSize, $compress, $dryRun);
                break;
            case 'combined':
                $this->cleanupCombined($days, $maxSize, $compress, $dryRun);
                break;
            default:
                $this->error("Невідома стратегія: {$strategy}");
                return 1;
        }
        
        return 0;
    }
    
    /**
     * Очищення за віком файлів
     */
    private function cleanupByAge($days, $compress, $dryRun)
    {
        $cutoffDate = Carbon::now()->subDays($days);
        $this->info("📅 Видаляю логи старіші за {$days} днів (до {$cutoffDate->format('Y-m-d')})");
        
        $logFiles = File::glob(storage_path('logs') . '/*.log');
        $processed = 0;
        $freedSpace = 0;
        
        foreach ($logFiles as $logFile) {
            $fileName = basename($logFile);
            $fileTime = Carbon::createFromTimestamp(File::lastModified($logFile));
            $fileSize = File::size($logFile);
            
            if ($fileName === '.gitignore' || $fileName === 'gitignore') {
                continue;
            }
            
            if ($fileTime->lt($cutoffDate)) {
                if ($compress && !$dryRun) {
                    $this->compressFile($logFile);
                    $this->line("🗜️  Стиснуто: {$fileName}");
                } else {
                    if (!$dryRun) {
                        File::delete($logFile);
                        $this->line("🗑️  Видалено: {$fileName}");
                    } else {
                        $this->line("🔍 Буде видалено: {$fileName}");
                    }
                }
                $processed++;
                $freedSpace += $fileSize;
            }
        }
        
        $this->showResults($processed, $freedSpace);
    }
    
    /**
     * Очищення за розміром
     */
    private function cleanupBySize($maxSizeMB, $compress, $dryRun)
    {
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;
        $this->info("📏 Видаляю логи більші за {$maxSizeMB} MB");
        
        $logFiles = File::glob(storage_path('logs') . '/*.log');
        $processed = 0;
        $freedSpace = 0;
        
        foreach ($logFiles as $logFile) {
            $fileName = basename($logFile);
            $fileSize = File::size($logFile);
            
            if ($fileName === '.gitignore' || $fileName === 'gitignore') {
                continue;
            }
            
            if ($fileSize > $maxSizeBytes) {
                if ($compress && !$dryRun) {
                    $this->compressFile($logFile);
                    $this->line("🗜️  Стиснуто: {$fileName} ({$this->formatBytes($fileSize)})");
                } else {
                    if (!$dryRun) {
                        File::delete($logFile);
                        $this->line("🗑️  Видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                    } else {
                        $this->line("🔍 Буде видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                    }
                }
                $processed++;
                $freedSpace += $fileSize;
            }
        }
        
        $this->showResults($processed, $freedSpace);
    }
    
    /**
     * Комбіноване очищення
     */
    private function cleanupCombined($days, $maxSizeMB, $compress, $dryRun)
    {
        $cutoffDate = Carbon::now()->subDays($days);
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;
        
        $this->info("🔄 Комбіноване очищення:");
        $this->info("   📅 Старіші за {$days} днів");
        $this->info("   📏 Більші за {$maxSizeMB} MB");
        
        $logFiles = File::glob(storage_path('logs') . '/*.log');
        $processed = 0;
        $freedSpace = 0;
        
        foreach ($logFiles as $logFile) {
            $fileName = basename($logFile);
            $fileTime = Carbon::createFromTimestamp(File::lastModified($logFile));
            $fileSize = File::size($logFile);
            
            if ($fileName === '.gitignore' || $fileName === 'gitignore') {
                continue;
            }
            
            $shouldProcess = $fileTime->lt($cutoffDate) || $fileSize > $maxSizeBytes;
            
            if ($shouldProcess) {
                if ($compress && !$dryRun) {
                    $this->compressFile($logFile);
                    $this->line("🗜️  Стиснуто: {$fileName} ({$this->formatBytes($fileSize)})");
                } else {
                    if (!$dryRun) {
                        File::delete($logFile);
                        $this->line("🗑️  Видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                    } else {
                        $this->line("🔍 Буде видалено: {$fileName} ({$this->formatBytes($fileSize)})");
                    }
                }
                $processed++;
                $freedSpace += $fileSize;
            }
        }
        
        $this->showResults($processed, $freedSpace);
    }
    
    /**
     * Стиснення файлу
     */
    private function compressFile($filePath)
    {
        $compressedPath = $filePath . '.gz';
        
        if (function_exists('gzopen')) {
            $content = File::get($filePath);
            $gz = gzopen($compressedPath, 'w9');
            gzwrite($gz, $content);
            gzclose($gz);
            File::delete($filePath);
        }
    }
    
    /**
     * Показати результати
     */
    private function showResults($processed, $freedSpace)
    {
        $this->newLine();
        $this->info("📊 РЕЗУЛЬТАТИ:");
        $this->info("✅ Оброблено файлів: {$processed}");
        $this->info("💾 Звільнено місця: " . $this->formatBytes($freedSpace));
        
        if (!$this->option('dry-run')) {
            Log::info("Logs maintenance completed", [
                'processed_files' => $processed,
                'freed_space' => $this->formatBytes($freedSpace),
                'strategy' => $this->option('strategy')
            ]);
        }
        
        $this->newLine();
        $this->info("🎉 Обслуговування логів завершено!");
    }
    
    /**
     * Форматування розміру файлу
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