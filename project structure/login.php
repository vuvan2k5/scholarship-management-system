<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/auth.php';

$pdo = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {

        $error = "Please fill in all fields.";

    } else {

        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if ($user && $password === $user['password_hash']) {

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user_name'] = $user['full_name'];

            $_SESSION['role'] = $user['role'];

            $_SESSION['email'] = $user['email'];

            $_SESSION['student_code'] = $user['student_code'];

            // Redirect theo role

            if ($user['role'] === 'admin') {

                header("Location: admin/dashboard.php");

            } elseif ($user['role'] === 'student') {

                header("Location: student/dashboard.php");
            exit;

            } else {

                header("Location: index.php");
            }

            exit;

        } else {

            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Login</title>

    <style>

        body{
            font-family: Arial;
            background: #f4f6f9;
        }

        .login-box{

            width: 400px;

            margin: 100px auto;

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2{
            text-align: center;
            margin-bottom: 20px;
        }

        input{

            width: 100%;

            padding: 12px;

            margin-bottom: 15px;

            border: 1px solid #ccc;

            border-radius: 5px;
        }

        button{

            width: 100%;

            padding: 12px;

            background: #007bff;

            color: white;

            border: none;

            border-radius: 5px;

            cursor: pointer;
        }

        button:hover{

            background: #0056b3;
        }

        .error{

            color: red;

            margin-bottom: 15px;

            text-align: center;
        }

    </style>

</head>

<body>

    <div class="login-box">

        <h2>Scholarship System Login</h2>

        <?php if (!empty($error)) : ?>

            <div class="error">
                <?= $error ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="email"
                name="email"
                placeholder="Enter email"
                required
            >

            <input
                type="password"
                name="password"
                placeholder="Enter password"
                required
            >

            <button type="submit">
                Login
            </button>

        </form>

    </div>

</body>
</html>