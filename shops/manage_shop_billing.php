<<<<<<< HEAD
<?php

/**
 * KShops24 상점 결제 내역 상세 보기 (manage_shop_billing.php)
 * - 역할: 상점주가 자신의 과거 및 현재 결제, 청구 내역을 전체 조회하는 전용 페이지
 * - 실행: manage_shop.php 컨테이너 내에서 include 되어 실행됨
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// [추가] 결제 완료 알림 폼 제출 처리 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payment_notice') {
    $payment_id = (int)$_POST['payment_id'];
    $is_paid = isset($_POST['is_paid']) ? 'Y' : 'N';
    $pay_date = trim($_POST['pay_date']);
    $pay_method = trim($_POST['pay_method']);
    if ($pay_method === '기타') {
        $pay_method_etc = trim($_POST['pay_method_etc']);
        $pay_method = "기타 ({$pay_method_etc})";
    }
    $message_text = trim($_POST['message_text']);

    // 결제 항목 정보 조회
    $stmt_pay = $pdo->prepare("SELECT * FROM shop_payments WHERE id = ? AND shop_id = ?");
    $stmt_pay->execute([$payment_id, $shop_id]);
    $pay_info = $stmt_pay->fetch();

    if ($pay_info) {
        global $pay_type_labels;
        $p_type_label = $pay_type_labels[$pay_info['pay_type']] ?? $pay_info['pay_type'];
        $p_amount = number_format($pay_info['amount'], 2);

        $title = "결제 완료 알림: [{$p_type_label}] ₱{$p_amount}";
        $content = "■ 결제(송금) 완료 내역 알림\n";
        $content .= "- 청구 항목: {$p_type_label}\n";
        $content .= "- 청구 금액: ₱{$p_amount}\n";
        $content .= "- 납부 확인(상점주): " . ($is_paid == 'Y' ? '납부 완료' : '미납/확인요망') . "\n";
        $content .= "- 결제일: {$pay_date}\n";
        $content .= "- 결제 방식: {$pay_method}\n\n";
        $content .= "■ 전달 메시지:\n{$message_text}";

        $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, 0, 'message', 'shop', ?, ?, NOW())")->execute([$shop_id, $title, $content]);
        $pdo->prepare("UPDATE shop_payments SET is_noticed = 1 WHERE id = ?")->execute([$payment_id]);

        if (function_exists('notifyAdminsViaTelegram')) notifyAdminsViaTelegram($pdo, "💸 <b>[결제 완료 알림 도착]</b>\n\n상점 ID: {$shop_id}\n{$content}");

        echo "<script>alert('결제 완료 알림이 본사로 성공적으로 전달되었습니다.'); location.replace('manage_shop.php?pg=manage_shop_billing');</script>";
        exit;
    }
}

// 1. 페이징 설정
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 15;
$offset = ($page - 1) * $limit;

// 2. 전체 건수 조회 및 페이징 계산
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM shop_payments WHERE shop_id = ?");
$stmt_count->execute([$shop_id]);
$total_payments = $stmt_count->fetchColumn();
$total_pages = ceil($total_payments / $limit) ?: 1;

// 3. 결제 데이터 조회 (최신 청구일 순)
$stmt = $pdo->prepare("SELECT * FROM shop_payments WHERE shop_id = ? ORDER BY billing_date DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$shop_id]);
$payments = $stmt->fetchAll();

// 4. 미납 금액 총합 조회 (이번 달 및 과거 연체 건 포함)
$end_of_month_date = date('Y-m-t');
$stmt_unpaid = $pdo->prepare("SELECT SUM(amount) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND expiring_date <= ?");
$stmt_unpaid->execute([$shop_id, $end_of_month_date]);
$total_unpaid = (float)$stmt_unpaid->fetchColumn();
?>

<div class="container-fluid p-0">
    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('결제 관리', 'bi-credit-card', '<span class="badge bg-white text-secondary border shadow-sm px-3 py-2 rounded-pill">총 <span class="text-primary">' . number_format($total_payments) . '</span>건</span>'); ?>

    <!-- 총 미납액 요약 위젯 -->
    <?php if ($total_unpaid > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="<?php echo UI_SECTION_CARD; ?> bg-danger bg-opacity-10 border-start border-4 border-danger">
                    <div class="p-3 p-md-4 d-flex flex-column h-100 box-responsive-between flex-md-row">

                        <div class="mb-0">
                            <h6 class="fw-bold text-danger mb-1">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>총 미납 금액
                            </h6>
                            <span class="small text-danger opacity-75">
                                이번 달까지 납부해야 할 (연체 포함) 미납 청구 금액의 합계입니다.
                            </span>
                        </div>

                        <h3 class="fw-bold text-danger m-0 text-nowrap">
                            ₱ <?php echo number_format($total_unpaid, 2); ?>
                        </h3>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?php echo UI_SECTION_CARD; ?> overflow-hidden">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php echo renderSectionHeader('결제 상세 내역', 'bi-receipt'); ?>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle mb-0" style="min-width: 700px; table-layout: fixed;">
                    <thead class="table-light">
                        <tr class="small text-muted text-center">
                            <th class="py-3" style="width: 100px;">청구일</th>
                            <th class="py-3" style="width: 100px;">만료일</th>
                            <th class="py-3" style="width: 110px;">항목</th>
                            <th class="py-3 text-end" style="width: 110px;">금액</th>
                            <th class="py-3" style="width: 90px;">납부 상태</th>
                            <th class="py-3" style="width: 100px;">납부일</th>
                            <th class="py-3 text-start" style="width: 300px;">비고</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                                    결제 내역이 없습니다.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <?php
                                $is_unpaid_target = ($p['paid'] == 'n' && ($p['expiring_date'] ?? '9999-12-31') <= date('Y-m-t'));
                                $exp_class = '';
                                // [수정] '미납' 상태일 때만 만료일 강조 표시
                                if ($p['paid'] === 'n' && !empty($p['expiring_date'])) {
                                    if ($p['expiring_date'] <= date('Y-m-d')) {
                                        $exp_class = 'text-danger fw-bold';
                                    } elseif ($p['expiring_date'] <= date('Y-m-d', strtotime('+7 days'))) {
                                        $exp_class = 'text-warning fw-bold';
                                    }
                                }
                                ?>
                                <tr class="small text-center <?= $is_unpaid_target ? 'table-danger' : '' ?>">
                                    <td class="text-nowrap"><?= $p['billing_date'] ?></td>
                                    <td class="text-nowrap <?= $exp_class ?>"><?= $p['expiring_date'] ?? '-' ?></td>
                                    <td class="text-nowrap"><span class="badge bg-light text-dark border fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span></td>
                                    <td class="text-nowrap text-end fw-bold text-success pe-3">₱<?= number_format($p['amount'], 2) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($p['paid'] == 'y'): ?>
                                            <span class="badge bg-success">납부 완료</span>
                                        <?php elseif ($p['paid'] == 'f'): ?>
                                            <span class="badge bg-info">무료 적용</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <span class="badge bg-danger">미납</span>
                                                <?php if (!empty($p['is_noticed'])): ?>
                                                    <span class="badge bg-secondary" style="font-size: 0.7rem;">결제 완료 알림</span>
                                                <?php else: ?>
                                                    <?php
                                                    $type_name = $pay_type_labels[$p['pay_type']] ?? $p['pay_type'];
                                                    $safe_type_name = htmlspecialchars($type_name, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:0.7rem;" onclick="openPaymentNoticeModal(<?= $p['id'] ?>, '<?= $safe_type_name ?>', <?= $p['amount'] ?>)">결제 완료 알리기</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?= $p['pay_date'] ?? '-' ?></td>

                                    <td class="text-start text-muted text-wrap text-break" style="line-height: 1.4;">
                                        <?= htmlspecialchars($p['note'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이지네이션 -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0 pagination-sm">
                            <?php if ($page > 1): ?><li class="page-item"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
        <div class="p-3 border-top">
            <p <?php echo UI_INFO_MD_LABEL; ?>> 결제 관련 문의사항이나 미납금 납부 등은 <strong>대시보드의 메시지 보드</strong>를 통해 본사로 문의해 주시기 바랍니다. </p>
        </div>
    </div>
</div>

<!-- 결제 알림 모달 -->
<div class="modal fade" id="paymentNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4" method="POST" action="manage_shop.php?pg=manage_shop_billing">
            <input type="hidden" name="action" value="send_payment_notice">
            <input type="hidden" name="payment_id" id="notice_payment_id">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-bell-fill me-2"></i>결제 알림 보내기</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 small mb-4 shadow-sm">
                    <strong id="notice_target_info"></strong> 항목에 대한 결제 내역을 본사로 전달합니다.
                </div>
                <div class="mb-3 d-flex align-items-center">
                    <span class="fw-bold text-dark me-3">납부(송금)를 완료하셨나요?</span>
                    <div class="form-check form-check-reverse mb-0">
                        <input class="form-check-input border-primary" type="checkbox" name="is_paid" id="is_paid" value="1" style="cursor:pointer;" onchange="toggleNoticeSubmitBtn()">
                        <label class="form-check-label fw-bold text-primary" for="is_paid" style="cursor:pointer;">예</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">결제일 (송금일)</label>
                    <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">결제 방식</label>
                    <select name="pay_method" id="pay_method" class="form-select" onchange="togglePayMethodEtc()" required>
                        <option value="GCash">GCash</option>
                        <option value="은행이체">은행이체</option>
                        <option value="기타">기타 (직접 입력)</option>
                    </select>
                    <input type="text" name="pay_method_etc" id="pay_method_etc" class="form-control mt-2 d-none" placeholder="결제 방식을 입력해주세요.">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-dark">KShops24에 남길 메시지</label>
                    <textarea name="message_text" class="form-control" rows="4" placeholder="송금자명, 참조번호(Reference No.) 등을 남겨주시면 빠른 처리에 도움이 됩니다."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" id="btn_submit_notice" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm" disabled>알림 메시지 보내기 <i class="bi bi-send-fill ms-1"></i></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPaymentNoticeModal(id, typeName, amount) {
        document.getElementById('notice_payment_id').value = id;
        document.getElementById('notice_target_info').innerText = '[' + typeName + '] ₱' + Number(amount).toLocaleString();
        document.getElementById('is_paid').checked = false;
        toggleNoticeSubmitBtn();
        document.getElementById('pay_method').value = 'GCash';
        togglePayMethodEtc();
        document.querySelector('textarea[name="message_text"]').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentNoticeModal')).show();
    }

    function toggleNoticeSubmitBtn() {
        const isChecked = document.getElementById('is_paid').checked;
        document.getElementById('btn_submit_notice').disabled = !isChecked;
    }

    function togglePayMethodEtc() {
        const sel = document.getElementById('pay_method');
        const etcInput = document.getElementById('pay_method_etc');
        if (sel.value === '기타') {
            etcInput.classList.remove('d-none');
            etcInput.required = true;
        } else {
            etcInput.classList.add('d-none');
            etcInput.required = false;
        }
    }
=======
<?php

/**
 * KShops24 상점 결제 내역 상세 보기 (manage_shop_billing.php)
 * - 역할: 상점주가 자신의 과거 및 현재 결제, 청구 내역을 전체 조회하는 전용 페이지
 * - 실행: manage_shop.php 컨테이너 내에서 include 되어 실행됨
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// [추가] 결제 완료 알림 폼 제출 처리 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payment_notice') {
    $payment_id = (int)$_POST['payment_id'];
    $is_paid = isset($_POST['is_paid']) ? 'Y' : 'N';
    $pay_date = trim($_POST['pay_date']);
    $pay_method = trim($_POST['pay_method']);
    if ($pay_method === '기타') {
        $pay_method_etc = trim($_POST['pay_method_etc']);
        $pay_method = "기타 ({$pay_method_etc})";
    }
    $message_text = trim($_POST['message_text']);

    // 결제 항목 정보 조회
    $stmt_pay = $pdo->prepare("SELECT * FROM shop_payments WHERE id = ? AND shop_id = ?");
    $stmt_pay->execute([$payment_id, $shop_id]);
    $pay_info = $stmt_pay->fetch();

    if ($pay_info) {
        global $pay_type_labels;
        $p_type_label = $pay_type_labels[$pay_info['pay_type']] ?? $pay_info['pay_type'];
        $p_amount = number_format($pay_info['amount'], 2);

        $title = "결제 완료 알림: [{$p_type_label}] ₱{$p_amount}";
        $content = "■ 결제(송금) 완료 내역 알림\n";
        $content .= "- 청구 항목: {$p_type_label}\n";
        $content .= "- 청구 금액: ₱{$p_amount}\n";
        $content .= "- 납부 확인(상점주): " . ($is_paid == 'Y' ? '납부 완료' : '미납/확인요망') . "\n";
        $content .= "- 결제일: {$pay_date}\n";
        $content .= "- 결제 방식: {$pay_method}\n\n";
        $content .= "■ 전달 메시지:\n{$message_text}";

        $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, 0, 'message', 'shop', ?, ?, NOW())")->execute([$shop_id, $title, $content]);
        $pdo->prepare("UPDATE shop_payments SET is_noticed = 1 WHERE id = ?")->execute([$payment_id]);

        if (function_exists('notifyAdminsViaTelegram')) notifyAdminsViaTelegram($pdo, "💸 <b>[결제 완료 알림 도착]</b>\n\n상점 ID: {$shop_id}\n{$content}");

        echo "<script>alert('결제 완료 알림이 본사로 성공적으로 전달되었습니다.'); location.replace('manage_shop.php?pg=manage_shop_billing');</script>";
        exit;
    }
}

// 1. 페이징 설정
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 15;
$offset = ($page - 1) * $limit;

// 2. 전체 건수 조회 및 페이징 계산
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM shop_payments WHERE shop_id = ?");
$stmt_count->execute([$shop_id]);
$total_payments = $stmt_count->fetchColumn();
$total_pages = ceil($total_payments / $limit) ?: 1;

// 3. 결제 데이터 조회 (최신 청구일 순)
$stmt = $pdo->prepare("SELECT * FROM shop_payments WHERE shop_id = ? ORDER BY billing_date DESC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$shop_id]);
$payments = $stmt->fetchAll();

// 4. 미납 금액 총합 조회 (이번 달 및 과거 연체 건 포함)
$end_of_month_date = date('Y-m-t');
$stmt_unpaid = $pdo->prepare("SELECT SUM(amount) FROM shop_payments WHERE shop_id = ? AND paid = 'n' AND expiring_date <= ?");
$stmt_unpaid->execute([$shop_id, $end_of_month_date]);
$total_unpaid = (float)$stmt_unpaid->fetchColumn();
?>

<div class="container-fluid p-0">
    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('결제 관리', 'bi-credit-card', '<span class="badge bg-white text-secondary border shadow-sm px-3 py-2 rounded-pill">총 <span class="text-primary">' . number_format($total_payments) . '</span>건</span>'); ?>

    <!-- 총 미납액 요약 위젯 -->
    <?php if ($total_unpaid > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="<?php echo UI_SECTION_CARD; ?> bg-danger bg-opacity-10 border-start border-4 border-danger">
                    <div class="p-3 p-md-4 d-flex flex-column h-100 box-responsive-between flex-md-row">

                        <div class="mb-0">
                            <h6 class="fw-bold text-danger mb-1">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>총 미납 금액
                            </h6>
                            <span class="small text-danger opacity-75">
                                이번 달까지 납부해야 할 (연체 포함) 미납 청구 금액의 합계입니다.
                            </span>
                        </div>

                        <h3 class="fw-bold text-danger m-0 text-nowrap">
                            ₱ <?php echo number_format($total_unpaid, 2); ?>
                        </h3>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?php echo UI_SECTION_CARD; ?> overflow-hidden">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php echo renderSectionHeader('결제 상세 내역', 'bi-receipt'); ?>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle mb-0" style="min-width: 700px; table-layout: fixed;">
                    <thead class="table-light">
                        <tr class="small text-muted text-center">
                            <th class="py-3" style="width: 100px;">청구일</th>
                            <th class="py-3" style="width: 100px;">만료일</th>
                            <th class="py-3" style="width: 110px;">항목</th>
                            <th class="py-3 text-end" style="width: 110px;">금액</th>
                            <th class="py-3" style="width: 90px;">납부 상태</th>
                            <th class="py-3" style="width: 100px;">납부일</th>
                            <th class="py-3 text-start" style="width: 300px;">비고</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                                    결제 내역이 없습니다.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <?php
                                $is_unpaid_target = ($p['paid'] == 'n' && ($p['expiring_date'] ?? '9999-12-31') <= date('Y-m-t'));
                                $exp_class = '';
                                // [수정] '미납' 상태일 때만 만료일 강조 표시
                                if ($p['paid'] === 'n' && !empty($p['expiring_date'])) {
                                    if ($p['expiring_date'] <= date('Y-m-d')) {
                                        $exp_class = 'text-danger fw-bold';
                                    } elseif ($p['expiring_date'] <= date('Y-m-d', strtotime('+7 days'))) {
                                        $exp_class = 'text-warning fw-bold';
                                    }
                                }
                                ?>
                                <tr class="small text-center <?= $is_unpaid_target ? 'table-danger' : '' ?>">
                                    <td class="text-nowrap"><?= $p['billing_date'] ?></td>
                                    <td class="text-nowrap <?= $exp_class ?>"><?= $p['expiring_date'] ?? '-' ?></td>
                                    <td class="text-nowrap"><span class="badge bg-light text-dark border fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span></td>
                                    <td class="text-nowrap text-end fw-bold text-success pe-3">₱<?= number_format($p['amount'], 2) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($p['paid'] == 'y'): ?>
                                            <span class="badge bg-success">납부 완료</span>
                                        <?php elseif ($p['paid'] == 'f'): ?>
                                            <span class="badge bg-info">무료 적용</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <span class="badge bg-danger">미납</span>
                                                <?php if (!empty($p['is_noticed'])): ?>
                                                    <span class="badge bg-secondary" style="font-size: 0.7rem;">결제 완료 알림</span>
                                                <?php else: ?>
                                                    <?php
                                                    $type_name = $pay_type_labels[$p['pay_type']] ?? $p['pay_type'];
                                                    $safe_type_name = htmlspecialchars($type_name, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:0.7rem;" onclick="openPaymentNoticeModal(<?= $p['id'] ?>, '<?= $safe_type_name ?>', <?= $p['amount'] ?>)">결제 완료 알리기</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?= $p['pay_date'] ?? '-' ?></td>

                                    <td class="text-start text-muted text-wrap text-break" style="line-height: 1.4;">
                                        <?= htmlspecialchars($p['note'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이지네이션 -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0 pagination-sm">
                            <?php if ($page > 1): ?><li class="page-item"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link shadow-none" href="?pg=manage_shop_billing&p=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
        <div class="p-3 border-top">
            <p <?php echo UI_INFO_MD_LABEL; ?>> 결제 관련 문의사항이나 미납금 납부 등은 <strong>대시보드의 메시지 보드</strong>를 통해 본사로 문의해 주시기 바랍니다. </p>
        </div>
    </div>
</div>

<!-- 결제 알림 모달 -->
<div class="modal fade" id="paymentNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4" method="POST" action="manage_shop.php?pg=manage_shop_billing">
            <input type="hidden" name="action" value="send_payment_notice">
            <input type="hidden" name="payment_id" id="notice_payment_id">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-bell-fill me-2"></i>결제 알림 보내기</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 small mb-4 shadow-sm">
                    <strong id="notice_target_info"></strong> 항목에 대한 결제 내역을 본사로 전달합니다.
                </div>
                <div class="mb-3 d-flex align-items-center">
                    <span class="fw-bold text-dark me-3">납부(송금)를 완료하셨나요?</span>
                    <div class="form-check form-check-reverse mb-0">
                        <input class="form-check-input border-primary" type="checkbox" name="is_paid" id="is_paid" value="1" style="cursor:pointer;" onchange="toggleNoticeSubmitBtn()">
                        <label class="form-check-label fw-bold text-primary" for="is_paid" style="cursor:pointer;">예</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">결제일 (송금일)</label>
                    <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">결제 방식</label>
                    <select name="pay_method" id="pay_method" class="form-select" onchange="togglePayMethodEtc()" required>
                        <option value="GCash">GCash</option>
                        <option value="은행이체">은행이체</option>
                        <option value="기타">기타 (직접 입력)</option>
                    </select>
                    <input type="text" name="pay_method_etc" id="pay_method_etc" class="form-control mt-2 d-none" placeholder="결제 방식을 입력해주세요.">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-dark">KShops24에 남길 메시지</label>
                    <textarea name="message_text" class="form-control" rows="4" placeholder="송금자명, 참조번호(Reference No.) 등을 남겨주시면 빠른 처리에 도움이 됩니다."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" id="btn_submit_notice" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm" disabled>알림 메시지 보내기 <i class="bi bi-send-fill ms-1"></i></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPaymentNoticeModal(id, typeName, amount) {
        document.getElementById('notice_payment_id').value = id;
        document.getElementById('notice_target_info').innerText = '[' + typeName + '] ₱' + Number(amount).toLocaleString();
        document.getElementById('is_paid').checked = false;
        toggleNoticeSubmitBtn();
        document.getElementById('pay_method').value = 'GCash';
        togglePayMethodEtc();
        document.querySelector('textarea[name="message_text"]').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentNoticeModal')).show();
    }

    function toggleNoticeSubmitBtn() {
        const isChecked = document.getElementById('is_paid').checked;
        document.getElementById('btn_submit_notice').disabled = !isChecked;
    }

    function togglePayMethodEtc() {
        const sel = document.getElementById('pay_method');
        const etcInput = document.getElementById('pay_method_etc');
        if (sel.value === '기타') {
            etcInput.classList.remove('d-none');
            etcInput.required = true;
        } else {
            etcInput.classList.add('d-none');
            etcInput.required = false;
        }
    }
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>