<?php

/**
 * KShops24 고객 리뷰 관리 모듈 (manage_shop_review.php)
 * - 역할: 고객들이 남긴 리뷰를 조회, 삭제, 그리고 답변을 다는 기능 제공
 * - 실행: manage_shop.php 컨테이너 내에서 include 되어 실행됨
 */

if (!isset($shop_id)) {
    exit('Error: $shop_id is not set. This page must be included with a valid $shop_id.');
} // 직접 접근 방지

// 기본 이미지 설정 (원하는 이모티콘 파일명으로 변경하세요)
$default_profile_img = '/assets/default_emoticon.png';

// $shop 정보 로드 (is_show_review 값 포함)
$stmt_shop = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt_shop->execute([$shop_id]);
$shop = $stmt_shop->fetch(PDO::FETCH_ASSOC);

$ui = json_decode($shop['ui_settings'] ?? '{}', true);
$supported_langs_name = [
    'ko' => '한국어',
    'en' => '영어',
    'tl' => '따갈로그어',
    'zh' => '중국어',
    'ja' => '일본어',
    'vi' => '베트남어',
    'th' => '태국어',
    'id' => '인도네시아어',
    'ms' => '말레이시아어',
    'es' => '스페인어',
    'fr' => '프랑스어',
    'de' => '독일어',
    'ru' => '러시아어'
];
$is_multi = (($ui['is_multilingual'] ?? 0) == 1);
$lang1 = $ui['multilingual_lang1'] ?? 'none';
$lang2 = $ui['multilingual_lang2'] ?? 'none';
$lang1_code = $lang1;
$lang2_code = $lang2;
$lang1_display = $supported_langs_name[$lang1_code] ?? '제1외국어';
$lang2_display = $supported_langs_name[$lang2_code] ?? '제2외국어';

