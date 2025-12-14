<?php
session_start();
include('../Database/db_connect.php'); // include your DB connection  

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email    = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role     = 'buyer'; // or $_POST['role'] if you have a field for it  

    $stmt = $conn->prepare("INSERT INTO users (username,email,password_hash,role) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $username, $email, $password, $role);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! You can now log in.";
        header("Location: login.php");
        exit;
    } else {
        $error = "Username already exists or database error.";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <!-- External CSS -->
    <link rel="stylesheet" href="/Ecommerce/User/css/register.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h2>Create an account</h2>
            <p class="subtitle">Join our shop as a buyer</p>

            <?php if (isset($error)) : ?>
                <div class="alert error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        required
                    >
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            required
                        >
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Show/Hide password">
                            👁
                        </button>
                    </div>
                    <small class="hint">Click the eye to show or hide your password.</small>
                </div>

                <div class="actions">
                    <button type="submit" class="btn primary">Register</button>
                    <a href="/Ecommerce/User/index.php" class="btn secondary">Cancel</a>
                </div>
            </form>

            <p class="footnote">
                Already have an account?
                <a href="login.php" class="link">Log in</a>
            </p>
        </div>
    </div>

    <script>
        // Password show/hide
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePassword');

        toggleBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // change eye icon
            if (type === 'text') {
                this.textContent = '🙈'; // eye closed
            } else {
                this.textContent = '👁'; // eye open
            }
        });
    </script>
</body>
</html>
