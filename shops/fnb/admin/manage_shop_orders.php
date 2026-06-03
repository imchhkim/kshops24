<?php

/**
 * KShops24 F&B 주문 관리 모듈 (manage_shop_orders.php)
 * [역할] 접수된 주문 목록 출력, 상세 내역 확인, 주문 상태 변경
 */

// 부모 페이지(manage_shop.php)에서 정의된 $shop_id가 없으면 실행 중단
if (!isset($shop_id)) exit;

/**
 * 주문 행 렌더링 헬퍼 함수
 */
if (!function_exists('renderOrderRow')) {
    function renderOrderRow($order, $pdo)
    {
        $stmt_items = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");

        // JS 모달 전달용으로 포맷팅된 번호 추가
        if (function_exists('formatPHPhone')) {
            $order['customer_phone_formatted'] = formatPHPhone($order['customer_phone']);
        } else {
            $order['customer_phone_formatted'] = $order['customer_phone'];
        }

        $stmt_items->execute([$order['id']]);
        $items = $stmt_items->fetchAll();

        $summary_parts = [];
        $total_qty = 0;
        foreach ($items as $item) {
            $summary_parts[] = $item['item_name'] . " x " . $item['quantity'];
            $total_qty += (int)$item['quantity'];
        }
        $item_summary = !empty($summary_parts) ? implode(", ", $summary_parts) : "내역 없음";
?>
        <div id="order-row-<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>" class="list-group-item p-3 border-bottom <?php echo ($order['status'] === 'pending') ? 'list-group-item-warning border-start border-5 border-danger' : ''; ?>">
            <div class="row g-2 align-items-center">
                <!-- 1. 시간/주문번호 및 고객 연락처 정보 -->
                <div class="col-12 col-lg-2">
                    <div class="fw-bold text-dark mb-1 d-inline-block d-lg-block me-2">
                        <?php echo date('m-d H:i:s', strtotime($order['created_at'])); ?>
                        <?php if ($order['status'] === 'pending'): ?><span class="badge bg-danger ms-1 fw-bold new-order-badge">NEW</span><?php endif; ?>
                    </div>
                    <div class="small text-muted d-inline-block d-lg-block mb-2"><?php echo $order['order_no']; ?></div>
                    <?php if (!empty($order['kakao_nickname'])): ?>
                        <div class="fw-bold mb-1" style="color: #3A1D1D;"><i class="bi bi-chat-fill me-1" style="color: #F8D300; -webkit-text-stroke: 1px #3A1D1D;"></i><?php echo htmlspecialchars($order['kakao_nickname']); ?></div>
                    <?php endif; ?>
                    <div class="fw-bold mb-1">
                        <a href="#" onclick="searchCustomerOrders('<?php echo htmlspecialchars($order['customer_phone']); ?>'); return false;" class="text-decoration-none text-dark" title="이 고객의 모든 주문내역 보기">
                            <i class="bi bi-telephone me-1 text-primary"></i><?php echo htmlspecialchars($order['customer_phone_formatted']); ?>
                        </a>
                    </div>

                    <!-- 주문정보 복사 버튼 -->
                    <?php
                    $customer_name = !empty($order['kakao_nickname']) ? $order['kakao_nickname'] : '고객';
                    $phone = !empty($order['customer_phone_formatted']) ? $order['customer_phone_formatted'] : $order['customer_phone'];
                    $addr = $order['customer_address'] . (!empty($order['customer_landmark']) ? " (" . $order['customer_landmark'] . ")" : "");
                    $pin_url = (isset($order['customer_lat'], $order['customer_lng']) && $order['customer_lat'] !== '' && $order['customer_lng'] !== '') ? "https://maps.google.com/?q={$order['customer_lat']},{$order['customer_lng']}" : "없음";
                    $pay_method = ($order['payment_method'] ?? 'cash') === 'cash' ? '현금(Cash)' : '기타(GCash 등)';
                    $pay_amt = "₱ " . number_format($order['total_price']);
                    $pay_detail = !empty($order['payment_detail']) ? $order['payment_detail'] : '없음';

                    if (($order['order_type'] ?? 'delivery') === 'pickup') {
                        $pickup_time = $order['pickup_time'] ?? '미지정';
                        $copy_text = "[매장픽업 정보]\n👤 고객명: {$customer_name}\n📞 연락처: {$phone}\n🕒 방문시간: {$pickup_time}\n💰 결제: {$pay_method} / {$pay_amt}\n📝 요청사항: {$pay_detail}";
                    } else {
                        $copy_text = "[배달 정보]\n👤 고객명: {$customer_name}\n📞 연락처: {$phone}\n🏠 주소: {$addr}\n📍 핀위치: {$pin_url}\n💰 결제: {$pay_method} / {$pay_amt}\n📝 요청사항: {$pay_detail}";
                    }
                    ?>
                    <div class="mt-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 shadow-sm" style="font-size: 0.75rem;" onclick="copyDeliveryInfo(this)" data-clipboard-text="<?php echo htmlspecialchars($copy_text, ENT_QUOTES); ?>">
                            <i class="bi bi-clipboard me-1"></i>주문정보 복사
                        </button>
                    </div>
                </div>
                <!-- 2. 주문 정보 통합 영역 (주소, 핀위치, 메뉴 요약, 결제/상태) -->
                <div class="col-12 col-lg-10 mt-3 mt-lg-0">
                    <!-- 첫번째 줄: 고객 주소 및 랜드마크 또는 픽업 정보 -->
                    <?php if (($order['order_type'] ?? 'delivery') === 'pickup'): ?>
                        <div class="mb-2 w-100 text-break bg-primary bg-opacity-10 p-2 rounded-3 border border-primary border-opacity-25 d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6 px-3 py-2"><i class="bi bi-shop me-1"></i> 매장픽업</span>
                            <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($order['pickup_time'] ?? '시간 미지정'); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="mb-1 w-100 text-break">
                            <span class="small fw-bold text-dark"><i class="bi bi-geo-alt text-primary me-1"></i>고객 주소 : </span>
                            <span class="small fw-bold text-dark">
                                <?php echo htmlspecialchars($order['customer_address']); ?>
                                <?php if (!empty($order['customer_landmark'])): ?>
                                    <span class="text-primary fw-bold ms-1">(<?php echo htmlspecialchars($order['customer_landmark']); ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- 두번째 줄: 구글 핀위치 URL 및 지도 모달 연결 -->
                        <?php if (isset($order['customer_lat'], $order['customer_lng']) && $order['customer_lat'] !== '' && $order['customer_lng'] !== ''): ?>
                            <div class="mb-2 w-100 text-break">
                                <span class="small fw-bold text-dark"><i class="bi bi-pin-map-fill text-danger me-1"></i>구글 핀위치 : </span>
                                <a href="#" onclick="openAdminMapModal(<?php echo $order['customer_lat']; ?>, <?php echo $order['customer_lng']; ?>); return false;" class="small text-decoration-none text-danger" title="지도 모달 띄우기">
                                    https://maps.google.com/?q=<?php echo $order['customer_lat']; ?>,<?php echo $order['customer_lng']; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- 세번째 줄: 주문 내역 버튼 (총 수량 및 금액 포함) -->
                    <div id="summary-container-<?php echo $order['id']; ?>" class="mb-2">
                        <button type="button" class="btn btn-sm btn-light border fw-bold w-100 text-start py-2 d-flex justify-content-between align-items-center shadow-sm" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($items), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($order), ENT_QUOTES); ?>)">
                            <div class="text-truncate me-2">
                                <i class="bi bi-search me-2 text-primary"></i><?php echo htmlspecialchars($item_summary); ?>
                                <span class="text-primary ms-1">(총 <?php echo $total_qty; ?>개)</span>
                            </div>
                            <div class="fw-bold text-primary fs-6 text-nowrap">₱ <?php echo number_format($order['total_price']); ?></div>
                        </button>
                        <?php if ($order['status'] === 'cancelled' && !empty($order['cancel_reason'])): ?>
                            <div class="mt-2 text-danger small fw-bold cancel-reason-display"><i class="bi bi-exclamation-triangle-fill me-1"></i>취소사유: <?php echo htmlspecialchars($order['cancel_reason']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- 하단: 결제 방식 및 상태 버튼 -->
                    <div class="row mt-2 align-items-center g-2">
                        <div class="col-12 col-lg-5">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="small fw-bold text-dark">
                                    <i class="bi bi-wallet2 me-1 text-primary"></i>결제: <?php echo ($order['payment_method'] ?? 'cash') === 'cash' ? '현금(Cash)' : '기타(GCash 등)'; ?>
                                </div>
                                <!-- 영수증 인쇄 버튼 -->
                                <!-- 하드웨어 최적화: 롤 프린터(영수증 프린터) 지원
                                    필리핀 소상공인들은 일반 A4 프린터보다 유지비가 저렴한 58mm 또는 80mm 열전사 롤 프린터(Thermal Receipt Printer)를 가장 많이 사용합니다.

                                    @media print 전용 CSS 설계:
                                    A4 레이아웃이 아닌, 롤 프린터 폭(width: 58mm 또는 80mm)에 딱 맞춘 전용 인쇄용 CSS를 작성해야 합니다. 좌우 여백을 최소화하고 글자 크기를 9pt ~ 11pt 내외로 조절하여 글자가 깨지거나 잘리지 않게 만듭니다.

                                    웹 브라우저 기본 폰트 사용: 프린터 자체 폰트나 시스템 기본 폰트(Sans-serif계열)를 사용하여 인쇄 속도를 극대화하고 폰트가 밀리는 현상을 방지합니다. -->
                                <button type="button" class="btn btn-sm btn-outline-dark py-1 px-2" onclick="prepareAndPrint(<?php echo htmlspecialchars(json_encode($items), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($order), ENT_QUOTES); ?>)" title="영수증 인쇄"><i class="bi bi-printer"></i> 배달 영수증 인쇄</button>
                            </div>
                            <?php if (!empty($order['payment_detail'])): ?>
                                <div class="text-muted fw-normal mt-1 bg-light p-1 rounded border small" style="font-size: 0.75rem; word-break: break-all;">
                                    <i class="bi bi-chat-left-dots me-1"></i><?php echo htmlspecialchars($order['payment_detail']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-lg-7" id="status-container-<?php echo $order['id']; ?>">
                            <?php if ($order['status'] === 'completed'): ?>
                                <div class="text-end text-lg-center">
                                    <span class="badge bg-success px-4 py-2 fw-bold fs-6"><i class="bi bi-check-circle me-1"></i>주문완료</span>
                                </div>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <div class="text-end text-lg-center">
                                    <span class="badge bg-danger px-4 py-2 fw-bold fs-6"><i class="bi bi-x-circle me-1"></i>주문취소</span>
                                    <?php if (!empty($order['cancel_reason'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.75rem; word-break: keep-all;"><?php echo htmlspecialchars($order['cancel_reason']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1 w-100" role="group">
                                    <input type="radio" class="btn-check" name="status_<?php echo $order['id']; ?>" id="st_p_<?php echo $order['id']; ?>" value="pending" <?php echo ($order['status'] === 'pending') ? 'checked' : ''; ?> onchange="updateOrderStatus(<?php echo $order['id']; ?>, 'pending')">
                                    <label class="btn btn-outline-warning btn-sm flex-fill py-2 fw-bold" for="st_p_<?php echo $order['id']; ?>">접수</label>

                                    <input type="radio" class="btn-check" name="status_<?php echo $order['id']; ?>" id="st_c_<?php echo $order['id']; ?>" value="cooking" <?php echo $order['status'] === 'cooking' ? 'checked' : ''; ?> onchange="updateOrderStatus(<?php echo $order['id']; ?>, 'cooking')">
                                    <label class="btn btn-outline-primary btn-sm flex-fill py-2 fw-bold" for="st_c_<?php echo $order['id']; ?>">요리</label>

                                    <?php if (($order['order_type'] ?? 'delivery') !== 'pickup'): ?>
                                        <input type="radio" class="btn-check" name="status_<?php echo $order['id']; ?>" id="st_d_<?php echo $order['id']; ?>" value="delivery" <?php echo $order['status'] === 'delivery' ? 'checked' : ''; ?> onchange="updateOrderStatus(<?php echo $order['id']; ?>, 'delivery')">
                                        <label class="btn btn-outline-info btn-sm flex-fill py-2 fw-bold" for="st_d_<?php echo $order['id']; ?>">배달</label>
                                    <?php endif; ?>

                                    <input type="radio" class="btn-check" name="status_<?php echo $order['id']; ?>" id="st_f_<?php echo $order['id']; ?>" value="completed" <?php echo $order['status'] === 'completed' ? 'checked' : ''; ?> onchange="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')">
                                    <label class="btn btn-outline-success btn-sm flex-fill py-2 fw-bold" for="st_f_<?php echo $order['id']; ?>">완료</label>

                                    <input type="radio" class="btn-check" name="status_<?php echo $order['id']; ?>" id="st_x_<?php echo $order['id']; ?>" value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'checked' : ''; ?> onchange="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')">
                                    <label class="btn btn-outline-danger btn-sm flex-fill py-2 fw-bold" for="st_x_<?php echo $order['id']; ?>">취소</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}

// ---------------------------------------------------------
// 0. 신규 주문 폴링 처리 (AJAX)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'poll_new_orders') {
    // [수정] 상위 파일(manage_shop.php)에서 이미 출력된 HTML이 섞이지 않도록 모든 버퍼를 닫고 완전히 비웁니다.
    while (ob_get_level()) {
        ob_end_clean();
    }

    $last_id = (int)($_GET['last_id'] ?? 0);
    $stmt_poll = $pdo->prepare("SELECT MAX(id) FROM shop_orders WHERE shop_id = ?");
    $stmt_poll->execute([$shop_id]);
    $max_id = (int)$stmt_poll->fetchColumn();

    $new_html = '';
    $new_count = 0;
    if ($max_id > 0 && $max_id > $last_id) {
        $stmt_new = $pdo->prepare("
            SELECT o.*, 
                   (SELECT c.nickname FROM platform_customers c 
                    WHERE REPLACE(c.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci = o.customer_phone COLLATE utf8mb4_unicode_ci 
                    ORDER BY c.updated_at DESC, c.id DESC LIMIT 1) AS kakao_nickname 
            FROM shop_orders o 
            WHERE o.shop_id = ? AND o.id > ? 
            ORDER BY o.id DESC");
        $stmt_new->execute([$shop_id, $last_id]);
        $new_orders = $stmt_new->fetchAll();
        $new_count = count($new_orders);
        ob_start();
        foreach ($new_orders as $order) renderOrderRow($order, $pdo);
        $new_html = ob_get_clean();
    }

    echo "|||JSON_START|||";
    echo json_encode(['status' => 'success', 'latest_id' => max($max_id, $last_id), 'html' => $new_html, 'count' => $new_count]);
    echo "|||JSON_END|||";
    exit;
}

// ---------------------------------------------------------
// 0-1. 고객 주문 내역 조회 (AJAX 페이징)
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_customer_orders') {
    while (ob_get_level()) {
        ob_end_clean();
    }

    $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    if (empty($phone)) {
        echo "|||JSON_START|||";
        echo json_encode(['status' => 'error', 'message' => '전화번호를 입력해주세요.']);
        echo "|||JSON_END|||";
        exit;
    }

    // 전체 건수 조회
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM shop_orders WHERE shop_id = ? AND REPLACE(customer_phone, '-', '') = ?");
    $stmt_count->execute([$shop_id, $phone]);
    $total_count = $stmt_count->fetchColumn();
    $total_pages = ceil($total_count / $limit);

    // [추가] 요약 정보 조회 (주문완료/취소 건수 및 최근 주문 주소)
    $completed_count = 0;
    $cancelled_count = 0;
    $latest_address = '주소 정보 없음';
    if ($total_count > 0) {
        $stmt_stats = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM shop_orders WHERE shop_id = ? AND REPLACE(customer_phone, '-', '') = ? GROUP BY status");
        $stmt_stats->execute([$shop_id, $phone]);
        $stats = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
        $completed_count = $stats['completed'] ?? 0;
        $cancelled_count = $stats['cancelled'] ?? 0;

        $stmt_addr = $pdo->prepare("SELECT customer_address FROM shop_orders WHERE shop_id = ? AND REPLACE(customer_phone, '-', '') = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_addr->execute([$shop_id, $phone]);
        $latest_address = $stmt_addr->fetchColumn() ?: '주소 정보 없음';
    }

    // 페이징 데이터 조회 (시간 역순)
    $stmt_orders = $pdo->prepare("
        SELECT o.*, 
               (SELECT c.nickname FROM platform_customers c 
                WHERE REPLACE(c.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci = o.customer_phone COLLATE utf8mb4_unicode_ci 
                ORDER BY c.updated_at DESC, c.id DESC LIMIT 1) AS kakao_nickname 
        FROM shop_orders o 
        WHERE o.shop_id = ? AND REPLACE(o.customer_phone, '-', '') = ?
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt_orders->bindValue(1, $shop_id, PDO::PARAM_INT);
    $stmt_orders->bindValue(2, $phone, PDO::PARAM_STR);
    $stmt_orders->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt_orders->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt_orders->execute();
    $customer_orders = $stmt_orders->fetchAll();

    ob_start();
    if (count($customer_orders) > 0) {
        foreach ($customer_orders as $order) renderOrderRow($order, $pdo);
    } else {
        echo '<div class="list-group-item text-center py-5 text-muted no-data border-bottom">해당 고객의 주문 내역이 없습니다.</div>';
    }
    $html = ob_get_clean();

    echo "|||JSON_START|||";
    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'total_count' => $total_count,
        'summary' => ['completed_count' => $completed_count, 'cancelled_count' => $cancelled_count, 'latest_address' => $latest_address]
    ]);
    echo "|||JSON_END|||";
    exit;
}

// ---------------------------------------------------------
// 1. 주문 상태 변경 처리 (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $cancel_reason = $_POST['cancel_reason'] ?? ''; // 모달에서 전달된 취소 사유

    // 취소 사유(cancel_reason)를 DB에 영구 저장 
    $stmt = $pdo->prepare("UPDATE shop_orders SET status = ?, cancel_reason = ? WHERE id = ? AND shop_id = ?");
    $stmt->execute([$new_status, $cancel_reason, $order_id, $shop_id]);

    // AJAX 요청인 경우 JSON 응답 후 종료
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // 이전까지 출력된 HTML(헤더 등)이 있다면 모두 제거하여 순수 JSON만 반환
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo "|||JSON_START|||";
        echo json_encode(['status' => 'success']);
        echo "|||JSON_END|||";
        exit;
    }

    echo "<script>location.href='manage_shop.php?pg=manage_shop_orders&msg=status_updated';</script>";
    exit;
}

// ---------------------------------------------------------
// 2. 데이터 로딩
// ---------------------------------------------------------
$search_date = $_GET['search_date'] ?? date('Y-m-d');
// 주문 데이터와 함께, 전화번호를 기준으로 가입된 고객의 카카오 닉네임을 조인해서 가져옵니다.
$query = "SELECT o.*, 
                 (SELECT c.nickname FROM platform_customers c 
                  WHERE REPLACE(c.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci = o.customer_phone COLLATE utf8mb4_unicode_ci 
                  ORDER BY c.updated_at DESC, c.id DESC LIMIT 1) AS kakao_nickname 
          FROM shop_orders o 
          WHERE o.shop_id = ?";
$params = [$shop_id];

if ($search_date) {
    $query .= " AND DATE(o.created_at) = ?";
    $params[] = $search_date;
}

$query .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// 선택된 날짜의 상태별 건수 계산
$stats = ['pending' => 0, 'cooking' => 0, 'delivery' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($orders as $ord) {
    $s = $ord['status'];
    if (array_key_exists($s, $stats)) {
        $stats[$s]++;
    }
}

// 탭별 전체 합계 계산
$active_total = $stats['pending'] + $stats['cooking'] + $stats['delivery'];
$completed_total = $stats['completed'];
$cancelled_total = $stats['cancelled'];

// 신규 주문 감지용: 현재 가장 높은 주문 ID 조회 (페이지 최초 진입 기준)
$stmt_max = $pdo->prepare("SELECT MAX(id) FROM shop_orders WHERE shop_id = ?");
$stmt_max->execute([$shop_id]);
$current_max_order_id = (int)$stmt_max->fetchColumn();

?>

<style>
    @keyframes flashWarning {

        0%,
        100% {
            background-color: transparent;
        }

        50% {
            background-color: rgba(255, 193, 7, 0.4);
        }

        /* 노란색 반투명 깜빡임 */
    }

    .flash-bg {
        animation: flashWarning 0.5s ease-in-out 3;
    }

    /* 방금 들어온 신규 항목 강조 애니메이션 */
    @keyframes highlightNewItem {

        0%,
        100% {
            background-color: inherit;
        }

        50% {
            background-color: rgba(220, 53, 69, 0.2) !important;
        }

        /* 붉은색 반투명 강조 */
    }

    .new-item-highlight {
        animation: highlightNewItem 0.8s ease-in-out 4;
        /* 0.8초 주기로 4번 깜빡임 */
    }

    /* 신규 주문 뱃지 깜빡임 애니메이션 */
    @keyframes pulseBadge {
        0% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.6;
            transform: scale(1.1);
        }

        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .new-order-badge {
        animation: pulseBadge 1.5s infinite;
    }

    /* 인쇄 전용 CSS (중복 정의 방지 및 clean 패턴) */
    @media print {
        /* 인쇄할 때 화면의 네비게이션, 버튼 등 불필요한 요소 숨김 처리 */
        body * {
            visibility: hidden;
        }
        #print_section, #print_section * {
            visibility: visible;
            color: #000 !important; /* 흑백 프린터 최적화 */
            font-family: sans-serif;
        }
        #print_section {
            position: absolute;
            left: 0;
            top: 0;
            width: 80mm; /* 롤 프린터 폭 (80mm) */
            margin: 0;
            padding: 0;
        }
        /* 프린트 다이얼로그에서 자체적인 여백 없애기 */
        @page { margin: 0; }
    }
</style>

<div class="container-fluid p-0">

    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('주문/배달 관리', 'bi-cart-check'); ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('주문 상태가 변경되었습니다.', 'success');
            });
        </script>
    <?php endif; ?>

    <div class="card p-4 border-0 shadow-sm">
        <div class="box-responsive-between mb-4">
            <?php echo renderSectionHeader('주문/배달 관리', 'bi bi-cart-check'); ?>
            <form method="GET" class="d-flex align-items-center flex-wrap justify-content-center justify-content-md-start gap-2 ms-md-auto">
                <input type="hidden" name="pg" value="manage_shop_orders">
                <input type="date" name="search_date" id="search_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_date); ?>" style="width: 150px;">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">조회</button>
                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" onclick="setToday()">오늘 주문 보기</button>

                <div class="d-flex flex-wrap align-items-center gap-2 gap-md-4 ms-0 ms-md-2 ps-0 ps-md-4 border-0 border-md-start fs-6 fs-md-5 mt-2 mt-md-0 w-100 w-md-auto">
                    <span class="text-nowrap">주문접수 <strong id="stat-pending" class="text-warning"><?php echo $stats['pending']; ?></strong></span>
                    <span class="text-nowrap">요리중 <strong id="stat-cooking" class="text-primary"><?php echo $stats['cooking']; ?></strong></span>
                    <span class="text-nowrap">배달중 <strong id="stat-delivery" class="text-info"><?php echo $stats['delivery']; ?></strong></span>
                    <span class="text-nowrap">주문완료 <strong id="stat-completed" class="text-success"><?php echo $stats['completed']; ?></strong></span>
                    <span class="text-nowrap">주문취소 <strong id="stat-cancelled" class="text-danger"><?php echo $stats['cancelled']; ?></strong></span>
                </div>
            </form>
        </div>

        <!-- 탭 메뉴 -->
        <ul class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto" id="orderTabs" role="tablist" style="scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold text-nowrap" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab">주문/배달 관리 (<span id="tab-count-active"><?php echo $active_total; ?></span>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-nowrap" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-pane" type="button" role="tab">배달/픽업 완료 (<span id="tab-count-completed"><?php echo $completed_total; ?></span>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-nowrap" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled-pane" type="button" role="tab">주문취소 (<span id="tab-count-cancelled"><?php echo $cancelled_total; ?></span>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-nowrap" id="customer-orders-tab" data-bs-toggle="tab" data-bs-target="#customer-orders-pane" type="button" role="tab">고객주문관리</button>
            </li>
        </ul>

        <div class="tab-content" id="orderTabsContent">
            <!-- 주문/배달 관리 탭 -->
            <div class="tab-pane fade show active" id="active-pane" role="tabpanel">
                <div class="list-group list-group-flush border-top" id="active-orders-body">
                    <?php
                    $active_count = 0;
                    foreach ($orders as $order):
                        if ($order['status'] === 'completed' || $order['status'] === 'cancelled') continue;
                        $active_count++;
                        renderOrderRow($order, $pdo);
                    endforeach;
                    if ($active_count === 0): ?>
                        <div class="list-group-item text-center py-5 text-muted no-data border-bottom">진행 중인 주문이 없습니다.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 주문/픽업 완료 탭 -->
            <div class="tab-pane fade" id="completed-pane" role="tabpanel">
                <div class="list-group list-group-flush border-top" id="completed-orders-body">
                    <?php
                    $completed_count = 0;
                    foreach ($orders as $order):
                        if ($order['status'] !== 'completed') continue;
                        $completed_count++;
                        renderOrderRow($order, $pdo);
                    endforeach;
                    if ($completed_count === 0): ?>
                        <div class="list-group-item text-center py-5 text-muted no-data border-bottom">완료된 주문이 없습니다.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 주문취소 탭 -->
            <div class="tab-pane fade" id="cancelled-pane" role="tabpanel">
                <div class="list-group list-group-flush border-top" id="cancelled-orders-body">
                    <?php
                    $cancelled_count = 0;
                    foreach ($orders as $order):
                        if ($order['status'] !== 'cancelled') continue;
                        $cancelled_count++;
                        renderOrderRow($order, $pdo);
                    endforeach;
                    if ($cancelled_count === 0): ?>
                        <div class="list-group-item text-center py-5 text-muted no-data border-bottom">취소된 주문이 없습니다.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 고객주문관리 탭 -->
            <div class="tab-pane fade" id="customer-orders-pane" role="tabpanel">
                <div class="p-3 bg-light border-top border-bottom">
                    <form id="customer-search-form" onsubmit="event.preventDefault(); loadCustomerOrders(1);">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-telephone text-primary"></i></span>
                            <input type="tel" id="search_customer_phone" class="form-control border-start-0" placeholder="고객 전화번호 입력 (예: 09XX...)" value="">
                            <button type="submit" class="btn btn-primary fw-bold px-4">조회</button>
                        </div>
                    </form>
                </div>

                <!-- [추가] 고객 요약 정보 영역 -->
                <div id="customer-summary-container" class="bg-white p-3 border-bottom d-none"></div>

                <div id="customer-orders-results" class="list-group list-group-flush border-bottom">
                    <div class="list-group-item text-center py-5 text-muted no-data">고객 전화번호를 검색해주세요.</div>
                </div>
                <div id="customer-orders-pagination" class="p-3 d-flex justify-content-center"></div>
            </div>
        </div>
    </div>
</div>

<!-- 주문 상세 내역 모달 -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i>주문 상세 내역</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="modal-order-info" class="mb-4 p-3 bg-light rounded-3 small"></div>
                <div id="modal-items-list"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- [추가] 주문 취소 사유 모달 -->
<div class="modal fade" id="cancelReasonModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>주문 취소</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3 text-dark fw-bold">주문을 정말로 취소하시겠습니까?</p>
                <div class="mb-3">
                    <label class="form-label small text-muted">취소 사유 (고객에게 안내될 수 있습니다)</label>
                    <textarea id="cancel_reason_input" class="form-control" rows="3" placeholder="예: 재료 소진, 배달 불가 지역 등"></textarea>
                </div>
                <input type="hidden" id="cancel_target_order_id">
            </div>
            <div class="modal-footer border-0 d-flex gap-2">
                <button type="button" class="btn btn-light flex-fill rounded-pill fw-bold" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger flex-fill rounded-pill fw-bold" onclick="executeCancelOrder()">취소 확정</button>
            </div>
        </div>
    </div>
</div>

<!-- [신규 주문 알림 토스트: X 누를 때까지 사라지지 않음] -->
<div class="toast-container position-fixed bottom-0 end-0 p-4" style="z-index: 1100;">
    <div id="newOrderToast" class="toast align-items-center text-white bg-danger border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="toast-header bg-danger text-white border-0">
            <strong class="me-auto fs-6"><i class="bi bi-bell-fill me-1 text-warning"></i> 새 주문 알림</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body fw-bold fs-6">
            새로운 주문이 목록에 추가되었습니다!
        </div>
    </div>
</div>

<!-- [추가] 관리자용 배달 위치 확인 모달 -->
<div class="modal fade" id="adminLocationMapModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt-fill me-2"></i>배달 위치 확인</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="admin-delivery-map-container" style="width: 100%; height: 60vh; min-height: 350px; background-color: #e9ecef;"></div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 영수증 인쇄 전용 영역 (화면에는 보이지 않고 인쇄 시에만 활성화) -->
<div id="print_section" class="d-none d-print-block p-3">
    <div class="receipt-header text-center mb-3">
        <h4 class="fw-bold mb-1 pb-2 border-bottom"><?php echo htmlspecialchars($shop['shop_name'] ?? 'Shop Name'); ?></h4>
        <p class="small text-secondary m-0 fw-bold">Delivery Receipt (DR)</p>
    </div>
    
    <div style="font-size: 11pt;">
        <div class="mb-1"><strong>Order No:</strong> <span id="print_order_no"></span></div>
        <div class="mb-1"><strong>Date:</strong> <span id="print_order_date"></span></div>
        <div class="mb-1"><strong>Customer:</strong> <span id="print_customer_name"></span></div>
        <div class="mb-1"><strong>Phone:</strong> <span id="print_customer_phone"></span></div>
        <div class="mb-1"><strong>Address:</strong> <span id="print_customer_address"></span></div>
        <div class="mb-2"><strong>Payment:</strong> <span id="print_payment_method"></span></div>
        
        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
        <table style="width: 100%; text-align: left; font-size: 11pt;">
            <thead>
                <tr>
                    <th style="padding-bottom: 5px;">Item</th>
                    <th style="text-align: right; padding-bottom: 5px;">Qty</th>
                    <th style="text-align: right; padding-bottom: 5px;">Amt</th>
                </tr>
            </thead>
            <tbody id="print_items_list">
            </tbody>
        </table>
        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
        
        <div class="d-flex justify-content-between mb-2 fs-5 mt-2">
            <strong>Total:</strong>
            <strong id="print_total_price"></strong>
        </div>
        
        <div style="font-size: 9pt; text-align: center; color: #555; margin-top: 20px; padding-top: 10px; border-top: 1px dashed #000;">
            <?php if (!empty($shop['registered_name'])): ?>
                <strong><?php echo htmlspecialchars($shop['registered_name']); ?></strong><br>
            <?php endif; ?>
            <?php if (!empty($shop['business_address'])): ?>
                <?php echo htmlspecialchars($shop['business_address']); ?><br>
            <?php endif; ?>
            <?php if (!empty($shop['tin_number'])): ?>
                TIN: <?php echo htmlspecialchars($shop['tin_number']); ?> (<?php echo htmlspecialchars($shop['business_type'] ?? 'Non-VAT'); ?>)<br>
            <?php endif; ?>
            <br>
            This document is for internal delivery tracking purposes only and does not serve as an Official Receipt (OR) for tax purposes.
        </div>
    </div>
</div>

<!-- 무료 오픈소스 지도 라이브러리 Leaflet.js 로드 -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // URL의 status 파라미터에 따라 해당 탭 자동 활성화
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status === 'completed') {
            const tab = new bootstrap.Tab(document.getElementById('completed-tab'));
            tab.show();
        } else if (status === 'cancelled') {
            const tab = new bootstrap.Tab(document.getElementById('cancelled-tab'));
            tab.show();
        }
    });

    /**
     * 날짜를 오늘로 설정하고 조회
     */
    function setToday() {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('search_date').value = `${yyyy}-${mm}-${dd}`;
        document.getElementById('search_date').form.submit();
    }

    let adminDeliveryMap = null;
    let adminDeliveryMarker = null;

    // [신규] 사장님이 고객의 좌표 버튼을 눌렀을 때 실행되는 지도 모달 제어 함수
    function openAdminMapModal(lat, lng) {
        const modalEl = document.getElementById('adminLocationMapModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        modalEl.addEventListener('shown.bs.modal', function initAdminMap() {
            if (typeof L === 'undefined') {
                alert('지도 라이브러리(Leaflet)가 로드되지 않았습니다.');
                return;
            }
            if (!adminDeliveryMap) {
                adminDeliveryMap = L.map('admin-delivery-map-container', {
                    zoomControl: true,
                    attributionControl: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19
                }).addTo(adminDeliveryMap);
            }

            adminDeliveryMap.invalidateSize();
            adminDeliveryMap.setView([lat, lng], 17);

            if (adminDeliveryMarker) {
                adminDeliveryMarker.setLatLng([lat, lng]);
            } else {
                const markerIcon = L.divIcon({
                    html: '<i class="bi bi-geo-alt-fill text-danger" style="font-size: 3rem; text-shadow: 0 2px 5px rgba(0,0,0,0.3);"></i>',
                    iconSize: [48, 48],
                    iconAnchor: [24, 48],
                    className: 'bg-transparent border-0'
                });
                adminDeliveryMarker = L.marker([lat, lng], {
                    icon: markerIcon
                }).addTo(adminDeliveryMap);
            }
            modalEl.removeEventListener('shown.bs.modal', initAdminMap);
        });
    }

    function populatePrintSection(items, order) {
        // [추가] 인쇄용 영역 데이터 채우기
        document.getElementById('print_order_no').innerText = order.order_no;
        document.getElementById('print_order_date').innerText = order.created_at;
        document.getElementById('print_customer_name').innerText = order.kakao_nickname || '고객';
        document.getElementById('print_customer_phone').innerText = order.customer_phone_formatted || order.customer_phone;
        
        let addr = '';
        if (order.order_type === 'pickup') {
            addr = '[매장픽업] ' + (order.pickup_time || '시간 미지정');
        } else {
            addr = order.customer_address;
            if (order.customer_landmark) addr += ' (' + order.customer_landmark + ')';
        }
        document.getElementById('print_customer_address').innerText = addr;
        
        let payMethod = order.payment_method === 'cash' ? '현금(Cash)' : '기타(GCash 등)';
        if (order.payment_detail) payMethod += ' - ' + order.payment_detail;
        document.getElementById('print_payment_method').innerText = payMethod;

        let printItemsHtml = '';
        items.forEach(item => {
            printItemsHtml += `
                <tr>
                    <td style="padding: 2px 0;">${item.item_name}</td>
                    <td style="text-align: right; padding: 2px 0;">${item.quantity}</td>
                    <td style="text-align: right; padding: 2px 0;">₱ ${(item.price * item.quantity).toLocaleString()}</td>
                </tr>
            `;
        });
        document.getElementById('print_items_list').innerHTML = printItemsHtml;
        document.getElementById('print_total_price').innerText = '₱ ' + parseInt(order.total_price).toLocaleString();
    }

    function showOrderDetails(items, order) {
        const info = document.getElementById('modal-order-info');
        
        let deliveryInfoHtml = '';
        if (order.order_type === 'pickup') {
            deliveryInfoHtml = `<div class="mb-1"><strong>수령방식:</strong> <span class="badge bg-primary">매장픽업</span></div>
            <div class="mb-1"><strong>방문시간:</strong> <span class="text-danger fw-bold">${order.pickup_time || '미지정'}</span></div>`;
        } else {
            deliveryInfoHtml = `<div class="mb-1"><strong>배달주소:</strong> ${order.customer_address}</div>
            ${order.customer_landmark ? `<div><strong>랜드마크:</strong> ${order.customer_landmark}</div>` : ''}`;
        }

        info.innerHTML = `
        <div class="mb-1"><strong>주문번호:</strong> ${order.order_no}</div>
        <div class="mb-1"><strong>연락처:</strong> ${order.customer_phone_formatted || order.customer_phone}</div>
        ${deliveryInfoHtml}
    `;

        const list = document.getElementById('modal-items-list');
        let itemsHtml = items.map(item => `
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-light">
            <div>
                <div class="fw-bold text-dark">${item.item_name}</div>
                <div class="small text-muted">₱ ${parseInt(item.price).toLocaleString()} × ${item.quantity}개</div>
            </div>
            <div class="fw-bold text-primary">₱ ${(item.price * item.quantity).toLocaleString()}</div>
        </div>
    `).join('');

        // 주문 총 합계 추가
        itemsHtml += `
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-2">
            <div class="fw-bold fs-5">주문 총 합계</div>
            <div class="fw-bold fs-4 text-primary">₱ ${parseInt(order.total_price).toLocaleString()}</div>
        </div>
    `;

        list.innerHTML = itemsHtml;

        populatePrintSection(items, order);

        new bootstrap.Modal(document.getElementById('orderDetailModal')).show();
    }

    // [추가] 영수증 인쇄 실행 함수
    function printReceipt() {
        window.print();
    }

    // [신규] 인쇄 준비 및 실행 함수
    function prepareAndPrint(items, order) {
        populatePrintSection(items, order);
        printReceipt();
    }

    /**
     * 주문 상태 즉시 변경 (AJAX)
     */
    async function updateOrderStatus(orderId, status, reason = '') {
        // [추가] "완료" 상태로 변경할 때 확인 팝업 노출 및 취소 시 원복 처리
        if (status === 'completed') {
            if (!confirm('정말로 완료처리 하시겠습니까?')) {
                const row = document.getElementById(`order-row-${orderId}`);
                if (row) {
                    const oldStatus = row.dataset.status;
                    let prefix = 'p';
                    if (oldStatus === 'cooking') prefix = 'c';
                    else if (oldStatus === 'delivery') prefix = 'd';
                    else if (oldStatus === 'completed') prefix = 'f';
                    else if (oldStatus === 'cancelled') prefix = 'x';

                    const oldRadio = document.getElementById(`st_${prefix}_${orderId}`);
                    if (oldRadio) oldRadio.checked = true;
                }
                return;
            }
        }

        // [추가] "취소" 상태로 변경할 때 취소 사유 모달 노출 및 원복 처리
        if (status === 'cancelled' && reason === '') {
            const modalEl = document.getElementById('cancelReasonModal');
            const row = document.getElementById(`order-row-${orderId}`);

            document.getElementById('cancel_target_order_id').value = orderId;
            document.getElementById('cancel_reason_input').value = '';

            // 모달 닫기 시 원복을 위한 이전 상태 저장
            modalEl.dataset.oldStatus = row ? row.dataset.status : 'pending';
            modalEl.dataset.orderId = orderId;

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return; // 여기서 함수 종료, 모달의 '취소 확정' 버튼에서 다시 호출됨
        }

        const formData = new FormData();
        formData.append('update_order_status', '1');
        formData.append('order_id', orderId);
        formData.append('status', status);
        if (reason) formData.append('cancel_reason', reason);

        try {
            const response = await fetch('manage_shop.php?pg=manage_shop_orders', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // [수정] HTML 찌꺼기 무시하고 순수 JSON 구간만 추출하여 파싱 (안전성 강화)
            const text = await response.text();

            const startMarker = "|||JSON_START|||";
            const endMarker = "|||JSON_END|||";
            const startIdx = text.indexOf(startMarker);
            const endIdx = text.indexOf(endMarker);

            if (startIdx === -1 || endIdx === -1) throw new Error("Invalid response format");
            const result = JSON.parse(text.substring(startIdx + startMarker.length, endIdx));

            if (result && result.status === 'success') {
                // 1. 상단 요약 숫자 배달 업데이트
                const row = document.getElementById(`order-row-${orderId}`);

                const oldStatus = row.dataset.status;
                const mappedNewStatus = status;

                if (oldStatus !== mappedNewStatus) {
                    const oldStatEl = document.getElementById(`stat-${oldStatus}`);
                    const newStatEl = document.getElementById(`stat-${mappedNewStatus}`);

                    if (oldStatEl) oldStatEl.innerText = Math.max(0, parseInt(oldStatEl.innerText || 0) - 1);
                    if (newStatEl) newStatEl.innerText = parseInt(newStatEl.innerText || 0) + 1;

                    // 2. 탭 카운트 배달 업데이트
                    const activeTabCountEl = document.getElementById('tab-count-active');
                    const completedTabCountEl = document.getElementById('tab-count-completed');
                    const cancelledTabCountEl = document.getElementById('tab-count-cancelled');

                    const oldS = row.dataset.status;
                    const newS = status;

                    // 이전 탭 카운트 감소
                    if (oldS === 'completed') {
                        if (completedTabCountEl) completedTabCountEl.innerText = Math.max(0, parseInt(completedTabCountEl.innerText) - 1);
                    } else if (oldS === 'cancelled') {
                        if (cancelledTabCountEl) cancelledTabCountEl.innerText = Math.max(0, parseInt(cancelledTabCountEl.innerText) - 1);
                    } else {
                        if (activeTabCountEl) activeTabCountEl.innerText = Math.max(0, parseInt(activeTabCountEl.innerText) - 1);
                    }

                    // 새로운 탭 카운트 증가
                    if (newS === 'completed') {
                        if (completedTabCountEl) completedTabCountEl.innerText = parseInt(completedTabCountEl.innerText) + 1;
                    } else if (newS === 'cancelled') {
                        if (cancelledTabCountEl) cancelledTabCountEl.innerText = parseInt(cancelledTabCountEl.innerText) + 1;
                    } else {
                        if (activeTabCountEl) activeTabCountEl.innerText = parseInt(activeTabCountEl.innerText) + 1;
                    }

                    // 3. 행 상태 데이터 및 스타일 업데이트
                    row.dataset.status = status;
                    if (status === 'pending') {
                        row.classList.add('list-group-item-warning', 'border-start', 'border-5', 'border-danger');
                        const timeContainer = row.querySelector('.col-12.col-lg-2 .fw-bold.text-dark');
                        if (status === 'pending' && timeContainer && !timeContainer.querySelector('.new-order-badge')) {
                            timeContainer.insertAdjacentHTML('beforeend', ' <span class="badge bg-danger ms-1 fw-bold new-order-badge">NEW</span>');
                        }
                    } else {
                        row.classList.remove('list-group-item-warning', 'border-start', 'border-5', 'border-danger');
                        const badge = row.querySelector('.new-order-badge');
                        if (badge) badge.remove();
                    }

                    // 4. 탭 간 이동 처리
                    let targetBodyId = 'active-orders-body';
                    if (status === 'completed') targetBodyId = 'completed-orders-body';
                    else if (status === 'cancelled') targetBodyId = 'cancelled-orders-body';

                    const targetBody = document.getElementById(targetBodyId);

                    if (targetBody && row.parentElement !== targetBody) {
                        const noData = targetBody.querySelector('.no-data');
                        if (noData) noData.remove();
                        targetBody.prepend(row);

                        // [추가] 주문완료/주문취소 탭으로 이동 시 더 이상 상태를 변경할 수 없도록 버튼을 뱃지로 교체
                        if (status === 'completed' || status === 'cancelled') {
                            const statusContainer = document.getElementById(`status-container-${orderId}`);
                            if (statusContainer) {
                                if (status === 'completed') {
                                    statusContainer.innerHTML = '<div class="text-end text-lg-center"><span class="badge bg-success px-4 py-2 fw-bold fs-6"><i class="bi bi-check-circle me-1"></i>주문완료</span></div>';
                                } else {
                                    const reasonHtml = reason ? `<div class="small text-muted mt-1" style="font-size: 0.75rem; word-break: keep-all;">${reason}</div>` : '';
                                    statusContainer.innerHTML = `<div class="text-end text-lg-center"><span class="badge bg-danger px-4 py-2 fw-bold fs-6"><i class="bi bi-x-circle me-1"></i>주문취소</span>${reasonHtml}</div>`;

                                    // [추가] 중앙 주문 요약 버튼 아래에도 취소 사유를 붉은 글씨로 노출
                                    if (reason) {
                                        const summaryContainer = document.getElementById(`summary-container-${orderId}`);
                                        if (summaryContainer && !summaryContainer.querySelector('.cancel-reason-display')) {
                                            summaryContainer.insertAdjacentHTML('beforeend', `<div class="mt-2 text-danger small fw-bold cancel-reason-display"><i class="bi bi-exclamation-triangle-fill me-1"></i>취소사유: ${reason}</div>`);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                alert('상태 변경에 실패했습니다.');
            }
        } catch (err) {
            console.error('Error updating status:', err);
        }
    }

    // ==========================================
    // [상점주 신규 주문 감지 폴링 시스템]
    // ==========================================
    let latestOrderId = <?php echo $current_max_order_id; ?>;
    const newOrderSound = new Audio('<?php echo defined("NOTIFICATION_SOUND") ? NOTIFICATION_SOUND : "/assets/sounds/dingdongg.mp3"; ?>');

    let titleBlinkInterval = null;
    const originalTitle = document.title;

    function startTitleBlink() {
        if (titleBlinkInterval) return;
        let isBlinked = false;
        titleBlinkInterval = setInterval(() => {
            document.title = isBlinked ? originalTitle : '🔔 (새 주문) ' + originalTitle;
            isBlinked = !isBlinked;
        }, 1000);
    }

    function stopTitleBlink() {
        if (titleBlinkInterval) {
            clearInterval(titleBlinkInterval);
            titleBlinkInterval = null;
            document.title = originalTitle;
        }
    }

    // [시각 효과] 알림 소리가 재생 안 될 때를 대비해 화면 전체 배경을 깜빡입니다.
    function flashBackground() {
        document.body.classList.add('flash-bg');
        setTimeout(() => {
            document.body.classList.remove('flash-bg');
        }, 1500); // 0.5초 x 3회 반복
    }

    async function pollForNewOrders() {
        try {
            // 현재 내 화면에 떠있는 가장 최신 주문 번호를 함께 보냅니다.
            const response = await fetch('manage_shop.php?pg=manage_shop_orders&action=poll_new_orders&last_id=' + latestOrderId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // [수정] 혹시라도 HTML이 섞여서 SyntaxError가 나는 것을 방지하기 위한 추출 로직
            const text = await response.text();
            const startMarker = "|||JSON_START|||";
            const endMarker = "|||JSON_END|||";
            const startIdx = text.indexOf(startMarker);
            const endIdx = text.indexOf(endMarker);

            if (startIdx === -1 || endIdx === -1) return; // 마커가 없으면 무시
            const data = JSON.parse(text.substring(startIdx + startMarker.length, endIdx));

            if (data.status === 'success' && data.latest_id > latestOrderId) {
                latestOrderId = data.latest_id; // 업데이트하여 중복 알림 방지

                // 서버에서 새로 생성해서 보내준 HTML 주문 카드를 내역 최상단에 끼워 넣습니다.
                if (data.html) {
                    const activeBody = document.getElementById('active-orders-body');
                    const noData = activeBody.querySelector('.no-data');
                    if (noData) noData.remove();

                    // [버그수정] DOM 중복 삽입 방지 로직 (파싱 후 ID 교차 검증)
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.html;

                    let actualAddedCount = 0;
                    // 오래된 주문부터 역순으로 꺼내어 최상단(afterbegin)에 순서대로 쌓아 올림
                    const newRows = Array.from(tempDiv.children).reverse();

                    newRows.forEach(row => {
                        if (!document.getElementById(row.id)) {
                            activeBody.insertAdjacentElement('afterbegin', row);
                            actualAddedCount++;

                            row.classList.add('new-item-highlight');
                            setTimeout(() => row.classList.remove('new-item-highlight'), 3200);
                        }
                    });

                    // 상단 메뉴 탭과 카운트 뱃지 숫자 갱신
                    if (actualAddedCount > 0) {
                        const activeTabCountEl = document.getElementById('tab-count-active');
                        if (activeTabCountEl) activeTabCountEl.innerText = parseInt(activeTabCountEl.innerText || 0) + actualAddedCount;

                        const statPending = document.getElementById('stat-pending');
                        if (statPending) statPending.innerText = parseInt(statPending.innerText || 0) + actualAddedCount;
                    }
                }

                // 브라우저 배경 깜빡임 시각 효과
                flashBackground();

                // 알림음 재생
                newOrderSound.play().catch(e => console.log('브라우저 정책으로 오디오 자동재생이 제한됨(화면 클릭 필요)'));

                // 타이틀 탭 깜빡임 시작
                startTitleBlink();

                // autohide: false 옵션으로 X버튼을 누르기 전까지 사라지지 않게 설정
                const toastEl = document.getElementById('newOrderToast');
                const toast = new bootstrap.Toast(toastEl, {
                    autohide: false
                });

                // 알림창(Toast)이 닫힐 때 깜빡임도 같이 멈추도록 이벤트 등록
                toastEl.addEventListener('hidden.bs.toast', stopTitleBlink, {
                    once: true
                });

                toast.show();
            }
        } catch (err) {}
    }

    setInterval(pollForNewOrders, 10000); // 10초마다 백그라운드 확인

    // ==========================================
    // [주문 취소 모달 전용 헬퍼 함수들]
    // ==========================================
    function executeCancelOrder() {
        const orderId = document.getElementById('cancel_target_order_id').value;
        const reason = document.getElementById('cancel_reason_input').value.trim();

        bootstrap.Modal.getInstance(document.getElementById('cancelReasonModal')).hide();
        // reason을 파라미터로 넘겨 모달 팝업 로직을 패스하고 AJAX 호출 진행
        updateOrderStatus(orderId, 'cancelled', reason || '상점 사정으로 인한 취소');
    }

    // 취소 모달이 '취소 확정' 없이 그냥 닫혔을 때(배경 클릭 등) 기존 상태로 라디오 버튼 복구
    document.addEventListener('DOMContentLoaded', function() {
        const cancelModalEl = document.getElementById('cancelReasonModal');
        if (cancelModalEl) {
            cancelModalEl.addEventListener('hide.bs.modal', function() {
                const orderId = this.dataset.orderId;
                if (!orderId) return;

                const row = document.getElementById(`order-row-${orderId}`);
                if (row && row.dataset.status !== 'cancelled') {
                    const oldStatus = this.dataset.oldStatus;
                    let prefix = 'p';
                    if (oldStatus === 'cooking') prefix = 'c';
                    else if (oldStatus === 'delivery') prefix = 'd';

                    const oldRadio = document.getElementById(`st_${prefix}_${orderId}`);
                    const cancelRadio = document.getElementById(`st_x_${orderId}`);
                    if (cancelRadio && cancelRadio.checked && oldRadio) oldRadio.checked = true;
                }
            });
        }
    });

    /**
     * 배달 정보 클립보드 복사
     */
    async function copyDeliveryInfo(btn) {
        const text = btn.getAttribute('data-clipboard-text');
        if (!text) return;

        try {
            await navigator.clipboard.writeText(text);
            if (typeof showToast === 'function') {
                showToast('배달 정보가 클립보드에 복사되었습니다.', 'success');
            } else {
                alert('배달 정보가 클립보드에 복사되었습니다.');
            }
        } catch (err) {
            const tempElem = document.createElement('textarea');
            tempElem.value = text;
            document.body.appendChild(tempElem);
            tempElem.select();
            document.execCommand('copy');
            document.body.removeChild(tempElem);
            if (typeof showToast === 'function') {
                showToast('배달 정보가 클립보드에 복사되었습니다.', 'success');
            } else {
                alert('배달 정보가 클립보드에 복사되었습니다.');
            }
        }
    }

    /**
     * 고객 전화번호 클릭 시 탭 이동 및 검색
     */
    function searchCustomerOrders(phone) {
        document.getElementById('search_customer_phone').value = phone;

        // 고객주문관리 탭 활성화
        const tabEl = document.getElementById('customer-orders-tab');
        const tab = new bootstrap.Tab(tabEl);
        tab.show();

        // 검색 실행 (1페이지)
        loadCustomerOrders(1);
    }

    /**
     * 고객 주문 목록 로드 (AJAX 페이징)
     */
    async function loadCustomerOrders(page) {
        const phone = document.getElementById('search_customer_phone').value.trim();
        if (!phone) return alert('전화번호를 입력해주세요.');

        const resultsContainer = document.getElementById('customer-orders-results');
        const paginationContainer = document.getElementById('customer-orders-pagination');

        resultsContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted small">불러오는 중...</div></div>';
        paginationContainer.innerHTML = '';

        try {
            const response = await fetch(`manage_shop.php?pg=manage_shop_orders&action=get_customer_orders&phone=${encodeURIComponent(phone)}&page=${page}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const text = await response.text();
            const result = JSON.parse(text.substring(text.indexOf("|||JSON_START|||") + 16, text.indexOf("|||JSON_END|||")));

            const summaryContainer = document.getElementById('customer-summary-container');

            if (result.status === 'success') {
                resultsContainer.innerHTML = result.html;

                // [추가] 요약 정보 UI 렌더링
                if (summaryContainer && result.summary && result.total_count > 0) {
                    summaryContainer.innerHTML = `
                        <div class="row text-center g-2 mb-2">
                            <div class="col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                                    <div class="small text-success fw-bold mb-1">주문완료</div>
                                    <div class="fs-5 fw-bold text-dark">${result.summary.completed_count}<span class="fs-6 fw-normal ms-1">건</span></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                                    <div class="small text-danger fw-bold mb-1">주문취소</div>
                                    <div class="fs-5 fw-bold text-dark">${result.summary.cancelled_count}<span class="fs-6 fw-normal ms-1">건</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2 p-2 bg-light rounded-3 border">
                            <i class="bi bi-geo-alt-fill text-primary mt-1"></i>
                            <div>
                                <div class="small fw-bold text-muted mb-1">최근 배달 주소</div>
                                <div class="small text-dark fw-medium">${result.summary.latest_address}</div>
                            </div>
                        </div>
                    `;
                    summaryContainer.classList.remove('d-none');
                } else if (summaryContainer) {
                    summaryContainer.classList.add('d-none');
                }

                if (result.total_pages > 1) {
                    let pageHtml = '<ul class="pagination pagination-sm mb-0 shadow-sm">';
                    for (let i = 1; i <= result.total_pages; i++) {
                        pageHtml += `<li class="page-item ${i === result.current_page ? 'active' : ''}"><a class="page-link" href="#" onclick="loadCustomerOrders(${i}); return false;">${i}</a></li>`;
                    }
                    pageHtml += '</ul>';
                    paginationContainer.innerHTML = pageHtml;
                }
            } else resultsContainer.innerHTML = `<div class="list-group-item text-center py-5 text-danger">${result.message}</div>`;
        } catch (err) {
            resultsContainer.innerHTML = '<div class="list-group-item text-center py-5 text-danger border-bottom">서버 통신 오류가 발생했습니다.</div>';
        }
    }
</script>