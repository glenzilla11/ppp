<?php if (isLoggedIn()) redirect('index.php?page=cashier'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chairman POS - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="splash-screen" id="splashScreen">
                <div class="splash-content">
                    <div class="splash-logo">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="splash-text">
                        <h1 id="splashText">Chairman POS</h1>
                    </div>
                    <div class="splash-subtitle" id="splashSubtitle">Point of Sale</div>
                    <div class="splash-footer">
                        <span class="developer-name">Glen</span>
                    </div>
                </div>
            </div>

            <div class="login-form" id="loginForm" style="display: none;">
                <div class="form-header">
                    <h2>Login</h2>
                    <p class="form-subtitle">Chairman POS System</p>
                </div>

                <form id="authForm" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pin">PIN</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="pin" name="pin" placeholder="Enter PIN" maxlength="50" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Login</button>

                    <div id="loginError" class="alert alert-danger" style="display: none;"></div>
                </form>

            </div>
        </div>
    </div>

    <script>
        // Splash screen with text cycling
        const splashTexts = ['Chairman POS', 'Point of Sale', 'Fast. Reliable.', '+254735065427'];
        let textIndex = 0;
        let charIndex = 0;
        const splashTextEl = document.getElementById('splashText');
        const splashSubtitleEl = document.getElementById('splashSubtitle');

        function typeText() {
            const text = splashTexts[textIndex];
            if (charIndex < text.length) {
                splashTextEl.textContent += text[charIndex];
                charIndex++;
                setTimeout(typeText, 60);
            } else {
                setTimeout(() => {
                    splashTextEl.textContent = '';
                    charIndex = 0;
                    textIndex = (textIndex + 1) % splashTexts.length;
                    splashSubtitleEl.textContent = splashTexts[textIndex];
                    typeText();
                }, 2000);
            }
        }

        typeText();

        // Show login form after 5 seconds
        setTimeout(() => {
            document.getElementById('splashScreen').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('email').focus();
        }, 5000);

        // Handle login
        async function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const pin = document.getElementById('pin').value;
            const errorEl = document.getElementById('loginError');

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({email, pin})
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect based on role
                    const redirectUrl = data.data.role === 'admin' || data.data.role === 'manager' 
                        ? 'index.php?page=admin' 
                        : 'index.php?page=cashier';
                    window.location.href = redirectUrl;
                } else {
                    errorEl.textContent = data.message || 'Login failed';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Connection error';
                errorEl.style.display = 'block';
            }
        }
    </script>
</body>
</html>
