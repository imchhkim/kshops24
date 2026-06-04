<?php

/**
 * KShops24 F&B 주문 처리 핸들러
 */
// [버그 수정] 공통 헤더에서 발생할 수 있는 경고/공백 등의 HTML 출력을 버퍼링으로 차단하여 JSON 응답을 보호합니다.
ob_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
ob_end_clean();

// 응답을 JSON으로 유지 (common_header의 에러 핸들러와 충돌 방지)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)$_POST['shop_id'];

    // [추가] 프론트엔드 카트 모달에서 "주문하기" 버튼 클릭 시 사전 배달 시간 검증
    if (isset($_POST['action']) && $_POST['action'] === 'check_delivery_time') {
        $stmt_policy = $pdo->prepare("SELECT delivery_hours FROM shops WHERE id = ?");
        $stmt_policy->execute([$shop_id]);
        $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);

        $delivery_hours = $policy['delivery_hours'] ?? '';
        if (!empty($delivery_hours) && strpos($delivery_hours, '~') !== false) {
            list($start_time, $end_time) = explode('~', $delivery_hours);
            $start_time = trim($start_time);
            $end_time = trim($end_time);

            if (!empty($start_time) && !empty($end_time)) {
                $current_time = date('H:i');
                $is_open = false;

                // 주간/야간 영업 판단
                if ($start_time <= $end_time) {
                    if ($current_time >= $start_time && $current_time <= $end_time) {
                        $is_open = true;
                    }
                } else {
                    if ($current_time >= $start_time || $current_time <= $end_time) {
                        $is_open = true;
                    }
                }

                if (!$is_open) {
                    echo json_encode(['status' => 'error', 'message' => "지금은 배달 가능한 시간이 아닙니다.\n배달 가능 시간은 [ {$start_time} ~ {$end_time} ] 입니다."]);
                    exit;
                }
            }
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    $phone = trim($_POST['customer_phone']);
    $address = trim($_POST['customer_address']);
    $landmark = trim($_POST['customer_landmark']);
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $payment_detail = trim($_POST['payment_detail'] ?? '');
    $customer_lat = !empty($_POST['customer_lat']) ? trim($_POST['customer_lat']) : null;
    $customer_lng = !empty($_POST['customer_lng']) ? trim($_POST['customer_lng']) : null;
    $cart_data = json_decode($_POST['cart_data'], true);
    $order_type = $_POST['order_type'] ?? 'delivery';
    $pickup_time = $_POST['pickup_time'] ?? null;

    if (empty($cart_data) || !$shop_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. 주문 번호 생성 (YYYYMMDD-RANDOM)
        $order_no = date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));

        // 2. 상품 총액 계산
        $subtotal_price = 0;
        foreach ($cart_data as $item) {
            $subtotal_price += ($item['price'] * $item['quantity']);
        }

        // 2-1. 배달 시간 검증 및 배달비 정책 조회
        $stmt_policy = $pdo->prepare("SELECT delivery_hours, delivery_fee, free_delivery_amount FROM shops WHERE id = ?");
        $stmt_policy->execute([$shop_id]);
        $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);

        // [추가] 배달 가능 시간 검증 로직
        $delivery_hours = $policy['delivery_hours'] ?? '';
        if (!empty($delivery_hours) && strpos($delivery_hours, '~') !== false) {
            list($start_time, $end_time) = explode('~', $delivery_hours);
            $start_time = trim($start_time);
            $end_time = trim($end_time);

            if (!empty($start_time) && !empty($end_time)) {
                $current_time = date('H:i');
                $is_open = false;

                // 주간 영업 (예: 10:00 ~ 22:00)
                if ($start_time <= $end_time) {
                    if ($current_time >= $start_time && $current_time <= $end_time) {
                        $is_open = true;
                    }
                } else {
                    // 야간 영업 (예: 22:00 ~ 04:00) - 자정을 넘기는 경우
                    if ($current_time >= $start_time || $current_time <= $end_time) {
                        $is_open = true;
                    }
                }

                if (!$is_open) {
                    echo json_encode(['status' => 'error', 'message' => "지금은 배달 가능한 시간이 아닙니다.\n배달 가능 시간은 [ {$start_time} ~ {$end_time} ] 입니다."]);
                    exit;
                }
            }
        }

        $applied_delivery_fee = (int)($policy['delivery_fee'] ?? 0);
        $free_delivery_amount = (int)($policy['free_delivery_amount'] ?? 0);

        // 매장픽업 주문일 경우 배달비 무료
        if ($order_type === 'pickup') {
            $applied_delivery_fee = 0;
        } else {
            // 무료 배달 기준이 있고(0 초과), 주문액이 기준 이상이면 배달비 무료
            if ($free_delivery_amount > 0 && $subtotal_price >= $free_delivery_amount) {
                $applied_delivery_fee = 0;
            }
        }

        $total_price = $subtotal_price + $applied_delivery_fee;


        // 3. 주문 마스터 저장 (shop_orders)
        $sql_order = "INSERT INTO shop_orders (shop_id, order_no, customer_phone, customer_address, customer_landmark, total_price, order_type, pickup_time, payment_method, payment_detail, customer_lat, customer_lng, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql_order);
        $stmt->execute([$shop_id, $order_no, $phone, $address, $landmark, $total_price, $order_type, $pickup_time, $payment_method, $payment_detail, $customer_lat, $customer_lng]);
        $order_id = $pdo->lastInsertId();

        // 4. 주문 상세 내역 저장 (shop_order_items)
        $sql_item = "INSERT INTO shop_order_items (order_id, item_id, item_name, price, quantity) VALUES (?, ?, ?, ?, ?)";
        $stmt_item = $pdo->prepare($sql_item);

        foreach ($cart_data as $item) {
            $stmt_item->execute([
                $order_id,
                $item['id'],
                $item['name'],
                $item['price'],
                $item['quantity']
            ]);
        }

        // 5. 최신 배달 정보를 세션에 즉시 반영 (로그인/비로그인 공통)
        // 이를 통해 새로고침 후에도 '지난번 정보'가 유지됨
        $_SESSION['customer_ph_phone'] = $phone;
        $_SESSION['customer_ph_address'] = $address;
        $_SESSION['customer_ph_landmark'] = $landmark;

        // 6. [통합고객] 로그인된 사용자인 경우, 플랫폼 전역 DB 프로필도 함께 업데이트
        if (isset($_SESSION['customer_id'])) {
            $stmt_profile = $pdo->prepare("UPDATE platform_customers SET ph_phone = ?, ph_address = ?, ph_landmark = ?, updated_at = NOW() WHERE id = ?");
            $stmt_profile->execute([$phone, $address, $landmark, $_SESSION['customer_id']]);
        }

        $pdo->commit();

        // 7. [텔레그램 알림 발송] 상점주의 텔레그램으로 푸시 알림 전송 (주문 성공 직후)
        try {
            $stmt_shop = $pdo->prepare("SELECT shop_name, use_telegram_alert, telegram_alert_types, telegram_chat_id, ui_settings FROM shops WHERE id = ?");
            $stmt_shop->execute([$shop_id]);
            $shop_info = $stmt_shop->fetch(PDO::FETCH_ASSOC);

            if ($shop_info && $shop_info['use_telegram_alert'] === 'Y' && !empty($shop_info['telegram_chat_id'])) {
                $alert_types = explode(',', $shop_info['telegram_alert_types'] ?? '');
                if (in_array('order', $alert_types)) {
                    $chat_id = $shop_info['telegram_chat_id'];
                    $shop_name = $shop_info['shop_name'] ?? '상점';

                    $ui = json_decode($shop_info['ui_settings'] ?? '{}', true);
                    $shop_currency = $ui['currency'] ?? 'PHP';
                    $currency_symbols = [
                        'PHP' => '₱',
                        'KRW' => '₩',
                        'USD' => '$',
                        'JPY' => '¥',
                        'CNY' => '¥',
                        'VND' => '₫'
                    ];
                    $currency_symbol = $currency_symbols[$shop_currency] ?? '₱';

                    // 주문 상세 메뉴 문자열 구성
                    $item_details = "";
                    foreach ($cart_data as $item) {
                        $item_details .= "  - " . $item['name'] . " " . $item['quantity'] . "개\n";
                    }

                    $msg = "🔔 <b>[{$shop_name}] 신규 주문 접수!</b>\n\n";
                    $msg .= "▪ 주문번호: {$order_no}\n";
                    $order_type_kr = ($order_type === 'pickup') ? '매장픽업' : '배달';
                    if ($order_type === 'pickup' && !empty($pickup_time)) {
                        $order_type_kr .= " (방문예정: {$pickup_time})";
                    }
                    $msg .= "▪ 주문유형: {$order_type_kr}\n";
                    $msg .= "▪ 주문메뉴:\n{$item_details}";
                    $msg .= "▪ 상품금액: {$currency_symbol} " . number_format($subtotal_price) . "\n";
                    $msg .= "▪ 배달비용: {$currency_symbol} " . number_format($applied_delivery_fee) . "\n";
                    $msg .= "▪ 총결제액: {$currency_symbol} " . number_format($total_price) . "\n";

                    $pay_method_kr = ($payment_method === 'cash') ? '현금(Cash)' : '기타(GCash 등)';
                    $msg .= "▪ 결제방식: {$pay_method_kr}\n";
                    if (!empty($payment_detail)) $msg .= "▪ 결제/잔돈요청: {$payment_detail}\n";

                    $msg .= "▪ 연락처: {$phone}\n";
                    $msg .= "▪ 주소: {$address} {$landmark}\n\n";
                    $msg .= "<a href='https://kshops24.com/shops/manage_shop.php?pg=manage_shop_orders'>👉 배달 관리 페이지 확인하기</a>";

                    // [알림 함수 호출] 공용 텔레그램 발송 함수 실행
                    if (function_exists('send_ps24_telegram')) {
                        send_ps24_telegram($msg, $chat_id);
                    }
                }
            }
        } catch (Exception $e) {
            // 텔레그램 발송 실패가 전체 주문 취소로 이어지지 않도록 에러를 무시합니다.
        }

        echo json_encode(['status' => 'success', 'order_no' => $order_no]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
