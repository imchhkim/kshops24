<?php
/**
 * [탭 파일] 메시지 및 이메일 관리
 * 위치: admin/manage_shop_tab_message.php
 */
if (!isset($pdo)) exit;

// =========================================================================
// [공통 함수] 메시지/이메일 내역 렌더링
// =========================================================================
if (!function_exists('renderBoardListHTML')) {
    function renderBoardListHTML($board_list, $shop_id, $f_keyword, $board_type, $total_pages, $current_page) {
        ob_start();
        $type_label = ($board_type === 'email_log') ? '이메일' : '메시지';
        $bg_class = ($board_type === 'email_log') ? 'bg-warning-subtle' : 'bg-white';
        $ajax_class = ($board_type === 'email_log') ? 'ajax-email-link' : 'ajax-msg-link';
?>
        <div class="list-group shadow-sm border-0 rounded overflow-hidden text-start mb-3">
            <?php foreach ($board_list as $b): ?>
                <div class="list-group-item p-3 border-0 border-bottom <?= $bg_class ?>">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $b['sender_type'] == 'admin' ? 'bg-dark' : 'bg-info text-white' ?>"><?= $b['sender_type'] == 'admin' ? '본사' : '상점' ?></span>
                            <?php if ($board_type == 'email_log'): ?><small class="text-warning fw-bold"><i class="bi bi-envelope-paper"></i> Email Log</small><?php endif; ?>
                        </div>
                        <div>
                            <span class="me-2"><?= $b['created_at'] ?></span>
                            <?php if ($board_type !== 'email_log'): ?>
                                <button type="button" class="btn btn-link p-0 text-primary me-2 btn-reply-board" data-id="<?= $b['id'] ?>" data-title="<?= htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="답변 남기기"><i class="bi bi-reply-fill"></i></button>
                                <button type="button" class="btn btn-link p-0 text-secondary me-1 btn-edit-board" data-id="<?= $b['id'] ?>" data-title="<?= htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-content="<?= htmlspecialchars($b['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-type="<?= $b['type'] ?>"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-link p-0 text-danger btn-delete-board" data-id="<?= $b['id'] ?>"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($b['title']) ?></h6>
                    <div class="small text-secondary mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer;" onclick="this.style.webkitLineClamp = this.style.webkitLineClamp === '2' ? 'unset' : '2';" title="클릭하여 전체 내용 펼치기/접기"><?= nl2br(htmlspecialchars($b['content'])) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($board_list)) echo "<div class='p-5 text-center bg-white text-muted small'>검색된 {$type_label} 내역이 없습니다.</div>"; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): $url = "admin_view.php?page=manage_shop&id={$shop_id}&ajax_board={$board_type}&page_num={$i}&keyword=" . urlencode($f_keyword); ?>
                        <li class="page-item <?= ($i == $current_page ? 'active' : '') ?>"><a class="page-link shadow-none border-0 mx-1 rounded-circle text-center <?= $ajax_class ?>" style="width: 30px;" href="<?= $url ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
<?php
        return ob_get_clean();
    }
}

