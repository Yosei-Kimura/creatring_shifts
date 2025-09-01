<?php
require_once '../config/db_connect.php';

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;

if (!$id) {
    die('IDが指定されていません。');
}

try {
    if ($action === 'update') {
        // データの更新
        $stmt = $conn->prepare(
            "UPDATE availabilities SET grade = :grade, party_id = :party_id, start_time = :start, end_time = :end WHERE id = :id"
        );
        $stmt->execute([
            ':grade' => $_POST['grade'],
            ':party_id' => !empty($_POST['party_id']) ? $_POST['party_id'] : null,
            ':start' => $_POST['start_time'],
            ':end' => $_POST['end_time'],
            ':id' => $id
        ]);

        // 属性の更新 (一旦すべて削除してから、再度登録する方式が簡単)
        $stmt_del_attr = $conn->prepare("DELETE FROM availability_attributes WHERE availability_id = :id");
        $stmt_del_attr->execute([':id' => $id]);

        if (!empty($_POST['attribute_ids'])) {
            $stmt_add_attr = $conn->prepare("INSERT INTO availability_attributes (availability_id, attribute_id) VALUES (:avail_id, :attr_id)");
            foreach ($_POST['attribute_ids'] as $attr_id) {
                $stmt_add_attr->execute([':avail_id' => $id, 'attr_id' => $attr_id]);
            }
        }

    } elseif ($action === 'delete') {
        // データの削除
        $stmt = $conn->prepare("DELETE FROM availabilities WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
} catch (PDOException $e) {
    die("処理に失敗しました: " . $e->getMessage());
}

header('Location: ../admin.php');
exit();
?>