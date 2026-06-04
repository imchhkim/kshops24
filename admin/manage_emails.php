<<<<<<< HEAD
<?php

/**
 * KShops24 이메일 관리 (manage_emails.php)
 * - 역할: 상점들에게 보낼 이메일 템플릿 관리 및 발송 내역 조회
 * - 실행: manage_site.php 탭 내에서 include 되어 실행됨
 */

// [AJAX 단독 호출 지원] HTML 껍데기 없이 순수 통신만 가능하도록 개선
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// ---------------------------------------------------------
// 1. AJAX 및 Action 처리
// ---------------------------------------------------------

// [템플릿 저장] 이메일 템플릿은 모달창에서 에디터로 수정 후 비동기로 저장됨
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_save_template') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['content'] ?? '';
    try {
        $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_tpl->execute();
        $json_tpl = $stmt_tpl->fetchColumn();
        $templates = $json_tpl ? json_decode($json_tpl, true) : [];

        $templates[$key] = $value;
        $json_updated = json_encode($templates, JSON_UNESCAPED_UNICODE);

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'email_templates'")->execute([$json_updated]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_updated]);
        }
        echo "AJAX_SUCCESS";
    } catch (Exception $e) {
        echo "AJAX_ERROR: " . $e->getMessage();
    }
    exit;
}

// [템플릿 삭제] 이메일 템플릿 내용 비동기(AJAX) 초기화
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_delete_template') {
    while (ob_get_level()) ob_end_clean(); // 깔끔한 JSON 응답을 위해 앞선 출력 버퍼를 완벽히 제거
    $key = $_POST['key'] ?? '';
    try {
        $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_tpl->execute();
        $json_tpl = $stmt_tpl->fetchColumn();
        $templates = $json_tpl ? json_decode($json_tpl, true) : [];

        unset($templates[$key]); // 빈 값을 넣는 대신 아예 배열에서 키를 삭제시켜 DB 용량을 최적화
        $json_updated = json_encode($templates, JSON_UNESCAPED_UNICODE);

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'email_templates'")->execute([$json_updated]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_updated]);
        }
        echo "AJAX_SUCCESS";
    } catch (Exception $e) {
        echo "AJAX_ERROR: " . $e->getMessage();
    }
    exit;
}

// [내역 단일 삭제] 이메일 발송 내역 개별 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_email_log' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND type = ?")->execute([$_GET['id'], BOARD_TYPE_EMAIL_LOG]);

    // 상태 유지 리다이렉트
    $redirect_params = $_GET;
    unset($redirect_params['action'], $redirect_params['id']);
    $redirect_params['msg'] = 'email_log_deleted';

    echo "<script>location.replace('admin_view.php?" . http_build_query($redirect_params) . "');</script>";
    exit;
}

// [내역 일괄 삭제] 이메일 발송 실패 건 일괄 삭제
if (isset($_POST['delete_failed_emails'])) {
    $stmt = $pdo->prepare("DELETE FROM shop_board WHERE type = ? AND title LIKE ?");
    $stmt->execute([BOARD_TYPE_EMAIL_LOG, '%[발송 실패]%']);
    $count = $stmt->rowCount();

    $redirect_params = $_GET;
    $redirect_params['msg'] = 'failed_emails_deleted';
    $redirect_params['count'] = $count;

    echo "<script>location.replace('admin_view.php?" . http_build_query($redirect_params) . "');</script>";
    exit;
}

// ---------------------------------------------------------
// 2. 데이터 로딩 및 페이징/검색 준비
// ---------------------------------------------------------

// 이메일 템플릿 목록 정의 (홑따옴표를 제거하여 실제 상태 상수값 적용)
$email_types = [
    SHOP_STATUS_APPLYING   => '입점 신청 안내 이메일',
    SHOP_STATUS_TESTING    => '테스트 돌입 안내 이메일',
    SHOP_STATUS_ACTIVE     => '입점 완료/오픈 이메일',
    SHOP_STATUS_INACTIVE_SOON => '휴점 임박 알림 이메일',
    SHOP_STATUS_INACTIVE   => '휴점 알림 이메일',
    SHOP_STATUS_CLOSED_SOON => '폐점 임박 알림 이메일',
    SHOP_STATUS_CLOSED     => '폐점 알림 이메일',
    SHOP_STATUS_DELETED_SOON => '삭제 임박 알림 이메일',
    SHOP_STATUS_DELETED     => '삭제 알림 이메일'
];

