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

    if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
        return [false, "Şəkil serverə yüklənə bilmədi.", null];
    }

    chmod($targetPath, 0644);

    return [true, "", $safeFileName];
}