// [AJAX] 리뷰 노출 설정 변경
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_review_display') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $is_show = (int)($_POST['is_show'] ?? 1);
    try {
        $pdo->prepare("UPDATE shops SET is_show_review = ? WHERE id = ?")->execute([$is_show, $shop_id]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_review_label') {
    $existing_ui = json_decode($shop['ui_settings'] ?? '{}', true);
    if (!is_array($existing_ui)) $existing_ui = [];
    $ui_raw = $_POST['ui'] ?? [];
    $ui_new = array_map('trim', $ui_raw);
    $ui_merged = array_merge($existing_ui, $ui_new);
    $pdo->prepare("UPDATE shops SET ui_settings = ? WHERE id = ?")->execute([json_encode($ui_merged, JSON_UNESCAPED_UNICODE), $shop_id]);
    if (ob_get_level() > 0) ob_end_clean();
    header("Location: manage_shop.php?pg=manage_shop_review&msg=label_saved");
    exit;
}

// 2. 액션 처리 (리뷰 삭제 및 답변 저장)
if (isset($_GET['action']) && $_GET['action'] == 'delete_review' && isset($_GET['id'])) {
    $rev_id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM reviews WHERE id = ? AND shop_id = ?")->execute([$rev_id, $shop_id]);
    if (ob_get_level() > 0) ob_end_clean();
    header("Location: manage_shop.php?pg=manage_shop_review&msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_reply') {
    $rev_id = (int)$_POST['review_id'];
    $reply = trim($_POST['reply_content']);
    if (empty($reply)) {
        $pdo->prepare("UPDATE reviews SET owner_reply = NULL, reply_created_at = NULL WHERE id = ? AND shop_id = ?")->execute([$rev_id, $shop_id]);
    } else {
        $pdo->prepare("UPDATE reviews SET owner_reply = ?, reply_created_at = NOW() WHERE id = ? AND shop_id = ?")->execute([$reply, $rev_id, $shop_id]);
    }
    if (ob_get_level() > 0) ob_end_clean();
    header("Location: manage_shop.php?pg=manage_shop_review&msg=replied");
    exit;
}

// 3. 알림 메시지 출력
if (isset($_GET['msg'])) {
    $msg = '';
    $msg_type = 'success';
    if ($_GET['msg'] === 'deleted') {
        $msg = '리뷰가 성공적으로 삭제되었습니다.';
        $msg_type = 'info';
    }
    if ($_GET['msg'] === 'replied') {
        $msg = '사장님의 답변이 저장되었습니다.';
    }
    if ($_GET['msg'] === 'label_saved') {
        $msg = '섹션 제목이 저장되었습니다.';
    }
    if ($msg) echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg}', '{$msg_type}'); });</script>";
}

// 4. 페이징 및 데이터 로드
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 10;
$offset = ($page - 1) * $limit;

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE shop_id = ?");
$stmt_count->execute([$shop_id]);
$total_reviews = $stmt_count->fetchColumn();
$total_pages = ceil($total_reviews / $limit) ?: 1;

$stmt = $pdo->prepare("
    SELECT r.*, c.nickname as customer_name, c.profile_img 
    FROM reviews r
    LEFT JOIN platform_customers c ON r.customer_id = c.id
    WHERE r.shop_id = ?
    ORDER BY r.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $shop_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

// 데이터를 가져온 후, 프로필 이미지가 없는 경우 기본 이미지로 처리
foreach ($reviews as &$review) {
    if (empty($review['profile_img'])) {
        $review['profile_img'] = $default_profile_img;
    }
}
// reference 관계를 해제합니다.
unset($review);
?>

<div class="container-fluid p-0">
    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('리뷰 관리', 'bi-chat-square-text', '<span class="badge bg-white text-secondary border shadow-sm px-3 py-2 rounded-pill">총 <span class="text-primary">' . number_format($total_reviews) . '</span>개의 리뷰</span>'); ?>

    <div class="row g-2">
        <?php if (empty($reviews)): ?>
            <div class="col-12 text-center py-4 bg-white rounded-4 border shadow-sm text-muted">
                <i class="bi bi-chat-left-dots fs-1 d-block mb-2 opacity-50"></i>
                등록된 고객 리뷰가 없습니다.
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 mb-2">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo htmlspecialchars($rev['profile_img'] ?: '/assets/no-logo.png'); ?>" class="rounded-circle shadow-sm" style="width:36px; height:36px; object-fit:cover; border:2px solid #eee;">
                                    <div>
                                        <div class="fw-bold text-dark mb-0" style="font-size: 0.9rem;"><?php echo htmlspecialchars($rev['customer_name'] ?: '고객'); ?></div>
                                        <div class="text-warning" style="font-size: 0.8rem;">
                                            <?php for ($i = 1; $i <= 5; $i++) echo $i <= $rev['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted mb-1" style="font-size:0.75rem;"><?php echo substr($rev['created_at'], 0, 16); ?></div>
                                    <a href="manage_shop.php?pg=manage_shop_review&action=delete_review&id=<?php echo $rev['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2 shadow-sm" style="font-size:0.7rem;" onclick="return confirm('이 리뷰를 완전히 삭제하시겠습니까? (복구 불가)')"><i class="bi bi-trash"></i> 삭제</a>
                                </div>
                            </div>
                            <p class="mb-2 text-dark small" style="line-height: 1.4;"><?php echo nl2br(htmlspecialchars($rev['content'])); ?></p>

                            <?php if (!empty($rev['owner_reply'])): ?>
                                <div class="bg-light p-2 rounded-4 position-relative border border-primary border-opacity-25 mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-primary" style="font-size: 0.8rem;"><i class="bi bi-arrow-return-right me-1"></i>사장님 답변</span>
                                        <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" style="font-size: 0.75rem;"
                                            data-reply="<?php echo htmlspecialchars($rev['owner_reply'], ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="openReplyModal(<?php echo $rev['id']; ?>, this)"><i class="bi bi-pencil me-1"></i>수정</button>
                                    </div>
                                    <p class="mb-0 text-dark fw-medium" style="line-height: 1.4; font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($rev['owner_reply'])); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="text-end mt-2 pt-2 border-top">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-3 fw-bold shadow-sm" style="font-size: 0.8rem;"
                                        data-reply=""
                                        onclick="openReplyModal(<?php echo $rev['id']; ?>, this)"><i class="bi bi-chat-dots me-1"></i>답변 남기기</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4 mb-5">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?pg=manage_shop_review&p=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?pg=manage_shop_review&p=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="?pg=manage_shop_review&p=<?php echo $page + 1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- 섹션 제목 (레이블) 관리 영역 -->

    <div class="row g-3 mb-4 mt-2">
        <div class="col-12">
            <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-primary">
                <div class="p-3 p-md-4 d-flex flex-column h-100">
                    <?php echo renderSectionHeader('리뷰 섹션 설정', 'bi-gear', [], '<div class="form-check form-switch m-0 d-flex align-items-center"><input class="form-check-input ms-0 me-2 mt-0" type="checkbox" id="toggleReviewDisplay" ' . ((!isset($shop['is_show_review']) || $shop['is_show_review'] == 1) ? 'checked' : '') . ' onchange="toggleReview(this)"><label class="form-check-label fw-bold text-primary mb-0" for="toggleReviewDisplay" style="cursor: pointer;">홈페이지 노출</label></div>'); ?>

                    <div class="bg-light p-3 rounded-4">
                        <form method="POST" action="manage_shop.php?pg=manage_shop_review">
                            <input type="hidden" name="action" value="save_review_label">

                            <?php if ($is_multi && ($lang1 !== 'none' || $lang2 !== 'none')): ?>
                                <ul class="nav nav-tabs mb-3" id="review-label-tab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active fw-bold px-3 py-2" id="review-ko-tab" data-bs-toggle="tab" data-bs-target="#review-ko-pane" type="button" role="tab">한국어 (기본)</button>
                                    </li>
                                    <?php if ($lang1 !== 'none'): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link fw-bold px-3 py-2" id="review-lang1-tab" data-bs-toggle="tab" data-bs-target="#review-lang1-pane" type="button" role="tab"><?php echo htmlspecialchars($lang1_display); ?></button>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($lang2 !== 'none'): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link fw-bold px-3 py-2" id="review-lang2-tab" data-bs-toggle="tab" data-bs-target="#review-lang2-pane" type="button" role="tab"><?php echo htmlspecialchars($lang2_display); ?></button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="tab-content mb-3" id="review-label-tabContent">
                                <div class="tab-pane fade show active" id="review-ko-pane" role="tabpanel">
                                    <label class="form-label small fw-bold text-muted">섹션 제목 (레이블)</label>
                                    <input type="text" name="ui[label_review]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_review'] ?? '고객 리뷰'); ?>" placeholder="예: 고객 리뷰">
                                </div>
                                <?php if ($is_multi && $lang1 !== 'none'): ?>
                                    <div class="tab-pane fade" id="review-lang1-pane" role="tabpanel">
                                        <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang1_display); ?>)</label>
                                        <input type="text" name="ui[label_review_<?php echo $lang1_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_review_' . $lang1_code] ?? ''); ?>">
                                    </div>
                                <?php endif; ?>
                                <?php if ($is_multi && $lang2 !== 'none'): ?>
                                    <div class="tab-pane fade" id="review-lang2-pane" role="tabpanel">
                                        <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang2_display); ?>)</label>
                                        <input type="text" name="ui[label_review_<?php echo $lang2_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_review_' . $lang2_code] ?? ''); ?>">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 저장</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>