// JSON 통합 템플릿 데이터 로드
$stmt_email_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
$stmt_email_tpl->execute();
$json_email_tpl = $stmt_email_tpl->fetchColumn();
$email_templates_data = $json_email_tpl ? json_decode($json_email_tpl, true) : [];

// 페이징 및 검색 변수 수집
$email_page = max(1, (int)($_GET['email_page'] ?? 1));
$email_limit = 10;
$email_offset = ($email_page - 1) * $email_limit;

$f_email_shop = trim($_GET['f_email_shop'] ?? '');
$f_email_status = trim($_GET['f_email_status'] ?? '');

$where_clause = "WHERE b.type = '" . BOARD_TYPE_EMAIL_LOG . "'";
$params = [];

if ($f_email_shop !== '') {
    $where_clause .= " AND (s.id = ? OR s.shop_name LIKE ? OR s.subdomain LIKE ?)";
    $params[] = $f_email_shop;
    $params[] = "%{$f_email_shop}%";
    $params[] = "%{$f_email_shop}%";
}
if ($f_email_status === 'failed') {
    $where_clause .= " AND b.title LIKE ?";
    $params[] = '%[발송 실패]%';
} elseif ($f_email_status === 'success') {
    $where_clause .= " AND b.title NOT LIKE ?";
    $params[] = '%[발송 실패]%';
}

// 내역 개수 및 페이지 계산
$count_sql = "SELECT COUNT(*) FROM shop_board b LEFT JOIN shops s ON b.shop_id = s.id $where_clause";
$stmt_email_count = $pdo->prepare($count_sql);
$stmt_email_count->execute($params);
$total_email_count = $stmt_email_count->fetchColumn();
$total_email_pages = ceil($total_email_count / $email_limit) ?: 1;

// 실제 내역 데이터 로드
$email_sql = "
    SELECT b.*, s.shop_name, s.subdomain 
    FROM shop_board b
    LEFT JOIN shops s ON b.shop_id = s.id
    $where_clause
    ORDER BY b.id DESC
    LIMIT $email_limit OFFSET $email_offset
";
$stmt_email = $pdo->prepare($email_sql);
$stmt_email->execute($params);
$email_logs = $stmt_email->fetchAll();

// 페이징 링크용 쿼리 스트링 조립
$query_string = http_build_query([
    'page' => 'manage_site',
    'view' => 'email',
    'f_email_shop' => $f_email_shop,
    'f_email_status' => $f_email_status
]);
?>

