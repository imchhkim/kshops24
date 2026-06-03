<?php

/**
 * KShops24 부동산 카테고리 문의(Inquiry) 관리 모듈
 * - 기능: 부동산 고객 문의(관심 매물, 상담 요청 등) 내역 확인 및 상태 관리
 * - 상점 관리자 페이지 (manage_shop.php)에서 include 되어 실행됩니다.
 */

if (!isset($shop_id)) exit; // 직접 접근 차단

// ==========================================================
// 1. AJAX 요청 처리 (상태 변경 및 삭제)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    } // 출력 버퍼 정리
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);

    try {
        // [추가] 실시간 폴링: 새로운 문의 접수 여부 확인 (마지막 확인한 ID 기준)
        if ($action === 'check_new_inquiry') {
            $last_id = (int)($_POST['last_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT MAX(id) FROM shop_inquiries WHERE shop_id = ?");
            $stmt->execute([$shop_id]);
            $latest_id = (int)$stmt->fetchColumn();
            
            $has_new = ($latest_id > $last_id);
            
            echo json_encode(['status' => 'success', 'has_new' => $has_new, 'latest_id' => $latest_id]);
            exit;
        }

        // 위의 폴링을 제외한 상태 변경, 삭제, 메모 저장 등의 액션은 inquiry_id가 반드시 필요합니다.
        if (!$inquiry_id) {
            echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
            exit;
        }

        if ($action === 'update_status') {
            $new_status = $_POST['new_status'] ?? 'pending';

            // 상태 업데이트
            $stmt = $pdo->prepare("UPDATE shop_inquiries SET status = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$new_status, $inquiry_id, $shop_id]);

            // 이력 기록
            $status_labels = [
                'pending' => '상담 대기',
                'contacted' => '상담 중',
                'completed' => '상담 완료',
                'cancelled' => '상담 취소'
            ];
            $label = $status_labels[$new_status] ?? '알 수 없음';

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "고객 문의 상태 변경", "상담 번호 #{$inquiry_id} 상태가 [{$label}](으)로 변경되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => "상담 상태가 '{$label}'(으)로 변경되었습니다."]);
            exit;
        }

        if ($action === 'delete_inquiry') {
            $stmt = $pdo->prepare("DELETE FROM shop_inquiries WHERE id = ? AND shop_id = ?");
            $stmt->execute([$inquiry_id, $shop_id]);

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "고객 문의 삭제", "상담 번호 #{$inquiry_id} 내역이 삭제되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => '문의 내역이 안전하게 삭제되었습니다.']);
            exit;
        }

        if ($action === 'save_reply_memo') {
            $owner_reply = trim($_POST['owner_reply'] ?? '');
            $owner_memo = trim($_POST['owner_memo'] ?? '');
            $new_status = $_POST['new_status'] ?? 'pending';

            // 답변 및 메모 저장
            $stmt = $pdo->prepare("UPDATE shop_inquiries SET owner_reply = ?, owner_memo = ?, status = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$owner_reply, $owner_memo, $new_status, $inquiry_id, $shop_id]);

            $status_labels = [
                'pending' => '상담 대기',
                'contacted' => '상담 중',
                'completed' => '상담 완료',
                'cancelled' => '상담 취소'
            ];
            $label = $status_labels[$new_status] ?? '알 수 없음';

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "문의 답변/메모 및 상태 저장", "상담 번호 #{$inquiry_id} 상태([{$label}]) 및 답변/메모가 저장되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => '상담 정보(상태, 답변, 메모)가 성공적으로 저장되었습니다.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '처리 중 오류가 발생했습니다: ' . $e->getMessage()]);
        exit;
    }
}

// ==========================================================
// 2. 데이터 필터링 및 조회 로직 (백엔드)
// ==========================================================
// [추가] 월(Month) 단위 기간 필터 도입 (기본값: 이번 달)
$target_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $target_month)) {
    $target_month = date('Y-m');
}

// date 파라미터가 없으면 해당 달의 오늘 날짜(이번 달인 경우)를 설정
if (!isset($_GET['date'])) {
    $target_date = ($target_month === date('Y-m')) ? date('Y-m-d') : '';
} else {
    $target_date = $_GET['date'];
}
$current_status = $_GET['status'] ?? 'all';

$where_sql = "shop_id = ?";
$params = [$shop_id];

// 선택한 월(Month)의 데이터만 조회
$where_sql .= " AND created_at LIKE ?";
$params[] = "{$target_month}-%";

// 선택한 일(Date) 필터
if ($target_date !== '') {
    $where_sql .= " AND DATE(created_at) = ?";
    $params[] = $target_date;
}

// 'all'이 아닐 때만 상태 필터링 쿼리를 추가합니다.
if ($current_status !== 'all') {
    $where_sql .= " AND status = ?";
    $params[] = $current_status;
}

// 전체 및 상태별 카운트 집계 (해당 월 기준)
$stmt_counts = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM shop_inquiries WHERE shop_id = ? AND created_at LIKE ? GROUP BY status");
$stmt_counts->execute([$shop_id, "{$target_month}-%"]);
$counts = ['all' => 0, 'pending' => 0, 'contacted' => 0, 'completed' => 0, 'cancelled' => 0];

foreach ($stmt_counts->fetchAll() as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = $row['cnt'];
    }
    $counts['all'] += $row['cnt'];
}

// 초기 최신 문의 ID (폴링 기준점)
$stmt_max_id = $pdo->prepare("SELECT MAX(id) FROM shop_inquiries WHERE shop_id = ?");
$stmt_max_id->execute([$shop_id]);
$initial_latest_id = (int)$stmt_max_id->fetchColumn();

// [추가] 달력에 상담 건수를 스마트하게 표시하기 위한 날짜별 통계 집계
$stmt_cal = $pdo->prepare("SELECT DATE(created_at) as inq_date, status, COUNT(*) as cnt FROM shop_inquiries WHERE shop_id = ? AND created_at LIKE ? GROUP BY DATE(created_at), status");
$stmt_cal->execute([$shop_id, "{$target_month}-%"]);
$cal_stats = [];
foreach ($stmt_cal->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $d = $row['inq_date'];
    if (!isset($cal_stats[$d])) {
        $cal_stats[$d] = ['total' => 0, 'pending' => 0];
    }
    $cal_stats[$d]['total'] += $row['cnt'];
    if ($row['status'] === 'pending') {
        $cal_stats[$d]['pending'] += $row['cnt'];
    }
}

// 문의 내역 목록 조회
$stmt = $pdo->prepare("SELECT * FROM shop_inquiries WHERE {$where_sql} ORDER BY created_at DESC");
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 상태 UI 맵핑 (리스트 출력 시 사용)
$status_map = [
    'pending'   => ['label' => '상담 대기', 'color' => 'bg-warning text-dark', 'icon' => 'bi-hourglass-split'],
    'contacted' => ['label' => '상담 중',   'color' => 'bg-info text-white',   'icon' => 'bi-headset'],
    'completed' => ['label' => '상담 완료', 'color' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
    'cancelled' => ['label' => '상담 취소', 'color' => 'bg-secondary text-white', 'icon' => 'bi-x-circle-fill']
];
?>

<!-- 상단 타이틀 영역 -->
<?php echo renderPageHeader('고객 상담 관리', 'bi-chat-square-text'); ?>

<!-- [추가] 월(Month) 선택 필터 폼 -->
<div class="mb-3 p-2 bg-white rounded-4 shadow-sm border border-opacity-50">
    <form id="month-filter-form" method="GET" action="manage_shop.php" class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between m-0 gap-2">
        <div class="d-flex align-items-center">
            <input type="hidden" name="pg" value="manage_shop_inquiries">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($current_status); ?>">
            <label class="fw-bold me-3 ms-2 small text-dark"><i class="bi bi-calendar-month me-1 text-primary"></i>조회 년/월</label>
            
            <!-- 브라우저 기본 영어 표현을 숨기고, 한국어(YYYY년 M월)로 직관적으로 표시하는 커스텀 UI -->
            <div class="position-relative d-inline-block">
                <div class="form-control form-control-sm w-auto fw-bold bg-light border-0 d-flex align-items-center" style="pointer-events: none;">
                    <?php echo date('Y', strtotime($target_month)) . '년 ' . date('n', strtotime($target_month)) . '월'; ?>
                    <i class="bi bi-caret-down-fill ms-2 text-muted" style="font-size: 0.7rem;"></i>
                </div>
                <input type="month" name="month" class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0; cursor: pointer;" value="<?php echo htmlspecialchars($target_month); ?>" onchange="this.form.submit()">
            </div>
        </div>
        
        <div class="d-flex gap-2 w-100 w-md-auto justify-content-end">
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold flex-shrink-0" onclick="goToThisMonth()">이번달</button>   
        </div>
    </form>
</div>
<script>
    function goToThisMonth() {
        const form = document.getElementById('month-filter-form');
        form.querySelector('input[name="month"]').value = '<?php echo date('Y-m'); ?>';
        form.submit();
    }
</script>

<!-- [추가] 모바일 최적화 가로 스크롤 월간(Monthly) 달력 -->
<?php
$dates = [];
$start_date = $target_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));
$today = date('Y-m-d');

