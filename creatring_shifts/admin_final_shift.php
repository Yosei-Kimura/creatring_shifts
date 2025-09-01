<?php
require_once 'config/db_connect.php';
$event_id = $_GET['event_id'] ?? null;
if (!$event_id) { die("イベントが指定されていません。"); }

try {
    // イベント情報を取得
    $stmt_event = $conn->prepare("SELECT name, event_date FROM events WHERE id = :event_id");
    $stmt_event->execute([':event_id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) { die("イベントが見つかりません。"); }
    $event_name = $event['name'];
    $event_date = $event['event_date'];

    // 全ての募集枠と割り当てメンバーを取得
    $sql_data = "SELECT 
                r.id AS req_id, p.name AS position_name, r.shift_name, r.start_time, r.end_time, r.required_people,
                fa.id AS final_id, fa.member_name
            FROM shift_requirements r
            JOIN positions p ON r.position_id = p.id
            LEFT JOIN final_assignments fa ON r.id = fa.requirement_id AND fa.event_id = :event_id
            WHERE p.event_id = :event_id
            ORDER BY r.start_time, p.name";
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->execute([':event_id' => $event_id]);
    $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // 全てのシフト希望者とその希望時間を取得
    $stmt_avail = $conn->prepare("SELECT submitter_name, start_time, end_time FROM availabilities WHERE event_name = :event_name");
    $stmt_avail->execute([':event_name' => $event_name]);
    $all_availabilities = $stmt_avail->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC); // 名前をキーにする

    // データ整理
    $schedule_by_time = [];
    $schedule_by_person = [];
    $all_shifts = [];
    $assigned_members_set = [];

    foreach ($data as $row) {
        $time_slot = date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time']));
        $shift_key = $row['shift_name'] ?: $row['position_name']; 
        
        if (!isset($schedule_by_time[$time_slot])) { $schedule_by_time[$time_slot] = []; }
        if (!isset($schedule_by_time[$time_slot][$shift_key])) {
            $schedule_by_time[$time_slot][$shift_key] = ['req_id' => $row['req_id'], 'needed' => $row['required_people'], 'members' => []];
        }
        if ($row['member_name']) {
            $schedule_by_time[$time_slot][$shift_key]['members'][] = ['final_id' => $row['final_id'], 'name' => $row['member_name']];
            $schedule_by_person[$row['member_name']][] = ['start' => new DateTime($row['start_time']), 'end' => new DateTime($row['end_time']), 'shift' => $shift_key . ' [' . $time_slot . ']'];
            $assigned_members_set[$row['member_name']] = true;
        }
        if (!in_array($shift_key, $all_shifts)) { $all_shifts[] = $shift_key; }
    }
    
    // 個人別表示の休憩時間を計算
    foreach ($schedule_by_person as $name => &$shifts) {
        usort($shifts, fn($a, $b) => $a['start'] <=> $b['start']);
        $person_availability = $all_availabilities[$name][0] ?? null;
        if(!$person_availability) continue;

        $last_end = new DateTime($person_availability['start_time']);
        $availability_end = new DateTime($person_availability['end_time']);
        $breaks = [];

        foreach ($shifts as $shift) {
            if ($shift['start'] > $last_end) { $breaks[] = ['start' => $last_end, 'end' => $shift['start'], 'shift' => '休憩 ['. $last_end->format('H:i') .' - '. $shift['start']->format('H:i') .']']; }
            $last_end = $shift['end'];
        }
        if ($availability_end > $last_end) { $breaks[] = ['start' => $last_end, 'end' => $availability_end, 'shift' => '休憩 ['. $last_end->format('H:i') .' - '. $availability_end->format('H:i') .']']; }
        
        $shifts = array_merge($shifts, $breaks);
        usort($shifts, fn($a, $b) => $a['start'] <=> $b['start']);
    }

    $break_members_total = array_diff(array_keys($all_availabilities), array_keys($assigned_members_set));
    foreach ($break_members_total as $member) {
        if (empty($schedule_by_person[$member])) { $schedule_by_person[$member][] = ['shift' => '休憩']; }
    }
    ksort($schedule_by_time); ksort($schedule_by_person); sort($all_shifts);

} catch(PDOException $e) { die("データ取得失敗: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>完成シフト | <?php echo htmlspecialchars($event_name); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .view-toggle { margin-bottom: 10px; }
        .view-toggle button { padding: 10px 15px; cursor: pointer; border: 1px solid #ccc; background: #f0f0f0; }
        .view-toggle button.active { background: #007bff; color: white; border-color: #007bff; }
        #edit-mode-info { color: #555; font-size: 0.9em; display: block; margin-bottom: 10px; }
        .edit-button { padding: 10px 20px; font-weight: bold; cursor: pointer; border: 1px solid; border-radius: 5px; }
        #edit-btn { background-color: #ffc107; border-color: #ffc107; }
        #save-btn { background-color: #28a745; border-color: #28a745; color: white; }
        .schedule-view { display: none; }
        .schedule-view.active { display: block; }
        .table-wrapper { overflow-x: auto; max-width: 100%; }
        table { border-collapse: separate; border-spacing: 0; }
        th, td { vertical-align: top; white-space: nowrap; border: 1px solid #ddd; padding: 8px; }
        th:first-child, td:first-child { position: sticky; left: 0; background-color: #f8f9fa; z-index: 1; }
        th.break-col, td.break-col { background-color: #f0f8ff; position: sticky; right: 0; z-index: 1; box-shadow: -2px 0 5px -2px #ccc; }
        .member-list { list-style-type: none; padding: 5px; margin: 0; min-height: 30px; border: 1px dashed transparent; border-radius: 4px; }
        .member-item { background: #e9ecef; padding: 5px; margin-bottom: 5px; border-radius: 4px; }
        .member-item.draggable { cursor: grab; }
        .member-item.view-only { cursor: pointer; }
        details > summary { cursor: pointer; color: #0056b3; }
        .understaffed { background-color: #fff2f2; }
        .unassigned-panel { border: 2px dashed #ccc; padding: 10px; margin-top: 20px; background: #f8f9fa; display: none; }
        .unassigned-panel h3 { margin-top: 0; }
        .empty-slot { color: #ccc; text-align: center; font-size: 1.5em; vertical-align: middle; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; display: none; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 20px 30px; border-radius: 8px; z-index: 101; width: 90%; max-width: 500px; position: relative; }
        .modal-close { position: absolute; top: 10px; right: 15px; font-size: 1.5em; cursor: pointer; color: #aaa; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 95%;">
        <h1>完成シフト：<?php echo htmlspecialchars($event_name); ?></h1>
        <div class="controls">
            <div><a href="dashboard.php">&laquo; ダッシュボードに戻る</a><a href="admin_create_shift.php?event_id=<?php echo $event_id; ?>" style="margin-left: 15px;">&laquo; 自動作成ページに戻る</a></div>
            <div><button id="edit-btn" class="edit-button">編集</button><button id="save-btn" class="edit-button" style="display:none;">閲覧モードに戻る</button></div>
        </div>

        <div class="view-toggle">
            <button id="time-view-btn" class="active">タイムテーブル表示</button>
            <button id="person-view-btn">個人別表示</button>
        </div>
        <small id="edit-mode-info">タイムテーブル表示でのみ編集できます。ドラッグで編集できます。</small>

        <div id="time-view" class="schedule-view active">
            <div id="table-wrapper" class="table-wrapper">
            <table>
                <thead><tr><th>時間</th><?php foreach ($all_shifts as $s) echo "<th>".htmlspecialchars($s)."</th>";?><th class="break-col">休憩</th></tr></thead>
                <tbody>
                <?php foreach ($schedule_by_time as $time => $shifts): ?>
                <tr>
                    <td><b><?php echo $time; ?></b></td>
                    <?php foreach ($all_shifts as $shift_key): ?>
                        <?php 
                        $slot_info = $shifts[$shift_key] ?? null;
                        if ($slot_info) {
                            $is_understaffed = count($slot_info['members']) < $slot_info['needed'];
                            echo "<td class='" . ($is_understaffed ? 'understaffed' : '') . "'>";
                            echo "<ul class='member-list' data-req-id='" . $slot_info['req_id'] . "'>";
                            foreach ($slot_info['members'] as $member) { echo "<li class='member-item view-only' data-final-id='" . $member['final_id'] . "' data-member-name='" . htmlspecialchars($member['name']) . "'>" . htmlspecialchars($member['name']) . "</li>"; }
                            echo "</ul>";
                            echo "<small class='count-display' data-needed='" . $slot_info['needed'] . "'>(現在: " . count($slot_info['members']) . "人 / 必要: " . $slot_info['needed'] . "人)</small>";
                            echo "</td>";
                        } else { echo "<td class='empty-slot'>×</td>"; }
                        ?>
                    <?php endforeach; ?>
                    <td class="break-col">
                        <ul class="member-list" data-req-id="0">
                        <?php
                        $time_parts = explode(' - ', $time);
                        $current_slot_start = new DateTime($event_date . ' ' . $time_parts[0]);
                        $current_slot_end = new DateTime($event_date . ' ' . $time_parts[1]);
                        
                        $busy_members_now = [];
                        foreach($shifts as $s_info) { foreach($s_info['members'] as $m) { $busy_members_now[$m['name']] = true; } }
                        
                        $break_now = [];
                        foreach ($all_availabilities as $name => $avail_slots) {
                            if (isset($busy_members_now[$name])) continue;
                            foreach ($avail_slots as $avail) {
                                $avail_start = new DateTime($avail['start_time']);
                                $avail_end = new DateTime($avail['end_time']);
                                if ($avail_start <= $current_slot_start && $avail_end >= $current_slot_end) {
                                    $break_now[] = $name;
                                    break;
                                }
                            }
                        }
                        
                        if (count($break_now) > 5) {
                            echo "<details><summary>".count($break_now)."人が休憩中</summary>";
                            foreach($break_now as $b) { echo "<li class='member-item view-only' data-final-id='0' data-member-name='".htmlspecialchars($b)."'>".htmlspecialchars($b)."</li>"; }
                            echo "</details>";
                        } else {
                            foreach($break_now as $b) { echo "<li class='member-item view-only' data-final-id='0' data-member-name='".htmlspecialchars($b)."'>".htmlspecialchars($b)."</li>"; }
                        }
                        ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div id="person-view" class="schedule-view">
            <table>
                <thead><tr><th>名前</th><th>担当シフト</th></tr></thead>
                <tbody>
                <?php foreach ($schedule_by_person as $name => $shifts): ?>
                <tr>
                    <td><b><?php echo htmlspecialchars($name); ?></b></td>
                    <td><ul class="member-list">
                        <?php foreach($shifts as $shift) echo "<li>".htmlspecialchars($shift['shift'])."</li>";?>
                    </ul></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="unassigned-panel" class="unassigned-panel">
            <h3>休憩・未割り当てエリア</h3>
            <ul id="unassigned-list" class="member-list" data-req-id="0"></ul>
        </div>
    </div>

    <div id="member-modal" class="modal-overlay">
        <div class="modal-content">
            <span id="modal-close-btn" class="modal-close">&times;</span>
            <h3 id="modal-member-name"></h3>
            <div id="modal-member-details"></div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('edit-btn');
    const saveBtn = document.getElementById('save-btn');
    const editModeInfo = document.getElementById('edit-mode-info');
    let isEditMode = false;

    function enterEditMode() {
        if (!confirm('編集モードに移行します。よろしいですか？')) return;
        isEditMode = true;
        editBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
        editModeInfo.style.display = 'none';
        document.getElementById('unassigned-panel').style.display = 'block';
        document.querySelectorAll('.member-item').forEach(item => {
            item.classList.add('draggable');
            item.classList.remove('view-only');
            item.draggable = true;
        });
    }

    function exitEditMode() {
        if (!confirm('閲覧モードに移行します。よろしいですか？')) return;
        isEditMode = false;
        editBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
        editModeInfo.style.display = 'block';
        document.getElementById('unassigned-panel').style.display = 'none';
        document.querySelectorAll('.member-item').forEach(item => {
            item.classList.remove('draggable');
            item.classList.add('view-only');
            item.draggable = false;
        });
    }

    editBtn.addEventListener('click', enterEditMode);
    saveBtn.addEventListener('click', exitEditMode);
    
    function updateCounts(listElement) {
        if (!listElement) return;
        const display = listElement.nextElementSibling;
        if (display && display.classList.contains('count-display')) {
            const needed = display.dataset.needed;
            const current = listElement.children.length;
            display.textContent = `(現在: ${current}人 / 必要: ${needed}人)`;
            listElement.parentElement.classList.toggle('understaffed', current < needed);
        }
    }

    let draggedItem = null;
    let draggedItemOriginalParent = null;
    document.addEventListener('dragstart', (e) => {
        if (!isEditMode || !e.target.classList.contains('member-item')) return;
        draggedItem = e.target;
        draggedItemOriginalParent = draggedItem.parentNode;
        setTimeout(() => e.target.style.opacity = '0.5', 0);
    });

    document.addEventListener('dragend', () => {
        if (draggedItem) {
            setTimeout(() => {
                draggedItem.style.opacity = '1';
                draggedItem = null;
                draggedItemOriginalParent = null;
            }, 0);
        }
    });

    document.querySelectorAll('.member-list').forEach(list => {
        list.addEventListener('dragover', e => e.preventDefault());
        list.addEventListener('drop', e => {
            e.preventDefault();
            if (!draggedItem) return;
            const targetList = e.currentTarget;
            if (targetList === draggedItemOriginalParent) return;
            const toReqId = targetList.dataset.reqId;
            const finalId = draggedItem.dataset.finalId;
            const action = (toReqId === "0") ? 'unassign' : 'move';
            targetList.appendChild(draggedItem);
            updateCounts(draggedItemOriginalParent);
            updateCounts(targetList);
            fetch('actions/move_assignment_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=${action}&final_id=${finalId}&to_req_id=${toReqId}`
            })
            .then(response => { if (!response.ok) throw new Error('サーバー応答エラー'); return response.json(); })
            .then(data => {
                if (!data.success) {
                    alert('エラー: ' + (data.error || '不明なエラー'));
                    draggedItemOriginalParent.appendChild(draggedItem);
                    updateCounts(draggedItemOriginalParent);
                    updateCounts(targetList);
                }
            })
            .catch(error => {
                console.error('通信エラー:', error);
                alert('通信エラーが発生しました。変更は元に戻されます。');
                draggedItemOriginalParent.appendChild(draggedItem);
                updateCounts(draggedItemOriginalParent);
                updateCounts(targetList);
            });
        });
    });

    // ドラッグ中の横スクロール機能
    const tableWrapper = document.getElementById('table-wrapper');
    let scrollInterval = null;
    tableWrapper.addEventListener('dragover', function(e) {
        if (!isEditMode || !draggedItem) return;
        const rect = tableWrapper.getBoundingClientRect();
        const scrollZone = 60;
        clearInterval(scrollInterval);
        if (e.clientX < rect.left + scrollZone) {
            scrollInterval = setInterval(() => { tableWrapper.scrollLeft -= 15; }, 20);
        } else if (e.clientX > rect.right - scrollZone) {
            scrollInterval = setInterval(() => { tableWrapper.scrollLeft += 15; }, 20);
        }
    });
    document.addEventListener('dragend', () => { clearInterval(scrollInterval); });
    
    const timeViewBtn = document.getElementById('time-view-btn');
    const personViewBtn = document.getElementById('person-view-btn');
    const timeView = document.getElementById('time-view');
    const personView = document.getElementById('person-view');
    timeViewBtn.addEventListener('click', () => {
        timeView.classList.add('active'); personView.classList.remove('active');
        timeViewBtn.classList.add('active'); personViewBtn.classList.remove('active');
    });
    personViewBtn.addEventListener('click', () => {
        timeView.classList.remove('active'); personView.classList.add('active');
        timeViewBtn.classList.remove('active'); personViewBtn.classList.add('active');
    });

    // ポップアップ機能
    const memberModal = document.getElementById('member-modal');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalMemberName = document.getElementById('modal-member-name');
    const modalMemberDetails = document.getElementById('modal-member-details');
    document.querySelector('.container').addEventListener('click', function(e) {
        if (isEditMode || !e.target.classList.contains('member-item') || !e.target.dataset.memberName) return;
        const memberName = e.target.dataset.memberName;
        modalMemberName.textContent = memberName;
        modalMemberDetails.innerHTML = '読み込み中...';
        memberModal.style.display = 'flex';
        fetch(`actions/get_member_details.php?event_id=<?php echo $event_id; ?>&name=${encodeURIComponent(memberName)}`)
            .then(response => response.json())
            .then(data => {
                if(data.error){ modalMemberDetails.innerHTML = `<p style="color:red;">${data.error}</p>`; return; }
                let detailsHtml = `<p><strong>学年:</strong> ${data.grade || ''}</p><p><strong>PT名:</strong> ${data.party_name || '未所属'}</p><p><strong>属性:</strong> ${data.attributes || 'なし'}</p><p><strong>シフト可能時間:</strong> ${data.start_time} - ${data.end_time}</p>`;
                modalMemberDetails.innerHTML = detailsHtml;
            })
            .catch(() => modalMemberDetails.innerHTML = `<p style="color:red;">情報の取得に失敗しました。</p>`);
    });
    modalCloseBtn.addEventListener('click', () => memberModal.style.display = 'none');
    memberModal.addEventListener('click', (e) => { if (e.target === memberModal) { memberModal.style.display = 'none'; } });
});
</script>
</body>
</html>