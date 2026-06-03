<?php

/**
 * KShops24 부동산 카테고리 대시보드
 * - 문의 내역 요약 등 상점의 주요 현황을 표시합니다.
 */

if (!isset($shop_id)) exit; // 직접 접근 차단

$widget_mode = $widget_mode ?? 'summary';

if ($widget_mode === 'summary') {
    // 1. 통계 데이터 로드
    $stmt_counts = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM shop_inquiries WHERE shop_id = ? GROUP BY status");
    $stmt_counts->execute([$shop_id]);
    $counts = ['all' => 0, 'pending' => 0, 'contacted' => 0, 'completed' => 0, 'cancelled' => 0];

    foreach ($stmt_counts->fetchAll() as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = $row['cnt'];
        }
        $counts['all'] += $row['cnt'];
    }

    // 오늘 들어온 문의 수
    $stmt_today = $pdo->prepare("SELECT COUNT(*) FROM shop_inquiries WHERE shop_id = ? AND DATE(created_at) = CURDATE()");
    $stmt_today->execute([$shop_id]);
    $today_count = $stmt_today->fetchColumn();

    // 2. 최근 문의 내역 조회 (최대 5건)
    $stmt_recent = $pdo->prepare("SELECT * FROM shop_inquiries WHERE shop_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_recent->execute([$shop_id]);
    $recent_inquiries = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    // 상태 UI 맵핑
    $status_map = [
        'pending'   => ['label' => '상담 대기', 'color' => 'bg-warning text-dark', 'icon' => 'bi-hourglass-split'],
        'contacted' => ['label' => '상담 중',   'color' => 'bg-info text-white',   'icon' => 'bi-headset'],
        'completed' => ['label' => '상담 완료', 'color' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
        'cancelled' => ['label' => '상담 취소', 'color' => 'bg-secondary text-white', 'icon' => 'bi-x-circle-fill']
    ];
?>

    <!-- 요약 통계 카드 영역 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-primary bg-gradient text-white">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold m-0 opacity-75">오늘 신규 문의</h6>
                        <i class="bi bi-calendar-event fs-4 opacity-50"></i>
                    </div>
                    <h2 class="fw-bold m-0"><?php echo number_format($today_count); ?> <span class="fs-6 fw-normal opacity-75">건</span></h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-warning bg-gradient text-dark">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold m-0 opacity-75">상담 대기</h6>
                        <i class="bi bi-hourglass-split fs-4 opacity-50"></i>
                    </div>
                    <h2 class="fw-bold m-0"><?php echo number_format($counts['pending']); ?> <span class="fs-6 fw-normal opacity-75">건</span></h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-info bg-gradient text-white">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold m-0 opacity-75">상담 중</h6>
                        <i class="bi bi-headset fs-4 opacity-50"></i>
                    </div>
                    <h2 class="fw-bold m-0"><?php echo number_format($counts['contacted']); ?> <span class="fs-6 fw-normal opacity-75">건</span></h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold m-0 text-muted">총 누적 문의</h6>
                        <i class="bi bi-inboxes text-muted fs-4 opacity-50"></i>
                    </div>
                    <h2 class="fw-bold m-0 text-dark"><?php echo number_format($counts['all']); ?> <span class="fs-6 fw-normal text-muted">건</span></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- 상담 대기 내역 영역 -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center rounded-top-4">
            <h5 class="fw-bold m-0 text-dark"><i class="bi bi-chat-square-text text-primary me-2"></i>상담 대기 내역</h5>
            <a href="manage_shop.php?pg=manage_shop_inquiries" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">모두 보기 <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent_inquiries)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted mb-3" style="font-size: 2.5rem;"></i>
                    <h6 class="fw-bold text-dark m-0">상담 대기가 없습니다.</h6>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush rounded-bottom-4">
                    <?php foreach ($recent_inquiries as $inq):
                        $status = $status_map[$inq['status']] ?? ['label' => '알 수 없음', 'color' => 'bg-light text-dark', 'icon' => 'bi-question'];
                        $items = json_decode($inq['inquiry_data'], true);
                        if (!is_array($items)) $items = [];
                        $item_count = count($items);
                        $first_item_name = $item_count > 0 ? htmlspecialchars($items[0]['name'] ?? '매물명 없음') : '선택된 매물 없음';
                        if ($item_count > 1) {
                            $first_item_name .= " 외 " . ($item_count - 1) . "건";
                        }
                    ?>
                        <div class="list-group-item p-3 p-md-4">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-md-auto">
                                    <span class="badge <?php echo $status['color']; ?> rounded-pill px-3 py-2 fw-bold text-nowrap w-100">
                                        <i class="bi <?php echo $status['icon']; ?> me-1"></i><?php echo $status['label']; ?>
                                    </span>
                                </div>
                                <div class="col-12 col-md flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="fw-bold m-0 text-dark text-truncate" style="max-width: 90%;">
                                            <?php echo $first_item_name; ?>
                                        </h6>
                                        <small class="text-muted text-nowrap ms-2"><?php echo date('m/d H:i', strtotime($inq['created_at'])); ?></small>
                                    </div>
                                    <div class="small text-muted text-truncate mb-2">
                                        <i class="bi bi-person me-1"></i><?php echo function_exists('formatPHPhone') ? formatPHPhone($inq['customer_phone']) : $inq['customer_phone']; ?>
                                    </div>
                                    <div class="p-2 bg-light rounded text-dark small text-truncate">
                                        <i class="bi bi-chat-quote me-1 text-primary"></i><?php echo htmlspecialchars($inq['customer_inquiry']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
} elseif ($widget_mode === 'resource_policy') {
?>
    <!-- 리소스 정책 안내 위젯 -->
<?php
} elseif ($widget_mode === 'resource_status') {
?>
    <!-- 주문 건수 진행률 위젯 -->
<?php
}
?>