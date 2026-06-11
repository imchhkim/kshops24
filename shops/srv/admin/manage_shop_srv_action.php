<?php

/**
 * KShops24 서비스/예약 상품 관리 액션 처리 모듈 (manage_shop_srv_action.php)
 * - 서비스 정책 수정, 서비스 카테고리/상품 추가, 수정, 삭제 및 AJAX 상태 변경을 처리합니다.
 */
if (!isset($shop_id)) exit;

$redirect_pg = $_GET['pg'] ?? 'manage_shop_item';

// [AJAX] 정책 노출 상태 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_policy_display') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $is_show = (int)($_POST['is_show'] ?? 1);
    try {
        $pdo->prepare("UPDATE shops SET is_show_delivery = ? WHERE id = ?")->execute([$is_show, $shop_id]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// --- [0] 서비스/예약 정책 수정 로직 ---
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop']) && !isset($_POST['flyer_order'])) {
    $dh_always = isset($_POST['dh_always_available']) ? 1 : 0;
    $delivery_hours_start = trim($_POST['delivery_hours_start'] ?? '');
    $delivery_hours_end = trim($_POST['delivery_hours_end'] ?? '');

    if ($dh_always) {
        $delivery_hours = '상시 문의';
    } elseif ($delivery_hours_start !== '' && $delivery_hours_end !== '') {
        $delivery_hours = $delivery_hours_start . '~' . $delivery_hours_end;
    } else {
        $delivery_hours = ''; // 비워두면 뷰에서 '영업시간과 동일'로 처리
    }

    $fee_info = trim($_POST['delivery_fee_info'] ?? '');
    $payment_methods = trim($_POST['payment_methods'] ?? '');

    // [다국어] 서비스 정책 번역 데이터 수집 및 JSON 변환
    $translations = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'delivery_fee_info_') === 0) {
            $langCode = str_replace('delivery_fee_info_', '', $key);
            if (!empty(trim($value))) {
                if (!isset($translations[$langCode])) $translations[$langCode] = [];
                $translations[$langCode]['delivery_fee_info'] = trim($value);
            }
        }
        if (strpos($key, 'payment_methods_') === 0) {
            $langCode = str_replace('payment_methods_', '', $key);
            if (!empty(trim($value))) {
                if (!isset($translations[$langCode])) $translations[$langCode] = [];
                $translations[$langCode]['payment_methods'] = trim($value);
            }
        }
    }
    $json_translations = !empty($translations) ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null;

    $sql = "UPDATE shops SET delivery_hours = ?, delivery_fee_info = ?, payment_methods = ?, policy_translations = ? WHERE id = ?";
    $pdo->prepare($sql)->execute([
        $delivery_hours,
        $fee_info,
        $payment_methods,
        $json_translations,
        $shop_id
    ]);

    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=policy_updated';</script>";
    exit;
}

// ---------------------------------------------------------
// --- [0-1] 서비스/예약 스케줄 설정 업데이트 ---
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $slot_interval = (int)($_POST['slot_interval'] ?? 60);
    $max_concurrent = (int)($_POST['max_concurrent'] ?? 1);
    $slots = $_POST['slots'] ?? [];
    
    $settings = [
        'slot_interval' => $slot_interval,
        'max_concurrent' => $max_concurrent,
        'available_slots' => $slots
    ];
    
    $json_settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
    
    $pdo->prepare("UPDATE shops SET reservation_settings = ? WHERE id = ?")->execute([$json_settings, $shop_id]);
    
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=policy_updated';</script>";
    exit;
}

