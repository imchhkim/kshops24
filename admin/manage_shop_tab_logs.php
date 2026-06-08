<?php
/**
 * [탭 파일] 로그 관리
 * 위치: admin/manage_shop_tab_logs.php
 */
if (!isset($pdo)) exit;

// =========================================================================
// [1] Action 처리 (POST/GET)
// =========================================================================
if ($tab_mode === 'action') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action === 'add_log' && $shop_id > 0) {
            $date = str_replace('T', ' ', $_POST['log_date'] ?? date('Y-m-d H:i:s'));
            addShopHistoryLog($pdo, $shop_id, $_POST['log_type'] ?? 'info', $_POST['log_title'] ?? '', $_POST['log_content'] ?? '', $date);
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_added');</script>";
            exit;
        }
        if ($action === 'edit_log' && $shop_id > 0) {
            $log_index = (int)$_POST['log_index'];
            $date = str_replace('T', ' ', $_POST['log_date'] ?? date('Y-m-d H:i:s'));
            $stmt_shop = $pdo->prepare("SELECT history_log FROM shops WHERE id = ?");
            $stmt_shop->execute([$shop_id]);
            $history_array = json_decode($stmt_shop->fetchColumn() ?: '[]', true);
            if (isset($history_array[$log_index])) {
                $history_array[$log_index] = ['type' => $_POST['log_type'] ?? 'info', 'title' => $_POST['log_title'] ?? '', 'content' => $_POST['log_content'] ?? '', 'date' => $date];
                $pdo->prepare("UPDATE shops SET history_log = ? WHERE id = ?")->execute([json_encode($history_array, JSON_UNESCAPED_UNICODE), $shop_id]);
            }
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_edited');</script>";
            exit;
        }
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['log_index']) && $shop_id > 0) {
        $log_index = (int)$_GET['log_index'];
        if ($log_index >= 0) {
            $stmt_shop = $pdo->prepare("SELECT history_log FROM shops WHERE id = ?");
            $stmt_shop->execute([$shop_id]);
            $history_array = json_decode($stmt_shop->fetchColumn() ?: '[]', true);
            if (isset($history_array[$log_index])) {
                unset($history_array[$log_index]);
                $history_array = array_values($history_array);
                $pdo->prepare("UPDATE shops SET history_log = ? WHERE id = ?")->execute([json_encode($history_array, JSON_UNESCAPED_UNICODE), $shop_id]);
            }
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_deleted');</script>";
            exit;
        }
    }
}

