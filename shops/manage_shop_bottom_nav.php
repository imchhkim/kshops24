<?php
/**
 * KShops24 대시보드 - 모바일 하단 네비게이션 모듈
 */
if (!isset($shop_id)) exit;
?>
<!-- [모바일 화면 전용] 하단 네비게이션 영역 -->
<nav class="bottom-nav shadow-lg">
    <a class="nav-link text-center <?php echo (($_GET['pg'] ?? 'manage_shop_dashboard') === 'manage_shop_dashboard') ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_dashboard">
        <i class="bi bi-speedometer2 d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">대시보드</span>
    </a>
    <?php
    $menu_type = 'mobile';
    if (file_exists($category_sidebar)) {
        include $category_sidebar;
    }
    ?>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'shop' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=shop">
        <i class="bi bi-shop-window d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">상점설정</span>
    </a>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'manage_shop_homepage' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_homepage">
        <i class="bi bi-window-sidebar d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">홈페이지</span>
    </a>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'manage_shop_customer' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_customer">
        <i class="bi bi-people d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">고객관리</span>
    </a>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'manage_shop_review' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_review">
        <i class="bi bi-chat-square-text d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">리뷰관리</span>
    </a>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'manage_shop_billing' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_billing">
        <i class="bi bi-credit-card d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">결제관리</span>
    </a>
    <a class="nav-link text-center <?php echo ($_GET['pg'] ?? '') === 'manage_shop_emojis' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_emojis">
        <i class="bi bi-emoji-smile d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">이모지</span>
    </a>
    <a class="nav-link text-center text-dark" href="#" data-bs-toggle="modal" data-bs-target="#manualMobileModal">
        <i class="bi bi-journal-text d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">매뉴얼들</span>
    </a>
    <a class="nav-link text-center text-danger" href="logout.php">
        <i class="bi bi-box-arrow-right d-block fs-5 mb-1"></i>
        <span style="font-size: 0.75rem;">로그아웃</span>
    </a>
</nav>

<!-- [추가] 모바일 전용 매뉴얼 목록 팝업(Modal) -->
<div class="modal fade" id="manualMobileModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 bg-light rounded-top-4 pb-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i>매뉴얼 목록</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush rounded-bottom-4">
                    <a href="manage_shop.php?pg=manual_domain" class="list-group-item list-group-item-action py-3 px-4 text-dark">
                        <i class="bi bi-link-45deg me-2 fs-5 text-primary align-middle"></i> <span class="fw-medium">외부 도메인 연결</span>
                        <i class="bi bi-chevron-right float-end text-muted small mt-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>