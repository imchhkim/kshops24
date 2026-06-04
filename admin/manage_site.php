<<<<<<< HEAD
<?php

/**
 * KShops24 사이트 통합 설정 (admin/manage_site.php)
 * [Layered 최적화] admin_view.php 내부 포함용 (중복 태그 제거 및 경로 수정)
 */

// 1. 데이터 로직 처리
// ---------------------------------------------------------
// URL의 'view' 파라미터를 통해 현재 활성화될 탭(기본값: config)을 결정합니다.
$view = $_GET['view'] ?? 'config';

// ---------------------------------------------------------
// [AJAX 통신] 관리자 텔레그램 알림 테스트 발송
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_telegram') {
    $chat_id = trim($_POST['chat_id'] ?? '');

    if (empty($chat_id)) {
        echo "AJAX_ERROR: 수신받을 Chat ID를 입력해주세요.";
        exit;
    }

    $msg = "🔔 [KShops24 시스템 알림]\n관리자 텔레그램 연동 테스트가 성공적으로 완료되었습니다.";
    // lib_utils.php 의 안정적인 cURL 공용 함수 재활용
    $response = send_ps24_telegram($msg, $chat_id);
    $result = json_decode($response, true);

    echo ($result && isset($result['ok']) && $result['ok'] === true) ? "AJAX_SUCCESS" : "AJAX_ERROR: 발송 실패 - " . ($result['description'] ?? '알 수 없는 오류');
    exit;
}

// ---------------------------------------------------------
// A-1. 기본 사이트 설정 일괄 저장
// ---------------------------------------------------------
// '사이트 설정' 탭에서 넘어온 배열 형태의 데이터를 DB에 일괄 반영합니다.
if (isset($_POST['save_settings'])) {
    try {
        // 여러 개의 쿼리가 실행되므로, 데이터 무결성을 보장하기 위해 트랜잭션을 시작합니다.
        $pdo->beginTransaction();
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = ?");
        $stmt_update = $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES (?, ?)");

        // 넘어온 $_POST['settings'] 배열을 순회하며 Upsert 작업을 수행합니다.
        foreach ($_POST['settings'] as $key => $value) {
            // [추가] JSON 설정값(배열)이 넘어올 경우 문자열로 인코딩하여 저장
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $stmt_check->execute([$key]);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt_update->execute([$value, $key]);
            } else {
                $stmt_insert->execute([$key, $value]);
            }
        }
        // 모든 쿼리가 성공하면 DB에 최종 반영
        $pdo->commit();
        $section_name = $_POST['section_name'] ?? '사이트 전역 설정';
        $message = showAlert("{$section_name}이(가) 성공적으로 저장되었습니다.", "success");

        // [추가] 관리자의 정책 변경 행동 로그 기록
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, "{$section_name} 변경", $_POST['settings']);
        // }
    } catch (Exception $e) {
        // 오류 발생 시 변경 사항 롤백
        $pdo->rollBack();
        $message = showAlert("저장 실패: " . $e->getMessage(), "danger");
    }
}

// ---------------------------------------------------------
// A-2~6. 기타 처리 (관리자 추가/삭제, 공지사항 CRUD, 카테고리 관리 등)
// ---------------------------------------------------------
// * 찰리님의 기존 로직을 유지하되 Action 경로만 admin_view.php?page=manage_site&view=... 로 리다이렉션 되도록 처리됨 *

// [카테고리 관리] 카테고리 추가
if (isset($_POST['add_category'])) {
    try {
        $cate_key = trim($_POST['cate_key']);
        $cate_name = trim($_POST['cate_name']);
        
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'shop_categories'");
        $stmt_check->execute();
        $exists = $stmt_check->fetchColumn() > 0;
        
        $cats = [];
        if ($exists) {
            $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
            $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
        }
        
        if (isset($cats[$cate_key])) throw new Exception("이미 존재하는 Key입니다.");
        $cats[$cate_key] = $cate_name;
        
        $json_val = json_encode($cats, JSON_UNESCAPED_UNICODE);
        $exists ? $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")->execute([$json_val]) : $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('shop_categories', ?)")->execute([$json_val]);
        
        echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_added');</script>";
        exit;
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '상점 카테고리 추가', ['cate_key' => $_POST['cate_key'], 'cate_name' => $_POST['cate_name']]);
        // }
    } catch (Exception $e) {
        $message = showAlert("카테고리 추가 실패: " . $e->getMessage(), "danger");
    }
}

// [카테고리 관리] 카테고리명 수정
if (isset($_POST['edit_category'])) {
    try {
        $cate_key = trim($_POST['cate_key']);
        $new_name = trim($_POST['new_name']);
        
        $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
        $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
        
        if (!isset($cats[$cate_key])) throw new Exception("존재하지 않는 카테고리입니다.");
        $cats[$cate_key] = $new_name;
        
        $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")
            ->execute([json_encode($cats, JSON_UNESCAPED_UNICODE)]);
        echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_edited');</script>";
        exit;
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '상점 카테고리 수정', ['cate_key' => $cate_key, 'new_name' => $new_name]);
        // }
    } catch (Exception $e) {
        $message = showAlert("카테고리 수정 실패: " . $e->getMessage(), "danger");
    }
}

// [카테고리 관리] 카테고리 삭제
if (isset($_GET['del_cate'])) {
    $cate_key = $_GET['del_cate'];
    $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
    $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
    if (isset($cats[$cate_key])) {
        unset($cats[$cate_key]);
        $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")
            ->execute([json_encode($cats, JSON_UNESCAPED_UNICODE)]);
    }
    echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_deleted');</script>";
    exit;
}

// [계정 관리] 최고 관리자 또는 스태프 계정 추가
if (isset($_POST['add_admin'])) {
    // 비밀번호는 반드시 password_hash를 사용하여 단방향 암호화하여 저장합니다.
    $new_pass = password_hash($_POST['new_admin_pass'], PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO admins (admin_id, admin_pass, admin_name, admin_kakao_id) VALUES (?, ?, ?, ?)")
            ->execute([$_POST['new_admin_id'], $new_pass, $_POST['new_admin_name'], $_POST['new_admin_kakao']]);
        $message = showAlert("새 관리자가 추가되었습니다.", "success");
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '신규 관리자 계정 생성', ['admin_id' => $_POST['new_admin_id']]);
        // }
    } catch (Exception $e) {
        $message = showAlert("생성 실패", "danger");
    }
}

// [계정 관리] 관리자 삭제 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_admin' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$_GET['id']]);
    // if (function_exists('recordAdminAction')) {
    //     recordAdminAction($pdo, '관리자 계정 삭제', ['admin_table_id' => $_GET['id']]);
    // }
    echo "<script>location.replace('admin_view.php?page=manage_site&view=admins&msg=admin_deleted');</script>";
    exit;
}

// [공지사항 관리] 포털 메인에 노출될 전체 공지사항 추가/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_notice') {
        // 전체 공지사항의 경우 특정 상점에 귀속되지 않으므로 shop_id를 0으로 저장합니다.
        // sender_type='admin'으로 본사에서 올린 공지임을 명시합니다.
        $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content) VALUES (0, ?, 'admin', ?, ?)")
            ->execute([BOARD_TYPE_NOTICE, $_POST['title'], $_POST['content']]);
        $message = showAlert("공지사항 게시 완료", "success");
    } elseif ($_POST['action'] === 'edit_notice') {
        $pdo->prepare("UPDATE shop_board SET title = ?, content = ? WHERE id = ? AND shop_id = 0")
            ->execute([$_POST['title'], $_POST['content'], $_POST['id']]);
        $message = showAlert("공지사항 수정 완료", "success");
    }
}

// [공지사항 관리] 삭제 처리 (shop_id=0 검증을 통해 타 상점 게시글 삭제 방지)
if (isset($_GET['action']) && $_GET['action'] === 'delete_notice' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = 0")->execute([$_GET['id']]);
    $message = showAlert("공지사항 삭제 완료", "info");
}

// [로그 관리] 탭 전환이나 리다이렉트 후에도 사용자가 설정했던 필터 상태를 유지하기 위해 URL 파라미터를 구성합니다.
$filter_query = http_build_query([
    'log_type'   => $_GET['log_type'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date'] ?? ''
]);

// [로그 관리] 개별 로그 항목 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM site_logs WHERE id = ?")->execute([$_GET['id']]);
    // 삭제 처리 후, 사용자 경험을 위해 기존 검색 필터 조건을 물고 되돌아갑니다.
    $target_url = "admin_view.php?page=manage_site&view=logs&msg=log_deleted&" . $filter_query;
    echo "<script>location.replace('{$target_url}');</script>";
    exit;
}

// [로그 관리] 용량 관리를 위해 특정 날짜 이전의 오래된 로그들을 일괄 삭제합니다.
if (isset($_POST['delete_old_logs'])) {
    $before_date = $_POST['delete_before_date'] ?? '';
    if ($before_date) {
        $stmt = $pdo->prepare("DELETE FROM site_logs WHERE created_at < ?");
        $stmt->execute([$before_date . " 00:00:00"]);
        $count = $stmt->rowCount();
        // 처리 결과(삭제된 건수 등)를 파라미터로 전달하여 알림 메시지를 구성합니다.
        $target_url = "admin_view.php?page=manage_site&view=logs&msg=old_logs_deleted&count=$count&date=$before_date&" . $filter_query;
        echo "<script>location.replace('{$target_url}');</script>";
        exit;
    }
}

