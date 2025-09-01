<?php
require_once 'config/db_connect.php';
try {
    $events = $conn->query("SELECT id, name, event_date FROM events ORDER BY event_date DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("イベントリストの取得に失敗しました: " . $e->getMessage());
}
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <title>シフト管理システム ダッシュボード</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-toggle {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .dashboard-toggle-header {
            background-color: #f7f7f7;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            transition: background-color 0.2s;
        }
        .dashboard-toggle-header:hover {
            background-color: #e9e9e9;
        }
        .dashboard-toggle-header h2 {
            margin: 0;
            font-size: 1.2em;
            color: #333;
        }
        .dashboard-toggle-header .arrow {
            font-size: 1.5em;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .dashboard-toggle-content {
            padding: 20px;
            border-top: 1px solid #ddd;
            display: none; /* 初期状態は非表示 */
        }
        .dashboard-toggle-content.is-open {
            display: block;
        }
        .dashboard-toggle-header:not(.is-closed) .arrow {
            transform: rotate(90deg);
        }
        .event-list {
            list-style-type: none;
            padding-left: 0;
        }
        .event-list li {
            margin-bottom: 10px;
        }
        .event-list a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .event-list a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>シフト管理システム</h1>
        
        <div class="dashboard-toggle">
            <div class="dashboard-toggle-header">
                <h2>シフト希望を提出する</h2>
                <span class="arrow">▶</span>
            </div>
            <div class="dashboard-toggle-content">
                <p>参加したいイベント名をクリックしてください。</p>
                <ul class="event-list">
                    <?php foreach ($events as $event): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($base_url . 'index.php?event_id=' . $event['id']); ?>">
                                <?php echo htmlspecialchars($event['event_date'] . ' : ' . $event['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="dashboard-toggle">
            <div class="dashboard-toggle-header">
                <h2>管理者メニュー</h2>
                <span class="arrow">▶</span>
            </div>
            <div class="dashboard-toggle-content">
                <div class="menu-grid">
                    <a href="admin.php" class="card"><h2>シフト希望一覧</h2><p>提出されたシフト希望を一覧で確認します。</p></a>
                    <a href="admin_master.php" class="card"><h2>各種マスタ設定</h2><p>イベント、PT名、チーム、属性などを設定します。</p></a>
                    <a href="admin_requirements.php" class="card"><h2>シフト要件設定</h2><p>募集枠の作成を行います。</p></a>
                    <a href="admin_create_shift.php" class="card"><h2>シフト自動作成</h2><p>募集枠とシフト希望を元にシフトを自動作成します。</p></a>
                    <a href="admin_final_shift.php" class="card"><h2>完成シフト確認</h2><p>自動作成されたシフトの内容を確認します。</p></a>
                </div>
            </div>
        </div>
    </div>
<script>
document.querySelectorAll('.dashboard-toggle-header').forEach(header => {
    header.addEventListener('click', () => {
        const content = header.nextElementSibling;
        header.classList.toggle('is-closed');
        content.classList.toggle('is-open');
    });
});
</script>
</body>
</html>