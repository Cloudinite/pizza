<?php
defined('PS_APP') || exit;

/**
 * Stores an uploaded menu photo as a 480×480 centre-cropped WebP (JPEG fallback) in /uploads.
 * Re-encoding with GD strips metadata and anything that is not a real image.
 * @return array{0: ?string, 1: string} [file name or null, error message]
 */
function image_store_upload(array $file, string $prefix): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'Obrázok sa nepodarilo nahrať (max. ' . ini_get('upload_max_filesize') . ').'];
    }
    if ($file['size'] > 12 * 1024 * 1024) {
        return [null, 'Obrázok je príliš veľký (max. 12 MB).'];
    }
    if (!function_exists('imagecreatetruecolor')) {
        return [null, 'Server nepodporuje úpravu obrázkov (chýba PHP rozšírenie GD).'];
    }
    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? 0;
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        IMAGETYPE_GIF => @imagecreatefromgif($file['tmp_name']),
        default => false,
    };
    if (!$src) {
        return [null, 'Podporované sú obrázky JPG, PNG alebo WebP.'];
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) {
            $src = imagerotate($src, $rot, 0);
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $size = 480;
    $dst = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
    imagedestroy($src);

    $dir = PS_PUBLIC . '/uploads';
    $name = $prefix . '-' . bin2hex(random_bytes(5));
    if (function_exists('imagewebp')) {
        $name .= '.webp';
        $ok = imagewebp($dst, "$dir/$name", 82);
    } else {
        $name .= '.jpg';
        $ok = imagejpeg($dst, "$dir/$name", 84);
    }
    imagedestroy($dst);
    if (!$ok) {
        return [null, 'Obrázok sa nepodarilo uložiť – skontrolujte práva zápisu do priečinka uploads.'];
    }
    return [$name, ''];
}

function image_delete(?string $name): void
{
    if ($name && preg_match('/^[a-z0-9-]+\.(webp|jpg)$/', $name)) {
        @unlink(PS_PUBLIC . '/uploads/' . $name);
    }
}
