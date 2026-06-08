<?php

/**
 * [하위 파일] KShops24 상점 통합 관리 (admin/manage_shops.php)
 * [업데이트] 상단 탭에 상태별 상점 숫자(Count) 표시 로직 추가 + 검색 기능 통합
 * 
 * 휴점처리 프로세스
 * 1. shop table에 입점 정보 수정 (status : inactive, history_log : 휴점 관련 정보) 
 * 2. shop_board table에 상점주에게 보내는 휴점 통보 쪽지 (type:message) 저장
 * 3. 상점주에게 휴점 이메일(inactive_email_template) 보내고, shop_board table에 이메일 보낸 내역 기록(type:email_log)하기
 *
 * 
 */

$message = ""; // Initialize message variable for showAlert
$search = trim($_GET['search'] ?? ''); // 검색어 추가

// 1. 데이터 처리 로직 (Action)
// -----------------------------------------------------------------------
// URL 파라미터(GET) 또는 폼 데이터(POST)를 통해 전달된 액션(action)과 상점 ID(id)를 수신합니다.
// 이 로직은 HTML 출력 전에 실행되어야 합니다.

// POST request handling for status changes (from modal forms or direct POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $target_id = (int) ($_POST['id'] ?? 0);
    $post_view = $_POST['view'] ?? SHOP_STATUS_ACTIVE; // Get view from POST for redirect consistency
    $post_search = trim($_GET['search'] ?? ''); // Search is always from GET for the main page

    if ($target_id > 0) {
        $new_status = '';
        $message_key = '';
        $redirect_view = $post_view; // Default redirect to the same view

        try {
            switch ($action) {
                case 'to_apply':
                    $new_status = SHOP_STATUS_APPLYING;
                    $message_key = 'shop_applied';
                    $redirect_view = SHOP_STATUS_APPLYING;
                    break;
                case 'to_testing':
                    $new_status = SHOP_STATUS_TESTING;
                    $message_key = 'shop_testing';
                    $redirect_view = SHOP_STATUS_TESTING;
                    break;
                case 'to_active':
                    $new_status = SHOP_STATUS_ACTIVE;
                    $message_key = 'shop_activated';
                    $redirect_view = SHOP_STATUS_ACTIVE;
                    break;
                case 'to_inactive_soon':
                    $new_status = SHOP_STATUS_INACTIVE_SOON;
                    $message_key = 'to_inactive_soon';
                    $redirect_view = SHOP_STATUS_INACTIVE_SOON;
                    break;
                case 'to_suspend':
                    $new_status = SHOP_STATUS_INACTIVE;
                    $message_key = 'shop_suspended';
                    $redirect_view = SHOP_STATUS_INACTIVE;

                    $suspend_date = $_POST['suspend_date'] ?? date('Y-m-d');
                    $suspend_reason = $_POST['suspend_reason'] ?? '';

                    $pdo->beginTransaction();
                    try {
                        suspendShop($pdo, $target_id, $suspend_reason, $suspend_date);

                        $target_shop = $pdo->query("SELECT manager_email, manager_name, shop_name, subdomain FROM shops WHERE id = {$target_id}")->fetch();

                        $message_title = "[안내] 상점이 휴점 처리되었습니다.";
                        $message_content = "상점주님, 안녕하세요.\n\n해당 상점이 다음과 같은 사유로 휴점 처리되었습니다.\n\n- 처리일: {$suspend_date}\n- 사유: {$suspend_reason}\n\n문의사항은 본사 고객센터로 연락 바랍니다.";
                        $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content, is_secret, created_at) VALUES (?, 'message', 'admin', ?, ?, 1, NOW())")
                            ->execute([$target_id, $message_title, $message_content]);

                        $pdo->commit();

                        $email_result = false;
                        if (function_exists('sendShopEmail')) {
                            try {
                                $email_result = sendShopEmail($pdo, $target_shop['manager_email'], SHOP_STATUS_INACTIVE, [
                                    'manager_name' => $target_shop['manager_name'],
                                    'shop_name' => $target_shop['shop_name'],
                                    'subdomain' => $target_shop['subdomain'],
                                    'suspend_date' => $suspend_date,
                                    'suspend_reason' => $suspend_reason,
                                    'shops:shop_name' => $target_shop['shop_name'],
                                    'shops:subdomain' => $target_shop['subdomain'],
                                    'shops:manager_email' => $target_shop['manager_email']
                                ]);
                            } catch (Exception $emailEx) {
                                error_log("휴점 통보 이메일 발송 실패 shop_id: {$target_id}. Error: " . $emailEx->getMessage());
                            }
                        }

                        $email_subject = ($email_result === true) ? "[KShops24] 휴점 안내 메일 발송 완료" : "[발송 실패] 휴점 안내 메일";
                        $email_content_log = ($email_result === true) ? "휴점 안내 이메일 발송 완료\n수신자: " . $target_shop['manager_email'] : "휴점 안내 이메일 발송 실패\n수신자: " . $target_shop['manager_email'] . "\n사유: " . (is_string($email_result) ? $email_result : "알 수 없는 오류");
                        addShopHistoryLog($pdo, $target_id, SHOP_HISTORY_EMAIL, $email_subject, $email_content_log);
                    } catch (Exception $e) {
                        if ($pdo->inTransaction())
                            $pdo->rollBack();
                        throw $e;
                    }
                    break;
                case 'to_closed':
                    $new_status = SHOP_STATUS_CLOSED;
                    $message_key = 'shop_closed';
                    $redirect_view = SHOP_STATUS_CLOSED;
                    if (function_exists('closeShopWithRename')) {
                        closeShopWithRename($pdo, $target_id);
                    } else {
                        $pdo->prepare("UPDATE shops SET status=? WHERE id=?")->execute([$new_status, $target_id]);
                    }
                    break;
            }

            // Perform generic status update if not already done by specific action (e.g., to_suspend, to_closed)
            if ($new_status !== '' && !in_array($action, ['to_suspend', 'to_closed'])) {
                $pdo->prepare("UPDATE shops SET status=? WHERE id=?")->execute([$new_status, $target_id]);
                addShopHistoryLog($pdo, $target_id, SHOP_HISTORY_STATUS, "상태 변경", "상점 상태가 '{$new_status}'(으)로 변경되었습니다.");
            }

            // [수정] header() 에러 방지를 위해 JavaScript 리다이렉트 사용
            echo "<script>location.href='admin_view.php?page=manage_shops&view={$redirect_view}&search=" . urlencode($post_search) . "&msg={$message_key}';</script>";
            exit;
        } catch (Exception $e) {
            $message = showAlert("오류 발생: " . $e->getMessage(), "danger");
        }
    }
}

