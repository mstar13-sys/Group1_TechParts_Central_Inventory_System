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
            font-size: 28px;
            font-weight: 700;
            color: #00d9ff;
            margin-bottom: 8px;
            text-align: center;
        }

        .step-label {
            text-align: center;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 24px;
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

        /* Used for the "done" go-to-login link styled as a button */
        a.login-btn {
            display: block;
            text-align: center;
            line-height: 48px;
            text-decoration: none;
        }

        .create {
            margin-top: 20px;
            text-align: center;
        }

        .create a {
            color: #00d9ff;
            font-size: 13px;
            text-decoration: none;
        }

        .create a:hover {
            color: #ffffff;
        }

        /* Success / done screen */
        .done-icon {
            font-size: 50px;
            text-align: center;
            margin-bottom: 16px;
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
                padding: 36px;
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

            .logo-box {
                width: 55px;
                height: 55px;
                margin-bottom: 10px;
            }

            .logo-box img {
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
                padding: 24px;
            }

            .right-side h2 {
                font-size: 22px;
            }

            .input-group {
                margin-bottom: 12px;
            }

            .input-group input {
                height: 42px;
            }

            .login-btn {
                height: 42px;
                font-size: 15px;
            }

            .create {
                margin-top: 14px;
            }
        }
    </style>
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
                <div class="error" data-swal>⚠ <?= htmlspecialchars($error) ?></div>
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

                <div class="done-alert" data-swal data-title="Password Updated" data-message="Your password has been changed successfully."></div>
                <div class="done-icon">🔓</div>
                <h2>Password Updated!</h2>
                <p class="step-label">Your password has been changed successfully.</p>
                <a href="login.php" class="login-btn">Go to Login →</a>

            <?php endif; ?>

        </div>

    </div>

</body>
<script src="/js/app.js"></script>
</html>
