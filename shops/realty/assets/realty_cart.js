/**
 * [REALTY 카테고리 전용 자바스크립트 엔진]
 * 위치: /shops/realty/assets/realty_cart.js
 * 설명: 부동산 상점의 프론트엔드 비즈니스 로직(관심매물, 모달, 문의내역 등)을 단일 파일로 캡슐화
 */

// [UX 개선] 모바일 환경에서 페이지 로딩 후 이전 스크롤 위치나 해시로 인해 화면이 중간으로 강제 이동(점프)하는 현상 원천 차단
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
// common_footer.php 에 내장된 자동 스크롤/해시 복원 기능 원천 무력화
sessionStorage.removeItem('pageScrollPos');
if (window.location.hash) {
    history.replaceState(null, null, window.location.pathname + window.location.search);
}
window.scrollTo(0, 0); // 무조건 최상단 노출 고정

let wishlist = [];
if (typeof REALTY_CONFIG !== 'undefined') {
    wishlist = JSON.parse(localStorage.getItem('wishlist_' + REALTY_CONFIG.shopId)) || [];
}

let inquiryToDelete = null;
let inquiryToCancel = null;
let pendingRealtyAction = null;
window.hasConfirmedLoginChoice = false;

// 매물 상세 모달 띄우기 및 데이터 바인딩
window.openMenuDetailModal = function(item) {
    const modalEl = document.getElementById('menuDetailModal');
    if (!modalEl) return;

    document.getElementById('detail-menu-name').innerText = item.item_name || '';
    document.getElementById('detail-menu-info').innerText = item.item_info || '';

    const finalPrice = document.getElementById('detail-final-price');
    const origPrice = document.getElementById('detail-original-price');
    const price = parseInt(item.item_price) || 0;
    const discountRate = parseInt(item.item_discount_rate) || 0;
    const discountPrice = parseInt(item.item_discount_price) || 0;

    if (discountRate > 0) {
        finalPrice.innerText = REALTY_CONFIG.currencySymbol + ' ' + discountPrice.toLocaleString();
        origPrice.innerText = REALTY_CONFIG.currencySymbol + ' ' + price.toLocaleString();
        origPrice.classList.remove('d-none');
    } else {
        finalPrice.innerText = REALTY_CONFIG.currencySymbol + ' ' + price.toLocaleString();
        origPrice.classList.add('d-none');
    }

    const badgesContainer = document.getElementById('detail-badges');
    let badgesHtml = '';
    if (item.trade_type) badgesHtml += `<span class="badge bg-primary me-1">${item.trade_type}</span>`;
    if (discountRate > 0) badgesHtml += `<span class="badge bg-danger me-1">급매</span>`;
    if (item.is_best == 1) badgesHtml += `<span class="badge bg-warning text-dark me-1">추천</span>`;
    if (item.is_new == 1) badgesHtml += `<span class="badge bg-info me-1">신규</span>`;
    badgesContainer.innerHTML = badgesHtml;

    const photoContainer = document.getElementById('menu-detail-photo');
    const photoGuideText = document.getElementById('photo-guide-text');
    const videoContainer = document.getElementById('menu-detail-video');
    const videoGuideText = document.getElementById('video-guide-text');
    const photoTabBtn = document.getElementById('photo-tab');
    const videoTabBtn = document.getElementById('video-tab');
    let photoUrls = [];
    let videoUrls = [];

    if (item.item_img) {
        try {
            let parsedImg = item.item_img;
            if (typeof parsedImg === 'string' && parsedImg.startsWith('[')) parsedImg = JSON.parse(parsedImg);
            if (Array.isArray(parsedImg)) photoUrls = parsedImg.filter(url => url && url.trim() !== '');
            else photoUrls = [parsedImg];
        } catch (e) {
            photoUrls = [item.item_img];
        }
    }

    if (item.item_youtube_url) {
        try {
            let ytUrls = JSON.parse(item.item_youtube_url);
            if (Array.isArray(ytUrls)) {
                ytUrls.forEach(url => { if (url && url.trim() !== '') videoUrls.push(url); });
            } else if (typeof ytUrls === 'string' && ytUrls.trim() !== '') {
                videoUrls.push(ytUrls);
            }
        } catch (e) {
            if (item.item_youtube_url.trim() !== '') videoUrls.push(item.item_youtube_url);
        }
    }

    const photoCarouselId = 'detail-photo-carousel-' + item.id;
    if (photoUrls.length > 0) {
        document.getElementById('photo-tab-item').style.display = 'block';
        if (typeof generateDynamicCarousel === 'function') {
            photoContainer.innerHTML = generateDynamicCarousel(photoCarouselId, photoUrls, { useLightbox: true });
            photoGuideText.classList.toggle('d-none', photoUrls.length <= 1);
        } else {
            photoContainer.innerHTML = `<img src="${photoUrls[0]}" class="w-100" style="aspect-ratio: 4/3; object-fit: cover; background: #000;">`;
            photoGuideText.classList.add('d-none');
        }
    } else {
        document.getElementById('photo-tab-item').style.display = 'none';
        photoContainer.innerHTML = `<div class="d-flex justify-content-center align-items-center w-100 bg-light" style="aspect-ratio: 4/3;"><i class="bi bi-camera text-muted fs-1"></i></div>`;
        photoGuideText.classList.add('d-none');
    }

    const videoCarouselId = 'detail-video-carousel-' + item.id;
    if (videoUrls.length > 0) {
        document.getElementById('video-tab-item').style.display = 'block';
        if (typeof generateDynamicCarousel === 'function') {
            videoContainer.innerHTML = generateDynamicCarousel(videoCarouselId, videoUrls);
            videoGuideText.classList.toggle('d-none', videoUrls.length <= 1);
        } else {
            videoContainer.innerHTML = `<div class="d-flex justify-content-center align-items-center w-100 bg-light" style="aspect-ratio: 4/3;"><i class="bi bi-youtube text-muted fs-1"></i></div>`;
            videoGuideText.classList.add('d-none');
        }
    } else {
        document.getElementById('video-tab-item').style.display = 'none';
        videoContainer.innerHTML = `<div class="d-flex justify-content-center align-items-center w-100 bg-light" style="aspect-ratio: 4/3;"><i class="bi bi-youtube text-muted fs-1"></i></div>`;
        videoGuideText.classList.add('d-none');
    }

    if (photoUrls.length > 0) bootstrap.Tab.getOrCreateInstance(photoTabBtn).show();
    else if (videoUrls.length > 0) bootstrap.Tab.getOrCreateInstance(videoTabBtn).show();

    const onModalShown = function() {
        if (photoUrls.length > 0 && typeof initDynamicCarousel === 'function') initDynamicCarousel(photoCarouselId, { useLightbox: true });
        if (videoUrls.length > 0 && typeof initDynamicCarousel === 'function') initDynamicCarousel(videoCarouselId);
        modalEl.removeEventListener('shown.bs.modal', onModalShown);
    };
    modalEl.addEventListener('shown.bs.modal', onModalShown);

    const singleInquiryInput = document.getElementById('single_inquiry_item_data');
    if (singleInquiryInput) singleInquiryInput.value = JSON.stringify(item);

    const phoneInput = document.getElementById('single_customer_phone');
    if (phoneInput && !phoneInput.value.trim()) {
        const savedPhone = REALTY_CONFIG.customerPhone || localStorage.getItem('realty_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    const btnWishlist = document.getElementById('btn-wishlist');
    if (btnWishlist) {
        const isWishedLocally = wishlist.some(w => parseInt(w.id) === parseInt(item.id));
        if (isWishedLocally && (parseInt(item.wish_count) || 0) === 0) {
            item.wish_count = 1;
        }
        btnWishlist.setAttribute('data-item', JSON.stringify(item));
    }
    window.updateWishlistButton(item.id);

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

window.updateWishlistUI = function() {
    const count = wishlist.length;
    const badge = document.getElementById('cart-count-badge');
    if(badge) badge.innerText = count;

    const btnOrderCol = document.getElementById('btn-order-col');
    const btnHistoryCol = document.getElementById('btn-history-col');
    if(!btnOrderCol || !btnHistoryCol) return;

    if (count > 0) {
        btnOrderCol.style.display = 'block';
        btnHistoryCol.classList.remove('col-12');
        btnHistoryCol.classList.add('col-6');
    } else {
        btnOrderCol.style.display = 'none';
        btnHistoryCol.classList.remove('col-6');
        btnHistoryCol.classList.add('col-12');
    }

    const wishBadges = document.querySelectorAll('.item-wish-badge');
    wishBadges.forEach(el => {
        const itemId = parseInt(el.getAttribute('data-item-id'));
        if (wishlist.some(w => parseInt(w.id) === itemId)) {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    });
}

window.toggleWishlist = function(btnEl) {
    const btn = btnEl || document.getElementById('btn-wishlist');
    if (!btn) return;

    const itemStr = btn.getAttribute('data-item');
    if (!itemStr) return;
    const item = JSON.parse(itemStr);

    const existingIndex = wishlist.findIndex(w => parseInt(w.id) === parseInt(item.id));
    let actionType = '';

    if (existingIndex > -1) {
        wishlist.splice(existingIndex, 1);
        item.wish_count = Math.max(0, (parseInt(item.wish_count) || 0) - 1);
        actionType = 'remove';
        if (typeof showToast === 'function') showToast(REALTY_CONFIG.langWishlistRemoved, 'info');
    } else {
        wishlist.push(item);
        item.wish_count = (parseInt(item.wish_count) || 0) + 1;
        actionType = 'add';
        if (typeof showToast === 'function') showToast(REALTY_CONFIG.langWishlistAdded, 'success');
    }

    btn.setAttribute('data-item', JSON.stringify(item));

    localStorage.setItem('wishlist_' + REALTY_CONFIG.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.updateWishlistButton(item.id);

    try {
        const formData = new FormData();
        formData.append('action', actionType);
        formData.append('shop_id', REALTY_CONFIG.shopId);
        formData.append('item_id', item.id);

        fetch('/shops/realty/shop_realty_wishlist_handler.php', {
            method: 'POST',
            body: formData
        }).catch(e => console.error('Wishlist sync error:', e));
    } catch (e) {
        console.error('Wishlist request error:', e);
    }
}

window.updateWishlistButton = function(itemId) {
    const btn = document.getElementById('btn-wishlist');
    if (!btn) return;

    const itemStr = btn.getAttribute('data-item');
    let currentCount = 0;
    if (itemStr) {
        const item = JSON.parse(itemStr);
        currentCount = parseInt(item.wish_count) || 0;
    }

    const countHtml = currentCount > 0 
        ? `<span id="detail-wish-count" class="fw-bold ms-1" style="font-size: 0.95rem;">${currentCount}</span>` 
        : `<span id="detail-wish-count" class="fw-bold ms-1 d-none" style="font-size: 0.95rem;">0</span>`;

    const isWished = wishlist.some(w => parseInt(w.id) === parseInt(itemId));
    if (isWished) {
        btn.className = 'btn btn-danger rounded-pill px-3 shadow-sm flex-shrink-0 d-inline-flex align-items-center justify-content-center';
        btn.innerHTML = `<i class="bi bi-heart-fill" style="font-size: 1rem;"></i>${countHtml}`;
    } else {
        btn.className = 'btn btn-outline-danger rounded-pill px-3 shadow-sm flex-shrink-0 d-inline-flex align-items-center justify-content-center';
        btn.innerHTML = `<i class="bi bi-heart" style="font-size: 1rem;"></i>${countHtml}`;
    }
    btn.blur();
}

window.scrollToCategory = function(event, targetId) {
    event.preventDefault();
    const targetElement = document.getElementById(targetId);
    if (targetElement) {
        const offset = 124;
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;
        window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
    }
}

window.showCartViewModal = function() {
    const listContainer = document.getElementById('cart-view-items-list');
    if(!listContainer) return;
    let html = '';
    let total = 0;

    if (wishlist.length === 0) {
        const msgEmpty = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langWishlistEmpty) ? REALTY_CONFIG.langWishlistEmpty : '관심목록이 비어있습니다.';
        html = `<div class="text-center text-muted py-5">${msgEmpty}</div>`;
    } else {
        wishlist.forEach((item, index) => {
            const price = parseInt(item.item_discount_rate) > 0 ? parseInt(item.item_discount_price) : parseInt(item.item_price);
            total += price;
            let thumb = '/assets/no-logo.png';
            if (item.item_img) {
                const langDiscount = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langDiscount) ? REALTY_CONFIG.langDiscount : '할인';
                try {
                    let parsed = item.item_img;
                    if (typeof parsed === 'string' && parsed.startsWith('[')) parsed = JSON.parse(parsed);
                    if (Array.isArray(parsed) && parsed.length > 0) thumb = parsed[0];
                    else thumb = parsed;
                } catch (e) {
                    thumb = item.item_img;
                }
            }

            html += `
                <div class="d-flex align-items-center mb-3 p-2 border rounded bg-white shadow-sm">
                    <img src="${thumb}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" class="me-3 border">
                    <div class="flex-grow-1" style="min-width:0;">
                        <h6 class="fw-bold mb-1 text-truncate" style="font-size: 0.95rem;">${item.item_name} ${item.originalPrice ? '<span class="badge bg-danger ms-1" style="font-size: 0.7rem;">' + langDiscount + '</span>' : ''}</h6>
                        <div class="text-primary fw-bold small">${REALTY_CONFIG.currencySymbol} ${price.toLocaleString()}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 ms-2" onclick="window.removeFromWishlist(${index})"><i class="bi bi-x-lg"></i></button>
                </div>
            `;
        });
    }

    listContainer.innerHTML = html;
    const subtotalEl = document.getElementById('cart-view-subtotal');
    if (subtotalEl) subtotalEl.innerText = REALTY_CONFIG.currencySymbol + ' ' + total.toLocaleString();
    const totalPriceEl = document.getElementById('cart-view-total-price');
    if (totalPriceEl) totalPriceEl.innerText = REALTY_CONFIG.currencySymbol + ' ' + total.toLocaleString();

    const phoneInput = document.getElementById('customer_phone');
    if (phoneInput && !phoneInput.value.trim()) {
        const savedPhone = REALTY_CONFIG.customerPhone || localStorage.getItem('realty_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    if(typeof showBsModal === 'function') showBsModal('cartViewModal');
    else {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cartViewModal'));
        modal.show();
    }
}

window.removeFromWishlist = function(index) {
    wishlist.splice(index, 1);
    localStorage.setItem('wishlist_' + REALTY_CONFIG.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.showCartViewModal();

    const itemId = document.getElementById('qna-item-id') ? document.getElementById('qna-item-id').value : null;
    if (itemId) window.updateWishlistButton(itemId);
}

window.confirmDeleteInquiry = function(inquiryId) {
    inquiryToDelete = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('deleteOrderConfirmModal');
    else {
        const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteOrderConfirmModal'));
        confirmModal.show();
    }
}

window.executeDeleteInquiry = async function() {
    if (!inquiryToDelete) return;

    let phone = REALTY_CONFIG.customerPhone || localStorage.getItem('realty_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('shop_id', REALTY_CONFIG.shopId);
    formData.append('inquiry_id', inquiryToDelete);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/realty/shop_realty_inquiry_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('deleteOrderConfirmModal');
            else {
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteOrderConfirmModal'));
                if (confirmModal) confirmModal.hide();
            }

            const msgSuccess = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langDeleteSuccess) ? REALTY_CONFIG.langDeleteSuccess : '문의 내역이 성공적으로 삭제되었습니다.';
            if (typeof showToast === 'function') showToast(msgSuccess, 'success');
            window.fetchRealtyOrderHistory(phone);

            const badge = document.getElementById('order-count-badge');
            if (badge && parseInt(badge.innerText) > 0) badge.innerText = parseInt(badge.innerText) - 1;
            const modalBadge = document.getElementById('modal-inquiry-count-badge');
            if (modalBadge && parseInt(modalBadge.innerText) > 0) modalBadge.innerText = parseInt(modalBadge.innerText) - 1;
        } else {
            alert(result.message || '삭제에 실패했습니다.');
        }
    } catch (e) {
        const msgCommError = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langCommError) ? REALTY_CONFIG.langCommError : '서버 통신 중 오류가 발생했습니다.';
        alert(msgCommError);
    } finally {
        inquiryToDelete = null;
    }
}

window.confirmCancelInquiry = function(inquiryId) {
    inquiryToCancel = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('cancelInquiryConfirmModal');
    else {
        const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelInquiryConfirmModal'));
        confirmModal.show();
    }
}

window.executeCancelInquiry = async function() {
    if (!inquiryToCancel) return;

    let phone = REALTY_CONFIG.customerPhone || localStorage.getItem('realty_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('shop_id', REALTY_CONFIG.shopId);
    formData.append('inquiry_id', inquiryToCancel);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/realty/shop_realty_inquiry_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('cancelInquiryConfirmModal');
            else {
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('cancelInquiryConfirmModal'));
                if (confirmModal) confirmModal.hide();
            }
            const msgSuccess = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langCancelSuccess) ? REALTY_CONFIG.langCancelSuccess : '상담이 성공적으로 취소되었습니다.';
            if (typeof showToast === 'function') showToast(msgSuccess, 'success');
            window.fetchRealtyOrderHistory(phone);
        } else {
            alert(result.message || '상담 취소에 실패했습니다.');
        }
    } catch (e) {
        const msgCommError = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langCommError) ? REALTY_CONFIG.langCommError : '서버 통신 중 오류가 발생했습니다.';
        alert(msgCommError);
    } finally {
        inquiryToCancel = null;
    }
}

