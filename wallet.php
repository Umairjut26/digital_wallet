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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigitalPay - Wallet Dashboard</title>
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
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header/Navigation -->
    <nav class="bg-white shadow-soft border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-primary to-primary-light rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-wallet text-white text-lg"></i>
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-primary to-primary-light bg-clip-text text-transparent">DigitalPay</span>
                </div>
                
                <!-- User Info & Actions -->
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center space-x-3 bg-gray-50 rounded-lg px-4 py-2">
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-semibold text-sm">
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
                        <div>
                            <p class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email'] ?? 'user@example.com'); ?></p>
                        </div>
                    </div>
                    <button onclick="window.location.href='logout.php'" 
                            class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center space-x-2 shadow-soft">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome & Balance Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>! 👋</h1>
            <p class="text-gray-600">Here's your financial summary for today</p>
        </div>

        <!-- Balance Card -->
        <div class="bg-gradient-to-r from-primary to-primary-light text-white rounded-2xl p-6 mb-8 shadow-card relative overflow-hidden animate-fade-in">
            <!-- Background Pattern -->
            <div class="absolute top-0 right-0 -mt-20 -mr-20 w-60 h-60 rounded-full bg-white bg-opacity-10"></div>
            <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-40 h-40 rounded-full bg-white bg-opacity-10"></div>
            
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                    <div class="mb-6 md:mb-0">
                        <h2 class="text-lg font-medium mb-2 opacity-90">Available Balance</h2>
                        <div class="text-4xl md:text-5xl font-bold mb-1">$<?php echo number_format($balance, 2); ?></div>
                        <div class="flex items-center space-x-1 text-sm opacity-90">
                            <i class="fas fa-arrow-up text-green-300"></i>
                            <span>12.5% increase from last month</span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3">
                        <button id="openSendModal" 
                                class="glass-effect border border-white border-opacity-30 text-white px-5 py-3 rounded-xl font-semibold hover:bg-white hover:text-primary transition-all duration-300 flex items-center space-x-2 shadow-soft">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Money</span>
                        </button>
                        <button class="glass-effect border border-white border-opacity-30 text-white px-5 py-3 rounded-xl font-semibold hover:bg-white hover:text-primary transition-all duration-300 flex items-center space-x-2 shadow-soft">
                            <i class="fas fa-plus"></i>
                            <span>Add Funds</span>
                        </button>
                        <button class="glass-effect border border-white border-opacity-30 text-white px-5 py-3 rounded-xl font-semibold hover:bg-white hover:text-primary transition-all duration-300 flex items-center space-x-2 shadow-soft">
                            <i class="fas fa-download"></i>
                            <span>Withdraw</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Income Card -->
            <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 hover:shadow-lg transition-all duration-300 animate-slide-up">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Total Income</h3>
                        <div class="text-2xl font-bold text-gray-900">$1,245.80</div>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-xl shadow-soft">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span>12.5% from last month</span>
                </div>
            </div>
            
            <!-- Expense Card -->
            <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 hover:shadow-lg transition-all duration-300 animate-slide-up" style="animation-delay: 0.1s">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Total Expense</h3>
                        <div class="text-2xl font-bold text-gray-900">$684.30</div>
                    </div>
                    <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 text-xl shadow-soft">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-red-600">
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span>8.2% from last month</span>
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
                    <i class="fas fa-arrow-up mr-1"></i>
                    <span>24.7% from last month</span>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-2xl p-6 shadow-card border border-gray-100 mb-8 animate-fade-in">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-1">Recent Transactions</h2>
                    <p class="text-gray-500 text-sm">Your latest financial activities</p>
                </div>
                <button id="toggleBtn" 
                        class="bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center space-x-2 shadow-soft mt-4 sm:mt-0">
                    <i class="fas fa-list"></i>
                    <span>View All</span>
                </button>
            </div>
            
            <div class="space-y-4" id="transactionsList">
                <?php if (count($recent_transactions) > 0): ?>
                    <?php foreach ($recent_transactions as $t): ?>
                        <div class="flex items-center p-4 rounded-xl border border-gray-100 hover:bg-gray-50 transition-all duration-300 hover:shadow-soft group">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mr-4 shadow-soft <?php echo $t['type'] == 'sent' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                                <i class="fas fa-<?php echo $t['type'] == 'sent' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900">
                                    <?php 
                                        if ($t['type'] == 'sent') {
                                            echo 'To: ' . htmlspecialchars($t['receiver_name']);
                                        } else {
                                            echo 'From: ' . htmlspecialchars($t['sender_name']);
                                        }
                                    ?>
                                </h4>
                                <p class="text-sm text-gray-500"><?php echo $t['created_at']; ?></p>
                            </div>
                            <div class="font-bold text-lg <?php echo $t['type'] == 'sent' ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo $t['type'] == 'sent' ? '-' : '+'; ?>$<?php echo number_format($t['amount'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-soft">
                            <i class="fas fa-exchange-alt text-gray-400 text-xl"></i>
                        </div>
                        <p class="text-gray-500">No transactions yet. Send or receive money to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

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
                    <label for="receiver" class="block text-sm font-medium text-gray-700 mb-2">Select Receiver</label>
                    <select id="receiver" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all duration-300 shadow-soft">
                        <option value="">Choose a recipient</option>
                        <?php foreach($all_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
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
        // Modal functionality
        const openModalBtn = document.getElementById('openSendModal');
        const closeModalBtn = document.getElementById('closeModal');
        const modal = document.getElementById('sendModal');
        
        openModalBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
        });
        
        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            document.getElementById('message').innerHTML = '';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
                document.getElementById('message').innerHTML = '';
            }
        });
        
        // Send payment functionality
        document.getElementById('sendBtn').addEventListener('click', async () => {
            const receiver = document.getElementById('receiver');
            const amount = document.getElementById('amount');
            const message = document.getElementById('message');
            
            if (!receiver.value || !amount.value) {
                message.innerHTML = '<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">Please fill in all fields</div>';
                return;
            }
            
            if (parseFloat(amount.value) <= 0) {
                message.innerHTML = '<div class="bg-red-50 text-red-600 p-3 rounded-lg border border-red-200 text-center">Please enter a valid amount</div>';
                return;
            }
            
            // Simulate API call
            message.innerHTML = '<div class="bg-blue-50 text-blue-600 p-3 rounded-lg border border-blue-200 text-center">Processing payment...</div>';
            
            // In a real application, you would make an AJAX call to send.php
            // For demo purposes, we'll simulate a successful payment
            setTimeout(() => {
                message.innerHTML = '<div class="bg-green-50 text-green-600 p-3 rounded-lg border border-green-200 text-center">Payment sent successfully!</div>';
                
                // Reset form
                receiver.value = '';
                amount.value = '';
                
                // Close modal after 2 seconds
                setTimeout(() => {
                    modal.classList.add('hidden');
                    message.innerHTML = '';
                    
                    // In a real app, you would refresh the transaction list
                    alert('Payment successful! The page will refresh to show the updated transaction.');
                    location.reload();
                }, 2000);
            }, 1500);
        });
        
        // Transaction toggle functionality
        const toggleBtn = document.getElementById('toggleBtn');
        
        // In a real application, you would fetch all transactions via AJAX
        // For this demo, we'll just redirect to a transactions page
        toggleBtn.addEventListener('click', () => {
            window.location.href = 'transactions.php';
        });
    </script>
</body>
</html>