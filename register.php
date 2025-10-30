<?php
session_start();
require 'config.php';

$errors = [];
$registration_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Validation
    if ($name === '') $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($mobile) !== 10 || !ctype_digit($mobile)) {
        $errors[] = "Valid 10-digit mobile number is required.";
    }
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $user = DB::queryFirstRow("SELECT id FROM users WHERE email=%s OR mobile=%s", $email, $mobile);
        if ($user) {
            $errors[] = "Email or mobile number already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            DB::insert('users', [
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => $hash
            ]);

            $user_id = DB::insertId();

            // Create wallet for this user
            DB::insert('wallets', [
                'user_id' => $user_id,
                'balance' => 1000.00
            ]);

            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $registration_success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Digital Ease Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-5">
    <div class="w-full max-w-lg bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-white px-8 py-10 border-b border-gray-200 text-center">
            <i class="fas fa-wallet text-5xl text-blue-600 mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Create Account</h1>
            <p class="text-gray-600">Sign up to start using Digital Wallet</p>
        </div>
        
        <!-- Form Container -->
        <div class="px-8 py-6">
            <!-- Success Message -->
            <?php if ($registration_success): ?>
                <div id="successMessage" class="bg-green-50 text-green-700 p-3 rounded-lg mb-4 text-sm">
                    Registration successful! Redirecting to login...
                </div>
            <?php endif; ?>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div id="errorMessage" class="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm" class="space-y-5">
                <!-- Name Field -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            placeholder="Enter your full name"
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition-colors"
                        >
                    </div>
                </div>
                
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email address"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition-colors"
                        >
                    </div>
                </div>
                
                <!-- Mobile Number Field -->
                <div>
                    <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <div class="relative">
                        <i class="fas fa-phone absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="tel" 
                            id="mobile" 
                            name="mobile" 
                            placeholder="Enter 10-digit mobile number" 
                            maxlength="10"
                            value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition-colors"
                        >
                    </div>
                </div>
                
                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition-colors"
                        >
                        <button 
                            type="button" 
                            class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            id="togglePassword"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Confirm Password Field -->
                <div>
                    <label for="confirm" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="password" 
                            id="confirm" 
                            name="confirm" 
                            placeholder="Confirm your password"
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 transition-colors"
                        >
                        <button 
                            type="button" 
                            class="password-toggle absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            id="toggleConfirmPassword"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                    <i class="fas fa-user-plus"></i>
                    Register
                </button>
            </form>
            
            <!-- Login Link -->
            <div class="text-center mt-6 text-gray-600 text-sm">
                Already have an account? 
                <a href="login.php" class="text-blue-600 font-medium hover:text-blue-700 hover:underline">Login</a>
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
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmInput = document.getElementById('confirm');
            const icon = this.querySelector('i');
            
            if (confirmInput.type === 'password') {
                confirmInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Mobile number formatting (only allow numbers)
        document.getElementById('mobile').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const mobile = document.getElementById('mobile').value;
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm').value;
            
            const errors = [];
            
            if (!name) {
                errors.push('Full name is required');
            }
            
            if (!email) {
                errors.push('Email address is required');
            } else if (!isValidEmail(email)) {
                errors.push('Please enter a valid email address');
            }
            
            if (!mobile) {
                errors.push('Mobile number is required');
            } else if (!/^\d{10}$/.test(mobile)) {
                errors.push('Mobile number must be exactly 10 digits');
            }
            
            if (!password) {
                errors.push('Password is required');
            } else if (password.length < 6) {
                errors.push('Password must be at least 6 characters long');
            }
            
            if (!confirm) {
                errors.push('Please confirm your password');
            } else if (password !== confirm) {
                errors.push('Passwords do not match');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                
                const errorMessage = document.getElementById('errorMessage');
                if (!errorMessage) {
                    const errorDiv = document.createElement('div');
                    errorDiv.id = 'errorMessage';
                    errorDiv.className = 'bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm';
                    
                    const errorList = document.createElement('ul');
                    errorList.className = 'list-disc list-inside';
                    
                    errors.forEach(error => {
                        const li = document.createElement('li');
                        li.textContent = error;
                        errorList.appendChild(li);
                    });
                    
                    errorDiv.appendChild(errorList);
                    
                    const formContainer = document.querySelector('.px-8.py-6');
                    const successMessage = document.querySelector('#successMessage');
                    const existingError = document.querySelector('#errorMessage');
                    
                    if (successMessage) {
                        formContainer.insertBefore(errorDiv, successMessage.nextSibling);
                    } else if (existingError) {
                        existingError.replaceWith(errorDiv);
                    } else {
                        formContainer.insertBefore(errorDiv, document.getElementById('registerForm'));
                    }
                } else {
                    const errorList = errorMessage.querySelector('ul');
                    errorList.innerHTML = '';
                    errors.forEach(error => {
                        const li = document.createElement('li');
                        li.textContent = error;
                        errorList.appendChild(li);
                    });
                    errorMessage.classList.remove('hidden');
                }
            }
        });
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Clear error when user starts typing
        const inputs = ['name', 'email', 'mobile', 'password', 'confirm'];
        inputs.forEach(inputId => {
            document.getElementById(inputId).addEventListener('input', function() {
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.classList.add('hidden');
            });
        });
        
        // Redirect on success
        <?php if ($registration_success): ?>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>