// [UI 알림] 리다이렉트 후 URL에 'msg' 파라미터가 있으면 알맞은 안내창을 띄워줍니다.
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'log_deleted') $message = showAlert("로그 기록이 삭제되었습니다.", "info");
    if ($_GET['msg'] === 'old_logs_deleted') {
        $count = $_GET['count'] ?? 0;
        $date = $_GET['date'] ?? '';
        $message = showAlert("{$date} 이전의 로그 {$count}건이 영구 삭제되었습니다.", "warning");
    }
    if ($_GET['msg'] === 'admin_deleted') {
        $message = showAlert("관리자 계정이 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'email_log_deleted') {
        $message = showAlert("이메일 발송 내역이 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'failed_emails_deleted') {
        $count = $_GET['count'] ?? 0;
        $message = showAlert("발송 실패한 이메일 내역 {$count}건이 일괄 삭제되었습니다.", "warning");
    }
    if ($_GET['msg'] === 'cate_deleted') {
        $message = showAlert("카테고리가 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'cate_added') {
        $message = showAlert("새 카테고리가 추가되었습니다.", "success");
    }
    if ($_GET['msg'] === 'cate_edited') {
        $message = showAlert("카테고리 이름이 수정되었습니다.", "success");
    }
    if ($_GET['msg'] === 'tpl_added') $message = showAlert("메시지 템플릿이 추가되었습니다.", "success");
    if ($_GET['msg'] === 'tpl_edited') $message = showAlert("메시지 템플릿이 수정되었습니다.", "success");
    if ($_GET['msg'] === 'tpl_deleted') $message = showAlert("메시지 템플릿이 삭제되었습니다.", "info");
}

// ---------------------------------------------------------
// 2. 데이터 로딩
// ---------------------------------------------------------
// 설정 테이블의 Key-Value 구조를 연관 배열 형태로 변환하여 뷰(HTML)에서 쓰기 쉽게 만듭니다.
$settings_raw = $pdo->query("SELECT * FROM site_settings")->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['set_key']] = $s['set_value'];
}

// [추가] JSON 형태로 저장된 초과 요금 정책(Tier Policy) 디코딩
$billing_policy = json_decode($settings['billing_tier_policy'] ?? '{"free_orders": 300, "overage_per_order": 5, "free_disk_mb": 1024, "overage_disk_unit_mb": 1024, "overage_disk_fee": 100, "free_db_mb": 50, "overage_db_unit_mb": 10, "overage_db_fee": 50}', true);
// 하위 호환성 처리 (기존에 저장된 overage_per_gb 값을 재사용)
$unit_mb = $billing_policy['overage_disk_unit_mb'] ?? 1024;
$disk_fee = $billing_policy['overage_disk_fee'] ?? ($billing_policy['overage_per_gb'] ?? 100);
$admins = $pdo->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
$stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
$json_cat = $stmt_cat->fetchColumn();
$categories = $json_cat ? json_decode($json_cat, true) : [];
$notices = ($view === 'notice') ? $pdo->query("SELECT * FROM shop_board WHERE shop_id = 0 AND type = '" . BOARD_TYPE_NOTICE . "' ORDER BY id DESC")->fetchAll() : [];

// [로그 탭 데이터 로딩] 사용자가 설정한 필터 조건에 맞게 WHERE 절을 동적으로 구성합니다.
$log_filter_type = $_GET['log_type'] ?? '';
$log_filter_start = $_GET['start_date'] ?? '';
$log_filter_end = $_GET['end_date'] ?? '';

$log_sql = "SELECT * FROM site_logs WHERE 1=1";
$log_params = [];

// 검색 조건 병합 (Prepared Statement 활용)
if ($log_filter_type) {
    $log_sql .= " AND log_type = ?";
    $log_params[] = $log_filter_type;
}
if ($log_filter_start) {
    $log_sql .= " AND created_at >= ?";
    $log_params[] = $log_filter_start . " 00:00:00";
}
if ($log_filter_end) {
    $log_sql .= " AND created_at <= ?";
    $log_params[] = $log_filter_end . " 23:59:59";
}
// 서버 과부하 방지를 위해 최신 100개까지만 노출합니다.
$log_sql .= " ORDER BY id DESC LIMIT 100";

$stmt_logs = $pdo->prepare($log_sql);
$stmt_logs->execute($log_params);
$site_logs = ($view === 'logs') ? $stmt_logs->fetchAll() : [];

?>

<style>
.settings-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.settings-title {
    <?=$UI_STYLE['section_title'] ?>display: flex;
    align-items: center;
}

.settings-title i {
    color: #4e73df;
    margin-right: 8px;
}

.form-label-custom {
    <?=$UI_STYLE['item_label'] ?>
}

.section-sub-text {
    <?=$UI_STYLE['section_sub'] ?>
}
</style>

