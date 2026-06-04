<style>
    /* 메뉴 및 UI 공통 스타일: Mobile-First 기반의 깔끔한 카드 레이아웃 */
    /* 메뉴 카드 설정 */
    .menu-item-card {
        border-radius: 15px;
        overflow: hidden;
        transition: 0.3s;
        height: 100%;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        background: #fff;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .menu-item-img {
        aspect-ratio: 4/3;
        object-fit: cover;
        width: 100%;
        background-color: #fdfdfd;
    }

    .menu-item-card .card-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    /* 텍스트 줄임 처리 */
    .menu-item-name {
        font-weight: 700;
        font-size: 1.05rem;
        min-height: 2.6em;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .menu-item-info {
        font-size: 0.8rem;
        color: #777;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.8em;
        line-height: 1.4;
    }

    .menu-item-price {
        font-weight: 700;
        margin-top: auto;
        padding-top: 10px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: flex-end;
    }

    /* 품절 효과 */
    .soldout-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        aspect-ratio: 4/3;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
        border-radius: 15px 15px 0 0;
    }

    .is-soldout .menu-item-img {
        filter: grayscale(100%);
        opacity: 0.7;
    }

    .price-strike {
        text-decoration: line-through;
        opacity: 0.6;
        font-size: 0.85rem;
        margin-right: 5px;
    }

    .x-small {
        font-size: 0.75rem;
    }

    /* 메뉴판 가로 스크롤 */
    .menu-scroll-container {
        display: flex;
        gap: 15px;
        overflow-x: auto;
        padding: 10px 5px;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .menu-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .board-img-wrapper {
        flex: 0 0 auto;
        width: 230px;
        aspect-ratio: 3/4;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #eee;
        background: #f8f9fa;
    }

    .board-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }


    /* 메뉴 섹션 타이틀 스타일 (정적 띠 형태) */
    .menu-section-title {
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        border-top: 3px solid var(--accent-color, #004aad);
        border-bottom: 1px solid #e0e0e0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 10px;
        margin-left: -15px;
        margin-right: -15px;
        padding: 15px 20px;
    }

    .menu-section-title h2 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    /* 카테고리 퀵 네비게이션 래퍼 */
    .nav-scroll-wrapper {
        position: sticky;
        /* 상점 상단 부분에서 떨어지는 값 */
        top: 53px;
        /* 로고 영역의 줄어든 높이에 맞춰 값 조정 (기존 60px) */
        z-index: 1021;
        background: #fff;
        margin-left: -15px;
        margin-right: -15px;
        border-bottom: 1px solid #eee;
    }

    /* 가로 스크롤 가능 여부를 알려주는 좌우 인디케이터 */
    .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        /* 터치 방해 금지 */
        z-index: 10;
        color: var(--accent-color, #004aad);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .scroll-indicator.left {
        left: 0;
        background: linear-gradient(to right, #fff 40%, rgba(255, 255, 255, 0) 100%);
    }

    .scroll-indicator.right {
        right: 0;
        background: linear-gradient(to left, #fff 40%, rgba(255, 255, 255, 0) 100%);
    }

    .scroll-indicator.visible {
        opacity: 1;
    }

    /* 카테고리 퀵 네비게이션 스타일 */
    .category-nav-scroll {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
        padding: 12px 15px;
    }

    .category-nav-scroll::-webkit-scrollbar {
        display: none;
    }

    .category-nav-btn {
        flex: 0 0 auto;
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #555;
        text-decoration: none;
        transition: 0.2s;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
    }

    .category-nav-btn:active,
    .category-nav-btn:focus,
    .category-nav-btn.active {
        background: var(--accent-color, #004aad);
        color: white;
        border-color: var(--accent-color, #004aad);
    }

    /* [강화] 카테고리 제목 바: 좌우를 꽉 채우는 고급스러운 띠(Belt) 디자인 - 모든 테마 공통 적용 */
    .cat-title-bar {
        position: sticky;
        /* [수정] 상단 메인헤더 높이 + 카테고리 네비게이션 높이를 합산한 값. 오버랩 방지 !!! */
        top: 117px;
        /* 기존 124px에서 nav-scroll-wrapper가 줄어든 만큼 같이 줄임 */
        z-index: 1020;
        background: linear-gradient(90deg, #1a1a1a 0%, var(--accent-color, #004aad) 50%, #1a1a1a 100%);
        color: #ffffff;
        font-size: 1.4rem;
        /* 더 크게 키움 */
        font-weight: 800;
        /* 최대 굵기 */
        text-align: center;
        padding: 3px 20px;
        margin-top: 0px;
        margin-bottom: 30px;
        border-radius: 0;
        /* 띠 모양을 위해 각지게 설정 */
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        letter-spacing: -0.5px;
        /* 컨테이너 좌우 여백을 무시하고 꽉 채우는 벨트 효과 */
        margin-left: -15px;
        margin-right: -15px;
        border-top: 2px solid rgba(255, 255, 255, 0.1);
        border-bottom: 2px solid rgba(0, 0, 0, 0.2);
    }

    .cat-title-bar i {
        font-size: 1.1rem;
        opacity: 0.8;
    }

    @media (max-width: 768px) {
        .hero-section {
            height: 35vh;
        }

        .board-img-wrapper {
            width: 160px;
        }
    }

    /* 플로팅 카트 바 */
    #floating-cart-bar,
    #floating-wishlist-bar {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1030;
        width: 95%;
        max-width: 600px;
    }

    .cart-btn-main {
        background: #004aad;
        color: white;
        border: none;
        width: 100%;
        padding: 12px 15px;
        border-radius: 50px;
        font-weight: 700;
        box-shadow: 0 8px 20px rgba(0, 74, 173, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
    }

    .cart-btn-secondary {
        background: #6c757d;
        color: white;
        border: none;
        width: 100%;
        padding: 12px 15px;
        border-radius: 50px;
        font-weight: 700;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 0.9rem;
    }

    .cart-btn-secondary i {
        margin-right: 5px;
    }

    .fnb_shop-info-summary-section .info-item span {
        font-size: 0.9rem;
        font-weight: 600;
    }

    /* 모달 디자인 커스텀 */
    .modal-content {
        border-radius: 24px;
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 1.5rem;
    }

    .qty-control-btn {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
        border: 1px solid #dee2e6;
        background: white;
    }

    .qty-control-btn:active {
        background: #f8f9fa;
        transform: scale(0.95);
    }

    .cart-receipt-item {
        padding: 12px 0;
        border-bottom: 1px dashed #e0e0e0;
    }

    .cart-receipt-item:last-child {
        border-bottom: none;
    }

    /* [추가] 메뉴 상세 모달 미디어 영역 스타일 */
    .detail-media-container {
        width: 100%;
        aspect-ratio: 4/3;
        background-color: #f0f0f0;
        /* 이미지 로딩 전 배경색 */
        overflow: hidden;
    }

    /* 카루셀 내부 요소들이 4:3 비율을 꽉 채우도록 강제 */
    .detail-media-container .carousel,
    .detail-media-container .carousel-inner,
    .detail-media-container .carousel-item {
        width: 100%;
        height: 100%;
    }

    .detail-media-container img,
    .detail-media-container iframe {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* [추가] 카트 담기 애니메이션 */
    .fly-to-cart {
        position: fixed;
        z-index: 1080;
        /* 모달보다 위에 표시 */
        border-radius: 50%;
        transition: all 0.8s cubic-bezier(0.5, -0.5, 1, 1);
        object-fit: cover;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    /* [수정] 모달 오픈 시 레이아웃 밀림(shift) 현상 방지 */
    body.modal-open {
        /* Bootstrap이 자동으로 처리하지만, 일부 환경(macOS 등)에서의 충돌을 막기 위해 명시적으로 재선언합니다. */
        overflow: hidden;
        padding-right: var(--bs-scrollbar-width, 0) !important;
    }

    body.modal-open .sticky-top {
        /* 상단 고정 메뉴바가 밀리지 않도록 스크롤바 너비만큼 여백을 추가합니다. */
        padding-right: var(--bs-scrollbar-width, 0) !important;
    }

    body.modal-open #floating-cart-bar {
        /* [최종 수정] 모달 오픈 시 플로팅 바가 body 컨텐츠 중앙과 정렬이 틀어지는 것을 방지합니다. */
        /* 스크롤바 너비의 절반만큼 왼쪽으로 이동시켜 body 컨텐츠의 새로운 중앙에 맞춥니다. */
        /* 이 방법은 width나 padding을 변경하지 않으므로 버튼 크기 변형이 발생하지 않습니다. */
        left: calc(50% - var(--bs-scrollbar-width, 0px) / 2);
    }

    /* 유효성 검사 실패 시 빨간색 테두리 강조 및 떨림 애니메이션 */
    .is-invalid-pulse {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        animation: pulseError 0.4s ease;
    }

    @keyframes pulseError {
        0% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-4px);
        }

        50% {
            transform: translateX(4px);
        }

        75% {
            transform: translateX(-4px);
        }

        100% {
            transform: translateX(0);
        }
    }

    /* 다크모드 대응 (모든 기본 CSS가 선언된 이후 마지막에 덮어쓰기 위해 하단 배치) */
    <?php if (($shop['shop_skin'] ?? '') === 'dark'): ?>.menu-item-card {
        background: #2a2a2a;
        color: #fff;
    }

    .menu-item-info {
        color: #aaa;
    }

    .category-nav-scroll {
        background: #1a1a1a;
        border-bottom-color: #333;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
    }

    .category-nav-btn {
        background: #333;
        border-color: #444;
        color: #ccc;
    }

    .menu-section-title {
        background: #1a1a1a;
        border-bottom-color: #333;
    }

    /* 메뉴 상세 모달 미디어 영역 덮어쓰기 */
    .detail-media-container {
        background-color: #000;
    }

    <?php endif; ?>
</style>