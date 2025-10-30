<?php
session_start();
require 'config.php'; // MeekroDB configuration file

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
    if ($password === '') $errors[] = "Password required.";

    if (empty($errors)) {
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);

        if ($user && password_verify($password, $user['password'])) {
            // ✅ Session start after successful login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            // ✅ Check if wallet already exists
            $wallet = DB::queryFirstRow("SELECT * FROM wallets WHERE user_id=%i", $user['id']);

            if (!$wallet) {
                // 👇 Agar wallet nahi bana hua, to automatically create kar do
                DB::insert('wallets', [
                    'user_id' => $user['id'],
                    'balance' => 1000.00 // starting balance for old users
                ]);
            }

            // ✅ Redirect to dashboard
            header("Location: wallet.php");
            exit;

        } else {
            $errors[] = "Incorrect email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Digital Ease Pay</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="text-center p-8 border-b border-gray-100">
            <i class="fas fa-wallet text-4xl text-blue-600 mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Welcome Back</h1>
            <p class="text-gray-600">Login to your Digital Wallet</p>
        </div>
        
        <!-- Form Container -->
        <div class="p-6">
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-lg border border-red-200 mb-6">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li class="text-sm"><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm" class="space-y-6">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
                            placeholder="Enter your email address"
                        >
                    </div>
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
                            placeholder="Enter your password"
                        >
                        <button 
                            type="button" 
                            id="togglePassword" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-300"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Login Button -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </button>
            </form>
            
            <!-- Register Link -->
            <div class="text-center mt-6">
                <p class="text-gray-600 text-sm">
                    Don't have an account? 
                    <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-700 hover:underline transition-all duration-300">
                        Register
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            const errors = [];
            
            if (!email) {
                errors.push('Email address is required');
            } else if (!isValidEmail(email)) {
                errors.push('Please enter a valid email address');
            }
            
            if (!password) {
                errors.push('Password is required');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                
                // Remove existing error message
                const existingError = document.querySelector('.bg-red-50');
                if (existingError) {
                    existingError.remove();
                }
                
                // Create error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'bg-red-50 text-red-600 p-4 rounded-lg border border-red-200 mb-6';
                
                const errorList = document.createElement('ul');
                errorList.className = 'list-disc list-inside space-y-1';
                
                errors.forEach(error => {
                    const li = document.createElement('li');
                    li.className = 'text-sm';
                    li.textContent = error;
                    errorList.appendChild(li);
                });
                
                errorDiv.appendChild(errorList);
                
                // Insert error message
                const formContainer = document.querySelector('.p-6');
                const form = document.getElementById('loginForm');
                formContainer.insertBefore(errorDiv, form);
            }
        });
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Clear error when user starts typing
        document.getElementById('email').addEventListener('input', function() {
            const errorMessage = document.querySelector('.bg-red-50');
            if (errorMessage) errorMessage.remove();
        });
        
        document.getElementById('password').addEventListener('input', function() {
            const errorMessage = document.querySelector('.bg-red-50');
            if (errorMessage) errorMessage.remove();
        });
    </script>
</body>
</html>