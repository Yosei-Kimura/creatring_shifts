<?php
require_once 'config/db_connect.php';
try {
    // 必要なデータをすべて取得
    $events = $conn->query("SELECT id, name, event_date FROM events ORDER BY event_date DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $parties = $conn->query("SELECT id, name FROM parties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // 選択されたイベントIDを取得
    $selected_event_id = $_GET['event_id'] ?? ($events[0]['id'] ?? null);

    $attributes = [];
    $positions = [];
    $settings = ['default_start_time' => '09:00', 'default_end_time' => '17:00'];

    if ($selected_event_id) {
        // 選択されたイベントに紐づく属性を取得
        $stmt_attr = $conn->prepare("SELECT id, name FROM attributes WHERE event_id = :event_id ORDER BY name");
        $stmt_attr->execute([':event_id' => $selected_event_id]);
        $attributes = $stmt_attr->fetchAll(PDO::FETCH_ASSOC);

        // 選択されたイベントに紐づくコンテンツチームを取得
        $stmt_pos = $conn->prepare("SELECT id, name FROM positions WHERE event_id = :event_id ORDER BY name");
        $stmt_pos->execute([':event_id' => $selected_event_id]);
        $positions = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);

        // 選択されたイベントに紐づく規定値を取得
        $stmt_settings = $conn->prepare("SELECT default_start_time, default_end_time FROM event_settings WHERE event_id = :event_id");
        $stmt_settings->execute([':event_id' => $selected_event_id]);
        if ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
            $settings = $row;
        }
    }
} catch (PDOException $e) { 
    die("データ取得失敗: " . $e->getMessage()); 
}

