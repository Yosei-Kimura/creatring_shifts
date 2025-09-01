<?php
// actions/master_action.php
require_once '../config/db_connect.php';

$type = $_POST['type'] ?? ''; 
$action = $_POST['action'] ?? '';
$event_id = $_POST['event_id'] ?? null; // どのイベントに対する操作かを識別

$message = '処理が完了しました。';

try {
    switch ($type) {
        case 'event':
            if ($action === 'add' && !empty($_POST['name']) && !empty($_POST['event_date'])) {
                $stmt = $conn->prepare("INSERT INTO events (name, event_date) VALUES (:name, :event_date)");
                $stmt->execute(['name' => $_POST['name'], 'event_date' => $_POST['event_date']]);
            } elseif ($action === 'delete' && !empty($_POST['id'])) {
                $stmt = $conn->prepare("DELETE FROM events WHERE id = :id");
                $stmt->execute(['id' => $_POST['id']]);
            }
            break;

        case 'party':
            $table_name = 'parties';
            if ($action === 'add' && !empty($_POST['name'])) {
                $stmt = $conn->prepare("INSERT INTO $table_name (name) VALUES (:name)");
                $stmt->execute(['name' => $_POST['name']]);
            } elseif ($action === 'delete' && !empty($_POST['id'])) {
                $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = :id");
                $stmt->execute(['id' => $_POST['id']]);
            }
            break;
        
        case 'position':
            $table_name = 'positions';
            if ($action === 'add' && !empty($_POST['name']) && !empty($event_id)) {
                $stmt = $conn->prepare("INSERT INTO $table_name (name, event_id) VALUES (:name, :event_id)");
                $stmt->execute(['name' => $_POST['name'], 'event_id' => $event_id]);
            } elseif ($action === 'delete' && !empty($_POST['id'])) {
                $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = :id");
                $stmt->execute(['id' => $_POST['id']]);
            }
            break;
            
        case 'attribute':
            $table_name = 'attributes';
            if ($action === 'add' && !empty($_POST['name']) && !empty($event_id)) {
                $stmt = $conn->prepare("INSERT INTO $table_name (name, event_id) VALUES (:name, :event_id)");
                $stmt->execute(['name' => $_POST['name'], 'event_id' => $event_id]);
            } elseif ($action === 'delete' && !empty($_POST['id'])) {
                $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = :id");
                $stmt->execute(['id' => $_POST['id']]);
            }
            break;
            
        case 'setting':
            if ($action === 'update' && !empty($event_id)) {
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $sql = "INSERT INTO event_settings (event_id, default_start_time, default_end_time) VALUES (:eid, :start, :end) 
                        ON DUPLICATE KEY UPDATE default_start_time = :start, default_end_time = :end";
                $stmt = $conn->prepare($sql);
                $stmt->execute(['eid' => $event_id, 'start' => $start_time, 'end' => $end_time]);
                $message = '設定を保存しました。';
            }
            break;
            
        default:
            die('無効なタイプです。');
    }
} catch (PDOException $e) { 
    die("処理に失敗: " . $e->getMessage()); 
}

// 処理完了後、作業していたイベントのページに戻るためのURLを構築
$redirect_url = '../admin_master.php?message=' . urlencode($message);
if ($event_id) {
    $redirect_url .= '&event_id=' . $event_id;
}
header('Location: ' . $redirect_url);
exit();
?>