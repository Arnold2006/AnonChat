<?php
require 'db.php';
session_start();

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Check if the username already exists
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $error = "Username already exists. Please choose another.";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt->execute([$username, $passwordHash])) {
            $_SESSION['user_id'] = $db->lastInsertId();
            header("Location: chat.php");
            exit;
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Create an Account</title>
   <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h2>Create an Account</h2>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="post" action="">
            <div class="form-group">
                <label for="register-username">Username</label>
                <input type="text" id="register-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="register-password">Password</label>
                <input type="password" id="register-password" name="password" required>
            </div>
            <input type="submit" name="register" value="Register">
        </form>

        <div class="link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>
</body>
</html>
