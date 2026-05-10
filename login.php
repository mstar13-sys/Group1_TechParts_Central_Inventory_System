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
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>TechGear | Login</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        min-height: 100vh;
        background: #0d1b2a;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .login-box {
        display: flex;
        width: 820px;
        height: 460px;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid rgba(0, 255, 255, 0.25);
        box-shadow: 0 0 30px rgba(0, 255, 255, 0.07);
    }

    /* ---- LEFT SIDE ---- */
    .left-side {
        width: 50%;
        background: #0a1929;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        border-right: 1px solid rgba(0, 255, 255, 0.15);
    }

    .logo-box {
        width: 80px;
        height: 80px;
        border-radius: 18px;
        border: 1px solid rgba(0, 229, 255, 0.35);
        background: rgba(0, 229, 255, 0.05);
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .logo-box img {
        width: 80px;
        height: 80px;
        object-fit: cover;
    }

    .left-side h1 {
        font-size: 26px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 6px;
    }

    .left-side h1 span {
        color: #00e5ff;
    }

    .left-side p {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.4);
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 24px;
    }

    .left-side small {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.25);
        text-align: center;
        max-width: 200px;
        line-height: 1.7;
    }

    /* ---- RIGHT SIDE ---- */
    .right-side {
        width: 50%;
        background: #0d1b2a;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 44px 40px;
    }

    .right-side h2 {
        font-size: 30px;
        font-weight: 700;
        color: #00d9ff;
        margin-bottom: 28px;
        text-align: center;
    }

    .error {
        background: rgba(255, 80, 80, 0.1);
        border: 1px solid rgba(255, 80, 80, 0.3);
        color: #ff8080;
        font-size: 13px;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 16px;
    }

    .input-group {
        position: relative;
        margin-bottom: 16px;
    }

    .input-group input {
        width: 100%;
        height: 48px;
        padding: 0 16px;
        border-radius: 8px;
        border: 1px solid rgba(0, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.04);
        color: #ffffff;
        font-size: 15px;
        outline: none;
        transition: 0.2s;
    }

    .input-group input::placeholder {
        color: rgba(255, 255, 255, 0.45);
    }

    .input-group input:focus {
        border-color: #00e5ff;
        box-shadow: 0 0 10px rgba(0, 229, 255, 0.2);
    }

    .options {
        display: flex;
        justify-content: flex-end;
        margin: 6px 0 22px;
    }

    .options a {
        color: #00d9ff;
        font-size: 13px;
        text-decoration: none;
    }

    .options a:hover {
        color: #ffffff;
    }

    .login-btn {
        width: 100%;
        height: 48px;
        border: none;
        border-radius: 8px;
        background: linear-gradient(90deg, #00d8ff, #00a8ff);
        color: #ffffff;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 0 16px rgba(0, 216, 255, 0.35);
        transition: 0.2s;
    }

    .login-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 0 24px rgba(0, 216, 255, 0.55);
    }

    .create {
        margin-top: 20px;
        text-align: center;
    }

    .create a {
        color: #00d9ff;
        font-size: 14px;
        text-decoration: none;
    }

    .create a:hover {
        color: #ffffff;
    }

    @media (max-width: 768px) {
        body { padding: 40px 30px; }
        .login-box { flex-direction: column; width: 420px; height: auto; margin: auto; }
        .left-side { width: 100%; padding: 36px 30px; border-right: none; border-bottom: 1px solid rgba(0, 255, 255, 0.15); }
        .right-side { width: 100%; padding: 36px; }
    }

    @media (max-width: 600px) {
        body { padding: 16px; }
        .login-box { width: 100%; max-width: 400px; height: auto; }
        .left-side { padding: 20px 24px; }
        .logo-box { width: 55px; height: 55px; margin-bottom: 10px; }
        .logo-box img { width: 55px; }
        .left-side h1 { font-size: 20px; margin-bottom: 4px; }
        .left-side p { font-size: 10px; margin-bottom: 0; }
        .left-side small { display: none; }
        .right-side { padding: 24px; }
        .right-side h2 { font-size: 22px; margin-bottom: 18px; }
        .input-group { margin-bottom: 12px; }
        .input-group input { height: 42px; }
        .options { margin: 4px 0 16px; }
        .login-btn { height: 42px; font-size: 15px; }
        .create { margin-top: 14px; }
    }
</style>
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