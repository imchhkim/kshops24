/**
 * [컴포넌트] F&B 전용 카트 및 주문 JS 엔진 (fnb_cart.js)
 * F&B 환경에서의 메뉴 담기, 카트 수정, 결제 모달 관리 및 폴링 시스템을 전담합니다.
 * (사전에 FNB_CONFIG 및 shopMenusData 전역 객체가 정의되어 있어야 합니다.)
 */

let freeDeliveryAmount = 0;
let deliveryFee = 0;
let cart = [];
let currentItem = null;
let currentQty = 1;
let wishlist = [];

// [신규] 나의 주문 상태 플로팅 패널 토글 제어
let isOrderStatusPanelOpen = false;

window.toggleOrderStatusPanel = function() {
    const panel = document.getElementById('floating-order-status-panel');
    const badge = document.getElementById('floating-order-status-badge');
    if (!panel) return;
    
    isOrderStatusPanelOpen = !isOrderStatusPanelOpen;
    if (isOrderStatusPanelOpen) {
        panel.classList.remove('d-none');
        if (badge) badge.style.display = 'none'; // 창이 열리면 새 알림 뱃지 숨김
        if (typeof window.pollOrderStatus === 'function') window.pollOrderStatus();
    } else {
        panel.classList.add('d-none');
    }
}

window.showFloatingOrderStatus = function() {
    const btn = document.getElementById('floating-order-status-btn');
    if (btn) btn.classList.remove('d-none');
    if (!isOrderStatusPanelOpen) window.toggleOrderStatusPanel();
}

function openMenuDetailModalById(id) {
    const cfg = getShopConfig();
    const item = (cfg && cfg.allItemsData) ? cfg.allItemsData[id] : null;
    if (item) openMenuDetailModal(item);
}

/**
 * [공용] 메뉴 ID를 받아 첫 번째 썸네일 이미지 경로를 반환하는 함수
 */
