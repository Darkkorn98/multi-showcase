<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error.log');

if (!isset($showcaseFolder)) {
    header('Content-Type: text/plain');
    echo "Showcase folder not set.";
    http_response_code(500);
    exit;
}

$showcaseFolder = rtrim($showcaseFolder, DIRECTORY_SEPARATOR);

// Load config
$configPath = $showcaseFolder . DIRECTORY_SEPARATOR . 'settings.php';
if (!file_exists($configPath)) {
    header('Content-Type: text/plain');
    echo "Settings file missing in showcase folder.";
    http_response_code(500);
    exit;
}
$config = require $configPath;

// Setup cache dir
$cacheDir = $showcaseFolder . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Config vars with defaults
$targetWidth    = $config['output_width'] ?? 400;
$targetHeight   = $config['output_height'] ?? 400;
$cacheTTL       = $config['cache_ttl'] ?? 3600;
$fontFile       = $config['font_path'] ?? (__DIR__ . '/fonts/OpenSans-Regular.ttf');
$fontSize       = $config['font_size'] ?? 16;
$padding        = $config['text_padding'] ?? 10;
$angle          = $config['text_angle'] ?? 0;
$fontColorRGB   = $config['font_color'] ?? [255, 255, 255];
$shadowColorRGB = $config['shadow_color'] ?? [0, 0, 0];
$fallbackImageConfig = $config['fallback_image'] ?? 'fallback.png';
$fallbackCredit = $config['fallback_credit'] ?? '';
$delimiter      = $config['delimiter'] ?? '__';
$textPosition   = strtolower($config['text_position'] ?? 'bottom-left');
$useArtistPrefix = $config['use_artist_prefix'] ?? true; // true = artist__image, false = image__artist

// Handle image folder path (absolute or relative)
$imgFolderConfig = $config['image_folder'] ?? 'images';

if (
    str_starts_with($imgFolderConfig, '/') ||            // Unix absolute path
    preg_match('/^[A-Za-z]:\\\\/', $imgFolderConfig)     // Windows drive letter path
) {
    $imageFolder = rtrim($imgFolderConfig, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
} else {
    $imageFolder = rtrim($showcaseFolder . DIRECTORY_SEPARATOR . $imgFolderConfig, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

// Detect if fallback image path is absolute or relative
if (
    str_starts_with($fallbackImageConfig, '/') ||            // Unix absolute path
    preg_match('/^[A-Za-z]:\\\\/', $fallbackImageConfig)     // Windows drive letter path
) {
    $fallbackImage = $fallbackImageConfig;
} else {
    $fallbackImage = $showcaseFolder . DIRECTORY_SEPARATOR . $fallbackImageConfig;
}

// Function to extract artist credit from filename
function extractCredit(string $filename, string $delimiter, bool $usePrefix = true): string {
    if (str_contains($filename, $delimiter)) {
        $parts = explode($delimiter, $filename, 2);
        if ($usePrefix) {
            $artist = trim($parts[0]);
        } else {
            $artist = trim($parts[1]);
        }
        return $artist !== '' ? $artist : '';
    }
    return '';
}

// Verify fallback image exists
if (!file_exists($fallbackImage)) {
    $possiblePath = realpath($fallbackImage);
    if ($possiblePath && file_exists($possiblePath)) {
        $fallbackImage = $possiblePath;
    } else {
        error_log("Fallback image file not found: $fallbackImage");
        header("Content-Type: text/plain");
        echo "Fallback image missing.";
        http_response_code(500);
        exit;
    }
}

// Extract fallback credit if empty
if (empty($fallbackCredit)) {
    $fallbackCredit = extractCredit(basename($fallbackImage), $delimiter, $useArtistPrefix);
}

// Allowed image extensions
$allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

// Scan image folder for images
$images = [];
if (is_dir($imageFolder)) {
    foreach (scandir($imageFolder) as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions, true) && $file !== basename($fallbackImage)) {
            $fullPath = $imageFolder . $file;
            if (file_exists($fullPath)) {
                $credit = extractCredit($file, $delimiter, $useArtistPrefix);
                $images[$fullPath] = $credit;
            }
        }
    }
} else {
    error_log("Image directory does not exist: $imageFolder");
}

// Pick a random image or fallback
if (empty($images)) {
    error_log("No images found in $imageFolder. Using fallback.");
    $imagePath = $fallbackImage;
    $credit = $fallbackCredit;
} else {
    $keys = array_keys($images);
    $imagePath = $keys[array_rand($keys)];
    $credit = $images[$imagePath];
}

// GIF passthrough
if (str_ends_with(strtolower($imagePath), '.gif')) {
    $imageData = @file_get_contents($imagePath);
    if (!$imageData) {
        error_log("Failed to fetch GIF: $imagePath");
        header("Content-Type: text/plain");
        echo "Failed to fetch image.";
        http_response_code(500);
        exit;
    }
    header("Content-Type: image/gif");
    echo $imageData;
    exit;
}

// Cache key and file
$cacheKey = md5($imagePath . $credit . $targetWidth . $targetHeight . $fontSize . $angle . implode(',', $fontColorRGB) . implode(',', $shadowColorRGB));
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.png';

// Serve cached if valid
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) <= $cacheTTL) {
    header('Content-Type: image/png');
    readfile($cacheFile);
    exit;
}