</div>

<!-- [모달] 사장님 답변 작성 -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4" method="POST" action="manage_shop.php?pg=manage_shop_review">
            <input type="hidden" name="action" value="save_reply">
            <input type="hidden" name="review_id" id="reply_review_id">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-left-text me-2 text-primary"></i>사장님 답변 작성</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="small text-muted mb-3">고객의 리뷰에 친절한 답변을 남겨주세요.<br>내용을 지우고 저장하면 답변이 삭제됩니다.</p>
                <textarea name="reply_content" id="reply_content" class="form-control bg-light border-0 p-3" rows="5" placeholder="고객님, 이용해 주셔서 감사합니다!"></textarea>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm">답변 저장하기 <i class="bi bi-send-fill ms-1"></i></button></div>
        </form>
    </div>
</div>

<script>
    // 사장님 답변 모달 띄우기 및 폼 세팅 (스크립트로 강제 호출)
    function openReplyModal(reviewId, btnEl) {
        document.getElementById('reply_review_id').value = reviewId;
        document.getElementById('reply_content').value = btnEl.getAttribute('data-reply') || '';
        try {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('replyModal')).show();
        } catch (e) {
            console.error("Modal Error: ", e);
        }
    }

    // 리뷰 노출 토글 스위치 변경 시 서버에 변경 요청을 보내는 함수
    // @param {HTMLElement} el - 토글 스위치(checkbox) 요소, 체크 여부에 따라 리뷰 노출 설정을 변경함
    function toggleReview(el) {
        const isShow = el.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('action', 'toggle_review_display');
        formData.append('is_show', isShow);

        fetch('manage_shop.php?pg=manage_shop_review', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    return res.json();
                } else {
                    const text = await res.text();
                    throw new Error('서버에서 JSON이 아닌 응답을 반환했습니다: ' + text);
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    if (typeof showToast === 'function') showToast('리뷰 섹션 노출 설정이 변경되었습니다.', 'success');
                } else {
                    alert('변경 실패: ' + data.message);
                    el.checked = !el.checked;
                }
            })
            .catch(err => {
                alert('통신 오류 또는 서버 오류가 발생했습니다.\n' + err.message);
                el.checked = !el.checked;
            });
    }
</script>