window.formatPhoneInput = function(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val.length > 4 && val.length <= 7) {
        val = val.substring(0, 4) + '-' + val.substring(4);
    } else if (val.length > 7) {
        val = val.substring(0, 4) + '-' + val.substring(4, 7) + '-' + val.substring(7, 11);
    }
    input.value = val;
}

window.fetchLastAddress = function() {
    if (REALTY_CONFIG.customerPhone) {
        document.getElementById('customer_phone').value = REALTY_CONFIG.customerPhone;
        if (typeof showToast === 'function') showToast('기존 정보를 불러왔습니다.', 'success');
        else alert('기존 정보를 불러왔습니다.');
    } else {
        alert('저장된 기존 정보가 없거나 로그인이 필요합니다.');
    }
}

window.fetchRealtyOrderHistory = async function(phoneRaw) {
    const phone = phoneRaw.replace(/\D/g, '');
    if (!phone) return;

    if(typeof showBsModal === 'function') showBsModal('realtyOrderHistoryModal');
    else {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('realtyOrderHistoryModal'));
        modal.show();
    }

    const historyForm = document.getElementById('realty-non-member-history-form');
    const infoForm = document.getElementById('realty-member-history-info');
    if (historyForm) historyForm.style.display = 'none';
    if (infoForm) infoForm.style.display = 'block';

    const phoneDisplay = document.getElementById('realty-history-phone-display');
    if (phoneDisplay) phoneDisplay.innerHTML = '<i class="bi bi-telephone text-primary me-2"></i>' + phoneRaw;

    const resultsContainer = document.getElementById('realty-history-results');
    const msgLoading = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langLoading) ? REALTY_CONFIG.langLoading : '내역을 불러오는 중입니다...';
    if (resultsContainer) resultsContainer.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">${msgLoading}</div></div>`;

    try {
        const formData = new FormData();
        formData.append('shop_id', REALTY_CONFIG.shopId);
        formData.append('phone', phone);
        const response = await fetch('/shops/realty/shop_realty_inquiry_history.php', { method: 'POST', body: formData });
        const data = await response.text();
        if (resultsContainer) resultsContainer.innerHTML = data;
    } catch (e) {
        const msgCommError = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langCommError) ? REALTY_CONFIG.langCommError : '통신 오류가 발생했습니다.';
        if (resultsContainer) resultsContainer.innerHTML = `<div class="text-center py-5 text-danger">${msgCommError}</div>`;
    }
}

