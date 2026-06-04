<?php

/**
 * KShops24 상점 정책 및 카테고리 관리 (manage_shop_menu_policy.php)
 * - 기존 메뉴 관리에서 분리된 정책, 레이블, 실물 메뉴판, 카테고리 관리 역할
 */

if (!isset($shop_id)) exit;

require_once __DIR__ . '/manage_shop_menu_action.php';

// 카테고리별 설정 파일 로드
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

// UI 설정 로드
if (!isset($ui)) {
    $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
    $stmt_ui->execute([$shop_id]);
    $ui = json_decode($stmt_ui->fetchColumn() ?: '{}', true);
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

    .animate-spin {
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
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
    <?php echo renderPageHeader('메뉴 정책 관리', 'bi-ui-checks'); ?>

    <!-- [섹션 1] 배달 정책 요약 -->
    <div class="col-12">
        <div class="<?php echo UI_SECTION_CARD; ?>">
            <!-- [개선 1] 배달 정책 요약 (모바일 최적화된 카드형 레이아웃으로 재구성) -->
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <?php
                $delivery_checked = (!isset($shop['is_show_delivery']) || $shop['is_show_delivery'] == 1) ? 'checked' : '';
                $right_btn_html = '
                <div class="d-flex align-items-center gap-2 gap-md-3 justify-content-center justify-content-md-end">
                    <div class="form-check form-switch m-0 d-flex align-items-center border border-md bg-light rounded-pill px-3 py-1 px-md-3 py-md-1 shadow-sm">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="toggleDeliveryDisplay" ' . $delivery_checked . ' onchange="toggleDelivery(this)">
                        <label class="form-check-label small fw-bold text-primary mb-0" for="toggleDeliveryDisplay" style="cursor: pointer; white-space: nowrap;">홈페이지 노출</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#editDeliveryModal" style="font-size: 0.75rem;">
                        <i class="bi bi-pencil-square me-1"></i> 정책 수정
                    </button>
                </div>';
                echo renderSectionHeader('상점 배달 정책', 'bi-truck', [], $right_btn_html);
                ?>
                <div class="row d-none d-md-flex g-0">
                    <div class="col-md-6 border-end">
                        <dl class="row mb-0 small">
                            <dt class="col-4 text-muted mb-0">배달 가능 시간</dt>
                            <dd class="col-8 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></dd>
                            <dt class="col-4 text-muted mt-2 mb-0">배달비 안내</dt>
                            <dd class="col-8 mt-2 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></dd>
                            <dt class="col-4 text-muted mt-2 mb-0">배달비</dt>
                            <dd class="col-8 mt-2 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_fee'] ?: '미입력'); ?></dd>
                            <dt class="col-4 text-muted mt-2 mb-0">무료 배달 주문액</dt>
                            <dd class="col-8 mt-2 mb-0 fw-bold"><?php echo htmlspecialchars($shop['free_delivery_amount'] ?: '무료배달 정책 없음'); ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted mb-2">최소 주문 금액</dt>
                            <dd class="col-7 mb-2 fw-bold text-primary">₱ <?php echo number_format($shop['min_delivery_amount'] ?? 0); ?></dd>
                            <dt class="col-5 text-muted mb-0">예상 배달 시간</dt>
                            <dd class="col-7 mb-0 fw-bold"><?php echo htmlspecialchars($shop['estimated_delivery_time'] ?: '주문 후 30~50분'); ?></dd>
                            <dt class="col-5 text-muted mt-2 mb-0">매장픽업 가능</dt>
                            <dd class="col-7 mt-2 mb-0"><?php echo ($shop['is_pickup_available'] == 1) ? '<span class="text-success fw-bold">가능</span>' : '<span class="text-danger fw-bold">불가</span>'; ?></dd>
                        </dl>
                    </div>
                </div>
                <!-- [개선 1] 모바일에서는 아이콘과 텍스트를 한 줄로 배치하여 공간 활용 극대화 -->
                <div class="d-md-none">
                    <div class="bg-light p-3 rounded-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">배달 가능 시간:</span>
                            <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">배달 최소 주문 금액:</span>
                            <span class="fw-bold text-primary small text-end">₱ <?php echo number_format($shop['min_delivery_amount'] ?? 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">배달비:</span>
                            <span class="fw-bold text-primary small text-end">₱ <?php echo number_format($shop['delivery_fee'] ?? 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">무료 배달 주문액:</span>
                            <span class="fw-bold text-primary small text-end"><?php echo htmlspecialchars($shop['free_delivery_amount'] ?: '무료배달 정책 없음'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">예상 배달 시간:</span>
                            <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['estimated_delivery_time'] ?: '-'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">배달비 정책:</span>
                            <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '문의'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">결제 수단:</span>
                            <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['payment_methods'] ?: '문의'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">매장픽업 가능:</span>
                            <span class="fw-bold small text-end"><?php echo ($shop['is_pickup_available'] == 1) ? '<span class="text-success">가능</span>' : '<span class="text-danger">불가</span>'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- [섹션 2] 실물 메뉴판 사진 관리 -->
            <div class="col-12">
                <div class="<?php echo UI_SECTION_CARD; ?>">
                    <form id="itemboard-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="saveImageBatch(event, 'itemboard_images')">
                        <input type="hidden" name="itemboard_order" id="itemboard_order_input" value="[]">
                        <?php echo renderSectionHeader(
                            htmlspecialchars($ui['label_menu_board'] ?? (defined('FNB_DEFAULT_LABEL_MENU_BOARD') ? FNB_DEFAULT_LABEL_MENU_BOARD : '실물 메뉴판')),
                            'bi-images',
                            ['<i class="bi bi-info-circle me-1"></i> 메뉴판 사진은 <span class="text-primary fw-bold">3:4 세로 비율</span> 촬영이 가장 깔끔합니다. 이미지를 드래그하여 순서를 바꿀 수 있습니다. 수정 후에는 꼭 "실물 메뉴판 저장" 버튼을 눌러주세요.'],
                            '<label for="itemboard-multi-input" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm mb-0" style="font-size: 0.75rem; cursor: pointer;"><i class="bi bi-plus-lg me-1"></i>사진 추가</label>'
                        ); ?>
                        <div class="mb-3">
                            <div class="row g-2 p-3 border rounded shadow-inner bg-light row-cols-2 row-cols-md-5" id="itemboard-image-container" style="min-height: 120px;">
                                <?php if (!empty($board_list)): foreach ($board_list as $board): ?>
                                        <div class="col gallery-item" id="itemboard_images-item-<?php echo $board['id']; ?>" data-path="<?php echo htmlspecialchars($board['board_img_path'], ENT_QUOTES); ?>" style="cursor: grab;">
                                            <div class="position-relative">
                                                <img src="<?php echo htmlspecialchars($board['board_img_path']); ?>" class="w-100 rounded border shadow-sm" style="aspect-ratio: 3/4; object-fit: cover;">
                                                <button type="button" onclick="event.stopPropagation(); deleteBatchImage('itemboard_images', <?php echo $board['id']; ?>)" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                                            </div>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                                <div class="empty-msg text-muted small w-100 text-center my-auto py-2 <?php echo empty($board_list) ? '' : 'd-none'; ?>">등록된 메뉴판 사진이 없습니다.</div>
                                <button type="button" class="btn-add-img d-none"></button>
                            </div>
                            <input type="file" id="itemboard-multi-input" class="d-none" accept="image/*" multiple onchange="addBatchImage('itemboard_images', this)">
                        </div>
                        <div class="mt-auto text-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 메뉴판 저장</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- [섹션 3] 메뉴 섹션 설정 -->
            <div class="col-12">
                <div class="<?php echo UI_SECTION_CARD; ?>">
                    <form id="ui-labels-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="return false;">
                        <input type="hidden" name="update_ui_labels_bulk" value="1">
                        <?php echo renderSectionHeader(
                            '메뉴 섹션 설정 (레이블 및 순서)',
                            'bi-sort-numeric-down',
                            [
                                '<i class="bi bi-info-circle me-1"></i> 각 항목의 오른쪽의 핸들 아이콘을 위/아래로 드래그하여 <strong>순서를 조정하거나 값을 수정</strong>할 수 있습니다(<strong>자동 저장</strong>).',
                                '<i class="bi bi-emoji-smile me-1 text-warning"></i><a href="https://getemoji.com/" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Get Emoji</a>에 접속해서 <strong>멋진 이모지</strong>들(👑 🍗 🍝 🍺 ...)을 사용하세요.'
                            ],
                            '<span class="badge bg-success d-none" id="save-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
                        ); ?>
                        <div id="sortable-section-list" class="list-group list-group-flush border-top border-bottom">
                            <?php
                            $lang1 = $ui['multilingual_lang1'] ?? 'none';
                            $lang2 = $ui['multilingual_lang2'] ?? 'none';
                            $lang1_code = $lang1;
                            $lang2_code = $lang2;
                            // [다국어] 설정된 언어의 사전 파일 로드 (기본값 번역 노출용)
                            $lang1_dict = [];
                            if ($lang1 !== 'none') {
                                $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . "/common/lang/{$lang1_code}.php";
                                if (file_exists($file_path)) $lang1_dict = include $file_path;
                            }

                            $lang2_dict = [];
                            if ($lang2 !== 'none') {
                                $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . "/common/lang/{$lang2_code}.php";
                                if (file_exists($file_path)) $lang2_dict = include $file_path;
                            }

                            $sections = [
                                ['name' => '실물 메뉴판', 'key' => 'menu_board',    'default' => FNB_DEFAULT_LABEL_MENU_BOARD,    'color' => ''],
                                ['name' => '할인 메뉴',   'key' => 'discount_menu', 'default' => FNB_DEFAULT_LABEL_DISCOUNT_MENU, 'color' => ''],
                                ['name' => '신메뉴',      'key' => 'new_menu',      'default' => FNB_DEFAULT_LABEL_NEW_MENU,      'color' => ''],
                                ['name' => '인기 메뉴',   'key' => 'best_menu',     'default' => FNB_DEFAULT_LABEL_BEST_MENU,     'color' => ''],
                                ['name' => '모든 메뉴',   'key' => 'all_menu',      'default' => FNB_DEFAULT_LABEL_ALL_MENU,      'color' => '']
                            ];
                            uasort($sections, function ($a, $b) use ($ui) {
                                $orderA = (int)($ui['order_' . $a['key']] ?? 99);
                                $orderB = (int)($ui['order_' . $b['key']] ?? 99);
                                return $orderA <=> $orderB;
                            });
                            foreach ($sections as $s):
                                $key = $s['key'];
                                $val = $ui['label_' . $key] ?? '';
                                $order = $ui['order_' . $key] ?? 1;
                            ?>
                                <div class="list-group-item px-3 py-3 border rounded-3 mb-2 d-flex align-items-center gap-3 sort-item bg-white shadow-xs">
                                    <div class="flex-grow-1">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-3 small fw-bold text-muted"><?php echo $s['name']; ?></div>
                                            <div class="col-md-9">
                                                <div class="d-flex flex-column gap-1">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                                                        <input type="text" name="label_<?php echo $key; ?>" class="form-control border-start-0 <?php echo $s['color']; ?>" value="<?php echo htmlspecialchars($val); ?>" placeholder="기본값: <?php echo htmlspecialchars($s['default']); ?>" onchange="saveUiLabels()">
                                                    </div>
                                                    <?php if ($lang1 !== 'none'): ?>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                                            <input type="text" name="label_<?php echo $key; ?>_<?php echo $lang1_code; ?>" class="form-control border-start-0 <?php echo $s['color']; ?>" value="<?php echo htmlspecialchars($ui['label_' . $key . '_' . $lang1_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars($lang1_dict[$s['default']] ?? $s['default']); ?>" onchange="saveUiLabels()">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($lang2 !== 'none'): ?>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                                            <input type="text" name="label_<?php echo $key; ?>_<?php echo $lang2_code; ?>" class="form-control border-start-0 <?php echo $s['color']; ?>" value="<?php echo htmlspecialchars($ui['label_' . $key . '_' . $lang2_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars($lang2_dict[$s['default']] ?? $s['default']); ?>" onchange="saveUiLabels()">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="hidden" name="order_<?php echo $key; ?>" class="order-input" value="<?php echo $order; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="drag-handle text-muted cursor-move" style="cursor: grab;"><i class="bi bi-list fs-4"></i></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- [섹션 4] 메뉴 카테고리 관리 -->
            <div class="col-12">
                <div class="<?php echo UI_SECTION_CARD; ?>">
                    <div class="p-3 p-md-4 d-flex flex-column h-100">
                        <?php echo renderSectionHeader(
                            '모든 메뉴 카테고리 설정',
                            'bi-tag',
                            [
                                '<i class="bi bi-info-circle me-1"></i> 각 항목의 오른쪽의 핸들 아이콘을 위/아래로 드래그하여 <strong>순서를 조정하거나 값을 수정</strong>할 수 있습니다(<strong>자동 저장</strong>).',
                                '<i class="bi bi-emoji-smile me-1 text-warning"></i><a href="https://getemoji.com/" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Get Emoji</a>에 접속해서 <strong>멋진 이모지</strong>들(👑 🍗 🍝 🍺 ...)을 사용하세요.'
                            ],
                            '<span class="badge bg-success d-none" id="save-cat-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
                        ); ?>
                        <div class="d-flex flex-column gap-3">
                            <form action="manage_shop.php?pg=manage_shop_menu_policy" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="current_pg" value="manage_shop_menu_policy">
                                <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="예: 메인요리, 사이드" required>
                                <button type="submit" name="add_category" class="btn btn-sm btn-dark px-3 flex-shrink-0">추가</button>
                            </form>
                            <div class="list-group list-group-flush border-top border-bottom mt-1" id="category-list-sortable">
                                <?php
                                $lang1 = $ui['multilingual_lang1'] ?? 'none';
                                $lang2 = $ui['multilingual_lang2'] ?? 'none';
                                $lang1_code = $lang1;
                                $lang2_code = $lang2;
                                $cat_idx = 1;
                                foreach ($category_list as $cat):
                                    $cat_name = $cat['cat_name'];
                                    $trans_arr = !empty($cat['translations']) ? json_decode($cat['translations'], true) : [];
                                ?>
                                    <div class="list-group-item px-3 py-3 border rounded-3 mb-2 d-flex align-items-center gap-3 sort-cat-item bg-white shadow-xs" data-id="<?php echo $cat['id']; ?>">
                                        <div class="flex-grow-1">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-3 small fw-bold text-muted d-flex justify-content-between align-items-center">
                                                    <span class="text-truncate">카테고리 <?php echo $cat_idx++; ?></span>
                                                    <a href="manage_shop.php?pg=manage_shop_menu_policy&del_cat=<?php echo $cat['id']; ?>&current_pg=manage_shop_menu_policy" class="btn-close" style="font-size: 0.6rem;" onclick="return confirm('카테고리를 삭제하시겠습니까?')"></a>
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="d-flex flex-column gap-1">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                                                            <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($cat_name); ?>" placeholder="카테고리명" onchange="updateCategoryName(<?php echo $cat['id']; ?>, this.value, '<?php echo htmlspecialchars($cat_name, ENT_QUOTES); ?>')">
                                                        </div>
                                                        <?php if ($lang1 !== 'none'): ?>
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                                                <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang1_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars($lang1_dict[$cat_name] ?? $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang1_code; ?>', this.value)">
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($lang2 !== 'none'): ?>
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                                                <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang2_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars($lang2_dict[$cat_name] ?? $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang2_code; ?>', this.value)">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="drag-handle text-muted cursor-move" style="cursor: grab;"><i class="bi bi-list fs-4"></i></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/manage_shop_menu_modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <script>
        async function updateCategoryName(catId, newName, oldName) {
            if (!newName || newName.trim() === '' || newName.trim() === oldName) return;
            const formData = new FormData();
            formData.append('action', 'edit_category');
            formData.append('cat_id', catId);
            formData.append('new_name', newName.trim());
            formData.append('current_pg', 'manage_shop_menu_policy');
            try {
                const response = await fetch('manage_shop.php?pg=manage_shop_menu_policy', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                if (result.status === 'success') {
                    const badge = document.getElementById('save-cat-success-badge');
                    if (badge) {
                        badge.classList.remove('d-none');
                        setTimeout(() => badge.classList.add('d-none'), 2000);
                    }
                } else {
                    alert('수정 실패: ' + result.message);
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function updateCategoryTranslation(catId, langCode, newVal) {
            const formData = new FormData();
            formData.append('action', 'edit_category_translation');
            formData.append('cat_id', catId);
            formData.append('lang_code', langCode);
            formData.append('new_translation', newVal.trim());
            formData.append('current_pg', 'manage_shop_menu_policy');

            try {
                const response = await fetch('manage_shop.php?pg=manage_shop_menu_policy', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();
                if (result.status === 'success') {
                    const badge = document.getElementById('save-cat-success-badge');
                    if (badge) {
                        badge.classList.remove('d-none');
                        setTimeout(() => badge.classList.add('d-none'), 2000);
                    }
                } else {
                    alert('수정 실패: ' + result.message);
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function saveUiLabels() {
            const form = document.getElementById('ui-labels-form');
            const formData = new FormData(form);
            formData.append('current_pg', 'manage_shop_menu_policy');
            try {
                const res = await fetch('manage_shop.php?pg=manage_shop_menu_policy', {
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
                    setTimeout(() => badge.classList.add('d-none'), 2000);
                }
            } catch (err) {
                console.error('Save error:', err);
            }
        }

        function toggleDelivery(el, syncId = null) {
            const isShow = el.checked ? 1 : 0;
            const formData = new FormData();
            formData.append('action', 'toggle_delivery_display');
            formData.append('is_show', isShow);
            if (syncId) {
                const syncEl = document.getElementById(syncId);
                if (syncEl) syncEl.checked = el.checked;
            } else {
                const mobileEl = document.getElementById('toggleDeliveryDisplayMobile');
                if (mobileEl) mobileEl.checked = el.checked;
            }
            fetch('manage_shop.php?pg=manage_shop_menu_policy', {
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
        function initPolicyPageModules() {
            initImageBatchManager('itemboard_images', {
                containerId: 'itemboard-image-container',
                itemClass: 'col gallery-item',
                aspectRatio: '3/4',
                addBtnSelector: '.btn-add-img',
                emptyMsgSelector: '.empty-msg',
                uploadParams: {
                    target_id: <?php echo $shop_id; ?>,
                    table: 'shop_item_boards',
                    column: 'board_img_path',
                    folder: 'itemboard'
                },
                deleteUrl: 'manage_shop.php?pg=manage_shop_menu_policy',
                deleteActionName: 'delete_board_img',
                deleteIdParam: 'board_id',
                sortable: true,
                hiddenOrderInputId: 'itemboard_order_input'
            });

            if (typeof Sortable === 'undefined') return;

            const sectionListEl = document.getElementById('sortable-section-list');
            if (sectionListEl) {
                Sortable.create(sectionListEl, {
                    animation: 150,
                    handle: '.drag-handle',
                    filter: 'input',
                    preventOnFilter: false,
                    ghostClass: 'bg-light',
                    delay: 0,
                    touchStartThreshold: 5,
                    fallbackOnBody: true,
                    forceFallback: true,
                    onEnd: function() {
                        const items = sectionListEl.querySelectorAll('.sort-item');
                        items.forEach((item, index) => {
                            const orderInput = item.querySelector('.order-input');
                            if (orderInput) orderInput.value = index + 1;
                        });
                        saveUiLabels();
                    }
                });
            }

            const catListEl = document.getElementById('category-list-sortable');
            if (catListEl) {
                Sortable.create(catListEl, {
                    animation: 150,
                    ghostClass: 'opacity-50',
                    filter: 'a, button',
                    preventOnFilter: false,
                    delay: 0,
                    touchStartThreshold: 5,
                    fallbackOnBody: true,
                    forceFallback: true,
                    onEnd: async function() {
                        const items = catListEl.querySelectorAll('.sort-cat-item');
                        const orderData = Array.from(items).map(item => item.dataset.id);
                        const formData = new FormData();
                        formData.append('update_category_order', '1');
                        formData.append('order_data', JSON.stringify(orderData));
                        formData.append('current_pg', 'manage_shop_menu_policy');
                        try {
                            const res = await fetch('manage_shop.php?pg=manage_shop_menu_policy', {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            const data = await res.json();
                            if (data.status !== 'success') {
                                alert('카테고리 순서 저장에 실패했습니다.');
                            } else {
                                const badge = document.getElementById('save-cat-success-badge');
                                if (badge) {
                                    badge.classList.remove('d-none');
                                    setTimeout(() => badge.classList.add('d-none'), 2000);
                                }
                            }
                        } catch (err) {
                            console.error('Category order update error:', err);
                        }
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPolicyPageModules);
        } else {
            setTimeout(initPolicyPageModules, 100);
        }
    </script>