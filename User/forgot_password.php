<?php
require_once __DIR__ . '/../admin/config.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $error = "Please enter your email.";
    } else {
        // Find user by email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        // Always show generic message
        if (!$user) {
            $success = "If that email exists, we sent a reset link.";
        } else {
            $user_id = (int)$user["id"];

            // Create secure token
            $token = bin2hex(random_bytes(32));
            $token_hash = password_hash($token, PASSWORD_DEFAULT);
            $expires_at = date("Y-m-d H:i:s", time() + 60*30); // 30 minutes

            // Store reset request
            $stmt = $conn->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $user_id, $token_hash, $expires_at);
            $stmt->execute();
            $stmt->close();

            // ✅ IMPORTANT: file name must match your real file
            $reset_link = "http://localhost:8080/Ecommerce/User/reset_password.php?token=" . urlencode($token);

            $success = "Reset link sent! (Local test link below)";
            $success .= "<br><a href='$reset_link'>$reset_link</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="/Ecommerce/User/css/login.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card">
    <h2>Forgot your password?</h2>
    <p class="subtitle">Enter your email to reset password</p>

    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" required>
      </div>

      <div class="actions">
        <button type="submit" class="btn primary">Send reset link</button>
        <a href="login.php" class="btn secondary">Back</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