<div class="site-management-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-end border-bottom mb-4 pb-0 gap-2">
        <ul class="nav nav-tabs border-bottom-0 mb-0">
            <li class="nav-item">
                <a class="nav-link <?= $view == 'config' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=config">사이트 설정</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_daily_reports' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_daily_reports">일별 리포트 관리</a>
            </li>            
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_monthly_reports' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_monthly_reports">월별 리포트 관리</a>
            </li>            

            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_messages' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_messages">메시지 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_emails' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_emails">이메일 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'logs' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=logs">로그 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'admins' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=admins">관리자 계정</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'notice' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=notice">공지사항 관리</a>
            </li>
        </ul>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <?php if ($view === 'config'): ?>
    <div class="row g-4">
        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="고객지원 채널">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-headset"></i> 고객지원 채널</div>
                    <div class="mb-3"><label class="form-label-custom">고객센터 카톡 ID</label><input type="text"
                            name="settings[cs_kakao_id]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_kakao_id'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label-custom">고객센터 연락처</label><input type="text"
                            name="settings[cs_phone]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_phone'] ?? ''); ?>"></div>
                    <div class="mb-0"><label class="form-label-custom">고객센터 이메일</label><input type="email"
                            name="settings[cs_email]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_email'] ?? ''); ?>"></div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="입점 및 유지 비용 정보">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-credit-card"></i> 입점 및 유지 비용 정보</div>
                    <div class="mb-3">
                        <label class="form-label-custom">가입비, 메모, 기준일</label>
                        <input type="number" name="settings[setup_fee]" class="form-control mb-2" placeholder="금액 (숫자만)"
                            value="<?= htmlspecialchars($settings['setup_fee'] ?? ''); ?>">
                        <input type="text" name="settings[setup_fee_info]" class="form-control mb-2"
                            value="<?= htmlspecialchars($settings['setup_fee_info'] ?? ''); ?>">
                        <input type="date" name="settings[setup_fee_date]" class="form-control"
                            value="<?= htmlspecialchars($settings['setup_fee_date'] ?? ''); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">유지비, 메모, 기준일</label>
                        <input type="number" name="settings[monthly_fee]" class="form-control mb-2"
                            placeholder="금액 (숫자만)" value="<?= htmlspecialchars($settings['monthly_fee'] ?? ''); ?>">
                        <input type="text" name="settings[monthly_fee_info]" class="form-control mb-2"
                            value="<?= htmlspecialchars($settings['monthly_fee_info'] ?? ''); ?>">
                        <input type="date" name="settings[monthly_fee_date]" class="form-control"
                            value="<?= htmlspecialchars($settings['monthly_fee_date'] ?? ''); ?>">
                    </div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="초과 요금 설정 및 프로세스 정책">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-speedometer2"></i> 초과 요금 설정 및 프로세스 정책</div>

                    <!-- 무료 주문 건수 정책 -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">* 무료 주문 건수</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="settings[billing_tier_policy][free_orders]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['free_orders']); ?>">
                                <span class="input-group-text bg-light text-muted">건/월</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">건당 초과 요금</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="settings[billing_tier_policy][overage_per_order]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['overage_per_order']); ?>">
                                <span class="input-group-text bg-light text-muted">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 무료 디스크 용량 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 무료 디스크 용량 정책</label>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="무료 디스크 용량">
                                <span class="input-group-text bg-light text-muted">무료</span>
                                <input type="number" name="settings[billing_tier_policy][free_disk_mb]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['free_disk_mb'] ?? 1024); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="초과 과금 단위 용량">
                                <span class="input-group-text bg-light text-muted px-1">초과</span>
                                <input type="number" name="settings[billing_tier_policy][overage_disk_unit_mb]"
                                    class="form-control" value="<?= htmlspecialchars($unit_mb); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB당</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="단위당 초과 요금">
                                <input type="number" name="settings[billing_tier_policy][overage_disk_fee]"
                                    class="form-control" value="<?= htmlspecialchars($disk_fee); ?>">
                                <span class="input-group-text bg-light text-muted px-1">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 무료 DB 용량 정책 -->
                    <div class="row g-2 mb-3">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 무료 DB 용량 정책</label>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="무료 DB 용량">
                                <span class="input-group-text bg-light text-muted">무료</span>
                                <input type="number" name="settings[billing_tier_policy][free_db_mb]" class="form-control" value="<?= htmlspecialchars($billing_policy['free_db_mb'] ?? 50); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="초과 과금 단위 용량">
                                <span class="input-group-text bg-light text-muted px-1">초과</span>
                                <input type="number" name="settings[billing_tier_policy][overage_db_unit_mb]" class="form-control" value="<?= htmlspecialchars($billing_policy['overage_db_unit_mb'] ?? 10); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB당</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="단위당 초과 요금">
                                <input type="number" name="settings[billing_tier_policy][overage_db_fee]" class="form-control" value="<?= htmlspecialchars($billing_policy['overage_db_fee'] ?? 50); ?>">
                                <span class="input-group-text bg-light text-muted px-1">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 과금 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 과금 정책</label>
                        </div>
                        <div class="col-12">
                            <div class="input-group input-group-sm" title="과금 정책">
                                <textarea name="settings[billing_process_policy]" class="form-control"
                                    rows="10"><?= htmlspecialchars($settings['billing_process_policy'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 상점 상태 프로세스 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 상점 상태 프로세스 정책</label>
                        </div>
                        <div class="col-12">
                            <div class="input-group input-group-sm" title="상점 상태 프로세스 정책">
                                <textarea name="settings[shop_status_process_policy]" class="form-control"
                                    rows="10"><?= htmlspecialchars($settings['shop_status_process_policy'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>

                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="관리자 텔레그램 알림">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title">
                        <i class="bi bi-telegram" style="color: #2CA5E0;"></i> 관리자 텔레그램 알림
                        <button type="button"
                            class="btn btn-sm btn-outline-info ms-auto rounded-pill px-3 fw-bold shadow-sm"
                            onclick="testAdminTelegram()">
                            <i class="bi bi-send-fill me-1"></i> 테스트 발송
                        </button>
                    </div>
                    <div class="mb-3"><label class="form-label-custom">Chat ID (채팅방 번호)</label><input type="text"
                            name="settings[admin_telegram_chat_id]" id="admin_telegram_chat_id" class="form-control"
                            value="<?= htmlspecialchars($settings['admin_telegram_chat_id'] ?? ''); ?>"
                            placeholder="예: 123456789"></div>
                    <div class="form-text small">
                        <i class="bi bi-info-circle me-1"></i>입점 신청, 시스템 에러 발생 등 본사 최고 관리자용 주요 알림을 수신합니다.<br>
                        <i class="bi bi-robot text-primary mt-1 me-1 d-inline-block"></i>발신용 봇 토큰은 <code>/config.php</code>의 설정값을 공통으로 사용합니다.
                    </div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <form method="POST" action="admin_view.php?page=manage_site&view=config">
                <input type="hidden" name="section_name" value="서비스 이용 약관 및 면책 조항">
                <div class="settings-card">
                    <div class="settings-title"><i class="bi bi-file-earmark-text"></i> 서비스 이용 약관 및 면책 조항</div>
                    <div class="mb-0">
                        <label class="form-label-custom">약관 본문 (입점 신청 화면에 표시됩니다)</label>
                        <textarea name="settings[terms_of_use]" id="terms_editor" class="form-control"
                            placeholder="서비스 이용 약관 내용을 입력하세요."><?= htmlspecialchars($settings['terms_of_use'] ?? ''); ?></textarea>
                        <div class="form-text mt-2">※ 에디터를 통해 작성한 서식(굵게, 색상 등)이 입점 신청 화면에 그대로 반영됩니다.</div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <form method="POST" action="admin_view.php?page=manage_site&view=config">
                <input type="hidden" name="section_name" value="개인정보 수집 및 이용 동의">
                <div class="settings-card">
                    <div class="settings-title"><i class="bi bi-file-earmark-text"></i> 개인정보 수집 및 이용 동의</div>
                    <div class="mb-0">
                        <label class="form-label-custom">약관 본문 (입점 신청 화면에 표시됩니다)</label>
                        <textarea name="settings[privacy_policy]" id="privacy_editor" class="form-control"
                            placeholder="개인정보 수집 및 이용 동의 내용을 입력하세요."><?= htmlspecialchars($settings['privacy_policy'] ?? ''); ?></textarea>
                        <div class="form-text mt-2">※ 에디터를 통해 작성한 서식(굵게, 색상 등)이 입점 신청 화면에 그대로 반영됩니다.</div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <div class="settings-card mt-3">
        <div class="settings-title"><i class="bi bi-grid-1x2"></i> 상점 카테고리</div>
        <form method="POST" action="admin_view.php?page=manage_site&view=config" class="row g-2 mb-4">
            <div class="col-md-4"><input type="text" name="cate_key" class="form-control" placeholder="Key (예: laundry)"
                    required></div>
            <div class="col-md-5"><input type="text" name="cate_name" class="form-control" placeholder="표시 이름" required>
            </div>
            <div class="col-md-3"><button type="submit" name="add_category"
                    class="btn btn-dark w-100 rounded-pill fw-bold">추가</button></div>
        </form>
        <table class="table table-ps24 table-hover align-middle">
            <thead>
                <tr class="small">
                    <th class="t-center">Key</th>
                    <th class="t-center">카테고리명</th>
                    <th class="t-center">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $key => $name): ?>
                <tr class="small">
                    <td class="t-center"><code><?= htmlspecialchars($key) ?></code></td>
                    <td class="t-center">
                        <form method="POST" action="admin_view.php?page=manage_site&view=config"
                            class="d-flex gap-1 m-0">
                            <input type="hidden" name="cate_key" value="<?= htmlspecialchars($key) ?>">
                            <input type="text" name="new_name" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($name) ?>" required>
                            <button type="submit" name="edit_category"
                                class="btn btn-sm btn-outline-secondary border-1">저장</button>
                        </form>
                    </td>
                    <td class="t-center"><a
                            href="admin_view.php?page=manage_site&view=config&del_cate=<?= urlencode($key) ?>"
                            class="text-danger" onclick="return confirm('삭제?')"><i class="bi bi-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($view === 'manage_emails'): ?>
    <?php
        // 새로운 통합 이메일 관리 파일 로드
        $manage_emails_file = __DIR__ . '/manage_emails.php';
        if (file_exists($manage_emails_file)) include $manage_emails_file;
        ?>

    <?php elseif ($view === 'notice'): ?>
    <div class="settings-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="settings-title mb-0"><i class="bi bi-megaphone"></i> 공지사항 관리</div>
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                data-bs-target="#addNoticeModal">+ 새 공지</button>
        </div>
        <table class="table table-ps24 table-hover align-middle">
            <thead>
                <tr class="small">
                    <th class="t-center" style="width:80px;">유형</th>
                    <th class="t-center">제목</th>
                    <th class="t-center" style="width:100px;">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notices as $n): ?>
                <tr class="small">
                    <td class="t-center">
                        <?= !empty($n['is_notice']) ? '<span class="badge bg-danger">중요</span>' : '<span class="text-muted small">일반</span>' ?>
                    </td>
                    <td class="t-center"><a href="javascript:void(0);"
                            data-notice='<?= htmlspecialchars(json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                            class="text-decoration-none text-dark fw-bold btn-edit-notice"><?= htmlspecialchars($n['title']) ?></a>
                    </td>
                    <td class="t-center">
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 btn-edit-notice"
                            data-notice='<?= htmlspecialchars(json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'><i
                                class="bi bi-pencil"></i></button>
                        <a href="admin_view.php?page=manage_site&view=notice&action=delete_notice&id=<?= $n['id'] ?>"
                            class="text-danger ms-2" onclick="return confirm('삭제?')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($view === 'logs'): ?>
    <div class="settings-card">
        <div class="settings-title mb-4"><i class="bi bi-terminal-split"></i> 시스템 로그 관리</div>

        <!-- 로그 필터 폼 -->
        <form method="GET" action="admin_view.php" class="row g-2 mb-4 p-3 bg-light rounded-3">
            <input type="hidden" name="page" value="manage_site">
            <input type="hidden" name="view" value="logs">
            <div class="col-md-2">
                <select name="log_type" class="form-select form-select-sm">
                    <option value="">모든 유형</option>
                    <?php foreach ($site_log_type_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $log_filter_type === $val ? 'selected' : '' ?>><?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm"
                    value="<?= $log_filter_start ?>"></div>
            <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm"
                    value="<?= $log_filter_end ?>"></div>
            <div class="col-md-3 d-flex gap-1">
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('today')">금일</button>
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('week')">1주일</button>
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('month')">1개월</button>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-dark btn-sm w-100">검색</button></div>
            <div class="col-md-1">
                <a href="admin_view.php?page=manage_site&view=logs" class="btn btn-outline-secondary btn-sm w-100"><i
                        class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>

        <div class="d-flex justify-content-end mb-3">
            <form method="POST" class="row g-2 align-items-center bg-light p-2 rounded-3 border"
                onsubmit="return confirm('선택한 날짜 이전의 모든 로그가 영구 삭제됩니다. 계속하시겠습니까?');">
                <div class="col-auto small fw-bold text-muted">이전 로그 삭제:</div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(1)">1개월
                            전</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(3)">3개월
                            전</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(6)">6개월
                            전</button>
                    </div>
                </div>
                <div class="col-auto">
                    <input type="date" name="delete_before_date" id="delete_before_date"
                        class="form-control form-control-sm" required title="이 날짜 이전의 로그를 삭제합니다.">
                </div>
                <div class="col-auto">
                    <button type="submit" name="delete_old_logs"
                        class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> 삭제 실행
                    </button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-ps24 table-hover align-middle">
                <thead>
                    <tr class="small">
                        <th class="t-center" style="width:100px;">유형</th>
                        <th>메시지 / 상세 정보</th>
                        <th class="t-center" style="width:120px;">접속 IP</th>
                        <th class="t-center" style="width:160px;">일시</th>
                        <th class="t-center" style="width:60px;">관리</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($site_logs as $log):
                            $badge_class = ($log['log_type'] === LOG_TYPE_EMAIL_FAIL || $log['log_type'] === LOG_TYPE_ERROR) ? 'bg-danger' : (($log['log_type'] === LOG_TYPE_ADMIN_ACTION) ? 'bg-warning text-dark fw-bold' : 'bg-info');
                        ?>
                    <tr>
                        <td class="t-center"><span
                                class="badge <?= $badge_class ?>"><?= strtoupper($log['log_type']) ?></span></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($log['message'] ?? '') ?></div>
                            <?php if ($log['details']): ?>
                            <div class="text-muted x-small mt-1" style="font-size:0.75rem;">
                                <?= $log['details'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="t-center text-muted"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                        <td class="t-center text-muted"><?= $log['created_at'] ?></td>
                        <td class="t-center">
                            <!-- 개별 삭제 시에도 현재 필터 조건을 함께 전달하여 리다이렉트 시 필터가 유지되도록 함 -->
                            <a href="admin_view.php?page=manage_site&view=logs&action=delete_log&id=<?= $log['id'] ?>&<?= $filter_query ?>"
                                class="text-danger" onclick="return confirm('삭제하시겠습니까?')"><i
                                    class="bi bi-x-circle"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($site_logs)) echo "<tr><td colspan='5' class='text-center py-5 text-muted'>기록된 로그가 없습니다.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($view === 'admins'): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="settings-card">
                <div class="settings-title"><i class="bi bi-shield-lock"></i> 관리자 계정 목록</div>
                <table class="table table-ps24 table-hover align-middle">
                    <thead>
                        <tr class="small">
                            <th class="t-center">ID</th>
                            <th class="t-center">이름</th>
                            <th class="t-center">카카오 ID</th>
                            <th class="t-center">비밀번호</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($admins as $a): ?>
                        <tr>
                            <td class="t-center fw-bold"><?= htmlspecialchars($a['admin_id']) ?></td>
                            <td class="t-center"><?= htmlspecialchars($a['admin_name']) ?></td>
                            <td class="t-center"><span
                                    class="badge bg-light text-dark border"><?= htmlspecialchars($a['admin_kakao_id'] ?? '-') ?></span>
                            </td>
                            <td class="t-center">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal"
                                    data-bs-target="#passModal<?= $a['id'] ?>">변경</button>
                                <?php if ($a['admin_id'] !== 'admin'): ?><a
                                    href="admin_view.php?page=manage_site&view=admins&action=delete_admin&id=<?= $a['id'] ?>"
                                    class="text-danger ms-2" onclick="return confirm('정말로 이 관리자를 삭제하시겠습니까?')"><i
                                        class="bi bi-trash"></i></a><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-4">
            <div class="settings-card">
                <div class="settings-title">관리자 추가</div>
                <form method="POST" action="admin_view.php?page=manage_site&view=admins">
                    <input type="text" name="new_admin_id" class="form-control mb-2" placeholder="아이디" required>
                    <input type="text" name="new_admin_name" class="form-control mb-2" placeholder="이름" required>
                    <input type="text" name="new_admin_kakao" class="form-control mb-2" placeholder="카카오톡 ID (알림용)">
                    <input type="password" name="new_admin_pass" class="form-control mb-3" placeholder="비밀번호" required>
                    <button type="submit" name="add_admin" class="btn btn-dark w-100 rounded-pill fw-bold">계정
                        생성</button>
                </form>
            </div>
        </div>
    </div>

    <?php elseif ($view === 'manage_messages'): ?>
    <?php
        // 새로운 메시지 관리 파일 로드
        $manage_messages_file = __DIR__ . '/manage_messages.php';
        if (file_exists($manage_messages_file)) include $manage_messages_file;
        ?>
    <?php elseif ($view === 'manage_daily_reports'): ?>
    <?php
        // 일별 리포트 관리 파일 로드
        $manage_daily_reports_file = __DIR__ . '/manage_daily_reports.php';
        if (file_exists($manage_daily_reports_file)) include $manage_daily_reports_file;
        ?>
    <?php elseif ($view === 'manage_monthly_reports'): ?>
    <?php
        // 월별 리포트 관리 파일 로드
        $manage_monthly_reports_file = __DIR__ . '/manage_monthly_reports.php';
        if (file_exists($manage_monthly_reports_file)) include $manage_monthly_reports_file;
        ?>                 
    <?php endif; ?>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST"
            action="admin_view.php?page=manage_site&view=notice">
            <input type="hidden" name="action" value="add_notice">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="fw-bold mb-0">새 공지사항 작성</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="text" name="title" class="form-control mb-3" placeholder="공지사항 제목을 입력하세요" required>
                <textarea name="content" class="form-control" rows="10" placeholder="공지 내용을 상세히 입력하세요"
                    required></textarea>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">게시하기</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="admin_view.php?page=manage_site&view=notice"><input
                type="hidden" name="action" value="edit_notice"><input type="hidden" name="id" id="edit_id">
            <div class="modal-header border-0">
                <h5 class="fw-bold">공지 수정</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><input type="text" name="title" id="edit_title" class="form-control mb-3" required>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                        name="is_notice" id="edit_is_notice"><label class="form-check-label" for="edit_is_notice">상단
                        고정</label></div><textarea name="content" id="edit_content" class="form-control" rows="10"
                    required></textarea>
            </div>
            <div class="modal-footer border-0"><button type="submit" class="btn btn-primary rounded-pill px-4">수정
                    완료</button></div>
        </form>
    </div>
</div>

<?php foreach ($admins as $a): ?>
<div class="modal fade" id="passModal<?= $a['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="admin_view.php?page=manage_site&view=admins">
            <div class="modal-header border-0">
                <h5 class="fw-bold">비번 변경: <?= $a['admin_id'] ?></h5><button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><input type="hidden" name="target_id" value="<?= $a['id'] ?>"><input type="password"
                    name="new_pass" class="form-control" placeholder="새 비밀번호" required></div>
            <div class="modal-footer border-0"><button type="submit" name="change_pass"
                    class="btn btn-primary rounded-pill px-4">변경 저장</button></div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
$(document).ready(function() {
    // Summernote 초기화
    if ($('#email_editor').length && typeof $.fn.summernote !== 'undefined') {
        $('#email_editor').summernote({
            height: 350,
            lang: 'ko-KR'
        });
    }

    // 이용 약관 및 개인정보 수집 동의 에디터 초기화 (공용)
    if ($('#terms_editor, #privacy_editor').length && typeof $.fn.summernote !== 'undefined') {
        $('#terms_editor, #privacy_editor').summernote({
            height: 400,
            lang: 'ko-KR',
            placeholder: '약관 및 동의 내용을 상세히 작성해주세요.',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }
});

// jQuery 이벤트 위임을 사용하여 동적으로 생성된 요소에도 이벤트 바인딩 및 데이터 파싱 오류 방지
$(document).on('click', '.btn-edit-notice', function() {
    let data = $(this).data('notice');
    if (typeof data === 'string') {
        data = JSON.parse(data);
    }
    // 공지사항 수정 버튼 클릭 시 모달창의 각 입력 필드에 기존 데이터를 채워 넣습니다.
    $('#edit_id').val(data.id);
    $('#edit_title').val(data.title);
    $('#edit_content').val(data.content);
    $('#edit_is_notice').prop('checked', data.is_notice == 1);
    $('#editNoticeModal').modal('show');
});

// [관리자 텔레그램 테스트 발송]
function testAdminTelegram() {
    const chatId = $('#admin_telegram_chat_id').val();

    if (!chatId) {
        alert('테스트를 위해 수신받을 Chat ID를 입력해주세요.');
        return;
    }

    const $btn = $('.settings-title .btn-outline-info');
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>발송 중...');

    $.post('admin_view.php?page=manage_site&view=config', {
        action: 'test_telegram',
        chat_id: chatId
    }, function(data) {
        if (data.includes('AJAX_SUCCESS')) alert('테스트 메시지가 성공적으로 전송되었습니다!\\n스마트폰의 텔레그램 앱을 확인해보세요.');
        else alert(data.replace('AJAX_ERROR: ', ''));
    }).always(function() {
        $btn.prop('disabled', false).html(originalText);
    });
}

// [UI 편의성] 로그 기간 설정 함수 ('금일', '1주일', '1개월' 버튼 클릭 시 자동 입력)
function setLogPeriod(period) {
    const startInput = document.querySelector('input[name="start_date"]');
    const endInput = document.querySelector('input[name="end_date"]');
    if (!startInput || !endInput) return;

    const now = new Date();
    const formatDate = (date) => {
        return date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate())
            .slice(-2);
    };

    const endStr = formatDate(now);
    let start = new Date(now);

    if (period === 'week') start.setDate(now.getDate() - 7);
    else if (period === 'month') start.setMonth(now.getMonth() - 1);

    startInput.value = formatDate(start);
    endInput.value = endStr;
}

// [UI 편의성] 오래된 로그 삭제를 위해 특정 개월 수 이전의 날짜를 자동 계산하여 입력합니다.
function setDeleteBeforeDate(months) {
    const input = document.getElementById('delete_before_date');
    if (!input) return;
    const now = new Date();
    now.setMonth(now.getMonth() - months);
    const year = now.getFullYear();
    const month = ('0' + (now.getMonth() + 1)).slice(-2);
    const day = ('0' + now.getDate()).slice(-2);
    input.value = `${year}-${month}-${day}`;
}
=======
<?php

/**
 * KShops24 사이트 통합 설정 (admin/manage_site.php)
 * [Layered 최적화] admin_view.php 내부 포함용 (중복 태그 제거 및 경로 수정)
 */

// 1. 데이터 로직 처리
// ---------------------------------------------------------
// URL의 'view' 파라미터를 통해 현재 활성화될 탭(기본값: config)을 결정합니다.
$view = $_GET['view'] ?? 'config';

// ---------------------------------------------------------
// [AJAX 통신] 관리자 텔레그램 알림 테스트 발송
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_telegram') {
    $chat_id = trim($_POST['chat_id'] ?? '');

    if (empty($chat_id)) {
        echo "AJAX_ERROR: 수신받을 Chat ID를 입력해주세요.";
        exit;
    }

    $msg = "🔔 [KShops24 시스템 알림]\n관리자 텔레그램 연동 테스트가 성공적으로 완료되었습니다.";
    // lib_utils.php 의 안정적인 cURL 공용 함수 재활용
    $response = send_ps24_telegram($msg, $chat_id);
    $result = json_decode($response, true);

    echo ($result && isset($result['ok']) && $result['ok'] === true) ? "AJAX_SUCCESS" : "AJAX_ERROR: 발송 실패 - " . ($result['description'] ?? '알 수 없는 오류');
    exit;
}

// ---------------------------------------------------------
// A-1. 기본 사이트 설정 일괄 저장
// ---------------------------------------------------------
// '사이트 설정' 탭에서 넘어온 배열 형태의 데이터를 DB에 일괄 반영합니다.
if (isset($_POST['save_settings'])) {
    try {
        // 여러 개의 쿼리가 실행되므로, 데이터 무결성을 보장하기 위해 트랜잭션을 시작합니다.
        $pdo->beginTransaction();
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = ?");
        $stmt_update = $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES (?, ?)");

        // 넘어온 $_POST['settings'] 배열을 순회하며 Upsert 작업을 수행합니다.
        foreach ($_POST['settings'] as $key => $value) {
            // [추가] JSON 설정값(배열)이 넘어올 경우 문자열로 인코딩하여 저장
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $stmt_check->execute([$key]);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt_update->execute([$value, $key]);
            } else {
                $stmt_insert->execute([$key, $value]);
            }
        }
        // 모든 쿼리가 성공하면 DB에 최종 반영
        $pdo->commit();
        $section_name = $_POST['section_name'] ?? '사이트 전역 설정';
        $message = showAlert("{$section_name}이(가) 성공적으로 저장되었습니다.", "success");

        // [추가] 관리자의 정책 변경 행동 로그 기록
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, "{$section_name} 변경", $_POST['settings']);
        // }
    } catch (Exception $e) {
        // 오류 발생 시 변경 사항 롤백
        $pdo->rollBack();
        $message = showAlert("저장 실패: " . $e->getMessage(), "danger");
    }
}

// ---------------------------------------------------------
// A-2~6. 기타 처리 (관리자 추가/삭제, 공지사항 CRUD, 카테고리 관리 등)
// ---------------------------------------------------------
// * 찰리님의 기존 로직을 유지하되 Action 경로만 admin_view.php?page=manage_site&view=... 로 리다이렉션 되도록 처리됨 *

// [카테고리 관리] 카테고리 추가
if (isset($_POST['add_category'])) {
    try {
        $cate_key = trim($_POST['cate_key']);
        $cate_name = trim($_POST['cate_name']);
        
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'shop_categories'");
        $stmt_check->execute();
        $exists = $stmt_check->fetchColumn() > 0;
        
        $cats = [];
        if ($exists) {
            $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
            $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
        }
        
        if (isset($cats[$cate_key])) throw new Exception("이미 존재하는 Key입니다.");
        $cats[$cate_key] = $cate_name;
        
        $json_val = json_encode($cats, JSON_UNESCAPED_UNICODE);
        $exists ? $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")->execute([$json_val]) : $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('shop_categories', ?)")->execute([$json_val]);
        
        echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_added');</script>";
        exit;
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '상점 카테고리 추가', ['cate_key' => $_POST['cate_key'], 'cate_name' => $_POST['cate_name']]);
        // }
    } catch (Exception $e) {
        $message = showAlert("카테고리 추가 실패: " . $e->getMessage(), "danger");
    }
}