window.submitRealtyHistorySearch = function() {
    const phoneInput = document.getElementById('realty_history_search_phone');
    if (phoneInput && phoneInput.value.trim() !== '') {
        const phoneRaw = phoneInput.value;
        const phone = phoneRaw.replace(/\D/g, '');
        if (!phone) return;
        localStorage.setItem('realty_last_search_phone', phoneRaw);

        const resultsContainer = document.getElementById('realty-history-results');
        const msgLoading = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langLoading) ? REALTY_CONFIG.langLoading : '내역을 불러오는 중입니다...';
        if (resultsContainer) resultsContainer.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">${msgLoading}</div></div>`;

        const formData = new FormData();
        formData.append('shop_id', REALTY_CONFIG.shopId);
        formData.append('phone', phone);

        fetch('/shops/realty/shop_realty_inquiry_history.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => { if (resultsContainer) resultsContainer.innerHTML = data; })
            .catch(e => { 
                const msgCommError = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langCommError) ? REALTY_CONFIG.langCommError : '통신 오류가 발생했습니다.';
                if (resultsContainer) resultsContainer.innerHTML = `<div class="text-center py-5 text-danger">${msgCommError}</div>`; 
            });
    }
};

window.continueWithoutLogin = function() {
    window.hasConfirmedLoginChoice = true;
    if(typeof hideBsModal === 'function') hideBsModal('loginChoiceModal');
    else {
        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginChoiceModal'));
        if (loginModal) loginModal.hide();
    }

    if (pendingRealtyAction === 'submitOrder') {
        setTimeout(() => { window.submitOrder(); }, 300);
    } else if (pendingRealtyAction === 'submitSingleInquiry') {
        setTimeout(() => { window.submitSingleInquiry(); }, 300);
    } else if (pendingRealtyAction === 'viewHistory') {
        const historyForm = document.getElementById('realty-non-member-history-form');
        const infoForm = document.getElementById('realty-member-history-info');
        if (historyForm) historyForm.style.display = 'block';
        if (infoForm) infoForm.style.display = 'none';

        const phoneInput = document.getElementById('realty_history_search_phone');
        if (phoneInput) {
            const savedPhone = localStorage.getItem('realty_last_search_phone');
            phoneInput.value = savedPhone ? savedPhone : '';
        }

        const resultsContainer = document.getElementById('realty-history-results');
        const msgEnterPhone = (typeof REALTY_CONFIG !== 'undefined' && REALTY_CONFIG.langEnterPhoneToSearch) ? REALTY_CONFIG.langEnterPhoneToSearch : '전화번호를 입력하고 조회 버튼을 눌러주세요.';
        if (resultsContainer) resultsContainer.innerHTML = `<div class="text-center py-5 text-muted">${msgEnterPhone}</div>`;

        if(typeof showBsModal === 'function') showBsModal('realtyOrderHistoryModal');
        else {
            const historyModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('realtyOrderHistoryModal'));
            historyModal.show();
        }
        pendingRealtyAction = null;
    }
};

