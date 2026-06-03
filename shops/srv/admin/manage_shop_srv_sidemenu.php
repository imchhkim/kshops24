<?php

/**
 * F&B(식당/카페) 카테고리 전용 사이드바 메뉴
 * 위치: /public_html/shops/fnb/admin/manage_shop_srv_sidemenu.php
 */
if (!isset($shop)) exit;

$menu_type = $menu_type ?? 'pc';

if ($menu_type === 'pc'):
?>
    <hr class="my-2">
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_reservations' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_reservations">
        <i class="bi bi-calendar2-check me-2"></i> 예약 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_srv' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_srv">
        <i class="bi bi-card-checklist me-2"></i> 서비스 관리
    </a>
    <a class="nav-link rounded-3 mb-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_srv_policy' ? 'active' : 'text-dark'; ?>" href="manage_shop.php?pg=manage_shop_srv_policy">
        <i class="bi bi-sliders me-2"></i> 서비스 정책 관리
    </a>
    <hr class="my-2">
<?php else: ?>

    <!-- 모바일 하단 내비게이션용 아이템 -->
    <a href="manage_shop.php?pg=manage_shop_reservations"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_reservations' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-calendar2-check fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">예약관리</span>
    </a>
    <a href="manage_shop.php?pg=manage_shop_srv"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_srv' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-card-checklist fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">서비스관리</span>
    </a>
    <a href="manage_shop.php?pg=manage_shop_srv_policy"
        class="nav-link text-center rounded-3 p-2 flex-grow-1 <?php echo ($_GET['pg'] ?? '') === 'manage_shop_srv_policy' ? 'bg-danger text-white shadow-sm fw-bold' : 'bg-danger-subtle text-danger border-0'; ?>"
        style="min-width: 80px; transition: all 0.2s;">
        <i class="bi bi-sliders fs-5 d-block mb-1"></i>
        <span style="font-size:0.65rem;">정책관리</span>
    </a>
<?php endif; ?>