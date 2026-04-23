<?php
session_start();
require_once 'database.php';

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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>IM ComParts | Login</title>
<style>
    *{
        margin:0;
        padding:0;
        box-sizing:border-box;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    body{
        min-height:100vh;
        background:#0d1b2a;
        overflow:hidden;
    }

    .main-container{
        display:flex;
        height:100vh;
    }

    .left-panel{
        width:60%;
        background:#0d1b2a url("logo.png") center center no-repeat;
        background-size:100%;
        position:relative;
    }

    .right-panel{
        width:40%;
        display:flex;
        justify-content:center;
        align-items:center;
        background:
            radial-gradient(circle at top left, rgba(0,255,255,.08), transparent 35%),
            radial-gradient(circle at bottom right, rgba(0,140,255,.06), transparent 30%),
            #0d1b2a;
        padding:30px;
    }

    .login-box{
        width:100%;
        max-width:430px;

        padding:42px 34px;
        border-radius:18px;

        background:rgba(255,255,255,0.06);
        backdrop-filter:blur(18px);
        -webkit-backdrop-filter:blur(18px);

        border:1px solid rgba(0,255,255,0.35);

        box-shadow:
            0 0 25px rgba(0,255,255,0.08),
            inset 0 0 12px rgba(255,255,255,0.03);
    }

    .login-box h2{
        text-align:center;
        color: rgb(0, 217, 255);
        font-size:42px;
        font-weight:700;
        margin-bottom:35px;
        text-shadow:0 0 12px rgba(255,255,255,0.15);
    }

    .input-group{
        position:relative;
        margin-bottom:18px;
    }

    .input-group input{
        width:100%;
        height:52px;
        padding:0 48px 0 18px;

        border-radius:10px;
        outline:none;

        border:1px solid rgba(0,255,255,0.35);
        background:rgba(255,255,255,0.04);

        color:#ffffff;
        font-size:15px;

        transition:0.25s ease;
    }

    .input-group input::placeholder{
        color:rgba(255,255,255,0.58);
    }

    .input-group input:focus{
        border-color:#00eaff;
        box-shadow:0 0 12px rgba(0,234,255,.35);
    }

    .icon{
        position:absolute;
        right:16px;
        top:50%;
        transform:translateY(-50%);
        color:rgba(255,255,255,0.55);
        font-size:18px;
    }

    .options{
        display:flex;
        justify-content:flex-end;
        margin:8px 0 24px;
    }

    .options a{
        color:#2fe6ff;
        text-decoration:none;
        font-size:15px;
        transition:0.25s;
    }

    .options a:hover{
        color:#ffffff;
    }

    .login-btn{
        width:100%;
        height:52px;
        border:none;
        border-radius:10px;

        font-size:20px;
        font-weight:600;
        color:#ffffff;
        cursor:pointer;

        background:linear-gradient(90deg,#00d8ff,#00a8ff);
        box-shadow:
            0 0 18px rgba(0,216,255,0.45),
            inset 0 0 10px rgba(255,255,255,0.08);

        transition:0.25s ease;
    }

    .login-btn:hover{
        transform:translateY(-2px);
        box-shadow:
            0 0 28px rgba(0,216,255,0.65),
            inset 0 0 12px rgba(255,255,255,0.1);
    }

    .create{
        margin-top:26px;
        text-align:center;
    }

    .create a{
        color:#2fe6ff;
        text-decoration:none;
        font-size:17px;
        font-weight:500;
    }

    .create a:hover{
        color:#ffffff;
    }

    @media(max-width:950px){

        .main-container{
            flex-direction:column;
        }

        .left-panel{
            width:100%;
            height:35vh;
            background-size:38%;
        }

        .right-panel{
            width:100%;
            height:65vh;
        }
    }

    @media(max-width:600px){

        .left-panel{
            display:none;
        }

        .right-panel{
            width:100%;
            height:100vh;
        }

        .login-box{
            padding:34px 22px;
        }

        .login-box h2{
            font-size:34px;
        }
    }
</style>
</head>

<body>

<div class="main-container">

    <div class="left-panel"></div>

    <div class="right-panel">

        <form class="login-box">

            <h2>Login</h2>

            <div class="input-group">
                <input type="text" placeholder="👤Username">
            </div>

            <div class="input-group">
                <input type="password" placeholder="🔒Password">
            </div>

            <div class="options">
                <a href="#">Forgot Password?</a>
            </div>

            <button class="login-btn">Login</button>

            <div class="create">
                <a href="#">Create an account</a>
            </div>

        </form>

    </div>

</div>

</body>
</html>