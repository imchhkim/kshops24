<?php
/**
 * KShops24 고객 정보 업데이트 처리 (update_customer_info.php)
 * - 역할: 카카오 로그인 후 추가로 입력받은 필리핀 현지 연락처 및 배달 주소를 DB와 세션에 저장함.
 * - 비즈니스 로직: F&B 및 배달 서비스 이용 시 필요한 필수 고객 정보를 확보하고 유지 관리함.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$landmark = $_POST['landmark'] ?? '';

// 입력된 번호에서 하이픈 등 숫자 이외의 문자 제거
$phone = preg_replace('/[^0-9]/', '', $phone);

if (empty($phone) || empty($address)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

// 필리핀 휴대전화 번호 형식 검증 (09XXXXXXXXX)
if (!preg_match('/^09[0-9]{9}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => '필리핀 번호 형식이 아닙니다. (09로 시작하는 11자리 숫자)']);
    exit;
}

try {
    // [통합고객] 플랫폼 전역 고객 정보 업데이트
    $stmt = $pdo->prepare("UPDATE platform_customers SET ph_phone = ?, ph_address = ?, ph_landmark = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$phone, $address, $landmark, $_SESSION['customer_id']]);

    $_SESSION['customer_ph_phone'] = $phone;
    $_SESSION['customer_ph_address'] = $address;
    $_SESSION['customer_ph_landmark'] = $landmark;

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}