// [카테고리 관리] 카테고리명 수정
if (isset($_POST['edit_category'])) {
    try {
        $cate_key = trim($_POST['cate_key']);
        $new_name = trim($_POST['new_name']);
        
        $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
        $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
        
        if (!isset($cats[$cate_key])) throw new Exception("존재하지 않는 카테고리입니다.");
        $cats[$cate_key] = $new_name;
        
        $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")
            ->execute([json_encode($cats, JSON_UNESCAPED_UNICODE)]);
        echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_edited');</script>";
        exit;
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '상점 카테고리 수정', ['cate_key' => $cate_key, 'new_name' => $new_name]);
        // }
    } catch (Exception $e) {
        $message = showAlert("카테고리 수정 실패: " . $e->getMessage(), "danger");
    }
}

// [카테고리 관리] 카테고리 삭제
if (isset($_GET['del_cate'])) {
    $cate_key = $_GET['del_cate'];
    $stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
    $cats = json_decode($stmt_cat->fetchColumn() ?: '{}', true);
    if (isset($cats[$cate_key])) {
        unset($cats[$cate_key]);
        $pdo->prepare("UPDATE site_settings SET set_value = ? WHERE set_key = 'shop_categories'")
            ->execute([json_encode($cats, JSON_UNESCAPED_UNICODE)]);
    }
    echo "<script>location.replace('admin_view.php?page=manage_site&view=config&msg=cate_deleted');</script>";
    exit;
}

