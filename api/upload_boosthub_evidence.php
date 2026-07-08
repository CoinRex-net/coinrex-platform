<?php
/**
 * Upload BoostHub Evidence Screenshot
 * POST: multipart/form-data with 'screenshot' file
 * Returns the URL of the uploaded file.
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null);

    if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES['screenshot']) ? $_FILES['screenshot']['error'] : 'no_file';
        throw new RuntimeException('Screenshot upload failed. Error code: ' . $error_code);
    }

    $file = $_FILES['screenshot'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types, true)) {
        throw new RuntimeException('Invalid file type. Allowed: JPG, PNG, GIF, WebP.');
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new RuntimeException('File too large. Maximum size is 5MB.');
    }

    // Generate unique filename
    $ext = match ($mime_type) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $filename = 'boosthub_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $upload_dir = dirname(__DIR__) . '/uploads/boosthub/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $dest_path = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    // Build the URL
    $base_url = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
    $file_url = $base_url . '/uploads/boosthub/' . $filename;

    apiSuccessResponse([
        'url' => $file_url,
        'filename' => $filename,
        'message' => 'Screenshot uploaded successfully.',
    ]);

} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
