<<<<<<< HEAD
<?php

/**
 * 파일명: /common/common_footer.php
 * 역할: 공통 하단 에러 감지 및 리소스 정리
 */
?>
<style>
    /* =========================================================================
       [공통 모듈] 동적 미디어 슬라이더 (Dynamic Media Carousel) 애니메이션 엔진
       - transition 옵션 인자값에 따른 슬라이딩 속도 및 방식 독립적 제어
       ========================================================================= */

    /* 부트스트랩 고유 동작과의 충돌 방지를 위해 기본 트랜지션 시간만 정밀 타겟팅 */
    .carousel-transition-fast .carousel-item {
        transition: transform 0.3s ease-in-out;
    }

    .carousel-transition-fast.carousel-fade .carousel-item {
        transition: opacity 0.3s ease-in-out;
    }

    .carousel-transition-none .carousel-item {
        transition: none !important;
    }

    /* OS 설정에서 '애니메이션 끄기(prefers-reduced-motion)'를 한 고객에게도 무조건 부드럽게 보이도록 강제 적용 */
    @media (prefers-reduced-motion: reduce) {
        .carousel-transition-smooth .carousel-item {
            transition: transform 0.6s ease-in-out !important;
        }

        .carousel-transition-smooth.carousel-fade .carousel-item {
            transition: opacity 0.6s ease-in-out !important;
        }

        .carousel-transition-fast .carousel-item {
            transition: transform 0.3s ease-in-out !important;
        }
    }
</style>

<!-- 
    [시스템 공통 알림] AJAX 작업 성공/실패 시 화면 우측 하단에 뜨는 공통 토스트 메시지 영역
-->
<div class="toast-container position-fixed end-0 p-3" style="bottom: 80px; z-index: 2100;">
    <div id="sysToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="sysToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- 
    푸터에서 추가적인 에러 감지가 필요한 경우 
    여기에서 error_get_last()를 다시 체크하여 
    페이지 하단에 추가 경고를 노출할 수 있습니다.
-->
<footer class="mt-5 py-3 text-center text-muted border-top small">
    &copy; <?php echo date('Y'); ?> <a href="https://kshops24.com/" class="text-muted" target="_blank">KShops24</a> All rights reserved.
</footer>

