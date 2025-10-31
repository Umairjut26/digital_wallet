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

// All transactions for the user
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
    <title>All Transactions | DigitalPay</title>
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
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Main Container -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="sidebar-gradient text-white w-64 fixed h-full">
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
                    <a href="transactions.php" class="flex items-center space-x-3 bg-primary bg-opacity-20 px-4 py-3 rounded-xl font-medium">
                        <i class="fas fa-exchange-alt text-lg w-6 text-center"></i>
                        <span>Transactions</span>
                    </a>
                    <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl font-medium hover:bg-white hover:bg-opacity-10 transition-all duration-300">
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
                            <p class="text-xs text-gray-300 truncate" title="<?php echo htmlspecialchars($user['email'] ?? 'user@example.com'); ?>">
                                <?php 
                                $email = $user['email'] ?? 'user@example.com';
                                if (strlen($email) > 20) {
                                    echo htmlspecialchars(substr($email, 0, 17)) . '...';
                                } else {
                                    echo htmlspecialchars($email);
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Top Header -->
            <header class="bg-white shadow-soft border-b border-gray-200 sticky top-0 z-30">
                <div class="flex justify-between items-center px-6 py-4">
                    <div class="flex items-center space-x-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">All Transactions</h1>
                            <p class="text-gray-500 text-sm">Complete transaction history</p>
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
                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Received</h3>
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
                                <h3 class="text-gray-500 text-sm font-medium mb-1">Total Sent</h3>
                                <div class="text-2xl font-bold text-red-600">$<?php echo number_format($total_expenses, 2); ?></div>
                            </div>
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 text-xl">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="bg-white rounded-2xl shadow-card border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Transaction History</h2>
                                <p class="text-gray-500 text-sm mt-1">All your financial activities</p>
                            </div>
                            <div class="flex items-center space-x-2 mt-3 sm:mt-0">
                                <span class="text-sm text-gray-600">
                                    Total: <?php echo count($all_transactions); ?> transactions
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($all_transactions) > 0): ?>
                                    <?php foreach ($all_transactions as $t): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center <?php echo $t['type'] == 'sent' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?> mr-3">
                                                        <i class="fas fa-<?php echo $t['type'] == 'sent' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?php 
                                                                if ($t['type'] == 'sent') {
                                                                    echo 'To: ' . htmlspecialchars($t['receiver_name']);
                                                                } else {
                                                                    echo 'From: ' . htmlspecialchars($t['sender_name']);
                                                                }
                                                            ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 capitalize"><?php echo $t['type']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-bold text-lg <?php echo $t['type'] == 'sent' ? 'text-red-600' : 'text-green-600'; ?>">
                                                    <?php echo $t['type'] == 'sent' ? '-' : '+'; ?>$<?php echo number_format($t['amount'], 2); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y g:i A', strtotime($t['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 text-xs rounded-full <?php echo $t['type'] == 'sent' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo ucfirst($t['type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center">
                                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i class="fas fa-exchange-alt text-gray-400 text-xl"></i>
                                            </div>
                                            <p class="text-gray-500 text-lg font-medium">No transactions yet</p>
                                            <p class="text-gray-400 mt-1">Send or receive money to get started!</p>
                                            <button onclick="window.location.href='wallet.php'" 
                                                    class="mt-4 bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary-dark transition-all duration-300">
                                                Go to Dashboard
                                            </button>
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
</body>
</html>