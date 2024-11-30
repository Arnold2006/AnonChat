<?php
require 'db.php'; // Include database connection
session_start();

// Admin password (you should store this securely in an environment variable or hashed in the database)
$adminPassword = 'admin123'; 

// Check if admin is authenticated
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $adminPassword) {
        // Password is correct; proceed with cleanup
        try {
            // 1. Delete all messages from the database
            $stmt = $db->prepare("DELETE FROM messages");
            $stmt->execute();

            // 2. Delete all files in the uploads directory
            $uploadFolder = "uploads/";
            $thumbFolder = "uploads/thumbnails/";

            // Helper function to delete files in a folder
            function deleteFilesInFolder($folder) {
                $files = glob($folder . '*'); // Get all files in the folder
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file); // Delete the file
                    }
                }
            }

            // Delete files in uploads and thumbnails
            deleteFilesInFolder($uploadFolder);
            deleteFilesInFolder($thumbFolder);

            // Success message
            $successMessage = "All messages, uploaded files, and thumbnails have been successfully deleted.";
        } catch (Exception $e) {
            $errorMessage = "An error occurred: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Incorrect admin password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Cleanup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
        }
        .container {
            text-align: center;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        input[type="password"] {
            padding: 10px;
            margin: 10px 0;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            padding: 10px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #c82333;
        }
        .message {
            margin-top: 20px;
            font-size: 16px;
        }
        .message.success {
            color: green;
        }
        .message.error {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Cleanup</h1>
        <p>Enter the admin password to delete all chat messages and uploaded files, including thumbnails.</p>
        <form method="post" action="">
            <input type="password" name="password" placeholder="Admin Password" required>
            <button type="submit">Perform Cleanup</button>
        </form>
        <?php if (isset($successMessage)): ?>
            <p class="message success"><?= htmlspecialchars($successMessage) ?></p>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <p class="message error"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
