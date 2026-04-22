<?php

require_once "config.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("books.php");
}

verifyCsrfToken($_POST['csrf_token'] ?? null);

$user_id = currentUserId();
$book_id = (int) ($_POST["book_id"] ?? 0);

if ($user_id === null || $book_id <= 0) {
    redirectTo("books.php");
}

try {
    $bookStmt = $pdo->prepare("
        SELECT id, user_id, status, is_deleted
        FROM books
        WHERE id = ?
        LIMIT 1
    ");
    $bookStmt->execute([$book_id]);
    $book = $bookStmt->fetch();

    if (!$book || (int)($book['is_deleted'] ?? 0) === 1) {
        appLog('favorite_action', 'Favorite toggle attempted on missing/deleted book', [
            'user_id' => $user_id,
            'book_id' => $book_id,
        ]);
    } elseif (($book['status'] ?? 'active') !== 'active' && (int)($book['user_id'] ?? 0) !== $user_id) {
        appLog('favorite_action', 'Favorite toggle attempted on non-public book', [
            'user_id' => $user_id,
            'book_id' => $book_id,
            'status' => $book['status'] ?? null,
        ]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND book_id = ? LIMIT 1");
        $stmt->execute([$user_id, $book_id]);
        $favorite = $stmt->fetch();

        if ($favorite) {
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);

            appLog('favorite_removed', 'Book removed from favorites', [
                'user_id' => $user_id,
                'book_id' => $book_id,
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, book_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $book_id]);

            appLog('favorite_added', 'Book added to favorites', [
                'user_id' => $user_id,
                'book_id' => $book_id,
            ]);
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Favorite toggle DB error', [
        'user_id' => $user_id,
        'book_id' => $book_id,
        'error' => $e->getMessage(),
    ]);
}

$redirect = $_SERVER["HTTP_REFERER"] ?? basePath("books.php");

if (!is_string($redirect) || $redirect === '' || strpos($redirect, APP_BASE_URL . '/') === false) {
    $redirect = basePath("books.php");
}

redirect($redirect);