// GET request handling for status changes (from dropdown links) or delete actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $target_id = (int) $_GET['id'];
    $get_view = $_GET['view'] ?? SHOP_STATUS_ACTIVE;
    $get_search = trim($_GET['search'] ?? '');

    if ($target_id > 0) {
        $new_status = '';
        $message_key = '';
        $redirect_view = $get_view;

        try {
            // [수정] 누락되었던 GET 요청에 의한 실제 상태 변경 및 삭제 로직 추가
            switch ($action) {
                case 'to_testing_get':
                    $new_status = SHOP_STATUS_TESTING;
                    $message_key = 'shop_testing';
                    $redirect_view = SHOP_STATUS_TESTING;
                    break;
                case 'to_active_get':
                    $new_status = SHOP_STATUS_ACTIVE;
                    $message_key = 'shop_activated';
                    $redirect_view = SHOP_STATUS_ACTIVE;
                    break;
                case 'to_closed_get':
                    $new_status = SHOP_STATUS_CLOSED;
                    $message_key = 'shop_closed';
                    $redirect_view = SHOP_STATUS_CLOSED;
                    break;
                case 'delete_shop':
                    if (function_exists('deleteShopCompletely')) {
                        deleteShopCompletely($pdo, $target_id);
                    } else {
                        $pdo->prepare("DELETE FROM shops WHERE id=?")->execute([$target_id]);
                    }
                    $message_key = 'shop_deleted';
                    break;
            }

            if ($new_status !== '') {
                $pdo->prepare("UPDATE shops SET status=? WHERE id=?")->execute([$new_status, $target_id]);
                addShopHistoryLog($pdo, $target_id, SHOP_HISTORY_STATUS, "상태 변경", "상점 상태가 '{$new_status}'(으)로 변경되었습니다.");
            }

            // [수정] header() 에러 방지를 위해 JavaScript 리다이렉트 사용
            echo "<script>location.href='admin_view.php?page=manage_shops&view={$redirect_view}&search=" . urlencode($get_search) . "&msg={$message_key}';</script>";
            exit;
        } catch (Exception $e) {
            $message = showAlert("오류 발생: " . $e->getMessage(), "danger");
        }
    }
}

