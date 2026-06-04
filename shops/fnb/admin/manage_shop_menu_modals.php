<<<<<<< HEAD
<?php

/**
 * KShops24 메뉴 관리 모달 분리 모듈 (manage_shop_menu_modals.php)
 */
if (!isset($shop_id)) exit;

// [다국어] UI 설정에서 활성화된 언어 코드 가져오기
$lang1 = $ui['multilingual_lang1'] ?? 'none';
$lang2 = $ui['multilingual_lang2'] ?? 'none';
$lang1_code = $lang1;
$lang2_code = $lang2;
?>

<!-- [모달 1] 메뉴 설정 모달 -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" onsubmit="saveImageBatch(event, 'menu_images')">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">메뉴 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-primary"><i class="bi bi-images me-1"></i> 메뉴 이미지 (최대 10장)</label>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 메뉴 이미지음식 사진은 <span class="fw-bold text-primary">4:3 비율</span> 촬영을 권장합니다. 첫 번째 사진이 대표 썸네일로 노출됩니다. 이미지를 드래그하여 순서를 변경하세요.</p>

                    <div id="menu-image-container" class="row g-2 p-1 border rounded shadow-inner bg-light" style="min-height: 90px;">
                        <div class="empty-msg text-muted small w-100 text-center my-auto py-2">등록된 사진이 없습니다.</div>
                        <button type="button" class="btn-add-img d-none"></button>
                    </div>

                    <div class="d-grid mt-3">
                        <label for="menu-file-multi" class="btn btn-outline-primary btn-sm border-dashed py-2 fw-bold mb-0" style="cursor: pointer;">
                            <i class="bi bi-camera-fill me-1"></i> 사진 등록 / 추가하기
                        </label>
                    </div>
                    <input type="hidden" name="item_img_path" id="item_img_path">
                    <input type="hidden" name="old_img_path" id="old_img_path">
                    <input type="hidden" name="item_id" id="item_id">
                    <input type="file" id="menu-file-multi" class="d-none" accept="image/*" multiple onchange="addBatchImage('menu_images', this)">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">카테고리</label>
                    <select name="cat_id" id="cat_id" class="form-select">
                        <option value="">선택 안 함 (기타)</option>
                        <?php foreach ($category_list as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['cat_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">메뉴 이름</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="item_name" id="item_name" class="form-control border-start-0" required>
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <input type="text" name="item_name_<?php echo $lang1_code; ?>" id="item_name_<?php echo $lang1_code; ?>" class="form-control border-start-0">
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <input type="text" name="item_name_<?php echo $lang2_code; ?>" id="item_name_<?php echo $lang2_code; ?>" class="form-control border-start-0">
                            </div>
                        <?php endif; ?>
                    </div>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 외국어 입력은 파파고와 같은 번역 서비스 사이트를 이용하세요.</p>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold"><i class="bi bi-youtube text-danger me-1"></i> YouTube 동영상 링크</label>
                    <input type="url" name="item_youtube_url" id="item_youtube_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-bold">정상 가격</label>
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
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">메뉴 설명</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <textarea name="item_info" id="item_info" class="form-control border-start-0" rows="2"></textarea>
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <textarea name="item_info_<?php echo $lang1_code; ?>" id="item_info_<?php echo $lang1_code; ?>" class="form-control border-start-0" rows="2"></textarea>
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <textarea name="item_info_<?php echo $lang2_code; ?>" id="item_info_<?php echo $lang2_code; ?>" class="form-control border-start-0" rows="2"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_best" id="bestM"><label class="form-check-label small fw-bold" for="bestM">🌟 BEST</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_new" id="newM"><label class="form-check-label small fw-bold" for="newM">🔥 NEW</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-danger text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_soldout" id="soldoutM"><label class="form-check-label small fw-bold text-danger" for="soldoutM">🚫 품절</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-dark text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_hide" id="hideM"><label class="form-check-label small fw-bold text-dark" for="hideM">🌑 숨김</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="add_menu" id="modal-submit-btn" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [모달 2] 배달 정책 수정 -->
<div class="modal fade" id="editDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=manage_shop_menu_policy">
            <input type="hidden" name="current_pg" value="manage_shop_menu_policy">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>운영 및 배달 정보 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                $dh = $shop['delivery_hours'] ?? '';
                $dh_start = '';
                $dh_end = '';
                if (strpos($dh, '~') !== false) {
                    $parts = explode('~', $dh);
                    $dh_start = trim($parts[0]);
                    $dh_end = trim($parts[1]);
                }
                ?>
                <!-- [개선 1] 배달 가능 시간 입력 방식 개선 (시작/종료 시간 분리 및 HTML5 time input 활용) -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">배달 가능 시간</label>
                    <div class="input-group input-group-sm">
                        <input type="time" name="delivery_hours_start" class="form-control" value="<?php echo htmlspecialchars($dh_start); ?>">
                        <span class="input-group-text bg-light border-start-0 border-end-0">~</span>
                        <input type="time" name="delivery_hours_end" class="form-control" value="<?php echo htmlspecialchars($dh_end); ?>">
                    </div>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 시간을 비워두면 영업시간과 같습니다.</p>
                </div>
                <div class="row g-2 mb-0">
                    <div class="col-6"><label class="form-label small fw-bold">배달 최소 주문액</label><input type="number" name="min_delivery_amount" class="form-control" value="<?php echo (int)($shop['min_delivery_amount'] ?? 0); ?>"></div>
                    <div class="col-6"><label class="form-label small fw-bold">배달 예상 시간</label><input type="text" name="estimated_delivery_time" class="form-control" value="<?php echo htmlspecialchars($shop['estimated_delivery_time'] ?? ''); ?>" placeholder="주문 후 30~50분"></div>
                </div>
                <div class="row g-2 mb-0 mt-2">
                    <div class="col-6"><label class="form-label small fw-bold">배달비</label><input type="number" name="delivery_fee" class="form-control" value="<?php echo (int)($shop['delivery_fee'] ?? 0); ?>"></div>
                    <div class="col-6"><label class="form-label small fw-bold">무료 배달 주문액</label><input type="number" name="free_delivery_amount" class="form-control" value="<?php echo (int)($shop['free_delivery_amount'] ?? 0); ?>"><label class="form-label small fw-bold text-danger">0을 입력한 경우, 무료배달 없음</label></div>
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
                <div class="mb-3 mt-2">
                    <label class="form-label small fw-bold">배달비 안내</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="delivery_fee_info" class="form-control border-start-0" value="<?php echo htmlspecialchars($shop['delivery_fee_info'] ?? ''); ?>">
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
                <div class="row g-2 mb-0">
                    <div class="col-8"><label class="form-label small fw-bold">결제 수단</label><input type="text" name="payment_methods" class="form-control" value="<?php echo htmlspecialchars($shop['payment_methods'] ?? 'Cash, GCash'); ?>"></div>
                    <div class="col-4"><label class="form-label small fw-bold">매장픽업 가능</label><select name="is_pickup_available" class="form-select">
                            <option value="1" <?php echo ($shop['is_pickup_available'] == 1) ? 'selected' : ''; ?>>가능</option>
                            <option value="0" <?php echo ($shop['is_pickup_available'] == 0) ? 'selected' : ''; ?>>불가</option>
                        </select></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-dark w-100 py-3 fw-bold rounded-pill shadow">배달 정책 저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [모달 3] 이미지 원본 보기 -->
<div class="modal fade" id="imageViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 p-2 bg-white rounded-circle shadow" data-bs-dismiss="modal" style="z-index: 10;"></button>
                <img src="" id="modal-image-view" class="img-fluid rounded shadow-lg" style="max-height: 80vh;" onerror="this.style.display='none'; document.getElementById('modal-no-image-text').style.setProperty('display', 'flex', 'important');">
            </div>
        </div>
    </div>
</div>

<!-- [모달 4] 메뉴 미디어(이미지/영상) 상세 보기 -->
<div class="modal fade" id="menuMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuMediaModalTitle">미디어 보기</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="menuMediaModalBody"></div>
        </div>
    </div>
=======
<?php

/**
 * KShops24 메뉴 관리 모달 분리 모듈 (manage_shop_menu_modals.php)
 */
if (!isset($shop_id)) exit;

// [다국어] UI 설정에서 활성화된 언어 코드 가져오기
$lang1 = $ui['multilingual_lang1'] ?? 'none';
$lang2 = $ui['multilingual_lang2'] ?? 'none';
$lang1_code = $lang1;
$lang2_code = $lang2;
?>

<!-- [모달 1] 메뉴 설정 모달 -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" onsubmit="saveImageBatch(event, 'menu_images')">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">메뉴 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-primary"><i class="bi bi-images me-1"></i> 메뉴 이미지 (최대 10장)</label>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 메뉴 이미지음식 사진은 <span class="fw-bold text-primary">4:3 비율</span> 촬영을 권장합니다. 첫 번째 사진이 대표 썸네일로 노출됩니다. 이미지를 드래그하여 순서를 변경하세요.</p>

                    <div id="menu-image-container" class="row g-2 p-1 border rounded shadow-inner bg-light" style="min-height: 90px;">
                        <div class="empty-msg text-muted small w-100 text-center my-auto py-2">등록된 사진이 없습니다.</div>
                        <button type="button" class="btn-add-img d-none"></button>
                    </div>

                    <div class="d-grid mt-3">
                        <label for="menu-file-multi" class="btn btn-outline-primary btn-sm border-dashed py-2 fw-bold mb-0" style="cursor: pointer;">
                            <i class="bi bi-camera-fill me-1"></i> 사진 등록 / 추가하기
                        </label>
                    </div>
                    <input type="hidden" name="item_img_path" id="item_img_path">
                    <input type="hidden" name="old_img_path" id="old_img_path">
                    <input type="hidden" name="item_id" id="item_id">
                    <input type="file" id="menu-file-multi" class="d-none" accept="image/*" multiple onchange="addBatchImage('menu_images', this)">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">카테고리</label>
                    <select name="cat_id" id="cat_id" class="form-select">
                        <option value="">선택 안 함 (기타)</option>
                        <?php foreach ($category_list as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['cat_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">메뉴 이름</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="item_name" id="item_name" class="form-control border-start-0" required>
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <input type="text" name="item_name_<?php echo $lang1_code; ?>" id="item_name_<?php echo $lang1_code; ?>" class="form-control border-start-0">
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <input type="text" name="item_name_<?php echo $lang2_code; ?>" id="item_name_<?php echo $lang2_code; ?>" class="form-control border-start-0">
                            </div>
                        <?php endif; ?>
                    </div>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 외국어 입력은 파파고와 같은 번역 서비스 사이트를 이용하세요.</p>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold"><i class="bi bi-youtube text-danger me-1"></i> YouTube 동영상 링크</label>
                    <input type="url" name="item_youtube_url" id="item_youtube_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-bold">정상 가격</label>
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
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">메뉴 설명</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <textarea name="item_info" id="item_info" class="form-control border-start-0" rows="2"></textarea>
                        </div>
                        <?php if ($lang1 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang1_code); ?></span>
                                <textarea name="item_info_<?php echo $lang1_code; ?>" id="item_info_<?php echo $lang1_code; ?>" class="form-control border-start-0" rows="2"></textarea>
                            </div>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;"><?php echo strtoupper($lang2_code); ?></span>
                                <textarea name="item_info_<?php echo $lang2_code; ?>" id="item_info_<?php echo $lang2_code; ?>" class="form-control border-start-0" rows="2"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_best" id="bestM"><label class="form-check-label small fw-bold" for="bestM">🌟 BEST</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-light text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_new" id="newM"><label class="form-check-label small fw-bold" for="newM">🔥 NEW</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-danger text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_soldout" id="soldoutM"><label class="form-check-label small fw-bold text-danger" for="soldoutM">🚫 품절</label></div>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch card p-2 border-dark text-center"><input class="form-check-input ms-0 me-2" type="checkbox" name="is_hide" id="hideM"><label class="form-check-label small fw-bold text-dark" for="hideM">🌑 숨김</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="add_menu" id="modal-submit-btn" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [모달 2] 배달 정책 수정 -->
<div class="modal fade" id="editDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=manage_shop_menu_policy">
            <input type="hidden" name="current_pg" value="manage_shop_menu_policy">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>운영 및 배달 정보 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                $dh = $shop['delivery_hours'] ?? '';
                $dh_start = '';
                $dh_end = '';
                if (strpos($dh, '~') !== false) {
                    $parts = explode('~', $dh);
                    $dh_start = trim($parts[0]);
                    $dh_end = trim($parts[1]);
                }
                ?>
                <!-- [개선 1] 배달 가능 시간 입력 방식 개선 (시작/종료 시간 분리 및 HTML5 time input 활용) -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">배달 가능 시간</label>
                    <div class="input-group input-group-sm">
                        <input type="time" name="delivery_hours_start" class="form-control" value="<?php echo htmlspecialchars($dh_start); ?>">
                        <span class="input-group-text bg-light border-start-0 border-end-0">~</span>
                        <input type="time" name="delivery_hours_end" class="form-control" value="<?php echo htmlspecialchars($dh_end); ?>">
                    </div>
                    <p <?php echo UI_INFO_SM_LABEL; ?>> 시간을 비워두면 영업시간과 같습니다.</p>
                </div>
                <div class="row g-2 mb-0">
                    <div class="col-6"><label class="form-label small fw-bold">배달 최소 주문액</label><input type="number" name="min_delivery_amount" class="form-control" value="<?php echo (int)($shop['min_delivery_amount'] ?? 0); ?>"></div>
                    <div class="col-6"><label class="form-label small fw-bold">배달 예상 시간</label><input type="text" name="estimated_delivery_time" class="form-control" value="<?php echo htmlspecialchars($shop['estimated_delivery_time'] ?? ''); ?>" placeholder="주문 후 30~50분"></div>
                </div>
                <div class="row g-2 mb-0 mt-2">
                    <div class="col-6"><label class="form-label small fw-bold">배달비</label><input type="number" name="delivery_fee" class="form-control" value="<?php echo (int)($shop['delivery_fee'] ?? 0); ?>"></div>
                    <div class="col-6"><label class="form-label small fw-bold">무료 배달 주문액</label><input type="number" name="free_delivery_amount" class="form-control" value="<?php echo (int)($shop['free_delivery_amount'] ?? 0); ?>"><label class="form-label small fw-bold text-danger">0을 입력한 경우, 무료배달 없음</label></div>
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
                <div class="mb-3 mt-2">
                    <label class="form-label small fw-bold">배달비 안내</label>
                    <div class="d-flex flex-column gap-1">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="font-size: 0.7rem; min-width: 50px;">KO</span>
                            <input type="text" name="delivery_fee_info" class="form-control border-start-0" value="<?php echo htmlspecialchars($shop['delivery_fee_info'] ?? ''); ?>">
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
                <div class="row g-2 mb-0">
                    <div class="col-8"><label class="form-label small fw-bold">결제 수단</label><input type="text" name="payment_methods" class="form-control" value="<?php echo htmlspecialchars($shop['payment_methods'] ?? 'Cash, GCash'); ?>"></div>
                    <div class="col-4"><label class="form-label small fw-bold">매장픽업 가능</label><select name="is_pickup_available" class="form-select">
                            <option value="1" <?php echo ($shop['is_pickup_available'] == 1) ? 'selected' : ''; ?>>가능</option>
                            <option value="0" <?php echo ($shop['is_pickup_available'] == 0) ? 'selected' : ''; ?>>불가</option>
                        </select></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-dark w-100 py-3 fw-bold rounded-pill shadow">배달 정책 저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [모달 3] 이미지 원본 보기 -->
<div class="modal fade" id="imageViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 p-2 bg-white rounded-circle shadow" data-bs-dismiss="modal" style="z-index: 10;"></button>
                <img src="" id="modal-image-view" class="img-fluid rounded shadow-lg" style="max-height: 80vh;" onerror="this.style.display='none'; document.getElementById('modal-no-image-text').style.setProperty('display', 'flex', 'important');">
            </div>
        </div>
    </div>
</div>

<!-- [모달 4] 메뉴 미디어(이미지/영상) 상세 보기 -->
<div class="modal fade" id="menuMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuMediaModalTitle">미디어 보기</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="menuMediaModalBody"></div>
        </div>
    </div>
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</div>