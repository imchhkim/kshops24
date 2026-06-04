<?php

/**
 * 파일명: /admin/cron_daily_works.php
 * 역할: 매일 정해진 시간에 실행되는 일간 배치 작업 통합 스크립트.
 *       만료 임박 알림, 미납 연체자 자동 휴점 처리, 장기 휴점자 자동 폐점 처리 등
 * 실행 방법: 서버 Crontab에 매일 1회 지정된 시간에 실행되도록 등록
 *            (예: 0 1 * * * php /path/to/cron_daily_works.php)
 * 
 * 1. 오늘 시점에서, /config.php의 SHOP_STATUS_INACTIVE_SOON_DAYS 후의 날짜를 계산해서, 이 날짜에 결제 마감일인 모든 유형의 미납 청구건에 대한 알림 메시지(SHOP_STATUS_INACTIVE_SOON)를 해당 상점에 보낸다.
 * 2. 오늘 시점에서, 어제가 모든 유형의 미납 청구건들 중에 결제 마감일이었던 경우, 그 상점에 대하여 휴점 처리(SHOP_STATUS_INACTIVE)하고, 이에 대한 알림 메시지(SHOP_STATUS_CLOSED_SOON)를 해당 상점에 보낸다.
 * 3. 정상영업(active) 처리 : 오늘 날짜로 모든 미납된 청구서가 납입되면, 정상영업(active) 처리한다. 휴점일, 폐점일, 삭제일 모두 null 입력. "정상 영업 알림" 메시지를 보낸다.
 * 4. 휴점 후 폐점 임박 알림 : 휴점 상태이고 폐점일이 WARNING_SHOP_STATUS_CLOSED_SOON_DAYS 일 남은 경우 알림.
 * 5. 휴첨 후 폐점 처리 : 폐점일이 오늘인 휴점 상점을 폐점(closed) 처리.
 * 6. 폐점 후 삭제 알림 : 삭제일이 WARNING_SHOP_STATUS_DELETED_SOON_DAYS 일 남은 폐점 상점에 알림.
 * 7. 폐점 후 삭제 : 삭제일이 오늘인 폐점 상점을 시스템에서 완전 삭제.
 * 8. 고아 파일(Orphaned Files) 및 만료된 DB 세션 자동 청소 (Inode 최적화)
 */

// 수동 강제 실행이 필요할 경우 GET 파라미터로 ?force_run=1 을 붙여 실행할 수 있습니다.
if (php_sapi_name() !== 'cli' && !isset($_GET['force_run'])) {
    die("이 스크립트는 CLI(Cron) 환경에서만 실행할 수 있습니다.");
}

// 공통 헤더 로드 (DB 연결 및 lib_utils.php, config.php 상수 등 포함)
require_once dirname(__DIR__) . '/common/common_header.php';

$log_output = "";

$log_output .= "=================================================\n";
$log_output .= "[KShops24] 일간 정기 통합 배치 작업 시작\n";
$log_output .= "실행 시간: " . date('Y-m-d H:i:s') . "\n";
$log_output .= "=================================================\n\n";

// [사전 작업] 정확한 날짜 추적을 위해 shops 테이블 스키마 자동 업데이트
try {
    $pdo->exec("ALTER TABLE shops ADD COLUMN inactive_date DATE NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE shops ADD COLUMN closed_date DATE NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE shops ADD COLUMN deleted_date DATE NULL DEFAULT NULL");
} catch (Exception $e) {
    // 이미 컬럼이 존재하면 무시
}

// -------------------------------------------------------------------------
// [공통 변수 및 템플릿 로드]
// -------------------------------------------------------------------------
$today = date('Y-m-d');
$inactive_soon_days = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
$closed_soon_days = defined('SHOP_STATUS_CLOSED_SOON_DAYS') ? SHOP_STATUS_CLOSED_SOON_DAYS : 30;
$warning_closed_soon_days = defined('WARNING_SHOP_STATUS_CLOSED_SOON_DAYS') ? WARNING_SHOP_STATUS_CLOSED_SOON_DAYS : 14;
$warning_deleted_soon_days = defined('WARNING_SHOP_STATUS_DELETED_SOON_DAYS') ? WARNING_SHOP_STATUS_DELETED_SOON_DAYS : 30;
$deleted_soon_days = defined('SHOP_STATUS_DELETED_SOON_DAYS') ? SHOP_STATUS_DELETED_SOON_DAYS : 30;

