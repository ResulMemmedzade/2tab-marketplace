<?php

require_once "config.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("mybooks.php");
}

verifyCsrfToken($_POST['csrf_token'] ?? null);

$id = (int)($_POST["id"] ?? 0);
$user_id = currentUserId();

if ($id > 0 && $user_id !== null) {
    try {
        $stmt = $pdo->prepare("SELECT image FROM books WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $book = $stmt->fetch();

        if ($book) {
            if (!empty($book["image"])) {
                $imageName = basename((string)$book["image"]);
                $imagePath = rtrim(UPLOAD_STORAGE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $imageName;

                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM books WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            appLog('book_deleted', 'Book deleted by owner', [
                'book_id' => $id,
                'user_id' => $user_id,
            ]);
        } else {
            appLog('suspicious_activity', 'Delete attempt on non-owned or missing book', [
                'book_id' => $id,
                'user_id' => $user_id,
            ]);
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());

        appLog('system_error', 'DB error during book delete', [
            'book_id' => $id,
            'user_id' => $user_id,
            'error' => $e->getMessage(),
        ]);
    }
}

redirectTo("mybooks.php");