<?php
// actions/submit.php
require_once '../config/db_connect.php';

$event_id = $_POST['event_id'] ?? null;
$submitter_name = $_POST['submitter_name'] ?? '';
$grade = $_POST['grade'] ?? '';
$party_id = !empty($_POST['party_id']) ? $_POST['party_id'] : null;
$attribute_ids = $_POST['attribute_ids'] ?? [];
$start_time_str = $_POST['start_time'] ?? '';
$end_time_str = $_POST['end_time'] ?? '';

if (empty($event_id) || empty($submitter_name) || empty($grade) || empty($start_time_str) || empty($end_time_str)) {
    die("エラー：必須項目が入力されていません。");
}

try {
    $stmt_event = $conn->prepare("SELECT name, event_date FROM events WHERE id = :event_id");
    $stmt_event->execute([':event_id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        die("有効なイベントではありません。");
    }
    $event_name = $event['name'];
    $event_date = $event['event_date'];

    // ★★★ ここからが修正箇所 ★★★
    // 重複チェックのSQL文を修正。availabilitiesテーブルのみを参照するようにします。
    $stmt_check = $conn->prepare(
        "SELECT COUNT(*) FROM availabilities WHERE submitter_name = :name AND event_name = :event_name"
    );
    $stmt_check->execute([':name' => $submitter_name, ':event_name' => $event_name]);
    $count = $stmt_check->fetchColumn();
    // ★★★ ここまでが修正箇所 ★★★

    if ($count > 0) {
        die("<h1>登録エラー</h1><p>". htmlspecialchars($event_name) ."には既に同じ名前で希望が提出されています。</p><a href='../index.php?event_id=". $event_id ."'>戻る</a>");
    }

    $conn->beginTransaction();
    
    $start_datetime = $event_date . ' ' . $start_time_str;
    $end_datetime = $event_date . ' ' . $end_time_str;

    // ★★★ availabilitiesテーブルにはevent_idカラムがないため、INSERT文からも削除 ★★★
    $stmt_avail = $conn->prepare(
        "INSERT INTO availabilities (submitter_name, grade, party_id, event_name, start_time, end_time) 
         VALUES (:name, :grade, :party_id, :event_name, :start, :end)"
    );
    $stmt_avail->execute([
        ':name' => $submitter_name,
        ':grade' => $grade,
        ':party_id' => $party_id,
        ':event_name' => $event_name,
        ':start' => $start_datetime,
        ':end' => $end_datetime
    ]);

    $availability_id = $conn->lastInsertId();

    if (!empty($attribute_ids)) {
        $stmt_attr = $conn->prepare("INSERT INTO availability_attributes (availability_id, attribute_id) VALUES (:avail_id, :attr_id)");
        foreach ($attribute_ids as $attr_id) {
            $stmt_attr->execute([':avail_id' => $availability_id, 'attr_id' => $attr_id]);
        }
    }

    $conn->commit();
    echo "<h1>シフト希望を登録しました！</h1><p>ご協力ありがとうございます。</p><a href='../index.php?event_id=" . urlencode($event_id) . "'>続けて登録する</a>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("登録に失敗しました: " . $e->getMessage());
}
?>