// [계정 관리] 최고 관리자 또는 스태프 계정 추가
if (isset($_POST['add_admin'])) {
    // 비밀번호는 반드시 password_hash를 사용하여 단방향 암호화하여 저장합니다.
    $new_pass = password_hash($_POST['new_admin_pass'], PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO admins (admin_id, admin_pass, admin_name, admin_kakao_id) VALUES (?, ?, ?, ?)")
            ->execute([$_POST['new_admin_id'], $new_pass, $_POST['new_admin_name'], $_POST['new_admin_kakao']]);
        $message = showAlert("새 관리자가 추가되었습니다.", "success");
        // if (function_exists('recordAdminAction')) {
        //     recordAdminAction($pdo, '신규 관리자 계정 생성', ['admin_id' => $_POST['new_admin_id']]);
        // }
    } catch (Exception $e) {
        $message = showAlert("생성 실패", "danger");
    }
}

// [계정 관리] 관리자 삭제 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_admin' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$_GET['id']]);
    // if (function_exists('recordAdminAction')) {
    //     recordAdminAction($pdo, '관리자 계정 삭제', ['admin_table_id' => $_GET['id']]);
    // }
    echo "<script>location.replace('admin_view.php?page=manage_site&view=admins&msg=admin_deleted');</script>";
    exit;
}

// [공지사항 관리] 포털 메인에 노출될 전체 공지사항 추가/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_notice') {
        // 전체 공지사항의 경우 특정 상점에 귀속되지 않으므로 shop_id를 0으로 저장합니다.
        // sender_type='admin'으로 본사에서 올린 공지임을 명시합니다.
        $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content) VALUES (0, ?, 'admin', ?, ?)")
            ->execute([BOARD_TYPE_NOTICE, $_POST['title'], $_POST['content']]);
        $message = showAlert("공지사항 게시 완료", "success");
    } elseif ($_POST['action'] === 'edit_notice') {
        $pdo->prepare("UPDATE shop_board SET title = ?, content = ? WHERE id = ? AND shop_id = 0")
            ->execute([$_POST['title'], $_POST['content'], $_POST['id']]);
        $message = showAlert("공지사항 수정 완료", "success");
    }
}

// [공지사항 관리] 삭제 처리 (shop_id=0 검증을 통해 타 상점 게시글 삭제 방지)
if (isset($_GET['action']) && $_GET['action'] === 'delete_notice' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = 0")->execute([$_GET['id']]);
    $message = showAlert("공지사항 삭제 완료", "info");
}

// [로그 관리] 탭 전환이나 리다이렉트 후에도 사용자가 설정했던 필터 상태를 유지하기 위해 URL 파라미터를 구성합니다.
$filter_query = http_build_query([
    'log_type'   => $_GET['log_type'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date'] ?? ''
]);

// [로그 관리] 개별 로그 항목 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM site_logs WHERE id = ?")->execute([$_GET['id']]);
    // 삭제 처리 후, 사용자 경험을 위해 기존 검색 필터 조건을 물고 되돌아갑니다.
    $target_url = "admin_view.php?page=manage_site&view=logs&msg=log_deleted&" . $filter_query;
    echo "<script>location.replace('{$target_url}');</script>";
    exit;
}

// [로그 관리] 용량 관리를 위해 특정 날짜 이전의 오래된 로그들을 일괄 삭제합니다.
if (isset($_POST['delete_old_logs'])) {
    $before_date = $_POST['delete_before_date'] ?? '';
    if ($before_date) {
        $stmt = $pdo->prepare("DELETE FROM site_logs WHERE created_at < ?");
        $stmt->execute([$before_date . " 00:00:00"]);
        $count = $stmt->rowCount();
        // 처리 결과(삭제된 건수 등)를 파라미터로 전달하여 알림 메시지를 구성합니다.
        $target_url = "admin_view.php?page=manage_site&view=logs&msg=old_logs_deleted&count=$count&date=$before_date&" . $filter_query;
        echo "<script>location.replace('{$target_url}');</script>";
        exit;
    }
}