window.openRealtyOrderHistoryModal = function() {
    if (!REALTY_CONFIG.isCustomerLoggedIn) {
        pendingRealtyAction = 'viewHistory';
        sessionStorage.setItem('postLoginAction', 'realty_history');
        if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
        else {
            const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginChoiceModal'));
            loginModal.show();
        }
    } else {
        if (!REALTY_CONFIG.customerPhone) {
            window.loginChoiceContext = 'realty_history';
            if(typeof showMyInfoModal === 'function') window.showMyInfoModal();
        } else {
            window.fetchRealtyOrderHistory(REALTY_CONFIG.customerPhone);
        }
    }
};

window.autoSubmitRealtyCartInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_realty_phone');
    const savedInquiry = sessionStorage.getItem('temp_realty_inquiry');
    if (savedPhone !== null) {
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) phoneInput.value = savedPhone;
    }
    if (savedInquiry !== null) {
        const inquiryInput = document.getElementById('customer_inquiry');
        if (inquiryInput) inquiryInput.value = savedInquiry;
    }
    sessionStorage.removeItem('temp_realty_phone');
    sessionStorage.removeItem('temp_realty_inquiry');
    window.hasConfirmedLoginChoice = true;
    window.submitOrder();
};

window.autoSubmitRealtySingleInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_realty_single_phone');
    const savedInquiry = sessionStorage.getItem('temp_realty_single_inquiry');
    const savedItem = sessionStorage.getItem('temp_realty_single_item');
    if (savedPhone !== null) {
        const phoneInput = document.getElementById('single_customer_phone');
        if (phoneInput) phoneInput.value = savedPhone;
    }
    if (savedInquiry !== null) {
        const inquiryInput = document.getElementById('single_customer_inquiry');
        if (inquiryInput) inquiryInput.value = savedInquiry;
    }
    if (savedItem !== null) {
        const itemInput = document.getElementById('single_inquiry_item_data');
        if (itemInput) itemInput.value = savedItem;
    }
    sessionStorage.removeItem('temp_realty_single_phone');
    sessionStorage.removeItem('temp_realty_single_inquiry');
    sessionStorage.removeItem('temp_realty_single_item');
    window.hasConfirmedLoginChoice = true;
    window.submitSingleInquiry();
};

