<?php
// actions/user_action.php

// ★★★ 修正点1: パスの先頭に「../」を追加 ★★★
// DB接続ファイルを読み込む
require_once '../config/db_connect.php';

// POSTリクエストからactionの値を取得
$action = $_POST['action'] ?? '';

// actionの値に応じて処理を分岐
switch ($action) {
    // --- メンバー追加処理 ---
    case 'add':
        // (ここの処理内容は変更ありません)
        $name = $_POST['name'] ?? '';
        $grade = $_POST['grade'] ?? '';
        $contact_info = $_POST['contact_info'] ?? '';
        if (empty($name)) { die('名前は必須です。'); }
        try {
            $username = str_replace(' ', '', $name) . '_' . time(); 
            $password_hash = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, grade, contact_info, username, password_hash) VALUES (:name, :grade, :contact, :username, :password)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':grade', $grade);
            $stmt->bindParam(':contact', $contact_info);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password_hash);
            $stmt->execute();
        } catch (PDOException $e) {
            die("メンバーの追加に失敗しました: " . $e->getMessage());
        }
        break;

    // --- メンバー削除処理 ---
    case 'delete':
        // (ここの処理内容は変更ありません)
        $user_id = $_POST['user_id'] ?? '';
        if (empty($user_id)) { die('ユーザーIDが指定されていません。'); }
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            die("メンバーの削除に失敗しました: " . $e->getMessage());
        }
        break;

    default:
        break;
}

// ★★★ 修正点2: パスの先頭に「../」を追加 ★★★
// 処理が終わったら、メンバー管理ページに自動で戻る
header('Location: ../admin_users.php');
exit();
?>