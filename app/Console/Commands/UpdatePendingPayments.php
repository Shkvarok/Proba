<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePendingPayments extends Command
{
    protected $signature = 'payments:update-pending';
    
    protected $description = 'Update status of pending payments that are older than 24 hours';
    
    public function handle()
    {
        $this->info('Checking for stale pending payments...');
        
        // Знаходимо платежі, які знаходяться в статусі "pending" більше 24 годин
        $stalePendingPayments = Payment::where('payment_status', 'pending')
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->get();
            
        $count = $stalePendingPayments->count();
        $this->info("Found {$count} stale pending payments.");
        
        foreach ($stalePendingPayments as $payment) {
            // Змінюємо статус на "failed"
            $payment->payment_status = 'failed';
            $payment->save();
            
            $this->info("Payment #{$payment->id} marked as failed.");
            Log::info("Stale pending payment #{$payment->id} automatically marked as failed.");
        }
        
        $this->info('Finished updating stale pending payments.');
        
        return 0;
    }
}