// Read image data (try fallback if fail)
$imageData = @file_get_contents($imagePath);
if (!$imageData && $imagePath !== $fallbackImage) {
    error_log("Failed to read image: $imagePath. Trying fallback.");
    $imagePath = $fallbackImage;
    $credit = $fallbackCredit;
    $imageData = @file_get_contents($fallbackImage);
}

if (!$imageData) {
    error_log("No valid image data found.");
    header("Content-Type: text/plain");
    echo "No valid image to display.";
    http_response_code(500);
    exit;
}

// Create image resource from string
$sourceImage = @imagecreatefromstring($imageData);
if (!$sourceImage) {
    error_log("Invalid image data for $imagePath");
    header("Content-Type: text/plain");
    echo "Failed to process image.";
    http_response_code(500);
    exit;
}

// Resize preserving aspect ratio
$srcW = imagesx($sourceImage);
$srcH = imagesy($sourceImage);
$scale = min($targetWidth / $srcW, $targetHeight / $srcH);
$newW = (int)($srcW * $scale);
$newH = (int)($srcH * $scale);

// Create transparent canvas
$resized = imagecreatetruecolor($targetWidth, $targetHeight);
imagesavealpha($resized, true);
$transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
imagefill($resized, 0, 0, $transparent);
imagealphablending($resized, true);

// Center resized image on canvas
$dstX = (int)(($targetWidth - $newW) / 2);
$dstY = (int)(($targetHeight - $newH) / 2);
imagecopyresampled($resized, $sourceImage, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

// Allocate colors for text
$textColor = imagecolorallocate($resized, ...$fontColorRGB);
$shadowColor = imagecolorallocate($resized, ...$shadowColorRGB);

// Calculate text bounding box
$creditToDraw = $credit ?: '';
if (!empty($creditToDraw) && file_exists($fontFile) && function_exists('imagettftext')) {
    $bbox = imagettfbbox($fontSize, $angle, $fontFile, $creditToDraw);
    $textWidth = abs($bbox[2] - $bbox[0]);
    $textHeight = abs($bbox[7] - $bbox[1]);

    switch ($textPosition) {
        case 'top-left':
            $textX = $padding;
            $textY = $padding + $textHeight;
            break;
        case 'top-right':
            $textX = $targetWidth - $padding - $textWidth;
            $textY = $padding + $textHeight;
            break;
        case 'center':
            $textX = (int)(($targetWidth - $textWidth) / 2);
            $textY = (int)(($targetHeight + $textHeight) / 2);
            break;
        case 'bottom-left':
            $textX = $padding;
            $textY = $targetHeight - $padding;
            break;
        case 'bottom-right':
            $textX = $targetWidth - $padding - $textWidth;
            $textY = $targetHeight - $padding;
            break;
        default:
            $textX = $padding;
            $textY = $targetHeight - $padding;
            break;
    }

    // Draw shadow first then text
    imagettftext($resized, $fontSize, $angle, $textX + 1, $textY + 1, $shadowColor, $fontFile, $creditToDraw);
    imagettftext($resized, $fontSize, $angle, $textX, $textY, $textColor, $fontFile, $creditToDraw);

} else {
    // Fallback built-in font
    $fontHeight = 5;
    $textX = $padding;
    $textY = $targetHeight - $padding - imagefontheight($fontHeight);
    imagestring($resized, $fontHeight, $textX + 1, $textY + 1, $creditToDraw, $shadowColor);
    imagestring($resized, $fontHeight, $textX, $textY, $creditToDraw, $textColor);
}

// Save cache file
imagepng($resized, $cacheFile);

// Output image
header('Content-Type: image/png');
imagepng($resized);

imagedestroy($sourceImage);
imagedestroy($resized);
exit;
