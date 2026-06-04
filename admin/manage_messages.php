<?php

/**
 * KShops24 메시지 템플릿 및 발송 내역 관리 (manage_messages.php)
 * - 역할: 상점들에게 보낼 경고/알림 메시지 템플릿을 CRUD하고, 발송 내역을 조회함
 * - 실행: manage_site.php 탭 내에서 include 되어 실행됨
 */

// 독립 실행 차단
if (!isset($pdo)) exit;

// 1. 테이블 키 기본값 확인 및 생성 (없을 경우 빈 JSON으로 초기화)
try {
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'message_templates'");
    $stmt_check->execute();
    if ($stmt_check->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('message_templates', '{}')")->execute();
    }
} catch (Exception $e) {
}

// 2. Action 처리 (템플릿 추가/수정/삭제 및 내역 삭제)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg_action'])) {
    $action = $_POST['msg_action'];

    // 현재 템플릿 로드
    $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'message_templates'");
    $stmt_tpl->execute();
    $json_tpl = $stmt_tpl->fetchColumn();
    $templates = $json_tpl ? json_decode($json_tpl, true) : [];

    if ($action === 'add_template' || $action === 'edit_template') {
        $tpl_key = trim($_POST['tpl_key']);
        $tpl_title = trim($_POST['tpl_title']);
        $tpl_content = trim($_POST['tpl_content']);

        // 키가 빈 값이면 튕김
        if (!empty($tpl_key)) {
            $templates[$tpl_key] = [
                'title' => $tpl_title,
                'content' => $tpl_content
            ];

            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'message_templates'")
                ->execute([json_encode($templates, JSON_UNESCAPED_UNICODE)]);

            echo "<script>location.replace('admin_view.php?page=manage_site&view=manage_messages&msg=" . ($action == 'add_template' ? 'tpl_added' : 'tpl_edited') . "');</script>";
            exit;
        }
    }
}

if (isset($_GET['msg_action'])) {
    // 템플릿 삭제
    if ($_GET['msg_action'] === 'delete_template' && isset($_GET['tpl_key'])) {
        $tpl_key = $_GET['tpl_key'];

        $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'message_templates'");
        $stmt_tpl->execute();
        $json_tpl = $stmt_tpl->fetchColumn();
        $templates = $json_tpl ? json_decode($json_tpl, true) : [];

        if (isset($templates[$tpl_key])) {
            unset($templates[$tpl_key]);
            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'message_templates'")
                ->execute([json_encode($templates, JSON_UNESCAPED_UNICODE)]);
        }
        echo "<script>location.replace('admin_view.php?page=manage_site&view=manage_messages&msg=tpl_deleted');</script>";
        exit;
    }

    // 발송 내역 삭제
    if ($_GET['msg_action'] === 'delete_msg_log' && isset($_GET['id'])) {
        $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND sender_type = 'admin'")->execute([$_GET['id']]);

        // 검색 및 페이징 상태를 유지하여 리다이렉트
        $redirect_params = $_GET;
        unset($redirect_params['msg_action'], $redirect_params['id']);
        $redirect_params['msg'] = 'log_deleted';

        echo "<script>location.replace('admin_view.php?" . http_build_query($redirect_params) . "');</script>";
        exit;
    }
}

// 3. 데이터 로딩
// 3-1. 템플릿 로드
$stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'message_templates'");
$stmt_tpl->execute();
$json_tpl = $stmt_tpl->fetchColumn();
$message_templates = $json_tpl ? json_decode($json_tpl, true) : [];

// 3-2. 발송 내역 페이징 및 검색 로드
$msg_page = max(1, (int)($_GET['msg_page'] ?? 1));
$msg_limit = 10;
$msg_offset = ($msg_page - 1) * $msg_limit;

$f_shop = trim($_GET['f_shop'] ?? '');
$f_keyword = trim($_GET['f_keyword'] ?? '');
$f_start_date = trim($_GET['f_start_date'] ?? '');
$f_end_date = trim($_GET['f_end_date'] ?? '');

$where_clause = "WHERE b.type = 'message' AND b.sender_type = 'admin'";
$params = [];

if ($f_shop !== '') {
    $where_clause .= " AND (s.id = ? OR s.shop_name LIKE ? OR s.subdomain LIKE ?)";
    $params[] = $f_shop;
    $params[] = "%{$f_shop}%";
    $params[] = "%{$f_shop}%";
}
if ($f_keyword !== '') {
    $where_clause .= " AND (b.title LIKE ? OR b.content LIKE ?)";
    $params[] = "%{$f_keyword}%";
    $params[] = "%{$f_keyword}%";
}
if ($f_start_date !== '') {
    $where_clause .= " AND b.created_at >= ?";
    $params[] = $f_start_date . " 00:00:00";
}
if ($f_end_date !== '') {
    $where_clause .= " AND b.created_at <= ?";
    $params[] = $f_end_date . " 23:59:59";
}

