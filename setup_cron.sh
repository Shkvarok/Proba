#!/bin/bash

# Скрипт для налаштування автоматичного очищення логів
# Використовується для налаштування cron job

echo "🔧 Налаштування автоматичного очищення логів"
echo "=============================================="

# Шлях до проекту
PROJECT_PATH="$(pwd)"
SCHEDULER_COMMAND="cd $PROJECT_PATH && php artisan schedule:run"

echo "📁 Шлях до проекту: $PROJECT_PATH"
echo "🔄 Команда планувальника: $SCHEDULER_COMMAND"
echo ""

# Перевіряємо чи існує cron job
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "⚠️  Cron job для Laravel Scheduler вже налаштований:"
    crontab -l | grep "schedule:run"
else
    echo "📝 Додаємо cron job для Laravel Scheduler..."
    
    # Додаємо cron job (кожну хвилину)
    (crontab -l 2>/dev/null; echo "* * * * * $SCHEDULER_COMMAND >> /dev/null 2>&1") | crontab -
    
    echo "✅ Cron job додано!"
    echo "🕐 Планувальник буде запускатися кожну хвилину"
fi

echo ""
echo "📋 Поточні cron jobs:"
crontab -l

echo ""
echo "🎯 Налаштування завершено!"
echo ""
echo "📝 Команди для ручного очищення логів:"
echo "   php artisan logs:cleanup --dry-run"
echo "   php artisan logs:maintenance --strategy=age --days=7 --dry-run"
echo ""
echo "📊 Моніторинг:"
echo "   tail -f storage/logs/cleanup.log"
echo "   tail -f storage/logs/laravel.log" 