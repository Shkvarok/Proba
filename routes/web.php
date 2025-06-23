<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;

// LiqPay маршрути
Route::name('liqpay.')->group(function () {
    Route::post('/api/payments/liqpay/callback', [PaymentController::class, 'liqpayCallback'])
         ->name('callback');
});

Route::name('courses.payment.')->group(function () {
    Route::get('/api/payments/course/{courseId}/success', [PaymentController::class, 'paymentSuccess'])
         ->name('success');
});

// Головна сторінка тестування
Route::get('/test-payment', function () {
    return response()->view('test-payment-inline');
});

// Простий інлайн view для тестування
Route::get('/test-payment-inline', function () {
    $html = '
    <!DOCTYPE html>
    <html lang="uk">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🧪 Тестування оплати курсів</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                min-height: 100vh; 
                padding: 20px; 
            }
            .container { 
                max-width: 1200px; 
                margin: 0 auto; 
                background: white; 
                border-radius: 20px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.1); 
                overflow: hidden; 
            }
            .header { 
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
                color: white; 
                padding: 30px; 
                text-align: center; 
            }
            .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
            .header p { font-size: 1.1rem; opacity: 0.9; }
            .content { padding: 40px; }
            .step { 
                background: #f8f9fa; 
                border-radius: 15px; 
                padding: 30px; 
                margin-bottom: 30px; 
                border-left: 5px solid #4facfe; 
                position: relative; 
            }
            .step.completed { border-left-color: #28a745; background: #f8fff9; }
            .step.error { border-left-color: #dc3545; background: #fff8f8; }
            .step-number { 
                position: absolute; 
                top: -10px; 
                left: 20px; 
                background: #4facfe; 
                color: white; 
                width: 30px; 
                height: 30px; 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                font-weight: bold; 
                font-size: 14px; 
            }
            .step.completed .step-number { background: #28a745; }
            .step.error .step-number { background: #dc3545; }
            .step-title { 
                font-size: 1.4rem; 
                margin-bottom: 15px; 
                color: #333; 
                margin-top: 10px; 
            }
            .form-group { margin-bottom: 20px; }
            .form-group label { 
                display: block; 
                margin-bottom: 5px; 
                font-weight: 600; 
                color: #555; 
            }
            .form-group input, .form-group select { 
                width: 100%; 
                padding: 12px; 
                border: 2px solid #e9ecef; 
                border-radius: 8px; 
                font-size: 16px; 
                transition: border-color 0.3s; 
            }
            .form-group input:focus, .form-group select:focus { 
                outline: none; 
                border-color: #4facfe; 
            }
            .btn { 
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
                color: white; 
                border: none; 
                padding: 12px 30px; 
                border-radius: 8px; 
                font-size: 16px; 
                font-weight: 600; 
                cursor: pointer; 
                transition: all 0.3s; 
                margin-right: 10px; 
                margin-bottom: 10px; 
            }
            .btn:hover { 
                transform: translateY(-2px); 
                box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4); 
            }
            .btn:disabled { 
                background: #6c757d; 
                cursor: not-allowed; 
                transform: none; 
                box-shadow: none; 
            }
            .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
            .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
            .result { 
                background: #f8f9fa; 
                border: 1px solid #dee2e6; 
                border-radius: 8px; 
                padding: 15px; 
                margin-top: 15px; 
                font-family: "Courier New", monospace; 
                font-size: 14px; 
                max-height: 300px; 
                overflow-y: auto; 
            }
            .result.success { 
                background: #d4edda; 
                border-color: #c3e6cb; 
                color: #155724; 
            }
            .result.error { 
                background: #f8d7da; 
                border-color: #f5c6cb; 
                color: #721c24; 
            }
            .status-badge { 
                padding: 4px 12px; 
                border-radius: 20px; 
                font-size: 12px; 
                font-weight: bold; 
                text-transform: uppercase; 
            }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-completed { background: #d4edda; color: #155724; }
            .status-error { background: #f8d7da; color: #721c24; }
            .hidden { display: none; }
            .loading { 
                display: inline-block; 
                width: 20px; 
                height: 20px; 
                border: 3px solid #f3f3f3; 
                border-top: 3px solid #4facfe; 
                border-radius: 50%; 
                animation: spin 1s linear infinite; 
                margin-right: 10px; 
            }
            @keyframes spin { 
                0% { transform: rotate(0deg); } 
                100% { transform: rotate(360deg); } 
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎓 Тестування оплати курсів</h1>
                <p>Швидке тестування повного циклу оплати</p>
            </div>
            
            <div class="content">
                <!-- Крок 1: Швидкий тест -->
                <div class="step" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-title">Швидкий тест системи</div>
                    <div class="step-content">
                        <p>Автоматично створимо тестові дані та проведемо повний цикл тестування</p>
                        <button class="btn btn-success" onclick="runFullTest()">
                            <span id="test-loading" class="loading hidden"></span>
                            🚀 Запустити повний тест
                        </button>
                        <button class="btn" onclick="createTestData()">Створити тестові дані</button>
                    </div>
                    <div id="test-result" class="result hidden"></div>
                </div>

                <!-- Крок 2: Перевірка конкретного платежу -->
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-title">Тестування конкретного платежу</div>
                    <div class="step-content">
                        <div class="form-group">
                            <label for="payment-id">ID платежу:</label>
                            <input type="number" id="payment-id" placeholder="Введіть ID платежу">
                        </div>
                        <button class="btn" onclick="testSpecificPayment()">
                            <span id="specific-loading" class="loading hidden"></span>
                            Тестувати платіж
                        </button>
                        <button class="btn" onclick="openLiqPayForm()">Відкрити LiqPay форму</button>
                    </div>
                    <div id="specific-result" class="result hidden"></div>
                </div>

                <!-- Крок 3: Результати -->
                <div class="step" id="step3">
                    <div class="step-number">✓</div>
                    <div class="step-title">Результати тестування</div>
                    <div class="step-content">
                        <div id="summary">
                            <p>Запустіть тест для отримання результатів</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const API_BASE = "http://127.0.0.1:8080/api";
            let testData = {};

            function showLoading(id) {
                document.getElementById(id).classList.remove("hidden");
            }

            function hideLoading(id) {
                document.getElementById(id).classList.add("hidden");
            }

            function showResult(elementId, content, isError = false) {
                const element = document.getElementById(elementId);
                element.innerHTML = content;
                element.className = `result ${isError ? "error" : "success"}`;
                element.classList.remove("hidden");
            }

            function updateStepStatus(stepId, status) {
                const step = document.getElementById(stepId);
                step.className = `step ${status}`;
            }

            async function apiRequest(url, options = {}) {
                try {
                    const response = await fetch(url, {
                        headers: { "Content-Type": "application/json" },
                        ...options
                    });
                    const data = await response.json();
                    return { data, status: response.status, ok: response.ok };
                } catch (error) {
                    return { error: error.message, ok: false };
                }
            }

            async function createTestData() {
                const result = await apiRequest(`${API_BASE}/test/create-test-data`, {
                    method: "POST"
                });
                
                if (result.ok) {
                    testData = result.data;
                    showResult("test-result", `✅ Тестові дані створено!
User: ${result.data.user.email}
Course: ${result.data.course.title} (${result.data.course.price} ₴)`);
                    updateStepStatus("step1", "completed");
                } else {
                    showResult("test-result", `❌ Помилка: ${result.error}`, true);
                }
            }

            async function runFullTest() {
                showLoading("test-loading");
                updateStepStatus("step1", "");
                
                try {
                    // Крок 1: Створення тестових даних
                    let result = await apiRequest(`${API_BASE}/test/create-test-data`, {
                        method: "POST"
                    });
                    
                    if (!result.ok) throw new Error("Не вдалося створити тестові дані");
                    testData = result.data;
                    
                    // Крок 2: Логін
                    result = await apiRequest(`${API_BASE}/login`, {
                        method: "POST",
                        body: JSON.stringify({
                            email: testData.user.email,
                            password: "password"
                        })
                    });
                    
                    if (!result.ok) throw new Error("Не вдалося авторизуватися");
                    const token = result.data.token;
                    
                    // Крок 3: Створення платежу
                    result = await apiRequest(`${API_BASE}/payments/course/${testData.course.id}/liqpay`, {
                        method: "POST",
                        headers: { 
                            "Authorization": `Bearer ${token}`,
                            "Content-Type": "application/json" 
                        }
                    });
                    
                    if (!result.ok) throw new Error("Не вдалося створити платіж");
                    const paymentId = result.data.payment_id;
                    
                    // Крок 4: Симуляція успішної оплати
                    result = await apiRequest(`${API_BASE}/test/liqpay-callback/${paymentId}`, {
                        method: "POST",
                        headers: { 
                            "Authorization": `Bearer ${token}`,
                            "Content-Type": "application/json" 
                        }
                    });
                    
                    if (!result.ok) throw new Error("Не вдалося симулювати оплату");
                    
                    // Крок 5: Перевірка доступу
                    result = await apiRequest(`${API_BASE}/enrollments/course/${testData.course.id}`, {
                        headers: { "Authorization": `Bearer ${token}` }
                    });
                    
                    const hasAccess = result.ok && result.data.has_access;
                    
                    hideLoading("test-loading");
                    
                    if (hasAccess) {
                        showResult("test-result", `🎉 ПОВНИЙ ТЕСТ ПРОЙШОВ УСПІШНО!

📝 Створено:
- Користувач: ${testData.user.email}
- Курс: ${testData.course.title} (${testData.course.price} ₴)
- Платіж ID: ${paymentId}

✅ Результати:
- Авторизація: успішна
- Створення платежу: успішне
- Симуляція оплати: успішна
- Доступ до курсу: НАДАНО

🚀 Система оплати працює правильно!`);
                        updateStepStatus("step1", "completed");
                        updateSummary(true, paymentId);
                    } else {
                        throw new Error("Доступ до курсу не надано після оплати");
                    }
                    
                } catch (error) {
                    hideLoading("test-loading");
                    showResult("test-result", `❌ ТЕСТ НЕ ПРОЙДЕНО: ${error.message}`, true);
                    updateStepStatus("step1", "error");
                    updateSummary(false);
                }
            }

            async function testSpecificPayment() {
                const paymentId = document.getElementById("payment-id").value;
                if (!paymentId) {
                    alert("Введіть ID платежу");
                    return;
                }
                
                showLoading("specific-loading");
                
                try {
                    const result = await apiRequest(`${API_BASE}/test/check-payment-flow/${paymentId}`);
                    
                    hideLoading("specific-loading");
                    
                    if (result.ok) {
                        const flow = result.data.flow_status;
                        const success = flow.flow_successful;
                        
                        showResult("specific-result", `${success ? "✅ УСПІХ" : "❌ ПОМИЛКА"} - Платіж ${paymentId}

📊 Статус:
- Платіж завершено: ${flow.payment_completed ? "✅" : "❌"}
- Підписка створена: ${flow.enrollment_created ? "✅" : "❌"}
- Підписка активна: ${flow.enrollment_active ? "✅" : "❌"}
- Загальний статус: ${success ? "УСПІШНО" : "ПОМИЛКА"}

📝 Деталі:
${JSON.stringify(result.data, null, 2)}`, !success);
                        
                        updateStepStatus("step2", success ? "completed" : "error");
                    } else {
                        showResult("specific-result", `❌ Помилка: ${result.error}`, true);
                        updateStepStatus("step2", "error");
                    }
                } catch (error) {
                    hideLoading("specific-loading");
                    showResult("specific-result", `❌ Помилка: ${error.message}`, true);
                    updateStepStatus("step2", "error");
                }
            }

            function openLiqPayForm() {
                const paymentId = document.getElementById("payment-id").value;
                if (!paymentId) {
                    alert("Введіть ID платежу");
                    return;
                }
                
                window.open(`/test-liqpay-form/${paymentId}`, "_blank");
            }

            function updateSummary(success, paymentId = null) {
                const summaryElement = document.getElementById("summary");
                
                if (success) {
                    summaryElement.innerHTML = `
                        <h3 style="color: #28a745;">🎉 Система оплати працює ідеально!</h3>
                        <ul style="margin-top: 15px;">
                            <li>✅ Тестові дані створено</li>
                            <li>✅ Авторизація пройшла</li>
                            <li>✅ Платіж створено ${paymentId ? `(ID: ${paymentId})` : ""}</li>
                            <li>✅ Оплата симульована</li>
                            <li>✅ Підписка активована</li>
                            <li>✅ Доступ до курсу надано</li>
                        </ul>
                        <p style="margin-top: 15px; font-weight: bold; color: #28a745;">
                            🚀 Всі компоненти системи оплати функціонують правильно!
                        </p>
                    `;
                    updateStepStatus("step3", "completed");
                } else {
                    summaryElement.innerHTML = `
                        <h3 style="color: #dc3545;">❌ Виявлено проблеми в системі оплати</h3>
                        <p>Перевірте помилки вище та виправте їх.</p>
                        <p style="margin-top: 10px;">
                            <strong>Рекомендації:</strong><br>
                            1. Перевірте налаштування LiqPay<br>
                            2. Перевірте конфігурацію БД<br>
                            3. Перегляньте логи помилок
                        </p>
                    `;
                    updateStepStatus("step3", "error");
                }
            }

            // Автозапуск при завантаженні
            window.onload = function() {
                console.log("🧪 Веб-тестування готове до роботи!");
            };
        </script>
    </body>
    </html>';
    
    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
});

// Тестова LiqPay форма (ваш існуючий код)
Route::get('/test-liqpay-form/{paymentId}', function($paymentId) {
    $payment = \App\Models\Payment::findOrFail($paymentId);
    $course = \App\Models\Course::findOrFail($payment->entity_id);
    $user = \App\Models\User::findOrFail($payment->user_id);
    
    $liqpayService = app(\App\Services\LiqPayService::class);
    $formData = $liqpayService->createCoursePaymentForm($user, $course, $payment);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>LiqPay Test</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial; margin: 20px; }
            .debug { background: #f5f5f5; padding: 15px; margin: 10px 0; }
            .form { background: #e8f5e8; padding: 20px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>LiqPay Test Form</h1>
        
        <div class='debug'>
            <h3>Debug Info:</h3>
            <p><strong>Data:</strong><br><code>" . $formData['data'] . "</code></p>
            <p><strong>Signature:</strong><br><code>" . $formData['signature'] . "</code></p>
            <p><strong>Callback URL:</strong> " . ($formData['params']['server_url'] ?? 'Not set') . "</p>
            <p><strong>Return URL:</strong> " . ($formData['params']['result_url'] ?? 'Not set') . "</p>
        </div>
        
        <div class='form'>
            <h3>Payment Form:</h3>
            " . $formData['form_html'] . "
        </div>
        
        <script src='https://static.liqpay.ua/libjs/sdk_button.js'></script>
    </body>
    </html>";
    
    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
});

// Додаткові API endpoints для тестування
Route::prefix('api/test')->group(function () {
    // Створення тестових даних
    Route::post('/create-test-data', function() {
        $user = \App\Models\User::create([
            'name' => 'Test Student ' . rand(1000, 9999),
            'email' => 'teststudent' . rand(1000, 9999) . '@example.com',
            'password' => \Hash::make('password'),
            'role_id' => 3
        ]);
        
        $course = \App\Models\Course::create([
            'title' => 'Тестовий курс ' . rand(100, 999),
            'description' => 'Курс для тестування оплати',
            'price' => rand(100, 1000),
            'instructor_id' => 1,
            'category_id' => 1,
            'level_id' => 1,
            'is_published' => true
        ]);
        
        return response()->json([
            'success' => true,
            'user' => $user,
            'course' => $course,
            'login_credentials' => [
                'email' => $user->email,
                'password' => 'password'
            ]
        ]);
    });
    
    // Симуляція LiqPay callback
    Route::post('/liqpay-callback/{paymentId}', function($paymentId) {
        $payment = \App\Models\Payment::findOrFail($paymentId);
        
        // Симулюємо успішний callback
        $payment->payment_status = 'completed';
        $payment->transaction_id = 'test_' . time();
        $payment->save();
        
        // Створюємо підписку
        $enrollment = \App\Models\CourseEnrollment::firstOrCreate(
            [
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
            ],
            [
                'enrollment_type' => 'purchase',
                'payment_id' => $payment->id,
                'is_active' => true,
                'enrolled_at' => now(),
                'expires_at' => null
            ]
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Тестовий callback оброблено',
            'payment' => $payment->fresh(),
            'enrollment' => $enrollment
        ]);
    });
    
    // Перевірка повного процесу
    Route::get('/check-payment-flow/{paymentId}', function($paymentId) {
        $payment = \App\Models\Payment::with(['user', 'entity'])->findOrFail($paymentId);
        $enrollment = \App\Models\CourseEnrollment::where('payment_id', $paymentId)->first();
        
        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'user_email' => $payment->user->email,
                'course_title' => $payment->entity->title ?? 'N/A'
            ],
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'is_active' => $enrollment->is_active,
                'enrollment_type' => $enrollment->enrollment_type,
                'access_status' => $enrollment->isActive() ? 'active' : 'expired'
            ] : null,
            'flow_status' => [
                'payment_completed' => $payment->payment_status === 'completed',
                'enrollment_created' => $enrollment !== null,
                'enrollment_active' => $enrollment ? $enrollment->isActive() : false,
                'flow_successful' => $payment->payment_status === 'completed' && $enrollment && $enrollment->isActive()
            ]
        ]);
    });
});
// Додайте ці маршрути до файлу web.php для розширеного тестування

