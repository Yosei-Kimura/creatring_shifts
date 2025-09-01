<?php
require_once 'config/db_connect.php';
$events = $conn->query("SELECT id, name, event_date FROM events ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$selected_event_id = $_GET['event_id'] ?? null;
$positions = [];
$requirements = [];

if ($selected_event_id) {
    try {
        $stmt_pos = $conn->prepare("SELECT id, name FROM positions WHERE event_id = :event_id ORDER BY name");
        $stmt_pos->execute([':event_id' => $selected_event_id]);
        $positions = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);
        $sql = "SELECT r.id, r.shift_name, r.start_time, r.end_time, r.required_people, p.name AS position_name
                FROM shift_requirements r JOIN positions p ON r.position_id = p.id WHERE p.event_id = :event_id
                ORDER BY r.start_time, p.name";
        $stmt_req = $conn->prepare($sql);
        $stmt_req->execute([':event_id' => $selected_event_id]);
        $requirements = $stmt_req->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { die("データ取得失敗: " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ | シフト要件設定</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <h1>シフト要件設定</h1>
    <a href="dashboard.php">&laquo; ダッシュボードに戻る</a>
    <div class="form-container" style="margin-top: 20px;">
        <form method="GET" action="admin_requirements.php">
            <div class="form-group">
                <label for="event_id"><b>① イベントを選択してください</b></label>
                <select id="event_id" name="event_id" onchange="this.form.submit()"><option value="">-- 選択 --</option>
                    <?php foreach ($events as $event): ?><option value="<?php echo $event['id']; ?>" <?php if ($event['id'] == $selected_event_id) echo 'selected'; ?>><?php echo htmlspecialchars($event['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <hr>
    <?php if ($selected_event_id): ?>
    <div class="form-container">
        <h2>募集枠を作成</h2>
        <form action="actions/requirement_action.php" method="POST">
            <input type="hidden" name="action" value="add_requirement">
            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($selected_event_id); ?>">
            <div class="form-group"><label>コンテンツチーム</label><select name="position_id" required><option value="">-- 選択 --</option><?php foreach ($positions as $pos):?><option value="<?php echo $pos['id'];?>"><?php echo htmlspecialchars($pos['name']);?></option><?php endforeach;?></select></div>
            <div class="form-group"><label>シフト名</label><input type="text" name="shift_name" placeholder="（任意）"></div>
            <div class="form-group" style="display:flex; gap: 15px;">
                <div><label>開始時間</label><input type="time" name="start_time" required></div>
                <div><label>終了時間</label><input type="time" name="end_time" required></div>
                <div><label>必要人数</label><input type="number" name="required_people" value="1" min="1" required style="width: 80px;"></div>
            </div>
            <button type="submit" class="btn-submit">作成</button>
        </form>
    </div>
    <h2 style="margin-top: 40px;">募集枠一覧</h2>
    <table><thead><tr><th>チーム</th><th>シフト名</th><th>時間</th><th>人数</th><th>操作</th></tr></thead>
    <tbody><?php foreach ($requirements as $req):?><tr>
        <td><?php echo htmlspecialchars($req['position_name']);?></td><td><?php echo htmlspecialchars($req['shift_name']);?></td>
        <td><?php echo date('H:i', strtotime($req['start_time']));?> - <?php echo date('H:i', strtotime($req['end_time']));?></td>
        <td><?php echo htmlspecialchars($req['required_people']);?>人</td>
        <td><form action="actions/requirement_action.php" method="POST" onsubmit="return confirm('削除しますか？');"><input type="hidden" name="action" value="delete_requirement"><input type="hidden" name="requirement_id" value="<?php echo $req['id'];?>"><input type="hidden" name="event_id" value="<?php echo $selected_event_id;?>"><button type="submit" class="btn-delete">削除</button></form></td>
    </tr><?php endforeach;?></tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>