$count_sql = "SELECT COUNT(*) FROM shop_board b LEFT JOIN shops s ON b.shop_id = s.id $where_clause";
$stmt_msg_count = $pdo->prepare($count_sql);
$stmt_msg_count->execute($params);
$total_msg_count = $stmt_msg_count->fetchColumn();
$total_msg_pages = ceil($total_msg_count / $msg_limit) ?: 1;

$msg_sql = "
    SELECT b.*, s.shop_name, s.subdomain 
    FROM shop_board b
    LEFT JOIN shops s ON b.shop_id = s.id
    $where_clause
    ORDER BY b.id DESC
    LIMIT $msg_limit OFFSET $msg_offset
";
$stmt_msg = $pdo->prepare($msg_sql);
$stmt_msg->execute($params);
$msg_logs = $stmt_msg->fetchAll();

// 페이징 및 삭제 링크에 사용할 쿼리 스트링
$query_string = http_build_query([
    'page' => 'manage_site',
    'view' => 'manage_messages',
    'f_shop' => $f_shop,
    'f_keyword' => $f_keyword,
    'f_start_date' => $f_start_date,
    'f_end_date' => $f_end_date
]);
?>

<div class="row g-4">
    <!-- 왼쪽: 템플릿 관리 -->
    <div class="col-md-5">
        <div class="settings-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="settings-title mb-0"><i class="bi bi-card-text"></i> 메시지 템플릿 관리</div>
                <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" onclick="openTemplateModal()"><i class="bi bi-plus-lg me-1"></i>새 템플릿</button>
            </div>

            <div class="alert alert-light border small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i> 상점 관리 페이지에서 메시지를 보낼 때 불러올 수 있는 기본 템플릿을 설정합니다.<br>
                <code class="ms-3">{shop_name}</code> : 상점명 치환<br>
                <code class="ms-3">{unpaid_amount}</code> : 연체액 치환<br>
                <code class="ms-3">{SHOP_CLOSED_AFTER_INACTIVE}</code> : "휴점" 후 "폐점" 전환 기간 등 (config.php에서 정의된 상수) 치환<br>
                <code class="ms-3">{now}</code> : 오늘 이시간 치환<br>
                <code class="ms-3">{today}</code> : 오늘 날짜 치환<br>
            </div>

            <div class="list-group list-group-flush border rounded shadow-sm">
                <?php if (empty($message_templates)): ?>
                    <div class="list-group-item text-center py-5 text-muted small">
                        <i class="bi bi-inboxes fs-2 d-block mb-2 opacity-50"></i>등록된 템플릿이 없습니다.
                    </div>
                <?php else: ?>
                    <?php foreach ($message_templates as $key => $tpl): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary">Key: <?= htmlspecialchars($key) ?></span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1"
                                        onclick="openTemplateModal('<?= htmlspecialchars($key, ENT_QUOTES) ?>', <?= htmlspecialchars(json_encode($tpl['title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tpl['content']), ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="admin_view.php?page=manage_site&view=manage_messages&msg_action=delete_template&tpl_key=<?= urlencode($key) ?>"
                                        class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('이 템플릿을 삭제하시겠습니까?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($tpl['title']) ?></h6>
                            <p class="mb-0 small text-muted text-truncate" style="max-height: 40px; white-space: pre-wrap; overflow: hidden;"><?= htmlspecialchars($tpl['content']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 발송 내역 -->
    <div class="col-md-7">
        <div class="settings-card h-100">
            <div class="settings-title mb-4"><i class="bi bi-send-check"></i> 본사 → 상점 메시지 발송 내역</div>

            <!-- 발송 내역 검색 폼 -->
            <form method="GET" action="admin_view.php" class="row g-2 mb-3 bg-light p-3 rounded border align-items-center mx-0">
                <input type="hidden" name="page" value="manage_site">
                <input type="hidden" name="view" value="manage_messages">

                <div class="col-md-3">
                    <input type="text" name="f_shop" class="form-control form-control-sm" placeholder="상점 ID / 상점명 / 도메인" value="<?= htmlspecialchars($f_shop) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="f_keyword" class="form-control form-control-sm" placeholder="제목 또는 내용 검색" value="<?= htmlspecialchars($f_keyword) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="f_start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($f_start_date) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="f_end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($f_end_date) ?>">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-dark btn-sm flex-grow-1 fw-bold">검색</button>
                    <a href="admin_view.php?page=manage_site&view=manage_messages" class="btn btn-outline-secondary btn-sm" title="검색 초기화"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div class="table-responsive bg-white border rounded shadow-sm">
                <table class="table table-ps24 table-hover align-middle mb-0">
                    <thead>
                        <tr class="small">
                            <th class="t-center" style="width: 120px;">수신 상점</th>
                            <th class="t-center">메시지 내용</th>
                            <th class="t-center" style="width: 120px;">발송 일시</th>
                            <th class="t-center" style="width: 60px;">관리</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($msg_logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">발송된 메시지 내역이 없습니다.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($msg_logs as $log): ?>
                                <tr>
                                    <td class="t-center">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['shop_name'] ?? '미지정') ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;">ID: #<?= $log['shop_id'] ?></div>
                                    </td>
                                    <td class="t-center">
                                        <div class="fw-bold text-dark mb-1 <?= empty($log['is_read']) ? 'text-primary' : '' ?>">
                                            <?php if (empty($log['is_read'])): ?><span class="badge bg-danger rounded-pill me-1" style="font-size:0.6rem;">안읽음</span><?php endif; ?>
                                            <?= htmlspecialchars($log['title']) ?>
                                        </div>
                                        <div class="text-muted lh-sm text-truncate" style="max-width: 250px; font-size:0.75rem;"><?= htmlspecialchars($log['content']) ?></div>
                                    </td>
                                    <td class="t-center text-muted" style="font-size: 0.75rem;"><?= date('y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                    <td class="t-center">
                                        <a href="admin_view.php?<?= $query_string ?>&msg_action=delete_msg_log&id=<?= $log['id'] ?>&msg_page=<?= $msg_page ?>"
                                            class="text-danger" onclick="return confirm('이 발송 내역을 삭제하시겠습니까?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이징 -->
            <?php if ($total_msg_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php for ($i = 1; $i <= $total_msg_pages; $i++): ?>
                            <li class="page-item <?= ($i == $msg_page ? 'active' : '') ?>">
                                <a class="page-link shadow-none border-0 mx-1 rounded-circle text-center" style="width: 30px;"
                                    href="admin_view.php?<?= $query_string ?>&msg_page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 메시지 템플릿 추가/수정 모달 -->
<div class="modal fade" id="msgTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg text-start" method="POST" action="admin_view.php?page=manage_site&view=manage_messages">
            <input type="hidden" name="msg_action" id="tpl_action" value="add_template">

            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="tpl_modal_title"><i class="bi bi-card-text me-2"></i>메시지 템플릿 등록</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">템플릿 식별 키 (Key)</label>
                    <input type="text" name="tpl_key" id="tpl_key" class="form-control bg-light" placeholder="영문/숫자 예: warning_fee" required>
                    <div class="form-text" style="font-size:0.75rem;">코드 내부에서 템플릿을 불러올 때 사용하는 고유 식별자입니다. (수정 시 변경 불가)</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">템플릿 제목 (메시지 타이틀)</label>
                    <input type="text" name="tpl_title" id="tpl_title" class="form-control" placeholder="예: [경고] 상점 연체 알림" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-dark">메시지 내용</label>
                    <textarea name="tpl_content" id="tpl_content" class="form-control" rows="6" placeholder="메시지 본문 내용을 입력하세요..." required></textarea>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">저장하기</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openTemplateModal(key = '', title = '', content = '') {
        const modalTitle = document.getElementById('tpl_modal_title');
        const actionInput = document.getElementById('tpl_action');
        const keyInput = document.getElementById('tpl_key');
        const titleInput = document.getElementById('tpl_title');
        const contentInput = document.getElementById('tpl_content');

        if (key) {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>메시지 템플릿 수정';
            actionInput.value = 'edit_template';
            keyInput.value = key;
            keyInput.readOnly = true; // 수정 시 키값 변경 불가
            titleInput.value = title;
            contentInput.value = content;
        } else {
            modalTitle.innerHTML = '<i class="bi bi-card-text me-2"></i>메시지 템플릿 등록';
            actionInput.value = 'add_template';
            keyInput.value = '';
            keyInput.readOnly = false;
            titleInput.value = '';
            contentInput.value = '';
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('msgTemplateModal')).show();
    }
</script>