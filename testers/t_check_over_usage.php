<?php

/**
 * [하위 파일] 추가 용량 초과 과금 시뮬레이터
 * 위치: /public_html/testers/t_check_over_usage.php
 * 역할: 특정 상점 ID를 입력하면 무료 리소스(주문수, 디스크)의 2배를 사용했다고 가정하고 초과 요금을 청구함.
 */

require_once __DIR__ . '/t_common.php';

$result_html = "";
$shop_id_input = $_POST['shop_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($shop_id_input)) {
    $shop_id = (int)$shop_id_input;

    try {
        // 1. 대상 상점 조회
        $stmt_shop = $pdo->prepare("SELECT * FROM shops WHERE id = ? AND status IN ('active', 'owner_inactive', 'inactive_soon', 'inactive')");
        $stmt_shop->execute([$shop_id]);
        $shop = $stmt_shop->fetch();

        if (!$shop) {
            throw new Exception("해당 상점은 과금 대상 상태(운영, 휴점 등)가 아니거나 찾을 수 없습니다.");
        }

        // 2. 통합 요금 정책 조회
        $stmt_policy = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'billing_tier_policy'");
        $stmt_policy->execute();
        $policy_json = $stmt_policy->fetchColumn();

        $policy = $policy_json ? json_decode($policy_json, true) : [
            'free_orders' => 300,
            'overage_per_order' => 5,
            'free_disk_mb' => 1024,
            'overage_disk_unit_mb' => 1024,
            'overage_disk_fee' => 100,
            'free_db_mb' => 50,
            'overage_db_unit_mb' => 10,
            'overage_db_fee' => 50
        ];

        $unit_mb = (int)($policy['overage_disk_unit_mb'] ?? 1024) ?: 1024;
        $disk_fee_per_unit = (int)($policy['overage_disk_fee'] ?? 100);

        $db_unit_mb = (int)($policy['overage_db_unit_mb'] ?? 10) ?: 10;
        $db_fee_per_unit = (int)($policy['overage_db_fee'] ?? 50);

        // 3. 실제 적용 한도 계산 (커스텀 혜택 우선)
        $limit_orders = isset($shop['custom_free_orders']) && $shop['custom_free_orders'] !== null ? (int)$shop['custom_free_orders'] : (int)($policy['free_orders'] ?? 300);
        $limit_disk = isset($shop['custom_free_disk_mb']) && $shop['custom_free_disk_mb'] !== null ? (int)$shop['custom_free_disk_mb'] : (int)($policy['free_disk_mb'] ?? 1024);
        $limit_db = isset($shop['custom_free_db_mb']) && $shop['custom_free_db_mb'] !== null ? (int)$shop['custom_free_db_mb'] : (int)($policy['free_db_mb'] ?? 50);

        // 4. 무료 제공량의 "2배" 사용 시뮬레이션 (한도가 0일 경우 기본 설정량 사용 가정)
        $sim_orders = $limit_orders > 0 ? $limit_orders * 2 : 100;
        $sim_disk = $limit_disk > 0 ? $limit_disk * 2 : 1024;
        $sim_db = $limit_db > 0 ? $limit_db * 2 : 50;

        $total_overage_fee = 0;
        $billing_notes = [];

        // 5. 초과 요금 계산
        if ($sim_orders > $limit_orders) {
            $excess_orders = $sim_orders - $limit_orders;
            $order_fee = $excess_orders * $policy['overage_per_order'];
            $total_overage_fee += $order_fee;
            $billing_notes[] = "가상 시뮬레이션 (주문건수 초과): {$excess_orders}건 (+{$order_fee} PHP)";
        }

        if ($sim_disk > $limit_disk) {
            $excess_mb = $sim_disk - $limit_disk;
            $excess_units = ceil($excess_mb / $unit_mb);
            $disk_fee = $excess_units * $disk_fee_per_unit;
            $total_overage_fee += $disk_fee;
            $billing_notes[] = "가상 시뮬레이션 (디스크 용량 초과): {$excess_mb}MB (+{$disk_fee} PHP)";
        }

        if ($sim_db > $limit_db) {
            $excess_db_mb = $sim_db - $limit_db;
            $excess_db_units = ceil($excess_db_mb / $db_unit_mb);
            $db_fee = $excess_db_units * $db_fee_per_unit;
            $total_overage_fee += $db_fee;
            $billing_notes[] = "가상 시뮬레이션 (DB 용량 초과): {$excess_db_mb}MB (+{$db_fee} PHP)";
        }

        // 6. 청구서(결제 내역) 삽입
        if ($total_overage_fee > 0) {
            $target_month = date('Y-m');
            $note_str = "[{$target_month} 테스트 초과 사용료]\n" . implode("\n", $billing_notes);

            recordShopPayment(
                $pdo,
                $shop_id,
                'addon', // 초과 요금 항목
                $total_overage_fee,
                $note_str,
                'n', // 미납 상태
                date('Y-m-d'), // 청구일: 오늘
                date('Y-m-t')  // 만료일: 이번 달 말일
            );

            $result_html = "<div class='alert alert-success shadow-sm border-start border-4 border-success mt-4'>
                <h5 class='fw-bold'><i class='bi bi-check-circle-fill me-2'></i>가상 과금 시뮬레이션 완료</h5>
                <hr>
                <p class='mb-2'>적용 상점: <strong>{$shop['shop_name']}</strong> (ID: {$shop_id})</p>
                <ul class='mb-3 text-secondary'>
                    <li><strong>주문 건수 한도:</strong> {$limit_orders}건 &rarr; <strong class='text-danger'>가상 사용량: {$sim_orders}건</strong></li>
                    <li><strong>디스크 한도:</strong> {$limit_disk}MB &rarr; <strong class='text-danger'>가상 사용량: {$sim_disk}MB</strong></li>
                    <li><strong>DB 한도:</strong> {$limit_db}MB &rarr; <strong class='text-danger'>가상 사용량: {$sim_db}MB</strong></li>
                </ul>
                <h4 class='text-danger fw-bold'>총 청구 요금: ₱ " . number_format($total_overage_fee) . "</h4>
                <div class='mt-3 p-2 bg-light rounded text-muted small'><i class='bi bi-info-circle me-1'></i>이 상점의 '결제/수납 관리' 탭에서 미납 처리된 [추가요금(addon)] 항목을 확인할 수 있습니다.</div>
            </div>";
        } else {
            $result_html = "<div class='alert alert-warning mt-4'><i class='bi bi-exclamation-triangle-fill me-2'></i>초과 요금이 발생하지 않는 한도 설정입니다.</div>";
        }
    } catch (Exception $e) {
        $result_html = "<div class='alert alert-danger mt-4'><i class='bi bi-x-octagon-fill me-2'></i>" . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>추가 용량 초과 과금 테스트</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="bg-light p-4">
    <div class="card shadow-sm border-start border-5 border-primary mb-4">
        <div class="card-body">
            <h5 class="fw-bold text-primary"><i class="bi bi-cash-coin me-2"></i>추가 용량(리소스) 초과 과금 테스트</h5>
            <p class="text-muted small mb-0">상점 ID를 입력하고 실행하면, 해당 상점이 <strong>무료 한도의 2배</strong>를 사용했다고 가상으로 계산하여 이번 달 말일 만료 조건으로 'addon(추가요금)' 청구서를 발행합니다. (실제 DB에 결제 내역이 쌓이므로 주의하세요)</p>
        </div>
    </div>
    <form method="POST" class="d-flex gap-2" style="max-width: 600px;">
        <h5 class="fw-bold text-primary">상점 ID</h5>
        <input type="number" name="shop_id" class="form-control" placeholder="상점 ID (숫자)" value="<?= htmlspecialchars($shop_id_input) ?>" required>
        <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">시뮬레이션 실행</button>
    </form>
    <?= $result_html ?>
</body>

</html>