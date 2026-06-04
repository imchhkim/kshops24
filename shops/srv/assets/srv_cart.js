<<<<<<< HEAD
/**
 * [SRV 카테고리 전용 자바스크립트 엔진]
 * 위치: /shops/srv/assets/srv_cart.js
 * 설명: 서비스/예약 상점의 프론트엔드 비즈니스 로직(장바구니, 모달, 폴링, 달력 렌더링 등)을 단일 파일로 캡슐화
 */

let isOrderStatusPanelOpen = false;
let knownOrderStatuses = {};
let isFirstPoll = true;
const srvNotifySoundUrl = typeof NOTIFICATION_SOUND !== 'undefined' ? NOTIFICATION_SOUND : '/assets/sounds/dingdongg.mp3';
const notifySound = new Audio(srvNotifySoundUrl);

let wishlist = [];
let inquiryToDelete = null;
let inquiryToCancel = null;
let pendingSrvAction = null;
window.hasConfirmedLoginChoice = false;

// 모바일 환경 페이지 로딩 후 점프 방지 
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
sessionStorage.removeItem('pageScrollPos');
if (window.location.hash) {
    history.replaceState(null, null, window.location.pathname + window.location.search);
}
window.scrollTo(0, 0);

document.addEventListener('DOMContentLoaded', () => {
    const cfg = getShopConfig();
    if (cfg) {
        wishlist = JSON.parse(localStorage.getItem('wishlist_' + cfg.shopId)) || [];
        updateWishlistUI();
        pollOrderStatus();
        setInterval(pollOrderStatus, 10000);
    }

    // 서비스 상세 모달 이벤트 바인딩
    const detailModal = document.getElementById('menuDetailModal');
    if (detailModal) {
        detailModal.addEventListener('hidden.bs.modal', function() {
            const videoContainer = document.getElementById('menu-detail-video');
            const photoContainer = document.getElementById('menu-detail-photo');
            if (videoContainer) videoContainer.innerHTML = '';
            if (photoContainer) photoContainer.innerHTML = '';
        });
        detailModal.addEventListener('shown.bs.modal', function() {
            if (!document.getElementById('single_reservation_date').value) {
                window.renderCalendar('single');
                window.updateAvailableTimesGrid('single');
            }
            window.checkMyReservationsNotice('single');
        });
    }

    // 카트 모달 이벤트 바인딩
    const cartModal = document.getElementById('cartViewModal');
    if (cartModal) {
        cartModal.addEventListener('shown.bs.modal', function() {
            if (!document.getElementById('cart_reservation_date').value) {
                window.renderCalendar('cart');
                window.updateAvailableTimesGrid('cart');
            }
            window.checkMyReservationsNotice('cart');
        });
    }

    const successModalEl = document.getElementById('inquirySuccessModal');
    if (successModalEl) {
        successModalEl.addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
    }

    // 카테고리 스크롤 드래그 설정
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

window.toggleOrderStatusPanel = function() {
    const panel = document.getElementById('floating-order-status-panel');
    const badge = document.getElementById('floating-order-status-badge');
    if (!panel) return;
    
    isOrderStatusPanelOpen = !isOrderStatusPanelOpen;
    if (isOrderStatusPanelOpen) {
        panel.classList.remove('d-none');
        if (badge) badge.style.display = 'none';
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

window.pollOrderStatus = async function() {
    const cfg = getShopConfig();
    let phone = '';
    if (cfg && cfg.customerPhone) {
        phone = cfg.customerPhone.replace(/\D/g, '');
    } else {
        const guestPhone = localStorage.getItem('srv_last_search_phone');
        if (guestPhone) phone = guestPhone.replace(/\D/g, '');
    }
    if (!phone) return;

    const fd = new FormData(); fd.append('action', 'poll'); fd.append('shop_id', cfg.shopId); fd.append('phone', phone);
    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: fd });
        const result = await response.json();
        if (result.status === 'success' && result.orders) {
            let hasOrders = result.orders.length > 0;
            result.orders.forEach(order => {
                const currentStatus = order.status;
                if (!isFirstPoll && knownOrderStatuses[order.id] && knownOrderStatuses[order.id] !== currentStatus) {
                    const sInfo = (cfg.orderStatusMap && cfg.orderStatusMap[currentStatus]) ? Object.assign({}, cfg.orderStatusMap[currentStatus]) : { text: currentStatus, class: 'primary' };
                    
                    const toastEl = document.getElementById('orderStatusToast');
                    if(toastEl) {
                        document.getElementById('orderStatusToastBody').innerHTML = `🔔 고객님의 예약이 <span class="badge bg-white text-${sInfo.class} ms-1">${sInfo.text}</span> 상태가 되었습니다!`;
                        toastEl.className = `toast align-items-center text-white bg-${sInfo.class} border-0 w-100 shadow-lg rounded-4`;
                        if (typeof bootstrap !== 'undefined') new bootstrap.Toast(toastEl, { autohide: false }).show();
                    }
                    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                    notifySound.play().catch(e => {});
                    if (document.getElementById('serviceInquiryHistoryModal') && document.getElementById('serviceInquiryHistoryModal').classList.contains('show')) window.fetchServiceInquiryHistory(phone);

                    if (!isOrderStatusPanelOpen) {
                        const badge = document.getElementById('floating-order-status-badge');
                        if (badge) badge.style.display = 'block';
                    }
                }
                knownOrderStatuses[order.id] = currentStatus;
            });
            
            const activeOrders = result.orders.filter(o => ['pending', 'confirmed', 'contacted'].includes(o.status));
            const hasActiveOrders = activeOrders.length > 0;
            
            const btn = document.getElementById('floating-order-status-btn');
            if (btn) {
                if (hasActiveOrders) {
                    btn.classList.remove('d-none');
                    const iconEl = document.getElementById('floating-order-status-icon');
                    if (iconEl) {
                        const currentOrder = activeOrders[0];
                        const currentStatus = currentOrder.status;
                        let iconClass = 'bi-calendar-check';
                        if (currentStatus === 'pending') iconClass = 'bi-check2-circle'; 
                        iconEl.className = `bi ${iconClass} fs-3 text-dark`;
                    }
                } else {
                    btn.classList.add('d-none');
                }
            }
            
            const contentPanel = document.getElementById('floating-order-status-content');
            if (contentPanel) {
                if (!hasActiveOrders) {
                    contentPanel.innerHTML = '<div class="text-center py-4 text-muted small">진행 중인 예약 내역이 없습니다.</div>';
                    if (isOrderStatusPanelOpen) window.toggleOrderStatusPanel();
                } else {
                    contentPanel.innerHTML = activeOrders.map(order => {
                        let statusObj = (cfg.orderStatusMap) ? Object.assign({}, cfg.orderStatusMap[order.status] || { text: order.status, class: 'secondary' }) : { text: order.status, class: 'secondary' };
                        let iconClass = 'bi-info-circle';
                        if (order.status === 'pending') iconClass = 'bi-clock';
                        else if (order.status === 'confirmed' || order.status === 'contacted') iconClass = 'bi-calendar-check';
                        else if (order.status === 'completed') iconClass = 'bi-check-circle';
                        else if (order.status === 'cancelled') iconClass = 'bi-x-circle';

                        let itemsHtml = '';
                        if (order.items) {
                            try {
                                let itemsArray = JSON.parse(order.items);
                                if (itemsArray.length > 0) {
                                    let summary = itemsArray.map(item => `${item.name} <span class="fw-bold text-dark">x1</span>`).join(', ');
                                    itemsHtml = `<div class="text-muted mt-2 text-break" style="font-size:0.75rem;"><i class="bi bi-cart2 me-1 text-primary"></i>${summary}</div>`;
                                }
                            } catch (e) {}
                        }

                        let timeStr = order.created_at;
                        try {
                            let d = new Date(order.created_at.replace(/-/g, '/')); 
                            if (!isNaN(d)) timeStr = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                        } catch (e) {}

                        let replyHtml = '';
                        if (order.owner_reply && order.owner_reply.trim() !== '') {
                            let escapedReply = order.owner_reply.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                            replyHtml = `<div class="mt-2 p-2 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-2 small text-dark"><div class="fw-bold text-primary mb-1" style="font-size: 0.7rem;"><i class="bi bi-reply-fill me-1"></i>상점의 답변</div><div style="font-size: 0.75rem;">${escapedReply}</div></div>`;
                        }

                        return `<div class="card border-0 shadow-sm mb-3 rounded-3"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center mb-2"><span class="badge bg-${statusObj.class} px-2 py-1 shadow-sm"><i class="bi ${iconClass} me-1"></i>${statusObj.text}</span></div><div class="d-flex justify-content-between align-items-center mb-1"><span class="fw-bold text-dark small"><i class="bi bi-receipt me-1"></i>${order.order_no}</span><span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i>${timeStr}</span></div><div class="bg-light p-2 rounded-2 mt-2 border">${itemsHtml}</div>${replyHtml}</div></div>`;
                    }).join('');
                }
            }
            isFirstPoll = false;
        }
    } catch (err) {}
};

// ==========================================
// [달력 렌더링 로직 통합]
// ==========================================
window.checkMyReservationsNotice = function(type) {
    const cfg = getShopConfig();
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};
    const noticeEl = document.getElementById(type + '_my_reservation_notice');
    if (noticeEl) {
        if (Object.keys(myRes).length > 0) noticeEl.classList.remove('d-none');
        else noticeEl.classList.add('d-none');
    }
}

