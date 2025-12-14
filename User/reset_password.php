<?php
require_once __DIR__ . '/../admin/config.php';

$error = "";
$success = "";

$token = $_GET["token"] ?? "";
if ($token === "") {
    die("Invalid reset link.");
}

// Get latest valid reset tokens
$stmt = $conn->prepare("
    SELECT id, user_id, token_hash, expires_at
    FROM password_resets
    WHERE used = 0 AND expires_at > NOW()
    ORDER BY created_at DESC
");
$stmt->execute();
$res = $stmt->get_result();

$reset_row = null;
while ($row = $res->fetch_assoc()) {
    if (password_verify($token, $row["token_hash"])) {
        $reset_row = $row;
        break;
    }
}
$stmt->close();

if (!$reset_row) {
    die("Reset link is invalid or already used.");
}

$reset_id = (int)$reset_row["id"];
$user_id  = (int)$reset_row["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_pass = $_POST["new_password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    if ($new_pass === "" || $confirm === "") {
        $error = "Please fill both fields.";
    } elseif ($new_pass !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update password
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute();
        $stmt->close();

        // Mark this reset token as used
        $stmt = $conn->prepare("UPDATE password_resets SET used=1 WHERE id=?");
        $stmt->bind_param("i", $reset_id);
        $stmt->execute();
        $stmt->close();

        $success = "Password updated successfully. You can login now.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="/Ecommerce/User/css/login.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card">
    <h2>Reset Password</h2>

    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success"><?= htmlspecialchars($success) ?></div>
      <a href="login.php" class="btn primary" style="margin-top:10px;display:inline-block;">
        Go to Login
      </a>
    <?php else: ?>
      <form method="post">
        <div class="input-group">
          <label>New Password</label>
          <input type="password" name="new_password" required>
        </div>

        <div class="input-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required>
        </div>

        <div class="actions">
          <button type="submit" class="btn primary">Update password</button>
        </div>
      </form>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
