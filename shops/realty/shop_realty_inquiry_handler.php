<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $phone = preg_replace('/[^0-9]/', '', $_POST['customer_phone'] ?? '');
    $inquiry = trim($_POST['customer_inquiry'] ?? '');
    $inquiry_data = trim($_POST['inquiry_data'] ?? '');

    if (empty($inquiry_data) || !$shop_id || empty($phone) || empty($inquiry)) {
        echo json_encode(['status' => 'error', 'message' => '필수 정보가 누락되었습니다.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO shop_inquiries (shop_id, customer_phone, customer_inquiry, inquiry_data, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$shop_id, $phone, $inquiry, $inquiry_data]);

        // 세션에 최신 연락처 기록 (다음 로그인 시 편의 제공)
        $_SESSION['customer_ph_phone'] = $phone;
        if (isset($_SESSION['customer_id'])) {
            // [버그 수정] 존재하지 않는 테이블(shop_customers) 호출 에러 수정
            $pdo->prepare("UPDATE platform_customers SET ph_phone = ?, updated_at = NOW() WHERE id = ?")->execute([$phone, $_SESSION['customer_id']]);
        }

        $pdo->commit();

        // 상점주 텔레그램 알림 발송 처리
        try {
            $stmt_shop = $pdo->prepare("SELECT shop_name, use_telegram_alert, telegram_alert_types, telegram_chat_id FROM shops WHERE id = ?");
            $stmt_shop->execute([$shop_id]);
            $shop_info = $stmt_shop->fetch(PDO::FETCH_ASSOC);

            if ($shop_info && $shop_info['use_telegram_alert'] === 'Y' && !empty($shop_info['telegram_chat_id'])) {
                $alert_types = explode(',', $shop_info['telegram_alert_types'] ?? '');
                // 부동산에서는 'order'를 신규 문의 접수로 간주
                if (in_array('order', $alert_types)) {
                    $items = json_decode($inquiry_data, true);
                    $item_details = "";
                    if (is_array($items)) foreach ($items as $item) $item_details .= "  - " . $item['name'] . "\n";

                    $msg = "🔔 <b>[{$shop_info['shop_name']}] 신규 부동산 상담 문의!</b>\n\n";
                    $msg .= "▪ 관심매물:\n{$item_details}";
                    $msg .= "▪ 연락처: {$phone}\n";
                    $msg .= "▪ 문의내용: {$inquiry}\n\n";
                    $msg .= "<a href='https://kshops24.com/shops/login.php'>👉 관리자 페이지에서 확인하기</a>";

                    if (function_exists('send_ps24_telegram')) send_ps24_telegram($msg, $shop_info['telegram_chat_id']);
                }
            }
        } catch (Exception $e) {
        } // 알림 실패가 접수 실패로 이어지지 않게 무시

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => '서버 오류가 발생했습니다.']);
    }
}