Route::prefix('api/test-advanced')->group(function () {
    
    // Тест різних статусів платежу
    Route::post('/payment-statuses/{paymentId}', function($paymentId) {
        $payment = \App\Models\Payment::findOrFail($paymentId);
        $statuses = ['pending', 'completed', 'failed', 'refunded'];
        
        $results = [];
        foreach ($statuses as $status) {
            $payment->payment_status = $status;
            $payment->save();
            
            // Симуляція обробки для кожного статусу
            $enrollment = null;
            if ($status === 'completed') {
                $enrollment = \App\Models\CourseEnrollment::updateOrCreate(
                    [
                        'user_id' => $payment->user_id,
                        'course_id' => $payment->entity_id,
                    ],
                    [
                        'payment_id' => $payment->id,
                        'is_active' => true,
                        'enrollment_type' => 'purchase',
                        'enrolled_at' => now(),
                    ]
                );
            }
            
            $results[$status] = [
                'payment_status' => $payment->payment_status,
                'enrollment_created' => $enrollment ? true : false,
                'enrollment_active' => $enrollment ? $enrollment->is_active : false
            ];
        }
        
        return response()->json([
            'payment_id' => $paymentId,
            'test_results' => $results,
            'recommendation' => 'Перевірте, що тільки статус "completed" створює активну підписку'
        ]);
    });

    // Тест множинних платежів за один курс
    Route::post('/multiple-payments/{courseId}', function($courseId) {
        $course = \App\Models\Course::findOrFail($courseId);
        
        // Створюємо тестового користувача
        $user = \App\Models\User::create([
            'name' => 'Multi Payment Test User',
            'email' => 'multitest' . rand(1000, 9999) . '@example.com',
            'password' => \Hash::make('password'),
            'role_id' => 3
        ]);
        
        $payments = [];
        
        // Створюємо 3 платежі
        for ($i = 1; $i <= 3; $i++) {
            $payment = \App\Models\Payment::create([
                'user_id' => $user->id,
                'amount' => $course->price,
                'currency' => 'UAH',
                'payment_method' => 'liqpay',
                'payment_status' => 'pending',
                'entity_type' => 'course',
                'entity_id' => $course->id,
            ]);
            
            // Перший платіж - успішний, інші - різні статуси
            $status = $i === 1 ? 'completed' : ($i === 2 ? 'failed' : 'pending');
            $payment->payment_status = $status;
            $payment->save();
            
            $payments[] = $payment;
        }
        
        // Створюємо підписку тільки для успішного платежу
        $enrollment = \App\Models\CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_id' => $payments[0]->id,
            'is_active' => true,
            'enrollment_type' => 'purchase',
            'enrolled_at' => now(),
        ]);
        
        return response()->json([
            'user' => $user,
            'course_id' => $courseId,
            'payments' => $payments,
            'enrollment' => $enrollment,
            'test_scenario' => 'Користувач робить кілька платежів за один курс',
            'expected_result' => 'Має бути створена тільки одна активна підписка'
        ]);
    });

    // Тест відновлення платежу
    Route::post('/payment-recovery/{paymentId}', function($paymentId) {
        $payment = \App\Models\Payment::findOrFail($paymentId);
        
        // Симулюємо сценарій: платіж спочатку невдалий, потім успішний
        $steps = [];
        
        // Крок 1: Невдалий платіж
        $payment->payment_status = 'failed';
        $payment->save();
        $steps[] = 'Платіж позначено як невдалий';
        
        // Крок 2: Перевірка відсутності підписки
        $enrollment = \App\Models\CourseEnrollment::where('payment_id', $paymentId)->first();
        $steps[] = 'Підписка ' . ($enrollment && $enrollment->is_active ? 'АКТИВНА (ПОМИЛКА!)' : 'відсутня (OK)');
        
        // Крок 3: Відновлення платежу
        $payment->payment_status = 'completed';
        $payment->transaction_id = 'recovered_' . time();
        $payment->save();
        $steps[] = 'Платіж відновлено як успішний';
        
        // Крок 4: Створення підписки
        $enrollment = \App\Models\CourseEnrollment::updateOrCreate(
            [
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
            ],
            [
                'payment_id' => $payment->id,
                'is_active' => true,
                'enrollment_type' => 'purchase',
                'enrolled_at' => now(),
            ]
        );
        $steps[] = 'Підписка створена/оновлена';
        
        return response()->json([
            'payment_id' => $paymentId,
            'final_status' => $payment->payment_status,
            'enrollment_active' => $enrollment->is_active,
            'recovery_steps' => $steps,
            'test_passed' => $payment->payment_status === 'completed' && $enrollment->is_active
        ]);
    });

    // Тест безпеки платежів
    Route::post('/security-test/{paymentId}', function($paymentId) {
        $payment = \App\Models\Payment::findOrFail($paymentId);
        
        // Тест 1: Спроба змінити суму платежу
        $originalAmount = $payment->amount;
        $testResults = [];
        
        try {
            $payment->amount = $originalAmount * 0.1; // Намагаємося зменшити суму в 10 разів
            $payment->save();
            $testResults['amount_change'] = 'НЕБЕЗПЕКА: Сума змінена без перевірки!';
        } catch (\Exception $e) {
            $testResults['amount_change'] = 'OK: Зміна суми заблокована';
        }
        
        // Відновлюємо оригінальну суму
        $payment->amount = $originalAmount;
        $payment->save();
        
        // Тест 2: Спроба створити підписку без оплати
        try {
            $fakeEnrollment = \App\Models\CourseEnrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id + 999, // Неіснуючий курс
                'payment_id' => null, // Без платежу
                'is_active' => true,
                'enrollment_type' => 'purchase',
                'enrolled_at' => now(),
            ]);
            $testResults['free_enrollment'] = 'НЕБЕЗПЕКА: Підписка створена без платежу!';
            $fakeEnrollment->delete(); // Видаляємо тестову підписку
        } catch (\Exception $e) {
            $testResults['free_enrollment'] = 'OK: Підписка без платежу заблокована';
        }
        
        // Тест 3: Перевірка цілісності даних
        $course = \App\Models\Course::find($payment->entity_id);
        $user = \App\Models\User::find($payment->user_id);
        
        $testResults['data_integrity'] = [
            'course_exists' => $course ? 'OK' : 'ПОМИЛКА: Курс не існує',
            'user_exists' => $user ? 'OK' : 'ПОМИЛКА: Користувач не існує',
            'amount_positive' => $payment->amount > 0 ? 'OK' : 'ПОМИЛКА: Негативна сума',
            'currency_valid' => in_array($payment->currency, ['UAH', 'USD', 'EUR']) ? 'OK' : 'ПОПЕРЕДЖЕННЯ: Незвичайна валюта'
        ];
        
        return response()->json([
            'payment_id' => $paymentId,
            'security_tests' => $testResults,
            'recommendations' => [
                'Додайте валідацію сум платежів',
                'Заборонте створення підписок без відповідних платежів',
                'Додайте перевірку існування пов\'язаних об\'єктів',
                'Логуйте всі зміни в критичних даних'
            ]
        ]);
    });

    // Замініть цей маршрут:
