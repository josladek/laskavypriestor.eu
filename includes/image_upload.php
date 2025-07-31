<?php
/**
 * Image upload utility functions
 */

if (!function_exists('uploadImage')) {
function uploadImage($file, $uploadDir = 'uploads/', $maxSize = 5 * 1024 * 1024) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Chyba pri nahrávaní súboru.'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Súbor je príliš veľký. Maximálna veľkosť je 5MB.'];
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Nepodporovaný typ súboru. Povolené sú: JPG, PNG, GIF, WEBP.'];
    }
    
    // Create upload directory if it doesn't exist
    $fullUploadDir = __DIR__ . '/../' . $uploadDir;
    if (!is_dir($fullUploadDir)) {
        if (!mkdir($fullUploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Nepodarilo sa vytvoriť adresár pre nahrávanie.'];
        }
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $fullUploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Resize image if needed
        $resizedPath = resizeImage($targetPath, 800, 600);
        if ($resizedPath) {
            // Remove original if resize was successful and different
            if ($resizedPath !== $targetPath) {
                unlink($targetPath);
                $filename = basename($resizedPath);
            }
        }
        
        return [
            'success' => true, 
            'filename' => $filename,
            'url' => $uploadDir . $filename
        ];
    } else {
        return ['success' => false, 'error' => 'Nepodarilo sa nahrať súbor.'];
    }
}
}

if (!function_exists('resizeImage')) {
function resizeImage($sourcePath, $maxWidth = 800, $maxHeight = 600, $quality = 85) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    
    // Don't upscale images
    if ($ratio >= 1) {
        return $sourcePath;
    }
    
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    // Create source image
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
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
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Generate output filename
    $pathInfo = pathinfo($sourcePath);
    $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_resized.' . $pathInfo['extension'];
    
    // Save resized image
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($newImage, $outputPath, $quality);
            break;
        case 'image/png':
            $result = imagepng($newImage, $outputPath, 9);
            break;
        case 'image/gif':
            $result = imagegif($newImage, $outputPath);
            break;
        case 'image/webp':
            $result = imagewebp($newImage, $outputPath, $quality);
            break;
    }
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result ? $outputPath : false;
}
}

if (!function_exists('deleteImage')) {
function deleteImage($filename, $uploadDir = 'uploads/') {
    if (empty($filename)) {
        return true;
    }
    
    $filePath = __DIR__ . '/../' . $uploadDir . $filename;
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return true;
}
}

if (!function_exists('getImageUrl')) {
function getImageUrl($filename, $uploadDir = 'uploads/') {
    if (empty($filename)) {
        return null;
    }
    
    return url($uploadDir . $filename);
}
}

/**
 * Handle image upload with proper validation and resizing
 */
if (!function_exists('handleImageUpload')) {
function handleImageUpload($file, $subfolder = '') {
    $uploadDir = 'uploads/';
    if (!empty($subfolder)) {
        $uploadDir .= $subfolder . '/';
    }
    
    return uploadImage($file, $uploadDir);
}
}
?>