$message = $_GET['message'] ?? '';
// このシステムの基本URLを取得 (URL生成用)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ | マスタ設定</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .toggle-header { cursor: pointer; background-color: #f2f2f2; padding: 10px; border: 1px solid #ddd; margin-top: 20px; user-select: none; position: relative; }
        .toggle-header::before { content: '▶ '; font-size: 0.8em; }
        .toggle-header:not(.is-closed)::before { content: '▼ '; }
        .toggle-content { border: 1px solid #ddd; border-top: none; padding: 20px; }
        .toggle-content.is-closed { display: none; }
        .btn-delete { background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>マスタ設定</h1>
        <a href="dashboard.php">&laquo; ダッシュボードに戻る</a>
        <?php if ($message): ?><p style="color: green; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

        <div id="event-section" class="toggle-section">
            <h2 class="toggle-header">イベント管理</h2>
            <div class="toggle-content">
                <form action="actions/master_action.php" method="POST">
                    <input type="hidden" name="type" value="event"><input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>新しいイベントを追加</label>
                        <div style="display:flex; gap: 15px;">
                            <input type="text" name="name" placeholder="イベント名" required>
                            <input type="date" name="event_date" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">追加</button>
                </form>
                <h3 style="margin-top:30px;">イベント一覧</h3>
                <table>
                    <thead><tr><th>イベント日</th><th>イベント名</th><th>募集URL</th><th>操作</th></tr></thead>
                    <tbody><?php foreach($events as $e):?><tr>
                        <td><?php echo htmlspecialchars($e['event_date']);?></td>
                        <td><?php echo htmlspecialchars($e['name']);?></td>
                        <td>
                            <input type="text" value="<?php echo htmlspecialchars($base_url . 'index.php?event_id=' . $e['id']); ?>" readonly style="width: 200px;">
                            <button onclick="copyToClipboard(this)">コピー</button>
                        </td>
                        <td><form action="actions/master_action.php" method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="type" value="event"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $e['id'];?>"><button type="submit" class="btn-delete">削除</button></form></td>
                    </tr><?php endforeach;?></tbody>
                </table>
            </div>
        </div>

        <div id="party-section" class="toggle-section">
            <h2 class="toggle-header">PT名管理</h2>
            <div class="toggle-content">
                 <form action="actions/master_action.php" method="POST">
                    <input type="hidden" name="type" value="party"><input type="hidden" name="action" value="add">
                    <div class="form-group"><label>新しいPTを追加</label><input type="text" name="name" required></div>
                    <button type="submit" class="btn-submit">追加</button>
                </form>
                <h3 style="margin-top:30px;">PT名一覧</h3>
                <table><thead><tr><th>PT名</th><th>操作</th></tr></thead>
                <tbody><?php foreach($parties as $p):?><tr><td><?php echo htmlspecialchars($p['name']);?></td><td><form action="actions/master_action.php" method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="type" value="party"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $p['id'];?>"><button type="submit" class="btn-delete">削除</button></form></td></tr><?php endforeach;?></tbody></table>
            </div>
        </div>
        
        <hr style="margin-top:40px;">

        <div class="form-container" style="margin-top: 20px;">
            <form method="GET" action="admin_master.php" id="event_select_form">
                <div class="form-group">
                    <label for="event_selector"><b>チーム・属性・規定値を設定するイベントを選択</b></label>
                    <select id="event_selector" name="event_id" onchange="this.form.submit()">
                        <option value="">-- イベントを選択 --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" <?php if ($event['id'] == $selected_event_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($event['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_event_id): 
            $selected_event_name = $events[array_search($selected_event_id, array_column($events, 'id'))]['name'];
        ?>
        <div id="position-section" class="toggle-section">
            <h2 class="toggle-header">コンテンツチーム管理 (<?php echo htmlspecialchars($selected_event_name); ?>)</h2>
            <div class="toggle-content">
                <form action="actions/master_action.php" method="POST">
                    <input type="hidden" name="type" value="position"><input type="hidden" name="action" value="add">
                    <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                    <div class="form-group"><label>新しいコンテンツチームを追加</label><input type="text" name="name" required></div>
                    <button type="submit" class="btn-submit">追加</button>
                </form>
                <h3 style="margin-top:30px;">コンテンツチーム一覧</h3>
                <table><thead><tr><th>チーム名</th><th>操作</th></tr></thead>
                <tbody><?php foreach($positions as $p):?><tr><td><?php echo htmlspecialchars($p['name']);?></td><td><form action="actions/master_action.php" method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="type" value="position"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $p['id'];?>"><input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>"><button type="submit" class="btn-delete">削除</button></form></td></tr><?php endforeach;?></tbody></table>
            </div>
        </div>

        <div id="attribute-section" class="toggle-section">
            <h2 class="toggle-header">属性管理 (<?php echo htmlspecialchars($selected_event_name); ?>)</h2>
            <div class="toggle-content">
                <form action="actions/master_action.php" method="POST">
                    <input type="hidden" name="type" value="attribute"><input type="hidden" name="action" value="add">
                    <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                    <div class="form-group"><label>新しい属性を追加</label><input type="text" name="name" required></div>
                    <button type="submit" class="btn-submit">追加</button>
                </form>
                <h3 style="margin-top:30px;">属性一覧</h3>
                <table><thead><tr><th>属性名</th><th>操作</th></tr></thead>
                <tbody><?php foreach($attributes as $a):?><tr><td><?php echo htmlspecialchars($a['name']);?></td><td><form action="actions/master_action.php" method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="type" value="attribute"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $a['id'];?>"><input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>"><button type="submit" class="btn-delete">削除</button></form></td></tr><?php endforeach;?></tbody></table>
            </div>
        </div>

        <div id="setting-section" class="toggle-section">
            <h2 class="toggle-header">規定値設定 (<?php echo htmlspecialchars($selected_event_name); ?>)</h2>
            <div class="toggle-content">
                <form action="actions/master_action.php" method="POST">
                    <input type="hidden" name="type" value="setting"><input type="hidden" name="action" value="update">
                    <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                    <div class="form-group"><label>規定の開始時刻</label><input type="time" name="start_time" value="<?php echo htmlspecialchars($settings['default_start_time']);?>" required></div>
                    <div class="form-group"><label>規定の終了時刻</label><input type="time" name="end_time" value="<?php echo htmlspecialchars($settings['default_end_time']);?>" required></div>
                    <button type="submit" class="btn-submit">設定を保存</button>
                </form>
            </div>
        </div>
        <?php else: ?>
            <p style="margin-top:20px; font-weight:bold;">まず「イベント管理」からイベントを登録してください。</p>
        <?php endif; ?>
    </div>

<script>
function copyToClipboard(button) {
    const input = button.previousElementSibling;
    input.select();
    document.execCommand('copy');
    button.textContent = 'コピー完了';
    setTimeout(() => { button.textContent = 'コピー'; }, 2000);
}

document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.toggle-section');
    sections.forEach(section => {
        const header = section.querySelector('.toggle-header');
        const content = section.querySelector('.toggle-content');
        if(!header || !content) return;
        const sectionId = section.id;
        const forms = content.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                localStorage.setItem('openSectionId', sectionId);
                const eventId = document.getElementById('event_selector')?.value;
                if (eventId) {
                    localStorage.setItem('selectedEventId', eventId);
                }
            });
        });
        header.addEventListener('click', () => {
            header.classList.toggle('is-closed');
            content.classList.toggle('is-closed');
        });
        header.classList.add('is-closed');
        content.classList.add('is-closed');
    });
    const openSectionId = localStorage.getItem('openSectionId');
    if (openSectionId) {
        const sectionToOpen = document.getElementById(openSectionId);
        if (sectionToOpen) {
            const header = sectionToOpen.querySelector('.toggle-header');
            const content = sectionToOpen.querySelector('.toggle-content');
            if (header && content) {
                header.classList.remove('is-closed');
                content.classList.remove('is-closed');
                sectionToOpen.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            localStorage.removeItem('openSectionId');
        }
    }
    const selectedEventId = localStorage.getItem('selectedEventId');
    if (selectedEventId && !window.location.search.includes('event_id=')) {
        const form = document.getElementById('event_select_form');
        const selector = document.getElementById('event_selector');
        if (form && selector) {
            selector.value = selectedEventId;
            // Anchor to scroll to the event selector after redirect
            form.action += '#event_select_form'; 
            form.submit();
        }
    }
    localStorage.removeItem('selectedEventId');
});
</script>
</body>
</html>