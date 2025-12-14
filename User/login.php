<?php
// Use central config: starts session + connects to DB
require_once __DIR__ . '/../admin/config.php';

// Initialize error variable so it's always defined
$error = "";

/**
 * Handle POST
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter username and password.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, email, role, password_hash
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Database error: " . $conn->error;

        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $u_username, $email, $role, $hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {

                    session_regenerate_id(true);

                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"]       = (int)$id;
                    $_SESSION["user_id"]  = (int)$id;
                    $_SESSION["username"] = $u_username;
                    $_SESSION["email"]    = $email;
                    $_SESSION["role"]     = $role;

                    // ✅ Redirect after login (ADMIN vs USER)
                    if ($role === 'admin') {
                        header("Location: /Ecommerce/admin/admin_home.php");
                        exit;
                    } else {
                        header("Location: /Ecommerce/User/home.php");
                        exit;
                    }

                } else {
                    $error = "Invalid password.";
                }

            } else {
                $error = "No account found with that username.";
            }

            $stmt->close();
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login</title>

  <link rel="stylesheet" href="/Ecommerce/User/css/login.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">

        <h2>Welcome Back</h2>
        <p class="subtitle">Login to your account</p>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                    <button type="button" id="togglePassword" class="toggle-password">👁</button>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn primary">Login</button>
                <a href="/Ecommerce/User/index.php" class="btn secondary">Cancel</a>
            </div>
        </form>

        <p style="margin-top:10px;">
            <a href="forgot_password.php" class="link">Forgot password?</a>
        </p>

        <p class="footnote">
            Don't have an account?
            <a href="register.php" class="link">Signup</a>
        </p>

    </div>
</div>

<script>
    // Show / Hide password
    const password = document.getElementById("password");
    const toggle = document.getElementById("togglePassword");

    toggle.addEventListener("click", () => {
        const type = password.type === "password" ? "text" : "password";
        password.type = type;
        toggle.textContent = type === "text" ? "🙈" : "👁";
    });
</script>

</body>
</html>
