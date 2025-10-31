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

// Wallet info
$wallet = DB::queryFirstRow("SELECT * FROM wallets WHERE user_id=%i", $user_id);
$balance = $wallet ? $wallet['balance'] : 0;

// Recent transactions (last 5)
$recent_transactions = DB::query("
    SELECT 
        t.id, 
        t.amount, 
        t.created_at, 
        s.name AS sender_name, 
        r.name AS receiver_name,
        CASE 
            WHEN t.sender_id = %i THEN 'sent'
            ELSE 'received'
        END AS type
    FROM transactions t
    LEFT JOIN users s ON t.sender_id = s.id
    LEFT JOIN users r ON t.receiver_id = r.id
    WHERE t.sender_id=%i OR t.receiver_id=%i
    ORDER BY t.id DESC
    LIMIT 5
", $user_id, $user_id, $user_id);

// All transactions for the full history
$all_transactions = DB::query("
    SELECT 
        t.id, 
        t.amount, 
        t.created_at, 
        s.name AS sender_name, 
        r.name AS receiver_name,
        CASE 
            WHEN t.sender_id = %i THEN 'sent'
            ELSE 'received'
        END AS type
    FROM transactions t
    LEFT JOIN users s ON t.sender_id = s.id
    LEFT JOIN users r ON t.receiver_id = r.id
    WHERE t.sender_id=%i OR t.receiver_id=%i
    ORDER BY t.id DESC
", $user_id, $user_id, $user_id);

// All other users for dropdown
$all_users = DB::query("SELECT id, name, email FROM users WHERE id != %i", $user_id);

// Calculate stats
$total_income = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE receiver_id=%i AND status='completed'
", $user_id) ?: 0;

$total_expenses = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE sender_id=%i AND status='completed'
", $user_id) ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigitalPay - Professional Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        'pulse-soft': 'pulseSoft 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        pulseSoft: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.8' },
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
        .gradient-bg {
            background: linear-gradient(135deg, #0052FF 0%, #4D7CFF 50%, #0039B3 100%);
        }
        .sidebar-gradient {
            background: linear-gradient(180deg, #1F2937 0%, #374151 100%);
        }
        .sidebar-collapsed {
            width: 80px !important;
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
                    <a href="#" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium group">
                        <i class="fas fa-home text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Dashboard</span>
                    </a>
                    <a href="transactions.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
                        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Transactions</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
                        <i class="fas fa-chart-line text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity" id="nav-text">Analytics</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
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
                            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                            <p class="text-gray-500 text-sm">Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>! 👋</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <button class="relative p-2 text-gray-600 hover:text-primary transition-colors duration-300">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full"></span>
                        </button>
                        
                        <!-- Quick Actions -->
                        <button onclick="window.location.href='logout.php'" 
                                class="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-all duration-300 flex items-center space-x-2 shadow-soft">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Quick Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Balance Card -->
                    <div class="gradient-bg text-white rounded-2xl p-6 shadow-card relative overflow-hidden animate-fade-in">
                        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-20 h-20 rounded-full bg-white bg-opacity-10"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-medium opacity-90">Total Balance</h3>
                                <i class="fas fa-wallet opacity-70"></i>
                            </div>
                            <div class="text-2xl font-bold mb-2">$<?php echo number_format($balance, 2); ?></div>
                            <p class="text-sm opacity-80">Available to spend</p>
                        </div>
                    </div>

                    <!-- Income Card -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 hover:shadow-lg transition-all duration-300 animate-slide-up">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Income</h3>
                                <div class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_income, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-xl shadow-soft">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-green-600">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i>
                            <span>All time received</span>
                        </div>
                    </div>

                    <!-- Expense Card -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 hover:shadow-lg transition-all duration-300 animate-slide-up" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Expenses</h3>
                                <div class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_expenses, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 text-xl shadow-soft">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-red-600">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i>
                            <span>All time sent</span>
                        </div>
                    </div>

                    <!-- Transactions Card -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 hover:shadow-lg transition-all duration-300 animate-slide-up" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Transactions</h3>
                                <div class="text-2xl font-bold text-gray-900"><?php echo count($all_transactions); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-xl shadow-soft">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-blue-600">
                            <i class="fas fa-chart-line mr-1 text-xs"></i>
                            <span>Transaction count</span>
                        </div>
                    </div>
                </div>

                <!-- Action Cards & Recent Transactions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column - Quick Actions -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <button id="openSendModal" 
                                        class="bg-primary text-white p-4 rounded-xl font-semibold hover:bg-primary-dark transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md">
                                    <i class="fas fa-paper-plane text-xl"></i>
                                    <span class="text-sm">Send Money</span>
                                </button>
                                <button class="bg-success text-white p-4 rounded-xl font-semibold hover:bg-green-700 transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md">
                                    <i class="fas fa-plus text-xl"></i>
                                    <span class="text-sm">Add Funds</span>
                                </button>
                                <button class="bg-warning text-white p-4 rounded-xl font-semibold hover:bg-yellow-700 transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md">
                                    <i class="fas fa-download text-xl"></i>
                                    <span class="text-sm">Withdraw</span>
                                </button>
                                <button class="bg-secondary text-white p-4 rounded-xl font-semibold hover:bg-gray-600 transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md">
                                    <i class="fas fa-qrcode text-xl"></i>
                                    <span class="text-sm">QR Pay</span>
                                </button>
                            </div>
                        </div>

                        <!-- Account Summary -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Account Summary</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Account Number</span>
                                    <span class="font-mono text-sm"><?php echo htmlspecialchars($wallet['account_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Member Since</span>
                                    <span class="font-medium"><?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Status</span>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Verified</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Recent Transactions -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-card border border-gray-100 overflow-hidden">
                            <!-- Transaction Header -->
                            <div class="border-b border-gray-200 px-6 py-4">
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Recent Transactions</h2>
                                        <p class="text-gray-500 text-sm mt-1">Your latest financial activities</p>
                                    </div>
                                    <button onclick="window.location.href='transactions.php'" 
                                            class="bg-gray-100 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center space-x-2 shadow-soft mt-3 sm:mt-0">
                                        <i class="fas fa-list"></i>
                                        <span>View All</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Transaction List -->
                            <div class="divide-y divide-gray-200">
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $t): ?>
                                        <div class="px-6 py-4 hover:bg-gray-50 transition-colors duration-200">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-4">
                                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center <?php echo $t['type'] == 'sent' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                                                        <i class="fas fa-<?php echo $t['type'] == 'sent' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold text-gray-900">
                                                            <?php 
                                                                if ($t['type'] == 'sent') {
                                                                    echo htmlspecialchars($t['receiver_name']);
                                                                } else {
                                                                    echo htmlspecialchars($t['sender_name']);
                                                                }
                                                            ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="font-bold text-lg <?php echo $t['type'] == 'sent' ? 'text-red-600' : 'text-green-600'; ?>">
                                                        <?php echo $t['type'] == 'sent' ? '-' : '+'; ?>$<?php echo number_format($t['amount'], 2); ?>
                                                    </div>
                                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $t['type'] == 'sent' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <?php echo ucfirst($t['type']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="px-6 py-12 text-center">
                                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <i class="fas fa-exchange-alt text-gray-400 text-xl"></i>
                                        </div>
                                        <p class="text-gray-500 text-lg font-medium">No transactions yet</p>
                                        <p class="text-gray-400 mt-1">Send or receive money to get started!</p>
                                        <button id="openSendModal2" class="mt-4 bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary-dark transition-all duration-300">
                                            Make Your First Transaction
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Additional Info Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <!-- Security Status -->
                            <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-bold text-gray-900">Security Status</h3>
                                    <i class="fas fa-shield-alt text-green-500"></i>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">2FA Enabled</span>
                                        <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Biometric Login</span>
                                        <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Last Login</span>
                                        <span class="text-sm text-gray-500">Just now</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Support Card -->
                            <div class="bg-gradient-to-r from-primary to-primary-light text-white rounded-2xl p-6 shadow-card relative overflow-hidden">
                                <div class="absolute top-0 right-0 -mt-10 -mr-10 w-20 h-20 rounded-full bg-white bg-opacity-10"></div>
                                <div class="relative z-10">
                                    <h3 class="font-bold mb-2">Need Help?</h3>
                                    <p class="text-sm opacity-90 mb-4">Our support team is here to help you 24/7</p>
                                    <button class="bg-white text-primary px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 transition-all duration-300">
                                        Contact Support
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Send Money Modal -->
    <div id="sendModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden animate-fade-in">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-card">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Send Money</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700 text-xl transition-colors duration-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label for="receiver_account" class="block text-sm font-medium text-gray-700 mb-2">Receiver Account Number</label>
                    <input type="text" id="receiver_account" placeholder="Enter receiver account number"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                </div>

                <div>
                    <label for="receiver_name" class="block text-sm font-medium text-gray-700 mb-2">Receiver Name</label>
                    <input type="text" id="receiver_name" placeholder="Enter receiver full name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                </div>
                
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                    <input type="number" id="amount" step="0.01" placeholder="Enter amount" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                </div>
                
                <button id="sendBtn" 
                        class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-primary-dark transition-all duration-300 flex items-center justify-center space-x-2 shadow-soft hover:shadow-md">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Payment</span>
                </button>
                
                <div id="message" class="mt-4"></div>
            </div>
        </div>
    </div>

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
                // Collapse sidebar
                sidebar.classList.remove('sidebar-expanded');
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.remove('main-content-expanded');
                mainContent.classList.add('main-content-collapsed');
                
                // Hide text elements
                navTexts.forEach(text => {
                    text.style.opacity = '0';
                    text.style.display = 'none';
                });
                
                // Switch logos
                logoFull.style.display = 'none';
                logoMini.style.display = 'block';
                
                // Switch user info
                userInfoFull.style.display = 'none';
                userInfoMini.style.display = 'block';
                
            } else {
                // Expand sidebar
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.classList.add('sidebar-expanded');
                mainContent.classList.remove('main-content-collapsed');
                mainContent.classList.add('main-content-expanded');
                
                // Show text elements
                navTexts.forEach(text => {
                    text.style.display = 'block';
                    setTimeout(() => {
                        text.style.opacity = '1';
                    }, 50);
                });
                
                // Switch logos
                logoFull.style.display = 'flex';
                logoMini.style.display = 'none';
                
                // Switch user info
                userInfoFull.style.display = 'flex';
                userInfoMini.style.display = 'none';
            }
        }

        sidebarToggle.addEventListener('click', toggleSidebar);

        // Modal functionality
        const openModalBtn = document.getElementById('openSendModal');
        const openModalBtn2 = document.getElementById('openSendModal2');
        const closeModalBtn = document.getElementById('closeModal');
        const modal = document.getElementById('sendModal');
        
        [openModalBtn, openModalBtn2].forEach(btn => {
            if (btn) btn.addEventListener('click', () => modal.classList.remove('hidden'));
        });
        
        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            document.getElementById('message').innerHTML = '';
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                document.getElementById('message').innerHTML = '';
            }
        });

        // Send payment functionality
        document.getElementById('sendBtn').addEventListener('click', async () => {
            const receiver_account = document.getElementById('receiver_account').value.trim();
            const receiver_name = document.getElementById('receiver_name').value.trim();
            const amount = document.getElementById('amount').value.trim();
            const message = document.getElementById('message');

            if (!receiver_account || !receiver_name || !amount) {
                message.innerHTML = '<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">Please fill in all fields</div>';
                return;
            }

            if (parseFloat(amount) <= 0) {
                message.innerHTML = '<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">Please enter a valid amount</div>';
                return;
            }

            message.innerHTML = '<div class="bg-blue-50 text-blue-600 p-3 rounded-lg border border-blue-200 text-center">Processing payment...</div>';

            try {
                const res = await fetch('send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        receiver_account: receiver_account,
                        receiver_name: receiver_name,
                        amount: amount
                    })
                });

                const data = await res.json();

                if (data.status === 'success') {
                    message.innerHTML = `<div class="bg-green-50 text-green-600 p-3 rounded-lg border border-green-200 text-center">${data.message}</div>`;
                    
                    setTimeout(() => {
                        modal.classList.add('hidden');
                        message.innerHTML = '';
                        location.reload();
                    }, 2000);
                } else {
                    message.innerHTML = `<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">${data.message}</div>`;
                }
            } catch (err) {
                message.innerHTML = `<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">Error: ${err}</div>`;
            }
        });
    </script>

</body>
</html>