function getThumbImg(itemId) {
    const cfg = getShopConfig();
    const itemData = (cfg && cfg.allItemsData) ? cfg.allItemsData[itemId] : null;
    if (!itemData) return '/assets/no-logo.png';
    let cleanImgData = itemData.item_img || '';
    if (cleanImgData.includes('&quot;')) cleanImgData = cleanImgData.replace(/&quot;/g, '"');
    let imgs = [];
    try {
        let parsed = cleanImgData.startsWith('[') ? JSON.parse(cleanImgData) : (cleanImgData ? [cleanImgData] : []);
        if (typeof parsed === 'string' && parsed.startsWith('[')) parsed = JSON.parse(parsed);
        imgs = Array.isArray(parsed) ? parsed : [cleanImgData];
    } catch (e) {
        const match = cleanImgData.match(/(\/[^"'\s\\]+\.(?:jpg|jpeg|png|gif|webp))/i);
        imgs = match ? [match[1]] : (cleanImgData ? [cleanImgData] : []);
    }
    return imgs.length > 0 ? imgs[0] : '/assets/no-logo.png';
}

/**
 * [최적화] 원본 이미지 경로에서 썸네일 이미지 경로를 유추합니다.
 * 썸네일이 없을 경우를 대비하여 img 태그의 onerror 이벤트와 함께 사용해야 합니다.
 */
function getOptimizedThumbImgPath(originalPath) {
    if (!originalPath || originalPath.indexOf('/uploads/') === -1) return originalPath;
    const parts = originalPath.split('/');
    const filename = parts.pop();
    parts.push('thumb_' + filename);
    return parts.join('/');
}

/**
 * [복구] 카트 담기 애니메이션 효과 (이전 깨진 코드 원상복구)
 */
function flyToCartAnimation(sourceElement) {
    if (!sourceElement) return;
    const targetElement = document.querySelector('#floating-cart-bar #btn-order-col');
    if (!targetElement) return;

    const sourceRect = sourceElement.getBoundingClientRect();
    const targetRect = targetElement.getBoundingClientRect();

    const flyingImg = sourceElement.cloneNode(true);
    flyingImg.className = 'fly-to-cart';
    flyingImg.style.left = `${sourceRect.left}px`;
    flyingImg.style.top = `${sourceRect.top}px`;
    flyingImg.style.width = `${sourceRect.width}px`;
    flyingImg.style.height = `${sourceRect.height}px`;
    document.body.appendChild(flyingImg);

    setTimeout(() => {
        flyingImg.style.left = `${targetRect.left + targetRect.width / 2 - 15}px`;
        flyingImg.style.top = `${targetRect.top + targetRect.height / 2 - 15}px`;
        flyingImg.style.width = '30px';
        flyingImg.style.height = '30px';
        flyingImg.style.opacity = '0';
        flyingImg.style.transform = 'scale(0.1) rotate(360deg)';
    }, 10);

    setTimeout(() => {
        flyingImg.remove();
    }, 800);
}

function updateQtyUI() {
    const qtyDisplayDetail = document.getElementById('detail-current-qty');
    if (qtyDisplayDetail) qtyDisplayDetail.innerText = currentQty;
    const qtyDisplaySimple = document.getElementById('current-qty');
    if (qtyDisplaySimple) qtyDisplaySimple.innerText = currentQty;

    const cfg = getShopConfig();
    if (!currentItem) return;

    const isDiscounted = currentItem.item_discount_rate > 0;
    const originalPrice = parseInt(currentItem.item_price);
    const finalPrice = isDiscounted ? parseInt(currentItem.item_discount_price) : originalPrice;
    const subtotal = finalPrice * currentQty;

    const subtotalDisplayDetail = document.getElementById('detail-subtotal');
    if (subtotalDisplayDetail) {
        subtotalDisplayDetail.innerText = cfg.currencySymbol + ' ' + subtotal.toLocaleString();
    }

    const qtySubtotalContainer = document.getElementById('qty-subtotal-container');
    if (qtySubtotalContainer) {
        const priceHtml = isDiscounted ? `<span class="text-decoration-line-through me-1">${cfg.currencySymbol} ${originalPrice.toLocaleString()}</span> ${cfg.currencySymbol} ${finalPrice.toLocaleString()}` : `${cfg.currencySymbol} ${finalPrice.toLocaleString()}`;
        qtySubtotalContainer.innerHTML = `${priceHtml} × ${currentQty} = <span class="text-primary fw-bold">${cfg.currencySymbol} ${subtotal.toLocaleString()}</span>`;

        const existingCartContainer = document.getElementById('existing-cart-container');
        const existingCartList = document.getElementById('existing-cart-list');
        const grandTotalContainer = document.getElementById('grand-total-container');
        let cartTotal = 0;

        if (cart.length > 0) {
            existingCartContainer.classList.remove('d-none');
            grandTotalContainer.classList.remove('d-none');

            existingCartList.innerHTML = cart.map((item, index) => {
                const itemSubtotal = item.price * item.quantity;
                cartTotal += itemSubtotal;
                const itemPriceHtml = item.originalPrice ? `<span class="text-decoration-line-through me-1">${cfg.currencySymbol} ${item.originalPrice.toLocaleString()}</span> ${cfg.currencySymbol} ${item.price.toLocaleString()}` : `${cfg.currencySymbol} ${item.price.toLocaleString()}`;
                const dividerClass = index < cart.length - 1 ? 'border-bottom pb-2 mb-2' : '';
                const thumbImg = getThumbImg(item.id);
                const optThumb = getOptimizedThumbImgPath(thumbImg);
                return `
                <div class="d-flex justify-content-between align-items-center ${dividerClass}" style="font-size: 0.85rem;">
                    <div class="d-flex align-items-center gap-2">
                        <img src="${optThumb}" class="rounded shadow-sm" style="width: 32px; height: 32px; object-fit: cover;" onerror="if(this.getAttribute('data-fallback') !== 'true'){ this.setAttribute('data-fallback', 'true'); this.src='${thumbImg}'; } else { this.onerror=null; this.src='/assets/no-logo.png'; }">
                        <div>
                            <div class="fw-bold text-dark">${item.name}</div>
                            <div class="text-muted small">${itemPriceHtml} × ${item.quantity}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold text-dark">${cfg.currencySymbol} ${itemSubtotal.toLocaleString()}</span>
                        <button class="btn btn-sm btn-outline-danger border-0 rounded-circle p-1" onclick="removeFromCart(${index})"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>`;
            }).join('');

            const grandTotal = cartTotal + subtotal;
            document.getElementById('grand-total-price').innerText = cfg.currencySymbol + ' ' + grandTotal.toLocaleString();
        } else {
            existingCartContainer.classList.add('d-none');
            grandTotalContainer.classList.add('d-none');
        }
    }
}

function showCartViewModal() {
    renderCartViewContent();
    showBsModal('cartViewModal');
}

function renderCartViewContent() {
    const cfg = getShopConfig();
    const listContainer = document.getElementById('cart-view-items-list');
    const noticeContainer = document.getElementById('free-delivery-notice');
    listContainer.innerHTML = '';
    let subtotal = 0;

    if (cart.length === 0) {
        const msgEmpty = (cfg && cfg.langCartEmpty) ? cfg.langCartEmpty : '카트가 비어 있습니다.';
        listContainer.innerHTML = `<div class="text-center py-5 text-muted">${msgEmpty}</div>`;
        document.getElementById('cart-view-subtotal').innerText = cfg.currencySymbol + ' 0';
        document.getElementById('cart-view-delivery-fee').innerText = cfg.currencySymbol + ' 0';
        document.getElementById('cart-view-total-price').innerText = cfg.currencySymbol + ' 0';
        if (noticeContainer) noticeContainer.classList.add('d-none');
        return;
    }

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        const originalItemTotal = item.originalPrice ? item.originalPrice * item.quantity : null;
        const subtotalHtml = originalItemTotal ?
            `<span class="text-decoration-line-through text-muted small me-2">${cfg.currencySymbol} ${originalItemTotal.toLocaleString()}</span>${cfg.currencySymbol} ${itemTotal.toLocaleString()}` :
            `${cfg.currencySymbol} ${itemTotal.toLocaleString()}`;

        const thumbImg = getThumbImg(item.id);
        const optThumb = getOptimizedThumbImgPath(thumbImg);
        const langDiscount = (cfg && cfg.langDiscount) ? cfg.langDiscount : '할인';
        const nameHtml = item.originalPrice ? `${item.name} <span class="badge bg-danger ms-1" style="font-size: 0.7rem;">${langDiscount}</span>` : item.name;

        listContainer.innerHTML += `
        <div class="card border-0 bg-light rounded-3 p-2 mb-2">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div class="d-flex align-items-center gap-2">
                    <img src="${optThumb}" class="rounded shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="if(this.getAttribute('data-fallback') !== 'true'){ this.setAttribute('data-fallback', 'true'); this.src='${thumbImg}'; } else { this.onerror=null; this.src='/assets/no-logo.png'; }">
                    <div class="fw-bold text-dark fs-6">${nameHtml}</div>
                </div>
                <button class="btn btn-sm text-danger p-0" onclick="removeFromCart(${index})"><i class="bi bi-x-lg fw-bold"></i></button>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-white border shadow-xs rounded-circle p-0" style="width:30px; height:30px;" onclick="updateCartItemQty(${index}, -1)"><i class="bi bi-dash"></i></button>
                    <span class="fw-bold px-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-white border shadow-xs rounded-circle p-0" style="width:30px; height:30px;" onclick="updateCartItemQty(${index}, 1)"><i class="bi bi-plus"></i></button>
                </div>
                <div class="text-primary fw-bold">${subtotalHtml}</div>
            </div>
        </div>`;
    });

    let currentDeliveryFee = 0;
    // [수정] 무료 배달 정책이 없거나(0), 주문 금액이 무료 배달 기준보다 낮을 때 배달비 부과
    if (subtotal > 0 && (freeDeliveryAmount === 0 || subtotal < freeDeliveryAmount)) {
        currentDeliveryFee = deliveryFee;
    }
    const grandTotal = subtotal + currentDeliveryFee;

    document.getElementById('cart-view-subtotal').innerText = cfg.currencySymbol + ' ' + subtotal.toLocaleString();
    document.getElementById('cart-view-delivery-fee').innerText = cfg.currencySymbol + ' ' + currentDeliveryFee.toLocaleString();
    document.getElementById('cart-view-total-price').innerText = cfg.currencySymbol + ' ' + grandTotal.toLocaleString();

    if (noticeContainer) {
        if (freeDeliveryAmount === 0) {
            noticeContainer.classList.add('d-none'); // 무료 배달 정책 없음: 안내창 숨김
        } else if (subtotal > 0 && subtotal < freeDeliveryAmount) {
            const remainingAmount = freeDeliveryAmount - subtotal;
            noticeContainer.innerHTML = `<i class="bi bi-truck me-1"></i> <strong>${cfg.currencySymbol} ${remainingAmount.toLocaleString()}</strong> ${cfg.langFreeDeliveryMore || '만 더 담으면 배달료가 무료!'}`;
            noticeContainer.classList.remove('d-none', 'alert-success');
            noticeContainer.classList.add('alert-info');
        } else if (subtotal >= freeDeliveryAmount) {
            noticeContainer.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${cfg.langFreeDeliverySuccess || '축하합니다! <strong>무료 배달</strong>이 적용됩니다.'}`;
            noticeContainer.classList.remove('d-none', 'alert-info');
            noticeContainer.classList.add('alert-success');
        } else {
            noticeContainer.classList.add('d-none');
        }
    }
}

function updateCartItemQty(index, delta) {
    if (cart[index]) {
        cart[index].quantity += delta;
        if (cart[index].quantity < 1) {
            removeFromCart(index);
        } else {
            saveCart();
            updateCartUI();
            renderCartViewContent();
        }
    }
}

async function proceedToOrderFromCart() {
    const cfg = getShopConfig();
    // [추가] 주문서 작성 창으로 넘어가기 전, 배달 가능한 시간인지 서버에 사전 검증 요청
    const formData = new FormData();
    formData.append('action', 'check_delivery_time');
    formData.append('shop_id', cfg.shopId);

    try {
        const response = await fetch('/shops/fnb/shop_fnb_order_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.status === 'error') {
            alert(result.message);
            return; // 배달 시간이 아니면 여기서 중단 (다음 모달로 전환 차단)
        }
    } catch (err) {
        console.error('Delivery time check error:', err);
    }

    hideBsModal('cartViewModal');
    setTimeout(() => {
        window.hasConfirmedLoginChoice = false; // 주문하기 클릭 시 카카오 로그인 다시 묻기 강제 적용
        showCartModal();
    }, 300);
}

function openQtyModal(item) {
    const cfg = getShopConfig();
    currentItem = item;
    currentQty = 1;
    const isDiscounted = item.item_discount_rate > 0;
    const originalPrice = parseInt(item.item_price);
    const finalPrice = isDiscounted ? parseInt(item.item_discount_price) : originalPrice;

    // [추가] 썸네일 이미지 가져오기 및 모달에 동적 추가
    const thumbImg = getThumbImg(item.id);
    const optThumb = getOptimizedThumbImgPath(thumbImg);
    let imgEl = document.getElementById('qty-menu-img');
    if (!imgEl) {
        const nameEl = document.getElementById('qty-menu-name');
        if (nameEl) {
            imgEl = document.createElement('img');
            imgEl.id = 'qty-menu-img';
            imgEl.className = 'rounded shadow-sm mb-3 d-block mx-auto';
            imgEl.style.width = '160px';
            imgEl.style.height = '120px'; // 4:3 비율로 변경
            imgEl.style.objectFit = 'cover';
            nameEl.parentNode.insertBefore(imgEl, nameEl);
        }
    }
    if (imgEl) {
        imgEl.src = optThumb;
        imgEl.onerror = function() { if(this.getAttribute('data-fallback') !== 'true') { this.setAttribute('data-fallback', 'true'); this.src = thumbImg; } else { this.onerror=null; this.src = '/assets/no-logo.png'; } };
        imgEl.removeAttribute('data-fallback'); // 열 때마다 초기화
    }

    // [수정] 메뉴 이름에 할인 배지 동적 추가
    let nameHtml = item.item_name;
    if (isDiscounted) {
        nameHtml += ' <span class="badge bg-danger ms-1 align-text-bottom" style="font-size: 0.7rem;">할인</span>';
    }
    document.getElementById('qty-menu-name').innerHTML = nameHtml;

    if (isDiscounted) {
        document.getElementById('qty-menu-price').innerHTML =
            `<span class="text-muted text-decoration-line-through small me-2">${cfg.currencySymbol} ${originalPrice.toLocaleString()}</span>` +
            `<span class="text-primary">${cfg.currencySymbol} ${finalPrice.toLocaleString()}</span>`;
    } else {
        document.getElementById('qty-menu-price').innerText = cfg.currencySymbol + ' ' + finalPrice.toLocaleString();
    }
    updateQtyUI();
    showBsModal('qtyModal');
}

function openMenuDetailModal(item) {
    const cfg = getShopConfig();
    currentItem = item;
    currentQty = 1;

    const mediaContainer = document.getElementById('menu-detail-media');
    const nameEl = document.getElementById('detail-menu-name');
    const infoEl = document.getElementById('detail-menu-info');
    const finalPriceEl = document.getElementById('detail-final-price');
    const originalPriceEl = document.getElementById('detail-original-price');
    const slideGuide = document.getElementById('slide-guide-text');

    nameEl.innerText = item.item_name;
    infoEl.innerHTML = item.item_info ? item.item_info.replace(/\n/g, '<br>') : '상세 설명이 없습니다.';

    const isDiscounted = parseInt(item.item_discount_rate) > 0;
    const fPrice = isDiscounted ? parseInt(item.item_discount_price) : parseInt(item.item_price);
    finalPriceEl.innerText = cfg.currencySymbol + ' ' + fPrice.toLocaleString();

    if (isDiscounted) {
        originalPriceEl.innerText = cfg.currencySymbol + ' ' + parseInt(item.item_price).toLocaleString();
        originalPriceEl.classList.remove('d-none');
    } else {
        originalPriceEl.classList.add('d-none');
    }

    let imgs = [];
    try {
        let cleanImgData = item.item_img || '';
        if (cleanImgData.includes('&quot;')) cleanImgData = cleanImgData.replace(/&quot;/g, '"');
        let parsed = cleanImgData.startsWith('[') ? JSON.parse(cleanImgData) : (cleanImgData ? [cleanImgData] : []);
        if (typeof parsed === 'string' && parsed.startsWith('[')) parsed = JSON.parse(parsed);
        imgs = Array.isArray(parsed) ? parsed : [cleanImgData];
    } catch (e) {
        const match = (item.item_img || '').match(/(\/[^"'\s\\]+\.(?:jpg|jpeg|png|gif|webp))/i);
        imgs = match ? [match[1]] : (item.item_img ? [item.item_img] : []);
    }
    if (imgs.length === 0 && !item.item_youtube_url) imgs = ['/assets/no-logo.png'];

    let mediaList = [...imgs];
    if (item.item_youtube_url) mediaList.push(item.item_youtube_url);

    if (mediaList.length > 1) slideGuide.classList.remove('d-none');
    else slideGuide.classList.add('d-none');

    if (typeof generateDynamicCarousel === 'function') {
        mediaContainer.innerHTML = generateDynamicCarousel('menuCarousel', mediaList, { objectFit: 'cover', transition: 'smooth' });
        initDynamicCarousel('menuCarousel');
    } else {
        mediaContainer.innerHTML = `<img src="${mediaList[0]}" class="w-100 h-100" style="object-fit: cover;">`;
    }

    if (cfg.isDeliveryAvailable) {
        updateQtyUI();
    } else {
        const wishBtn = document.querySelector('.detail-wishlist-btn');
        if (wishBtn) {
            const isWished = wishlist.some(i => i.id === item.id);
            if (isWished) {
                wishBtn.classList.replace('btn-outline-danger', 'btn-danger');
                wishBtn.querySelector('.detail-wishlist-icon').classList.replace('bi-heart', 'bi-heart-fill');
                wishBtn.querySelector('.detail-wishlist-text').innerText = (cfg && cfg.langRemoveWishlist) ? cfg.langRemoveWishlist : '찜취소';
            } else {
                wishBtn.classList.replace('btn-danger', 'btn-outline-danger');
                wishBtn.querySelector('.detail-wishlist-icon').classList.replace('bi-heart-fill', 'bi-heart');
                wishBtn.querySelector('.detail-wishlist-text').innerText = (cfg && cfg.langAddWishlist) ? cfg.langAddWishlist : '찜하기';
            }
        }
    }
    showBsModal('menuDetailModal');

    document.getElementById('menuDetailModal').addEventListener('hidden.bs.modal', function() {
        mediaContainer.innerHTML = '';
    }, { once: true });
}

function changeQty(delta) {
    currentQty = Math.max(1, currentQty + delta);
    updateQtyUI();
}

function addToCart() {
    let sourceImgElement = document.querySelector('#menuDetailModal.show .carousel-item.active img');
    if (!sourceImgElement && currentItem) sourceImgElement = document.getElementById(`menu-img-${currentItem.id}`);
    if (sourceImgElement) flyToCartAnimation(sourceImgElement);

    const existingIndex = cart.findIndex(i => i.id === currentItem.id);
    const isDiscounted = currentItem.item_discount_rate > 0;
    const originalPrice = parseInt(currentItem.item_price);
    const finalPrice = isDiscounted ? parseInt(currentItem.item_discount_price) : originalPrice;

    if (existingIndex > -1) {
        cart[existingIndex].quantity += currentQty;
    } else {
        cart.push({
            id: currentItem.id,
            name: currentItem.item_name,
            price: finalPrice,
            originalPrice: isDiscounted ? originalPrice : null,
            quantity: currentQty
        });
    }
    saveCart();
    hideBsModal('qtyModal');
    hideBsModal('menuDetailModal');
    updateCartUI();
}

function saveCart() {
    const cfg = getShopConfig();
    if(cfg) localStorage.setItem('fnb_cart_' + cfg.shopId, JSON.stringify(cart));
}

function updateCartUI() {
    const totalQty = cart.reduce((sum, item) => sum + (item && item.quantity ? parseInt(item.quantity) : 0), 0);
    const bar = document.getElementById('floating-cart-bar');
    const btnOrderCol = document.getElementById('btn-order-col');
    const btnHistoryCol = document.getElementById('btn-history-col');
    if(!bar) return;
    bar.style.display = 'block';

    if (totalQty > 0) {
        btnOrderCol.style.display = 'block';
        btnHistoryCol.className = 'col-6';
        document.getElementById('cart-count-badge').innerText = totalQty;
    } else {
        btnOrderCol.style.display = 'none';
        btnHistoryCol.className = 'col-10 col-md-6';
    }
}

function showCartModal() {
    const cfg = getShopConfig();
    // 비회원이더라도 이미 연락처가 입력되어 있다면 바로 결제 모달 띄우기
    // [수정] 세션 스토리지 기억을 제거하고 현재 진행 중인 단일 프로세스에서만 확인 상태를 유지합니다.
    const hasConfirmed = window.hasConfirmedLoginChoice === true;
    
    if (cfg && !cfg.isCustomerLoggedIn && !cfg.customerPhone && !hasConfirmed) {
        window.loginChoiceContext = 'cart'; // [추가] 모달 호출 목적 기록
        showBsModal('loginChoiceModal');
        return;
    }
    renderCartModalContent();
}

function renderCartModalContent() {
    const cfg = getShopConfig();
    const listContainer = document.getElementById('cart-items-list');
    listContainer.innerHTML = '';
    let subtotal = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        const priceHtml = item.originalPrice ?
            `<span class="text-decoration-line-through text-muted small me-1">${cfg.currencySymbol} ${item.originalPrice.toLocaleString()}</span>${cfg.currencySymbol} ${item.price.toLocaleString()}` :
            `${cfg.currencySymbol} ${item.price.toLocaleString()}`;
            
        const thumbImg = getThumbImg(item.id);
        const optThumb = getOptimizedThumbImgPath(thumbImg);
        const nameHtml = item.originalPrice ? `${item.name} <span class="badge bg-danger ms-1" style="font-size: 0.7rem;">할인</span>` : item.name;

        listContainer.innerHTML += `
        <div class="d-flex justify-content-between align-items-center cart-receipt-item">
            <div class="d-flex align-items-center gap-2">
                <img src="${optThumb}" class="rounded shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="if(this.getAttribute('data-fallback') !== 'true'){ this.setAttribute('data-fallback', 'true'); this.src='${thumbImg}'; } else { this.onerror=null; this.src='/assets/no-logo.png'; }">
                <div>
                    <div class="fw-bold text-dark">${nameHtml}</div>
                    <div class="small text-muted fw-medium">${priceHtml} × ${item.quantity}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3"><span class="fw-bold text-dark">${cfg.currencySymbol} ${itemTotal.toLocaleString()}</span><button class="btn btn-sm btn-outline-danger border-0 rounded-circle p-1" onclick="removeFromCart(${index})"><i class="bi bi-trash3"></i></button></div>
        </div>`;
    });

    let currentDeliveryFee = 0;
    if (subtotal > 0 && (freeDeliveryAmount === 0 || subtotal < freeDeliveryAmount)) currentDeliveryFee = deliveryFee;
    const grandTotal = subtotal + currentDeliveryFee;

    document.getElementById('cart-modal-summary').innerHTML = `
        <div class="w-100">
            <div class="d-flex justify-content-between align-items-center small mb-1 px-2"><span class="text-muted">${cfg.langProductAmount || '상품 금액 (Subtotal)'}</span><span class="fw-medium">${cfg.currencySymbol} ${subtotal.toLocaleString()}</span></div>
            <div class="d-flex justify-content-between align-items-center small mb-2 px-2"><span class="text-muted">${cfg.langDeliveryFee || '배달료 (Delivery Fee)'}</span><span class="fw-medium">${cfg.currencySymbol} ${currentDeliveryFee.toLocaleString()}</span></div>
            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top px-2"><span class="fw-bold text-muted">${cfg.langTotalAmount || '총 결제 예정 금액 </br>(Grand Total)'}</span><span class="fw-bold fs-3 text-primary" id="cart-total-price">${cfg.currencySymbol} ${grandTotal.toLocaleString()}</span></div>
        </div>`;

    const phoneField = document.getElementById('customer_phone');
    const addrField = document.getElementById('customer_address');
    const landmarkField = document.getElementById('customer_landmark');
    const coordField = document.getElementById('customer_coordinates');

    if (!phoneField.value) {
        phoneField.value = (cfg && cfg.customerPhone) ? cfg.customerPhone : localStorage.getItem('ps24_guest_phone') || '';
    }
    if (!addrField.value) {
        addrField.value = (cfg && cfg.customerAddress) ? cfg.customerAddress : localStorage.getItem('ps24_guest_address') || '';
    }
    if (!landmarkField.value) {
        landmarkField.value = (cfg && cfg.customerLandmark) ? cfg.customerLandmark : localStorage.getItem('ps24_guest_landmark') || '';
    }
    if (coordField && !coordField.value) {
        const savedCoord = (cfg && cfg.customerCoordinates) ? cfg.customerCoordinates : localStorage.getItem('ps24_guest_coordinates') || '';
        if (savedCoord) {
            coordField.value = savedCoord;
            coordField.style.display = 'block';
        }
    }

    const inputGroup = document.getElementById('phone-input-group');
    const fetchBtn = document.getElementById('btn-fetch-address');
    if (cfg && cfg.isCustomerLoggedIn) {
        inputGroup.classList.remove('input-group');
        if (fetchBtn) fetchBtn.style.display = 'none';
    }

    showBsModal('cartModal');
}

function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    updateCartUI();
    if (document.getElementById('qtyModal').classList.contains('show')) updateQtyUI();
    if (document.getElementById('cartViewModal').classList.contains('show')) renderCartViewContent();
    if (document.getElementById('cartModal').classList.contains('show')) renderCartModalContent();
    if (cart.length === 0) {
        hideBsModal('cartModal');
        hideBsModal('cartViewModal');
    }
}

// [추가] FNB 비회원 "로그인 없이 계속하기" 브릿지 함수
window.continueWithoutLogin = function() {
    window.hasConfirmedLoginChoice = true;
    if(typeof hideBsModal === 'function') hideBsModal('loginChoiceModal');

    setTimeout(() => {
        if (window.loginChoiceContext === 'history') {
            const historyForm = document.getElementById('non-member-history-form');
            const infoForm = document.getElementById('member-history-info');
            if (historyForm) historyForm.style.display = 'block';
            if (infoForm) infoForm.style.display = 'none';

            const phoneInput = document.getElementById('history_search_phone');
            if (phoneInput) {
                const savedPhone = localStorage.getItem('ps24_guest_phone');
                if (savedPhone) phoneInput.value = savedPhone;
            }

            const results = document.getElementById('history-results');
            const cfg = getShopConfig();
            const msgEnterPhone = (cfg && cfg.langEnterPhoneToSearch) ? cfg.langEnterPhoneToSearch : '전화번호를 입력하고 조회 버튼을 눌러주세요.';
            if (results) results.innerHTML = `<div class="text-center py-5 text-muted">${msgEnterPhone}</div>`;

            if(typeof showBsModal === 'function') showBsModal('orderHistoryModal');
            window.loginChoiceContext = null;
        } else if (window.loginChoiceContext === 'cart') {
            if (typeof renderCartModalContent === 'function') renderCartModalContent();
            window.loginChoiceContext = null;
        } else {
            if (typeof renderCartModalContent === 'function') renderCartModalContent();
        }
    }, 300);
};

async function searchOrderHistory() {
    const cfg = getShopConfig();
    let phone = '';
    const resultsContainer = document.getElementById('history-results');

    // [수정] 로그인 여부에 따라 전화번호를 가져오는 소스를 분기합니다.
    if (cfg && cfg.isCustomerLoggedIn && cfg.customerPhone) {
        phone = cfg.customerPhone.replace(/\D/g, '');
        // 로그인 사용자를 위해 UI를 설정합니다.
        document.getElementById('non-member-history-form').style.display = 'none';
        document.getElementById('member-history-info').style.display = 'block';
        document.getElementById('history-phone-display').innerHTML = `<i class="bi bi-telephone text-primary me-2"></i>${cfg.customerPhone}`;
    } else {
        const phoneInput = document.getElementById('history_search_phone');
        if (!phoneInput) return;
        phone = phoneInput.value.replace(/\D/g, '');
    }

    const msgInvalidPhone = (cfg && cfg.langInvalidPhone) ? cfg.langInvalidPhone : '올바른 필리핀 번호 형식이 아닙니다. (09로 시작하는 11자리)';
    if (!/^09\d{9}$/.test(phone)) {
        resultsContainer.innerHTML = `<div class="alert alert-danger py-2 small text-center">${msgInvalidPhone}</div>`;
        return;
    }

    const msgLoading = (cfg && cfg.langLoading) ? cfg.langLoading : '내역을 불러오는 중입니다...';
    resultsContainer.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 small text-muted">${msgLoading}</div></div>`;

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId);
    formData.append('customer_phone', phone);

    try {
        const response = await fetch('/shops/fnb/shop_fnb_order_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if (result.orders && result.orders.length > 0) {
                resultsContainer.innerHTML = result.orders.map(order => {
                    const items = JSON.parse(order.items);
                    const itemNames = items.map(i => `${i.item_name} x ${i.quantity}`).join(', ');
                    let sInfo = (cfg && cfg.orderStatusMap && cfg.orderStatusMap[order.status]) ? Object.assign({}, cfg.orderStatusMap[order.status]) : { text: order.status || '알수없음', class: 'secondary' };
                    if (order.status === 'delivery' && order.order_type === 'pickup') sInfo.text = '픽업대기';

                    let addressHtml = '';
                    if (order.order_type === 'pickup') {
                        addressHtml = `<p class="x-small text-muted mb-2"><i class="bi bi-shop text-primary me-1"></i>매장픽업: <span class="fw-bold text-danger">${order.pickup_time || '시간 미지정'}</span></p>`;
                    } else {
                        addressHtml = `<p class="x-small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i>${order.customer_address}</p>`;
                        if (order.customer_landmark) {
                            addressHtml += `<p class="x-small text-muted mb-2"><i class="bi bi-flag me-1"></i>${order.customer_landmark}</p>`;
                        }
                    }

                    // [수정] 비회원, 회원 상관없이 '재주문', '취소/삭제' 버튼을 노출합니다.
                    const actionButtons = `
                        <div class="d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                            <div class="flex-grow-1 pe-2">${ (order.status === 'completed' || order.status === 'cancelled') ? `<button class="btn btn-sm btn-dark w-100 rounded-pill fw-bold py-2" onclick='reorder(${JSON.stringify(order)})'><i class="bi bi-arrow-repeat me-1"></i> ${cfg.langReorder || '동일한 주문을 카트에 넣기'}</button>` : '' }</div>
                            <button type="button" class="btn btn-outline-danger rounded-circle flex-shrink-0 shadow-sm bg-white d-flex align-items-center justify-content-center" onclick="confirmDeleteOrder(${order.id}, '${order.status}')" title="내역 삭제" style="width: 36px; height: 36px; padding: 0;"><i class="bi bi-x-lg border-0"></i></button>
                        </div>
                    `;

                    return `
                    <div class="card mb-3 border-0 shadow-sm bg-light rounded-4">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2"><div><span class="badge bg-white text-secondary border me-1">${order.created_at.substring(0,10)}</span><span class="badge bg-${sInfo.class}">${sInfo.text}</span></div><span class="fw-bold text-primary fs-6">${cfg.currencySymbol} ${parseInt(order.total_price).toLocaleString()}</span></div>
                            <p class="small text-dark mb-2 fw-medium">${itemNames}</p>
                            ${addressHtml}
                            ${actionButtons}
                        </div>
                    </div>`;
                }).join('');
            } else {
                const msgNoRecord = (cfg && cfg.langNoOrderRecord) ? cfg.langNoOrderRecord : '해당 번호로는 주문 기록이 없습니다.';
                resultsContainer.innerHTML = `<div class="alert alert-warning py-2 text-center small">${msgNoRecord}</div>`;
            }
        } else {
            resultsContainer.innerHTML = `<div class="alert alert-danger py-2 text-center small">${result.message || '오류가 발생했습니다.'}</div>`;
        }
    } catch (err) {
        const msgCommError = (cfg && cfg.langCommError) ? cfg.langCommError : '서버 통신 중 오류가 발생했습니다.';
        resultsContainer.innerHTML = `<div class="alert alert-danger py-2 text-center small">${msgCommError}</div>`; 
    }
}

let targetOrderIdToDelete = null;
function confirmDeleteOrder(orderId, status) {
    const cfg = getShopConfig();
    const msgCannotCancel = (cfg && cfg.langCannotCancel) ? cfg.langCannotCancel : '요리 중 혹은 배달 중에는 주문 취소가 불가능 합니다.';
    if (status === 'cooking' || status === 'delivery') return alert(msgCannotCancel);
    targetOrderIdToDelete = orderId;
    const titleEl = document.getElementById('deleteOrderConfirmTitle');
    const descEl = document.getElementById('deleteOrderConfirmDesc');
    const btnEl = document.getElementById('deleteOrderConfirmBtn');
    if (status === 'pending') {
        titleEl.innerText = '주문을 취소하시겠습니까?'; descEl.innerText = '상점에도 취소 처리되며 기록이 완전히 삭제됩니다.'; btnEl.innerText = '주문취소';
    } else {
        titleEl.innerText = '정말로 삭제하시겠습니까?'; descEl.innerText = '목록에서 완전히 지워집니다.'; btnEl.innerText = '삭제';
    }
    showBsModal('deleteOrderConfirmModal');
}

async function executeDeleteOrder() {
    const cfg = getShopConfig();
    if (!targetOrderIdToDelete) return;
    const formData = new FormData();
    formData.append('action', 'delete'); formData.append('order_id', targetOrderIdToDelete); formData.append('shop_id', cfg.shopId);
    try {
        const response = await fetch('/shops/fnb/shop_fnb_order_history.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') { hideBsModal('deleteOrderConfirmModal'); searchOrderHistory(); }
        else alert('삭제 중 오류가 발생했습니다: ' + (result.message || ''));
    } catch (err) { alert('서버 통신 중 오류가 발생했습니다.'); }
}

async function fetchLastAddress() {
    const cfg = getShopConfig();
    const phone = document.getElementById('customer_phone').value.trim();
    if (!phone) return alert('전화번호를 먼저 입력해주세요.');
    const formData = new FormData(); formData.append('shop_id', cfg.shopId); formData.append('customer_phone', phone);
    try {
        const response = await fetch('/shops/fnb/shop_fnb_order_history.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success' && result.orders && result.orders.length > 0) {
            document.getElementById('customer_address').value = result.orders[0].customer_address;
            document.getElementById('customer_landmark').value = result.orders[0].customer_landmark || '';
            alert('최근 배달 주소를 성공적으로 불러왔습니다.');
        } else alert('주문 내역이 없습니다.');
    } catch (err) { alert('조회 중 오류가 발생했습니다.'); }
}

function reorder(order) {
    const cfg = getShopConfig();
    const msgReorderConfirm = (cfg && cfg.langReorderConfirm) ? cfg.langReorderConfirm : '이전 주문 항목들을 카트에 담으시겠습니까?';
    if (!confirm(msgReorderConfirm)) return;
    cart = JSON.parse(order.items).map(i => ({ id: i.item_id, name: i.item_name, price: parseInt(i.price), quantity: parseInt(i.quantity) }));
    saveCart();
    const phoneInput = document.getElementById('customer_phone');
    phoneInput.value = order.customer_phone;
    if(typeof formatPhoneInput === 'function') formatPhoneInput(phoneInput);
    document.getElementById('customer_address').value = order.customer_address;
    document.getElementById('customer_landmark').value = order.customer_landmark || '';
    updateCartUI();
    hideBsModal('orderHistoryModal');
    setTimeout(() => { showCartModal(); }, 300);
}

// 중복 클릭 방지용 플래그
let isSubmittingOrder = false;

async function submitOrder() {
    if (isSubmittingOrder) return;

    const phoneInput = document.getElementById('customer_phone');
    const phoneRaw = phoneInput.value;
    const phoneDigits = phoneRaw.replace(/\D/g, '');
    const address = document.getElementById('customer_address').value;
    const landmark = document.getElementById('customer_landmark').value;

    const coordInput = document.getElementById('customer_coordinates');
    const coordinates = coordInput ? coordInput.value : '';

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const paymentDetail = paymentMethod === 'cash' ? document.getElementById('payment_detail_cash').value : document.getElementById('payment_detail_other').value;

    const msgInvalidPhone = (cfg && cfg.langInvalidPhone) ? cfg.langInvalidPhone : '올바른 필리핀 번호 형식이 아닙니다.\n09로 시작하는 11자리 번호를 입력해주세요.';
    if (!/^09\d{9}$/.test(phoneDigits)) { alert(msgInvalidPhone); phoneInput.focus(); return; }
    if (!address) return alert('배달 주소를 입력해 주세요.');

    // [UX 개선] 입력한 배달 정보(전화번호, 주소, 랜드마크)를 로컬 스토리지에 저장하여 다음 주문 시 자동 완성
    localStorage.setItem('ps24_guest_phone', phoneRaw);
    localStorage.setItem('ps24_guest_address', address);
    localStorage.setItem('ps24_guest_landmark', landmark);
    if (coordinates) localStorage.setItem('ps24_guest_coordinates', coordinates);

    const cfg = getShopConfig();
    if (cfg) {
        cfg.customerPhone = phoneRaw;
        cfg.customerAddress = address;
        cfg.customerLandmark = landmark;
        if (coordinates) cfg.customerCoordinates = coordinates;
    }

    isSubmittingOrder = true;
    
    // 버튼 비활성화 및 로딩 UI 표시 (주문하기 버튼 ID가 btn-submit-order라고 가정)
    const submitBtn = document.getElementById('btn-submit-order');
    let originalBtnHtml = '';
    if (submitBtn) {
        originalBtnHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>처리 중...';
        submitBtn.disabled = true;
    }

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId); formData.append('customer_phone', phoneDigits);
    formData.append('customer_address', address); formData.append('customer_landmark', landmark);
    formData.append('payment_method', paymentMethod);
    formData.append('payment_detail', paymentDetail);
    formData.append('cart_data', JSON.stringify(cart));

    try {
        const response = await fetch('/shops/fnb/shop_fnb_order_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            const isDelivery = document.getElementById('order_delivery') ? document.getElementById('order_delivery').checked : true;
            
            let successMsg = `주문이 성공적으로 접수되었습니다!\n\n■ 주문번호: ${result.order_no}\n■ 연락처: ${phoneRaw}`;
            if (isDelivery) {
                successMsg += `\n■ 배달주소: ${address}`;
                if (landmark) successMsg += `\n■ 랜드마크: ${landmark}`;
            } else {
                const pickupTime = document.getElementById('pickup_time') ? document.getElementById('pickup_time').value : '';
                successMsg += `\n■ 수령방식: 매장픽업`;
                if (pickupTime) successMsg += `\n■ 방문예정시간: ${pickupTime}`;
            }
            alert(successMsg);
            cart = []; saveCart(); location.reload();
        } else alert(result.message); // 백엔드에서 보낸 배달 시간 안내 메시지가 깔끔하게 그대로 출력됩니다.
    } catch (err) { 
        alert('서버 통신 오류가 발생했습니다.'); 
    } finally {
        // 응답이 돌아오면 플래그 해제 및 버튼 원상 복구
        isSubmittingOrder = false;
        if (submitBtn) {
            submitBtn.innerHTML = originalBtnHtml;
            submitBtn.disabled = false;
        }
    }
}

