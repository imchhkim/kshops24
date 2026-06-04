<?php

/**
 * KShops24 상점 기본 정보 설정 액션 모듈 (manage_shop_info_action.php)
 * - 역할: 상점 기본 정보 수정, 텔레그램 연동, 비밀번호 변경 등 백엔드 로직 처리
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// ---------------------------------------------------------
// [AJAX] 실시간 중복 체크 (수정 모드 전용)
// ---------------------------------------------------------
if (isset($_GET['check_field']) && isset($_GET['value'])) {
    while (ob_get_level()) {
        ob_end_clean();
    } // 출력 버퍼 정리
    $field = $_GET['check_field'];
    $value = trim($_GET['value']);
    $allowed = ['kakao_id', 'phone_mobile', 'kakao_channel_id', 'phone_landline', 'facebook_url', 'telegram_chat_id'];

    if (in_array($field, $allowed) && !empty($value)) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM shops WHERE {$field} = ? AND id != ? AND status != 'closed'");
        $stmt_check->execute([$value, $shop_id]);
        $is_dup = $stmt_check->fetchColumn() > 0;
        echo $is_dup ? "duplicate" : "available";
    }
    exit;
}

// ---------------------------------------------------------
// [AJAX] 텔레그램 봇 연동 테스트 발송
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_telegram') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $chat_id = trim($_POST['telegram_chat_id'] ?? '');

    if (empty($chat_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Chat ID가 누락되었습니다.']);
        exit;
    }

    $msg = "🔔 [KShops24 테스트 알림]\n정상적으로 연동되었습니다!\n앞으로 KShops24의 모든 알림은 이곳으로 전송됩니다.";

    $response = send_ps24_telegram($msg, $chat_id);
    $result = json_decode($response, true);
    if ($result && isset($result['ok']) && $result['ok'] === true) {
        echo json_encode(['status' => 'success', 'message' => "테스트 메시지가 성공적으로 전송되었습니다!\n스마트폰의 텔레그램 앱을 확인해보세요."]);
    } else {
        $error_desc = $result['description'] ?? '알 수 없는 오류 (토큰 또는 Chat ID를 다시 확인해주세요)';
        echo json_encode(['status' => 'error', 'message' => '발송 실패: ' . $error_desc]);
    }
    exit;
}

// [AJAX] 텔레그램 설정 저장 (개별 폼 대응)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_telegram_config') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $use_alert = $_POST['use_telegram_alert'] ?? 'N';
    $alert_types = isset($_POST['alert_types']) && is_array($_POST['alert_types']) ? implode(',', $_POST['alert_types']) : 'order,cancel,message,review';

    try {
        $pdo->prepare("UPDATE shops SET telegram_chat_id = ?, use_telegram_alert = ?, telegram_alert_types = ? WHERE id = ?")->execute([$chat_id, $use_alert, $alert_types, $shop_id]);
        echo json_encode(['status' => 'success', 'message' => '텔레그램 설정이 성공적으로 저장되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// 상점 설정값(POST) 저장 및 업데이트 메인 로직
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop'])) {
    // 1. 비밀번호 변경 (AJAX 전용 처리)
    if (isset($_POST['ajax_pw_change'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            if (empty($_POST['current_password'])) throw new Exception("현재 비밀번호를 입력해주세요.");
            if (!password_verify($_POST['current_password'], $shop['manager_password'])) throw new Exception("현재 비밀번호가 올바르지 않습니다.");
            if (empty($_POST['new_password']) || $_POST['new_password'] !== $_POST['confirm_password']) throw new Exception("변경할 비밀번호 확인이 일치하지 않습니다.");

            $hashed_pw = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE shops SET manager_password = ?, is_temp_password = 0 WHERE id = ?")->execute([$hashed_pw, $shop_id]);
            echo json_encode(['status' => 'success', 'message' => '비밀번호가 성공적으로 변경되었습니다.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 1-2. 임시 비밀번호 발송 (AJAX 전용 처리)
    if (isset($_POST['ajax_send_temp_pw'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $email = $shop['manager_email'];
            if (empty($email)) throw new Exception("등록된 관리자 이메일이 없습니다.");

            $temp_pw = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
            $hashed_pw = password_hash($temp_pw, PASSWORD_DEFAULT);

            $pdo->prepare("UPDATE shops SET manager_password = ?, is_temp_password = 1 WHERE id = ?")->execute([$hashed_pw, $shop_id]);

            // 공용 함수 호출을 통한 모듈화
            $status = sendTempPasswordEmail($pdo, $email, $temp_pw);

            if ($status === true) {
                echo json_encode(['status' => 'success', 'message' => "등록된 관리자 이메일({$email})로 임시 비밀번호가 발송되었습니다."]);
            } else {
                throw new Exception("메일 발송 실패: " . $status);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    try {
        // 2. 일반 폼 전송 방식에서의 비밀번호 변경 처리
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) throw new Exception("현재 비밀번호를 입력해주세요.");
            if (!password_verify($_POST['current_password'], $shop['manager_password'])) throw new Exception("현재 비밀번호가 올바르지 않습니다.");
            if ($_POST['new_password'] !== $_POST['confirm_password']) throw new Exception("변경할 비밀번호가 서로 일치하지 않습니다.");
            $hashed_pw = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE shops SET manager_password = ?, is_temp_password = 0 WHERE id = ?")->execute([$hashed_pw, $shop_id]);
        }

        // 3. DB 저장 직전 중복 데이터 최종 검증
        $check_fields = [
            'kakao_id' => '카카오톡 ID',
            'phone_mobile' => '휴대전화',
            'kakao_channel_id' => '카카오 채널 ID',
            'phone_landline' => '유선전화',
            'facebook_url' => '페이스북 URL',
            'telegram_chat_id' => '텔레그램 챗ID'
        ];

        $check_sql = "SELECT subdomain, kakao_id, phone_mobile, kakao_channel_id, phone_landline, facebook_url, telegram_chat_id FROM shops WHERE id != ? AND status != 'closed' AND (";
        $check_parts = [];
        $check_params = [$shop_id];
        foreach ($check_fields as $f_key => $f_label) {
            if (!empty($_POST[$f_key])) {
                $check_parts[] = "$f_key = ?";
                $check_params[] = $_POST[$f_key];
            }
        }
        if (!empty($check_parts)) {
            $stmt_check = $pdo->prepare($check_sql . implode(' OR ', $check_parts) . ") LIMIT 1");
            $stmt_check->execute($check_params);
            if ($stmt_check->fetch()) throw new Exception("중복된 정보(카톡ID/전화번호 등)가 이미 등록되어 있습니다.");
        }

        // 4. 요일별 영업시간 데이터(JSON) 처리
        if (isset($_POST['bh']) && is_array($_POST['bh'])) {
            $_POST['business_hours'] = json_encode($_POST['bh'], JSON_UNESCAPED_UNICODE);
        }

        // 5. 동적 업데이트 쿼리(Partial Update) 생성
        $updatable_fields = [
            'manager_name',
            'manager_name_en',
            'business_hours',
            'delivery_hours',
            'phone_mobile',
            'phone_landline',
            'kakao_id',
            'kakao_channel_id',
            'facebook_url',
            'physical_address',
            'min_delivery_amount',
            'delivery_fee_info',
            'estimated_delivery_time',
            'payment_methods',
            'is_pickup_available',
            'is_delivery_available',
            'telegram_chat_id',
            'use_telegram_alert',
            'telegram_alert_types',
            'tin_number',
            'registered_name',
            'business_address',
            'business_type'
        ];

        $update_parts = [];
        $params = [];
        foreach ($updatable_fields as $field) {
            if (isset($_POST[$field])) {
                $update_parts[] = "$field = ?";
                $val = $_POST[$field];
                if (in_array($field, ['min_delivery_amount', 'is_pickup_available', 'is_delivery_available'])) {
                    $val = (int)$val;
                }
                if (in_array($field, ['kakao_id', 'kakao_channel_id', 'facebook_url', 'phone_mobile', 'phone_landline', 'telegram_chat_id']) && $val === '') {
                    $val = null;
                }
                $params[] = $val;
            }
        }

        // 6. UI 레이블 설정 처리 (JSON 병합)
        if (isset($_POST['ui'])) {
            $existing_ui = json_decode($shop['ui_settings'] ?? '{}', true);
            if (!is_array($existing_ui)) $existing_ui = [];
            $ui_raw = $_POST['ui'];
            $ui_new = array_map('trim', $ui_raw);
            $ui_new = array_filter($ui_new, fn($v) => $v !== '');
            $ui_merged = array_merge($existing_ui, $ui_new);
            $update_parts[] = "ui_settings = ?";
            $params[] = json_encode($ui_merged, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($update_parts)) {
            $sql = "UPDATE shops SET " . implode(', ', $update_parts) . " WHERE id = ?";
            $params[] = $shop_id;
            $pdo->prepare($sql)->execute($params);
        }

        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => '설정이 성공적으로 저장되었습니다.']);
            exit;
        }

        $message = "설정이 성공적으로 저장되었습니다.";
        $msg_type = "success";

        // 업데이트가 끝나면 현재 페이지에 최신 상태를 반영하기 위해 다시 조회
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $shop = $stmt->fetch();
    } catch (Exception $e) {
        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
        $message = $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// 데이터 준비 (갤러리, 카테고리 등)
// ---------------------------------------------------------
static $shop_category_labels = ['fnb' => '음식점/배달', 'cafe' => '카페/디저트', 'beauty' => '뷰티/헤어', 'mart' => '마트/식료품', 'service' => '일반 서비스/기타'];