// [제어 파라미터] 파라미터로 only_inode=1 이 전달되면 무거운 앞단계를 패스합니다.
$only_inode = isset($_GET['only_inode']) && $_GET['only_inode'] == '1';

if (!$only_inode) {
    // =========================================================================
    // [작업 1] 휴점 임박 알림 (inactive_soon)
    // =========================================================================
    $log_output .= "--- [작업 1] 만료 임박 상점 알림 발송 시작 ---\n";
    try {
        $target_date1 = date('Y-m-d', strtotime("+$inactive_soon_days days"));
        $log_output .= "▶ 대상 결제 마감일: {$target_date1} (오늘로부터 {$inactive_soon_days}일 후)\n";

        $sql1 = "
            SELECT s.id, s.shop_name, SUM(p.amount) as total_unpaid 
            FROM shops s
            JOIN shop_payments p ON s.id = p.shop_id
            WHERE s.status = 'active' 
            AND p.paid = 'n' 
            AND p.expiring_date = ?
            GROUP BY s.id, s.shop_name
        ";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute([$target_date1]);
        $warning_shops = $stmt1->fetchAll();

        $cnt1 = 0;
        foreach ($warning_shops as $shop) {
            sendShopMessage($pdo, $shop['id'], 'inactive_soon', [
                'shop_name' => $shop['shop_name'],
                'expiring_date' => $target_date1,
                'unpaid_amount' => number_format($shop['total_unpaid']),
                'SHOP_CLOSED_AFTER_INACTIVE' => $closed_soon_days
            ]);

            $cnt1++;
            $log_output .= "  -> [알림 발송] 상점 ID {$shop['id']} ({$shop['shop_name']})\n";
        }
        $log_output .= ">>> [작업 1 완료] 총 {$cnt1}건 알림 발송 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 1 오류] " . $e->getMessage() . "\n\n";
    }


    // =========================================================================
    // [작업 2] 미납 연체 상점 휴점 처리 (inactive)
    // =========================================================================
    $log_output .= "--- [작업 2] 미납 연체 상점 휴점 처리 시작 ---\n";
    try {
        $suspend_target_date = date('Y-m-d', strtotime('-1 day'));
        $log_output .= "▶ 대상 결제 마감일: {$suspend_target_date} (어제 만료)\n";

        $closed_date_val = date('Y-m-d', strtotime("+$closed_soon_days days"));
        $deleted_days_add = $closed_soon_days + $warning_deleted_soon_days + $deleted_soon_days;
        $deleted_date_val = date('Y-m-d', strtotime("+$deleted_days_add days"));

        $sql2 = "
            SELECT s.id, s.shop_name, SUM(p.amount) as total_unpaid 
            FROM shops s
            JOIN shop_payments p ON s.id = p.shop_id
            WHERE s.status = 'active' 
            AND p.paid = 'n' 
            AND p.expiring_date = ?
            GROUP BY s.id, s.shop_name
        ";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$suspend_target_date]);
        $suspend_shops = $stmt2->fetchAll();

        $cnt2 = 0;
        foreach ($suspend_shops as $shop) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE shops SET status = 'inactive', inactive_date = ?, closed_date = ?, deleted_date = ? WHERE id = ?")
                    ->execute([$today, $closed_date_val, $deleted_date_val, $shop['id']]);
                addShopHistoryLog($pdo, $shop['id'], SHOP_HISTORY_STATUS, "자동 휴점 처리", "월 사용료 혹은 추가 요금 연체 미납");

                sendShopMessage($pdo, $shop['id'], 'inactive', [
                    'shop_name' => $shop['shop_name'],
                    'expiring_date' => $closed_date_val,
                    'unpaid_amount' => number_format($shop['total_unpaid']),
                    'SHOP_CLOSED_AFTER_INACTIVE' => $closed_soon_days
                ]);

                $pdo->commit();
                $cnt2++;
                $log_output .= "  -> [휴점 처리] 상점 ID {$shop['id']} ({$shop['shop_name']})\n";
            } catch (Exception $e) {
                $pdo->rollBack();
                $log_output .= "  -> [처리 실패] 상점 ID {$shop['id']}: " . $e->getMessage() . "\n";
            }
        }
        $log_output .= ">>> [작업 2 완료] 총 {$cnt2}건 휴점 처리 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 2 오류] " . $e->getMessage() . "\n\n";
    }


    // =========================================================================
    // [작업 3] 정상영업(active) 복귀 처리
    // =========================================================================
    $log_output .= "--- [작업 3] 미납 완납 상점 정상영업 복귀 처리 시작 ---\n";
    try {
        // 휴점 상태인데 모든 결제가 납부('y' 또는 'f')되어 미납건이 0인 상점 검출
        $stmt3 = $pdo->query("
            SELECT id, shop_name 
            FROM shops 
            WHERE status = 'inactive' 
            AND id NOT IN (SELECT shop_id FROM shop_payments WHERE paid = 'n')
        ");
        $active_shops = $stmt3->fetchAll();

        $cnt3 = 0;
        foreach ($active_shops as $shop) {
            $pdo->prepare("UPDATE shops SET status = 'active', inactive_date = NULL, closed_date = NULL, deleted_date = NULL WHERE id = ?")
                ->execute([$shop['id']]);
            addShopHistoryLog($pdo, $shop['id'], SHOP_HISTORY_STATUS, "정상 영업 복귀", "모든 미납금 완납으로 인한 영업 재개");

            sendShopMessage($pdo, $shop['id'], 'active', [
                'shop_name' => $shop['shop_name']
            ]);

            $cnt3++;
            $log_output .= "  -> [정상 영업] 상점 ID {$shop['id']} ({$shop['shop_name']})\n";
        }
        $log_output .= ">>> [작업 3 완료] 총 {$cnt3}건 정상영업 복귀 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 3 오류] " . $e->getMessage() . "\n\n";
    }

    // =========================================================================
    // [작업 4] 휴점 후 폐점 임박 알림 (closed_soon)
    // =========================================================================
    $log_output .= "--- [작업 4] 폐점 임박 알림 발송 시작 ---\n";
    try {
        $target_date4 = date('Y-m-d', strtotime("+$warning_closed_soon_days days"));
        $stmt4 = $pdo->prepare("SELECT id, shop_name FROM shops WHERE status = 'inactive' AND closed_date = ?");
        $stmt4->execute([$target_date4]);
        $shops4 = $stmt4->fetchAll();

        $cnt4 = 0;
        foreach ($shops4 as $shop) {
            sendShopMessage($pdo, $shop['id'], 'closed_soon', [
                'shop_name' => $shop['shop_name'],
                'expiring_date' => $target_date4
            ]);
            $cnt4++;
        }
        $log_output .= ">>> [작업 4 완료] 총 {$cnt4}건 알림 발송 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 4 오류] " . $e->getMessage() . "\n\n";
    }

    // =========================================================================
    // [작업 5] 휴점 후 폐점 처리 (closed)
    // =========================================================================
    $log_output .= "--- [작업 5] 휴점 상점 자동 폐점 처리 시작 ---\n";
    try {
        $stmt5 = $pdo->prepare("SELECT id, shop_name FROM shops WHERE status = 'inactive' AND closed_date = ?");
        $stmt5->execute([$today]);
        $shops5 = $stmt5->fetchAll();

        $cnt5 = 0;
        foreach ($shops5 as $shop) {
            if (function_exists('closeShopWithRename')) {
                closeShopWithRename($pdo, $shop['id']);
            } else {
                $pdo->prepare("UPDATE shops SET status = 'closed' WHERE id = ?")->execute([$shop['id']]);
                addShopHistoryLog($pdo, $shop['id'], SHOP_HISTORY_STATUS, "자동 폐점 처리", "휴점 기간 초과");
            }
            sendShopMessage($pdo, $shop['id'], 'closed', [
                'shop_name' => $shop['shop_name']
            ]);
            $cnt5++;
            $log_output .= "  -> [폐점 처리] 상점 ID {$shop['id']} ({$shop['shop_name']})\n";
        }
        $log_output .= ">>> [작업 5 완료] 총 {$cnt5}건 자동 폐점 처리 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 5 오류] " . $e->getMessage() . "\n\n";
    }

    // =========================================================================
    // [작업 6] 폐점 후 삭제 임박 알림 (deleted_soon / 이메일)
    // =========================================================================
    $log_output .= "--- [작업 6] 삭제 임박 알림 이메일 발송 시작 ---\n";
    try {
        $target_date6 = date('Y-m-d', strtotime("+$warning_deleted_soon_days days"));
        $stmt6 = $pdo->prepare("SELECT id, shop_name, subdomain, manager_email, deleted_date FROM shops WHERE status = 'closed' AND deleted_date = ?");
        $stmt6->execute([$target_date6]);
        $shops6 = $stmt6->fetchAll();

        $cnt6 = 0;
        foreach ($shops6 as $shop) {
            $email_res = sendShopEmail($pdo, $shop['manager_email'], 'deleted_soon', [
                'shop_id' => $shop['id'],
                'shops:shop_name' => $shop['shop_name'],
                'shops:subdomain' => $shop['subdomain'],
                'deleted_date' => $shop['deleted_date']
            ]);

            // 발송 내역 히스토리 로그 기록
            $log_title = ($email_res === true) ? "삭제 임박 알림 메일 발송" : "삭제 임박 알림 메일 발송 실패";
            $log_content = ($email_res === true) ? "수신자: {$shop['manager_email']}" : "사유: " . (is_string($email_res) ? $email_res : "알 수 없는 오류");
            addShopHistoryLog($pdo, $shop['id'], SHOP_HISTORY_EMAIL, $log_title, $log_content);

            $cnt6++;
        }
        $log_output .= ">>> [작업 6 완료] 총 {$cnt6}건 알림 이메일 발송 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 6 오류] " . $e->getMessage() . "\n\n";
    }

    // =========================================================================
    // [작업 7] 폐점 후 완전 삭제 (deleted / 이메일)
    // =========================================================================
    $log_output .= "--- [작업 7] 상점 영구 삭제 처리 시작 ---\n";
    try {
        $stmt7 = $pdo->prepare("SELECT id, shop_name, subdomain, manager_email FROM shops WHERE status = 'closed' AND deleted_date = ?");
        $stmt7->execute([$today]);
        $shops7 = $stmt7->fetchAll();

        $cnt7 = 0;
        foreach ($shops7 as $shop) {
            // 삭제 전 마지막 이메일 발송
            $email_res = sendShopEmail($pdo, $shop['manager_email'], 'deleted', [
                'shop_id' => $shop['id'],
                'shops:shop_name' => $shop['shop_name'],
                'shops:subdomain' => $shop['subdomain']
            ]);

            // 상점 데이터 및 폴더 완전 삭제
            if (function_exists('deleteShopCompletely')) {
                deleteShopCompletely($pdo, $shop['id']);
            } else {
                $pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$shop['id']]);
            }
            $cnt7++;
            $log_output .= "  -> [삭제 처리] 상점 ID {$shop['id']} ({$shop['shop_name']})\n";
        }
        $log_output .= ">>> [작업 7 완료] 총 {$cnt7}건 상점 영구 삭제 완료\n\n";
    } catch (Exception $e) {
        $log_output .= ">>> [작업 7 오류] " . $e->getMessage() . "\n\n";
    }

} // end if (!$only_inode)