/**
 * [신규] 주문 조회 버튼 클릭 시 실행되는 메인 함수
 * 로그인 여부에 따라 분기 처리
 */
function showOrderHistory() {
    const cfg = getShopConfig();
    if (cfg && cfg.isCustomerLoggedIn) {
        // --- 로그인 사용자 ---
        if (cfg.customerPhone) {
            // 1. 전화번호가 있으면 바로 내역 조회
            showBsModal('orderHistoryModal');
            searchOrderHistory();
        } else {
            // 2. 전화번호가 없으면 정보 입력 유도
            // 정보 저장 후 주문내역을 봐야 하므로 컨텍스트 설정
            window.loginChoiceContext = 'history';
            showBsModal('phInfoModal');
        }
    } else {
        // --- 비로그인 사용자 ---
        // 주문 조회의 경우 이전에 '로그인 없이 계속하기'를 선택했더라도 다시 한 번 카카오 로그인을 유도합니다.
        window.hasConfirmedLoginChoice = false; // [추가] 비회원 주문 조회 시 팝업을 무조건 먼저 띄우기 위한 플래그 초기화
        window.loginChoiceContext = 'history'; // 컨텍스트를 'history'로 설정
        sessionStorage.setItem('postLoginAction', 'history'); // 로그인 완료 후 주문 조회 모달로 자동 복귀를 위해 저장
        showBsModal('loginChoiceModal');
    }
}

