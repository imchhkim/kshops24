/**
 * [공통 컴포넌트] KShops24 공통 자바스크립트 모듈
 * 위치: /common/assets/shop_common.js
 * 역할: 모든 상점(FNB, Realty, SRV)에서 공통으로 사용되는 UI/UX 및 유틸리티 엔진
 */

// ==========================================
// 1. 공통 유틸리티 및 헬퍼 함수
// ==========================================

// 통합 설정 객체 가져오기 (각 카테고리에 맞춰 자동 매핑)
function getShopConfig() {
    if (typeof PS24_SHOP_CONFIG !== 'undefined') return PS24_SHOP_CONFIG;
    if (typeof FNB_CONFIG !== 'undefined') return FNB_CONFIG;
    if (typeof REALTY_CONFIG !== 'undefined') return REALTY_CONFIG;
    if (typeof SRV_CONFIG !== 'undefined') return SRV_CONFIG;
    return null;
}

function showBsModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
        modal.show();
    }
}

function hideBsModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }
}

window.showCustomAlert = function(message, type = 'info', title = '알림', callback = null) {
    const modalEl = document.getElementById('customAlertModal');
    if (!modalEl) {
        alert(message);
        if (callback) callback();
        return;
    }

    let iconHtml = '<i class="bi bi-info-circle-fill text-primary" style="font-size: 3rem;"></i>';
    let btnClass = 'btn-primary';

    if (type === 'success') {
        iconHtml = '<i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>';
        btnClass = 'btn-success';
    } else if (type === 'warning') {
        iconHtml = '<i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>';
        btnClass = 'btn-warning text-dark';
    } else if (type === 'danger' || type === 'error') {
        iconHtml = '<i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>';
        btnClass = 'btn-danger';
    }

    document.getElementById('customAlertIcon').innerHTML = iconHtml;
    document.getElementById('customAlertTitle').innerText = title;
    document.getElementById('customAlertMessage').innerHTML = message.replace(/\n/g, '<br>');

    const btn = document.getElementById('customAlertBtn');
    btn.className = `btn ${btnClass} w-100 fw-bold rounded-pill shadow-sm`;

    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    newBtn.addEventListener('click', function() {
        bsModal.hide();
        if (typeof callback === 'function') setTimeout(callback, 300);
    });
    bsModal.show();
};

// [추가] 헤더 네비게이션용 주문 내역 모달 호출 브릿지
window.showOrderHistoryModal = function() {
    if (typeof showOrderHistory === 'function') {
        showOrderHistory(); // FNB 카테고리
    } else if (typeof openRealtyOrderHistoryModal === 'function') {
        openRealtyOrderHistoryModal(); // 부동산 카테고리
    } else if (typeof openServiceInquiryHistoryModal === 'function') {
        openServiceInquiryHistoryModal(); // 서비스 카테고리
    } else {
        showBsModal('orderHistoryModal'); // 기본 모달 폴백
    }
};

// [추가] 헤더 네비게이션용 연락처 수정 모달 호출 브릿지
window.showMyInfoModal = function() { showBsModal('phInfoModal'); };

// ==========================================
// 2. 카카오 로그인 및 전화번호 헬퍼
// https://developers.kakao.com/console/app/1403294/product/login/advanced 접속해서
// Redirect URI에 https://kshops24.com/shops/customer_kakao_callback.php 등록 필요
// ==========================================
try {
    if (typeof Kakao !== 'undefined' && !Kakao.isInitialized()) {
        Kakao.init('bad682065d7a997ad74c2bd0c5f7121c');
    }
} catch (e) {
    console.error('Kakao Init Error:', e);
}

function loginWithKakao(keepAction = false, btnElem = null) {
    if (btnElem) {
        btnElem.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ...';
        btnElem.classList.add('disabled');
    } else {
        const loaderHtml = `<div id="fullPageLoader" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:9999; display:flex; justify-content:center; align-items:center;"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div></div>`;
        document.body.insertAdjacentHTML('beforeend', loaderHtml);
    }

    if (!keepAction) sessionStorage.removeItem('postLoginAction');
    
    const cfg = getShopConfig();
    const subdomain = (cfg && cfg.subdomain) ? cfg.subdomain : ''; 
    location.href = 'https://kauth.kakao.com/oauth/authorize?client_id=461d2ab817f7b7832592405576a4068d&redirect_uri=https%3A%2F%2Fkshops24.com%2Fshops%2Fcustomer_kakao_callback.php&response_type=code&state=' + encodeURIComponent(subdomain);
}

