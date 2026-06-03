<?php

/**
 * KShops24 메뉴 관리 모듈 (manage_shop_menu.php)
 * * [업데이트 내역]
 * - 2026-02-19: 카테고리 그룹화 및 품절(is_soldout) 기능 추가
 * - 2026-02-20: 판매 중지(is_hide) 기능 추가 및 UI 고도화
 * - 2026-02-21: /shops/fnb/ 경로 구조에 따른 상대 경로 최적화
 * * [환경] PHP 8.x, PDO(MySQL), Hostinger Shared Hosting
 * [보안] $shop_id 변수를 통해 다른 상점의 메뉴를 수정할 수 없도록 격리 설계됨
 */

// 세션 또는 부모 페이지에서 정의된 $shop_id가 없으면 실행 중단 (비정상 접근 차단)
if (!isset($shop_id)) exit;

// ---------------------------------------------------------
// --- [A~C] 백엔드 액션(데이터 처리) 로직 분리 ---
// ---------------------------------------------------------
require_once __DIR__ . '/manage_shop_menu_action.php';

// ---------------------------------------------------------
// --- [D] 데이터 로딩 ---
// ---------------------------------------------------------

// 카테고리별 설정 파일 로드 (상수 정의)
$category_config_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/fnb/fnb_config.php";
if (file_exists($category_config_path)) {
    include_once $category_config_path;
}