// 위시리스트 토글 기능
window.toggleWishlist = function(item, btnElement) {
    const cfg = getShopConfig();
    if (!item) return;
    const existingIndex = wishlist.findIndex(i => i.id === item.id);
    
    if (existingIndex > -1) {
        wishlist.splice(existingIndex, 1);
        if (btnElement) {
            btnElement.classList.replace('btn-danger', 'btn-outline-danger');
            const icon = btnElement.querySelector('i');
            if (icon) icon.classList.replace('bi-heart-fill', 'bi-heart');
            const text = btnElement.querySelector('span');
            if (text) text.innerText = (cfg && cfg.langAddWishlist) ? cfg.langAddWishlist : '찜하기';
        }
    } else {
        wishlist.push({
            id: item.id,
            name: item.item_name,
            price: item.item_discount_rate > 0 ? item.item_discount_price : item.item_price,
            originalPrice: item.item_discount_rate > 0 ? item.item_price : null
        });
        if (btnElement) {
            flyToCartAnimationWish(btnElement.closest('.menu-item-card')?.querySelector('img') || document.querySelector('#menuDetailModal.show .carousel-item.active img'));
            btnElement.classList.replace('btn-outline-danger', 'btn-danger');
            const icon = btnElement.querySelector('i');
            if (icon) icon.classList.replace('bi-heart', 'bi-heart-fill');
            const text = btnElement.querySelector('span');
            if (text) text.innerText = (cfg && cfg.langRemoveWishlist) ? cfg.langRemoveWishlist : '찜취소';
        }
    }
    saveWishlist();
    updateWishlistUI();
};

