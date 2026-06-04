<<<<<<< HEAD
<?php

/**
 * KShops24 서비스/예약 관리 모달 분리 모듈 (manage_shop_srv_modals.php)
 */
if (!isset($shop_id)) exit;
?>

<!-- 서비스 정보 수정 모달 -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="itemForm" method="POST" action="manage_shop.php?pg=manage_shop_srv" onsubmit="prepareYoutubeUrls(); saveImageBatch(event, 'item_images');" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">서비스 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-primary"><i class="bi bi-images me-1"></i>서비스 이미지 (최대 10장)</label>
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
                        <label class="form-label small fw-bold">서비스 유형</label>
                        <select name="trade_type" id="trade_type" class="form-select" required>
                            <?php
                            $service_types = defined('SRV_SERVICE_TYPES') ? SRV_SERVICE_TYPES : ['방문 서비스', '매장 내 서비스', '온라인 서비스', '기타'];
                            foreach ($service_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- [신규] 다국어 지원 매물 정보 입력 탭 -->
                <div class="mb-4">
                    <?php
                    $is_multi = ($ui['is_multilingual'] ?? 0) == 1;
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
                    if ($is_multi) {
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
                    }
                    ?>
                    <?php if (!empty($active_langs)): ?>
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
                    <?php endif; ?>

                    <div class="<?php echo !empty($active_langs) ? 'tab-content bg-light p-3 rounded border border-top-0" style="border-top-left-radius: 0 !important;"' : ''; ?>">
                        <!-- 한국어 -->
                        <div class="<?php echo !empty($active_langs) ? 'tab-pane fade show active' : ''; ?>" id="lang-ko" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary">서비스 이름 <span class="text-danger">*</span></label>
                                <input type="text" name="item_name" id="item_name" class="form-control" placeholder="예: 남성 디자인 컷, 프리미엄 스팀 세차" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-primary">서비스 상세 설명</label>
                                <textarea name="item_info" id="item_info" class="form-control" rows="3" placeholder="서비스의 특징, 소요 시간, 포함/불포함 내역 등 상세 정보"></textarea>
                            </div>
                        </div>

                        <!-- 설정된 외국어 1, 2 -->
                        <?php foreach ($active_langs as $code => $name): ?>
                            <div class="tab-pane fade" id="lang-<?php echo $code; ?>" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">서비스 이름 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="item_name_<?php echo $code; ?>" id="item_name_<?php echo $code; ?>" class="form-control">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-secondary">서비스 상세 설명 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <textarea name="item_info_<?php echo $code; ?>" id="item_info_<?php echo $code; ?>" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($active_langs)): ?>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> 외국어 정보는 파파고와 같은 번역 서비스 사이트에서 한국어를 번역한 후에 입력하세요.</p>
                    <?php endif; ?>
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
                        <label class="form-label small fw-bold">서비스 가격 (₱)</label>
                        <input type="number" name="item_price" id="item_price" class="form-control" required oninput="calculateDiscount()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">할인율 (%)</label>
                        <input type="number" name="item_discount_rate" id="item_discount_rate" class="form-control" placeholder="0" oninput="calculateDiscount()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">할인 가격</label>
                        <input type="number" name="item_discount_price" id="item_discount_price" class="form-control" readonly>
                    </div>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> "할인율 (%)"에 값을 입력하면 본 서비스는 "할인 서비스"로 표시됩니다.</p>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_best" id="bestI"><label class="form-check-label small fw-bold" for="bestI">🌟 추천</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_new" id="newI"><label class="form-check-label small fw-bold" for="newI">🔥 신규</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-danger text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_soldout" id="soldoutI"><label class="form-check-label small fw-bold text-danger" for="soldoutI">🚫 예약마감</label></div>
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

