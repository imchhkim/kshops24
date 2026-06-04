<<<<<<< HEAD
<?php

/**
 * [뷰] 상점 관리 대시보드 (manage_shop_dashboard.php)
 * - 상점 요약 정보, 관리자 메시지, 상점 상태 등을 표시합니다.
 * - manage_shop.php에서 include되어 실행됩니다.
 */
if (!isset($shop_id)) exit; // 개별 실행 방지

// [데이터 조회 1] 오늘의 방문자 수 (visit_stats 테이블 참조)
$stmt_v = $pdo->prepare("SELECT unique_visitors FROM visit_stats WHERE shop_id = ? AND visit_date = CURDATE()");
$stmt_v->execute([$shop_id]);
$today_visitors = $stmt_v->fetchColumn() ?: 0;

// [데이터 조회 3] 최근 고객 리뷰 3개
$recent_dashboard_reviews = [];

// 기본 이미지 설정 (원하는 이모티콘 파일명으로 변경하세요)
$default_profile_img = '/assets/default_emoticon.png';

try {
    $stmt_r = $pdo->prepare("
        SELECT r.*, c.nickname as customer_name, c.profile_img 
        FROM reviews r
        LEFT JOIN platform_customers c ON r.customer_id = c.id
        WHERE r.shop_id = ?
        ORDER BY r.id DESC LIMIT 3
    ");
    $stmt_r->execute([$shop_id]);
    $recent_dashboard_reviews = $stmt_r->fetchAll();

    // 데이터를 가져온 후, 프로필 이미지가 없는 경우 기본 이미지로 처리
    foreach ($recent_dashboard_reviews as &$review) {
        if (empty($review['profile_img'])) {
            $review['profile_img'] = $default_profile_img;
        }
    }
    // reference 관계를 해제합니다.
    unset($review);
} catch (Exception $e) {
    // 필요에 따라 예외 처리를 기록합니다.}
}

// [데이터 조회 4] 리소스 사용량 및 요금 정책 (이번 달 기준)
$stmt_policy = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'billing_tier_policy'");
$stmt_policy->execute();
$policy_json = $stmt_policy->fetchColumn();
$billing_policy = $policy_json ? json_decode($policy_json, true) : [
    'free_orders' => 300,
    'overage_per_order' => 5,
    'free_disk_mb' => 1024,
    'overage_disk_unit_mb' => 1024,
    'overage_disk_fee' => 100,
    'free_db_mb' => 50,
    'overage_db_unit_mb' => 10,
    'overage_db_fee' => 50
];

$unit_mb = $billing_policy['overage_disk_unit_mb'] ?? 1024;
$disk_fee = $billing_policy['overage_disk_fee'] ?? ($billing_policy['overage_per_gb'] ?? 100);
$db_unit_mb = $billing_policy['overage_db_unit_mb'] ?? 10;
$db_fee = $billing_policy['overage_db_fee'] ?? 50;

// [추가] 내 상점의 개별(커스텀) 한도 조회
$stmt_custom = $pdo->prepare("SELECT custom_free_orders, custom_free_disk_mb FROM shops WHERE id = ?");
$stmt_custom->execute([$shop_id]);
$shop_custom = $stmt_custom->fetch();
$my_free_orders = $shop_custom['custom_free_orders'] !== null ? (int)$shop_custom['custom_free_orders'] : $billing_policy['free_orders'];
$my_free_disk_mb = $shop_custom['custom_free_disk_mb'] !== null ? (int)$shop_custom['custom_free_disk_mb'] : $billing_policy['free_disk_mb'];
// (참고) shops 테이블에 custom_free_db_mb 컬럼이 없을 경우를 대비한 방어 코드
$my_free_db_mb = isset($shop_custom['custom_free_db_mb']) && $shop_custom['custom_free_db_mb'] !== null ? (int)$shop_custom['custom_free_db_mb'] : ($billing_policy['free_db_mb'] ?? 50);

$resources = getShopResourceUsage($pdo, $shop_id);
$current_disk_mb = $resources['disk'] / 1048576;
$current_db_mb = $resources['db'] / 1048576;

$disk_percent = $my_free_disk_mb > 0 ? min(100, ($current_disk_mb / $my_free_disk_mb) * 100) : 0;

$disk_color = $disk_percent >= 90 ? 'bg-danger' : ($disk_percent >= 75 ? 'bg-warning' : 'bg-primary');

$db_percent = $my_free_db_mb > 0 ? min(100, ($current_db_mb / $my_free_db_mb) * 100) : 0;
$db_color = $db_percent >= 90 ? 'bg-danger' : ($db_percent >= 75 ? 'bg-warning' : 'bg-info');

// [데이터 조회 5] 최근 결제 내역 (최대 5건)
$stmt_payments = $pdo->prepare("SELECT * FROM shop_payments WHERE shop_id = ? ORDER BY billing_date DESC, id DESC LIMIT 5");
$stmt_payments->execute([$shop_id]);
$recent_payments = $stmt_payments->fetchAll();

// [데이터 조회 6] 총 미납액 계산 (이번 달 및 과거 연체 건 포함)
$end_of_month_date = date('Y-m-t');
$stmt_unpaid = $pdo->prepare("SELECT SUM(amount) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND expiring_date <= ?");
$stmt_unpaid->execute([$shop_id, $end_of_month_date]);
$total_unpaid = (float)$stmt_unpaid->fetchColumn();

// [데이터 조회 7] 결제 만료 임박 여부 체크
// 1. 현재 사용 가능한 최대 기한 (완납/무료)
$stmt_exp = $pdo->prepare("
    SELECT COALESCE(
               MIN(CASE WHEN max_date >= CURDATE() THEN max_date END),
               MAX(max_date)
           ) 
    FROM (
        SELECT MAX(CAST(NULLIF(expiring_date, '') AS DATE)) as max_date 
        FROM shop_payments 
        WHERE shop_id = ? AND paid IN ('y', 'f') AND pay_type IN ('6months', 'monthly', '4months_free', 'addon', 'etc')
        GROUP BY pay_type
    ) p_sub
");
$stmt_exp->execute([$shop_id]);
$max_exp_date = $stmt_exp->fetchColumn();

// 2. 미납 청구서 중 가장 임박하거나 연체된 기한
$stmt_unpaid_exp = $pdo->prepare("SELECT MIN(CAST(NULLIF(expiring_date, '') AS DATE)) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND pay_type IN ('6months', 'monthly', '4months_free', 'addon', 'etc')");
$stmt_unpaid_exp->execute([$shop_id]);
$min_unpaid_date = $stmt_unpaid_exp->fetchColumn();

$is_expiring_soon = false;
$is_expired = false;
$days_left = null;
$alert_date = $min_unpaid_date ?: $max_exp_date;

if ($alert_date) {
    $diff = (new DateTime(date('Y-m-d')))->diff(new DateTime($alert_date));
    $days_left = (int)$diff->format('%R%a');

    if ($days_left < 0) $is_expired = true;
    elseif ($days_left <= SHOP_STATUS_INACTIVE_SOON_DAYS) $is_expiring_soon = true;
}

// [동적 위젯] 상점 카테고리별 특화 대시보드 경로 설정
$shop_category = $shop['category'] ?: 'fnb';
$cat_dashboard_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/{$shop_category}/admin/manage_shop_{$shop_category}_dashboard.php";
?>

<!-- 최상단 타이틀 -->
<?php echo renderPageHeader('대시보드', 'bi-speedometer2'); ?>

<!-- 공통: 상점 상태 -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 <?php echo ($shop['status'] === 'active') ? 'bg-primary' : 'bg-danger'; ?> text-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-shop-window fs-2 text-white-50 me-2"></i>
                        <small class="text-white-50 fs-6">상점 상태 : </small>
                        <?php echo ($shop['status'] === 'active') ? '운영중' : (($shop['status'] === 'inactive') ? '휴점중' : (($shop['status'] === 'closed') ? '폐점중' : '')); ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- 오늘의 방문자 위젯 -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center h-100">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="bi bi-people-fill text-primary"></i>
                    </div>
                    <span class="text-muted small fw-bold">오늘 방문자 : <?php echo number_format($today_visitors); ?> 명</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- 결제 만료 임박 경고 위젯 -->
<?php if ($is_expired): ?>
    <div class="alert alert-danger shadow-sm border-0 border-start border-4 border-danger box-responsive-between mb-4 rounded-3 p-3" role="alert">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3 flex-grow-1 me-md-4">
            <i class="bi bi-x-octagon-fill fs-2 text-danger"></i>
            <div class="text-center text-md-start">
                <h6 class="fw-bold mb-1 text-danger">납부 기한이 지났거나 상점 이용 기간이 만료되었습니다. (<?= abs($days_left) ?>일 연체)</h6>
                <span class="small text-danger opacity-75">납부 기한이 <strong><?= $alert_date ?></strong>부로 경과되었습니다. 서비스 중단을 막으려면 신속히 미납금을 결제해 주세요.</span>
            </div>
        </div>

        <div class="flex-shrink-0 d-grid d-md-block ms-md-auto">
            <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-danger rounded-pill px-4 py-2 fw-bold shadow-sm text-nowrap">결제 관리</a>
        </div>
    </div>

<?php elseif ($is_expiring_soon): ?>
    <div class="alert alert-warning shadow-sm border-0 border-start border-4 border-warning box-responsive-between mb-4 rounded-3 p-3" role="alert">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3 flex-grow-1 me-md-4">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div class="text-center text-md-start">
                <h6 class="fw-bold mb-1 text-danger">청구서 납부 기한 및 서비스 이용 기간이 만료될 예정입니다. (남은 기간: <?= $days_left ?>일)</h6>
                <span class="small text-dark opacity-75">납부 기한이 <strong><?= $alert_date ?></strong>에 만료됩니다. 서비스가 중단되지 않도록 연장 결제를 진행해 주세요.</span>
            </div>
        </div>

        <div class="flex-shrink-0 d-grid d-md-block ms-md-auto">
            <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-danger rounded-pill px-4 py-2 fw-bold shadow-sm text-nowrap">결제 관리</a>
        </div>
    </div>
<?php endif; ?>

<!-- 카테고리별 위젯 섹션 -->
<div class="row mb-4">
    <div class="col-12">
        <!-- 카테고리 특화 요약 위젯 (F&B: 신규 주문 알림 등) -->
        <?php
        if (file_exists($cat_dashboard_path)) {
            $widget_mode = 'summary';
            include $cat_dashboard_path;
        }
        ?>
    </div>
</div>


<!-- [추가] 최근 고객 리뷰 & 리소스 사용량 위젯 -->
<div class="row g-3 mb-4">

    <!-- [추가] 최근 고객 리뷰 위젯 -->
    <div class="col-12 col-md-4">
        <div class="<?php echo UI_SECTION_CARD; ?> overflow-hidden" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('최근 고객 리뷰', 'bi-star-fill text-warning', [], '<a href="manage_shop.php?pg=manage_shop_review" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">더보기 <i class="bi bi-chevron-right ms-1"></i></a>'); ?>
                <div class="list-group list-group-flush border-top pt-2">
                    <?php if (empty($recent_dashboard_reviews)): ?>
                        <div class="text-center py-4 text-muted small"><i class="bi bi-chat-left-dots d-block fs-3 mb-2 opacity-50"></i>최근 작성된 리뷰가 없습니다.</div>
                        <?php else: foreach ($recent_dashboard_reviews as $rev): ?>
                            <div class="list-group-item p-3 border-bottom-0 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo htmlspecialchars($rev['profile_img'] ?: '/assets/no-logo.png'); ?>" class="rounded-circle shadow-sm" style="width: 24px; height: 24px; object-fit: cover;">
                                        <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($rev['customer_name'] ?: '고객'); ?></span>
                                    </div>
                                    <div class="text-warning" style="font-size: 0.75rem;">
                                        <?php for ($i = 1; $i <= 5; $i++) echo $i <= $rev['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                                    </div>
                                </div>
                                <p class="mb-1 text-secondary small text-truncate" style="max-width: 100%; line-height: 1.4;"><?php echo htmlspecialchars($rev['content']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo substr($rev['created_at'], 0, 10); ?></div>
                                    <?php if (!empty($rev['owner_reply'])): ?><span class="badge bg-light text-primary border" style="font-size: 0.65rem;">답변함</span><?php endif; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 리소스 사용량 모니터링 위젯 -->
    <div class="col-12 col-md-8">
        <div class="<?php echo UI_SECTION_CARD; ?>" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('이번 달 리소스 사용량 (<span class="text-primary">' . date('m월') . '</span>)', 'bi-server text-primary'); ?>

                <!-- 현재 적용 중인 요금 정책 설명 -->
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-secondary mb-3" style="font-size: 1rem;"><i class="bi bi bi-receipt me-1"></i>현재 적용 중인 요금 정책</h6>
                    <div class="row small text-dark">
                        <div class="col-12 mb-1">
                            <!-- [동적 위젯] 카테고리별 리소스 정책 설명 -->
                            <?php
                            if (file_exists($cat_dashboard_path)) {
                                $widget_mode = 'resource_policy';
                                include $cat_dashboard_path;
                            }
                            ?>
                        </div>
                        <div class="col-12 mb-1">
                            <span class="text-muted d-inline-block">* 디스크 용량 :</span> <span class="fw-bold text-primary">기본 <?php echo number_format($my_free_disk_mb); ?>MB</span> 제공
                            <span class="text-danger ms-1"><strong>(초과 시 <?php echo number_format($unit_mb); ?>MB당 ₱<?php echo number_format($disk_fee); ?> 청구)</strong></span>
                        </div>
                        <div class="col-12 mb-1">
                            <span class="text-muted d-inline-block">* DB 용량 :</span> <span class="fw-bold text-primary">기본 <?php echo number_format($my_free_db_mb); ?>MB</span> 제공
                            <span class="text-danger ms-1"><strong>(초과 시 <?php echo number_format($db_unit_mb); ?>MB당 ₱<?php echo number_format($db_fee); ?> 청구)</strong></span>
                        </div>
                    </div>
                    <h6 class="text-secondary mb-2" style="font-size: 0.85rem;"><i class="bi bi-info-circle-fill me-1"></i>매월 1일, 지난달 사용량을 검사하여 요금을 청구합니다.</h6>
                </div>

                <div class="row g-4">
                    <!-- [동적 위젯] 카테고리별 리소스 정책 설명 -->
                    <?php
                    if (file_exists($cat_dashboard_path)) {
                        $widget_mode = 'resource_status';
                        include $cat_dashboard_path;
                    }
                    ?>
                    <!-- 디스크 사용량 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-secondary">디스크 <span class="text-muted fw-normal" style="font-size:0.7rem;">(이미지 등)</span></span>
                            <span class="small fw-bold text-dark"><?php echo number_format($current_disk_mb, 1); ?> / <?php echo number_format($my_free_disk_mb); ?> MB</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar <?php echo $disk_color; ?>" role="progressbar" style="width: <?php echo $disk_percent; ?>%"></div>
                        </div>
                        <?php if ($current_disk_mb > $my_free_disk_mb): ?>
                            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>초과됨 (<?php echo number_format($unit_mb); ?>MB당 <?php echo number_format($disk_fee); ?> PHP 청구 예정)</div>
                        <?php endif; ?>
                    </div>
                    <!-- DB 사용량 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-secondary">DB <span class="text-muted fw-normal" style="font-size:0.7rem;">(텍스트 데이터)</span></span>
                            <span class="small fw-bold text-dark"><?php echo number_format($current_db_mb, 1); ?> / <?php echo number_format($my_free_db_mb); ?> MB</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar <?php echo $db_color; ?>" role="progressbar" style="width: <?php echo $db_percent; ?>%"></div>
                        </div>
                        <?php if ($current_db_mb > $my_free_db_mb): ?>
                            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>초과됨 (<?php echo number_format($db_unit_mb); ?>MB당 <?php echo number_format($db_fee); ?> PHP 청구 예정)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- 최근 결제 정보 위젯 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="<?php echo UI_SECTION_CARD; ?>" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php
                $billing_right_html = '
                <div class="d-flex align-items-center justify-content-center justify-content-md-end w-100 w-md-auto gap-3">
                    ' . ($total_unpaid > 0 ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1 fw-bold">총 미납액: ₱' . number_format($total_unpaid, 2) . '</span>' : '') . '
                    <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">모든 내역 보기 <i class="bi bi-chevron-right ms-1"></i></a>
                </div>';
                echo renderSectionHeader('최근 청구 및 결제 정보', 'bi-credit-card text-success', [], $billing_right_html);
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr class="small text-muted text-center">
                                <th>청구일</th>
                                <th>만료일</th>
                                <th>항목</th>
                                <th>금액</th>
                                <th>납부</th>
                                <th>납부일</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">결제 내역이 없습니다.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $p): ?>
                                    <?php $is_unpaid_target = ($p['paid'] == 'n' && ($p['expiring_date'] ?? '9999-12-31') <= date('Y-m-t')); ?>
                                    <tr class="small align-middle text-center <?= $is_unpaid_target ? 'table-danger' : '' ?>">
                                        <td><?= $p['billing_date'] ?></td>
                                        <td><?= $p['expiring_date'] ?? '-' ?></td>
                                        <td><span class="badge border text-dark fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span></td>
                                        <td class="text-end fw-bold text-success pe-3 pe-md-4">₱<?= number_format($p['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($p['paid'] == 'y'): ?>
                                                <span class="badge bg-success">납부</span>
                                            <?php elseif ($p['paid'] == 'f'): ?>
                                                <span class="badge bg-info">무료</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">미납</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $p['pay_date'] ?? '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 암호 변경 모달 -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="pwChangeForm" onsubmit="handlePasswordChange(event)">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i>비밀번호 변경</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 에러 메시지 출력 영역 -->
                <div id="pw-error-msg" class="alert alert-danger d-none small mb-3 border-0 shadow-sm"></div>

                <div class="alert alert-warning border-0 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>보안 규칙:</strong> 대/소문자 및 숫자 사용해서 6글자 이상 입력해 주세요.
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-primary">현재 비밀번호</label>
                    <input type="password" name="current_password" id="current_password" class="form-control border-primary border-opacity-25" placeholder="기존 비밀번호 입력" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">새 비밀번호</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">비밀번호 확인</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow">암호 변경하기</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // [JS] 수정 모달 실시간 중복 체크 로직
        document.querySelectorAll('.check-dup').forEach(input => {
            input.addEventListener('blur', function() {
                const field = this.name;
                const value = this.value.trim();
                const target = this;

                if (!value) return; // 빈 값은 검사 건너뜀

                fetch(`manage_shop.php?check_field=${field}&value=${encodeURIComponent(value)}`)
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === 'duplicate') {
                            alert('이미 사용되고 있는 정보입니다.');
                            target.value = ''; // 입력값 비우기
                            setTimeout(() => target.focus(), 10); // 다시 포커스
                        }
                    });
            });
        });
    });

    /**
     * [추가] 실시간 전화번호 포맷팅 함수 (JS)
     * - Mobile: 09XX-XXX-XXXX
     * - Landline (Manila): 02-XXXX-XXXX
     * - Landline (Province): 0XX-XXX-XXXX
     */
    function formatPhoneInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';

        if (input.name === 'phone_mobile') {
            // 필리핀 모바일: 0917-123-4567
            if (val.length <= 4) {
                result = val;
            } else if (val.length <= 7) {
                result = val.slice(0, 4) + '-' + val.slice(4);
            } else {
                result = val.slice(0, 4) + '-' + val.slice(4, 7) + '-' + val.slice(7, 11);
            }
        } else {
            // 유선 전화 (랜드라인)
            if (val.startsWith('02')) {
                // 메트로 마닐라 (02-8XXX-XXXX 등 10자리)
                if (val.length <= 2) result = val;
                else if (val.length <= 6) result = val.slice(0, 2) + '-' + val.slice(2);
                else result = val.slice(0, 2) + '-' + val.slice(2, 6) + '-' + val.slice(6, 10);
            } else {
                // 기타 지역 (043-123-4567 등 10~11자리)
                if (val.length <= 3) result = val;
                else if (val.length <= 6) result = val.slice(0, 3) + '-' + val.slice(3);
                else result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 10);
            }
        }
        input.value = result;
    }

    /**
     * [개선] 비밀번호 변경 AJAX 처리
     * - 기존의 alert() 대신 모달 내부 에러 메시지 출력
     */
    async function handlePasswordChange(e) {
        e.preventDefault();

        const form = e.target;
        const errorDiv = document.getElementById('pw-error-msg');
        const pw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

        // 초기화
        errorDiv.classList.add('d-none');
        errorDiv.innerText = '';

        // 클라이언트 측 유효성 검사
        if (!regex.test(pw)) {
            errorDiv.innerText = '비밀번호는 대/소문자 및 숫자 포함 6자 이상이어야 합니다.';
            errorDiv.classList.remove('d-none');
            return;
        }
        if (pw !== confirm) {
            errorDiv.innerText = '새 비밀번호가 서로 일치하지 않습니다.';
            errorDiv.classList.remove('d-none');
            return;
        }

        // 서버 전송 (AJAX)
        const formData = new FormData(form);
        formData.append('update_shop', '1');
        formData.append('ajax_pw_change', '1');

        try {
            const response = await fetch('manage_shop.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                location.reload(); // 성공 시에만 페이지 갱신
            } else {
                // 에러 발생 시 모달창을 유지하고 에러 내용 표시
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
                document.getElementById('current_password').focus();
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    }
</script>
<div class="row g-4">
    <?php
    // [메시지 로드 및 페이징 처리]
    $msg_page = max(1, (int)($_GET['msg_p'] ?? 1));
    $msg_limit = 5;
    $msg_offset = ($msg_page - 1) * $msg_limit;

    // 본사와 주고받은 'message' 타입 중 최상위 부모(parent_id = 0) 메시지 개수 조회
    $stmt_msg_count = $pdo->prepare("SELECT COUNT(*) FROM shop_board WHERE shop_id = ? AND type = 'message' AND parent_id = 0");
    $stmt_msg_count->execute([$shop_id]);
    $total_messages = $stmt_msg_count->fetchColumn();
    $total_msg_pages = ceil($total_messages / $msg_limit) ?: 1;

    // 부모 메시지 데이터 로드
    $stmt_msg = $pdo->prepare("SELECT * FROM shop_board WHERE shop_id = ? AND type = 'message' AND parent_id = 0 ORDER BY created_at DESC LIMIT $msg_limit OFFSET $msg_offset");
    $stmt_msg->execute([$shop_id]);
    $shop_messages = $stmt_msg->fetchAll();

    // 화면에 보여질 부모 메시지들의 답글(Cascade) 데이터 일괄 로드
    $parent_ids = array_column($shop_messages, 'id');
    $replies = [];
    if (!empty($parent_ids)) {
        $in_placeholders = str_repeat('?,', count($parent_ids) - 1) . '?';
        $stmt_replies = $pdo->prepare("SELECT * FROM shop_board WHERE type = 'message' AND parent_id IN ($in_placeholders) ORDER BY created_at ASC");
        $stmt_replies->execute($parent_ids);
        foreach ($stmt_replies->fetchAll() as $r) {
            $replies[$r['parent_id']][] = $r;
        }
    }

    // 상점주 -> 본사 메시지 전송(POST) 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_msg_to_admin') {
        $msg_title = trim($_POST['msg_title']);
        $msg_content = trim($_POST['msg_content']);
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        if (!empty($msg_title) && !empty($msg_content)) {
            $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, ?, 'message', 'shop', ?, ?, NOW())")
                ->execute([$shop_id, $parent_id, $msg_title, $msg_content]);

            // [추가] 상점주가 본사로 메시지를 보냈을 때 슈퍼관리자에게 텔레그램 알림 전송
            if (function_exists('notifyAdminsViaTelegram')) {
                $shop_name = $shop['shop_name'] ?? '알 수 없는 상점';
                $is_reply = ($parent_id > 0) ? "답변" : "새 메시지";
                $tel_msg = "💬 <b>[상점 {$is_reply} 도착]</b>\n\n상점명: {$shop_name} (ID: {$shop_id})\n제목: {$msg_title}\n\n" . strip_tags($msg_content);
                notifyAdminsViaTelegram($pdo, $tel_msg);
            }

            // 전송 완료 후 1페이지로 새로고침
            echo "<script>location.replace('manage_shop.php?pg=manage_shop_dashboard&msg_p=1');</script>";
            exit;
        }
    }
    ?>
    <!-- 공통: 관리자 메시지 보드 -->
    <div class="col-12">
        <div class="<?php echo UI_SECTION_CARD; ?>">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('KShops24 메시지 보드', 'bi-envelope-paper-heart-fill', [], '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" onclick="openMsgNewModal()"><i class="bi bi-send-fill me-1"></i>새 메시지 작성</button>'); ?>
                <div class="list-group list-group-flush flex-grow-1 border-top pt-2">
                    <?php if (empty($shop_messages)): ?>
                        <div class="text-center py-5 text-muted my-auto"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>주고받은 메시지가 없습니다.</div>
                        <?php else: foreach ($shop_messages as $index => $msg): ?>
                            <div class="list-group-item p-4 border-bottom-0 border-top <?php echo ($msg['sender_type'] === 'admin') ? 'bg-light bg-opacity-50' : 'bg-white'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#msg-content-<?php echo $index; ?>">
                                    <div class="d-flex align-items-center">
                                        <?php if ($msg['sender_type'] === 'admin'): ?>
                                            <i class="bi bi-headset text-primary me-3 fs-5"></i>
                                        <?php else: ?>
                                            <i class="bi bi-shop text-success me-3 fs-5"></i>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">
                                                <?php if ($msg['sender_type'] === 'admin'): ?>
                                                    <span class="badge bg-primary me-1" style="font-size: 0.65rem;">본사</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success me-1" style="font-size: 0.65rem;">보냄</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($msg['title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo date('Y.m.d H:i', strtotime($msg['created_at'])); ?></small>
                                        </div>
                                    </div><i class="bi bi-chevron-down text-muted"></i>
                                </div>
                                <div class="collapse <?php echo ($index === 0 || !empty($replies[$msg['id']])) ? 'show' : ''; ?>" id="msg-content-<?php echo $index; ?>">
                                    <div class="bg-white border rounded-3 p-3 mt-2 ms-5 text-secondary small lh-base position-relative">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>

                                        <!-- [추가] Cascade 방식 답글 출력 영역 -->
                                        <?php if (!empty($replies[$msg['id']])): ?>
                                            <div class="ms-3 ms-md-4 mt-3 pt-2 border-top border-light">
                                                <?php foreach ($replies[$msg['id']] as $reply): ?>
                                                    <div class="d-flex align-items-start mb-3">
                                                        <i class="bi bi-arrow-return-right text-muted me-2 mt-2"></i>
                                                        <div class="bg-light border rounded-3 p-3 flex-grow-1 text-secondary small lh-base">
                                                            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                                                                <strong class="text-dark">
                                                                    <?php if ($reply['sender_type'] === 'admin'): ?>
                                                                        <span class="badge bg-primary me-1" style="font-size: 0.65rem;">본사</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-success me-1" style="font-size: 0.65rem;">보냄</span>
                                                                    <?php endif; ?>
                                                                    <?php echo htmlspecialchars($reply['title']); ?>
                                                                </strong>
                                                                <span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i><?php echo date('Y.m.d H:i', strtotime($reply['created_at'])); ?></span>
                                                            </div>
                                                            <div class="text-dark"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- 답글(댓글) 달기 버튼 -->
                                        <div class="text-end mt-3 border-top pt-2">
                                            <button class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-bold" style="font-size: 0.75rem;"
                                                onclick="openMsgReplyModal(this)"
                                                data-id="<?php echo $msg['id']; ?>"
                                                data-title="RE: <?php echo htmlspecialchars($msg['title']); ?>">
                                                <i class="bi bi-reply-fill me-1"></i>답변 남기기
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>

                <!-- 페이징 영역 -->
                <?php if ($total_msg_pages > 1): ?>
                    <div class="card-footer bg-white border-top-0 py-3 mt-auto">
                        <nav aria-label="Message Pagination">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php if ($msg_page > 1): ?>
                                    <li class="page-item"><a class="page-link text-dark shadow-none border-0 ajax-page-link" href="?pg=manage_shop_dashboard&msg_p=<?php echo $msg_page - 1; ?>"><i class="bi bi-chevron-left"></i></a></li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $msg_page - 2); $i <= min($total_msg_pages, $msg_page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $msg_page) ? 'active' : ''; ?>">
                                        <a class="page-link shadow-none mx-1 rounded-circle text-center ajax-page-link <?php echo ($i == $msg_page) ? 'bg-primary border-primary text-white' : 'text-dark border-0'; ?>" style="width: 30px;" href="?pg=manage_shop_dashboard&msg_p=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($msg_page < $total_msg_pages): ?>
                                    <li class="page-item"><a class="page-link text-dark shadow-none border-0 ajax-page-link" href="?pg=manage_shop_dashboard&msg_p=<?php echo $msg_page + 1; ?>"><i class="bi bi-chevron-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 본사 메시지 보내기/답변 모달 -->
    <div class="modal fade" id="sendMsgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4" method="POST" action="manage_shop.php?pg=manage_shop_dashboard">
                <input type="hidden" name="action" value="send_msg_to_admin">
                <input type="hidden" name="parent_id" id="send_msg_parent_id" value="0">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send-fill me-2"></i>본사에 메시지 보내기</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">제목</label>
                        <input type="text" name="msg_title" id="send_msg_title" class="form-control" placeholder="제목을 입력하세요" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark">내용</label>
                        <textarea name="msg_content" id="send_msg_content" class="form-control" rows="6" placeholder="문의사항이나 요청하실 내용을 남겨주세요." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm">메시지 전송하기 <i class="bi bi-arrow-right-circle-fill ms-1"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMsgNewModal() {
            document.getElementById('send_msg_parent_id').value = '0';
            document.getElementById('send_msg_title').value = '';
            document.getElementById('send_msg_content').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sendMsgModal')).show();
        }

        function openMsgReplyModal(btn) {
            const parentId = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            document.getElementById('send_msg_parent_id').value = parentId;
            document.getElementById('send_msg_title').value = title;
            document.getElementById('send_msg_content').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sendMsgModal')).show();

            // 모달이 열린 후 커서를 내용 텍스트에어리어 맨 앞으로 이동
            setTimeout(() => {
                const textarea = document.getElementById('send_msg_content');
                textarea.focus();
            }, 500);
        }
    </script>