// ==========================================
// 4. 리뷰 모듈 엔진
// ==========================================
function initReviewModule() {
    document.querySelectorAll('.rating-stars i').forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            const ratingInput = document.getElementById('review_rating');
            if(ratingInput) ratingInput.value = rating;

            const modal = this.closest('.modal-content');
            if(modal) {
                modal.querySelectorAll('.rating-stars i').forEach(s => {
                    s.className = parseInt(s.getAttribute('data-rating')) <= rating ? 'bi bi-star-fill' : 'bi bi-star';
                });
            }
        });
    });
}

async function submitReview() {
    const cfg = getShopConfig();
    if(!cfg) return;

    const action = document.getElementById('review_action').value;
    const rating = document.getElementById('review_rating').value;
    const content = document.getElementById('review_content').value.trim();
    const reviewId = document.getElementById('edit_review_id').value;
    const btn = document.querySelector('#reviewWriteModal .btn-primary');

    if (!content) {
        showCustomAlert("리뷰 내용을 입력해주세요.", 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>처리 중...';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('shop_id', cfg.shopId);
    formData.append('rating', rating);
    formData.append('content', content);
    if (action === 'update') formData.append('review_id', reviewId);

    try {
        const res = await fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData });
        const result = await res.json();

        if (result.status === 'success') {
            hideBsModal('reviewWriteModal');
            if(typeof refreshRecentReviews === 'function') refreshRecentReviews();
        } else {
            showCustomAlert(result.message || '오류가 발생했습니다.', 'danger');
        }
    } catch (err) {
        showCustomAlert('서버 통신 중 오류가 발생했습니다.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '리뷰 등록하기 <i class="bi bi-send-fill ms-1"></i>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initReviewModule();
});

// ==========================================
// 5. [공통 모바일 UX] 드래그 스크롤 및 카테고리 스파이
// ==========================================
window.scrollToCategory = function(event, targetId) {
    if(event) event.preventDefault();
    let targetElement = typeof targetId === 'string' ? document.getElementById(targetId) : document.querySelector(targetId);
    if (targetElement) {
        const offset = 124;
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;
        window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // 5-1. 가로 스크롤 영역 마우스/터치 드래그 지원
    document.querySelectorAll('.menu-scroll-container, .category-nav-scroll').forEach(slider => {
        let isDown = false, isDragging = false, startX, scrollLeft;
        slider.addEventListener('mousedown', (e) => { isDown = true; isDragging = false; slider.style.cursor = 'grabbing'; startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft; });
        ['mouseleave', 'mouseup'].forEach(evt => slider.addEventListener(evt, () => { isDown = false; slider.style.cursor = 'grab'; }));
        slider.addEventListener('mousemove', (e) => { if (!isDown) return; e.preventDefault(); const x = e.pageX - slider.offsetLeft; const walk = (x - startX) * 1.5; if (Math.abs(walk) > 5) isDragging = true; slider.scrollLeft = scrollLeft - walk; });
        slider.style.cursor = 'grab';
        slider.querySelectorAll('a, img').forEach(el => el.addEventListener('dragstart', (e) => e.preventDefault()));
        slider.addEventListener('click', (e) => { if (isDragging) { e.preventDefault(); e.stopPropagation(); } }, true);
    });

    // 5-2. 카테고리 네비게이션 스크롤 스파이 (자동 하이라이트 및 클릭 이동)
    const headerOffset = 124;
    const navBtns = document.querySelectorAll('.category-nav-btn');
    const anchors = document.querySelectorAll('.category-anchor');
    if(navBtns.length > 0 && anchors.length > 0) {
        navBtns.forEach(btn => {
            if (!btn.hasAttribute('onclick')) btn.addEventListener('click', function(e) { window.scrollToCategory(e, this.getAttribute('href')); });
        });
        function updateActiveButtonOnScroll() {
            let currentSectionId = '';
            anchors.forEach(anchor => { if (anchor.getBoundingClientRect().top <= headerOffset + 5) currentSectionId = anchor.id; });
            let activeBtn = null;
            navBtns.forEach(btn => { const isActive = btn.getAttribute('href') === '#' + currentSectionId; btn.classList.toggle('active', isActive); if (isActive) activeBtn = btn; });
            if (activeBtn) { const navContainer = document.querySelector('.category-nav-scroll'); if (navContainer) navContainer.scrollTo({ left: activeBtn.offsetLeft - (navContainer.offsetWidth / 2) + (activeBtn.offsetWidth / 2), behavior: 'smooth' }); }
        }
        let scrollTimeout;
        document.addEventListener('scroll', () => { clearTimeout(scrollTimeout); scrollTimeout = setTimeout(updateActiveButtonOnScroll, 50); });
        updateActiveButtonOnScroll();
    }
});