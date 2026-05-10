<?php
// login.php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!empty($_SESSION['user_id'])) {
  header('Location: /dashboard.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email && $password) {
    try {
      $db   = getDB();
      $stmt = $db->prepare('SELECT ID, Name, Role, Password FROM User WHERE Email = ? AND IsActive = 1 LIMIT 1');
      $stmt->execute([$email]);
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['Password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['ID'];
        $_SESSION['user_name'] = $user['Name'];
        $_SESSION['role']      = $user['Role'];
        header('Location: /dashboard.php');
        exit;
      }
      $error = 'Invalid email or password.  ';
      
    } catch (PDOException $e) {
      error_log("Login Error: " . $e->getMessage());
      $error = 'Database connection error. Please try again later.';
    }
  } else {
    $error = 'Please fill in all fields.';
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>IM ComParts | Login</title>
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
    }

    .logo-box svg {
        width: 42px;
        height: 42px;
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
    body {
        padding: 40px 30px;
    }

    .login-box {
        flex-direction: column;
        width: 420px;
        height: auto;
        margin: auto;
    }

    .left-side {
        width: 100%;
        padding: 36px 30px;
        border-right: none;
        border-bottom: 1px solid rgba(0, 255, 255, 0.15);
    }

    .right-side {
        width: 100%;
        padding: 36px 36px;
    }
}

    @media (max-width: 600px) {
    body {
        padding: 16px;
    }

    .login-box {
        width: 100%;
        max-width: 400px;
        height: auto;
    }

    .left-side {
        padding: 20px 24px;
    }

    .left-side .logo-box {
        width: 55px;
        height: 55px;
        margin-bottom: 10px;
    }

    .left-side .logo-box img {
        width: 55px;
    }

    .left-side h1 {
        font-size: 20px;
        margin-bottom: 4px;
    }

    .left-side p {
        font-size: 10px;
        margin-bottom: 0;
    }

    .left-side small {
        display: none;
    }

    .right-side {
        padding: 24px 24px;
    }

    .right-side h2 {
        font-size: 22px;
        margin-bottom: 18px;
    }

    .input-group {
        margin-bottom: 12px;
    }

    .input-group input {
        height: 42px;
    }

    .options {
        margin: 4px 0 16px;
    }

    .login-btn {
        height: 42px;
        font-size: 15px;
    }

    .create {
        margin-top: 14px;
    }}
</style>
</head>

<body>

    <div class="login-box">

        <!-- LEFT SIDE -->
        <div class="left-side">

            <div class="logo-box">
                <img src="logo.png" alt="Logo" width="80">
            </div>

            <h1><span>Tech</span>Parts</h1>
            <p>Inventory & POS System</p>
            <small>Manage computer parts inventory and sales in one place.</small>

        </div>

        <!-- RIGHT SIDE -->
        <div class="right-side">

            <h2>Login</h2>

            <?php if (!empty($error)): ?>
            <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button class="login-btn" type="submit">Login →</button>

            </form>

            <div style="margin-top: 20px; padding: 14px; background: rgba(0, 229, 255, 0.08); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 8px; font-size: 12px; color: rgba(255, 255, 255, 0.7); line-height: 1.6;">
              <strong style="color: #00e5ff; display: block; margin-bottom: 6px;">Demo Credentials</strong>
              <div>Admin: admin@techparts.com</div>
              <div>Password: Admin123</div>
            </div>

        </div>

    </div>

</body>
</html>