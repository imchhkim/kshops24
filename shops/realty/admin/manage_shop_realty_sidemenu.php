<?php

/**
 * F&B(식당/카페) 카테고리 전용 사이드바 메뉴
 * 위치: /public_html/shops/fnb/admin/manage_shop_realty_sidemenu.php
 */
if (!isset($shop)) exit;

$menu_type = $menu_type ?? 'pc';

if ($menu_type === 'pc'):
?>
    <hr class="my-2">
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_inquiries' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_inquiries">
        <i class="bi bi-list-stars me-2"></i> 문의 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_item' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_item">
        <i class="bi bi-list-stars me-2"></i> 매물 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_item_policy' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_item_policy">
        <i class="bi bi-list-stars me-2"></i> 매물 정책 관리
    </a>
    <hr class="my-2">
<?php else: ?>

    <!-- 모바일 하단 내비게이션용 아이템 -->
    <a href="manage_shop.php?pg=manage_shop_inquiries"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_inquiries' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-list-task fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">문의관리</span>
    </a>
    <a href="manage_shop.php?pg=manage_shop_item"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_item' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-list-task fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">매물관리</span>
    </a>
    <a href="manage_shop.php?pg=manage_shop_item_policy"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_item_policy' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-list-task fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">정책관리</span>
    </a>
<?php endif; ?>