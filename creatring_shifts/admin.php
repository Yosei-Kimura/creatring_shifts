<?php
require_once 'config/db_connect.php';

// --- フィルターの値を取得 ---
$filter_event_name = $_GET['event_name'] ?? '';
$filter_name = $_GET['name'] ?? '';
$filter_grade = $_GET['grade'] ?? '';
$filter_party_id = $_GET['party_id'] ?? ''; // PT名はIDで受け取るように変更

try {
    // フィルター用のリストを取得
    $events = $conn->query("SELECT DISTINCT event_name FROM availabilities ORDER BY event_name")->fetchAll(PDO::FETCH_COLUMN);
    $parties = $conn->query("SELECT id, name FROM parties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // --- 基本となるSQL文 ---
    $sql = "SELECT 
                a.id, a.submitter_name, a.grade,
                a.start_time, a.end_time, p.name AS party_name,
                GROUP_CONCAT(attr.name SEPARATOR ', ') AS attributes
            FROM availabilities a
            LEFT JOIN parties p ON a.party_id = p.id
            LEFT JOIN availability_attributes aa ON a.id = aa.availability_id
            LEFT JOIN attributes attr ON aa.attribute_id = attr.id";

    // --- フィルター条件に応じてSQL文を動的に構築 ---
    $where_clauses = [];
    $params = [];

    if (!empty($filter_event_name)) {
        $where_clauses[] = "a.event_name = :event_name";
        $params[':event_name'] = $filter_event_name;
    }
    if (!empty($filter_name)) {
        $where_clauses[] = "a.submitter_name LIKE :name";
        $params[':name'] = '%' . $filter_name . '%';
    }
    if (!empty($filter_grade)) {
        $where_clauses[] = "a.grade = :grade";
        $params[':grade'] = $filter_grade;
    }
    if (!empty($filter_party_id)) {
        $where_clauses[] = "a.party_id = :party_id";
        $params[':party_id'] = $filter_party_id;
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " GROUP BY a.id ORDER BY a.start_time DESC";
    
    // --- SQLの実行 ---
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("データの取得に失敗しました: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ | シフト希望一覧</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-panel { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }
        .filter-buttons { grid-column: 1 / -1; display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <h1>管理者ページ | シフト希望一覧</h1>
        <a href="dashboard.php">&laquo; ダッシュボードに戻る</a>

        <div class="filter-panel" style="margin-top: 20px;">
            <form method="GET" action="admin.php">
                <div class="form-group">
                    <label for="event_name" style="font-weight: bold;">イベント名で絞り込み</label>
                    <select name="event_name" id="event_name">
                        <option value="">-- 全てのイベント --</option>
                        <?php foreach($events as $event): ?>
                            <option value="<?php echo htmlspecialchars($event); ?>" <?php if($event == $filter_event_name) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($event); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr>
                <p style="font-weight: bold;">詳細フィルター</p>
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="name">名前</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($filter_name); ?>" placeholder="部分一致で検索">
                    </div>
                    <div class="form-group">
                        <label for="grade">学年</label>
                        <select name="grade" id="grade">
                            <option value="">-- 全て --</option>
                            <option value="1年生" <?php if('1年生' == $filter_grade) echo 'selected';?>>1年生</option>
                            <option value="2年生" <?php if('2年生' == $filter_grade) echo 'selected';?>>2年生</option>
                            <option value="3年生" <?php if('3年生' == $filter_grade) echo 'selected';?>>3年生</option>
                            <option value="4年生" <?php if('4年生' == $filter_grade) echo 'selected';?>>4年生</option>
                            <option value="その他" <?php if('その他' == $filter_grade) echo 'selected';?>>その他</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="party_id">PT名</label>
                        <select name="party_id" id="party_id">
                            <option value="">-- 全て --</option>
                            <?php foreach($parties as $party): ?>
                                <option value="<?php echo $party['id']; ?>" <?php if($party['id'] == $filter_party_id) echo 'selected';?>>
                                    <?php echo htmlspecialchars($party['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-buttons" style="margin-top: 20px;">
                    <button type="submit" class="btn-submit" style="width: 150px;">絞り込み</button>
                    <a href="admin.php" style="text-decoration: none; padding: 12px 20px; border: 1px solid #ccc; background-color: #fff; color: #333; border-radius: 4px;">リセット</a>
                </div>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>名前</th><th>学年</th><th>PT名</th><th>属性</th><th>開始時間</th><th>終了時間</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($availabilities) > 0): ?>
                    <?php foreach ($availabilities as $av): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($av['submitter_name']); ?></td>
                            <td><?php echo htmlspecialchars($av['grade']); ?></td>
                            <td><?php echo htmlspecialchars($av['party_name']); ?></td>
                            <td><?php echo htmlspecialchars($av['attributes']); ?></td>
                            <td><?php echo date('H:i', strtotime($av['start_time'])); ?></td>
                            <td><?php echo date('H:i', strtotime($av['end_time'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="no-data">該当するシフト希望はありません。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>