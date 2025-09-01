<?php
require_once 'config/db_connect.php';

$selected_event_id = $_GET['event_id'] ?? null;
if (!$selected_event_id) {
    die("イベントが指定されていません。ダッシュボードからアクセスしてください。");
}

try {
    $stmt_event = $conn->prepare("SELECT name, event_date FROM events WHERE id = :event_id");
    $stmt_event->execute([':event_id' => $selected_event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        die("指定されたイベントが見つかりません。");
    }
    $event_name = $event['name'];
    $event_date = $event['event_date'];

    $parties = $conn->query("SELECT id, name FROM parties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_attr = $conn->prepare("SELECT id, name FROM attributes WHERE event_id = :event_id ORDER BY name");
    $stmt_attr->execute([':event_id' => $selected_event_id]);
    $attributes = $stmt_attr->fetchAll(PDO::FETCH_ASSOC);

    $stmt_settings = $conn->prepare("SELECT default_start_time, default_end_time FROM event_settings WHERE event_id = :event_id");
    $stmt_settings->execute([':event_id' => $selected_event_id]);
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $default_start_time = $settings['default_start_time'] ?? '09:00';
    $default_end_time = $settings['default_end_time'] ?? '17:00';

} catch (PDOException $e) {
    die("データ取得に失敗: " . $e->getMessage());
}

$default_start_datetime = $event_date . 'T' . $default_start_time;
$default_end_datetime = $event_date . 'T' . $default_end_time;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event_name); ?> シフト希望フォーム</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container form-container">
        <h1><?php echo htmlspecialchars($event_name); ?> (<?php echo $event_date; ?>)</h1>
        <p>シフト希望を提出してください。</p>
        <form action="actions/submit.php" method="POST">
            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($selected_event_id); ?>">
            <div class="form-group"><label>名前</label><input type="text" name="submitter_name" required></div>
            <div class="form-group"><label>学年</label><select name="grade" required><option value="">--選択--</option><option value="1年生">1年生</option><option value="2年生">2年生</option><option value="3年生">3年生</option><option value="4年生">4年生</option><option value="その他">その他</option></select></div>
            <div class="form-group"><label>PT名</label><select name="party_id"><option value="">--未選択--</option><?php foreach ($parties as $party): ?><option value="<?php echo $party['id']; ?>"><?php echo htmlspecialchars($party['name']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>属性</label><ul><?php foreach ($attributes as $attribute): ?><li><input type="checkbox" name="attribute_ids[]" value="<?php echo $attribute['id'];?>" id="attr_<?php echo $attribute['id'];?>"><label for="attr_<?php echo $attribute['id'];?>"><?php echo htmlspecialchars($attribute['name']);?></label></li><?php endforeach;?></ul></div>
            <div class="form-group"><label>開始時間</label><input type="time" name="start_time" value="<?php echo $default_start_time; ?>" required></div>
            <div class="form-group"><label>終了時間</label><input type="time" name="end_time" value="<?php echo $default_end_time; ?>" required></div>
            <button type="submit" class="btn-submit">提出</button>
        </form>
    </div>
</body>
</html>