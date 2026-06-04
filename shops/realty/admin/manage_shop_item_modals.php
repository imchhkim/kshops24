<?php

/**
 * KShops24 부동산 매물 관리 모달 분리 모듈 (manage_shop_item_modals.php)
 */
if (!isset($shop_id)) exit;
?>

<!-- 매물 정보 수정 모달 -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="itemForm" method="POST" action="manage_shop.php?pg=manage_shop_item" onsubmit="prepareYoutubeUrls(); saveImageBatch(event, 'item_images');" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">매물 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-primary"><i class="bi bi-images me-1"></i>매물 이미지 (최대 10장)</label>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> 4:3 비율을 권장합니다. 첫 번째가 썸네일입니다. 드래그하여 순서를 변경하세요.</p>
                    <style>
                        /* 모바일에서 이미지가 2장씩 꽉 차게 (gap-2 반반 고려) */
                        #item-image-container .gallery-item {
                            width: calc(50% - 4px) !important;
                            height: auto !important;
                            aspect-ratio: 4 / 3;
                        }

                        /* PC에서는 이미지가 3장씩 꽉 차게 설정 (gap-2를 고려한 33.33% 비율) */
                        @media (min-width: 768px) {
                            #item-image-container .gallery-item {
                                width: calc(33.333% - 5.33px) !important;
                            }
                        }
                    </style>
                    <div id="item-image-container" class="d-flex flex-wrap gap-2 p-2 justify-content-start border rounded shadow-inner bg-light" style="min-height: 90px;">
                        <div class="empty-msg text-muted small w-100 text-center my-auto py-2">등록된 사진이 없습니다.</div>
                        <button type="button" class="btn-add-img d-none"></button>
                    </div>
                    <div class="d-grid mt-3">
                        <button type="button" onclick="document.getElementById('item-file-multi').click()" class="btn btn-outline-primary btn-sm border-dashed py-2 fw-bold">
                            <i class="bi bi-camera-fill me-1"></i> 사진 등록 / 추가하기
                        </button>
                    </div>
                    <input type="hidden" name="item_img_path" id="item_img_path">
                    <input type="hidden" name="old_img_path" id="old_img_path">
                    <input type="hidden" name="item_id" id="item_id">
                    <input type="file" id="item-file-multi" class="d-none" accept="image/*" multiple onchange="addBatchImage('item_images', this)">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">카테고리</label>
                        <select name="cat_id" id="cat_id" class="form-select">
                            <option value="">선택 안 함 (기타)</option>
                            <?php foreach ($category_list as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['cat_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">거래 유형</label>
                        <select name="trade_type" id="trade_type" class="form-select" required>
                            <?php
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
                            
                            foreach ($trade_options as $type): 
                                if (empty($type)) continue;
                            ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- [신규] 다국어 지원 매물 정보 입력 탭 -->
                <div class="mb-4">
                    <?php
                    $supported_langs_name = [
                        'ko' => '한국어',
                        'en' => '영어',
                        'zh' => '중국어',
                        'ja' => '일본어',
                        'es' => '스페인어',
                        'fr' => '프랑스어',
                        'ru' => '러시아어',
                        'vi' => '베트남어'
                    ];
                    $active_langs = [];
                    for ($i = 1; $i <= 2; $i++) {
                        $lang = $ui["multilingual_lang{$i}"] ?? 'none';
                        if ($lang !== 'none') {
                            if ($lang === 'etc') {
                                $code = strtolower(trim($ui["multilingual_lang{$i}_custom_code"] ?? "etc{$i}"));
                                if (empty($code)) $code = "etc{$i}";
                                $name = trim($ui["multilingual_lang{$i}_custom_name"] ?? 'Other');
                                $active_langs[$code] = $name;
                            } else {
                                $active_langs[$lang] = $supported_langs_name[$lang] ?? strtoupper($lang);
                            }
                        }
                    }
                    ?>
                    <ul class="nav nav-tabs mb-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="lang-ko-tab" data-bs-toggle="tab" data-bs-target="#lang-ko" type="button" role="tab">한국어 <span class="text-danger">*</span></button>
                        </li>
                        <?php foreach ($active_langs as $code => $name): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold text-muted" id="lang-<?php echo $code; ?>-tab" data-bs-toggle="tab" data-bs-target="#lang-<?php echo $code; ?>" type="button" role="tab"><?php echo htmlspecialchars($name); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tab-content bg-light p-3 rounded border border-top-0" style="border-top-left-radius: 0 !important;">
                        <!-- 한국어 -->
                        <div class="tab-pane fade show active" id="lang-ko" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary">매물 이름 <span class="text-danger">*</span></label>
                                <input type="text" name="item_name" id="item_name" class="form-control" placeholder="예: 알라방 콘도 1베드룸 or 소나타 2015식" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-primary">매물 상세 설명</label>
                                <textarea name="item_info" id="item_info" class="form-control" rows="3" placeholder="면적, 구조, 부대시설, 차랑 연식 옵션 설명 등 상세 정보"></textarea>
                            </div>
                        </div>

                        <!-- 설정된 외국어 1, 2 -->
                        <?php foreach ($active_langs as $code => $name): ?>
                            <div class="tab-pane fade" id="lang-<?php echo $code; ?>" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">매물 이름 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="item_name_<?php echo $code; ?>" id="item_name_<?php echo $code; ?>" class="form-control">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-secondary">매물 상세 설명 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <textarea name="item_info_<?php echo $code; ?>" id="item_info_<?php echo $code; ?>" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> 외국어 정보는 파파고와 같은 번역 서비스 사이트에서 한국어를 번역한 후에 입력하세요.</p>
                </div>

                <!-- [수정] 다중 YouTube 링크 입력 UI -->
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="bi bi-youtube text-danger me-1"></i>YouTube 동영상 링크</label>
                    <div id="youtube-links-container" class="d-flex flex-column gap-2">
                        <!-- 동적 입력 필드가 여기에 추가됩니다. -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addYoutubeInput()">
                        <i class="bi bi-plus-circle me-1"></i> 링크 추가
                    </button>
                    <!-- 최종 JSON 데이터가 여기에 담겨 서버로 전송됩니다. -->
                    <input type="hidden" name="item_youtube_url" id="item_youtube_url">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-bold">매매가/월세 (₱)</label>
                        <input type="text" id="item_price_display" class="form-control" required oninput="formatNumberInput(this); calculateDiscount()">
                        <input type="hidden" name="item_price" id="item_price">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">급매 할인율 (%)</label>
                        <input type="number" name="item_discount_rate" id="item_discount_rate" class="form-control" placeholder="0" oninput="calculateDiscount()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">급매 가격</label>
                        <input type="text" id="item_discount_price_display" class="form-control" readonly>
                        <input type="hidden" name="item_discount_price" id="item_discount_price">
                    </div>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> "급매 할인율 (%)"에 값을 입력하면 본 매물은 "급매" 처리됩니다.</p>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_best" id="bestI"><label class="form-check-label small fw-bold" for="bestI">🌟 추천</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_new" id="newI"><label class="form-check-label small fw-bold" for="newI">🔥 신규</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-danger text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_soldout" id="soldoutI"><label class="form-check-label small fw-bold text-danger" for="soldoutI">🚫 거래완료</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-dark text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_hide" id="hideI"><label class="form-check-label small fw-bold text-dark" for="hideI">🌑 숨김</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="add_item" id="modal-submit-btn" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- 상담 및 기타 정책 수정 모달 -->
<div class="modal fade" id="editPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=manage_shop_item_policy" onsubmit="return prepareTradeTypes();">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>상담 및 기타 정책 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                $dh = $shop['delivery_hours'] ?? '';
                $is_always = ($dh === '상시 문의');
                $dh_start = '';
                $dh_end = '';
                if (strpos($dh, '~') !== false) {
                    $parts = explode('~', $dh);
                    $dh_start = trim($parts[0]);
                    $dh_end = trim($parts[1]);
                }
                $dh_class = $is_always ? 'bg-secondary bg-opacity-10 text-muted' : '';
                $dh_attr = $is_always ? 'readonly tabindex="-1" style="pointer-events: none;"' : '';
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">상담 가능 시간</label>
                        <div class="form-check form-switch mb-0 d-flex align-items-center">
                            <input class="form-check-input mt-0 me-2" type="checkbox" name="dh_always_available" id="dh_always_available" value="1" <?php echo $is_always ? 'checked' : ''; ?> onchange="toggleDhInputs()">
                            <label class="form-check-label small fw-bold" for="dh_always_available" style="cursor: pointer;">상시 문의</label>
                        </div>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="time" name="delivery_hours_start" id="dh_start" class="form-control <?php echo $dh_class; ?>" value="<?php echo htmlspecialchars($dh_start); ?>" <?php echo $dh_attr; ?>>
                        <span class="input-group-text bg-light border-start-0 border-end-0">~</span>
                        <input type="time" name="delivery_hours_end" id="dh_end" class="form-control <?php echo $dh_class; ?>" value="<?php echo htmlspecialchars($dh_end); ?>" <?php echo $dh_attr; ?>>
                    </div>
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle text-primary me-1"></i> 상담 가능 시간을 비워두면 영업시간과 동일하게 표시됩니다.</div>
                </div>
                <?php
                $lang1 = $ui['multilingual_lang1'] ?? 'none';
                $lang2 = $ui['multilingual_lang2'] ?? 'none';
                $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                if (empty($lang1_code)) $lang1_code = 'etc1';
                $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                if (empty($lang2_code)) $lang2_code = 'etc2';
                $policy_trans = json_decode($shop['policy_translations'] ?? '{}', true);
                ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">수수료 안내</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="delivery_fee_info" class="form-control border-start-0" value="<?php echo htmlspecialchars($shop['delivery_fee_info'] ?? ''); ?>" placeholder="예: 매매/임대 수수료 규정 따름">
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <input type="text" name="delivery_fee_info_<?php echo $lang1_code; ?>" class="form-control border-start-0" value="<?php echo htmlspecialchars($policy_trans[$lang1_code]['delivery_fee_info'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <input type="text" name="delivery_fee_info_<?php echo $lang2_code; ?>" class="form-control border-start-0" value="<?php echo htmlspecialchars($policy_trans[$lang2_code]['delivery_fee_info'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">거래 방식</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="payment_methods" class="form-control border-start-0" value="<?php echo htmlspecialchars($shop['payment_methods'] ?? ''); ?>" placeholder="예: 은행 이체, 수표 등">
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <input type="text" name="payment_methods_<?php echo $lang1_code; ?>" class="form-control border-start-0" value="<?php echo htmlspecialchars($policy_trans[$lang1_code]['payment_methods'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <input type="text" name="payment_methods_<?php echo $lang2_code; ?>" class="form-control border-start-0" value="<?php echo htmlspecialchars($policy_trans[$lang2_code]['payment_methods'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr class="my-4 border-secondary border-opacity-25">
                <div class="mb-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-primary mb-0">매물 거래 유형 (다국어 지원)</label>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill shadow-sm" onclick="addTradeTypeField()"><i class="bi bi-plus-lg me-1"></i>추가</button>
                    </div>
                    <div id="trade-types-container" class="d-flex flex-column gap-2 mb-2">
                        <!-- 동적 필드 생성 영역 -->
                    </div>
                    <input type="hidden" name="custom_trade_types" id="custom_trade_types_hidden">
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle text-primary me-1"></i> 매물 등록 시 선택할 <b>거래 유형 옵션</b>을 추가하고 언어별 번역을 입력하세요.</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-dark w-100 py-3 fw-bold rounded-pill shadow">정책 저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- 이미지 원본 보기 모달 -->
<div class="modal fade" id="imageViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 p-2 bg-white rounded-circle shadow" data-bs-dismiss="modal" aria-label="Close" style="z-index: 10;"></button>
                <img src="" id="modal-image-view" class="img-fluid rounded shadow-lg" style="max-height: 80vh;" onerror="this.style.display='none'; document.getElementById('modal-no-image-text').style.setProperty('display', 'flex', 'important');">
                <div id="modal-no-image-text" class="bg-white rounded shadow-lg align-items-center justify-content-center flex-column w-100" style="height: 300px; display: none;">
                    <i class="bi bi-camera text-muted" style="font-size: 4rem; margin-bottom: 15px;"></i>
                    <h5 class="fw-bold text-dark">등록된 사진이 없습니다.</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleDhInputs() {
        const isAlways = document.getElementById('dh_always_available').checked;
        const startInput = document.getElementById('dh_start');
        const endInput = document.getElementById('dh_end');

        startInput.readOnly = isAlways;
        endInput.readOnly = isAlways;

        if (isAlways) {
            startInput.value = '';
            endInput.value = '';
            startInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            startInput.style.pointerEvents = 'none';
            startInput.tabIndex = -1;
            endInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            endInput.style.pointerEvents = 'none';
            endInput.tabIndex = -1;
        } else {
            startInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            startInput.style.pointerEvents = 'auto';
            startInput.removeAttribute('tabindex');
            endInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            endInput.style.pointerEvents = 'auto';
            endInput.removeAttribute('tabindex');
        }
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        renderTradeTypes();
    });

    <?php
    $ctt_val = $ui['custom_trade_types'] ?? '';
    $ctt_array = [];
    if (!empty($ctt_val)) {
        $decoded = json_decode($ctt_val, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $ctt_array = $decoded;
        } else {
            // 기존 콤마 구분자 하위 호환 처리
            $parts = array_filter(array_map('trim', explode(',', $ctt_val)));
            foreach ($parts as $p) {
                $ctt_array[] = ['ko' => $p];
            }
        }
    } else {
        $default_types = defined('REALTY_TRADE_TYPES') ? REALTY_TRADE_TYPES : ['매매', '장기임대 (1년 혹은 그 이상)', '단기임대 (수개월)', '기타'];
        foreach ($default_types as $p) {
            $ctt_array[] = ['ko' => $p];
        }
    }
    ?>
    const tradeTypesData = <?php echo json_encode($ctt_array, JSON_UNESCAPED_UNICODE); ?>;
    const lang1Code = '<?php echo $lang1 !== "none" ? $lang1_code : ""; ?>';
    const lang2Code = '<?php echo $lang2 !== "none" ? $lang2_code : ""; ?>';
    
    function renderTradeTypes() {
        const container = document.getElementById('trade-types-container');
        if (!container) return;
        container.innerHTML = '';
        tradeTypesData.forEach(item => {
            container.insertAdjacentHTML('beforeend', createTradeTypeHtml(item));
        });
    }

    function createTradeTypeHtml(item = {ko:''}) {
        let html = `<div class="card p-2 border border-secondary border-opacity-25 bg-white shadow-sm trade-type-item position-relative">
            <button type="button" class="btn btn-sm text-danger position-absolute top-0 end-0 m-1 p-0 px-1" onclick="this.closest('.trade-type-item').remove()" title="삭제"><i class="bi bi-x-circle-fill fs-5"></i></button>
            <div class="d-flex flex-column gap-1 pe-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted" style="min-width:55px; font-size:0.7rem;">KO <span class="text-danger ms-1">*</span></span>
                    <input type="text" class="form-control border-start-0 trade-type-input" data-lang="ko" value="${item.ko || ''}" placeholder="한국어 필수" required>
                </div>`;
        if (lang1Code) html += `<div class="input-group input-group-sm"><span class="input-group-text bg-light text-muted" style="min-width:55px; font-size:0.7rem; text-transform:uppercase;">${lang1Code}</span><input type="text" class="form-control border-start-0 trade-type-input" data-lang="${lang1Code}" value="${item[lang1Code] || ''}"></div>`;
        if (lang2Code) html += `<div class="input-group input-group-sm"><span class="input-group-text bg-light text-muted" style="min-width:55px; font-size:0.7rem; text-transform:uppercase;">${lang2Code}</span><input type="text" class="form-control border-start-0 trade-type-input" data-lang="${lang2Code}" value="${item[lang2Code] || ''}"></div>`;
        html += `</div></div>`;
        return html;
    }

    function addTradeTypeField() {
        document.getElementById('trade-types-container').insertAdjacentHTML('beforeend', createTradeTypeHtml());
    }

    function prepareTradeTypes() {
        const items = [];
        document.querySelectorAll('.trade-type-item').forEach(el => {
            let obj = {};
            el.querySelectorAll('.trade-type-input').forEach(input => {
                obj[input.dataset.lang] = input.value.trim();
            });
            if (obj.ko) items.push(obj);
        });
        document.getElementById('custom_trade_types_hidden').value = JSON.stringify(items);
        return true;
    }
</script>

<!-- 미디어 상세 보기 모달 -->
<div class="modal fade" id="itemMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="background-color: rgba(20, 20, 20, 0.85) !important; backdrop-filter: blur(12px);">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title text-white" id="itemMediaModalTitle">미디어 보기</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="itemMediaModalBody"></div>
        </div>
    </div>
</div>

<!-- [모달] 매물 미리보기 모달 (관리자용 읽기 전용) -->
<div class="modal fade" id="previewItemModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-eye me-2"></i> 매물 상세 미리보기 <span id="preview-lang-badge" class="badge bg-secondary ms-2 d-none"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-0 bg-light">
                    <ul class="nav nav-pills mb-3 gap-2" role="tablist">
                        <li class="nav-item" id="preview-photo-tab-item">
                            <button class="nav-link active fw-bold px-3 py-1" data-bs-toggle="pill" data-bs-target="#preview-photo-pane" type="button"><i class="bi bi-images me-1"></i>사진 정보</button>
                        </li>
                        <li class="nav-item" id="preview-video-tab-item">
                            <button class="nav-link fw-bold px-3 py-1" data-bs-toggle="pill" data-bs-target="#preview-video-pane" type="button"><i class="bi bi-youtube me-1"></i>동영상 정보</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="preview-photo-pane">
                            <div id="preview-photo-container" class="detail-media-container position-relative rounded overflow-hidden shadow-sm" style="aspect-ratio: 4/3;"></div>
                        </div>
                        <div class="tab-pane fade" id="preview-video-pane">
                            <div id="preview-video-container" class="detail-media-container position-relative rounded overflow-hidden shadow-sm" style="aspect-ratio: 4/3;"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4 pt-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div id="preview-badges" class="mb-2 d-flex flex-wrap gap-1"></div>
                            <h4 class="fw-bold mb-1" id="preview-item-name">매물명</h4>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-lg rounded-pill px-4 shadow-sm flex-shrink-0" disabled style="opacity: 0.5;">
                            <i class="bi bi-heart me-2"></i>
                        </button>
                    </div>
                    <div class="price-area mb-4 p-3 bg-light rounded-3">
                        <span class="fs-4 fw-bold text-primary" id="preview-final-price">₱ 0</span>
                        <span class="text-muted text-decoration-line-through small ms-2 d-none" id="preview-original-price">₱ 0</span>
                    </div>
                    <div class="mb-5">
                        <h6 class="fw-bold mb-3"><i class="bi bi-card-text me-2"></i>상세 정보</h6>
                        <p class="text-secondary" id="preview-item-info" style="line-height: 1.7; white-space: pre-wrap;">상세 설명</p>
                    </div>
                    <div class="inquiry-section opacity-50" style="pointer-events: none;">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill me-2"></i>이 매물 문의하기 (미리보기 모드)</h6>
                        <div class="mb-4 bg-white p-3 rounded-4 border shadow-sm">
                            <button class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow-sm" type="button" disabled>문의 접수 완료하기 (비활성화)</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100 py-3 rounded-pill fw-bold" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>