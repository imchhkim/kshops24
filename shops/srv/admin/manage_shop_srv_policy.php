<?php

/**
 * KShops24 서비스/예약 관리 - 정책 및 환경설정 모듈 (manage_shop_srv_policy.php)
 * - 역할: 서비스/예약 정책, 프로모션 관리, UI 레이블 설정, 카테고리 관리 뷰 및 JS 분리
 */
if (!isset($shop_id)) exit;

$current_pg = $_GET['pg'] ?? 'manage_shop_srv';
$is_standalone = ($current_pg === 'manage_shop_srv_policy');

// 단독 페이지로 접근했을 때 누락된 데이터 및 액션 로드
if ($is_standalone) {
    require_once __DIR__ . '/manage_shop_srv_action.php';

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
                $msg_text = '프로모션 이미지가 삭제되었습니다.';
                $msg_type = 'warning';
                break;
            case 'label_updated':
                $msg_text = '레이블이 수정되었습니다.';
                break;
            case 'policy_updated':
                $msg_text = '서비스/예약 정책이 성공적으로 수정되었습니다.';
                break;
        }
        if ($msg_text) echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('{$msg_text}', '{$msg_type}'); });</script>";
    }
    echo renderPageHeader('서비스 정책 관리', 'bi-sliders');
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

