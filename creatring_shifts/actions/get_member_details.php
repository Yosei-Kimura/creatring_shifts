<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

$event_id = $_GET['event_id'] ?? 0;
$member_name = $_GET['name'] ?? '';

if (!$event_id || !$member_name) {
    echo json_encode(['error' => '情報が不足しています。']);
    exit();
}

try {
    // イベント名も取得しておく
    $stmt_event = $conn->prepare("SELECT name FROM events WHERE id = :event_id");
    $stmt_event->execute([':event_id' => $event_id]);
    $event_name = $stmt_event->fetchColumn();

    if (!$event_name) {
        echo json_encode(['error' => 'イベントが見つかりません。']);
        exit();
    }

    // シフト希望情報と属性を取得
    $sql = "SELECT 
                a.grade, p.name AS party_name,
                DATE_FORMAT(a.start_time, '%H:%i') as start_time,
                DATE_FORMAT(a.end_time, '%H:%i') as end_time,
                GROUP_CONCAT(attr.name SEPARATOR ', ') AS attributes
            FROM availabilities a
            LEFT JOIN parties p ON a.party_id = p.id
            LEFT JOIN availability_attributes aa ON a.id = aa.availability_id
            LEFT JOIN attributes attr ON aa.attribute_id = attr.id
            WHERE a.submitter_name = :name AND a.event_name = :event_name
            GROUP BY a.id
            LIMIT 1"; // 同姓同名がいない前提

    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $member_name, ':event_name' => $event_name]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($details) {
        echo json_encode($details);
    } else {
        echo json_encode(['error' => '該当者の詳細情報が見つかりません。']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}
?>