<<<<<<< HEAD
<?php

/**
 * KShops24 식당/카페 전용 상세 뷰 (F&B Type)
 * [업데이트: 2026-02-21]
 * - 상세 내용: 메뉴 카테고리별 그룹화 렌더링 및 모바일 UX(가로 스크롤) 강화
 * - 수정 내용: 경로 최적화 및 기타 메뉴 중복 출력 버그 수정
 * - 특징: Mobile-First 디자인, 카트 LocalStorage 관리, 카카오 세션 연동
 */

// 세션이나 인클루드 경로를 통해 $shop 데이터가 사전에 정의되어 있어야 함
if (!isset($shop)) exit;

// ==========================================================
// 1. 데이터 로드 섹션 (DB Query)
// ==========================================================

// [1-1] 실물 메뉴판 이미지 로드: 종이 메뉴판을 찍어서 올린 사진 리스트를 가져옵니다.
$stmt_boards = $pdo->prepare("SELECT board_img_path FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_boards->execute([$shop['id']]);
$menu_boards = $stmt_boards->fetchAll();

// [1-2] 개별 메뉴 아이템 로드: 숨김 처리되지 않은 모든 메뉴를 카테고리 순서 및 설정된 정렬 순서대로 가져옵니다.
$stmt_items = $pdo->prepare("
    SELECT m.*, c.cat_name, c.translations AS cat_translations 
    FROM shop_items m 
    LEFT JOIN shop_item_categories c ON m.cat_id = c.id 
    WHERE m.shop_id = ? AND m.is_hide = 0 
    ORDER BY c.sort_order ASC, m.sort_order ASC, m.id DESC
");
$stmt_items->execute([$shop['id']]);
$all_menus = $stmt_items->fetchAll();

// ==========================================================
// 2. 데이터 분류 로직 (Array Filtering)
// ==========================================================
// 화면의 각 섹션(할인, 신메뉴, 카테고리별 등)에 뿌려줄 데이터를 배열로 미리 나눕니다.
$discount_items = [];    // 할인 메뉴
$new_items = [];         // 신메뉴
$best_items = [];        // 인기메뉴
$category_menus = [];    // 카테고리별 일반 메뉴
$no_category_items = []; // 기타 메뉴
$search_results = [];    // [추가] 다국어 검색을 위한 결과 배열

foreach ($all_menus as $m) {
    // [검색 로직] 언어 설정에 관계없이 원본 텍스트와 번역된 데이터 전체를 모두 검색하여 완벽한 매칭 지원
    if (!empty($search_keyword)) {
        $raw_name = $m['item_name'] ?? '';
        $raw_info = $m['item_info'] ?? '';
        $raw_trans = $m['translations'] ?? '';

        if (
            mb_stripos($raw_name, $search_keyword) !== false ||
            mb_stripos($raw_info, $search_keyword) !== false ||
            mb_stripos($raw_trans, $search_keyword) !== false
        ) {
            $search_results[] = $m;
        }
    }

    // 1. 하이라이트 섹션용 데이터 수집 (중복 수집 허용)
    if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0) {
        $discount_items[] = $m;
    }
    if ($m['is_new'] == 1) {
        $new_items[] = $m;
    }
    if ($m['is_best'] == 1) {
        $best_items[] = $m;
    }

    // 2. 전체 메뉴 섹션용 데이터 수집 (카테고리별 그룹화 - 모든 메뉴 빠짐없이 포함)
    if (!empty($m['cat_name'])) {
        $category_menus[$m['cat_name']][] = $m;
    } else {
        $no_category_items[] = $m;
    }
}

// ==========================================================
// 3. UI 렌더링 헬퍼 함수
// ==========================================================
// 반복되는 메뉴 카드 디자인을 한 곳에서 관리하기 위한 렌더링 함수입니다.
if (!function_exists('renderMenuItem')) {
    function renderMenuItem($item)
    {
        global $currency_symbol; // 부모 파일에서 선언된 화폐 기호 전역 변수 가져오기

        $soldout = ($item['is_soldout'] == 1);
        $has_discount = (!empty($item['item_discount_rate']) && $item['item_discount_rate'] > 0);

        // [이미지 처리 로직]: 다중 이미지(JSON) 대응. 배열이면 첫 번째 사진을, 아니면 기존 문자열 경로를 사용합니다.
        $item_img_data = $item['item_img'] ?? '';
        $item_img = '/assets/no-logo.png'; // 기본 이미지
        $img_count = 0; // 추가된 사진 장수 카운트

        if (!empty($item_img_data)) {
            // 1. 특수문자 디코딩 (웹 방화벽 등에 의해 &quot; 로 치환되었을 경우 대비)
            $clean_data = htmlspecialchars_decode($item_img_data);
            $decoded = json_decode($clean_data, true);

            // 2. 이중 JSON 인코딩 방어 (문자열 안에 또 배열 문자열이 있는 경우)
            if (is_string($decoded) && strpos(trim($decoded), '[') === 0) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded) && !empty($decoded[0])) {
                $item_img = $decoded[0];
                $img_count = count(array_filter($decoded)); // 빈 값 제외한 실제 업로드된 이미지 수
            } else if (isset($item_img_data[0]) && $item_img_data[0] !== '[') {
                // JSON 배열 형식이 아닌 일반 텍스트 경로 데이터인 경우에만 대응
                $item_img = $item_img_data;
                $img_count = 1;
            } else {
                // 3. 파싱에 실패했을 경우 정규식으로 첫 번째 이미지 경로 강제 추출 (최후의 보루)
                if (preg_match('/(\/[^"\'\s\\\\]+\.(?:jpg|jpeg|png|gif|webp))/i', $clean_data, $matches)) {
                    $item_img = $matches[1];
                    $img_count = 1;
                }
            }
        }

        // [다국어 적용] 한국어(기본값) fallback 처리
        $disp_item_name = function_exists('t_db') ? t_db($item['item_name'], $item['translations'] ?? '', 'item_name') : $item['item_name'];
        $disp_item_info = function_exists('t_db') ? t_db($item['item_info'], $item['translations'] ?? '', 'item_info') : $item['item_info'];

        $export_item = $item;
        $export_item['item_name'] = $disp_item_name;
        $export_item['item_info'] = $disp_item_info;
        $safe_item_json = htmlspecialchars(json_encode($export_item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

?>
        <!-- 메뉴 아이템 카드 -->
        <div class="col-6 col-md-4 col-lg-3">
            <div class="menu-item-card <?php echo $soldout ? 'is-soldout' : ''; ?>"
                <?php if (!$soldout): ?>onclick="openMenuDetailModalById(<?php echo $item['id']; ?>)" <?php endif; ?>
                style="cursor: pointer;">
                <!-- 배지 레이어 -->
                <div class="position-absolute top-0 start-0 m-2 z-3 d-flex flex-column gap-1 align-items-start">
                    <?php if ($has_discount && !$soldout): ?>
                        <span class="badge bg-danger shadow-sm py-2 px-3 rounded-pill" style="font-size: 0.85rem;">-<?php echo $item['item_discount_rate']; ?>%</span>
                    <?php endif; ?>

                    <?php if ($item['is_best'] == 1 && !$soldout): ?>
                        <span class="badge bg-warning text-dark shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🌟 BEST</span>
                    <?php endif; ?>

                    <?php if ($item['is_new'] == 1 && !$soldout): ?>
                        <span class="badge bg-info text-white shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🔥 NEW</span>
                    <?php endif; ?>

                    <?php if (!empty($item['item_youtube_url']) && !$soldout): ?>
                        <span class="badge bg-danger shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;"><i class="bi bi-play-btn-fill me-1"></i>VIDEO</span>
                    <?php endif; ?>
                </div>

                <!-- [추가] 다중 이미지 알림 배지 (우측 상단) -->
                <?php if ($img_count > 1 && !$soldout): ?>
                    <div class="position-absolute top-0 end-0 m-2 z-3">
                        <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.7rem; backdrop-filter: blur(4px);">
                            <i class="bi bi-images me-1"></i><?php echo $img_count; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($soldout): ?>
                    <div class="soldout-overlay"><span class="soldout-badge"><?php echo __('품절'); ?></span></div>
                <?php endif; ?>

                <img src="<?php echo function_exists('getThumbnailPath') ? getThumbnailPath($item_img) : $item_img; ?>"
                    id="menu-img-<?php echo $item['id']; ?>"
                    class="menu-item-img"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                    onerror="this.onerror=null; this.src='/assets/no-logo.png';"
                    alt="<?php echo htmlspecialchars($disp_item_name); ?>">

                <div class="card-body text-center">
                    <div class="menu-item-name"><?php echo htmlspecialchars($disp_item_name); ?></div>

                    <div class="menu-item-info">
                        <?php echo !empty($disp_item_info) ? nl2br(htmlspecialchars($disp_item_info)) : '&nbsp;'; ?>
                    </div>

                    <div class="menu-item-price d-flex flex-column align-items-center mt-2">
                        <div class="text-center">
                            <?php if ($soldout): ?>
                                <span class="price-strike"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <div class="text-danger small fw-bold"><?php echo __('품절'); ?></div>
                            <?php else: ?>
                                <?php if ($has_discount): ?>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="price-strike x-small text-muted mb-0"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                        <span class="text-primary fw-bold"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_discount_price'] ?? 0)); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-dark"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$soldout): ?>
                            <?php
                            global $shop;
                            if (($shop['is_delivery_available'] ?? 1) == 1): ?>
                                <button class="btn btn-sm btn-primary rounded-pill px-4 py-2 mt-3 w-100 shadow-sm"
                                    data-item="<?php echo $safe_item_json; ?>"
                                    onclick="event.stopPropagation(); openQtyModal(JSON.parse(this.getAttribute('data-item')))">
                                    <i class="bi bi-cart-plus me-1"></i> <?php echo __('카트 담기'); ?>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-4 py-2 mt-3 w-100 shadow-sm wishlist-btn-<?php echo $item['id']; ?>"
                                    data-item="<?php echo $safe_item_json; ?>"
                                    onclick="event.stopPropagation(); toggleWishlist(JSON.parse(this.getAttribute('data-item')), this)">
                                    <i class="bi bi-heart me-1 wishlist-icon-<?php echo $item['id']; ?>"></i> <span class="wishlist-text-<?php echo $item['id']; ?>"><?php echo __('찜하기'); ?></span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
?>

<?php include_once __DIR__ . '/includes/fnb_styles.php'; ?>

<!-- F&B 전용 추가 정보 섹션 (배달 정책 및 공지사항) -->
<section class="fnb_shop-info-summary-section">
    <!-- [배달 정책 요약] 메인 바 아래에 식당 전용 배달 정보를 추가로 표시 -->
    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
        <div class="bg-light border-bottom py-1">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-center gap-2 small text-muted">
                    <?php
                    $methods = [];
                    if (($shop['is_delivery_available'] ?? 1) == 1) {
                        $methods[] = __('배달');
                        $methods[] = __('매장픽업');
                    }
                    $disp_methods = implode(' / ', $methods);
                    
                    $base_fee_info = !empty($shop['delivery_fee_info']) ? $shop['delivery_fee_info'] : '0';
                    $base_payment = !empty($shop['payment_methods']) ? $shop['payment_methods'] : 'Cash';
                    $disp_fee_info = function_exists('t_db') ? t_db($base_fee_info, $shop['policy_translations'] ?? '', 'delivery_fee_info') : $base_fee_info;
                    $disp_payment = function_exists('t_db') ? t_db($base_payment, $shop['policy_translations'] ?? '', 'payment_methods') : $base_payment;

                    // 배달 가능 시간이 비어있으면 오늘 요일의 영업시간을 동적으로 가져옵니다. (공용 헬퍼 함수 사용)
                    $disp_delivery_hours = $shop['delivery_hours'] ?? '';
                    if (empty($disp_delivery_hours)) {
                        $disp_delivery_hours = function_exists('getTodayBusinessHours') ? getTodayBusinessHours($shop['business_hours'] ?? '') : '24' . __('시간');
                    }
                    ?>
                    <span><i class="bi bi-shop me-1"></i> <strong><?php echo htmlspecialchars($disp_methods); ?></strong></span>
                    <span><i class="bi bi-stopwatch me-1"></i> <?php echo __('배달가능시간') . ':'; ?> <strong class="<?php echo $disp_delivery_hours === __('휴무') ? 'text-danger' : ''; ?>"><?php echo htmlspecialchars($disp_delivery_hours); ?></strong></span>
                    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?><span><i class="bi bi-truck me-1"></i> <?php echo __('배달비 안내') . ':'; ?> <strong><?php echo htmlspecialchars($disp_fee_info); ?></strong></span><?php endif; ?>
                    <span><i class="bi bi-credit-card me-1"></i> <?php echo __('결제') . ':'; ?> <strong><?php echo htmlspecialchars($disp_payment); ?></strong></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- [추가] 부모 파일(shop_view.php)에서 생성한 공지사항 및 공통 검색창 UI 출력 -->
<?php echo $common_notice_ui ?? ''; ?>
<?php echo $common_search_form_ui ?? ''; ?>

<!-- [추가] 메뉴 검색 결과 영역 -->
<div class="container mb-4">
    <?php if (!empty($search_keyword)): ?>
        <section id="search-results-section" class="scroll-nav-target">
            <div class="menu-section-title">
                <h2><i class="bi bi-search me-2 text-primary"></i><?php echo __('검색 결과'); ?> <span class="text-primary fs-5">(<?php echo count($search_results); ?>)</span></h2>
            </div>
            <?php if (count($search_results) > 0): ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($search_results as $item) renderMenuItem($item); ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white border rounded-4 shadow-sm">
                    <i class="bi bi-search text-muted mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark"><?php echo __('검색된 메뉴가 없습니다.'); ?></h5>
                    <p class="text-muted small"><?php echo __('다른 검색어로 다시 시도해 보세요.'); ?></p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php
// [섹션 노출 제어]: 관리자 페이지에서 설정한 순서(sort_order)에 따라 섹션들을 동적으로 정렬합니다.
$display_sections = [
    'menu_board' => ['order' => (int)($ui['order_menu_board'] ?? 1), 'active' => !empty($menu_boards)],
    'discount'   => ['order' => (int)($ui['order_discount_menu'] ?? 2), 'active' => !empty($discount_items)],
    'new'        => ['order' => (int)($ui['order_new_menu'] ?? 3), 'active' => !empty($new_items)],
    'best'       => ['order' => (int)($ui['order_best_menu'] ?? 4), 'active' => !empty($best_items)],
    'all'        => ['order' => (int)($ui['order_all_menu'] ?? 5), 'active' => (!empty($category_menus) || !empty($no_category_items))]
];

// 설정된 순서(order)에 따라 정렬
uasort($display_sections, function ($a, $b) {
    return $a['order'] <=> $b['order'];
});
?>

<div class="container mt-2 mb-1 pb-1">
    <?php foreach ($display_sections as $section_key => $section): ?>
        <?php if (!$section['active']) continue; ?>

        <!-- 1. 실물 메뉴판 섹션: 가로 스크롤(Slide) 형태로 제공 -->
        <?php if ($section_key === 'menu_board'): ?>
            <section id="menu-boards" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_menu_board'] ?? __(FNB_DEFAULT_LABEL_MENU_BOARD)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-book me-2"></i><?php echo htmlspecialchars($ui['label_menu_board'] ?? __(FNB_DEFAULT_LABEL_MENU_BOARD)); ?></h2>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i><?php echo __('메뉴판을 좌우로 밀어서 확인하세요.'); ?></p>
                    <div class="text-muted small fw-bold"><i class="bi bi-arrow-left-right"></i> Slide</div>
                </div>
                <div class="menu-scroll-container">
                    <?php foreach ($menu_boards as $board): ?>
                        <div class="board-img-wrapper shadow-sm">
                            <a data-fslightbox="menu-gallery" href="<?php echo $board['board_img_path']; ?>">
                                <img src="<?php echo function_exists('getThumbnailPath') ? getThumbnailPath($board['board_img_path']) : $board['board_img_path']; ?>" class="w-100 h-auto" loading="lazy" onerror="this.onerror=null; this.src='/assets/no-logo.png';">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 2. 할인 메뉴 섹션 (Special Offers) -->
        <?php elseif ($section_key === 'discount'): ?>
            <section id="section-discount" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_discount_menu'] ?? __(FNB_DEFAULT_LABEL_DISCOUNT_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-graph-down-arrow me-2"></i><?php echo htmlspecialchars($ui['label_discount_menu'] ?? __(FNB_DEFAULT_LABEL_DISCOUNT_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($discount_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 3. 신메뉴 섹션 (New Arrivals) -->
        <?php elseif ($section_key === 'new'): ?>
            <section id="section-new" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_new_menu'] ?? __(FNB_DEFAULT_LABEL_NEW_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-truck me-2"></i><?php echo htmlspecialchars($ui['label_new_menu'] ?? __(FNB_DEFAULT_LABEL_NEW_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($new_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 4. 추천 메뉴 섹션 (Best Seller) -->
        <?php elseif ($section_key === 'best'): ?>
            <section id="section-best" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_best_menu'] ?? __(FNB_DEFAULT_LABEL_BEST_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-hand-thumbs-up me-2"></i><?php echo htmlspecialchars($ui['label_best_menu'] ?? __(FNB_DEFAULT_LABEL_BEST_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($best_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 5. 전체 메뉴 섹션: 상점의 모든 메뉴를 카테고리별로 묶어서 보여주는 메인 영역의 시작 -->
        <?php elseif ($section_key === 'all'): ?>
            <section id="section-all" class="mt-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_all_menu'] ?? __(FNB_DEFAULT_LABEL_ALL_MENU)); ?>">
                <!-- 전체 메뉴 섹션의 최상단 타이틀을 출력하는 부분 -->
                <!-- 관리자가 설정한 커스텀 라벨($ui['label_all_menu'])이 있으면 사용하고, 없으면 기본값인 '메뉴들 (OUR MENU)'을 출력함 -->
                <div class="menu-section-title">
                    <h2><i class="bi bi-shop me-2"></i><?php echo htmlspecialchars($ui['label_all_menu'] ?? __(FNB_DEFAULT_LABEL_ALL_MENU)); ?></h2>
                </div>

                <!-- [추가] 카테고리 퀵 네비게이션 바 -->
                <!-- 모바일 환경에서 사용자가 원하는 카테고리로 빠르게 이동할 수 있도록, 스크롤 시 화면 상단에 고정(Sticky)되는 가로 스크롤 메뉴바 -->
                <div class="nav-scroll-wrapper">
                    <div class="scroll-indicator left"><i class="bi bi-chevron-left"></i></div>

                    <div class="category-nav-scroll" id="categoryNavScroll">
                        <!-- DB에서 불러와 가공한 $category_menus 배열의 키(카테고리명)들을 순회하며 상단 네비게이션 버튼들을 생성 -->
                        <!-- href 속성에 카테고리명을 md5로 해시한 값을 id로 지정하여, 클릭 시 해당 카테고리 영역으로 부드럽게 스크롤 이동하도록 함 -->
                        <?php foreach (array_keys($category_menus) as $cat_name):
                            $cat_translations = $category_menus[$cat_name][0]['cat_translations'] ?? '';
                            $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                        ?>
                            <a href="#cat-<?php echo md5($cat_name); ?>" class="category-nav-btn"><?php echo htmlspecialchars($display_cat_name); ?></a>
                        <?php endforeach; ?>

                        <!-- 카테고리가 아예 지정되지 않은 메뉴들('기타' 메뉴)이 존재할 경우, 네비게이션 바 맨 끝에 '기타' 버튼을 별도로 추가함 -->
                        <?php if (!empty($no_category_items)):
                            $display_etc_name = __('기타 메뉴');
                        ?>
                            <a href="#cat-<?php echo md5('기타'); ?>" class="category-nav-btn"><?php echo htmlspecialchars($display_etc_name); ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="scroll-indicator right"><i class="bi bi-chevron-right"></i></div>
                </div>

                <!-- 카테고리별 메뉴 목록 렌더링 영역 -->
                <?php 
                // 부모 파일(shop_view.php)에서 설정된 $theme_color를 바탕으로 단 하나의 통일된 그라데이션 생성
                $hex = ltrim($theme_color, '#');
                if (strlen($hex) !== 6) $hex = '004aad';
                $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
                $theme_color_dark = sprintf("#%02x%02x%02x", max(0, round($r * 0.8)), max(0, round($g * 0.8)), max(0, round($b * 0.8)));
                $cat_gradient = "linear-gradient(135deg, {$theme_color} 0%, {$theme_color_dark} 100%)";
                ?>
                <!-- 카테고리명($cat_name)과 해당 카테고리에 속한 메뉴 데이터들($items)을 하나씩 꺼내어 화면에 그림 -->
                <?php foreach ($category_menus as $cat_name => $items):
                    $cat_translations = $items[0]['cat_translations'] ?? '';
                    $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                ?>
                    <!-- [핵심 수정] 위쪽 스크롤 불가 버그 해결을 위해, Sticky되지 않는 투명 앵커 요소를 분리하여 기준점으로 사용합니다. -->
                    <div id="cat-<?php echo md5($cat_name); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_cat_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: <?php echo $cat_gradient; ?> !important; background-color: transparent !important; border: none;">
                        <i class="bi bi-stars"></i>
                        <?php echo htmlspecialchars($display_cat_name); ?>
                        <i class="bi bi-stars"></i>
                    </div>
                    <!-- 실제 메뉴 카드들이 2열~4열 등으로 배치되는 그리드 컨테이너 -->
                    <div class="row g-3 mb-4">
                        <!-- 이 카테고리에 속한 개별 메뉴 아이템들을 순회하며 renderMenuItem() 헬퍼 함수로 UI를 생이름 -->
                        <?php foreach ($items as $item) renderMenuItem($item); ?>
                    </div>
                <?php endforeach; ?>

                <!-- 카테고리가 미지정된 메뉴('기타' 메뉴)들을 렌더링하는 영역 ($no_category_items 배열에 데이터가 있을 때만 실행됨) -->
                <?php if (!empty($no_category_items)):
                    $display_etc_name = __('기타 메뉴 (OTHERS)');
                ?>
                    <div id="cat-<?php echo md5('기타'); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_etc_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important; background-color: transparent !important; border: none;">
                        <i class="bi bi-grid-fill"></i>
                        <?php echo htmlspecialchars($display_etc_name); ?>
                        <i class="bi bi-grid-fill"></i>
                    </div>
                    <div class="row g-3 mb-4">
                        <!-- 기타 메뉴에 속한 아이템들을 순회하며 동일하게 렌더링함 -->
                        <?php foreach ($no_category_items as $item) renderMenuItem($item); ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- [플로팅 바]: 화면 하단에 고정되어 카트 상태와 주문 조회 버튼을 상시 노출합니다. -->

<!-- 카카오톡에 로그인 하지 않은 상태의 로직
1. "주문 조회" 플로팅 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "문의 내역 조회" 모달이 뜬다.
2. "카트보기" 플로팅 버튼을 누르면, "카트 확인" 모달이 뜬다. "주문하기" 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "주문서 작성" 모달이 뜬다. -->

<!-- 카카오톡에 로그인한 상태의 로직
1. "주문 조회" 플로팅 버튼을 누르면, "주문 내역 조회" 모달이 뜬다.
2. "카트보기" 플로팅 버튼을 누르면, "카트 확인" 모달이 뜬다. "주문하기" 버튼을 누르면, "주문서 작성" 모달이 뜬다. -->

<?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
    <div id="floating-cart-bar" class="container-fluid" style="display:none;">
        <div class="row g-2 justify-content-center">
            <div class="col-6" id="btn-history-col">
                <button class="cart-btn-secondary" onclick="showOrderHistory()">
                    <i class="bi bi-clock-history"></i> <?php echo __('주문 조회'); ?>
                </button>
            </div>
            <div class="col-6" id="btn-order-col" style="display:none;">
                <button class="cart-btn-main" onclick="showCartViewModal()">
                    <i class="bi bi-cart3 me-1"></i> <?php echo __('카트 보기'); ?> (<span id="cart-count-badge">0</span>)
                </button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div id="floating-wishlist-bar" class="container-fluid" style="display:none;">
        <div class="row g-2 justify-content-center">
            <div class="col-6" id="btn-wishlist-col">
                <button class="cart-btn-main bg-danger" onclick="showWishlistModal()">
                    <i class="bi bi-heart-fill me-1"></i> <?php echo __('위시 리스트'); ?> (<span id="wishlist-count-badge">0</span>)
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// [추가] 모달 HTML 컴포넌트를 Include 방식으로 불러옵니다.
include_once $_SERVER['DOCUMENT_ROOT'] . '/shops/fnb/includes/fnb_modals.php';
?>

<?php include_once __DIR__ . '/includes/fnb_scripts.php'; ?>

<!-- [추가] 분리된 F&B 전용 카트 및 UI 동작 모듈 로드 -->
<!-- [수정] 브라우저 캐시를 무시하고 최신 JS 파일을 불러오도록 버전 쿼리 파라미터 추가 -->
=======
<?php

/**
 * KShops24 식당/카페 전용 상세 뷰 (F&B Type)
 * [업데이트: 2026-02-21]
 * - 상세 내용: 메뉴 카테고리별 그룹화 렌더링 및 모바일 UX(가로 스크롤) 강화
 * - 수정 내용: 경로 최적화 및 기타 메뉴 중복 출력 버그 수정
 * - 특징: Mobile-First 디자인, 카트 LocalStorage 관리, 카카오 세션 연동
 */

// 세션이나 인클루드 경로를 통해 $shop 데이터가 사전에 정의되어 있어야 함
if (!isset($shop)) exit;

// ==========================================================
// 1. 데이터 로드 섹션 (DB Query)
// ==========================================================

// [1-1] 실물 메뉴판 이미지 로드: 종이 메뉴판을 찍어서 올린 사진 리스트를 가져옵니다.
$stmt_boards = $pdo->prepare("SELECT board_img_path FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_boards->execute([$shop['id']]);
$menu_boards = $stmt_boards->fetchAll();

// [1-2] 개별 메뉴 아이템 로드: 숨김 처리되지 않은 모든 메뉴를 카테고리 순서 및 설정된 정렬 순서대로 가져옵니다.
$stmt_items = $pdo->prepare("
    SELECT m.*, c.cat_name, c.translations AS cat_translations 
    FROM shop_items m 
    LEFT JOIN shop_item_categories c ON m.cat_id = c.id 
    WHERE m.shop_id = ? AND m.is_hide = 0 
    ORDER BY c.sort_order ASC, m.sort_order ASC, m.id DESC
");
$stmt_items->execute([$shop['id']]);
$all_menus = $stmt_items->fetchAll();

// ==========================================================
// 2. 데이터 분류 로직 (Array Filtering)
// ==========================================================
// 화면의 각 섹션(할인, 신메뉴, 카테고리별 등)에 뿌려줄 데이터를 배열로 미리 나눕니다.
$discount_items = [];    // 할인 메뉴
$new_items = [];         // 신메뉴
$best_items = [];        // 인기메뉴
$category_menus = [];    // 카테고리별 일반 메뉴
$no_category_items = []; // 기타 메뉴
$search_results = [];    // [추가] 다국어 검색을 위한 결과 배열

foreach ($all_menus as $m) {
    // [검색 로직] 언어 설정에 관계없이 원본 텍스트와 번역된 데이터 전체를 모두 검색하여 완벽한 매칭 지원
    if (!empty($search_keyword)) {
        $raw_name = $m['item_name'] ?? '';
        $raw_info = $m['item_info'] ?? '';
        $raw_trans = $m['translations'] ?? '';

        if (
            mb_stripos($raw_name, $search_keyword) !== false ||
            mb_stripos($raw_info, $search_keyword) !== false ||
            mb_stripos($raw_trans, $search_keyword) !== false
        ) {
            $search_results[] = $m;
        }
    }

    // 1. 하이라이트 섹션용 데이터 수집 (중복 수집 허용)
    if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0) {
        $discount_items[] = $m;
    }
    if ($m['is_new'] == 1) {
        $new_items[] = $m;
    }
    if ($m['is_best'] == 1) {
        $best_items[] = $m;
    }

    // 2. 전체 메뉴 섹션용 데이터 수집 (카테고리별 그룹화 - 모든 메뉴 빠짐없이 포함)
    if (!empty($m['cat_name'])) {
        $category_menus[$m['cat_name']][] = $m;
    } else {
        $no_category_items[] = $m;
    }
}

// ==========================================================
// 3. UI 렌더링 헬퍼 함수
// ==========================================================
// 반복되는 메뉴 카드 디자인을 한 곳에서 관리하기 위한 렌더링 함수입니다.
if (!function_exists('renderMenuItem')) {
    function renderMenuItem($item)
    {
        global $currency_symbol; // 부모 파일에서 선언된 화폐 기호 전역 변수 가져오기

        $soldout = ($item['is_soldout'] == 1);
        $has_discount = (!empty($item['item_discount_rate']) && $item['item_discount_rate'] > 0);

        // [이미지 처리 로직]: 다중 이미지(JSON) 대응. 배열이면 첫 번째 사진을, 아니면 기존 문자열 경로를 사용합니다.
        $item_img_data = $item['item_img'] ?? '';
        $item_img = '/assets/no-logo.png'; // 기본 이미지
        $img_count = 0; // 추가된 사진 장수 카운트

        if (!empty($item_img_data)) {
            // 1. 특수문자 디코딩 (웹 방화벽 등에 의해 &quot; 로 치환되었을 경우 대비)
            $clean_data = htmlspecialchars_decode($item_img_data);
            $decoded = json_decode($clean_data, true);

            // 2. 이중 JSON 인코딩 방어 (문자열 안에 또 배열 문자열이 있는 경우)
            if (is_string($decoded) && strpos(trim($decoded), '[') === 0) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded) && !empty($decoded[0])) {
                $item_img = $decoded[0];
                $img_count = count(array_filter($decoded)); // 빈 값 제외한 실제 업로드된 이미지 수
            } else if (isset($item_img_data[0]) && $item_img_data[0] !== '[') {
                // JSON 배열 형식이 아닌 일반 텍스트 경로 데이터인 경우에만 대응
                $item_img = $item_img_data;
                $img_count = 1;
            } else {
                // 3. 파싱에 실패했을 경우 정규식으로 첫 번째 이미지 경로 강제 추출 (최후의 보루)
                if (preg_match('/(\/[^"\'\s\\\\]+\.(?:jpg|jpeg|png|gif|webp))/i', $clean_data, $matches)) {
                    $item_img = $matches[1];
                    $img_count = 1;
                }
            }
        }

        // [다국어 적용] 한국어(기본값) fallback 처리
        $disp_item_name = function_exists('t_db') ? t_db($item['item_name'], $item['translations'] ?? '', 'item_name') : $item['item_name'];
        $disp_item_info = function_exists('t_db') ? t_db($item['item_info'], $item['translations'] ?? '', 'item_info') : $item['item_info'];

        $export_item = $item;
        $export_item['item_name'] = $disp_item_name;
        $export_item['item_info'] = $disp_item_info;
        $safe_item_json = htmlspecialchars(json_encode($export_item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

?>
        <!-- 메뉴 아이템 카드 -->
        <div class="col-6 col-md-4 col-lg-3">
            <div class="menu-item-card <?php echo $soldout ? 'is-soldout' : ''; ?>"
                <?php if (!$soldout): ?>onclick="openMenuDetailModalById(<?php echo $item['id']; ?>)" <?php endif; ?>
                style="cursor: pointer;">
                <!-- 배지 레이어 -->
                <div class="position-absolute top-0 start-0 m-2 z-3 d-flex flex-column gap-1 align-items-start">
                    <?php if ($has_discount && !$soldout): ?>
                        <span class="badge bg-danger shadow-sm py-2 px-3 rounded-pill" style="font-size: 0.85rem;">-<?php echo $item['item_discount_rate']; ?>%</span>
                    <?php endif; ?>

                    <?php if ($item['is_best'] == 1 && !$soldout): ?>
                        <span class="badge bg-warning text-dark shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🌟 BEST</span>
                    <?php endif; ?>

                    <?php if ($item['is_new'] == 1 && !$soldout): ?>
                        <span class="badge bg-info text-white shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🔥 NEW</span>
                    <?php endif; ?>

                    <?php if (!empty($item['item_youtube_url']) && !$soldout): ?>
                        <span class="badge bg-danger shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;"><i class="bi bi-play-btn-fill me-1"></i>VIDEO</span>
                    <?php endif; ?>
                </div>

                <!-- [추가] 다중 이미지 알림 배지 (우측 상단) -->
                <?php if ($img_count > 1 && !$soldout): ?>
                    <div class="position-absolute top-0 end-0 m-2 z-3">
                        <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.7rem; backdrop-filter: blur(4px);">
                            <i class="bi bi-images me-1"></i><?php echo $img_count; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($soldout): ?>
                    <div class="soldout-overlay"><span class="soldout-badge"><?php echo __('품절'); ?></span></div>
                <?php endif; ?>

                <img src="<?php echo function_exists('getThumbnailPath') ? getThumbnailPath($item_img) : $item_img; ?>"
                    id="menu-img-<?php echo $item['id']; ?>"
                    class="menu-item-img"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                    onerror="this.onerror=null; this.src='/assets/no-logo.png';"
                    alt="<?php echo htmlspecialchars($disp_item_name); ?>">

                <div class="card-body text-center">
                    <div class="menu-item-name"><?php echo htmlspecialchars($disp_item_name); ?></div>

                    <div class="menu-item-info">
                        <?php echo !empty($disp_item_info) ? nl2br(htmlspecialchars($disp_item_info)) : '&nbsp;'; ?>
                    </div>

                    <div class="menu-item-price d-flex flex-column align-items-center mt-2">
                        <div class="text-center">
                            <?php if ($soldout): ?>
                                <span class="price-strike"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <div class="text-danger small fw-bold"><?php echo __('품절'); ?></div>
                            <?php else: ?>
                                <?php if ($has_discount): ?>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="price-strike x-small text-muted mb-0"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                        <span class="text-primary fw-bold"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_discount_price'] ?? 0)); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-dark"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$soldout): ?>
                            <?php
                            global $shop;
                            if (($shop['is_delivery_available'] ?? 1) == 1): ?>
                                <button class="btn btn-sm btn-primary rounded-pill px-4 py-2 mt-3 w-100 shadow-sm"
                                    data-item="<?php echo $safe_item_json; ?>"
                                    onclick="event.stopPropagation(); openQtyModal(JSON.parse(this.getAttribute('data-item')))">
                                    <i class="bi bi-cart-plus me-1"></i> <?php echo __('카트 담기'); ?>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-4 py-2 mt-3 w-100 shadow-sm wishlist-btn-<?php echo $item['id']; ?>"
                                    data-item="<?php echo $safe_item_json; ?>"
                                    onclick="event.stopPropagation(); toggleWishlist(JSON.parse(this.getAttribute('data-item')), this)">
                                    <i class="bi bi-heart me-1 wishlist-icon-<?php echo $item['id']; ?>"></i> <span class="wishlist-text-<?php echo $item['id']; ?>"><?php echo __('찜하기'); ?></span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
?>

<?php include_once __DIR__ . '/includes/fnb_styles.php'; ?>

<!-- F&B 전용 추가 정보 섹션 (배달 정책 및 공지사항) -->
<section class="fnb_shop-info-summary-section">
    <!-- [배달 정책 요약] 메인 바 아래에 식당 전용 배달 정보를 추가로 표시 -->
    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
        <div class="bg-light border-bottom py-1">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-center gap-2 small text-muted">
                    <?php
                    $methods = [];
                    if (($shop['is_delivery_available'] ?? 1) == 1) {
                        $methods[] = __('배달');
                        $methods[] = __('매장픽업');
                    }
                    $disp_methods = implode(' / ', $methods);
                    
                    $base_fee_info = !empty($shop['delivery_fee_info']) ? $shop['delivery_fee_info'] : '0';
                    $base_payment = !empty($shop['payment_methods']) ? $shop['payment_methods'] : 'Cash';
                    $disp_fee_info = function_exists('t_db') ? t_db($base_fee_info, $shop['policy_translations'] ?? '', 'delivery_fee_info') : $base_fee_info;
                    $disp_payment = function_exists('t_db') ? t_db($base_payment, $shop['policy_translations'] ?? '', 'payment_methods') : $base_payment;

                    // 배달 가능 시간이 비어있으면 오늘 요일의 영업시간을 동적으로 가져옵니다. (공용 헬퍼 함수 사용)
                    $disp_delivery_hours = $shop['delivery_hours'] ?? '';
                    if (empty($disp_delivery_hours)) {
                        $disp_delivery_hours = function_exists('getTodayBusinessHours') ? getTodayBusinessHours($shop['business_hours'] ?? '') : '24' . __('시간');
                    }
                    ?>
                    <span><i class="bi bi-shop me-1"></i> <strong><?php echo htmlspecialchars($disp_methods); ?></strong></span>
                    <span><i class="bi bi-stopwatch me-1"></i> <?php echo __('배달가능시간') . ':'; ?> <strong class="<?php echo $disp_delivery_hours === __('휴무') ? 'text-danger' : ''; ?>"><?php echo htmlspecialchars($disp_delivery_hours); ?></strong></span>
                    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?><span><i class="bi bi-truck me-1"></i> <?php echo __('배달비 안내') . ':'; ?> <strong><?php echo htmlspecialchars($disp_fee_info); ?></strong></span><?php endif; ?>
                    <span><i class="bi bi-credit-card me-1"></i> <?php echo __('결제') . ':'; ?> <strong><?php echo htmlspecialchars($disp_payment); ?></strong></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- [추가] 부모 파일(shop_view.php)에서 생성한 공지사항 및 공통 검색창 UI 출력 -->
<?php echo $common_notice_ui ?? ''; ?>
<?php echo $common_search_form_ui ?? ''; ?>

<!-- [추가] 메뉴 검색 결과 영역 -->
<div class="container mb-4">
    <?php if (!empty($search_keyword)): ?>
        <section id="search-results-section" class="scroll-nav-target">
            <div class="menu-section-title">
                <h2><i class="bi bi-search me-2 text-primary"></i><?php echo __('검색 결과'); ?> <span class="text-primary fs-5">(<?php echo count($search_results); ?>)</span></h2>
            </div>
            <?php if (count($search_results) > 0): ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($search_results as $item) renderMenuItem($item); ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white border rounded-4 shadow-sm">
                    <i class="bi bi-search text-muted mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark"><?php echo __('검색된 메뉴가 없습니다.'); ?></h5>
                    <p class="text-muted small"><?php echo __('다른 검색어로 다시 시도해 보세요.'); ?></p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php
// [섹션 노출 제어]: 관리자 페이지에서 설정한 순서(sort_order)에 따라 섹션들을 동적으로 정렬합니다.
$display_sections = [
    'menu_board' => ['order' => (int)($ui['order_menu_board'] ?? 1), 'active' => !empty($menu_boards)],
    'discount'   => ['order' => (int)($ui['order_discount_menu'] ?? 2), 'active' => !empty($discount_items)],
    'new'        => ['order' => (int)($ui['order_new_menu'] ?? 3), 'active' => !empty($new_items)],
    'best'       => ['order' => (int)($ui['order_best_menu'] ?? 4), 'active' => !empty($best_items)],
    'all'        => ['order' => (int)($ui['order_all_menu'] ?? 5), 'active' => (!empty($category_menus) || !empty($no_category_items))]
];

// 설정된 순서(order)에 따라 정렬
uasort($display_sections, function ($a, $b) {
    return $a['order'] <=> $b['order'];
});
?>

<div class="container mt-2 mb-1 pb-1">
    <?php foreach ($display_sections as $section_key => $section): ?>
        <?php if (!$section['active']) continue; ?>

        <!-- 1. 실물 메뉴판 섹션: 가로 스크롤(Slide) 형태로 제공 -->
        <?php if ($section_key === 'menu_board'): ?>
            <section id="menu-boards" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_menu_board'] ?? __(FNB_DEFAULT_LABEL_MENU_BOARD)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-book me-2"></i><?php echo htmlspecialchars($ui['label_menu_board'] ?? __(FNB_DEFAULT_LABEL_MENU_BOARD)); ?></h2>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i><?php echo __('메뉴판을 좌우로 밀어서 확인하세요.'); ?></p>
                    <div class="text-muted small fw-bold"><i class="bi bi-arrow-left-right"></i> Slide</div>
                </div>
                <div class="menu-scroll-container">
                    <?php foreach ($menu_boards as $board): ?>
                        <div class="board-img-wrapper shadow-sm">
                            <a data-fslightbox="menu-gallery" href="<?php echo $board['board_img_path']; ?>">
                                <img src="<?php echo function_exists('getThumbnailPath') ? getThumbnailPath($board['board_img_path']) : $board['board_img_path']; ?>" class="w-100 h-auto" loading="lazy" onerror="this.onerror=null; this.src='/assets/no-logo.png';">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 2. 할인 메뉴 섹션 (Special Offers) -->
        <?php elseif ($section_key === 'discount'): ?>
            <section id="section-discount" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_discount_menu'] ?? __(FNB_DEFAULT_LABEL_DISCOUNT_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-graph-down-arrow me-2"></i><?php echo htmlspecialchars($ui['label_discount_menu'] ?? __(FNB_DEFAULT_LABEL_DISCOUNT_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($discount_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 3. 신메뉴 섹션 (New Arrivals) -->
        <?php elseif ($section_key === 'new'): ?>
            <section id="section-new" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_new_menu'] ?? __(FNB_DEFAULT_LABEL_NEW_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-truck me-2"></i><?php echo htmlspecialchars($ui['label_new_menu'] ?? __(FNB_DEFAULT_LABEL_NEW_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($new_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 4. 추천 메뉴 섹션 (Best Seller) -->
        <?php elseif ($section_key === 'best'): ?>
            <section id="section-best" class="mb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_best_menu'] ?? __(FNB_DEFAULT_LABEL_BEST_MENU)); ?>">
                <div class="menu-section-title">
                    <h2><i class="bi bi-hand-thumbs-up me-2"></i><?php echo htmlspecialchars($ui['label_best_menu'] ?? __(FNB_DEFAULT_LABEL_BEST_MENU)); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($best_items as $item) renderMenuItem($item); ?>
                </div>
            </section>

            <!-- 5. 전체 메뉴 섹션: 상점의 모든 메뉴를 카테고리별로 묶어서 보여주는 메인 영역의 시작 -->
        <?php elseif ($section_key === 'all'): ?>
            <section id="section-all" class="mt-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($ui['label_all_menu'] ?? __(FNB_DEFAULT_LABEL_ALL_MENU)); ?>">
                <!-- 전체 메뉴 섹션의 최상단 타이틀을 출력하는 부분 -->
                <!-- 관리자가 설정한 커스텀 라벨($ui['label_all_menu'])이 있으면 사용하고, 없으면 기본값인 '메뉴들 (OUR MENU)'을 출력함 -->
                <div class="menu-section-title">
                    <h2><i class="bi bi-shop me-2"></i><?php echo htmlspecialchars($ui['label_all_menu'] ?? __(FNB_DEFAULT_LABEL_ALL_MENU)); ?></h2>
                </div>

                <!-- [추가] 카테고리 퀵 네비게이션 바 -->
                <!-- 모바일 환경에서 사용자가 원하는 카테고리로 빠르게 이동할 수 있도록, 스크롤 시 화면 상단에 고정(Sticky)되는 가로 스크롤 메뉴바 -->
                <div class="nav-scroll-wrapper">
                    <div class="scroll-indicator left"><i class="bi bi-chevron-left"></i></div>

                    <div class="category-nav-scroll" id="categoryNavScroll">
                        <!-- DB에서 불러와 가공한 $category_menus 배열의 키(카테고리명)들을 순회하며 상단 네비게이션 버튼들을 생성 -->
                        <!-- href 속성에 카테고리명을 md5로 해시한 값을 id로 지정하여, 클릭 시 해당 카테고리 영역으로 부드럽게 스크롤 이동하도록 함 -->
                        <?php foreach (array_keys($category_menus) as $cat_name):
                            $cat_translations = $category_menus[$cat_name][0]['cat_translations'] ?? '';
                            $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                        ?>
                            <a href="#cat-<?php echo md5($cat_name); ?>" class="category-nav-btn"><?php echo htmlspecialchars($display_cat_name); ?></a>
                        <?php endforeach; ?>

                        <!-- 카테고리가 아예 지정되지 않은 메뉴들('기타' 메뉴)이 존재할 경우, 네비게이션 바 맨 끝에 '기타' 버튼을 별도로 추가함 -->
                        <?php if (!empty($no_category_items)):
                            $display_etc_name = __('기타 메뉴');
                        ?>
                            <a href="#cat-<?php echo md5('기타'); ?>" class="category-nav-btn"><?php echo htmlspecialchars($display_etc_name); ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="scroll-indicator right"><i class="bi bi-chevron-right"></i></div>
                </div>

                <!-- 카테고리별 메뉴 목록 렌더링 영역 -->
                <?php 
                // 부모 파일(shop_view.php)에서 설정된 $theme_color를 바탕으로 단 하나의 통일된 그라데이션 생성
                $hex = ltrim($theme_color, '#');
                if (strlen($hex) !== 6) $hex = '004aad';
                $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
                $theme_color_dark = sprintf("#%02x%02x%02x", max(0, round($r * 0.8)), max(0, round($g * 0.8)), max(0, round($b * 0.8)));
                $cat_gradient = "linear-gradient(135deg, {$theme_color} 0%, {$theme_color_dark} 100%)";
                ?>
                <!-- 카테고리명($cat_name)과 해당 카테고리에 속한 메뉴 데이터들($items)을 하나씩 꺼내어 화면에 그림 -->
                <?php foreach ($category_menus as $cat_name => $items):
                    $cat_translations = $items[0]['cat_translations'] ?? '';
                    $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                ?>
                    <!-- [핵심 수정] 위쪽 스크롤 불가 버그 해결을 위해, Sticky되지 않는 투명 앵커 요소를 분리하여 기준점으로 사용합니다. -->
                    <div id="cat-<?php echo md5($cat_name); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_cat_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: <?php echo $cat_gradient; ?> !important; background-color: transparent !important; border: none;">
                        <i class="bi bi-stars"></i>
                        <?php echo htmlspecialchars($display_cat_name); ?>
                        <i class="bi bi-stars"></i>
                    </div>
                    <!-- 실제 메뉴 카드들이 2열~4열 등으로 배치되는 그리드 컨테이너 -->
                    <div class="row g-3 mb-4">
                        <!-- 이 카테고리에 속한 개별 메뉴 아이템들을 순회하며 renderMenuItem() 헬퍼 함수로 UI를 생이름 -->
                        <?php foreach ($items as $item) renderMenuItem($item); ?>
                    </div>
                <?php endforeach; ?>

                <!-- 카테고리가 미지정된 메뉴('기타' 메뉴)들을 렌더링하는 영역 ($no_category_items 배열에 데이터가 있을 때만 실행됨) -->
                <?php if (!empty($no_category_items)):
                    $display_etc_name = __('기타 메뉴 (OTHERS)');
                ?>
                    <div id="cat-<?php echo md5('기타'); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_etc_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important; background-color: transparent !important; border: none;">
                        <i class="bi bi-grid-fill"></i>
                        <?php echo htmlspecialchars($display_etc_name); ?>
                        <i class="bi bi-grid-fill"></i>
                    </div>
                    <div class="row g-3 mb-4">
                        <!-- 기타 메뉴에 속한 아이템들을 순회하며 동일하게 렌더링함 -->
                        <?php foreach ($no_category_items as $item) renderMenuItem($item); ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- [플로팅 바]: 화면 하단에 고정되어 카트 상태와 주문 조회 버튼을 상시 노출합니다. -->

<!-- 카카오톡에 로그인 하지 않은 상태의 로직
1. "주문 조회" 플로팅 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "문의 내역 조회" 모달이 뜬다.
2. "카트보기" 플로팅 버튼을 누르면, "카트 확인" 모달이 뜬다. "주문하기" 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "주문서 작성" 모달이 뜬다. -->

<!-- 카카오톡에 로그인한 상태의 로직
1. "주문 조회" 플로팅 버튼을 누르면, "주문 내역 조회" 모달이 뜬다.
2. "카트보기" 플로팅 버튼을 누르면, "카트 확인" 모달이 뜬다. "주문하기" 버튼을 누르면, "주문서 작성" 모달이 뜬다. -->

<?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
    <div id="floating-cart-bar" class="container-fluid" style="display:none;">
        <div class="row g-2 justify-content-center">
            <div class="col-6" id="btn-history-col">
                <button class="cart-btn-secondary" onclick="showOrderHistory()">
                    <i class="bi bi-clock-history"></i> <?php echo __('주문 조회'); ?>
                </button>
            </div>
            <div class="col-6" id="btn-order-col" style="display:none;">
                <button class="cart-btn-main" onclick="showCartViewModal()">
                    <i class="bi bi-cart3 me-1"></i> <?php echo __('카트 보기'); ?> (<span id="cart-count-badge">0</span>)
                </button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div id="floating-wishlist-bar" class="container-fluid" style="display:none;">
        <div class="row g-2 justify-content-center">
            <div class="col-6" id="btn-wishlist-col">
                <button class="cart-btn-main bg-danger" onclick="showWishlistModal()">
                    <i class="bi bi-heart-fill me-1"></i> <?php echo __('위시 리스트'); ?> (<span id="wishlist-count-badge">0</span>)
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// [추가] 모달 HTML 컴포넌트를 Include 방식으로 불러옵니다.
include_once $_SERVER['DOCUMENT_ROOT'] . '/shops/fnb/includes/fnb_modals.php';
?>

<?php include_once __DIR__ . '/includes/fnb_scripts.php'; ?>

<!-- [추가] 분리된 F&B 전용 카트 및 UI 동작 모듈 로드 -->
<!-- [수정] 브라우저 캐시를 무시하고 최신 JS 파일을 불러오도록 버전 쿼리 파라미터 추가 -->
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
<script src="<?php echo $fnb_js_path; ?>?v=<?php echo $fnb_js_ver; ?>" defer></script>