<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Reset Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: grid;
            place-items: center;
            min-height: 100vh;
            background-color: #f3f4f6;
            margin: 0;
        }
        .container {
            background: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            width: 350px;
        }
        h2 {
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
            box-sizing: border-box; /* Mencegah padding merusak layout */
        }
        button {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 0.25rem;
            background-color: #333;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background-color: #555;
        }
        #message {
            margin-top: 1rem;
            font-weight: 500;
            text-align: center;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Set Password Baru Anda</h2>
        
        <form id="reset-form">
            
            <input type="hidden" id="token" name="token">
            <input type="hidden" id="email" name="email">

            <div class="form-group">
                <label for="password">Password Baru</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password Baru</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit">Reset Password</button>
        </form>

        <div id="message"></div>
    </div>

    <script>
        const BACKEND_URL = '{{ url('') }}';
        const form = document.getElementById('reset-form');
        const messageDiv = document.getElementById('message');
        const tokenInput = document.getElementById('token');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');

        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const token = params.get('token');
            const email = params.get('email');

            if (!token || !email) {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Token atau Email tidak valid. Silakan coba minta link baru.';
                form.style.display = 'none';
                return;
            }

            tokenInput.value = token;
            emailInput.value = email;
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault(); 

            // Ambil token dari meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const payload = {
                token: tokenInput.value,
                email: emailInput.value,
                password: passwordInput.value,
                password_confirmation: passwordConfirmationInput.value,
            };

            messageDiv.textContent = '';
            messageDiv.className = '';

            try {
                const response = await fetch(`${BACKEND_URL}/api/reset-password`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        
                        // 👇 [PERBAIKAN 2 DARI 2] Tambahkan header X-CSRF-TOKEN 👇
                        'X-CSRF-TOKEN': csrfToken 
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    let errorMessage = data.message || 'Terjadi kesalahan.';
                    if (data.errors && data.errors.password) {
                        errorMessage = data.errors.password[0];
                    }
                    throw new Error(errorMessage);
                }

                messageDiv.className = 'success';
                messageDiv.textContent = data.message || 'Password Anda berhasil direset! Silakan login.';
                form.style.display = 'none';

            } catch (error) {
                messageDiv.className = 'error';
                messageDiv.textContent = error.message;
            }
        });
    </script>

</body>
</html>