window.submitOrder = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('orderForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const phoneInput = document.getElementById('customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, ''); 
    const inquiryInput = document.getElementById('customer_inquiry');
    if (wishlist.length === 0) return alert('선택된 매물이 없습니다.');

    if (!REALTY_CONFIG.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingRealtyAction = 'submitOrder';
        sessionStorage.setItem('postLoginAction', 'realty_cart_inquiry_auto_submit'); 
        sessionStorage.setItem('temp_realty_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_realty_inquiry', inquiryInput ? inquiryInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');
        else {
            const currentModal = bootstrap.Modal.getInstance(document.getElementById('cartViewModal'));
            if (currentModal) currentModal.hide();
        }
        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
            else {
                const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginChoiceModal'));
                loginModal.show();
            }
        }, 150);
        return;
    }

    const cartData = wishlist.map(item => ({
        id: item.id,
        name: item.item_name,
        price: parseInt(item.item_discount_rate) > 0 ? parseInt(item.item_discount_price) : parseInt(item.item_price),
        originalPrice: null,
        quantity: 1
    }));

    const formData = new FormData();
    formData.append('shop_id', REALTY_CONFIG.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', inquiryInput ? inquiryInput.value.trim() : '');
    formData.append('inquiry_data', JSON.stringify(cartData)); 

    const submitBtn = document.querySelector('#cartViewModal .btn-primary');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/realty/shop_realty_inquiry_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (phoneInput) localStorage.setItem('realty_last_search_phone', phoneInput.value);
            wishlist = [];
            localStorage.removeItem('wishlist_' + REALTY_CONFIG.shopId);
            window.updateWishlistUI();
            if (inquiryInput) inquiryInput.value = '';

            if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');
            else {
                const cartModalEl = document.getElementById('cartViewModal');
                if (cartModalEl && cartModalEl.classList.contains('show')) {
                    const modal = bootstrap.Modal.getInstance(cartModalEl);
                    if (modal) modal.hide();
                }
            }

            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

            const badge = document.getElementById('order-count-badge');
            if (badge) badge.innerText = parseInt(badge.innerText || 0) + 1;
            const modalBadge = document.getElementById('modal-inquiry-count-badge');
            if (modalBadge) modalBadge.innerText = parseInt(modalBadge.innerText || 0) + 1;

            setTimeout(() => {
                const phoneDisplay = document.getElementById('success_registered_phone');
                if (phoneDisplay) phoneDisplay.innerText = phoneInput ? phoneInput.value : '';
                
                if(typeof showBsModal === 'function') showBsModal('inquirySuccessModal');
                else {
                    const successModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('inquirySuccessModal'));
                    successModal.show();
                }
            }, 150);
        } else {
            alert('접수 실패: ' + result.message);
        }
    } catch (err) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = originalText;
    }
}

