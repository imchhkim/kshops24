<?php
/**
 * [공통 컴포넌트] KShops24 공통 모달 모음
 * 위치: /common/shop_common_modals.php
 * 역할: 모든 상점(FNB, Realty, SRV 등)에서 공통으로 사용되는 모달 UI
 */

// [버그 수정] 자바스크립트 환경 변수가 지연 로딩될 경우를 대비해 PHP에서 상점 국가 설정을 직접 주입
$common_cCode = 'PH';
if (isset($shop['ui_settings'])) {
    $common_ui = json_decode($shop['ui_settings'], true);
    if (!empty($common_ui['country'])) $common_cCode = $common_ui['country'];
} elseif (isset($ui['country'])) {
    $common_cCode = $ui['country'];
}

// [개선] config.php의 공통 배열을 활용하여 모달 렌더링 시 Placeholder 및 힌트 텍스트 처리
$phone_format = function_exists('getCountryPhoneFormat') ? getCountryPhoneFormat($common_cCode) : ['placeholder' => '0917 123 4567', 'hint' => '(필리핀)'];
$ph_placeholder = $phone_format['placeholder'];
$ph_hint_text = $phone_format['hint'];
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

<!-- [공통] 내 연락처 등록/수정 모달 (phInfoModal) -->
<div class="modal fade" id="phInfoModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard me-2"></i><?php echo __('내 연락처 등록/수정'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small"><?php echo __('원활한 상담 및 처리를 위해 본인의 핸드폰 번호를 한 번만 입력해 주세요. 타인이 사용하고 있는 번호는 사용할 수 없습니다.'); ?></p>
                <form id="phInfoForm" onsubmit="validateAndSavePhone(event)">
                    <div class="mb-3">
                        <label class="form-label fw-bold small"><?php echo __('본인 핸드폰 번호'); ?> <span id="phCountryHint" class="text-primary ms-1 fw-normal" style="font-size: 0.8rem;"><?php echo $ph_hint_text; ?></span></label>
                        <p <?php echo UI_INFO_SM_LABEL;?>> <?php echo __(PHONE_INFO_NOTICE_1); ?></p>
                        <!-- [수정] 1순위: DB 기반 PHP 세션 번호를 자동 완성합니다. 국가별 포맷팅은 JS에서 일임합니다. -->
                        <input type="tel" id="ph_phone" class="form-control mt-2" placeholder="<?php echo $ph_placeholder; ?>" maxlength="20" required value="<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $_SESSION['customer_ph_phone'] ?? '')); ?>">
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
// [UX 개선] PHP 공용 설정 배열을 JavaScript에서도 활용할 수 있도록 JSON 주입
const countryPhoneFormats = <?php echo json_encode(isset($country_phone_formats) ? $country_phone_formats : ['PH' => ['placeholder' => '0917 123 4567', 'hint' => '(필리핀)']]); ?>;

// [UX 개선] 입력 필드에 국가별 전화번호 포맷팅(하이픈) 및 커서 위치 보정 기능 탑재
document.addEventListener('DOMContentLoaded', function() {
    const phModal = document.getElementById('phInfoModal');
    const phoneInput = document.getElementById('ph_phone');
    
    if (phModal && phoneInput) {
        // 1. 실시간 글로벌 번호 포맷팅 및 커서 고정
        phoneInput.addEventListener('input', function(e) {
            let cursorPosition = this.selectionStart;
            let oldLength = this.value.length;

            let cCode = '<?php echo $common_cCode; ?>';
            if (typeof FNB_CONFIG !== 'undefined' && FNB_CONFIG.country) cCode = FNB_CONFIG.country;
            else if (typeof PS24_SHOP_CONFIG !== 'undefined' && PS24_SHOP_CONFIG.country) cCode = PS24_SHOP_CONFIG.country;
            else if (typeof getShopConfig === 'function') {
                const cfg = getShopConfig();
                if (cfg && cfg.country) cCode = cfg.country;
            }

            if (typeof libphonenumber !== 'undefined') {
                // 비숫자 문자 제거 후 순수 번호 추출 (이전에 들어간 하이픈이 파싱 에러를 유발하는 현상 원천 차단)
                let rawValue = this.value.replace(/[^\d+]/g, '');
                
                const formatter = new libphonenumber.AsYouType(cCode);
                let formattedValue = formatter.input(rawValue);
                
                this.value = formattedValue;

                // 하이픈 증감에 따른 커서 위치 보정
                let newLength = this.value.length;
                cursorPosition += (newLength - oldLength);
                this.setSelectionRange(cursorPosition, cursorPosition);
            } else {
                this.value = this.value.replace(/[^\d+]/g, '');
            }
        });

        // 2. 모달창 뜰 때 힌트 텍스트(Placeholder) 국가 동기화 및 초기 포맷팅 트리거
        phModal.addEventListener('show.bs.modal', function () {
            if (!phoneInput.value.trim()) {
                const localPhone = localStorage.getItem('ps24_guest_phone') || localStorage.getItem('srv_last_search_phone') || localStorage.getItem('realty_last_search_phone');
                if (localPhone) phoneInput.value = localPhone;
            }
            
            let cCode = '<?php echo $common_cCode; ?>';
            if (typeof FNB_CONFIG !== 'undefined' && FNB_CONFIG.country) cCode = FNB_CONFIG.country;
            else if (typeof PS24_SHOP_CONFIG !== 'undefined' && PS24_SHOP_CONFIG.country) cCode = PS24_SHOP_CONFIG.country;
            else if (typeof getShopConfig === 'function') {
                const cfg = getShopConfig();
                if (cfg && cfg.country) cCode = cfg.country;
            }

            // [개선] 설정 파일에서 로드한 글로벌 전화번호 포맷 객체(JSON) 활용
            const formatData = countryPhoneFormats[cCode] || countryPhoneFormats['PH'];
            phoneInput.placeholder = formatData.placeholder;
            let hintText = formatData.hint;

            const phCountryHintEl = document.getElementById('phCountryHint');
            if (phCountryHintEl) phCountryHintEl.innerText = hintText;

            // 값이 있으면 하이픈 자동 포맷팅 1회 실행
            if (phoneInput.value) {
                phoneInput.dispatchEvent(new Event('input'));
            }
        });
    }
});

async function validateAndSavePhone(e) {
    // 기본 제출 방지 및 이벤트 중복 실행(전파) 중단
    e.preventDefault();
    e.stopImmediatePropagation();

    const phoneInput = document.getElementById('ph_phone');
    if (!phoneInput || phoneInput.value.trim() === '') return;
    
    const phoneStr = phoneInput.value.trim();
    const addressStr = document.getElementById('ph_address') ? document.getElementById('ph_address').value : '';
    const landmarkStr = document.getElementById('ph_landmark') ? document.getElementById('ph_landmark').value : '';

    const btn = e.target.querySelector('button[type="submit"]');
    const originalBtnHtml = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>저장 중...';
    
    try {
        let shopId = 0;
        if (typeof PS24_SHOP_CONFIG !== 'undefined') shopId = PS24_SHOP_CONFIG.shopId;
        else if (typeof FNB_CONFIG !== 'undefined') shopId = FNB_CONFIG.shopId;
        else if (typeof getShopConfig === 'function') {
            const cfg = getShopConfig();
            if (cfg) shopId = cfg.shopId;
        }
        
        const fd = new FormData();
        fd.append('shop_id', shopId);
        fd.append('phone', phoneStr);
        
        // 1. 중복 검사
        const res = await fetch('/shops/check_customer_phone.php', { method: 'POST', body: fd });
        const result = await res.json();
        
        if (result.status === 'duplicate') {
            if (typeof showToast === 'function') showToast(result.message || '해당 전화번호는 다른 고객이 이미 사용중 입니다.', 'danger');
            else alert(result.message || '해당 전화번호는 다른 고객이 이미 사용중 입니다.');
            phoneInput.focus();
            return; // 에러 시 저장하지 않고 중단
        }
        
        // 2. DB 업데이트 (회원의 경우 세션 및 DB 내 전화번호 동기화)
        const updateFd = new FormData();
        updateFd.append('phone', phoneStr);
        updateFd.append('address', addressStr);
        updateFd.append('landmark', landmarkStr);
        await fetch('/shops/update_customer_info.php', { method: 'POST', body: updateFd });

        // 3. 검사 통과 시 브라우저 로컬 스토리지에 새 번호 덮어쓰기(저장)
        localStorage.setItem('ps24_guest_phone', phoneStr);
        localStorage.setItem('srv_last_search_phone', phoneStr);
        localStorage.setItem('realty_last_search_phone', phoneStr);
        
        // JS 메모리 상의 전역 환경 변수도 즉시 업데이트하여 새로고침 없이도 새 번호가 반영되게 함
        if (typeof FNB_CONFIG !== 'undefined') FNB_CONFIG.customerPhone = phoneStr;
        if (typeof PS24_SHOP_CONFIG !== 'undefined') PS24_SHOP_CONFIG.customerPhone = phoneStr;
        if (typeof getShopConfig === 'function') {
            const cfg = getShopConfig();
            if (cfg) cfg.customerPhone = phoneStr;
        }

        // 4. 모달 닫기 및 후속 콜백 연동
        const modalEl = document.getElementById('phInfoModal');
        if (modalEl && typeof bootstrap !== 'undefined') bootstrap.Modal.getInstance(modalEl)?.hide();
        
        if (window.pendingPhInfoAction === 'srv_history' && typeof window.fetchServiceInquiryHistory === 'function') {
            setTimeout(() => { window.fetchServiceInquiryHistory(phoneStr); window.pendingPhInfoAction = null; }, 300);
        } else if (typeof showToast === 'function') {
            showToast('연락처가 성공적으로 저장/변경되었습니다.', 'success');
        }
    } catch (err) {
        if (typeof showToast === 'function') showToast('통신 오류가 발생했습니다.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
    }
}
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

// "로그인 없이 계속하기" 버튼 클릭 시 실행
function continueWithoutLogin() {
    // 1. 로그인 선택 모달 닫기
    const loginModalEl = document.getElementById('loginChoiceModal');
    let delay = 0;
    if (loginModalEl) {
        const bsModal = bootstrap.Modal.getInstance(loginModalEl);
        if (bsModal) {
            bsModal.hide();
            delay = 300; // 모달 닫힘 애니메이션 시간 대기
        }
    }

    // 2. 모달이 부드럽게 닫힌 후 후속 작업 실행 (Bootstrap 애니메이션 충돌 방지)
    setTimeout(() => {
        if (typeof window.pendingActionWithoutLogin === 'function') {
            window.pendingActionWithoutLogin();
            window.pendingActionWithoutLogin = null; // 1회 실행 후 초기화
        } else {
            // [수정] 특정 액션 없이 로그인 창이 불렸을 때의 기본 폴백(Fallback) 처리
            // 브라우저에 저장된 전화번호가 있고, SRV 카테고리처럼 내역 조회를 지원한다면 연락처 모달을 건너뛰고 내역 띄우기
            const savedPhone = localStorage.getItem('ps24_guest_phone') || localStorage.getItem('srv_last_search_phone') || localStorage.getItem('realty_last_search_phone');
            if (savedPhone && typeof window.fetchServiceInquiryHistory === 'function') {
                window.fetchServiceInquiryHistory(savedPhone);
            } else {
                const phModalEl = document.getElementById('phInfoModal');
                if (phModalEl) {
                    const bsPhModal = bootstrap.Modal.getOrCreateInstance(phModalEl);
                    bsPhModal.show();
                }
            }
        }
    }, delay);
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