// =========================================================================
// [작업 8] 고아 파일(Orphaned Files) 및 만료된 DB 세션 자동 청소 (Inode 최적화)
// =========================================================================
$log_output .= "--- [작업 8] 시스템 찌꺼기(고아 파일/세션) 자동 청소 시작 ---\n";
try {
    // 1. 만료된 DB 세션 청소 (설정된 12시간이 지난 세션 안전 삭제)
    $stmt_session = $pdo->prepare("DELETE FROM site_sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)");
    $stmt_session->execute();
    $deleted_sessions = $stmt_session->rowCount();
    $log_output .= "  -> [DB 세션 정리] 기간 만료된 세션 데이터 {$deleted_sessions}개 삭제 완료\n";

    // 2. 고아 파일(DB에 없는 찌꺼기 이미지 및 폴더) 분석 및 삭제
    if (function_exists('analyzeSystemDiskIntegrity') && function_exists('deletePhysicalFiles') && function_exists('deleteDirectoryCompletely')) {
        $integrity = analyzeSystemDiskIntegrity($pdo);
        
        // 고아 폴더 삭제 (삭제된 상점의 잔재)
        $deleted_dirs = 0;
        foreach ($integrity['orphaned_directories'] as $dir) {
            $abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . str_replace(SHOP_UPLOADS_URL, '/uploads/shops/', $dir['path']);
            if (deleteDirectoryCompletely($abs_path)) {
                $deleted_dirs++;
            }
        }
        $log_output .= "  -> [고아 폴더 정리] 주인 잃은 빈 폴더 {$deleted_dirs}개 삭제 완료\n";

        // 고아 파일 삭제 (업로드 중 취소된 찌꺼기 파일 등)
        $orphaned_paths = array_column($integrity['orphaned_files'], 'path');
        $deleted_files = deletePhysicalFiles($orphaned_paths);
        $log_output .= "  -> [고아 파일 정리] DB에 등록되지 않은 잉여 이미지 파일 {$deleted_files}개 삭제 완료\n";
    }

    // 3. 오래된 시스템 로그(site_logs) 자동 청소 (90일 초과 데이터 삭제하여 DB 용량 확보)
    $stmt_logs = $pdo->prepare("DELETE FROM site_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt_logs->execute();
    $deleted_logs = $stmt_logs->rowCount();
    $log_output .= "  -> [시스템 로그 정리] 90일이 지난 오래된 DB 로그 {$deleted_logs}개 삭제 완료\n";
    
    $log_output .= ">>> [작업 8 완료] 서버 Inode 및 DB 용량 최적화 완료\n\n";
} catch (Exception $e) {
    $log_output .= ">>> [작업 8 오류] " . $e->getMessage() . "\n\n";
}


// =========================================================================
// [향후 추가 작업 영역]
// =========================================================================
/*
$log_output .= "--- [작업 4] 추가 작업 영역 ---\n";
try {
    // 새로운 일간 배치 로직을 이곳에 추가하시면 됩니다.
} catch (Exception $e) {
    $log_output .= ">>> [작업 4 오류] " . $e->getMessage() . "\n\n";
}
*/

// =========================================================================
// 모든 배치 작업 종료 처리
// =========================================================================
$log_output .= "=================================================\n";
$log_output .= "[KShops24] 모든 일간 정기 통합 배치 작업 종료\n";
$log_output .= "종료 시간: " . date('Y-m-d H:i:s') . "\n";
$log_output .= "=================================================\n";

// DB에 전체 리포트 내용 저장
try {
    $stmt = $pdo->prepare("INSERT INTO site_logs (log_type, message, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute(['cron_daily_report', "일간 배치 작업 리포트", $log_output]);
} catch (Exception $e) {
    error_log("Failed to save daily cron report to DB: " . $e->getMessage());
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success']);
} else {
    if (php_sapi_name() === 'cli') {
        echo $log_output;
    } else {
        echo "<pre>{$log_output}</pre>";
    }
}
