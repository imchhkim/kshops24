<?php
/**
 * KShops24 대시보드 - PC 사이드바 네비게이션 모듈
 */
if (!isset($shop_id)) exit;
?>
<!-- [PC 화면 전용] 좌측 사이드바 네비게이션 영역 -->
<nav class="sidebar">
    <div class="p-4 text-center border-bottom mb-3">
        <h4 class="fw-bold mb-0" style="color: <?php echo $dash_brand_color; ?>;">KShops24</h4>
        <small class="text-muted"><?= htmlspecialchars($shop_category_label) ?></small>
    </div>

    <!-- 공통 관리자 메뉴 목록 -->
    <div class="nav flex-column px-3 sidebar-menu-scroll pb-4">
        <a class="nav-link rounded-3 mb-1 <?php echo (($_GET['pg'] ?? 'manage_shop_dashboard') === 'manage_shop_dashboard') ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_dashboard">
            <i class="bi bi-speedometer2 me-2"></i> 대시보드
        </a>

        <?php
        // [동적 라우팅] 현재 상점의 카테고리(fnb, beauty, realty 등)에 맞춰 특화된 사이드바 메뉴를 자동 로드합니다.
        $category_sidebar = __DIR__ . "/{$shop_category}/admin/manage_shop_{$shop_category}_sidemenu.php";
        $menu_type = 'pc';
        if (file_exists($category_sidebar)) {
            include $category_sidebar;
        }
        ?>

        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'shop' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=shop">
            <i class="bi bi-shop-window me-2"></i> 상점 관리
        </a>
        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_homepage' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_homepage">
            <i class="bi bi-window-sidebar me-2"></i> 홈페이지 관리
        </a>
        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_customer' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_customer">
            <i class="bi bi-people me-2"></i> 고객 관리
        </a>
        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_review' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_review">
            <i class="bi bi-chat-square-text me-2"></i> 리뷰 관리
        </a>
        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_billing' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_billing">
            <i class="bi bi-credit-card me-2"></i> 결제 관리
        </a>

        <hr class="my-4">
        <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_emojis' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_emojis">
            <i class="bi bi-emoji-smile me-2"></i> 이모지 관리
        </a>

        <!-- [추가] 매뉴얼들 목록 (아코디언) -->
        <?php $is_manual_active = (($_GET['pg'] ?? '') === 'manual_domain'); ?>
        <a class="nav-link rounded-3 mb-1 <?php echo $is_manual_active ? 'text-primary fw-bold' : 'text-dark'; ?>" data-bs-toggle="collapse" href="#manualMenuCollapse" role="button" aria-expanded="<?php echo $is_manual_active ? 'true' : 'false'; ?>" aria-controls="manualMenuCollapse">
            <i class="bi bi-journal-text me-2"></i> 매뉴얼들 <i class="bi bi-chevron-down float-end mt-1" style="font-size: 0.8rem;"></i>
        </a>
        <div class="collapse <?php echo $is_manual_active ? 'show' : ''; ?>" id="manualMenuCollapse">
            <div class="nav flex-column ms-3 mb-1">
                <a class="nav-link rounded-3 py-2 <?php echo $is_manual_active ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manual_domain">
                    <i class="bi bi-link-45deg me-1 <?php echo $is_manual_active ? '' : 'text-primary'; ?>"></i> 외부 도메인 연결
                </a>
            </div>
        </div>

        <a class="nav-link text-danger mt-2" href="logout.php">
            <i class="bi bi-box-arrow-right me-2"></i> 로그아웃
        </a>
    </div>
</nav>