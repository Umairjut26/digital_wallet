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

// Monthly stats for insights
$current_month_income = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE receiver_id=%i AND status='completed' 
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
", $user_id) ?: 0;

$current_month_expenses = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE sender_id=%i AND status='completed'
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
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
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        'bounce-soft': 'bounceSoft 2s infinite',
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
                        },
                        bounceSoft: {
                            '0%, 100%': { 
                                transform: 'translateY(0)',
                                animationTimingFunction: 'cubic-bezier(0.8, 0, 1, 1)'
                            },
                            '50%': {
                                transform: 'translateY(-5px)',
                                animationTimingFunction: 'cubic-bezier(0, 0, 0.2, 1)'
                            }
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
        .transition-width {
            transition: width 0.3s ease-in-out;
        }
        .transition-margin {
            transition: margin-left 0.3s ease-in-out;
        }
        .gradient-text {
            background: linear-gradient(135deg, #0052FF 0%, #4D7CFF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Mobile sidebar styles */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
                width: 280px !important;
            }
            #sidebar.mobile-open {
                transform: translateX(0);
            }
            #mainContent {
                margin-left: 0 !important;
            }
            #mobileOverlay {
                display: none;
            }
            #mobileOverlay.mobile-open {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 39;
            }
        }
        
        /* Desktop sidebar hide/show */
        .sidebar-hidden {
            transform: translateX(-100%) !important;
        }
        .main-content-full {
            margin-left: 0 !important;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="md:hidden"></div>

    <!-- Main Container -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-gradient text-white fixed h-full z-40 w-64 transition-width md:transform-none">
            <div class="p-4 h-full flex flex-col">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-wallet text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-bold whitespace-nowrap">DigitalPay</span>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-2 flex-1">
                    <a href="dashboard.php" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium group">
                        <i class="fas fa-home text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity">Dashboard</span>
                    </a>
                    <a href="transactions.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
                        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity">Transactions</span>
                    </a>
                    <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
                        <i class="fas fa-chart-line text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity">Analytics</span>
                    </a>
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300 group">
                        <i class="fas fa-cog text-lg w-6 text-center"></i>
                        <span class="whitespace-nowrap group-hover:opacity-100 transition-opacity">Settings</span>
                    </a>
                </nav>
                
                <!-- User Profile -->
                <div class="border-t border-gray-600 pt-4">
                    <div class="flex items-center space-x-3">
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
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div id="mainContent" class="flex-1 ml-64 transition-margin">
            <!-- Mobile View: Only Balance and Quick Actions -->
            <div class="block md:hidden space-y-6">
                <!-- Balance Card for Mobile - Rounded only at bottom -->
                <div class="gradient-bg text-white rounded-t-none rounded-b-2xl p-6 shadow-card relative overflow-hidden animate-fade-in w-full">
                    <div class="absolute top-0 right-0 -mt-10 -mr-10 w-20 h-20 rounded-full bg-white bg-opacity-10"></div>
                    <div class="relative z-10">
                        <!-- JazzCash text above welcome -->
                        <div class="mb-1">
                            <h3 class="font-bold text-lg opacity-90">DigitalPay</h3>
                        </div>
                        
                        <!-- User Name above balance -->
                        <div class="mb-4">
                            <h3 class="font-medium opacity-90">Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!</h3>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <!-- Left side - Balance info -->
                            <div class="flex-1">
                                <div class="text-2xl font-bold mb-2">$<?php echo number_format($balance, 2); ?></div>
                                <p class="text-sm opacity-80">Available to spend</p>
                            </div>
                            
                            <!-- Right side - Plus icon with Money text (centered and aligned to right) -->
                            <div class="flex flex-col items-center justify-center space-y-1 bg-white/10 p-3 rounded-l-xl w-30">
                                <div class="w-7 h-7 bg-white rounded-full flex items-center justify-center mb-1">
                                    <i class="fas fa-plus text-blue-600 text-xs"></i>
                                </div>
                                <span class="text-xs text-white opacity-90">Money</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions for Mobile - 9 Actions with Text Below Icons -->
                <div class="grid grid-cols-3 gap-4 mr-5 ml-5">
                    <!-- Money Transfer -->
                    <button id="openSendModalMobile" 
                            class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-exchange-alt text-xl text-primary"></i>
                        <span class="text-xs text-gray-700">Money Transfer</span>
                    </button>
                    
                    <!-- Bill and Payment -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-file-invoice-dollar text-xl text-green-500"></i>
                        <span class="text-xs text-gray-700">Bill & Payment</span>
                    </button>
                    
                    <!-- Load & Packages -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-mobile-alt text-xl text-purple-500"></i>
                        <span class="text-xs text-gray-700">Load & Packages</span>
                    </button>
                    
                    <!-- Banking & Finance -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-university text-xl text-blue-500"></i>
                        <span class="text-xs text-gray-700">Banking & Finance</span>
                    </button>
                    
                    <!-- Marketplace -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-store text-xl text-orange-500"></i>
                        <span class="text-xs text-gray-700">Marketplace</span>
                    </button>
                    
                    <!-- Govt Payments -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-landmark text-xl text-red-500"></i>
                        <span class="text-xs text-gray-700">Govt Payments</span>
                    </button>
                    
                    <!-- Travel -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-plane text-xl text-teal-500"></i>
                        <span class="text-xs text-gray-700">Travel</span>
                    </button>
                    
                    <!-- Other Payment & Services -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-cogs text-xl text-indigo-500"></i>
                        <span class="text-xs text-gray-700">Other Services</span>
                    </button>
                    
                    <!-- More -->
                    <button class="bg-white p-4 rounded-xl font-semibold transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md border border-gray-100">
                        <i class="fas fa-ellipsis-h text-xl text-gray-500"></i>
                        <span class="text-xs text-gray-700">More</span>
                    </button>
                </div>

                <!-- Promotional Banner -->
                <div class="bg-gradient-to-r from-purple-600 to-blue-600 rounded-2xl p-5 text-white shadow-card relative overflow-hidden mx-4">
                    <div class="absolute top-0 right-0 -mt-8 -mr-8 w-24 h-24 rounded-full bg-white bg-opacity-10"></div>
                    <div class="relative z-10">
                        <h3 class="font-bold text-lg mb-1">Special Offer!</h3>
                        <p class="text-sm opacity-90 mb-3">Get 5% cashback on your next bill payment</p>
                        <button class="bg-white text-purple-600 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-opacity-90 transition-all duration-300">
                            Claim Now
                        </button>
                    </div>
                </div>

                <!-- Bottom Navigation Menu - Home, Agent, Location, QR Code, Favourites, Promotions -->
                <div class="bg-white rounded-2xl p-3 shadow-card border border-gray-100">
                    <div class="flex justify-between items-center">
                        <!-- Home -->
                        <div class="flex flex-col items-center space-y-1">
                            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600">
                                <i class="fas fa-home text-sm"></i>
                            </div>
                            <span class="text-xs text-gray-700 font-medium">Home</span>
                        </div>
                        
                        <!-- Agent with Location Icon -->
                        <div class="flex flex-col items-center space-y-1">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center text-green-600 relative">
                                <i class="fas fa-map-marker-alt text-sm"></i>
                            </div>
                            <span class="text-xs text-gray-700 font-medium">Agent</span>
                        </div>
                        
                        <!-- QR Code -->
                        <div class="flex flex-col items-center space-y-1">
                            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600">
                                <i class="fas fa-qrcode text-sm"></i>
                            </div>
                            <span class="text-xs text-gray-700 font-medium">QR Code</span>
                        </div>
                        
                        <!-- Favourites -->
                        <div class="flex flex-col items-center space-y-1">
                            <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center text-yellow-600">
                                <i class="fas fa-star text-sm"></i>
                            </div>
                            <span class="text-xs text-gray-700 font-medium">Favourites</span>
                        </div>
                        
                        <!-- Promotions -->
                        <div class="flex flex-col items-center space-y-1">
                            <div class="w-10 h-10 bg-pink-100 rounded-xl flex items-center justify-center text-pink-600">
                                <i class="fas fa-tag text-sm"></i>
                            </div>
                            <span class="text-xs text-gray-700 font-medium">Promotions</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop View: Full Dashboard -->
            <div class="hidden md:block">
                <!-- Top Header for Desktop -->
                <header class="bg-white shadow-soft border-b border-gray-200 sticky top-0 z-30 mb-6">
                    <div class="flex justify-between items-center px-6 py-4">
                        <div class="flex items-center space-x-4">
                            <!-- Desktop sidebar toggle -->
                            <button id="desktopSidebarToggle" class="text-gray-600 hover:text-primary transition-colors duration-300">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Financial Dashboard</h1>
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

                <!-- Quick Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 mr-5 ml-5">
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

                <!-- Quick Actions Only -->
                <div class="flex justify-center">
                    <div class="w-full max-w-2xl">
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <button id="openSendModal" 
                                        class="bg-primary text-white p-4 rounded-xl font-semibold hover:bg-primary-dark transition-all duration-300 flex flex-col items-center justify-center space-y-2 shadow-soft hover:shadow-md animate-pulse-soft">
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
                    </div>
                </div>
            </div>
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
        // Desktop sidebar toggle functionality
        const desktopSidebarToggle = document.getElementById('desktopSidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        let isDesktopSidebarHidden = false;
        
        if (desktopSidebarToggle) {
            desktopSidebarToggle.addEventListener('click', () => {
                isDesktopSidebarHidden = !isDesktopSidebarHidden;
                
                if (isDesktopSidebarHidden) {
                    // Hide sidebar completely
                    sidebar.classList.add('sidebar-hidden');
                    mainContent.classList.add('main-content-full');
                } else {
                    // Show sidebar
                    sidebar.classList.remove('sidebar-hidden');
                    mainContent.classList.remove('main-content-full');
                }
            });
        }

        // Mobile sidebar toggle functionality
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', () => {
                sidebar.classList.add('mobile-open');
                mobileOverlay.classList.add('mobile-open');
                document.body.style.overflow = 'hidden'; // Prevent scrolling when sidebar is open
            });
        }
        
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeMobileSidebarFunc);
        }
        
        function closeMobileSidebarFunc() {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('mobile-open');
            document.body.style.overflow = ''; // Restore scrolling
        }

        // Modal functionality for desktop
        const openModalBtn = document.getElementById('openSendModal');
        const closeModalBtn = document.getElementById('closeModal');
        const modal = document.getElementById('sendModal');
        
        if (openModalBtn) {
            openModalBtn.addEventListener('click', () => modal.classList.remove('hidden'));
        }
        
        // Modal functionality for mobile
        const openModalBtnMobile = document.getElementById('openSendModalMobile');
        if (openModalBtnMobile) {
            openModalBtnMobile.addEventListener('click', () => modal.classList.remove('hidden'));
        }
        
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