function saveWishlist() {
    const cfg = getShopConfig();
    if(cfg) localStorage.setItem('fnb_wishlist_' + cfg.shopId, JSON.stringify(wishlist));
}

function updateWishlistUI() {
    const totalQty = wishlist.length;
    const bar = document.getElementById('floating-wishlist-bar');
    
    if (bar) {
        if (totalQty > 0) {
            bar.style.display = 'block';
            const badge = document.getElementById('wishlist-count-badge');
            if (badge) badge.innerText = String(totalQty).trim();
        } else {
            bar.style.display = 'none';
        }
    }
    
    const cfg = getShopConfig();
    wishlist.forEach(item => {
        const icons = document.querySelectorAll('.wishlist-icon-' + item.id);
        const texts = document.querySelectorAll('.wishlist-text-' + item.id);
        icons.forEach(i => i.classList.replace('bi-heart', 'bi-heart-fill'));
        texts.forEach(t => t.innerText = (cfg && cfg.langRemoveWishlist) ? cfg.langRemoveWishlist : '찜취소');
        icons.forEach(icon => {
            const btn = icon.closest('button');
            if(btn) btn.classList.replace('btn-outline-danger', 'btn-danger');
        });
    });

    const detailBtn = document.querySelector('.detail-wishlist-btn');
    if (detailBtn && currentItem) {
        const isWished = wishlist.some(i => i.id === currentItem.id);
        if (isWished) {
            detailBtn.classList.replace('btn-outline-danger', 'btn-danger');
            detailBtn.querySelector('.detail-wishlist-icon').classList.replace('bi-heart', 'bi-heart-fill');
            detailBtn.querySelector('.detail-wishlist-text').innerText = (cfg && cfg.langRemoveWishlist) ? cfg.langRemoveWishlist : '찜취소';
        } else {
            detailBtn.classList.replace('btn-danger', 'btn-outline-danger');
            detailBtn.querySelector('.detail-wishlist-icon').classList.replace('bi-heart-fill', 'bi-heart');
            detailBtn.querySelector('.detail-wishlist-text').innerText = (cfg && cfg.langAddWishlist) ? cfg.langAddWishlist : '찜하기';
        }
    }
}