$current = strtotime($start_date);
$end = strtotime($end_date);

while ($current <= $end) {
    $d = date('Y-m-d', $current);
    $dates[] = [
        'date' => $d,
        'day' => date('d', strtotime($d)),
        'week_day' => date('w', strtotime($d)),
        'is_today' => ($d === $today),
        'is_selected' => ($d === $target_date)
    ];
    $current = strtotime('+1 day', $current);
}
$week_map = ['일', '월', '화', '수', '목', '금', '토'];
?>
<style>
    /* 가로 스크롤바 표시 및 스타일링 */
    #weekCalendar::-webkit-scrollbar { height: 6px; }
    #weekCalendar::-webkit-scrollbar-track { background: #f8f9fa; border-radius: 10px; }
    #weekCalendar::-webkit-scrollbar-thumb { background: #ced4da; border-radius: 10px; }
</style>

<!-- 달력 영역 -->
<div class="mb-3 position-relative">
    <div class="d-flex overflow-auto gap-2 pb-2 px-1" id="weekCalendar" style="-webkit-overflow-scrolling: touch;">
        <a href="?pg=manage_shop_inquiries&status=<?php echo htmlspecialchars($current_status); ?>&month=<?php echo urlencode($target_month); ?>&date=" class="btn <?php echo $target_date === '' ? 'btn-dark shadow' : 'btn-white bg-white border shadow-sm text-dark'; ?> d-flex flex-column align-items-center justify-content-center flex-shrink-0 rounded-4 transition-all" style="width: 60px; height: 75px;">
            <span class="small mb-1">전체</span>
            <span class="fw-bold fs-5">All</span>
        </a>
        <?php foreach ($dates as $d): 
            $is_today_unselected = ($d['is_today'] && !$d['is_selected']);
            $text_class = ($target_date === '' || !$d['is_selected']) ? ($is_today_unselected ? 'text-white-50' : ($d['week_day'] == 0 ? 'text-danger' : ($d['week_day'] == 6 ? 'text-primary' : 'text-muted'))) : 'text-white-50';
            $btn_class = $d['is_selected'] ? 'btn-dark shadow' : ($d['is_today'] ? 'shadow-sm border-0' : 'btn-white bg-white border shadow-sm text-dark');
            $today_style = $is_today_unselected ? 'background-color: #fd7e14; color: white;' : '';
            $stats = $cal_stats[$d['date']] ?? null;
        ?>
            <a href="?pg=manage_shop_inquiries&status=<?php echo htmlspecialchars($current_status); ?>&month=<?php echo urlencode($target_month); ?>&date=<?php echo $d['date']; ?>" 
               class="btn <?php echo $btn_class; ?> position-relative d-flex flex-column align-items-center justify-content-center flex-shrink-0 rounded-4 date-item transition-all <?php echo $d['is_today'] ? 'today-item' : ''; ?>" 
               style="width: 60px; height: 75px; <?php echo $today_style; ?>">
                <span class="small mb-1 <?php echo $text_class; ?> fw-bold" style="font-size: 0.75rem;"><?php echo $week_map[$d['week_day']]; ?></span>
                <span class="fw-bold fs-5 <?php echo ($d['is_selected'] || $is_today_unselected) ? 'text-white' : 'text-dark'; ?>"><?php echo $d['day']; ?></span>
                
                <!-- 상담 상태 건수 뱃지 표시 -->
                <?php if ($stats && $stats['total'] > 0): ?>
                    <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill shadow-sm <?php echo $stats['pending'] > 0 ? 'bg-danger' : 'bg-primary'; ?>" style="font-size: 0.65rem; padding: 0.3em 0.5em; transform: translate(-10%, 15%) !important;">
                        <?php echo $stats['total']; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 탭 네비게이션: 모바일 최적화 레이아웃 -->
