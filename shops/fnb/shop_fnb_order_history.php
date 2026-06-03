<?php

/**
 * KShops24 F&B 주문 내역 조회 핸들러
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'fetch';
    $shop_id = (int)($_POST['shop_id'] ?? 0);

    if (!$shop_id) {
        echo json_encode(['status' => 'error', 'message' => '상점 정보가 올바르지 않습니다.']);
        exit;
    }

    // --- 1. 주문 내역 삭제 처리 ---
    if ($action === 'delete') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if (!$order_id) {
            echo json_encode(['status' => 'error', 'message' => '주문 번호가 누락되었습니다.']);
            exit;
        }

        try {
            // 해당 주문의 현재 상태 확인
            $stmt_check = $pdo->prepare("SELECT status FROM shop_orders WHERE id = ? AND shop_id = ?");
            $stmt_check->execute([$order_id, $shop_id]);
            $order_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($order_info) {
                if ($order_info['status'] === 'pending') {
                    // [수정] "주문접수"(pending) 상태인 경우, 상점주의 DB 내역에서도 주문을 완전히 삭제 (주문취소)
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM shop_order_items WHERE order_id = ?")->execute([$order_id]);
                    $pdo->prepare("DELETE FROM shop_orders WHERE id = ? AND shop_id = ?")->execute([$order_id, $shop_id]);
                    $pdo->commit();
                } else {
                    // 기존 로직: 완전 삭제(DELETE) 대신 소프트 삭제(UPDATE) 처리
                    $stmt_del_order = $pdo->prepare("UPDATE shop_orders SET is_deleted_by_customer = 1 WHERE id = ? AND shop_id = ?");
                    $stmt_del_order->execute([$order_id, $shop_id]);
                }
            }

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => '삭제 실패: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- 1.5 주문 내역 상태 폴링 (가벼운 데이터만 조회) ---
    if ($action === 'poll') {
        $phone = preg_replace('/[^0-9]/', '', $_POST['customer_phone'] ?? '');
        if (empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => '전화번호를 입력해주세요.']);
            exit;
        }

        try {
            // [통합고객] o.* (주문내역)과 함께 pc.* (플랫폼 통합 고객의 닉네임, 프로필 등)을 조인하여 최신 정보 로드
            $stmt = $pdo->prepare("
                SELECT o.id, o.order_no, o.status, o.total_price, o.created_at, o.customer_address, o.order_type, o.pickup_time, pc.nickname, pc.profile_img
                FROM shop_orders o
                LEFT JOIN platform_customers pc ON REPLACE(o.customer_phone, '-', '') COLLATE utf8mb4_unicode_ci = REPLACE(pc.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci
                WHERE o.shop_id = ? AND REPLACE(o.customer_phone, '-', '') COLLATE utf8mb4_unicode_ci = ? AND o.is_deleted_by_customer = 0
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$shop_id, $phone]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // [추가] 각 주문별 메뉴 내역(아이템 이름, 수량) 포함
            foreach ($orders as &$order) {
                $stmt_items = $pdo->prepare("SELECT item_name, quantity FROM shop_order_items WHERE order_id = ?");
                $stmt_items->execute([$order['id']]);
                $order['items'] = json_encode($stmt_items->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
            }

            echo json_encode(['status' => 'success', 'orders' => $orders]);
        } catch (Exception $e) {
        }
        exit;
    }

    // --- 2. 주문 내역 조회 처리 (기존 로직) ---
    $phone = preg_replace('/[^0-9]/', '', $_POST['customer_phone'] ?? '');
    if (empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => '전화번호를 입력해주세요.']);
        exit;
    }

    try {
        // 1. 최근 5개의 주문 내역을 가져옴
        // [통합고객] 주문 내역과 함께 플랫폼 고객 테이블을 조인하여 항상 최신 고객 정보(프로필 등)를 포함시킵니다.
        $stmt = $pdo->prepare("
            SELECT o.*, pc.nickname, pc.profile_img
            FROM shop_orders o
            LEFT JOIN platform_customers pc ON REPLACE(o.customer_phone, '-', '') COLLATE utf8mb4_unicode_ci = REPLACE(pc.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci
            WHERE o.shop_id = ? AND REPLACE(o.customer_phone, '-', '') COLLATE utf8mb4_unicode_ci = ? AND o.is_deleted_by_customer = 0
            ORDER BY o.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$shop_id, $phone]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. 각 주문별 아이템 내역을 PHP에서 안전하게 결합
        foreach ($orders as &$order) {
            $stmt_items = $pdo->prepare("SELECT item_id, item_name, price, quantity FROM shop_order_items WHERE order_id = ?");
            $stmt_items->execute([$order['id']]);
            // 프론트엔드가 JSON 파싱을 기대하므로 JSON 문자열로 인코딩하여 반환
            $order['items'] = json_encode($stmt_items->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        }

        echo json_encode(['status' => 'success', 'orders' => $orders]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
