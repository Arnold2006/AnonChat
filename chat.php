<?php
require 'db.php';
session_start();

// Redirect to login page if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Log out functionality
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Update last active time for the current user
$stmt = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

// Thumbnail creation function
function createThumbnail($source, $destination, $width) {
    // Check if source image exists
    if (!file_exists($source)) {
        die("Source file does not exist: $source");
    }

    // Get image information
    $info = getimagesize($source);
    if (!$info) {
        die("Not a valid image file: $source");
    }

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

    // Get original dimensions
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    // Calculate new dimensions while maintaining aspect ratio
    $height = (int) (($width / $originalWidth) * $originalHeight); // Explicit cast to integer
    $width = (int) $width; // Explicit cast to integer

    // Create a new blank image
    $thumbnail = imagecreatetruecolor($width, $height);

    // Resize the original image into the blank image
    if (!imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight)) {
        die("Failed to create thumbnail.");
    }

    // Save the thumbnail to the destination
    if (!imagejpeg($thumbnail, $destination)) {
        die("Failed to save thumbnail to: $destination");
    }

    // Free up memory
    imagedestroy($image);
    imagedestroy($thumbnail);
}


// Handle messages and image uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = $_POST['message'] ?? '';
    $uploadedImages = [];

    if (!empty($_FILES['images']['name'][0])) {
        $targetDir = "uploads/";
        $thumbnailDir = $targetDir . "thumbnails/";

        // Ensure directories exist
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0755, true);

        foreach ($_FILES['images']['name'] as $key => $imageName) {
            $imagePath = $targetDir . basename($imageName);
            $thumbnailPath = $thumbnailDir . basename($imageName);

            // Move the uploaded file to the uploads directory
            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $imagePath)) {
                $uploadedImages[] = $imagePath;

                // Create a thumbnail
                createThumbnail($imagePath, $thumbnailPath, 200); // Thumbnail width = 200px
            } else {
                die("Failed to upload file: $imageName");
            }
        }
    }

    // Save message and uploaded image paths to the database
    $stmt = $db->prepare("INSERT INTO messages (user_id, message, image_path) VALUES (?, ?, ?)");
    if (empty($uploadedImages)) {
        $stmt->execute([$_SESSION['user_id'], $message, null]);
    } else {
        foreach ($uploadedImages as $imagePath) {
            $stmt->execute([$_SESSION['user_id'], $message, $imagePath]);
        }
    }
}

// Get active users (last active within the last 5 minutes)
$activeUsers = $db->query("SELECT username FROM users WHERE last_active > (NOW() - INTERVAL 5 MINUTE)")->fetchAll(PDO::FETCH_COLUMN);

// Get messages (newest first)
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
        <!-- Sidebar with active users -->
        <div class="sidebar">
            <h2>Active Users</h2>
            <ul>
                <?php foreach ($activeUsers as $user): ?>
                    <li><?= htmlspecialchars($user) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Chat content -->
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
                            <img src="<?= htmlspecialchars('uploads/thumbnails/' . basename($row['image_path'])) ?>" alt="Image Thumbnail" onclick="openModal('<?= htmlspecialchars($row['image_path']) ?>')">
                            <a href="<?= htmlspecialchars($row['image_path']) ?>" download class="download-icon">Download</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" enctype="multipart/form-data">
                <textarea name="message" rows="3" placeholder="Enter your message" required></textarea>
                <input type="file" name="images[]" multiple>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <img id="modal-image" src="" alt="Image">
</div>

   <script>
    // Auto-update chat every 3 seconds
    setInterval(function () {
        fetch(location.href)
            .then(response => response.text())
            .then(data => {
                const chatBox = document.getElementById("chat-box");
                const newChatBox = new DOMParser().parseFromString(data, 'text/html').getElementById("chat-box");
                chatBox.innerHTML = newChatBox.innerHTML;
            });
    }, 3000);

    // Open image in modal
function openModal(imageSrc) {
    const modal = document.getElementById("imageModal");
    const modalImage = document.getElementById("modal-image");
    modal.style.display = "flex";
    modalImage.src = imageSrc;
}

// Close image modal and reset the image source
function closeModal() {
    const modal = document.getElementById("imageModal");
    const modalImage = document.getElementById("modal-image");
    modal.style.display = "none";
    modalImage.src = ""; // Reset the image source to clear the modal memory
}

    // Send message on "Enter" key press
    const messageInput = document.querySelector('textarea[name="message"]');
    const form = messageInput.closest('form'); // Get the parent form element

    messageInput.addEventListener('keypress', function (event) {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault(); // Prevent adding a new line
            form.submit(); // Submit the form
        }
    });
</script>
</body>
</html>
