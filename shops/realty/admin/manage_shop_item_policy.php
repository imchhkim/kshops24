<<<<<<< HEAD
<?php

/**
 * KShops24 부동산 매물 관리 - 정책 및 환경설정 모듈 (manage_shop_item_policy.php)
 * - 역할: 부동산 중개 정책, 전단지 관리, UI 레이블 설정, 카테고리 관리 뷰 및 JS 분리
 */
if (!isset($shop_id)) exit;

$current_pg = $_GET['pg'] ?? 'manage_shop_item';
$is_standalone = ($current_pg === 'manage_shop_item_policy');

// 단독 페이지로 접근했을 때 누락된 데이터 및 액션 로드
if ($is_standalone) {
    require_once __DIR__ . '/manage_shop_item_action.php';

    if (!isset($category_list)) {
        $stmt = $pdo->prepare("SELECT * FROM shop_item_categories WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$shop_id]);
        $category_list = $stmt->fetchAll();
    }
    if (!isset($board_list)) {
        $stmt = $pdo->prepare("SELECT * FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$shop_id]);
        $board_list = $stmt->fetchAll();
    }
    if (!isset($ui)) {
        $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
        $stmt_ui->execute([$shop_id]);
        $ui = json_decode($stmt_ui->fetchColumn() ?: '{}', true);
    }
    if (!isset($shop)) {
        $stmt_shop = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt_shop->execute([$shop_id]);
        $shop = $stmt_shop->fetch(PDO::FETCH_ASSOC);
    }

    echo '<div class="container-fluid p-0">';
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
            case 'label_updated':
                $msg_text = '레이블이 수정되었습니다.';
                break;
            case 'policy_updated':
                $msg_text = '부동산 및 중개 정책이 성공적으로 수정되었습니다.';
                break;
        }
        if ($msg_text) echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg_text}', '{$msg_type}'); });</script>";
    }
    echo renderPageHeader('매물 정책 관리', 'bi-gear-fill');
    echo '<div class="row g-4">';
}
?>

<style>
    /* 카테고리 배지 스타일 (단독 페이지에서도 디자인이 깨지지 않도록 추가) */
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
</style>
<?php if ($is_standalone): ?>
    <!-- 단독 페이지 접근 시 마우스 드래그(Sortable) 라이브러리 로드 -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php endif; ?>