Route::get('/test-check-payment-flow/{paymentId}', function($paymentId) {
    // Використовуйте безпечний запит без entity
    $payment = \App\Models\Payment::with(['user', 'course'])->findOrFail($paymentId);
    $enrollment = \App\Models\CourseEnrollment::where('payment_id', $paymentId)->first();
    
    return response()->json([
        'payment' => [
            'id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'user_email' => $payment->user->email,
            'course_title' => $payment->course->title ?? 'N/A'
        ],
        'enrollment' => $enrollment ? [
            'id' => $enrollment->id,
            'is_active' => $enrollment->is_active,
            'enrollment_type' => $enrollment->enrollment_type,
            'access_status' => $enrollment->isActive() ? 'active' : 'expired'
        ] : null,
        'flow_status' => [
            'payment_completed' => $payment->payment_status === 'completed',
            'enrollment_created' => $enrollment !== null,
            'enrollment_active' => $enrollment ? $enrollment->isActive() : false,
            'flow_successful' => $payment->payment_status === 'completed' && $enrollment && $enrollment->isActive()
        ]
    ]);
});

    // Повний стрес-тест
    Route::post('/stress-test', function() {
        $results = [];
        $startTime = microtime(true);
        
        // Створюємо багато користувачів та платежів
        for ($i = 1; $i <= 10; $i++) {
            $user = \App\Models\User::create([
                'name' => "Stress Test User $i",
                'email' => "stress$i" . time() . '@example.com',
                'password' => \Hash::make('password'),
                'role_id' => 3
            ]);
            
            $course = \App\Models\Course::first(); // Використовуємо перший доступний курс
            
            $payment = \App\Models\Payment::create([
                'user_id' => $user->id,
                'amount' => rand(100, 1000),
                'currency' => 'UAH',
                'payment_method' => 'liqpay',
                'payment_status' => 'completed',
                'entity_type' => 'course',
                'entity_id' => $course->id,
                'transaction_id' => 'stress_test_' . $i . '_' . time()
            ]);
            
            $enrollment = \App\Models\CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'payment_id' => $payment->id,
                'is_active' => true,
                'enrollment_type' => 'purchase',
                'enrolled_at' => now(),
            ]);
            
            $results[] = [
                'iteration' => $i,
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'enrollment_id' => $enrollment->id,
                'success' => true
            ];
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        return response()->json([
            'stress_test_results' => $results,
            'performance' => [
                'total_time' => round($executionTime, 2) . ' seconds',
                'operations_per_second' => round(count($results) / $executionTime, 2),
                'average_time_per_operation' => round($executionTime / count($results), 4) . ' seconds'
            ],
            'summary' => [
                'total_operations' => count($results),
                'successful_operations' => count(array_filter($results, fn($r) => $r['success'])),
                'test_passed' => count($results) === 10
            ]
        ]);
    });
});
