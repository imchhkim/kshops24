<?php

/**
 * 파일명: /admin/cron_monthly_works.php (구 cron_monthly_billing.php)
 * 역할: 매월 1일에 실행되는 월간 배치 작업 통합 스크립트.
 *       리소스 초과 요금 청구 외에도 향후 추가될 월간 자동화 작업들을 관리합니다.
 * 실행 방법: 서버 Crontab에 매월 1일 자정 실행 등록
 *            (예: 0 0 1 * * php /path/to/cron_monthly_works.php)
 */

// CLI 모드에서만 실행되도록 보안 처리 (웹 브라우저 직접 접근 차단)
// 수동 강제 실행이 필요할 경우 GET 파라미터로 ?force_run=1 을 붙여 실행할 수 있습니다.
if (php_sapi_name() !== 'cli' && !isset($_GET['force_run'])) {
    die("이 스크립트는 CLI(Cron) 환경에서만 실행할 수 있습니다.");
}

// 공통 헤더 로드 (DB 연결 및 lib_utils.php 등 포함)
require_once dirname(__DIR__) . '/common/common_header.php';

 $log_output = "";

 $log_output .= "=================================================\n";
 $log_output .= "[KShops24] 월간 정기 통합 배치 작업 시작\n";
 $log_output .= "실행 시간: " . date('Y-m-d H:i:s') . "\n";
 $log_output .= "=================================================\n\n";

// -------------------------------------------------------------------------
// [공통 변수] 대상 월 설정 (기본값: 전월)
// 리소스 청구나 통계 등은 항상 '지난달'을 기준으로 계산합니다.
// -------------------------------------------------------------------------
$target_month = date('Y-m', strtotime('first day of last month'));
 $log_output .= "▶ 분석 대상 월: {$target_month}\n\n";


// =========================================================================
// [작업 1] 월간 리소스(디스크/DB) 사용량 분석 및 초과 요금 청구
// =========================================================================
 $log_output .= "--- [작업 1] 월간 리소스 초과 요금 청구 시작 ---\n";
$success_count = 0;
$fail_count = 0;
$total_billed = 0;

try {
    // 과금 대상인 활성 상태('active') 상점만 조회
    $stmt = $pdo->query("SELECT id, shop_name FROM shops WHERE status = 'active'");
    $shops = $stmt->fetchAll();

    foreach ($shops as $shop) {
        $log_output .= "상점 검사 중: {$shop['shop_name']} (ID: {$shop['id']})... ";

        if (function_exists('processMonthlyOverageBilling')) {
            // 핵심 과금 로직 실행 (lib_utils.php 참조)
            $result = processMonthlyOverageBilling($pdo, $shop['id'], $target_month);

            if ($result['success']) {
                $success_count++;
                if ($result['billed_amount'] > 0) {
                    $total_billed += $result['billed_amount'];
                    $log_output .= "[청구됨] {$result['billed_amount']} PHP - {$result['message']}\n";
                } else {
                    $log_output .= "[무료통과] 초과 리소스 없음\n";
                }
            } else {
                $fail_count++;
                $log_output .= "[실패] {$result['message']}\n";
            }
        } else {
            $log_output .= "[오류] processMonthlyOverageBilling 함수를 찾을 수 없습니다.\n";
            $fail_count++;
        }
    }

    $log_output .= ">>> [작업 1 완료] 총 검사: " . count($shops) . " / 청구액: " . number_format($total_billed) . " PHP (성공: {$success_count}, 실패: {$fail_count})\n\n";

} catch (Exception $e) {
    error_log("Cron Billing Error: " . $e->getMessage());
    $log_output .= ">>> [작업 1 치명적 오류] " . $e->getMessage() . "\n\n";
    if (function_exists('recordSiteLog')) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "월간 리소스 청구 배치 오류", ['error' => $e->getMessage()]);
    }
}


// =========================================================================
// [작업 2] 향후 추가될 월간 작업 영역 (예: DB 정리, 로그 삭제, 통계 등)
// =========================================================================
/*
 $log_output .= "--- [작업 2] 6개월 이상 지난 시스템 로그 자동 삭제 시작 ---\n";
try {
    $delete_date = date('Y-m-d 00:00:00', strtotime('-6 months'));
    $stmt_clean = $pdo->prepare("DELETE FROM site_logs WHERE created_at < ?");
    $stmt_clean->execute([$delete_date]);
    $deleted_rows = $stmt_clean->rowCount();
    
    $log_output .= ">>> [작업 2 완료] 삭제된 로그 수: {$deleted_rows}건\n\n";
} catch (Exception $e) {
    $log_output .= ">>> [작업 2 오류] " . $e->getMessage() . "\n\n";
}
*/


// =========================================================================
// 모든 배치 작업 종료 처리
// =========================================================================
 $log_output .= "=================================================\n";
 $log_output .= "[KShops24] 모든 월간 정기 통합 배치 작업 종료\n";
 $log_output .= "종료 시간: " . date('Y-m-d H:i:s') . "\n";
 $log_output .= "=================================================\n";

// DB에 전체 리포트 내용 저장
try {
    $stmt = $pdo->prepare("INSERT INTO site_logs (log_type, message, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute(['cron_monthly_report', "{$target_month} 월간 배치 작업 리포트", $log_output]);
} catch (Exception $e) {
    // DB 저장 실패가 전체 프로세스를 중단시키지 않도록 에러 로그만 남깁니다.
    error_log("Failed to save monthly cron report to DB: " . $e->getMessage());
}

// AJAX 요청일 경우 JSON 응답, CLI나 직접 브라우저 접근일 경우 텍스트 출력
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
