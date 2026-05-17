<?php
session_start();
require_once 'includes/config.php';

// If already logged in, go straight to dashboard
if (!empty($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit();
}

/*
 * How the 3 steps work:
 *   Step 1 — user enters their email
 *   Step 2 — user enters their full name to prove identity (no email server needed)
 *   Step 3 — user sets a new password
 *
 * Progress is tracked using $_SESSION['fp_step'].
 */

$step  = $_SESSION['fp_step'] ?? 1;
$error = '';

// ── STEP 1 submit: check if email exists ──────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step1'])) {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            $db   = new Database();
            $pdo  = $db->getConnection();
            $stmt = $pdo->prepare('SELECT ID, Name FROM User WHERE Email = :email AND IsActive = 1 LIMIT 1');
            $stmt->execute([':email' => $email]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                // Save info in session so we can verify and update later
                $_SESSION['fp_step']    = 2;
                $_SESSION['fp_email']   = $email;
                $_SESSION['fp_user_id'] = $user['ID'];
                $_SESSION['fp_name']    = $user['Name'];
                $step = 2;
            } else {
                $error = 'No account found with that email.';
            }
        } catch (PDOException $e) {
            error_log('ForgotPW step1: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    }
}

// ── STEP 2 submit: verify full name ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step2'])) {
    verifyCsrf();
    $inputName = trim($_POST['full_name'] ?? '');

    if (empty($inputName)) {
        $error = 'Please enter your full name.';
    } elseif (strcasecmp($inputName, $_SESSION['fp_name'] ?? '') !== 0) {
        // strcasecmp compares strings ignoring uppercase/lowercase
        $error = 'Name does not match our records. Please try again.';
    } else {
        $_SESSION['fp_step']     = 3;
        $_SESSION['fp_verified'] = true;
        $step = 3;
    }
}

// ── STEP 3 submit: save new password ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step3'])) {
    verifyCsrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (empty($password) || empty($confirm)) {
        $error = 'Please fill in both fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (empty($_SESSION['fp_verified'])) {
        // Safety check — should not happen normally
        $error = 'Session expired. Please start over.';
        unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_user_id'], $_SESSION['fp_name'], $_SESSION['fp_verified']);
        $step = 1;
    } else {
        try {
            $db   = new Database();
            $pdo  = $db->getConnection();
            $stmt = $pdo->prepare('UPDATE User SET Password = :password WHERE ID = :id');
            $stmt->execute([
                ':password' => password_hash($password, PASSWORD_BCRYPT),
                ':id'       => $_SESSION['fp_user_id'],
            ]);

            // Clean up session variables used for this reset flow
            unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_user_id'], $_SESSION['fp_name'], $_SESSION['fp_verified']);

            $step = 'done';
        } catch (PDOException $e) {
            error_log('ForgotPW step3: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    }
}

// Allow user to go back and use a different email
if (isset($_GET['reset'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_user_id'], $_SESSION['fp_name'], $_SESSION['fp_verified']);
    header('Location: forgot_password.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TechGear | Forgot Password</title>
    <link rel="stylesheet" href="css/forgot_password.css" />
</head>

<body>

    <div class="login-box">

        <!-- LEFT SIDE — same on all steps -->
        <div class="left-side">
            <div class="logo-box">
                <img src="logo.png" alt="Logo" width="80">
            </div>
            <h1>Tech<span>Gear</span></h1>
            <p>Inventory Management</p>
            <small>Track and manage your computer parts inventory in one place.</small>
        </div>

        <!-- RIGHT SIDE — changes per step -->
        <div class="right-side">

            <?php if (!empty($error)): ?>
                <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- ── STEP 1: Enter email ── -->
            <?php if ($step === 1): ?>

                <h2>Forgot Password?</h2>
                <p class="step-label">Step 1 of 3 — Enter your email</p>

                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email address"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <button class="login-btn" type="submit" name="step1">Continue →</button>
                    <div class="create">
                        <a href="login.php">← Back to Login</a>
                    </div>
                </form>

                <!-- ── STEP 2: Verify full name ── -->
            <?php elseif ($step === 2): ?>

                <h2>Verify Identity</h2>
                <p class="step-label">Step 2 of 3 — Enter the name on your account</p>

                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="input-group">
                        <input type="text" name="full_name" placeholder="Your full name">
                    </div>
                    <button class="login-btn" type="submit" name="step2">Verify →</button>
                    <div class="create">
                        <a href="forgot_password.php?reset=1">← Use a different email</a>
                    </div>
                </form>

                <!-- ── STEP 3: Set new password ── -->
            <?php elseif ($step === 3): ?>

                <h2>New Password</h2>
                <p class="step-label">Step 3 of 3 — Choose a new password</p>

                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="New password (min. 8 characters)">
                    </div>
                    <div class="input-group">
                        <input type="password" name="confirm" placeholder="Confirm new password">
                    </div>
                    <button class="login-btn" type="submit" name="step3">Update Password</button>
                </form>

                <!-- ── DONE ── -->
            <?php elseif ($step === 'done'): ?>

                <div class="done-icon">🔓</div>
                <h2>Password Updated!</h2>
                <p class="step-label">Your password has been changed successfully.</p>
                <a href="login.php" class="login-btn">Go to Login →</a>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>