<?php
session_start();
require 'config.php';

$errors = [];
$registration_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Validation
    if ($name === '') $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $user = DB::queryFirstRow("SELECT id FROM users WHERE email=%s", $email);
        if ($user) {
            $errors[] = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            DB::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => $hash
            ]);

             $user_id = DB::insertId();

        // ✅ Create wallet for this user
        DB::insert('wallets', [
            'user_id' => $user_id,
            'name'    => $name,
            'balance' => 1000.00 // Starting balance
        ]);

            $_SESSION['user_id'] = DB::insertId();
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
  <title>Register | Professional Form</title>
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
    }
    
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    
    .register-card {
      max-width: 1000px;
      width: 100%;
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      opacity: 0;
      transform: translateY(30px) scale(0.95);
      animation: cardEntrance 0.8s ease forwards;
    }
    
    @keyframes cardEntrance {
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    
    .brand-section {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 50px 40px;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .brand-section::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      top: -100px;
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
      bottom: -80px;
      left: -80px;
      animation: float 8s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(5deg); }
    }
    
    .logo {
      font-size: 4rem;
      margin-bottom: 20px;
      z-index: 1;
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    .brand-section h3 {
      font-weight: 700;
      margin-bottom: 15px;
      z-index: 1;
      font-size: 1.8rem;
    }
    
    .brand-section p {
      text-align: center;
      opacity: 0.9;
      z-index: 1;
      font-size: 1.1rem;
      line-height: 1.6;
    }
    
    .form-section {
      padding: 50px 40px;
      background: #fff;
    }
    
    .form-section h2 {
      color: var(--primary);
      font-weight: 800;
      margin-bottom: 8px;
      font-size: 2.2rem;
      position: relative;
      display: inline-block;
    }
    
    .form-section h2::after {
      content: '';
      position: absolute;
      bottom: -5px;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--accent);
      border-radius: 2px;
    }
    
    .form-section p.lead {
      color: var(--gray);
      margin-bottom: 40px;
      font-size: 1.1rem;
    }
    
    .form-group {
      margin-bottom: 25px;
      position: relative;
    }
    
    .form-label {
      position: absolute;
      top: 18px;
      left: 55px;
      color: var(--gray);
      transition: var(--transition);
      pointer-events: none;
      font-size: 1rem;
      z-index: 5;
      background: white;
      padding: 0 5px;
    }
    
    .form-control {
      border-radius: 12px;
      padding: 18px 20px 18px 55px;
      border: 2px solid var(--border);
      transition: var(--transition);
      font-size: 1rem;
      height: 60px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .form-control:focus {
      border-color: var(--secondary);
      box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
      transform: translateY(-2px);
    }
    
    .form-control:focus + .form-label,
    .form-control:not(:placeholder-shown) + .form-label {
      top: -10px;
      left: 50px;
      font-size: 0.85rem;
      color: var(--secondary);
      font-weight: 600;
    }
    
    .input-icon {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
      z-index: 10;
      font-size: 20px;
      transition: var(--transition);
    }
    
    .form-control:focus ~ .input-icon {
      color: var(--secondary);
      transform: translateY(-50%) scale(1.1);
    }
    
    .btn-register {
      background: linear-gradient(to right, var(--secondary), var(--accent));
      border: none;
      padding: 16px 30px;
      border-radius: 12px;
      font-weight: 700;
      font-size: 18px;
      transition: var(--transition);
      width: 100%;
      margin-top: 20px;
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    }
    
    .btn-register:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(52, 152, 219, 0.4);
    }
    
    .btn-register:active {
      transform: translateY(-2px);
    }
    
    .btn-register::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 5px;
      height: 5px;
      background: rgba(255, 255, 255, 0.5);
      opacity: 0;
      border-radius: 100%;
      transform: scale(1, 1) translate(-50%);
      transform-origin: 50% 50%;
    }
    
    .btn-register:focus:not(:active)::after {
      animation: ripple 1s ease-out;
    }
    
    @keyframes ripple {
      0% {
        transform: scale(0, 0);
        opacity: 0.5;
      }
      100% {
        transform: scale(20, 20);
        opacity: 0;
      }
    }
    
    .login-link {
      text-align: center;
      margin-top: 30px;
      color: var(--gray);
      font-size: 1rem;
    }
    
    .login-link a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
      position: relative;
    }
    
    .login-link a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--accent);
      transition: var(--transition);
    }
    
    .login-link a:hover {
      color: var(--accent);
    }
    
    .login-link a:hover::after {
      width: 100%;
    }
    
    .alert {
      border-radius: 12px;
      border: none;
      padding: 20px 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .password-toggle {
      position: absolute;
      right: 20px;
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
    
    .form-check {
      margin-top: 10px;
      padding-left: 30px;
    }
    
    .form-check-input {
      width: 20px;
      height: 20px;
      margin-left: -30px;
      margin-top: 5px;
      border: 2px solid var(--border);
      transition: var(--transition);
    }
    
    .form-check-input:checked {
      background-color: var(--secondary);
      border-color: var(--secondary);
      box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }
    
    .form-check-label {
      color: var(--gray);
      font-size: 0.95rem;
    }
    
    .terms-link {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }
    
    .terms-link:hover {
      color: var(--accent);
    }
    
    .input-success .form-control {
      border-color: var(--success);
    }
    
    .input-success .input-icon {
      color: var(--success);
    }
    
    .input-error .form-control {
      border-color: var(--danger);
    }
    
    .input-error .input-icon {
      color: var(--danger);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .brand-section {
        padding: 40px 30px;
      }
      
      .logo {
        font-size: 3rem;
      }
      
      .brand-section h3 {
        font-size: 1.6rem;
      }
      
      .form-section {
        padding: 40px 30px;
      }
      
      .form-section h2 {
        font-size: 1.8rem;
      }
    }
    
    /* Animation classes */
    .animate-field {
      opacity: 0;
      transform: translateX(-20px);
      animation: slideInRight 0.6s ease forwards;
    }
    
    @keyframes slideInRight {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    .animate-field:nth-child(1) { animation-delay: 0.1s; }
    .animate-field:nth-child(2) { animation-delay: 0.2s; }
    .animate-field:nth-child(3) { animation-delay: 0.3s; }
    .animate-field:nth-child(4) { animation-delay: 0.4s; }
    .animate-field:nth-child(5) { animation-delay: 0.5s; }
    .animate-field:nth-child(6) { animation-delay: 0.6s; }
    
    .success-checkmark {
      display: none;
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--success);
      font-size: 20px;
      z-index: 10;
    }
    
    .input-success .success-checkmark {
      display: block;
      animation: bounceIn 0.6s ease;
    }
  </style>
</head>
<body>
  <div class="register-card">
    <div class="row g-0">
      <!-- Brand Section -->
      <div class="col-lg-5 d-none d-lg-block">
        <div class="brand-section">
          <div class="logo">
            <i class="fas fa-rocket"></i>
          </div>
          <h3>Join Our Platform</h3>
          <p>Create an account to access exclusive features and connect with professionals worldwide.</p>
        </div>
      </div>
      
      <!-- Form Section -->
      <div class="col-lg-7">
        <div class="form-section">
          <h2 class="animate__animated animate__fadeIn">Create Account</h2>
          <p class="lead animate__animated animate__fadeIn animate__delay-1s">Fill in your details to get started</p>
          
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
          
          <form method="POST" id="registerForm">
            <!-- Name Field -->
            <div class="form-group animate-field">
              <i class="fas fa-user input-icon"></i>
              <input type="text" class="form-control" id="name" name="name" placeholder=" " value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
              <label for="name" class="form-label">Full Name</label>
              <i class="fas fa-check success-checkmark"></i>
            </div>
            
            <!-- Email Field -->
            <div class="form-group animate-field">
              <i class="fas fa-envelope input-icon"></i>
              <input type="email" class="form-control" id="email" name="email" placeholder=" " value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
              <label for="email" class="form-label">Email Address</label>
              <i class="fas fa-check success-checkmark"></i>
            </div>
            
            <!-- Password Field -->
            <div class="form-group animate-field">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" class="form-control" id="password" name="password" placeholder=" ">
              <label for="password" class="form-label">Password</label>
              <button type="button" class="password-toggle" id="togglePassword">
                <i class="fas fa-eye"></i>
              </button>
              <i class="fas fa-check success-checkmark"></i>
            </div>
            
            <!-- Confirm Password Field -->
            <div class="form-group animate-field">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" class="form-control" id="confirm" name="confirm" placeholder=" ">
              <label for="confirm" class="form-label">Confirm Password</label>
              <button type="button" class="password-toggle" id="toggleConfirmPassword">
                <i class="fas fa-eye"></i>
              </button>
              <i class="fas fa-check success-checkmark"></i>
            </div>
            
            <!-- Terms Checkbox -->
            <div class="form-check mb-4 animate-field">
              <input class="form-check-input" type="checkbox" id="termsCheck" name="termsCheck">
              <label class="form-check-label" for="termsCheck">
                I agree to the <a href="#" class="terms-link">Terms and Conditions</a>
              </label>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="btn btn-register animate-field">
              <i class="fas fa-user-plus me-2"></i>Create Account
            </button>
          </form>
          
          <!-- Login Link -->
          <div class="login-link animate__animated animate__fadeIn animate__delay-2s">
            Already have an account? <a href="login.php">Sign in here</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  
  <script>
    // Show SweetAlert if registration was successful
    <?php if ($registration_success): ?>
      document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
          title: 'Registration Successful!',
          text: 'Welcome to our community! You will be redirected to login page in 2 seconds.',
          icon: 'success',
          showConfirmButton: false,
          timer: 2000,
          timerProgressBar: true,
          didOpen: () => {
            Swal.showLoading();
          },
          willClose: () => {
            window.location.href = 'login.php';
          }
        });
      });
    <?php endif; ?>
    
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
    
    // Form validation and error display
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      // Clear previous errors
      const errorAlert = document.querySelector('.alert.alert-danger');
      if (errorAlert) {
        errorAlert.style.display = 'none';
      }
      
      // Get form values
      const name = document.getElementById('name').value;
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      const confirm = document.getElementById('confirm').value;
      const termsCheck = document.getElementById('termsCheck').checked;
      
      // Simple validation (for demo purposes)
      const errors = [];
      
      if (!name) {
        errors.push('Name is required');
      }
      
      if (!email) {
        errors.push('Email is required');
      } else if (!isValidEmail(email)) {
        errors.push('Please enter a valid email address');
      }
      
      if (!password) {
        errors.push('Password is required');
      } else if (password.length < 6) {
        errors.push('Password must be at least 6 characters long');
      }
      
      if (password !== confirm) {
        errors.push('Passwords do not match');
      }
      
      if (!termsCheck) {
        errors.push('You must agree to the terms and conditions');
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
          formSection.insertBefore(errorAlert, document.getElementById('registerForm'));
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
      }
    });
    
    // Email validation function
    function isValidEmail(email) {
      const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return re.test(String(email).toLowerCase());
    }
    
    // Add validation feedback to form inputs
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('blur', function() {
        const parent = this.parentElement;
        const checkmark = parent.querySelector('.success-checkmark');
        
        if (this.value && this.checkValidity()) {
          parent.classList.add('input-success');
          parent.classList.remove('input-error');
          checkmark.style.display = 'block';
        } else if (this.value && !this.checkValidity()) {
          parent.classList.add('input-error');
          parent.classList.remove('input-success');
          checkmark.style.display = 'none';
        } else {
          parent.classList.remove('input-success', 'input-error');
          checkmark.style.display = 'none';
        }
      });
      
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });
    });
  </script>
</body>
</html>