<ul class="nav nav-pills row g-2 mb-4 border-bottom pb-3 px-2">

    <!-- [전체] 버튼: 모바일 100% (col-12), PC 자동 (col-md-auto) -->
    <li class="nav-item col-12 col-md-auto">
        <?php $is_all = ($current_status === 'all'); ?>
        <a class="nav-link rounded-pill fw-bold px-3 py-2 ajax-page-link text-center <?php echo $is_all ? 'active' : 'bg-white text-dark border'; ?>"
            href="?pg=manage_shop_inquiries&status=all&month=<?php echo urlencode($target_month); ?>&date=<?php echo urlencode($target_date); ?>">
            전체
            <span class="badge <?php echo $is_all ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'; ?> ms-1 rounded-pill">
                <?php echo $counts['all']; ?>
            </span>
        </a>
    </li>

    <?php
    $nav_items = [
        'pending'   => '상담 대기',
        'contacted' => '상담 중',
        'completed' => '상담 완료',
        'cancelled' => '상담 취소'
    ];

    foreach ($nav_items as $key => $label):
        $is_active = ($current_status === $key);
    ?>
        <!-- [상태별] 버튼: 모바일 50% (col-6), PC 자동 (col-md-auto) -->
        <li class="nav-item col-6 col-md-auto">
            <a class="nav-link rounded-pill fw-bold px-2 py-2 ajax-page-link text-center <?php echo $is_active ? 'active' : 'bg-white text-dark border'; ?>"
                href="?pg=manage_shop_inquiries&status=<?php echo $key; ?>&month=<?php echo urlencode($target_month); ?>&date=<?php echo urlencode($target_date); ?>"
                style="font-size: 0.85rem; display: flex; align-items: center; justify-content: center; height: 100%;">

                <span class="text-nowrap"><?php echo $label; ?></span>
                <span class="badge <?php echo $is_active ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'; ?> ms-1 rounded-pill">
                    <?php echo $counts[$key]; ?>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- 문의 목록 영역 -->