<!-- [섹션 1] 서비스/예약 정책 -->
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
            echo renderSectionHeader('서비스/예약 정책', 'bi-info-circle', [], $right_btn_html);
            ?>
            <div class="row d-none d-md-flex g-0">
                <div class="col-md-6 border-end">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-2">예약 가능 시간</dt>
                        <dd class="col-8 mb-2 fw-bold"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></dd>
                        <dt class="col-4 text-muted mb-0">서비스 비용 안내</dt>
                        <dd class="col-8 mb-0 fw-bold"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></dd>
                    </dl>
                </div>
                <div class="col-md-6 ps-md-4">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted mb-0">지불 방식</dt>
                        <dd class="col-8 mb-0 fw-bold"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- 모바일 버전 UI -->
            <div class="d-md-none">
                <div class="bg-light p-3 rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">예약 가능 시간:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_hours'] ?: '영업시간과 동일'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">서비스 비용 안내:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['delivery_fee_info'] ?: '미입력'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="text-muted small" style="display: inline-block; min-width: 80px; flex-shrink: 0;">지불 방식:</span>
                        <span class="fw-bold small text-end"><?php echo htmlspecialchars($shop['payment_methods'] ?: '미입력'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [섹션 2] 서비스/예약 설정 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form method="POST" class="p-3 p-md-4 d-flex flex-column h-100" action="manage_shop.php?pg=<?php echo htmlspecialchars($current_pg); ?>">
            <input type="hidden" name="update_reservation" value="1">
            <?php echo renderSectionHeader(
                '서비스/예약 스케줄 설정',
                'bi-calendar-check',
                [
                    '<i class="bi bi-info-circle me-1"></i> 상점의 <strong>영업시간 내</strong>에서 고객이 예약 가능한 시간대를 요일별로 설정하세요.',
                    '<i class="bi bi-people me-1"></i> 직원이 여러 명일 경우 <strong>동시간대 수용 인원(중복 예약 수)</strong>을 설정하여 동일 시간대에 여러 예약을 받을 수 있습니다.'
                ]
            ); 
            
            // 기존 저장된 예약 설정 로드
            $res_settings = json_decode($shop['reservation_settings'] ?? '{}', true);
            $slot_interval = $res_settings['slot_interval'] ?? 60;
            $max_concurrent = $res_settings['max_concurrent'] ?? 1;
            $available_slots = $res_settings['available_slots'] ?? [];
            
            // 영업시간 데이터 파싱
            $biz_hours_json = $shop['business_hours'] ?? '{}';
            $biz_hours = json_decode($biz_hours_json, true);
            if (!is_array($biz_hours)) $biz_hours = [];
            
            $days = [
                'mon' => '월요일',
                'tue' => '화요일',
                'wed' => '수요일',
                'thu' => '목요일',
                'fri' => '금요일',
                'sat' => '토요일',
                'sun' => '일요일'
            ];
            ?>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">예약 시간 단위 (분)</label>
                    <!-- 시간 단위를 바꾸면 즉시 폼이 전송되어 슬롯을 재구성함 -->
                    <select name="slot_interval" class="form-select" onchange="document.getElementById('update_res_btn').click();">
                        <option value="30" <?php echo $slot_interval == 30 ? 'selected' : ''; ?>>30분</option>
                        <option value="60" <?php echo $slot_interval == 60 ? 'selected' : ''; ?>>1시간 (60분)</option>
                        <option value="90" <?php echo $slot_interval == 90 ? 'selected' : ''; ?>>1시간 30분 (90분)</option>
                        <option value="120" <?php echo $slot_interval == 120 ? 'selected' : ''; ?>>2시간 (120분)</option>
                    </select>
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle text-warning"></i> 단위를 변경하면 시간표가 재구성되며 즉시 저장됩니다.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">동시간대 수용 인원 (중복 예약 허용 수)</label>
                    <input type="number" name="max_concurrent" class="form-control" value="<?php echo htmlspecialchars($max_concurrent); ?>" min="1" required>
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-lightbulb text-primary"></i> 1로 설정하면 중복 예약을 허용하지 않습니다.</div>
                </div>
            </div>

            <div class="mb-3 border rounded-3 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 text-center align-middle" style="min-width: 600px;">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 120px;">요일</th>
                                <th>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>예약 가능 시간 선택</span>
                                        <div class="form-check form-switch mb-0 d-flex align-items-center justify-content-end">
                                            <input class="form-check-input mt-0 me-2" type="checkbox" id="toggle_all_slots" onchange="toggleAllSlots(this)" style="cursor: pointer;">
                                            <label class="form-check-label small fw-normal" for="toggle_all_slots" style="cursor: pointer;">모두 선택</label>
                                        </div>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $d_key => $d_name): 
                                $open_time = '09:00';
                                $close_time = '18:00';
                                $is_closed = false;

                                // 상점 정보에서 해당 요일의 영업시간 추출 (JSON 포맷 방어)
                                $day_biz = $biz_hours[$d_key] ?? null;
                                if (is_string($day_biz)) {
                                    if ($day_biz === '휴무' || $day_biz === 'closed') {
                                        $is_closed = true;
                                    } elseif (strpos($day_biz, '~') !== false) {
                                        list($open_time, $close_time) = array_map('trim', explode('~', $day_biz));
                                    }
                                } elseif (is_array($day_biz)) {
                                    if (($day_biz['closed'] ?? false) || ($day_biz['status'] ?? '') === 'closed') {
                                        $is_closed = true;
                                    } else {
                                        $open_time = $day_biz['open'] ?? '09:00';
                                        $close_time = $day_biz['close'] ?? '18:00';
                                    }
                                }

                                $day_slots = $available_slots[$d_key] ?? [];
                            ?>
                                <tr>
                                    <td class="bg-light fw-bold text-dark">
                                        <?php echo $d_name; ?><br>
                                        <?php if ($is_closed): ?>
                                            <span class="badge bg-danger mt-1">휴무</span>
                                        <?php else: ?>
                                            <small class="text-muted fw-normal" style="font-size:0.7rem;"><?php echo htmlspecialchars($open_time); ?>~<?php echo htmlspecialchars($close_time); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-start p-3">
                                        <?php if ($is_closed): ?>
                                            <div class="text-muted small">영업시간 설정에서 '휴무'로 지정된 요일입니다.</div>
                                        <?php else: 
                                            $start = strtotime("1970-01-01 $open_time");
                                            $end = strtotime("1970-01-01 $close_time");
                                            // 종료 시간이 시작 시간보다 빠르면 익일 처리 (예: 22:00 ~ 02:00)
                                            if ($start > $end) $end += 86400;

                                            $slots_html = '<div class="d-flex flex-wrap gap-2">';
                                            $current = $start;
                                            while ($current + ($slot_interval * 60) <= $end) {
                                                $slot_time = date('H:i', $current);
                                                $is_checked = in_array($slot_time, $day_slots) ? 'checked' : '';
                                                $slot_id = "slot_{$d_key}_" . str_replace(':', '', $slot_time);
                                                
                                                $slots_html .= '
                                                <div class="form-check form-switch p-0 m-0 border rounded px-2 py-1 bg-white shadow-sm d-flex align-items-center gap-2" style="min-width:85px; justify-content:center; cursor: pointer;">
                                                    <input class="form-check-input m-0" type="checkbox" name="slots['.$d_key.'][]" value="'.$slot_time.'" id="'.$slot_id.'" '.$is_checked.' style="cursor: pointer;">
                                                    <label class="form-check-label small mb-0" for="'.$slot_id.'" style="user-select: none; cursor: pointer;">'.$slot_time.'</label>
                                                </div>';
                                                
                                                $current += $slot_interval * 60;
                                            }
                                            $slots_html .= '</div>';
                                            
                                            if ($current == $start) {
                                                echo '<div class="text-muted small">영업시간이 짧아 선택할 예약 시간이 없습니다.</div>';
                                            } else {
                                                echo $slots_html;
                                            }
                                        ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-auto text-end pt-3">
                <button type="submit" id="update_res_btn" name="update_reservation" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 설정 저장</button>
            </div>
        </form>
    </div>
