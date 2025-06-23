@echo off
chcp 65001 >nul

echo 🔧 Налаштування автоматичного очищення логів для Windows
echo ========================================================

set PROJECT_PATH=%~dp0
set SCHEDULER_COMMAND=cd /d "%PROJECT_PATH%" ^& php artisan schedule:run

echo 📁 Шлях до проекту: %PROJECT_PATH%
echo 🔄 Команда планувальника: %SCHEDULER_COMMAND%
echo.

echo 📝 Для налаштування автоматичного очищення логів:
echo.
echo 1️⃣  Відкрийте "Планувальник завдань Windows"
echo 2️⃣  Створіть нове завдання
echo 3️⃣  Налаштуйте запуск щонеділі о 02:00
echo 4️⃣  Використайте команду: %SCHEDULER_COMMAND%
echo.

echo 🎯 Альтернативні способи:
echo.
echo 📋 Ручне очищення:
echo    php artisan logs:cleanup --dry-run
echo    php artisan logs:maintenance --strategy=age --days=7 --dry-run
echo.
echo 🔄 Запуск планувальника вручну:
echo    php artisan schedule:run
echo.
echo 📊 Моніторинг:
echo    type storage\logs\cleanup.log
echo    type storage\logs\laravel.log
echo.

echo ✅ Налаштування завершено!
pause 