<div class="row g-4">
    <!-- 왼쪽: 이메일 템플릿 관리 -->
    <div class="col-md-5">
        <div class="settings-card h-100">
            <div class="settings-title mb-4"><i class="bi bi-envelope-open"></i> 자동 안내 이메일 템플릿</div>

            <div class="alert alert-light border small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i> 상점의 상태가 변경되거나 액션이 발생할 때 자동으로 발송되는 이메일들의 기본 양식을 설정합니다. manage_emails.php에 정의된 주요 시스템 알림 템플릿 외에도, 향후 필요에 따라 추가적인 템플릿이 이 목록에 자동으로 반영될 수 있습니다. 템플릿 내용을 비우면 해당 상황 발생 시 이메일이 발송되지 않으니 주의해주세요.
            </div>

            <div class="list-group list-group-flush border rounded shadow-sm">
                <?php foreach ($email_types as $key => $label): ?>
                    <?php
                    // 삭제 후 다시 에디터를 열었을 때 불필요한 텍스트가 주입되는 것을 방지하기 위해 빈 문자열 처리
                    $tpl_content = $email_templates_data[$key] ?? '';
                    ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <?= htmlspecialchars($label) ?>
                                    <span id="badge_<?= htmlspecialchars($key) ?>" class="ms-2 badge <?= !empty($tpl_content) ? 'bg-success' : 'bg-secondary opacity-50' ?> fw-normal" style="font-size:0.7rem;">
                                        <?= !empty($tpl_content) ? '사용중' : '내용없음' ?>
                                    </span>
                                </h6>
                                <span class="badge bg-secondary opacity-75">Key: <?= htmlspecialchars($key) ?></span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-bold me-1" onclick="openTemplateEditor('<?= htmlspecialchars($key, ENT_QUOTES) ?>', '<?= htmlspecialchars($label, ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteEmailTemplate(this, '<?= htmlspecialchars($key, ENT_QUOTES) ?>')" title="내용 비우기">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <textarea id="val_<?= htmlspecialchars($key) ?>" style="display:none;"><?= htmlspecialchars($tpl_content); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 발송 내역 -->
    <div class="col-md-7">
        <div class="settings-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="settings-title mb-0"><i class="bi bi-envelope-paper"></i> 자동 이메일 시스템 발송 내역</div>
                <form method="POST" action="admin_view.php?<?= $query_string ?>" class="mb-0" onsubmit="return confirm('발송 실패한 모든 내역을 영구 삭제하시겠습니까?');">
                    <button type="submit" name="delete_failed_emails" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-trash3 me-1"></i> 실패 건 일괄 삭제
                    </button>
                </form>
            </div>

            <!-- 발송 내역 검색 폼 -->
            <form method="GET" action="admin_view.php" class="row g-2 mb-3 bg-light p-3 rounded border align-items-center mx-0">
                <input type="hidden" name="page" value="manage_site">
                <input type="hidden" name="view" value="email">

                <div class="col-md-5">
                    <input type="text" name="f_email_shop" class="form-control form-control-sm" placeholder="상점 ID / 상점명 / 도메인" value="<?= htmlspecialchars($f_email_shop) ?>">
                </div>
                <div class="col-md-4">
                    <select name="f_email_status" class="form-select form-select-sm">
                        <option value="">전체 내역 보기</option>
                        <option value="failed" <?= $f_email_status == 'failed' ? 'selected' : '' ?>>발송 실패 건만 보기</option>
                        <option value="success" <?= $f_email_status == 'success' ? 'selected' : '' ?>>발송 성공 건만 보기</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-dark btn-sm flex-grow-1 fw-bold">검색</button>
                    <a href="admin_view.php?page=manage_site&view=manage_emails" class="btn btn-outline-secondary btn-sm" title="검색 초기화"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div class="table-responsive bg-white border rounded shadow-sm">
                <table class="table table-ps24 table-hover align-middle mb-0">
                    <thead>
                        <tr class="small">
                            <th class="t-center" style="width: 120px;">수신 상점</th>
                            <th class="t-center">메일 제목 / 요약</th>
                            <th class="t-center" style="width: 120px;">발송 일시</th>
                            <th class="t-center" style="width: 60px;">관리</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($email_logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">검색된 발송 내역이 없습니다.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($email_logs as $log): ?>
                                <?php $is_failed = (strpos($log['title'], '[발송 실패]') !== false); ?>
                                <tr class="<?= $is_failed ? 'table-danger' : '' ?>">
                                    <td class="t-center">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['shop_name'] ?? '미지정') ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;">ID: #<?= $log['shop_id'] ?></div>
                                    </td>
                                    <td class="t-center">
                                        <div class="fw-bold mb-1 <?= $is_failed ? 'text-danger' : 'text-dark' ?>">
                                            <?php if ($is_failed): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                            <?= htmlspecialchars($log['title']) ?>
                                        </div>
                                        <div class="text-muted lh-sm text-truncate <?= $is_failed ? 'fw-bold text-danger' : '' ?>" style="max-width: 250px; font-size:0.75rem;">
                                            <?= nl2br(htmlspecialchars($log['content'])) ?>
                                        </div>
                                    </td>
                                    <td class="t-center text-muted" style="font-size: 0.75rem;"><?= date('y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                    <td class="t-center">
                                        <a href="admin_view.php?<?= $query_string ?>&action=delete_email_log&id=<?= $log['id'] ?>&email_page=<?= $email_page ?>"
                                            class="text-danger" onclick="return confirm('이 발송 내역을 삭제하시겠습니까?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이징 -->
            <?php if ($total_email_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php for ($i = 1; $i <= $total_email_pages; $i++): ?>
                            <li class="page-item <?= ($i == $email_page ? 'active' : '') ?>">
                                <a class="page-link shadow-none border-0 mx-1 rounded-circle text-center" style="width: 30px;"
                                    href="admin_view.php?<?= $query_string ?>&email_page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Template Editor Modal -->
<div class="modal fade" id="templateEditorModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="templateEditorTitle"><i class="bi bi-envelope-paper me-2"></i>템플릿 수정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <textarea id="modal_template_editor"></textarea>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm" onclick="saveTemplateEditor()">반영하기</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentEditKey = '';
    $(document).ready(function() {
        $('#modal_template_editor').summernote({
            height: 500,
            iframe: true, // 에디터를 iframe으로 격리하여 CSS 유출 방지
            lang: 'ko-KR',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    });

    function openTemplateEditor(key, title) {
        currentEditKey = key;
        const content = $('#val_' + key).val();
        const modalEl = document.getElementById('templateEditorModal');
        const modal = new bootstrap.Modal(modalEl);

        $('#templateEditorTitle').html('<i class="bi bi-envelope-paper me-2"></i>' + title + ' 수정');

        // 모달이 완전히 화면에 나타난 후(shown) 에디터에 코드를 주입
        modalEl.addEventListener('shown.bs.modal', function() {
            $('#modal_template_editor').summernote('code', content);
        }, {
            once: true
        });

        modal.show();
    }

    function saveTemplateEditor() {
        const content = $('#modal_template_editor').summernote('code');
        const $btn = $('#templateEditorModal .btn-primary');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>저장 중...');

        // [수정] 다른 HTML이 섞이지 않도록 해당 파일로 직접 요청
        $.post('manage_emails.php', {
            action: 'ajax_save_template',
            key: currentEditKey,
            content: content
        }, function(data) {
            if (data.includes('AJAX_SUCCESS')) {
                $('#val_' + currentEditKey).val(content);
                if (content.trim() !== '') {
                    $('#badge_' + currentEditKey).removeClass('bg-secondary opacity-50').addClass('bg-success').text('사용중');
                } else {
                    $('#badge_' + currentEditKey).removeClass('bg-success').addClass('bg-secondary opacity-50').text('내용없음');
                }
                alert('이메일 템플릿 내용이 성공적으로 저장되었습니다.');
                bootstrap.Modal.getInstance(document.getElementById('templateEditorModal')).hide();
            } else {
                alert('저장 중 오류가 발생했습니다.');
            }
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    }

    function deleteEmailTemplate(btn, key) {
        if (!confirm('이 템플릿 내용을 삭제(비우기)하시겠습니까?\n\n※ 시스템 필수 알림 항목이므로 목록에서 사라지지는 않으며, 뱃지 상태가 [내용없음]으로 변경됩니다.\n(내용이 없으면 해당 상황 발생 시 이메일이 자동 발송되지 않습니다)')) return;

        const $btn = $(btn);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        // [수정] 다른 HTML이 섞이지 않도록 해당 파일로 직접 요청
        $.post('manage_emails.php', {
            action: 'ajax_delete_template',
            key: key
        }, function(data) {
            if (data.includes('AJAX_SUCCESS')) {
                $('#val_' + key).val(''); // 화면 상의 텍스트도 즉시 빈 값으로 초기화
                $('#badge_' + key).removeClass('bg-success').addClass('bg-secondary opacity-50').text('내용없음');
                alert('템플릿 내용이 성공적으로 비워졌습니다.\n(언제든 에디터로 다시 내용을 채울 수 있습니다)');
            } else {
                alert('삭제 중 오류가 발생했습니다.');
            }
        }).always(function() {
            $btn.prop('disabled', false).html(originalHtml);
        });
    }
=======
<?php

/**
 * KShops24 이메일 관리 (manage_emails.php)
 * - 역할: 상점들에게 보낼 이메일 템플릿 관리 및 발송 내역 조회
 * - 실행: manage_site.php 탭 내에서 include 되어 실행됨
 */

// [AJAX 단독 호출 지원] HTML 껍데기 없이 순수 통신만 가능하도록 개선
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// ---------------------------------------------------------
// 1. AJAX 및 Action 처리
// ---------------------------------------------------------

// [템플릿 저장] 이메일 템플릿은 모달창에서 에디터로 수정 후 비동기로 저장됨
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_save_template') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['content'] ?? '';
    try {
        $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_tpl->execute();
        $json_tpl = $stmt_tpl->fetchColumn();
        $templates = $json_tpl ? json_decode($json_tpl, true) : [];

        $templates[$key] = $value;
        $json_updated = json_encode($templates, JSON_UNESCAPED_UNICODE);

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'email_templates'")->execute([$json_updated]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_updated]);
        }
        echo "AJAX_SUCCESS";
    } catch (Exception $e) {
        echo "AJAX_ERROR: " . $e->getMessage();
    }
    exit;
}

// [템플릿 삭제] 이메일 템플릿 내용 비동기(AJAX) 초기화
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_delete_template') {
    while (ob_get_level()) ob_end_clean(); // 깔끔한 JSON 응답을 위해 앞선 출력 버퍼를 완벽히 제거
    $key = $_POST['key'] ?? '';
    try {
        $stmt_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_tpl->execute();
        $json_tpl = $stmt_tpl->fetchColumn();
        $templates = $json_tpl ? json_decode($json_tpl, true) : [];

        unset($templates[$key]); // 빈 값을 넣는 대신 아예 배열에서 키를 삭제시켜 DB 용량을 최적화
        $json_updated = json_encode($templates, JSON_UNESCAPED_UNICODE);

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'email_templates'")->execute([$json_updated]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_updated]);
        }
        echo "AJAX_SUCCESS";
    } catch (Exception $e) {
        echo "AJAX_ERROR: " . $e->getMessage();
    }
    exit;
}

// [내역 단일 삭제] 이메일 발송 내역 개별 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_email_log' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND type = ?")->execute([$_GET['id'], BOARD_TYPE_EMAIL_LOG]);

    // 상태 유지 리다이렉트
    $redirect_params = $_GET;
    unset($redirect_params['action'], $redirect_params['id']);
    $redirect_params['msg'] = 'email_log_deleted';

    echo "<script>location.replace('admin_view.php?" . http_build_query($redirect_params) . "');</script>";
    exit;
}

// [내역 일괄 삭제] 이메일 발송 실패 건 일괄 삭제
if (isset($_POST['delete_failed_emails'])) {
    $stmt = $pdo->prepare("DELETE FROM shop_board WHERE type = ? AND title LIKE ?");
    $stmt->execute([BOARD_TYPE_EMAIL_LOG, '%[발송 실패]%']);
    $count = $stmt->rowCount();

    $redirect_params = $_GET;
    $redirect_params['msg'] = 'failed_emails_deleted';
    $redirect_params['count'] = $count;

    echo "<script>location.replace('admin_view.php?" . http_build_query($redirect_params) . "');</script>";
    exit;
}

// ---------------------------------------------------------
// 2. 데이터 로딩 및 페이징/검색 준비
// ---------------------------------------------------------

// 이메일 템플릿 목록 정의 (홑따옴표를 제거하여 실제 상태 상수값 적용)
$email_types = [
    SHOP_STATUS_APPLYING   => '입점 신청 안내 이메일',
    SHOP_STATUS_TESTING    => '테스트 돌입 안내 이메일',
    SHOP_STATUS_ACTIVE     => '입점 완료/오픈 이메일',
    SHOP_STATUS_INACTIVE_SOON => '휴점 임박 알림 이메일',
    SHOP_STATUS_INACTIVE   => '휴점 알림 이메일',
    SHOP_STATUS_CLOSED_SOON => '폐점 임박 알림 이메일',
    SHOP_STATUS_CLOSED     => '폐점 알림 이메일',
    SHOP_STATUS_DELETED_SOON => '삭제 임박 알림 이메일',
    SHOP_STATUS_DELETED     => '삭제 알림 이메일'
];

// JSON 통합 템플릿 데이터 로드
$stmt_email_tpl = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
$stmt_email_tpl->execute();
$json_email_tpl = $stmt_email_tpl->fetchColumn();
$email_templates_data = $json_email_tpl ? json_decode($json_email_tpl, true) : [];

// 페이징 및 검색 변수 수집
$email_page = max(1, (int)($_GET['email_page'] ?? 1));
$email_limit = 10;
$email_offset = ($email_page - 1) * $email_limit;

$f_email_shop = trim($_GET['f_email_shop'] ?? '');
$f_email_status = trim($_GET['f_email_status'] ?? '');

$where_clause = "WHERE b.type = '" . BOARD_TYPE_EMAIL_LOG . "'";
$params = [];

if ($f_email_shop !== '') {
    $where_clause .= " AND (s.id = ? OR s.shop_name LIKE ? OR s.subdomain LIKE ?)";
    $params[] = $f_email_shop;
    $params[] = "%{$f_email_shop}%";
    $params[] = "%{$f_email_shop}%";
}
if ($f_email_status === 'failed') {
    $where_clause .= " AND b.title LIKE ?";
    $params[] = '%[발송 실패]%';
} elseif ($f_email_status === 'success') {
    $where_clause .= " AND b.title NOT LIKE ?";
    $params[] = '%[발송 실패]%';
}

// 내역 개수 및 페이지 계산
$count_sql = "SELECT COUNT(*) FROM shop_board b LEFT JOIN shops s ON b.shop_id = s.id $where_clause";
$stmt_email_count = $pdo->prepare($count_sql);
$stmt_email_count->execute($params);
$total_email_count = $stmt_email_count->fetchColumn();
$total_email_pages = ceil($total_email_count / $email_limit) ?: 1;

// 실제 내역 데이터 로드
$email_sql = "
    SELECT b.*, s.shop_name, s.subdomain 
    FROM shop_board b
    LEFT JOIN shops s ON b.shop_id = s.id
    $where_clause
    ORDER BY b.id DESC
    LIMIT $email_limit OFFSET $email_offset
";
$stmt_email = $pdo->prepare($email_sql);
$stmt_email->execute($params);
$email_logs = $stmt_email->fetchAll();

// 페이징 링크용 쿼리 스트링 조립
$query_string = http_build_query([
    'page' => 'manage_site',
    'view' => 'email',
    'f_email_shop' => $f_email_shop,
    'f_email_status' => $f_email_status
]);
?>

<div class="row g-4">
    <!-- 왼쪽: 이메일 템플릿 관리 -->
    <div class="col-md-5">
        <div class="settings-card h-100">
            <div class="settings-title mb-4"><i class="bi bi-envelope-open"></i> 자동 안내 이메일 템플릿</div>

            <div class="alert alert-light border small text-muted mb-3">
                <i class="bi bi-info-circle me-1"></i> 상점의 상태가 변경되거나 액션이 발생할 때 자동으로 발송되는 이메일들의 기본 양식을 설정합니다. manage_emails.php에 정의된 주요 시스템 알림 템플릿 외에도, 향후 필요에 따라 추가적인 템플릿이 이 목록에 자동으로 반영될 수 있습니다. 템플릿 내용을 비우면 해당 상황 발생 시 이메일이 발송되지 않으니 주의해주세요.
            </div>

            <div class="list-group list-group-flush border rounded shadow-sm">
                <?php foreach ($email_types as $key => $label): ?>
                    <?php
                    // 삭제 후 다시 에디터를 열었을 때 불필요한 텍스트가 주입되는 것을 방지하기 위해 빈 문자열 처리
                    $tpl_content = $email_templates_data[$key] ?? '';
                    ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <?= htmlspecialchars($label) ?>
                                    <span id="badge_<?= htmlspecialchars($key) ?>" class="ms-2 badge <?= !empty($tpl_content) ? 'bg-success' : 'bg-secondary opacity-50' ?> fw-normal" style="font-size:0.7rem;">
                                        <?= !empty($tpl_content) ? '사용중' : '내용없음' ?>
                                    </span>
                                </h6>
                                <span class="badge bg-secondary opacity-75">Key: <?= htmlspecialchars($key) ?></span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-bold me-1" onclick="openTemplateEditor('<?= htmlspecialchars($key, ENT_QUOTES) ?>', '<?= htmlspecialchars($label, ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteEmailTemplate(this, '<?= htmlspecialchars($key, ENT_QUOTES) ?>')" title="내용 비우기">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <textarea id="val_<?= htmlspecialchars($key) ?>" style="display:none;"><?= htmlspecialchars($tpl_content); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 발송 내역 -->
    <div class="col-md-7">
        <div class="settings-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="settings-title mb-0"><i class="bi bi-envelope-paper"></i> 자동 이메일 시스템 발송 내역</div>
                <form method="POST" action="admin_view.php?<?= $query_string ?>" class="mb-0" onsubmit="return confirm('발송 실패한 모든 내역을 영구 삭제하시겠습니까?');">
                    <button type="submit" name="delete_failed_emails" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-trash3 me-1"></i> 실패 건 일괄 삭제
                    </button>
                </form>
            </div>

            <!-- 발송 내역 검색 폼 -->
            <form method="GET" action="admin_view.php" class="row g-2 mb-3 bg-light p-3 rounded border align-items-center mx-0">
                <input type="hidden" name="page" value="manage_site">
                <input type="hidden" name="view" value="email">

                <div class="col-md-5">
                    <input type="text" name="f_email_shop" class="form-control form-control-sm" placeholder="상점 ID / 상점명 / 도메인" value="<?= htmlspecialchars($f_email_shop) ?>">
                </div>
                <div class="col-md-4">
                    <select name="f_email_status" class="form-select form-select-sm">
                        <option value="">전체 내역 보기</option>
                        <option value="failed" <?= $f_email_status == 'failed' ? 'selected' : '' ?>>발송 실패 건만 보기</option>
                        <option value="success" <?= $f_email_status == 'success' ? 'selected' : '' ?>>발송 성공 건만 보기</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-dark btn-sm flex-grow-1 fw-bold">검색</button>
                    <a href="admin_view.php?page=manage_site&view=manage_emails" class="btn btn-outline-secondary btn-sm" title="검색 초기화"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div class="table-responsive bg-white border rounded shadow-sm">
                <table class="table table-ps24 table-hover align-middle mb-0">
                    <thead>
                        <tr class="small">
                            <th class="t-center" style="width: 120px;">수신 상점</th>
                            <th class="t-center">메일 제목 / 요약</th>
                            <th class="t-center" style="width: 120px;">발송 일시</th>
                            <th class="t-center" style="width: 60px;">관리</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($email_logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">검색된 발송 내역이 없습니다.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($email_logs as $log): ?>
                                <?php $is_failed = (strpos($log['title'], '[발송 실패]') !== false); ?>
                                <tr class="<?= $is_failed ? 'table-danger' : '' ?>">
                                    <td class="t-center">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($log['shop_name'] ?? '미지정') ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;">ID: #<?= $log['shop_id'] ?></div>
                                    </td>
                                    <td class="t-center">
                                        <div class="fw-bold mb-1 <?= $is_failed ? 'text-danger' : 'text-dark' ?>">
                                            <?php if ($is_failed): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                            <?= htmlspecialchars($log['title']) ?>
                                        </div>
                                        <div class="text-muted lh-sm text-truncate <?= $is_failed ? 'fw-bold text-danger' : '' ?>" style="max-width: 250px; font-size:0.75rem;">
                                            <?= nl2br(htmlspecialchars($log['content'])) ?>
                                        </div>
                                    </td>
                                    <td class="t-center text-muted" style="font-size: 0.75rem;"><?= date('y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                    <td class="t-center">
                                        <a href="admin_view.php?<?= $query_string ?>&action=delete_email_log&id=<?= $log['id'] ?>&email_page=<?= $email_page ?>"
                                            class="text-danger" onclick="return confirm('이 발송 내역을 삭제하시겠습니까?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이징 -->
            <?php if ($total_email_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php for ($i = 1; $i <= $total_email_pages; $i++): ?>
                            <li class="page-item <?= ($i == $email_page ? 'active' : '') ?>">
                                <a class="page-link shadow-none border-0 mx-1 rounded-circle text-center" style="width: 30px;"
                                    href="admin_view.php?<?= $query_string ?>&email_page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Template Editor Modal -->
<div class="modal fade" id="templateEditorModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="templateEditorTitle"><i class="bi bi-envelope-paper me-2"></i>템플릿 수정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <textarea id="modal_template_editor"></textarea>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm" onclick="saveTemplateEditor()">반영하기</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentEditKey = '';
    $(document).ready(function() {
        $('#modal_template_editor').summernote({
            height: 500,
            iframe: true, // 에디터를 iframe으로 격리하여 CSS 유출 방지
            lang: 'ko-KR',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    });

    function openTemplateEditor(key, title) {
        currentEditKey = key;
        const content = $('#val_' + key).val();
        const modalEl = document.getElementById('templateEditorModal');
        const modal = new bootstrap.Modal(modalEl);

        $('#templateEditorTitle').html('<i class="bi bi-envelope-paper me-2"></i>' + title + ' 수정');

        // 모달이 완전히 화면에 나타난 후(shown) 에디터에 코드를 주입
        modalEl.addEventListener('shown.bs.modal', function() {
            $('#modal_template_editor').summernote('code', content);
        }, {
            once: true
        });

        modal.show();
    }

    function saveTemplateEditor() {
        const content = $('#modal_template_editor').summernote('code');
        const $btn = $('#templateEditorModal .btn-primary');
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>저장 중...');

        // [수정] 다른 HTML이 섞이지 않도록 해당 파일로 직접 요청
        $.post('manage_emails.php', {
            action: 'ajax_save_template',
            key: currentEditKey,
            content: content
        }, function(data) {
            if (data.includes('AJAX_SUCCESS')) {
                $('#val_' + currentEditKey).val(content);
                if (content.trim() !== '') {
                    $('#badge_' + currentEditKey).removeClass('bg-secondary opacity-50').addClass('bg-success').text('사용중');
                } else {
                    $('#badge_' + currentEditKey).removeClass('bg-success').addClass('bg-secondary opacity-50').text('내용없음');
                }
                alert('이메일 템플릿 내용이 성공적으로 저장되었습니다.');
                bootstrap.Modal.getInstance(document.getElementById('templateEditorModal')).hide();
            } else {
                alert('저장 중 오류가 발생했습니다.');
            }
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    }

    function deleteEmailTemplate(btn, key) {
        if (!confirm('이 템플릿 내용을 삭제(비우기)하시겠습니까?\n\n※ 시스템 필수 알림 항목이므로 목록에서 사라지지는 않으며, 뱃지 상태가 [내용없음]으로 변경됩니다.\n(내용이 없으면 해당 상황 발생 시 이메일이 자동 발송되지 않습니다)')) return;

        const $btn = $(btn);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        // [수정] 다른 HTML이 섞이지 않도록 해당 파일로 직접 요청
        $.post('manage_emails.php', {
            action: 'ajax_delete_template',
            key: key
        }, function(data) {
            if (data.includes('AJAX_SUCCESS')) {
                $('#val_' + key).val(''); // 화면 상의 텍스트도 즉시 빈 값으로 초기화
                $('#badge_' + key).removeClass('bg-success').addClass('bg-secondary opacity-50').text('내용없음');
                alert('템플릿 내용이 성공적으로 비워졌습니다.\n(언제든 에디터로 다시 내용을 채울 수 있습니다)');
            } else {
                alert('삭제 중 오류가 발생했습니다.');
            }
        }).always(function() {
            $btn.prop('disabled', false).html(originalHtml);
        });
    }
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>