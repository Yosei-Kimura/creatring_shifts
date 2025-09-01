<?php
require_once '../config/db_connect.php';

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;
$name = $_POST['name'] ?? '';
$event_id = $_POST['event_id'] ?? null; // ★★★ event_idを取得 ★★★

try {
    if ($action === 'add' && !empty($name) && !empty($event_id)) {
        // ★★★ event_idも一緒に保存 ★★★
        $stmt = $conn->prepare("INSERT INTO positions (name, event_id) VALUES (:name, :event_id)");
        $stmt->execute(['name' => $name, 'event_id' => $event_id]);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM positions WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
} catch (PDOException $e) {
    die("処理に失敗しました: " . $e->getMessage());
}

header('Location: ../admin_positions.php');
exit();
?>