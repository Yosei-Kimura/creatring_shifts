<?php
require_once '../config/db_connect.php';

header('Content-Type: application/json'); // 結果をJSON形式で返すことを宣言

$requirement_id = $_GET['requirement_id'] ?? 0;

if (!$requirement_id) {
    echo json_encode(['error' => 'Requirement ID is missing.']);
    exit();
}

try {
    // 1. まず、募集枠の詳細（特に時間帯）を取得
    $stmt_req = $conn->prepare("SELECT start_time, end_time FROM shift_requirements WHERE id = :id");
    $stmt_req->execute([':id' => $requirement_id]);
    $requirement = $stmt_req->fetch(PDO::FETCH_ASSOC);

    if (!$requirement) {
        echo json_encode(['error' => 'Requirement not found.']);
        exit();
    }

    $req_start = $requirement['start_time'];
    $req_end = $requirement['end_time'];

    // 2. その時間帯に勤務可能と希望を出した人を探す
    // (条件: 希望開始時間 <= 募集枠の開始時間 AND 希望終了時間 >= 募集枠の終了時間)
    $stmt_avail = $conn->prepare(
        "SELECT DISTINCT submitter_name FROM availabilities 
         WHERE start_time <= :req_start AND end_time >= :req_end
         ORDER BY submitter_name"
    );
    $stmt_avail->execute([':req_start' => $req_start, ':req_end' => $req_end]);
    $candidates = $stmt_avail->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($candidates); // 検索結果をJSON形式で出力

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>