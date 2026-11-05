<?php
declare(strict_types=1);

function management_media_directory(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
}

function management_media_path(string $name): ?string
{
    if (!preg_match('/\A[a-f0-9]{32,64}\.(?:png|jpg|jpeg|webp)\z/', $name)) {
        return null;
    }
    return management_media_directory() . DIRECTORY_SEPARATOR . $name;
}

function &management_media_state(): array
{
    static $state = ['created' => [], 'retired' => []];
    return $state;
}

function management_media_upload(array $file, string $field): string
{
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new ManagementError('Nie udało się przesłać obrazu.', 422, $field);
    }
    if (!extension_loaded('gd') || !extension_loaded('fileinfo')) {
        throw new ManagementError('Serwer nie obsługuje przetwarzania obrazów.', 503, $field);
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new ManagementError('Obraz może mieć najwyżej 5 MB.', 422, $field);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || ($info['mime'] ?? null) !== $mime
        || ($mime === 'image/webp' && (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')))
        || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8000000) {
        throw new ManagementError('Wybierz PNG, JPEG lub WebP: maksymalnie 4096 × 4096 i 8 mln pikseli.', 422, $field);
    }
    $memoryLimit = trim((string) ini_get('memory_limit'));
    if ($memoryLimit !== '-1') {
        $unit = strtolower(substr($memoryLimit, -1));
        $factor = match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
        $available = (int) $memoryLimit * $factor - memory_get_usage(true);
        if ($available < $info[0] * $info[1] * 12 + 16 * 1024 * 1024) {
            throw new ManagementError('Obraz jest zbyt duży do przetworzenia. Zmniejsz jego wymiary.', 422, $field);
        }
    }
    $source = match ($mime) {
        'image/png' => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        default => @imagecreatefromjpeg($file['tmp_name']),
    };
    if (!$source) {
        throw new ManagementError('Nie można odczytać obrazu.', 422, $field);
    }
    $canvas = null;
    $handle = null;
    $path = null;
    try {
        $canvas = imagecreatetruecolor($info[0], $info[1]);
        if (!$canvas) {
            throw new ManagementError('Nie można przetworzyć obrazu.', 503, $field);
        }
        if ($mime === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }
        if (!imagecopy($canvas, $source, 0, 0, 0, 0, $info[0], $info[1])) {
            throw new ManagementError('Nie można przetworzyć obrazu.', 503, $field);
        }
        $directory = management_media_directory();
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ManagementError('Magazyn obrazów jest niedostępny.', 503, $field);
        }
        if (is_link($directory) || is_link(dirname($directory))) {
            throw new ManagementError('Nieprawidłowa konfiguracja magazynu obrazów.', 503, $field);
        }
        $extension = match ($mime) { 'image/png' => '.png', 'image/webp' => '.webp', default => '.jpg' };
        $name = bin2hex(random_bytes(24)) . $extension;
        $path = management_media_path($name);
        $handle = @fopen($path, 'xb');
        if (!$handle) {
            throw new ManagementError('Nie można zapisać obrazu.', 503, $field);
        }
        $state =& management_media_state();
        $state['created'][$name] = true;
        $success = match ($mime) {
            'image/png' => imagepng($canvas, $handle, 6),
            'image/webp' => imagewebp($canvas, $handle, 86),
            default => imagejpeg($canvas, $handle, 88),
        };
        if (!$success || !fflush($handle)) {
            throw new ManagementError('Nie można zapisać obrazu.', 503, $field);
        }
        return $name;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($canvas instanceof GdImage) {
            imagedestroy($canvas);
        }
        imagedestroy($source);
    }
}

function management_media_retire(string $name): void
{
    if (management_media_path($name) !== null) {
        $state =& management_media_state();
        $state['retired'][$name] = true;
    }
}

function management_media_cleanup(array $names): void
{
    foreach (array_keys($names) as $name) {
        $path = management_media_path($name);
        if ($path !== null && !is_link(management_media_directory()) && !is_link(dirname(management_media_directory())) && is_file($path) && !is_link($path) && !@unlink($path)) {
            error_log('Management media cleanup failed for generated asset.');
        }
    }
}

/** Call only after a successful database commit. */
function management_media_commit(): void
{
    $state =& management_media_state();
    management_media_cleanup($state['retired']);
    $state = ['created' => [], 'retired' => []];
}

/** Call after every database rollback, including validation and audit failures. */
function management_media_rollback(): void
{
    $state =& management_media_state();
    management_media_cleanup($state['created']);
    $state = ['created' => [], 'retired' => []];
}
