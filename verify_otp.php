<?php
session_start();
require 'config.php';

if (!isset($_SESSION['pending_user'])) {
    die("No user pending verification.");
}

$user_id = $_SESSION['pending_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);

    $query = "SELECT * FROM otp_codes 
              WHERE user_id = $user_id AND is_used = 0 
              ORDER BY created_at DESC LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        $expires_at = strtotime($row['expires_at']);
        if (time() > $expires_at) {
            echo "<p style='color:red'>OTP expired. Please register again.</p>";
        } elseif ($row['otp_code'] == $otp) {
            mysqli_query($conn, "UPDATE users SET is_verified = 1 WHERE id = $user_id");
            mysqli_query($conn, "UPDATE otp_codes SET is_used = 1 WHERE id = {$row['id']}");
            unset($_SESSION['pending_user']);
            echo "<p style='color:green'>Verification successful! You can now <a href='login.php'>login</a>.</p>";
        } else {
            echo "<p style='color:red'>Invalid OTP. Try again.</p>";
        }
    } else {
        echo "<p style='color:red'>No OTP found. Please register again.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Verify OTP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
  <div class="container mt-5" style="max-width:400px;">
    <div class="card p-4 shadow">
      <h4 class="text-center mb-3">Enter OTP</h4>
      <form method="POST">
        <input type="text" name="otp" class="form-control mb-3" placeholder="Enter 6-digit code" required>
        <button type="submit" class="btn btn-primary w-100">Verify</button>
      </form>
    </div>
  </div>
</body>
</html>
