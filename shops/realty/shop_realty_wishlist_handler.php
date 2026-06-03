<?php

/**
 * [API] 부동산 매물 찜하기(관심등록) 동기화 핸들러
 * 클라이언트에서 찜 등록/해제 시 DB의 wish_count 값을 실시간으로 증감시킵니다.
 */

// 불필요한 공백이나 경고문 출력을 방지하여 완벽한 JSON 응답 보장
ob_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$shop_id = (int)($_POST['shop_id'] ?? 0);
$item_id = (int)($_POST['item_id'] ?? 0);

// 파라미터 검증
if (!$shop_id || !$item_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    // [핵심] 찜 횟수 업데이트 쿼리. 
    // 'remove' 시 GREATEST(0, ...)를 사용하여 카운트가 0 미만(음수)으로 떨어지는 것을 방지합니다.
    if ($action === 'add') {
        $sql = "UPDATE shop_items SET wish_count = wish_count + 1 WHERE id = ? AND shop_id = ?";
    } else {
        $sql = "UPDATE shop_items SET wish_count = GREATEST(0, wish_count - 1) WHERE id = ? AND shop_id = ?";
    }
    
    $pdo->prepare($sql)->execute([$item_id, $shop_id]);
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB 처리 중 오류가 발생했습니다.']);
}