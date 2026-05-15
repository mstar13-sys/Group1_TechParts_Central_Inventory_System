<?php
session_start();
require_once 'includes/config.php';

// If already logged in, go straight to dashboard
if (!empty($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $login_error = 'Please enter your email and password.';
    } else {
        try {
            $db  = new Database();
            $pdo = $db->getConnection(); // FIX: was never called before

            // FIX: column is Email, not username. Table is User (capital U)
            $stmt = $pdo->prepare('SELECT * FROM User WHERE Email = :email AND IsActive = 1 LIMIT 1');
            $stmt->execute([':email' => $email]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // FIX: use password_verify() — passwords in DB are bcrypt hashed
                if (password_verify($password, $user['Password'])) {
                    session_regenerate_id(true);
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id']   = $user['ID'];
                    $_SESSION['name']      = $user['Name'];
                    $_SESSION['user_name'] = $user['Name'];
                    $_SESSION['email']     = $user['Email'];
                    $_SESSION['role']      = $user['Role'];
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $login_error = 'Invalid email or password.';
                }
            } else {
                $login_error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $login_error = 'A database error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TechGear | Login</title> 
    link rel="stylesheet" href="assets/css/login.css" />
</head>

<body>

    <div class="login-box">

        <!-- LEFT SIDE -->
        <div class="left-side">
            <div class="logo-box">
                <img src="logo.png" alt="Logo" width="80">
            </div>
            <h1>Tech<span>Gear</span></h1>
            <p>Inventory Management</p>
            <small>Track and manage your computer parts inventory in one place.</small>
        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <h2>Login</h2>

            <?php if (!empty($login_error)): ?>
                <div class="error">⚠ <?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="input-group">
                    <!-- Changed from username to email (matches the DB column) -->
                    <input type="email" name="email" placeholder="Email address"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Password">
                </div>

                <div class="options">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button class="login-btn" type="submit" name="login">Login</button>

            </form>

        </div>

    </div>

</body>

</html>