<?php
/**
 * [공통 컴포넌트] KShops24 공통 모달 모음
 * 위치: /common/shop_common_modals.php
 * 역할: 모든 상점(FNB, Realty, SRV 등)에서 공통으로 사용되는 모달 UI
 */
?>
<!-- [공통] 시스템 알림 토스트 (sysToast) -->
<div class="toast-container position-fixed end-0 p-3" style="bottom: 80px; z-index: 2100;">
    <div id="sysToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="sysToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- [공통] 연락처 등록/수정 모달 (phInfoModal) -->
<div class="modal fade" id="phInfoModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard me-2"></i><?php echo __('내 연락처 등록/수정'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small"><?php echo __('원활한 상담 및 처리를 위해 본인의 핸드폰 번호를 한 번만 입력해 주세요.'); ?></p>
                <form id="phInfoForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold small"><?php echo __('본인 핸드폰 번호'); ?></label>
                        <p class="bi bi-info-circle-fill text-primary mb-0 mt-0" style="font-size: 0.7rem"> <?php echo __(PHONE_INFO_NOTICE_1); ?></p>
                        <!-- [수정] 1순위: DB 기반 PHP 세션 번호를 자동 완성합니다. -->
                        <input type="tel" id="ph_phone" class="form-control mt-2" placeholder="0917-123-4567" oninput="formatPhoneInput(this)" maxlength="13" required value="<?php echo htmlspecialchars(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>">
                    </div>
                    <input type="hidden" id="ph_address" value="공용 정보 등록">
                    <input type="hidden" id="ph_landmark" value="">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill"><?php echo __('저장'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// [추가] 2순위: 비회원 등 세션에 번호가 없는 상태로 모달이 열릴 경우 브라우저(로컬 스토리지)에 저장된 번호를 불러옵니다.
document.addEventListener('DOMContentLoaded', function() {
    const phModal = document.getElementById('phInfoModal');
    if (phModal) {
        phModal.addEventListener('show.bs.modal', function () {
            const phoneInput = document.getElementById('ph_phone');
            if (phoneInput && !phoneInput.value.trim()) {
                const localPhone = localStorage.getItem('ps24_guest_phone') || localStorage.getItem('srv_last_search_phone') || localStorage.getItem('realty_last_search_phone');
                if (localPhone) {
                    phoneInput.value = localPhone;
                    if (typeof formatPhoneInput === 'function') formatPhoneInput(phoneInput);
                }
            }
        });
    }
});
</script>

<!-- [공통] 리뷰 작성 모달 (reviewWriteModal) -->
<div class="modal fade" id="reviewWriteModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="reviewModalTitle"><i class="bi bi-pencil-square me-2 text-primary"></i><?php echo __('리뷰 작성'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0 text-center">
                <input type="hidden" id="review_action" value="write">
                <input type="hidden" id="edit_review_id" value="">
                <div class="mb-3">
                    <p class="small text-muted mb-2 fw-bold"><?php echo __('이 상점에서의 경험은 어떠셨나요?'); ?></p>
                    <div class="rating-stars text-warning fs-1 d-flex justify-content-center gap-1" style="cursor: pointer;">
                        <i class="bi bi-star-fill" data-rating="1"></i>
                        <i class="bi bi-star-fill" data-rating="2"></i>
                        <i class="bi bi-star-fill" data-rating="3"></i>
                        <i class="bi bi-star-fill" data-rating="4"></i>
                        <i class="bi bi-star-fill" data-rating="5"></i>
                    </div>
                    <input type="hidden" id="review_rating" value="5">
                </div>
                <textarea id="review_content" class="form-control bg-light border-0 p-3" rows="5" placeholder="<?php echo htmlspecialchars(__('다른 고객들에게 도움이 되도록 솔직한 리뷰를 남겨주세요. 악의적인 리뷰는 삭제될 수 있습니다.')); ?>" required style="font-size: 0.95rem;"></textarea>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm" onclick="submitReview()"><?php echo __('리뷰 등록하기'); ?> <i class="bi bi-send-fill ms-1"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- [공통] 모든 리뷰 보기 모달 (reviewListModal) -->
<div class="modal fade" id="reviewListModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light py-3 border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-left-text me-2 text-primary"></i><?php echo __('모든 고객 리뷰'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light" id="reviewListContainer"></div>
            <div class="modal-footer border-0 p-3 bg-white justify-content-center shadow-sm">
                <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-bold" id="btnLoadMoreReviews" style="display:none;" onclick="loadReviews()"><?php echo __('더 보기'); ?> <i class="bi bi-chevron-down ms-1"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- [공통] 로그인 선택 모달 (loginChoiceModal) -->
<div class="modal fade" id="loginChoiceModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-white border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold mx-auto text-dark"><i class="bi bi-question-circle me-2 text-primary"></i><?php echo __('로그인 방법 선택'); ?></h5>
                <button type="button" class="btn-close ms-0" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-secondary mb-4"><?php echo __('카카오톡으로 로그인하시면,'); ?><br><strong><?php echo __('연락처 입력 등의 번거로움이'); ?><br><?php echo __('이후부터 영구히 사라집니다.'); ?></strong></p>
                <div class="d-grid gap-3">
                    <button type="button" class="btn fw-bold py-3 rounded-pill shadow-sm" style="background-color:#FEE500; color:#3A1D1D; border:none;" onclick="executeKakaoLoginModal(this)">
                        <i class="bi bi-chat-fill me-2"></i> <?php echo __('카카오톡으로 1초 로그인'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary py-3 rounded-pill fw-bold" onclick="if(typeof continueWithoutLogin === 'function') continueWithoutLogin();">
                        <?php echo __('로그인 없이 계속하기'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// [버그 수정] 외부 JS 파일(shop_common.js)에서 PHP 변수($kakao_login_url)를 파싱하지 못해 발생하는 
// "인증 정보가 부족합니다" 카카오 KOE 에러를 원천 차단하기 위해, PHP 파일 내부에서 안전하게 렌더링 후 리다이렉트 처리
function executeKakaoLoginModal(btnElem) {
    // 시각적 피드백 (로딩 스피너 작동)
    if (btnElem) {
        btnElem.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ...';
        btnElem.classList.add('disabled');
    }
    
    // 이전에 저장된 세션 액션(장바구니 복원, 문의 내역 등)을 보존하며 즉시 카카오 서버로 이동
    window.location.href = '<?php echo $kakao_login_url ?? ""; ?>';
}
</script>

<!-- [공통] 커스텀 알림 모달 (customAlertModal) -->
<div class="modal fade" id="customAlertModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <div id="customAlertIcon" class="mb-3"></div>
                <h6 class="fw-bold mb-2" id="customAlertTitle"><?php echo __('알림'); ?></h6>
                <p class="small text-muted mb-4" id="customAlertMessage"></p>
                <button type="button" class="btn w-100 fw-bold rounded-pill shadow-sm" id="customAlertBtn" data-bs-dismiss="modal"><?php echo __('확인'); ?></button>
            </div>
        </div>
    </div>
</div>