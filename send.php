<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json'); // JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $amount = floatval($_POST['amount']);

    // Sender aur Receiver ek hi user nahi hone chahiye
    if ($sender_id == $receiver_id) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot send money to yourself!']);
        exit;
    }

    // Sender aur Receiver dono ka data lo
    $sender = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $sender_id);
    $receiver = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $receiver_id);

    if (!$receiver) {
        echo json_encode(['status' => 'error', 'message' => 'Receiver not found!']);
        exit;
    }

    // Sender wallet check karo
    $sender_wallet = DB::queryFirstRow("SELECT * FROM wallets WHERE user_id=%i", $sender_id);
    if (!$sender_wallet) {
        echo json_encode(['status' => 'error', 'message' => 'Sender wallet not found!']);
        exit;
    }

    // Balance check
    if ($sender_wallet['balance'] < $amount) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient balance!']);
        exit;
    }

    // Transaction start
    DB::startTransaction();

    try {
        // Sender se balance cut
        DB::query("UPDATE wallets SET balance = balance - %d WHERE user_id=%i", $amount, $sender_id);

        // Receiver ko balance add
        DB::query("UPDATE wallets SET balance = balance + %d WHERE user_id=%i", $amount, $receiver_id);

        // Transaction record karo with names
        DB::insert('transactions', [
            'sender_id' => $sender_id,
            'sender_name' => $sender['name'],
            'receiver_id' => $receiver_id,
            'receiver_name' => $receiver['name'],
            'amount' => $amount,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $transaction_id = DB::insertId();

        DB::commit();

        // Sender ka new balance lao
        $new_balance = DB::queryFirstField("SELECT balance FROM wallets WHERE user_id=%i", $sender_id);

        echo json_encode([
            'status' => 'success',
            'message' => "✅ Payment of $$amount sent successfully from {$sender['name']} to {$receiver['name']}!",
            'new_balance' => $new_balance,
            'transaction' => [
                'id' => $transaction_id,
                'sender_name' => $sender['name'],
                'receiver_name' => $receiver['name'],
                'amount' => $amount,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        DB::rollback();
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
}
?>
