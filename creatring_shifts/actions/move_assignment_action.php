<?php
require_once '../config/db_connect.php';
// このファイルの応答はすべてJSON形式であることを宣言します
header('Content-Type: application/json');

// レスポンス用の配列を準備
$response = ['success' => false, 'error' => ''];

$action = $_POST['action'] ?? '';
$final_id = $_POST['final_id'] ?? 0;
$to_req_id = $_POST['to_req_id'] ?? 0;

if (!$final_id) {
    $response['error'] = '無効なデータです。';
    echo json_encode($response);
    exit();
}

try {
    if ($action === 'move' && $to_req_id) {
        $stmt = $conn->prepare("UPDATE final_assignments SET requirement_id = :to_req_id WHERE id = :final_id");
        $success = $stmt->execute([
            ':to_req_id' => $to_req_id,
            ':final_id' => $final_id
        ]);
    } elseif ($action === 'unassign') {
        $stmt = $conn->prepare("DELETE FROM final_assignments WHERE id = :final_id");
        $success = $stmt->execute([':final_id' => $final_id]);
    } else {
        throw new Exception('無効なアクションです。');
    }
    
    if ($success) {
        $response['success'] = true;
    } else {
        $response['error'] = 'データベースの更新に失敗しました。';
    }
} catch (Exception $e) {
    // エラーが発生した場合も、必ずJSON形式でエラー内容を返します
    $response['error'] = 'データベースエラー: ' . $e->getMessage();
    http_response_code(500); // サーバーエラーを示すステータスコード
}

// 最後に必ずJSON形式で応答を出力し、処理を終了します
echo json_encode($response);
exit();
?>