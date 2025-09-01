<?php
require_once '../config/db_connect.php';

$action = $_POST['action'] ?? '';
$event_id = $_POST['event_id'] ?? null;

try {
    // イベントの日付を取得
    $event_date = '';
    if ($event_id) {
        $stmt_date = $conn->prepare("SELECT event_date FROM events WHERE id = :event_id");
        $stmt_date->execute([':event_id' => $event_id]);
        $event_date = $stmt_date->fetchColumn();
    }
    if (!$event_date) {
        throw new Exception('有効なイベント日が見つかりません。');
    }

    switch ($action) {
        case 'add_requirement':
            $start_datetime = $event_date . ' ' . $_POST['start_time'];
            $end_datetime = $event_date . ' ' . $_POST['end_time'];

            $stmt = $conn->prepare(
                "INSERT INTO shift_requirements (position_id, shift_name, start_time, end_time, required_people)
                 VALUES (:pid, :sname, :start, :end, :req_p)"
            );
            $stmt->execute([
                ':pid' => $_POST['position_id'],
                ':sname' => $_POST['shift_name'],
                ':start' => $start_datetime,
                ':end' => $end_datetime,
                ':req_p' => $_POST['required_people']
            ]);
            break;

        case 'delete_requirement':
             $stmt = $conn->prepare("DELETE FROM shift_requirements WHERE id = :req_id");
             $stmt->execute([':req_id' => $_POST['requirement_id']]);
             break;
    }
} catch (Exception $e) {
    die("処理に失敗しました: " . $e->getMessage());
}

// 処理が終わったら、作業していたイベントのページに戻る
$redirect_url = '../admin_requirements.php?event_id=' . urlencode($event_id);
header('Location: ' . $redirect_url);
exit();
?>