// [데이터 로드] 카테고리 목록
$category_list = $pdo->prepare("SELECT * FROM shop_item_categories WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$category_list->execute([$shop_id]);
$category_list = $category_list->fetchAll();

// [데이터 로드] 실물 메뉴판 사진 리스트
$board_list = $pdo->prepare("SELECT * FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$board_list->execute([$shop_id]);
$board_list = $board_list->fetchAll();

// UI 설정 로드 (부모에서 정의되지 않았을 경우를 대비)
if (!isset($ui)) {
    $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
    $stmt_ui->execute([$shop_id]);
    $ui = json_decode($stmt_ui->fetchColumn() ?: '{}', true);
}

// [다국어] UI 설정에서 활성화된 언어 코드 가져오기 (JS에서 사용)
$lang1 = $ui['multilingual_lang1'] ?? 'none';
$lang2 = $ui['multilingual_lang2'] ?? 'none';
$lang1_code = $lang1;
$lang2_code = $lang2;
// [데이터 로드] 메뉴 통계 (전체, 품절, 숨김 수량 계산)
$stmt_stats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_soldout = 1 THEN 1 ELSE 0 END) as soldout,
    SUM(CASE WHEN is_hide = 1 THEN 1 ELSE 0 END) as hidden
    FROM shop_items WHERE shop_id = ?");
$stmt_stats->execute([$shop_id]);
$menu_stats = $stmt_stats->fetch();

// [데이터 로드] 전체 메뉴 리스트 (카테고리 순서 + 메뉴별 순서 기준)
$menu_list = $pdo->prepare("SELECT m.*, c.cat_name FROM shop_items m 
                             LEFT JOIN shop_item_categories c ON m.cat_id = c.id 
                             WHERE m.shop_id = ? 
                             ORDER BY c.sort_order ASC, m.sort_order ASC, m.id DESC");
$menu_list->execute([$shop_id]);
$all_menus = $menu_list->fetchAll();

// [데이터 가공] 화면 출력을 위해 카테고리별로 메뉴를 그룹화합니다.
$grouped_menus = [];
foreach ($all_menus as $m) {
    $cat_name = $m['cat_name'] ?: '기타';
    $grouped_menus[$cat_name][] = $m;
}
if (isset($grouped_menus['기타'])) {
    $temp_unassigned = $grouped_menus['기타'];
    unset($grouped_menus['기타']);
    $grouped_menus['기타'] = $temp_unassigned;
}
?>

<style>
    /* 실물 메뉴판 갤러리 썸네일을 감싸는 박스. 마우스를 올렸을 때 살짝 커지는(scale) 효과를 줍니다. */
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

    /* 주요 메뉴 리스트 테이블 좌측에 작게 표시되는 썸네일 이미지의 규격입니다. */
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

    /* 품절 또는 숨김 처리된 메뉴의 썸네일 이미지를 흑백으로 만들고 반투명하게 처리합니다. */
    .grayscale {
        filter: grayscale(100%);
        opacity: 0.5;
    }

    /* '메뉴 카테고리 설정' 박스 안에 들어가는 카테고리 이름 태그(배지)의 모양입니다. */
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

    /* 품절(Sold Out)된 메뉴가 위치한 테이블 행(Row)의 배경색을 살짝 붉게 만들고 전체 투명도를 낮춥니다. */
    .is_soldout-row {
        opacity: 0.7;
        background-color: #fffafa !important;
    }

    /* 숨김 처리된 메뉴가 위치한 테이블 행(Row)의 배경색을 회색빛으로 만듭니다. */
    .is-hide-row {
        background-color: #f1f3f5 !important;
        opacity: 0.6;
    }

    /* AJAX 요청 시 로딩 아이콘(스피너)이 빙글빙글 돌도록 해주는 애니메이션 클래스입니다. */
    .animate-spin {
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    /* [추가] 모달 내 이미지/영상 고정 크기 (4:3 비율) */
    #menuMediaModalBody {
        width: 100%;
        aspect-ratio: 4/3;
        background-color: #000;
        overflow: hidden;
    }

    #menuMediaModalBody .carousel,
    #menuMediaModalBody .carousel-inner,
    #menuMediaModalBody .carousel-item {
        width: 100%;
        height: 100%;
    }

    #menuMediaModalBody img,
    #menuMediaModalBody iframe {
        width: 100%;
        height: 100%;
        object-fit: contain;
        /* 원본 비율을 유지하며 잘리지 않게 함 */
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
    /**
     * [상태 메시지 알림 처리]
     * 상단의 PHP 로직(추가/수정/삭제 등)이 처리된 후 ?msg=xxx 파라미터와 함께 이 페이지로 리다이렉트되면,
     * 해당 msg 값에 맞는 적절한 토스트(Toast) 알림창을 우측 하단에 띄워줍니다.
     */
    if (isset($_GET['msg'])) {
        $msg_text = '';
        $msg_type = 'success';
        switch ($_GET['msg']) {
            case 'cat_added':
                $msg_text = '메뉴 카테고리가 추가되었습니다.';
                break;
            case 'cat_deleted':
                $msg_text = '카테고리가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'board_deleted':
                $msg_text = '메뉴판 이미지가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'menu_added':
                $msg_text = '신규 메뉴가 등록되었습니다.';
                break;
            case 'menu_updated':
                $msg_text = '메뉴 정보가 수정되었습니다.';
                break;
            case 'menu_deleted':
                $msg_text = '메뉴가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'label_updated':
                $msg_text = '레이블이 수정되었습니다.';
                break;
            case 'delivery_updated':
                $msg_text = '운영 및 배달 정책이 성공적으로 수정되었습니다.';
                break;
        }
        if ($msg_text) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg_text}', '{$msg_type}'); });</script>";
        }
    }
    ?>

    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('메뉴 관리', 'bi-list-stars'); ?>

    <div class="row g-4">
        <!-- 메뉴 등록 현황 -->
        <div class="col-12">
            <div class="<?php echo UI_SECTION_CARD; ?>">
                <div class="p-3 p-md-4 d-flex flex-column h-100">
                    <?php echo renderSectionHeader('메뉴 등록 현황', 'bi-bar-chart-line'); ?>
                    <div class="row text-center mt-2">
                        <div class="col-4 border-end">
                            <div class="text-muted small mb-1">총 등록 메뉴</div>
                            <div class="fw-bold fs-5 text-dark"><?php echo number_format($menu_stats['total'] ?? 0); ?>개</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="text-muted small mb-1">품절</div>
                            <div class="fw-bold fs-5 text-danger"><?php echo number_format($menu_stats['soldout'] ?? 0); ?>개</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small mb-1">숨김 처리</div>
                            <div class="fw-bold fs-5 text-muted"><?php echo number_format($menu_stats['hidden'] ?? 0); ?>개</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 메뉴 리스트 (드래그 앤 드롭으로 순서 변경 가능) -->
        <div class="col-12">
            <div class="<?php echo UI_SECTION_CARD; ?>">
                <div class="p-3 p-md-4 d-flex flex-column h-100">
                    <?php echo renderSectionHeader(
                        '메뉴 리스트',
                        'bi-grid-3x3-gap',
                        ['<i class="bi bi-info-circle me-1"></i> 메뉴를 마우스로 드래그하여 순서를 자유롭게 변경할 수 있습니다.'],
                        '<button class="btn btn-primary btn-sm rounded-pill px-3 ms-md-auto" onclick="openAddModal()"><i class="bi bi-plus-lg me-1"></i> 메뉴 추가</button>'
                    ); ?>

                    <!-- [모바일 최적화] 테이블(Table) 대신 리스트(List Group) 구조를 사용하여 가로 스크롤을 방지하고 반응형으로 구현 -->
                    <div class="list-group list-group-flush border-top" id="menu-items-sortable">
                        <?php if (empty($grouped_menus)): ?>
                            <div class="list-group-item text-center py-5 text-muted border-bottom">등록된 메뉴가 없습니다.</div>
                        <?php else: ?>
                            <?php foreach ($grouped_menus as $cat_title => $items): ?>
                                <!-- 카테고리 헤더 -->
                                <div class="list-group-item bg-light py-2 no-drag border-bottom">
                                    <div class="fw-bold text-dark"><i
                                            class="bi bi-folder2-open me-2"></i><?php echo htmlspecialchars($cat_title); ?> <span
                                            class="badge bg-secondary ms-2 fw-normal"><?php echo count($items); ?></span></div>
                                </div>
                                <?php foreach ($items as $m): ?>
                                    <div id="menu-row-<?php echo $m['id']; ?>" data-id="<?php echo $m['id']; ?>"
                                        class="list-group-item px-1 px-md-3 py-3 sort-menu-row border-bottom <?php echo $m['is_soldout'] ? 'is_soldout-row' : ''; ?> <?php echo $m['is_hide'] ? 'is-hide-row' : ''; ?>">
                                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 gap-md-3 w-100">

                                            <div class="d-flex align-items-start flex-grow-1" style="min-width: 0;">
                                                <div class="drag-handle-menu text-muted me-1 me-md-2 mt-2 flex-shrink-0" style="cursor: grab;"><i class="bi bi-grip-vertical fs-5"></i></div>
                                                <?php
                                                $img_val = $m['item_img'] ?? '';
                                                $display_thumb = '/assets/img/no-food.png';
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
                                                    $decoded_img = json_decode($img_val, true);
                                                    if (is_array($decoded_img)) {
                                                        $valid_imgs = array_filter($decoded_img);
                                                        if (!empty($valid_imgs)) {
                                                            $display_thumb = array_values($valid_imgs)[0];
                                                            $img_count = count($valid_imgs);
                                                        }
                                                    } else if ($img_val && $img_val[0] !== '[') {
                                                        $display_thumb = $img_val;
                                                        $img_count = 1;
                                                    }
                                                }

                                                $safe_item_json = htmlspecialchars(json_encode($m, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

                                                $translations = [];
                                                if (!empty($m['translations'])) {
                                                    $decoded_trans = json_decode(htmlspecialchars_decode($m['translations']), true);
                                                    if (is_string($decoded_trans)) $decoded_trans = json_decode($decoded_trans, true);
                                                    if (is_array($decoded_trans)) $translations = $decoded_trans;
                                                }
                                                $has_lang1 = ($lang1 !== 'none' && (!empty($translations[$lang1_code]['item_name']) || !empty($translations[$lang1_code]['item_info'])));
                                                $has_lang2 = ($lang2 !== 'none' && (!empty($translations[$lang2_code]['item_name']) || !empty($translations[$lang2_code]['item_info'])));
                                                ?>
                                                <div class="d-flex flex-column align-items-center me-2 me-md-3 flex-shrink-0">
                                                    <img src="<?php echo $display_thumb; ?>" class="menu-list-img shadow-sm <?php echo $m['is_soldout'] || $m['is_hide'] ? 'grayscale' : ''; ?>" data-item="<?php echo $safe_item_json; ?>" onclick="showMenuMediaModal(JSON.parse(this.getAttribute('data-item')))" style="cursor: pointer;" title="이미지/영상 보기">
                                                    <?php if ($img_count > 1 || $video_count > 0): ?>
                                                        <div class="mt-1 d-flex gap-1 justify-content-center w-100">
                                                            <?php if ($img_count > 1): ?><span class="badge bg-secondary" style="font-size: 0.6rem; padding: 0.25em 0.4em; font-weight: normal;"><i class="bi bi-images me-1"></i><?php echo $img_count; ?></span><?php endif; ?>
                                                            <?php if ($video_count > 0): ?><span class="badge bg-danger" style="font-size: 0.6rem; padding: 0.25em 0.4em; font-weight: normal;"><i class="bi bi-youtube me-1"></i><?php echo $video_count; ?></span><?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-flex flex-column justify-content-center flex-grow-1" style="min-width: 0;">
                                                    <div class="fw-bold mb-1 text-truncate">
                                                        <?php if ($m['is_hide']): ?><span class="text-muted small">[숨김]</span><?php endif; ?>
                                                        <?php if ($m['is_soldout']): ?><span class="text-danger">[품절]</span><?php endif; ?>
                                                    </div>
                                                    <div class="fw-bold text-truncate mb-1" style="font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars($m['item_name'] ?? ''); ?>
                                                    </div>
                                                    <div class="text-secondary small item-info-text"><?php echo htmlspecialchars($m['item_info'] ?? ''); ?></div>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-end align-items-md-center flex-md-shrink-0 mt-2 mt-md-0 w-100 w-md-auto info-action-container">
                                                <div class="d-flex flex-row flex-md-column justify-content-between align-items-center align-items-md-end gap-2 gap-md-1 w-100 w-md-auto mb-2 mb-md-0">
                                                    <div class="fw-bold text-primary">
                                                        <?php if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0): ?>
                                                            <?php echo number_format((float)($m['item_discount_price'] ?? 0)); ?> ₱
                                                            <span class="text-danger small fw-normal ms-1">(<?php echo $m['item_discount_rate']; ?>%)</span>
                                                        <?php else: ?>
                                                            <?php echo number_format((float)($m['item_price'] ?? 0)); ?> ₱
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                                        <?php if ($has_lang1): ?><span class="badge bg-secondary" title="다국어 1"><?php echo strtoupper($lang1_code); ?></span><?php endif; ?>
                                                        <?php if ($has_lang2): ?><span class="badge bg-secondary" title="다국어 2"><?php echo strtoupper($lang2_code); ?></span><?php endif; ?>
                                                        <?php if ($m['is_best']): ?><span class="badge bg-warning text-dark">BEST</span><?php endif; ?>
                                                        <?php if ($m['is_new']): ?><span class="badge bg-info">NEW</span><?php endif; ?>
                                                        <?php if (!empty($m['item_discount_rate']) && $m['item_discount_rate'] > 0): ?><span class="badge bg-danger">할인</span><?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="text-end ms-md-3 w-100 w-md-auto border-top border-md-0 pt-2 pt-md-0 d-flex justify-content-end gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-item="<?php echo $safe_item_json; ?>" onclick="openEditModal(JSON.parse(this.getAttribute('data-item')))"><i class="bi bi-pencil"></i><span class="d-md-none ms-1">수정</span></button>
                                                    <a href="manage_shop.php?pg=manage_shop_menu&del_menu=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('삭제하시겠습니까?')"><i class="bi bi-trash"></i><span class="d-md-none ms-1">삭제</span></a>
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
<?php include __DIR__ . '/manage_shop_menu_modals.php'; ?>

<!-- [버그 수정] 드래그 앤 드롭 기능을 위한 SortableJS 라이브러리 추가 -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    /**
     * [JS 함수] 할인 금액 자동 계산
     * 사용자가 '정상 가격'이나 '할인율' 칸에 숫자를 입력할 때마다 즉시 실행됩니다.
     * 입력된 비율에 맞춰 '할인 가격'을 자동으로 계산하여 읽기 전용(readonly) 필드에 채워줍니다.
     */
    function calculateDiscount() {
        const price = parseInt(document.getElementById('item_price').value) || 0;
        const rate = parseInt(document.getElementById('item_discount_rate').value) || 0;
        const discountPriceInput = document.getElementById('item_discount_price');

        if (rate > 0) {
            const discounted = Math.floor(price * (1 - rate / 100));
            discountPriceInput.value = discounted;
        } else {
            discountPriceInput.value = price;
        }
    }

    /**
     * [JS 함수] 메뉴 신규 등록 모달 열기
     * 이전 입력 데이터가 남아있지 않도록 폼(Form)을 초기화하고, 이미지 배열을 비운 상태로 새 모달창을 띄웁니다.
     */
    function openAddModal() {
        const modal = new bootstrap.Modal(document.getElementById('menuModal'));
        const form = document.querySelector('#menuModal form');
        form.reset(); // 폼의 모든 입력 필드를 초기화합니다.

        document.querySelector('#menuModal .modal-title').innerText = "새 메뉴 등록";
        document.getElementById('modal-submit-btn').name = "add_menu";
        document.getElementById('item_id').value = "";
        document.getElementById('old_img_path').value = "";

        // [추가] 다국어 필드 초기화
        const lang1Code = '<?php echo $lang1_code; ?>';
        const lang2Code = '<?php echo $lang2_code; ?>';
        if (document.getElementById(`item_name_${lang1Code}`)) {
            document.getElementById(`item_name_${lang1Code}`).value = '';
            document.getElementById(`item_info_${lang1Code}`).value = '';
        }
        if (document.getElementById(`item_name_${lang2Code}`)) {
            document.getElementById(`item_name_${lang2Code}`).value = '';
            document.getElementById(`item_info_${lang2Code}`).value = '';
        }

        // [버그 수정] 스마트 이미지 일괄 처리 모듈을 사용하여 이미지 미리보기 영역을 초기화합니다.
        const manager = imageBatchManagers['menu_images'];
        if (manager) {
            manager.state.newFiles = [];
            manager.state.deletedItems = [];
            const container = manager.container;
            if (container) {
                container.querySelectorAll('.gallery-item').forEach(item => item.remove());
                const emptyMsg = container.querySelector('.empty-msg');
                if (emptyMsg) emptyMsg.classList.remove('d-none');
            }
        }
        modal.show();
    }

    /**
     * [JS 함수] 메뉴 정보 수정 모달 열기
     * 리스트에서 '수정' 버튼을 클릭한 특정 메뉴의 기존 데이터(이름, 가격, 할인, 상태, 이미지 등)를 
     * 모달 내의 각 폼 필드에 자동으로 매핑(채워넣기)한 후 모달창을 띄웁니다.
     * @param {object} menu - PHP에서 JSON 형태로 변환되어 넘어온 개별 메뉴 데이터 객체
     */
    function openEditModal(menu) {
        const modal = new bootstrap.Modal(document.getElementById('menuModal'));
        document.querySelector('#menuModal .modal-title').innerText = "메뉴 정보 수정";
        document.getElementById('modal-submit-btn').name = "edit_menu";
        document.getElementById('item_id').value = menu.id;
        document.getElementById('cat_id').value = menu.cat_id || "";
        document.getElementById('item_name').value = menu.item_name;
        document.getElementById('item_youtube_url').value = menu.item_youtube_url || "";
        document.getElementById('item_price').value = menu.item_price;
        document.getElementById('item_discount_rate').value = menu.item_discount_rate || "";
        document.getElementById('item_discount_price').value = menu.item_discount_price || menu.item_price;
        document.getElementById('item_info').value = menu.item_info || "";
        document.getElementById('bestM').checked = (parseInt(menu.is_best) === 1);
        document.getElementById('newM').checked = (parseInt(menu.is_new) === 1);
        document.getElementById('soldoutM').checked = (parseInt(menu.is_soldout) === 1);
        document.getElementById('hideM').checked = (parseInt(menu.is_hide) === 1);

        // [추가] 다국어 필드 채우기
        const translations = menu.translations ? JSON.parse(menu.translations) : {};
        const lang1Code = '<?php echo $lang1_code; ?>';
        const lang2Code = '<?php echo $lang2_code; ?>';

        if (document.getElementById(`item_name_${lang1Code}`)) {
            document.getElementById(`item_name_${lang1Code}`).value = translations[lang1Code]?.item_name || '';
            document.getElementById(`item_info_${lang1Code}`).value = translations[lang1Code]?.item_info || '';
        }
        if (document.getElementById(`item_name_${lang2Code}`)) {
            document.getElementById(`item_name_${lang2Code}`).value = translations[lang2Code]?.item_name || '';
            document.getElementById(`item_info_${lang2Code}`).value = translations[lang2Code]?.item_info || '';
        }

        // [리팩토링] 기존 이미지 로드 및 렌더링
        const manager = imageBatchManagers['menu_images'];
        if (manager) {
            manager.state.newFiles = [];
            manager.state.deletedItems = [];
            const container = manager.container;
            if (container) {
                container.querySelectorAll('.gallery-item').forEach(item => item.remove());

                const imgPath = menu.item_img || '';
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
                        if (!path) return; // Bootstrap 그리드 시스템을 사용하여 반응형 레이아웃 적용 (모바일 2개, PC 3개)
                        const div = document.createElement('div');
                        div.className = 'col-6 col-md-4 gallery-item';
                        div.id = `menu_images-item-old_${idx}`;
                        div.dataset.path = path;
                        div.style.cursor = 'grab';

                        const badgeHtml = idx === 0 ? `<span class="badge bg-primary position-absolute top-0 start-0 m-1" style="font-size: 0.6rem;">대표</span>` : '';

                        div.innerHTML = `
                        <div class="position-relative">
                            <img src="${path}" class="w-100 rounded border shadow-sm" style="aspect-ratio: 4/3; object-fit: cover;">
                            ${badgeHtml}
                            <button type="button" onclick="event.stopPropagation(); deleteBatchImage('menu_images', 'old_${idx}')" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                        </div>
                    `;
                        if (addBtn) {
                            container.insertBefore(div, addBtn);
                        } else {
                            container.appendChild(div);
                        }
                    });
                } else {
                    if (emptyMsg) emptyMsg.classList.remove('d-none');
                }
            }
        }

        document.getElementById('old_img_path').value = menu.item_img || '';
        modal.show();
    }

    /**
     * [JS 함수] 이미지 확대 보기
     */
    function viewImage(src) {
        const modal = new bootstrap.Modal(document.getElementById('imageViewModal'));
        const imgView = document.getElementById('modal-image-view');
        const noImgText = document.getElementById('modal-no-image-text');

        if (src.includes('no-food.png') || !src) {
            imgView.style.display = 'none';
            if (noImgText) noImgText.style.setProperty('display', 'flex', 'important');
        } else {
            imgView.src = src;
            imgView.style.display = 'block';
            if (noImgText) noImgText.style.setProperty('display', 'none', 'important');
        }

        modal.show();
    }

    /**
     * [추가] 메뉴의 모든 이미지와 유튜브 영상을 슬라이더로 보여주는 모달
     * @param {object} menu - PHP에서 JSON으로 인코딩된 메뉴 객체
     */
    async function showMenuMediaModal(menu) {
        if (!menu) return;

        // 1. 미디어 데이터 준비 (이미지 + 유튜브)
        let mediaItems = [];
        const imgPath = menu.item_img || '';
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
                // JSON 파싱 실패 시 일반 문자열로 간주
                if (imgPath.startsWith('/')) {
                    imagePaths = [imgPath];
                }
            }
        }

        // 빈 경로 제거
        mediaItems = imagePaths.filter(p => p && p.trim() !== '');

        if (menu.item_youtube_url) {
            mediaItems.push(menu.item_youtube_url);
        }

        if (mediaItems.length === 0) {
            viewImage(''); // 이미지가 없으면 '등록된 사진이 없습니다' 모달을 재활용
            return;
        }

        // 2. 모달 제목 설정
        const modalTitleEl = document.getElementById('menuMediaModalTitle');
        const safeMenuName = menu.item_name.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        modalTitleEl.innerHTML = `${safeMenuName} <span class="badge bg-dark ms-2">${mediaItems.length}개</span>`;

        // 3. 공통 캐러셀 엔진 호출
        const modalBody = document.getElementById('menuMediaModalBody');
        const carouselId = `menu-media-carousel-${menu.id}`;

        modalBody.innerHTML = (typeof generateDynamicCarousel === 'function') ?
            generateDynamicCarousel(carouselId, mediaItems) :
            '<div class="p-4 text-center text-danger">슬라이더 생성 모듈(generateDynamicCarousel)을 찾을 수 없습니다.</div>';

        // 4. 모달 표시 및 캐러셀 활성화
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuMediaModal'));
        modal.show();
        document.getElementById('menuMediaModal').addEventListener('shown.bs.modal', () => {
            if (typeof initDynamicCarousel === 'function') initDynamicCarousel(carouselId);
        }, {
            once: true
        });
    }

    /**
     * [JS 함수] 카테고리 이름 수정 (AJAX)
     */
    async function editCategoryName(catId, oldName) {
        const newName = prompt('새로운 카테고리 이름을 입력하세요:', oldName);

        if (newName === null || newName.trim() === '' || newName.trim() === oldName) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'edit_category');
        formData.append('cat_id', catId);
        formData.append('new_name', newName.trim());

        try {
            const response = await fetch('manage_shop.php?pg=manage_shop_menu', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const result = await response.json();

            if (result.status === 'success') {
                location.reload();
            } else {
                alert('수정 실패: ' + result.message);
            }
        } catch (err) {
            alert('통신 오류가 발생했습니다.');
        }
    }

    /**
     * [JS 함수] UI 레이블 및 순서 자동 저장 (AJAX)
     */
    async function saveUiLabels() {
        const form = document.getElementById('ui-labels-form');
        const formData = new FormData(form);

        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_menu', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.status === 'success') {
                const badge = document.getElementById('save-success-badge');
                badge.classList.remove('d-none');
                setTimeout(() => badge.classList.add('d-none'), 2000); // 2초 후 메시지 숨김
            }
        } catch (err) {
            console.error('Save error:', err);
        }
    }

    /**
     * [JS 함수] 배달 정책 홈페이지 노출 여부 토글 (AJAX)
     */
    function toggleDelivery(el, syncId = null) {
        const isShow = el.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('action', 'toggle_delivery_display');
        formData.append('is_show', isShow);

        // 모바일/PC 버튼 상태 동기화
        if (syncId) {
            const syncEl = document.getElementById(syncId);
            if (syncEl) syncEl.checked = el.checked;
        } else {
            const mobileEl = document.getElementById('toggleDeliveryDisplayMobile');
            if (mobileEl) mobileEl.checked = el.checked;
        }

        fetch('manage_shop.php?pg=manage_shop_menu', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast('배달 정책 노출 설정이 변경되었습니다.', 'success');
            } else {
                alert('변경 실패: ' + data.message);
                el.checked = !el.checked;
            }
        }).catch(err => {
            alert('통신 오류가 발생했습니다.');
            el.checked = !el.checked;
            if (syncId) {
                const syncEl = document.getElementById(syncId);
                if (syncEl) syncEl.checked = el.checked;
            } else {
                const mobileEl = document.getElementById('toggleDeliveryDisplayMobile');
                if (mobileEl) mobileEl.checked = el.checked;
            }
        });
    }

    // [버그 수정] 모듈 초기화 함수 분리 (AJAX 로드 시에도 강제 초기화 대응)
    function initMenuPageModules() {
        initImageBatchManager('menu_images', {
            containerId: 'menu-image-container',
            itemClass: 'col-6 col-md-4 gallery-item',
            aspectRatio: '4/3',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: {
                target_id: <?php echo $shop_id; ?>,
                table: 'shop_items',
                column: 'item_img',
                folder: 'itemimages'
            },
            sortable: true,
            hiddenOrderInputId: 'item_img_path'
        });

        if (typeof Sortable === 'undefined') return;

        // 3. 주요 메뉴 리스트 정렬
        const menuListEl = document.getElementById('menu-items-sortable');
        if (menuListEl) {
            Sortable.create(menuListEl, {
                animation: 150,
                handle: '.drag-handle-menu',
                filter: '.no-drag, button, a',
                preventOnFilter: false,
                ghostClass: 'bg-light',
                delay: 0,
                touchStartThreshold: 5,
                fallbackOnBody: true,
                forceFallback: true, // [추가] 아이폰 사파리 터치 충돌 방지
                onEnd: async function() {
                    const rows = menuListEl.querySelectorAll('.sort-menu-row');
                    const orderData = Array.from(rows).map(row => row.dataset.id);
                    const formData = new FormData();
                    formData.append('update_menu_order', '1');
                    formData.append('order_data', JSON.stringify(orderData));
                    try {
                        const res = await fetch('manage_shop.php?pg=manage_shop_menu', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (data.status !== 'success') alert('메뉴 순서 저장에 실패했습니다.');
                    } catch (err) {
                        console.error('Order update error:', err);
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenuPageModules);
    } else {
        setTimeout(initMenuPageModules, 100);
    }
</script>