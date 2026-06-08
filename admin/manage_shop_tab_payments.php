<?php
/**
 * [탭 파일] 결제 수납 관리
 * 위치: admin/manage_shop_tab_payments.php
 */
if (!isset($pdo)) exit;

// =========================================================================
// [공통 함수] 결제 내역 렌더링 (AJAX 및 View 공용)
// =========================================================================
if (!function_exists('renderPaymentTableHTML')) {
    function renderPaymentTableHTML($payment_list, $shop_id, $f_year, $f_month, $f_note, $sort_col, $sort_dir, $pay_type_labels, $total_pay_pages, $pay_page) {
        ob_start();
        $pay_base_url = "admin_view.php?page=manage_shop&id={$shop_id}&view=payments&f_year={$f_year}&f_month={$f_month}&f_note=" . urlencode($f_note);
        $bd_next_dir = ($sort_col === 'billing_date' && $sort_dir === 'asc') ? 'desc' : 'asc';
        $ed_next_dir = ($sort_col === 'expiring_date' && $sort_dir === 'asc') ? 'desc' : 'asc';
        $bd_icon = ($sort_col === 'billing_date') ? ($sort_dir === 'asc' ? 'bi-caret-up-fill text-dark' : 'bi-caret-down-fill text-dark') : 'bi-arrow-down-up text-muted opacity-25';
        $ed_icon = ($sort_col === 'expiring_date') ? ($sort_dir === 'asc' ? 'bi-caret-up-fill text-dark' : 'bi-caret-down-fill text-dark') : 'bi-arrow-down-up text-muted opacity-25';
?>
        <div class="table-responsive bg-white rounded shadow-sm border">
            <table class="table table-ps24 table-hover align-middle mb-0">
                <thead>
                    <tr class="small">
                        <th style="width: 12%;"><a href="<?= $pay_base_url ?>&sort_col=billing_date&sort_dir=<?= $bd_next_dir ?>" class="text-decoration-none text-muted ajax-pay-link">청구일 <i class="bi <?= $bd_icon ?> ms-1"></i></a></th>
                        <th style="width: 12%;"><a href="<?= $pay_base_url ?>&sort_col=expiring_date&sort_dir=<?= $ed_next_dir ?>" class="text-decoration-none text-muted ajax-pay-link">만료일 <i class="bi <?= $ed_icon ?> ms-1"></i></a></th>
                        <th style="width: 10%;">항목</th>
                        <th style="width: 12%;">금액</th>
                        <th style="width: 8%;">납부</th>
                        <th style="width: 10%;">납부일</th>
                        <th style="width: 26%;">비고</th>
                        <th style="width: 10%;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_list as $p):
                        $row_class = '';
                        $row_style = '';
                        if ($p['paid'] === 'n' && !empty($p['expiring_date']) && $p['expiring_date'] !== '0000-00-00') {
                            $exp_date = new DateTime($p['expiring_date']);
                            $today = new DateTime(date('Y-m-d'));
                            $warning_days = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
                            $warning_date = (clone $today)->modify("+$warning_days days");
                            if ($exp_date < $today) {
                                $row_class = 'table-danger border-danger';
                                $row_style = 'border-width: 2px;';
                            } elseif ($exp_date <= $warning_date) {
                                $row_class = 'table-warning border-warning';
                                $row_style = 'border-width: 2px; border-color: #fd7e14 !important;';
                            }
                        }
                    ?>
                        <tr class="small <?= $row_class ?>" style="<?= $row_style ?>">
                            <td class="t-center"><?= $p['billing_date'] ?></td>
                            <td class="t-center"><?= $p['expiring_date'] ?? '-' ?></td>
                            <td class="t-center"><span class="badge border text-dark fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span></td>
                            <td class="t-end fw-bold text-success pe-4">₱<?= number_format($p['amount'], 2) ?></td>
                            <td class="t-center"><?php if ($p['paid'] == 'y'): ?><span class="badge bg-success">납부</span><?php elseif ($p['paid'] == 'f'): ?><span class="badge bg-info">무료</span><?php else: ?><span class="badge bg-danger">미납</span><?php endif; ?></td>
                            <td class="t-center"><?= $p['pay_date'] ?? '-' ?></td>
                            <td class="text-muted ps-3"><?= htmlspecialchars($p['note'] ?? '-') ?></td>
                            <td class="t-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 btn-edit-payment" data-id="<?= $p['id'] ?>" data-type="<?= $p['pay_type'] ?>" data-amount="<?= $p['amount'] ?>" data-billing="<?= $p['billing_date'] ?>" data-expiring="<?= $p['expiring_date'] ?? '' ?>" data-paid="<?= $p['paid'] ?>" data-paydate="<?= $p['pay_date'] ?? '' ?>" data-note="<?= htmlspecialchars($p['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1 btn-delete-payment" data-id="<?= $p['id'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payment_list)) echo "<tr><td colspan='8' class='text-center py-5 text-muted'>결제 내역이 없습니다.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pay_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $total_pay_pages; $i++): $url = "admin_view.php?page=manage_shop&id={$shop_id}&view=payments&pay_page={$i}&f_year={$f_year}&f_month={$f_month}&f_note=" . urlencode($f_note) . "&sort_col={$sort_col}&sort_dir={$sort_dir}"; ?>
                        <li class="page-item <?= ($i == $pay_page ? 'active' : '') ?>"><a class="page-link shadow-none border-0 mx-1 rounded-circle text-center ajax-pay-link" style="width: 30px;" href="<?= $url ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
<?php
        return ob_get_clean();
    }
}

