<?php

/**
 * [하위 파일] KShops24 상점 상세 관리 - 하단 탭 모듈
 * 역할: manage_shop.php 파일의 크기를 줄이고 코드 가독성을 높이기 위해 하단 탭과 관련된 '기능(Logic)' 및 'UI(View)' 영역을 분리함
 */
if (!isset($pdo)) exit; // 직접 접근 차단

// 메인에서 정의해주지 않은 경우 기본적으로 화면 출력 모드로 동작합니다.
if (!isset($tab_mode)) {
    $tab_mode = 'view';
}

// -------------------------------------------------------------------------
// 공용 HTML 렌더링 함수 선언 (AJAX 및 일반 VIEW 화면에서 모두 사용)
// -------------------------------------------------------------------------
if (!function_exists('renderPaymentTableHTML')) {
    function renderPaymentTableHTML($payment_list, $shop_id, $f_year, $f_month, $f_note, $sort_col, $sort_dir, $pay_type_labels, $total_pay_pages, $pay_page)
    {
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
                        <th style="width: 12%;"><a
                                href="<?= $pay_base_url ?>&sort_col=billing_date&sort_dir=<?= $bd_next_dir ?>"
                                class="text-decoration-none text-muted ajax-pay-link">청구일 <i
                                    class="bi <?= $bd_icon ?> ms-1"></i></a></th>
                        <th style="width: 12%;"><a
                                href="<?= $pay_base_url ?>&sort_col=expiring_date&sort_dir=<?= $ed_next_dir ?>"
                                class="text-decoration-none text-muted ajax-pay-link">만료일 <i
                                    class="bi <?= $ed_icon ?> ms-1"></i></a></th>
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
                        // 미납(n) 상태이고 만료일이 지정된 항목을 평가합니다.
                        if ($p['paid'] === 'n' && !empty($p['expiring_date']) && $p['expiring_date'] !== '0000-00-00') {
                            $exp_date = new DateTime($p['expiring_date']);
                            $today = new DateTime(date('Y-m-d'));
                            $warning_days = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
                            $warning_date = (clone $today)->modify("+$warning_days days");

                            if ($exp_date < $today) {
                                // 기한 지남 (연체/폐점 원인) -> 빨간색 박스
                                $row_class = 'table-danger border-danger';
                                $row_style = 'border-width: 2px;';
                            } elseif ($exp_date <= $warning_date) {
                                // 결제 만료 임박 -> 주황색 박스
                                $row_class = 'table-warning border-warning';
                                $row_style = 'border-width: 2px; border-color: #fd7e14 !important;';
                            }
                        }
                    ?>
                        <tr class="small <?= $row_class ?>" style="<?= $row_style ?>">
                            <td class="t-center"><?= $p['billing_date'] ?></td>
                            <td class="t-center"><?= $p['expiring_date'] ?? '-' ?></td>
                            <td class="t-center"><span
                                    class="badge border text-dark fw-normal"><?= $pay_type_labels[$p['pay_type']] ?? $p['pay_type'] ?></span>
                            </td>
                            <td class="t-end fw-bold text-success pe-4">₱<?= number_format($p['amount'], 2) ?></td>
                            <td class="t-center"><?php if ($p['paid'] == 'y'): ?><span
                                        class="badge bg-success">납부</span><?php elseif ($p['paid'] == 'f'): ?><span
                                        class="badge bg-info">무료</span><?php else: ?><span
                                        class="badge bg-danger">미납</span><?php endif; ?></td>
                            <td class="t-center"><?= $p['pay_date'] ?? '-' ?></td>
                            <td class="text-muted ps-3"><?= htmlspecialchars($p['note'] ?? '-') ?></td>
                            <td class="t-center"><button type="button"
                                    class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 btn-edit-payment"
                                    data-id="<?= $p['id'] ?>" data-type="<?= $p['pay_type'] ?>" data-amount="<?= $p['amount'] ?>"
                                    data-billing="<?= $p['billing_date'] ?>" data-expiring="<?= $p['expiring_date'] ?? '' ?>"
                                    data-paid="<?= $p['paid'] ?>" data-paydate="<?= $p['pay_date'] ?? '' ?>"
                                    data-note="<?= htmlspecialchars($p['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><i
                                        class="bi bi-pencil"></i></button><button type="button"
                                    class="btn btn-sm btn-outline-danger border-0 py-0 px-1 btn-delete-payment"
                                    data-id="<?= $p['id'] ?>"><i class="bi bi-trash"></i></button></td>
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
                        <li class="page-item <?= ($i == $pay_page ? 'active' : '') ?>"><a
                                class="page-link shadow-none border-0 mx-1 rounded-circle text-center ajax-pay-link"
                                style="width: 30px;" href="<?= $url ?>"><?= $i ?></a></li><?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderBoardListHTML')) {
    function renderBoardListHTML($board_list, $shop_id, $f_keyword, $board_type, $total_pages, $current_page)
    {
        ob_start();
        $type_label = ($board_type === 'email_log') ? '이메일' : '메시지';
        $bg_class = ($board_type === 'email_log') ? 'bg-warning-subtle' : 'bg-white';
        $ajax_class = ($board_type === 'email_log') ? 'ajax-email-link' : 'ajax-msg-link';
    ?>
        <div class="list-group shadow-sm border-0 rounded overflow-hidden text-start mb-3">
            <?php foreach ($board_list as $b): ?>
                <div class="list-group-item p-3 border-0 border-bottom <?= $bg_class ?>">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <div class="d-flex align-items-center gap-2"><span
                                class="badge <?= $b['sender_type'] == 'admin' ? 'bg-dark' : 'bg-info text-white' ?>"><?= $b['sender_type'] == 'admin' ? '본사' : '상점' ?></span><?php if ($board_type == 'email_log'): ?><small
                                    class="text-warning fw-bold"><i class="bi bi-envelope-paper"></i> Email Log</small><?php endif; ?>
                        </div>
                        <div><span class="me-2"><?= $b['created_at'] ?></span><?php if ($board_type !== 'email_log'): ?><button type="button" class="btn btn-link p-0 text-primary me-2 btn-reply-board" data-id="<?= $b['id'] ?>" data-title="<?= htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="답변 남기기"><i class="bi bi-reply-fill"></i></button><button
                                    type="button" class="btn btn-link p-0 text-secondary me-1 btn-edit-board" data-id="<?= $b['id'] ?>"
                                    data-title="<?= htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-content="<?= htmlspecialchars($b['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-type="<?= $b['type'] ?>"><i class="bi bi-pencil"></i></button><?php endif; ?><button
                                type="button" class="btn btn-link p-0 text-danger btn-delete-board" data-id="<?= $b['id'] ?>"><i
                                    class="bi bi-trash"></i></button></div>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($b['title']) ?></h6>
                    <div class="small text-secondary mb-0"
                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer;"
                        onclick="this.style.webkitLineClamp = this.style.webkitLineClamp === '2' ? 'unset' : '2';"
                        title="클릭하여 전체 내용 펼치기/접기"><?= nl2br(htmlspecialchars($b['content'])) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($board_list)) echo "<div class='p-5 text-center bg-white text-muted small'>검색된 {$type_label} 내역이 없습니다.</div>"; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): $url = "admin_view.php?page=manage_shop&id={$shop_id}&ajax_board={$board_type}&page_num={$i}&keyword=" . urlencode($f_keyword); ?>
                        <li class="page-item <?= ($i == $current_page ? 'active' : '') ?>"><a
                                class="page-link shadow-none border-0 mx-1 rounded-circle text-center <?= $ajax_class ?>"
                                style="width: 30px;" href="<?= $url ?>"><?= $i ?></a></li><?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
<?php
        return ob_get_clean();
    }
}

// =========================================================================
// [모드 1: ACTION] 탭 기능 관련 POST/GET/AJAX 처리
// =========================================================================
if ($tab_mode === 'action') {
    // DB / 파일 용량 관리 AJAX 처리
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_shop_file') {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            $file_path = $_POST['file_path'] ?? '';
            if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
                exit;
            }
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
            if (file_exists($absolute_path) && is_file($absolute_path)) {
                if (@unlink($absolute_path)) echo json_encode(['status' => 'success']);
                else echo json_encode(['status' => 'error', 'message' => '파일 삭제 권한이 없습니다.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
            }
            exit;
        }
        if ($_POST['action'] === 'delete_shop_files_bulk') {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            $file_paths = json_decode($_POST['file_paths'] ?? '[]', true);
            if (!is_array($file_paths)) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 데이터 형식입니다.']);
                exit;
            }
            $deleted_count = 0;
            foreach ($file_paths as $file_path) {
                if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) continue;
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                if (file_exists($absolute_path) && is_file($absolute_path)) {
                    if (@unlink($absolute_path)) $deleted_count++;
                }
            }
            echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
            exit;
        }
    }

    // POST 핸들러
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'ajax_send_msg_email') {
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json');
                $send_type = $_POST['send_type'] ?? 'message';
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $template_key = $_POST['template_key'] ?? '';
                $parent_id = (int)($_POST['parent_id'] ?? 0);
                if (empty($title) || empty($content)) {
                    echo json_encode(['status' => 'error', 'message' => '제목과 내용을 입력해주세요.']);
                    exit;
                }
                if (function_exists('replaceShopTemplateVars')) {
                    $replaced = replaceShopTemplateVars($pdo, $shop_id, ['title' => $title, 'content' => $content]);
                    $title = $replaced['title'];
                    $content = $replaced['content'];
                }
                if ($send_type === 'message') {
                    $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, ?, 'message', 'admin', ?, ?, NOW())")->execute([$shop_id, $parent_id, $title, $content]);
                    addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_MESSAGE, "관리자 메시지 발송", "제목: {$title}");

                    // [텔레그램 알림] 상점주가 '본사 알림(message)' 수신에 동의한 경우 발송
                    $stmt_tel = $pdo->prepare("SELECT telegram_chat_id, use_telegram_alert, telegram_alert_types FROM shops WHERE id = ?");
                    $stmt_tel->execute([$shop_id]);
                    $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);

                    if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
                        $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
                        if (in_array('message', $alert_types)) {
                            $tel_msg = "🔔 <b>[본사 알림 메시지]</b>\n\n<b>{$title}</b>\n\n" . strip_tags($content);
                            send_ps24_telegram($tel_msg, $tel_info['telegram_chat_id']);
                        }
                    }

                    echo json_encode(['status' => 'success', 'message' => '메시지가 발송되었습니다.']);
                } else if ($send_type === 'email') {
                    $stmt_e = $pdo->prepare("SELECT manager_email, manager_name, shop_name, subdomain, phone_mobile, category FROM shops WHERE id = ?");
                    $stmt_e->execute([$shop_id]);
                    $shop_info = $stmt_e->fetch(PDO::FETCH_ASSOC);
                    if (!$shop_info || empty($shop_info['manager_email'])) {
                        echo json_encode(['status' => 'error', 'message' => '수신할 이메일 주소가 없습니다.']);
                        exit;
                    }
                    $email_result = false;
                    if ($template_key === 'custom') {
                        $to_email = $shop_info['manager_email'];
                        $subject = '=?UTF-8?B?' . base64_encode($title) . '?=';
                        $html_content = '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        table { width: 100% !important; max-width: 100% !important; }
        img { max-width: 100% !important; height: auto !important; }
    </style>
</head>
<body style="margin:0; padding:15px; background-color:#f4f7f9; font-family:\'Apple SD Gothic Neo\', \'Malgun Gothic\', sans-serif;">
    <div style="width:100%; max-width:650px; margin:0 auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); box-sizing: border-box;">
        <div style="background-color:#004aad; height:6px; width:100%;"></div>
        <div style="padding:30px 20px; color:#333; line-height:1.6; font-size:15px; word-break: break-word; overflow-x: hidden;">
            ' . nl2br(htmlspecialchars($content)) . '
        </div>
    </div>
</body>
</html>';
                        $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nFrom: KShops24 <support@KShops24.com>\r\nX-Mailer: PHP/" . phpversion();
                        $email_result = @mail($to_email, $subject, chunk_split(base64_encode($html_content)), $headers);
                    } else {
                        if (function_exists('sendShopEmail')) {
                            $email_result = sendShopEmail($pdo, $shop_info['manager_email'], $template_key, ['manager_name' => $shop_info['manager_name'], 'shop_name' => $shop_info['shop_name'], 'subdomain' => $shop_info['subdomain'], 'phone_mobile' => $shop_info['phone_mobile'], 'category' => $shop_info['category'], 'shops:shop_name' => $shop_info['shop_name'], 'shops:subdomain' => $shop_info['subdomain'], 'shops:manager_email' => $shop_info['manager_email']]);
                        }
                    }
                    if ($email_result === true) {
                        $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content, created_at) VALUES (?, 'email_log', 'admin', ?, ?, NOW())")->execute([$shop_id, $title, $content]);
                        addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_EMAIL, "관리자 이메일 발송", "제목: {$title}");
                        echo json_encode(['status' => 'success', 'message' => '이메일이 발송되었습니다.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => '이메일 발송 실패: ' . (is_string($email_result) ? $email_result : '알 수 없는 오류')]);
                    }
                }
                exit;
            }
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
            if ($action === 'ajax_delete_board') {
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json');
                $board_id = (int)($_POST['board_id'] ?? 0);
                if ($board_id > 0) {
                    $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = ?")->execute([$board_id, $shop_id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => '유효하지 않은 항목입니다.']);
                }
                exit;
            }
            if ($action === 'ajax_edit_board') {
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json');
                $board_id = (int)($_POST['board_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if ($board_id > 0) {
                    $pdo->prepare("UPDATE shop_board SET title = ?, content = ? WHERE id = ? AND shop_id = ? AND type = 'message'")->execute([$title, $content, $board_id, $shop_id]);
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
                    // if (function_exists('recordAdminAction')) recordAdminAction($pdo, "상점 [ID: {$shop_id}] 비용 청구 내역 추가", ['pay_type' => $_POST['pay_type'], 'amount' => $_POST['amount']]);

                    // [텔레그램 알림] 신규 비용 청구 등록 시 상점주에게 알림 발송
                    $stmt_tel = $pdo->prepare("SELECT shop_name, telegram_chat_id, use_telegram_alert, telegram_alert_types FROM shops WHERE id = ?");
                    $stmt_tel->execute([$shop_id]);
                    $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);

                    if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
                        $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
                        if (in_array('message', $alert_types)) {
                            global $pay_type_labels;
                            $type_name = $pay_type_labels[$_POST['pay_type']] ?? $_POST['pay_type'];
                            $amount_formatted = number_format((float)$_POST['amount']);
                            $exp_str = $expiring_date ? $expiring_date : '기한 없음';

                            $tel_msg = "🔔 <b>[본사 청구서 발행 알림]</b>\n\n";
                            $tel_msg .= "상점: {$tel_info['shop_name']}\n";
                            $tel_msg .= "항목: {$type_name}\n";
                            $tel_msg .= "금액: ₱ {$amount_formatted}\n";
                            $tel_msg .= "청구일: {$_POST['billing_date']}\n";
                            $tel_msg .= "납부기한: {$exp_str}\n";
                            if (!empty($_POST['note'])) {
                                $tel_msg .= "비고: {$_POST['note']}\n";
                            }
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
                    // if (function_exists('recordAdminAction')) recordAdminAction($pdo, "상점 [ID: {$shop_id}] 결제 내역 수정", ['payment_id' => $payment_id, 'amount' => $_POST['amount']]);
                    echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=payments&msg=payment_edited');</script>";
                    exit;
                }
                if ($action === 'add_log') {
                    $date = str_replace('T', ' ', $_POST['log_date'] ?? date('Y-m-d H:i:s'));
                    addShopHistoryLog($pdo, $shop_id, $_POST['log_type'] ?? 'info', $_POST['log_title'] ?? '', $_POST['log_content'] ?? '', $date);
                    echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_added');</script>";
                    exit;
                }
                if ($action === 'edit_log') {
                    $log_index = (int)$_POST['log_index'];
                    $date = str_replace('T', ' ', $_POST['log_date'] ?? date('Y-m-d H:i:s'));
                    $stmt_shop = $pdo->prepare("SELECT history_log FROM shops WHERE id = ?");
                    $stmt_shop->execute([$shop_id]);
                    $history_array = json_decode($stmt_shop->fetchColumn() ?: '[]', true);
                    if (isset($history_array[$log_index])) {
                        $history_array[$log_index] = ['type' => $_POST['log_type'] ?? 'info', 'title' => $_POST['log_title'] ?? '', 'content' => $_POST['log_content'] ?? '', 'date' => $date];
                        $pdo->prepare("UPDATE shops SET history_log = ? WHERE id = ?")->execute([json_encode($history_array, JSON_UNESCAPED_UNICODE), $shop_id]);
                    }
                    echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_edited');</script>";
                    exit;
                }
            }
        } catch (Exception $e) {
            global $message; // 에러 메시지를 전역 변수에 바인딩하여 뷰에서 출력
            if (function_exists('showAlert')) $message = showAlert("오류 발생: " . $e->getMessage(), "danger");
        }
    }

    // GET 핸들러
    if (isset($_GET['ajax_board'])) {
        if (ob_get_level()) ob_clean();
        $board_type = $_GET['ajax_board'] === 'email_log' ? 'email_log' : 'message';
        $keyword = trim($_GET['keyword'] ?? '');
        $page_num = max(1, (int)($_GET['page_num'] ?? 1));
        $limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 20;
        $offset = ($page_num - 1) * $limit;
        $where = "WHERE shop_id = ? AND type = ?";
        $params = [$shop_id, $board_type];
        if ($keyword) {
            $where .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM shop_board $where");
        $count_stmt->execute($params);
        $total_pages = ceil($count_stmt->fetchColumn() / $limit);
        $stmt = $pdo->prepare("SELECT * FROM shop_board $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        echo renderBoardListHTML($stmt->fetchAll(), $shop_id, $keyword, $board_type, $total_pages, $page_num);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'delete_payment' && isset($_GET['payment_id'])) {
        $payment_id = (int)$_GET['payment_id'];
        if ($payment_id > 0 && $shop_id > 0) {
            $pdo->prepare("DELETE FROM shop_payments WHERE id = ? AND shop_id = ?")->execute([$payment_id, $shop_id]);
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=payments&msg=payment_deleted');</script>";
            exit;
        }
    }
    if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['log_index'])) {
        $log_index = (int)$_GET['log_index'];
        if ($log_index >= 0 && $shop_id > 0) {
            $stmt_shop = $pdo->prepare("SELECT history_log FROM shops WHERE id = ?");
            $stmt_shop->execute([$shop_id]);
            $history_array = json_decode($stmt_shop->fetchColumn() ?: '[]', true);
            if (isset($history_array[$log_index])) {
                unset($history_array[$log_index]);
                $history_array = array_values($history_array);
                $pdo->prepare("UPDATE shops SET history_log = ? WHERE id = ?")->execute([json_encode($history_array, JSON_UNESCAPED_UNICODE), $shop_id]);
            }
            echo "<script>location.replace('admin_view.php?page=manage_shop&id={$shop_id}&view=logs&msg=log_deleted');</script>";
            exit;
        }
    }
    return; // ACTION 모드 종료
}

