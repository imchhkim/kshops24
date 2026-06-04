<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $action = $_POST['action'] ?? 'fetch';
    $context = $_POST['context'] ?? 'customer'; // 호출 컨텍스트 (admin/customer)

    // [추가] 문의 내역 삭제 처리
    if ($action === 'delete') {
        header('Content-Type: application/json');
        $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);

        if (!$inquiry_id || empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
            exit;
        }

        try {
            // 보안: 해당 상점의 해당 고객 번호로 접수된 문의만 삭제 가능
            $stmt = $pdo->prepare("DELETE FROM shop_inquiries WHERE id = ? AND shop_id = ? AND customer_phone = ?");
            $stmt->execute([$inquiry_id, $shop_id, $phone]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => '삭제 중 오류가 발생했습니다.']);
        }
        exit;
    }

    // [추가] 상담 취소 처리
    if ($action === 'cancel') {
        header('Content-Type: application/json');
        $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);

        if (!$inquiry_id || empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
            exit;
        }

        try {
            // 보안: 본인의 문의 내역 중 '상담 대기' 또는 '상담 중' 상태인 경우에만 취소 가능
            $stmt = $pdo->prepare("UPDATE shop_inquiries SET status = 'cancelled' WHERE id = ? AND shop_id = ? AND customer_phone = ? AND status IN ('pending', 'contacted')");
            $stmt->execute([$inquiry_id, $shop_id, $phone]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '취소할 수 없는 상태이거나 권한이 없습니다.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => '취소 중 오류가 발생했습니다.']);
        }
        exit;
    }

    if (!$shop_id || empty($phone)) {
        echo '<div class="text-center py-4 text-danger">올바른 요청이 아닙니다.</div>';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM shop_inquiries WHERE shop_id = ? AND customer_phone = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$shop_id, $phone]);
        $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($inquiries)) {
            echo '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>문의 내역이 없습니다.</div>';
            exit;
        }

        $status_map = [
            'pending' => ['label' => '상담 대기', 'color' => 'bg-warning text-dark'],
            'contacted' => ['label' => '상담 중', 'color' => 'bg-info text-white'],
            'completed' => ['label' => '상담 완료', 'color' => 'bg-success'],
            'cancelled' => ['label' => '상담 취소', 'color' => 'bg-secondary']
        ];

        $html = '<div class="d-flex flex-column gap-3">';
        foreach ($inquiries as $inq) {
            $status = $status_map[$inq['status']] ?? ['label' => '알 수 없음', 'color' => 'bg-light text-dark'];
            $items = json_decode($inq['inquiry_data'], true);
            $item_names = [];
            if (is_array($items)) foreach ($items as $item) $item_names[] = htmlspecialchars($item['name'] ?? '매물');

            $item_str = implode(', ', $item_names);
            $date = date('Y-m-d H:i', strtotime($inq['created_at']));
            $inquiry_text = nl2br(htmlspecialchars($inq['customer_inquiry'] ?: '상세 요청사항 없음'));

            $html .= "<div class='card border-0 shadow-sm rounded-4'><div class='card-body p-3'>
                        <div class='d-flex justify-content-between align-items-start mb-2'>
                            <div>
                                <span class='badge {$status['color']} rounded-pill px-2'>{$status['label']}</span>
                                <span class='small text-muted ms-1'>{$date}</span>
                            </div>
                            ";
            // [수정] 관리자 컨텍스트에서는 삭제 버튼을 노출하지 않음
            if ($context !== 'admin') {
                $html .= "<div class='d-flex align-items-center gap-1'>";
                if ($inq['status'] === 'pending' || $inq['status'] === 'contacted') {
                    $html .= "<button type='button' class='btn btn-sm btn-outline-warning py-0 px-2 shadow-sm' style='font-size: 0.75rem; border-radius: 12px;' onclick='confirmCancelInquiry({$inq['id']})'>상담취소</button>";
                }
                $html .= "<button type='button' class='btn btn-sm btn-outline-danger border-0 py-0 px-2' onclick='confirmDeleteInquiry({$inq['id']})' title='내역 삭제'><i class='bi bi-trash'></i></button>";
                $html .= "</div>";
            }
            $html .= "</div>
                        <h6 class='fw-bold text-dark mb-2 text-truncate'><i class='bi bi-building me-1'></i>{$item_str}</h6>
                        <div class='bg-light p-2 rounded-3 small text-muted'><i class='bi bi-chat-quote text-primary me-1'></i>{$inquiry_text}</div>";

            // [추가] 상점 안내용 답변 출력
            if (!empty($inq['owner_reply'])) {
                $owner_reply_text = nl2br(htmlspecialchars($inq['owner_reply']));
                $html .= "<div class='bg-primary bg-opacity-10 p-2 rounded-3 small text-dark border border-primary border-opacity-25 mt-2'>
                            <div class='fw-bold text-primary mb-1'><i class='bi bi-reply-fill me-1'></i>상점의 답변</div>
                            <div>{$owner_reply_text}</div>
                          </div>";
            }

            // [추가] 상점 전용 메모 출력 (관리자 컨텍스트에서만 노출)
            if ($context === 'admin' && !empty($inq['owner_memo'])) {
                $owner_memo_text = nl2br(htmlspecialchars($inq['owner_memo']));
                $html .= "<div class='bg-warning bg-opacity-10 p-2 rounded-3 small text-dark border border-warning border-opacity-25 mt-2'>
                            <div class='fw-bold text-warning-emphasis mb-1'><i class='bi bi-journal-text me-1'></i>상점 전용 메모 <span class='fw-normal text-muted'>(고객 미노출)</span></div>
                            <div>{$owner_memo_text}</div>
                          </div>";
            }

            $html .= "</div></div>";
        }
        echo $html . '</div>';
    } catch (Exception $e) {
        echo '<div class="text-center py-4 text-danger">통신 오류가 발생했습니다.</div>';
    }
}
