<?php

/**
 * KShops24 F&B 카테고리 특화 대시보드 위젯 (manage_shop_fnb_dashboard.php)
 * - manage_shop_dashboard.php에서 카테고리별로 동적으로 include 됩니다.
 * - $widget_mode 변수에 따라 요약 위젯, 리소스 정책, 리소스 현황 UI를 렌더링합니다.
 */
if (!isset($shop_id)) exit;

$widget_mode = $widget_mode ?? 'summary';

if ($widget_mode === 'summary') {
    // [데이터 조회] 신규 주문 건수 ('pending' 상태)
    $stmt_o = $pdo->prepare("SELECT COUNT(*) FROM shop_orders WHERE shop_id = ? AND status = 'pending'");
    $stmt_o->execute([$shop_id]);
    $new_orders_count = $stmt_o->fetchColumn() ?: 0;

    // [데이터 조회] 주문 통계 (오늘, 어제, 최근 N일)
    $period_days = 7; // 최근 일수 설정 (수정 가능)
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $start_date = date('Y-m-d', strtotime("-" . ($period_days - 1) . " days"));

    $stmt_stats = $pdo->prepare("
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_sales
        FROM shop_orders 
        WHERE shop_id = ? AND created_at >= ?
        GROUP BY DATE(created_at)
    ");
    $stmt_stats->execute([$shop_id, $start_date . ' 00:00:00']);
    $stats_data = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

    $stat_today = ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'sales' => 0];
    $stat_yesterday = ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'sales' => 0];
    $stat_period = ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'sales' => 0];

    foreach ($stats_data as $row) {
        $d = $row['order_date'];

        // 오늘 현황
        if ($d === $today) {
            $stat_today['total'] = $row['total_count'];
            $stat_today['completed'] = $row['completed_count'];
            $stat_today['cancelled'] = $row['cancelled_count'];
            $stat_today['sales'] = $row['total_sales'];
        }

        // 어제 현황
        if ($d === $yesterday) {
            $stat_yesterday['total'] = $row['total_count'];
            $stat_yesterday['completed'] = $row['completed_count'];
            $stat_yesterday['cancelled'] = $row['cancelled_count'];
            $stat_yesterday['sales'] = $row['total_sales'];
        }

        // 지난 N일 (오늘 포함 전체 기간 누적)
        $stat_period['total'] += $row['total_count'];
        $stat_period['completed'] += $row['completed_count'];
        $stat_period['cancelled'] += $row['cancelled_count'];
        $stat_period['sales'] += $row['total_sales'];
    }
