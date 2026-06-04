<?php

/**
 * KShops24 부동산 전용 상세 뷰 (Realty Type)
 * - 기능: FNB의 DB 구조(shop_items 등)를 그대로 활용하되, 매물(Property/Item)과 관심목록(Inquiry) 형태로 UI 출력
 */

if (!isset($shop)) exit;

// ==========================================================
// 1. 데이터 로드 섹션
// ==========================================================

// [1-1] 홍보 전단지 이미지 로드 (종이 전단지/포스터 대체)
$stmt_boards = $pdo->prepare("SELECT board_img_path FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_boards->execute([$shop['id']]);
$flyer_boards = $stmt_boards->fetchAll();

// [1-2] 개별 매물 로드
$stmt_items = $pdo->prepare("
    SELECT m.*, c.cat_name, c.translations AS cat_translations 
    FROM shop_items m 
    LEFT JOIN shop_item_categories c ON m.cat_id = c.id 
    WHERE m.shop_id = ? AND m.is_hide = 0 
    ORDER BY c.sort_order ASC, m.sort_order ASC, m.id DESC
");
$stmt_items->execute([$shop['id']]);
$all_items = $stmt_items->fetchAll();

// ==========================================================
// 2. 데이터 분류 로직
// ==========================================================
$quick_sale_items = [];  // 급매 (할인율 적용된 항목)
$new_items = [];         // 신규 물건
$best_items = [];        // 추천 물건
$category_items = [];    // 카테고리별 매물 (매매/전세/월세 등)
$no_category_items = []; // 기타 매물
$search_results = [];    // [추가] 다국어 검색을 위한 결과 배열

foreach ($all_items as $m) {

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

    if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0) {
        $quick_sale_items[] = $m;
    }
    if ($m['is_new'] == 1) {
        $new_items[] = $m;
    }
    if ($m['is_best'] == 1) {
        $best_items[] = $m;
    }

    if (!empty($m['cat_name'])) {
        $category_items[$m['cat_name']][] = $m;
    } else {
        $no_category_items[] = $m;
    }
}

// ==========================================================
// 3. UI 렌더링 헬퍼 함수
// ==========================================================
if (!function_exists('renderRealtyItem')) {
    function renderRealtyItem($item)
    {
        global $currency_symbol; // 부모 파일에서 선언된 화폐 기호 전역 변수 가져오기

        // 부동산에서는 is_soldout을 '거래완료'로 간주
        $soldout = ($item['is_soldout'] == 1);
        $has_discount = (!empty($item['item_discount_rate']) && $item['item_discount_rate'] > 0);

        $item_img_data = $item['item_img'] ?? '';
        $item_img = '/assets/no-logo.png';
        $img_count = 0;

        if (!empty($item_img_data)) {
            $clean_data = htmlspecialchars_decode($item_img_data);
            $decoded = json_decode($clean_data, true);
            if (is_string($decoded) && strpos(trim($decoded), '[') === 0) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded) && !empty($decoded[0])) {
                $item_img = $decoded[0];
                $img_count = count(array_filter($decoded));
            } else if (isset($item_img_data[0]) && $item_img_data[0] !== '[') {
                $item_img = $item_img_data;
                $img_count = 1;
            } else {
                if (preg_match('/(\/[^"\'\s\\\\]+\.(?:jpg|jpeg|png|gif|webp))/i', $clean_data, $matches)) {
                    $item_img = $matches[1];
                    $img_count = 1;
                }
            }
        }

        // [추가] 유튜브 동영상 유효성 검사 및 카운트
        $youtube_val = $item['item_youtube_url'] ?? '';
        $video_count = 0;
        if (!empty($youtube_val)) {
            if (str_starts_with(trim($youtube_val), '[')) {
                $decoded_yt = json_decode($youtube_val, true);
                if (is_array($decoded_yt)) {
                    $valid_yts = array_filter($decoded_yt, function ($v) {
                        return !empty(trim($v));
                    });
                    $video_count = count($valid_yts);
                }
            } else {
                if (!empty(trim($youtube_val))) {
                    $video_count = 1;
                }
            }
        }

        // [다국어 적용] 한국어(기본값) fallback 처리: 번역이 없거나 언어 설정이 없으면 한국어 노출
        $disp_item_name = function_exists('t_db') ? t_db($item['item_name'], $item['translations'] ?? '', 'item_name') : $item['item_name'];
        $disp_item_info = function_exists('t_db') ? t_db($item['item_info'], $item['translations'] ?? '', 'item_info') : $item['item_info'];

        // JS 모달, 관심매물(장바구니) 등에도 번역된 언어가 출력되도록 JSON용 데이터 덮어쓰기
        $export_item = $item;
        $export_item['item_name'] = $disp_item_name;
        $export_item['item_info'] = $disp_item_info;
        $export_item['trade_type'] = __($item['trade_type'] ?? '');

        $safe_item_json = htmlspecialchars(json_encode($export_item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
        <!-- 매물 아이템 카드 -->
        <div class="col-6 col-md-4 col-lg-3">
            <div class="menu-item-card <?php echo $soldout ? 'is-soldout' : ''; ?>"
                <?php if (!$soldout): ?>data-item="<?php echo $safe_item_json; ?>" onclick="openMenuDetailModal(JSON.parse(this.getAttribute('data-item')))" <?php endif; ?>
                style="cursor: pointer;">
                <!-- 배지 레이어 -->
                <div class="position-absolute top-0 start-0 m-2 z-3 d-flex flex-column gap-1 align-items-start">
                    <?php if ($has_discount && !$soldout): ?>
                        <span class="badge bg-danger shadow-sm py-2 px-3 rounded-pill" style="font-size: 0.85rem;"><?php echo __('급매'); ?></span>
                    <?php endif; ?>
                    <?php if ($item['is_best'] == 1 && !$soldout): ?>
                        <span class="badge bg-warning text-dark shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🌟 <?php echo __('추천'); ?></span>
                    <?php endif; ?>
                    <?php if ($item['is_new'] == 1 && !$soldout): ?>
                        <span class="badge bg-info text-white shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;">🔥 <?php echo __('신규'); ?></span>
                    <?php endif; ?>
                    <?php if ($video_count > 0 && !$soldout): ?>
                        <span class="badge bg-dark shadow-sm py-1 px-2 rounded-pill" style="font-size: 0.7rem;"><i class="bi bi-play-btn-fill me-1"></i><?php echo __('영상'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- 우측 상단 배지 레이어 (관심 하트 및 이미지 개수) -->
                <div class="position-absolute top-0 end-0 m-2 z-3 d-flex flex-column gap-1 align-items-end">
                    <!-- 관심 매물 하트 아이콘 (JS에서 로컬 스토리지 확인 후 표시) -->
                    <span class="item-wish-badge d-none text-danger fs-5 lh-1" data-item-id="<?php echo $item['id']; ?>" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                        <i class="bi bi-heart-fill"></i>
                    </span>
                    <?php if ($img_count > 1 && !$soldout): ?>
                        <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.7rem; backdrop-filter: blur(4px);">
                            <i class="bi bi-images me-1"></i><?php echo $img_count; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($soldout): ?>
                    <div class="soldout-overlay"><span class="soldout-badge fw-bold text-white fs-5" style="border:2px solid white; padding:5px 15px; border-radius:5px;"><?php echo __('거래완료'); ?></span></div>
                <?php endif; ?>

                <img src="<?php echo $item_img; ?>" id="menu-img-<?php echo $item['id']; ?>" class="menu-item-img" loading="lazy" onerror="this.onerror=null; this.src='/assets/no-logo.png';">

                <!-- 카드 바디 -->
                <div class="card-body text-center">
                    <div class="menu-item-name"><?php echo htmlspecialchars($disp_item_name); ?></div>
                    <div class="menu-item-info"><?php echo !empty($disp_item_info) ? nl2br(htmlspecialchars($disp_item_info)) : '&nbsp;'; ?></div>

                    <div class="menu-item-price d-flex flex-column align-items-center mt-2">
                        <div class="text-center">
                            <?php if ($soldout): ?>
                                <span class="price-strike"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <div class="text-danger small fw-bold"><?php echo __('거래완료'); ?></div>
                            <?php else: ?>
                                <?php if ($has_discount): ?>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="price-strike x-small text-muted mb-0"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                        <span class="text-primary fw-bold"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_discount_price'] ?? 0)); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-dark fw-bold"><?php echo $currency_symbol; ?> <?php echo number_format((float)($item['item_price'] ?? 0)); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$soldout): ?>
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 mt-3 w-100 shadow-sm"
                                data-item="<?php echo $safe_item_json; ?>"
                                onclick="event.stopPropagation(); openMenuDetailModal(JSON.parse(this.getAttribute('data-item')))">
                                <i class="bi bi-building me-1"></i> <?php echo __('상세 보기'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
?>

<?php include_once __DIR__ . '/includes/realty_styles.php'; ?>

<style>
    /* F&B 모듈과 동일하게 타이틀 바가 스크롤 시 매물 배지 위로 올라오도록 보정 */
    .cat-title-bar {
        z-index: 10 !important;
    }
</style>

<!-- 부동산 전용 추가 정보 섹션 -->
<section class="fnb_shop-info-summary-section">
    <?php if (($shop['is_show_delivery'] ?? 1) == 1): ?>
        <div class="bg-light border-bottom py-1">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-center gap-2 small text-muted">
                    <?php
                    $base_fee_info = !empty($shop['delivery_fee_info']) ? $shop['delivery_fee_info'] : __('상담 시 안내');
                    $base_payment = !empty($shop['payment_methods']) ? $shop['payment_methods'] : __('매매 / 임대');
                    $disp_fee_info = function_exists('t_db') ? t_db($base_fee_info, $shop['policy_translations'] ?? '', 'delivery_fee_info') : $base_fee_info;
                    $disp_payment = function_exists('t_db') ? t_db($base_payment, $shop['policy_translations'] ?? '', 'payment_methods') : $base_payment;

                    // 상담 가능 시간 동적 로직 적용 ("상시 가능" 및 "영업시간 동일" 처리)
                    $disp_consult_hours = $shop['delivery_hours'] ?? '';
                    if ($disp_consult_hours === '상시 문의') {
                        $ch_class = 'text-success'; // 상시 가능일 때 초록색 강조
                    } else if (empty($disp_consult_hours)) {
                        $disp_consult_hours = function_exists('getTodayBusinessHours') ? getTodayBusinessHours($shop['business_hours'] ?? '') : '24' . __('시간');
                        $ch_class = ($disp_consult_hours === __('휴무')) ? 'text-danger' : '';
                    } else {
                        $ch_class = '';
                    }
                    ?>
                    <span><i class="bi bi-clock me-1"></i> <?php echo __('상담가능시간') . ':'; ?> <strong class="<?php echo $ch_class; ?>"><?php echo __(htmlspecialchars($disp_consult_hours)); ?></strong></span>
                    <span><i class="bi bi-info-circle me-1"></i> <?php echo __('수수료 안내:'); ?> <strong><?php echo htmlspecialchars($disp_fee_info); ?></strong></span>
                    <span><i class="bi bi-credit-card me-1"></i> <?php echo __('거래방식:'); ?> <strong><?php echo htmlspecialchars($disp_payment); ?></strong></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- [추가] 부모 파일(shop_view.php)에서 생성한 공지사항 및 공통 검색창 UI 출력 -->
<?php echo $common_notice_ui ?? ''; ?>
<?php echo $common_search_form_ui ?? ''; ?>

<!-- 매물 검색 결과 영역 (검색창 폼은 부모 파일인 shop_view.php에서 처리함) -->
<div class="container mb-4">
    <?php if (!empty($search_keyword)): ?>
        <section id="search-results-section" class="scroll-nav-target">
            <div class="menu-section-title">
                <h2><i class="bi bi-search me-2 text-primary"></i><?php echo __('검색 결과'); ?> <span class="text-primary fs-5">(<?php echo count($search_results); ?>)</span></h2>
            </div>
            <?php if (count($search_results) > 0): ?>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($search_results as $item) renderRealtyItem($item); ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white border rounded-4 shadow-sm">
                    <i class="bi bi-search text-muted mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark"><?php echo __('검색된 매물이 없습니다.'); ?></h5>
                    <p class="text-muted small"><?php echo __('다른 검색어로 다시 시도해 보세요.'); ?></p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php
// [섹션 노출 제어]
$display_sections = [
    'flyer'      => ['order' => (int)($ui['order_flyer'] ?? 1), 'active' => !empty($flyer_boards)],
    'quick_sale' => ['order' => (int)($ui['order_quick_sale'] ?? 2), 'active' => !empty($quick_sale_items)],
    'new'        => ['order' => (int)($ui['order_new_item'] ?? 3), 'active' => !empty($new_items)],
    'best'       => ['order' => (int)($ui['order_best_item'] ?? 4), 'active' => !empty($best_items)],
    'all'        => ['order' => (int)($ui['order_all_items'] ?? 5), 'active' => (!empty($category_items) || !empty($no_category_items))]
];

uasort($display_sections, function ($a, $b) {
    return $a['order'] <=> $b['order'];
});
?>

<div class="container mt-2 mb-1 pb-1">
    <?php foreach ($display_sections as $section_key => $section): ?>
        <?php if (!$section['active']) continue; ?>

        <!-- 1. 홍보 전단지 섹션 -->
        <?php if ($section_key === 'flyer'): ?>
            <?php
            $disp_label = $ui['label_flyer'] ?? __(REALTY_DEFAULT_LABEL_FLYER);
            if ($global_current_lang !== 'ko' && !empty($ui["label_flyer_{$global_current_lang}"])) $disp_label = $ui["label_flyer_{$global_current_lang}"];
            ?>
            <section id="menu-boards" class="scroll-nav-target mb-5" data-nav-label="<?php echo htmlspecialchars($disp_label); ?>">
                <div class="menu-section-title">
                    <h2><?php echo htmlspecialchars($disp_label); ?></h2>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i><?php echo __('전단지를 좌우로 밀어서 확인하세요.'); ?></p>
                    <div class="text-muted small fw-bold"><i class="bi bi-arrow-left-right"></i> Slide</div>
                </div>
                <div class="menu-scroll-container">
                    <?php foreach ($flyer_boards as $board):
                        $img_path = trim($board['board_img_path'] ?? '');
                        if (empty($img_path)) continue; // 빈 이미지 경로일 경우 DOM 생성을 건너뜀
                    ?>
                        <div class="board-img-wrapper shadow-sm">
                            <a data-fslightbox="menu-gallery" href="<?php echo htmlspecialchars($img_path); ?>">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" class="w-100 h-auto" loading="lazy" onerror="this.onerror=null; this.src='/assets/no-logo.png';">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 2. 급매 섹션 -->
        <?php elseif ($section_key === 'quick_sale'): ?>
            <?php
            $disp_label = $ui['label_quick_sale'] ?? __(REALTY_DEFAULT_LABEL_QUICK_SALE);
            if ($global_current_lang !== 'ko' && !empty($ui["label_quick_sale_{$global_current_lang}"])) $disp_label = $ui["label_quick_sale_{$global_current_lang}"];
            ?>
            <section id="quick-sale-section" class="scroll-nav-target mb-5" data-nav-label="<?php echo htmlspecialchars($disp_label); ?>">
                <div class="menu-section-title">
                    <h2><?php echo htmlspecialchars($disp_label); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($quick_sale_items as $item) renderRealtyItem($item); ?>
                </div>
            </section>

            <!-- 3. 신규 매물 섹션 -->
        <?php elseif ($section_key === 'new'): ?>
            <?php
            $disp_label = $ui['label_new_item'] ?? __(REALTY_DEFAULT_LABEL_NEW_ITEM);
            if ($global_current_lang !== 'ko' && !empty($ui["label_new_item_{$global_current_lang}"])) $disp_label = $ui["label_new_item_{$global_current_lang}"];
            ?>
            <section id="new-items-section" class="scroll-nav-target mb-5" data-nav-label="<?php echo htmlspecialchars($disp_label); ?>">
                <div class="menu-section-title">
                    <h2><?php echo htmlspecialchars($disp_label); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($new_items as $item) renderRealtyItem($item); ?>
                </div>
            </section>

            <!-- 4. 추천 매물 섹션 -->
        <?php elseif ($section_key === 'best'): ?>
            <?php
            $disp_label = $ui['label_best_item'] ?? __(REALTY_DEFAULT_LABEL_BEST_MENU);
            if ($global_current_lang !== 'ko' && !empty($ui["label_best_item_{$global_current_lang}"])) $disp_label = $ui["label_best_item_{$global_current_lang}"];
            ?>
            <section id="best-items-section" class="scroll-nav-target mb-5" data-nav-label="<?php echo htmlspecialchars($disp_label); ?>">
                <div class="menu-section-title">
                    <h2><?php echo htmlspecialchars($disp_label); ?></h2>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php foreach ($best_items as $item) renderRealtyItem($item); ?>
                </div>
            </section>

            <!-- 5. 전체 매물 섹션 (카테고리 탭 연동) -->
        <?php elseif ($section_key === 'all'): ?>
            <?php
            $disp_label = $ui['label_all_items'] ?? __(REALTY_DEFAULT_LABEL_ALL_ITEMS);
            if ($global_current_lang !== 'ko' && !empty($ui["label_all_items_{$global_current_lang}"])) $disp_label = $ui["label_all_items_{$global_current_lang}"];
            ?>
            <section id="all-items-section" class="scroll-nav-target mt-5" data-nav-label="<?php echo htmlspecialchars($disp_label); ?>">
                <!-- 섹션 타이틀 -->
                <div class="menu-section-title">
                    <h2><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($disp_label); ?></h2>
                </div>

                <!-- 카테고리 네비게이션 바 -->
                <div class="nav-scroll-wrapper">
                    <div class="scroll-indicator left"><i class="bi bi-chevron-left"></i></div>
                    <div class="category-nav-scroll" id="categoryNavScroll">
                        <!-- 카테고리별 네비게이션 버튼 -->
                        <?php foreach ($category_items as $cat_name => $items):
                            $cat_translations = $items[0]['cat_translations'] ?? '';
                            $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                            if ($display_cat_name === $cat_name && function_exists('translate_realty_category')) {
                                $display_cat_name = translate_realty_category($cat_name, $global_current_lang);
                            }
                        ?>
                            <a href="#cat-<?php echo md5($cat_name); ?>" class="category-nav-btn" onclick="scrollToCategory(event, 'cat-<?php echo md5($cat_name); ?>')"><?php echo htmlspecialchars($display_cat_name); ?></a>
                        <?php endforeach; ?>
                        <?php if (!empty($no_category_items)): ?>
                            <?php
                            $display_etc_name = __('기타 매물');
                            if (function_exists('translate_realty_category')) {
                                $display_etc_name = translate_realty_category($display_etc_name, $global_current_lang);
                            }
                            ?>
                            <a href="#cat-<?php echo md5('기타'); ?>" class="category-nav-btn" onclick="scrollToCategory(event, 'cat-<?php echo md5('기타'); ?>')"><?php echo htmlspecialchars($display_etc_name); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="scroll-indicator right"><i class="bi bi-chevron-right"></i></div>
                </div>

                <!-- 카테고리 섹션 -->
                <?php 
                // 부모 파일(shop_view.php)에서 설정된 $theme_color를 바탕으로 단 하나의 통일된 그라데이션 생성
                $hex = ltrim($theme_color, '#');
                if (strlen($hex) !== 6) $hex = '004aad';
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                // 원본 색상보다 20% 어두운 색상을 계산하여 투톤 그라데이션 연결
                $theme_color_dark = sprintf("#%02x%02x%02x", max(0, round($r * 0.8)), max(0, round($g * 0.8)), max(0, round($b * 0.8)));
                $cat_gradient = "linear-gradient(135deg, {$theme_color} 0%, {$theme_color_dark} 100%)";
                
                foreach ($category_items as $cat_name => $items):
                    $cat_translations = $items[0]['cat_translations'] ?? '';
                    $display_cat_name = function_exists('t_db') ? t_db($cat_name, $cat_translations) : $cat_name;
                    if ($display_cat_name === $cat_name && function_exists('translate_realty_category')) {
                        $display_cat_name = translate_realty_category($cat_name, $global_current_lang);
                    }
                ?>
                    <!-- 카테고리 앵커 (스크롤 네비게이션 타겟) -->
                    <div id="cat-<?php echo md5($cat_name); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_cat_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: <?php echo $cat_gradient; ?> !important; background-color: transparent !important; border: none;">
                        <?php echo htmlspecialchars($display_cat_name); ?>
                    </div>
                    <div class="row g-3 mb-4">
                        <?php foreach ($items as $item) renderRealtyItem($item); ?>
                    </div>
                <?php endforeach; ?>

                <!-- 카테고리가 없는 매물 섹션 -->
                <?php if (!empty($no_category_items)): ?>
                    <?php
                    $display_etc_name = __('기타 매물');
                    if (function_exists('translate_realty_category')) {
                        $display_etc_name = translate_realty_category($display_etc_name, $global_current_lang);
                    }
                    ?>
                    <div id="cat-<?php echo md5('기타'); ?>" class="category-anchor scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($display_etc_name); ?>" data-nav-indent="true"></div>
                    <div class="cat-title-bar text-white shadow-sm" style="background-image: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important; background-color: transparent !important; border: none;">
                        <i class="bi bi-grid-fill"></i> <?php echo htmlspecialchars($display_etc_name); ?> (OTHERS) <i class="bi bi-grid-fill"></i>
                    </div>
                    <div class="row g-3 mb-4">
                        <?php foreach ($no_category_items as $item) renderRealtyItem($item); ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>


<!-- 카카오톡에 로그인 하지 않은 상태의 로직
1. "나의 문의 내역" 플로팅 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "나의 문의 내역" 모달이 뜬다.
1-1. "나의 문의 내역" 플로팅 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "카카오톡으로 1초 로그인" 버튼을 클릭하면, "카카오톡으로 로그인 성공!" 모달 후, "나의 문의 내역" 모달이 뜬다.


2. "관심 매물 조회" 플로팅 버튼을 누르면, "관심 매물 목록" 모달이 뜬다. "문의 접수 완료하기" 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "로그인 없이 계속하기" 버튼을 클릭하면, "고개 문의가 등록되었습니다" 알림 모달이 뜬다.
2-1. "관심 매물 조회" 플로팅 버튼을 누르면, "관심 매물 목록" 모달이 뜬다. "문의 접수 완료하기" 버튼을 누르면, "로그인 방법 선택" 모달이 뜬다. -> "카카오톡으로 1초 로그인" 버튼을 클릭하면, "카카오톡으로 로그인 성공!" 모달 후, "고개 문의가 등록되었습니다" 알림 모달이 뜬다. -->

<!-- 카카오톡에 로그인한 상태의 로직
1. "나의 문의 내역" 플로팅 버튼을 누르면, "문의 내역 조회" 모달이 뜬다.
2. "카트보기" 플로팅 버튼을 누르면, "카트 확인" 모달이 뜬다. "주문하기" 버튼을 누르면, "주문서 작성" 모달이 뜬다. -->

<!-- [플로팅 바]: 문의 내역 및 관시 매물 조회 -->
<div id="floating-cart-bar" class="container-fluid">
    <div class="row g-2 justify-content-center">
        <div class="col-6" id="btn-history-col">
            <!-- 부동산 전용 문의 내역 조회 로직 -->
            <button class="cart-btn-secondary w-100 d-flex align-items-center justify-content-center" onclick="openRealtyOrderHistoryModal()">
                <?php
                $my_inquiry_count = 0;
                $customer_phone_for_count = preg_replace('/[^0-9]/', '', $_SESSION['customer_ph_phone'] ?? '');
                if (!empty($customer_phone_for_count)) {
                    $stmt_inq_cnt = $pdo->prepare("SELECT COUNT(*) FROM shop_inquiries WHERE shop_id = ? AND customer_phone = ?");
                    $stmt_inq_cnt->execute([$shop['id'], $customer_phone_for_count]);
                    $my_inquiry_count = (int)$stmt_inq_cnt->fetchColumn();
                }
                ?>
                <i class="bi bi-clock-history me-2"></i> <?php echo mb_strimwidth(__('문의 내역'), 0, 10, '...', 'UTF-8'); ?> (<span id="order-count-badge"><?php echo $my_inquiry_count; ?></span>)
            </button>
        </div>
        <div class="col-6" id="btn-order-col" style="display:none;">
            <!-- 관심 매물 확인 로직 -->
            <button class="cart-btn-main w-100 d-flex align-items-center justify-content-center" onclick="showCartViewModal()">
                <i class="bi bi-bookmark-heart-fill me-2"></i> <?php echo mb_strimwidth(__('관심 매물'), 0, 10, '...', 'UTF-8'); ?> (<span id="cart-count-badge">0</span>)
            </button>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/realty_scripts.php'; ?>

<?php
// 부동산 전용 모달 HTML 컴포넌트를 안전하게 불러옵니다.
$realty_modals_path = __DIR__ . '/includes/realty_modals.php';
$fallback_path = __DIR__ . '/realty_modals.php';

if (file_exists($realty_modals_path)) {
    include_once $realty_modals_path;
} elseif (file_exists($fallback_path)) {
    include_once $fallback_path;
} else {
    echo "<script>console.error('realty_modals.php 파일을 찾을 수 없습니다. 서버의 /shops/realty/includes/ 폴더에 파일이 정상적으로 업로드되었는지 확인해주세요.');</script>";
}
?>

<!-- Realty 전용 장바구니 및 동작 스크립트 로드 -->
<script src="/shops/realty/assets/realty_cart.js?v=<?php echo time(); ?>" defer></script>