<?php
require_once 'config/db_connect.php';
try {
    // イベントリストを取得（ドロップダウン用）
    $events = $conn->query("SELECT id, name FROM events ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // コンテンツチームと、それが属するイベントの名前をJOINして取得
    $sql = "SELECT p.id, p.name, e.name AS event_name
            FROM positions p
            LEFT JOIN events e ON p.event_id = e.id
            ORDER BY e.name, p.name";
    $positions = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("リストの取得に失敗しました: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ | コンテンツチーム管理</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>コンテンツチーム管理</h1>
        <a href="dashboard.php">&laquo; ダッシュボードに戻る</a>
        <div class="form-container" style="margin-top: 30px;">
            <h2>新しいコンテンツチームを追加</h2>
            <form action="actions/position_action.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="event_id">どのイベントに所属しますか？</label>
                    <select id="event_id" name="event_id" required>
                        <option value="">-- イベントを選択 --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="name">コンテンツチーム名</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <button type="submit" class="btn-submit">追加する</button>
            </form>
        </div>
        <h2 style="margin-top: 40px;">コンテンツチーム一覧</h2>
        <table>
            <thead><tr><th>イベント名</th><th>コンテンツチーム名</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($positions as $pos): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pos['event_name'] ?? '未分類'); ?></td>
                    <td><?php echo htmlspecialchars($pos['name']); ?></td>
                    <td>
                        <form action="actions/position_action.php" method="POST" onsubmit="return confirm('本当に削除しますか？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $pos['id']; ?>">
                            <button type="submit" class="btn-delete">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>