// =========================================================================
// [모드 2: DATA] 탭 화면 렌더링을 위한 데이터 로딩
// =========================================================================
if ($tab_mode === 'data') {
    // 사이트 비용 환경 변수 로드
    $site_fees = [];
    $stmt_fees = $pdo->query("SELECT set_key, set_value FROM site_settings WHERE set_key IN ('monthly_fee', 'setup_fee')");
    while ($row = $stmt_fees->fetch()) {
        $site_fees[$row['set_key']] = (int)$row['set_value'];
    }

    // 변수 초기화
    $payment_list = [];
    $total_pay_pages = 0;
    $pay_page = 1;
    $msg_list = [];
    $email_list = [];
    $total_msg_pages = 1;
    $total_email_pages = 1;
    $total_disk_usage = 0;
    $total_db_usage = 0;
    $db_details = [];
    $orphaned_files = [];
    $all_files_list = [];
    $msg_tpl_js = [];
    $email_tpl_js = [];
    $message_templates = [];

    $f_year = $_GET['f_year'] ?? '';
    $f_month = $_GET['f_month'] ?? '';
    $f_note = trim($_GET['f_note'] ?? '');
    $sort_col = $_GET['sort_col'] ?? 'expiring_date';
    $sort_dir = strtolower($_GET['sort_dir'] ?? 'asc');
    $limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 20;

    if (isset($s) && $s) {
        // 템플릿 정보 로드
        $stmt_tpl = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'message_templates'");
        $message_templates = json_decode($stmt_tpl->fetchColumn() ?: '{}', true);
        $stmt_email_tpl = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $email_templates_data = json_decode($stmt_email_tpl->fetchColumn() ?: '{}', true);

        foreach ($message_templates as $k => $v) {
            $msg_tpl_js[$k] = ['title' => $v['title'], 'content' => $v['content']];
        }
        $email_types_map = [SHOP_STATUS_APPLYING => '입점 신청 안내 이메일', SHOP_STATUS_TESTING => '테스트 돌입 안내 이메일', SHOP_STATUS_ACTIVE => '입점 완료/오픈 이메일', SHOP_STATUS_INACTIVE_SOON => '휴점 경고 이메일', SHOP_STATUS_INACTIVE => '휴점 통보 이메일', SHOP_STATUS_CLOSED_SOON => '폐점 경고 이메일', SHOP_STATUS_CLOSED => '폐점 통보 이메일'];
        $subject_map = [SHOP_STATUS_APPLYING => '[KShops24] 입점 신청이 성공적으로 접수되었습니다.', SHOP_STATUS_TESTING => '[KShops24] 상점 구축(테스팅) 작업이 시작되었습니다.', SHOP_STATUS_ACTIVE => '[KShops24] 상점이 정식으로 오픈되었습니다!', SHOP_STATUS_INACTIVE_SOON => '[KShops24] 상점 서비스 일시 중지(휴점) 사전 안내.', SHOP_STATUS_INACTIVE => '[KShops24] 상점 서비스가 일시 중지(휴점) 되었습니다.', SHOP_STATUS_CLOSED_SOON => '[KShops24] 상점 폐점 사전 안내.', SHOP_STATUS_CLOSED => '[KShops24] 상점 서비스가 영구 종료(폐점) 되었습니다.'];
        foreach ($email_types_map as $k => $name) {
            if (isset($email_templates_data[$k])) {
                $email_tpl_js[$k] = ['title' => $subject_map[$k] ?? "[KShops24] 시스템 안내 메일", 'content' => strip_tags(html_entity_decode($email_templates_data[$k], ENT_QUOTES, 'UTF-8'))];
            }
        }

        // A. 결제 내역 탭
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

        // B. 메시지 탭
        if ($active_tab === 'message') {
            $msg_where = "WHERE shop_id = ? AND type = 'message'";
            $msg_count = $pdo->prepare("SELECT COUNT(*) FROM shop_board $msg_where");
            $msg_count->execute([$shop_id]);
            $total_msg_pages = ceil($msg_count->fetchColumn() / $limit) ?: 1;
            $stmt_msg = $pdo->prepare("SELECT * FROM shop_board $msg_where ORDER BY id DESC LIMIT $limit");
            $stmt_msg->execute([$shop_id]);
            $msg_list = $stmt_msg->fetchAll();
            $em_where = "WHERE shop_id = ? AND type = 'email_log'";
            $em_count = $pdo->prepare("SELECT COUNT(*) FROM shop_board $em_where");
            $em_count->execute([$shop_id]);
            $total_email_pages = ceil($em_count->fetchColumn() / $limit) ?: 1;
            $stmt_em = $pdo->prepare("SELECT * FROM shop_board $em_where ORDER BY id DESC LIMIT $limit");
            $stmt_em->execute([$shop_id]);
            $email_list = $stmt_em->fetchAll();
            $pdo->prepare("UPDATE shop_board SET is_read = 1 WHERE shop_id = ? AND sender_type = 'shop' AND is_read = 0")->execute([$shop_id]); // 메시지 읽음 처리
        }

        // C. 파일 용량 탭
        if ($active_tab === 'files') {
            if (function_exists('getShopResourceUsage')) {
                $usage = getShopResourceUsage($pdo, $shop_id);
                $total_disk_usage = $usage['disk'] ?? 0;
                $total_db_usage = $usage['db'] ?? 0;
                $db_details = $usage['db_details'] ?? [];
            }
            if (function_exists('analyzeShopDiskIntegrity')) {
                $integrity_result = analyzeShopDiskIntegrity($pdo, $shop_id);
                $orphaned_files = $integrity_result['orphaned_files'] ?? [];
            }
            $shop_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $s['subdomain'];
            if (is_dir($shop_dir)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($shop_dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    $all_files_list[] = ['path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname()), 'name' => $file->getFilename(), 'size' => $file->isFile() ? $file->getSize() : 0, 'is_dir' => $file->isDir(), 'depth' => $iterator->getDepth()];
                }
            }
        }
    }
    return; // DATA 모드 종료
}

// =========================================================================
// [모드 3: VIEW] 화면 HTML 렌더링
// =========================================================================
?>
<div class="col-12 mt-4">
    <div class="fs-4 fw-bold text-dark d-flex align-items-center mb-3">
        <i class="bi bi-gear-fill me-2 text-primary"></i>
        <span>상점 관리</span>
    </div>

    <!-- [2] 탭 네비게이션 (결제 수납 / 메시지 / 로그 / 파일) -->
    <div class="inner-tab-container">
        <nav class="inner-tab-nav">
            <a class="ajax-tab-link <?= $active_tab == 'payments' ? 'active' : '' ?>"
                href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments">결제 수납 관리</a>
            <a class="ajax-tab-link <?= $active_tab == 'message' ? 'active' : '' ?>"
                href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=message">메시지/이메일 관리</a>
            <a class="ajax-tab-link <?= $active_tab == 'logs' ? 'active' : '' ?>"
                href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs">로그 관리</a>
            <a class="ajax-tab-link <?= $active_tab == 'files' ? 'active' : '' ?>"
                href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=files">DB / 파일 용량 관리</a>
        </nav>
    </div>

    <div class="tab-content border-0">

        <!-- [2-C] DB / 파일 용량 관리 탭 내용 -->
        <div class="tab-pane fade <?= $active_tab == 'files' ? 'show active' : '' ?>" id="files">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-info"><i class="bi bi-database me-2"></i>DB 사용 용량 분석</h5>
                    <p class="card-text text-muted mb-3">
                        현재 상점이 데이터베이스(DB)에서 차지하고 있는 데이터 용량과 테이블별 사용 현황입니다.
                    </p>
                    <div class="alert alert-light border d-flex align-items-center mb-3">
                        <i class="bi bi-server fs-2 me-3 text-info"></i>
                        <div>
                            <strong class="d-block text-dark">총 DB 사용량:
                                <?= function_exists('formatBytes') ? formatBytes($total_db_usage) : $total_db_usage . ' B' ?></strong>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle border mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th class="ps-3 py-2">테이블명</th>
                                    <th class="text-end">기록 수(Rows)</th>
                                    <th class="text-end pe-3">사용 용량</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($db_details)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">사용 중인 데이터가 없습니다.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($db_details as $db_item): ?>
                                        <tr>
                                            <td class="ps-3"><code
                                                    class="text-dark"><?= htmlspecialchars($db_item['table']) ?></code></td>
                                            <td class="text-end"><?= number_format($db_item['rows']) ?> 건</td>
                                            <td class="text-end pe-3 fw-bold text-secondary">
                                                <?= function_exists('formatBytes') ? formatBytes($db_item['size']) : $db_item['size'] . ' B' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 mt-3">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-primary"><i class="bi bi-hdd-stack me-2"></i>서버 용량 분석</h5>
                    <p class="card-text text-muted">
                        현재 상점(<?= htmlspecialchars($s['subdomain']) ?>)이 서버에서 사용 중인 총 디스크 용량과 불필요한 파일 목록입니다.
                    </p>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-hdd-fill fs-2 me-3"></i>
                        <div>
                            <strong class="d-block">총 사용량:
                                <?= function_exists('formatBytes') ? formatBytes($total_disk_usage) : $total_disk_usage . ' B' ?></strong>
                            <small>경로: <code>/uploads/shops/<?= htmlspecialchars($s['subdomain']) ?>/</code></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-warning"><i class="bi bi-question-circle-fill me-2"></i>DB에 없는 파일
                            (고아 파일)</h6>
                        <?php if (!empty($orphaned_files)): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-all-orphaned"
                                data-paths='<?= htmlspecialchars(json_encode(array_column($orphaned_files, 'path')), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="bi bi-trash3"></i> 전체 일괄 삭제
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush orphaned-file-list">
                        <?php if (empty($orphaned_files)): ?>
                            <li class="list-group-item text-center py-4 text-muted">
                                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                                DB에 기록되지 않은 불필요한 파일이 없습니다.
                            </li>
                        <?php else: ?>
                            <?php foreach ($orphaned_files as $file): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <code><?= getImageModalTrigger($file['path']) ?></code>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary rounded-pill me-2"><?= htmlspecialchars($file['size_formatted']) ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file"
                                            data-path="<?= htmlspecialchars($file['path']) ?>">
                                            <i class="bi bi-trash"></i> 삭제
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="bi bi-folder2-open me-2"></i>전체 파일 목록</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php if (empty($all_files_list)): ?>
                            <li class="list-group-item text-center py-4 text-muted">업로드된 파일이나 폴더가 없습니다.</li>
                        <?php else: ?>
                            <?php foreach ($all_files_list as $file): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"
                                    style="padding-left: <?= 1 + $file['depth'] * 1.5 ?>rem;">
                                    <div>
                                        <?php if ($file['is_dir']): ?>
                                            <i class="bi bi-folder-fill text-warning me-2"></i>
                                            <strong><?= htmlspecialchars($file['name']) ?></strong>
                                        <?php else: ?>
                                            <?php
                                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

                                            // 윈도우(XAMPP 등) 환경에서 경로 슬래시(\)가 섞여있을 수 있으므로 웹용(/)으로 치환
                                            $web_path = str_replace('\\', '/', $file['path']);
                                            ?>

                                            <?php if ($is_image): ?>
                                                <i class="bi bi-file-image text-primary me-2"></i>
                                                <?= getImageModalTrigger($web_path, $file['name']) ?>
                                            <?php else: ?>
                                                <i class="bi bi-file-earmark-text text-muted me-2"></i>
                                                <?= htmlspecialchars($file['name']) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$file['is_dir']): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= function_exists('formatBytes') ? formatBytes($file['size']) : $file['size'] . ' B' ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <!-- [2-A] 결제 수납 관리 탭 내용 -->
        <div class="tab-pane fade <?= $active_tab == 'payments' ? 'show active' : '' ?>" id="payment">
            <!-- 상단 컨트롤 영역: 비용 청구 폼 & 검색 필터 -->
            <div class="row g-4 mb-4">
                <!-- 왼쪽: 신규 비용 청구 폼 -->
                <div class="col-xl-8">
                    <div class="card shadow-sm border-0 h-100 border-start border-4 border-success">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="fw-bold m-0 text-success"><i class="bi bi-plus-circle-fill me-2"></i>신규 비용 청구 등록
                            </h6>
                        </div>
                        <div class="card-body bg-light-subtle pt-0">
                            <form method="POST"
                                action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments"
                                class="row g-3 align-items-end">
                                <input type="hidden" name="action" value="add_payment">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">청구 항목</label>
                                    <select name="pay_type" id="pay_type_select" class="form-select shadow-sm"
                                        onchange="updateAmount()">
                                        <?php foreach ($pay_type_labels as $val => $label): ?><option
                                                value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">금액</label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white text-muted">₱</span>
                                        <input type="number" name="amount" id="amount_input" class="form-control"
                                            placeholder="0" value="<?= ($site_fees['monthly_fee'] ?? 0) * 6 ?>"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">청구 발생일</label>
                                    <input type="date" name="billing_date" id="billing_date_input"
                                        class="form-control shadow-sm" value="<?= date('Y-m-d') ?>"
                                        onchange="updateAmount()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">만료일 (기한)</label>
                                    <input type="date" name="expiring_date" id="next_billing_input"
                                        class="form-control shadow-sm"
                                        value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">비고 (메모)</label>
                                    <input type="text" name="note" id="note_input" class="form-control shadow-sm"
                                        placeholder="예: 2026년 하반기 사용료"
                                        value="<?= date('Y년 n월') . ' ~ ' . date('Y년 n월', strtotime('+5 months')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">납부 여부 및 일자</label>
                                    <div class="input-group shadow-sm">
                                        <div class="input-group-text bg-white">
                                            <input class="form-check-input mt-0" type="checkbox" name="paid"
                                                id="paid_check" value="y" onchange="togglePayDate()" aria-label="납부 완료">
                                            <span class="ms-2 small fw-bold text-success">완납</span>
                                        </div>
                                        <input type="date" name="pay_date" id="pay_date_input"
                                            class="form-control bg-light" disabled>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm"><i
                                            class="bi bi-save me-1"></i> 저장</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 오른쪽: 검색 및 필터 -->
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
                                    <input type="text" name="f_note"
                                        class="form-control form-control-sm border-0 shadow-none"
                                        placeholder="비고 내용 검색..." value="<?= htmlspecialchars($f_note) ?>">
                                </div>
                                <div class="col-12 mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold"><i
                                            class="bi bi-funnel-fill me-1"></i> 필터 적용</button>
                                    <a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=payments"
                                        id="btn-payment-reset" class="btn btn-outline-light btn-sm px-3" title="초기화"><i
                                            class="bi bi-arrow-counterclockwise"></i></a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 결제 내역 테이블 컨테이너 (AJAX 영역) -->
            <h6 class="fw-bold m-0 text-success mb-3"><i class="bi bi-plus-circle-fill me-2"></i>비용 청구 내역</h6>
            <div id="payment_table_container" class="position-relative">
                <?= renderPaymentTableHTML($payment_list, $shop_id, $f_year, $f_month, $f_note, $sort_col, $sort_dir, $pay_type_labels, $total_pay_pages, $pay_page) ?>
            </div>
        </div>

        <!-- [2-B] 메시지/이메일 관리 탭 내용 -->
        <div class="tab-pane fade <?= $active_tab == 'message' ? 'show active' : '' ?>" id="board">

            <div class="card shadow-sm border-0 mb-4 p-4 bg-light text-start border-start border-4 border-primary">
                <div class="settings-title fs-5 fw-bold mb-3 text-primary"><i class="bi bi-envelope me-2"></i> 상점에
                    메시지/이메일 보내기</div>
                <form id="form-send-msg-email" class="row g-3">
                    <input type="hidden" name="parent_id" id="inp_parent_id" value="0">
                    <div class="col-md-4">
                        <label class="small fw-bold mb-1">유형 선택</label>
                        <select name="send_type" id="sel_send_type" class="form-select shadow-sm">
                            <option value="message">메시지 (쪽지)</option>
                            <option value="email">이메일</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="small fw-bold mb-1">템플릿 선택</label>
                        <select name="template_key" id="sel_template_key" class="form-select shadow-sm">
                            <option value="custom" selected>직접 입력 (자유형)</option>
                            <?php foreach ($message_templates as $tpl_key => $tpl): ?>
                                <option value="<?= htmlspecialchars($tpl_key) ?>"><?= htmlspecialchars($tpl['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold mb-1">제목</label>
                        <input type="text" name="title" id="inp_msg_title" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold mb-1">내용</label>
                        <textarea name="content" id="inp_msg_content" class="form-control shadow-sm" rows="4"
                            required></textarea>
                    </div>

                    <div class="col-12">
                        <label class="small fw-bold mb-1">사용가능 변수</label>
                        <div class="form-text small"><i class="bi bi-info-circle me-1"></i>{shop_name} : 상점명 치환</div>
                        <div class="form-text small"><i class="bi bi-info-circle me-1"></i>{unpaid_amount} : 연체액 치환
                        </div>
                        <div class="form-text small"><i class="bi bi-info-circle me-1"></i>{SHOP_CLOSED_AFTER_INACTIVE}
                            : "휴점" 후 "폐점" 전환 기간 등 (config.php에서 정의된 상수) 치환</div>
                        <div class="form-text small"><i class="bi bi-info-circle me-1"></i>{now} : 오늘 이시간 치환</div>
                        <div class="form-text small"><i class="bi bi-info-circle me-1"></i>{today} : 오늘 날짜 치환</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" id="btn-submit-msg-email"
                            class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-send me-1"></i> 전송</button>
                    </div>
                </form>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100 bg-white p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0"><i class="bi bi-chat-dots me-2 text-primary"></i>메시지 수발신 내역</h6>
                        </div>
                        <form id="form-search-msg" class="d-flex gap-1 mb-3"><input type="text" name="f_msg"
                                class="form-control form-control-sm bg-light border-0 shadow-none"
                                placeholder="메시지 검색..."><button type="submit"
                                class="btn btn-dark btn-sm px-3 fw-bold">검색</button></form>
                        <div id="msg_table_container">
                            <?= renderBoardListHTML($msg_list, $shop_id, '', 'message', $total_msg_pages, 1) ?></div>
                    </div>
                </div>

                <!-- [UI 스크립트] 메시지 리스트에서 답변 남기기 기능 연동 -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // '답변 남기기' 버튼 클릭 시 폼으로 자동 이동 및 세팅
                        document.body.addEventListener('click', function(e) {
                            const btnReply = e.target.closest('.btn-reply-board');
                            if (btnReply) {
                                e.preventDefault();
                                const boardId = btnReply.getAttribute('data-id');
                                let title = btnReply.getAttribute('data-title');

                                if (!title.startsWith('[RE]')) {
                                    title = '[RE] ' + title;
                                }

                                // 폼 필드 자동 채우기
                                const inpParent = document.getElementById('inp_parent_id');
                                const inpTitle = document.getElementById('inp_msg_title');
                                const selType = document.getElementById('sel_send_type');
                                const selTpl = document.getElementById('sel_template_key');
                                const inpContent = document.getElementById('inp_msg_content');

                                if (inpParent) inpParent.value = boardId;
                                if (selType) selType.value = 'message';
                                if (selTpl) selTpl.value = 'custom';
                                if (inpTitle) inpTitle.value = title;

                                // 포커스를 텍스트 영역으로 주고, 부드럽게 스크롤 이동
                                if (inpContent) {
                                    inpContent.value = '';
                                    inpContent.focus();
                                    inpContent.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'center'
                                    });
                                }
                            }
                        });

                        // 전송 후 답변 상태를 기본(새 메시지 작성)으로 초기화
                        const msgForm = document.getElementById('form-send-msg-email');
                        if (msgForm) {
                            msgForm.addEventListener('submit', function() {
                                // 전송 완료 처리를 기다린 후 1초 뒤 폼 정리
                                setTimeout(() => {
                                    const inpParent = document.getElementById('inp_parent_id');
                                    if (inpParent) inpParent.value = '0';
                                    const inpTitle = document.getElementById('inp_msg_title');
                                    if (inpTitle) inpTitle.value = '';
                                    const inpContent = document.getElementById('inp_msg_content');
                                    if (inpContent) inpContent.value = '';
                                }, 1000);
                            });
                        }
                    });
                </script>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 h-100 bg-white p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0"><i class="bi bi-envelope-paper me-2 text-warning"></i>이메일 전송 내역</h6>
                        </div>
                        <form id="form-search-email" class="d-flex gap-1 mb-3"><input type="text" name="f_email"
                                class="form-control form-control-sm bg-light border-0 shadow-none"
                                placeholder="이메일 검색..."><button type="submit"
                                class="btn btn-dark btn-sm px-3 fw-bold">검색</button></form>
                        <div id="email_table_container">
                            <?= renderBoardListHTML($email_list, $shop_id, '', 'email_log', $total_email_pages, 1) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- [2-D] 로그 관리 탭 내용 -->
        <div class="tab-pane fade <?= $active_tab == 'logs' ? 'show active' : '' ?>" id="logs">
            <!-- 로그 검색 폼 -->
            <div class="card shadow-sm border-0 mb-3 bg-white p-3">
                <form method="GET" action="admin_view.php" class="row g-2 align-items-center">
                    <input type="hidden" name="page" value="manage_shop">
                    <input type="hidden" name="id" value="<?= $shop_id ?>">
                    <input type="hidden" name="view" value="logs">
                    <div class="col">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="log_search" class="form-control border-0 bg-light shadow-none"
                                placeholder="제목 또는 내용으로 검색..."
                                value="<?= htmlspecialchars($_GET['log_search'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-dark btn-sm px-3 fw-bold">검색</button>
                    </div>
                    <div class="col-auto"><a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs"
                            class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-info p-3 bg-light text-dark">
                <h6 class="fw-bold mb-3 text-info"><i class="bi bi-clock-history me-2"></i>수동 로그 추가</h6>
                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&view=logs"
                    class="row g-2">
                    <input type="hidden" name="action" value="add_log">
                    <div class="col-md-2">
                        <label class="small fw-bold mb-1">유형</label>
                        <select name="log_type" class="form-select form-select-sm border-0 shadow-sm">
                            <option value="info">정보 (info)</option>
                            <option value="status">상태 (status)</option>
                            <option value="billing">결제 (billing)</option>
                            <option value="message">메시지 (message)</option>
                            <option value="email">이메일 (email)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold mb-1">제목</label>
                        <input type="text" name="log_title" class="form-control form-control-sm border-0 shadow-sm"
                            placeholder="로그 제목" required>
                    </div>
                    <div class="col-md-5">
                        <label class="small fw-bold mb-1">상세 내용</label>
                        <input type="text" name="log_content" class="form-control form-control-sm border-0 shadow-sm"
                            placeholder="상세 내용">
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold mb-1">일시</label>
                        <input type="datetime-local" name="log_date"
                            class="form-control form-control-sm border-0 shadow-sm" value="<?= date('Y-m-d\TH:i') ?>"
                            required>
                    </div>
                    <div class="col-12 mt-2 text-end">
                        <button type="submit"
                            class="btn btn-info btn-sm text-white fw-bold px-4 shadow-sm">추가하기</button>
                    </div>
                </form>
            </div>

            <div class="card shadow-sm border-0 mb-3 bg-white p-3">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ul me-2"></i>상점 히스토리 목록</h6>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light text-center small text-muted">
                            <tr>
                                <th width="15%">일시</th>
                                <th width="10%">유형</th>
                                <th width="25%" class="text-start">제목</th>
                                <th width="40%" class="text-start">상세 내용</th>
                                <th width="10%">관리</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php
                            $log_search = trim($_GET['log_search'] ?? '');
                            $history_log = json_decode($s['history_log'] ?? '[]', true);
                            $filtered_history = [];
                            if (is_array($history_log) && !empty($history_log)) {
                                if ($log_search) {
                                    foreach ($history_log as $idx => $event) {
                                        $title = $event['title'] ?? '';
                                        $content = $event['content'] ?? '';
                                        if (stripos($title, $log_search) !== false || stripos($content, $log_search) !== false) {
                                            $filtered_history[$idx] = $event;
                                        }
                                    }
                                } else {
                                    $filtered_history = $history_log;
                                }
                            }
                            if (!empty($filtered_history)):
                                $reversed_keys = array_reverse(array_keys($filtered_history));
                                foreach ($reversed_keys as $idx):
                                    $event = $filtered_history[$idx];
                                    $date_val = htmlspecialchars($event['date'] ?? '');
                                    $date_input_val = str_replace(' ', 'T', $date_val);
                                    if (strlen($date_input_val) > 16) {
                                        $date_input_val = substr($date_input_val, 0, 16);
                                    }
                                    $badge_color = 'bg-secondary';
                                    switch ($event['type'] ?? '') {
                                        case 'status':
                                            $badge_color = 'bg-warning text-dark';
                                            break;
                                        case 'billing':
                                            $badge_color = 'bg-success';
                                            break;
                                        case 'message':
                                            $badge_color = 'bg-primary';
                                            break;
                                        case 'email':
                                            $badge_color = 'bg-info text-dark';
                                            break;
                                        case 'info':
                                            $badge_color = 'bg-secondary';
                                            break;
                                    }
                            ?>
                                    <tr>
                                        <td class="text-center text-muted"><?= $date_val ?></td>
                                        <td class="text-center"><span
                                                class="badge <?= $badge_color ?> fw-normal"><?= htmlspecialchars($event['type'] ?? 'info') ?></span>
                                        </td>
                                        <td class="text-start fw-bold text-dark"><?= htmlspecialchars($event['title'] ?? '') ?>
                                        </td>
                                        <td class="text-start text-muted">
                                            <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer;"
                                                onclick="this.style.webkitLineClamp = this.style.webkitLineClamp === '2' ? 'unset' : '2';"
                                                title="클릭하여 전체 내용 펼치기/접기">
                                                <?= nl2br(htmlspecialchars($event['content'] ?? '')) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 btn-edit-log"
                                                data-idx="<?= $idx ?>"
                                                data-type="<?= htmlspecialchars($event['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>"
                                                data-title="<?= htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-content="<?= htmlspecialchars($event['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-date="<?= $date_input_val ?>"><i class="bi bi-pencil"></i></button>
                                            <a href="admin_view.php?page=manage_shop&id=<?= $shop_id ?>&action=delete_log&log_index=<?= $idx ?>"
                                                class="btn btn-sm btn-outline-danger border-0 py-0 px-1"
                                                onclick="return confirm('이 로그를 삭제하시겠습니까?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <?= $log_search ? '검색된 로그가 없습니다.' : '기록된 히스토리가 없습니다.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- // tab-pane id="logs" -->
    </div> <!-- // tab-content -->
</div> <!-- // col-12 -->