// =========================================================================
// [1] Action 처리 (POST/GET)
// =========================================================================
if ($tab_mode === 'action') {
    // AJAX GET 호출 (목록 페이징 검색)
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

    // POST 전송
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'ajax_send_msg_email') {
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json');
            $send_type = $_POST['send_type'] ?? 'message';
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $template_key = $_POST['template_key'] ?? '';
            $parent_id = (int)($_POST['parent_id'] ?? 0);
            
            if (empty($title) || empty($content)) {
                echo json_encode(['status' => 'error', 'message' => '제목과 내용을 입력해주세요.']); exit;
            }
            
            if (function_exists('replaceShopTemplateVars')) {
                $replaced = replaceShopTemplateVars($pdo, $shop_id, ['title' => $title, 'content' => $content]);
                $title = $replaced['title'];
                $content = $replaced['content'];
            }
            
            if ($send_type === 'message') {
                $pdo->prepare("INSERT INTO shop_board (shop_id, parent_id, type, sender_type, title, content, created_at) VALUES (?, ?, 'message', 'admin', ?, ?, NOW())")->execute([$shop_id, $parent_id, $title, $content]);
                addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_MESSAGE, "관리자 메시지 발송", "제목: {$title}");
                
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
                    echo json_encode(['status' => 'error', 'message' => '수신할 이메일 주소가 없습니다.']); exit;
                }
                $email_result = false;
                if ($template_key === 'custom') {
                    $to_email = $shop_info['manager_email'];
                    $subject = '=?UTF-8?B?' . base64_encode($title) . '?=';
                    $html_content = '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>table { width: 100% !important; max-width: 100% !important; } img { max-width: 100% !important; height: auto !important; }</style></head><body style="margin:0; padding:15px; background-color:#f4f7f9; font-family:\'Apple SD Gothic Neo\', \'Malgun Gothic\', sans-serif;"><div style="width:100%; max-width:650px; margin:0 auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); box-sizing: border-box;"><div style="background-color:#004aad; height:6px; width:100%;"></div><div style="padding:30px 20px; color:#333; line-height:1.6; font-size:15px; word-break: break-word; overflow-x: hidden;">' . nl2br(htmlspecialchars($content)) . '</div></div></body></html>';
                    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nFrom: KShops24 <support@kshops24.com>\r\nX-Mailer: PHP/" . phpversion();
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
        
        if ($action === 'ajax_delete_board') {
            if (ob_get_level()) ob_clean(); header('Content-Type: application/json');
            $board_id = (int)($_POST['board_id'] ?? 0);
            if ($board_id > 0) {
                $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = ?")->execute([$board_id, $shop_id]);
                echo json_encode(['status' => 'success']);
            } else { echo json_encode(['status' => 'error', 'message' => '유효하지 않은 항목입니다.']); }
            exit;
        }
        
        if ($action === 'ajax_edit_board') {
            if (ob_get_level()) ob_clean(); header('Content-Type: application/json');
            $board_id = (int)($_POST['board_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($board_id > 0) {
                $pdo->prepare("UPDATE shop_board SET title = ?, content = ? WHERE id = ? AND shop_id = ? AND type = 'message'")->execute([$title, $content, $board_id, $shop_id]);
                echo json_encode(['status' => 'success']);
            } else { echo json_encode(['status' => 'error', 'message' => '유효하지 않은 항목입니다.']); }
            exit;
        }
    }
}

// =========================================================================
// [2] Data 로딩
// =========================================================================
if ($tab_mode === 'data') {
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

        // 상점주가 보낸 새로운 메시지 읽음 처리
        $pdo->prepare("UPDATE shop_board SET is_read = 1 WHERE shop_id = ? AND sender_type = 'shop' AND is_read = 0")->execute([$shop_id]);
    }
}

// =========================================================================
// [3] View 렌더링
// =========================================================================
if ($tab_mode === 'view'):
?>
    <div class="card shadow-sm border-0 mb-4 p-4 bg-light text-start border-start border-4 border-primary">
        <div class="settings-title fs-5 fw-bold mb-3 text-primary"><i class="bi bi-envelope me-2"></i> 상점에 메시지/이메일 보내기</div>
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
                        <option value="<?= htmlspecialchars($tpl_key) ?>"><?= htmlspecialchars($tpl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="small fw-bold mb-1">제목</label><input type="text" name="title" id="inp_msg_title" class="form-control shadow-sm" required></div>
            <div class="col-12"><label class="small fw-bold mb-1">내용</label><textarea name="content" id="inp_msg_content" class="form-control shadow-sm" rows="4" required></textarea></div>
            <div class="col-12 text-end">
                <button type="submit" id="btn-submit-msg-email" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-send me-1"></i> 전송</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2 text-primary"></i>메시지 수발신 내역</h6>
                <form id="form-search-msg" class="d-flex gap-1 mb-3"><input type="text" name="f_msg" class="form-control form-control-sm bg-light border-0 shadow-none" placeholder="메시지 검색..."><button type="submit" class="btn btn-dark btn-sm px-3 fw-bold">검색</button></form>
                <div id="msg_table_container"><?= renderBoardListHTML($msg_list, $shop_id, '', 'message', $total_msg_pages, 1) ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white p-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-envelope-paper me-2 text-warning"></i>이메일 전송 내역</h6>
                <form id="form-search-email" class="d-flex gap-1 mb-3"><input type="text" name="f_email" class="form-control form-control-sm bg-light border-0 shadow-none" placeholder="이메일 검색..."><button type="submit" class="btn btn-dark btn-sm px-3 fw-bold">검색</button></form>
                <div id="email_table_container"><?= renderBoardListHTML($email_list, $shop_id, '', 'email_log', $total_email_pages, 1) ?></div>
            </div>
        </div>
    </div>

    <!-- [모달] 메시지 수정 모달 -->
    <div class="modal fade" id="editBoardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>메시지 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="board_id" id="edit_board_id">
                        <div class="mb-3"><label class="small mb-1 fw-bold">제목</label><input type="text" name="title" id="edit_board_title" class="form-control" required></div>
                        <div class="mb-3"><label class="small mb-1 fw-bold">내용</label><textarea name="content" id="edit_board_content" class="form-control" rows="5" required></textarea></div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-dark px-5 fw-bold shadow-sm">수정 완료</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>