// =========================================================================
// [1] Action 처리
// =========================================================================
if ($tab_mode === 'action') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action === 'ajax_delete_payment') {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            $payment_id = (int)($_POST['payment_id'] ?? 0);
            if ($payment_id > 0) {
                $pdo->prepare("DELETE FROM shop_payments WHERE id = ? AND shop_id = ?")->execute([$payment_id, $shop_id]);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '유효하지 않은 항목입니다.']);
            }
            exit;
        }
        if ($shop_id > 0) {
            if ($action === 'add_payment') {
                $paid = isset($_POST['paid']) ? 'y' : 'n';
                $pay_date = ($paid === 'y' && !empty($_POST['pay_date'])) ? $_POST['pay_date'] : null;
                $expiring_date = !empty($_POST['expiring_date']) ? $_POST['expiring_date'] : null;
                $pdo->prepare("INSERT INTO shop_payments (shop_id, pay_type, amount, billing_date, expiring_date, pay_date, paid, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$shop_id, $_POST['pay_type'], $_POST['amount'], $_POST['billing_date'], $expiring_date, $pay_date, $paid, $_POST['note']]);
                
                $stmt_tel = $pdo->prepare("SELECT shop_name, telegram_chat_id, use_telegram_alert, telegram_alert_types FROM shops WHERE id = ?");
                $stmt_tel->execute([$shop_id]);
                $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);
                if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
                    $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
                    if (in_array('message', $alert_types)) {
                        global $pay_type_labels;
                        $type_name = $pay_type_labels[$_POST['pay_type']] ?? $_POST['pay_type'];
                        $tel_msg = "🔔 <b>[본사 청구서 발행 알림]</b>\n\n상점: {$tel_info['shop_name']}\n항목: {$type_name}\n금액: ₱ " . number_format((float)$_POST['amount']) . "\n청구일: {$_POST['billing_date']}\n납부기한: " . ($expiring_date ? $expiring_date : '기한 없음') . "\n";
                        if (!empty($_POST['note'])) $tel_msg .= "비고: {$_POST['note']}\n";
                        $tel_msg .= "\n<i>자세한 내역은 상점 관리자 페이지 [결제 관리]에서 확인해주세요.</i>";
                        send_ps24_telegram($tel_msg, $tel_info['telegram_chat_id']);
                    }
                }
                echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=payments&msg=payment_added');</script>";
                exit;
            }
            if ($action === 'edit_payment') {
                $payment_id = (int)$_POST['payment_id'];
                $paid = isset($_POST['paid']) ? 'y' : 'n';
                $pay_date = ($paid === 'y' && !empty($_POST['pay_date'])) ? $_POST['pay_date'] : null;
                $expiring_date = !empty($_POST['expiring_date']) ? $_POST['expiring_date'] : null;
                $pdo->prepare("UPDATE shop_payments SET pay_type = ?, amount = ?, billing_date = ?, expiring_date = ?, pay_date = ?, paid = ?, note = ? WHERE id = ? AND shop_id = ?")->execute([$_POST['pay_type'], $_POST['amount'], $_POST['billing_date'], $expiring_date, $pay_date, $paid, $_POST['note'], $payment_id, $shop_id]);
                addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_BILLING, "결제 내역 수정", "금액: {$_POST['amount']} / 납부여부: {$paid}");
                if (isset($_POST['bill_next_6_months']) && $_POST['bill_next_6_months'] == '1' && !empty($expiring_date) && function_exists('add6MonthBill')) {
                    add6MonthBill($pdo, $shop_id, (new DateTime($expiring_date))->modify('+1 day')->format('Y-m-d'));
                }
                echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=payments&msg=payment_edited');</script>";
                exit;
            }
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'delete_payment' && isset($_GET['payment_id'])) {
        $payment_id = (int)$_GET['payment_id'];
        if ($payment_id > 0 && $shop_id > 0) {
            $pdo->prepare("DELETE FROM shop_payments WHERE id = ? AND shop_id = ?")->execute([$payment_id, $shop_id]);
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=payments&msg=payment_deleted');</script>";
            exit;
        }
    }
}