<div class="row" id="inquiry-list-container">
    <div class="col-12">
        <?php if (empty($inquiries)): ?>
            <div class="card border-0 shadow-sm rounded-4 py-5 text-center">
                <i class="bi bi-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">해당 날짜에 조회된 문의 내역이 없습니다.</h5>
            </div>
        <?php else: ?>
            <?php
            $list_num = 1; // [추가] 순차적 목록 번호 카운터
            foreach ($inquiries as $inq):
                $status = $status_map[$inq['status']] ?? ['label' => '알 수 없음', 'color' => 'bg-light text-dark', 'icon' => 'bi-question'];
                $ts = strtotime($inq['created_at']);
                $kr_days = ['일', '월', '화', '수', '목', '금', '토'];
                $date = date('Y-m-d', $ts) . ' (' . $kr_days[date('w', $ts)] . ') ' . date('H:i', $ts);

                // 관심 매물 배열 디코딩
                $items = json_decode($inq['inquiry_data'], true);
                if (!is_array($items)) $items = [];
            ?>
                <div class="card shadow-sm mb-4 border-0 rounded-4">
                    <!-- card-header: 모바일에서 세로 정렬(flex-column), PC에서 가로 정렬(flex-md-row) -->
                    <div class="card-header bg-white border-bottom pt-3 pb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center rounded-top-4 gap-3">

                        <!-- [모바일 1번째 줄 / PC 좌측] 정보 영역 -->
                        <div class="d-flex align-items-center justify-content-between w-100 w-md-auto gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <!-- 순차적 목록 번호 -->
                                <span class="fw-bold text-secondary fs-5">#<?php echo $list_num++; ?></span>
                                <!-- 상태 뱃지 (공용) -->
                                <span class="badge <?php echo $status['color']; ?> rounded-pill px-3 py-2 fw-bold text-nowrap">
                                    <i class="bi <?php echo $status['icon']; ?> me-1"></i><?php echo $status['label']; ?>
                                </span>
                            </div>
                            <!-- 접수일시 (공용, 모바일 우측 정렬) -->
                            <small class="text-muted fw-medium text-nowrap ms-2">
                                <i class="bi bi-clock me-1"></i>접수일시: <?php echo $date; ?>
                            </small>
                        </div>

                        <!-- [모바일 2번째 줄 / PC 우측] 관리 버튼 영역 -->
                        <div class="d-flex w-100 w-md-auto justify-content-end gap-2 mt-2 mt-md-0">
                            <select class="form-select form-select-sm fw-bold border-secondary shadow-sm rounded-pill px-3" style="min-width: 120px; cursor: pointer;" onchange="selectNewStatus(<?php echo $inq['id']; ?>, this.value, this.options[this.selectedIndex].text)">
                                    <option value="pending" <?php echo $inq['status'] === 'pending' ? 'selected' : ''; ?>>상담 대기</option>
                                    <option value="contacted" <?php echo $inq['status'] === 'contacted' ? 'selected' : ''; ?>>상담 중</option>
                                    <option value="completed" <?php echo $inq['status'] === 'completed' ? 'selected' : ''; ?>>상담 완료</option>
                                    <option value="cancelled" <?php echo $inq['status'] === 'cancelled' ? 'selected' : ''; ?>>상담 취소</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm flex-shrink-0" onclick="deleteInquiry(<?php echo $inq['id']; ?>)" title="이 문의 영구 삭제">
                                    <i class="bi bi-trash3-fill"></i><span class="d-none d-md-inline ms-1">삭제</span>
                                </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4 pb-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                            <div><!-- 부동산은 예약일시가 없으므로 이 영역은 비워둠 --></div>
                            
                            <div class="text-md-end mt-2 mt-md-0">
                                <div class="d-flex justify-content-between justify-content-md-end align-items-center mb-2 gap-2">
                                    <h6 class="fw-bold small text-muted mb-0"><i class="bi bi-telephone-inbound-fill text-primary me-1"></i> 고객 연락처</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-sm" style="font-size: 0.75rem;" onclick="showCustomerInquiryHistory('<?php echo $inq['customer_phone']; ?>')">
                                        <i class="bi bi-list-ul me-1"></i>모두보기
                                    </button>
                                </div>
                                <h4 class="fw-bold mb-0 text-center text-md-end">
                                    <a href="tel:<?php echo $inq['customer_phone']; ?>" class="text-decoration-none text-dark bg-light px-3 py-2 rounded-3 d-inline-block shadow-sm transition-all">
                                        <?php echo function_exists('formatPHPhone') ? formatPHPhone($inq['customer_phone']) : $inq['customer_phone']; ?>
                                    </a>
                                </h4>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-5 border-md-end">
                                <h6 class="fw-bold small text-muted mb-3"><i class="bi bi-building text-primary me-1"></i> 관심 매물 목록 <span class="badge bg-secondary rounded-pill ms-1"><?php echo count($items); ?></span></h6>
                                <ul class="list-group list-group-flush rounded-3 shadow-sm border">
                                    <?php if (empty($items)): ?>
                                        <li class="list-group-item text-muted small text-center py-3">선택한 매물이 없습니다.</li>
                                    <?php else: foreach ($items as $item): ?>
                                        <li class="list-group-item bg-light border-bottom border-white py-2 px-3 fw-bold text-dark text-truncate">
                                            <i class="bi bi-check2 text-success me-2"></i><?php echo htmlspecialchars($item['name'] ?? '매물명 없음'); ?>
                                        </li>
                                    <?php endforeach; endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-7">
                                <h6 class="fw-bold small text-muted mb-3"><i class="bi bi-chat-quote-fill text-primary me-1"></i> 고객 상세 문의/요청 사항</h6>
                                <div class="p-3 border border-primary border-opacity-25 rounded-3 bg-light shadow-sm mb-4">
                                    <p class="mb-0 text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($inq['customer_inquiry'] ?: '특별한 요청사항이 없습니다.'); ?></p>
                                </div>

                                <!-- [추가] 상점 답변 및 메모 폼 -->
                                <form id="memo-form-<?php echo $inq['id']; ?>" onsubmit="saveInquiryMemo(<?php echo $inq['id']; ?>); return false;">
                                    <input type="hidden" name="new_status" value="<?php echo $inq['status']; ?>">
                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-bold text-dark"><i class="bi bi-reply-fill text-success me-1"></i> 고객 안내용 답변</label>
                                            <textarea name="owner_reply" class="form-control text-dark" rows="3" placeholder="고객에게 안내할 답변 내용을 작성해 주세요. (고객이 '나의 문의 내역' 확인 시 노출됩니다)"><?php echo htmlspecialchars($inq['owner_reply'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-bold text-dark"><i class="bi bi-journal-text text-warning me-1"></i> 상점 전용 메모 <span class="fw-normal text-muted">(고객 미노출)</span></label>
                                            <textarea name="owner_memo" class="form-control bg-warning bg-opacity-10 text-dark border-warning border-opacity-25" rows="3" placeholder="관리자만 볼 수 있는 상담 진행 상황이나 특이사항을 기록하세요."><?php echo htmlspecialchars($inq['owner_memo'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-sm btn-dark rounded-pill px-4 shadow-sm fw-bold btn-save-memo"><i class="bi bi-save me-1"></i> 답변/메모 저장</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- [추가] 특정 고객 전체 문의 내역 조회 모달 -->
<div class="modal fade" id="customerHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>고객 전체 문의 내역</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="customer-history-phone-display" class="alert alert-light border shadow-sm text-center mb-4 rounded-4 fw-bold text-primary fs-5">
                    <!-- 전화번호 표시 -->
                </div>
                <div id="customer-history-results">
                    <!-- AJAX 결과 출력 영역 -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // [UX 개선] 달력 로드 시, 선택된 날짜 또는 '오늘 날짜'로 스크롤 자동 이동 (중앙 정렬)
    document.addEventListener('DOMContentLoaded', function() {
        // 1순위: 사용자가 선택한 날짜 (btn-dark), 2순위: 오늘 날짜 (today-item)
        const targetEl = document.querySelector('.date-item.btn-dark') || document.querySelector('.today-item');
        if (targetEl) {
            const container = document.getElementById('weekCalendar');
            const scrollPos = targetEl.offsetLeft - (container.clientWidth / 2) + (targetEl.clientWidth / 2);
            container.scrollLeft = scrollPos;
        }
    });

    // 드롭다운 메뉴에서 상태 선택 시 하단 폼(select)과 연동 및 안내
    function selectNewStatus(inquiryId, newStatus, statusText) {
        const form = document.getElementById(`memo-form-${inquiryId}`);
        if (!form) return;
        
        const inputEl = form.querySelector('input[name="new_status"]');
        if (inputEl) {
            inputEl.value = newStatus;
            
            // 폼 영역으로 부드럽게 스크롤
            form.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    // 삭제 AJAX
    async function deleteInquiry(inquiryId) {
        if (!confirm('정말로 이 고객 문의를 삭제하시겠습니까?\n삭제 후에는 복구할 수 없습니다.')) return;

        const formData = new FormData();
        formData.append('action', 'delete_inquiry');
        formData.append('inquiry_id', inquiryId);

        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_inquiries', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                else alert(data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                alert('오류: ' + data.message);
            }
        } catch (e) {
            alert('통신 중 오류가 발생했습니다.');
        }
    }

    // 답변 및 메모 저장 AJAX
    async function saveInquiryMemo(inquiryId) {
        const form = document.getElementById(`memo-form-${inquiryId}`);
        const formData = new FormData(form);
        formData.append('action', 'save_reply_memo');
        formData.append('inquiry_id', inquiryId);

        const btn = form.querySelector('.btn-save-memo');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';
        btn.disabled = true;

        try {
            const res = await fetch(location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                else alert(data.message);
                setTimeout(() => location.reload(), 800); // 뱃지 등의 상태 반영을 위해 화면 새로고침
            } else {
                alert('오류: ' + data.message);
            }
        } catch (e) {
            alert('통신 중 오류가 발생했습니다.');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    // [추가] 고객 전화번호 기반 전체 문의 내역 불러오기 (고객용 API 재활용)
    async function showCustomerInquiryHistory(phoneRaw) {
        const phone = phoneRaw.replace(/\D/g, '');
        if (!phone) return;

        const modalEl = document.getElementById('customerHistoryModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        // [수정] 전화번호를 항상 0000-000-0000 형식으로 일관되게 포맷팅
        let formattedPhone = phone;
        if (phone.length > 4 && phone.length <= 7) {
            formattedPhone = phone.substring(0, 4) + '-' + phone.substring(4);
        } else if (phone.length > 7) {
            formattedPhone = phone.substring(0, 4) + '-' + phone.substring(4, 7) + '-' + phone.substring(7, 11);
        }

        document.getElementById('customer-history-phone-display').innerHTML = '<i class="bi bi-telephone-fill me-2"></i>' + formattedPhone;
        const resultsContainer = document.getElementById('customer-history-results');
        resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

        modal.show();

        const formData = new FormData();
        formData.append('shop_id', <?php echo $shop_id; ?>);
        formData.append('phone', phone);
        formData.append('context', 'admin'); // [추가] 관리자 페이지에서 호출됨을 명시

        try {
            const response = await fetch('/shops/realty/shop_realty_inquiry_history.php', {
                method: 'POST',
                body: formData
            });
            resultsContainer.innerHTML = await response.text();
        } catch (e) {
            resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>';
        }
    }

    // ==========================================================
    // [기능 2, 3] 실시간 새 문의 체크 및 알림음 재생 (Polling)
    // ==========================================================
    let lastInquiryId = <?php echo $initial_latest_id; ?>;
    
    // 오디오 객체 생성 (F&B 카테고리와 동일한 종소리 알림음 적용)
    const notificationSound = new Audio(NOTIFICATION_SOUND); 

    document.addEventListener('DOMContentLoaded', function() {
        // 5초 주기로 서버에 새로운 문의가 들어왔는지 확인 (실시간 반영)
        setInterval(checkNewInquiry, 5000);
    });

    async function checkNewInquiry() {
        const formData = new FormData();
        formData.append('action', 'check_new_inquiry');
        formData.append('last_id', lastInquiryId);
        
        try {
            // [핵심 버그 수정] 상대경로 하드코딩 시 404 에러나 JSON 파싱 에러(Unexpected token <) 발생 가능성 차단
            const res = await fetch(location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!res.ok) throw new Error('Network response was not ok');
            
            const data = await res.json();
            
            if (data.status === 'success') {
                if (data.has_new) {
                    lastInquiryId = parseInt(data.latest_id);
                    
                    // 벨소리 울리기
                    notificationSound.play().catch(function(error) {
                        console.log("Audio play blocked:", error);
                    });
                    
                    // 화면 상단에 빨간색 사각형 알림 띄우기 (3초 후 자동 닫힘)
                    const alertId = 'new-inquiry-alert-' + Date.now();
                    const alertHtml = `
                        <div id="${alertId}" class="alert alert-danger alert-dismissible fade show shadow-lg fw-bold" role="alert" style="border: 2px solid #dc3545; font-size: 1.1rem; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; min-width: 320px; text-align: center;">
                            <i class="bi bi-bell-fill fs-4 me-2"></i>새로운 고객 문의가 접수되었습니다!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', alertHtml);

                    setTimeout(() => {
                        const alertEl = document.getElementById(alertId);
                        if (alertEl) {
                            const bsAlert = new bootstrap.Alert(alertEl);
                            bsAlert.close();
                        }
                    }, 3000);
                    
                    // 화면 새로고침 없이 백그라운드에서 문의 목록, 탭 카운트 갱신
                    refreshInquiryList();
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }

    async function refreshInquiryList() {
        try {
            // 현재 주소(필터 조건 포함)를 백그라운드에서 다시 호출
            const response = await fetch(location.href);
            const htmlText = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            
            // 1. 주간 달력 갱신
            const newCal = doc.getElementById('weekCalendar');
            const oldCal = document.getElementById('weekCalendar');
            if (newCal && oldCal) oldCal.innerHTML = newCal.innerHTML;

            // 2. 탭 네비게이션(카운트 뱃지) 갱신
            const newNav = doc.querySelector('.nav.nav-pills.row.g-2');
            const oldNav = document.querySelector('.nav.nav-pills.row.g-2');
            if (newNav && oldNav) oldNav.innerHTML = newNav.innerHTML;
            
            // 3. 문의 목록 영역 갱신
            const newList = doc.getElementById('inquiry-list-container');
            const oldList = document.getElementById('inquiry-list-container');
            if (newList && oldList) {
                oldList.innerHTML = newList.innerHTML;
            } else {
                location.reload();
            }
        } catch (error) {
            console.error('List refresh error:', error);
        }
    }
</script>