function flyToCartAnimationWish(sourceElement) {
    if (!sourceElement) return;
    const targetElement = document.querySelector('#floating-wishlist-bar button');
    if (!targetElement) return;

    const sourceRect = sourceElement.getBoundingClientRect();
    const targetRect = targetElement.getBoundingClientRect();

    const flyingImg = sourceElement.cloneNode(true);
    flyingImg.className = 'fly-to-cart';
    flyingImg.style.left = `${sourceRect.left}px`;
    flyingImg.style.top = `${sourceRect.top}px`;
    flyingImg.style.width = `${sourceRect.width}px`;
    flyingImg.style.height = `${sourceRect.height}px`;
    document.body.appendChild(flyingImg);

    setTimeout(() => {
        flyingImg.style.left = `${targetRect.left + targetRect.width / 2 - 15}px`;
        flyingImg.style.top = `${targetRect.top + targetRect.height / 2 - 15}px`;
        flyingImg.style.width = '30px';
        flyingImg.style.height = '30px';
        flyingImg.style.opacity = '0';
        flyingImg.style.transform = 'scale(0.1) rotate(360deg)';
    }, 10);

    setTimeout(() => {
        flyingImg.remove();
    }, 800);
}

window.showWishlistModal = function() {
    const listContainer = document.getElementById('wishlist-items-list');
    if(!listContainer) return;
    listContainer.innerHTML = '';
    
    if (wishlist.length === 0) {
        listContainer.innerHTML = '<div class="text-center py-5 text-muted">위시 리스트가 비어 있습니다.</div>';
        showBsModal('wishlistModal');
        return;
    }
    
    wishlist.forEach((item, index) => {
        const priceHtml = item.originalPrice ?
            `<span class="text-decoration-line-through text-muted small me-2">${cfg.currencySymbol} ${parseInt(item.originalPrice).toLocaleString()}</span>${cfg.currencySymbol} ${parseInt(item.price).toLocaleString()}` :
            `${cfg.currencySymbol} ${parseInt(item.price).toLocaleString()}`;
            
        const thumbImg = getThumbImg(item.id);
        const optThumb = getOptimizedThumbImgPath(thumbImg);
        const nameHtml = item.originalPrice ? `${item.name} <span class="badge bg-danger ms-1" style="font-size: 0.7rem;">할인</span>` : item.name;

        listContainer.innerHTML += `
        <div class="card border-0 bg-light rounded-3 p-2 mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                    <img src="${optThumb}" class="rounded shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="if(this.getAttribute('data-fallback') !== 'true'){ this.setAttribute('data-fallback', 'true'); this.src='${thumbImg}'; } else { this.onerror=null; this.src='/assets/no-logo.png'; }">
                    <div>
                        <div class="fw-bold text-dark fs-6">${nameHtml}</div>
                        <div class="text-primary fw-bold small">${priceHtml}</div>
                    </div>
                </div>
                <button class="btn btn-sm text-danger p-0 px-2" onclick="removeWishlistItem(${index})"><i class="bi bi-heart-fill fs-5"></i></button>
            </div>
        </div>`;
    });
    
    showBsModal('wishlistModal');
};

window.removeWishlistItem = function(index) {
    const cfg = getShopConfig();
    const item = wishlist[index];
    wishlist.splice(index, 1);
    saveWishlist();
    updateWishlistUI();
    
    const icons = document.querySelectorAll('.wishlist-icon-' + item.id);
    const texts = document.querySelectorAll('.wishlist-text-' + item.id);
    icons.forEach(i => i.classList.replace('bi-heart-fill', 'bi-heart'));
    texts.forEach(t => t.innerText = (cfg && cfg.langAddWishlist) ? cfg.langAddWishlist : '찜하기');
    icons.forEach(icon => {
        const btn = icon.closest('button');
        if(btn) btn.classList.replace('btn-danger', 'btn-outline-danger');
    });
    
    showWishlistModal(); 
    
    if (wishlist.length === 0) {
        hideBsModal('wishlistModal');
    }
};

// ============================================================================
// [모달 내 UI 함수 통합]
// ============================================================================
window.updateCartTotalUI = function(isDelivery) {
    const cfg = getShopConfig();
    const subtotalElem = document.getElementById('cart-view-subtotal');
    if (!subtotalElem || !cfg) return;
    
    let subtotalStr = subtotalElem.innerText.replace(/[^0-9]/g, '');
    let subtotal = parseInt(subtotalStr, 10) || 0;
    
    let curDeliveryFee = 0;
    if (isDelivery) {
        const defaultFee = cfg.deliveryFee || 0;
        const freeThreshold = cfg.freeDeliveryAmount || 0;
        
        if (freeThreshold > 0 && subtotal >= freeThreshold) {
            curDeliveryFee = 0;
        } else {
            curDeliveryFee = defaultFee;
        }
    }
    
    let total = subtotal + curDeliveryFee;
    const totalElem1 = document.getElementById('cart-total-price');
    if (totalElem1) totalElem1.innerText = cfg.currencySymbol + ' ' + total.toLocaleString();
};

window.toggleOrderType = function() {
    const isDelivery = document.getElementById('order_delivery') ? document.getElementById('order_delivery').checked : false;
    const addrWrap = document.getElementById('delivery_address_wrap');
    const pickWrap = document.getElementById('pickup_notice_wrap');
    const pickupTimeWrap = document.getElementById('pickup_time_wrap');
    const feeNotices = document.querySelectorAll('.delivery-fee-notice-area');
    const fetchAddressBtn = document.getElementById('btn-fetch-address');
    
    feeNotices.forEach(el => { el.style.display = isDelivery ? 'block' : 'none'; });
    
    if (isDelivery) {
        if (addrWrap) addrWrap.style.display = 'block';
        if (pickWrap) pickWrap.style.display = 'none';
        if (fetchAddressBtn) fetchAddressBtn.style.display = '';
        if (pickupTimeWrap) {
            pickupTimeWrap.style.display = 'none';
            const ptInput = document.getElementById('pickup_time');
            if (ptInput) ptInput.required = false;
        }
    } else {
        if (addrWrap) addrWrap.style.display = 'none';
        if (pickWrap) pickWrap.style.display = 'block';
        if (fetchAddressBtn) fetchAddressBtn.style.display = 'none';
        if (pickupTimeWrap) {
            pickupTimeWrap.style.display = 'block';
            const ptInput = document.getElementById('pickup_time');
            if (ptInput) ptInput.required = true;
        }
    }
    
    window.updateCartTotalUI(isDelivery);
};

let deliveryMap = null;
let currentCoordinates = { lat: 14.5995, lng: 120.9842 };

window.openLocationMapModal = function() {
    const coordInput = document.getElementById('customer_coordinates');
    if (coordInput && coordInput.value.includes('google.com/maps?q=')) {
        const match = coordInput.value.match(/q=(-?\d+\.\d+),(-?\d+\.\d+)/);
        if (match) {
            currentCoordinates = { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
        }
    }

    showBsModal('locationMapModal');
    const modalEl = document.getElementById('locationMapModal');
    
    modalEl.addEventListener('shown.bs.modal', function initMapOnModal() {
        if (typeof L === 'undefined') { alert('지도 라이브러리(Leaflet)가 로드되지 않았습니다.'); return; }
        if (!deliveryMap) {
            deliveryMap = L.map('delivery-map-container', { zoomControl: true, attributionControl: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(deliveryMap);
            deliveryMap.setView([currentCoordinates.lat, currentCoordinates.lng], 16);
            deliveryMap.on("move", () => {
                const center = deliveryMap.getCenter();
                currentCoordinates = { lat: center.lat, lng: center.lng };
            });
            deliveryMap.on("click", (e) => { deliveryMap.panTo(e.latlng); });
            if (!coordInput || !coordInput.value.includes('google.com/maps?q=')) {
                window.moveToCurrentLocation(true);
            }
        } else {
            deliveryMap.invalidateSize();
            deliveryMap.setView([currentCoordinates.lat, currentCoordinates.lng], 16);
        }
    }, { once: true });
};

window.moveToCurrentLocation = function(silent = false) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            const pos = { lat: position.coords.latitude, lng: position.coords.longitude };
            if (deliveryMap) {
                deliveryMap.setView([pos.lat, pos.lng], 16);
                currentCoordinates = pos;
            }
        }, (error) => {
            if (!silent) alert('위치 정보를 가져올 수 없습니다.\n스마트폰의 GPS(위치) 설정과 브라우저 권한을 확인해주세요.');
        }, { enableHighAccuracy: true, timeout: 5000 });
    } else {
        if (!silent) alert('이 브라우저에서는 위치 정보(GPS)를 지원하지 않습니다.');
    }
};

window.saveLocationCoordinates = function() {
    const coordStr = `${currentCoordinates.lat},${currentCoordinates.lng}`;
    const mapUrl = `https://www.google.com/maps?q=${coordStr}`;
    const coordInput = document.getElementById('customer_coordinates');

    if (coordInput) {
        coordInput.value = mapUrl;
        coordInput.style.display = 'block';
    }

    const latInput = document.getElementById('customer_lat');
    const lngInput = document.getElementById('customer_lng');
    if (latInput && lngInput) {
        latInput.value = currentCoordinates.lat;
        lngInput.value = currentCoordinates.lng;
    }

    localStorage.setItem('ps24_guest_coordinates', mapUrl);
    hideBsModal('locationMapModal');
};

