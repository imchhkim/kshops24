<?php

/**
 * [하위 파일] KShops24 특정 상점 상세 관리 (admin/manage_shop.php)
 * [최종 수정] 2026-02-14: 메시지 탭에 페이징(상수 활용) 및 검색 기능 추가
 */

// [AJAX 단독 호출 지원]
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// ---------------------------------------------------------
// 1. 초기 변수 설정 (알림 헬퍼 함수는 lib_utils.php로 이동됨)
// ---------------------------------------------------------
$message = "";
$shop_id = (int)($_GET['id'] ?? 0);
$active_tab = $_GET['view'] ?? 'payments';

// 하위 호환: 기존 파라미터가 존재하면 자동 탭 전환
if (!isset($_GET['view']) && (isset($_GET['board_page']) || isset($_GET['f_board']))) {
    $active_tab = 'message';
}

// ---------------------------------------------------------
// 2. [위임] 탭 전용 액션(POST/GET/AJAX) 모듈 호출
// ---------------------------------------------------------
$tab_mode = 'action';
include __DIR__ . '/manage_shop_tabs.php';

$s = null;

// ---------------------------------------------------------
// 3. 상점 기본 메인 로직 처리 (POST)
// (탭과 무관한 메인 화면의 상점 정보 수정 및 상태 변경 전용)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    try {
        $action = $_POST['action'] ?? '';

        // [빠른 검색 기능]
        // 상단 검색바에서 ID 또는 서브도메인으로 검색 시, 해당 상점의 관리 페이지로 즉시 리다이렉트합니다.
        if ($action === 'quick_find') {
            $search_val = trim($_POST['search_val'] ?? '');
            if ($search_val) {
                // 보안을 위해 Prepared Statement를 사용하여 SQL 인젝션을 방지합니다.
                $find = $pdo->prepare("SELECT id FROM shops WHERE id = ? OR subdomain = ? LIMIT 1");
                $find->execute([$search_val, $search_val]);
                $found_id = $find->fetchColumn();
                if ($found_id) {
                    // 검색된 상점이 존재하면 즉시 해당 상점 관리 페이지로 이동
                    echo "<script>location.replace('admin_view.php?page=manage_shop&id=" . $found_id . "');</script>";
                    exit;
                } else {
                    // 일치하는 상점이 없을 경우 경고 메시지 출력
                    $message = showAlert("상점을 찾을 수 없습니다: <strong>" . htmlspecialchars($search_val) . "</strong>", "danger");
                }
            }
        }

        if ($shop_id > 0) {
            // [상점 기본 정보 수정]
            if ($action === 'update_info') {
                // [수정] 상점의 모든 정보를 동적으로 업데이트하기 위한 필드 매핑
                $updatable_fields = [
                    'shop_name',
                    'shop_name_en',
                    'manager_email',
                    'manager_name',
                    'manager_name_en',
                    'category',
                    'status',
                    'phone_mobile',
                    'phone_landline',
                    'kakao_id',
                    'kakao_channel_id',
                    'location_city',
                    'physical_address',
                    'business_hours',
                    'delivery_hours',
                    'min_delivery_amount',
                    'delivery_fee_info',
                    'estimated_delivery_time',
                    'payment_methods',
                    'is_pickup_available',
                    'is_delivery_available',
                    'custom_domain',
                    'shop_skin',
                    'shop_font',
                    'shop_youtube_url',
                    'top_label',
                    'main_title',
                    'sub_title',
                    'shop_map_html',
                    'is_show_story',
                    'is_show_gallery',
                    'is_show_map',
                    'is_show_main_title',
                    'is_show_review',
                    'is_show_delivery',
                    'use_telegram_alert',
                    'telegram_chat_id',
                    'telegram_alert_types',
                    'urgent_notice',
                    'general_notice',
                    'shop_intro',
                    'shop_description',
                    'custom_free_orders',
                    'custom_free_disk_mb'
                ];

                $update_parts = [];
                $params = [];

                foreach ($updatable_fields as $field) {
                    if (isset($_POST[$field])) {
                        $update_parts[] = "$field = ?";
                        $val = $_POST[$field];
                        if ($field === 'telegram_alert_types' && is_array($val)) {
                            $val = implode(',', $val);
                        }
                        if (in_array($field, ['min_delivery_amount', 'is_pickup_available', 'is_delivery_available', 'is_show_story', 'is_show_gallery', 'is_show_map', 'is_show_main_title', 'is_show_review', 'is_show_delivery'])) $val = (int)$val;
                        if (in_array($field, ['custom_domain', 'kakao_id', 'kakao_channel_id', 'custom_free_orders', 'custom_free_disk_mb']) && $val === '') $val = null;
                        $params[] = $val;
                    } elseif ($field === 'telegram_alert_types') {
                        // 체크박스가 하나도 선택되지 않은 경우 빈 값 처리
                        $update_parts[] = "$field = ?";
                        $params[] = '';
                    }
                }
                addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_INFO, "상점 기본 정보 수정", "관리자에 의해 상점 정보가 업데이트되었습니다.");

                // [버그 수정] 폼에서 전달된 UI 설정이 있을 경우 기존 설정과 병합(Merge)하여 안전하게 저장합니다.
                if (isset($_POST['ui']) && is_array($_POST['ui'])) {
                    // [수정] 이 시점에서는 상점 정보 객체($s)가 비어있으므로 DB에서 기존 설정값을 직접 조회해 와야 합니다.
                    $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
                    $stmt_ui->execute([$shop_id]);
                    $existing_ui_json = $stmt_ui->fetchColumn();

                    $existing_ui = json_decode($existing_ui_json ?: '{}', true);
                    if (!is_array($existing_ui)) $existing_ui = [];

                    $ui_raw = $_POST['ui'];
                    $ui_new = array_map('trim', $ui_raw);
                    $ui_merged = array_merge($existing_ui, $ui_new);
                    foreach ($ui_merged as $k => $v) {
                        if ($v === '') unset($ui_merged[$k]);
                    }
                    $ui_settings = json_encode($ui_merged, JSON_UNESCAPED_UNICODE);
                    $update_parts[] = "ui_settings = ?";
                    $params[] = $ui_settings;
                }

                // 비밀번호 변경 요청이 있을 경우에만 비밀번호 업데이트 쿼리를 동적으로 추가합니다.
                if (!empty($_POST['new_password'])) {
                    $update_parts[] = "manager_password = ?";
                    // Native PHP 표준 단방향 암호화 함수인 password_hash를 사용합니다.
                    $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                }
                $params[] = $shop_id;
                $sql = "UPDATE shops SET " . implode(', ', $update_parts) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($params);

                // [폐점 처리 보강]
                // 상태 변경 폼이 포함된 기본 정보 모달에서 'closed'로 넘어온 경우
                if (isset($_POST['status']) && $_POST['status'] === 'closed' && function_exists('closeShopWithRename')) {
                    closeShopWithRename($pdo, $shop_id);
                }

                // [추가] 관리자의 상점 설정 변경 행동 로그 기록
                if (function_exists('recordAdminAction')) {
                    $log_details = $_POST;
                    unset($log_details['new_password']); // 보안상 비밀번호 변경 시도값은 로그에서 제외
                    // recordAdminAction($pdo, "상점 [ID: {$shop_id}] 기본 정보 및 한도 수정", $log_details);
                }

                $current_view = $_GET['view'] ?? 'payments';
                echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view={$current_view}&msg=info_updated');</script>";
                exit;
            }

            // [상점 상태 수동 변경 (active / inactive)]
            if ($action === 'update_manual_status') {
                $new_status = $_POST['manual_status'] ?? '';
                // HTML5 datetime-local 포맷('T' 포함)을 DB 저장용 형식으로 변환
                $status_date = str_replace('T', ' ', $_POST['status_date'] ?? date('Y-m-d H:i'));
                $suspend_ymd = substr($status_date, 0, 10);
                $reason = trim($_POST['status_reason'] ?? '');
                $send_msg = isset($_POST['send_message']) ? true : false;
                $template_key = $_POST['message_template'] ?? '';

                if (in_array($new_status, ['active', 'inactive'])) {
                    if ($new_status === 'inactive') {
                        $closed_soon_days = defined('SHOP_STATUS_CLOSED_SOON_DAYS') ? SHOP_STATUS_CLOSED_SOON_DAYS : 30;
                        $warning_deleted_soon_days = defined('WARNING_SHOP_STATUS_DELETED_SOON_DAYS') ? WARNING_SHOP_STATUS_DELETED_SOON_DAYS : 30;
                        $deleted_soon_days = defined('SHOP_STATUS_DELETED_SOON_DAYS') ? SHOP_STATUS_DELETED_SOON_DAYS : 30;

                        $closed_date_val = date('Y-m-d', strtotime($suspend_ymd . " +{$closed_soon_days} days"));
                        $deleted_days_add = $closed_soon_days + $warning_deleted_soon_days + $deleted_soon_days;
                        $deleted_date_val = date('Y-m-d', strtotime($suspend_ymd . " +{$deleted_days_add} days"));

                        $pdo->prepare("UPDATE shops SET status = ?, inactive_date = ?, closed_date = ?, deleted_date = ? WHERE id = ?")->execute([$new_status, $suspend_ymd, $closed_date_val, $deleted_date_val, $shop_id]);
                    } else {
                        $pdo->prepare("UPDATE shops SET status = ?, inactive_date = NULL, closed_date = NULL, deleted_date = NULL WHERE id = ?")->execute([$new_status, $shop_id]);
                    }

                    addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_STATUS, "수동 상태 변경 ({$new_status})", "사유: {$reason}", $status_date);

                    // 상점명 조회를 위해 다시 로드
                    $stmt_hist = $pdo->prepare("SELECT shop_name FROM shops WHERE id = ?");
                    $stmt_hist->execute([$shop_id]);
                    $shop_info = $stmt_hist->fetch();

                    // 쪽지 메시지 발송 처리 (공용 함수 사용)
                    if ($send_msg && $template_key) {
                        sendShopMessage($pdo, $shop_id, $template_key, ['shop_name' => $shop_info['shop_name'], 'date' => $status_date, 'reason' => $reason]);
                    }

                    $current_view = $_GET['view'] ?? 'payments';
                    echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view={$current_view}&msg=status_updated');</script>";
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        $message = showAlert("오류 발생: " . $e->getMessage(), "danger");
    }
}

