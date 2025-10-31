<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// User info
$user = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $user_id);
$wallet = DB::queryFirstRow("SELECT * FROM wallets WHERE user_id=%i", $user_id);

$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');

    // Validation
    if ($name === '') {
        $error_msg = "Name is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Valid email is required.";
    } elseif (strlen($mobile) !== 10 || !ctype_digit($mobile)) {
        $error_msg = "Valid 10-digit mobile number is required.";
    } else {
        // Check if email or mobile already exists for other users
        $existing_user = DB::queryFirstRow("SELECT id FROM users WHERE (email=%s OR mobile=%s) AND id != %i", $email, $mobile, $user_id);
        if ($existing_user) {
            $error_msg = "Email or mobile number already registered by another user.";
        } else {
            // Update user profile
            DB::update('users', [
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'updated_at' => date('Y-m-d H:i:s')
            ], "id=%i", $user_id);

            // Update wallet account name if changed
            if ($wallet && $wallet['account_name'] !== $name) {
                DB::update('wallets', [
                    'account_name' => $name
                ], "user_id=%i", $user_id);
            }

            $_SESSION['user_name'] = $name;
            $user['name'] = $name;
            $user['email'] = $email;
            $user['mobile'] = $mobile;
            
            $success_msg = "Profile updated successfully!";
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "New password must be at least 6 characters.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            DB::update('users', [
                'password' => $new_hash,
                'updated_at' => date('Y-m-d H:i:s')
            ], "id=%i", $user_id);
            
            $success_msg = "Password changed successfully!";
        } else {
            $error_msg = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | DigitalPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0052FF',
                        'primary-light': '#4D7CFF',
                        'primary-dark': '#0039B3',
                        secondary: '#6B7280',
                        success: '#10B981',
                        danger: '#EF4444',
                        warning: '#F59E0B',
                        dark: '#1F2937',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }
        .shadow-soft {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .shadow-card {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-gradient {
            background: linear-gradient(180deg, #1F2937 0%, #374151 100%);
        }
        .sidebar-collapsed {
            width: 100px !important;
        }
        .sidebar-expanded {
            width: 256px !important;
        }
        .main-content-expanded {
            margin-left: 256px !important;
        }
        .main-content-collapsed {
            margin-left: 80px !important;
        }
        .transition-width {
            transition: width 0.3s ease-in-out;
        }
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Main Container -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-gradient text-white fixed h-full z-40 sidebar-expanded transition-width">
            <div class="p-4 h-full flex flex-col">
                <!-- Logo & Toggle -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center space-x-3" id="logo-full">
                        <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-wallet text-white text-lg"></i>
                        </div>
                        <span class="text-xl font-bold whitespace-nowrap">DigitalPay</span>
                    </div>
                    <div class="w-10 h-10 flex items-center justify-center" id="logo-mini" style="display: none;">
                        <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-wallet text-white text-lg"></i>
                        </div>
                    </div>
                    <button id="sidebarToggle" class="text-gray-300 hover:text-white p-2 rounded-lg hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>

                <!-- Navigation -->
<nav class="space-y-2 flex-1">
                    <a href="wallet.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
        <i class="fas fa-home text-lg w-6 text-center"></i>
        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Dashboard</span>
    </a>
    <a href="transactions.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Transactions</span>
    </a>
    <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
        <i class="fas fa-chart-line text-lg w-6 text-center"></i>
        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Analytics</span>
    </a>
                    <a href="transactions.php" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium">
        <i class="fas fa-cog text-lg w-6 text-center"></i>
        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Settings</span>
    </a>
</nav>
                
                <!-- User Profile -->
                <div class="border-t border-gray-600 pt-4">
                    <div class="flex items-center space-x-3" id="user-info-full">
                        <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                            <?php 
                                $initials = '';
                                if (isset($user['name'])) {
                                    $nameParts = explode(' ', $user['name']);
                                    foreach ($nameParts as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                }
                                echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm truncate"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                            <p class="text-xs text-gray-300 truncate"><?php echo htmlspecialchars($user['email'] ?? 'user@example.com'); ?></p>
                        </div>
                    </div>
                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-semibold mx-auto" id="user-info-mini" style="display: none;">
                        <?php 
                            $initials = '';
                            if (isset($user['name'])) {
                                $nameParts = explode(' ', $user['name']);
                                foreach ($nameParts as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                }
                            }
                            echo substr($initials, 0, 2);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div id="mainContent" class="flex-1 main-content-expanded transition-margin">
            <!-- Top Header -->
            <header class="bg-white shadow-soft border-b border-gray-200 sticky top-0 z-30">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                            <p class="text-gray-500 text-sm">Manage your account preferences</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-600 hover:text-primary transition-colors duration-300">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full"></span>
                        </button>
                        
                        <!-- Quick Actions -->
                        <button onclick="window.location.href='wallet.php'" 
                                class="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-all duration-300 flex items-center space-x-2 shadow-soft">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Dashboard</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Messages -->
                <?php if ($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border border-green-200 animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success_msg); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6 border border-red-200 animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column - Profile Settings -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Profile Information -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 animate-slide-up">
                            <h3 class="text-xl font-bold text-gray-900 mb-6">Profile Information</h3>
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                        <input type="text" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                                    </div>
                                    
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" id="mobile" name="mobile" maxlength="10"
                                               value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Number</label>
                                        <div class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-600">
                                            <?php echo htmlspecialchars($wallet['account_number'] ?? 'N/A'); ?>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Account number cannot be changed</p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end pt-4">
                                    <button type="submit" 
                                            class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-dark transition-all duration-300 flex items-center space-x-2 shadow-soft hover:shadow-md">
                                        <i class="fas fa-save"></i>
                                        <span>Update Profile</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Settings -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 animate-slide-up" style="animation-delay: 0.1s">
                            <h3 class="text-xl font-bold text-gray-900 mb-6">Security Settings</h3>
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                    <div class="relative">
                                        <input type="password" id="current_password" name="current_password" 
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft pr-12">
                                        <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 password-toggle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <div class="relative">
                                            <input type="password" id="new_password" name="new_password" 
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft pr-12">
                                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 password-toggle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <div class="relative">
                                            <input type="password" id="confirm_password" name="confirm_password" 
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft pr-12">
                                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 password-toggle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end pt-4">
                                    <button type="submit" 
                                            class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-dark transition-all duration-300 flex items-center space-x-2 shadow-soft hover:shadow-md">
                                        <i class="fas fa-key"></i>
                                        <span>Change Password</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column - Account Info & Preferences -->
                    <div class="space-y-6">
                        <!-- Account Status -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 animate-slide-up" style="animation-delay: 0.2s">
                            <h3 class="text-xl font-bold text-gray-900 mb-6">Account Status</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Verification Status</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full font-medium">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Member Since</span>
                                    <span class="font-medium"><?php echo date('M j, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Last Updated</span>
                                    <span class="font-medium"><?php echo date('M j, Y', strtotime($user['updated_at'] ?? $user['created_at'] ?? 'now')); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Security Features -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 animate-slide-up" style="animation-delay: 0.3s">
                            <h3 class="text-xl font-bold text-gray-900 mb-6">Security Features</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Two-Factor Authentication</h4>
                                            <p class="text-sm text-gray-500">Enhanced security</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-green-500 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Email Notifications</h4>
                                            <p class="text-sm text-gray-500">Transaction alerts</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-blue-500 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600">
                                            <i class="fas fa-fingerprint"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">Biometric Login</h4>
                                            <p class="text-sm text-gray-500">Fingerprint/Face ID</p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="bg-red-50 border border-red-200 rounded-2xl p-6 animate-slide-up" style="animation-delay: 0.4s">
                            <h3 class="text-xl font-bold text-red-800 mb-4">Danger Zone</h3>
                            <p class="text-red-700 mb-4 text-sm">Once you delete your account, there is no going back. Please be certain.</p>
                            <button onclick="confirmDelete()" 
                                    class="w-full bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700 transition-all duration-300 flex items-center justify-center space-x-2">
                                <i class="fas fa-trash-alt"></i>
                                <span>Delete Account</span>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <script>
        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const navTexts = document.querySelectorAll('#nav-text');
        const logoFull = document.getElementById('logo-full');
        const logoMini = document.getElementById('logo-mini');
        const userInfoFull = document.getElementById('user-info-full');
        const userInfoMini = document.getElementById('user-info-mini');

        let isSidebarCollapsed = false;

        function toggleSidebar() {
            isSidebarCollapsed = !isSidebarCollapsed;
            
            if (isSidebarCollapsed) {
                sidebar.classList.remove('sidebar-expanded');
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.remove('main-content-expanded');
                mainContent.classList.add('main-content-collapsed');

                navTexts.forEach(text => {
                    text.style.opacity = '0';
                    text.style.display = 'none';
                });

                logoFull.style.display = 'none';
                logoMini.style.display = 'flex';
                logoMini.style.justifyContent = 'center';

                userInfoFull.style.display = 'none';
                userInfoMini.style.display = 'flex';
                userInfoMini.style.justifyContent = 'center';
                userInfoMini.style.alignItems = 'center';

            } else {
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.classList.add('sidebar-expanded');
                mainContent.classList.remove('main-content-collapsed');
                mainContent.classList.add('main-content-expanded');

                navTexts.forEach(text => {
                    text.style.display = 'block';
                    setTimeout(() => {
                        text.style.opacity = '1';
                    }, 50);
                });

                logoFull.style.display = 'flex';
                logoMini.style.display = 'none';

                userInfoFull.style.display = 'flex';
                userInfoMini.style.display = 'none';
            }
        }

        sidebarToggle.addEventListener('click', toggleSidebar);

        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        // Mobile number input formatting
        document.getElementById('mobile').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Account deletion confirmation
        function confirmDelete() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this! All your data will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                background: '#fff',
                color: '#1F2937'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Your account has been scheduled for deletion.',
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false,
                        background: '#fff',
                        color: '#1F2937'
                    });
                }
            });
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease-in-out';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>