window.openOrderConfirmModal = function() {
    const isDelivery = document.getElementById('order_delivery') ? document.getElementById('order_delivery').checked : false;
    const pickupTimeInput = document.getElementById('pickup_time');
    
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('orderForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 배달 주소 등)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const phoneInput = document.getElementById('customer_phone');
    const addressInput = document.getElementById('customer_address');

    if (!phoneInput || !phoneInput.value.trim()) return alert('연락처를 입력해주세요.');
    if (isDelivery && (!addressInput || !addressInput.value.trim())) return alert('배달 주소를 입력해주세요.');
    if (!isDelivery && pickupTimeInput && !pickupTimeInput.value.trim()) return alert('매장 방문 예정 시간을 입력해주세요.');

    const latInput = document.getElementById('customer_lat');
    const lngInput = document.getElementById('customer_lng');
    if (latInput && lngInput && (!latInput.value || !lngInput.value)) {
        const savedCoord = localStorage.getItem('ps24_guest_coordinates');
        if (savedCoord) {
            const match = savedCoord.match(/q=(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (match) { latInput.value = match[1]; lngInput.value = match[2]; }
        }
    }

    const isCash = document.getElementById('pay_cash') && document.getElementById('pay_cash').checked;
    const paymentMethodText = isCash ? '현금 (Cash)' : '기타 (GCash, Bank 등)';
    const paymentDetail = isCash ? (document.getElementById('payment_detail_cash') ? document.getElementById('payment_detail_cash').value : '') : (document.getElementById('payment_detail_other') ? document.getElementById('payment_detail_other').value : '');
    
    let orderTypeText = isDelivery ? '배달' : '매장픽업';
    if (!isDelivery && pickupTimeInput && pickupTimeInput.value.trim()) orderTypeText += ' <span class="badge bg-secondary ms-1 fw-normal">' + pickupTimeInput.value.trim() + ' 방문예정</span>';

    const phone = phoneInput.value;
    const address = addressInput.value;
    const landmark = document.getElementById('customer_landmark') ? document.getElementById('customer_landmark').value : '';
    const totalPriceText = document.getElementById('cart-total-price') ? document.getElementById('cart-total-price').innerText : '₱ 0';
    const deliveryFeeText = document.getElementById('cart-view-delivery-fee') ? document.getElementById('cart-view-delivery-fee').innerText : '₱ 0';

    document.getElementById('confirm-order-type').innerHTML = orderTypeText;
    document.getElementById('confirm-payment-method').innerText = paymentMethodText;
    document.getElementById('confirm-customer-phone').innerText = phone;

    let addressHtml = address;
    if (latInput && lngInput && latInput.value && lngInput.value) {
        addressHtml += ' <span class="badge bg-danger bg-opacity-10 text-danger ms-1 border border-danger fw-bold" style="font-size:0.7rem;"><i class="bi bi-geo-alt-fill me-1"></i>핀위치 포함됨</span>';
    }
    document.getElementById('confirm-customer-address').innerHTML = addressHtml;

    if (isDelivery) {
        document.getElementById('confirm-customer-address-row').style.display = 'flex';
        document.getElementById('confirm-landmark-row').style.display = 'flex';
    } else {
        document.getElementById('confirm-customer-address-row').style.display = 'none';
        document.getElementById('confirm-landmark-row').style.display = 'none';
    }

    const landmarkRow = document.getElementById('confirm-landmark-row');
    if (landmarkRow) {
        document.getElementById('confirm-customer-landmark').innerText = landmark;
        landmarkRow.style.display = (landmark && isDelivery) ? 'flex' : 'none';
    }

    const detailRow = document.getElementById('confirm-payment-detail-row');
    if (detailRow) {
        document.getElementById('confirm-payment-detail').innerText = paymentDetail;
        detailRow.style.display = paymentDetail ? 'flex' : 'none';
    }

    const confirmDeliveryFee = document.getElementById('confirm-delivery-fee');
    if (confirmDeliveryFee) {
        if (isDelivery) {
            const feeValue = parseInt(deliveryFeeText.replace(/[^0-9]/g, ''), 10) || 0;
            if (feeValue === 0 || deliveryFeeText.includes('무료')) confirmDeliveryFee.innerHTML = '<span class="text-success">무료 (Free)</span>';
            else confirmDeliveryFee.innerHTML = deliveryFeeText;
        } else {
            confirmDeliveryFee.innerHTML = '₱ 0 (매장픽업)';
        }
    }

    document.getElementById('confirm-total-price').innerText = totalPriceText;

    const confirmItemsList = document.getElementById('confirm-items-list');
    const cartItemsContainer = document.getElementById('cart-items-list');
    if (cartItemsContainer && confirmItemsList) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = cartItemsContainer.innerHTML;
        wrapper.querySelectorAll('button, input').forEach(c => c.remove());
        wrapper.querySelectorAll('.d-flex.align-items-center').forEach(item => {
            item.classList.remove('bg-white', 'shadow-sm', 'p-2');
            item.classList.add('border-bottom', 'pb-2', 'mb-2');
        });
        confirmItemsList.innerHTML = wrapper.innerHTML;
    }

    showBsModal('orderConfirmModal');
};

window.processFinalOrder = function() {
    hideBsModal('orderConfirmModal');
    setTimeout(() => {
        if (typeof submitOrder === 'function') {
            const originalFetch = window.fetch;
            window.fetch = async function(...args) {
                if (args[0] && typeof args[0] === 'string' && args[0].includes('shop_fnb_order_handler.php')) {
                    const options = args[1];
                    if (options && options.body && options.body instanceof FormData) {
                        const lat = document.getElementById('customer_lat')?.value;
                        const lng = document.getElementById('customer_lng')?.value;
                        if (lat && lng && !options.body.has('customer_lat')) {
                            options.body.append('customer_lat', lat);
                            options.body.append('customer_lng', lng);
                        }
                        
                        const isDelivery = document.getElementById('order_delivery') ? document.getElementById('order_delivery').checked : false;
                        const orderType = isDelivery ? 'delivery' : 'pickup';
                        if (!options.body.has('order_type')) options.body.append('order_type', orderType);
                        if (!isDelivery) {
                            const pickupTime = document.getElementById('pickup_time')?.value;
                            if (pickupTime && !options.body.has('pickup_time')) options.body.append('pickup_time', pickupTime);
                        }
                    }
                }
                const response = await originalFetch.apply(this, args);
                window.fetch = originalFetch;
                return response;
            };
            submitOrder();
        }
    }, 300);
};

// ============================================================================
// [UI/UX 이벤트 바인딩 및 폴링 엔진 초기화]
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const cfg = getShopConfig();
    if (cfg) {
        try { cart = JSON.parse(localStorage.getItem('fnb_cart_' + cfg.shopId)) || []; if (!Array.isArray(cart)) cart = []; } catch (e) { cart = []; }
        try { wishlist = JSON.parse(localStorage.getItem('fnb_wishlist_' + cfg.shopId)) || []; if (!Array.isArray(wishlist)) wishlist = []; } catch (e) { wishlist = []; }
        freeDeliveryAmount = cfg.freeDeliveryAmount || 0;
        deliveryFee = cfg.deliveryFee || 0;
    }
    updateCartUI();
    updateWishlistUI();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_cart') === '1' && cart.length > 0) {
        if (cfg && !cfg.needsPhInfo) renderCartModalContent();
        const url = new URL(window.location);
        url.searchParams.delete('open_cart');
        window.history.replaceState({}, '', url);
    }

    // [UX 개선] 주문서 작성 폼 제출 시 누락된 필수 필드 빨간색 강조 (fnb_scripts.php 에서 이동)
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-primary');
        if (btn && btn.getAttribute('onclick') && btn.getAttribute('onclick').includes('submitOrder')) {
            const phoneInput = document.getElementById('customer_phone');
            const addressInput = document.getElementById('customer_address');

            if (phoneInput && phoneInput.offsetParent !== null && !phoneInput.value.replace(/[^0-9]/g, '').trim()) {
                phoneInput.classList.remove('is-invalid-pulse');
                void phoneInput.offsetWidth; 
                phoneInput.classList.add('is-invalid-pulse');
            }
            if (addressInput && addressInput.offsetParent !== null && !addressInput.value.trim()) {
                addressInput.classList.remove('is-invalid-pulse');
                void addressInput.offsetWidth;
                addressInput.classList.add('is-invalid-pulse');
            }
        }
    });
    document.body.addEventListener('input', function(e) {
        if (e.target && (e.target.id === 'customer_phone' || e.target.id === 'customer_address')) {
            if (e.target.value.trim()) e.target.classList.remove('is-invalid-pulse', 'is-invalid');
        }
    });

    // [실시간 폴링]
    let knownOrderStatuses = {};
    let isFirstPoll = true;
    // PHP 상수를 직접 읽을 수 없으므로 실제 경로 문자열을 삽입하거나 전역 변수 여부를 체크합니다.
    const notifySound = new Audio(typeof NOTIFICATION_SOUND !== 'undefined' ? NOTIFICATION_SOUND : '/assets/sounds/dingdongg.mp3');

    window.pollOrderStatus = async function() {
        let phone = '';
        if (cfg && cfg.customerPhone) {
            phone = cfg.customerPhone.replace(/\D/g, '');
        } else {
            const guestPhone = localStorage.getItem('ps24_guest_phone');
            if (guestPhone) phone = guestPhone.replace(/\D/g, '');
        }
        if (!phone) return;

        const fd = new FormData(); fd.append('action', 'poll'); fd.append('shop_id', cfg.shopId); fd.append('customer_phone', phone);
        try {
            const response = await fetch('/shops/fnb/shop_fnb_order_history.php', { method: 'POST', body: fd });
            const result = await response.json();
            if (result.status === 'success' && result.orders) {
                let hasOrders = result.orders.length > 0;
                result.orders.forEach(order => {
                    const currentStatus = order.status;
                    if (!isFirstPoll && knownOrderStatuses[order.id] && knownOrderStatuses[order.id] !== currentStatus) {
                        const sInfo = (cfg && cfg.orderStatusMap && cfg.orderStatusMap[currentStatus]) ? Object.assign({}, cfg.orderStatusMap[currentStatus]) : { text: currentStatus, class: 'primary' };
                        if (currentStatus === 'delivery' && order.order_type === 'pickup') sInfo.text = '픽업대기';
                        
                        const toastEl = document.getElementById('orderStatusToast');
                        if(toastEl) {
                            document.getElementById('orderStatusToastBody').innerHTML = `🔔 주문하신 메뉴가 <span class="badge bg-white text-${sInfo.class} ms-1">${sInfo.text}</span> 상태가 되었습니다!`;
                            toastEl.className = `toast align-items-center text-white bg-${sInfo.class} border-0 w-100 shadow-lg rounded-4`;
                            if (typeof bootstrap !== 'undefined') new bootstrap.Toast(toastEl, { autohide: false }).show();
                        }
                        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                        notifySound.play().catch(e => {});
                        if (document.getElementById('orderHistoryModal') && document.getElementById('orderHistoryModal').classList.contains('show')) searchOrderHistory();

                        if (!isOrderStatusPanelOpen) {
                            const badge = document.getElementById('floating-order-status-badge');
                            if (badge) badge.style.display = 'block';
                        }
                        
                        if (currentStatus === 'cooking' || currentStatus === 'delivery' || currentStatus === 'completed') {
                            const alertModal = document.getElementById('orderStatusAlertModal');
                            if (alertModal) {
                                const titleEl = document.getElementById('orderStatusAlertTitle');
                                const descEl = document.getElementById('orderStatusAlertDesc');
                                const iconEl = document.getElementById('orderStatusAlertIcon');
                                
                                if (currentStatus === 'cooking') {
                                    iconEl.innerHTML = '<i class="bi bi-fire text-danger" style="font-size: 3.5rem;"></i>';
                                    titleEl.innerText = cfg.langAlertCookingTitle || '주문하신 음식이 맛있게 요리 중입니다! 👨‍🍳';
                                    descEl.innerText = cfg.langAlertCookingDesc || '주문하신 음식을 정성껏 만들고 있습니다.';
                                } else if (currentStatus === 'delivery') {
                                    if (order.order_type === 'pickup') {
                                        iconEl.innerHTML = '<i class="bi bi-bag-check text-info" style="font-size: 3.5rem;"></i>';
                                        titleEl.innerText = cfg.langMenuReadyTitle || '메뉴가 준비되었습니다! 🛍️';
                                        descEl.innerText = cfg.langMenuReadyDesc || '주문하신 메뉴가 포장 완료되었습니다. 매장으로 방문해주세요.';
                                    } else {
                                        iconEl.innerHTML = '<i class="bi bi-scooter text-info" style="font-size: 3.5rem;"></i>';
                                        titleEl.innerText = cfg.langAlertDeliveryTitle || '주문하신 음식 배달을 시작하였습니다! 🛵';
                                        descEl.innerText = cfg.langAlertDeliveryDesc || '맛있는 음식이 고객님을 향해 출발했습니다.';
                                    }
                                } else if (currentStatus === 'completed') {
                                    iconEl.innerHTML = '<i class="bi bi-patch-check-fill text-success" style="font-size: 3.5rem;"></i>';
                                    titleEl.innerText = cfg.langAlertCompletedTitle || '주문이 완료되었습니다! 🙏';
                                    descEl.innerText = cfg.langAlertCompletedDesc || '주문이 완료 되었습니다. 저희 상점을 이용해 주셔서 감사합니다 !!!';
                                }
                                
                                if (typeof bootstrap !== 'undefined') {
                                    const modalInstance = bootstrap.Modal.getOrCreateInstance(alertModal);
                                    modalInstance.show();
                                }
                            }
                        }
                    }
                    knownOrderStatuses[order.id] = currentStatus;
                });
                
                // [수정] 진행 중인(주문접수, 요리중, 배달중) 주문만 필터링
                const activeOrders = result.orders.filter(o => ['pending', 'cooking', 'delivery'].includes(o.status));
                const hasActiveOrders = activeOrders.length > 0;
                
                const btn = document.getElementById('floating-order-status-btn');
                if (btn) {
                    if (hasActiveOrders) {
                        btn.classList.remove('d-none');
                        
                        // [추가] 가장 최근 주문의 상태를 기준으로 동적 아이콘 업데이트
                        const iconEl = document.getElementById('floating-order-status-icon');
                        if (iconEl) {
                            const currentOrder = activeOrders[0];
                            const currentStatus = currentOrder.status;
                            let iconClass = 'bi-scooter';
                            if (currentStatus === 'pending') iconClass = 'bi-check2-circle'; // 체크 마크
                            else if (currentStatus === 'cooking') iconClass = 'bi-cup-hot';  // 김이 나는 음식
                            else if (currentStatus === 'delivery') iconClass = currentOrder.order_type === 'pickup' ? 'bi-bag-check' : 'bi-scooter'; // 오토바이 또는 픽업백
                            iconEl.className = `bi ${iconClass} fs-3 text-dark`;
                        }
                    } else {
                        btn.classList.add('d-none');
                    }
                }
                
                const contentPanel = document.getElementById('floating-order-status-content');
                if (contentPanel) {
                    if (!hasActiveOrders) {
                        const msgNoActiveOrder = (cfg && cfg.langNoActiveOrder) ? cfg.langNoActiveOrder : '진행 중인 주문 내역이 없습니다.';
                        contentPanel.innerHTML = `<div class="text-center py-4 text-muted small">${msgNoActiveOrder}</div>`;
                        // 패널이 열려있는 상태에서 모든 주문이 완료(또는 취소)되었다면 창을 자동으로 닫아줌
                        if (isOrderStatusPanelOpen) window.toggleOrderStatusPanel();
                    } else {
                        contentPanel.innerHTML = activeOrders.map(order => {
                            let statusObj = (cfg && cfg.orderStatusMap) ?
                                Object.assign({}, cfg.orderStatusMap[order.status] || { text: order.status, class: 'secondary' }) : { text: order.status, class: 'secondary' };

                            if (order.status === 'delivery' && order.order_type === 'pickup') {
                                statusObj.text = '픽업대기';
                            }

                            let iconClass = 'bi-info-circle';
                            if (order.status === 'pending') iconClass = 'bi-clock';
                            else if (order.status === 'cooking') iconClass = 'bi-fire';
                            else if (order.status === 'delivery') iconClass = order.order_type === 'pickup' ? 'bi-bag-check' : 'bi-scooter';
                            else if (order.status === 'completed') iconClass = 'bi-check-circle';
                            else if (order.status === 'cancelled') iconClass = 'bi-x-circle';

                            let itemsHtml = '';
                            if (order.items) {
                                try {
                                    let itemsArray = JSON.parse(order.items);
                                    if (itemsArray.length > 0) {
                                        let summary = itemsArray.map(item => `${item.item_name} <span class="fw-bold text-dark">x${item.quantity}</span>`).join(', ');
                                        itemsHtml = `<div class="text-muted mt-2 text-break" style="font-size:0.75rem;"><i class="bi bi-cart2 me-1 text-primary"></i>${summary}</div>`;
                                    }
                                } catch (e) {}
                            }

                            let addressHtml = '';
                            if (order.order_type === 'pickup') {
                                addressHtml = `<div class="text-muted mt-1 text-break" style="font-size:0.75rem;"><i class="bi bi-shop me-1 text-primary"></i>매장픽업: <span class="fw-bold text-danger">${order.pickup_time || '시간 미지정'}</span></div>`;
                            } else if (order.customer_address) {
                                addressHtml = `<div class="text-muted mt-1 text-break" style="font-size:0.75rem;"><i class="bi bi-geo-alt me-1 text-danger"></i>${order.customer_address}</div>`;
                            }

                            let timeStr = order.created_at;
                            try {
                                let d = new Date(order.created_at.replace(/-/g, '/')); // iOS Safari 호환성 처리
                                if (!isNaN(d)) timeStr = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                            } catch (e) {}

                            return `<div class="card border-0 shadow-sm mb-3 rounded-3"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center mb-2"><span class="badge bg-${statusObj.class} px-2 py-1 shadow-sm"><i class="bi ${iconClass} me-1"></i>${statusObj.text}</span><span class="small fw-bold text-primary">₱ ${parseInt(order.total_price).toLocaleString()}</span></div><div class="d-flex justify-content-between align-items-center mb-1"><span class="fw-bold text-dark small"><i class="bi bi-receipt me-1"></i>${order.order_no}</span><span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i>${timeStr}</span></div><div class="bg-light p-2 rounded-2 mt-2 border">${itemsHtml}${addressHtml}</div></div></div>`;
                        }).join('');
                    }
                }
                isFirstPoll = false;
            }
        } catch (err) {}
    };
    setInterval(window.pollOrderStatus, 10000);
    window.pollOrderStatus();


    // 엔진 초기화가 성공적으로 끝났음을 알림 (로딩 먹통 방지 인터셉터 해제)
    window.ps24JsLoaded = true;
});