<!-- 서비스/예약 정책 수정 모달 -->
<div class="modal fade" id="editPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=manage_shop_srv_policy">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>서비스/예약 정책 수정</h5>
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
                        <label class="form-label small fw-bold mb-0">예약 가능 시간</label>
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
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle text-primary me-1"></i> 예약 가능 시간을 비워두면 영업시간과 동일하게 표시됩니다.</div>
                </div>
                <?php
                $is_multi = ($ui['is_multilingual'] ?? 0) == 1;
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
                if ($is_multi) {
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
                }
                $policy_trans = json_decode($shop['policy_translations'] ?? '{}', true);
                ?>
                <div class="mb-4">
                    <?php if (!empty($active_langs)): ?>
                    <ul class="nav nav-tabs mb-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="policy-ko-tab" data-bs-toggle="tab" data-bs-target="#policy-ko" type="button" role="tab">한국어 <span class="text-danger">*</span></button>
                        </li>
                        <?php foreach ($active_langs as $code => $name): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold text-muted" id="policy-<?php echo $code; ?>-tab" data-bs-toggle="tab" data-bs-target="#policy-<?php echo $code; ?>" type="button" role="tab"><?php echo htmlspecialchars($name); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="<?php echo !empty($active_langs) ? 'tab-content bg-light p-3 rounded border border-top-0" style="border-top-left-radius: 0 !important;"' : ''; ?>">
                        <!-- 한국어 -->
                        <div class="<?php echo !empty($active_langs) ? 'tab-pane fade show active' : ''; ?>" id="policy-ko" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary">서비스 비용 안내</label>
                                <input type="text" name="delivery_fee_info" class="form-control" value="<?php echo htmlspecialchars($shop['delivery_fee_info'] ?? ''); ?>" placeholder="예: 예약 시 안내">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-primary">서비스 방식</label>
                                <input type="text" name="payment_methods" class="form-control" value="<?php echo htmlspecialchars($shop['payment_methods'] ?? ''); ?>" placeholder="예: 예약, 방문 결제 등">
                            </div>
                        </div>

                        <!-- 설정된 외국어 1, 2 -->
                        <?php foreach ($active_langs as $code => $name): ?>
                            <div class="tab-pane fade" id="policy-<?php echo $code; ?>" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">서비스 비용 안내 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="delivery_fee_info_<?php echo $code; ?>" class="form-control" value="<?php echo htmlspecialchars($policy_trans[$code]['delivery_fee_info'] ?? ''); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-secondary">서비스 방식 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="payment_methods_<?php echo $code; ?>" class="form-control" value="<?php echo htmlspecialchars($policy_trans[$code]['payment_methods'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($active_langs)): ?>
                    <p class="text-muted small mt-2 mb-0"><i class="bi bi-lightbulb me-1"></i> 외국어 정보는 파파고와 같은 번역 서비스 사이트에서 한국어를 번역한 후에 입력하세요.</p>
                    <?php endif; ?>
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

<!-- [모달] 서비스 미리보기 모달 (관리자용 읽기 전용) -->
<div class="modal fade" id="previewItemModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-eye me-2"></i> 서비스 상세 미리보기 <span id="preview-lang-badge" class="badge bg-secondary ms-2 d-none"></span></h5>
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
                            <h4 class="fw-bold mb-1" id="preview-item-name">서비스명</h4>
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
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill me-2"></i>이 서비스 문의하기 (미리보기 모드)</h6>
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
=======
<?php

/**
 * KShops24 서비스/예약 관리 모달 분리 모듈 (manage_shop_srv_modals.php)
 */
if (!isset($shop_id)) exit;
?>

<!-- 서비스 정보 수정 모달 -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="itemForm" method="POST" action="manage_shop.php?pg=manage_shop_srv" onsubmit="prepareYoutubeUrls(); saveImageBatch(event, 'item_images');" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">서비스 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-primary"><i class="bi bi-images me-1"></i>서비스 이미지 (최대 10장)</label>
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
                        <label class="form-label small fw-bold">서비스 유형</label>
                        <select name="trade_type" id="trade_type" class="form-select" required>
                            <?php
                            $service_types = defined('SRV_SERVICE_TYPES') ? SRV_SERVICE_TYPES : ['방문 서비스', '매장 내 서비스', '온라인 서비스', '기타'];
                            foreach ($service_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- [신규] 다국어 지원 매물 정보 입력 탭 -->
                <div class="mb-4">
                    <?php
                    $is_multi = ($ui['is_multilingual'] ?? 0) == 1;
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
                    if ($is_multi) {
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
                    }
                    ?>
                    <?php if (!empty($active_langs)): ?>
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
                    <?php endif; ?>

                    <div class="<?php echo !empty($active_langs) ? 'tab-content bg-light p-3 rounded border border-top-0" style="border-top-left-radius: 0 !important;"' : ''; ?>">
                        <!-- 한국어 -->
                        <div class="<?php echo !empty($active_langs) ? 'tab-pane fade show active' : ''; ?>" id="lang-ko" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary">서비스 이름 <span class="text-danger">*</span></label>
                                <input type="text" name="item_name" id="item_name" class="form-control" placeholder="예: 남성 디자인 컷, 프리미엄 스팀 세차" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-primary">서비스 상세 설명</label>
                                <textarea name="item_info" id="item_info" class="form-control" rows="3" placeholder="서비스의 특징, 소요 시간, 포함/불포함 내역 등 상세 정보"></textarea>
                            </div>
                        </div>

                        <!-- 설정된 외국어 1, 2 -->
                        <?php foreach ($active_langs as $code => $name): ?>
                            <div class="tab-pane fade" id="lang-<?php echo $code; ?>" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">서비스 이름 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="item_name_<?php echo $code; ?>" id="item_name_<?php echo $code; ?>" class="form-control">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-secondary">서비스 상세 설명 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <textarea name="item_info_<?php echo $code; ?>" id="item_info_<?php echo $code; ?>" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($active_langs)): ?>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> 외국어 정보는 파파고와 같은 번역 서비스 사이트에서 한국어를 번역한 후에 입력하세요.</p>
                    <?php endif; ?>
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
                        <label class="form-label small fw-bold">서비스 가격 (₱)</label>
                        <input type="number" name="item_price" id="item_price" class="form-control" required oninput="calculateDiscount()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">할인율 (%)</label>
                        <input type="number" name="item_discount_rate" id="item_discount_rate" class="form-control" placeholder="0" oninput="calculateDiscount()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-danger">할인 가격</label>
                        <input type="number" name="item_discount_price" id="item_discount_price" class="form-control" readonly>
                    </div>
                    <p class="text-muted small mt-2 mb-2"><i class="bi bi-lightbulb me-1"></i> "할인율 (%)"에 값을 입력하면 본 서비스는 "할인 서비스"로 표시됩니다.</p>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_best" id="bestI"><label class="form-check-label small fw-bold" for="bestI">🌟 추천</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_new" id="newI"><label class="form-check-label small fw-bold" for="newI">🔥 신규</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-danger text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_soldout" id="soldoutI"><label class="form-check-label small fw-bold text-danger" for="soldoutI">🚫 예약마감</label></div>
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

<!-- 서비스/예약 정책 수정 모달 -->
<div class="modal fade" id="editPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=manage_shop_srv_policy">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>서비스/예약 정책 수정</h5>
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
                        <label class="form-label small fw-bold mb-0">예약 가능 시간</label>
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
                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle text-primary me-1"></i> 예약 가능 시간을 비워두면 영업시간과 동일하게 표시됩니다.</div>
                </div>
                <?php
                $is_multi = ($ui['is_multilingual'] ?? 0) == 1;
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
                if ($is_multi) {
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
                }
                $policy_trans = json_decode($shop['policy_translations'] ?? '{}', true);
                ?>
                <div class="mb-4">
                    <?php if (!empty($active_langs)): ?>
                    <ul class="nav nav-tabs mb-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="policy-ko-tab" data-bs-toggle="tab" data-bs-target="#policy-ko" type="button" role="tab">한국어 <span class="text-danger">*</span></button>
                        </li>
                        <?php foreach ($active_langs as $code => $name): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold text-muted" id="policy-<?php echo $code; ?>-tab" data-bs-toggle="tab" data-bs-target="#policy-<?php echo $code; ?>" type="button" role="tab"><?php echo htmlspecialchars($name); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="<?php echo !empty($active_langs) ? 'tab-content bg-light p-3 rounded border border-top-0" style="border-top-left-radius: 0 !important;"' : ''; ?>">
                        <!-- 한국어 -->
                        <div class="<?php echo !empty($active_langs) ? 'tab-pane fade show active' : ''; ?>" id="policy-ko" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary">서비스 비용 안내</label>
                                <input type="text" name="delivery_fee_info" class="form-control" value="<?php echo htmlspecialchars($shop['delivery_fee_info'] ?? ''); ?>" placeholder="예: 예약 시 안내">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small fw-bold text-primary">서비스 방식</label>
                                <input type="text" name="payment_methods" class="form-control" value="<?php echo htmlspecialchars($shop['payment_methods'] ?? ''); ?>" placeholder="예: 예약, 방문 결제 등">
                            </div>
                        </div>

                        <!-- 설정된 외국어 1, 2 -->
                        <?php foreach ($active_langs as $code => $name): ?>
                            <div class="tab-pane fade" id="policy-<?php echo $code; ?>" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">서비스 비용 안내 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="delivery_fee_info_<?php echo $code; ?>" class="form-control" value="<?php echo htmlspecialchars($policy_trans[$code]['delivery_fee_info'] ?? ''); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold text-secondary">서비스 방식 (<?php echo htmlspecialchars($name); ?>)</label>
                                    <input type="text" name="payment_methods_<?php echo $code; ?>" class="form-control" value="<?php echo htmlspecialchars($policy_trans[$code]['payment_methods'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($active_langs)): ?>
                    <p class="text-muted small mt-2 mb-0"><i class="bi bi-lightbulb me-1"></i> 외국어 정보는 파파고와 같은 번역 서비스 사이트에서 한국어를 번역한 후에 입력하세요.</p>
                    <?php endif; ?>
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

<!-- [모달] 서비스 미리보기 모달 (관리자용 읽기 전용) -->
<div class="modal fade" id="previewItemModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-eye me-2"></i> 서비스 상세 미리보기 <span id="preview-lang-badge" class="badge bg-secondary ms-2 d-none"></span></h5>
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
                            <h4 class="fw-bold mb-1" id="preview-item-name">서비스명</h4>
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
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill me-2"></i>이 서비스 문의하기 (미리보기 모드)</h6>
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
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</div>