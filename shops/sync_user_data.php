<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php'; // DB 연결($pdo) 및 세션 시작

// JSON 응답을 위해 이전에 출력된 HTML이나 경고문이 있다면 제거
if (ob_get_level()) ob_clean();
header('Content-Type: application/json');

$customer_id = $_SESSION['customer_id'] ?? null;
$action = $_GET['action'] ?? '';

if (!$customer_id) {
    echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
    exit;
}

try {
    global $pdo;
    
    if ($action === 'save') {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        if ($key !== '') {
            $stmt = $pdo->prepare("INSERT INTO customer_storage (customer_id, data_key, data_value, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE data_value = ?, updated_at = NOW()");
            $stmt->execute([$customer_id, $key, $value, $value]);
            echo json_encode(['status' => 'success']);
        }
    } elseif ($action === 'delete') {
        $key = $_POST['key'] ?? '';
        if ($key !== '') {
            $stmt = $pdo->prepare("DELETE FROM customer_storage WHERE customer_id = ? AND data_key = ?");
            $stmt->execute([$customer_id, $key]);
            echo json_encode(['status' => 'success']);
        }
    } elseif ($action === 'load') {
        $stmt = $pdo->prepare("SELECT data_key, data_value FROM customer_storage WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[$row['data_key']] = $row['data_value'];
        }
        echo json_encode(['status' => 'success', 'items' => $items]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '잘못된 액션입니다.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB 오류: ' . $e->getMessage()]);
}
?>