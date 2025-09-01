<?php
require_once '../config/db_connect.php';

$action = $_POST['action'] ?? '';
$event_id = $_POST['event_id'] ?? null;

// 確定メンバーの追加処理
if ($action === 'add_fixed_member') {
    $requirement_id = $_POST['requirement_id'] ?? 0;
    $member_name = $_POST['member_name'] ?? '';

    if (!empty($requirement_id) && !empty($member_name)) {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO fixed_assignments (requirement_id, member_name) VALUES (:req_id, :name)"
            );
            $stmt->execute([':req_id' => $requirement_id, ':name' => $member_name]);
        } catch (PDOException $e) {
            die("確定メンバーの追加に失敗しました: " . $e->getMessage());
        }
    }
    // メンバー追加後は、同じイベント選択状態のページに戻る
    header('Location: ../admin_create_shift.php?event_id=' . $event_id);
    exit();
}

// シフト自動作成の実行処理
if ($action === 'execute_creation' && $event_id) {
    try {
        $conn->beginTransaction();

        // 0. このイベントの古い計算結果を削除
        $stmt_delete = $conn->prepare("DELETE FROM final_assignments WHERE event_id = :event_id");
        $stmt_delete->execute([':event_id' => $event_id]);

        // 1. 準備フェーズ：全データを取得
        // 募集枠 (確定メンバー数を引いた、本当に必要な人数も計算)
        $sql_req = "SELECT r.id, r.start_time, r.end_time, r.required_people, 
                           COUNT(fa.id) AS fixed_count, (r.required_people - COUNT(fa.id)) AS needed_count
                    FROM shift_requirements r
                    JOIN positions p ON r.position_id = p.id
                    LEFT JOIN fixed_assignments fa ON r.id = fa.requirement_id
                    WHERE p.event_id = :event_id
                    GROUP BY r.id";
        $stmt_req = $conn->prepare($sql_req);
        $stmt_req->execute([':event_id' => $event_id]);
        $requirements = $stmt_req->fetchAll(PDO::FETCH_ASSOC);

        // 応募者プール (希望時間)
        $sql_avail = "SELECT a.submitter_name, a.start_time, a.end_time
                      FROM availabilities a
                      WHERE a.event_name = (SELECT name FROM events WHERE id = :event_id)";
        $stmt_avail = $conn->prepare($sql_avail);
        $stmt_avail->execute([':event_id' => $event_id]);
        $candidates_raw = $stmt_avail->fetchAll(PDO::FETCH_ASSOC);
        
        $candidates = [];
        foreach ($candidates_raw as $c) {
            $candidates[$c['submitter_name']][] = ['start' => $c['start_time'], 'end' => $c['end_time']];
        }

        // 確定メンバーを先に割り当てリストに追加し、労働時間を記録
        $stmt_fixed = $conn->prepare("SELECT requirement_id, member_name FROM fixed_assignments fa JOIN shift_requirements sr ON fa.requirement_id = sr.id JOIN positions p ON sr.position_id = p.id WHERE p.event_id = :event_id");
        $stmt_fixed->execute([':event_id' => $event_id]);
        $final_assignments = [];
        $workload = []; 
        foreach ($stmt_fixed as $fixed) {
            $final_assignments[] = ['req_id' => $fixed['requirement_id'], 'member' => $fixed['member_name']];
            @$workload[$fixed['member_name']]++; 
        }

        // 2. マッチングフェーズ
        foreach ($requirements as $req) {
            if ($req['needed_count'] <= 0) continue; // 既に埋まっている枠はスキップ

            $eligible_candidates = [];
            foreach ($candidates as $name => $avail_slots) {
                // 時間が合うかチェック
                $is_available = false;
                foreach ($avail_slots as $slot) {
                    if ($slot['start'] <= $req['start_time'] && $slot['end'] >= $req['end_time']) {
                        $is_available = true;
                        break;
                    }
                }
                if (!$is_available) continue;

                // 候補者リストに追加 (スコア = 勤務回数が少ないほど高得点)
                $eligible_candidates[] = ['name' => $name, 'score' => -(@$workload[$name] ?? 0)];
            }

            // スコアが高い順（勤務時間が少ない順）にソート
            usort($eligible_candidates, fn($a, $b) => $b['score'] <=> $a['score']);

            // 人数分だけ割り当てる
            for ($i = 0; $i < $req['needed_count']; $i++) {
                if (empty($eligible_candidates)) break; // 候補者がいなくなったら終了

                $assigned_this_loop = false;
                foreach ($eligible_candidates as $key => $candidate) {
                    // この時間帯に、他のシフトも含めてまだ割り当てられていないかチェック
                    $is_already_busy = false;
                    foreach ($final_assignments as $assignment) {
                        $assigned_req = null;
                        foreach($requirements as $r) {
                            if($r['id'] == $assignment['req_id']) {
                                $assigned_req = $r;
                                break;
                            }
                        }
                        if ($assignment['member'] === $candidate['name'] && $assigned_req) {
                            // 時間の重複チェック: (StartA <= EndB) and (EndA >= StartB)
                            if ($req['start_time'] < $assigned_req['end_time'] && $req['end_time'] > $assigned_req['start_time']) {
                                $is_already_busy = true;
                                break;
                            }
                        }
                    }

                    if (!$is_already_busy) {
                        // 割り当てリストに追加
                        $final_assignments[] = ['req_id' => $req['id'], 'member' => $candidate['name']];
                        @$workload[$candidate['name']]++;
                        
                        // 割り当てたので、このループの候補者リストから削除
                        unset($eligible_candidates[$key]);
                        $assigned_this_loop = true;
                        break; // 次の必要人数 (i+1) の割り当てに移る
                    }
                }
                // このループで誰も割り当てられなかったら、これ以上探しても無駄なので次の募集枠へ
                if(!$assigned_this_loop) break;
            }
        }

        // 3. 最終化フェーズ：結果をDBに保存
        $stmt_insert = $conn->prepare("INSERT INTO final_assignments (event_id, requirement_id, member_name) VALUES (:eid, :rid, :name)");
        foreach ($final_assignments as $assignment) {
            $stmt_insert->execute([
                ':eid' => $event_id,
                ':rid' => $assignment['req_id'],
                ':name' => $assignment['member']
            ]);
        }

        $conn->commit();
        // 成功したら結果表示ページに移動
        header('Location: ../admin_final_shift.php?event_id=' . $event_id);
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        die("シフト作成中にエラーが発生しました: " . $e->getMessage());
    }
}
?>