?>
    <style>
        .stat-click-row {
            cursor: pointer;
            transition: background-color 0.2s;
            padding: 4px 8px;
            margin: 0 -8px;
            border-radius: 6px;
        }

        .stat-click-row:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
    </style>

    <div class="row w-100 m-0 g-3">
        <!-- 오늘 주문 현황 -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1.25rem;">
                <div class="card-body p-3 d-flex flex-column">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-event me-2 text-primary"></i>오늘 주문 현황</h6>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $today; ?>'"><span class="text-muted">총 주문</span><span class="fw-bold"><?php echo number_format($stat_today['total']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $today; ?>&status=completed'"><span class="text-muted">배달 완료</span><span class="fw-bold text-success"><?php echo number_format($stat_today['completed']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $today; ?>&status=cancelled'"><span class="text-muted">주문 취소</span><span class="fw-bold text-danger"><?php echo number_format($stat_today['cancelled']); ?>건</span></div>
                    <hr class="my-2 border-secondary border-opacity-25 mt-auto">
                    <div class="d-flex justify-content-between align-items-center px-1"><span class="text-muted fw-bold small">매출액</span><span class="fw-bold text-primary fs-5">₱ <?php echo number_format($stat_today['sales']); ?></span></div>
                </div>
            </div>
        </div>
        <!-- 어제 주문 현황 -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1.25rem;">
                <div class="card-body p-3 d-flex flex-column">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-minus me-2 text-secondary"></i>어제 주문 현황</h6>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $yesterday; ?>'"><span class="text-muted">총 주문</span><span class="fw-bold"><?php echo number_format($stat_yesterday['total']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $yesterday; ?>&status=completed'"><span class="text-muted">배달 완료</span><span class="fw-bold text-success"><?php echo number_format($stat_yesterday['completed']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=<?php echo $yesterday; ?>&status=cancelled'"><span class="text-muted">주문 취소</span><span class="fw-bold text-danger"><?php echo number_format($stat_yesterday['cancelled']); ?>건</span></div>
                    <hr class="my-2 border-secondary border-opacity-25 mt-auto">
                    <div class="d-flex justify-content-between align-items-center px-1"><span class="text-muted fw-bold small">매출액</span><span class="fw-bold text-dark fs-5">₱ <?php echo number_format($stat_yesterday['sales']); ?></span></div>
                </div>
            </div>
        </div>

        <!-- 최근 N일 주문 현황 -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1.25rem;">
                <div class="card-body p-3 d-flex flex-column">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-week me-2 text-info"></i>최근 <?php echo $period_days; ?>일 현황</h6>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date='"><span class="text-muted">총 주문</span><span class="fw-bold"><?php echo number_format($stat_period['total']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-1 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=&status=completed'"><span class="text-muted">배달 완료</span><span class="fw-bold text-success"><?php echo number_format($stat_period['completed']); ?>건</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small stat-click-row" onclick="location.href='manage_shop.php?pg=manage_shop_orders&search_date=&status=cancelled'"><span class="text-muted">주문 취소</span><span class="fw-bold text-danger"><?php echo number_format($stat_period['cancelled']); ?>건</span></div>
                    <hr class="my-2 border-secondary border-opacity-25 mt-auto">
                    <div class="d-flex justify-content-between align-items-center px-1"><span class="text-muted fw-bold small">매출액</span><span class="fw-bold text-info fs-5">₱ <?php echo number_format($stat_period['sales']); ?></span></div>
                </div>
            </div>
        </div>
    </div>
<?php
} elseif ($widget_mode === 'resource_policy') {
?>
    <!-- 리소스 정책 안내 위젯 -->
    <div class="col-md-12 mb-2 mb-md-0">
        <span class="text-muted d-inline-block">* 주문 건수 :</span> <span class="fw-bold text-primary">매월 <?php echo number_format($my_free_orders); ?>건</span> 무료 제공
        <span class="text-danger ms-1"><strong>(초과 시 1건당 ₱<?php echo number_format($billing_policy['overage_per_order']); ?> 추가 요금 청구)</strong></span>
    </div>
<?php
} elseif ($widget_mode === 'resource_status') {
    // [데이터 조회] 이번 달 성공적으로 완료된 주문 건수 (completed 상태)
    $start_of_month = date('Y-m-01 00:00:00');
    $end_of_month = date('Y-m-t 23:59:59');
    $stmt_cur_orders = $pdo->prepare("
        SELECT COUNT(*) FROM shop_orders 
        WHERE shop_id = ? AND created_at >= ? AND created_at <= ? 
        AND status = 'completed'
    ");
    $stmt_cur_orders->execute([$shop_id, $start_of_month, $end_of_month]);
    $current_order_count = (int)$stmt_cur_orders->fetchColumn();

    $order_percent = $my_free_orders > 0 ? min(100, ($current_order_count / $my_free_orders) * 100) : 0;
    $order_color = $order_percent >= 90 ? 'bg-danger' : ($order_percent >= 75 ? 'bg-warning' : 'bg-success');
?>
    <!-- 주문 건수 진행률 위젯 -->
    <div class="col-md-12">
        <div class="d-flex justify-content-between mb-1">
            <span class="small fw-bold text-secondary">성공적으로 완료된 주문 건수</span>
            <span class="small fw-bold text-dark"><?php echo number_format($current_order_count); ?> / <?php echo number_format($my_free_orders); ?> 건</span>
        </div>
        <div class="progress" style="height: 10px; border-radius: 10px;">
            <div class="progress-bar <?php echo $order_color; ?>" role="progressbar" style="width: <?php echo $order_percent; ?>%"></div>
        </div>
        <?php if ($current_order_count > $my_free_orders): ?>
            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>초과됨 (건당 <?php echo $billing_policy['overage_per_order']; ?> PHP 청구 예정)</div>
        <?php endif; ?>
    </div>
<?php
}
?>