// [UI 알림] 리다이렉트 후 URL에 'msg' 파라미터가 있으면 알맞은 안내창을 띄워줍니다.
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'log_deleted') $message = showAlert("로그 기록이 삭제되었습니다.", "info");
    if ($_GET['msg'] === 'old_logs_deleted') {
        $count = $_GET['count'] ?? 0;
        $date = $_GET['date'] ?? '';
        $message = showAlert("{$date} 이전의 로그 {$count}건이 영구 삭제되었습니다.", "warning");
    }
    if ($_GET['msg'] === 'admin_deleted') {
        $message = showAlert("관리자 계정이 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'email_log_deleted') {
        $message = showAlert("이메일 발송 내역이 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'failed_emails_deleted') {
        $count = $_GET['count'] ?? 0;
        $message = showAlert("발송 실패한 이메일 내역 {$count}건이 일괄 삭제되었습니다.", "warning");
    }
    if ($_GET['msg'] === 'cate_deleted') {
        $message = showAlert("카테고리가 삭제되었습니다.", "info");
    }
    if ($_GET['msg'] === 'cate_added') {
        $message = showAlert("새 카테고리가 추가되었습니다.", "success");
    }
    if ($_GET['msg'] === 'cate_edited') {
        $message = showAlert("카테고리 이름이 수정되었습니다.", "success");
    }
    if ($_GET['msg'] === 'tpl_added') $message = showAlert("메시지 템플릿이 추가되었습니다.", "success");
    if ($_GET['msg'] === 'tpl_edited') $message = showAlert("메시지 템플릿이 수정되었습니다.", "success");
    if ($_GET['msg'] === 'tpl_deleted') $message = showAlert("메시지 템플릿이 삭제되었습니다.", "info");
}

// ---------------------------------------------------------
// 2. 데이터 로딩
// ---------------------------------------------------------
// 설정 테이블의 Key-Value 구조를 연관 배열 형태로 변환하여 뷰(HTML)에서 쓰기 쉽게 만듭니다.
$settings_raw = $pdo->query("SELECT * FROM site_settings")->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['set_key']] = $s['set_value'];
}

// [추가] JSON 형태로 저장된 초과 요금 정책(Tier Policy) 디코딩
$billing_policy = json_decode($settings['billing_tier_policy'] ?? '{"free_orders": 300, "overage_per_order": 5, "free_disk_mb": 1024, "overage_disk_unit_mb": 1024, "overage_disk_fee": 100, "free_db_mb": 50, "overage_db_unit_mb": 10, "overage_db_fee": 50}', true);
// 하위 호환성 처리 (기존에 저장된 overage_per_gb 값을 재사용)
$unit_mb = $billing_policy['overage_disk_unit_mb'] ?? 1024;
$disk_fee = $billing_policy['overage_disk_fee'] ?? ($billing_policy['overage_per_gb'] ?? 100);
$admins = $pdo->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
$stmt_cat = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
$json_cat = $stmt_cat->fetchColumn();
$categories = $json_cat ? json_decode($json_cat, true) : [];
$notices = ($view === 'notice') ? $pdo->query("SELECT * FROM shop_board WHERE shop_id = 0 AND type = '" . BOARD_TYPE_NOTICE . "' ORDER BY id DESC")->fetchAll() : [];

// [로그 탭 데이터 로딩] 사용자가 설정한 필터 조건에 맞게 WHERE 절을 동적으로 구성합니다.
$log_filter_type = $_GET['log_type'] ?? '';
$log_filter_start = $_GET['start_date'] ?? '';
$log_filter_end = $_GET['end_date'] ?? '';

$log_sql = "SELECT * FROM site_logs WHERE 1=1";
$log_params = [];

// 검색 조건 병합 (Prepared Statement 활용)
if ($log_filter_type) {
    $log_sql .= " AND log_type = ?";
    $log_params[] = $log_filter_type;
}
if ($log_filter_start) {
    $log_sql .= " AND created_at >= ?";
    $log_params[] = $log_filter_start . " 00:00:00";
}
if ($log_filter_end) {
    $log_sql .= " AND created_at <= ?";
    $log_params[] = $log_filter_end . " 23:59:59";
}
// 서버 과부하 방지를 위해 최신 100개까지만 노출합니다.
$log_sql .= " ORDER BY id DESC LIMIT 100";

$stmt_logs = $pdo->prepare($log_sql);
$stmt_logs->execute($log_params);
$site_logs = ($view === 'logs') ? $stmt_logs->fetchAll() : [];

?>

<style>
.settings-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.settings-title {
    <?=$UI_STYLE['section_title'] ?>display: flex;
    align-items: center;
}

.settings-title i {
    color: #4e73df;
    margin-right: 8px;
}

.form-label-custom {
    <?=$UI_STYLE['item_label'] ?>
}

.section-sub-text {
    <?=$UI_STYLE['section_sub'] ?>
}
</style>

