<?php

/**
 * KShops24 회원 탈퇴 처리 로직 (customer_withdrawal.php)
 */

// [버그 수정] 불필요한 HTML/CSS 출력을 막기 위해 버퍼링 시작
ob_start();

// 1. 공통 헤더 로드 (DB 연결 및 세션 관리)
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

ob_end_clean();

// 2. 응답 포맷 JSON 설정 (ajax 호출)
header('Content-Type: application/json');

// 3. [보안] 로그인 여부 확인 (비로그인 사용자 접근 차단)
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'error', 'message' => '로그인되지 않았습니다.']);
    exit;
}

$customer_id = (int)$_SESSION['customer_id'];
$shop_id = (int)$_SESSION['customer_shop_id']; // 접속 중인 상점 ID 가져오기

try {
    // 4. 트랜잭션 시작 (All-or-Nothing 보장)
    $pdo->beginTransaction();

    // 5. [데이터 관리] 플랫폼 통합 정책: 주문 내역과 플랫폼 고객 정보는 보존하고, 해당 상점과의 단골 연결(Mapping)만 해제합니다.
    $stmt = $pdo->prepare("DELETE FROM shop_customer_mapping WHERE shop_id = ? AND customer_id = ?");
    $stmt->execute([$shop_id, $customer_id]);

    // 7. 트랜잭션 Commit
    $pdo->commit();

    // 8. [세션 종료] 고객 세션 변수만 선택적으로 제거 (상점주 세션 등에 영향 안 주도록 처리)
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_shop_id']);
    unset($_SESSION['customer_nickname']);
    unset($_SESSION['customer_profile_img']);
    unset($_SESSION['customer_ph_phone']);
    unset($_SESSION['customer_ph_address']);

    // 9. [응답] 성공 메시지 반환
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // 10. [오류] 롤백 및 오류 메시지 반환
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
