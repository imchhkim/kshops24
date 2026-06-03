<?php

/**
 * KShops24 F&B 매출 관리 (manage_shop_sales.php)
 */
if (!isset($shop_id)) exit;

// 1. 날짜 설정 (현재 월 또는 선택된 월)
$target_month = $_GET['month'] ?? date('Y-m');
$year = date('Y', strtotime($target_month));
$month = date('m', strtotime($target_month));

$first_day = "$target_month-01";
$last_day = date('Y-m-t', strtotime($first_day));

// 2. 해당 월의 모든 주문 데이터 로드 (주문완료 기준 매출 집계)
$stmt = $pdo->prepare("
    SELECT o.*, DATE(o.created_at) as order_date, 
           (SELECT c.nickname FROM platform_customers c 
            WHERE REPLACE(c.ph_phone, '-', '') COLLATE utf8mb4_unicode_ci = o.customer_phone COLLATE utf8mb4_unicode_ci 
            ORDER BY c.updated_at DESC, c.id DESC LIMIT 1) AS kakao_nickname 
    FROM shop_orders o 
    WHERE o.shop_id = ? AND o.created_at BETWEEN ? AND ? 
    ORDER BY o.created_at ASC
");
$stmt->execute([$shop_id, $first_day . " 00:00:00", $last_day . " 23:59:59"]);
$all_orders = $stmt->fetchAll();

// 3. 데이터 가공 (일자별 그룹화)
$daily_stats = [];
$monthly_total_sales = 0;
$monthly_total_completed_count = 0;
$monthly_total_all_count = 0;
$monthly_total_cancelled_count = 0;

foreach ($all_orders as $order) {
    $date = $order['order_date'];
    if (!isset($daily_stats[$date])) {
        $daily_stats[$date] = ['completed_count' => 0, 'all_count' => 0, 'cancelled_count' => 0, 'sales' => 0, 'orders' => []];
    }

    $daily_stats[$date]['orders'][] = $order;
    $daily_stats[$date]['all_count']++;
    $monthly_total_all_count++;

    // 'completed'(배송완료) 상태인 경우에만 매출액에 합산
    if ($order['status'] === 'completed') {
        $daily_stats[$date]['completed_count']++;
        $daily_stats[$date]['sales'] += $order['total_price'];
        $monthly_total_sales += $order['total_price'];
        $monthly_total_completed_count++;
    } elseif ($order['status'] === 'cancelled') {
        $daily_stats[$date]['cancelled_count']++;
        $monthly_total_cancelled_count++;
    }
}

// 4. 달력 생성을 위한 변수
$start_weekday = date('w', strtotime($first_day)); // 0(일) ~ 6(토)
$total_days = date('t', strtotime($first_day));
?>

<style>
    .calendar-table {
        table-layout: fixed;
        background: #fff;
        border-radius: 15px;
        overflow: hidden;
    }

    .calendar-table th {
        background: #f8f9fa;
        text-align: center;
        padding: 12px;
        font-size: 0.85rem;
        color: #666;
    }

    .calendar-day {
        height: 100px;
        vertical-align: top;
        padding: 8px !important;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid #f1f1f1 !important;
    }

    .calendar-day:hover {
        background-color: #f0f7ff;
    }

    .calendar-day.today {
        background-color: #fffdf0;
    }

    .calendar-day.active {
        background-color: #e7f1ff;
        border: 2px solid #0d6efd !important;
    }

    .day-num {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: block;
    }

    .day-info {
        font-size: 0.75rem;
        line-height: 1.4;
    }

    .sales-amt {
        color: #0d6efd;
        font-weight: 700;
    }

    .order-cnt {
        color: #666;
    }

    .other-month {
        background: #fafafa;
        color: #ccc;
        cursor: default;
    }

    .detail-section {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="container-fluid p-0">

    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('배달 매출 관리', 'bi-graph-up-arrow'); ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="pg" value="manage_shop_sales">
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo $target_month; ?>" onchange="this.form.submit()">
            </form>
            <div class="btn-group btn-group-sm">
                <a href="?pg=manage_shop_sales&month=<?php echo date('Y-m', strtotime($first_day . ' -1 month')); ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
                <a href="?pg=manage_shop_sales&month=<?php echo date('Y-m', strtotime($first_day . ' +1 month')); ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <!-- 월간 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold">이번 달 총 배달 매출액</small>
                <h3 class="fw-bold mb-0 text-primary">₱ <?php echo number_format($monthly_total_sales); ?></h3>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-info">
                <small class="text-muted fw-bold">이번 달 총 주문 건수</small>
                <h3 class="fw-bold mb-0 text-info"><?php echo number_format($monthly_total_all_count); ?>건</h3>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-success">
                <small class="text-muted fw-bold">이번 달 주문완료 건수</small>
                <h3 class="fw-bold mb-0 text-success"><?php echo number_format($monthly_total_completed_count); ?>건</h3>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-danger">
                <small class="text-muted fw-bold">이번 달 총 주문취소 건수</small>
                <h3 class="fw-bold mb-0 text-danger"><?php echo number_format($monthly_total_cancelled_count); ?>건</h3>
            </div>
        </div>
    </div>

    <!-- 달력 섹션 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table calendar-table mb-0">
                <thead>
                    <tr>
                        <th class="text-danger">일</th>
                        <th>월</th>
                        <th>화</th>
                        <th>수</th>
                        <th>목</th>
                        <th>금</th>
                        <th class="text-primary">토</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // 1일 시작 전 공백
                        for ($i = 0; $i < $start_weekday; $i++) echo '<td class="calendar-day other-month"></td>';

                        for ($d = 1; $d <= $total_days; $d++) {
                            $current_date = sprintf("%s-%02d", $target_month, $d);
                            $is_today = ($current_date === date('Y-m-d')) ? 'today' : '';
                            $data = $daily_stats[$current_date] ?? null;

                            if (($i + $d - 1) % 7 == 0 && $d > 1) echo '</tr><tr>';
                        ?>
                            <td class="calendar-day <?php echo $is_today; ?>" onclick="showDayDetail('<?php echo $current_date; ?>', this)">
                                <span class="day-num"><?php echo $d; ?></span>
                                <?php if ($data): ?>
                                    <div class="day-info">
                                        <div class="sales-amt">₱ <?php echo number_format($data['sales']); ?></div>
                                        <div class="mt-1 d-flex flex-column gap-0" style="font-size: 0.65rem; line-height: 1.2;">
                                            <span class="text-muted">총: <?php echo $data['all_count']; ?>건</span>
                                            <span class="text-success">완: <?php echo $data['completed_count']; ?>건</span>
                                            <span class="text-danger">취: <?php echo $data['cancelled_count']; ?>건</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php
                        }

                        // 마지막 날 이후 공백
                        $remaining = (7 - (($start_weekday + $total_days) % 7)) % 7;
                        for ($i = 0; $i < $remaining; $i++) echo '<td class="calendar-day other-month"></td>';
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 상세 내역 섹션 (클릭 시 노출) -->
    <div id="detail-container">
        <?php foreach ($daily_stats as $date => $data): ?>
            <div id="detail-<?php echo $date; ?>" class="detail-section">
                <div class="card border-0 shadow-sm p-4">
                    <div class="box-responsive-between mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i><?php echo $date; ?> 상세 주문 내역</h6>
                        <span class="badge bg-primary px-3 py-2 ms-md-auto">일 매출액: ₱ <?php echo number_format($data['sales']); ?></span>
                    </div>

                    <!-- 탭 네비게이션 -->
                    <ul class="nav nav-tabs mb-3" id="salesTab-<?php echo $date; ?>" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="completed-tab-<?php echo $date; ?>" data-bs-toggle="tab" data-bs-target="#completed-pane-<?php echo $date; ?>" type="button" role="tab">
                                주문완료 <span class="badge bg-success ms-1"><?php echo $data['completed_count']; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-dark" id="cancelled-tab-<?php echo $date; ?>" data-bs-toggle="tab" data-bs-target="#cancelled-pane-<?php echo $date; ?>" type="button" role="tab">
                                주문취소 <span class="badge bg-danger ms-1"><?php echo $data['cancelled_count']; ?></span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- 주문완료 탭 -->
                        <div class="tab-pane fade show active" id="completed-pane-<?php echo $date; ?>" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle small mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>시간</th>
                                            <th>주문번호</th>
                                            <th>고객정보</th>
                                            <th>금액</th>
                                            <th>상태</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $completed_orders = array_filter($data['orders'], fn($o) => $o['status'] === 'completed');
                                        if (empty($completed_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">주문완료된 주문이 없습니다.</td>
                                            </tr>
                                            <?php else:
                                            foreach ($completed_orders as $ord):
                                                global $FNB_ORDER_STATUS;
                                                $status_info = $FNB_ORDER_STATUS[$ord['status']] ?? ['text' => '알수없음', 'class' => 'secondary'];
                                            ?>
                                                <tr>
                                                    <td><?php echo date('H:i', strtotime($ord['created_at'])); ?></td>
                                                    <td class="fw-bold text-dark"><?php echo $ord['order_no']; ?></td>
                                                    <td>
                                                        <?php if (!empty($ord['kakao_nickname'])): ?>
                                                            <div class="fw-bold mb-1" style="color: #3A1D1D;"><i class="bi bi-chat-fill me-1" style="color: #F8D300; -webkit-text-stroke: 1px #3A1D1D;"></i><?php echo htmlspecialchars($ord['kakao_nickname']); ?></div>
                                                        <?php endif; ?>
                                                        <div class="fw-bold mb-1"><i class="bi bi-telephone text-muted me-1"></i><?php echo function_exists('formatPHPhone') ? htmlspecialchars(formatPHPhone($ord['customer_phone'])) : htmlspecialchars($ord['customer_phone']); ?></div>
                                                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($ord['customer_address']); ?></div>
                                                        <?php if (!empty($ord['customer_landmark'])): ?>
                                                            <div class="small text-primary mt-1"><i class="bi bi-flag me-1"></i><?php echo htmlspecialchars($ord['customer_landmark']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-primary fs-6">₱ <?php echo number_format($ord['total_price']); ?></td>
                                                    <td><span class="badge bg-<?php echo $status_info['class']; ?>"><i class="bi bi-check-circle me-1"></i><?php echo $status_info['text']; ?></span></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 주문취소 탭 -->
                        <div class="tab-pane fade" id="cancelled-pane-<?php echo $date; ?>" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle small mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>시간</th>
                                            <th>주문번호</th>
                                            <th>고객정보</th>
                                            <th>금액</th>
                                            <th>취소사유</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $cancelled_orders = array_filter($data['orders'], fn($o) => $o['status'] === 'cancelled');
                                        if (empty($cancelled_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">취소된 주문이 없습니다.</td>
                                            </tr>
                                            <?php else:
                                            foreach ($cancelled_orders as $ord):
                                            ?>
                                                <tr>
                                                    <td><?php echo date('H:i', strtotime($ord['created_at'])); ?></td>
                                                    <td class="fw-bold text-muted text-decoration-line-through"><?php echo $ord['order_no']; ?></td>
                                                    <td class="opacity-75">
                                                        <?php if (!empty($ord['kakao_nickname'])): ?>
                                                            <div class="fw-bold mb-1" style="color: #3A1D1D;"><i class="bi bi-chat-fill me-1" style="color: #F8D300; -webkit-text-stroke: 1px #3A1D1D;"></i><?php echo htmlspecialchars($ord['kakao_nickname']); ?></div>
                                                        <?php endif; ?>
                                                        <div class="fw-bold mb-1"><i class="bi bi-telephone text-muted me-1"></i><?php echo function_exists('formatPHPhone') ? htmlspecialchars(formatPHPhone($ord['customer_phone'])) : htmlspecialchars($ord['customer_phone']); ?></div>
                                                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($ord['customer_address']); ?></div>
                                                        <?php if (!empty($ord['customer_landmark'])): ?>
                                                            <div class="small text-primary mt-1"><i class="bi bi-flag me-1"></i><?php echo htmlspecialchars($ord['customer_landmark']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-muted text-decoration-line-through">₱ <?php echo number_format($ord['total_price']); ?></td>
                                                    <td>
                                                        <span class="badge bg-danger mb-1"><i class="bi bi-x-circle me-1"></i>주문취소</span>
                                                        <div class="small text-danger fw-bold mt-1"><?php echo htmlspecialchars($ord['cancel_reason'] ?: '사유 없음'); ?></div>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- 데이터 없는 날짜 클릭 시 안내 -->
        <div id="detail-empty" class="card border-0 shadow-sm p-5 text-center text-muted" style="display:none;">
            <i class="bi bi-inbox fs-1 mb-2"></i>
            <p class="mb-0">해당 날짜에는 주문 내역이 없습니다.</p>
        </div>
    </div>
</div>

<script>
    /**
     * 일자별 상세 내역 표시
     */
    function showDayDetail(date, el) {
        // 모든 상세 섹션 숨기기
        document.querySelectorAll('.detail-section').forEach(sec => sec.style.display = 'none');
        document.getElementById('detail-empty').style.display = 'none';

        // 모든 달력 셀 선택 해제
        document.querySelectorAll('.calendar-day').forEach(td => td.classList.remove('active'));

        // 현재 셀 선택 표시
        el.classList.add('active');

        const target = document.getElementById('detail-' + date);
        if (target) {
            target.style.display = 'block';
            // 상세 내역으로 스크롤 이동
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        } else {
            document.getElementById('detail-empty').style.display = 'block';
        }
    }
</script>