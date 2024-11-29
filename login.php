<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: chat.php");
        exit;
    } else {
        echo "Forkert brugernavn eller adgangskode.";
    }
}
?>
<form method="post">
    Brugernavn: <input type="text" name="username" required>
    Adgangskode: <input type="password" name="password" required>
    <button type="submit">Login</button>
</form>
