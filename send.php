<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_SESSION['user_id'] ?? null;
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $receiver_account = strtoupper(str_replace(' ', '', $_POST['receiver_account'] ?? ''));
    $amount = floatval($_POST['amount'] ?? 0);

    // Validation
    if (!$sender_id || $receiver_name === '' || $receiver_account === '' || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all fields correctly.']);
        exit;
    }

    // Fetch sender wallet
    $sender_wallet = DB::queryFirstRow("SELECT * FROM wallets WHERE user_id=%s", $sender_id);
    if (!$sender_wallet) {
        echo json_encode(['status' => 'error', 'message' => 'Sender wallet not found!']);
        exit;
    }

    // Fetch receiver wallet
    $receiver_wallet = DB::queryFirstRow("
        SELECT w.*, u.name AS receiver_name 
        FROM wallets w 
        JOIN users u ON w.user_id = u.id 
        WHERE w.account_number=%s AND u.name=%s
    ", $receiver_account, $receiver_name);

    if (!$receiver_wallet) {
        echo json_encode(['status' => 'error', 'message' => 'Receiver account not found or name mismatch!']);
        exit;
    }

    // Prevent self-transfer
    if ($receiver_wallet['user_id'] == $sender_id) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot send money to yourself!']);
        exit;
    }

    // Check balance
    if ($sender_wallet['balance'] < $amount) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient balance!']);
        exit;
    }

    // Transaction start
    DB::startTransaction();
    try {
        // Deduct from sender
        DB::query("UPDATE wallets SET balance = balance - %s WHERE user_id = %s", $amount, $sender_id);

        // Add to receiver
        DB::query("UPDATE wallets SET balance = balance + %s WHERE user_id = %s", $amount, $receiver_wallet['user_id']);

        // Record transaction
        DB::insert('transactions', [
            'sender_id'        => $sender_id,
            'sender_name'      => $sender_wallet['account_name'] ?? $sender_wallet['sender_name'] ?? 'Unknown',
            'receiver_id'      => $receiver_wallet['user_id'],
            'receiver_name'    => $receiver_wallet['receiver_name'],
            'receiver_account' => $receiver_wallet['account_number'],
            'amount'           => $amount,
            'currency'         => 'USD',
            'created_at'       => date('Y-m-d H:i:s')
        ]);

        DB::commit();

        $new_balance = DB::queryFirstField("SELECT balance FROM wallets WHERE user_id=%s", $sender_id);

        echo json_encode([
            'status'      => 'success',
            'message'     => "✅ Payment of USD $amount sent successfully to {$receiver_wallet['receiver_name']}!",
            'new_balance' => $new_balance,
            'currency'    => 'USD'
        ]);
    } catch (Exception $e) {
        DB::rollback();
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
}
?>