=======
<?php

/**
 * [뷰] 상점 관리 대시보드 (manage_shop_dashboard.php)
 * - 상점 요약 정보, 관리자 메시지, 상점 상태 등을 표시합니다.
 * - manage_shop.php에서 include되어 실행됩니다.
 */
if (!isset($shop_id)) exit; // 개별 실행 방지

// [데이터 조회 1] 오늘의 방문자 수 (visit_stats 테이블 참조)
$stmt_v = $pdo->prepare("SELECT unique_visitors FROM visit_stats WHERE shop_id = ? AND visit_date = CURDATE()");
$stmt_v->execute([$shop_id]);
$today_visitors = $stmt_v->fetchColumn() ?: 0;

// [데이터 조회 3] 최근 고객 리뷰 3개
$recent_dashboard_reviews = [];

// 기본 이미지 설정 (원하는 이모티콘 파일명으로 변경하세요)
$default_profile_img = '/assets/default_emoticon.png';

try {
    $stmt_r = $pdo->prepare("
        SELECT r.*, c.nickname as customer_name, c.profile_img 
        FROM reviews r
        LEFT JOIN platform_customers c ON r.customer_id = c.id
        WHERE r.shop_id = ?
        ORDER BY r.id DESC LIMIT 3
    ");
    $stmt_r->execute([$shop_id]);
    $recent_dashboard_reviews = $stmt_r->fetchAll();

    // 데이터를 가져온 후, 프로필 이미지가 없는 경우 기본 이미지로 처리
    foreach ($recent_dashboard_reviews as &$review) {
        if (empty($review['profile_img'])) {
            $review['profile_img'] = $default_profile_img;
        }
    }
    // reference 관계를 해제합니다.
    unset($review);
} catch (Exception $e) {
    // 필요에 따라 예외 처리를 기록합니다.}
}

// [데이터 조회 4] 리소스 사용량 및 요금 정책 (이번 달 기준)
$stmt_policy = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'billing_tier_policy'");
$stmt_policy->execute();
$policy_json = $stmt_policy->fetchColumn();
$billing_policy = $policy_json ? json_decode($policy_json, true) : [
    'free_orders' => 300,
    'overage_per_order' => 5,
    'free_disk_mb' => 1024,
    'overage_disk_unit_mb' => 1024,
    'overage_disk_fee' => 100,
    'free_db_mb' => 50,
    'overage_db_unit_mb' => 10,
    'overage_db_fee' => 50
];

$unit_mb = $billing_policy['overage_disk_unit_mb'] ?? 1024;
$disk_fee = $billing_policy['overage_disk_fee'] ?? ($billing_policy['overage_per_gb'] ?? 100);
$db_unit_mb = $billing_policy['overage_db_unit_mb'] ?? 10;
$db_fee = $billing_policy['overage_db_fee'] ?? 50;

// [추가] 내 상점의 개별(커스텀) 한도 조회
$stmt_custom = $pdo->prepare("SELECT custom_free_orders, custom_free_disk_mb FROM shops WHERE id = ?");
$stmt_custom->execute([$shop_id]);
$shop_custom = $stmt_custom->fetch();
$my_free_orders = $shop_custom['custom_free_orders'] !== null ? (int)$shop_custom['custom_free_orders'] : $billing_policy['free_orders'];
$my_free_disk_mb = $shop_custom['custom_free_disk_mb'] !== null ? (int)$shop_custom['custom_free_disk_mb'] : $billing_policy['free_disk_mb'];
// (참고) shops 테이블에 custom_free_db_mb 컬럼이 없을 경우를 대비한 방어 코드
$my_free_db_mb = isset($shop_custom['custom_free_db_mb']) && $shop_custom['custom_free_db_mb'] !== null ? (int)$shop_custom['custom_free_db_mb'] : ($billing_policy['free_db_mb'] ?? 50);

$resources = getShopResourceUsage($pdo, $shop_id);
$current_disk_mb = $resources['disk'] / 1048576;
$current_db_mb = $resources['db'] / 1048576;

$disk_percent = $my_free_disk_mb > 0 ? min(100, ($current_disk_mb / $my_free_disk_mb) * 100) : 0;

$disk_color = $disk_percent >= 90 ? 'bg-danger' : ($disk_percent >= 75 ? 'bg-warning' : 'bg-primary');

$db_percent = $my_free_db_mb > 0 ? min(100, ($current_db_mb / $my_free_db_mb) * 100) : 0;
$db_color = $db_percent >= 90 ? 'bg-danger' : ($db_percent >= 75 ? 'bg-warning' : 'bg-info');

// [데이터 조회 5] 최근 결제 내역 (최대 5건)
$stmt_payments = $pdo->prepare("SELECT * FROM shop_payments WHERE shop_id = ? ORDER BY billing_date DESC, id DESC LIMIT 5");
$stmt_payments->execute([$shop_id]);
$recent_payments = $stmt_payments->fetchAll();

// [데이터 조회 6] 총 미납액 계산 (이번 달 및 과거 연체 건 포함)
$end_of_month_date = date('Y-m-t');
$stmt_unpaid = $pdo->prepare("SELECT SUM(amount) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND expiring_date <= ?");
$stmt_unpaid->execute([$shop_id, $end_of_month_date]);
$total_unpaid = (float)$stmt_unpaid->fetchColumn();

// [데이터 조회 7] 결제 만료 임박 여부 체크
// 1. 현재 사용 가능한 최대 기한 (완납/무료)
$stmt_exp = $pdo->prepare("
    SELECT COALESCE(
               MIN(CASE WHEN max_date >= CURDATE() THEN max_date END),
               MAX(max_date)
           ) 
    FROM (
        SELECT MAX(CAST(NULLIF(expiring_date, '') AS DATE)) as max_date 
        FROM shop_payments 
        WHERE shop_id = ? AND paid IN ('y', 'f') AND pay_type IN ('6months', 'monthly', '4months_free', 'addon', 'etc')
        GROUP BY pay_type
    ) p_sub
");
$stmt_exp->execute([$shop_id]);
$max_exp_date = $stmt_exp->fetchColumn();

// 2. 미납 청구서 중 가장 임박하거나 연체된 기한
$stmt_unpaid_exp = $pdo->prepare("SELECT MIN(CAST(NULLIF(expiring_date, '') AS DATE)) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND pay_type IN ('6months', 'monthly', '4months_free', 'addon', 'etc')");
$stmt_unpaid_exp->execute([$shop_id]);
$min_unpaid_date = $stmt_unpaid_exp->fetchColumn();

$is_expiring_soon = false;
$is_expired = false;
$days_left = null;
$alert_date = $min_unpaid_date ?: $max_exp_date;

if ($alert_date) {
    $diff = (new DateTime(date('Y-m-d')))->diff(new DateTime($alert_date));
    $days_left = (int)$diff->format('%R%a');

    if ($days_left < 0) $is_expired = true;
    elseif ($days_left <= SHOP_STATUS_INACTIVE_SOON_DAYS) $is_expiring_soon = true;
}

// [동적 위젯] 상점 카테고리별 특화 대시보드 경로 설정
$shop_category = $shop['category'] ?: 'fnb';
$cat_dashboard_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/{$shop_category}/admin/manage_shop_{$shop_category}_dashboard.php";
?>

<!-- 최상단 타이틀 -->
<?php echo renderPageHeader('대시보드', 'bi-speedometer2'); ?>

<!-- 공통: 상점 상태 -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 <?php echo ($shop['status'] === 'active') ? 'bg-primary' : 'bg-danger'; ?> text-white">
            <div class="card-body p-3 d-flex align-items-center">
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-shop-window fs-2 text-white-50 me-2"></i>
                        <small class="text-white-50 fs-6">상점 상태 : </small>
                        <?php echo ($shop['status'] === 'active') ? '운영중' : (($shop['status'] === 'inactive') ? '휴점중' : (($shop['status'] === 'closed') ? '폐점중' : '')); ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- 오늘의 방문자 위젯 -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center h-100">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="bi bi-people-fill text-primary"></i>
                    </div>
                    <span class="text-muted small fw-bold">오늘 방문자 : <?php echo number_format($today_visitors); ?> 명</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- 결제 만료 임박 경고 위젯 -->
<?php if ($is_expired): ?>
    <div class="alert alert-danger shadow-sm border-0 border-start border-4 border-danger box-responsive-between mb-4 rounded-3 p-3" role="alert">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3 flex-grow-1 me-md-4">
            <i class="bi bi-x-octagon-fill fs-2 text-danger"></i>
            <div class="text-center text-md-start">
                <h6 class="fw-bold mb-1 text-danger">납부 기한이 지났거나 상점 이용 기간이 만료되었습니다. (<?= abs($days_left) ?>일 연체)</h6>
                <span class="small text-danger opacity-75">납부 기한이 <strong><?= $alert_date ?></strong>부로 경과되었습니다. 서비스 중단을 막으려면 신속히 미납금을 결제해 주세요.</span>
            </div>
        </div>

        <div class="flex-shrink-0 d-grid d-md-block ms-md-auto">
            <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-danger rounded-pill px-4 py-2 fw-bold shadow-sm text-nowrap">결제 관리</a>
        </div>
    </div>

<?php elseif ($is_expiring_soon): ?>
    <div class="alert alert-warning shadow-sm border-0 border-start border-4 border-warning box-responsive-between mb-4 rounded-3 p-3" role="alert">
        <div class="d-flex flex-column flex-md-row align-items-center gap-3 flex-grow-1 me-md-4">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div class="text-center text-md-start">
                <h6 class="fw-bold mb-1 text-danger">청구서 납부 기한 및 서비스 이용 기간이 만료될 예정입니다. (남은 기간: <?= $days_left ?>일)</h6>
                <span class="small text-dark opacity-75">납부 기한이 <strong><?= $alert_date ?></strong>에 만료됩니다. 서비스가 중단되지 않도록 연장 결제를 진행해 주세요.</span>
            </div>
        </div>

        <div class="flex-shrink-0 d-grid d-md-block ms-md-auto">
            <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-danger rounded-pill px-4 py-2 fw-bold shadow-sm text-nowrap">결제 관리</a>
        </div>
    </div>
<?php endif; ?>

<!-- 카테고리별 위젯 섹션 -->
<div class="row mb-4">
    <div class="col-12">
        <!-- 카테고리 특화 요약 위젯 (F&B: 신규 주문 알림 등) -->
        <?php
        if (file_exists($cat_dashboard_path)) {
            $widget_mode = 'summary';
            include $cat_dashboard_path;
        }
        ?>
    </div>
</div>


<!-- [추가] 최근 고객 리뷰 & 리소스 사용량 위젯 -->
<div class="row g-3 mb-4">

    <!-- [추가] 최근 고객 리뷰 위젯 -->
    <div class="col-12 col-md-4">
        <div class="<?php echo UI_SECTION_CARD; ?> overflow-hidden" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('최근 고객 리뷰', 'bi-star-fill text-warning', [], '<a href="manage_shop.php?pg=manage_shop_review" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">더보기 <i class="bi bi-chevron-right ms-1"></i></a>'); ?>
                <div class="list-group list-group-flush border-top pt-2">
                    <?php if (empty($recent_dashboard_reviews)): ?>
                        <div class="text-center py-4 text-muted small"><i class="bi bi-chat-left-dots d-block fs-3 mb-2 opacity-50"></i>최근 작성된 리뷰가 없습니다.</div>
                        <?php else: foreach ($recent_dashboard_reviews as $rev): ?>
                            <div class="list-group-item p-3 border-bottom-0 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo htmlspecialchars($rev['profile_img'] ?: '/assets/no-logo.png'); ?>" class="rounded-circle shadow-sm" style="width: 24px; height: 24px; object-fit: cover;">
                                        <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($rev['customer_name'] ?: '고객'); ?></span>
                                    </div>
                                    <div class="text-warning" style="font-size: 0.75rem;">
                                        <?php for ($i = 1; $i <= 5; $i++) echo $i <= $rev['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                                    </div>
                                </div>
                                <p class="mb-1 text-secondary small text-truncate" style="max-width: 100%; line-height: 1.4;"><?php echo htmlspecialchars($rev['content']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo substr($rev['created_at'], 0, 10); ?></div>
                                    <?php if (!empty($rev['owner_reply'])): ?><span class="badge bg-light text-primary border" style="font-size: 0.65rem;">답변함</span><?php endif; ?>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 리소스 사용량 모니터링 위젯 -->
    <div class="col-12 col-md-8">
        <div class="<?php echo UI_SECTION_CARD; ?>" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('이번 달 리소스 사용량 (<span class="text-primary">' . date('m월') . '</span>)', 'bi-server text-primary'); ?>

                <!-- 현재 적용 중인 요금 정책 설명 -->
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-secondary mb-3" style="font-size: 1rem;"><i class="bi bi bi-receipt me-1"></i>현재 적용 중인 요금 정책</h6>
                    <div class="row small text-dark">
                        <div class="col-12 mb-1">
                            <!-- [동적 위젯] 카테고리별 리소스 정책 설명 -->
                            <?php
                            if (file_exists($cat_dashboard_path)) {
                                $widget_mode = 'resource_policy';
                                include $cat_dashboard_path;
                            }
                            ?>
                        </div>
                        <div class="col-12 mb-1">
                            <span class="text-muted d-inline-block">* 디스크 용량 :</span> <span class="fw-bold text-primary">기본 <?php echo number_format($my_free_disk_mb); ?>MB</span> 제공
                            <span class="text-danger ms-1"><strong>(초과 시 <?php echo number_format($unit_mb); ?>MB당 ₱<?php echo number_format($disk_fee); ?> 청구)</strong></span>
                        </div>
                        <div class="col-12 mb-1">
                            <span class="text-muted d-inline-block">* DB 용량 :</span> <span class="fw-bold text-primary">기본 <?php echo number_format($my_free_db_mb); ?>MB</span> 제공
                            <span class="text-danger ms-1"><strong>(초과 시 <?php echo number_format($db_unit_mb); ?>MB당 ₱<?php echo number_format($db_fee); ?> 청구)</strong></span>
                        </div>
                    </div>
                    <h6 class="text-secondary mb-2" style="font-size: 0.85rem;"><i class="bi bi-info-circle-fill me-1"></i>매월 1일, 지난달 사용량을 검사하여 요금을 청구합니다.</h6>
                </div>

                <div class="row g-4">
                    <!-- [동적 위젯] 카테고리별 리소스 정책 설명 -->
                    <?php
                    if (file_exists($cat_dashboard_path)) {
                        $widget_mode = 'resource_status';
                        include $cat_dashboard_path;
                    }
                    ?>
                    <!-- 디스크 사용량 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-secondary">디스크 <span class="text-muted fw-normal" style="font-size:0.7rem;">(이미지 등)</span></span>
                            <span class="small fw-bold text-dark"><?php echo number_format($current_disk_mb, 1); ?> / <?php echo number_format($my_free_disk_mb); ?> MB</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar <?php echo $disk_color; ?>" role="progressbar" style="width: <?php echo $disk_percent; ?>%"></div>
                        </div>
                        <?php if ($current_disk_mb > $my_free_disk_mb): ?>
                            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>초과됨 (<?php echo number_format($unit_mb); ?>MB당 <?php echo number_format($disk_fee); ?> PHP 청구 예정)</div>
                        <?php endif; ?>
                    </div>
                    <!-- DB 사용량 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-secondary">DB <span class="text-muted fw-normal" style="font-size:0.7rem;">(텍스트 데이터)</span></span>
                            <span class="small fw-bold text-dark"><?php echo number_format($current_db_mb, 1); ?> / <?php echo number_format($my_free_db_mb); ?> MB</span>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar <?php echo $db_color; ?>" role="progressbar" style="width: <?php echo $db_percent; ?>%"></div>
                        </div>
                        <?php if ($current_db_mb > $my_free_db_mb): ?>
                            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>초과됨 (<?php echo number_format($db_unit_mb); ?>MB당 <?php echo number_format($db_fee); ?> PHP 청구 예정)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- 최근 결제 정보 위젯 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="<?php echo UI_SECTION_CARD; ?>" style="border-radius: 1.25rem;">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php
                $billing_right_html = '
                <div class="d-flex align-items-center justify-content-center justify-content-md-end w-100 w-md-auto gap-3">
                    ' . ($total_unpaid > 0 ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1 fw-bold">총 미납액: ₱' . number_format($total_unpaid, 2) . '</span>' : '') . '
                    <a href="manage_shop.php?pg=manage_shop_billing" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">모든 내역 보기 <i class="bi bi-chevron-right ms-1"></i></a>
                </div>';
                echo renderSectionHeader('최근 청구 및 결제 정보', 'bi-credit-card text-success', [], $billing_right_html);
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-nowrap">
                        <thead class="table-light">
                            <tr class="small text-muted text-center">
                                <th>청구일</th>
                                <th>만료일</th>
                                <th>항목</th>
                                <th>금액</th>
                                <th>납부</th>
                                <th>납부일</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">결제 내역이 없습니다.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $p): ?>
                                    <?php $is_unpaid_target = ($p['paid'] == 'n' && ($p['expiring_date'] ?? '9999-12-31') <= date('Y-m-t')); ?>
                                    <tr class="small align-middle text-center <?= $is_unpaid_target ? 'table-danger' : '' ?>">
                                        <td><?= $p['billing_date'] ?></td>
                                        <td><?= $p['expiring_date'] ?? '-' ?></td>
                                        <td><span class="badge border text-dark fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span></td>
                                        <td class="text-end fw-bold text-success pe-3 pe-md-4">₱<?= number_format($p['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($p['paid'] == 'y'): ?>
                                                <span class="badge bg-success">납부</span>
                                            <?php elseif ($p['paid'] == 'f'): ?>
                                                <span class="badge bg-info">무료</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">미납</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $p['pay_date'] ?? '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 암호 변경 모달 -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="pwChangeForm" onsubmit="handlePasswordChange(event)">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i>비밀번호 변경</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 에러 메시지 출력 영역 -->
                <div id="pw-error-msg" class="alert alert-danger d-none small mb-3 border-0 shadow-sm"></div>

                <div class="alert alert-warning border-0 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>보안 규칙:</strong> 대/소문자 및 숫자 사용해서 6글자 이상 입력해 주세요.
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-primary">현재 비밀번호</label>
                    <input type="password" name="current_password" id="current_password" class="form-control border-primary border-opacity-25" placeholder="기존 비밀번호 입력" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">새 비밀번호</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">비밀번호 확인</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow">암호 변경하기</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // [JS] 수정 모달 실시간 중복 체크 로직
        document.querySelectorAll('.check-dup').forEach(input => {
            input.addEventListener('blur', function() {
                const field = this.name;
                const value = this.value.trim();
                const target = this;

                if (!value) return; // 빈 값은 검사 건너뜀

                fetch(`manage_shop.php?check_field=${field}&value=${encodeURIComponent(value)}`)
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === 'duplicate') {
                            alert('이미 사용되고 있는 정보입니다.');
                            target.value = ''; // 입력값 비우기
                            setTimeout(() => target.focus(), 10); // 다시 포커스
                        }
                    });
            });
        });
    });

    /**
     * [추가] 실시간 전화번호 포맷팅 함수 (JS)
     * - Mobile: 09XX-XXX-XXXX
     * - Landline (Manila): 02-XXXX-XXXX
     * - Landline (Province): 0XX-XXX-XXXX
     */
    function formatPhoneInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';

        if (input.name === 'phone_mobile') {
            // 필리핀 모바일: 0917-123-4567
            if (val.length <= 4) {
                result = val;
            } else if (val.length <= 7) {
                result = val.slice(0, 4) + '-' + val.slice(4);
            } else {
                result = val.slice(0, 4) + '-' + val.slice(4, 7) + '-' + val.slice(7, 11);
            }
        } else {
            // 유선 전화 (랜드라인)
            if (val.startsWith('02')) {
                // 메트로 마닐라 (02-8XXX-XXXX 등 10자리)
                if (val.length <= 2) result = val;
                else if (val.length <= 6) result = val.slice(0, 2) + '-' + val.slice(2);
                else result = val.slice(0, 2) + '-' + val.slice(2, 6) + '-' + val.slice(6, 10);
            } else {
                // 기타 지역 (043-123-4567 등 10~11자리)
                if (val.length <= 3) result = val;
                else if (val.length <= 6) result = val.slice(0, 3) + '-' + val.slice(3);
                else result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 10);
            }
        }
        input.value = result;
    }

    /**
     * [개선] 비밀번호 변경 AJAX 처리
     * - 기존의 alert() 대신 모달 내부 에러 메시지 출력
     */
    async function handlePasswordChange(e) {
        e.preventDefault();

        const form = e.target;
        const errorDiv = document.getElementById('pw-error-msg');
        const pw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

        // 초기화
        errorDiv.classList.add('d-none');
        errorDiv.innerText = '';

        // 클라이언트 측 유효성 검사
        if (!regex.test(pw)) {
            errorDiv.innerText = '비밀번호는 대/소문자 및 숫자 포함 6자 이상이어야 합니다.';
            errorDiv.classList.remove('d-none');
            return;
        }
        if (pw !== confirm) {
            errorDiv.innerText = '새 비밀번호가 서로 일치하지 않습니다.';
            errorDiv.classList.remove('d-none');
            return;
        }

        // 서버 전송 (AJAX)
        const formData = new FormData(form);
        formData.append('update_shop', '1');
        formData.append('ajax_pw_change', '1');

        try {
            const response = await fetch('manage_shop.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                location.reload(); // 성공 시에만 페이지 갱신
            } else {
                // 에러 발생 시 모달창을 유지하고 에러 내용 표시
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
                document.getElementById('current_password').focus();
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    }
</script>
<div class="row g-4">
    <?php
    // [메시지 로드 및 페이징 처리]
    $msg_page = max(1, (int)($_GET['msg_p'] ?? 1));
    $msg_limit = 5;
    $msg_offset = ($msg_page - 1) * $msg_limit;

    // 본사와 주고받은 'message' 타입 중 최상위 부모(parent_id = 0) 메시지 개수 조회
    $stmt_msg_count = $pdo->prepare("SELECT COUNT(*) FROM shop_board WHERE shop_id = ? AND type = 'message' AND parent_id = 0");
    $stmt_msg_count->execute([$shop_id]);
    $total_messages = $stmt_msg_count->fetchColumn();
    $total_msg_pages = ceil($total_messages / $msg_limit) ?: 1;

    // 부모 메시지 데이터 로드
    $stmt_msg = $pdo->prepare("SELECT * FROM shop_board WHERE shop_id = ? AND type = 'message' AND parent_id = 0 ORDER BY created_at DESC LIMIT $msg_limit OFFSET $msg_offset");
    $stmt_msg->execute([$shop_id]);
    $shop_messages = $stmt_msg->fetchAll();

    // 화면에 보여질 부모 메시지들의 답글(Cascade) 데이터 일괄 로드
    $parent_ids = array_column($shop_messages, 'id');
    $replies = [];
    if (!empty($parent_ids)) {
        $in_placeholders = str_repeat('?,', count($parent_ids) - 1) . '?';
        $stmt_replies = $pdo->prepare("SELECT * FROM shop_board WHERE type = 'message' AND parent_id IN ($in_placeholders) ORDER BY created_at ASC");
        $stmt_replies->execute($parent_ids);
        foreach ($stmt_replies->fetchAll() as $r) {
            $replies[$r['parent_id']][] = $r;
        }
    }

    // 상점주 -> 본사 메시지 전송(POST) 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_msg_to_admin') {
        $msg_title = trim($_POST['msg_title']);
        $msg_content = trim($_POST['msg_content']);
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        if (!empty($msg_title) && !empty($msg_content)) {
            $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, ?, 'message', 'shop', ?, ?, NOW())")
                ->execute([$shop_id, $parent_id, $msg_title, $msg_content]);

            // [추가] 상점주가 본사로 메시지를 보냈을 때 슈퍼관리자에게 텔레그램 알림 전송
            if (function_exists('notifyAdminsViaTelegram')) {
                $shop_name = $shop['shop_name'] ?? '알 수 없는 상점';
                $is_reply = ($parent_id > 0) ? "답변" : "새 메시지";
                $tel_msg = "💬 <b>[상점 {$is_reply} 도착]</b>\n\n상점명: {$shop_name} (ID: {$shop_id})\n제목: {$msg_title}\n\n" . strip_tags($msg_content);
                notifyAdminsViaTelegram($pdo, $tel_msg);
            }

            // 전송 완료 후 1페이지로 새로고침
            echo "<script>location.replace('manage_shop.php?pg=manage_shop_dashboard&msg_p=1');</script>";
            exit;
        }
    }
    ?>
    <!-- 공통: 관리자 메시지 보드 -->
    <div class="col-12">
        <div class="<?php echo UI_SECTION_CARD; ?>">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php echo renderSectionHeader('KShops24 메시지 보드', 'bi-envelope-paper-heart-fill', [], '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" onclick="openMsgNewModal()"><i class="bi bi-send-fill me-1"></i>새 메시지 작성</button>'); ?>
                <div class="list-group list-group-flush flex-grow-1 border-top pt-2">
                    <?php if (empty($shop_messages)): ?>
                        <div class="text-center py-5 text-muted my-auto"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>주고받은 메시지가 없습니다.</div>
                        <?php else: foreach ($shop_messages as $index => $msg): ?>
                            <div class="list-group-item p-4 border-bottom-0 border-top <?php echo ($msg['sender_type'] === 'admin') ? 'bg-light bg-opacity-50' : 'bg-white'; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#msg-content-<?php echo $index; ?>">
                                    <div class="d-flex align-items-center">
                                        <?php if ($msg['sender_type'] === 'admin'): ?>
                                            <i class="bi bi-headset text-primary me-3 fs-5"></i>
                                        <?php else: ?>
                                            <i class="bi bi-shop text-success me-3 fs-5"></i>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">
                                                <?php if ($msg['sender_type'] === 'admin'): ?>
                                                    <span class="badge bg-primary me-1" style="font-size: 0.65rem;">본사</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success me-1" style="font-size: 0.65rem;">보냄</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($msg['title']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo date('Y.m.d H:i', strtotime($msg['created_at'])); ?></small>
                                        </div>
                                    </div><i class="bi bi-chevron-down text-muted"></i>
                                </div>
                                <div class="collapse <?php echo ($index === 0 || !empty($replies[$msg['id']])) ? 'show' : ''; ?>" id="msg-content-<?php echo $index; ?>">
                                    <div class="bg-white border rounded-3 p-3 mt-2 ms-5 text-secondary small lh-base position-relative">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>

                                        <!-- [추가] Cascade 방식 답글 출력 영역 -->
                                        <?php if (!empty($replies[$msg['id']])): ?>
                                            <div class="ms-3 ms-md-4 mt-3 pt-2 border-top border-light">
                                                <?php foreach ($replies[$msg['id']] as $reply): ?>
                                                    <div class="d-flex align-items-start mb-3">
                                                        <i class="bi bi-arrow-return-right text-muted me-2 mt-2"></i>
                                                        <div class="bg-light border rounded-3 p-3 flex-grow-1 text-secondary small lh-base">
                                                            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                                                                <strong class="text-dark">
                                                                    <?php if ($reply['sender_type'] === 'admin'): ?>
                                                                        <span class="badge bg-primary me-1" style="font-size: 0.65rem;">본사</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-success me-1" style="font-size: 0.65rem;">보냄</span>
                                                                    <?php endif; ?>
                                                                    <?php echo htmlspecialchars($reply['title']); ?>
                                                                </strong>
                                                                <span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i><?php echo date('Y.m.d H:i', strtotime($reply['created_at'])); ?></span>
                                                            </div>
                                                            <div class="text-dark"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- 답글(댓글) 달기 버튼 -->
                                        <div class="text-end mt-3 border-top pt-2">
                                            <button class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-bold" style="font-size: 0.75rem;"
                                                onclick="openMsgReplyModal(this)"
                                                data-id="<?php echo $msg['id']; ?>"
                                                data-title="RE: <?php echo htmlspecialchars($msg['title']); ?>">
                                                <i class="bi bi-reply-fill me-1"></i>답변 남기기
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>

                <!-- 페이징 영역 -->
                <?php if ($total_msg_pages > 1): ?>
                    <div class="card-footer bg-white border-top-0 py-3 mt-auto">
                        <nav aria-label="Message Pagination">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php if ($msg_page > 1): ?>
                                    <li class="page-item"><a class="page-link text-dark shadow-none border-0 ajax-page-link" href="?pg=manage_shop_dashboard&msg_p=<?php echo $msg_page - 1; ?>"><i class="bi bi-chevron-left"></i></a></li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $msg_page - 2); $i <= min($total_msg_pages, $msg_page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $msg_page) ? 'active' : ''; ?>">
                                        <a class="page-link shadow-none mx-1 rounded-circle text-center ajax-page-link <?php echo ($i == $msg_page) ? 'bg-primary border-primary text-white' : 'text-dark border-0'; ?>" style="width: 30px;" href="?pg=manage_shop_dashboard&msg_p=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($msg_page < $total_msg_pages): ?>
                                    <li class="page-item"><a class="page-link text-dark shadow-none border-0 ajax-page-link" href="?pg=manage_shop_dashboard&msg_p=<?php echo $msg_page + 1; ?>"><i class="bi bi-chevron-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 본사 메시지 보내기/답변 모달 -->
    <div class="modal fade" id="sendMsgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4" method="POST" action="manage_shop.php?pg=manage_shop_dashboard">
                <input type="hidden" name="action" value="send_msg_to_admin">
                <input type="hidden" name="parent_id" id="send_msg_parent_id" value="0">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send-fill me-2"></i>본사에 메시지 보내기</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">제목</label>
                        <input type="text" name="msg_title" id="send_msg_title" class="form-control" placeholder="제목을 입력하세요" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-dark">내용</label>
                        <textarea name="msg_content" id="send_msg_content" class="form-control" rows="6" placeholder="문의사항이나 요청하실 내용을 남겨주세요." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm">메시지 전송하기 <i class="bi bi-arrow-right-circle-fill ms-1"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMsgNewModal() {
            document.getElementById('send_msg_parent_id').value = '0';
            document.getElementById('send_msg_title').value = '';
            document.getElementById('send_msg_content').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sendMsgModal')).show();
        }

        function openMsgReplyModal(btn) {
            const parentId = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            document.getElementById('send_msg_parent_id').value = parentId;
            document.getElementById('send_msg_title').value = title;
            document.getElementById('send_msg_content').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sendMsgModal')).show();

            // 모달이 열린 후 커서를 내용 텍스트에어리어 맨 앞으로 이동
            setTimeout(() => {
                const textarea = document.getElementById('send_msg_content');
                textarea.focus();
            }, 500);
        }
    </script>

>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</div>