<?php
// Include the database connection file
require 'db.php';
session_start();

// --- SESSION MANAGEMENT ---

// Redirect to the login page if the user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit; // Terminate script execution
}

// Handle user logout
if (isset($_POST['logout'])) {
    session_unset(); // Remove all session variables
    session_destroy(); // Destroy the session
    header("Location: index.php"); // Redirect to the login page
    exit; // Terminate script execution
}

// Update the last active timestamp for the current user
$stmt = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

// --- FUNCTION DEFINITIONS ---

/**
 * Create a thumbnail for an image file.
 *
 * @param string $source The path to the original image file.
 * @param string $destination The path to save the thumbnail.
 * @param int $width The desired width of the thumbnail (height is calculated automatically).
 */
function createThumbnail($source, $destination, $width) {
    // Ensure the source file exists
    if (!file_exists($source)) {
        die("Source file does not exist: $source");
    }

    // Get the image's metadata
    $info = getimagesize($source);
    if (!$info) {
        die("Not a valid image file: $source");
    }

    // Determine the MIME type and load the image accordingly
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            die("Unsupported image type: $mime");
    }

    // Calculate new dimensions while maintaining aspect ratio
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    $height = (int)(($width / $originalWidth) * $originalHeight);

    // Create a blank image canvas for the thumbnail
    $thumbnail = imagecreatetruecolor($width, $height);

    // Resize the original image to fit the new dimensions
    if (!imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight)) {
        die("Failed to create thumbnail.");
    }

    // Save the thumbnail as a JPEG file
    if (!imagejpeg($thumbnail, $destination)) {
        die("Failed to save thumbnail to: $destination");
    }

    // Free memory used by image resources
    imagedestroy($image);
    imagedestroy($thumbnail);
}

// --- MAIN LOGIC: MESSAGE AND FILE HANDLING ---

/**
 * Handle form submission, including message saving and file uploads.
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = $_POST['message'] ?? ''; // Get the message from the form
    $uploadedFiles = []; // Store paths of uploaded files

    // Process file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $targetDir = "uploads/"; // Directory for storing uploaded files
        $thumbnailDir = $targetDir . "thumbnails/"; // Directory for storing thumbnails

        // Ensure the upload directories exist
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0755, true);

        foreach ($_FILES['files']['name'] as $key => $fileName) {
            $filePath = $targetDir . basename($fileName);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Define allowed file extensions
            $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowedArchiveExtensions = ['zip', '7z', '001', '002', '003'];

            if (in_array($fileExtension, $allowedImageExtensions)) {
                // Handle image uploads
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $filePath)) {
                    $uploadedFiles[] = $filePath; // Save the file path

                    // Create a thumbnail for the uploaded image
                    $thumbnailPath = $thumbnailDir . basename($fileName);
                    createThumbnail($filePath, $thumbnailPath, 200);
                } else {
                    die("Failed to upload image file: $fileName");
                }
            } elseif (in_array($fileExtension, $allowedArchiveExtensions)) {
                // Handle archive uploads (e.g., .7z, .zip)
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $filePath)) {
                    $uploadedFiles[] = $filePath; // Save the file path
                } else {
                    die("Failed to upload archive file: $fileName");
                }
            } else {
                // Reject unsupported file types
                die("Unsupported file type: $fileName");
            }
        }
    }

    // Save the message and file paths to the database
    $stmt = $db->prepare("INSERT INTO messages (user_id, message, image_path) VALUES (?, ?, ?)");
    if (empty($uploadedFiles)) {
        $stmt->execute([$_SESSION['user_id'], $message, null]);
    } else {
        foreach ($uploadedFiles as $filePath) {
            $stmt->execute([$_SESSION['user_id'], $message, $filePath]);
        }
    }
}

// --- FETCH DATA FOR DISPLAY ---

// Fetch active users (users active within the last 5 minutes)
$activeUsers = $db->query("SELECT username FROM users WHERE last_active > (NOW() - INTERVAL 5 MINUTE)")->fetchAll(PDO::FETCH_COLUMN);

// Fetch messages (ordered by newest first)
$messages = $db->query("SELECT m.*, u.username FROM messages m JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <link rel="stylesheet" href="stylechat.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Active Users</h2>
            <ul>
                <?php foreach ($activeUsers as $user): ?>
                    <li><?= htmlspecialchars($user) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="chat-content">
            <form method="post" class="button-form">
                <button type="submit" name="logout" class="action-btn">Log out</button>
                <a href="cleanup.php" target="_blank" class="action-btn">Admin</a>
            </form>

            <div class="chat-box" id="chat-box">
                <?php foreach ($messages as $row): ?>
                    <div class="message">
                        <strong><?= htmlspecialchars($row['username']) ?>:</strong> <?= htmlspecialchars($row['message']) ?><br>
                        <?php if ($row['image_path']): ?>
                            <?php $fileExtension = strtolower(pathinfo($row['image_path'], PATHINFO_EXTENSION)); ?>
                            <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?= htmlspecialchars('uploads/thumbnails/' . basename($row['image_path'])) ?>" alt="Image Thumbnail">
                            <?php elseif (in_array($fileExtension, ['zip', '7z'])): ?>
                                <a href="<?= htmlspecialchars($row['image_path']) ?>" download>Download <?= htmlspecialchars(basename($row['image_path'])) ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" enctype="multipart/form-data">
                <textarea name="message" rows="3" placeholder="Enter your message" required></textarea>
                <input type="file" name="files[]" multiple>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <script>
        // Automatically refresh the chat box every 3 seconds
        setInterval(function () {
            fetch(location.href)
                .then(response => response.text())
                .then(data => {
                    const chatBox = document.getElementById("chat-box");
                    const newChatBox = new DOMParser().parseFromString(data, 'text/html').getElementById("chat-box");
                    chatBox.innerHTML = newChatBox.innerHTML;
                });
        }, 3000);

        // Modal functionality for image viewing
        function openModal(imageSrc) {
            const modal = document.getElementById("imageModal");
            const modalImage = document.getElementById("modal-image");
            modal.style.display = "flex";
            modalImage.src = imageSrc;
        }

        function closeModal() {
            const modal = document.getElementById("imageModal");
            modal.style.display = "none";
        }
    </script>
</body>
</html>
