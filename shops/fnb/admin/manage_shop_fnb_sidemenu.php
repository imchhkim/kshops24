<?php

/**
 * F&B(식당/카페) 카테고리 전용 사이드바 메뉴
 * 위치: /public_html/shops/fnb/admin/manage_shop_fnb_sidemenu.php
 */
if (!isset($shop)) exit;

$menu_type = $menu_type ?? 'pc';

if ($menu_type === 'pc'):
?>
    <hr class="my-2">
<?php
    if (($shop['is_delivery_available'] ?? 1) == 1) {
?>
    <!-- PC용 사이드 메뉴 -->
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_orders' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_orders">
        <i class="bi bi-cart-check me-2"></i> 주문/배달 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_sales' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_sales">
        <i class="bi bi-graph-up-arrow me-2"></i> 주문 매출 관리
    </a>
<?php
    }
?>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_menu' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_menu">
        <i class="bi bi-grid-3x3-gap me-2"></i> 메뉴 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_menu_policy' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_menu_policy">
        <i class="bi bi-ui-checks me-2"></i> 메뉴 정책 관리
    </a>
    <hr class="my-2">
    
<?php else:
    if (($shop['is_delivery_available'] ?? 1) == 1) {
?>
    <!-- 모바일 하단 내비게이션용 아이템 -->
    <a href="manage_shop.php?pg=manage_shop_orders"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_orders' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-bell fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">주문배달관리</span>
    </a>

    <a href="manage_shop.php?pg=manage_shop_sales"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_sales' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-graph-up-arrow fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">배달매출관리</span>
    </a>
<?php
    }
?>
    <a href="manage_shop.php?pg=manage_shop_menu"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_menu' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-grid-3x3-gap fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">메뉴관리</span>
    </a>

    <a href="manage_shop.php?pg=manage_shop_menu_policy"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_menu_policy' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-ui-checks fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">상점정책</span>
    </a>
<?php endif; ?>