// =========================================================================
// [3] View 렌더링
// =========================================================================
if ($tab_mode === 'view'):
?>
    <div class="card shadow-sm border-0 mb-3 bg-white p-3">
        <form method="GET" action="admin_view.php" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="manage_shop">
            <input type="hidden" name="id" value="<?= $shop_id ?>">
            <input type="hidden" name="view" value="logs">
            <div class="col"><div class="input-group input-group-sm"><span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span><input type="text" name="log_search" class="form-control border-0 bg-light shadow-none" placeholder="제목 또는 내용으로 검색..." value="<?= htmlspecialchars($_GET['log_search'] ?? '') ?>"></div></div>
            <div class="col-auto"><button type="submit" class="btn btn-dark btn-sm px-3 fw-bold">검색</button></div>
            <div class="col-auto"><a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a></div>
        </form>
    </div>

    <div class="card shadow-sm border-0 mb-4 border-start border-4 border-info p-3 bg-light text-dark">
        <h6 class="fw-bold mb-3 text-info"><i class="bi bi-clock-history me-2"></i>수동 로그 추가</h6>
        <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs" class="row g-2">
            <input type="hidden" name="action" value="add_log">
            <div class="col-md-2"><label class="small fw-bold mb-1">유형</label><select name="log_type" class="form-select form-select-sm border-0 shadow-sm"><option value="info">정보</option><option value="status">상태</option><option value="billing">결제</option><option value="message">메시지</option><option value="email">이메일</option></select></div>
            <div class="col-md-3"><label class="small fw-bold mb-1">제목</label><input type="text" name="log_title" class="form-control form-control-sm border-0 shadow-sm" required></div>
            <div class="col-md-5"><label class="small fw-bold mb-1">상세 내용</label><input type="text" name="log_content" class="form-control form-control-sm border-0 shadow-sm"></div>
            <div class="col-md-2"><label class="small fw-bold mb-1">일시</label><input type="datetime-local" name="log_date" class="form-control form-control-sm border-0 shadow-sm" value="<?= date('Y-m-d\TH:i') ?>" required></div>
            <div class="col-12 mt-2 text-end"><button type="submit" class="btn btn-info btn-sm text-white fw-bold px-4 shadow-sm">추가하기</button></div>
        </form>
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-white p-3">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ul me-2"></i>상점 히스토리 목록</h6>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light text-center small text-muted">
                    <tr><th width="15%">일시</th><th width="10%">유형</th><th width="25%" class="text-start">제목</th><th width="40%" class="text-start">상세 내용</th><th width="10%">관리</th></tr>
                </thead>
                <tbody class="small">
                    <?php
                    $log_search = trim($_GET['log_search'] ?? '');
                    $history_log = json_decode($s['history_log'] ?? '[]', true);
                    $filtered_history = [];
                    if (is_array($history_log) && !empty($history_log)) {
                        if ($log_search) { foreach ($history_log as $idx => $event) { if (stripos($event['title'] ?? '', $log_search) !== false || stripos($event['content'] ?? '', $log_search) !== false) $filtered_history[$idx] = $event; } } else { $filtered_history = $history_log; }
                    }
                    if (!empty($filtered_history)):
                        $reversed_keys = array_reverse(array_keys($filtered_history));
                        foreach ($reversed_keys as $idx):
                            $event = $filtered_history[$idx];
                            $date_val = htmlspecialchars($event['date'] ?? '');
                            $date_input_val = substr(str_replace(' ', 'T', $date_val), 0, 16);
                            $badge_color = match($event['type'] ?? '') { 'status' => 'bg-warning text-dark', 'billing' => 'bg-success', 'message' => 'bg-primary', 'email' => 'bg-info text-dark', default => 'bg-secondary' };
                    ?>
                            <tr><td class="text-center text-muted"><?= $date_val ?></td><td class="text-center"><span class="badge <?= $badge_color ?> fw-normal"><?= htmlspecialchars($event['type'] ?? 'info') ?></span></td><td class="text-start fw-bold text-dark"><?= htmlspecialchars($event['title'] ?? '') ?></td><td class="text-start text-muted"><div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer;" onclick="this.style.webkitLineClamp = this.style.webkitLineClamp === '2' ? 'unset' : '2';"><?= nl2br(htmlspecialchars($event['content'] ?? '')) ?></div></td><td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 btn-edit-log" data-idx="<?= $idx ?>" data-type="<?= htmlspecialchars($event['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-content="<?= htmlspecialchars($event['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-date="<?= $date_input_val ?>"><i class="bi bi-pencil"></i></button><a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&action=delete_log&log_index=<?= $idx ?>" class="btn btn-sm btn-outline-danger border-0 py-0 px-1" onclick="return confirm('삭제하시겠습니까?')"><i class="bi bi-trash"></i></a></td></tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><?= $log_search ? '검색된 로그가 없습니다.' : '기록된 히스토리가 없습니다.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- [모달] 로그 내역 수정 모달 -->
    <div class="modal fade" id="editLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs">
                    <div class="modal-header bg-info text-white border-0 py-3"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>로그 내역 수정</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body p-4"><input type="hidden" name="action" value="edit_log"><input type="hidden" name="log_index" id="edit_log_index">
                        <div class="mb-3"><label class="small mb-1 fw-bold">유형</label><select name="log_type" id="edit_log_type" class="form-select"><option value="info">정보</option><option value="status">상태</option><option value="billing">결제</option><option value="message">메시지</option><option value="email">이메일</option></select></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">제목</label><input type="text" name="log_title" id="edit_log_title" class="form-control" required></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">상세 내용</label><textarea name="log_content" id="edit_log_content" class="form-control" rows="4"></textarea></div>
                        <div class="mb-0"><label class="small mb-1 fw-bold">일시</label><input type="datetime-local" name="log_date" id="edit_log_date" class="form-control" required></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-info text-white px-5 fw-bold shadow-sm">수정 완료</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>