window.renderCalendar = function(type, initDate = new Date()) {
    const cfg = getShopConfig();
    const container = document.getElementById(type + '_calendar_container');
    if (!container) return;
    
    const year = initDate.getFullYear();
    const month = initDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    today.setHours(0,0,0,0);
    const days = ['일', '월', '화', '수', '목', '금', '토']; // 프론트엔드 기본값
    const dayNames = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-2 px-2">
            <button type="button" class="btn btn-sm btn-white border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:30px; height:30px;" onclick="window.renderCalendar('${type}', new Date(${year}, ${month-1}, 1))"><i class="bi bi-chevron-left text-dark"></i></button>
            <div class="fw-bold fs-6">${year}. ${String(month + 1).padStart(2, '0')}</div>
            <button type="button" class="btn btn-sm btn-white border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:30px; height:30px;" onclick="window.renderCalendar('${type}', new Date(${year}, ${month+1}, 1))"><i class="bi bi-chevron-right text-dark"></i></button>
        </div>
        <div class="d-flex w-100 mb-1">
    `;
    days.forEach((d, idx) => {
        let colorClass = idx === 0 ? 'text-danger' : (idx === 6 ? 'text-primary' : 'text-muted');
        html += `<div class="flex-fill text-center small fw-bold ${colorClass}" style="font-size: 0.8rem;">${d}</div>`;
    });
    html += `</div><div class="d-flex flex-wrap w-100">`;
    for(let i=0; i<firstDay; i++) {
        html += `<div style="width: 14.28%; padding: 2px; padding-bottom: 16px;"></div>`;
    }
    const selectedDateInput = document.getElementById(type + '_reservation_date');
    const selectedDate = selectedDateInput ? selectedDateInput.value : '';
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};

    let availableSlotsConf = {};
    let holidaysConf = {};
    if (cfg && cfg.reservationSettings) {
        if (cfg.reservationSettings.available_slots) availableSlotsConf = cfg.reservationSettings.available_slots;
        if (cfg.reservationSettings.holidays) {
            let h_data = cfg.reservationSettings.holidays;
            if (Array.isArray(h_data)) {
                h_data.forEach(d => holidaysConf[d] = '임시휴일');
            } else {
                holidaysConf = h_data;
            }
        }
    }

    for(let i=1; i<=lastDate; i++) {
        const d = new Date(year, month, i);
        const dateStr = `${year}-${String(month+1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const dayOfWeek = d.getDay();
        const dayName = dayNames[dayOfWeek];
        
        let isPast = d < today;
        let isSelected = (dateStr === selectedDate);
        
        let slotsForDay = availableSlotsConf[dayName] || [];
        let isHoliday = (holidaysConf[dateStr] !== undefined);
        let holidayMemo = isHoliday ? holidaysConf[dateStr] : '';
        let isClosedDay = (!isPast && (slotsForDay.length === 0 || isHoliday));

        let textColor = dayOfWeek === 0 ? 'text-danger' : (dayOfWeek === 6 ? 'text-primary' : 'text-dark');
        let btnClass = isPast ? 'text-muted opacity-25' : `${textColor} fw-bold`;
        if (isClosedDay) btnClass = 'text-muted opacity-50';

        let bgStyle = isSelected ? 'bg-dark text-white shadow-sm' : (isClosedDay ? 'bg-light' : 'bg-white');
        if (isSelected) btnClass = 'fw-bold text-white';
        
        let cursor = (isPast || isClosedDay) ? 'not-allowed' : 'pointer';
        let clickEvent = (isPast || isClosedDay) ? '' : `onclick="window.selectDate('${type}', '${dateStr}')"`;
        
        let hasMyRes = (myRes[dateStr] && myRes[dateStr].length > 0);
        let myResBadge = hasMyRes ? `<div class="position-absolute bottom-0 start-50 translate-middle-x mb-1" style="width: 4px; height: 4px; background-color: #0d6efd; border-radius: 50%;"></div>` : '';
        
        let closedText = isClosedDay ? `<div class="text-danger fw-bold w-100 text-center text-truncate px-1" style="font-size: 0.65rem; position: absolute; bottom: -2px; left: 0;">휴무</div>` : '';
        if (isHoliday && !isPast) closedText = `<div class="text-danger fw-bold w-100 text-center text-truncate px-1" style="font-size: 0.65rem; position: absolute; bottom: -2px; left: 0;">${holidayMemo || '임시휴일'}</div>`;

        html += `
            <div style="width: 14.28%; padding: 2px; padding-bottom: 16px; position: relative;">
                <div class="position-relative mx-auto" style="width: 32px; height: 32px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center w-100 h-100 ${btnClass} ${bgStyle}" 
                         style="cursor: ${cursor}; font-size: 0.9rem;"
                         ${clickEvent}>
                         ${i}
                    </div>
                    ${myResBadge}
                </div>
                ${closedText}
            </div>
        `;
    }
    html += `</div>`;
    container.innerHTML = html;
}

window.selectDate = function(type, dateStr) {
    const input = document.getElementById(type + '_reservation_date');
    if (input) {
        input.value = dateStr;
        const parts = dateStr.split('-');
        window.renderCalendar(type, new Date(parts[0], parts[1]-1, 1));
        window.updateAvailableTimesGrid(type);
    }
}

window.updateAvailableTimesGrid = function(type) {
    const cfg = getShopConfig();
    const dateInput = document.getElementById(type + '_reservation_date');
    const timeInput = document.getElementById(type + '_reservation_time');
    const timeContainer = document.getElementById(type + '_time_container');
    if(timeInput) timeInput.value = '';
    
    if (!dateInput || !dateInput.value) {
        timeContainer.innerHTML = `<div class="text-muted small w-100 text-center py-2 bg-light rounded-3 border">날짜를 먼저 선택하세요.</div>`;
        return;
    }
    
    const date = new Date(dateInput.value);
    const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    const dayName = days[date.getDay()];
    
    const now = new Date();
    const todayDateStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const isToday = (dateInput.value === todayDateStr);
    const currentTimeVal = now.getHours() * 60 + now.getMinutes();

    let availableSlots = [];
    let holidaysConf = {};
    if (cfg && cfg.reservationSettings) {
        if (cfg.reservationSettings.available_slots) availableSlots = cfg.reservationSettings.available_slots[dayName] || [];
        if (cfg.reservationSettings.holidays) {
            let h_data = cfg.reservationSettings.holidays;
            if (Array.isArray(h_data)) {
                h_data.forEach(d => holidaysConf[d] = '임시휴일');
            } else {
                holidaysConf = h_data;
            }
        }
    }
    
    const maxConcurrent = (cfg && cfg.reservationSettings && cfg.reservationSettings.max_concurrent) ? parseInt(cfg.reservationSettings.max_concurrent) : 1;
    const bookedSlots = (cfg && cfg.bookedSlots) ? cfg.bookedSlots : {};
    const bookedForDate = bookedSlots[dateInput.value] || {};
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};
    const myResForDate = myRes[dateInput.value] || [];

    let isHoliday = (holidaysConf[dateInput.value] !== undefined);
    let holidayMemo = isHoliday ? holidaysConf[dateInput.value] : '';

    if (isHoliday) {
        timeContainer.innerHTML = `<div class="text-danger small w-100 text-center py-2 bg-light rounded-3 border fw-bold"><i class="bi bi-calendar-x me-1"></i>${holidayMemo || '해당 날짜는 상점 임시 휴일입니다.'}</div>`;
    } else if (availableSlots.length > 0) {
        let html = '';
        availableSlots.forEach(slot => {
            let isMyResTime = myResForDate.some(r => r.time === slot);
            let myResIcon = isMyResTime ? ' <i class="bi bi-check-circle-fill text-primary ms-1" title="나의 예약"></i>' : '';
            
            let bookedCount = bookedForDate[slot] || 0;
            let remainCount = maxConcurrent - bookedCount;
            if (remainCount < 0) remainCount = 0;
            
            let slotTimeVal = 0;
            const timeParts = slot.split(':');
            if (timeParts.length === 2) {
                slotTimeVal = parseInt(timeParts[0]) * 60 + parseInt(timeParts[1]);
            }
            let isPast = (isToday && slotTimeVal <= currentTimeVal);

            let btnClass = 'btn-outline-dark';
            let disabledAttr = '';
            let remainHtml = `<div style="font-size: 0.65rem; opacity: 0.8; margin-top: -2px;">남은자리 ${remainCount}</div>`;
            
            if (isPast) {
                btnClass = 'btn-outline-secondary opacity-50 bg-light';
                disabledAttr = 'disabled';
                remainHtml = `<div class="text-secondary fw-bold" style="font-size: 0.65rem; margin-top: -2px;"><i class="bi bi-clock-history me-1"></i>시간지남</div>`;
            } else if (remainCount === 0) {
                btnClass = 'btn-outline-secondary opacity-50 bg-light';
                disabledAttr = 'disabled';
                remainHtml = `<div class="text-danger fw-bold" style="font-size: 0.65rem; margin-top: -2px;">예약마감</div>`;
            }

            html += `
                <button type="button" class="btn ${btnClass} btn-sm flex-fill fw-bold rounded-3 time-btn-${type} d-flex flex-column align-items-center py-1" 
                        onclick="window.selectTime('${type}', '${slot}', this)" style="min-width: 75px;" ${disabledAttr}>
                    <div>${slot}${myResIcon}</div>
                    ${remainHtml}
                </button>
            `;
        });
        timeContainer.innerHTML = html;
    } else {
        timeContainer.innerHTML = `<div class="text-danger small w-100 text-center py-2 bg-light rounded-3 border fw-bold"><i class="bi bi-exclamation-circle me-1"></i>예약 가능한 시간이 없습니다.</div>`;
    }

    const detailsContainer = document.getElementById(type + '_my_reservation_details');
    if (detailsContainer) {
        if (myResForDate.length > 0) {
            let detailsHtml = '<div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 p-2 rounded-3 small mb-0 mt-2">';
            detailsHtml += '<div class="fw-bold text-primary mb-1"><i class="bi bi-bookmark-check me-1"></i>나의 예약 내역 (' + dateInput.value + ')</div>';
            detailsHtml += '<ul class="mb-0 ps-3 text-dark">';
            myResForDate.forEach(res => {
                detailsHtml += `<li><strong>${res.time}</strong> : ${res.items}</li>`;
            });
            detailsHtml += '</ul></div>';
            detailsContainer.innerHTML = detailsHtml;
        } else {
            detailsContainer.innerHTML = '';
        }
    }
}