<div class="site-management-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-end border-bottom mb-4 pb-0 gap-2">
        <ul class="nav nav-tabs border-bottom-0 mb-0">
            <li class="nav-item">
                <a class="nav-link <?= $view == 'config' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=config">사이트 설정</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_daily_reports' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_daily_reports">일별 리포트 관리</a>
            </li>            
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_monthly_reports' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_monthly_reports">월별 리포트 관리</a>
            </li>            

            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_messages' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_messages">메시지 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'manage_emails' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=manage_emails">이메일 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'logs' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=logs">로그 관리</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'admins' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=admins">관리자 계정</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == 'notice' ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_site&view=notice">공지사항 관리</a>
            </li>
        </ul>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <?php if ($view === 'config'): ?>
    <div class="row g-4">
        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="고객지원 채널">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-headset"></i> 고객지원 채널</div>
                    <div class="mb-3"><label class="form-label-custom">고객센터 카톡 ID</label><input type="text"
                            name="settings[cs_kakao_id]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_kakao_id'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label-custom">고객센터 연락처</label><input type="text"
                            name="settings[cs_phone]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_phone'] ?? ''); ?>"></div>
                    <div class="mb-0"><label class="form-label-custom">고객센터 이메일</label><input type="email"
                            name="settings[cs_email]" class="form-control"
                            value="<?= htmlspecialchars($settings['cs_email'] ?? ''); ?>"></div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="입점 및 유지 비용 정보">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-credit-card"></i> 입점 및 유지 비용 정보</div>
                    <div class="mb-3">
                        <label class="form-label-custom">가입비, 메모, 기준일</label>
                        <input type="number" name="settings[setup_fee]" class="form-control mb-2" placeholder="금액 (숫자만)"
                            value="<?= htmlspecialchars($settings['setup_fee'] ?? ''); ?>">
                        <input type="text" name="settings[setup_fee_info]" class="form-control mb-2"
                            value="<?= htmlspecialchars($settings['setup_fee_info'] ?? ''); ?>">
                        <input type="date" name="settings[setup_fee_date]" class="form-control"
                            value="<?= htmlspecialchars($settings['setup_fee_date'] ?? ''); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">유지비, 메모, 기준일</label>
                        <input type="number" name="settings[monthly_fee]" class="form-control mb-2"
                            placeholder="금액 (숫자만)" value="<?= htmlspecialchars($settings['monthly_fee'] ?? ''); ?>">
                        <input type="text" name="settings[monthly_fee_info]" class="form-control mb-2"
                            value="<?= htmlspecialchars($settings['monthly_fee_info'] ?? ''); ?>">
                        <input type="date" name="settings[monthly_fee_date]" class="form-control"
                            value="<?= htmlspecialchars($settings['monthly_fee_date'] ?? ''); ?>">
                    </div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="초과 요금 설정 및 프로세스 정책">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title"><i class="bi bi-speedometer2"></i> 초과 요금 설정 및 프로세스 정책</div>

                    <!-- 무료 주문 건수 정책 -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">* 무료 주문 건수</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="settings[billing_tier_policy][free_orders]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['free_orders']); ?>">
                                <span class="input-group-text bg-light text-muted">건/월</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">건당 초과 요금</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="settings[billing_tier_policy][overage_per_order]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['overage_per_order']); ?>">
                                <span class="input-group-text bg-light text-muted">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 무료 디스크 용량 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 무료 디스크 용량 정책</label>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="무료 디스크 용량">
                                <span class="input-group-text bg-light text-muted">무료</span>
                                <input type="number" name="settings[billing_tier_policy][free_disk_mb]"
                                    class="form-control"
                                    value="<?= htmlspecialchars($billing_policy['free_disk_mb'] ?? 1024); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="초과 과금 단위 용량">
                                <span class="input-group-text bg-light text-muted px-1">초과</span>
                                <input type="number" name="settings[billing_tier_policy][overage_disk_unit_mb]"
                                    class="form-control" value="<?= htmlspecialchars($unit_mb); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB당</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="단위당 초과 요금">
                                <input type="number" name="settings[billing_tier_policy][overage_disk_fee]"
                                    class="form-control" value="<?= htmlspecialchars($disk_fee); ?>">
                                <span class="input-group-text bg-light text-muted px-1">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 무료 DB 용량 정책 -->
                    <div class="row g-2 mb-3">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 무료 DB 용량 정책</label>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="무료 DB 용량">
                                <span class="input-group-text bg-light text-muted">무료</span>
                                <input type="number" name="settings[billing_tier_policy][free_db_mb]" class="form-control" value="<?= htmlspecialchars($billing_policy['free_db_mb'] ?? 50); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="초과 과금 단위 용량">
                                <span class="input-group-text bg-light text-muted px-1">초과</span>
                                <input type="number" name="settings[billing_tier_policy][overage_db_unit_mb]" class="form-control" value="<?= htmlspecialchars($billing_policy['overage_db_unit_mb'] ?? 10); ?>">
                                <span class="input-group-text bg-light text-muted px-1">MB당</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm" title="단위당 초과 요금">
                                <input type="number" name="settings[billing_tier_policy][overage_db_fee]" class="form-control" value="<?= htmlspecialchars($billing_policy['overage_db_fee'] ?? 50); ?>">
                                <span class="input-group-text bg-light text-muted px-1">원</span>
                            </div>
                        </div>
                    </div>

                    <!-- 과금 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 과금 정책</label>
                        </div>
                        <div class="col-12">
                            <div class="input-group input-group-sm" title="과금 정책">
                                <textarea name="settings[billing_process_policy]" class="form-control"
                                    rows="10"><?= htmlspecialchars($settings['billing_process_policy'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 상점 상태 프로세스 정책 -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 mb-1">
                            <label class="form-label-custom mb-0">* 상점 상태 프로세스 정책</label>
                        </div>
                        <div class="col-12">
                            <div class="input-group input-group-sm" title="상점 상태 프로세스 정책">
                                <textarea name="settings[shop_status_process_policy]" class="form-control"
                                    rows="10"><?= htmlspecialchars($settings['shop_status_process_policy'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>

                </div>
            </form>
        </div>

        <div class="col-md-6">
            <form method="POST" action="admin_view.php?page=manage_site&view=config" class="h-100">
                <input type="hidden" name="section_name" value="관리자 텔레그램 알림">
                <div class="settings-card h-100 d-flex flex-column">
                    <div class="settings-title">
                        <i class="bi bi-telegram" style="color: #2CA5E0;"></i> 관리자 텔레그램 알림
                        <button type="button"
                            class="btn btn-sm btn-outline-info ms-auto rounded-pill px-3 fw-bold shadow-sm"
                            onclick="testAdminTelegram()">
                            <i class="bi bi-send-fill me-1"></i> 테스트 발송
                        </button>
                    </div>
                    <div class="mb-3"><label class="form-label-custom">Chat ID (채팅방 번호)</label><input type="text"
                            name="settings[admin_telegram_chat_id]" id="admin_telegram_chat_id" class="form-control"
                            value="<?= htmlspecialchars($settings['admin_telegram_chat_id'] ?? ''); ?>"
                            placeholder="예: 123456789"></div>
                    <div class="form-text small">
                        <i class="bi bi-info-circle me-1"></i>입점 신청, 시스템 에러 발생 등 본사 최고 관리자용 주요 알림을 수신합니다.<br>
                        <i class="bi bi-robot text-primary mt-1 me-1 d-inline-block"></i>발신용 봇 토큰은 <code>/config.php</code>의 설정값을 공통으로 사용합니다.
                    </div>
                    <div class="mt-auto text-end pt-4">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <form method="POST" action="admin_view.php?page=manage_site&view=config">
                <input type="hidden" name="section_name" value="서비스 이용 약관 및 면책 조항">
                <div class="settings-card">
                    <div class="settings-title"><i class="bi bi-file-earmark-text"></i> 서비스 이용 약관 및 면책 조항</div>
                    <div class="mb-0">
                        <label class="form-label-custom">약관 본문 (입점 신청 화면에 표시됩니다)</label>
                        <textarea name="settings[terms_of_use]" id="terms_editor" class="form-control"
                            placeholder="서비스 이용 약관 내용을 입력하세요."><?= htmlspecialchars($settings['terms_of_use'] ?? ''); ?></textarea>
                        <div class="form-text mt-2">※ 에디터를 통해 작성한 서식(굵게, 색상 등)이 입점 신청 화면에 그대로 반영됩니다.</div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <form method="POST" action="admin_view.php?page=manage_site&view=config">
                <input type="hidden" name="section_name" value="개인정보 수집 및 이용 동의">
                <div class="settings-card">
                    <div class="settings-title"><i class="bi bi-file-earmark-text"></i> 개인정보 수집 및 이용 동의</div>
                    <div class="mb-0">
                        <label class="form-label-custom">약관 본문 (입점 신청 화면에 표시됩니다)</label>
                        <textarea name="settings[privacy_policy]" id="privacy_editor" class="form-control"
                            placeholder="개인정보 수집 및 이용 동의 내용을 입력하세요."><?= htmlspecialchars($settings['privacy_policy'] ?? ''); ?></textarea>
                        <div class="form-text mt-2">※ 에디터를 통해 작성한 서식(굵게, 색상 등)이 입점 신청 화면에 그대로 반영됩니다.</div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" name="save_settings"
                            class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">저장</button>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <div class="settings-card mt-3">
        <div class="settings-title"><i class="bi bi-grid-1x2"></i> 상점 카테고리</div>
        <form method="POST" action="admin_view.php?page=manage_site&view=config" class="row g-2 mb-4">
            <div class="col-md-4"><input type="text" name="cate_key" class="form-control" placeholder="Key (예: laundry)"
                    required></div>
            <div class="col-md-5"><input type="text" name="cate_name" class="form-control" placeholder="표시 이름" required>
            </div>
            <div class="col-md-3"><button type="submit" name="add_category"
                    class="btn btn-dark w-100 rounded-pill fw-bold">추가</button></div>
        </form>
        <table class="table table-ps24 table-hover align-middle">
            <thead>
                <tr class="small">
                    <th class="t-center">Key</th>
                    <th class="t-center">카테고리명</th>
                    <th class="t-center">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $key => $name): ?>
                <tr class="small">
                    <td class="t-center"><code><?= htmlspecialchars($key) ?></code></td>
                    <td class="t-center">
                        <form method="POST" action="admin_view.php?page=manage_site&view=config"
                            class="d-flex gap-1 m-0">
                            <input type="hidden" name="cate_key" value="<?= htmlspecialchars($key) ?>">
                            <input type="text" name="new_name" class="form-control form-control-sm"
                                value="<?= htmlspecialchars($name) ?>" required>
                            <button type="submit" name="edit_category"
                                class="btn btn-sm btn-outline-secondary border-1">저장</button>
                        </form>
                    </td>
                    <td class="t-center"><a
                            href="admin_view.php?page=manage_site&view=config&del_cate=<?= urlencode($key) ?>"
                            class="text-danger" onclick="return confirm('삭제?')"><i class="bi bi-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($view === 'manage_emails'): ?>
    <?php
        // 새로운 통합 이메일 관리 파일 로드
        $manage_emails_file = __DIR__ . '/manage_emails.php';
        if (file_exists($manage_emails_file)) include $manage_emails_file;
        ?>

    <?php elseif ($view === 'notice'): ?>
    <div class="settings-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="settings-title mb-0"><i class="bi bi-megaphone"></i> 공지사항 관리</div>
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                data-bs-target="#addNoticeModal">+ 새 공지</button>
        </div>
        <table class="table table-ps24 table-hover align-middle">
            <thead>
                <tr class="small">
                    <th class="t-center" style="width:80px;">유형</th>
                    <th class="t-center">제목</th>
                    <th class="t-center" style="width:100px;">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notices as $n): ?>
                <tr class="small">
                    <td class="t-center">
                        <?= !empty($n['is_notice']) ? '<span class="badge bg-danger">중요</span>' : '<span class="text-muted small">일반</span>' ?>
                    </td>
                    <td class="t-center"><a href="javascript:void(0);"
                            data-notice='<?= htmlspecialchars(json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                            class="text-decoration-none text-dark fw-bold btn-edit-notice"><?= htmlspecialchars($n['title']) ?></a>
                    </td>
                    <td class="t-center">
                        <button type="button" class="btn btn-sm btn-link text-primary p-0 btn-edit-notice"
                            data-notice='<?= htmlspecialchars(json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'><i
                                class="bi bi-pencil"></i></button>
                        <a href="admin_view.php?page=manage_site&view=notice&action=delete_notice&id=<?= $n['id'] ?>"
                            class="text-danger ms-2" onclick="return confirm('삭제?')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($view === 'logs'): ?>
    <div class="settings-card">
        <div class="settings-title mb-4"><i class="bi bi-terminal-split"></i> 시스템 로그 관리</div>

        <!-- 로그 필터 폼 -->
        <form method="GET" action="admin_view.php" class="row g-2 mb-4 p-3 bg-light rounded-3">
            <input type="hidden" name="page" value="manage_site">
            <input type="hidden" name="view" value="logs">
            <div class="col-md-2">
                <select name="log_type" class="form-select form-select-sm">
                    <option value="">모든 유형</option>
                    <?php foreach ($site_log_type_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $log_filter_type === $val ? 'selected' : '' ?>><?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm"
                    value="<?= $log_filter_start ?>"></div>
            <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm"
                    value="<?= $log_filter_end ?>"></div>
            <div class="col-md-3 d-flex gap-1">
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('today')">금일</button>
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('week')">1주일</button>
                <button type="button" class="btn btn-outline-dark btn-sm flex-fill"
                    onclick="setLogPeriod('month')">1개월</button>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-dark btn-sm w-100">검색</button></div>
            <div class="col-md-1">
                <a href="admin_view.php?page=manage_site&view=logs" class="btn btn-outline-secondary btn-sm w-100"><i
                        class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>

        <div class="d-flex justify-content-end mb-3">
            <form method="POST" class="row g-2 align-items-center bg-light p-2 rounded-3 border"
                onsubmit="return confirm('선택한 날짜 이전의 모든 로그가 영구 삭제됩니다. 계속하시겠습니까?');">
                <div class="col-auto small fw-bold text-muted">이전 로그 삭제:</div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(1)">1개월
                            전</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(3)">3개월
                            전</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setDeleteBeforeDate(6)">6개월
                            전</button>
                    </div>
                </div>
                <div class="col-auto">
                    <input type="date" name="delete_before_date" id="delete_before_date"
                        class="form-control form-control-sm" required title="이 날짜 이전의 로그를 삭제합니다.">
                </div>
                <div class="col-auto">
                    <button type="submit" name="delete_old_logs"
                        class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> 삭제 실행
                    </button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-ps24 table-hover align-middle">
                <thead>
                    <tr class="small">
                        <th class="t-center" style="width:100px;">유형</th>
                        <th>메시지 / 상세 정보</th>
                        <th class="t-center" style="width:120px;">접속 IP</th>
                        <th class="t-center" style="width:160px;">일시</th>
                        <th class="t-center" style="width:60px;">관리</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($site_logs as $log):
                            $badge_class = ($log['log_type'] === LOG_TYPE_EMAIL_FAIL || $log['log_type'] === LOG_TYPE_ERROR) ? 'bg-danger' : (($log['log_type'] === LOG_TYPE_ADMIN_ACTION) ? 'bg-warning text-dark fw-bold' : 'bg-info');
                        ?>
                    <tr>
                        <td class="t-center"><span
                                class="badge <?= $badge_class ?>"><?= strtoupper($log['log_type']) ?></span></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($log['message'] ?? '') ?></div>
                            <?php if ($log['details']): ?>
                            <div class="text-muted x-small mt-1" style="font-size:0.75rem;">
                                <?= $log['details'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="t-center text-muted"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                        <td class="t-center text-muted"><?= $log['created_at'] ?></td>
                        <td class="t-center">
                            <!-- 개별 삭제 시에도 현재 필터 조건을 함께 전달하여 리다이렉트 시 필터가 유지되도록 함 -->
                            <a href="admin_view.php?page=manage_site&view=logs&action=delete_log&id=<?= $log['id'] ?>&<?= $filter_query ?>"
                                class="text-danger" onclick="return confirm('삭제하시겠습니까?')"><i
                                    class="bi bi-x-circle"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($site_logs)) echo "<tr><td colspan='5' class='text-center py-5 text-muted'>기록된 로그가 없습니다.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($view === 'admins'): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="settings-card">
                <div class="settings-title"><i class="bi bi-shield-lock"></i> 관리자 계정 목록</div>
                <table class="table table-ps24 table-hover align-middle">
                    <thead>
                        <tr class="small">
                            <th class="t-center">ID</th>
                            <th class="t-center">이름</th>
                            <th class="t-center">카카오 ID</th>
                            <th class="t-center">비밀번호</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($admins as $a): ?>
                        <tr>
                            <td class="t-center fw-bold"><?= htmlspecialchars($a['admin_id']) ?></td>
                            <td class="t-center"><?= htmlspecialchars($a['admin_name']) ?></td>
                            <td class="t-center"><span
                                    class="badge bg-light text-dark border"><?= htmlspecialchars($a['admin_kakao_id'] ?? '-') ?></span>
                            </td>
                            <td class="t-center">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal"
                                    data-bs-target="#passModal<?= $a['id'] ?>">변경</button>
                                <?php if ($a['admin_id'] !== 'admin'): ?><a
                                    href="admin_view.php?page=manage_site&view=admins&action=delete_admin&id=<?= $a['id'] ?>"
                                    class="text-danger ms-2" onclick="return confirm('정말로 이 관리자를 삭제하시겠습니까?')"><i
                                        class="bi bi-trash"></i></a><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-4">
            <div class="settings-card">
                <div class="settings-title">관리자 추가</div>
                <form method="POST" action="admin_view.php?page=manage_site&view=admins">
                    <input type="text" name="new_admin_id" class="form-control mb-2" placeholder="아이디" required>
                    <input type="text" name="new_admin_name" class="form-control mb-2" placeholder="이름" required>
                    <input type="text" name="new_admin_kakao" class="form-control mb-2" placeholder="카카오톡 ID (알림용)">
                    <input type="password" name="new_admin_pass" class="form-control mb-3" placeholder="비밀번호" required>
                    <button type="submit" name="add_admin" class="btn btn-dark w-100 rounded-pill fw-bold">계정
                        생성</button>
                </form>
            </div>
        </div>
    </div>

    <?php elseif ($view === 'manage_messages'): ?>
    <?php
        // 새로운 메시지 관리 파일 로드
        $manage_messages_file = __DIR__ . '/manage_messages.php';
        if (file_exists($manage_messages_file)) include $manage_messages_file;
        ?>
    <?php elseif ($view === 'manage_daily_reports'): ?>
    <?php
        // 일별 리포트 관리 파일 로드
        $manage_daily_reports_file = __DIR__ . '/manage_daily_reports.php';
        if (file_exists($manage_daily_reports_file)) include $manage_daily_reports_file;
        ?>
    <?php elseif ($view === 'manage_monthly_reports'): ?>
    <?php
        // 월별 리포트 관리 파일 로드
        $manage_monthly_reports_file = __DIR__ . '/manage_monthly_reports.php';
        if (file_exists($manage_monthly_reports_file)) include $manage_monthly_reports_file;
        ?>                 
    <?php endif; ?>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST"
            action="admin_view.php?page=manage_site&view=notice">
            <input type="hidden" name="action" value="add_notice">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="fw-bold mb-0">새 공지사항 작성</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="text" name="title" class="form-control mb-3" placeholder="공지사항 제목을 입력하세요" required>
                <textarea name="content" class="form-control" rows="10" placeholder="공지 내용을 상세히 입력하세요"
                    required></textarea>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">게시하기</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="admin_view.php?page=manage_site&view=notice"><input
                type="hidden" name="action" value="edit_notice"><input type="hidden" name="id" id="edit_id">
            <div class="modal-header border-0">
                <h5 class="fw-bold">공지 수정</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><input type="text" name="title" id="edit_title" class="form-control mb-3" required>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                        name="is_notice" id="edit_is_notice"><label class="form-check-label" for="edit_is_notice">상단
                        고정</label></div><textarea name="content" id="edit_content" class="form-control" rows="10"
                    required></textarea>
            </div>
            <div class="modal-footer border-0"><button type="submit" class="btn btn-primary rounded-pill px-4">수정
                    완료</button></div>
        </form>
    </div>
</div>

<?php foreach ($admins as $a): ?>
<div class="modal fade" id="passModal<?= $a['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="admin_view.php?page=manage_site&view=admins">
            <div class="modal-header border-0">
                <h5 class="fw-bold">비번 변경: <?= $a['admin_id'] ?></h5><button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><input type="hidden" name="target_id" value="<?= $a['id'] ?>"><input type="password"
                    name="new_pass" class="form-control" placeholder="새 비밀번호" required></div>
            <div class="modal-footer border-0"><button type="submit" name="change_pass"
                    class="btn btn-primary rounded-pill px-4">변경 저장</button></div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
$(document).ready(function() {
    // Summernote 초기화
    if ($('#email_editor').length && typeof $.fn.summernote !== 'undefined') {
        $('#email_editor').summernote({
            height: 350,
            lang: 'ko-KR'
        });
    }

    // 이용 약관 및 개인정보 수집 동의 에디터 초기화 (공용)
    if ($('#terms_editor, #privacy_editor').length && typeof $.fn.summernote !== 'undefined') {
        $('#terms_editor, #privacy_editor').summernote({
            height: 400,
            lang: 'ko-KR',
            placeholder: '약관 및 동의 내용을 상세히 작성해주세요.',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }
});

// jQuery 이벤트 위임을 사용하여 동적으로 생성된 요소에도 이벤트 바인딩 및 데이터 파싱 오류 방지
$(document).on('click', '.btn-edit-notice', function() {
    let data = $(this).data('notice');
    if (typeof data === 'string') {
        data = JSON.parse(data);
    }
    // 공지사항 수정 버튼 클릭 시 모달창의 각 입력 필드에 기존 데이터를 채워 넣습니다.
    $('#edit_id').val(data.id);
    $('#edit_title').val(data.title);
    $('#edit_content').val(data.content);
    $('#edit_is_notice').prop('checked', data.is_notice == 1);
    $('#editNoticeModal').modal('show');
});

// [관리자 텔레그램 테스트 발송]
function testAdminTelegram() {
    const chatId = $('#admin_telegram_chat_id').val();

    if (!chatId) {
        alert('테스트를 위해 수신받을 Chat ID를 입력해주세요.');
        return;
    }

    const $btn = $('.settings-title .btn-outline-info');
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>발송 중...');

    $.post('admin_view.php?page=manage_site&view=config', {
        action: 'test_telegram',
        chat_id: chatId
    }, function(data) {
        if (data.includes('AJAX_SUCCESS')) alert('테스트 메시지가 성공적으로 전송되었습니다!\\n스마트폰의 텔레그램 앱을 확인해보세요.');
        else alert(data.replace('AJAX_ERROR: ', ''));
    }).always(function() {
        $btn.prop('disabled', false).html(originalText);
    });
}

// [UI 편의성] 로그 기간 설정 함수 ('금일', '1주일', '1개월' 버튼 클릭 시 자동 입력)
function setLogPeriod(period) {
    const startInput = document.querySelector('input[name="start_date"]');
    const endInput = document.querySelector('input[name="end_date"]');
    if (!startInput || !endInput) return;

    const now = new Date();
    const formatDate = (date) => {
        return date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate())
            .slice(-2);
    };

    const endStr = formatDate(now);
    let start = new Date(now);

    if (period === 'week') start.setDate(now.getDate() - 7);
    else if (period === 'month') start.setMonth(now.getMonth() - 1);

    startInput.value = formatDate(start);
    endInput.value = endStr;
}

// [UI 편의성] 오래된 로그 삭제를 위해 특정 개월 수 이전의 날짜를 자동 계산하여 입력합니다.
function setDeleteBeforeDate(months) {
    const input = document.getElementById('delete_before_date');
    if (!input) return;
    const now = new Date();
    now.setMonth(now.getMonth() - months);
    const year = now.getFullYear();
    const month = ('0' + (now.getMonth() + 1)).slice(-2);
    const day = ('0' + now.getDate()).slice(-2);
    input.value = `${year}-${month}-${day}`;
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>