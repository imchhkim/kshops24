<style>
    /* FNB 공통 스타일과 99% 호환되도록 클래스명 유지 */
    .menu-item-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease-in-out;
        /* [수정] 모든 속성에 부드러운 전환 효과 적용 */
        height: 100%;
        border: 1px solid #f0f0f0;
        /* [수정] 기존 box-shadow 대신 은은한 테두리 추가 */
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        /* [수정] 기본 그림자를 더 은은하게 변경 */
        background: #fff;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    /* [추가] 카드에 마우스를 올렸을 때 입체감과 상호작용 효과 부여 */
    .menu-item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .menu-item-img {
        aspect-ratio: 4/3;
        object-fit: cover;
        width: 100%;
        background-color: #fdfdfd;
    }

    /* 모달창 내부 사진 및 유튜브 영상 4:3 비율 강제 고정 */
    .detail-media-container img,
    .detail-media-container iframe {
        width: 100% !important;
        height: 100% !important;
        aspect-ratio: 4/3 !important;
        object-fit: cover;
    }

    .menu-item-card .card-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

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
        text-align: left;
        padding-left: 10px;
        /* [추가] 왼쪽 여백 부여 */
        padding-right: 10px;
        /* [추가] 우측 여백도 함께 주어 균형을 맞춤 */
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 5.6em;
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

    .soldout-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        aspect-ratio: 4/3;
        background: rgba(0, 0, 0, 0.5);
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

    .nav-scroll-wrapper {
        position: sticky;
        top: 53px;
        /* 로고 영역의 줄어든 높이에 맞춰 값 조정 (기존 60px) */
        z-index: 1021;
        background: #fff;
        margin-left: -15px;
        margin-right: -15px;
        border-bottom: 1px solid #eee;
    }

    .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
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

    .cat-title-bar {
        position: sticky;
        top: 117px; // 카테고리 네비게이션 바 바로 아래에 고정 (로고 53px + 네비게이션 64px)
        z-index: 1020;
        background: linear-gradient(90deg, #1a1a1a 0%, var(--accent-color, #004aad) 50%, #1a1a1a 100%);
        color: #ffffff;
        font-size: 1.4rem;
        font-weight: 800;
        text-align: center;
        padding: 3px 20px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-left: -15px;
        margin-right: -15px;
        border-top: 2px solid rgba(255, 255, 255, 0.1);
        border-bottom: 2px solid rgba(0, 0, 0, 0.2);
    }

    #floating-cart-bar {
        position: fixed;
        bottom: 20px;
        left: calc(50% - var(--bs-scrollbar-width, 0px) / 2);
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

    .fnb_shop-info-summary-section .info-item span {
        font-size: 0.9rem;
        font-weight: 600;
    }

    /* [UX 개선] 관심물건 등록(빈 하트) 버튼에 마우스가 올라가 있을 때 빨간색으로 채워지는 부트스트랩 기본 호버 효과 제거.
       이를 통해 클릭 직후 마우스가 버튼 위에 머물러 있어도 상태(등록/해제)를 직관적으로 구분할 수 있게 합니다. */
    #btn-wishlist.btn-outline-danger:hover {
        background-color: transparent !important;
        color: #dc3545 !important;
    }
</style>