<!-- [섹션 1] 상담 및 기타 정책 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php
            $policy_checked = (!isset($shop['is_show_delivery']) || $shop['is_show_delivery'] == 1) ? 'checked' : '';
            $right_btn_html = '
            <div class="d-flex align-items-center gap-2 gap-md-3 justify-content-center justify-content-md-end">
                <div class="form-check form-switch m-0 d-flex align-items-center border border-md bg-light rounded-pill px-3 py-1 px-md-3 py-md-1 shadow-sm">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="togglePolicyDisplay" ' . $policy_checked . ' onchange="togglePolicy(this)">
                    <label class="form-check-label small fw-bold text-primary mb-0" for="togglePolicyDisplay" style="cursor: pointer; white-space: nowrap;">홈페이지 노출</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#editPolicyModal" style="font-size: 0.75rem;">
                    <i class="bi bi-pencil-square me-1"></i> 정책 수정
                </button>
            </div>';
            echo renderSectionHeader('상담 및 기타 정책', 'bi-info-circle', [], $right_btn_html);

            // [추가] 거래 유형 표시용 데이터 추출
            $ctt_val = $ui['custom_trade_types'] ?? '';
            $trade_options = [];
            if (!empty($ctt_val)) {
                $decoded = json_decode($ctt_val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $opt) {
                        if (!empty($opt['ko'])) $trade_options[] = $opt['ko'];
                    }
                } else {
                    $trade_options = array_map('trim', explode(',', $ctt_val));
                }
            } else {
                $trade_options = defined('REALTY_TRADE_TYPES') ? REALTY_TRADE_TYPES : ['매매', '장기임대 (1년 혹은 그 이상)', '단기임대 (수개월)', '기타'];
            }
            $display_trade_types = implode(', ', array_filter($trade_options));
            ?>
            <div class="row d-none d-md-flex g-0">
                <div class="col-md-6 border-end">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-2">상담 가능 시간</dt>
                        <dd class="col-8 mb-2 fw-bold"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></dd>
                        <dt class="col-4 text-muted mb-0">수수료 안내</dt>
                        <dd class="col-8 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></dd>
                    </dl>
                </div>
                <div class="col-md-6 ps-md-4">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-2">거래 방식</dt>
                        <dd class="col-8 mb-2 fw-bold"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></dd>
                        <dt class="col-4 text-muted mb-0">매물 거래 유형</dt>
                        <dd class="col-8 mb-0 fw-bold text-truncate" title="<?php echo htmlspecialchars($display_trade_types); ?>"><?php echo htmlspecialchars($display_trade_types); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- 모바일 버전 UI -->
            <div class="d-md-none">
                <div class="bg-light p-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">상담 가능 시간:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">수수료 안내:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">거래 방식:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">거래 유형:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($display_trade_types); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [섹션 4] 홍보 전단지 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="flyer-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="saveImageBatch(event, 'flyer_images')">
            <input type="hidden" name="flyer_order" id="flyer_order_input" value="[]">
            <?php echo renderSectionHeader(
                htmlspecialchars($ui['label_flyer'] ?? (defined('REALTY_DEFAULT_LABEL_FLYER') ? REALTY_DEFAULT_LABEL_FLYER : '홍보 전단지')),
                'bi-images',
                ['<i class="bi bi-info-circle me-1"></i> 전단지 사진은 <span class="text-primary fw-bold">3:4 세로 비율</span>이 좋습니다. 수정 후에는 꼭 "저장" 버튼을 눌러주세요.'],
                '<button type="button" onclick="document.getElementById(\'flyer-multi-input\').click()" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i>사진 추가</button>'
            ); ?>
            <style>
                /* 모바일에서 전단지 이미지가 2장씩 꽉 차게 (gap-2 고려), PC에서는 고정 크기로 보이도록 설정 */
                #flyer-image-container .gallery-item {
                    width: calc(50% - 4px) !important;
                    height: auto !important;
                    aspect-ratio: 3 / 4;
                }

                @media (min-width: 768px) {
                    #flyer-image-container .gallery-item {
                        width: 120px !important;
                        height: 160px !important;
                    }
                }
            </style>
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2 p-3 border rounded shadow-inner bg-light" id="flyer-image-container" style="min-height: 120px;">
                    <?php if (!empty($board_list)): foreach ($board_list as $board): ?>
                            <div class="position-relative gallery-item" id="flyer_images-item-<?php echo $board['id']; ?>" data-path="<?php echo htmlspecialchars($board['board_img_path'], ENT_QUOTES); ?>" style="cursor: grab;">
                                <img src="<?php echo htmlspecialchars($board['board_img_path']); ?>" class="w-100 h-100 object-fit-cover rounded border shadow-sm">
                                <button type="button" onclick="event.stopPropagation(); deleteBatchImage('flyer_images', <?php echo $board['id']; ?>)" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                            </div>
                    <?php endforeach;
                    endif; ?>
                    <div class="empty-msg text-muted small w-100 text-center my-auto py-2 <?php echo empty($board_list) ? '' : 'd-none'; ?>">등록된 전단지 사진이 없습니다.</div>
                    <button type="button" class="btn-add-img d-none"></button>
                </div>
                <input type="file" id="flyer-multi-input" class="d-none" accept="image/*" multiple onchange="addBatchImage('flyer_images', this)">
            </div>
            <div class="mt-auto text-end pt-3 border-top mt-3">
                <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 전단지 저장</button>
            </div>
        </form>
    </div>
</div>