window.submitSingleInquiry = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('singleInquiryForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const phoneInput = document.getElementById('single_customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, '');
    const inquiryInput = document.getElementById('single_customer_inquiry');
    const itemDataInput = document.getElementById('single_inquiry_item_data');
    if (!itemDataInput || !itemDataInput.value) return;
    const item = JSON.parse(itemDataInput.value);

    if (!REALTY_CONFIG.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingRealtyAction = 'submitSingleInquiry';
        sessionStorage.setItem('postLoginAction', 'realty_single_inquiry_auto_submit'); 
        sessionStorage.setItem('temp_realty_single_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_realty_single_inquiry', inquiryInput ? inquiryInput.value : '');
        sessionStorage.setItem('temp_realty_single_item', itemDataInput ? itemDataInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');
        else {
            const currentModal = bootstrap.Modal.getInstance(document.getElementById('menuDetailModal'));
            if (currentModal) currentModal.hide();
        }
        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
            else {
                const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginChoiceModal'));
                loginModal.show();
            }
        }, 150);
        return;
    }

    const cartData = [{
        id: item.id,
        name: item.item_name,
        price: parseInt(item.item_discount_rate) > 0 ? parseInt(item.item_discount_price) : parseInt(item.item_price),
        originalPrice: null,
        quantity: 1
    }];

    const formData = new FormData();
    formData.append('shop_id', REALTY_CONFIG.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', inquiryInput ? inquiryInput.value.trim() : '');
    formData.append('inquiry_data', JSON.stringify(cartData));

    const submitBtn = document.querySelector('#singleInquiryForm .btn-dark');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/realty/shop_realty_inquiry_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (phoneInput) localStorage.setItem('realty_last_search_phone', phoneInput.value);
            inquiryInput.value = '';

            if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');
            else {
                const detailModalEl = document.getElementById('menuDetailModal');
                if (detailModalEl && detailModalEl.classList.contains('show')) {
                    const modal = bootstrap.Modal.getInstance(detailModalEl);
                    if (modal) modal.hide();
                }
            }

            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

            const badge = document.getElementById('order-count-badge');
            if (badge) badge.innerText = parseInt(badge.innerText || 0) + 1;
            const modalBadge = document.getElementById('modal-inquiry-count-badge');
            if (modalBadge) modalBadge.innerText = parseInt(modalBadge.innerText || 0) + 1;

            setTimeout(() => {
                const phoneDisplay = document.getElementById('success_registered_phone');
                if (phoneDisplay) phoneDisplay.innerText = phoneInput ? phoneInput.value : '';

                if(typeof showBsModal === 'function') showBsModal('inquirySuccessModal');
                else {
                    const successModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('inquirySuccessModal'));
                    successModal.show();
                }
            }, 150);
        } else {
            alert('접수 실패: ' + result.message);
        }
    } catch (err) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = originalText;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.updateWishlistUI === 'function') window.updateWishlistUI();

    if (typeof enableDragScroll === 'function') {
        enableDragScroll('#categoryNavScroll');
    }

    const catScroll = document.getElementById('categoryNavScroll');
    if (catScroll) {
        const leftInd = catScroll.parentElement.querySelector('.scroll-indicator.left');
        const rightInd = catScroll.parentElement.querySelector('.scroll-indicator.right');
        const updateIndicators = () => {
            if (leftInd) leftInd.classList.toggle('visible', catScroll.scrollLeft > 10);
            if (rightInd) rightInd.classList.toggle('visible', Math.ceil(catScroll.scrollLeft + catScroll.clientWidth) < catScroll.scrollWidth - 10);
        };
        catScroll.addEventListener('scroll', updateIndicators);
        window.addEventListener('resize', updateIndicators);
        setTimeout(updateIndicators, 300);
    }
});