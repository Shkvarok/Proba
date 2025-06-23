<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test LiqPay Payment Form</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .debug-section { 
            background: #f8f9fa; 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 5px; 
            border-left: 4px solid #007bff; 
        }
        .form-section { 
            background: #e8f5e8; 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 5px; 
            border: 2px solid #28a745; 
        }
        pre { 
            background: #2d3748; 
            color: #e2e8f0; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto; 
            font-size: 12px; 
        }
        .btn { 
            background: #28a745; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
        }
        .btn:hover { 
            background: #218838; 
        }
        .data-field { 
            word-break: break-all; 
            background: #f1f3f4; 
            padding: 10px; 
            border-radius: 3px; 
            margin: 5px 0; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test LiqPay Payment Form</h1>
        
        <div class="debug-section">
            <h2>📊 Debug Information</h2>
            <h3>Parameters:</h3>
            <pre>{{ json_encode($formData['params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            
            <h3>🔐 Data (Base64):</h3>
            <div class="data-field">{{ $formData['data'] }}</div>
            
            <h3>✍️ Signature:</h3>
            <div class="data-field">{{ $formData['signature'] }}</div>
            
            <h3>🌐 URLs:</h3>
            <ul>
                <li><strong>Action URL:</strong> {{ $formData['url'] }}</li>
                <li><strong>Callback URL:</strong> {{ $formData['params']['server_url'] ?? 'Not set' }}</li>
                <li><strong>Return URL:</strong> {{ $formData['params']['result_url'] ?? 'Not set' }}</li>
            </ul>
        </div>
        
        <div class="form-section">
            <h2>💳 Payment Form (Method 1 - Direct Form)</h2>
            <p>Ця форма відправить дані безпосередньо до LiqPay:</p>
            {!! $formData['form_html'] !!}
        </div>
        
        <div class="form-section">
            <h2>🔧 Payment Form (Method 2 - JavaScript SDK)</h2>
            <p>Використання JavaScript SDK LiqPay:</p>
            <div id="liqpay_checkout"></div>
            <button onclick="initLiqPay()" class="btn">Ініціювати оплату через SDK</button>
        </div>
        
        <div class="debug-section">
            <h2>🔍 Manual Test</h2>
            <p>Скопіюйте data та signature для ручного тестування:</p>
            <form method="POST" action="https://www.liqpay.ua/api/3/checkout" target="_blank">
                <label>Data:</label><br>
                <textarea name="data" rows="3" style="width: 100%; margin-bottom: 10px;">{{ $formData['data'] }}</textarea><br>
                
                <label>Signature:</label><br>
                <textarea name="signature" rows="2" style="width: 100%; margin-bottom: 10px;">{{ $formData['signature'] }}</textarea><br>
                
                <input type="submit" value="Test Manual Submit" class="btn">
            </form>
        </div>
    </div>

    <script src="https://static.liqpay.ua/libjs/sdk_button.js"></script>
    <script>
        function initLiqPay() {
            if (typeof LiqPayCheckout !== 'undefined') {
                window.LiqPayCheckoutCallback = function() {
                    LiqPayCheckout.init({
                        data: "{{ $formData['data'] }}",
                        signature: "{{ $formData['signature'] }}",
                        embedTo: "#liqpay_checkout",
                        mode: "embed"
                    }).on("liqpay.callback", function(data){
                        console.log('Payment callback:', data);
                        alert('Payment result: ' + JSON.stringify(data));
                    }).on("liqpay.ready", function(data){
                        console.log('LiqPay ready');
                    }).on("liqpay.close", function(data){
                        console.log('LiqPay closed');
                    });
                };
                window.LiqPayCheckoutCallback();
            } else {
                alert('LiqPay SDK не завантажений. Перевірте підключення до інтернету.');
            }
        }

        // Auto-init SDK when page loads
        window.addEventListener('load', function() {
            if (typeof LiqPayCheckout !== 'undefined') {
                console.log('LiqPay SDK loaded successfully');
            } else {
                console.error('LiqPay SDK failed to load');
            }
        });
    </script>
</body>
</html>