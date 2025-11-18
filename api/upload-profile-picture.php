<?php
/**
 * Profile Picture Upload API
 * Handles profile picture file uploads
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for profile picture upload']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Verify user exists in database
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userExists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found in database']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// Ensure profile_picture column exists
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() === 0) {
        // Add profile_picture column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER full_name");
    }
} catch (Exception $e) {
    // Column might already exist, continue
    error_log('Profile picture column check: ' . $e->getMessage());
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred');
        }

        $file = $_FILES['profile_picture'];

        // Validate file type (only images)
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds maximum limit of 5MB');
        }

        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Get old profile picture to delete it later
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldPicturePath = $oldProfile ? $oldProfile['profile_picture'] : null;

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;
        $relativePath = 'uploads/profile_pictures/' . $fileName;

        // Check if GD extension is available for image processing
        if (extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
            // Resize and optimize image using GD
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $targetSize = 300; // Target size for profile pictures

            // Create image resource based on type
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'image/webp':
                    $sourceImage = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : null;
                    if (!$sourceImage) {
                        // Fallback: save original file if WebP is not supported
                        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                            throw new Exception('Failed to save uploaded file');
                        }
                        break;
                    }
                    break;
                default:
                    throw new Exception('Unsupported image type');
            }

            if (isset($sourceImage) && $sourceImage) {
                // Calculate new dimensions maintaining aspect ratio
                if ($width > $height) {
                    $newWidth = $targetSize;
                    $newHeight = intval($height * ($targetSize / $width));
                } else {
                    $newHeight = $targetSize;
                    $newWidth = intval($width * ($targetSize / $height));
                }

                // Create new image
                $newImage = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve transparency for PNG and GIF
                if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                }

                // Resize image
                imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                // Save resized image
                switch ($mimeType) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        imagejpeg($newImage, $filePath, 85);
                        break;
                    case 'image/png':
                        imagepng($newImage, $filePath, 6);
                        break;
                    case 'image/gif':
                        imagegif($newImage, $filePath);
                        break;
                    case 'image/webp':
                        if (function_exists('imagewebp')) {
                            imagewebp($newImage, $filePath, 85);
                        } else {
                            imagejpeg($newImage, $filePath, 85);
                        }
                        break;
                }

                // Free memory
                imagedestroy($sourceImage);
                imagedestroy($newImage);
            }
        } else {
            // Fallback: save original file if GD is not available
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
        }

        // Update database - Use transaction to ensure data integrity
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$relativePath, $userId]);
            
            // Verify the update was successful
            if ($stmt->rowCount() === 0) {
                throw new Exception('Failed to update profile picture in database. User may not exist.');
            }
            
            // Verify the data was actually saved by querying it back
            $verifyStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $verifyStmt->execute([$userId]);
            $verified = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verified || $verified['profile_picture'] !== $relativePath) {
                throw new Exception('Database update verification failed. Profile picture was not saved correctly.');
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Delete old profile picture if it exists (only after successful database update)
            if ($oldPicturePath && file_exists(__DIR__ . '/../' . $oldPicturePath)) {
                @unlink(__DIR__ . '/../' . $oldPicturePath);
            }

            // Update session only after successful database save
            $_SESSION['user']['profile_picture'] = $relativePath;
            
            // Log successful upload
            if (function_exists('logActivity')) {
                logActivity("Profile picture uploaded successfully for user ID: {$userId}", 'INFO');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Profile picture uploaded and saved successfully',
                'profile_picture' => $relativePath,
                'profile_picture_url' => $relativePath // Return relative path for frontend
            ]);
            
        } catch (Exception $dbException) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            // Delete the uploaded file if database update failed
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            
            throw $dbException;
        }

    } catch (Exception $e) {
        http_response_code(400);
        
        // Log the error for debugging
        error_log('Profile picture upload error for user ' . $userId . ': ' . $e->getMessage());
        if (function_exists('logActivity')) {
            logActivity("Profile picture upload failed for user ID: {$userId} - " . $e->getMessage(), 'ERROR');
        }
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

