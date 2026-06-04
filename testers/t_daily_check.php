<?php

/**
 * KShops24 일간 배치(Cron) 테스트 도구 (t_daily_check.php)
 */

require_once __DIR__ . '/t_common.php';

$inactive_soon_days = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
$closed_soon_days = defined('SHOP_STATUS_CLOSED_SOON_DAYS') ? SHOP_STATUS_CLOSED_SOON_DAYS : 30;
$warning_closed_soon_days = defined('WARNING_SHOP_STATUS_CLOSED_SOON_DAYS') ? WARNING_SHOP_STATUS_CLOSED_SOON_DAYS : 14;
$warning_deleted_soon_days = defined('WARNING_SHOP_STATUS_DELETED_SOON_DAYS') ? WARNING_SHOP_STATUS_DELETED_SOON_DAYS : 30;
$deleted_soon_days = defined('SHOP_STATUS_DELETED_SOON_DAYS') ? SHOP_STATUS_DELETED_SOON_DAYS : 30;
$today = date('Y-m-d');

$msg = '';

// ---------------------------------------------------------
// [Action 1] 테스트 가상 상점 세팅
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'setup') {
    try {
        // 먼저 기존 테스트 데이터가 있다면 지우기
        $stmt = $pdo->query("SELECT id FROM shops WHERE shop_name LIKE '%테스트상점%' OR subdomain LIKE '%cron_test_%'");
        foreach ($stmt->fetchAll() as $row) {
            if (function_exists('deleteShopCompletely')) deleteShopCompletely($pdo, $row['id']);
        }

        $pass = password_hash('1234', PASSWORD_DEFAULT);

        // 1. [작업 1용] 휴점 임박 알림 (만료일: 오늘 + $inactive_soon_days)
        $exp_date_1 = date('Y-m-d', strtotime("+{$inactive_soon_days} days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, created_at) VALUES (?, ?, 'A테스터', '[A]휴점임박_테스트상점', 'cron_test_1', 'active', NOW())")
            ->execute(["test1@KShops24.local", $pass]);
        $id_1 = $pdo->lastInsertId();
        recordShopPayment($pdo, $id_1, 'monthly', 100, "휴점 임박 테스트", 'n', $today, $exp_date_1);

        // 2. [작업 2용] 휴점 처리 (만료일: 어제)
        $exp_date_2 = date('Y-m-d', strtotime("-1 days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, created_at) VALUES (?, ?, 'B테스터', '[B]휴점처리_테스트상점', 'cron_test_2', 'active', NOW())")
            ->execute(["test2@KShops24.local", $pass]);
        $id_2 = $pdo->lastInsertId();
        recordShopPayment($pdo, $id_2, 'monthly', 500, "휴점 처리 테스트", 'n', $today, $exp_date_2);

        // 3. [작업 3용] 정상영업 복귀 (상태 inactive, 미납 없음)
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, inactive_date, closed_date, deleted_date, created_at) VALUES (?, ?, 'C테스터', '[C]정상복귀_테스트상점', 'cron_test_3', 'inactive', ?, ?, ?, NOW())")
            ->execute(["test3@KShops24.local", $pass, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+25 days')), date('Y-m-d', strtotime('+85 days'))]);
        $id_3 = $pdo->lastInsertId();
        recordShopPayment($pdo, $id_3, 'monthly', 500, "완납 테스트", 'y', $today, $today);

        // 4. [작업 4용] 폐점 임박 알림 (상태 inactive, closed_date: 오늘 + 14)
        $close_date_4 = date('Y-m-d', strtotime("+{$warning_closed_soon_days} days"));
        $inactive_date_4 = date('Y-m-d', strtotime($close_date_4 . " -{$closed_soon_days} days"));
        $delete_date_4 = date('Y-m-d', strtotime($close_date_4 . " +".($warning_deleted_soon_days + $deleted_soon_days)." days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, inactive_date, closed_date, deleted_date, created_at) VALUES (?, ?, 'D테스터', '[D]폐점임박_테스트상점', 'cron_test_4', 'inactive', ?, ?, ?, NOW())")
            ->execute(["test4@KShops24.local", $pass, $inactive_date_4, $close_date_4, $delete_date_4]);
        $id_4 = $pdo->lastInsertId();
        recordShopPayment($pdo, $id_4, 'monthly', 500, "연체 중", 'n', $today, date('Y-m-d', strtotime('-15 days')));

        // 5. [작업 5용] 폐점 처리 (상태 inactive, closed_date: 오늘)
        $close_date_5 = $today;
        $inactive_date_5 = date('Y-m-d', strtotime($close_date_5 . " -{$closed_soon_days} days"));
        $delete_date_5 = date('Y-m-d', strtotime($close_date_5 . " +".($warning_deleted_soon_days + $deleted_soon_days)." days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, inactive_date, closed_date, deleted_date, created_at) VALUES (?, ?, 'E테스터', '[E]폐점처리_테스트상점', 'cron_test_5', 'inactive', ?, ?, ?, NOW())")
            ->execute(["test5@KShops24.local", $pass, $inactive_date_5, $close_date_5, $delete_date_5]);
        $id_5 = $pdo->lastInsertId();
        recordShopPayment($pdo, $id_5, 'monthly', 500, "연체 중 (폐점 대상)", 'n', $today, date('Y-m-d', strtotime('-30 days')));

        // 6. [작업 6용] 삭제 임박 알림 (상태 closed, deleted_date: 오늘 + 30)
        $delete_date_6 = date('Y-m-d', strtotime("+{$warning_deleted_soon_days} days"));
        $close_date_6 = date('Y-m-d', strtotime($delete_date_6 . " -".($warning_deleted_soon_days + $deleted_soon_days)." days"));
        $inactive_date_6 = date('Y-m-d', strtotime($close_date_6 . " -{$closed_soon_days} days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, inactive_date, closed_date, deleted_date, created_at) VALUES (?, ?, 'F테스터', '[F]삭제임박_테스트상점', 'cron_test_6', 'closed', ?, ?, ?, NOW())")
            ->execute(["test6@KShops24.local", $pass, $inactive_date_6, $close_date_6, $delete_date_6]);

        // 7. [작업 7용] 완전 삭제 (상태 closed, deleted_date: 오늘)
        $delete_date_7 = $today;
        $close_date_7 = date('Y-m-d', strtotime($delete_date_7 . " -".($warning_deleted_soon_days + $deleted_soon_days)." days"));
        $inactive_date_7 = date('Y-m-d', strtotime($close_date_7 . " -{$closed_soon_days} days"));
        $pdo->prepare("INSERT INTO shops (manager_email, manager_password, manager_name, shop_name, subdomain, status, inactive_date, closed_date, deleted_date, created_at) VALUES (?, ?, 'G테스터', '[G]완전삭제_테스트상점', 'cron_test_7', 'closed', ?, ?, ?, NOW())")
            ->execute(["test7@KShops24.local", $pass, $inactive_date_7, $close_date_7, $delete_date_7]);

        $msg = showAlert("7가지 라이프사이클 케이스의 가상 상점 세팅이 완료되었습니다. 이제 크론을 실행해보세요.", "success");
    } catch (Exception $e) {
        $msg = showAlert("테스트 데이터 생성 중 오류: " . $e->getMessage(), "danger");
    }
}

// ---------------------------------------------------------
// [Action 2] 테스트 가상 상점 일괄 삭제 (초기화)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    try {
        // 꼬리표(closed_)가 붙어 이름이 변경된 것까지 포함하여 검색
        $stmt = $pdo->query("SELECT id FROM shops WHERE shop_name LIKE '%테스트상점%' OR subdomain LIKE '%cron_test_%'");
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            if (function_exists('deleteShopCompletely')) {
                deleteShopCompletely($pdo, $row['id']);
                $count++;
            }
        }
        $msg = showAlert("테스트로 생성된 {$count}개의 가상 상점과 연관 데이터가 모두 삭제되었습니다.", "info");
    } catch (Exception $e) {
        $msg = showAlert("초기화 중 오류: " . $e->getMessage(), "danger");
    }
}

// 현재 테스트 상점들의 상태 조회
$stmt = $pdo->query("
    SELECT id, shop_name, subdomain, status, history_log, inactive_date, closed_date, deleted_date,
           (SELECT COUNT(*) FROM shop_payments WHERE shop_id = shops.id AND paid = 'n') as unpaid_cnt
    FROM shops 
    WHERE shop_name LIKE '%테스트상점%' OR subdomain LIKE '%cron_test_%' 
    ORDER BY shop_name ASC
");
$test_shops = $stmt->fetchAll();

// 자동 검증 모드(verify=1) 확인 및 G상점 삭제 성공 여부 사전 체크
$verify_mode = isset($_GET['verify']) && $_GET['verify'] == '1';
$g_found = false;
if (!empty($test_shops)) {
    foreach ($test_shops as $shop) {
        if (strpos($shop['shop_name'], '[G]') !== false) {
            $g_found = true;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>일간 배치(Cron) 시뮬레이터</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="bg-light pb-5">
    <div class="container mt-5">
        <div class="card shadow-sm mb-4 border-start border-5 border-info">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-info mb-2"><i class="bi bi-calendar-check me-2"></i>일간 배치(Cron) 로직
                    시뮬레이터</h4>
                <p class="card-text text-muted small mb-0">
                    <code>/admin/cron_daily_works.php</code> 파일이 매일 자정에 실행될 때 일어나는 라이프사이클 관리 작업을 모의 테스트합니다.<br>
                    <strong>1단계:</strong> '테스트 데이터 생성'을 눌러 7가지 케이스(휴점임박, 휴점처리, 정상복귀, 폐점임박, 폐점처리, 삭제임박, 완전삭제)의 상점을
                    만듭니다.<br>
                    <strong>2단계:</strong> '스크립트 강제 실행'을 눌러 배치 스크립트를 강제로 구동합니다.<br>
                    <strong>3단계:</strong> '결과 자동 검증'을 눌러 모든 상점의 데이터가 규칙에 맞게 처리되었는지 PASS/FAIL 여부를 확인합니다.
                </p>
            </div>
        </div>

        <?= $msg ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="?action=setup" class="btn btn-outline-primary rounded-pill px-3 fw-bold me-2 shadow-sm"><i
                        class="bi bi-magic me-1"></i> 1. 테스트 데이터 생성</a>
                <button class="btn btn-dark rounded-pill px-3 fw-bold shadow me-2" onclick="runCron()"><i
                        class="bi bi-play-fill me-1"></i> 2. 스크립트 강제 실행 (팝업)</button>
                <a href="?verify=1" class="btn btn-success rounded-pill px-3 fw-bold shadow"><i
                        class="bi bi-check-all me-1"></i> 3. 결과 자동 검증</a>
                <button class="btn btn-warning rounded-pill bt-4 px-3 fw-bold shadow me-2" onclick="runCronInodeOnly()"><i
class="bi bi-stars me-1"></i> [작업 8] 청소만 테스트(서버 Inode 및 DB 용량 최적화)</button>

            </div>
            <div>
                <a href="?action=clear" class="btn btn-outline-danger rounded-pill px-3 ms-2 shadow-sm"
                    onclick="return confirm('테스트 데이터를 모두 지우시겠습니까?');"><i class="bi bi-trash me-1"></i> 모두 삭제(초기화)</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-bold">
                <i class="bi bi-table text-primary me-2"></i>생성된 테스트 상점 현재 상태 모니터링
            </div>
            <div class="card-body p-0">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light small text-center">
                        <tr>
                            <th class="ps-3 py-3 text-start" style="width: 220px;">상점명 (서브도메인)</th>
                            <th>현재 상태 (status)</th>
                            <th>미납 청구</th>
                            <th>수신 쪽지</th>
                            <th>이메일 로그</th>
                            <th class="text-start">기대 결과 (검증 포인트)</th>
                        </tr>
                    </thead>
                    <tbody class="small text-center">
                        <?php if (empty($test_shops)): ?>
                        <tr>
                            <td colspan="6" class="py-5 text-muted">생성된 테스트 데이터가 없습니다. [테스트 데이터 생성] 버튼을 눌러주세요.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($test_shops as $shop): ?>
                        <?php
                                $history_arr = json_decode($shop['history_log'] ?: '[]', true) ?: [];
                                $msg_cnt = 0;
                                $email_cnt = 0;
                                foreach ($history_arr as $h) {
                                    $t = $h['type'] ?? '';
                                    if ($t === 'message') $msg_cnt++;
                                    if ($t === 'email') $email_cnt++;
                                }

                                $status_badge = 'bg-secondary';
                                if ($shop['status'] === 'active') $status_badge = 'bg-success';
                                if ($shop['status'] === 'inactive') $status_badge = 'bg-warning text-dark';
                                if ($shop['status'] === 'closed') $status_badge = 'bg-danger';

                                // 기대 결과 텍스트 분기
                                $expected = '';
                                $is_pass = false;
                                if (strpos($shop['shop_name'], '[A]') !== false) {
                                    $expected = "상태유지(<b>active</b>) + 수신쪽지 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(휴점 임박 알림)</span>";
                                    $is_pass = ($shop['status'] === 'active' && $msg_cnt > 0);
                                } elseif (strpos($shop['shop_name'], '[B]') !== false) {
                                    $expected = "상태변경(<b>inactive</b>) + 수신쪽지 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(휴점 처리 및 알림)</span>";
                                    $is_pass = ($shop['status'] === 'inactive' && $msg_cnt > 0);
                                } elseif (strpos($shop['shop_name'], '[C]') !== false) {
                                    $expected = "상태변경(<b>active</b>) + 수신쪽지 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(정상 영업 복귀)</span>";
                                    $is_pass = ($shop['status'] === 'active' && $msg_cnt > 0 && empty($shop['inactive_date']));
                                } elseif (strpos($shop['shop_name'], '[D]') !== false) {
                                    $expected = "상태유지(<b>inactive</b>) + 수신쪽지 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(폐점 임박 알림)</span>";
                                    $is_pass = ($shop['status'] === 'inactive' && $msg_cnt > 0);
                                } elseif (strpos($shop['shop_name'], '[E]') !== false) {
                                    $expected = "상태변경(<b>closed</b>) + 수신쪽지 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(폐점 처리)</span>";
                                    $is_pass = ($shop['status'] === 'closed' && $msg_cnt > 0);
                                } elseif (strpos($shop['shop_name'], '[F]') !== false) {
                                    $expected = "상태유지(<b>closed</b>) + 이메일로그 <b>1건 이상</b><br><span class='text-muted' style='font-size:0.7rem;'>(삭제 임박 알림)</span>";
                                    $is_pass = ($shop['status'] === 'closed' && $email_cnt > 0);
                                } elseif (strpos($shop['shop_name'], '[G]') !== false) {
                                    $expected = "목록에서 사라짐(<b>완전삭제</b>)<br><span class='text-muted' style='font-size:0.7rem;'>(배치 후 이 행이 안보여야 정상)</span>";
                                    $is_pass = false; // G가 목록에 남아있으면 무조건 FAIL
                                }

                                if ($verify_mode) {
                                    $expected .= "<div class='mt-1'>" . ($is_pass ? "<span class='badge bg-success px-2 py-1'><i class='bi bi-check-circle me-1'></i>PASS</span>" : "<span class='badge bg-danger px-2 py-1'><i class='bi bi-x-circle me-1'></i>FAIL</span>") . "</div>";
                                }
                                ?>
                        <tr>
                            <td class="ps-3 text-start">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($shop['shop_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($shop['subdomain']) ?></div>
                            </td>
                            <td>
                                <span
                                    class="badge <?= $status_badge ?> fs-6 d-block mb-1"><?= strtoupper($shop['status']) ?></span>
                                <?php if ($shop['inactive_date']): ?><div class="text-muted"
                                    style="font-size: 0.65rem;">휴점: <?= substr($shop['inactive_date'], 0, 10) ?></div>
                                <?php endif; ?>
                                <?php if ($shop['closed_date']): ?><div class="text-muted" style="font-size: 0.65rem;">
                                    폐점: <?= substr($shop['closed_date'], 0, 10) ?></div><?php endif; ?>
                                <?php if ($shop['deleted_date']): ?><div class="text-muted" style="font-size: 0.65rem;">
                                    삭제: <?= substr($shop['deleted_date'], 0, 10) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?= $shop['unpaid_cnt'] > 0 ? "<span class='badge bg-danger rounded-pill'>{$shop['unpaid_cnt']}건</span>" : "-" ?>
                            </td>
                            <td>
                                <?= $msg_cnt > 0 ? "<span class='badge bg-primary rounded-pill'>{$msg_cnt}건</span>" : "-" ?>
                            </td>
                            <td>
                                <?= $email_cnt > 0 ? "<span class='badge bg-info text-dark rounded-pill'>{$email_cnt}건</span>" : "-" ?>
                            </td>
                            <td class="text-start bg-light">
                                <?= $expected ?>
                            </td>
                        </tr>
                        <?php if (!empty($shop['history_log'])): ?>
                        <tr>
                            <td colspan="6" class="text-start bg-light text-muted border-bottom"
                                style="font-size: 0.7rem;">
                                <i class="bi bi-clock-history ms-3 me-1"></i>히스토리 로그:
                                <?= htmlspecialchars($shop['history_log']) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($verify_mode && !$g_found && !empty($test_shops)): ?>
                        <tr class="table-success border-success">
                            <td class="ps-3 text-start">
                                <div class="fw-bold text-dark">[G]완전삭제_테스트상점</div>
                                <div class="text-muted" style="font-size: 0.75rem;">cron_test_7</div>
                            </td>
                            <td colspan="4" class="text-center text-success fw-bold"><i
                                    class="bi bi-trash3-fill me-1"></i>DB 및 시스템에서 완벽하게 삭제(증발)되었습니다.</td>
                            <td class="text-start bg-light">
                                목록에서 사라짐(<b>완전삭제</b>)<br><span class='text-muted' style='font-size:0.7rem;'>(배치 후 이 행이
                                    안보여야 정상)</span>
                                <div class='mt-1'><span class='badge bg-success px-2 py-1'><i
                                            class='bi bi-check-circle me-1'></i>PASS</span></div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function runCron() {
        // 새 창의 가로세로 크기 및 위치 지정 (화면 중앙 근처)
        const width = 800;
        const height = 600;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        // cron 파일 강제 실행 호출
        const cronWindow = window.open('../admin/cron_daily_works.php?force_run=1', 'cronTestWindow',
            `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`);

        if (window.focus) {
            cronWindow.focus();
        }
    }

    function runCronInodeOnly() {
        // 새 창의 가로세로 크기 및 위치 지정 (화면 중앙 근처)
        const width = 800;
        const height = 600;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        // only_inode=1 파라미터를 추가하여 호출
        const cronWindow = window.open('../admin/cron_daily_works.php?force_run=1&only_inode=1', 'cronTestWindow',
            `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`);

        if (window.focus) {
            cronWindow.focus();
        }
    }
    </script>
</body>

</html>