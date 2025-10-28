<?php
session_start();
require_once 'config.php';

// Agar login nahi hua
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

// Transactions
$transactions = DB::query("
    SELECT 
        t.id, 
        t.amount, 
        t.created_at, 
        s.name AS sender_name, 
        r.name AS receiver_name
    FROM transactions t
    LEFT JOIN users s ON t.sender_id = s.id
    LEFT JOIN users r ON t.receiver_id = r.id
    WHERE t.sender_id=%i OR t.receiver_id=%i
    ORDER BY t.id DESC
", $user_id, $user_id);

// All other users for dropdown
$all_users = DB::query("SELECT id, name FROM users WHERE id != %i", $user_id);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard - Wallet</title>
  <style>
    body { font-family: sans-serif; background: #f5f5f5; padding: 30px; }
    .container { background: #fff; border-radius: 10px; padding: 20px; max-width: 700px; margin: auto; box-shadow: 0 0 10px #ddd; }
    h2 { color: #333; }
    .balance { font-size: 20px; margin: 10px 0; color: green; display: flex; align-items: center; justify-content: space-between; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: center; }
    button { padding: 10px 20px; background: #28a745; border: none; color: white; cursor: pointer; border-radius: 5px; }
    button:hover { background: #218838; }

    /* Modal */
 */
    .modal-content {
        background: #fff; 
        padding: 25px; 
        border-radius: 10px; 
        width: 350px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        text-align: center;
        animation: fadeIn 0.3s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    .modal input, .modal select {
        width: 100%; 
        padding: 8px; 
        margin: 8px 0;
        border: 1px solid #ccc; 
        border-radius: 5px;
    }
    .close {
        background: red; color: white; border: none;
        padding: 5px 10px; border-radius: 5px;
        cursor: pointer; float: right;
    }
    .msg {
        margin-top: 10px;
        font-weight: bold;
    }
    #toggleBtn {
        background: #28a745;
        border: none;
        color: white;
        padding: 6px 12px;
        border-radius: 5px;
        cursor: pointer;
    }
    #toggleBtn:hover {
        background: #218838;
    }
  </style>
</head>
<body>
<div class="container">
  <h2>Welcome, <?= htmlspecialchars($user['name']) ?> 👋</h2>
  <div class="balance">
      💰 Current Balance: <b>$<?= number_format($balance, 2) ?></b>
      <button id="openModal">Send Money</button>
  </div>

  <!-- Transaction History header + See All button -->
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3>Transaction History</h3>
    <button id="toggleBtn">See All</button>
  </div>

  <table id="transactionsTable">
    <thead>
      <tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Amount</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php foreach ($transactions as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['sender_name']) ?></td>
          <td><?= htmlspecialchars($t['receiver_name']) ?></td>
          <td>$<?= number_format($t['amount'], 2) ?></td>
          <td><?= $t['created_at'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Popup Modal -->
<div class="modal" id="sendModal">
  <div class="modal-content">
    <button class="close" id="closeModal">✖</button>
    <h3>Send Payment</h3>
    <select id="receiver">
        <option value="">Select Receiver</option>
        <?php foreach($all_users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="number" id="amount" step="0.01" placeholder="Enter amount">
    <button id="sendBtn">Send</button>
    <div class="msg" id="message"></div>
  </div>
</div>

<script>
document.getElementById("openModal").onclick = () => {
    document.getElementById("sendModal").style.display = "flex";
};
document.getElementById("closeModal").onclick = () => {
    document.getElementById("sendModal").style.display = "none";
    document.getElementById("message").innerHTML = "";
};

// ✅ AJAX Send button logic
document.getElementById("sendBtn").onclick = async () => {
    const receiver = document.getElementById("receiver");
    const amount = document.getElementById("amount");
    const msg = document.getElementById("message");

    if (!receiver.value || !amount.value) {
        msg.style.color = "red";
        msg.innerHTML = "⚠️ Please fill all fields.";
        return;
    }

    msg.style.color = "black";
    msg.innerHTML = "⏳ Sending...";

    const formData = new FormData();
    formData.append("send", "1");
    formData.append("receiver_id", receiver.value);
    formData.append("amount", amount.value);

    try {
        const res = await fetch("send.php", { method: "POST", body: formData });
        const data = await res.json();

        msg.style.color = data.status === "success" ? "green" : "red";
        msg.innerHTML = data.message;

        if (data.status === "success") {
            // ✅ Fields clear
            receiver.value = "";
            amount.value = "";

            // ✅ Add transaction instantly
            const table = document.querySelector("#transactionsTable tbody");
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${data.transaction.id}</td>
                <td>${data.transaction.sender_name}</td>
                <td>${data.transaction.receiver_name}</td>
                <td>$${parseFloat(data.transaction.amount).toFixed(2)}</td>
                <td>${data.transaction.created_at}</td>
            `;
            table.prepend(tr);

            // ✅ Close modal after delay
            setTimeout(() => {
                document.getElementById("sendModal").style.display = "none";
                msg.innerHTML = "";
            }, 2000);
        }
    } catch (err) {
        msg.style.color = "red";
        msg.innerHTML = "❌ Error sending payment.";
    }
};

// ✅ Show only 5 transactions initially + toggle logic
const rows = document.querySelectorAll("#transactionsTable tbody tr");
const toggleBtn = document.getElementById("toggleBtn");

function showLimitedTransactions() {
  rows.forEach((row, i) => {
    row.style.display = i < 5 ? "" : "none";
  });
  toggleBtn.textContent = "See All";
}

function showAllTransactions() {
  rows.forEach(row => (row.style.display = ""));
  toggleBtn.textContent = "Show Less";
}

let showingAll = false;
if (rows.length > 5) {
  showLimitedTransactions();
} else {
  toggleBtn.style.display = "none"; // Hide button if 5 or fewer transactions
}

toggleBtn.addEventListener("click", () => {
  showingAll = !showingAll;
  showingAll ? showAllTransactions() : showLimitedTransactions();
});
</script>

</body>
</html>