// 2. 숫자(Count) 집계 로직 (관리자 화면 상단의 탭 네비게이션에 표시될 상태별 상점의 총 개수를 계산합니다.)
// [최적화] 공용 함수를 사용하여 깔끔하게 상태별 카운트를 가져옵니다.
$counts = getShopStatusCounts($pdo);

// 3. 현재 탭 리스트 로딩 (검색 조건 포함)
// -----------------------------------------------------------------------
// 사용자가 선택한 탭(상태값)에 해당하는 상점 목록만 필터링하여 가져옵니다. 기본값은 '대기중(ACTIVE)'입니다.
$view = $_GET['view'] ?? SHOP_STATUS_ACTIVE;

// 상태별 상점 조회 + 만료일 서브쿼리 (가장 먼저 만료되는 날짜를 정확히 찾기 위해 COALESCE 적용)
$sql = "SELECT s.*, p.max_expiring_date,
               (SELECT GROUP_CONCAT(DISTINCT pay_type SEPARATOR ',') FROM shop_payments WHERE shop_id = s.id AND paid = 'n') as unpaid_types
        FROM shops s
        LEFT JOIN (
            " . SQL_EXPIRING_SUBQUERY . "
        ) p ON s.id = p.shop_id 
        WHERE s.status = ?";
$params = [$view];

