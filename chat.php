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
    if (!file_exists($source)) {
        die("Source file does not exist: $source");
    }

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

    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    $height = (int)(($width / $originalWidth) * $originalHeight);
    $thumbnail = imagecreatetruecolor($width, $height);

    if (!imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight)) {
        die("Failed to create thumbnail.");
    }

    if (!imagejpeg($thumbnail, $destination)) {
        die("Failed to save thumbnail to: $destination");
    }

    imagedestroy($image);
    imagedestroy($thumbnail);
}

// Handle messages and uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = $_POST['message'] ?? '';
    $uploadedFiles = [];

    if (!empty($_FILES['files']['name'][0])) {
        $targetDir = "uploads/";
        $thumbnailDir = $targetDir . "thumbnails/";

        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_dir($thumbnailDir)) mkdir($thumbnailDir, 0755, true);

        $sevenZipParts = [];
        foreach ($_FILES['files']['name'] as $key => $fileName) {
            $filePath = $targetDir . basename($fileName);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowedArchiveExtensions = ['zip', '7z', '001', '002', '003'];

            if (in_array($fileExtension, $allowedImageExtensions)) {
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $filePath)) {
                    $uploadedFiles[] = $filePath;

                    $thumbnailPath = $thumbnailDir . basename($fileName);
                    createThumbnail($filePath, $thumbnailPath, 200);
                } else {
                    die("Failed to upload file: $fileName");
                }
            } elseif (in_array($fileExtension, $allowedArchiveExtensions)) {
                $sevenZipParts[] = $filePath;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $filePath)) {
                    $uploadedFiles[] = $filePath;
                } else {
                    die("Failed to upload archive part: $fileName");
                }
            } else {
                die("Unsupported file type: $fileName");
            }
        }

        if (!empty($sevenZipParts)) {
            sort($sevenZipParts);
            $baseName = pathinfo($sevenZipParts[0], PATHINFO_FILENAME);

            $missingParts = false;
            for ($i = 1; $i <= count($sevenZipParts); $i++) {
                $expectedPart = sprintf("%s.7z.%03d", $baseName, $i);
                if (!in_array("$targetDir$expectedPart", $sevenZipParts)) {
                    $missingParts = true;
                    break;
                }
            }

            if ($missingParts) {
                die("Error: Missing parts of the .7z archive. Ensure all parts are uploaded.");
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO messages (user_id, message, image_path) VALUES (?, ?, ?)");
    if (empty($uploadedFiles)) {
        $stmt->execute([$_SESSION['user_id'], $message, null]);
    } else {
        foreach ($uploadedFiles as $filePath) {
            $stmt->execute([$_SESSION['user_id'], $message, $filePath]);
        }
    }
}

// Get active users
$activeUsers = $db->query("SELECT username FROM users WHERE last_active > (NOW() - INTERVAL 5 MINUTE)")->fetchAll(PDO::FETCH_COLUMN);

// Get messages
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
                                <img src="<?= htmlspecialchars('uploads/thumbnails/' . basename($row['image_path'])) ?>" alt="Image Thumbnail" onclick="openModal('<?= htmlspecialchars($row['image_path']) ?>')">
                                <a href="<?= htmlspecialchars($row['image_path']) ?>" download class="download-icon">Download</a>
                            <?php elseif (in_array($fileExtension, ['zip', '7z'])): ?>
                                <a href="<?= htmlspecialchars($row['image_path']) ?>" download class="download-icon">Download <?= htmlspecialchars(basename($row['image_path'])) ?></a>
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
    <div id="imageModal" class="modal" onclick="closeModal()">
        <img id="modal-image" src="" alt="Image">
    </div>

    <script>
        setInterval(function () {
            fetch(location.href)
                .then(response => response.text())
                .then(data => {
                    const chatBox = document.getElementById("chat-box");
                    const newChatBox = new DOMParser().parseFromString(data, 'text/html').getElementById("chat-box");
                    chatBox.innerHTML = newChatBox.innerHTML;
                });
        }, 3000);

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
