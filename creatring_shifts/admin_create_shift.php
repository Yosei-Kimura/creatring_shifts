<?php
require_once 'config/db_connect.php';

// イベントリストを取得
$events = $conn->query("SELECT id, name FROM events ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$selected_event_id = $_GET['event_id'] ?? null;
$requirements = [];
$fixed_assignments = [];

if ($selected_event_id) {
    try {
        // 募集枠を取得
        $sql_req = "SELECT r.id, r.shift_name, r.start_time, r.end_time, r.required_people, p.name AS position_name
                    FROM shift_requirements r
                    JOIN positions p ON r.position_id = p.id
                    WHERE p.event_id = :event_id
                    ORDER BY r.start_time, p.name";
        $stmt_req = $conn->prepare($sql_req);
        $stmt_req->execute([':event_id' => $selected_event_id]);
        $requirements = $stmt_req->fetchAll(PDO::FETCH_ASSOC);

        // 確定済みのメンバーを取得
        // requirement_idをキーとした連想配列にメンバー名のリストを格納
        $stmt_fixed_all = $conn->prepare(
            "SELECT fa.requirement_id, fa.member_name
             FROM fixed_assignments fa
             JOIN shift_requirements sr ON fa.requirement_id = sr.id
             JOIN positions p ON sr.position_id = p.id
             WHERE p.event_id = :event_id"
        );
        $stmt_fixed_all->execute([':event_id' => $selected_event_id]);
        foreach ($stmt_fixed_all as $row) {
            $fixed_assignments[$row['requirement_id']][] = $row['member_name'];
        }

    } catch (PDOException $e) {
        die("データ取得失敗: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理者ページ | シフト自動作成</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .candidate-list { margin-top: 10px; }
        .candidate-select { width: 150px; }
        .assigned-member {
            background-color: #e2e3e5;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>シフト自動作成</h1>
    <a href="dashboard.php">&laquo; ダッシュボードに戻る</a>

    <div class="form-container" style="margin-top: 20px;">
        <form method="GET" action="admin_create_shift.php">
            <div class="form-group">
                <label for="event_id"><b>① シフトを作成するイベントを選択してください</b></label>
                <select id="event_id" name="event_id" onchange="this.form.submit()">
                    <option value="">-- イベントを選択 --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event['id']; ?>" <?php if ($event['id'] == $selected_event_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($event['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <hr>

    <?php if ($selected_event_id): ?>
    <form action="actions/create_shift_action.php" method="POST" id="create-shift-form">
        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($selected_event_id); ?>">

        <div class="form-container">
            <h2>② 自動作成のルール設定</h2>
            <div class="form-group">
                <label for="max_continuous_hours">最大連続稼働時間（単位：時間）</label>
                <input type="number" name="max_continuous_hours" id="max_continuous_hours" value="4" min="1" step="0.5" style="width: 100px; padding: 8px;"> 時間
            </div>
        </div>

        <div style="margin-top: 40px;">
            <h2>③ 確定メンバーの事前割り当て</h2>
            <p>このシフトに必ず入る人が決まっている場合は、ここで割り当ててください。「候補者を表示」ボタンで、その時間帯に入れる希望を出した人が表示されます。</p>
            <table>
                <thead><tr><th>チーム</th><th>シフト名</th><th>時間</th><th>必要人数</th><th>確定メンバー</th></tr></thead>
                <tbody>
                <?php foreach ($requirements as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['position_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['shift_name']); ?></td>
                        <td><?php echo date('H:i', strtotime($req['start_time'])); ?> - <?php echo date('H:i', strtotime($req['end_time'])); ?></td>
                        <td><?php echo htmlspecialchars($req['required_people']); ?>人</td>
                        <td id="assignment-cell-<?php echo $req['id']; ?>">
                            <?php 
                            $assigned_members = $fixed_assignments[$req['id']] ?? [];
                            foreach($assigned_members as $member): ?>
                                <div class="assigned-member"><?php echo htmlspecialchars($member); ?></div>
                            <?php endforeach; ?>
                            
                            <div class="candidate-list"></div>

                            <button type="button" class="show-candidates-btn" data-req-id="<?php echo $req['id']; ?>">候補者を表示</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 40px; text-align: center;">
            <button type="submit" name="action" value="execute_creation" class="btn-submit" style="padding: 15px 40px; font-size: 1.2em;">
                シフト自動作成を実行する
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 全ての「候補者を表示」ボタンにクリックイベントを設定
    document.querySelectorAll('.show-candidates-btn').forEach(button => {
        button.addEventListener('click', function() {
            const reqId = this.dataset.reqId;
            const candidateListDiv = this.parentElement.querySelector('.candidate-list');
            
            // ボタンを非表示にし、"検索中..."メッセージを表示
            this.style.display = 'none';
            candidateListDiv.innerHTML = '検索中...';

            // 裏方プログラムに問い合わせて候補者リストを取得
            fetch(`actions/get_candidates.php?requirement_id=${reqId}`)
                .then(response => response.json())
                .then(candidates => {
                    if (candidates.error) {
                        candidateListDiv.innerHTML = `<p style="color:red;">${candidates.error}</p>`;
                        return;
                    }

                    if (candidates.length === 0) {
                        candidateListDiv.innerHTML = '候補者なし';
                        return;
                    }
                    
                    // 候補者を選択するためのフォームを作成
                    // formaction属性で、このフォームだけ別の処理に飛ばす
                    let formHtml = `
                        <form action="actions/create_shift_action.php" method="POST" style="display: flex; gap: 5px;">
                            <input type="hidden" name="action" value="add_fixed_member">
                            <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                            <input type="hidden" name="requirement_id" value="${reqId}">
                            <select name="member_name" class="candidate-select">`;

                    candidates.forEach(name => {
                        formHtml += `<option value="${name}">${name}</option>`;
                    });

                    formHtml += `
                            </select>
                            <button type="submit" style="padding: 5px 10px;">追加</button>
                        </form>`;
                    
                    candidateListDiv.innerHTML = formHtml;
                })
                .catch(error => {
                    candidateListDiv.innerHTML = `<p style="color:red;">エラーが発生しました。</p>`;
                    console.error('Error:', error);
                });
        });
    });
});
</script>
</body>
</html>