if ($search !== '') {
    // 검색어가 있을 경우 상점 ID, 상점명 또는 서브도메인에 검색어가 포함(LIKE)된 결과만 필터링합니다.
    $sql .= " AND (s.id = ? OR s.shop_name LIKE ? OR s.subdomain LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 최신 가입한 상점이 위로 올라오도록 ID 기준 내림차순(DESC) 정렬을 적용합니다.
$sql .= " ORDER BY id DESC";
$shops = $pdo->prepare($sql);
$shops->execute($params);
$shop_list = $shops->fetchAll();

// [수정] 휴점(SHOP_STATUS_INACTIVE) 탭일 경우, DB에 저장된 폐점 예정일을 기준으로 빠른 순 정렬
if ($view === SHOP_STATUS_INACTIVE) {
    usort($shop_list, function ($a, $b) {
        $dateA = !empty($a['closed_date']) ? substr($a['closed_date'], 0, 10) : '9999-12-31';
        $dateB = !empty($b['closed_date']) ? substr($b['closed_date'], 0, 10) : '9999-12-31';
        return strcmp($dateA, $dateB);
    });
} elseif ($view === SHOP_STATUS_CLOSED) {
    // [수정] 폐점(SHOP_STATUS_CLOSED) 탭일 경우, 데이터 삭제 예정일을 기준으로 빠른 순 정렬
    usort($shop_list, function ($a, $b) {
        $dateA = !empty($a['deleted_date']) ? substr($a['deleted_date'], 0, 10) : '9999-12-31';
        $dateB = !empty($b['deleted_date']) ? substr($b['deleted_date'], 0, 10) : '9999-12-31';
        return strcmp($dateA, $dateB);
    });
}
?>
<?php
// Handle messages after redirect (GET request with 'msg' parameter)
if (isset($_GET['msg'])) {
    $msg_type = 'info'; // Default type
    $msg_text = '';
    switch ($_GET['msg']) {
        case 'shop_applied':
            $msg_text = "상점이 '대기중' 상태로 변경되었습니다.";
            $msg_type = 'success';
            break;
        case 'shop_testing':
            $msg_text = "상점이 '테스트중' 상태로 변경되었습니다.";
            $msg_type = 'success';
            break;
        case 'shop_activated':
            $msg_text = "상점이 '운영중' 상태로 변경되었습니다.";
            $msg_type = 'success';
            break;
        case 'shop_suspended':
            $msg_text = "상점이 '휴점' 처리되었습니다.";
            $msg_type = 'warning';
            break;
        case 'shop_closed':
            $msg_text = "상점이 '폐점' 처리되었습니다.";
            $msg_type = 'danger';
            break;
        case 'shop_deleted':
            $msg_text = "상점 데이터가 영구 삭제되었습니다.";
            $msg_type = 'danger';
            break;
    }
    if ($msg_text) {
        // showAlert function is assumed to be available from common_header.php or manage_shop.php
        $message = showAlert($msg_text, $msg_type);
    }
}
?>

<style>
/* 숫자 뱃지 스타일 */
.count-badge {
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
    font-weight: 700;
    display: inline-block;
}

.nav-link.active .count-badge {
    background: #eef2ff;
    color: #4e73df;
}

/* 검색바 스타일 */
.search-box {
    margin-bottom: 0;
}

.search-box .form-control {
    border-radius: 20px;
    padding-left: 15px;
    font-size: 0.85rem;
    width: 220px;
    border-color: #e2e8f0;
}

.search-box .btn {
    border-radius: 20px;
    font-size: 0.85rem;
}
</style>

<div class="shop-management-wrap">

    <div class="d-flex flex-wrap justify-content-between align-items-end border-bottom mb-3 pb-0 gap-2">
        <!-- 상단 상태별 탭 네비게이션 (Bootstrap Tabs 적용) -->
        <ul class="nav nav-tabs border-bottom-0 mb-0">
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_ACTIVE ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_ACTIVE ?>">
                    운영중 <span class="count-badge"><?= $counts[SHOP_STATUS_ACTIVE] ?></span>
                </a>
            </li>
            <!-- 만료 임박 탭 추가 -->
            <li class="nav-item">
                <a class="nav-link text-secondary" href="admin_view.php?page=manage_expiring_shops">
                    만료 임박 (<?= SHOP_STATUS_INACTIVE_SOON_DAYS ?>일 이내)<span
                        class="count-badge text-danger"><?= $counts['expiring'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_INACTIVE ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_INACTIVE ?>">
                    휴점 <span class="count-badge"><?= $counts[SHOP_STATUS_INACTIVE] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_CLOSED ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_CLOSED ?>">
                    폐점 <span class="count-badge"><?= $counts[SHOP_STATUS_CLOSED] ?></span>
                </a>
            </li>

            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_OWNER_INACTIVE ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_OWNER_INACTIVE ?>">
                    상점주 휴점 <span class="count-badge"><?= $counts[SHOP_STATUS_OWNER_INACTIVE] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_OWNER_DELETED ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_OWNER_DELETED ?>">
                    상점주 폐점 <span class="count-badge"><?= $counts[SHOP_STATUS_OWNER_DELETED] ?></span>
                </a>
            </li>            

            <!-- 사용하지 않음
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_APPLYING ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_APPLYING ?>">
                    대기중 <span class="count-badge"><?= $counts[SHOP_STATUS_APPLYING] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view == SHOP_STATUS_TESTING ? 'active fw-bold text-primary' : 'text-secondary' ?>"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_TESTING ?>">
                    테스트중 <span class="count-badge"><?= $counts[SHOP_STATUS_TESTING] ?></span>
                </a>
            </li>
            -->

            <!-- [신규] 텔레그램 연동 상태 탭 추가 -->
            <li class="nav-item">
                <a class="nav-link text-secondary" href="admin_view.php?page=manage_telegram">
                    <i class="bi bi-telegram"></i> 텔레그램 상태
                </a>
            </li>
        </ul>

    </div>

    <div class="search-box mb-2">
        <!-- 검색 폼: 검색을 하더라도 현재 선택된 탭(view)을 유지하도록 hidden 필드로 전달합니다. -->
        <form action="admin_view.php" method="GET" class="d-flex gap-1">
            <input type="hidden" name="page" value="manage_shops">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="text" name="search" class="form-control" placeholder="상점명 또는 아이디 검색"
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
            <a href="admin_view.php?page=manage_shops&view=<?= $view ?>" class="btn btn-outline-secondary"><i
                    class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-ps24 table-hover align-middle">
            <thead>
                <tr class="small">
                    <th style="width: 70px;">ID</th>
                    <th style="width: 250px;">상점명(상점아이디)/도메인</th>
                    <th class="t-center">카테고리</th>
                    <th>점주/연락처</th>
                    <th class="t-center">방문</th>
                    <th class="t-center">미납 항목</th>
                    <?php if ($view === SHOP_STATUS_INACTIVE || $view === SHOP_STATUS_CLOSED): ?>
                    <th class="t-center">휴점일<br><span class="text-muted fw-normal" style="font-size:0.85em">등록일</span>
                    </th>
                    <th class="t-center">폐점일<br><span class="text-muted fw-normal" style="font-size:0.85em">삭제일</span>
                    </th>
                    <?php else: ?>
                    <th class="t-center">등록일</th>
                    <th class="t-center">만료일</th>
                    <?php endif; ?>
                    <th class="t-center px-4">매니지먼트</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shop_list)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <?= ($search !== '') ? "'" . htmlspecialchars($search) . "'에 대한 검색 결과가 없습니다." : "해당 상태의 상점이 없습니다." ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($shop_list as $s): ?>
                <!-- 반복문을 통해 개별 상점 정보를 테이블 행(row)으로 출력합니다. -->
                <!-- XSS(크로스 사이트 스크립팅) 공격 방지를 위해 사용자 입력 데이터는 반드시 htmlspecialchars()로 감싸서 출력합니다. -->
                <tr>
                    <td class="t-center text-muted fw-bold">
                        #<?= $s['id'] ?>
                        <?php if (($s['is_sample_shop'] ?? 'n') === 'y'): ?>
                            <div class="mt-1"><span class="badge bg-danger shadow-sm" style="font-size:0.65rem;">샘플</span></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="fw-bold text-dark mb-1">
                            <a href="admin_view.php?page=manage_shop&id=<?= $s['id'] ?>"
                                class="text-dark text-decoration-none" /* target을 고유하게 지정하여 이미 열려있으면 해당 창을 재사용합니다 */
                                target="shop_manage_<?= $s['id'] ?>" rel="noopener noreferrer">
                                <?= htmlspecialchars($s['shop_name']) ?> <i
                                    class="bi bi-arrow-right-short text-primary"></i>
                            </a>
                        </div>
                        <div class="small text-primary">
                            <i class="bi bi-globe2 me-1"></i>kshops24.com/<?= $s['subdomain'] ?>
                        </div>
                    </td>

                    <td class="t-center">
                        <span class="badge bg-light text-dark border fw-normal">
                            <?= htmlspecialchars($s['category'] ?? '미지정') ?>
                        </span>
                    </td>

                    <td>
                        <div class="small fw-bold"><?= htmlspecialchars($s['manager_name'] ?? '정보없음') ?> (<?= htmlspecialchars($s['manager_email'] ?? '정보없음') ?>)</div>
                        <div class="small text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['phone_mobile'] ?? '-') ?>
                        </div>
                    </td>

                    <td class="t-center">
                        <a href="https://kshops24.com/<?= $s['subdomain'] ?>" target="_blank"
                            class="btn btn-sm btn-outline-secondary py-0" style="font-size: 0.7rem;">
                            <i class="bi bi-box-arrow-up-right me-1"></i>방문
                        </a>
                    </td>

                    <!-- 미납 항목 -->
                    <td class="small t-center" style="max-width: 180px;">
                        <?php
                            if (!empty($s['unpaid_types'])) {
                                // 문자열 데이터를 배열로 분리하여 루프 실행
                                $types = explode(',', $s['unpaid_types']);
                                foreach ($types as $t) {
                                    // 공통 상수에 정의된 한글 라벨(pay_type_labels) 사용
                                    $label = $pay_type_labels[$t] ?? $t;
                                    echo "<span class='badge bg-white border border-danger text-danger rounded-pill me-1 mb-1 fw-bold px-2 shadow-sm' style='font-size:0.7rem;'>" . htmlspecialchars($label) . "</span>";
                                }
                            } else {
                                echo '<span class="text-muted">-</span>'; // 미납 내역이 없는 경우
                            }
                            ?>
                    </td>

                    <?php if ($view === SHOP_STATUS_INACTIVE || $view === SHOP_STATUS_CLOSED): ?>
                    <td class="t-center small">
                        <div class="fw-bold text-warning text-dark">
                            <?= !empty($s['inactive_date']) ? substr($s['inactive_date'], 0, 10) : '-' ?></div>
                        <div class="text-muted" style="font-size: 0.85em;">등록:
                            <?= date('Y-m-d', strtotime($s['created_at'])) ?></div>
                    </td>
                    <td class="t-center small">
                        <?php
                                $today = date('Y-m-d');
                                $is_closed_overdue = ($view === SHOP_STATUS_INACTIVE && !empty($s['closed_date']) && substr($s['closed_date'], 0, 10) < $today);
                                $is_deleted_overdue = ($view === SHOP_STATUS_CLOSED && !empty($s['deleted_date']) && substr($s['deleted_date'], 0, 10) < $today);
                                ?>
                        <div class="text-danger fw-bold">
                            <?= !empty($s['closed_date']) ? substr($s['closed_date'], 0, 10) : '-' ?>
                            <?php if ($is_closed_overdue): ?>
                            <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">초과</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.85em;">삭제:
                            <?= !empty($s['deleted_date']) ? substr($s['deleted_date'], 0, 10) : '-' ?>
                            <?php if ($is_deleted_overdue): ?>
                            <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">초과</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php else: ?>
                    <td class="t-center small text-muted">
                        <?= date('Y-m-d', strtotime($s['created_at'])) ?>
                    </td>
                    <td class="t-center small text-muted">
                        <?php
                                // 서브쿼리에서 계산된 만료일을 사용 (미납건이 있으면 미납 만료일, 없으면 정기구독 만료일)
                                $actual_exp_date = $s['max_expiring_date'];

                                if (!empty($actual_exp_date) && $actual_exp_date !== '0000-00-00') {
                                    $exp_date = new DateTime($actual_exp_date);
                                    $is_expiring = $exp_date <= (new DateTime())->modify('+' . SHOP_STATUS_INACTIVE_SOON_DAYS . ' days');
                                    $class = $is_expiring ? 'text-danger fw-bold' : '';
                                    echo "<span class='{$class}'>" . $exp_date->format('Y-m-d') . "</span>";
                                    if ($exp_date < new DateTime(date('Y-m-d')))
                                        echo ' <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">연체</span>';
                                } else {
                                    echo '<span class="text-danger">납입 기록 없음</span>';
                                }
                                ?>
                    </td>
                    <?php endif; ?>

                    <td class="t-center px-4">
                        <!-- 매니지먼트 액션 버튼 그룹 -->
                        <!-- 현재 상점의 상태($view)에 따라 워크플로우상 다음에 수행할 수 있는 논리적인 액션 버튼만 동적으로 노출시켜 관리자의 실수를 방지합니다. -->
                        <div class="btn-group shadow-sm">
                            <?php if ($view === SHOP_STATUS_APPLYING): // 대기중 -> 테스트중 
                                            ?>
                            <a href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_APPLYING ?>&action=to_testing_get&id=<?= $s['id'] ?>"
                                class="btn btn-sm btn-primary fw-bold px-3">구축 시작</a>
                            <?php elseif ($view === SHOP_STATUS_TESTING): // 테스트중 -> 운영중 
                                            ?>
                            <a href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_TESTING ?>&action=to_active_get&id=<?= $s['id'] ?>"
                                class="btn btn-sm btn-success text-white fw-bold px-3">서비스 오픈</a>
                            <?php elseif ($view === SHOP_STATUS_ACTIVE): // 운영중 -> 휴점처리 (모달) 
                                            ?>
                            <button type="button" onclick="openSuspendModal(<?= $s['id'] ?>)"
                                class="btn btn-sm btn-outline-warning fw-bold px-3">휴점처리</button>
                            <?php elseif ($view === SHOP_STATUS_INACTIVE): // 휴점 -> 재오픈 
                                            ?>
                            <a href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_INACTIVE ?>&action=to_active_get&id=<?= $s['id'] ?>"
                                class="btn btn-sm btn-outline-info fw-bold">재오픈</a>
                            <?php elseif ($view === SHOP_STATUS_CLOSED): // 폐점 -> 재구축 
                                            ?>
                            <a href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_CLOSED ?>&action=to_active_get&id=<?= $s['id'] ?>"
                                class="btn btn-sm btn-outline-secondary fw-bold">재오픈</a>
                            <?php endif; ?>

                            <button type="button"
                                class="btn btn-sm btn-light border-start dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">

                                <?php if ($view === SHOP_STATUS_INACTIVE): ?>
                                <li>
                                    <a class="dropdown-item small"
                                        href="admin_view.php?page=manage_shops&view=<?= $view ?>&action=to_closed_get&id=<?= $s['id'] ?>"
                                        onclick="return confirm('이 상점을 폐점 처리할까요?')">
                                        <i class="bi bi-slash-circle me-2"></i>폐점 처리
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php //if ($view === SHOP_STATUS_CLOSED): 
                                        ?>
                                <li>
                                    <!-- This was the problematic 'delete' link -->
                                    <a class="dropdown-item small text-danger"
                                        href="admin_view.php?page=manage_shops&view=<?= $view ?>&action=delete_shop&id=<?= $s['id'] ?>&search=<?= urlencode($search) ?>"
                                        onclick="return confirm('데이터가 영구 삭제됩니다. 계속할까요?')">
                                        <i class="bi bi-trash3 me-2"></i>삭제
                                    </a>
                                </li>
                                <?php //endif; 
                                        ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>



            </tbody>
        </table>
    </div>
</div>

<!-- 휴점 처리 사유 입력 모달 -->
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <!-- 폼과 모달 컨텐츠 컨테이너를 안전하게 분리 -->
        <div class="modal-content border-0 shadow-lg text-start">
            <form method="POST" action="admin_view.php?page=manage_shops&search=<?= urlencode($search) ?>">
                <!-- POST 액션, 상점 ID, 뷰(View) 값을 폼으로 전송 -->
                <input type="hidden" name="action" value="to_suspend">
                <input type="hidden" name="id" id="suspend_shop_id">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

                <div class="modal-header bg-warning text-dark border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pause-circle me-2"></i>휴점 처리</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">휴점 처리일</label>
                        <input type="date" name="suspend_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small">휴점 사유</label>
                        <textarea name="suspend_reason" class="form-control" rows="3"
                            placeholder="예: 개인 사정, 매장 리모델링, 기한 만료 등" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">휴점처리</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// JavaScript: 모달 열기 함수
?>
<script>
function openSuspendModal(shopId) {
    document.getElementById('suspend_shop_id').value = shopId;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal')).show();
}
</script>