// ---------------------------------------------------------
// --- [A] 카테고리(Category) 관련 처리 로직 ---
// ---------------------------------------------------------
if (isset($_POST['add_category'])) {
    $cat_name = trim($_POST['cat_name']);
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO shop_item_categories (shop_id, cat_name) VALUES (?, ?)");
        $stmt->execute([$shop_id, $cat_name]);
    }
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=cat_added';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $cat_id = (int)$_POST['cat_id'];
    $new_name = trim($_POST['new_name']);

    if (empty($new_name)) {
        echo json_encode(['status' => 'error', 'message' => '카테고리 이름을 입력해주세요.']);
        exit;
    }

    try {
        $pdo->prepare("UPDATE shop_item_categories SET cat_name = ? WHERE id = ? AND shop_id = ?")->execute([$new_name, $cat_id, $shop_id]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [AJAX 추가] AI 번역 결과 수동 편집 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category_translation') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $cat_id = (int)$_POST['cat_id'];
    $lang_code = trim($_POST['lang_code']);
    $new_translation = trim($_POST['new_translation']);

    if (empty($new_translation) || empty($lang_code)) {
        echo json_encode(['status' => 'error', 'message' => '번역 내용을 입력해주세요.']);
        exit;
    }

    try {
        // 기존 JSON 데이터를 가져온 후 특정 언어 코드 값만 부분 변경하여 다시 인코딩
        $stmt = $pdo->prepare("SELECT translations FROM shop_item_categories WHERE id = ? AND shop_id = ?");
        $stmt->execute([$cat_id, $shop_id]);
        $existing_json = $stmt->fetchColumn();

        $translations = $existing_json ? json_decode($existing_json, true) : [];
        $translations[$lang_code] = $new_translation;
        $json_translations = json_encode($translations, JSON_UNESCAPED_UNICODE);

        $pdo->prepare("UPDATE shop_item_categories SET translations = ? WHERE id = ? AND shop_id = ?")->execute([$json_translations, $cat_id, $shop_id]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['del_cat'])) {
    $cat_id = (int)$_GET['del_cat'];
    $pdo->prepare("DELETE FROM shop_item_categories WHERE id = ? AND shop_id = ?")->execute([$cat_id, $shop_id]);
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=cat_deleted';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category_order'])) {
    $order_data = json_decode($_POST['order_data'], true);
    if (is_array($order_data)) {
        $stmt = $pdo->prepare("UPDATE shop_item_categories SET sort_order = ? WHERE id = ? AND shop_id = ?");
        foreach ($order_data as $index => $id) {
            $stmt->execute([$index + 1, $id, $shop_id]);
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// ---------------------------------------------------------
// --- [B] 홍보 전단지(Board) 삭제 로직 ---
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_board_img') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $board_id = (int)$_POST['board_id'];
    try {
        $stmt = $pdo->prepare("SELECT board_img_path FROM shop_item_boards WHERE id = ? AND shop_id = ?");
        $stmt->execute([$board_id, $shop_id]);
        $file_info = $stmt->fetch();
        if ($file_info && !empty($file_info['board_img_path'])) {
            deletePhysicalFiles($file_info['board_img_path']);
        }
        $pdo->prepare("DELETE FROM shop_item_boards WHERE id = ? AND shop_id = ?")->execute([$board_id, $shop_id]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flyer_order'])) {
    $board_order = json_decode($_POST['flyer_order'], true);
    if (is_array($board_order)) {
        try {
            $pdo->exec("ALTER TABLE shop_item_boards ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
        } catch (Exception $e) {
        }
        $stmt_update_order = $pdo->prepare("UPDATE shop_item_boards SET sort_order = ? WHERE shop_id = ? AND board_img_path = ?");
        foreach ($board_order as $index => $path) {
            $stmt_update_order->execute([$index + 1, $shop_id, $path]);
        }
    }
    if (isset($_POST['ajax_update'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => '전단지 이미지가 성공적으로 저장되었습니다.']);
        exit;
    }
}

// ---------------------------------------------------------
// --- [C] 개별 서비스(Item) 관리 로직 ---
// ---------------------------------------------------------
if (isset($_GET['del_item'])) {
    $item_id = (int)$_GET['del_item'];
    $stmt = $pdo->prepare("SELECT item_img FROM shop_items WHERE id = ? AND shop_id = ?");
    $stmt->execute([$item_id, $shop_id]);
    if ($file_info = $stmt->fetch()) {
        if (!empty($file_info['item_img'])) deletePhysicalFiles($file_info['item_img']);
    }
    $pdo->prepare("DELETE FROM shop_items WHERE id = ? AND shop_id = ?")->execute([$item_id, $shop_id]);
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=item_deleted';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
    $is_best = isset($_POST['is_best']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_soldout = isset($_POST['is_soldout']) ? 1 : 0;
    $is_hide = isset($_POST['is_hide']) ? 1 : 0;
    $cat_id = !empty($_POST['cat_id']) ? (int)$_POST['cat_id'] : null;
    $trade_type = $_POST['trade_type'] ?? '방문 서비스';

    $price_type = $_POST['price_type'] ?? 'number';
    if ($price_type === 'text') {
        $item_price = 0;
        $discount_price = 0;
        $discount_rate = 0;
        $price_description = trim($_POST['price_description'] ?? '');
    } else {
        $item_price = !empty($_POST['item_price']) ? (int)$_POST['item_price'] : 0;
        $discount_price = !empty($_POST['item_discount_price']) ? (int)$_POST['item_discount_price'] : 0;
        $discount_rate = !empty($_POST['item_discount_rate']) ? (int)$_POST['item_discount_rate'] : 0;
        $price_description = null;
    }

    // [신규] POST 데이터를 스캔하여 다국어 데이터 JSON 동적 처리
    $translations = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'item_name_') === 0 && is_string($value)) {
            $langCode = str_replace('item_name_', '', $key);
            $info_val = isset($_POST['item_info_' . $langCode]) && is_string($_POST['item_info_' . $langCode]) ? $_POST['item_info_' . $langCode] : '';
            if (!empty(trim($value)) || !empty(trim($info_val))) {
                $translations[$langCode] = [
                    'item_name' => trim($value),
                    'item_info' => trim($info_val)
                ];
            }
        }
    }
    $json_translations = !empty($translations) ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null;

    $sql = "INSERT INTO shop_items (shop_id, cat_id, trade_type, item_name, item_price, item_discount_price, item_discount_rate, item_info, price_description, item_img, item_youtube_url, is_best, is_new, is_soldout, is_hide, translations) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([
        $shop_id,
        $cat_id,
        $trade_type,
        $_POST['item_name'],
            $item_price,
        $discount_price,
        $discount_rate,
        $_POST['item_info'] ?? '',
        $price_description,
        $_POST['item_img_path'] ?? '',
        $_POST['item_youtube_url'] ?? '',
        $is_best,
        $is_new,
        $is_soldout,
        $is_hide,
        $json_translations
    ]);

    if (isset($_POST['ajax_update'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => '신규 서비스가 등록되었습니다.']);
        exit;
    }
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=item_added';</script>";
    exit;
    } catch (Throwable $e) {
        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => '서비스 등록 중 오류가 발생했습니다: ' . $e->getMessage()]);
            exit;
        }
        echo "<script>alert('오류 발생: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    try {
    $item_id = (int)$_POST['item_id'];
    $is_best = isset($_POST['is_best']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_soldout = isset($_POST['is_soldout']) ? 1 : 0;
    $is_hide = isset($_POST['is_hide']) ? 1 : 0;
    $cat_id = !empty($_POST['cat_id']) ? (int)$_POST['cat_id'] : null;
    $trade_type = $_POST['trade_type'] ?? '방문 서비스';

    $price_type = $_POST['price_type'] ?? 'number';
    if ($price_type === 'text') {
        $item_price = 0;
        $discount_price = 0;
        $discount_rate = 0;
        $price_description = trim($_POST['price_description'] ?? '');
    } else {
        $item_price = !empty($_POST['item_price']) ? (int)$_POST['item_price'] : 0;
        $discount_price = !empty($_POST['item_discount_price']) ? (int)$_POST['item_discount_price'] : 0;
        $discount_rate = !empty($_POST['item_discount_rate']) ? (int)$_POST['item_discount_rate'] : 0;
        $price_description = null;
    }

    // [신규] POST 데이터를 스캔하여 다국어 데이터 JSON 동적 처리
    $translations = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'item_name_') === 0 && is_string($value)) {
            $langCode = str_replace('item_name_', '', $key);
            $info_val = isset($_POST['item_info_' . $langCode]) && is_string($_POST['item_info_' . $langCode]) ? $_POST['item_info_' . $langCode] : '';
            if (!empty(trim($value)) || !empty(trim($info_val))) {
                $translations[$langCode] = [
                    'item_name' => trim($value),
                    'item_info' => trim($info_val)
                ];
            }
        }
    }
    $json_translations = !empty($translations) ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null;

    $new_item_img = $_POST['item_img_path'] ?? '';
    $old_item_img = $_POST['old_img_path'] ?? '';

    if (!empty($new_item_img) && $new_item_img !== $old_item_img) {
        $old_paths = [];
        $new_paths = [];
        if (!empty($old_item_img)) {
            $old_decoded = json_decode($old_item_img, true);
            $old_paths = is_array($old_decoded) ? $old_decoded : [$old_item_img];
        }
        if (!empty($new_item_img)) {
            $new_decoded = json_decode($new_item_img, true);
            $new_paths = is_array($new_decoded) ? $new_decoded : [$new_item_img];
        }
        $deleted_paths = [];
        foreach ($old_paths as $p) {
            if (!empty($p) && !in_array($p, $new_paths)) {
                $deleted_paths[] = $p;
            }
        }
        if (!empty($deleted_paths)) deletePhysicalFiles($deleted_paths);
        $old_item_img = $new_item_img;
    }

    $sql = "UPDATE shop_items SET cat_id = ?, trade_type = ?, item_name = ?, item_price = ?, item_discount_price = ?, item_discount_rate = ?, item_info = ?, price_description = ?, item_img = ?, item_youtube_url = ?, is_best = ?, is_new = ?, is_soldout = ?, is_hide = ?, translations = ? WHERE id = ? AND shop_id = ?";
    $pdo->prepare($sql)->execute([
        $cat_id,
        $trade_type,
        $_POST['item_name'],
            $item_price,
        $discount_price,
        $discount_rate,
        $_POST['item_info'] ?? '',
        $price_description,
        $old_item_img,
        $_POST['item_youtube_url'] ?? '',
        $is_best,
        $is_new,
        $is_soldout,
        $is_hide,
        $json_translations,
        $item_id,
        $shop_id
    ]);

    if (isset($_POST['ajax_update'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => '서비스 정보가 수정되었습니다.']);
        exit;
    }
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=item_updated';</script>";
    exit;
    } catch (Throwable $e) {
        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => '서비스 수정 중 오류가 발생했습니다: ' . $e->getMessage()]);
            exit;
        }
        echo "<script>alert('오류 발생: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_order'])) {
    $order_data = json_decode($_POST['order_data'], true);
    if (is_array($order_data)) {
        $stmt = $pdo->prepare("UPDATE shop_items SET sort_order = ? WHERE id = ? AND shop_id = ?");
        foreach ($order_data as $index => $id) {
            $stmt->execute([$index + 1, $id, $shop_id]);
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ui_labels_bulk'])) {
    $stmt = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
    $stmt->execute([$shop_id]);
    $current_ui = json_decode($stmt->fetchColumn() ?: '{}', true);

    // label_ 및 order_ 접두사가 있는 모든 POST 데이터를 동적으로 저장 (다국어 키 대응)
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'label_') === 0 || strpos($key, 'order_') === 0) {
            $val = trim($val);
            if (empty($val)) unset($current_ui[$key]);
            else $current_ui[$key] = $val;
        }
    }
    $pdo->prepare("UPDATE shops SET ui_settings = ? WHERE id = ?")->execute([json_encode($current_ui, JSON_UNESCAPED_UNICODE), $shop_id]);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
    echo "<script>location.href='manage_shop.php?pg={$redirect_pg}&msg=label_updated';</script>";
    exit;
}
