<<<<<<< HEAD
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $customer_id = $_SESSION['customer_id'] ?? 0;

    if (!$shop_id || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => '필수 정보가 누락되었습니다.']);
        exit;
    }

    try {
        // 본인을 제외하고, 플랫폼 전체에서 이미 이 번호를 사용 중인 다른 카카오 계정이 있는지 전역 확인
        $stmt = $pdo->prepare("SELECT id FROM platform_customers WHERE ph_phone = ? AND id != ?");
        $stmt->execute([$phone, $customer_id]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'duplicate', 'message' => '이미 KShops24 플랫폼에 가입되어 사용 중인 전화번호입니다.<br>본인의 전화번호를 확인해 주세요.']);
        } else {
            echo json_encode(['status' => 'available']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '서버 오류가 발생했습니다.']);
    }
=======
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $customer_id = $_SESSION['customer_id'] ?? 0;

    if (!$shop_id || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => '필수 정보가 누락되었습니다.']);
        exit;
    }

    try {
        // 본인을 제외하고, 플랫폼 전체에서 이미 이 번호를 사용 중인 다른 카카오 계정이 있는지 전역 확인
        $stmt = $pdo->prepare("SELECT id FROM platform_customers WHERE ph_phone = ? AND id != ?");
        $stmt->execute([$phone, $customer_id]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'duplicate', 'message' => '이미 KShops24 플랫폼에 가입되어 사용 중인 전화번호입니다.<br>본인의 전화번호를 확인해 주세요.']);
        } else {
            echo json_encode(['status' => 'available']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '서버 오류가 발생했습니다.']);
    }
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
}