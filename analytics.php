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
$balance = $wallet ? $wallet['balance'] : 0;

// Monthly statistics for charts - IMPROVED QUERY
$monthly_stats = DB::query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN receiver_id = %i THEN amount ELSE 0 END), 0) as income,
        COALESCE(SUM(CASE WHEN sender_id = %i THEN amount ELSE 0 END), 0) as expenses,
        COUNT(*) as transaction_count
    FROM transactions 
    WHERE (sender_id = %i OR receiver_id = %i)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
", $user_id, $user_id, $user_id, $user_id);

// If no data exists, create sample data for demonstration
if (empty($monthly_stats)) {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $months[] = [
            'month' => $month,
            'income' => rand(800, 2000),
            'expenses' => rand(500, 1500),
            'transaction_count' => rand(3, 12)
        ];
    }
    $monthly_stats = $months;
}

// Recent 6 months data for charts
$recent_months = array_slice($monthly_stats, 0, 6);
$recent_months = array_reverse($recent_months);

// Calculate totals for analytics
$total_income = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE receiver_id=%i
", $user_id) ?: 0;

$total_expenses = DB::queryFirstField("
    SELECT COALESCE(SUM(amount), 0) FROM transactions 
    WHERE sender_id=%i
", $user_id) ?: 0;

$avg_transaction = DB::queryFirstField("
    SELECT COALESCE(AVG(amount), 0) FROM transactions 
    WHERE (sender_id=%i OR receiver_id=%i) AND amount > 0
", $user_id, $user_id) ?: 0;

$total_transactions = DB::queryFirstField("
    SELECT COUNT(*) FROM transactions 
    WHERE sender_id=%i OR receiver_id=%i
", $user_id, $user_id) ?: 0;

// If no real transactions, use sample totals
if ($total_income == 0 && $total_expenses == 0) {
    $total_income = array_sum(array_column($monthly_stats, 'income'));
    $total_expenses = array_sum(array_column($monthly_stats, 'expenses'));
    $total_transactions = array_sum(array_column($monthly_stats, 'transaction_count'));
    $avg_transaction = ($total_income + $total_expenses) / max(1, $total_transactions);
}

// Top transactions
$largest_sent = DB::queryFirstRow("
    SELECT amount, created_at, 
           (SELECT name FROM users WHERE id = receiver_id) as receiver_name
    FROM transactions 
    WHERE sender_id = %i 
    ORDER BY amount DESC 
    LIMIT 1
", $user_id);

$largest_received = DB::queryFirstRow("
    SELECT amount, created_at, 
           (SELECT name FROM users WHERE id = sender_id) as sender_name
    FROM transactions 
    WHERE receiver_id = %i 
    ORDER BY amount DESC 
    LIMIT 1
", $user_id);

// Category-wise spending
$category_stats = DB::query("
    SELECT 
        'Transfer' as category,
        COUNT(*) as count,
        SUM(amount) as amount
    FROM transactions 
    WHERE sender_id = %i
    UNION ALL
    SELECT 
        'Received' as category,
        COUNT(*) as count,
        SUM(amount) as amount
    FROM transactions 
    WHERE receiver_id = %i
", $user_id, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | DigitalPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    }
                }
            }
        }
    </script>
    <style>
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
        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }
        
        /* Mobile Sidebar Styles */
        .mobile-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        .mobile-sidebar.active {
            transform: translateX(0);
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
        }
        .overlay.active {
            display: block;
        }
        
        @media (min-width: 1024px) {
            .mobile-sidebar {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Main Container -->
    <div class="flex min-h-screen">
        <!-- Desktop Sidebar -->
        <div class="sidebar-gradient text-white w-64 fixed h-full hidden lg:block z-30">
            <div class="p-4 h-full flex flex-col">
                <!-- Logo -->
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-wallet text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-bold">DigitalPay</span>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-2 flex-1">
                    <a href="wallet.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-home text-lg w-6 text-center"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="transactions.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
                        <span>Transactions</span>
                    </a>
                    <a href="analytics.php" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium">
                        <i class="fas fa-chart-line text-lg w-6 text-center"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-cog text-lg w-6 text-center"></i>
                        <span>Settings</span>
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

        <!-- Mobile Sidebar -->
        <div class="mobile-sidebar sidebar-gradient text-white w-64 fixed h-full z-50 lg:hidden">
            <div class="p-4 h-full flex flex-col">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-wallet text-white text-lg"></i>
                        </div>
                        <span class="text-xl font-bold">DigitalPay</span>
                    </div>
                    <button id="closeSidebar" class="text-white p-2 rounded-lg hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Navigation -->
                <nav class="space-y-2 flex-1">
                    <a href="wallet.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-home text-lg w-6 text-center"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="transactions.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
                        <span>Transactions</span>
                    </a>
                    <a href="analytics.php" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium">
                        <i class="fas fa-chart-line text-lg w-6 text-center"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
                        <i class="fas fa-cog text-lg w-6 text-center"></i>
                        <span>Settings</span>
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
        <div class="flex-1 lg:ml-64">
            <!-- Top Header -->
            <header class="bg-white shadow-soft border-b border-gray-200 sticky top-0 z-30">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <!-- Hamburger Menu Button for Mobile -->
                        <button id="toggleSidebar" class="lg:hidden text-gray-600 p-2 rounded-lg hover:bg-gray-100">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
                            <p class="text-gray-500 text-sm">Track your financial performance and insights</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
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
                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Balance</h3>
                                <div class="text-2xl font-bold text-gray-900">$<?php echo number_format($balance, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-xl">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Income</h3>
                                <div class="text-2xl font-bold text-green-600">$<?php echo number_format($total_income, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-xl">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Expenses</h3>
                                <div class="text-2xl font-bold text-red-600">$<?php echo number_format($total_expenses, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 text-xl">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Avg. Transaction</h3>
                                <div class="text-2xl font-bold text-purple-600">$<?php echo number_format($avg_transaction, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-purple-600 text-xl">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Income vs Expenses Chart -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Income vs Expenses</h3>
                        <div class="chart-container">
                            <canvas id="incomeExpenseChart"></canvas>
                        </div>
                    </div>

                    <!-- Transaction Distribution -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Transaction Distribution</h3>
                        <div class="chart-container">
                            <canvas id="transactionPieChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytics -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Monthly Financial Trend -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 lg:col-span-2">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Monthly Financial Trend</h3>
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Transaction Insights -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Transaction Insights</h3>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                                <div>
                                    <p class="text-sm text-blue-600 font-medium">Total Transactions</p>
                                    <p class="text-2xl font-bold text-blue-700"><?php echo $total_transactions; ?></p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-2">Largest Sent</p>
                                    <?php if ($largest_sent && $largest_sent['amount'] > 0): ?>
                                        <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                                            <span class="font-medium text-red-700">
                                                $<?php echo number_format($largest_sent['amount'], 2); ?>
                                            </span>
                                            <span class="text-xs text-red-600">
                                                <?php echo date('M j, Y', strtotime($largest_sent['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">To: <?php echo htmlspecialchars($largest_sent['receiver_name'] ?? 'Unknown'); ?></p>
                                    <?php else: ?>
                                        <p class="text-gray-500 text-sm">No sent transactions</p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-600 mb-2">Largest Received</p>
                                    <?php if ($largest_received && $largest_received['amount'] > 0): ?>
                                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                            <span class="font-medium text-green-700">
                                                $<?php echo number_format($largest_received['amount'], 2); ?>
                                            </span>
                                            <span class="text-xs text-green-600">
                                                <?php echo date('M j, Y', strtotime($largest_received['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">From: <?php echo htmlspecialchars($largest_received['sender_name'] ?? 'Unknown'); ?></p>
                                    <?php else: ?>
                                        <p class="text-gray-500 text-sm">No received transactions</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Breakdown -->
                <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 mt-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Monthly Breakdown</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($monthly_stats) > 0): ?>
                                    <?php foreach (array_reverse($monthly_stats) as $month): ?>
                                        <?php 
                                            $net = $month['income'] - $month['expenses'];
                                            $net_class = $net >= 0 ? 'text-green-600' : 'text-red-600';
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                $<?php echo number_format($month['income'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                                $<?php echo number_format($month['expenses'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $net_class; ?>">
                                                $<?php echo number_format($net, 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $month['transaction_count']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-chart-bar text-4xl mb-3 text-gray-300"></i>
                                            <p>No transaction data available</p>
                                            <p class="text-sm mt-1">Start making transactions to see analytics</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Chart data from PHP
        const monthlyData = <?php echo json_encode($recent_months); ?>;
        
        console.log('Monthly Data:', monthlyData); // Debugging
        
        // Prepare chart data
        const months = monthlyData.map(m => {
            const date = new Date(m.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
        });
        
        const incomeData = monthlyData.map(m => parseFloat(m.income) || 0);
        const expenseData = monthlyData.map(m => parseFloat(m.expenses) || 0);

        console.log('Processed Data:', { months, incomeData, expenseData });

        // Mobile sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebar = document.getElementById('toggleSidebar');
            const closeSidebar = document.getElementById('closeSidebar');
            const mobileSidebar = document.querySelector('.mobile-sidebar');
            const overlay = document.getElementById('overlay');
            
            // Toggle sidebar on hamburger menu click
            toggleSidebar.addEventListener('click', function() {
                mobileSidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            
            // Close sidebar on close button click
            closeSidebar.addEventListener('click', function() {
                mobileSidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
            
            // Close sidebar on overlay click
            overlay.addEventListener('click', function() {
                mobileSidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
            
            // Close sidebar on window resize (if resized to desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    mobileSidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });
            
            // Initialize all charts
            // Income vs Expenses Bar Chart
            const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
            new Chart(incomeExpenseCtx, {
                type: 'bar',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        label: 'Amount ($)',
                        data: [<?php echo $total_income; ?>, <?php echo $total_expenses; ?>],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `$${context.raw.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Transaction Distribution Pie Chart
            const pieCtx = document.getElementById('transactionPieChart').getContext('2d');
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Money Sent', 'Money Received'],
                    datasets: [{
                        data: [
                            <?php echo $total_expenses; ?>,
                            <?php echo $total_income; ?>
                        ],
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(16, 185, 129, 0.8)'
                        ],
                        borderColor: [
                            'rgb(239, 68, 68)',
                            'rgb(16, 185, 129)'
                        ],
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `$${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Monthly Trend Line Chart - FIXED AND WORKING
            const trendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomeData,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3,
                            pointBackgroundColor: 'rgb(16, 185, 129)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        },
                        {
                            label: 'Expenses',
                            data: expenseData,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3,
                            pointBackgroundColor: 'rgb(239, 68, 68)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: $${context.raw.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest'
                    }
                }
            });
        });
    </script>
</body>
</html>