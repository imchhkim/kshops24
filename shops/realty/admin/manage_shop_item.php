<?php

/**
 * KShops24 부동산 매물 관리 모듈 (manage_shop_item.php)
 * * [환경] PHP 8.x, PDO(MySQL), Hostinger Shared Hosting
 * [보안] $shop_id 변수를 통해 다른 상점의 매물을 수정할 수 없도록 격리 설계됨
 */

// 세션 또는 부모 페이지에서 정의된 $shop_id가 없으면 실행 중단 (비정상 접근 차단)
if (!isset($shop_id)) exit;

// ---------------------------------------------------------
// --- [A~C] 백엔드 액션(데이터 처리) 로직 분리 ---
// ---------------------------------------------------------
require_once __DIR__ . '/manage_shop_item_action.php';

// ---------------------------------------------------------
// --- [D] 데이터 로딩 ---
// ---------------------------------------------------------

// 부동산 카테고리 설정 로드
$category_config_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/realty/realty_config.php";
if (file_exists($category_config_path)) {
    include_once $category_config_path;
}

// 카테고리 목록
$category_list = $pdo->prepare("SELECT * FROM shop_item_categories WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$category_list->execute([$shop_id]);
$category_list = $category_list->fetchAll();

// 홍보 전단지 리스트
$board_list = $pdo->prepare("SELECT * FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$board_list->execute([$shop_id]);
$board_list = $board_list->fetchAll();

// UI 설정
if (!isset($ui)) {
    $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
    $stmt_ui->execute([$shop_id]);
    $ui = json_decode($stmt_ui->fetchColumn() ?: '{}', true);
}

// 매물 통계
$stmt_stats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_soldout = 1 THEN 1 ELSE 0 END) as soldout,
    SUM(CASE WHEN is_hide = 1 THEN 1 ELSE 0 END) as hidden
    FROM shop_items WHERE shop_id = ?");
$stmt_stats->execute([$shop_id]);
$item_stats = $stmt_stats->fetch();

// 매물 리스트
$item_list = $pdo->prepare("SELECT m.*, c.cat_name FROM shop_items m 
                             LEFT JOIN shop_item_categories c ON m.cat_id = c.id 
                             WHERE m.shop_id = ? 
                             ORDER BY c.sort_order ASC, m.sort_order ASC, m.id DESC");
$item_list->execute([$shop_id]);
$all_items = $item_list->fetchAll();

// 매물 그룹화
$grouped_items = [];
foreach ($all_items as $m) {
    $cat_name = $m['cat_name'] ?: '기타';
    $grouped_items[$cat_name][] = $m;
}
if (isset($grouped_items['기타'])) {
    $temp_unassigned = $grouped_items['기타'];
    unset($grouped_items['기타']);
    $grouped_items['기타'] = $temp_unassigned;
}
?>

<style>
    .board-container {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        transition: transform 0.2s;
    }

    .board-container:hover {
        transform: scale(1.02);
    }

    .menu-list-img {
        width: 70px;
        height: 52px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #eee;
    }

    @media (min-width: 768px) {
        .menu-list-img {
            width: 80px;
            height: 60px;
        }
    }

    .grayscale {
        filter: grayscale(100%);
        opacity: 0.5;
    }

    .cat-badge-item {
        background: #e9ecef;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        margin-right: 5px;
        margin-bottom: 5px;
        border: 1px solid #dee2e6;
    }

    .is_soldout-row {
        opacity: 0.7;
        background-color: #fffafa !important;
    }

    .is-hide-row {
        background-color: #f1f3f5 !important;
        opacity: 0.6;
    }

    .animate-spin {
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    #itemMediaModalBody {
        width: 100%;
        aspect-ratio: 4/3;
        background-color: transparent;
        overflow: hidden;
    }

    #itemMediaModalBody .carousel,
    #itemMediaModalBody .carousel-inner,
    #itemMediaModalBody .carousel-item {
        width: 100%;
        height: 100%;
    }

    #itemMediaModalBody img,
    #itemMediaModalBody iframe {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /* 매물 설명 텍스트: 모바일 1줄, PC 2줄 말줄임 */
    .item-info-text {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-all;
        white-space: normal;
        line-height: 1.4;
        /* 압축기 최적화 과정에서 속성 삭제 방지 및 강제 적용 */
        -webkit-box-orient: vertical !important;
    }

    .info-action-container {
        padding-left: 30px;
    }

    @media (min-width: 768px) {

        /* [핵심 버그 수정] Bootstrap 5 기본 제공 클래스에 없는 w-md-auto 추가 */
        /* 우측 영역이 PC에서 강제로 100%를 차지해 좌측 매물 정보를 밀어내서 숨기는 현상 완벽 해결 */
        .w-md-auto {
            width: auto !important;
        }

        .item-info-text {
            -webkit-line-clamp: 2;
        }

        .info-action-container {
            padding-left: 0;
        }
    }
</style>

<div class="container-fluid p-0">
    <?php
    if (isset($_GET['msg'])) {
        $msg_text = '';
        $msg_type = 'success';
        switch ($_GET['msg']) {
            case 'cat_added':
                $msg_text = '카테고리가 추가되었습니다.';
                break;
            case 'cat_deleted':
                $msg_text = '카테고리가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'board_deleted':
                $msg_text = '홍보 전단지가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'item_added':
                $msg_text = '신규 매물이 등록되었습니다.';
                break;
            case 'item_updated':
                $msg_text = '매물 정보가 수정되었습니다.';
                break;
            case 'item_deleted':
                $msg_text = '매물이 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'label_updated':
                $msg_text = '레이블이 수정되었습니다.';
                break;
            case 'policy_updated':
                $msg_text = '부동산 및 중개 정책이 성공적으로 수정되었습니다.';
                break;
        }
        if ($msg_text) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg_text}', '{$msg_type}'); });</script>";
        }
    }
    ?>

    <?php echo renderPageHeader('매물 관리', 'bi-buildings'); ?>

    <!-- [섹션 3] 매물 통계 -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="<?php echo UI_SECTION_CARD; ?>">
                <div class="p-3 p-md-4 d-flex flex-column h-100">
                    <?php echo renderSectionHeader('매물 등록 현황', 'bi-bar-chart-line'); ?>
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-dark">총 등록 매물</span><span class="fw-bold fs-5"><?php echo number_format($item_stats['total'] ?? 0); ?>개</span></div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-danger">거래 완료</span><span class="fw-bold text-danger"><?php echo number_format($item_stats['soldout'] ?? 0); ?>개</span></div>
                    <div class="d-flex justify-content-between align-items-center"><span class="text-muted">숨김 처리</span><span class="fw-bold text-muted"><?php echo number_format($item_stats['hidden'] ?? 0); ?>개</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- [섹션 2] 매물 리스트 -->
    <div class="row g-4">
        <div class="col-12">
            <div class="<?php echo UI_SECTION_CARD; ?>">
                <div class="p-3 p-md-4 d-flex flex-column h-100">
                    <?php 
                    /**
                     * 매물 리스트 섹션 헤더 렌더링 (상수가 class 속성일 때)
                     * 상수의 값 앞뒤로 <i> 태그 구조를 완성하여 결합합니다.
                     */
                    echo renderSectionHeader(
                        '매물 리스트',
                        'bi-grid-3x3-gap',
                        [
                            '<i ' . UI_INFO_SM_LABEL . '></i> 처음 매물을 등록하는 경우, 반드시 <a href="manage_shop.php?pg=manage_shop_item_policy#item_category">매물 정책 관리에서 "전체 물건의 매물 카테고리 설정"</a>을 하셔야 합니다.',
                            '<i ' . UI_INFO_SM_LABEL . '></i> 마우스로 드래그하여 순서를 변경할 수 있습니다.'
                        ],
                        '<button class="btn btn-primary btn-sm rounded-pill px-3 ms-md-auto" onclick="openAddItemModal()"><i class="bi bi-plus-lg me-1"></i> 매물 추가</button>'
                    ); 
                    ?>

                    <div class="list-group list-group-flush border-top" id="item-list-sortable">
                        <?php
                        $lang1 = $ui['multilingual_lang1'] ?? 'none';
                        $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                        if (empty($lang1_code)) $lang1_code = 'etc1';

                        $lang2 = $ui['multilingual_lang2'] ?? 'none';
                        $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                        if (empty($lang2_code)) $lang2_code = 'etc2';
                        ?>
                        <?php if (empty($grouped_items)): ?>
                            <div class="list-group-item text-center py-5 text-muted border-bottom">등록된 매물이 없습니다.</div>
                        <?php else: ?>
                            <?php foreach ($grouped_items as $cat_title => $items): ?>
                                <div class="list-group-item bg-light py-2 px-2 px-md-3 no-drag border-bottom">
                                    <div class="fw-bold text-dark"><i class="bi bi-folder2-open me-2"></i><?php echo htmlspecialchars($cat_title); ?> <span class="badge bg-secondary ms-2 fw-normal"><?php echo count($items); ?></span></div>
                                </div>
                                <?php foreach ($items as $m): ?>
                                    <div id="item-row-<?php echo $m['id']; ?>" data-id="<?php echo $m['id']; ?>" class="list-group-item px-1 px-md-3 py-3 sort-item-row border-bottom <?php echo $m['is_soldout'] ? 'is_soldout-row' : ''; ?> <?php echo $m['is_hide'] ? 'is-hide-row' : ''; ?>">
                                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 gap-md-3 w-100">
                                            <div class="d-flex align-items-start flex-grow-1" style="min-width: 0;">
                                                <div class="drag-handle-item text-muted me-1 me-md-2 mt-2 flex-shrink-0" style="cursor: grab;"><i class="bi bi-grip-vertical fs-5"></i></div>
                                                <?php
                                                // JSON 디코딩 안전성 강화 및 이중 인코딩 방어
                                                $translations = [];
                                                if (!empty($m['translations'])) {
                                                    $decoded_trans = json_decode(htmlspecialchars_decode($m['translations']), true);
                                                    if (is_string($decoded_trans)) $decoded_trans = json_decode($decoded_trans, true);
                                                    if (is_array($decoded_trans)) $translations = $decoded_trans;
                                                }

                                                $has_lang1 = ($lang1 !== 'none' && (!empty($translations[$lang1_code]['item_name']) || !empty($translations[$lang1_code]['item_info'])));
                                                $has_lang2 = ($lang2 !== 'none' && (!empty($translations[$lang2_code]['item_name']) || !empty($translations[$lang2_code]['item_info'])));

                                                $img_val = $m['item_img'] ?? '';
                                                $display_thumb = '/assets/no-logo.png';
                                                $img_count = 0;

                                                $youtube_val = $m['item_youtube_url'] ?? '';
                                                $video_count = 0;
                                                if (!empty($youtube_val)) {
                                                    if (str_starts_with(trim($youtube_val), '[')) {
                                                        $decoded_yt = json_decode($youtube_val, true);
                                                        if (is_array($decoded_yt)) $video_count = count(array_filter($decoded_yt));
                                                    } else {
                                                        $video_count = 1;
                                                    }
                                                }

                                                if (!empty($img_val)) {
                                                    $decoded_img = json_decode(htmlspecialchars_decode($img_val), true);
                                                    if (is_string($decoded_img) && strpos(trim($decoded_img), '[') === 0) {
                                                        $decoded_img = json_decode($decoded_img, true);
                                                    }
                                                    if (is_array($decoded_img)) {
                                                        $valid_imgs = array_filter($decoded_img);
                                                        if (!empty($valid_imgs)) {
                                                            $display_thumb = array_values($valid_imgs)[0];
                                                            $img_count = count($valid_imgs);
                                                        }
                                                    } else if ($img_val && $img_val[0] !== '[') {
                                                        $display_thumb = $img_val;
                                                        $img_count = 1;
                                                    } else if (preg_match('/(\/[^"\'\s\\\\]+\.(?:jpg|jpeg|png|gif|webp))/i', htmlspecialchars_decode($img_val), $matches)) {
                                                        $display_thumb = $matches[1];
                                                        $img_count = 1;
                                                    }
                                                }

                                                // XSS 및 스크립트 에러 방지를 위해 data 속성을 통한 JSON 데이터 전달 방식 적용
                                                $safe_item_json = htmlspecialchars(json_encode($m, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <div class="d-flex flex-column align-items-center me-2 me-md-3 flex-shrink-0">
                                                    <img src="<?php echo $display_thumb; ?>" class="menu-list-img shadow-sm <?php echo $m['is_soldout'] || $m['is_hide'] ? 'grayscale' : ''; ?>" data-item="<?php echo $safe_item_json; ?>" onclick="showItemMediaModal(JSON.parse(this.getAttribute('data-item')))" style="cursor: pointer;" title="이미지/영상 보기">
                                                    <?php if ($img_count > 1 || $video_count > 0): ?>
                                                        <div class="mt-1 d-flex gap-1 justify-content-center w-100">
                                                            <?php if ($img_count > 1): ?><span class="badge bg-secondary" style="font-size: 0.6rem; padding: 0.25em 0.4em; font-weight: normal;"><i class="bi bi-images me-1"></i><?php echo $img_count; ?></span><?php endif; ?>
                                                            <?php if ($video_count > 0): ?><span class="badge bg-danger" style="font-size: 0.6rem; padding: 0.25em 0.4em; font-weight: normal;"><i class="bi bi-youtube me-1"></i><?php echo $video_count; ?></span><?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex flex-column justify-content-center flex-grow-1" style="min-width: 0;">
                                                    <div class="mb-1 d-flex flex-wrap align-items-center gap-1">
                                                        <?php if ($m['is_hide']): ?><span class="text-muted small">[숨김]</span><?php endif; ?>
                                                        <?php if ($m['is_soldout']): ?><span class="text-danger">[완료]</span><?php endif; ?>
                                                        <?php if (!empty($m['trade_type'])): ?>
                                                            <span class="badge border border-primary text-primary" style="font-size: 0.7rem; font-weight: bold;"><?php echo htmlspecialchars($m['trade_type']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="fw-bold text-truncate mb-1" style="font-size: 0.95rem;">
                                                        <span style="cursor: pointer; text-decoration: underline; text-decoration-color: rgba(0, 74, 173, 0.4); text-underline-offset: 4px; position: relative; z-index: 10;" onclick="previewItemDetail(JSON.parse(this.closest('.sort-item-row').querySelector('.menu-list-img').getAttribute('data-item')), 'ko')" title="매물 상세 미리보기">
                                                            <?php echo htmlspecialchars($m['item_name'] ?? ''); ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-secondary small item-info-text"><?php echo htmlspecialchars($m['item_info'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-end align-items-md-center flex-md-shrink-0 mt-2 mt-md-0 w-100 w-md-auto info-action-container">
                                                <div class="d-flex flex-row flex-md-column justify-content-between align-items-center align-items-md-end gap-2 gap-md-1 w-100 w-md-auto mb-2 mb-md-0">
                                                    <div class="fw-bold text-primary">
                                                        <?php if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0): ?>
                                                            <?php echo number_format((float)($m['item_discount_price'] ?? 0)); ?> ₱
                                                            <span class="text-danger small fw-normal ms-1">(<?php echo $m['item_discount_rate']; ?>% 급매)</span>
                                                        <?php else: ?>
                                                            <?php echo number_format((float)($m['item_price'] ?? 0)); ?> ₱
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                        <?php if ($has_lang1): ?><span class="badge bg-secondary" style="cursor:pointer; position: relative; z-index: 10;" title="다국어 1 미리보기" onclick="previewItemDetail(JSON.parse(this.closest('.sort-item-row').querySelector('.menu-list-img').getAttribute('data-item')), '<?php echo $lang1_code; ?>')"><?php echo strtoupper($lang1_code); ?></span><?php endif; ?>
                                                        <?php if ($has_lang2): ?><span class="badge bg-secondary" style="cursor:pointer; position: relative; z-index: 10;" title="다국어 2 미리보기" onclick="previewItemDetail(JSON.parse(this.closest('.sort-item-row').querySelector('.menu-list-img').getAttribute('data-item')), '<?php echo $lang2_code; ?>')"><?php echo strtoupper($lang2_code); ?></span><?php endif; ?>
                                                        <?php if ($m['is_best']): ?><span class="badge bg-warning text-dark">추천</span><?php endif; ?>
                                                        <?php if ($m['is_new']): ?><span class="badge bg-info">신규</span><?php endif; ?>
                                                        <?php if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0): ?><span class="badge bg-danger">급매</span><?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end ms-md-3 w-100 w-md-auto border-top border-md-0 pt-2 pt-md-0 d-flex justify-content-end gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-item="<?php echo $safe_item_json; ?>" onclick="openEditItemModal(JSON.parse(this.getAttribute('data-item')))"><i class="bi bi-pencil"></i><span class="d-md-none ms-1">수정</span></button>
                                                    <a href="manage_shop.php?pg=manage_shop_item&del_item=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('삭제하시겠습니까?')"><i class="bi bi-trash"></i><span class="d-md-none ms-1">삭제</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>



</div>

<!-- --------------------------------------------------------- -->
<!-- --- [모달] 추가/수정 등 팝업 UI 분리 --- -->
<!-- --------------------------------------------------------- -->
<?php include __DIR__ . '/manage_shop_item_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    // 숫자에 콤마를 찍어주는 함수 추가
    function formatNumberInput(input) {
        let value = input.value.replace(/[^0-9]/g, ''); // 숫자 이외의 문자 제거
        if (value) {
            input.value = parseInt(value, 10).toLocaleString();
        } else {
            input.value = '';
        }
    }

    function calculateDiscount() {
        const priceInput = document.getElementById('item_price_display');
        const price = parseInt(priceInput.value.replace(/,/g, '')) || 0;
        const rate = parseInt(document.getElementById('item_discount_rate').value) || 0;
        
        document.getElementById('item_price').value = price; // 실제 서버에 전송될 값 세팅
        const discountDisplay = document.getElementById('item_discount_price_display');
        const discountHidden = document.getElementById('item_discount_price');
        
        if (rate > 0) {
            const calcPrice = Math.floor(price * (1 - rate / 100));
            discountDisplay.value = calcPrice.toLocaleString();
            discountHidden.value = calcPrice;
        } else {
            discountDisplay.value = price > 0 ? price.toLocaleString() : '';
            discountHidden.value = price;
        }
    }

    // [신규] YouTube 링크 입력 필드 추가
    function addYoutubeInput(url = '') {
        const container = document.getElementById('youtube-links-container');
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm';
        div.innerHTML = `
            <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
            <input type="url" class="form-control youtube-url-input" value="${url}" placeholder="https://youtube.com/watch?v=...">
            <button class="btn btn-outline-danger" type="button" onclick="deleteYoutubeInput(this)"><i class="bi bi-trash"></i></button>
        `;
        container.appendChild(div);
    }

    // [신규] YouTube 링크 입력 필드 삭제
    function deleteYoutubeInput(btn) {
        btn.closest('.input-group').remove();
    }

    // [신규] 폼 제출 전, 다중 YouTube 링크를 JSON으로 변환
    function prepareYoutubeUrls() {
        const container = document.getElementById('youtube-links-container');
        if (!container) return true;

        const inputs = container.querySelectorAll('.youtube-url-input');
        const urls = Array.from(inputs).map(input => input.value.trim()).filter(url => url);

        document.getElementById('item_youtube_url').value = JSON.stringify(urls);
        return true; // 폼 제출 계속
    }

    // [추가] 매물 상세 미리보기 (다국어 지원 및 읽기 전용 모드)
    function previewItemDetail(item, langCode = 'ko') {
        if (!item) return;

        let previewItem = {
            ...item
        };
        const langBadgeEl = document.getElementById('preview-lang-badge');

        if (langCode !== 'ko') {
            try {
                const trans = previewItem.translations ? JSON.parse(previewItem.translations) : {};
                if (trans[langCode]) {
                    previewItem.item_name = trans[langCode].item_name || previewItem.item_name;
                    previewItem.item_info = trans[langCode].item_info || previewItem.item_info;

                    langBadgeEl.innerText = langCode.toUpperCase() + ' 버전';
                    langBadgeEl.classList.remove('d-none');
                } else {
                    langBadgeEl.classList.add('d-none');
                }
            } catch (e) {
                langBadgeEl.classList.add('d-none');
            }
        } else {
            langBadgeEl.classList.add('d-none');
        }

        // 텍스트 바인딩
        document.getElementById('preview-item-name').innerText = previewItem.item_name || '';
        document.getElementById('preview-item-info').innerText = previewItem.item_info || '';

        // 가격 바인딩
        const finalPrice = document.getElementById('preview-final-price');
        const origPrice = document.getElementById('preview-original-price');
        const price = parseInt(previewItem.item_price) || 0;
        const discountRate = parseInt(previewItem.item_discount_rate) || 0;
        const discountPrice = parseInt(previewItem.item_discount_price) || 0;

        if (discountRate > 0) {
            finalPrice.innerText = '₱ ' + discountPrice.toLocaleString();
            origPrice.innerText = '₱ ' + price.toLocaleString();
            origPrice.classList.remove('d-none');
        } else {
            finalPrice.innerText = '₱ ' + price.toLocaleString();
            origPrice.classList.add('d-none');
        }

        // 배지 바인딩
        const badgesContainer = document.getElementById('preview-badges');
        let badgesHtml = '';
        if (previewItem.trade_type) badgesHtml += `<span class="badge bg-primary me-1">${previewItem.trade_type}</span>`;
        if (discountRate > 0) badgesHtml += `<span class="badge bg-danger me-1">급매</span>`;
        if (previewItem.is_best == 1) badgesHtml += `<span class="badge bg-warning text-dark me-1">추천</span>`;
        if (previewItem.is_new == 1) badgesHtml += `<span class="badge bg-info me-1">신규</span>`;
        badgesContainer.innerHTML = badgesHtml;

        // 미디어(이미지/유튜브) 바인딩
        const photoContainer = document.getElementById('preview-photo-container');
        const videoContainer = document.getElementById('preview-video-container');
        const photoTabBtn = document.querySelector('[data-bs-target="#preview-photo-pane"]');
        const videoTabBtn = document.querySelector('[data-bs-target="#preview-video-pane"]');
        let photoUrls = [];
        let videoUrls = [];

        if (previewItem.item_img) {
            try {
                let parsedImg = previewItem.item_img;
                if (typeof parsedImg === 'string' && parsedImg.startsWith('[')) parsedImg = JSON.parse(parsedImg);
                if (Array.isArray(parsedImg)) photoUrls = parsedImg.filter(url => url && url.trim() !== '');
                else photoUrls = [parsedImg];
            } catch (e) {
                photoUrls = [previewItem.item_img];
            }
        }

        if (previewItem.item_youtube_url) {
            try {
                let ytUrls = JSON.parse(previewItem.item_youtube_url);
                if (Array.isArray(ytUrls)) ytUrls.forEach(url => {
                    if (url && url.trim() !== '') videoUrls.push(url);
                });
                else if (typeof ytUrls === 'string' && ytUrls.trim() !== '') videoUrls.push(ytUrls);
            } catch (e) {
                if (previewItem.item_youtube_url.trim() !== '') videoUrls.push(previewItem.item_youtube_url);
            }
        }

        // 슬라이더 HTML 렌더링 (공통 함수 재사용)
        const photoCarouselId = 'preview-photo-carousel-' + previewItem.id;
        if (photoUrls.length > 0) {
            document.getElementById('preview-photo-tab-item').style.display = 'block';
            photoContainer.innerHTML = (typeof generateDynamicCarousel === 'function') ? generateDynamicCarousel(photoCarouselId, photoUrls) : `<img src="${photoUrls[0]}" class="w-100" style="aspect-ratio: 4/3; object-fit: cover;">`;
        } else {
            document.getElementById('preview-photo-tab-item').style.display = 'none';
            photoContainer.innerHTML = `<div class="d-flex justify-content-center align-items-center w-100 bg-light" style="aspect-ratio: 4/3;"><i class="bi bi-camera text-muted fs-1"></i></div>`;
        }

        const videoCarouselId = 'preview-video-carousel-' + previewItem.id;
        if (videoUrls.length > 0) {
            document.getElementById('preview-video-tab-item').style.display = 'block';
            videoContainer.innerHTML = (typeof generateDynamicCarousel === 'function') ? generateDynamicCarousel(videoCarouselId, videoUrls) : `<div class="d-flex justify-content-center align-items-center w-100 bg-light" style="aspect-ratio: 4/3;"><i class="bi bi-youtube text-muted fs-1"></i></div>`;
        } else {
            document.getElementById('preview-video-tab-item').style.display = 'none';
            videoContainer.innerHTML = '';
        }

        if (photoUrls.length > 0) bootstrap.Tab.getOrCreateInstance(photoTabBtn).show();
        else if (videoUrls.length > 0) bootstrap.Tab.getOrCreateInstance(videoTabBtn).show();

        const modalEl = document.getElementById('previewItemModal');

        const onModalShown = function() {
            if (photoUrls.length > 0 && typeof initDynamicCarousel === 'function') initDynamicCarousel(photoCarouselId);
            if (videoUrls.length > 0 && typeof initDynamicCarousel === 'function') initDynamicCarousel(videoCarouselId);
            modalEl.removeEventListener('shown.bs.modal', onModalShown);
        };
        modalEl.addEventListener('shown.bs.modal', onModalShown);

        // 모달이 닫힐 때 백그라운드 영상 재생을 완벽히 정지시킴
        modalEl.addEventListener('hidden.bs.modal', function() {
            videoContainer.innerHTML = '';
            photoContainer.innerHTML = '';
        }, {
            once: true
        });

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function openAddItemModal() {
        const modalEl = document.getElementById('itemModal');
        if (!modalEl) {
            alert('모달 요소를 찾을 수 없습니다.');
            return;
        }

        // Bootstrap 5의 안전한 모달 인스턴스 가져오기 (충돌 방지)
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);

        const form = modalEl.querySelector('form');
        if (form) form.reset();

        const titleEl = modalEl.querySelector('.modal-title');
        if (titleEl) titleEl.innerText = "새 매물 등록";

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        const submitBtn = document.getElementById('modal-submit-btn');
        if (submitBtn) submitBtn.name = "add_item";
        setVal('item_id', "");
        setVal('old_img_path', "");
        setVal('trade_type', "매매");
        setVal('item_price_display', "");
        setVal('item_discount_price_display', "");

        // [다국어] 동적으로 생성된 다국어 입력 필드 자동 초기화
        document.querySelectorAll('input[name^="item_name_"]').forEach(input => {
            const langCode = input.name.replace('item_name_', '');
            setVal('item_name_' + langCode, "");
            setVal('item_info_' + langCode, "");
        });

        // [수정] YouTube 링크 컨테이너 초기화
        const youtubeContainer = document.getElementById('youtube-links-container');
        if (youtubeContainer) {
            youtubeContainer.innerHTML = '';
            addYoutubeInput(); // 새 매물 등록 시 빈 입력창 1개 추가
        }

        const manager = typeof imageBatchManagers !== 'undefined' ? imageBatchManagers['item_images'] : null;
        if (manager) {
            // manager 객체의 내부 구조가 다를 경우를 모두 대비한 안전한 초기화
            if (manager.state) {
                manager.state.newFiles = [];
                manager.state.deletedItems = [];
            }
            manager.newFiles = [];
            manager.deletedItems = [];

            const container = manager.container || document.getElementById('item-image-container');
            if (container) {
                container.querySelectorAll('.gallery-item').forEach(item => item.remove());
                const emptyMsg = container.querySelector('.empty-msg');
                if (emptyMsg) emptyMsg.classList.remove('d-none');
            }
        }
        modal.show();
    }

    function openEditItemModal(item) {
        if (!item) return;

        const modalEl = document.getElementById('itemModal');
        if (!modalEl) return;

        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);

        const titleEl = modalEl.querySelector('.modal-title');
        if (titleEl) titleEl.innerText = "매물 정보 수정";

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        const setCheck = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.checked = val;
        };

        const submitBtn = document.getElementById('modal-submit-btn');
        if (submitBtn) submitBtn.name = "edit_item";
        setVal('item_id', item.id || "");
        setVal('cat_id', item.cat_id || "");
        setVal('trade_type', item.trade_type || "매매");
        setVal('item_name', item.item_name || "");
        setVal('item_youtube_url', item.item_youtube_url || "");
        
        const price = parseInt(item.item_price) || 0;
        setVal('item_price', price || "");
        setVal('item_price_display', price > 0 ? price.toLocaleString() : "");
        
        setVal('item_discount_rate', item.item_discount_rate || "");
        const dp = parseInt(item.item_discount_price || item.item_price) || 0;
        setVal('item_discount_price', dp || "");
        setVal('item_discount_price_display', dp > 0 ? dp.toLocaleString() : "");
        setVal('item_info', item.item_info || "");

        // [다국어] JSON 파싱 및 데이터 바인딩
        let trans = {};
        try {
            if (item.translations) trans = JSON.parse(item.translations);
        } catch (e) {}
        // 동적으로 생성된 모든 다국어 필드 찾아서 바인딩
        document.querySelectorAll('input[name^="item_name_"]').forEach(input => {
            const langCode = input.name.replace('item_name_', '');
            setVal('item_name_' + langCode, trans?.[langCode]?.item_name || "");
            setVal('item_info_' + langCode, trans?.[langCode]?.item_info || "");
        });

        setCheck('bestI', parseInt(item.is_best) === 1);
        setCheck('newI', parseInt(item.is_new) === 1);
        setCheck('soldoutI', parseInt(item.is_soldout) === 1);
        setCheck('hideI', parseInt(item.is_hide) === 1);

        // [수정] 다중 YouTube 링크 처리
        const youtubeContainer = document.getElementById('youtube-links-container');
        if (youtubeContainer) {
            youtubeContainer.innerHTML = '';
            let youtubeUrls = [];
            try {
                // 변수가 문자열이 아닐 경우 발생하는 TypeError 방지
                if (item.item_youtube_url && String(item.item_youtube_url).trim().startsWith('[')) {
                    youtubeUrls = JSON.parse(item.item_youtube_url);
                } else if (item.item_youtube_url) {
                    youtubeUrls = [item.item_youtube_url]; // 기존 단일 URL 호환
                }
            } catch (e) {
                if (item.item_youtube_url) youtubeUrls = [item.item_youtube_url];
            }
            if (youtubeUrls.length > 0) youtubeUrls.forEach(url => addYoutubeInput(url));
            else addYoutubeInput(); // 링크가 없으면 빈 입력창 1개 추가
        }

        const manager = typeof imageBatchManagers !== 'undefined' ? imageBatchManagers['item_images'] : null;
        if (manager) {
            if (manager.state) {
                manager.state.newFiles = [];
                manager.state.deletedItems = [];
            }
            manager.newFiles = [];
            manager.deletedItems = [];

            const container = manager.container || document.getElementById('item-image-container');
            if (container) {
                container.querySelectorAll('.gallery-item').forEach(el => el.remove());
                const imgPath = item.item_img || '';
                let paths = [];
                try {
                    paths = imgPath.startsWith('[') ? JSON.parse(imgPath) : (imgPath ? [imgPath] : []);
                } catch (e) {
                    paths = imgPath ? [imgPath] : [];
                }

                // 이중 인코딩 방어
                if (typeof paths === 'string' && paths.startsWith('[')) {
                    paths = JSON.parse(paths);
                }

                const emptyMsg = container.querySelector('.empty-msg');
                const addBtn = container.querySelector('.btn-add-img');

                if (paths.length > 0) {
                    if (emptyMsg) emptyMsg.classList.add('d-none');
                    paths.forEach((path, idx) => {
                        if (!path) return;
                        const div = document.createElement('div');
                        div.className = 'position-relative gallery-item';
                        div.id = `item_images-item-old_${idx}`;
                        div.dataset.path = path;
                        div.style.width = '100px';
                        div.style.height = '100px';
                        div.style.cursor = 'grab';
                        const badgeHtml = idx === 0 ? `<span class="badge bg-primary position-absolute top-0 start-0 m-1" style="font-size: 0.6rem;">대표</span>` : '';
                        div.innerHTML = `<img src="${path}" class="w-100 h-100 object-fit-cover rounded border shadow-sm">${badgeHtml}<button type="button" onclick="event.stopPropagation(); deleteBatchImage('item_images', 'old_${idx}')" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>`;
                        if (addBtn) container.insertBefore(div, addBtn);
                        else container.appendChild(div);
                    });
                } else {
                    if (emptyMsg) emptyMsg.classList.remove('d-none');
                }
            }
        }
        setVal('old_img_path', item.item_img || '');
        modal.show();
    }

    function viewImage(src) {
        const modal = new bootstrap.Modal(document.getElementById('imageViewModal'));
        const imgView = document.getElementById('modal-image-view');
        const noImgText = document.getElementById('modal-no-image-text');
        if (src.includes('no-logo.png') || !src) {
            imgView.style.display = 'none';
            if (noImgText) noImgText.style.setProperty('display', 'flex', 'important');
        } else {
            imgView.src = src;
            imgView.style.display = 'block';
            if (noImgText) noImgText.style.setProperty('display', 'none', 'important');
        }
        modal.show();
    }

    async function showItemMediaModal(item) {
        if (!item) return;
        let mediaItems = [];
        const imgPath = item.item_img || '';
        let imagePaths = [];
        if (imgPath) {
            try {
                // 이중 인코딩 방어
                let decoded = JSON.parse(imgPath);
                if (typeof decoded === 'string' && decoded.startsWith('[')) {
                    imagePaths = JSON.parse(decoded);
                } else if (Array.isArray(decoded)) {
                    imagePaths = decoded;
                }
            } catch (e) {
                imagePaths = imgPath.startsWith('/') ? [imgPath] : [];
            }
        }
        mediaItems = imagePaths.filter(p => p && p.trim() !== '');
        // [수정] 다중 유튜브 URL 파싱 처리
        if (item.item_youtube_url) {
            try {
                let ytUrls = JSON.parse(item.item_youtube_url);
                if (Array.isArray(ytUrls)) {
                    ytUrls.forEach(url => {
                        if (url && url.trim() !== '') mediaItems.push(url);
                    });
                } else if (typeof ytUrls === 'string' && ytUrls.trim() !== '') {
                    mediaItems.push(ytUrls);
                }
            } catch (e) {
                if (item.item_youtube_url.trim() !== '') mediaItems.push(item.item_youtube_url);
            }
        }

        if (mediaItems.length === 0) {
            viewImage('');
            return;
        }
        const modalTitleEl = document.getElementById('itemMediaModalTitle');
        const safeName = item.item_name.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        modalTitleEl.innerHTML = `${safeName} <span class="badge bg-dark ms-2">${mediaItems.length}개</span>`;
        const modalBody = document.getElementById('itemMediaModalBody');
        const carouselId = `item-media-carousel-${item.id}`;
        modalBody.innerHTML = (typeof generateDynamicCarousel === 'function') ?
            generateDynamicCarousel(carouselId, mediaItems) :
            '<div class="p-4 text-center text-danger">슬라이더를 로드할 수 없습니다.</div>';
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('itemMediaModal'));
        modal.show();
        document.getElementById('itemMediaModal').addEventListener('shown.bs.modal', () => {
            if (typeof initDynamicCarousel === 'function') initDynamicCarousel(carouselId);
        }, {
            once: true
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initImageBatchManager('item_images', {
            containerId: 'item-image-container',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: {
                target_id: <?php echo (int)$shop_id; ?>,
                table: 'shop_items',
                column: 'item_img',
                folder: 'itemimages'
            },
            sortable: true,
            hiddenOrderInputId: 'item_img_path'
        });

        if (typeof Sortable === 'undefined') return;

        const itemListEl = document.getElementById('item-list-sortable');
        if (itemListEl) {
            Sortable.create(itemListEl, {
                animation: 150,
                handle: '.drag-handle-item',
                filter: '.no-drag, button, a',
                preventOnFilter: false,
                ghostClass: 'bg-light',
                forceFallback: true,
                onEnd: async function() {
                    const rows = itemListEl.querySelectorAll('.sort-item-row');
                    const orderData = Array.from(rows).map(row => row.dataset.id);
                    const formData = new FormData();
                    formData.append('update_item_order', '1');
                    formData.append('order_data', JSON.stringify(orderData));
                    try {
                        const res = await fetch('manage_shop.php?pg=manage_shop_item', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                    } catch (err) {}
                }
            });
        }
    });
</script>