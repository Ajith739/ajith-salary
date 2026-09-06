<?php
/**
 * Database Connection & Environment Diagnostic Page
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$connected = false;
$error = '';
$serverInfo = '';
$tables = [];
$usersCount = 0;

try {
    $db = getDB();
    $connected = true;
    $serverInfo = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('users', $tables)) {
        $uStmt = $db->query("SELECT COUNT(*) FROM users");
        $usersCount = (int)$uStmt->fetchColumn();
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 40px 20px; display: flex; justify-content: center; }
        .diag-card { background: #1e293b; max-width: 680px; width: 100%; border-radius: 16px; border: 1px solid #334155; padding: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        h1 { margin-top: 0; font-size: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .badge { font-size: 0.85rem; padding: 6px 12px; border-radius: 20px; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.92rem; }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-weight: 500; width: 35%; }
        td { color: #e2e8f0; font-family: monospace; }
        .help-box { background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding: 14px 16px; border-radius: 0 8px 8px 0; margin-top: 20px; font-size: 0.9rem; line-height: 1.5; color: #93c5fd; }
        .btn { display: inline-block; background: #3b82f6; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; margin-top: 15px; font-size: 0.9rem; }
        .btn:hover { background: #2563eb; }
        .tables-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .tbl-tag { background: #0f172a; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #334155; color: #38bdf8; }
    </style>
</head>
<body>
    <div class="diag-card">
        <h1>
            <span><?= APP_NAME ?> Database Status</span>
            <?php if ($connected): ?>
                <span class="badge badge-success">✓ Connected</span>
            <?php else: ?>
                <span class="badge badge-error">✕ Connection Failed</span>
            <?php endif; ?>
        </h1>

        <table>
            <tr>
                <th>DB Host</th>
                <td><?= htmlspecialchars(DB_HOST) ?></td>
            </tr>
            <tr>
                <th>DB Port</th>
                <td><?= (int)DB_PORT ?></td>
            </tr>
            <tr>
                <th>Database Name</th>
                <td><?= htmlspecialchars(DB_NAME) ?></td>
            </tr>
            <tr>
                <th>DB User</th>
                <td><?= htmlspecialchars(DB_USER) ?></td>
            </tr>
            <tr>
                <th>DB Password</th>
                <td><?= !empty(DB_PASS) ? '•••••••• (' . strlen(DB_PASS) . ' chars)' : '<em style="color:#f59e0b;">(empty)</em>' ?></td>
            </tr>
            <tr>
                <th>SSL Mode</th>
                <td><?= DB_SSL ? 'Enabled' : 'Disabled' ?></td>
            </tr>
            <?php if ($connected): ?>
            <tr>
                <th>MySQL Version</th>
                <td><?= htmlspecialchars($serverInfo) ?></td>
            </tr>
            <tr>
                <th>Tables Found (<?= count($tables) ?>)</th>
                <td>
                    <?php if (empty($tables)): ?>
                        <span style="color:#f87171;">None found. Please import database/schema.sql!</span>
                    <?php else: ?>
                        <div class="tables-list">
                            <?php foreach ($tables as $tbl): ?>
                                <span class="tbl-tag"><?= htmlspecialchars($tbl) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Registered Users</th>
                <td><?= $usersCount ?> user(s) in database</td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if (!$connected): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 14px; margin: 15px 0;">
                <strong style="color: #f87171;">Connection Error:</strong>
                <p style="font-family: monospace; font-size: 0.85rem; color: #fca5a5; margin: 6px 0 0 0;"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <div class="help-box">
            <strong>How to configure for Vercel:</strong><br>
            Go to <strong>Vercel Dashboard → Your Project → Settings → Environment Variables</strong>.<br>
            Add: <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code>.<br>
            <em>Remember: Vercel does not host MySQL databases; use a cloud MySQL host like TiDB Cloud or Aiven.</em>
        </div>

        <div style="margin-top: 20px;">
            <a href="login.php" class="btn">Go to Login Page →</a>
        </div>
    </div>
</body>
</html>