window.selectTime = function(type, slot, btnEl) {
    const input = document.getElementById(type + '_reservation_time');
    if(input) input.value = slot;
    const btns = document.querySelectorAll('.time-btn-' + type);
    btns.forEach(b => {
        if (!b.hasAttribute('disabled')) {
            b.classList.remove('btn-dark', 'text-white');
            b.classList.add('btn-outline-dark');
        }
    });
    if (!btnEl.hasAttribute('disabled')) {
        btnEl.classList.remove('btn-outline-dark');
        btnEl.classList.add('btn-dark', 'text-white');
    }
}

// [브릿지 스크립트 분리] 
window.triggerServiceDetailModal = function(itemId) {
    const cfg = getShopConfig();
    if (!cfg || !cfg.allItemsData) return;
    
    let item = cfg.allItemsData.find(i => parseInt(i.id) === parseInt(itemId));
    if (item) {
        let renderItem = JSON.parse(JSON.stringify(item));
        const cardEl = document.querySelector(`.menu-item-card[onclick*="triggerServiceDetailModal(${itemId})"]`);
        if (cardEl) {
            const nameEl = cardEl.querySelector('.menu-item-name');
            const infoEl = cardEl.querySelector('.menu-item-info');
            if (nameEl) renderItem.item_name = nameEl.innerText;
            if (infoEl) renderItem.item_info = infoEl.innerHTML.replace(/<br\s*\/?>/gi, '\n');
        }
        window.openMenuDetailModal(renderItem);
    } else {
        alert('해당 서비스 상세 정보를 찾을 수 없습니다.');
    }
}