// -----------------------------------------------------------------------
// 4. 상점 상세 메인 데이터 및 UI 설정 로드
// -----------------------------------------------------------------------
if ($shop_id > 0) {
    $stmt_shop = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
    $stmt_shop->execute([$shop_id]);
    $s = $stmt_shop->fetch();

    $payment_list = [];
    $ui = [];
    $total_pay_pages = 0;
    $pay_page = 1;

    // [공용 파라미터 초기화] 탭에 상관없이 HTML 렌더링 시 값을 유지하기 위해 상단에서 수신합니다.
    $f_year = $_GET['f_year'] ?? '';
    $f_month = $_GET['f_month'] ?? '';
    $f_note = trim($_GET['f_note'] ?? '');
    $sort_col = $_GET['sort_col'] ?? 'expiring_date';
    $sort_dir = strtolower($_GET['sort_dir'] ?? 'asc');
    $f_board = trim($_GET['f_board'] ?? '');

    // [페이징 공통 설정] 시스템 전역 상수 LISTS_PER_PAGE를 우선 참조하고, 없으면 20을 기본값으로 사용합니다.
    $limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 20;

    if ($s) {
        // [모듈화 보강] 
        // 상점의 업종(fnb, cafe 등)에 맞는 카테고리별 설정(상수) 파일을 동적으로 로드합니다.
        $category_config_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/{$s['category']}/config.php";
        if (file_exists($category_config_path)) {
            include_once $category_config_path;
        }

        // JSON으로 뭉쳐진 상점 전용 UI 커스텀 설정값을 연관 배열로 풀어냅니다.
        $ui = json_decode($s['ui_settings'] ?? '{}', true);

        // ---------------------------------------------------------
        // 5. [위임] 하위 탭들 UI 렌더링에 필요한 데이터 로딩
        // ---------------------------------------------------------
        $tab_mode = 'data';
        include __DIR__ . '/manage_shop_tabs.php';
    }
}

// 리다이렉트 후 전달받은 파라미터를 기반으로 알림창 출력 및 탭 고정
if (empty($message) && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'payment_deleted') {
        $active_tab = 'payments';
        $message = showAlert("결제 내역이 삭제되었습니다.", "warning");
    } elseif ($_GET['msg'] === 'payment_added') {
        $active_tab = 'payments';
        $message = showAlert("결제 내역이 등록되었습니다.", "success");
    } elseif ($_GET['msg'] === 'payment_edited') {
        $active_tab = 'payments';
        $message = showAlert("결제 내역이 수정되었습니다.", "success");
    } elseif ($_GET['msg'] === 'info_updated') {
        $message = showAlert("상점 정보가 성공적으로 업데이트되었습니다.", "success");
    } elseif ($_GET['msg'] === 'status_updated') {
        $message = showAlert("상점 상태가 수동으로 변경되었습니다.", "success");
    } elseif ($_GET['msg'] === 'log_deleted') {
        $active_tab = 'logs';
        $message = showAlert("로그 내역이 삭제되었습니다.", "warning");
    } elseif ($_GET['msg'] === 'log_added') {
        $active_tab = 'logs';
        $message = showAlert("새로운 로그가 추가되었습니다.", "success");
    } elseif ($_GET['msg'] === 'log_edited') {
        $active_tab = 'logs';
        $message = showAlert("로그 내역이 수정되었습니다.", "success");
    }
}

?>