<!-- [섹션 3] 섹션 설정 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="ui-labels-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="return false;">
            <input type="hidden" name="update_ui_labels_bulk" value="1">
            <?php echo renderSectionHeader(
                '매물 섹션 설정 (레이블 및 순서)',
                'bi-sort-numeric-down',
                ['<i class="bi bi-info-circle me-1"></i> 오른쪽 핸들을 드래그하여 <strong>순서를 조정하거나 레이블 값을 수정</strong>하면 자동으로 저장됩니다.'],
                '<span class="badge bg-success d-none" id="save-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
            ); ?>

            <div id="sortable-section-list" class="list-group list-group-flush border-top border-bottom">
                <?php
                $lang1 = $ui['multilingual_lang1'] ?? 'none';
                $lang2 = $ui['multilingual_lang2'] ?? 'none';

                $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                if (empty($lang1_code)) $lang1_code = 'etc1';

                $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                if (empty($lang2_code)) $lang2_code = 'etc2';

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
                    ['name' => '홍보 전단지', 'key' => 'flyer',      'default' => defined('REALTY_DEFAULT_LABEL_FLYER') ? REALTY_DEFAULT_LABEL_FLYER : '홍보 전단지', 'color' => ''],
                    ['name' => '급매 물건',      'key' => 'quick_sale', 'default' => defined('REALTY_DEFAULT_LABEL_QUICK_SALE') ? REALTY_DEFAULT_LABEL_QUICK_SALE : '급매 물건', 'color' => ''],
                    ['name' => '신규 물건',   'key' => 'new_item',   'default' => defined('REALTY_DEFAULT_LABEL_NEW_ITEM') ? REALTY_DEFAULT_LABEL_NEW_ITEM : '신규 물건', 'color' => ''],
                    ['name' => '추천 물건',   'key' => 'best_item',  'default' => defined('REALTY_DEFAULT_LABEL_BEST_MENU') ? REALTY_DEFAULT_LABEL_BEST_MENU : '추천 물건', 'color' => ''],
                    ['name' => '전체 물건',   'key' => 'all_items',  'default' => defined('REALTY_DEFAULT_LABEL_ALL_ITEMS') ? REALTY_DEFAULT_LABEL_ALL_ITEMS : '전체 물건', 'color' => '']
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