<script>
    /**
     * [공통 UI] 시스템 알림 토스트 표시 함수
     * @param {string} message 알림 메시지 내용
     * @param {string} type 알림 타입 (success, danger, info, warning)
     */
    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('sysToast');
        if (!toastEl) {
            alert(message);
            return;
        }
        const toastBody = document.getElementById('sysToastBody');

        toastEl.className = `toast align-items-center text-white bg-${type} border-0 shadow-lg`;

        let icon = type === 'danger' ? 'bi-exclamation-triangle-fill' : (type === 'info' ? 'bi-info-circle-fill' : 'bi-check-circle-fill');
        toastBody.innerHTML = `<i class="bi ${icon} me-2"></i> ${message}`;

        if (typeof bootstrap !== 'undefined') {
            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });
            toast.show();
        } else {
            alert(message);
        }
    }

    /**
     * [공통 AJAX] 모든 폼 제출을 페이지 새로고침 없이 처리하는 범용 함수
     * 사용법: <form onsubmit="handleAjaxFormSubmit(event)">
     */
    async function handleAjaxFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        let originalBtnHtml = '';

        if (submitBtn) {
            originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 처리 중...';
        }

        const formData = new FormData(form);
        // AJAX 요청임을 식별하기 위한 공통 플래그
        formData.append('ajax_update', '1');

        // FormData는 submit 버튼의 name/value를 자동으로 담지 않으므로 수동으로 추가 (update_shop 등)
        if (submitBtn && submitBtn.name) {
            formData.append(submitBtn.name, submitBtn.value || '1');
        }

        const url = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();

        try {
            const response = await fetch(url, {
                method: method,
                body: formData
            });

            const contentType = response.headers.get('content-type');
            if (!response.ok || !contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server Response:', text);
                throw new Error('서버에서 올바르지 않은 응답을 반환했습니다. (상태: ' + response.status + ')');
            }

            const result = await response.json();

            if (result.status === 'success') {
                showToast(result.message || '정상적으로 처리되었습니다.', 'success');

                // Bootstrap 모달 내부에 있는 폼이었다면 자동으로 모달 닫기
                const modal = form.closest('.modal');
                if (modal && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getInstance(modal).hide();
                }
            } else {
                showToast(result.message || '오류가 발생했습니다.', 'danger');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showToast('통신 중 오류가 발생했습니다. (' + error.message + ')', 'danger');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    }

    /**
     * [공통 유틸] 폼 내 필수 입력 필드(required) 빈 값 검증 및 시각적 에러 표시
     * - 시스템 내 어떤 폼이든 폼 요소나 ID를 넘기면 비어있는 필수 입력란에 빨간 테두리를 씌워줍니다.
     * @param {string|HTMLElement} formElement 폼의 ID 문자열 또는 DOM 요소
     * @returns {boolean} 모든 필수 필드가 입력되었으면 true, 누락이 있으면 false
     */
    function validateRequiredFields(formElement) {
        const form = typeof formElement === 'string' ? document.getElementById(formElement) : formElement;
        if (!form) return true;

        let isValid = true;
        let firstInvalidField = null;

        // 폼 내의 모든 required 속성이 부여된 입력 요소 찾기
        const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid'); // Bootstrap 5 에러(빨간 테두리) 클래스 추가
                if (!firstInvalidField) firstInvalidField = field;

                // 사용자가 에러난 곳에 입력을 시작하면 빨간 테두리를 즉시 제거
                field.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                }, {
                    once: true
                });
            }
        });

        if (firstInvalidField) {
            firstInvalidField.focus(); // 첫 번째 누락된 필드로 모바일 키보드 포커스 자동 이동
        }

        return isValid;
    }

    /**
     * 필리핀 전화번호 실시간 포맷팅 (공통)
     * - Mobile: 0917-123-4567
     * - Landline (Manila): 02-8123-4567
     * - Landline (Province): 043-123-4567
     */
    function formatPhoneInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';

        if (val.startsWith('02')) { // 마닐라 유선
            if (val.length <= 2) result = val;
            else if (val.length <= 6) result = val.slice(0, 2) + '-' + val.slice(2);
            else result = val.slice(0, 2) + '-' + val.slice(2, 6) + '-' + val.slice(6, 10);
        } else if (val.startsWith('09')) { // 모바일
            if (val.length <= 4) result = val;
            else if (val.length <= 7) result = val.slice(0, 4) + '-' + val.slice(4);
            else result = val.slice(0, 4) + '-' + val.slice(4, 7) + '-' + val.slice(7, 11);
        } else { // 지방 유선
            if (val.length <= 3) result = val;
            else if (val.length <= 6) result = val.slice(0, 3) + '-' + val.slice(3);
            else result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 10);
        }
        input.value = result;
    }

    /**
     * [공통 UI] 자동 소멸 알림창 제어
     * showAlert() 함수로 생성된 .msg-auto-close 클래스 요소를 3초 후 자동으로 닫습니다.
     */
    document.addEventListener('DOMContentLoaded', function() {
        const autoCloseAlerts = document.querySelectorAll('.msg-auto-close');
        autoCloseAlerts.forEach(function(alert) {
            setTimeout(function() {
                // Bootstrap의 Alert 인스턴스를 사용하여 부드럽게 닫기
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 3000); // 3초 후 실행
        });
    });

    /**
     * =========================================================================
     * [프론트엔드 모듈] 동적 미디어 슬라이더 엔진 (MediaSliderModule)
     * - 역할: 이미지와 유튜브 영상 통합 관리, 슬라이더 렌더링, 포인터 스와이프 제어
     * - 객체 지향 패턴으로 캡슐화하여 전역 변수 및 이벤트 오염 완벽 방지
     * =========================================================================
     */
    const MediaSliderModule = {
        /**
         * [핵심 버그 1 수정] 이미지 URL이 유튜브로 둔갑하는 버그 원천 차단
         */
        extractYoutubeId: function(url) {
            if (!url || (url.indexOf('youtube.com') === -1 && url.indexOf('youtu.be') === -1)) return null;
            const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
            return match ? match[1] : null;
        },

        generateHTML: function(containerId, mediaList, options = {}) {
            if (!mediaList || mediaList.length === 0) mediaList = ['/assets/no-logo.png'];

            const objectFit = options.objectFit || 'cover';
            const fadeClass = options.fade ? 'carousel-fade' : '';

            let transitionClass = 'carousel-transition-smooth';
            if (options.transition === 'fast') transitionClass = 'carousel-transition-fast';
            else if (options.transition === 'none') transitionClass = 'carousel-transition-none';

            let itemsHtml = '';
            let indicatorsHtml = '';

            mediaList.forEach((mediaUrl, idx) => {
                const isActive = idx === 0 ? 'active' : '';
                const ytId = this.extractYoutubeId(mediaUrl); // 강력한 도메인 검증 통과
                let mediaContent = '';

                // [최적화] 첫 번째 슬라이드는 즉시 로딩, 나머지는 지연 로딩(lazy)
                const loadAttr = idx === 0 ? 'fetchpriority="high"' : 'loading="lazy"';

                if (ytId) {
                    mediaContent = `<iframe id="${containerId}-video-${idx}" width="100%" height="100%" src="https://www.youtube.com/embed/${ytId}?enablejsapi=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="object-fit: ${objectFit}; pointer-events: auto;" ${loadAttr}></iframe>`;
                } else {
                    // [기능 추가] fslightbox 옵션이 활성화된 경우 <a> 태그로 감싸서 클릭 시 전체화면 확대 지원
                    if (options.useLightbox) {
                        const lightboxGroup = options.lightboxGroup || containerId;
                        mediaContent = `<a data-fslightbox="${lightboxGroup}" href="${mediaUrl}" class="d-block w-100 h-100" style="cursor: zoom-in;"><img src="${mediaUrl}" class="d-block w-100 h-100" style="object-fit: ${objectFit};" onerror="this.onerror=null; this.src='/assets/no-logo.png';" ${loadAttr}></a>`;
                    } else {
                        mediaContent = `<img src="${mediaUrl}" class="d-block w-100 h-100" style="object-fit: ${objectFit};" onerror="this.onerror=null; this.src='/assets/no-logo.png';" ${loadAttr}>`;
                    }
                }

                itemsHtml += `<div class="carousel-item h-100 ${isActive}">${mediaContent}</div>`;
                // [표준 복구] 부트스트랩 네이티브 Data API 속성 원상 복구
                indicatorsHtml += `<button type="button" data-bs-target="#${containerId}" data-bs-slide-to="${idx}" class="${isActive}"></button>`;
            });

            let controlsHtml = '';
            if (mediaList.length > 1) {
                controlsHtml = `
                    <button class="carousel-control-prev" type="button" data-bs-target="#${containerId}" data-bs-slide="prev" style="z-index: 10;">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#${containerId}" data-bs-slide="next" style="z-index: 10;">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                    <div class="carousel-indicators" style="z-index: 10;">${indicatorsHtml}</div>
                `;
            }

            return `
                <div id="${containerId}" class="carousel slide ${transitionClass} h-100 ${fadeClass}" data-bs-touch="true" ${options.interval ? `data-bs-ride="carousel" data-bs-interval="${options.interval}"` : ''}>
                    <div class="carousel-inner h-100">${itemsHtml}</div>
                    ${controlsHtml}
                </div>
            `;
        },

        /**
         * [핵심 버그 2 수정] 오른쪽 화살표 클릭 시 역방향 스크롤 오작동 해결 (PointerEvent 도입)
         */
        enableDragSwipe: function(carouselEl) {
            if (!carouselEl) return;
            let isDragging = false;
            let startX = 0;
            let isSwiped = false; // 드래그 스와이프 판별용

            carouselEl.addEventListener('pointerdown', (e) => {
                const ignoredElements = e.target.closest('button, a, .carousel-control-prev, .carousel-control-next, .carousel-indicators');
                // fslightbox 팝업용 a 태그는 스와이프가 가능해야 하므로 예외 처리합니다.
                if (ignoredElements && !ignoredElements.hasAttribute('data-fslightbox')) {
                    return;
                }

                // 부트스트랩 기본 모바일 터치 기능과의 중복 및 꼬임 방지를 위해 마우스일 경우만 커스텀 제어
                if (e.pointerType === 'touch') return;

                isDragging = true;
                isSwiped = false;
                startX = e.clientX;
                carouselEl.style.cursor = 'grabbing';
            });

            const stopDrag = (e) => {
                if (!isDragging) return;
                isDragging = false;
                carouselEl.style.cursor = 'default';

                const endX = e.clientX;
                const diffX = startX - endX;

                // 임계값 50px 이상 명확히 밀었을 때만 슬라이드 발동 (정확한 뱡항 판별)
                if (Math.abs(diffX) > 50) {
                    isSwiped = true;
                    const bsCarousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
                    if (diffX > 0) bsCarousel.next();
                    else bsCarousel.prev();
                }
            };

            carouselEl.addEventListener('pointerup', stopDrag);
            carouselEl.addEventListener('pointerleave', stopDrag);
            carouselEl.addEventListener('pointercancel', stopDrag);

            // [클릭 방지 로직] 스와이프 후 click 이벤트가 실행되어 팝업이 뜨는 것을 방지
            carouselEl.addEventListener('click', (e) => {
                if (isSwiped) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            // 이미지 드래그 고스트 현상 방지
            carouselEl.querySelectorAll('img, a').forEach(el => el.addEventListener('dragstart', e => e.preventDefault()));
        },

        init: function(containerId, options = {}) {
            const carouselEl = document.getElementById(containerId);
            if (!carouselEl || typeof bootstrap === 'undefined') return null;

            const instance = new bootstrap.Carousel(carouselEl, {
                interval: options.interval || false,
                touch: true, // 모바일 터치 지원 유지
                wrap: true // 무한 루프(원형 연결) 명시적 선언
            });

            this.enableDragSwipe(carouselEl);

            carouselEl.addEventListener('slide.bs.carousel', () => {
                carouselEl.querySelectorAll('iframe').forEach(iframe => {
                    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                });
            });

            // [기능 추가] fslightbox 기능이 쓰인 경우 동적으로 추가된 DOM을 재스캔하여 바인딩
            if (options.useLightbox && typeof refreshFsLightbox !== 'undefined') {
                refreshFsLightbox();
            }

            return instance;
        }
    };

    // [하위 호환성 유지] 기존에 사용되던 레거시 코드 호출을 위한 래퍼(Wrapper) 함수
    function generateDynamicCarousel(containerId, mediaList, options = {}) {
        return MediaSliderModule.generateHTML(containerId, mediaList, options);
    }

    function initDynamicCarousel(containerId, options = {}) {
        return MediaSliderModule.init(containerId, options);
    }

    function enableCarouselSwipe(carousel) {
        const el = typeof carousel === 'string' ? document.getElementById(carousel) : carousel;
        MediaSliderModule.enableDragSwipe(el);
    }
    /**
     * [공통 유틸] 텍스트를 클립보드에 복사하는 함수
     * @param {string} text - 복사할 텍스트
     * @param {string} successMessage - 복사 성공 시 alert으로 띄울 메시지
     */
    async function copyToClipboard(text, successMessage = '클립보드에 복사되었습니다.') {
        if (!text) {
            alert('복사할 내용이 없습니다.');
            return;
        }
        try {
            // 최신 비동기 Clipboard API 사용
            await navigator.clipboard.writeText(text);
            alert(successMessage);
        } catch (err) {
            // 구형 브라우저 또는 https가 아닌 환경을 위한 Fallback
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed"; // 화면에 보이지 않게 처리
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert(successMessage);
            } catch (err) {
                alert('이 브라우저에서는 복사를 지원하지 않습니다. 수동으로 복사해주세요.');
            }
            document.body.removeChild(textArea);
        }
    }

    /**
     * [공통 UI] 마우스 드래그로 가로 스크롤하는 기능을 활성화합니다.
     * @param {string} selector - 드래그 스크롤을 적용할 요소의 CSS 선택자
     */
    function enableDragScroll(selector) {
        const element = document.querySelector(selector);
        if (!element) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        element.style.cursor = 'grab';

        element.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - element.offsetLeft;
            scrollLeft = element.scrollLeft;
            element.style.cursor = 'grabbing';
        });

        ['mouseleave', 'mouseup'].forEach(event => {
            element.addEventListener(event, () => {
                isDown = false;
                element.style.cursor = 'grab';
            });
        });

        element.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - element.offsetLeft;
            const walk = (x - startX) * 1.5; // 스크롤 속도
            element.scrollLeft = scrollLeft - walk;
        });
    }

    /**
     * [공통 UX] 페이지 새로고침 시 스크롤 위치 복원 및 해시(#) 이동
     */
    document.addEventListener('DOMContentLoaded', function() {
        const savedScrollPos = sessionStorage.getItem('pageScrollPos');
        if (savedScrollPos) {
            setTimeout(() => {
                window.scrollTo(0, parseInt(savedScrollPos, 10));
                sessionStorage.removeItem('pageScrollPos');
            }, 50);
        }

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => sessionStorage.setItem('pageScrollPos', window.scrollY));
        });

        if (window.location.hash) {
            setTimeout(() => {
                try {
                    const target = document.querySelector(window.location.hash);
                    if (target) target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                } catch (e) {
                    console.warn('Invalid hash for scrolling:', window.location.hash);
                }
            }, 300);
        }
    });

    /**
     * =========================================================================
     * [공통] 이미지 일괄 처리 관리자 (미리보기 후 저장)
     * - 페이지 새로고침 없이 이미지 추가/삭제 미리보기를 제공하고, '저장' 시점에 일괄 처리합니다.
     * =========================================================================
     */
    const imageBatchManagers = {};

    /**
     * 특정 섹션을 이미지 일괄 처리 대상으로 등록하고 초기화합니다.
     * @param {string} key - 관리자를 식별할 고유 키 (예: 'gallery', 'background')
     * @param {object} config - 설정 객체
     */
    function initImageBatchManager(key, config) {
        imageBatchManagers[key] = {
            state: {
                newFiles: [], // { tempId, file }
                deletedItems: [], // 삭제할 ID 또는 경로
            },
            config: config,
            container: document.getElementById(config.containerId),
        };

        const manager = imageBatchManagers[key]; // [버그 수정] 변수 선언 누락 해결

        // [리팩토링] SortableJS(드래그 앤 드롭) 자동 초기화 지원
        if (config.sortable && manager.container && typeof Sortable !== 'undefined') {
            Sortable.create(manager.container, {
                animation: 150,
                draggable: '.gallery-item', // 드래그할 대상 클래스를 명시적으로 지정
                filter: config.sortableFilter || '.empty-msg, .btn-add-img, button, .btn, i', // 하위 태그까지 모두 드래그 무시 처리
                preventOnFilter: false, // 중요: 버튼 고유의 클릭(삭제) 이벤트가 작동하도록 허용
                ghostClass: 'opacity-50',
                delay: 0, // 드래그 반응 속도를 즉시 반응형으로 개선 (클릭/드래그 먹통 방지)
                touchStartThreshold: 5, // 터치 후 5px 이내 흔들림은 단순 클릭으로 인정하여 삭제버튼 활성화
                fallbackOnBody: true, // 드래그 시 잔상이 가려지는 현상 차단
                forceFallback: true // [추가] 아이폰 사파리 등 모바일 환경에서 터치 충돌 없이 부드러운 드래그 강제 적용
            });
        }
    }

    /**
     * 파일 선택 시, 미리보기를 생성하고 'newFiles' 대기열에 추가합니다.
     * @param {string} key - 관리자 식별 키
     * @param {HTMLInputElement} input - 파일이 선택된 <input type="file"> 요소
     */
    function addBatchImage(key, input) {
        const manager = imageBatchManagers[key];
        if (!manager || !input.files || input.files.length === 0) return;

        const {
            container,
            config
        } = manager;
        const addBtn = container.querySelector(config.addBtnSelector);
        const emptyMsg = container.querySelector('.empty-msg');

        // Get layout config, with defaults for backward compatibility
        const itemClass = config.itemClass || 'col-6 col-md-4 gallery-item';
        const aspectRatio = config.aspectRatio || '4/3';

        Array.from(input.files).forEach(file => {
            const tempId = `new_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            const objectURL = URL.createObjectURL(file);
            manager.state.newFiles.push({
                tempId: tempId,
                file: file
            });

            if (emptyMsg) emptyMsg.classList.add('d-none');

            const div = document.createElement('div');
            div.className = itemClass;
            div.id = `${key}-item-${tempId}`;
            div.style.cursor = 'grab';
            div.innerHTML = `
                <div class="position-relative">
                    <img src="${objectURL}" class="w-100 rounded border shadow-sm opacity-75" style="aspect-ratio: ${aspectRatio}; object-fit: cover;">
                    <span class="badge bg-warning position-absolute top-0 start-0 m-1" style="font-size: 0.6rem;">NEW</span>
                    <button type="button" onclick="event.stopPropagation(); deleteBatchImage('${key}', '${tempId}', true)" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                </div>
            `;
            if (container) {
                if (addBtn) {
                    container.insertBefore(div, addBtn);
                } else {
                    container.appendChild(div);
                }
            }
        });
        input.value = '';
    }

    /**
     * 이미지를 '삭제' 대기열에 추가하고 화면에서 숨깁니다.
     * @param {string} key - 관리자 식별 키
     * @param {string|number} itemId - 삭제할 아이템의 ID 또는 경로
     * @param {boolean} isTemp - 새로 추가된 임시 아이템인지 여부
     */
    function deleteBatchImage(key, itemId, isTemp = false) {
        const manager = imageBatchManagers[key];
        if (!manager) return;
        if (!confirm("목록에서 제거하시겠습니까?\n('저장' 버튼을 눌러야 최종 반영됩니다.)")) return;

        const itemEl = document.getElementById(`${key}-item-${itemId}`);
        if (itemEl) itemEl.style.display = 'none';

        if (isTemp) {
            manager.state.newFiles = manager.state.newFiles.filter(f => f.tempId !== itemId);
            if (itemEl) itemEl.remove();
        } else {
            manager.state.deletedItems.push(itemId);
        }

        const container = manager.container;
        const emptyMsg = container.querySelector('.empty-msg');
        const visibleItems = Array.from(container.querySelectorAll('.gallery-item')).filter(el => el.style.display !== 'none');
        if (emptyMsg) {
            emptyMsg.classList.toggle('d-none', visibleItems.length > 0);
        }
    }

    /**
     * 폼 제출 시, 삭제/업로드/저장 로직을 일괄 수행합니다.
     * @param {Event} event - 폼의 submit 이벤트
     * @param {string} key - 관리자 식별 키
     */
    async function saveImageBatch(event, key) {
        event.preventDefault();
        const manager = imageBatchManagers[key];
        const form = event.target;
        if (!manager) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>반영 중...';

        try {
            // 1. 삭제 처리 (설정에 따라 AJAX 요청 또는 로컬 처리)
            if (manager.config.deleteUrl) {
                for (const id of manager.state.deletedItems) {
                    const fd = new FormData();
                    fd.append('action', manager.config.deleteActionName);
                    fd.append(manager.config.deleteIdParam, id);
                    await fetch(manager.config.deleteUrl, {
                        method: 'POST',
                        body: fd
                    });
                }
            }

            // 2. 신규 업로드 처리
            const newPathsMap = {};
            if (manager.state.newFiles.length > 0) {
                const uploadPromises = manager.state.newFiles.map(fileObj => {
                    const fd = new FormData();
                    fd.append('image', fileObj.file);
                    Object.entries(manager.config.uploadParams).forEach(([k, v]) => fd.append(k, v));
                    fd.append('mode', 'insert');
                    return fetch('/common/upload_image.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => ({
                            tempId: fileObj.tempId,
                            ...data
                        }));
                });
                const results = await Promise.all(uploadPromises);
                
                let uploadErrors = [];
                results.forEach(res => {
                    if (res.status === 'success') {
                        newPathsMap[res.tempId] = res.path;
                    } else {
                        uploadErrors.push(res.message || '알 수 없는 오류');
                    }
                });

                // [에러 방어] 사진 업로드 실패 시 점주에게 팝업으로 원인 안내
                if (uploadErrors.length > 0) {
                    alert('일부 이미지 업로드에 실패했습니다:\n' + uploadErrors.join('\n'));
                }
            }

            // 3. 제출 전 콜백 실행 (hidden input 업데이트 등)
            if (typeof manager.config.beforeSubmit === 'function') {
                manager.config.beforeSubmit(newPathsMap, manager.state.deletedItems, manager);
            }

            // [리팩토링] 화면상의 이미지 순서대로 경로(Path) 배열을 추출하여 hidden input에 자동 삽입
            if (manager.config.hiddenOrderInputId) {
                const orderInput = document.getElementById(manager.config.hiddenOrderInputId);
                if (orderInput) {
                    const items = manager.container.querySelectorAll('.gallery-item');
                    let finalPaths = [];
                    items.forEach(item => {
                        if (item.style.display !== 'none') {
                            const existingPath = item.getAttribute('data-path');
                            if (existingPath) {
                                finalPaths.push(existingPath);
                            } else {
                                const tempId = item.id.replace(`${key}-item-`, '');
                                if (newPathsMap[tempId]) finalPaths.push(newPathsMap[tempId]);
                            }
                        }
                    });
                    orderInput.value = JSON.stringify(finalPaths);
                }
            }

            // 4. 나머지 폼 데이터(텍스트 등) 저장
            await handleAjaxFormSubmit(event);

            // 5. 성공 후 새로고침하여 UI 동기화
            setTimeout(() => location.reload(), 800);

        } catch (error) {
            alert('저장 처리 중 오류가 발생했습니다: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }

    /**
     * =========================================================================
     * [공통 UX] 범용 AJAX 페이징 처리 (PJAX 패턴)
     * - 클래스에 'ajax-page-link'가 포함된 링크 클릭 시 동작
     * - 전체 페이지 새로고침 없이 부모 영역(.card-body 또는 .card)만 비동기로 갱신
     * =========================================================================
     */
    document.addEventListener('DOMContentLoaded', function() {
        // 이벤트 중복 실행 방지
        if (window.isAjaxPaginationBound) return;
        window.isAjaxPaginationBound = true;

        // 이벤트 위임(Event Delegation)을 통해 동적으로 생성된 HTML 요소에서도 동작하게 함
        document.body.addEventListener('click', async function(e) {
            const link = e.target.closest('.ajax-page-link');
            if (!link) return;

            e.preventDefault();
            const url = link.href;

            // 페이징 버튼이 속해있는 가장 가까운 부모 영역(.card-body 또는 .card)을 자동으로 인식합니다.
            const targetContainer = link.closest('.card-body') || link.closest('.card');
            if (!targetContainer) {
                window.location.href = url;
                return;
            }

            // 통신 중 시각적 피드백 (반투명 및 클릭 방지)
            targetContainer.style.transition = 'opacity 0.2s';
            targetContainer.style.opacity = '0.4';
            targetContainer.style.pointerEvents = 'none';

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('Network error');

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                // 백그라운드에서 불러온 새 페이지 HTML 구조에서 동일한 인덱스의 영역을 추출해 치환합니다.
                const className = targetContainer.classList.contains('card-body') ? '.card-body' : '.card';
                const index = Array.from(document.querySelectorAll(className)).indexOf(targetContainer);
                const newContainer = doc.querySelectorAll(className)[index];

                if (newContainer) {
                    targetContainer.innerHTML = newContainer.innerHTML;
                    // 브라우저 주소창 URL도 조용히 변경하여 '뒤로가기'를 지원합니다 (스마트한 UX)
                    window.history.pushState({
                        path: url
                    }, '', url);
                } else {
                    window.location.href = url; // 렌더링 매칭 실패시 일반 페이지 이동
                }
            } catch (err) {
                window.location.href = url;
            } finally {
                targetContainer.style.opacity = '1';
                targetContainer.style.pointerEvents = 'auto';
            }
        });

        // 사용자가 스마트폰/브라우저 '뒤로가기' 버튼을 눌렀을 때 페이지 갱신 대응
        window.addEventListener('popstate', (e) => {
            // [버그 수정] 모달(Modal) 팝업이 열린 상태에서 뒤로가기를 눌렀을 때
            // 페이지 전체가 새로고침(Refresh)되어 버리는 현상 방지
            if (document.body.classList.contains('modal-open')) {
                return; // 새로고침을 중단하고 모달 전역 닫기 스크립트에 처리를 위임
            }
            window.location.reload();
        });
    });

    /**
     * =========================================================================
     * [공통 UX] 페이지 내 스크롤 네비게이션 자동 생성 (플로팅 퀵 메뉴)
     * - 클래스에 'scroll-nav-target'이 포함된 섹션들을 스캔하여 우측 상단 플로팅 메뉴를 생성합니다.
     * =========================================================================
     */
    const ScrollNavModule = {
        init: function() {
            const targets = document.querySelectorAll('.scroll-nav-target');
            if (targets.length === 0) return; // 네비게이션 대상이 없으면 실행 안함

            // [UI 개선] 메뉴에 적용될 동적 CSS 스타일 주입 (DRY: 별도 CSS 파일 오염 없이 모듈 내부에 캡슐화)
            if (!document.getElementById('quick-nav-style')) {
                const style = document.createElement('style');
                style.id = 'quick-nav-style';
                style.innerHTML = `
                    .quick-nav-menu { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(12px); border-radius: 16px !important; padding: 12px 10px !important; box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important; border: 1px solid rgba(0,0,0,0.05) !important; max-height: 65vh; overflow-y: auto; }
                    .quick-nav-menu::-webkit-scrollbar { width: 4px; }
                    .quick-nav-menu::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.15); border-radius: 4px; }
                    .quick-nav-menu .dropdown-item { border-radius: 8px; transition: all 0.2s ease; font-size: 0.95rem; }
                    .quick-nav-menu .dropdown-item:hover { background-color: #f0f4f8; color: #004aad !important; transform: translateX(5px); }
                    .quick-nav-btn { background: linear-gradient(135deg, #004aad, #002e6b) !important; color: white !important; border: 3px solid rgba(255,255,255,0.9) !important; box-shadow: 0 8px 20px rgba(0,74,173,0.4) !important; transition: transform 0.2s ease; touch-action: none; }
                    .quick-nav-btn:hover { transform: scale(1.05); }
                    .quick-nav-btn::after { display: none !important; } /* 부트스트랩 기본 화살표 숨김 */
                `;
                document.head.appendChild(style);
            }

            // 컨테이너 생성
            const navContainer = document.createElement('div');
            navContainer.className = 'position-fixed dropdown';
            // 상단 메뉴바 바로 아래 우측 위치 (모바일 터치 및 다른 버튼들과 겹치지 않게 조절)
            navContainer.style.top = '80px';
            navContainer.style.right = '15px';
            // [버그 수정] z-index를 최상위(2050)로 올려 다른 모달이나 플로팅 바 아래로 숨는 현상 해결
            navContainer.style.zIndex = '2050';

            // 플로팅 메뉴 버튼
            const btn = document.createElement('button');
            btn.className = 'btn rounded-circle d-flex align-items-center justify-content-center dropdown-toggle quick-nav-btn';
            btn.style.width = '52px';
            btn.style.height = '52px';
            btn.setAttribute('data-bs-toggle', 'dropdown');
            btn.innerHTML = '<i class="bi bi-list-nested fs-3"></i>';

            // 드롭다운 목록
            const menu = document.createElement('ul');
            menu.className = 'dropdown-menu dropdown-menu-end border-0 mt-2 quick-nav-menu';
            menu.style.minWidth = '220px';

            // 빠른 이동 메뉴
            const headerItem = document.createElement('li');
            headerItem.innerHTML = `<h6 class="dropdown-header fw-bold text-primary border-bottom pb-3 mb-2 px-2 d-flex align-items-center"><i class="bi bi-cursor-fill me-2"></i><?php echo __('빠른 이동 메뉴'); ?></h6>`;
            menu.appendChild(headerItem);

            // [추가] 화면 맨 위로 이동하는 고정 항목
            const topItem = document.createElement('li');
            topItem.innerHTML = `<a class="dropdown-item py-2 px-3 fw-bold text-primary d-flex align-items-center justify-content-between bg-light mb-1" href="#" onclick="event.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' });"><span><?php echo __('맨 위로'); ?></span><i class="bi bi-arrow-up-circle-fill fs-5"></i></a>`;
            menu.appendChild(topItem);

            targets.forEach(target => {
                const label = target.getAttribute('data-nav-label');
                const id = target.id;
                const isIndent = target.getAttribute('data-nav-indent') === 'true';

                // 화면에 보이지 않는 요소(display:none)는 네비게이션 목록에서 제외 (예: 오프된 기능)
                if (label && id && window.getComputedStyle(target).display !== 'none') {
                    const li = document.createElement('li');
                    if (isIndent) {
                        li.innerHTML = `<a class="dropdown-item py-1 ps-4 pe-3 fw-medium text-secondary d-flex align-items-center" href="#${id}" onclick="ScrollNavModule.scrollToTarget(event, '${id}')" style="font-size: 0.85rem;"><i class="bi bi-arrow-return-right me-2 text-muted opacity-50"></i><span class="text-truncate">${label}</span></a>`;
                    } else {
                        li.innerHTML = `<a class="dropdown-item py-2 px-3 fw-bold text-dark d-flex align-items-center justify-content-between" href="#${id}" onclick="ScrollNavModule.scrollToTarget(event, '${id}')"><span>${label}</span><i class="bi bi-chevron-right small text-muted opacity-50"></i></a>`;
                    }
                    menu.appendChild(li);
                }
            });

            // DOM에 삽입
            navContainer.appendChild(btn);
            navContainer.appendChild(menu);
            document.body.appendChild(navContainer);

            // [추가] 빠른 이동 메뉴 드래그 앤 드롭 이동 기능
            let isDragging = false;
            let isDragged = false;
            let startX, startY;

            // 세션 스토리지에 저장된 이전 위치가 있다면 복원
            const savedPos = sessionStorage.getItem('quickNavPos');
            if (savedPos) {
                const pos = JSON.parse(savedPos);
                navContainer.style.top = pos.top;
                navContainer.style.left = pos.left;
                navContainer.style.right = 'auto'; // 기본 right 기준을 무효화
            }

            btn.addEventListener('pointerdown', (e) => {
                // 왼쪽 마우스 클릭 또는 터치만 허용
                if (e.button !== 0 && e.pointerType === 'mouse') return;

                isDragging = true;
                isDragged = false;
                startX = e.clientX;
                startY = e.clientY;
                btn.setPointerCapture(e.pointerId);

                // right 기준으로 배치되어 있다면 left 기준으로 변환 (드래그 좌표 계산 편의성)
                if (navContainer.style.right !== 'auto') {
                    const rect = navContainer.getBoundingClientRect();
                    navContainer.style.left = rect.left + 'px';
                    navContainer.style.right = 'auto';
                }
            });

            btn.addEventListener('pointermove', (e) => {
                if (!isDragging) return;

                const diffX = e.clientX - startX;
                const diffY = e.clientY - startY;

                // 단순 터치(클릭)와 드래그를 구분하기 위한 임계값 (5px)
                if (!isDragged && (Math.abs(diffX) > 5 || Math.abs(diffY) > 5)) {
                    isDragged = true;
                }

                if (isDragged) {
                    const rect = navContainer.getBoundingClientRect();
                    let newLeft = rect.left + diffX;
                    let newTop = rect.top + diffY;

                    // 화면 바깥으로 나가지 않도록 경계 고정 (Boundary)
                    const maxLeft = window.innerWidth - rect.width;
                    const maxTop = window.innerHeight - rect.height;

                    if (newLeft < 0) newLeft = 0;
                    if (newLeft > maxLeft) newLeft = maxLeft;
                    if (newTop < 0) newTop = 0;
                    if (newTop > maxTop) newTop = maxTop;

                    navContainer.style.left = newLeft + 'px';
                    navContainer.style.top = newTop + 'px';

                    startX = e.clientX;
                    startY = e.clientY;
                }
            });

            const stopDrag = (e) => {
                if (!isDragging) return;
                isDragging = false;
                btn.releasePointerCapture(e.pointerId);

                if (isDragged) {
                    // 드래그를 마쳤을 때 현재 위치를 세션에 저장 (페이지 이동 시에도 유지)
                    sessionStorage.setItem('quickNavPos', JSON.stringify({
                        left: navContainer.style.left,
                        top: navContainer.style.top
                    }));
                }
            };

            btn.addEventListener('pointerup', stopDrag);
            btn.addEventListener('pointercancel', stopDrag);

            // [핵심] 드래그 중이었다면 클릭 이벤트를 차단하여 드롭다운 메뉴가 열리는 것을 방지
            btn.addEventListener('click', (e) => {
                if (isDragged) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true); // 캡처링 단계에서 차단
        },
        scrollToTarget: function(e, id) {
            e.preventDefault();
            const target = document.getElementById(id);
            if (target) {
                // 상단 고정 네비게이션(Navbar)의 높이를 고려해 여백(offset)을 70px 정도로 둠
                const headerOffset = 70;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        ScrollNavModule.init();
    });
</script>

<?php
// 모달 및 갤러리 팝업 모바일 뒤로가기 제어 스크립트 전역 출력
if (function_exists('renderPopupHistoryBackScript')) {
    echo renderPopupHistoryBackScript();
}
?>

<?php
// 공통 이미지 뷰어 모달 렌더링 (lib_utils.php)
if (function_exists('renderCommonImageModal')) {
    echo renderCommonImageModal();
}
?>

</body>

=======
<?php

/**
 * 파일명: /common/common_footer.php
 * 역할: 공통 하단 에러 감지 및 리소스 정리
 */
?>
<style>
    /* =========================================================================
       [공통 모듈] 동적 미디어 슬라이더 (Dynamic Media Carousel) 애니메이션 엔진
       - transition 옵션 인자값에 따른 슬라이딩 속도 및 방식 독립적 제어
       ========================================================================= */

    /* 부트스트랩 고유 동작과의 충돌 방지를 위해 기본 트랜지션 시간만 정밀 타겟팅 */
    .carousel-transition-fast .carousel-item {
        transition: transform 0.3s ease-in-out;
    }

    .carousel-transition-fast.carousel-fade .carousel-item {
        transition: opacity 0.3s ease-in-out;
    }

    .carousel-transition-none .carousel-item {
        transition: none !important;
    }

    /* OS 설정에서 '애니메이션 끄기(prefers-reduced-motion)'를 한 고객에게도 무조건 부드럽게 보이도록 강제 적용 */
    @media (prefers-reduced-motion: reduce) {
        .carousel-transition-smooth .carousel-item {
            transition: transform 0.6s ease-in-out !important;
        }

        .carousel-transition-smooth.carousel-fade .carousel-item {
            transition: opacity 0.6s ease-in-out !important;
        }

        .carousel-transition-fast .carousel-item {
            transition: transform 0.3s ease-in-out !important;
        }
    }
</style>

<!-- 
    [시스템 공통 알림] AJAX 작업 성공/실패 시 화면 우측 하단에 뜨는 공통 토스트 메시지 영역
-->
<div class="toast-container position-fixed end-0 p-3" style="bottom: 80px; z-index: 2100;">
    <div id="sysToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="sysToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- 
    푸터에서 추가적인 에러 감지가 필요한 경우 
    여기에서 error_get_last()를 다시 체크하여 
    페이지 하단에 추가 경고를 노출할 수 있습니다.
-->
<footer class="mt-5 py-3 text-center text-muted border-top small">
    &copy; <?php echo date('Y'); ?> <a href="https://kshops24.com/" class="text-muted" target="_blank">KShops24</a> All rights reserved.
</footer>

<script>
    /**
     * [공통 UI] 시스템 알림 토스트 표시 함수
     * @param {string} message 알림 메시지 내용
     * @param {string} type 알림 타입 (success, danger, info, warning)
     */
    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('sysToast');
        if (!toastEl) {
            alert(message);
            return;
        }
        const toastBody = document.getElementById('sysToastBody');

        toastEl.className = `toast align-items-center text-white bg-${type} border-0 shadow-lg`;

        let icon = type === 'danger' ? 'bi-exclamation-triangle-fill' : (type === 'info' ? 'bi-info-circle-fill' : 'bi-check-circle-fill');
        toastBody.innerHTML = `<i class="bi ${icon} me-2"></i> ${message}`;

        if (typeof bootstrap !== 'undefined') {
            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });
            toast.show();
        } else {
            alert(message);
        }
    }

    /**
     * [공통 AJAX] 모든 폼 제출을 페이지 새로고침 없이 처리하는 범용 함수
     * 사용법: <form onsubmit="handleAjaxFormSubmit(event)">
     */
    async function handleAjaxFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        let originalBtnHtml = '';

        if (submitBtn) {
            originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 처리 중...';
        }

        const formData = new FormData(form);
        // AJAX 요청임을 식별하기 위한 공통 플래그
        formData.append('ajax_update', '1');

        // FormData는 submit 버튼의 name/value를 자동으로 담지 않으므로 수동으로 추가 (update_shop 등)
        if (submitBtn && submitBtn.name) {
            formData.append(submitBtn.name, submitBtn.value || '1');
        }

        const url = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();

        try {
            const response = await fetch(url, {
                method: method,
                body: formData
            });

            const contentType = response.headers.get('content-type');
            if (!response.ok || !contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server Response:', text);
                throw new Error('서버에서 올바르지 않은 응답을 반환했습니다. (상태: ' + response.status + ')');
            }

            const result = await response.json();

            if (result.status === 'success') {
                showToast(result.message || '정상적으로 처리되었습니다.', 'success');

                // Bootstrap 모달 내부에 있는 폼이었다면 자동으로 모달 닫기
                const modal = form.closest('.modal');
                if (modal && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getInstance(modal).hide();
                }
            } else {
                showToast(result.message || '오류가 발생했습니다.', 'danger');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showToast('통신 중 오류가 발생했습니다. (' + error.message + ')', 'danger');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    }

    /**
     * [공통 유틸] 폼 내 필수 입력 필드(required) 빈 값 검증 및 시각적 에러 표시
     * - 시스템 내 어떤 폼이든 폼 요소나 ID를 넘기면 비어있는 필수 입력란에 빨간 테두리를 씌워줍니다.
     * @param {string|HTMLElement} formElement 폼의 ID 문자열 또는 DOM 요소
     * @returns {boolean} 모든 필수 필드가 입력되었으면 true, 누락이 있으면 false
     */
    function validateRequiredFields(formElement) {
        const form = typeof formElement === 'string' ? document.getElementById(formElement) : formElement;
        if (!form) return true;

        let isValid = true;
        let firstInvalidField = null;

        // 폼 내의 모든 required 속성이 부여된 입력 요소 찾기
        const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid'); // Bootstrap 5 에러(빨간 테두리) 클래스 추가
                if (!firstInvalidField) firstInvalidField = field;

                // 사용자가 에러난 곳에 입력을 시작하면 빨간 테두리를 즉시 제거
                field.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                }, {
                    once: true
                });
            }
        });

        if (firstInvalidField) {
            firstInvalidField.focus(); // 첫 번째 누락된 필드로 모바일 키보드 포커스 자동 이동
        }

        return isValid;
    }

    /**
     * 필리핀 전화번호 실시간 포맷팅 (공통)
     * - Mobile: 0917-123-4567
     * - Landline (Manila): 02-8123-4567
     * - Landline (Province): 043-123-4567
     */
    function formatPhoneInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';

        if (val.startsWith('02')) { // 마닐라 유선
            if (val.length <= 2) result = val;
            else if (val.length <= 6) result = val.slice(0, 2) + '-' + val.slice(2);
            else result = val.slice(0, 2) + '-' + val.slice(2, 6) + '-' + val.slice(6, 10);
        } else if (val.startsWith('09')) { // 모바일
            if (val.length <= 4) result = val;
            else if (val.length <= 7) result = val.slice(0, 4) + '-' + val.slice(4);
            else result = val.slice(0, 4) + '-' + val.slice(4, 7) + '-' + val.slice(7, 11);
        } else { // 지방 유선
            if (val.length <= 3) result = val;
            else if (val.length <= 6) result = val.slice(0, 3) + '-' + val.slice(3);
            else result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 10);
        }
        input.value = result;
    }

    /**
     * [공통 UI] 자동 소멸 알림창 제어
     * showAlert() 함수로 생성된 .msg-auto-close 클래스 요소를 3초 후 자동으로 닫습니다.
     */
    document.addEventListener('DOMContentLoaded', function() {
        const autoCloseAlerts = document.querySelectorAll('.msg-auto-close');
        autoCloseAlerts.forEach(function(alert) {
            setTimeout(function() {
                // Bootstrap의 Alert 인스턴스를 사용하여 부드럽게 닫기
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 3000); // 3초 후 실행
        });
    });

    /**
     * =========================================================================
     * [프론트엔드 모듈] 동적 미디어 슬라이더 엔진 (MediaSliderModule)
     * - 역할: 이미지와 유튜브 영상 통합 관리, 슬라이더 렌더링, 포인터 스와이프 제어
     * - 객체 지향 패턴으로 캡슐화하여 전역 변수 및 이벤트 오염 완벽 방지
     * =========================================================================
     */
    const MediaSliderModule = {
        /**
         * [핵심 버그 1 수정] 이미지 URL이 유튜브로 둔갑하는 버그 원천 차단
         */
        extractYoutubeId: function(url) {
            if (!url || (url.indexOf('youtube.com') === -1 && url.indexOf('youtu.be') === -1)) return null;
            const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
            return match ? match[1] : null;
        },

        generateHTML: function(containerId, mediaList, options = {}) {
            if (!mediaList || mediaList.length === 0) mediaList = ['/assets/no-logo.png'];

            const objectFit = options.objectFit || 'cover';
            const fadeClass = options.fade ? 'carousel-fade' : '';

            let transitionClass = 'carousel-transition-smooth';
            if (options.transition === 'fast') transitionClass = 'carousel-transition-fast';
            else if (options.transition === 'none') transitionClass = 'carousel-transition-none';

            let itemsHtml = '';
            let indicatorsHtml = '';

            mediaList.forEach((mediaUrl, idx) => {
                const isActive = idx === 0 ? 'active' : '';
                const ytId = this.extractYoutubeId(mediaUrl); // 강력한 도메인 검증 통과
                let mediaContent = '';

                // [최적화] 첫 번째 슬라이드는 즉시 로딩, 나머지는 지연 로딩(lazy)
                const loadAttr = idx === 0 ? 'fetchpriority="high"' : 'loading="lazy"';

                if (ytId) {
                    mediaContent = `<iframe id="${containerId}-video-${idx}" width="100%" height="100%" src="https://www.youtube.com/embed/${ytId}?enablejsapi=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen style="object-fit: ${objectFit}; pointer-events: auto;" ${loadAttr}></iframe>`;
                } else {
                    // [기능 추가] fslightbox 옵션이 활성화된 경우 <a> 태그로 감싸서 클릭 시 전체화면 확대 지원
                    if (options.useLightbox) {
                        const lightboxGroup = options.lightboxGroup || containerId;
                        mediaContent = `<a data-fslightbox="${lightboxGroup}" href="${mediaUrl}" class="d-block w-100 h-100" style="cursor: zoom-in;"><img src="${mediaUrl}" class="d-block w-100 h-100" style="object-fit: ${objectFit};" onerror="this.onerror=null; this.src='/assets/no-logo.png';" ${loadAttr}></a>`;
                    } else {
                        mediaContent = `<img src="${mediaUrl}" class="d-block w-100 h-100" style="object-fit: ${objectFit};" onerror="this.onerror=null; this.src='/assets/no-logo.png';" ${loadAttr}>`;
                    }
                }

                itemsHtml += `<div class="carousel-item h-100 ${isActive}">${mediaContent}</div>`;
                // [표준 복구] 부트스트랩 네이티브 Data API 속성 원상 복구
                indicatorsHtml += `<button type="button" data-bs-target="#${containerId}" data-bs-slide-to="${idx}" class="${isActive}"></button>`;
            });

            let controlsHtml = '';
            if (mediaList.length > 1) {
                controlsHtml = `
                    <button class="carousel-control-prev" type="button" data-bs-target="#${containerId}" data-bs-slide="prev" style="z-index: 10;">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#${containerId}" data-bs-slide="next" style="z-index: 10;">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                    <div class="carousel-indicators" style="z-index: 10;">${indicatorsHtml}</div>
                `;
            }

            return `
                <div id="${containerId}" class="carousel slide ${transitionClass} h-100 ${fadeClass}" data-bs-touch="true" ${options.interval ? `data-bs-ride="carousel" data-bs-interval="${options.interval}"` : ''}>
                    <div class="carousel-inner h-100">${itemsHtml}</div>
                    ${controlsHtml}
                </div>
            `;
        },

        /**
         * [핵심 버그 2 수정] 오른쪽 화살표 클릭 시 역방향 스크롤 오작동 해결 (PointerEvent 도입)
         */
        enableDragSwipe: function(carouselEl) {
            if (!carouselEl) return;
            let isDragging = false;
            let startX = 0;
            let isSwiped = false; // 드래그 스와이프 판별용

            carouselEl.addEventListener('pointerdown', (e) => {
                const ignoredElements = e.target.closest('button, a, .carousel-control-prev, .carousel-control-next, .carousel-indicators');
                // fslightbox 팝업용 a 태그는 스와이프가 가능해야 하므로 예외 처리합니다.
                if (ignoredElements && !ignoredElements.hasAttribute('data-fslightbox')) {
                    return;
                }

                // 부트스트랩 기본 모바일 터치 기능과의 중복 및 꼬임 방지를 위해 마우스일 경우만 커스텀 제어
                if (e.pointerType === 'touch') return;

                isDragging = true;
                isSwiped = false;
                startX = e.clientX;
                carouselEl.style.cursor = 'grabbing';
            });

            const stopDrag = (e) => {
                if (!isDragging) return;
                isDragging = false;
                carouselEl.style.cursor = 'default';

                const endX = e.clientX;
                const diffX = startX - endX;

                // 임계값 50px 이상 명확히 밀었을 때만 슬라이드 발동 (정확한 뱡항 판별)
                if (Math.abs(diffX) > 50) {
                    isSwiped = true;
                    const bsCarousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
                    if (diffX > 0) bsCarousel.next();
                    else bsCarousel.prev();
                }
            };

            carouselEl.addEventListener('pointerup', stopDrag);
            carouselEl.addEventListener('pointerleave', stopDrag);
            carouselEl.addEventListener('pointercancel', stopDrag);

            // [클릭 방지 로직] 스와이프 후 click 이벤트가 실행되어 팝업이 뜨는 것을 방지
            carouselEl.addEventListener('click', (e) => {
                if (isSwiped) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            // 이미지 드래그 고스트 현상 방지
            carouselEl.querySelectorAll('img, a').forEach(el => el.addEventListener('dragstart', e => e.preventDefault()));
        },

        init: function(containerId, options = {}) {
            const carouselEl = document.getElementById(containerId);
            if (!carouselEl || typeof bootstrap === 'undefined') return null;

            const instance = new bootstrap.Carousel(carouselEl, {
                interval: options.interval || false,
                touch: true, // 모바일 터치 지원 유지
                wrap: true // 무한 루프(원형 연결) 명시적 선언
            });

            this.enableDragSwipe(carouselEl);

            carouselEl.addEventListener('slide.bs.carousel', () => {
                carouselEl.querySelectorAll('iframe').forEach(iframe => {
                    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                });
            });

            // [기능 추가] fslightbox 기능이 쓰인 경우 동적으로 추가된 DOM을 재스캔하여 바인딩
            if (options.useLightbox && typeof refreshFsLightbox !== 'undefined') {
                refreshFsLightbox();
            }

            return instance;
        }
    };

    // [하위 호환성 유지] 기존에 사용되던 레거시 코드 호출을 위한 래퍼(Wrapper) 함수
    function generateDynamicCarousel(containerId, mediaList, options = {}) {
        return MediaSliderModule.generateHTML(containerId, mediaList, options);
    }

    function initDynamicCarousel(containerId, options = {}) {
        return MediaSliderModule.init(containerId, options);
    }

    function enableCarouselSwipe(carousel) {
        const el = typeof carousel === 'string' ? document.getElementById(carousel) : carousel;
        MediaSliderModule.enableDragSwipe(el);
    }
    /**
     * [공통 유틸] 텍스트를 클립보드에 복사하는 함수
     * @param {string} text - 복사할 텍스트
     * @param {string} successMessage - 복사 성공 시 alert으로 띄울 메시지
     */
    async function copyToClipboard(text, successMessage = '클립보드에 복사되었습니다.') {
        if (!text) {
            alert('복사할 내용이 없습니다.');
            return;
        }
        try {
            // 최신 비동기 Clipboard API 사용
            await navigator.clipboard.writeText(text);
            alert(successMessage);
        } catch (err) {
            // 구형 브라우저 또는 https가 아닌 환경을 위한 Fallback
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed"; // 화면에 보이지 않게 처리
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert(successMessage);
            } catch (err) {
                alert('이 브라우저에서는 복사를 지원하지 않습니다. 수동으로 복사해주세요.');
            }
            document.body.removeChild(textArea);
        }
    }

    /**
     * [공통 UI] 마우스 드래그로 가로 스크롤하는 기능을 활성화합니다.
     * @param {string} selector - 드래그 스크롤을 적용할 요소의 CSS 선택자
     */
    function enableDragScroll(selector) {
        const element = document.querySelector(selector);
        if (!element) return;

        let isDown = false;
        let startX;
        let scrollLeft;

        element.style.cursor = 'grab';

        element.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - element.offsetLeft;
            scrollLeft = element.scrollLeft;
            element.style.cursor = 'grabbing';
        });

        ['mouseleave', 'mouseup'].forEach(event => {
            element.addEventListener(event, () => {
                isDown = false;
                element.style.cursor = 'grab';
            });
        });

        element.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - element.offsetLeft;
            const walk = (x - startX) * 1.5; // 스크롤 속도
            element.scrollLeft = scrollLeft - walk;
        });
    }

    /**
     * [공통 UX] 페이지 새로고침 시 스크롤 위치 복원 및 해시(#) 이동
     */
    document.addEventListener('DOMContentLoaded', function() {
        const savedScrollPos = sessionStorage.getItem('pageScrollPos');
        if (savedScrollPos) {
            setTimeout(() => {
                window.scrollTo(0, parseInt(savedScrollPos, 10));
                sessionStorage.removeItem('pageScrollPos');
            }, 50);
        }

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => sessionStorage.setItem('pageScrollPos', window.scrollY));
        });

        if (window.location.hash) {
            setTimeout(() => {
                try {
                    const target = document.querySelector(window.location.hash);
                    if (target) target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                } catch (e) {
                    console.warn('Invalid hash for scrolling:', window.location.hash);
                }
            }, 300);
        }
    });

    /**
     * =========================================================================
     * [공통] 이미지 일괄 처리 관리자 (미리보기 후 저장)
     * - 페이지 새로고침 없이 이미지 추가/삭제 미리보기를 제공하고, '저장' 시점에 일괄 처리합니다.
     * =========================================================================
     */
    const imageBatchManagers = {};

    /**
     * 특정 섹션을 이미지 일괄 처리 대상으로 등록하고 초기화합니다.
     * @param {string} key - 관리자를 식별할 고유 키 (예: 'gallery', 'background')
     * @param {object} config - 설정 객체
     */
    function initImageBatchManager(key, config) {
        imageBatchManagers[key] = {
            state: {
                newFiles: [], // { tempId, file }
                deletedItems: [], // 삭제할 ID 또는 경로
            },
            config: config,
            container: document.getElementById(config.containerId),
        };

        const manager = imageBatchManagers[key]; // [버그 수정] 변수 선언 누락 해결

        // [리팩토링] SortableJS(드래그 앤 드롭) 자동 초기화 지원
        if (config.sortable && manager.container && typeof Sortable !== 'undefined') {
            Sortable.create(manager.container, {
                animation: 150,
                draggable: '.gallery-item', // 드래그할 대상 클래스를 명시적으로 지정
                filter: config.sortableFilter || '.empty-msg, .btn-add-img, button, .btn, i', // 하위 태그까지 모두 드래그 무시 처리
                preventOnFilter: false, // 중요: 버튼 고유의 클릭(삭제) 이벤트가 작동하도록 허용
                ghostClass: 'opacity-50',
                delay: 0, // 드래그 반응 속도를 즉시 반응형으로 개선 (클릭/드래그 먹통 방지)
                touchStartThreshold: 5, // 터치 후 5px 이내 흔들림은 단순 클릭으로 인정하여 삭제버튼 활성화
                fallbackOnBody: true, // 드래그 시 잔상이 가려지는 현상 차단
                forceFallback: true // [추가] 아이폰 사파리 등 모바일 환경에서 터치 충돌 없이 부드러운 드래그 강제 적용
            });
        }
    }

    /**
     * 파일 선택 시, 미리보기를 생성하고 'newFiles' 대기열에 추가합니다.
     * @param {string} key - 관리자 식별 키
     * @param {HTMLInputElement} input - 파일이 선택된 <input type="file"> 요소
     */
    function addBatchImage(key, input) {
        const manager = imageBatchManagers[key];
        if (!manager || !input.files || input.files.length === 0) return;

        const {
            container,
            config
        } = manager;
        const addBtn = container.querySelector(config.addBtnSelector);
        const emptyMsg = container.querySelector('.empty-msg');

        // Get layout config, with defaults for backward compatibility
        const itemClass = config.itemClass || 'col-6 col-md-4 gallery-item';
        const aspectRatio = config.aspectRatio || '4/3';

        Array.from(input.files).forEach(file => {
            const tempId = `new_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            const objectURL = URL.createObjectURL(file);
            manager.state.newFiles.push({
                tempId: tempId,
                file: file
            });

            if (emptyMsg) emptyMsg.classList.add('d-none');

            const div = document.createElement('div');
            div.className = itemClass;
            div.id = `${key}-item-${tempId}`;
            div.style.cursor = 'grab';
            div.innerHTML = `
                <div class="position-relative">
                    <img src="${objectURL}" class="w-100 rounded border shadow-sm opacity-75" style="aspect-ratio: ${aspectRatio}; object-fit: cover;">
                    <span class="badge bg-warning position-absolute top-0 start-0 m-1" style="font-size: 0.6rem;">NEW</span>
                    <button type="button" onclick="event.stopPropagation(); deleteBatchImage('${key}', '${tempId}', true)" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                </div>
            `;
            if (container) {
                if (addBtn) {
                    container.insertBefore(div, addBtn);
                } else {
                    container.appendChild(div);
                }
            }
        });
        input.value = '';
    }

    /**
     * 이미지를 '삭제' 대기열에 추가하고 화면에서 숨깁니다.
     * @param {string} key - 관리자 식별 키
     * @param {string|number} itemId - 삭제할 아이템의 ID 또는 경로
     * @param {boolean} isTemp - 새로 추가된 임시 아이템인지 여부
     */
    function deleteBatchImage(key, itemId, isTemp = false) {
        const manager = imageBatchManagers[key];
        if (!manager) return;
        if (!confirm("목록에서 제거하시겠습니까?\n('저장' 버튼을 눌러야 최종 반영됩니다.)")) return;

        const itemEl = document.getElementById(`${key}-item-${itemId}`);
        if (itemEl) itemEl.style.display = 'none';

        if (isTemp) {
            manager.state.newFiles = manager.state.newFiles.filter(f => f.tempId !== itemId);
            if (itemEl) itemEl.remove();
        } else {
            manager.state.deletedItems.push(itemId);
        }

        const container = manager.container;
        const emptyMsg = container.querySelector('.empty-msg');
        const visibleItems = Array.from(container.querySelectorAll('.gallery-item')).filter(el => el.style.display !== 'none');
        if (emptyMsg) {
            emptyMsg.classList.toggle('d-none', visibleItems.length > 0);
        }
    }

    /**
     * 폼 제출 시, 삭제/업로드/저장 로직을 일괄 수행합니다.
     * @param {Event} event - 폼의 submit 이벤트
     * @param {string} key - 관리자 식별 키
     */
    async function saveImageBatch(event, key) {
        event.preventDefault();
        const manager = imageBatchManagers[key];
        const form = event.target;
        if (!manager) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>반영 중...';

        try {
            // 1. 삭제 처리 (설정에 따라 AJAX 요청 또는 로컬 처리)
            if (manager.config.deleteUrl) {
                for (const id of manager.state.deletedItems) {
                    const fd = new FormData();
                    fd.append('action', manager.config.deleteActionName);
                    fd.append(manager.config.deleteIdParam, id);
                    await fetch(manager.config.deleteUrl, {
                        method: 'POST',
                        body: fd
                    });
                }
            }

            // 2. 신규 업로드 처리
            const newPathsMap = {};
            if (manager.state.newFiles.length > 0) {
                const uploadPromises = manager.state.newFiles.map(fileObj => {
                    const fd = new FormData();
                    fd.append('image', fileObj.file);
                    Object.entries(manager.config.uploadParams).forEach(([k, v]) => fd.append(k, v));
                    fd.append('mode', 'insert');
                    return fetch('/common/upload_image.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => ({
                            tempId: fileObj.tempId,
                            ...data
                        }));
                });
                const results = await Promise.all(uploadPromises);
                
                let uploadErrors = [];
                results.forEach(res => {
                    if (res.status === 'success') {
                        newPathsMap[res.tempId] = res.path;
                    } else {
                        uploadErrors.push(res.message || '알 수 없는 오류');
                    }
                });

                // [에러 방어] 사진 업로드 실패 시 점주에게 팝업으로 원인 안내
                if (uploadErrors.length > 0) {
                    alert('일부 이미지 업로드에 실패했습니다:\n' + uploadErrors.join('\n'));
                }
            }

            // 3. 제출 전 콜백 실행 (hidden input 업데이트 등)
            if (typeof manager.config.beforeSubmit === 'function') {
                manager.config.beforeSubmit(newPathsMap, manager.state.deletedItems, manager);
            }

            // [리팩토링] 화면상의 이미지 순서대로 경로(Path) 배열을 추출하여 hidden input에 자동 삽입
            if (manager.config.hiddenOrderInputId) {
                const orderInput = document.getElementById(manager.config.hiddenOrderInputId);
                if (orderInput) {
                    const items = manager.container.querySelectorAll('.gallery-item');
                    let finalPaths = [];
                    items.forEach(item => {
                        if (item.style.display !== 'none') {
                            const existingPath = item.getAttribute('data-path');
                            if (existingPath) {
                                finalPaths.push(existingPath);
                            } else {
                                const tempId = item.id.replace(`${key}-item-`, '');
                                if (newPathsMap[tempId]) finalPaths.push(newPathsMap[tempId]);
                            }
                        }
                    });
                    orderInput.value = JSON.stringify(finalPaths);
                }
            }

            // 4. 나머지 폼 데이터(텍스트 등) 저장
            await handleAjaxFormSubmit(event);

            // 5. 성공 후 새로고침하여 UI 동기화
            setTimeout(() => location.reload(), 800);

        } catch (error) {
            alert('저장 처리 중 오류가 발생했습니다: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }

    /**
     * =========================================================================
     * [공통 UX] 범용 AJAX 페이징 처리 (PJAX 패턴)
     * - 클래스에 'ajax-page-link'가 포함된 링크 클릭 시 동작
     * - 전체 페이지 새로고침 없이 부모 영역(.card-body 또는 .card)만 비동기로 갱신
     * =========================================================================
     */
    document.addEventListener('DOMContentLoaded', function() {
        // 이벤트 중복 실행 방지
        if (window.isAjaxPaginationBound) return;
        window.isAjaxPaginationBound = true;

        // 이벤트 위임(Event Delegation)을 통해 동적으로 생성된 HTML 요소에서도 동작하게 함
        document.body.addEventListener('click', async function(e) {
            const link = e.target.closest('.ajax-page-link');
            if (!link) return;

            e.preventDefault();
            const url = link.href;

            // 페이징 버튼이 속해있는 가장 가까운 부모 영역(.card-body 또는 .card)을 자동으로 인식합니다.
            const targetContainer = link.closest('.card-body') || link.closest('.card');
            if (!targetContainer) {
                window.location.href = url;
                return;
            }

            // 통신 중 시각적 피드백 (반투명 및 클릭 방지)
            targetContainer.style.transition = 'opacity 0.2s';
            targetContainer.style.opacity = '0.4';
            targetContainer.style.pointerEvents = 'none';

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('Network error');

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                // 백그라운드에서 불러온 새 페이지 HTML 구조에서 동일한 인덱스의 영역을 추출해 치환합니다.
                const className = targetContainer.classList.contains('card-body') ? '.card-body' : '.card';
                const index = Array.from(document.querySelectorAll(className)).indexOf(targetContainer);
                const newContainer = doc.querySelectorAll(className)[index];

                if (newContainer) {
                    targetContainer.innerHTML = newContainer.innerHTML;
                    // 브라우저 주소창 URL도 조용히 변경하여 '뒤로가기'를 지원합니다 (스마트한 UX)
                    window.history.pushState({
                        path: url
                    }, '', url);
                } else {
                    window.location.href = url; // 렌더링 매칭 실패시 일반 페이지 이동
                }
            } catch (err) {
                window.location.href = url;
            } finally {
                targetContainer.style.opacity = '1';
                targetContainer.style.pointerEvents = 'auto';
            }
        });

        // 사용자가 스마트폰/브라우저 '뒤로가기' 버튼을 눌렀을 때 페이지 갱신 대응
        window.addEventListener('popstate', (e) => {
            // [버그 수정] 모달(Modal) 팝업이 열린 상태에서 뒤로가기를 눌렀을 때
            // 페이지 전체가 새로고침(Refresh)되어 버리는 현상 방지
            if (document.body.classList.contains('modal-open')) {
                return; // 새로고침을 중단하고 모달 전역 닫기 스크립트에 처리를 위임
            }
            window.location.reload();
        });
    });

    /**
     * =========================================================================
     * [공통 UX] 페이지 내 스크롤 네비게이션 자동 생성 (플로팅 퀵 메뉴)
     * - 클래스에 'scroll-nav-target'이 포함된 섹션들을 스캔하여 우측 상단 플로팅 메뉴를 생성합니다.
     * =========================================================================
     */
    const ScrollNavModule = {
        init: function() {
            const targets = document.querySelectorAll('.scroll-nav-target');
            if (targets.length === 0) return; // 네비게이션 대상이 없으면 실행 안함

            // [UI 개선] 메뉴에 적용될 동적 CSS 스타일 주입 (DRY: 별도 CSS 파일 오염 없이 모듈 내부에 캡슐화)
            if (!document.getElementById('quick-nav-style')) {
                const style = document.createElement('style');
                style.id = 'quick-nav-style';
                style.innerHTML = `
                    .quick-nav-menu { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(12px); border-radius: 16px !important; padding: 12px 10px !important; box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important; border: 1px solid rgba(0,0,0,0.05) !important; max-height: 65vh; overflow-y: auto; }
                    .quick-nav-menu::-webkit-scrollbar { width: 4px; }
                    .quick-nav-menu::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.15); border-radius: 4px; }
                    .quick-nav-menu .dropdown-item { border-radius: 8px; transition: all 0.2s ease; font-size: 0.95rem; }
                    .quick-nav-menu .dropdown-item:hover { background-color: #f0f4f8; color: #004aad !important; transform: translateX(5px); }
                    .quick-nav-btn { background: linear-gradient(135deg, #004aad, #002e6b) !important; color: white !important; border: 3px solid rgba(255,255,255,0.9) !important; box-shadow: 0 8px 20px rgba(0,74,173,0.4) !important; transition: transform 0.2s ease; touch-action: none; }
                    .quick-nav-btn:hover { transform: scale(1.05); }
                    .quick-nav-btn::after { display: none !important; } /* 부트스트랩 기본 화살표 숨김 */
                `;
                document.head.appendChild(style);
            }

            // 컨테이너 생성
            const navContainer = document.createElement('div');
            navContainer.className = 'position-fixed dropdown';
            // 상단 메뉴바 바로 아래 우측 위치 (모바일 터치 및 다른 버튼들과 겹치지 않게 조절)
            navContainer.style.top = '80px';
            navContainer.style.right = '15px';
            // [버그 수정] z-index를 최상위(2050)로 올려 다른 모달이나 플로팅 바 아래로 숨는 현상 해결
            navContainer.style.zIndex = '2050';

            // 플로팅 메뉴 버튼
            const btn = document.createElement('button');
            btn.className = 'btn rounded-circle d-flex align-items-center justify-content-center dropdown-toggle quick-nav-btn';
            btn.style.width = '52px';
            btn.style.height = '52px';
            btn.setAttribute('data-bs-toggle', 'dropdown');
            btn.innerHTML = '<i class="bi bi-list-nested fs-3"></i>';

            // 드롭다운 목록
            const menu = document.createElement('ul');
            menu.className = 'dropdown-menu dropdown-menu-end border-0 mt-2 quick-nav-menu';
            menu.style.minWidth = '220px';

            // 빠른 이동 메뉴
            const headerItem = document.createElement('li');
            headerItem.innerHTML = `<h6 class="dropdown-header fw-bold text-primary border-bottom pb-3 mb-2 px-2 d-flex align-items-center"><i class="bi bi-cursor-fill me-2"></i><?php echo __('빠른 이동 메뉴'); ?></h6>`;
            menu.appendChild(headerItem);

            // [추가] 화면 맨 위로 이동하는 고정 항목
            const topItem = document.createElement('li');
            topItem.innerHTML = `<a class="dropdown-item py-2 px-3 fw-bold text-primary d-flex align-items-center justify-content-between bg-light mb-1" href="#" onclick="event.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' });"><span><?php echo __('맨 위로'); ?></span><i class="bi bi-arrow-up-circle-fill fs-5"></i></a>`;
            menu.appendChild(topItem);

            targets.forEach(target => {
                const label = target.getAttribute('data-nav-label');
                const id = target.id;
                const isIndent = target.getAttribute('data-nav-indent') === 'true';

                // 화면에 보이지 않는 요소(display:none)는 네비게이션 목록에서 제외 (예: 오프된 기능)
                if (label && id && window.getComputedStyle(target).display !== 'none') {
                    const li = document.createElement('li');
                    if (isIndent) {
                        li.innerHTML = `<a class="dropdown-item py-1 ps-4 pe-3 fw-medium text-secondary d-flex align-items-center" href="#${id}" onclick="ScrollNavModule.scrollToTarget(event, '${id}')" style="font-size: 0.85rem;"><i class="bi bi-arrow-return-right me-2 text-muted opacity-50"></i><span class="text-truncate">${label}</span></a>`;
                    } else {
                        li.innerHTML = `<a class="dropdown-item py-2 px-3 fw-bold text-dark d-flex align-items-center justify-content-between" href="#${id}" onclick="ScrollNavModule.scrollToTarget(event, '${id}')"><span>${label}</span><i class="bi bi-chevron-right small text-muted opacity-50"></i></a>`;
                    }
                    menu.appendChild(li);
                }
            });

            // DOM에 삽입
            navContainer.appendChild(btn);
            navContainer.appendChild(menu);
            document.body.appendChild(navContainer);

            // [추가] 빠른 이동 메뉴 드래그 앤 드롭 이동 기능
            let isDragging = false;
            let isDragged = false;
            let startX, startY;

            // 세션 스토리지에 저장된 이전 위치가 있다면 복원
            const savedPos = sessionStorage.getItem('quickNavPos');
            if (savedPos) {
                const pos = JSON.parse(savedPos);
                navContainer.style.top = pos.top;
                navContainer.style.left = pos.left;
                navContainer.style.right = 'auto'; // 기본 right 기준을 무효화
            }

            btn.addEventListener('pointerdown', (e) => {
                // 왼쪽 마우스 클릭 또는 터치만 허용
                if (e.button !== 0 && e.pointerType === 'mouse') return;

                isDragging = true;
                isDragged = false;
                startX = e.clientX;
                startY = e.clientY;
                btn.setPointerCapture(e.pointerId);

                // right 기준으로 배치되어 있다면 left 기준으로 변환 (드래그 좌표 계산 편의성)
                if (navContainer.style.right !== 'auto') {
                    const rect = navContainer.getBoundingClientRect();
                    navContainer.style.left = rect.left + 'px';
                    navContainer.style.right = 'auto';
                }
            });

            btn.addEventListener('pointermove', (e) => {
                if (!isDragging) return;

                const diffX = e.clientX - startX;
                const diffY = e.clientY - startY;

                // 단순 터치(클릭)와 드래그를 구분하기 위한 임계값 (5px)
                if (!isDragged && (Math.abs(diffX) > 5 || Math.abs(diffY) > 5)) {
                    isDragged = true;
                }

                if (isDragged) {
                    const rect = navContainer.getBoundingClientRect();
                    let newLeft = rect.left + diffX;
                    let newTop = rect.top + diffY;

                    // 화면 바깥으로 나가지 않도록 경계 고정 (Boundary)
                    const maxLeft = window.innerWidth - rect.width;
                    const maxTop = window.innerHeight - rect.height;

                    if (newLeft < 0) newLeft = 0;
                    if (newLeft > maxLeft) newLeft = maxLeft;
                    if (newTop < 0) newTop = 0;
                    if (newTop > maxTop) newTop = maxTop;

                    navContainer.style.left = newLeft + 'px';
                    navContainer.style.top = newTop + 'px';

                    startX = e.clientX;
                    startY = e.clientY;
                }
            });

            const stopDrag = (e) => {
                if (!isDragging) return;
                isDragging = false;
                btn.releasePointerCapture(e.pointerId);

                if (isDragged) {
                    // 드래그를 마쳤을 때 현재 위치를 세션에 저장 (페이지 이동 시에도 유지)
                    sessionStorage.setItem('quickNavPos', JSON.stringify({
                        left: navContainer.style.left,
                        top: navContainer.style.top
                    }));
                }
            };

            btn.addEventListener('pointerup', stopDrag);
            btn.addEventListener('pointercancel', stopDrag);

            // [핵심] 드래그 중이었다면 클릭 이벤트를 차단하여 드롭다운 메뉴가 열리는 것을 방지
            btn.addEventListener('click', (e) => {
                if (isDragged) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true); // 캡처링 단계에서 차단
        },
        scrollToTarget: function(e, id) {
            e.preventDefault();
            const target = document.getElementById(id);
            if (target) {
                // 상단 고정 네비게이션(Navbar)의 높이를 고려해 여백(offset)을 70px 정도로 둠
                const headerOffset = 70;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        ScrollNavModule.init();
    });
</script>

<?php
// 모달 및 갤러리 팝업 모바일 뒤로가기 제어 스크립트 전역 출력
if (function_exists('renderPopupHistoryBackScript')) {
    echo renderPopupHistoryBackScript();
}
?>

<?php
// 공통 이미지 뷰어 모달 렌더링 (lib_utils.php)
if (function_exists('renderCommonImageModal')) {
    echo renderCommonImageModal();
}
?>

</body>

>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</html>