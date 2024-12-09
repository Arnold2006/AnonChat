<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Cleanup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 250px; /* Width for 5x8 shape */
            height: 400px; /* Height for 5x8 shape */
        }
        .container h1 {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
        }
        .container p {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }
        input[type="password"],
        button {
            width: 100%; /* Same width for both input and button */
            padding: 10px 12px;
            margin-bottom: 15px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box; /* Ensures padding doesn't affect width */
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 15px;
            font-size: 14px;
        }
        .message.success {
            color: green;
        }
        .message.error {
            color: red;
        }
        .brand {
            margin-top: 15px;
            font-size: 14px;
            color: #555;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Set focus on the password input field when the page loads
            document.querySelector('input[name="password"]').focus();
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Sign In</h1>
        <p>Enter the admin password to proceed with cleanup.</p>
        <form method="post" action="">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
        <?php if (isset($successMessage)): ?>
            <p class="message success"><?= htmlspecialchars($successMessage) ?></p>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <p class="message error"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>
        <div class="brand">Admin Panel</div>
    </div>
</body>
</html>
