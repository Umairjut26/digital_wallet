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
  <title>Login | Professional Form</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --secondary: #3498db;
      --accent: #1abc9c;
      --light: #ecf0f1;
      --dark: #2c3e50;
      --gray: #95a5a6;
      --success: #2ecc71;
      --danger: #e74c3c;
      --border: #bdc3c7;
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    * {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      overflow-x: hidden;
    }
    
    .login-container {
      max-width: 420px;
      width: 100%;
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      opacity: 0;
      transform: translateY(30px) scale(0.95);
      animation: formEntrance 0.8s ease forwards;
    }
    
    @keyframes formEntrance {
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    
    .brand-section {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      padding: 40px 30px;
      color: white;
      position: relative;
      overflow: hidden;
      text-align: center;
    }
    
    .brand-section::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      top: -150px;
      right: -100px;
      animation: float 6s ease-in-out infinite;
    }
    
    .brand-section::after {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      bottom: -100px;
      left: -80px;
      animation: float 8s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-20px); }
    }
    
    .logo {
      font-size: 4rem;
      margin-bottom: 15px;
      z-index: 1;
      position: relative;
      display: inline-block;
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    .brand-section h3 {
      font-weight: 600;
      margin-bottom: 10px;
      z-index: 1;
      position: relative;
    }
    
    .brand-section p {
      opacity: 0.9;
      z-index: 1;
      position: relative;
    }
    
    .form-section {
      padding: 40px 30px;
      position: relative;
    }
    
    .form-section h2 {
      color: var(--primary);
      font-weight: 700;
      margin-bottom: 5px;
      text-align: center;
    }
    
    .form-section p.lead {
      color: var(--gray);
      margin-bottom: 30px;
      text-align: center;
    }
    
    .form-group {
      margin-bottom: 25px;
      position: relative;
    }
    
    .form-control {
      border-radius: 12px;
      padding: 18px 20px 18px 50px;
      border: 2px solid var(--border);
      transition: var(--transition);
      font-size: 16px;
      height: auto;
      background: #f8f9fa;
    }
    
    .form-control:focus {
      border-color: var(--secondary);
      box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
      background: white;
      transform: translateY(-2px);
    }
    
    .input-icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
      z-index: 10;
      font-size: 20px;
      transition: var(--transition);
    }
    
    .form-control:focus + .input-icon {
      color: var(--secondary);
      transform: translateY(-50%) scale(1.1);
    }
    
    .btn-login {
      background: linear-gradient(to right, var(--secondary), var(--accent));
      border: none;
      padding: 16px 30px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 16px;
      transition: var(--transition);
      width: 100%;
      margin-top: 10px;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: 0.5s;
    }
    
    .btn-login:hover::before {
      left: 100%;
    }
    
    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
    }
    
    .register-link {
      text-align: center;
      margin-top: 25px;
      color: var(--gray);
    }
    
    .register-link a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
      position: relative;
    }
    
    .register-link a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -2px;
      left: 0;
      background-color: var(--accent);
      transition: var(--transition);
    }
    
    .register-link a:hover {
      color: var(--accent);
    }
    
    .register-link a:hover::after {
      width: 100%;
    }
    
    .alert {
      border-radius: 12px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 25px;
    }
    
    .password-toggle {
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--gray);
      z-index: 10;
      cursor: pointer;
      font-size: 18px;
      transition: var(--transition);
    }
    
    .password-toggle:hover {
      color: var(--secondary);
      transform: translateY(-50%) scale(1.1);
    }
    
    .form-check-input:checked {
      background-color: var(--secondary);
      border-color: var(--secondary);
    }
    
    .forgot-link {
      color: var(--secondary);
      text-decoration: none;
      font-size: 14px;
      transition: var(--transition);
      position: relative;
    }
    
    .forgot-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 1px;
      bottom: -2px;
      left: 0;
      background-color: var(--accent);
      transition: var(--transition);
    }
    
    .forgot-link:hover {
      color: var(--accent);
    }
    
    .forgot-link:hover::after {
      width: 100%;
    }
    
    .options-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    
    /* Floating label effect */
    .floating-label {
      position: absolute;
      top: 18px;
      left: 50px;
      color: var(--gray);
      font-size: 16px;
      pointer-events: none;
      transition: var(--transition);
      background: #f8f9fa;
      padding: 0 5px;
    }
    
    .form-control:focus ~ .floating-label,
    .form-control:not(:placeholder-shown) ~ .floating-label {
      top: -10px;
      left: 45px;
      font-size: 12px;
      color: var(--secondary);
      background: white;
      z-index: 5;
    }
    
    /* Social login buttons */
    .social-login {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 25px;
    }
    
    .social-btn {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      transition: var(--transition);
      border: none;
    }
    
    .social-btn:hover {
      transform: translateY(-3px) scale(1.1);
    }
    
    .social-google {
      background: #DB4437;
    }
    
    .social-facebook {
      background: #4267B2;
    }
    
    .social-twitter {
      background: #1DA1F2;
    }
    
    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      margin: 25px 0;
    }
    
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }
    
    .divider span {
      padding: 0 15px;
      color: var(--gray);
      font-size: 14px;
    }
    
    /* Input animation classes */
    .animate-input-1 {
      opacity: 0;
      transform: translateX(-20px);
      animation: slideInRight 0.6s ease forwards 0.3s;
    }
    
    .animate-input-2 {
      opacity: 0;
      transform: translateX(-20px);
      animation: slideInRight 0.6s ease forwards 0.5s;
    }
    
    .animate-options {
      opacity: 0;
      animation: fadeIn 0.6s ease forwards 0.7s;
    }
    
    .animate-button {
      opacity: 0;
      transform: translateY(20px);
      animation: slideInUp 0.6s ease forwards 0.9s;
    }
    
    .animate-links {
      opacity: 0;
      animation: fadeIn 0.6s ease forwards 1.1s;
    }
    
    @keyframes slideInRight {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes slideInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes fadeIn {
      to {
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Brand Section -->
    <div class="brand-section">
      <div class="logo">
        <i class="fas fa-user-shield"></i>
      </div>
      <h3>Welcome Back</h3>
      <p>Sign in to continue your journey with us</p>
    </div>
    
    <!-- Form Section -->
    <div class="form-section">
      <h2 class="animate__animated animate__fadeIn">Secure Login</h2>
      <p class="lead animate__animated animate__fadeIn animate__delay-1s">Enter your credentials to access your account</p>
      
      <!-- Error Messages -->
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
          <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      
      <form method="POST" id="loginForm">
        <!-- Email Field -->
        <div class="form-group animate-input-1">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" class="form-control" id="email" name="email" placeholder=" " value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          <label class="floating-label">Email Address</label>
        </div>
        
        <!-- Password Field -->
        <div class="form-group animate-input-2">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" class="form-control" id="password" name="password" placeholder=" ">
          <label class="floating-label">Password</label>
          <button type="button" class="password-toggle" id="togglePassword">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        
        <!-- Remember Me & Forgot Password -->
        <div class="options-row animate-options">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rememberCheck" name="rememberCheck">
            <label class="form-check-label" for="rememberCheck">
              Remember me
            </label>
          </div>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>
        
        <!-- Submit Button -->
        <button type="submit" class="btn btn-login animate-button">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
      </form>
      
      <!-- Divider -->
      <div class="divider animate-links">
        <span>or continue with</span>
      </div>
      
      <!-- Social Login -->
      <div class="social-login animate-links">
        <button class="social-btn social-google">
          <i class="fab fa-google"></i>
        </button>
        <button class="social-btn social-facebook">
          <i class="fab fa-facebook-f"></i>
        </button>
        <button class="social-btn social-twitter">
          <i class="fab fa-twitter"></i>
        </button>
      </div>
      
      <!-- Register Link -->
      <div class="register-link animate-links">
        Don't have an account? <a href="register.php">Register here</a>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  
  <script>
    // Password toggle functionality
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
    
    // Form validation and error display
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      // Clear previous errors
      const errorAlert = document.querySelector('.alert.alert-danger');
      if (errorAlert) {
        errorAlert.style.display = 'none';
      }
      
      // Get form values
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      
      // Simple validation (for demo purposes)
      const errors = [];
      
      if (!email) {
        errors.push('Email is required');
      } else if (!isValidEmail(email)) {
        errors.push('Please enter a valid email address');
      }
      
      if (!password) {
        errors.push('Password is required');
      }
      
      // Display errors if any
      if (errors.length > 0) {
        e.preventDefault();
        
        // Create error alert if it doesn't exist
        let errorAlert = document.querySelector('.alert.alert-danger');
        if (!errorAlert) {
          errorAlert = document.createElement('div');
          errorAlert.className = 'alert alert-danger animate__animated animate__shakeX';
          errorAlert.setAttribute('role', 'alert');
          
          const errorList = document.createElement('ul');
          errorList.className = 'mb-0';
          errorAlert.appendChild(errorList);
          
          const formSection = document.querySelector('.form-section');
          formSection.insertBefore(errorAlert, document.getElementById('loginForm'));
        }
        
        // Update error list
        const errorList = errorAlert.querySelector('ul');
        errorList.innerHTML = '';
        errors.forEach(error => {
          const li = document.createElement('li');
          li.textContent = error;
          errorList.appendChild(li);
        });
        
        // Show error alert
        errorAlert.style.display = 'block';
        errorAlert.classList.add('animate__shakeX');
        setTimeout(() => {
          errorAlert.classList.remove('animate__shakeX');
        }, 1000);
      } else {
        // Show SweetAlert for successful login
        e.preventDefault();
        
        // Simulate successful login for demo
        Swal.fire({
          title: 'Login Successful!',
          text: 'Welcome back! You will be redirected to dashboard in 2 seconds.',
          icon: 'success',
          showConfirmButton: false,
          timer: 2000,
          timerProgressBar: true,
          didOpen: () => {
            Swal.showLoading();
          },
          willClose: () => {
            // In real application, you would submit the form here
            document.getElementById('loginForm').submit();
          }
        });
      }
    });
    
    // Email validation function
    function isValidEmail(email) {
      const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(String(email).toLowerCase());
    }
    
    // Add focus animations to form inputs
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });
      
      input.addEventListener('blur', function() {
        if (!this.value) {
          this.parentElement.classList.remove('focused');
        }
      });
    });
  </script>
</body>
</html>