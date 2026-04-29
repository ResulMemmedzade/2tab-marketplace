<?php

require_once "config.php";

requireAdmin();
ensureCsrfToken();

$currentAdminId = currentUserId();

$search = trim($_GET["search"] ?? "");
$roleFilter = trim($_GET["role"] ?? "all");

try {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        verifyCsrfToken($_POST["csrf_token"] ?? null);

        if (isset($_POST["make_admin"])) {
            $targetId = (int) $_POST["make_admin"];

            if ($targetId > 0) {
                try {
                    $oldStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                    $oldStmt->execute([$targetId]);
                    $targetUser = $oldStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                    $stmt->execute([$targetId]);

                    flashSuccess('İstifadəçi admin edildi.');

                    appLog('admin_action', 'Admin changed user role to admin', [
                        'admin_id' => $currentAdminId,
                        'action' => 'make_admin',
                        'target_user_id' => $targetId,
                        'target_user_name' => $targetUser['name'] ?? null,
                        'target_user_email' => $targetUser['email'] ?? null,
                        'old_role' => $targetUser['role'] ?? null,
                        'new_role' => 'admin',
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    appLog('system_error', 'Failed to change user role to admin', [
                        'admin_id' => $currentAdminId,
                        'target_user_id' => $targetId,
                        'error' => $e->getMessage(),
                    ]);

                    flashError('İstifadəçini admin etmək mümkün olmadı.');
                }
            }

            redirectTo("admin_users.php");
        }
        if (isset($_POST["ban_user"])) {
            $targetId = (int) $_POST["ban_user"];
        
            if ($targetId > 0 && $targetId !== $currentAdminId) {
        
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET status = 'banned',
                        banned_at = NOW(),
                        banned_by = ?
                    WHERE id = ? AND role != 'admin'
                ");
                $stmt->execute([$currentAdminId, $targetId]);
                $hideBooksStmt = $pdo->prepare("
    UPDATE books
    SET status = 'hidden'
    WHERE seller_id = ?
      AND status = 'active'
");
$hideBooksStmt->execute([$targetId]);
        
                flashSuccess("İstifadəçi BAN edildi.");
            }
        
            redirectTo("admin_users.php");
        }
        if (isset($_POST["unban_user"])) {
            $targetId = (int) $_POST["unban_user"];
        
            if ($targetId > 0) {
        
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET status = 'active',
                        banned_at = NULL,
                        banned_by = NULL,
                        banned_reason = NULL,
                        ban_expires_at = NULL,
                        strike_count = 0
                    WHERE id = ?
                ");
                $stmt->execute([$targetId]);
                $restoreBooksStmt = $pdo->prepare("
    UPDATE books
    SET status = 'active'
    WHERE seller_id = ?
      AND status = 'hidden'
");
$restoreBooksStmt->execute([$targetId]);
        
                flashSuccess("İstifadəçi UNBAN edildi.");
            }
        
            redirectTo("admin_users.php");
        }
        if (isset($_POST["make_buyer"])) {
            $targetId = (int) $_POST["make_buyer"];

            if ($targetId > 0 && $targetId !== $currentAdminId) {
                try {
                    $oldStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                    $oldStmt->execute([$targetId]);
                    $targetUser = $oldStmt->fetch();

                    $stmt = $pdo->prepare("UPDATE users SET role = 'buyer' WHERE id = ?");
                    $stmt->execute([$targetId]);

                    flashSuccess('İstifadəçi buyer edildi.');

                    appLog('admin_action', 'Admin changed user role to buyer', [
                        'admin_id' => $currentAdminId,
                        'action' => 'make_buyer',
                        'target_user_id' => $targetId,
                        'target_user_name' => $targetUser['name'] ?? null,
                        'target_user_email' => $targetUser['email'] ?? null,
                        'old_role' => $targetUser['role'] ?? null,
                        'new_role' => 'buyer',
                    ]);
                } catch (PDOException $e) {
                    error_log($e->getMessage());

                    appLog('system_error', 'Failed to change user role to buyer', [
                        'admin_id' => $currentAdminId,
                        'target_user_id' => $targetId,
                        'error' => $e->getMessage(),
                    ]);

                    flashError('İstifadəçini buyer etmək mümkün olmadı.');
                }
            }

            redirectTo("admin_users.php");
        }
    }

    $sql = "
        SELECT id, name, email, role, status, created_at
FROM users
        WHERE 1=1
    ";
    $params = [];

    if ($search !== "") {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (in_array($roleFilter, ["admin", "buyer"], true)) {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Database error in admin_users', [
        'error' => $e->getMessage(),
        'admin_id' => $currentAdminId,
    ]);

    $users = [];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Admin İstifadəçilər</title>
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
            vertical-align: middle;
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

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-admin {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-user {
            background: #dbeafe;
            color: #1d4ed8;
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

        .btn-admin {
            background: #dcfce7;
            color: #166534;
        }

        .btn-buyer {
            background: #fee2e2;
            color: #b91c1c;
        }

        .inline-form {
            display: inline;
        }

        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .self-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
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
        <a href="/admin.php" class="btn-back">← Geri qayıt</a>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-success">
            <?= e($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="flash-error">
                <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="page-title">
            <h1>Bütün istifadəçilər</h1>
            <p>Buradan sistemdə qeydiyyatdan keçən istifadəçiləri axtara və idarə edə bilərsən.</p>
        </div>

        <div class="admin-actions">
            <a href="<?= e(basePath('admin.php')) ?>" class="btn btn-light">Dashboard</a>
            <a href="<?= e(basePath('admin_books.php')) ?>" class="btn btn-light">Kitablar</a>
            <a href="<?= e(basePath('admin_users.php')) ?>" class="btn btn-primary">İstifadəçiləri idarə et</a>
        </div>

        <div class="filters-card">
            <form method="GET" class="filter-grid">
                <input
                    type="text"
                    name="search"
                    placeholder="Ad və ya email üzrə axtar..."
                    value="<?= e($search) ?>"
                >

                <select name="role">
                    <option value="all" <?php echo $roleFilter === "all" ? "selected" : ""; ?>>Bütün rollar</option>
                    <option value="admin" <?php echo $roleFilter === "admin" ? "selected" : ""; ?>>Admin</option>
                    <option value="buyer" <?php echo $roleFilter === "buyer" ? "selected" : ""; ?>>Buyer</option>
                </select>

                <button type="submit" class="btn btn-primary">Axtar</button>
                <a href="/admin_users.php" class="btn btn-light">Təmizlə</a>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Status</th>
                        <th>Qeydiyyat tarixi</th>
                        <th>Əməliyyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int)$user["id"]; ?></td>
                            <td><?= e($user["name"]) ?></td>
                            <td><?php echo htmlspecialchars($user["email"], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (($user["role"] ?? "") === "admin"): ?>
                                    <span class="badge badge-admin">admin</span>
                                <?php else: ?>
                                    <span class="badge badge-user"><?php echo htmlspecialchars($user["role"] ?? "buyer", ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
    <?php if ($user["status"] === "active"): ?>
        <span class="badge" style="background:#dcfce7;color:#166534;">active</span>
    <?php elseif ($user["status"] === "banned"): ?>
        <span class="badge" style="background:#fee2e2;color:#b91c1c;">banned</span>
    <?php elseif ($user["status"] === "temp_banned"): ?>
        <span class="badge" style="background:#fef3c7;color:#92400e;">temp</span>
    <?php elseif ($user["status"] === "flagged"): ?>
        <span class="badge" style="background:#e0f2fe;color:#075985;">flagged</span>
    <?php endif; ?>
</td>
                            <td><?php echo htmlspecialchars($user["created_at"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                            <div class="action-group">

<?php if (($user["role"] ?? "") !== "admin"): ?>

    <!-- Admin et -->
    <form method="POST" class="inline-form" onsubmit="return confirm('Bu istifadəçi admin olsun?');">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="make_admin" value="<?= (int)$user["id"]; ?>">
        <button type="submit" class="btn-action btn-admin">Admin et</button>
    </form>

    <!-- BAN / UNBAN -->
    <?php if ($user["status"] === "active"): ?>
        <form method="POST" class="inline-form" onsubmit="return confirm('Bu istifadəçi BAN edilsin?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="ban_user" value="<?= (int)$user["id"]; ?>">
            <button type="submit" class="btn-action" style="background:#fee2e2;color:#b91c1c;">
                Ban et
            </button>
        </form>
    <?php else: ?>
        <form method="POST" class="inline-form" onsubmit="return confirm('Bu istifadəçi UNBAN edilsin?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="unban_user" value="<?= (int)$user["id"]; ?>">
            <button type="submit" class="btn-action" style="background:#dcfce7;color:#166534;">
                Unban et
            </button>
        </form>
    <?php endif; ?>

<?php elseif ((int)$user["id"] !== (int)currentUserId()): ?>

    <!-- Buyer et -->
    <form method="POST" class="inline-form" onsubmit="return confirm('Bu istifadəçi buyer olsun?');">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="make_buyer" value="<?= (int)$user["id"]; ?>">
        <button type="submit" class="btn-action btn-buyer">Buyer et</button>
    </form>

<?php else: ?>

    <span class="self-label">Sən</span>

<?php endif; ?>

</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#64748b;">
                                Heç bir istifadəçi tapılmadı.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