<script>
    const templatesData = {
        message: <?= json_encode($msg_tpl_js ?? [], JSON_UNESCAPED_UNICODE) ?>,
        email: <?= json_encode($email_tpl_js ?? [], JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<!-- 
  =============================================================
  [View 영역 시작] 관리자 화면 메인 컨테이너
  ============================================================= 
-->
<div class="container-fluid py-4">
    <?= $message ?>

    <!-- 상단 헤더: 현재 선택된 상점 정보 및 빠른 검색 폼 -->
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h2 class="page-header-title mb-0">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i>
                <?php if ($s): ?>
                    <span class="badge bg-primary rounded-pill me-2">ID #<?= $s['id'] ?></span>
                    <?= htmlspecialchars($s['shop_name']) ?> <small class="text-muted fw-normal"
                        style="font-size: 0.6em;">상점 관리</small>
                <?php else: ?>
                    상점 상세 관리 <small class="text-muted fw-normal" style="font-size: 0.6em;">상점을 검색해 주세요</small>
                <?php endif; ?>
            </h2>
        </div>
        <div class="col-md-5 text-end">
            <div class="d-inline-flex gap-2">
                <form method="POST" class="d-flex bg-white border rounded-pill px-2 shadow-sm">
                    <input type="hidden" name="action" value="quick_find">
                    <input type="text" name="search_val"
                        class="form-control border-0 bg-transparent form-control-sm shadow-none"
                        placeholder="ID 또는 서브도메인" style="width: 160px;" required>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 m-1">검색 이동</button>
                </form>
                <a href="admin_view.php?page=manage_shops"
                    class="btn btn-outline-secondary btn-sm rounded-pill px-3 m-1 shadow-sm">전체 목록</a>
            </div>
        </div>
    </div>

    <?php if ($s): ?>
        <!-- 상점이 선택된 경우에만 상세 관리 패널 노출 -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow-sm border-0 text-start">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="fs-4 fw-bold text-dark d-flex align-items-center">
                            <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                            <span>상점 기본 정보</span>
                        </div>
                    </div>
                    <div class="card-body bg-light-subtle p-4">
                        <div class="row g-4">
                            <!-- 1. 기본 정보 -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-person-badge me-2"></i>기본 정보 및 계정</h6>
                                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editBasicModal"><i class="bi bi-pencil me-1"></i>수정</button>
                                </div>

                                <dl class="row small mb-0">
                                    <dt class="col-sm-5 text-muted">업종 / 상태</dt>
                                    <dd class="col-sm-7"><span
                                            class="badge bg-primary fw-normal"><?= htmlspecialchars($s['category'] ?? '-') ?></span>
                                        / <span
                                            class="badge bg-<?= ($s['status'] == 'active' ? 'success' : 'warning') ?> fw-normal"><?= strtoupper($s['status']) ?></span>
                                    </dd>
                                    <dt class="col-sm-5 text-muted">상점 주소</dt>
                                    <dd class="col-sm-7"><a href="/<?= $s['subdomain'] ?>" target="_blank"
                                            class="text-decoration-none fw-bold">/<?= htmlspecialchars($s['subdomain']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i></a></dd>

                                    <dt class="col-sm-5 text-muted">상점명</dt>
                                    <dd class="col-sm-7 fw-bold"><?= htmlspecialchars($s['shop_name']) ?> <span
                                            class="text-muted fw-normal">(<?= htmlspecialchars($s['shop_name_en'] ?? '-') ?>)</span>
                                    </dd>
                                    <dt class="col-sm-5 text-muted">관리자명</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($s['manager_name'] ?? '-') ?> <span
                                            class="text-muted fw-normal">(<?= htmlspecialchars($s['manager_name_en'] ?? '-') ?>)</span>
                                    </dd>
                                    <dt class="col-sm-5 text-muted">로그인 이메일(상점관리자 ID)</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($s['manager_email']) ?></dd>
                                    <dt class="col-sm-5 text-muted">연결 도메인</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($s['custom_domain'] ?: '-') ?></dd>
                                </dl>
                            </div>

                            <!-- 2. 연락처 및 위치 -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-geo-alt me-2"></i>연락처 및 위치 정보</h6>
                                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editContactModal"><i class="bi bi-pencil me-1"></i>수정</button>
                                </div>

                                <dl class="row small mb-0">
                                    <dt class="col-sm-4 text-muted">휴대전화</dt>
                                    <dd class="col-sm-8">
                                        <?= htmlspecialchars(function_exists('formatPHPhone') && $s['phone_mobile'] ? formatPHPhone($s['phone_mobile']) : ($s['phone_mobile'] ?: '-')) ?>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">매장전화</dt>
                                    <dd class="col-sm-8">
                                        <?= htmlspecialchars(function_exists('formatPHPhone') && $s['phone_landline'] ? formatPHPhone($s['phone_landline']) : ($s['phone_landline'] ?: '-')) ?>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">카카오톡 ID</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['kakao_id'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">카카오 채널 ID</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['kakao_channel_id'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">텔레그램 설정</dt>
                                    <dd class="col-sm-8 text-truncate">
                                        상태: <?= ($s['use_telegram_alert'] == 'Y') ? '<span class="text-success fw-bold">활성</span>' : '<span class="text-danger fw-bold">비활성</span>' ?><br>
                                        Chat ID: <?= htmlspecialchars($s['telegram_chat_id'] ?: '미설정') ?><br>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">텔레그램 수신 알림</dt>
                                    <dd class="col-sm-8 text-truncate">
                                        <span class="big text-muted">수신:
                                            <?php
                                            $alert_types = explode(',', $s['telegram_alert_types'] ?? 'order,cancel');
                                            $alert_labels = [];
                                            if (in_array('order', $alert_types)) $alert_labels[] = '주문';
                                            if (in_array('cancel', $alert_types)) $alert_labels[] = '취소';
                                            if (in_array('message', $alert_types)) $alert_labels[] = '본사알림';
                                            if (in_array('review', $alert_types)) $alert_labels[] = '리뷰';
                                            echo $alert_labels ? implode(', ', $alert_labels) : '없음';
                                            ?>
                                        </span>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">지역</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['location_city'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">실제 주소</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['physical_address'] ?: '-') ?></dd>
                                </dl>
                            </div>

                            <!-- 3. 배달 및 운영 정책 -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-secondary mb-0">
                                        <i class="bi <?= in_array($s['category'], ['fnb', 'cafe', 'mart']) ? 'bi-truck' : 'bi-clock-history' ?> me-2"></i>
                                        <?= in_array($s['category'], ['fnb', 'cafe', 'mart']) ? '배달 및 운영 정책' : '운영 정책' ?>
                                    </h6>
                                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editDeliveryModal"><i class="bi bi-pencil me-1"></i>수정</button>
                                </div>

                                <dl class="row small mb-0">
                                    <dt class="col-sm-4 text-muted">영업 시간</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        $bh = $s['business_hours'] ?? '';
                                        if (!empty($bh) && ($bh[0] === '{' || $bh[0] === '[')) {
                                            echo '<span class="badge bg-light text-primary border border-primary-subtle">고급 설정 (요일별)</span>';
                                        } else {
                                            echo htmlspecialchars($bh ?: '-');
                                        }
                                        ?>
                                    </dd>
                                    <?php if (in_array($s['category'], ['fnb', 'cafe', 'mart'])): ?>
                                    <dt class="col-sm-4 text-muted">배달 가능 시간</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['delivery_hours'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">최소 주문 금액</dt>
                                    <dd class="col-sm-8 text-primary fw-bold">₱ <?= number_format((int)$s['min_delivery_amount']) ?></dd>
                                    <dt class="col-sm-4 text-muted">예상 배달 시간</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['estimated_delivery_time'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">배달비 안내</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['delivery_fee_info'] ?: '-') ?></dd>
                                    <?php endif; ?>
                                    <dt class="col-sm-4 text-muted">결제 수단</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['payment_methods'] ?: '-') ?></dd>
                                    <?php if (in_array($s['category'], ['fnb', 'cafe', 'mart'])): ?>
                                    <dt class="col-sm-4 text-muted">배달 지원 여부</dt>
                                    <dd class="col-sm-8">
                                        <?= ($s['is_delivery_available'] ?? 1) == 1 ? '<span class="text-success fw-bold">가능</span>' : '<span class="text-danger fw-bold">불가</span>' ?>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">매장픽업 가능 여부</dt>
                                    <dd class="col-sm-8">
                                        <?= ($s['is_pickup_available'] ?? 1) == 1 ? '<span class="text-success fw-bold">가능</span>' : '<span class="text-danger fw-bold">불가</span>' ?>
                                    </dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            <!-- 4. UI 설정 및 기타 -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-display me-2"></i>UI 설정 및 시스템</h6>
                                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editUiModal"><i class="bi bi-pencil me-1"></i>수정</button>
                                </div>

                                <dl class="row small mb-0">
                                    <dt class="col-sm-4 text-muted">스킨 / 폰트</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($s['shop_skin'] ?? 'default') ?> /
                                        <?= htmlspecialchars($s['shop_font'] ?? 'Pretendard') ?></dd>
                                    <dt class="col-sm-4 text-muted">메인 홍보 문구</dt>
                                    <dd class="col-sm-8 text-truncate"
                                        title="<?= htmlspecialchars($s['main_title'] ?? '') ?>">
                                        <?= htmlspecialchars($s['main_title'] ?: '-') ?></dd>
                                    <dt class="col-sm-4 text-muted">기능 노출 현황</dt>
                                    <dd class="col-sm-8">
                                        <span
                                            class="badge <?= ($s['is_show_main_title'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">메인문구</span>
                                        <span
                                            class="badge <?= ($s['is_show_story'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">스토리</span>
                                        <span
                                            class="badge <?= ($s['is_show_gallery'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">갤러리</span>
                                        <span
                                            class="badge <?= ($s['is_show_map'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">지도</span>
                                        <span
                                            class="badge <?= ($s['is_show_review'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">리뷰</span>
                                        <span
                                            class="badge <?= ($s['is_show_delivery'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">배달</span>
                                        <span
                                            class="badge <?= (($ui['is_multilingual'] ?? 0) == 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">다국어</span>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">개별 리소스 한도</dt>
                                    <dd class="col-sm-8">
                                        <span class="text-info fw-bold">주문:</span>
                                        <?= $s['custom_free_orders'] !== null ? number_format($s['custom_free_orders']) . '건' : '<span class="text-muted">기본값</span>' ?>
                                        |
                                        <span class="text-info fw-bold">용량:</span>
                                        <?= $s['custom_free_disk_mb'] !== null ? number_format($s['custom_free_disk_mb']) . 'MB' : '<span class="text-muted">기본값</span>' ?>
                                    </dd>
                                    <dt class="col-sm-4 text-muted">가입일 / 최종수정</dt>
                                    <dd class="col-sm-8"><?= date('y-m-d H:i', strtotime($s['created_at'])) ?> <span
                                            class="text-muted mx-1">|</span>
                                        <?= date('y-m-d H:i', strtotime($s['updated_at'])) ?></dd>
                                </dl>
                            </div>

                            <!-- 5. 공지사항 및 상점 설명 -->
                            <?php if (!empty($s['urgent_notice']) || !empty($s['general_notice']) || !empty($s['shop_description'])): ?>
                                <div class="col-12 mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-megaphone me-2"></i>공지사항 및 상점 설명</h6>
                                        <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editUiModal"><i class="bi bi-pencil me-1"></i>수정</button>
                                    </div>
                                    <div class="bg-white p-3 border rounded shadow-sm">
                                        <?php if (!empty($s['urgent_notice'])): ?>
                                            <div class="mb-2"><span class="badge bg-danger me-2 align-top">긴급 공지</span> 
                                                <div class="small text-danger fw-bold d-inline-block"><?= $s['urgent_notice'] ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['general_notice'])): ?>
                                            <div class="mb-2"><span class="badge bg-info text-dark me-2 align-top">일반 공지</span> 
                                                <div class="small d-inline-block"><?= $s['general_notice'] ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['shop_intro']) || !empty($s['shop_description'])): ?>
                                            <div class="mb-0"><span class="badge bg-secondary me-2 align-top">상점 설명</span> <span
                                                    class="small fw-bold me-2"><?= htmlspecialchars($s['shop_intro'] ?? '') ?></span>
                                                <div class="small text-muted mt-2 pt-2 border-top"><?= $s['shop_description'] ?? '' ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col-12 mb-1">
                <div class="fs-4 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-sliders2-vertical me-2 text-warning"></i>
                    <span>상점 상태 수정</span>
                </div>
            </div>
            <!-- [신규] 상점 상태 수동 섹션 -->
            <div class="mt-2 p-3 bg-white border rounded shadow-sm border-start border-4 border-warning">

                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>">
                    <input type="hidden" name="action" value="update_manual_status">

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">상태 변경</label>
                            <select name="manual_status"
                                class="form-select form-select-sm fw-bold <?= $s['status'] === 'active' ? 'text-success' : 'text-danger' ?>">
                                <option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>정상영업 (active)
                                </option>
                                <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>휴점
                                    (inactive)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted mb-1">적용 일시</label>
                            <input type="datetime-local" name="status_date" class="form-control form-control-sm"
                                value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-1">변경 사유</label>
                        <input type="text" name="status_reason" class="form-control form-control-sm"
                            placeholder="예: 관리자 수동 처리, 연체 등" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">발송할 안내 메시지</label>
                        <select name="message_template" class="form-select form-select-sm border-primary">
                            <option value="">(발송 안 함 - 상태만 변경)</option>
                            <?php foreach ($message_templates as $tpl_key => $tpl): ?>
                                <option value="<?= htmlspecialchars($tpl_key) ?>"><?= htmlspecialchars($tpl['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text x-small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>템플릿을 선택하면
                            상태 변경과 동시에 상점에 쪽지가 발송됩니다.</div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold px-4 shadow-sm"
                            onclick="return confirm('설정한 내용으로 상점 상태를 변경하시겠습니까?');">
                            <i class="bi bi-check-lg me-1"></i> 상태 변경 적용하기
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $tab_mode = 'view';
        include __DIR__ . '/manage_shop_tabs.php';
        ?>
</div> <!-- // [수정] 열려있던 <div class="row">를 안전하게 닫습니다. -->
<?php else: ?>
    <!-- 상점이 선택되지 않은 경우 출력되는 빈 화면 안내 -->
    <div class="row justify-content-center py-5">
        <div class="col-md-6 text-center">
            <div class="p-5 bg-white rounded-4 shadow-sm border">
                <i class="bi bi-search text-light-emphasis mb-3" style="font-size: 4rem;"></i>
                <h4 class="fw-bold text-dark">관리할 상점을 검색해 주세요</h4>
                <p class="text-muted">상단 검색창에 상점의 <strong>ID 번호</strong> 또는 <strong>서브도메인</strong>을 입력하세요.</p>
                <div class="mt-4">
                    <a href="admin_view.php?page=manage_shops" class="btn btn-primary px-4 rounded-pill fw-bold">상점 목록
                        보러가기</a>
                </div>
            </div>
        </div>
    </div>

    <!-- [모달 4] 로그 내역 수정 모달 (엉뚱한 위치에 있던 모달을 올바른 섹션으로 이동) -->
    <div class="modal fade" id="editLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs">
                    <div class="modal-header bg-info text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>로그 내역 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_log">
                        <input type="hidden" name="log_index" id="edit_log_index">
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">유형</label>
                            <select name="log_type" id="edit_log_type" class="form-select">
                                <option value="info">정보 (info)</option>
                                <option value="status">상태 (status)</option>
                                <option value="billing">결제 (billing)</option>
                                <option value="message">메시지 (message)</option>
                                <option value="email">이메일 (email)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">제목</label>
                            <input type="text" name="log_title" id="edit_log_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">상세 내용</label>
                            <textarea name="log_content" id="edit_log_content" class="form-control" rows="4"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="small mb-1 fw-bold">일시</label>
                            <input type="datetime-local" name="log_date" id="edit_log_date" class="form-control"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-info text-white px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- 
  =============================================================
  [모달 영역] 수정 폼 등 팝업 화면들
  ============================================================= 
-->
<?php if ($s): ?>
    <!-- [모달 1] 기본 정보 및 계정 수정 모달 -->
    <div class="modal fade" id="editBasicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>기본 정보 및 계정 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">서브도메인 (상점 아이디)</label>
                                <input class="form-control bg-light" type="text"
                                    value="<?= htmlspecialchars($s['subdomain']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">연결 도메인</label>
                                <input class="form-control" name="custom_domain" type="text"
                                    value="<?= htmlspecialchars($s['custom_domain'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">상점명 (국문)</label>
                                <input class="form-control" name="shop_name" type="text"
                                    value="<?= htmlspecialchars($s['shop_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">상점명 (영문)</label>
                                <input class="form-control" name="shop_name_en" type="text"
                                    value="<?= htmlspecialchars($s['shop_name_en'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label">관리자 이메일 (ID)</label>
                            <input class="form-control bg-light" name="manager_email" type="email"
                                value="<?= htmlspecialchars($s['manager_email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label text-primary">관리자 비밀번호 변경 <small class="text-muted fw-normal">(변경
                                    시에만 입력)</small></label>
                            <input class="form-control border-primary-subtle" name="new_password" type="password"
                                placeholder="새 비밀번호 입력">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">관리자명 (국문)</label>
                                <input class="form-control" name="manager_name" type="text"
                                    value="<?= htmlspecialchars($s['manager_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">관리자명 (영문)</label>
                                <input class="form-control" name="manager_name_en" type="text"
                                    value="<?= htmlspecialchars($s['manager_name_en'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">업종 카테고리</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($shop_category_labels as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($s['category'] ?? '') == $key ? 'selected' : '' ?>>
                                            <?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">상점 상태</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= $s['status'] == 'active' ? 'selected' : '' ?>>Active (운영)
                                    </option>
                                    <option value="applying" <?= $s['status'] == 'applying' ? 'selected' : '' ?>>Applying
                                        (신청)</option>
                                    <option value="testing" <?= $s['status'] == 'testing' ? 'selected' : '' ?>>Testing (테스트)
                                    </option>
                                    <option value="inactive" <?= $s['status'] == 'inactive' ? 'selected' : '' ?>>Inactive
                                        (중지)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 2] 연락처 및 위치 정보 수정 모달 -->
    <div class="modal fade" id="editContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2"></i>연락처 및 위치 정보 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">휴대폰 번호</label>
                                <input class="form-control" name="phone_mobile" type="text"
                                    value="<?= htmlspecialchars($s['phone_mobile'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">일반 전화</label>
                                <input class="form-control" name="phone_landline" type="text"
                                    value="<?= htmlspecialchars($s['phone_landline'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">카카오톡 ID</label>
                                <input class="form-control" name="kakao_id" type="text"
                                    value="<?= htmlspecialchars($s['kakao_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">카카오 채널 ID</label>
                                <input class="form-control" name="kakao_channel_id" type="text"
                                    value="<?= htmlspecialchars($s['kakao_channel_id'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="item-attr-label">텔레그램 Chat ID</label>
                                <input class="form-control" name="telegram_chat_id" type="text"
                                    value="<?= htmlspecialchars($s['telegram_chat_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label text-primary">알림 활성화</label>
                                <select name="use_telegram_alert" class="form-select">
                                    <option value="Y" <?= ($s['use_telegram_alert'] ?? 'N') == 'Y' ? 'selected' : '' ?>>활성화 (Y)</option>
                                    <option value="N" <?= ($s['use_telegram_alert'] ?? 'N') != 'Y' ? 'selected' : '' ?>>비활성화 (N)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">지역 (City)</label>
                                <input class="form-control" name="location_city" type="text"
                                    value="<?= htmlspecialchars($s['location_city'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="item-attr-label text-primary">텔레그램 수신 알림 종류</label>
                                <?php $alert_types = explode(',', $s['telegram_alert_types'] ?? 'order,cancel,message,review'); ?>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="telegram_alert_types[]" value="order" id="alert_order" <?= in_array('order', $alert_types) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="alert_order">신규 주문</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="telegram_alert_types[]" value="cancel" id="alert_cancel" <?= in_array('cancel', $alert_types) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="alert_cancel">주문 취소</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="telegram_alert_types[]" value="message" id="alert_message" <?= in_array('message', $alert_types) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="alert_message">본사 알림/쪽지</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="telegram_alert_types[]" value="review" id="alert_review" <?= in_array('review', $alert_types) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="alert_review">고객 리뷰</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="item-attr-label">실제 주소(Physical Address)</label>
                                <input class="form-control" name="physical_address" type="text"
                                    value="<?= htmlspecialchars($s['physical_address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 3] 배달 및 운영 정책 수정 모달 -->
    <div class="modal fade" id="editDeliveryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>배달 및 운영 정책 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">영업 시간</label>
                                <input class="form-control" name="business_hours" type="text"
                                    value="<?= htmlspecialchars($s['business_hours'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">배달 가능 시간</label>
                                <input class="form-control" name="delivery_hours" type="text"
                                    value="<?= htmlspecialchars($s['delivery_hours'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="item-attr-label">최소 주문 금액</label>
                                <input class="form-control" name="min_delivery_amount" type="number"
                                    value="<?= htmlspecialchars($s['min_delivery_amount'] ?? '0') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">배달 예상 시간</label>
                                <input class="form-control" name="estimated_delivery_time" type="text"
                                    value="<?= htmlspecialchars($s['estimated_delivery_time'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">배달 가능 여부</label>
                                <select name="is_delivery_available" class="form-select">
                                    <option value="1" <?= ($s['is_delivery_available'] ?? 1) == 1 ? 'selected' : '' ?>>가능</option>
                                    <option value="0" <?= ($s['is_delivery_available'] ?? 1) == 0 ? 'selected' : '' ?>>불가</option>
                                </select>
                            </div>
                            <div class="col-md-4 mt-3">
                                <label class="item-attr-label">매장픽업 가능 여부</label>
                                <select name="is_pickup_available" class="form-select">
                                    <option value="1" <?= ($s['is_pickup_available'] ?? 1) == 1 ? 'selected' : '' ?>>가능
                                    </option>
                                    <option value="0" <?= ($s['is_pickup_available'] ?? 1) == 0 ? 'selected' : '' ?>>불가
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">배달비 안내</label>
                                <input class="form-control" name="delivery_fee_info" type="text"
                                    value="<?= htmlspecialchars($s['delivery_fee_info'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">결제 수단</label>
                                <input class="form-control" name="payment_methods" type="text"
                                    value="<?= htmlspecialchars($s['payment_methods'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 4] UI 설정 및 시스템 수정 모달 -->
    <div class="modal fade" id="editUiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-display me-2"></i>UI 설정 및 공지사항 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <input type="hidden" name="is_ui_update" value="1">

                        <div class="section-header-label border-bottom pb-2 mb-3 text-primary">01. 홈페이지 홍보 및 설정</div>
                        <div class="mb-3">
                            <label class="item-attr-label text-danger">긴급 공지사항</label>
                            <textarea class="form-control" name="urgent_notice"
                                rows="2"><?= htmlspecialchars($s['urgent_notice'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label text-info">일반 공지사항</label>
                            <textarea class="form-control" name="general_notice"
                                rows="3"><?= htmlspecialchars($s['general_notice'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="item-attr-label">상단 라벨</label>
                                <input class="form-control" name="top_label" type="text"
                                    value="<?= htmlspecialchars($s['top_label'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">메인 타이틀</label>
                                <input class="form-control" name="main_title" type="text"
                                    value="<?= htmlspecialchars($s['main_title'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">서브 타이틀</label>
                                <input class="form-control" name="sub_title" type="text"
                                    value="<?= htmlspecialchars($s['sub_title'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label">한줄 소개 (Intro)</label>
                            <input class="form-control" name="shop_intro" type="text"
                                value="<?= htmlspecialchars($s['shop_intro'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label">상세 설명</label>
                            <textarea class="form-control" name="shop_description"
                                rows="4"><?= htmlspecialchars($s['shop_description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label">홍보 유튜브 URL</label>
                            <input class="form-control" name="shop_youtube_url" type="text"
                                value="<?= htmlspecialchars($s['shop_youtube_url'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="item-attr-label">구글 지도 임베드 코드 (HTML)</label>
                            <textarea class="form-control" name="shop_map_html"
                                rows="3"><?= htmlspecialchars($s['shop_map_html'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">스킨 테마</label>
                                <select name="shop_skin" class="form-select">
                                    <option value="default"
                                        <?= ($s['shop_skin'] ?? 'default') == 'default' ? 'selected' : '' ?>>기본 화이트</option>
                                    <option value="dark" <?= ($s['shop_skin'] ?? '') == 'dark' ? 'selected' : '' ?>>모던 다크
                                    </option>
                                    <option value="luxury" <?= ($s['shop_skin'] ?? '') == 'luxury' ? 'selected' : '' ?>>럭셔리
                                        골드</option>
                                    <option value="nature" <?= ($s['shop_skin'] ?? '') == 'nature' ? 'selected' : '' ?>>네이처
                                        그린</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">폰트 스타일</label>
                                <select name="shop_font" class="form-select">
                                    <option value="Pretendard"
                                        <?= ($s['shop_font'] ?? 'Pretendard') == 'Pretendard' ? 'selected' : '' ?>>고딕(깔끔함)
                                    </option>
                                    <option value="Noto Sans KR"
                                        <?= ($s['shop_font'] ?? '') == 'Noto Sans KR' ? 'selected' : '' ?>>본고딕(표준)</option>
                                    <option value="Nanum Gothic"
                                        <?= ($s['shop_font'] ?? '') == 'Nanum Gothic' ? 'selected' : '' ?>>나눔고딕(부드러움)
                                    </option>
                                    <option value="Nanum Myeongjo"
                                        <?= ($s['shop_font'] ?? '') == 'Nanum Myeongjo' ? 'selected' : '' ?>>명조(우아함)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="section-header-label border-bottom pb-2 mt-4 mb-3">02. 기능 노출(ON/OFF) 및 레이블 설정</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">메인 타이틀</label>
                                <select name="is_show_main_title" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_main_title'] ?? 1) == 1 ? 'selected' : '' ?>>ON
                                    </option>
                                    <option value="0" <?= ($s['is_show_main_title'] ?? 1) == 0 ? 'selected' : '' ?>>OFF
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">스토리 섹션</label>
                                <select name="is_show_story" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_story'] ?? 1) == 1 ? 'selected' : '' ?>>ON</option>
                                    <option value="0" <?= ($s['is_show_story'] ?? 1) == 0 ? 'selected' : '' ?>>OFF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">갤러리 섹션</label>
                                <select name="is_show_gallery" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_gallery'] ?? 1) == 1 ? 'selected' : '' ?>>ON</option>
                                    <option value="0" <?= ($s['is_show_gallery'] ?? 1) == 0 ? 'selected' : '' ?>>OFF
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">위치/지도</label>
                                <select name="is_show_map" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_map'] ?? 1) == 1 ? 'selected' : '' ?>>ON</option>
                                    <option value="0" <?= ($s['is_show_map'] ?? 1) == 0 ? 'selected' : '' ?>>OFF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">리뷰 섹션</label>
                                <select name="is_show_review" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_review'] ?? 1) == 1 ? 'selected' : '' ?>>ON</option>
                                    <option value="0" <?= ($s['is_show_review'] ?? 1) == 0 ? 'selected' : '' ?>>OFF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">배달 정책</label>
                                <select name="is_show_delivery" class="form-select form-select-sm">
                                    <option value="1" <?= ($s['is_show_delivery'] ?? 1) == 1 ? 'selected' : '' ?>>ON
                                    </option>
                                    <option value="0" <?= ($s['is_show_delivery'] ?? 1) == 0 ? 'selected' : '' ?>>OFF
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">다국어 지원</label>
                                <select name="ui[is_multilingual]" class="form-select form-select-sm">
                                    <option value="1" <?= (($ui['is_multilingual'] ?? 0) == 1) ? 'selected' : '' ?>>ON</option>
                                    <option value="0" <?= (($ui['is_multilingual'] ?? 0) == 0) ? 'selected' : '' ?>>OFF</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="item-attr-label text-primary">화폐 단위</label>
                                <select name="ui[currency]" class="form-select form-select-sm">
                                    <option value="PHP" <?= (($ui['currency'] ?? 'PHP') == 'PHP') ? 'selected' : '' ?>>₱ (PHP)</option>
                                    <option value="KRW" <?= (($ui['currency'] ?? '') == 'KRW') ? 'selected' : '' ?>>₩ (KRW)</option>
                                    <option value="USD" <?= (($ui['currency'] ?? '') == 'USD') ? 'selected' : '' ?>>$ (USD)</option>
                                    <option value="JPY" <?= (($ui['currency'] ?? '') == 'JPY') ? 'selected' : '' ?>>¥ (JPY)</option>
                                    <option value="CNY" <?= (($ui['currency'] ?? '') == 'CNY') ? 'selected' : '' ?>>¥ (CNY)</option>
                                    <option value="VND" <?= (($ui['currency'] ?? '') == 'VND') ? 'selected' : '' ?>>₫ (VND)</option>
                                </select>
                            </div>
                        </div>

                        <!-- JSON 형식으로 묶여서 DB에 저장될 상점별 맞춤 UI 텍스트(라벨) 설정 영역 -->
                        <div class="row g-3">
                            <!-- 공통 섹션 -->
                            <div class="col-md-4">
                                <label class="item-attr-label">스토리 섹션명</label>
                                <input class="form-control form-control-sm" name="ui[label_story]" type="text"
                                    value="<?= htmlspecialchars($ui['label_story'] ?? SHOP_DEFAULT_LABEL_STORY) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">갤러리 섹션명</label>
                                <input class="form-control form-control-sm" name="ui[label_gallery]" type="text"
                                    value="<?= htmlspecialchars($ui['label_gallery'] ?? SHOP_DEFAULT_LABEL_GALLERY) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="item-attr-label">위치/지도 섹션명</label>
                                <input class="form-control form-control-sm" name="ui[label_location]" type="text"
                                    value="<?= htmlspecialchars($ui['label_location'] ?? SHOP_DEFAULT_LABEL_LOCATION) ?>">
                            </div>

                            <!-- 업종별 섹션 (F&B) -->
                            <?php if ($s['category'] === 'fnb' || $s['category'] === 'cafe'): ?>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold">실물 메뉴판 섹션명</label>
                                    <input class="form-control form-control-sm" name="ui[label_menu_board]" type="text"
                                        value="<?= htmlspecialchars($ui['label_menu_board'] ?? (defined('FNB_DEFAULT_LABEL_MENU_BOARD') ? FNB_DEFAULT_LABEL_MENU_BOARD : '전체 메뉴판 보기 (ALL MENUS)')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold">신상품 섹션명</label>
                                    <input class="form-control form-control-sm" name="ui[label_new_menu]" type="text"
                                        value="<?= htmlspecialchars($ui['label_new_menu'] ?? (defined('FNB_DEFAULT_LABEL_NEW_MENU') ? FNB_DEFAULT_LABEL_NEW_MENU : '신상품 (NEW ARRIVALS)')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold">대표메뉴 섹션명</label>
                                    <input class="form-control form-control-sm" name="ui[label_best_menu]" type="text"
                                        value="<?= htmlspecialchars($ui['label_best_menu'] ?? (defined('FNB_DEFAULT_LABEL_BEST_MENU') ? FNB_DEFAULT_LABEL_BEST_MENU : '대표 메뉴 (BEST SELLER)')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold">일반메뉴 섹션명</label>
                                    <input class="form-control form-control-sm" name="ui[label_all_menu]" type="text"
                                        value="<?= htmlspecialchars($ui['label_all_menu'] ?? (defined('FNB_DEFAULT_LABEL_ALL_MENU') ? FNB_DEFAULT_LABEL_ALL_MENU : '메뉴들 (OUR MENU)')) ?>">
                                </div>
                            <?php endif; ?>

                            <!-- 업종별 특수 설정 (부동산) -->
                            <?php if ($s['category'] === 'realty'): ?>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold text-success"><i class="bi bi-person-vcard me-1"></i>중개사
                                        자격증 번호</label>
                                    <input class="form-control form-control-sm border-success" name="ui[broker_license]"
                                        type="text" value="<?= htmlspecialchars($ui['broker_license'] ?? '') ?>"
                                        placeholder="예: PRC REB No. 12345">
                                </div>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold text-success"><i class="bi bi-building-check me-1"></i>소속
                                        중개 법인명</label>
                                    <input class="form-control form-control-sm border-success" name="ui[realty_agency]"
                                        type="text" value="<?= htmlspecialchars($ui['realty_agency'] ?? '') ?>"
                                        placeholder="예: Global Estate Corp.">
                                </div>
                            <?php endif; ?>

                            <!-- 업종별 특수 설정 (서비스/수리/청소) -->
                            <?php if ($s['category'] === 'service'): ?>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold text-info"><i class="bi bi-tools me-1"></i>기본 출장비 (Call-out
                                        Fee)</label>
                                    <input class="form-control form-control-sm border-info" name="ui[callout_fee]" type="text"
                                        value="<?= htmlspecialchars($ui['callout_fee'] ?? '') ?>" placeholder="예: 500 PHP">
                                </div>
                                <div class="col-md-6">
                                    <label class="small mb-1 fw-bold text-info"><i class="bi bi-clock-history me-1"></i>예약 시간 간격
                                        (분 단위)</label>
                                    <input class="form-control form-control-sm border-info" name="ui[booking_interval]"
                                        type="number" value="<?= htmlspecialchars($ui['booking_interval'] ?? '30') ?>"
                                        placeholder="예: 30">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="section-header-label border-bottom pb-2 mt-4 mb-3 text-primary"><i
                                class="bi bi-star-fill me-1"></i>03. 개별 리소스 예외 혜택 (슈퍼관리자 전용)</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="item-attr-label">개별 무료 주문 건수 한도</label>
                                <input class="form-control" name="custom_free_orders" type="number"
                                    value="<?= htmlspecialchars($s['custom_free_orders'] ?? '') ?>"
                                    placeholder="빈칸이면 사이트 기본값 적용">
                            </div>
                            <div class="col-md-6">
                                <label class="item-attr-label">개별 무료 디스크 한도 (MB)</label>
                                <input class="form-control" name="custom_free_disk_mb" type="number"
                                    value="<?= htmlspecialchars($s['custom_free_disk_mb'] ?? '') ?>"
                                    placeholder="빈칸이면 사이트 기본값 적용">
                            </div>
                            <div class="form-text small mt-1">값을 비워두면 관리자 설정(manage_site)의 공통 리소스 과금 정책을 따릅니다.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($s): ?>
    <!-- [모달 2] 메시지 수정 모달 -->
    <div class="modal fade" id="editBoardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>메시지 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="board_id" id="edit_board_id">
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">제목</label>
                            <input type="text" name="title" id="edit_board_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">내용</label>
                            <textarea name="content" id="edit_board_content" class="form-control" rows="5"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-dark px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 3] 결제 수납 내역 수정 모달 -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST"
                    action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments&pay_page=<?= $pay_page ?>&f_year=<?= $f_year ?>&f_month=<?= $f_month ?>&f_note=<?= urlencode($f_note) ?>">

                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>결제 내역 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_payment">
                        <input type="hidden" name="payment_id" id="edit_payment_id">
                        <div class="mb-3"><label class="small mb-1 fw-bold">항목</label>
                            <select name="pay_type" id="edit_pay_type" class="form-select">
                                <?php foreach ($pay_type_labels as $val => $label): ?><option value="<?= $val ?>">
                                        <?= $label ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">금액</label><input type="number" name="amount"
                                id="edit_amount" class="form-control" required></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">청구일</label><input type="date"
                                name="billing_date" id="edit_billing_date" class="form-control"></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">만료일</label><input type="date"
                                name="expiring_date" id="edit_expiring_date" class="form-control"></div>
                        <div class="mb-3">
                            <label class="small fw-bold mb-1">납부여부/일자</label>
                            <div class="input-group input-group-sm shadow-sm">
                                <div class="input-group-text border-0 bg-white">
                                    <input class="form-check-input mt-0" type="checkbox" name="paid" id="edit_paid_check"
                                        value="y" onchange="toggleEditPayDate()">
                                </div>
                                <input type="date" name="pay_date" id="edit_pay_date" class="form-control border-0"
                                    disabled>
                            </div>
                        </div>
                        <div class="mb-3 form-check form-switch bg-light border rounded p-3">
                            <input class="form-check-input" type="checkbox" name="bill_next_6_months"
                                id="bill_next_6_months" value="1" checked>
                            <label class="form-check-label fw-bold text-primary" for="bill_next_6_months">
                                다음 6개월 사용료 자동 청구
                            </label>
                            <div class="form-text small mt-1">이 결제건의 만료일 다음날부터 6개월치 사용료를 '미납' 상태로 자동 생성합니다.</div>
                        </div>
                        <div class="mb-0"><label class="small mb-1 fw-bold">비고</label><input type="text" name="note"
                                id="edit_note" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 
      =============================================================
      [JavaScript 영역] 클라이언트 사이드 동적 처리 함수들
      ============================================================= 
    -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // [결제 내역 검색 폼 AJAX 처리]
            const paymentSearchForm = document.getElementById('payment-search-form');
            if (paymentSearchForm) {
                paymentSearchForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(paymentSearchForm);
                    const params = new URLSearchParams(formData);

                    let url = 'manage_shop.php?' + params.toString() + '&ajax_payments=1';

                    const paymentContainer = document.getElementById('payment_table_container');
                    if (!paymentContainer) return;

                    paymentContainer.style.opacity = '0.5';
                    paymentContainer.style.pointerEvents = 'none';
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('Network error');
                        paymentContainer.innerHTML = await response.text();
                    } catch (error) {
                        console.error('AJAX Load Error:', error);
                        alert('검색 결과를 불러오는데 실패했습니다.');
                    } finally {
                        paymentContainer.style.opacity = '1';
                        paymentContainer.style.pointerEvents = 'auto';
                    }
                });
            }

            // 전역 이벤트 위임 (AJAX 탭 교체 및 동적 DOM 요소 지원)
            document.body.addEventListener('click', async function(e) {

                // [결제 내역 검색 초기화 AJAX 처리]
                const btnPaymentReset = e.target.closest('#btn-payment-reset');
                if (btnPaymentReset) {
                    e.preventDefault();
                    const paymentContainer = document.getElementById('payment_table_container');
                    if (!paymentContainer) return;

                    let url = btnPaymentReset.href.replace('admin_view.php?page=manage_shop&',
                        'manage_shop.php?');
                    url += '&ajax_payments=1';

                    paymentContainer.style.opacity = '0.5';
                    paymentContainer.style.pointerEvents = 'none';
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('Network error');
                        paymentContainer.innerHTML = await response.text();

                        // 폼 입력값 명시적 초기화
                        if (paymentSearchForm) {
                            paymentSearchForm.querySelector('select[name="f_year"]').value = '';
                            paymentSearchForm.querySelector('select[name="f_month"]').value = '';
                            paymentSearchForm.querySelector('input[name="f_note"]').value = '';
                        }
                    } catch (error) {
                        console.error('AJAX Load Error:', error);
                        alert('초기화 데이터를 불러오는데 실패했습니다.');
                    } finally {
                        paymentContainer.style.opacity = '1';
                        paymentContainer.style.pointerEvents = 'auto';
                    }
                }

                // [결제 내역 AJAX 삭제 처리]
                const delPayBtn = e.target.closest('.btn-delete-payment');
                if (delPayBtn) {
                    e.preventDefault();
                    if (!confirm('이 비용 청구 내역을 정말 삭제하시겠습니까?')) return;

                    const tr = delPayBtn.closest('tr');
                    const paymentId = delPayBtn.dataset.id;
                    const originalHtml = delPayBtn.innerHTML;

                    delPayBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    delPayBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'ajax_delete_payment');
                    formData.append('payment_id', paymentId);

                    try {
                        const response = await fetch('manage_shop.php?id=<?= $shop_id ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const json = await response.json();
                        if (json.status === 'success') {
                            tr.style.transition = "opacity 0.3s ease";
                            tr.style.opacity = "0";
                            setTimeout(() => {
                                tr.remove();
                            }, 300);
                        } else {
                            alert('삭제 중 오류가 발생했습니다: ' + (json.message || ''));
                            delPayBtn.disabled = false;
                            delPayBtn.innerHTML = originalHtml;
                        }
                    } catch (err) {
                        alert('통신 오류가 발생했습니다.');
                        delPayBtn.disabled = false;
                        delPayBtn.innerHTML = originalHtml;
                    }
                }

                // [결제 내역 AJAX 정렬 및 페이징]
                const payLink = e.target.closest('#payment_table_container .ajax-pay-link');
                if (payLink) {
                    e.preventDefault();
                    const paymentContainer = document.getElementById('payment_table_container');
                    if (!paymentContainer) return;

                    // admin_view.php 껍데기를 제외하고 실제 처리 파일(manage_shop.php)로 백그라운드 호출
                    let url = payLink.href.replace('admin_view.php?page=manage_shop&', 'manage_shop.php?');
                    url += '&ajax_payments=1';

                    paymentContainer.style.opacity = '0.5';
                    paymentContainer.style.pointerEvents = 'none';
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('Network error');
                        paymentContainer.innerHTML = await response.text();
                    } catch (error) {
                        console.error('AJAX Load Error:', error);
                        alert('데이터를 불러오는데 실패했습니다.');
                    } finally {
                        paymentContainer.style.opacity = '1';
                        paymentContainer.style.pointerEvents = 'auto';
                    }
                }

            });

            // [전역 이벤트 위임] 템플릿 변경 이벤트 처리 (AJAX 탭 교체 후에도 동작 지원)
            document.body.addEventListener('change', function(e) {
                if (e.target && e.target.id === 'sel_send_type') {
                    const type = e.target.value;
                    const selTpl = document.getElementById('sel_template_key');
                    if (!selTpl) return;

                    selTpl.innerHTML = '';

                    let optCustom = document.createElement('option');
                    optCustom.value = 'custom';
                    optCustom.text = '직접 입력 (자유형)';
                    selTpl.appendChild(optCustom);

                    const data = templatesData[type];

                    for (let key in data) {
                        let opt = document.createElement('option');
                        opt.value = key;
                        opt.text = data[key].title;
                        selTpl.appendChild(opt);
                    }

                    selTpl.value = 'custom';

                    // 템플릿 자동 선택 후 연쇄적으로 내용 업데이트 유도
                    selTpl.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

                if (e.target && e.target.id === 'sel_template_key') {
                    const selType = document.getElementById('sel_send_type');
                    const inpTitle = document.getElementById('inp_msg_title');
                    const inpContent = document.getElementById('inp_msg_content');
                    if (!selType || !inpTitle || !inpContent) return;

                    const type = selType.value;
                    const tplKey = e.target.value;
                    if (tplKey === 'custom') {
                        inpTitle.value = '';
                        inpContent.value = '';
                        inpTitle.readOnly = false;
                    } else {
                        if (templatesData[type] && templatesData[type][tplKey]) {
                            inpTitle.value = templatesData[type][tplKey].title;
                            inpContent.value = templatesData[type][tplKey].content;
                        }
                    }
                }
            });

            // [전역 이벤트 위임] 메시지 폼 전송 처리
            document.body.addEventListener('submit', async function(e) {
                if (e.target && e.target.id === 'form-send-msg-email') {
                    e.preventDefault();
                    const formSend = e.target;
                    const btn = document.getElementById('btn-submit-msg-email');
                    const originHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 전송중...';
                    btn.disabled = true;

                    const selType = document.getElementById('sel_send_type');
                    const selTpl = document.getElementById('sel_template_key');

                    const formData = new FormData(formSend);
                    formData.append('action', 'ajax_send_msg_email');
                    try {
                        const res = await fetch('manage_shop.php?id=<?= $shop_id ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            alert(json.message);
                            if (selTpl) {
                                selTpl.value = 'custom';
                                selTpl.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));
                            }
                            loadBoardList(selType ? (selType.value === 'message' ? 'message' :
                                'email_log') : 'message', '', 1);
                        } else {
                            alert('오류: ' + json.message);
                        }
                    } catch (err) {
                        alert('서버 통신 오류가 발생했습니다.');
                    } finally {
                        btn.innerHTML = originHtml;
                        btn.disabled = false;
                    }
                }
            });

            async function loadBoardList(type, keyword, page) {
                const containerId = type === 'message' ? 'msg_table_container' : 'email_table_container';
                const container = document.getElementById(containerId);
                if (!container) return;
                container.style.opacity = '0.5';
                try {
                    const res = await fetch(
                        `manage_shop.php?id=<?= $shop_id ?>&ajax_board=${type}&keyword=${encodeURIComponent(keyword)}&page_num=${page}`
                    );
                    container.innerHTML = await res.text();
                } catch (e) {
                    console.error('List Load Error');
                } finally {
                    container.style.opacity = '1';
                }
            }

            const formSearchMsg = document.getElementById('form-search-msg');
            if (formSearchMsg) {
                formSearchMsg.addEventListener('submit', function(e) {
                    e.preventDefault();
                    loadBoardList('message', this.querySelector('input[name="f_msg"]').value, 1);
                });
            }
            const formSearchEmail = document.getElementById('form-search-email');
            if (formSearchEmail) {
                formSearchEmail.addEventListener('submit', function(e) {
                    e.preventDefault();
                    loadBoardList('email_log', this.querySelector('input[name="f_email"]').value, 1);
                });
            }

            document.body.addEventListener('click', async function(e) {
                const msgLink = e.target.closest('.ajax-msg-link');
                if (msgLink) {
                    e.preventDefault();
                    const p = new URLSearchParams(msgLink.search);
                    loadBoardList('message', p.get('keyword') || '', p.get('page_num'));
                }

                const emailLink = e.target.closest('.ajax-email-link');
                if (emailLink) {
                    e.preventDefault();
                    const p = new URLSearchParams(emailLink.search);
                    loadBoardList('email_log', p.get('keyword') || '', p.get('page_num'));
                }

                const delBtn = e.target.closest('.btn-delete-board');
                if (delBtn) {
                    if (!confirm('정말 삭제하시겠습니까?')) return;
                    const formData = new FormData();
                    formData.append('action', 'ajax_delete_board');
                    formData.append('board_id', delBtn.dataset.id);
                    try {
                        const res = await fetch('manage_shop.php?id=<?= $shop_id ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            const type = delBtn.closest('#msg_table_container') ? 'message' : 'email_log';
                            const kw = (type === 'message' ? formSearchMsg : formSearchEmail).querySelector(
                                'input').value;
                            loadBoardList(type, kw, 1);
                        } else {
                            alert('삭제 중 오류가 발생했습니다.');
                        }
                    } catch (err) {
                        alert('통신 오류');
                    }
                }

                // [로그 내역 수정 모달 호출]
                const btnEditLog = e.target.closest('#logs .btn-edit-log');
                if (btnEditLog) {
                    document.getElementById('edit_log_index').value = btnEditLog.dataset.idx;
                    document.getElementById('edit_log_type').value = btnEditLog.dataset.type;
                    document.getElementById('edit_log_title').value = btnEditLog.dataset.title;
                    document.getElementById('edit_log_content').value = btnEditLog.dataset.content;
                    document.getElementById('edit_log_date').value = btnEditLog.dataset.date;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editLogModal')).show();
                }
            });

            // 메시지 수정 모달 제출 AJAX
            const formEditBoard = document.querySelector('#editBoardModal form');
            if (formEditBoard) {
                formEditBoard.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = this.querySelector('button[type="submit"]');
                    const originHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 수정중...';
                    btn.disabled = true;
                    const formData = new FormData(this);
                    formData.set('action', 'ajax_edit_board');
                    try {
                        const res = await fetch('manage_shop.php?id=<?= $shop_id ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('editBoardModal')).hide();
                            loadBoardList('message', document.querySelector('#form-search-msg input').value,
                                1);
                        } else {
                            alert('오류가 발생했습니다.');
                        }
                    } catch (err) {
                        alert('통신 오류');
                    } finally {
                        btn.innerHTML = originHtml;
                        btn.disabled = false;
                    }
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // [DB / 파일 용량 관리 탭] 삭제 버튼 이벤트 핸들러
            document.body.addEventListener('click', async function(e) {
                const inFilesTab = e.target.closest('#files');
                if (!inFilesTab) return;

                const deleteBtn = e.target.closest('.btn-delete-file');
                const bulkDeleteBtn = e.target.closest('.btn-delete-all-orphaned');

                // 개별 파일 삭제
                if (deleteBtn) {
                    if (!confirm('정말로 이 파일을 삭제하시겠습니까? (복구 불가)')) return;

                    const path = deleteBtn.dataset.path;
                    const li = deleteBtn.closest('li');
                    deleteBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'delete_shop_file');
                    formData.append('file_path', path);

                    try {
                        const res = await fetch('manage_shop.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        if (result.status === 'success') {
                            li.style.transition = "0.3s";
                            li.style.opacity = "0";
                            setTimeout(() => li.remove(), 300);
                        } else {
                            alert('삭제 실패: ' + result.message);
                            deleteBtn.disabled = false;
                        }
                    } catch (err) {
                        alert('서버 통신 오류가 발생했습니다.');
                        deleteBtn.disabled = false;
                    }
                }

                // 일괄 삭제
                if (bulkDeleteBtn) {
                    if (!confirm('경고: 목록에 있는 모든 고아 파일을 일괄 삭제하시겠습니까?\n(삭제 후 복구할 수 없습니다)')) return;

                    const paths = bulkDeleteBtn.dataset.paths;
                    bulkDeleteBtn.disabled = true;
                    bulkDeleteBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span>삭제 중...';

                    const formData = new FormData();
                    formData.append('action', 'delete_shop_files_bulk');
                    formData.append('file_paths', paths);

                    try {
                        const res = await fetch('manage_shop.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();
                        if (result.status === 'success') {
                            alert(`총 ${result.deleted_count}개의 파일이 일괄 삭제되었습니다.`);
                            const ul = bulkDeleteBtn.closest('.card-header').nextElementSibling
                                .querySelector('.orphaned-file-list');
                            if (ul) {
                                ul.innerHTML =
                                    '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>모두 삭제되었습니다.</li>';
                            }
                            bulkDeleteBtn.remove();
                        } else {
                            alert('일괄 삭제 실패: ' + result.message);
                            bulkDeleteBtn.disabled = false;
                            bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                        }
                    } catch (err) {
                        alert('서버 통신 오류가 발생했습니다.');
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                    }
                }
            });
        });
    </script>
    <script>
        // [상점 관리 탭 AJAX 부드러운 전환]
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('click', async function(e) {
                const tabLink = e.target.closest('.ajax-tab-link');
                if (tabLink) {
                    e.preventDefault();
                    const url = tabLink.href;

                    // 탭 UI 즉시 변경
                    document.querySelectorAll('.ajax-tab-link').forEach(el => el.classList.remove(
                        'active'));
                    tabLink.classList.add('active');

                    const tabContentContainer = document.querySelector('.tab-content.border-0');
                    if (tabContentContainer) {
                        tabContentContainer.style.opacity = '0.4';
                        tabContentContainer.style.pointerEvents = 'none';
                    }

                    try {
                        const response = await fetch(url);
                        const html = await response.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        const newContent = doc.querySelector('.tab-content.border-0');
                        if (newContent && tabContentContainer) {
                            tabContentContainer.innerHTML = newContent.innerHTML;
                            window.history.pushState({
                                path: url
                            }, '', url);
                        } else {
                            window.location.href = url;
                        }
                    } catch (err) {
                        window.location.href = url;
                    } finally {
                        if (tabContentContainer) {
                            tabContentContainer.style.opacity = '1';
                            tabContentContainer.style.pointerEvents = 'auto';
                        }
                    }
                }
            });

            // 브라우저 뒤로가기 대응
            window.addEventListener('popstate', function() {
                window.location.reload();
            });
        });
    </script>
    <script>
        // [비용 청구액 자동 계산 헬퍼 함수]
        // '항목' 드롭다운을 변경하거나 '청구일'을 변경했을 때, 청구될 금액 및 만료일을 자동으로 계산하여 입력 폼을 채웁니다.
        function updateAmount() {
            const type = document.getElementById('pay_type_select').value;
            const amountInput = document.getElementById('amount_input');
            const nextBillingInput = document.getElementById('next_billing_input');
            const noteInput = document.getElementById('note_input');
            const fees = <?php echo json_encode($site_fees); ?>;

            // 금액 설정
            if (type === '<?= PAY_TYPE_6MONTHS ?>' && fees.monthly_fee) {
                amountInput.value = fees.monthly_fee * 6;
            } else if (type === '<?= PAY_TYPE_SETUP ?>' && fees.setup_fee) {
                amountInput.value = fees.setup_fee;
            } else {
                amountInput.value = '';
            }

            // 날짜 계산 기준일
            const billingDateVal = document.getElementById('billing_date_input').value;
            const baseDate = billingDateVal ? new Date(billingDateVal) : new Date();

            // YYYY.MM.DD 포맷팅 함수 (내부 사용)
            const formatDate = (date) => {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}.${m}.${d}`;
            };

            if (type === '<?= PAY_TYPE_6MONTHS ?>') {
                // 1. 시작일 포맷팅
                const startDateStr = formatDate(baseDate);

                // 2. 종료일 계산 (6개월 뒤에서 하루 전)
                // 예: 2026.07.30 시작 -> 2027.01.30이 6개월 뒤이므로, 하루 전인 2027.01.29 종료
                const endDate = new Date(baseDate);
                endDate.setMonth(endDate.getMonth() + 6);
                endDate.setDate(endDate.getDate() - 1);
                const endDateStr = formatDate(endDate);

                // 3. 차기 청구일 (정확히 6개월 뒤)
                const nextDate = new Date(baseDate);
                nextDate.setMonth(nextDate.getMonth() + 6);
                if (nextBillingInput) {
                    nextBillingInput.value = nextDate.toISOString().split('T')[0];
                }

                // 4. Note 최종값 설정
                noteInput.value = `6개월 사용료 (${startDateStr} ~ ${endDateStr})`;

            } else {
                const year = baseDate.getFullYear();
                const month = baseDate.getMonth() + 1;
                const day = baseDate.getDate();
                noteInput.value = `${year}년 ${month}월 ${day}일`;
                if (nextBillingInput) nextBillingInput.value = '';
            }
        }

        // [납부 여부 토글 함수 (추가 폼)]
        // '납부 완료' 체크박스를 누르면 납부일자 입력 필드를 활성화하고 오늘 날짜를 기본값으로 채워줍니다.
        function togglePayDate() {
            const isPaid = document.getElementById('paid_check').checked;
            const payDateInput = document.getElementById('pay_date_input');

            payDateInput.disabled = !isPaid;

            if (isPaid) {
                const today = new Date().toISOString().split('T')[0];
                payDateInput.value = today;
            } else {
                payDateInput.value = '';
            }
        }

        // [납부 여부 토글 함수 (수정 모달 폼)]
        function toggleEditPayDate() {
            const isPaid = document.getElementById('edit_paid_check').checked;
            const payDateInput = document.getElementById('edit_pay_date');

            payDateInput.disabled = !isPaid;

            if (isPaid) {
                if (!payDateInput.value) {
                    const today = new Date().toISOString().split('T')[0];
                    payDateInput.value = today;
                }
            } else {
                payDateInput.value = '';
            }
        }
    </script>

<?php endif; ?>