// =========================================================================
// [2] Data 로딩
// =========================================================================
if ($tab_mode === 'data') {
    // 사이트 비용 환경 변수 로드
    $stmt_fees = $pdo->query("SELECT set_key, set_value FROM site_settings WHERE set_key IN ('monthly_fee', 'setup_fee')");
    while ($row = $stmt_fees->fetch()) $site_fees[$row['set_key']] = (int)$row['set_value'];

    if ($active_tab === 'payments') {
        $pay_page = (int)($_GET['pay_page'] ?? 1);
        if ($pay_page < 1) $pay_page = 1;
        $pay_offset = ($pay_page - 1) * $limit;
        if (!in_array($sort_col, ['billing_date', 'expiring_date'])) $sort_col = 'expiring_date';
        if (!in_array($sort_dir, ['asc', 'desc'])) $sort_dir = 'asc';
        $pay_where = "WHERE shop_id = ?";
        $pay_params = [$shop_id];
        if ($f_year && $f_month) {
            $pay_where .= " AND billing_date >= ? AND billing_date <= ?";
            $pay_params[] = "{$f_year}-" . str_pad($f_month, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
            $pay_params[] = date("Y-m-t", strtotime("{$f_year}-" . str_pad($f_month, 2, '0', STR_PAD_LEFT) . "-01")) . " 23:59:59";
        } elseif ($f_year) {
            $pay_where .= " AND billing_date >= ? AND billing_date <= ?";
            $pay_params[] = "{$f_year}-01-01 00:00:00";
            $pay_params[] = "{$f_year}-12-31 23:59:59";
        } elseif ($f_month) {
            $pay_where .= " AND billing_date >= ? AND billing_date <= ?";
            $pay_params[] = date('Y') . "-" . str_pad($f_month, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
            $pay_params[] = date("Y-m-t", strtotime(date('Y') . "-" . str_pad($f_month, 2, '0', STR_PAD_LEFT) . "-01")) . " 23:59:59";
        }
        if ($f_note) {
            $pay_where .= " AND note LIKE ?";
            $pay_params[] = "%$f_note%";
        }
        $total_pay_count = $pdo->prepare("SELECT COUNT(*) FROM shop_payments $pay_where");
        $total_pay_count->execute($pay_params);
        $total_pay_pages = ceil($total_pay_count->fetchColumn() / $limit);
        $stmt_pay = $pdo->prepare("SELECT * FROM shop_payments $pay_where ORDER BY $sort_col $sort_dir, id DESC LIMIT $limit OFFSET $pay_offset");
        $stmt_pay->execute($pay_params);
        $payment_list = $stmt_pay->fetchAll();

        if (isset($_GET['ajax_payments']) && $_GET['ajax_payments'] == '1') {
            global $pay_type_labels;
            echo renderPaymentTableHTML($payment_list, $shop_id, $f_year, $f_month, $f_note, $sort_col, $sort_dir, $pay_type_labels, $total_pay_pages, $pay_page);
            exit;
        }
    }
}

// =========================================================================
// [3] View 렌더링
// =========================================================================
if ($tab_mode === 'view'):
?>
    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 h-100 border-start border-4 border-success">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold m-0 text-success"><i class="bi bi-plus-circle-fill me-2"></i>신규 비용 청구 등록</h6>
                </div>
                <div class="card-body bg-light-subtle pt-0">
                    <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="add_payment">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">청구 항목</label>
                            <select name="pay_type" id="pay_type_select" class="form-select shadow-sm" onchange="updateAmount()">
                                <?php foreach ($pay_type_labels as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">금액</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted">₱</span>
                                <input type="number" name="amount" id="amount_input" class="form-control" placeholder="0" value="<?= ($site_fees['monthly_fee'] ?? 0) * 6 ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">청구 발생일</label>
                            <input type="date" name="billing_date" id="billing_date_input" class="form-control shadow-sm" value="<?= date('Y-m-d') ?>" onchange="updateAmount()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">만료일 (기한)</label>
                            <input type="date" name="expiring_date" id="next_billing_input" class="form-control shadow-sm" value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">비고 (메모)</label>
                            <input type="text" name="note" id="note_input" class="form-control shadow-sm" placeholder="예: 2026년 하반기 사용료" value="<?= date('Y년 n월') . ' ~ ' . date('Y년 n월', strtotime('+5 months')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">납부 여부 및 일자</label>
                            <div class="input-group shadow-sm">
                                <div class="input-group-text bg-white">
                                    <input class="form-check-input mt-0" type="checkbox" name="paid" id="paid_check" value="y" onchange="togglePayDate()" aria-label="납부 완료">
                                    <span class="ms-2 small fw-bold text-success">완납</span>
                                </div>
                                <input type="date" name="pay_date" id="pay_date_input" class="form-control bg-light" disabled>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm"><i class="bi bi-save me-1"></i> 저장</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100 bg-dark text-white">
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <h6 class="fw-bold mb-3"><i class="bi bi-search me-2"></i>결제 내역 검색</h6>
                    <form id="payment-search-form" class="row g-2">
                        <input type="hidden" name="page" value="manage_shop">
                        <input type="hidden" name="id" value="<?= $shop_id ?>">
                        <input type="hidden" name="view" value="payments">
                        <div class="col-6">
                            <select name="f_year" class="form-select form-select-sm border-0 shadow-none">
                                <option value="">전체 연도</option>
                                <?php for ($y = date('Y'); $y >= 2024; $y--) echo "<option value='$y'" . ($f_year == $y ? ' selected' : '') . ">{$y}년</option>"; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="f_month" class="form-select form-select-sm border-0 shadow-none">
                                <option value="">전체 월</option>
                                <?php for ($m = 1; $m <= 12; $m++) echo "<option value='$m'" . ($f_month == $m ? ' selected' : '') . ">{$m}월</option>"; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="text" name="f_note" class="form-control form-control-sm border-0 shadow-none" placeholder="비고 내용 검색..." value="<?= htmlspecialchars($f_note) ?>">
                        </div>
                        <div class="col-12 mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold"><i class="bi bi-funnel-fill me-1"></i> 필터 적용</button>
                            <a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments" id="btn-payment-reset" class="btn btn-outline-light btn-sm px-3" title="초기화"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h6 class="fw-bold m-0 text-success mb-3"><i class="bi bi-plus-circle-fill me-2"></i>비용 청구 내역</h6>
    <div id="payment_table_container" class="position-relative">
        <?= renderPaymentTableHTML($payment_list, $shop_id, $f_year, $f_month, $f_note, $sort_col, $sort_dir, $pay_type_labels, $total_pay_pages, $pay_page) ?>
    </div>

    <!-- [모달] 결제 수납 내역 수정 모달 -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments&pay_page=<?= $pay_page ?>&f_year=<?= $f_year ?>&f_month=<?= $f_month ?>&f_note=<?= urlencode($f_note) ?>">
                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>결제 내역 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_payment">
                        <input type="hidden" name="payment_id" id="edit_payment_id">
                        <div class="mb-3"><label class="small mb-1 fw-bold">항목</label>
                            <select name="pay_type" id="edit_pay_type" class="form-select">
                                <?php foreach ($pay_type_labels as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">금액</label><input type="number" name="amount" id="edit_amount" class="form-control" required></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">청구일</label><input type="date" name="billing_date" id="edit_billing_date" class="form-control"></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">만료일</label><input type="date" name="expiring_date" id="edit_expiring_date" class="form-control"></div>
                        <div class="mb-3">
                            <label class="small fw-bold mb-1">납부여부/일자</label>
                            <div class="input-group input-group-sm shadow-sm">
                                <div class="input-group-text border-0 bg-white">
                                    <input class="form-check-input mt-0" type="checkbox" name="paid" id="edit_paid_check" value="y" onchange="toggleEditPayDate()">
                                </div>
                                <input type="date" name="pay_date" id="edit_pay_date" class="form-control border-0" disabled>
                            </div>
                        </div>
                        <div class="mb-3 form-check form-switch bg-light border rounded p-3">
                            <input class="form-check-input" type="checkbox" name="bill_next_6_months" id="bill_next_6_months" value="1" checked>
                            <label class="form-check-label fw-bold text-primary" for="bill_next_6_months">다음 6개월 사용료 자동 청구</label>
                            <div class="form-text small mt-1">이 결제건의 만료일 다음날부터 6개월치 사용료를 '미납' 상태로 자동 생성합니다.</div>
                        </div>
                        <div class="mb-0"><label class="small mb-1 fw-bold">비고</label><input type="text" name="note" id="edit_note" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>