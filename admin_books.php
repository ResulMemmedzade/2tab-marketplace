<?php

require_once "config.php";

requireAdmin();
ensureCsrfToken();

$currentAdminId = currentUserId();

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "all");

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        verifyCsrfToken($_POST["csrf_token"] ?? null);

        if (isset($_POST["hide_id"])) {
            $bookId = (int) $_POST["hide_id"];

            if ($bookId > 0) {
                try {
                    $oldStmt = $pdo->prepare("SELECT id, title, status, seller_id, is_deleted FROM books WHERE id = ?");
                    $oldStmt->execute([$bookId]);
                    $targetBook = $oldStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE books SET status = 'hidden' WHERE id = ?");
                    $stmt->execute([$bookId]);

                    flashSuccess('Kitab gizlədildi.');

                    appLog('admin_action', 'Admin hid a book', [
                        'admin_id' => $currentAdminId,
                        'action' => 'hide_book',
                        'book_id' => $bookId,
                        'book_title' => $targetBook['title'] ?? null,
                        'old_status' => $targetBook['status'] ?? null,
                        'new_status' => 'hidden',
                        'seller_id' => $targetBook['seller_id'] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    appLog('system_error', 'Failed to hide book', [
                        'admin_id' => $currentAdminId,
                        'book_id' => $bookId,
                        'error' => $e->getMessage(),
                    ]);

                    flashError('Kitabı gizlətmək mümkün olmadı.');
                }
            }

            redirectTo("admin_books.php");
        }

        if (isset($_POST["activate_id"])) {
            $bookId = (int) $_POST["activate_id"];

            if ($bookId > 0) {
                try {
                    $oldStmt = $pdo->prepare("SELECT id, title, status, seller_id, is_deleted FROM books WHERE id = ?");
                    $oldStmt->execute([$bookId]);
                    $targetBook = $oldStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE books SET status = 'active' WHERE id = ?");
                    $stmt->execute([$bookId]);

                    flashSuccess('Kitab aktiv edildi.');

                    appLog('admin_action', 'Admin activated a book', [
                        'admin_id' => $currentAdminId,
                        'action' => 'activate_book',
                        'book_id' => $bookId,
                        'book_title' => $targetBook['title'] ?? null,
                        'old_status' => $targetBook['status'] ?? null,
                        'new_status' => 'active',
                        'seller_id' => $targetBook['seller_id'] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    flashError('Kitabı aktiv etmək mümkün olmadı.');
                }
            }

            redirectTo("admin_books.php");
        }

        if (isset($_POST["delete_id"])) {
            $bookId = (int) $_POST["delete_id"];

            if ($bookId > 0) {
                try {
                    $oldStmt = $pdo->prepare("SELECT id, title, status, seller_id, is_deleted FROM books WHERE id = ?");
                    $oldStmt->execute([$bookId]);
                    $targetBook = $oldStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE books SET is_deleted = 1 WHERE id = ?");
                    $stmt->execute([$bookId]);

                    flashSuccess('Kitab silinmiş kimi işarələndi.');

                    appLog('admin_action', 'Admin soft deleted a book', [
                        'admin_id' => $currentAdminId,
                        'action' => 'soft_delete',
                        'book_id' => $bookId,
                        'book_title' => $targetBook['title'] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    flashError('Kitabı silmək mümkün olmadı.');
                }
            }

            redirectTo("admin_books.php");
        }

        if (isset($_POST["restore_id"])) {
            $bookId = (int) $_POST["restore_id"];

            if ($bookId > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE books SET is_deleted = 0 WHERE id = ?");
                    $stmt->execute([$bookId]);

                    flashSuccess('Kitab bərpa edildi.');

                    appLog('admin_action', 'Admin restored book', [
                        'admin_id' => $currentAdminId,
                        'book_id' => $bookId,
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    flashError('Kitabı bərpa etmək mümkün olmadı.');
                }
            }

            redirectTo("admin_books.php");
        }
    }

    $sql = "
        SELECT books.*, users.name AS seller_name
        FROM books
        LEFT JOIN users ON books.seller_id = users.id
        WHERE 1=1
    ";
    $params = [];

    if ($search !== "") {
        $sql .= " AND (books.title LIKE ? OR books.author LIKE ? OR users.name LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
    }

    if ($statusFilter === "deleted") {
        $sql .= " AND books.is_deleted = 1";
    } elseif (in_array($statusFilter, ["active","hidden","sold"], true)) {
        $sql .= " AND books.status = ? AND books.is_deleted = 0";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY books.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $books = [];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Admin Kitablar</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .container {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 32px;
            color: #0f172a;
        }

        .page-title p {
            margin: 0;
            color: #64748b;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 16px;
            text-decoration: none;
            background: #e2e8f0;
            color: #1e293b;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 700;
        }

        .btn-back:hover {
            background: #cbd5e1;
        }

        .admin-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filters-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            margin-bottom: 20px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 12px;
            align-items: center;
        }

        input,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            background: #fff;
            color: #0f172a;
        }

        input:focus,
        select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .flash-success {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
            border: 1px solid #bbf7d0;
        }

        .flash-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
            border: 1px solid #fecaca;
        }

        .table-wrap {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            color: #0f172a;
        }

        tbody tr {
            transition: 0.15s ease;
        }

        tbody tr:hover {
            background: #f1f5f9;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-hidden {
            background: #fef3c7;
            color: #92400e;
        }

        .status-sold {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-default {
            background: #e2e8f0;
            color: #334155;
        }

        .deleted-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: #fee2e2;
            color: #b91c1c;
            margin-top: 6px;
        }

        .inline-form {
            display: inline;
        }

        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.15s ease;
            white-space: nowrap;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
        }

        .btn-light {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-light:hover {
            background: #cbd5e1;
        }

        .btn-action {
            display: inline-block;
            border: none;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-hide {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-activate {
            background: #dcfce7;
            color: #166534;
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-restore {
            background: #dbeafe;
            color: #1d4ed8;
        }

        @media (max-width: 950px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/admin_topbar.php'; ?>

    <div class="container">
        <a href="<?= e(basePath('admin.php')) ?>" class="btn-back">← Geri qayıt</a>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-success">
                <?= e($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="flash-error">
                <?= e($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="page-title">
            <h1>Bütün kitablar</h1>
            <p>Buradan kitab elanlarını idarə edə, axtara və bərpa edə bilərsən.</p>
        </div>

        <div class="admin-actions">
            <a href="<?= e(basePath('admin.php')) ?>" class="btn btn-light">Dashboard</a>
            <a href="<?= e(basePath('admin_books.php')) ?>" class="btn btn-primary">Kitabları idarə et</a>
            <a href="<?= e(basePath('admin_users.php')) ?>" class="btn btn-light">İstifadəçilər</a>
        </div>

        <div class="filters-card">
            <form method="GET" class="filter-grid">
                <input
                    type="text"
                    name="search"
                    placeholder="Kitab, müəllif və ya satıcı adı üzrə axtar..."
                    value="<?= e($search) ?>"
                >

                <select name="status">
                    <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>Hamısı</option>
                    <option value="active" <?php echo $statusFilter === "active" ? "selected" : ""; ?>>Aktiv</option>
                    <option value="hidden" <?php echo $statusFilter === "hidden" ? "selected" : ""; ?>>Gizli</option>
                    <option value="sold" <?php echo $statusFilter === "sold" ? "selected" : ""; ?>>Satılıb</option>
                    <option value="deleted" <?php echo $statusFilter === "deleted" ? "selected" : ""; ?>>Silinmiş</option>
                </select>

                <button type="submit" class="btn btn-primary">Axtar</button>
                <a href="<?= e(basePath('admin_books.php')) ?>" class="btn btn-light">Təmizlə</a>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kitab</th>
                        <th>Müəllif</th>
                        <th>Qiymət</th>
                        <th>Satıcı</th>
                        <th>Status</th>
                        <th>Əməliyyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <?php
                        $status = $book["status"] ?? "active";
                        $statusClass = in_array($status, ["active", "hidden", "sold"], true) ? "status-" . $status : "status-default";
                        $isDeleted = (int)($book["is_deleted"] ?? 0) === 1;
                        ?>
                        <tr>
                            <td><?php echo (int)$book["id"]; ?></td>
                            <td>
                                <?= e($book["title"]) ?>
                                <?php if ($isDeleted): ?>
                                    <div class="deleted-badge">Silinmiş</div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($book["author"]) ?></td>
                            <td><?= e($book["price"]) ?> AZN</td>
                            <td><?= e($book["seller_name"] ?? "Naməlum") ?></td>
                            <td>
                                <span class="status <?php echo $statusClass; ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <?php if ($isDeleted): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Bu kitab bərpa edilsin?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="restore_id" value="<?php echo (int)$book["id"]; ?>">
                                            <button type="submit" class="btn-action btn-restore">Bərpa et</button>
                                        </form>
                                    <?php else: ?>
                                        <?php if ($status === "active"): ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Bu kitab gizlədilsin?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="hide_id" value="<?php echo (int)$book["id"]; ?>">
                                                <button type="submit" class="btn-action btn-hide">Gizlət</button>
                                            </form>
                                        <?php elseif ($status === "hidden"): ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Bu kitab yenidən aktiv edilsin?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="activate_id" value="<?php echo (int)$book["id"]; ?>">
                                                <button type="submit" class="btn-action btn-activate">Aktiv et</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="inline-form" onsubmit="return confirm('Bu kitab silinmiş kimi işarələnsin?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo (int)$book["id"]; ?>">
                                            <button type="submit" class="btn-action btn-delete">Sil</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$books): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#64748b;">
                                Heç bir kitab tapılmadı.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