<!-- [섹션 4] 카테고리 관리 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php echo renderSectionHeader(
                '<span id="item_category">전체 물건의 매물 카테고리 설정</span>',
                'bi-tag',
                [
                    '<i class="bi bi-info-circle me-1"></i> 각 항목의 오른쪽 핸들을 드래그하여 <strong>순서를 조정</strong>할 수 있습니다.',
                    '<i class="bi bi-translate me-1 text-primary"></i> 카테고리 추가 후, 언어 코드 입력란에 번역된 이름을 <strong>직접 입력</strong>하면 즉시 저장됩니다.',
                    '<i class="bi bi-emoji-smile me-1 text-warning"></i><a href="https://getemoji.com/" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Get Emoji</a>에 접속해서 <strong>멋진 이모지</strong>들(👑 🍗 🍝 🍺 ...)을 사용하세요.'
                ],
                '<span class="badge bg-success d-none" id="save-cat-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
            ); ?>
            <div class="d-flex flex-column gap-3">
                <form action="manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>" method="POST" class="d-flex gap-2">
                    <input type="hidden" name="add_category" value="1">
                    <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="예: 주택 매매, 상가 임대" required>
                    <button type="submit" class="btn btn-sm btn-dark px-3 flex-shrink-0">추가</button>
                </form>
                <div class="list-group list-group-flush border-top border-bottom mt-1" id="category-list-sortable">
                    <?php
                    $lang1 = $ui['multilingual_lang1'] ?? 'none';
                    $lang2 = $ui['multilingual_lang2'] ?? 'none';

                    $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                    if (empty($lang1_code)) $lang1_code = 'etc1';

                    $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                    if (empty($lang2_code)) $lang2_code = 'etc2';

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
                                        <a href="manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>&del_cat=<?php echo $cat['id']; ?>" class="btn-close" style="font-size: 0.6rem;" onclick="return confirm('카테고리를 삭제하시겠습니까?')"></a>
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
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang1_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars(function_exists('translate_realty_category') ? translate_realty_category($cat_name, $lang1_code) : $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang1_code; ?>', this.value)">
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($lang2 !== 'none'): ?>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang2_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars(function_exists('translate_realty_category') ? translate_realty_category($cat_name, $lang2_code) : $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang2_code; ?>', this.value)">
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

<script>
    async function updateCategoryName(catId, newName, oldName) {
        if (!newName || newName.trim() === '' || newName.trim() === oldName) return;
        const formData = new FormData();
        formData.append('action', 'edit_category');
        formData.append('cat_id', catId);
        formData.append('new_name', newName.trim());
        try {
            const response = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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

        try {
            const response = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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
        try {
            const res = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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
        } catch (err) {}
    }

    function togglePolicy(el) {
        const isShow = el.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('action', 'toggle_policy_display');
        formData.append('is_show', isShow);
        fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast('정책 노출 설정이 변경되었습니다.', 'success');
            } else {
                alert('변경 실패: ' + data.message);
                el.checked = !el.checked;
            }
        }).catch(err => {
            alert('통신 오류');
            el.checked = !el.checked;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initImageBatchManager('flyer_images', {
            containerId: 'flyer-image-container',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: {
                target_id: <?php echo (int)$shop_id; ?>,
                table: 'shop_item_boards',
                column: 'board_img_path',
                folder: 'itemboard'
            },
            deleteUrl: 'manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>',
            deleteActionName: 'delete_board_img',
            deleteIdParam: 'board_id',
            sortable: true,
            hiddenOrderInputId: 'flyer_order_input'
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
                forceFallback: true,
                onEnd: function() {
                    sectionListEl.querySelectorAll('.sort-item').forEach((item, index) => {
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
                forceFallback: true,
                onEnd: async function() {
                    const items = catListEl.querySelectorAll('.sort-cat-item');
                    const orderData = Array.from(items).map(item => item.dataset.id);
                    const formData = new FormData();
                    formData.append('update_category_order', '1');
                    formData.append('order_data', JSON.stringify(orderData));
                    try {
                        const res = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            const badge = document.getElementById('save-cat-success-badge');
                            if (badge) {
                                badge.classList.remove('d-none');
                                setTimeout(() => badge.classList.add('d-none'), 2000);
                            }
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        }
    });
</script>

<?php
if ($is_standalone) {
    echo '</div></div>';
    include_once __DIR__ . '/manage_shop_item_modals.php';
}
=======
<?php

/**
 * KShops24 부동산 매물 관리 - 정책 및 환경설정 모듈 (manage_shop_item_policy.php)
 * - 역할: 부동산 중개 정책, 전단지 관리, UI 레이블 설정, 카테고리 관리 뷰 및 JS 분리
 */
if (!isset($shop_id)) exit;

$current_pg = $_GET['pg'] ?? 'manage_shop_item';
$is_standalone = ($current_pg === 'manage_shop_item_policy');

// 단독 페이지로 접근했을 때 누락된 데이터 및 액션 로드
if ($is_standalone) {
    require_once __DIR__ . '/manage_shop_item_action.php';

    if (!isset($category_list)) {
        $stmt = $pdo->prepare("SELECT * FROM shop_item_categories WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$shop_id]);
        $category_list = $stmt->fetchAll();
    }
    if (!isset($board_list)) {
        $stmt = $pdo->prepare("SELECT * FROM shop_item_boards WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$shop_id]);
        $board_list = $stmt->fetchAll();
    }
    if (!isset($ui)) {
        $stmt_ui = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
        $stmt_ui->execute([$shop_id]);
        $ui = json_decode($stmt_ui->fetchColumn() ?: '{}', true);
    }
    if (!isset($shop)) {
        $stmt_shop = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt_shop->execute([$shop_id]);
        $shop = $stmt_shop->fetch(PDO::FETCH_ASSOC);
    }

    echo '<div class="container-fluid p-0">';
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
            case 'label_updated':
                $msg_text = '레이블이 수정되었습니다.';
                break;
            case 'policy_updated':
                $msg_text = '부동산 및 중개 정책이 성공적으로 수정되었습니다.';
                break;
        }
        if ($msg_text) echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg_text}', '{$msg_type}'); });</script>";
    }
    echo renderPageHeader('매물 정책 관리', 'bi-gear-fill');
    echo '<div class="row g-4">';
}
?>

<style>
    /* 카테고리 배지 스타일 (단독 페이지에서도 디자인이 깨지지 않도록 추가) */
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
</style>
<?php if ($is_standalone): ?>
    <!-- 단독 페이지 접근 시 마우스 드래그(Sortable) 라이브러리 로드 -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php endif; ?>

<!-- [섹션 1] 상담 및 기타 정책 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php
            $policy_checked = (!isset($shop['is_show_delivery']) || $shop['is_show_delivery'] == 1) ? 'checked' : '';
            $right_btn_html = '
            <div class="d-flex align-items-center gap-2 gap-md-3 justify-content-center justify-content-md-end">
                <div class="form-check form-switch m-0 d-flex align-items-center border border-md bg-light rounded-pill px-3 py-1 px-md-3 py-md-1 shadow-sm">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="togglePolicyDisplay" ' . $policy_checked . ' onchange="togglePolicy(this)">
                    <label class="form-check-label small fw-bold text-primary mb-0" for="togglePolicyDisplay" style="cursor: pointer; white-space: nowrap;">홈페이지 노출</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#editPolicyModal" style="font-size: 0.75rem;">
                    <i class="bi bi-pencil-square me-1"></i> 정책 수정
                </button>
            </div>';
            echo renderSectionHeader('상담 및 기타 정책', 'bi-info-circle', [], $right_btn_html);

            // [추가] 거래 유형 표시용 데이터 추출
            $ctt_val = $ui['custom_trade_types'] ?? '';
            $trade_options = [];
            if (!empty($ctt_val)) {
                $decoded = json_decode($ctt_val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $opt) {
                        if (!empty($opt['ko'])) $trade_options[] = $opt['ko'];
                    }
                } else {
                    $trade_options = array_map('trim', explode(',', $ctt_val));
                }
            } else {
                $trade_options = defined('REALTY_TRADE_TYPES') ? REALTY_TRADE_TYPES : ['매매', '장기임대 (1년 혹은 그 이상)', '단기임대 (수개월)', '기타'];
            }
            $display_trade_types = implode(', ', array_filter($trade_options));
            ?>
            <div class="row d-none d-md-flex g-0">
                <div class="col-md-6 border-end">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-2">상담 가능 시간</dt>
                        <dd class="col-8 mb-2 fw-bold"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></dd>
                        <dt class="col-4 text-muted mb-0">수수료 안내</dt>
                        <dd class="col-8 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></dd>
                    </dl>
                </div>
                <div class="col-md-6 ps-md-4">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-2">거래 방식</dt>
                        <dd class="col-8 mb-2 fw-bold"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></dd>
                        <dt class="col-4 text-muted mb-0">매물 거래 유형</dt>
                        <dd class="col-8 mb-0 fw-bold text-truncate" title="<?php echo htmlspecialchars($display_trade_types); ?>"><?php echo htmlspecialchars($display_trade_types); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- 모바일 버전 UI -->
            <div class="d-md-none">
                <div class="bg-light p-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">상담 가능 시간:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">수수료 안내:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">거래 방식:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">거래 유형:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($display_trade_types); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [섹션 4] 홍보 전단지 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="flyer-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="saveImageBatch(event, 'flyer_images')">
            <input type="hidden" name="flyer_order" id="flyer_order_input" value="[]">
            <?php echo renderSectionHeader(
                htmlspecialchars($ui['label_flyer'] ?? (defined('REALTY_DEFAULT_LABEL_FLYER') ? REALTY_DEFAULT_LABEL_FLYER : '홍보 전단지')),
                'bi-images',
                ['<i class="bi bi-info-circle me-1"></i> 전단지 사진은 <span class="text-primary fw-bold">3:4 세로 비율</span>이 좋습니다. 수정 후에는 꼭 "저장" 버튼을 눌러주세요.'],
                '<button type="button" onclick="document.getElementById(\'flyer-multi-input\').click()" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i>사진 추가</button>'
            ); ?>
            <style>
                /* 모바일에서 전단지 이미지가 2장씩 꽉 차게 (gap-2 고려), PC에서는 고정 크기로 보이도록 설정 */
                #flyer-image-container .gallery-item {
                    width: calc(50% - 4px) !important;
                    height: auto !important;
                    aspect-ratio: 3 / 4;
                }

                @media (min-width: 768px) {
                    #flyer-image-container .gallery-item {
                        width: 120px !important;
                        height: 160px !important;
                    }
                }
            </style>
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2 p-3 border rounded shadow-inner bg-light" id="flyer-image-container" style="min-height: 120px;">
                    <?php if (!empty($board_list)): foreach ($board_list as $board): ?>
                            <div class="position-relative gallery-item" id="flyer_images-item-<?php echo $board['id']; ?>" data-path="<?php echo htmlspecialchars($board['board_img_path'], ENT_QUOTES); ?>" style="cursor: grab;">
                                <img src="<?php echo htmlspecialchars($board['board_img_path']); ?>" class="w-100 h-100 object-fit-cover rounded border shadow-sm">
                                <button type="button" onclick="event.stopPropagation(); deleteBatchImage('flyer_images', <?php echo $board['id']; ?>)" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 shadow-sm" style="width:22px; height:22px; transform: translate(30%, -30%); border-radius: 50%;"><i class="bi bi-x"></i></button>
                            </div>
                    <?php endforeach;
                    endif; ?>
                    <div class="empty-msg text-muted small w-100 text-center my-auto py-2 <?php echo empty($board_list) ? '' : 'd-none'; ?>">등록된 전단지 사진이 없습니다.</div>
                    <button type="button" class="btn-add-img d-none"></button>
                </div>
                <input type="file" id="flyer-multi-input" class="d-none" accept="image/*" multiple onchange="addBatchImage('flyer_images', this)">
            </div>
            <div class="mt-auto text-end pt-3 border-top mt-3">
                <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 전단지 저장</button>
            </div>
        </form>
    </div>
</div>

<!-- [섹션 3] 섹션 설정 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="ui-labels-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="return false;">
            <input type="hidden" name="update_ui_labels_bulk" value="1">
            <?php echo renderSectionHeader(
                '매물 섹션 설정 (레이블 및 순서)',
                'bi-sort-numeric-down',
                ['<i class="bi bi-info-circle me-1"></i> 오른쪽 핸들을 드래그하여 <strong>순서를 조정하거나 레이블 값을 수정</strong>하면 자동으로 저장됩니다.'],
                '<span class="badge bg-success d-none" id="save-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
            ); ?>

            <div id="sortable-section-list" class="list-group list-group-flush border-top border-bottom">
                <?php
                $lang1 = $ui['multilingual_lang1'] ?? 'none';
                $lang2 = $ui['multilingual_lang2'] ?? 'none';

                $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                if (empty($lang1_code)) $lang1_code = 'etc1';

                $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                if (empty($lang2_code)) $lang2_code = 'etc2';

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
                    ['name' => '홍보 전단지', 'key' => 'flyer',      'default' => defined('REALTY_DEFAULT_LABEL_FLYER') ? REALTY_DEFAULT_LABEL_FLYER : '홍보 전단지', 'color' => ''],
                    ['name' => '급매 물건',      'key' => 'quick_sale', 'default' => defined('REALTY_DEFAULT_LABEL_QUICK_SALE') ? REALTY_DEFAULT_LABEL_QUICK_SALE : '급매 물건', 'color' => ''],
                    ['name' => '신규 물건',   'key' => 'new_item',   'default' => defined('REALTY_DEFAULT_LABEL_NEW_ITEM') ? REALTY_DEFAULT_LABEL_NEW_ITEM : '신규 물건', 'color' => ''],
                    ['name' => '추천 물건',   'key' => 'best_item',  'default' => defined('REALTY_DEFAULT_LABEL_BEST_MENU') ? REALTY_DEFAULT_LABEL_BEST_MENU : '추천 물건', 'color' => ''],
                    ['name' => '전체 물건',   'key' => 'all_items',  'default' => defined('REALTY_DEFAULT_LABEL_ALL_ITEMS') ? REALTY_DEFAULT_LABEL_ALL_ITEMS : '전체 물건', 'color' => '']
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

<!-- [섹션 4] 카테고리 관리 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php echo renderSectionHeader(
                '<span id="item_category">전체 물건의 매물 카테고리 설정</span>',
                'bi-tag',
                [
                    '<i class="bi bi-info-circle me-1"></i> 각 항목의 오른쪽 핸들을 드래그하여 <strong>순서를 조정</strong>할 수 있습니다.',
                    '<i class="bi bi-translate me-1 text-primary"></i> 카테고리 추가 후, 언어 코드 입력란에 번역된 이름을 <strong>직접 입력</strong>하면 즉시 저장됩니다.',
                    '<i class="bi bi-emoji-smile me-1 text-warning"></i><a href="https://getemoji.com/" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Get Emoji</a>에 접속해서 <strong>멋진 이모지</strong>들(👑 🍗 🍝 🍺 ...)을 사용하세요.'
                ],
                '<span class="badge bg-success d-none" id="save-cat-success-badge">자동 저장됨 <i class="bi bi-check"></i></span>'
            ); ?>
            <div class="d-flex flex-column gap-3">
                <form action="manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>" method="POST" class="d-flex gap-2">
                    <input type="hidden" name="add_category" value="1">
                    <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="예: 주택 매매, 상가 임대" required>
                    <button type="submit" class="btn btn-sm btn-dark px-3 flex-shrink-0">추가</button>
                </form>
                <div class="list-group list-group-flush border-top border-bottom mt-1" id="category-list-sortable">
                    <?php
                    $lang1 = $ui['multilingual_lang1'] ?? 'none';
                    $lang2 = $ui['multilingual_lang2'] ?? 'none';

                    $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                    if (empty($lang1_code)) $lang1_code = 'etc1';

                    $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                    if (empty($lang2_code)) $lang2_code = 'etc2';

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
                                        <a href="manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>&del_cat=<?php echo $cat['id']; ?>" class="btn-close" style="font-size: 0.6rem;" onclick="return confirm('카테고리를 삭제하시겠습니까?')"></a>
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
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang1_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars(function_exists('translate_realty_category') ? translate_realty_category($cat_name, $lang1_code) : $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang1_code; ?>', this.value)">
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($lang2 !== 'none'): ?>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang2_code] ?? ''); ?>" placeholder="기본값: <?php echo htmlspecialchars(function_exists('translate_realty_category') ? translate_realty_category($cat_name, $lang2_code) : $cat_name); ?>" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang2_code; ?>', this.value)">
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

<script>
    async function updateCategoryName(catId, newName, oldName) {
        if (!newName || newName.trim() === '' || newName.trim() === oldName) return;
        const formData = new FormData();
        formData.append('action', 'edit_category');
        formData.append('cat_id', catId);
        formData.append('new_name', newName.trim());
        try {
            const response = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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

        try {
            const response = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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
        try {
            const res = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
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
        } catch (err) {}
    }

    function togglePolicy(el) {
        const isShow = el.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('action', 'toggle_policy_display');
        formData.append('is_show', isShow);
        fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast('정책 노출 설정이 변경되었습니다.', 'success');
            } else {
                alert('변경 실패: ' + data.message);
                el.checked = !el.checked;
            }
        }).catch(err => {
            alert('통신 오류');
            el.checked = !el.checked;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initImageBatchManager('flyer_images', {
            containerId: 'flyer-image-container',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: {
                target_id: <?php echo (int)$shop_id; ?>,
                table: 'shop_item_boards',
                column: 'board_img_path',
                folder: 'itemboard'
            },
            deleteUrl: 'manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>',
            deleteActionName: 'delete_board_img',
            deleteIdParam: 'board_id',
            sortable: true,
            hiddenOrderInputId: 'flyer_order_input'
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
                forceFallback: true,
                onEnd: function() {
                    sectionListEl.querySelectorAll('.sort-item').forEach((item, index) => {
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
                forceFallback: true,
                onEnd: async function() {
                    const items = catListEl.querySelectorAll('.sort-cat-item');
                    const orderData = Array.from(items).map(item => item.dataset.id);
                    const formData = new FormData();
                    formData.append('update_category_order', '1');
                    formData.append('order_data', JSON.stringify(orderData));
                    try {
                        const res = await fetch('manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            const badge = document.getElementById('save-cat-success-badge');
                            if (badge) {
                                badge.classList.remove('d-none');
                                setTimeout(() => badge.classList.add('d-none'), 2000);
                            }
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        }
    });
</script>

<?php
if ($is_standalone) {
    echo '</div></div>';
    include_once __DIR__ . '/manage_shop_item_modals.php';
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
?>