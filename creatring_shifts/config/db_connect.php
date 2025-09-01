<?php
// config/db_connect.php

// --- データベース接続情報 ---
// ※※※ ここはご自身のデータベース情報に書き換えてください ※※※
$servername = "mysql321.phy.lolipop.lan"; // 例: mysql123.phy.lolipop.jp
$username = "LAA0956269";         // データベースのユーザー名
$password = "marie2011";         // データベースのパスワード
$dbname = "LAA0956269-shiftsystem";             // データベース名

// --- データベースへの接続 ---
try {
    // PDOという仕組みでデータベースに接続します
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // エラーが起きた時に例外を投げるように設定
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    // 接続に失敗した場合は、エラーメッセージを表示して処理を完全に中断します
    die("データベース接続エラー: " . $e->getMessage());
}

// このファイルが読み込まれた時に、接続情報($conn)が他のファイルで使えるようになります。
?>