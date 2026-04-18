<?php
session_start();
require_once '../connection/database.php';

$db = new Database();
$db->conn;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare and execute query to check credentials
    $query = "SELECT * FROM users WHERE username = :username AND password = :password";
    $stmt = $db->conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        //mao ni ag rediction base sa role sa user
        if ($user['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['username'] = $username;
            header("Location: user_page.php");
            exit();
        }
    } else {
        $login_error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IM ComParts | Login</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
        }


        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
            background-image: url('../image/background.png');
            background-size: cover;
            background-position: center;
        }

        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            margin-top: 20px;
        }

        .form-group {
            width: 100%;
            padding: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            margin: 10px 0;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            width: 90%;
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .btn:hover {
            background: #2980b9;
        }

        /* Login Form */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: auto;
        }

        .login-form {
            background: white;
            padding: 10px;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            height: auto;
            opacity: .90;

            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }


        .login-form h2 {
            text-align: center;
            color: #2c3e50;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 10px;
        }


        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>

<body>


    <div class="login-container">
        <form class="login-form" method="POST">
            <h2>Login</h2>
            <?php
            if (isset($login_error)) {
                echo '<div class="error">' . $login_error . '</div>';
            }
            ?>
            <div class="form-group">
                <input type="text" name="username" required placeholder="Enter Username">
                <input type="password" name="password" required placeholder="Enter Password">
            </div>
            <button type="submit" name="login" class="btn">Login</button>
            <div class="toggle-link">
                Don't have an account? <a href="registration.php">Register here</a>
            </div>
        </form>

    </div>

</body>

</html>