window.openMenuDetailModal = function(item) {
    const cfg = getShopConfig();
    const modalEl = document.getElementById('menuDetailModal');
    if (!modalEl || !cfg) return;

    document.getElementById('detail-menu-name').innerText = item.item_name || '';
    document.getElementById('detail-menu-info').innerText = item.item_info || '';

    const finalPrice = document.getElementById('detail-final-price');
    const origPrice = document.getElementById('detail-original-price');
    const price = parseInt(item.item_price) || 0;
    const discountRate = parseInt(item.item_discount_rate) || 0;
    const discountPrice = parseInt(item.item_discount_price) || 0;

    if (discountRate > 0) {
        finalPrice.innerText = cfg.currencySymbol + ' ' + discountPrice.toLocaleString();
        origPrice.innerText = cfg.currencySymbol + ' ' + price.toLocaleString();
        origPrice.classList.remove('d-none');
    } else {
        finalPrice.innerText = cfg.currencySymbol + ' ' + price.toLocaleString();
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
        const savedPhone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    const btnWishlist = document.getElementById('btn-wishlist');
    if (btnWishlist) {
        // 모달 열 때 로컬 장바구니에 담겨있는데 카운트가 0인 경우 최소 1로 보정
        const isWishedLocally = wishlist.some(w => parseInt(w.id) === parseInt(item.id));
        if (isWishedLocally && (parseInt(item.wish_count) || 0) === 0) {
            item.wish_count = 1;
        }
        btnWishlist.setAttribute('data-item', JSON.stringify(item));
    }
    window.updateWishlistButton(item.id);

    if(typeof showBsModal === 'function') showBsModal('menuDetailModal');
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

    // [추가] 각 서비스 카드의 우측 상단 관심(하트) 뱃지 UI 동기화
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
    const cfg = getShopConfig();
    const btn = btnEl || document.getElementById('btn-wishlist');
    if (!btn || !cfg) return;

    const itemStr = btn.getAttribute('data-item');
    if (!itemStr) return;
    const item = JSON.parse(itemStr);

    const existingIndex = wishlist.findIndex(w => parseInt(w.id) === parseInt(item.id));
    let actionType = '';

    if (existingIndex > -1) {
        wishlist.splice(existingIndex, 1);
        // 해제 시 카운트 1 차감 (0 이하 방어)
        item.wish_count = Math.max(0, (parseInt(item.wish_count) || 0) - 1);
        actionType = 'remove';
        if (typeof showToast === 'function') showToast('관심 서비스에서 삭제되었습니다.', 'info');
    } else {
        wishlist.push(item);
        // 등록 시 카운트 1 증가
        item.wish_count = (parseInt(item.wish_count) || 0) + 1;
        actionType = 'add';
        if (typeof showToast === 'function') showToast('관심 서비스에 담겼습니다.', 'success');
    }

    // 변경된 카운트를 속성에 다시 저장하여 UI 업데이트에 반영
    btn.setAttribute('data-item', JSON.stringify(item));

    localStorage.setItem('wishlist_' + cfg.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.updateWishlistButton(item.id);

    // [핵심] 서버(DB)와 통신하여 전체 찜 횟수를 동기화 (SRV 전용 백엔드)
    try {
        const formData = new FormData();
        formData.append('action', actionType);
        formData.append('shop_id', cfg.shopId);
        formData.append('item_id', item.id);

        fetch('/shops/srv/shop_srv_wishlist_handler.php', {
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

    // 버튼에 저장된 최신 item 정보에서 카운트 가져오기
    const itemStr = btn.getAttribute('data-item');
    let currentCount = 0;
    if (itemStr) {
        const item = JSON.parse(itemStr);
        currentCount = parseInt(item.wish_count) || 0;
    }

    // 0이면 d-none 클래스로 숫자를 숨기고, 1 이상이면 노출
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


window.showCartViewModal = function() {
    const cfg = getShopConfig();
    const listContainer = document.getElementById('cart-view-items-list');
    if(!listContainer || !cfg) return;
    
    let html = '';
    let total = 0;

    if (wishlist.length === 0) {
        html = '<div class="text-center text-muted py-5">관심목록이 비어있습니다.</div>';
    } else {
        wishlist.forEach((item, index) => {
            const price = parseInt(item.item_discount_rate) > 0 ? parseInt(item.item_discount_price) : parseInt(item.item_price);
            total += price;
            let thumb = '/assets/no-logo.png';
            if (item.item_img) {
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
                        <h6 class="fw-bold mb-1 text-truncate" style="font-size: 0.95rem;">${item.item_name}</h6>
                        <div class="text-primary fw-bold small">${cfg.currencySymbol} ${price.toLocaleString()}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 ms-2" onclick="window.removeFromWishlist(${index})"><i class="bi bi-x-lg"></i></button>
                </div>
            `;
        });
    }

    listContainer.innerHTML = html;
    const subtotalEl = document.getElementById('cart-view-subtotal');
    if (subtotalEl) subtotalEl.innerText = cfg.currencySymbol + ' ' + total.toLocaleString();
    const totalPriceEl = document.getElementById('cart-view-total-price');
    if (totalPriceEl) totalPriceEl.innerText = cfg.currencySymbol + ' ' + total.toLocaleString();

    const phoneInput = document.getElementById('customer_phone');
    if (phoneInput && !phoneInput.value.trim()) {
        const savedPhone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    if(typeof showBsModal === 'function') showBsModal('cartViewModal');
}

window.removeFromWishlist = function(index) {
    const cfg = getShopConfig();
    wishlist.splice(index, 1);
    if(cfg) localStorage.setItem('wishlist_' + cfg.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.showCartViewModal();
}

window.confirmDeleteInquiry = function(inquiryId) {
    inquiryToDelete = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('deleteOrderConfirmModal');
}

window.executeDeleteInquiry = async function() {
    const cfg = getShopConfig();
    if (!inquiryToDelete || !cfg) return;

    let phone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('shop_id', cfg.shopId);
    formData.append('inquiry_id', inquiryToDelete);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('deleteOrderConfirmModal');

            if (typeof showToast === 'function') showToast('삭제되었습니다.', 'success');
            window.fetchServiceInquiryHistory(phone); 

            const badge = document.getElementById('order-count-badge');
            if (badge && parseInt(badge.innerText) > 0) badge.innerText = parseInt(badge.innerText) - 1;
            const modalBadge = document.getElementById('modal-inquiry-count-badge');
            if (modalBadge && parseInt(modalBadge.innerText) > 0) modalBadge.innerText = parseInt(modalBadge.innerText) - 1;
        } else {
            alert(result.message || '삭제에 실패했습니다.');
        }
    } catch (e) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        inquiryToDelete = null;
    }
}

window.confirmCancelInquiry = function(inquiryId) {
    inquiryToCancel = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('cancelInquiryConfirmModal');
}

window.executeCancelInquiry = async function() {
    const cfg = getShopConfig();
    if (!inquiryToCancel || !cfg) return;

    let phone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('shop_id', cfg.shopId);
    formData.append('inquiry_id', inquiryToCancel);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('cancelInquiryConfirmModal');

            if (typeof showToast === 'function') showToast('취소되었습니다.', 'success');
            window.fetchServiceInquiryHistory(phone); 
        } else {
            alert(result.message || '취소에 실패했습니다.');
        }
    } catch (e) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        inquiryToCancel = null;
    }
}

window.fetchServiceInquiryHistory = async function(phoneRaw) {
    const cfg = getShopConfig();
    if(!cfg) return;
    const phone = phoneRaw.replace(/\D/g, '');
    if (!phone) return;

    if(typeof showBsModal === 'function') showBsModal('serviceInquiryHistoryModal');

    const historyForm = document.getElementById('service-non-member-history-form');
    const infoForm = document.getElementById('service-member-history-info');
    if (historyForm) historyForm.style.display = 'none';
    if (infoForm) infoForm.style.display = 'block';

    const phoneDisplay = document.getElementById('service-history-phone-display');
    if (phoneDisplay) phoneDisplay.innerHTML = '<i class="bi bi-telephone text-primary me-2"></i>' + phoneRaw;

    const resultsContainer = document.getElementById('service-history-results');
    if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

    try {
        const formData = new FormData();
        formData.append('shop_id', cfg.shopId);
        formData.append('phone', phone);
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const data = await response.text();
        if (resultsContainer) resultsContainer.innerHTML = data;
    } catch (e) {
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>';
    }
}

window.submitServiceHistorySearch = function() {
    const cfg = getShopConfig();
    const phoneInput = document.getElementById('service_history_search_phone');
    if (phoneInput && phoneInput.value.trim() !== '') {
        const phoneRaw = phoneInput.value;
        const phone = phoneRaw.replace(/\D/g, '');
        if (!phone) return;

        localStorage.setItem('srv_last_search_phone', phoneRaw);

        const resultsContainer = document.getElementById('service-history-results');
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

        const formData = new FormData();
        formData.append('shop_id', cfg.shopId);
        formData.append('phone', phone);

        fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => { if (resultsContainer) resultsContainer.innerHTML = data; })
            .catch(e => { if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>'; });
    }
};

window.continueWithoutLogin = function() {
    window.hasConfirmedLoginChoice = true;
    if(typeof hideBsModal === 'function') hideBsModal('loginChoiceModal');

    if (pendingSrvAction === 'submitOrder') {
        setTimeout(() => { window.submitOrder(); }, 300);
    } else if (pendingSrvAction === 'submitSingleInquiry') {
        setTimeout(() => { window.submitSingleInquiry(); }, 300);
    } else if (pendingSrvAction === 'viewHistory') {
        const historyForm = document.getElementById('service-non-member-history-form');
        const infoForm = document.getElementById('service-member-history-info');

        if (historyForm) historyForm.style.display = 'block';
        if (infoForm) infoForm.style.display = 'none';

        const phoneInput = document.getElementById('service_history_search_phone');
        if (phoneInput) {
            const savedPhone = localStorage.getItem('srv_last_search_phone');
            phoneInput.value = savedPhone ? savedPhone : '';
        }

        const resultsContainer = document.getElementById('service-history-results');
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted">전화번호를 입력하고 조회 버튼을 눌러주세요.</div>';

        if(typeof showBsModal === 'function') showBsModal('serviceInquiryHistoryModal');
        pendingSrvAction = null;
    }
};

window.openServiceInquiryHistoryModal = function() {
    const cfg = getShopConfig();
    if(!cfg) return;

    if (!cfg.isCustomerLoggedIn) {
        pendingSrvAction = 'viewHistory';
        sessionStorage.setItem('postLoginAction', 'srv_history');
        if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
    } else {
        if (!cfg.customerPhone) {
            window.loginChoiceContext = 'srv_history';
            if(typeof showBsModal === 'function') showBsModal('phInfoModal');
        } else {
            window.fetchServiceInquiryHistory(cfg.customerPhone);
        }
    }
};

window.autoSubmitServiceCartInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_srv_phone');
    const savedInquiry = sessionStorage.getItem('temp_srv_inquiry');
    if (savedPhone !== null) {
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) phoneInput.value = savedPhone;
    }
    if (savedInquiry !== null) {
        const inquiryInput = document.getElementById('customer_inquiry');
        if (inquiryInput) inquiryInput.value = savedInquiry;
    }
    sessionStorage.removeItem('temp_srv_phone');
    sessionStorage.removeItem('temp_srv_inquiry');
    window.hasConfirmedLoginChoice = true;
    window.submitOrder();
};

window.autoSubmitServiceSingleInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_srv_single_phone');
    const savedInquiry = sessionStorage.getItem('temp_srv_single_inquiry');
    const savedItem = sessionStorage.getItem('temp_srv_single_item');

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

    sessionStorage.removeItem('temp_srv_single_phone');
    sessionStorage.removeItem('temp_srv_single_inquiry');
    sessionStorage.removeItem('temp_srv_single_item');

    window.hasConfirmedLoginChoice = true;
    window.submitSingleInquiry();
};

window.submitOrder = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('orderForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const cfg = getShopConfig();
    if (!cfg) return;

    const phoneInput = document.getElementById('customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, ''); 
    const inquiryInput = document.getElementById('customer_inquiry');

    const resDate = document.getElementById('cart_reservation_date').value;
    const resTime = document.getElementById('cart_reservation_time').value;

    if (wishlist.length === 0) return alert('선택된 서비스가 없습니다.');
    if (!resDate || !resTime) return alert('예약 희망일과 시간을 선택해주세요.');

    if (!cfg.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingSrvAction = 'submitOrder';
        sessionStorage.setItem('postLoginAction', 'srv_cart_inquiry_auto_submit'); 

        sessionStorage.setItem('temp_srv_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_srv_inquiry', inquiryInput ? inquiryInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');

        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
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

    const inquiryText = inquiryInput ? inquiryInput.value.trim() : '';
    const finalInquiry = `[예약 희망: ${resDate} ${resTime}]\n${inquiryText}`;

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', finalInquiry);
    formData.append('inquiry_data', JSON.stringify(cartData)); 
    formData.append('reservation_date', resDate);
    formData.append('reservation_time', resTime);

    const submitBtn = document.querySelector('#cartViewModal .btn-primary');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (cfg.myReservations) {
                if (!cfg.myReservations[resDate]) cfg.myReservations[resDate] = [];
                const itemNames = wishlist.map(i => i.item_name).join(', ');
                cfg.myReservations[resDate].push({ time: resTime, items: itemNames || '서비스' });
                
                if (!cfg.bookedSlots[resDate]) cfg.bookedSlots[resDate] = {};
                if (!cfg.bookedSlots[resDate][resTime]) cfg.bookedSlots[resDate][resTime] = 0;
                cfg.bookedSlots[resDate][resTime]++;
            }

            if (phoneInput) localStorage.setItem('srv_last_search_phone', phoneInput.value);

            wishlist = [];
            localStorage.removeItem('wishlist_' + cfg.shopId);
            window.updateWishlistUI();

            if (inquiryInput) inquiryInput.value = '';

            if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');

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
};

window.submitSingleInquiry = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('singleInquiryForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const cfg = getShopConfig();
    if(!cfg) return;

    const phoneInput = document.getElementById('single_customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, '');
    const inquiryInput = document.getElementById('single_customer_inquiry');
    const itemDataInput = document.getElementById('single_inquiry_item_data');

    const resDate = document.getElementById('single_reservation_date').value;
    const resTime = document.getElementById('single_reservation_time').value;

    if (!itemDataInput || !itemDataInput.value) return;
    if (!resDate || !resTime) return alert('예약 희망일과 시간을 선택해주세요.');
    const item = JSON.parse(itemDataInput.value);

    if (!cfg.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingSrvAction = 'submitSingleInquiry';
        sessionStorage.setItem('postLoginAction', 'srv_single_inquiry_auto_submit'); 

        sessionStorage.setItem('temp_srv_single_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_srv_single_inquiry', inquiryInput ? inquiryInput.value : '');
        sessionStorage.setItem('temp_srv_single_item', itemDataInput ? itemDataInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');

        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
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

    const inquiryText = inquiryInput ? inquiryInput.value.trim() : '';
    const finalInquiry = `[예약 희망: ${resDate} ${resTime}]\n${inquiryText}`;

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', finalInquiry);
    formData.append('inquiry_data', JSON.stringify(cartData));
    formData.append('reservation_date', resDate);
    formData.append('reservation_time', resTime);

    const submitBtn = document.querySelector('#singleInquiryForm .btn-dark');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (cfg.myReservations) {
                if (!cfg.myReservations[resDate]) cfg.myReservations[resDate] = [];
                cfg.myReservations[resDate].push({ time: resTime, items: item.item_name || '서비스' });
                
                if (!cfg.bookedSlots[resDate]) cfg.bookedSlots[resDate] = {};
                if (!cfg.bookedSlots[resDate][resTime]) cfg.bookedSlots[resDate][resTime] = 0;
                cfg.bookedSlots[resDate][resTime]++;
            }

            if (phoneInput) localStorage.setItem('srv_last_search_phone', phoneInput.value);

            inquiryInput.value = ''; 

            if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');

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
=======
/**
 * [SRV 카테고리 전용 자바스크립트 엔진]
 * 위치: /shops/srv/assets/srv_cart.js
 * 설명: 서비스/예약 상점의 프론트엔드 비즈니스 로직(장바구니, 모달, 폴링, 달력 렌더링 등)을 단일 파일로 캡슐화
 */

let isOrderStatusPanelOpen = false;
let knownOrderStatuses = {};
let isFirstPoll = true;
const srvNotifySoundUrl = typeof NOTIFICATION_SOUND !== 'undefined' ? NOTIFICATION_SOUND : '/assets/sounds/dingdongg.mp3';
const notifySound = new Audio(srvNotifySoundUrl);

let wishlist = [];
let inquiryToDelete = null;
let inquiryToCancel = null;
let pendingSrvAction = null;
window.hasConfirmedLoginChoice = false;

// 모바일 환경 페이지 로딩 후 점프 방지 
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
sessionStorage.removeItem('pageScrollPos');
if (window.location.hash) {
    history.replaceState(null, null, window.location.pathname + window.location.search);
}
window.scrollTo(0, 0);

document.addEventListener('DOMContentLoaded', () => {
    const cfg = getShopConfig();
    if (cfg) {
        wishlist = JSON.parse(localStorage.getItem('wishlist_' + cfg.shopId)) || [];
        updateWishlistUI();
        pollOrderStatus();
        setInterval(pollOrderStatus, 10000);
    }

    // 서비스 상세 모달 이벤트 바인딩
    const detailModal = document.getElementById('menuDetailModal');
    if (detailModal) {
        detailModal.addEventListener('hidden.bs.modal', function() {
            const videoContainer = document.getElementById('menu-detail-video');
            const photoContainer = document.getElementById('menu-detail-photo');
            if (videoContainer) videoContainer.innerHTML = '';
            if (photoContainer) photoContainer.innerHTML = '';
        });
        detailModal.addEventListener('shown.bs.modal', function() {
            if (!document.getElementById('single_reservation_date').value) {
                window.renderCalendar('single');
                window.updateAvailableTimesGrid('single');
            }
            window.checkMyReservationsNotice('single');
        });
    }

    // 카트 모달 이벤트 바인딩
    const cartModal = document.getElementById('cartViewModal');
    if (cartModal) {
        cartModal.addEventListener('shown.bs.modal', function() {
            if (!document.getElementById('cart_reservation_date').value) {
                window.renderCalendar('cart');
                window.updateAvailableTimesGrid('cart');
            }
            window.checkMyReservationsNotice('cart');
        });
    }

    const successModalEl = document.getElementById('inquirySuccessModal');
    if (successModalEl) {
        successModalEl.addEventListener('hidden.bs.modal', function () {
            location.reload();
        });
    }

    // 카테고리 스크롤 드래그 설정
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

window.toggleOrderStatusPanel = function() {
    const panel = document.getElementById('floating-order-status-panel');
    const badge = document.getElementById('floating-order-status-badge');
    if (!panel) return;
    
    isOrderStatusPanelOpen = !isOrderStatusPanelOpen;
    if (isOrderStatusPanelOpen) {
        panel.classList.remove('d-none');
        if (badge) badge.style.display = 'none';
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

window.pollOrderStatus = async function() {
    const cfg = getShopConfig();
    let phone = '';
    if (cfg && cfg.customerPhone) {
        phone = cfg.customerPhone.replace(/\D/g, '');
    } else {
        const guestPhone = localStorage.getItem('srv_last_search_phone');
        if (guestPhone) phone = guestPhone.replace(/\D/g, '');
    }
    if (!phone) return;

    const fd = new FormData(); fd.append('action', 'poll'); fd.append('shop_id', cfg.shopId); fd.append('phone', phone);
    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: fd });
        const result = await response.json();
        if (result.status === 'success' && result.orders) {
            let hasOrders = result.orders.length > 0;
            result.orders.forEach(order => {
                const currentStatus = order.status;
                if (!isFirstPoll && knownOrderStatuses[order.id] && knownOrderStatuses[order.id] !== currentStatus) {
                    const sInfo = (cfg.orderStatusMap && cfg.orderStatusMap[currentStatus]) ? Object.assign({}, cfg.orderStatusMap[currentStatus]) : { text: currentStatus, class: 'primary' };
                    
                    const toastEl = document.getElementById('orderStatusToast');
                    if(toastEl) {
                        document.getElementById('orderStatusToastBody').innerHTML = `🔔 고객님의 예약이 <span class="badge bg-white text-${sInfo.class} ms-1">${sInfo.text}</span> 상태가 되었습니다!`;
                        toastEl.className = `toast align-items-center text-white bg-${sInfo.class} border-0 w-100 shadow-lg rounded-4`;
                        if (typeof bootstrap !== 'undefined') new bootstrap.Toast(toastEl, { autohide: false }).show();
                    }
                    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                    notifySound.play().catch(e => {});
                    if (document.getElementById('serviceInquiryHistoryModal') && document.getElementById('serviceInquiryHistoryModal').classList.contains('show')) window.fetchServiceInquiryHistory(phone);

                    if (!isOrderStatusPanelOpen) {
                        const badge = document.getElementById('floating-order-status-badge');
                        if (badge) badge.style.display = 'block';
                    }
                }
                knownOrderStatuses[order.id] = currentStatus;
            });
            
            const activeOrders = result.orders.filter(o => ['pending', 'confirmed', 'contacted'].includes(o.status));
            const hasActiveOrders = activeOrders.length > 0;
            
            const btn = document.getElementById('floating-order-status-btn');
            if (btn) {
                if (hasActiveOrders) {
                    btn.classList.remove('d-none');
                    const iconEl = document.getElementById('floating-order-status-icon');
                    if (iconEl) {
                        const currentOrder = activeOrders[0];
                        const currentStatus = currentOrder.status;
                        let iconClass = 'bi-calendar-check';
                        if (currentStatus === 'pending') iconClass = 'bi-check2-circle'; 
                        iconEl.className = `bi ${iconClass} fs-3 text-dark`;
                    }
                } else {
                    btn.classList.add('d-none');
                }
            }
            
            const contentPanel = document.getElementById('floating-order-status-content');
            if (contentPanel) {
                if (!hasActiveOrders) {
                    contentPanel.innerHTML = '<div class="text-center py-4 text-muted small">진행 중인 예약 내역이 없습니다.</div>';
                    if (isOrderStatusPanelOpen) window.toggleOrderStatusPanel();
                } else {
                    contentPanel.innerHTML = activeOrders.map(order => {
                        let statusObj = (cfg.orderStatusMap) ? Object.assign({}, cfg.orderStatusMap[order.status] || { text: order.status, class: 'secondary' }) : { text: order.status, class: 'secondary' };
                        let iconClass = 'bi-info-circle';
                        if (order.status === 'pending') iconClass = 'bi-clock';
                        else if (order.status === 'confirmed' || order.status === 'contacted') iconClass = 'bi-calendar-check';
                        else if (order.status === 'completed') iconClass = 'bi-check-circle';
                        else if (order.status === 'cancelled') iconClass = 'bi-x-circle';

                        let itemsHtml = '';
                        if (order.items) {
                            try {
                                let itemsArray = JSON.parse(order.items);
                                if (itemsArray.length > 0) {
                                    let summary = itemsArray.map(item => `${item.name} <span class="fw-bold text-dark">x1</span>`).join(', ');
                                    itemsHtml = `<div class="text-muted mt-2 text-break" style="font-size:0.75rem;"><i class="bi bi-cart2 me-1 text-primary"></i>${summary}</div>`;
                                }
                            } catch (e) {}
                        }

                        let timeStr = order.created_at;
                        try {
                            let d = new Date(order.created_at.replace(/-/g, '/')); 
                            if (!isNaN(d)) timeStr = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
                        } catch (e) {}

                        let replyHtml = '';
                        if (order.owner_reply && order.owner_reply.trim() !== '') {
                            let escapedReply = order.owner_reply.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                            replyHtml = `<div class="mt-2 p-2 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-2 small text-dark"><div class="fw-bold text-primary mb-1" style="font-size: 0.7rem;"><i class="bi bi-reply-fill me-1"></i>상점의 답변</div><div style="font-size: 0.75rem;">${escapedReply}</div></div>`;
                        }

                        return `<div class="card border-0 shadow-sm mb-3 rounded-3"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center mb-2"><span class="badge bg-${statusObj.class} px-2 py-1 shadow-sm"><i class="bi ${iconClass} me-1"></i>${statusObj.text}</span></div><div class="d-flex justify-content-between align-items-center mb-1"><span class="fw-bold text-dark small"><i class="bi bi-receipt me-1"></i>${order.order_no}</span><span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i>${timeStr}</span></div><div class="bg-light p-2 rounded-2 mt-2 border">${itemsHtml}</div>${replyHtml}</div></div>`;
                    }).join('');
                }
            }
            isFirstPoll = false;
        }
    } catch (err) {}
};

// ==========================================
// [달력 렌더링 로직 통합]
// ==========================================
window.checkMyReservationsNotice = function(type) {
    const cfg = getShopConfig();
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};
    const noticeEl = document.getElementById(type + '_my_reservation_notice');
    if (noticeEl) {
        if (Object.keys(myRes).length > 0) noticeEl.classList.remove('d-none');
        else noticeEl.classList.add('d-none');
    }
}

window.renderCalendar = function(type, initDate = new Date()) {
    const cfg = getShopConfig();
    const container = document.getElementById(type + '_calendar_container');
    if (!container) return;
    
    const year = initDate.getFullYear();
    const month = initDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    today.setHours(0,0,0,0);
    const days = ['일', '월', '화', '수', '목', '금', '토']; // 프론트엔드 기본값
    const dayNames = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-2 px-2">
            <button type="button" class="btn btn-sm btn-white border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:30px; height:30px;" onclick="window.renderCalendar('${type}', new Date(${year}, ${month-1}, 1))"><i class="bi bi-chevron-left text-dark"></i></button>
            <div class="fw-bold fs-6">${year}. ${String(month + 1).padStart(2, '0')}</div>
            <button type="button" class="btn btn-sm btn-white border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:30px; height:30px;" onclick="window.renderCalendar('${type}', new Date(${year}, ${month+1}, 1))"><i class="bi bi-chevron-right text-dark"></i></button>
        </div>
        <div class="d-flex w-100 mb-1">
    `;
    days.forEach((d, idx) => {
        let colorClass = idx === 0 ? 'text-danger' : (idx === 6 ? 'text-primary' : 'text-muted');
        html += `<div class="flex-fill text-center small fw-bold ${colorClass}" style="font-size: 0.8rem;">${d}</div>`;
    });
    html += `</div><div class="d-flex flex-wrap w-100">`;
    for(let i=0; i<firstDay; i++) {
        html += `<div style="width: 14.28%; padding: 2px; padding-bottom: 16px;"></div>`;
    }
    const selectedDateInput = document.getElementById(type + '_reservation_date');
    const selectedDate = selectedDateInput ? selectedDateInput.value : '';
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};

    let availableSlotsConf = {};
    let holidaysConf = {};
    if (cfg && cfg.reservationSettings) {
        if (cfg.reservationSettings.available_slots) availableSlotsConf = cfg.reservationSettings.available_slots;
        if (cfg.reservationSettings.holidays) {
            let h_data = cfg.reservationSettings.holidays;
            if (Array.isArray(h_data)) {
                h_data.forEach(d => holidaysConf[d] = '임시휴일');
            } else {
                holidaysConf = h_data;
            }
        }
    }

    for(let i=1; i<=lastDate; i++) {
        const d = new Date(year, month, i);
        const dateStr = `${year}-${String(month+1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const dayOfWeek = d.getDay();
        const dayName = dayNames[dayOfWeek];
        
        let isPast = d < today;
        let isSelected = (dateStr === selectedDate);
        
        let slotsForDay = availableSlotsConf[dayName] || [];
        let isHoliday = (holidaysConf[dateStr] !== undefined);
        let holidayMemo = isHoliday ? holidaysConf[dateStr] : '';
        let isClosedDay = (!isPast && (slotsForDay.length === 0 || isHoliday));

        let textColor = dayOfWeek === 0 ? 'text-danger' : (dayOfWeek === 6 ? 'text-primary' : 'text-dark');
        let btnClass = isPast ? 'text-muted opacity-25' : `${textColor} fw-bold`;
        if (isClosedDay) btnClass = 'text-muted opacity-50';

        let bgStyle = isSelected ? 'bg-dark text-white shadow-sm' : (isClosedDay ? 'bg-light' : 'bg-white');
        if (isSelected) btnClass = 'fw-bold text-white';
        
        let cursor = (isPast || isClosedDay) ? 'not-allowed' : 'pointer';
        let clickEvent = (isPast || isClosedDay) ? '' : `onclick="window.selectDate('${type}', '${dateStr}')"`;
        
        let hasMyRes = (myRes[dateStr] && myRes[dateStr].length > 0);
        let myResBadge = hasMyRes ? `<div class="position-absolute bottom-0 start-50 translate-middle-x mb-1" style="width: 4px; height: 4px; background-color: #0d6efd; border-radius: 50%;"></div>` : '';
        
        let closedText = isClosedDay ? `<div class="text-danger fw-bold w-100 text-center text-truncate px-1" style="font-size: 0.65rem; position: absolute; bottom: -2px; left: 0;">휴무</div>` : '';
        if (isHoliday && !isPast) closedText = `<div class="text-danger fw-bold w-100 text-center text-truncate px-1" style="font-size: 0.65rem; position: absolute; bottom: -2px; left: 0;">${holidayMemo || '임시휴일'}</div>`;

        html += `
            <div style="width: 14.28%; padding: 2px; padding-bottom: 16px; position: relative;">
                <div class="position-relative mx-auto" style="width: 32px; height: 32px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center w-100 h-100 ${btnClass} ${bgStyle}" 
                         style="cursor: ${cursor}; font-size: 0.9rem;"
                         ${clickEvent}>
                         ${i}
                    </div>
                    ${myResBadge}
                </div>
                ${closedText}
            </div>
        `;
    }
    html += `</div>`;
    container.innerHTML = html;
}

window.selectDate = function(type, dateStr) {
    const input = document.getElementById(type + '_reservation_date');
    if (input) {
        input.value = dateStr;
        const parts = dateStr.split('-');
        window.renderCalendar(type, new Date(parts[0], parts[1]-1, 1));
        window.updateAvailableTimesGrid(type);
    }
}

window.updateAvailableTimesGrid = function(type) {
    const cfg = getShopConfig();
    const dateInput = document.getElementById(type + '_reservation_date');
    const timeInput = document.getElementById(type + '_reservation_time');
    const timeContainer = document.getElementById(type + '_time_container');
    if(timeInput) timeInput.value = '';
    
    if (!dateInput || !dateInput.value) {
        timeContainer.innerHTML = `<div class="text-muted small w-100 text-center py-2 bg-light rounded-3 border">날짜를 먼저 선택하세요.</div>`;
        return;
    }
    
    const date = new Date(dateInput.value);
    const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    const dayName = days[date.getDay()];
    
    const now = new Date();
    const todayDateStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const isToday = (dateInput.value === todayDateStr);
    const currentTimeVal = now.getHours() * 60 + now.getMinutes();

    let availableSlots = [];
    let holidaysConf = {};
    if (cfg && cfg.reservationSettings) {
        if (cfg.reservationSettings.available_slots) availableSlots = cfg.reservationSettings.available_slots[dayName] || [];
        if (cfg.reservationSettings.holidays) {
            let h_data = cfg.reservationSettings.holidays;
            if (Array.isArray(h_data)) {
                h_data.forEach(d => holidaysConf[d] = '임시휴일');
            } else {
                holidaysConf = h_data;
            }
        }
    }
    
    const maxConcurrent = (cfg && cfg.reservationSettings && cfg.reservationSettings.max_concurrent) ? parseInt(cfg.reservationSettings.max_concurrent) : 1;
    const bookedSlots = (cfg && cfg.bookedSlots) ? cfg.bookedSlots : {};
    const bookedForDate = bookedSlots[dateInput.value] || {};
    const myRes = (cfg && cfg.myReservations) ? cfg.myReservations : {};
    const myResForDate = myRes[dateInput.value] || [];

    let isHoliday = (holidaysConf[dateInput.value] !== undefined);
    let holidayMemo = isHoliday ? holidaysConf[dateInput.value] : '';

    if (isHoliday) {
        timeContainer.innerHTML = `<div class="text-danger small w-100 text-center py-2 bg-light rounded-3 border fw-bold"><i class="bi bi-calendar-x me-1"></i>${holidayMemo || '해당 날짜는 상점 임시 휴일입니다.'}</div>`;
    } else if (availableSlots.length > 0) {
        let html = '';
        availableSlots.forEach(slot => {
            let isMyResTime = myResForDate.some(r => r.time === slot);
            let myResIcon = isMyResTime ? ' <i class="bi bi-check-circle-fill text-primary ms-1" title="나의 예약"></i>' : '';
            
            let bookedCount = bookedForDate[slot] || 0;
            let remainCount = maxConcurrent - bookedCount;
            if (remainCount < 0) remainCount = 0;
            
            let slotTimeVal = 0;
            const timeParts = slot.split(':');
            if (timeParts.length === 2) {
                slotTimeVal = parseInt(timeParts[0]) * 60 + parseInt(timeParts[1]);
            }
            let isPast = (isToday && slotTimeVal <= currentTimeVal);

            let btnClass = 'btn-outline-dark';
            let disabledAttr = '';
            let remainHtml = `<div style="font-size: 0.65rem; opacity: 0.8; margin-top: -2px;">남은자리 ${remainCount}</div>`;
            
            if (isPast) {
                btnClass = 'btn-outline-secondary opacity-50 bg-light';
                disabledAttr = 'disabled';
                remainHtml = `<div class="text-secondary fw-bold" style="font-size: 0.65rem; margin-top: -2px;"><i class="bi bi-clock-history me-1"></i>시간지남</div>`;
            } else if (remainCount === 0) {
                btnClass = 'btn-outline-secondary opacity-50 bg-light';
                disabledAttr = 'disabled';
                remainHtml = `<div class="text-danger fw-bold" style="font-size: 0.65rem; margin-top: -2px;">예약마감</div>`;
            }

            html += `
                <button type="button" class="btn ${btnClass} btn-sm flex-fill fw-bold rounded-3 time-btn-${type} d-flex flex-column align-items-center py-1" 
                        onclick="window.selectTime('${type}', '${slot}', this)" style="min-width: 75px;" ${disabledAttr}>
                    <div>${slot}${myResIcon}</div>
                    ${remainHtml}
                </button>
            `;
        });
        timeContainer.innerHTML = html;
    } else {
        timeContainer.innerHTML = `<div class="text-danger small w-100 text-center py-2 bg-light rounded-3 border fw-bold"><i class="bi bi-exclamation-circle me-1"></i>예약 가능한 시간이 없습니다.</div>`;
    }

    const detailsContainer = document.getElementById(type + '_my_reservation_details');
    if (detailsContainer) {
        if (myResForDate.length > 0) {
            let detailsHtml = '<div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 p-2 rounded-3 small mb-0 mt-2">';
            detailsHtml += '<div class="fw-bold text-primary mb-1"><i class="bi bi-bookmark-check me-1"></i>나의 예약 내역 (' + dateInput.value + ')</div>';
            detailsHtml += '<ul class="mb-0 ps-3 text-dark">';
            myResForDate.forEach(res => {
                detailsHtml += `<li><strong>${res.time}</strong> : ${res.items}</li>`;
            });
            detailsHtml += '</ul></div>';
            detailsContainer.innerHTML = detailsHtml;
        } else {
            detailsContainer.innerHTML = '';
        }
    }
}

window.selectTime = function(type, slot, btnEl) {
    const input = document.getElementById(type + '_reservation_time');
    if(input) input.value = slot;
    const btns = document.querySelectorAll('.time-btn-' + type);
    btns.forEach(b => {
        if (!b.hasAttribute('disabled')) {
            b.classList.remove('btn-dark', 'text-white');
            b.classList.add('btn-outline-dark');
        }
    });
    if (!btnEl.hasAttribute('disabled')) {
        btnEl.classList.remove('btn-outline-dark');
        btnEl.classList.add('btn-dark', 'text-white');
    }
}

// [브릿지 스크립트 분리] 
window.triggerServiceDetailModal = function(itemId) {
    const cfg = getShopConfig();
    if (!cfg || !cfg.allItemsData) return;
    
    let item = cfg.allItemsData.find(i => parseInt(i.id) === parseInt(itemId));
    if (item) {
        let renderItem = JSON.parse(JSON.stringify(item));
        const cardEl = document.querySelector(`.menu-item-card[onclick*="triggerServiceDetailModal(${itemId})"]`);
        if (cardEl) {
            const nameEl = cardEl.querySelector('.menu-item-name');
            const infoEl = cardEl.querySelector('.menu-item-info');
            if (nameEl) renderItem.item_name = nameEl.innerText;
            if (infoEl) renderItem.item_info = infoEl.innerHTML.replace(/<br\s*\/?>/gi, '\n');
        }
        window.openMenuDetailModal(renderItem);
    } else {
        alert('해당 서비스 상세 정보를 찾을 수 없습니다.');
    }
}

window.openMenuDetailModal = function(item) {
    const cfg = getShopConfig();
    const modalEl = document.getElementById('menuDetailModal');
    if (!modalEl || !cfg) return;

    document.getElementById('detail-menu-name').innerText = item.item_name || '';
    document.getElementById('detail-menu-info').innerText = item.item_info || '';

    const finalPrice = document.getElementById('detail-final-price');
    const origPrice = document.getElementById('detail-original-price');
    const price = parseInt(item.item_price) || 0;
    const discountRate = parseInt(item.item_discount_rate) || 0;
    const discountPrice = parseInt(item.item_discount_price) || 0;

    if (discountRate > 0) {
        finalPrice.innerText = cfg.currencySymbol + ' ' + discountPrice.toLocaleString();
        origPrice.innerText = cfg.currencySymbol + ' ' + price.toLocaleString();
        origPrice.classList.remove('d-none');
    } else {
        finalPrice.innerText = cfg.currencySymbol + ' ' + price.toLocaleString();
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
        const savedPhone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    const btnWishlist = document.getElementById('btn-wishlist');
    if (btnWishlist) {
        // 모달 열 때 로컬 장바구니에 담겨있는데 카운트가 0인 경우 최소 1로 보정
        const isWishedLocally = wishlist.some(w => parseInt(w.id) === parseInt(item.id));
        if (isWishedLocally && (parseInt(item.wish_count) || 0) === 0) {
            item.wish_count = 1;
        }
        btnWishlist.setAttribute('data-item', JSON.stringify(item));
    }
    window.updateWishlistButton(item.id);

    if(typeof showBsModal === 'function') showBsModal('menuDetailModal');
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

    // [추가] 각 서비스 카드의 우측 상단 관심(하트) 뱃지 UI 동기화
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
    const cfg = getShopConfig();
    const btn = btnEl || document.getElementById('btn-wishlist');
    if (!btn || !cfg) return;

    const itemStr = btn.getAttribute('data-item');
    if (!itemStr) return;
    const item = JSON.parse(itemStr);

    const existingIndex = wishlist.findIndex(w => parseInt(w.id) === parseInt(item.id));
    let actionType = '';

    if (existingIndex > -1) {
        wishlist.splice(existingIndex, 1);
        // 해제 시 카운트 1 차감 (0 이하 방어)
        item.wish_count = Math.max(0, (parseInt(item.wish_count) || 0) - 1);
        actionType = 'remove';
        if (typeof showToast === 'function') showToast('관심 서비스에서 삭제되었습니다.', 'info');
    } else {
        wishlist.push(item);
        // 등록 시 카운트 1 증가
        item.wish_count = (parseInt(item.wish_count) || 0) + 1;
        actionType = 'add';
        if (typeof showToast === 'function') showToast('관심 서비스에 담겼습니다.', 'success');
    }

    // 변경된 카운트를 속성에 다시 저장하여 UI 업데이트에 반영
    btn.setAttribute('data-item', JSON.stringify(item));

    localStorage.setItem('wishlist_' + cfg.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.updateWishlistButton(item.id);

    // [핵심] 서버(DB)와 통신하여 전체 찜 횟수를 동기화 (SRV 전용 백엔드)
    try {
        const formData = new FormData();
        formData.append('action', actionType);
        formData.append('shop_id', cfg.shopId);
        formData.append('item_id', item.id);

        fetch('/shops/srv/shop_srv_wishlist_handler.php', {
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

    // 버튼에 저장된 최신 item 정보에서 카운트 가져오기
    const itemStr = btn.getAttribute('data-item');
    let currentCount = 0;
    if (itemStr) {
        const item = JSON.parse(itemStr);
        currentCount = parseInt(item.wish_count) || 0;
    }

    // 0이면 d-none 클래스로 숫자를 숨기고, 1 이상이면 노출
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


window.showCartViewModal = function() {
    const cfg = getShopConfig();
    const listContainer = document.getElementById('cart-view-items-list');
    if(!listContainer || !cfg) return;
    
    let html = '';
    let total = 0;

    if (wishlist.length === 0) {
        html = '<div class="text-center text-muted py-5">관심목록이 비어있습니다.</div>';
    } else {
        wishlist.forEach((item, index) => {
            const price = parseInt(item.item_discount_rate) > 0 ? parseInt(item.item_discount_price) : parseInt(item.item_price);
            total += price;
            let thumb = '/assets/no-logo.png';
            if (item.item_img) {
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
                        <h6 class="fw-bold mb-1 text-truncate" style="font-size: 0.95rem;">${item.item_name}</h6>
                        <div class="text-primary fw-bold small">${cfg.currencySymbol} ${price.toLocaleString()}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0 ms-2" onclick="window.removeFromWishlist(${index})"><i class="bi bi-x-lg"></i></button>
                </div>
            `;
        });
    }

    listContainer.innerHTML = html;
    const subtotalEl = document.getElementById('cart-view-subtotal');
    if (subtotalEl) subtotalEl.innerText = cfg.currencySymbol + ' ' + total.toLocaleString();
    const totalPriceEl = document.getElementById('cart-view-total-price');
    if (totalPriceEl) totalPriceEl.innerText = cfg.currencySymbol + ' ' + total.toLocaleString();

    const phoneInput = document.getElementById('customer_phone');
    if (phoneInput && !phoneInput.value.trim()) {
        const savedPhone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone');
        if (savedPhone) phoneInput.value = savedPhone;
    }

    if(typeof showBsModal === 'function') showBsModal('cartViewModal');
}

window.removeFromWishlist = function(index) {
    const cfg = getShopConfig();
    wishlist.splice(index, 1);
    if(cfg) localStorage.setItem('wishlist_' + cfg.shopId, JSON.stringify(wishlist));
    window.updateWishlistUI();
    window.showCartViewModal();
}

window.confirmDeleteInquiry = function(inquiryId) {
    inquiryToDelete = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('deleteOrderConfirmModal');
}

window.executeDeleteInquiry = async function() {
    const cfg = getShopConfig();
    if (!inquiryToDelete || !cfg) return;

    let phone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('shop_id', cfg.shopId);
    formData.append('inquiry_id', inquiryToDelete);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('deleteOrderConfirmModal');

            if (typeof showToast === 'function') showToast('삭제되었습니다.', 'success');
            window.fetchServiceInquiryHistory(phone); 

            const badge = document.getElementById('order-count-badge');
            if (badge && parseInt(badge.innerText) > 0) badge.innerText = parseInt(badge.innerText) - 1;
            const modalBadge = document.getElementById('modal-inquiry-count-badge');
            if (modalBadge && parseInt(modalBadge.innerText) > 0) modalBadge.innerText = parseInt(modalBadge.innerText) - 1;
        } else {
            alert(result.message || '삭제에 실패했습니다.');
        }
    } catch (e) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        inquiryToDelete = null;
    }
}

window.confirmCancelInquiry = function(inquiryId) {
    inquiryToCancel = inquiryId;
    if(typeof showBsModal === 'function') showBsModal('cancelInquiryConfirmModal');
}

window.executeCancelInquiry = async function() {
    const cfg = getShopConfig();
    if (!inquiryToCancel || !cfg) return;

    let phone = cfg.customerPhone || localStorage.getItem('srv_last_search_phone') || '';
    phone = phone.replace(/\D/g, '');
    if (!phone) return alert('전화번호 정보를 찾을 수 없습니다.');

    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('shop_id', cfg.shopId);
    formData.append('inquiry_id', inquiryToCancel);
    formData.append('phone', phone);

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            if(typeof hideBsModal === 'function') hideBsModal('cancelInquiryConfirmModal');

            if (typeof showToast === 'function') showToast('취소되었습니다.', 'success');
            window.fetchServiceInquiryHistory(phone); 
        } else {
            alert(result.message || '취소에 실패했습니다.');
        }
    } catch (e) {
        alert('서버 통신 중 오류가 발생했습니다.');
    } finally {
        inquiryToCancel = null;
    }
}

window.fetchServiceInquiryHistory = async function(phoneRaw) {
    const cfg = getShopConfig();
    if(!cfg) return;
    const phone = phoneRaw.replace(/\D/g, '');
    if (!phone) return;

    if(typeof showBsModal === 'function') showBsModal('serviceInquiryHistoryModal');

    const historyForm = document.getElementById('service-non-member-history-form');
    const infoForm = document.getElementById('service-member-history-info');
    if (historyForm) historyForm.style.display = 'none';
    if (infoForm) infoForm.style.display = 'block';

    const phoneDisplay = document.getElementById('service-history-phone-display');
    if (phoneDisplay) phoneDisplay.innerHTML = '<i class="bi bi-telephone text-primary me-2"></i>' + phoneRaw;

    const resultsContainer = document.getElementById('service-history-results');
    if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

    try {
        const formData = new FormData();
        formData.append('shop_id', cfg.shopId);
        formData.append('phone', phone);
        const response = await fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData });
        const data = await response.text();
        if (resultsContainer) resultsContainer.innerHTML = data;
    } catch (e) {
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>';
    }
}

window.submitServiceHistorySearch = function() {
    const cfg = getShopConfig();
    const phoneInput = document.getElementById('service_history_search_phone');
    if (phoneInput && phoneInput.value.trim() !== '') {
        const phoneRaw = phoneInput.value;
        const phone = phoneRaw.replace(/\D/g, '');
        if (!phone) return;

        localStorage.setItem('srv_last_search_phone', phoneRaw);

        const resultsContainer = document.getElementById('service-history-results');
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

        const formData = new FormData();
        formData.append('shop_id', cfg.shopId);
        formData.append('phone', phone);

        fetch('/shops/srv/shop_srv_reservation_history.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(data => { if (resultsContainer) resultsContainer.innerHTML = data; })
            .catch(e => { if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>'; });
    }
};

window.continueWithoutLogin = function() {
    window.hasConfirmedLoginChoice = true;
    if(typeof hideBsModal === 'function') hideBsModal('loginChoiceModal');

    if (pendingSrvAction === 'submitOrder') {
        setTimeout(() => { window.submitOrder(); }, 300);
    } else if (pendingSrvAction === 'submitSingleInquiry') {
        setTimeout(() => { window.submitSingleInquiry(); }, 300);
    } else if (pendingSrvAction === 'viewHistory') {
        const historyForm = document.getElementById('service-non-member-history-form');
        const infoForm = document.getElementById('service-member-history-info');

        if (historyForm) historyForm.style.display = 'block';
        if (infoForm) infoForm.style.display = 'none';

        const phoneInput = document.getElementById('service_history_search_phone');
        if (phoneInput) {
            const savedPhone = localStorage.getItem('srv_last_search_phone');
            phoneInput.value = savedPhone ? savedPhone : '';
        }

        const resultsContainer = document.getElementById('service-history-results');
        if (resultsContainer) resultsContainer.innerHTML = '<div class="text-center py-5 text-muted">전화번호를 입력하고 조회 버튼을 눌러주세요.</div>';

        if(typeof showBsModal === 'function') showBsModal('serviceInquiryHistoryModal');
        pendingSrvAction = null;
    }
};

window.openServiceInquiryHistoryModal = function() {
    const cfg = getShopConfig();
    if(!cfg) return;

    if (!cfg.isCustomerLoggedIn) {
        pendingSrvAction = 'viewHistory';
        sessionStorage.setItem('postLoginAction', 'srv_history');
        if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
    } else {
        if (!cfg.customerPhone) {
            window.loginChoiceContext = 'srv_history';
            if(typeof showBsModal === 'function') showBsModal('phInfoModal');
        } else {
            window.fetchServiceInquiryHistory(cfg.customerPhone);
        }
    }
};

window.autoSubmitServiceCartInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_srv_phone');
    const savedInquiry = sessionStorage.getItem('temp_srv_inquiry');
    if (savedPhone !== null) {
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) phoneInput.value = savedPhone;
    }
    if (savedInquiry !== null) {
        const inquiryInput = document.getElementById('customer_inquiry');
        if (inquiryInput) inquiryInput.value = savedInquiry;
    }
    sessionStorage.removeItem('temp_srv_phone');
    sessionStorage.removeItem('temp_srv_inquiry');
    window.hasConfirmedLoginChoice = true;
    window.submitOrder();
};

window.autoSubmitServiceSingleInquiry = function() {
    const savedPhone = sessionStorage.getItem('temp_srv_single_phone');
    const savedInquiry = sessionStorage.getItem('temp_srv_single_inquiry');
    const savedItem = sessionStorage.getItem('temp_srv_single_item');

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

    sessionStorage.removeItem('temp_srv_single_phone');
    sessionStorage.removeItem('temp_srv_single_inquiry');
    sessionStorage.removeItem('temp_srv_single_item');

    window.hasConfirmedLoginChoice = true;
    window.submitSingleInquiry();
};

window.submitOrder = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('orderForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const cfg = getShopConfig();
    if (!cfg) return;

    const phoneInput = document.getElementById('customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, ''); 
    const inquiryInput = document.getElementById('customer_inquiry');

    const resDate = document.getElementById('cart_reservation_date').value;
    const resTime = document.getElementById('cart_reservation_time').value;

    if (wishlist.length === 0) return alert('선택된 서비스가 없습니다.');
    if (!resDate || !resTime) return alert('예약 희망일과 시간을 선택해주세요.');

    if (!cfg.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingSrvAction = 'submitOrder';
        sessionStorage.setItem('postLoginAction', 'srv_cart_inquiry_auto_submit'); 

        sessionStorage.setItem('temp_srv_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_srv_inquiry', inquiryInput ? inquiryInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');

        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
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

    const inquiryText = inquiryInput ? inquiryInput.value.trim() : '';
    const finalInquiry = `[예약 희망: ${resDate} ${resTime}]\n${inquiryText}`;

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', finalInquiry);
    formData.append('inquiry_data', JSON.stringify(cartData)); 
    formData.append('reservation_date', resDate);
    formData.append('reservation_time', resTime);

    const submitBtn = document.querySelector('#cartViewModal .btn-primary');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (cfg.myReservations) {
                if (!cfg.myReservations[resDate]) cfg.myReservations[resDate] = [];
                const itemNames = wishlist.map(i => i.item_name).join(', ');
                cfg.myReservations[resDate].push({ time: resTime, items: itemNames || '서비스' });
                
                if (!cfg.bookedSlots[resDate]) cfg.bookedSlots[resDate] = {};
                if (!cfg.bookedSlots[resDate][resTime]) cfg.bookedSlots[resDate][resTime] = 0;
                cfg.bookedSlots[resDate][resTime]++;
            }

            if (phoneInput) localStorage.setItem('srv_last_search_phone', phoneInput.value);

            wishlist = [];
            localStorage.removeItem('wishlist_' + cfg.shopId);
            window.updateWishlistUI();

            if (inquiryInput) inquiryInput.value = '';

            if(typeof hideBsModal === 'function') hideBsModal('cartViewModal');

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
};

window.submitSingleInquiry = async function() {
    if (typeof validateRequiredFields === 'function' && !validateRequiredFields('singleInquiryForm')) {
        if (typeof showToast === 'function') showToast('필수 입력 정보(연락처, 문의 사항)를 모두 입력해주세요.', 'danger');
        else alert('필수 입력 정보를 모두 입력해주세요.');
        return;
    }

    const cfg = getShopConfig();
    if(!cfg) return;

    const phoneInput = document.getElementById('single_customer_phone');
    const phoneDigits = phoneInput.value.replace(/\D/g, '');
    const inquiryInput = document.getElementById('single_customer_inquiry');
    const itemDataInput = document.getElementById('single_inquiry_item_data');

    const resDate = document.getElementById('single_reservation_date').value;
    const resTime = document.getElementById('single_reservation_time').value;

    if (!itemDataInput || !itemDataInput.value) return;
    if (!resDate || !resTime) return alert('예약 희망일과 시간을 선택해주세요.');
    const item = JSON.parse(itemDataInput.value);

    if (!cfg.isCustomerLoggedIn && !window.hasConfirmedLoginChoice) {
        pendingSrvAction = 'submitSingleInquiry';
        sessionStorage.setItem('postLoginAction', 'srv_single_inquiry_auto_submit'); 

        sessionStorage.setItem('temp_srv_single_phone', phoneInput ? phoneInput.value : '');
        sessionStorage.setItem('temp_srv_single_inquiry', inquiryInput ? inquiryInput.value : '');
        sessionStorage.setItem('temp_srv_single_item', itemDataInput ? itemDataInput.value : '');

        if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');

        setTimeout(() => {
            if(typeof showBsModal === 'function') showBsModal('loginChoiceModal');
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

    const inquiryText = inquiryInput ? inquiryInput.value.trim() : '';
    const finalInquiry = `[예약 희망: ${resDate} ${resTime}]\n${inquiryText}`;

    const formData = new FormData();
    formData.append('shop_id', cfg.shopId);
    formData.append('customer_phone', phoneDigits);
    formData.append('customer_inquiry', finalInquiry);
    formData.append('inquiry_data', JSON.stringify(cartData));
    formData.append('reservation_date', resDate);
    formData.append('reservation_time', resTime);

    const submitBtn = document.querySelector('#singleInquiryForm .btn-dark');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = '접수 중...';

    try {
        const response = await fetch('/shops/srv/shop_srv_reservation_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            if (cfg.myReservations) {
                if (!cfg.myReservations[resDate]) cfg.myReservations[resDate] = [];
                cfg.myReservations[resDate].push({ time: resTime, items: item.item_name || '서비스' });
                
                if (!cfg.bookedSlots[resDate]) cfg.bookedSlots[resDate] = {};
                if (!cfg.bookedSlots[resDate][resTime]) cfg.bookedSlots[resDate][resTime] = 0;
                cfg.bookedSlots[resDate][resTime]++;
            }

            if (phoneInput) localStorage.setItem('srv_last_search_phone', phoneInput.value);

            inquiryInput.value = ''; 

            if(typeof hideBsModal === 'function') hideBsModal('menuDetailModal');

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
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
};