</div>

<!-- [섹션 4] 프로모션/이벤트 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="flyer-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="saveImageBatch(event, 'flyer_images')">
            <input type="hidden" name="flyer_order" id="flyer_order_input" value="[]">
            <?php echo renderSectionHeader(
                htmlspecialchars($ui['label_promotion'] ?? (defined('SRV_DEFAULT_LABEL_PROMOTION') ? SRV_DEFAULT_LABEL_PROMOTION : '프로모션/이벤트')),
                'bi-images',
                ['<i class="bi bi-info-circle me-1"></i> 프로모션 사진은 <span class="text-primary fw-bold">4:3 가로 비율</span>을 권장합니다. 수정 후에는 꼭 "저장" 버튼을 눌러주세요.'],
                '<button type="button" onclick="document.getElementById(\'flyer-multi-input\').click()" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i>사진 추가</button>'
            ); ?>
            <style>
                /* 모바일에서 전단지 이미지가 2장씩 꽉 차게 (gap-2 고려), PC에서는 고정 크기로 보이도록 설정 */
                #flyer-image-container .gallery-item {
                    width: calc(50% - 4px) !important;
                    height: auto !important;
                    aspect-ratio: 4 / 3;
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
                    <div class="empty-msg text-muted small w-100 text-center my-auto py-2 <?php echo empty($board_list) ? '' : 'd-none'; ?>">등록된 프로모션 사진이 없습니다.</div>
                    <button type="button" class="btn-add-img d-none"></button>
                </div>
                <input type="file" id="flyer-multi-input" class="d-none" accept="image/*" multiple onchange="addBatchImage('flyer_images', this)">
            </div>
            <div class="mt-auto text-end pt-3 border-top mt-3">
                <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 프로모션 저장</button>
            </div>
        </form>
    </div>
</div>

<!-- [섹션 3] 서비스 섹션 설정 -->
<div class="col-12">
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <form id="ui-labels-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="return false;">
            <input type="hidden" name="update_ui_labels_bulk" value="1">
            <?php echo renderSectionHeader(
                '서비스 섹션 설정 (레이블 및 순서)',
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
                    ['name' => '프로모션/이벤트', 'key' => 'promotion',        'default' => defined('SRV_DEFAULT_LABEL_PROMOTION') ? SRV_DEFAULT_LABEL_PROMOTION : '프로모션/이벤트', 'color' => ''],
                    ['name' => '할인 서비스',      'key' => 'discount_service', 'default' => defined('SRV_DEFAULT_LABEL_DISCOUNT_SERVICE') ? SRV_DEFAULT_LABEL_DISCOUNT_SERVICE : '할인 서비스', 'color' => ''],
                    ['name' => '신규 서비스',      'key' => 'new_service',      'default' => defined('SRV_DEFAULT_LABEL_NEW_SERVICE') ? SRV_DEFAULT_LABEL_NEW_SERVICE : '신규 서비스', 'color' => ''],
                    ['name' => '추천 서비스',      'key' => 'best_service',     'default' => defined('SRV_DEFAULT_LABEL_BEST_SERVICE') ? SRV_DEFAULT_LABEL_BEST_SERVICE : '추천 서비스', 'color' => ''],
                    ['name' => '전체 서비스',      'key' => 'all_services',     'default' => defined('SRV_DEFAULT_LABEL_ALL_SERVICES') ? SRV_DEFAULT_LABEL_ALL_SERVICES : '전체 서비스', 'color' => '']
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
                '<span id="item_category">전체 서비스의 서비스 카테고리 설정</span>',
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
                    <input type="text" name="cat_name" class="form-control form-control-sm" placeholder="예: 헤어컷, 네일아트, 출장 수리" required>
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
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang1_code] ?? ''); ?>" placeholder="번역된 카테고리명 입력" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang1_code; ?>', this.value)">
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($lang2 !== 'none'): ?>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                                    <input type="text" class="form-control border-start-0" value="<?php echo htmlspecialchars($trans_arr[$lang2_code] ?? ''); ?>" placeholder="번역된 카테고리명 입력" onchange="updateCategoryTranslation(<?php echo $cat['id']; ?>, '<?php echo $lang2_code; ?>', this.value)">
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

    // 모든 예약 시간 슬롯 체크박스를 일괄 선택/해제하는 함수
    function toggleAllSlots(el) {
        const isChecked = el.checked;
        const checkboxes = document.querySelectorAll('input[name^="slots["]');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
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
                folder: 'flyer'
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
    include_once __DIR__ . '/manage_shop_srv_modals.php';
}
?>