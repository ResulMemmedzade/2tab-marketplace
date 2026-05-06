<?php

declare(strict_types=1);

function validateUploadedImage(array $file, int $maxSizeBytes = 10485760): array
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, "Şəkil seçilməyib.", null, null];
    }

    if ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) {
        return [false, "Şəkil çox böyükdür. Maksimum ölçü 10 MB ola bilər.", null, null];
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        return [false, "Şəkil yüklənərkən xəta baş verdi.", null, null];
    }

    if (($file["size"] ?? 0) <= 0 || ($file["size"] ?? 0) > $maxSizeBytes) {
        return [false, "Şəkilin ölçüsü maksimum 10 MB ola bilər.", null, null];
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        return [false, "Yanlış upload sorğusu.", null, null];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file["tmp_name"]);

    $allowedMimeToExt = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
    ];

    if (!isset($allowedMimeToExt[$mimeType])) {
        return [false, "Yalnız jpg, png və webp şəkillər qəbul olunur.", null, null];
    }

    $imageInfo = getimagesize($file["tmp_name"]);
    if ($imageInfo === false) {
        return [false, "Bu fayl real şəkil deyil.", null, null];
    }

    if (($imageInfo["mime"] ?? "") !== $mimeType) {
        return [false, "Şəkil tipi uyğun deyil.", null, null];
    }

    return [true, "", $mimeType, $allowedMimeToExt[$mimeType]];
}

function createImageResource(string $path, string $mimeType)
{
    if ($mimeType === "image/jpeg") {
        return imagecreatefromjpeg($path);
    }

    if ($mimeType === "image/png") {
        return imagecreatefrompng($path);
    }

    if ($mimeType === "image/webp") {
        return imagecreatefromwebp($path);
    }

    return false;
}

function resizeImageResource($sourceImage, int $sourceWidth, int $sourceHeight, int $maxWidth = 1600, int $maxHeight = 1600)
{
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        return false;
    }

    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);

    $targetWidth = (int)round($sourceWidth * $ratio);
    $targetHeight = (int)round($sourceHeight * $ratio);

    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);

    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);

    imagecopyresampled(
        $targetImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    return $targetImage;
}

function saveCompressedImage(string $sourcePath, string $targetPath, string $mimeType): bool
{
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }

    $sourceWidth = (int)$imageInfo[0];
    $sourceHeight = (int)$imageInfo[1];

    $sourceImage = createImageResource($sourcePath, $mimeType);
    if ($sourceImage === false) {
        return false;
    }

    $targetImage = resizeImageResource($sourceImage, $sourceWidth, $sourceHeight);

    if ($targetImage === false) {
        imagedestroy($sourceImage);
        return false;
    }

    $saved = false;

    if ($mimeType === "image/jpeg") {
        $saved = imagejpeg($targetImage, $targetPath, 78);
    } elseif ($mimeType === "image/png") {
        $saved = imagepng($targetImage, $targetPath, 7);
    } elseif ($mimeType === "image/webp") {
        $saved = imagewebp($targetImage, $targetPath, 78);
    }

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    return $saved;
}

function saveUploadedImage(array $file, string $uploadDir, int $maxSizeBytes = 10485760): array
{
    [$ok, $message, $mimeType, $extension] = validateUploadedImage($file, $maxSizeBytes);

    if (!$ok) {
        return [false, $message, null];
    }

    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeFileName = bin2hex(random_bytes(16)) . "." . $extension;
    $targetPath = $uploadDir . $safeFileName;

    $compressed = saveCompressedImage($file["tmp_name"], $targetPath, $mimeType);

    if (!$compressed) {
        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
            return [false, "Şəkil serverə yüklənə bilmədi.", null];
        }
    }

    chmod($targetPath, 0644);

    return [true, "", $safeFileName];
}