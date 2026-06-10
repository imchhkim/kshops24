<?php

/**
 * [컴포넌트] F&B 전용 모달 (fnb_modals.php)
 * F&B 뷰어에서 사용되는 모든 팝업(메뉴 상세, 카트, 주문내역 등)을 보관합니다.
 */
?>
<!-- [신규] 메뉴 상세 정보 모달: 이미지 슬라이드, 영상, 상세설명, 카트담기 통합 -->
<div class="modal fade" id="menuDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0 mb-3 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle me-2"></i><?php echo __('메뉴 상세 보기'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- 상단 미디어 영역 (이미지 슬라이드 또는 유튜브) -->
            <div id="menu-detail-media" class="detail-media-container position-relative"></div>
            <!-- [추가] 슬라이드 안내 문구 -->
            <div id="slide-guide-text" class="text-center text-muted small py-2 bg-light d-none" style="border-bottom: 1px solid #eee;">
                <i class="bi bi-arrow-left-right me-1"></i> <?php echo __('사진을 좌우로 밀어 다른 사진/영상을 볼 수 있습니다.'); ?>
            </div>

            <div class="modal-body p-4">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h4 class="fw-bold mb-0" id="detail-menu-name">메뉴명</h4>
                        <div id="detail-badges"></div>
                    </div>
                    <div class="price-area mb-3">
                        <span class="fs-4 fw-bold text-primary" id="detail-final-price">₱ 0</span>
                        <span class="text-muted text-decoration-line-through small ms-2 d-none" id="detail-original-price">₱ 0</span>
                    </div>
                    <p class="text-secondary small mb-4" id="detail-menu-info" style="line-height: 1.6;">상세 설명이 들어갑니다.</p>
                </div>

                <!-- 수량 조절 섹션 -->
                <?php global $shop; ?>
                <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
                <div class="bg-light rounded-4 p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold small text-muted"><?php echo __('수량 선택'); ?></span>
                        <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-sm btn-white border shadow-sm rounded-circle p-0" style="width:32px; height:32px;" onclick="changeQty(-1)"><i class="bi bi-dash"></i></button>
                            <span class="fw-bold fs-5" id="detail-current-qty">1</span>
                            <button class="btn btn-sm btn-white border shadow-sm rounded-circle p-0" style="width:32px; height:32px;" onclick="changeQty(1)"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-10">
                        <span class="fw-bold"><?php echo __('총 합계'); ?></span>
                        <span class="fw-bold text-primary fs-5" id="detail-subtotal">₱ 0</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-grid">
                    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
                    <button type="button" class="btn btn-primary py-3 rounded-pill fw-bold shadow" onclick="addToCart()">
                        <i class="bi bi-cart-plus ms-1"></i> <?php echo __('카트 담기'); ?>
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline-danger py-3 rounded-pill fw-bold shadow detail-wishlist-btn" onclick="toggleWishlist(currentItem, this)">
                        <i class="bi bi-heart ms-1 detail-wishlist-icon"></i> <span class="detail-wishlist-text"><?php echo __('찜하기'); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [수량 선택 모달]: 메뉴 '담기' 버튼 클릭 시 나타나며, 수량 조절 및 실시간 금액을 계산합니다. -->
<div class="modal fade" id="qtyModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle me-2"></i><?php echo __('메뉴 담기'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <h4 class="fw-bold mb-1" id="qty-menu-name">메뉴명</h4>
                    <p class="text-primary fs-5 fw-bold" id="qty-menu-price">₱ 0</p>
                </div>
                <div class="d-flex justify-content-center align-items-center gap-4 mb-4">
                    <button class="qty-control-btn" onclick="changeQty(-1)"><i class="bi bi-dash-lg fs-4"></i></button>
                    <span class="fs-2 fw-bold" id="current-qty" style="min-width: 50px; text-align: center;">1</span>
                    <button class="qty-control-btn" onclick="changeQty(1)"><i class="bi bi-plus-lg fs-4"></i></button>
                </div>
                <div id="qty-subtotal-container" class="mb-4 py-3 border-top border-bottom text-center text-muted fw-medium"></div>
                <!-- 이미 담긴 항목 표시 영역 -->
                <div id="existing-cart-container" class="mb-4 d-none">
                    <p class="small fw-bold mb-2 text-secondary"><i class="bi bi-cart-check me-1"></i>현재 카트 내역</p>
                    <div id="existing-cart-list" class="bg-light rounded-4 p-3 shadow-sm" style="max-height: 150px; overflow-y: auto;"></div>
                </div>
                <!-- 전체 합계 표시 영역 -->
                <div id="grand-total-container" class="mb-4 p-3 bg-dark text-white rounded-4 d-none shadow-sm">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <span class="small fw-bold opacity-75">최종 합계 금액</span>
                        <span class="fw-bold fs-4" id="grand-total-price">₱ 0</span>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary py-3 rounded-pill fw-bold shadow-sm" onclick="addToCart()"><i class="bi bi-cart-plus ms-1"></i> <?php echo __('카트 담기'); ?></button>
                    <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 위시 리스트 모달 -->
<div class="modal fade" id="wishlistModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-heart-fill text-danger me-2"></i><?php echo __('위시 리스트'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="wishlist-items-list" class="mb-4" style="max-height: 350px; overflow-y: auto;"></div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-light text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [카트보기 모달]: 현재 담긴 모든 항목을 리스트로 보여주고 수량 수정 및 삭제를 지원합니다. -->
<div class="modal fade" id="cartViewModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cart3 me-2"></i><?php echo __('카트 확인'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cart-view-items-list" class="mb-4" style="max-height: 350px; overflow-y: auto;"></div>

                <?php
                $free_delivery_amount = (int)($shop['free_delivery_amount'] ?? 0);
                $delivery_fee = (int)($shop['delivery_fee'] ?? 0);
                if ($free_delivery_amount === 0):
                ?>
                    <!-- 무료 배달 정책이 없는 경우: 무조건 배달비 안내 노출 -->
                    <div class="alert alert-secondary small text-center py-2 mb-3">
                        <i class="bi bi-truck me-1"></i>
                        <?php if ($delivery_fee === 0): ?>
                            <span class="text-success fw-bold">무료 배달</span>
                        <?php else: ?>
                            기본 배달비 <strong class="text-dark">₱ <?php echo number_format($delivery_fee); ?></strong> 부과
                        <?php endif; ?>
                    </div>
                    <!-- JS(fnb_cart.js) 오류 방지용 숨김 요소 -->
                    <div id="free-delivery-notice" class="d-none"></div>
                <?php else: ?>
                    <div id="free-delivery-notice" class="alert small text-center py-2 mb-3 d-none"></div>
                <?php endif; ?>

                <div class="p-3 bg-dark text-white rounded-4 mb-4 shadow-sm" id="cart-view-summary">
                    <div class="d-flex justify-content-between align-items-center px-2 small mb-2">
                        <span class="opacity-75"><?php echo __('상품 금액'); ?></span>
                        <span id="cart-view-subtotal">₱ 0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-2 small mb-2">
                        <span class="opacity-75"><?php echo __('배달료'); ?></span>
                        <span id="cart-view-delivery-fee">₱ 0</span>
                    </div>
                    <hr class="my-2 border-white opacity-25">
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <span class="small fw-bold opacity-75"><?php echo __('최종 합계 금액'); ?></span>
                        <span class="fw-bold fs-4" id="cart-view-total-price">₱ 0</span>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary py-3 rounded-pill fw-bold shadow" onclick="proceedToOrderFromCart()">
                        <?php echo __('주문하기'); ?> <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                    <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('메뉴 더 담기'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [최종 주문서 작성 모달]: 배달 주소와 연락처를 입력받아 서버로 주문을 전송하는 최종 단계입니다. -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-check2-all me-2"></i><?php echo __('주문서 작성'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cart-items-list" class="mb-4 bg-light rounded-4 p-3 shadow-inner" style="max-height: 200px; overflow-y: auto;"></div>

                <?php if ((int)($shop['free_delivery_amount'] ?? 0) === 0): ?>
                    <!-- 무료 배달 정책이 없는 경우: 무조건 배달비 안내 노출 -->
                    <div class="alert alert-secondary small text-center py-2 mb-3 delivery-fee-notice-area">
                        <i class="bi bi-truck me-1"></i>
                        <?php if ((int)($shop['delivery_fee'] ?? 0) === 0): ?>
                            <span class="text-success fw-bold">무료 배달</span>
                        <?php else: ?>
                            기본 배달비 <strong class="text-dark">₱ <?php echo number_format((int)($shop['delivery_fee'] ?? 0)); ?></strong> 부과
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div id="cart-modal-summary" class="d-flex justify-content-between align-items-center mb-4 px-3 py-3 bg-white border rounded-4 shadow-sm">
                    <span class="fw-bold text-muted">총 결제 예정 금액</span>
                    <span class="fw-bold fs-3 text-primary" id="cart-total-price">₱ 0</span>
                </div>
                <form id="orderForm">
                    <?php
                    global $shop;
                    $is_del = (($shop['is_delivery_available'] ?? 1) == 1);
                    $is_pick = $is_del;
                    ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-bag-check me-1"></i> <?php echo __('주문 방식'); ?> *</label>
                        <div class="d-flex gap-4 mb-2 px-1">
                            <?php if ($is_del): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="order_type" id="order_delivery" value="delivery" <?php echo 'checked'; ?> onchange="toggleOrderType()">
                                <label class="form-check-label small fw-bold" for="order_delivery"><?php echo __('배달 (Delivery)'); ?></label>
                            </div>
                            <?php endif; ?>
                            <?php if ($is_pick): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="order_type" id="order_pickup" value="pickup" <?php echo !$is_del ? 'checked' : ''; ?> onchange="toggleOrderType()">
                                <label class="form-check-label small fw-bold" for="order_pickup"><?php echo __('매장픽업 : 방문시간을 꼭 적어 주세요.'); ?></label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_pick): ?>
                        <div id="pickup_time_wrap" class="mt-2" style="<?php echo $is_del ? 'display: none;' : ''; ?>">
                            <input type="text" id="pickup_time" name="pickup_time" class="form-control form-control-sm bg-light border-0" placeholder="<?php echo __('예: 오후 6시 30분 픽업'); ?>" <?php echo !$is_del ? 'required' : ''; ?>>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-wallet2 me-1"></i> <?php echo __('결제 방식'); ?> *</label>
                        <div class="d-flex gap-4 mb-2 px-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cash" value="cash" checked onchange="togglePaymentDetail()">
                                <label class="form-check-label small fw-bold" for="pay_cash">현금 (Cash)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_other" value="other" onchange="togglePaymentDetail()">
                                <label class="form-check-label small fw-bold" for="pay_other">기타 (GCash, Bank 등)</label>
                            </div>
                        </div>
                        <div id="cash_detail_wrap">
                            <input type="text" id="payment_detail_cash" class="form-control form-control-sm bg-light border-0" placeholder="<?php echo __('예: 1000페소 낼거니, 잔돈 준비해주세요.'); ?>">
                        </div>
                        <div id="other_detail_wrap" class="d-none">
                            <input type="text" id="payment_detail_other" class="form-control form-control-sm bg-light border-0" placeholder="<?php echo __('예: 배달오면 GCash로 결재할께요.'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('현지 핸드폰 번호'); ?> *</label>
                        <div class="input-group" id="phone-input-group">
                            <input type="tel" id="customer_phone" class="form-control form-control-lg" placeholder="+63 917 123 4567" oninput="formatPhoneInput(this)" maxlength="20" required>
                            <?php if ($is_del): ?>
                            <button type="button" class="btn btn-outline-primary fw-bold" id="btn-fetch-address" onclick="fetchLastAddress()"><?php echo __('기존 배달 정보 조회'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="delivery_address_wrap">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark"><i class="bi bi-geo-alt me-1"></i> <?php echo __('배달 주소'); ?> *</label>
                            <textarea id="customer_address" class="form-control" rows="2" placeholder="<?php echo __('상세 주소를 입력해 주세요'); ?>"></textarea>

                            <!-- [추가] 정확한 위치 (지도 핀) 설정 버튼 -->
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill shadow-sm" onclick="openLocationMapModal()">
                                    <i class="bi bi-geo-fill me-1"></i><?php echo __('원활한 배달을 위해, 정확한 지도 위치 선택'); ?>
                                </button>
                                <input type="text" id="customer_coordinates" class="form-control form-control-sm mt-2 bg-light text-primary fw-bold" placeholder="<?php echo __('지도에서 위치를 선택하면 구글 지도 URL이 기록됩니다.'); ?>" readonly style="display: none; cursor: pointer;" onclick="openLocationMapModal()" title="<?php echo __('클릭하면 지도로 위치를 확인/수정할 수 있습니다.'); ?>">
                                <input type="hidden" id="customer_lat" name="customer_lat">
                                <input type="hidden" id="customer_lng" name="customer_lng">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark"><i class="bi bi-flag me-1"></i> <?php echo __('랜드마크'); ?></label>
                            <input type="text" id="customer_landmark" class="form-control" placeholder="<?php echo __('예: 졸리비 근처, 파란색 대문'); ?>">
                        </div>
                    </div>
                    
                    <div id="pickup_notice_wrap" class="mb-4" style="<?php echo $is_del ? 'display: none;' : ''; ?>">
                        <div class="alert alert-info small py-2 m-0 border-0 shadow-sm"><i class="bi bi-info-circle-fill me-1"></i> <?php echo __('매장으로 직접 방문하여 수령해주세요.'); ?><br><strong><?php echo __('매장 주소'); ?>:</strong> <?php echo htmlspecialchars($shop['physical_address'] ?? '미등록'); ?></div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-3 rounded-pill fw-bold shadow" onclick="openOrderConfirmModal()"><?php echo __('주문 완료하기'); ?></button>
                        <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('메뉴 더 담기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 배달 위치 구글 맵 선택 모달 -->
<div class="modal fade" id="locationMapModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-secondary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt-fill me-2"></i><?php echo __('정확한 배달 위치 선택'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <!-- 지도가 렌더링될 영역 -->
                <div id="delivery-map-container" style="width: 100%; height: 60vh; min-height: 350px; background-color: #e9ecef;"></div>

                <!-- 맵 중앙을 가리키는 고정 마커 (지도를 드래그하여 핀에 맞추는 UX) -->
                <div class="position-absolute top-50 start-50 translate-middle" style="pointer-events: none; z-index: 1050;">
                    <i class="bi bi-geo-alt-fill text-danger" style="font-size: 3rem; text-shadow: 0 2px 5px rgba(0,0,0,0.3); transform: translateY(-50%); display: block;"></i>
                </div>

                <!-- 안내 문구 플로팅 -->
                <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3 z-3 w-75 text-center bg-white p-2 rounded-pill shadow-sm border">
                    <span class="small fw-bold text-dark"><i class="bi bi-arrows-move me-1"></i><?php echo __('지도를 움직여 핀을 배달 위치에 맞추세요.'); ?></span>
                </div>

                <!-- [신규] 내 현재 위치로 이동하는 GPS 버튼 -->
                <button type="button" class="btn btn-light rounded-circle shadow border position-absolute d-flex justify-content-center align-items-center" style="bottom: 20px; right: 15px; z-index: 1050; width: 45px; height: 45px;" onclick="moveToCurrentLocation()" title="<?php echo __('내 위치로 이동'); ?>">
                    <i class="bi bi-crosshair fs-5 text-primary"></i>
                </button>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal"><?php echo __('취소'); ?></button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="saveLocationCoordinates()"><?php echo __('위치 저장하기'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 최종 주문 확인 모달 -->
<div class="modal fade" id="orderConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i><?php echo __('최종 주문 확인'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-center fw-bold text-primary mb-3"><?php echo __('입력하신 주문 정보를 마지막으로 확인해주세요.'); ?></p>

                <div class="bg-light p-3 rounded-3 mb-3 border shadow-sm">
                    <h6 class="fw-bold mb-2 text-dark border-bottom pb-2"><?php echo __('주문 내역'); ?></h6>
                    <div id="confirm-items-list" class="small text-secondary mb-2" style="max-height: 200px; overflow-y: auto;"></div>
                    <?php if (($shop['is_delivery_available'] ?? 1) == 1): ?>
                    <div class="d-flex justify-content-between align-items-center small text-dark mt-2 border-top pt-2 mb-1">
                        <span><?php echo __('배달료'); ?></span>
                        <span id="confirm-delivery-fee" class="fw-bold">₱ 0</span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center fw-bold text-dark mt-1 border-top pt-2">
                        <span><?php echo __('총 결제 금액'); ?></span>
                        <span id="confirm-total-price" class="text-primary fs-5">₱ 0</span>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 mb-3 small border shadow-sm">
                    <h6 class="fw-bold mb-2 text-dark border-bottom pb-2"><?php echo __('배달 및 결제 정보'); ?></h6>
                    <div class="row mb-2">
                        <div class="col-4 text-muted"><?php echo __('주문 방식'); ?></div>
                        <div class="col-8 fw-bold text-primary" id="confirm-order-type"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4 text-muted"><?php echo __('결제 방식'); ?></div>
                        <div class="col-8 fw-bold text-dark" id="confirm-payment-method"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4 text-muted"><?php echo __('연락처'); ?></div>
                        <div class="col-8 fw-bold text-dark" id="confirm-customer-phone"></div>
                    </div>
                    <div class="row mb-2" id="confirm-customer-address-row">
                        <div class="col-4 text-muted"><?php echo __('배달 주소'); ?></div>
                        <div class="col-8 fw-bold text-dark" id="confirm-customer-address"></div>
                    </div>
                    <div class="row mb-2" id="confirm-landmark-row">
                        <div class="col-4 text-muted"><?php echo __('랜드마크'); ?></div>
                        <div class="col-8 fw-bold text-dark" id="confirm-customer-landmark"></div>
                    </div>
                    <div class="row" id="confirm-payment-detail-row">
                        <div class="col-4 text-muted"><?php echo __('결제 메모'); ?></div>
                        <div class="col-8 fw-bold text-dark" id="confirm-payment-detail"></div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="button" class="btn btn-primary py-3 rounded-pill bg-danger fw-bold shadow-sm" id="btn-final-submit" onclick="processFinalOrder()">
                        <i class="bi bi-check-circle me-1"></i> <?php echo __('주문 확정'); ?>
                    </button>
                    <button type="button" class="btn btn-light py-3 rounded-pill fw-bold shadow-sm border" data-bs-dismiss="modal">
                        <i class="bi bi-pencil me-1"></i> <?php echo __('주문 수정'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 무료 오픈소스 지도 라이브러리 Leaflet.js 로드 -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    /* 플로팅 버튼 둥둥 떠 있는 애니메이션 */
    @keyframes floatUpDown {
        0% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-8px);
        }

        100% {
            transform: translateY(0);
        }
    }

    .floating-anim {
        animation: floatUpDown 2s ease-in-out infinite;
    }
</style>

<!-- [신규] 나의 주문 상태 플로팅 버튼 및 창 -->
<!--  "pending, cooking, delivery" 상태의 주문이 있을 때만 버튼이 보이도록, JS에서 제어합니다. -->
<button id="floating-order-status-btn" class="btn btn-warning rounded-circle shadow-lg position-fixed d-none align-items-center justify-content-center floating-anim" style="top: 80px; left: 15px; width: 55px; height: 55px; z-index: 1040;" onclick="toggleOrderStatusPanel()">
    <i id="floating-order-status-icon" class="bi bi-scooter fs-3 text-dark"></i>
    <span class="position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle" id="floating-order-status-badge" style="display:none;">
        <span class="visually-hidden">새 알림</span>
    </span>
</button>

<div id="floating-order-status-panel" class="position-fixed bg-white shadow-lg rounded-4 d-none" style="top: 145px; left: 15px; width: 350px; z-index: 1045; border: 1px solid #ddd; max-height: calc(100vh - 160px); overflow-y: auto;">
    <div class="p-3 border-bottom bg-light rounded-top-4 d-flex justify-content-between align-items-center sticky-top" style="z-index: 1;">
        <h6 class="m-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i><?php echo __('나의 주문 상태'); ?></h6>
        <button type="button" class="btn-close" onclick="toggleOrderStatusPanel()"></button>
    </div>
    <div class="p-3 bg-light" id="floating-order-status-content" style="min-height: 150px;">
        <div class="text-center py-4 text-muted small">
            <div class="spinner-border spinner-border-sm text-primary mb-2"></div><br>
            주문 상태를 불러오는 중입니다...
        </div>
    </div>
</div>

<!-- [수정] 주문 내역 조회 모달 (비회원 조회 기능 통합) -->
<div class="modal fade" id="orderHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i><?php echo __('주문 내역 조회'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 비회원용 전화번호 입력 폼 (비로그인 시에만 노출) -->
                <div id="non-member-history-form" class="mb-4" style="display: none;">
                    <p class="mb-3 text-muted small"><?php echo __('주문 시 사용하신 필리핀 휴대폰 번호를 입력 후 조회해주세요.'); ?></p>
                    <div class="input-group shadow-sm rounded-pill">
                        <input type="tel" class="form-control form-control-lg border-0 bg-light" id="history_search_phone" placeholder="09XX-XXX-XXXX" oninput="formatPhoneInput && formatPhoneInput(this)" style="border-top-left-radius: 50rem; border-bottom-left-radius: 50rem;">
                        <button class="btn btn-primary fw-bold" type="button" onclick="saveGuestPhone(); searchOrderHistory();" style="border-top-right-radius: 50rem; border-bottom-right-radius: 50rem;"><?php echo __('조회하기'); ?></button>
                    </div>
                </div>

                <!-- 회원용 전화번호 표시 (로그인 시에만 노출) -->
                <div id="member-history-info" class="mb-3" style="display: none;">
                    <div class="alert alert-light border border-dark border-opacity-10 shadow-sm text-center rounded-4">
                        <p class="small text-muted mb-1"><?php echo __('조회 기준 연락처'); ?></p>
                        <h5 class="fw-bold text-dark m-0" id="history-phone-display"><i class="bi bi-telephone text-primary me-2"></i></h5>
                    </div>
                </div>

                <!-- 주문 내역 결과 표시 영역 -->
                <div id="history-results" style="max-height: 400px; overflow-y: auto;">
                    <!-- JS에 의해 내용이 채워짐 -->
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light w-100 fw-bold rounded-pill" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePaymentDetail() {
        const isCash = document.getElementById('pay_cash').checked;
        if (isCash) {
            document.getElementById('cash_detail_wrap').classList.remove('d-none');
            document.getElementById('other_detail_wrap').classList.add('d-none');
        } else {
            document.getElementById('cash_detail_wrap').classList.add('d-none');
            document.getElementById('other_detail_wrap').classList.remove('d-none');
        }
    }

    /**
     * [UX 개선] 비회원 주문 조회 시 사용한 전화번호를 로컬 스토리지에 저장하여 다음번 조회 시 자동 완성
     */
    function saveGuestPhone() {
        const phoneInput = document.getElementById('history_search_phone');
        if (phoneInput && phoneInput.value.trim() !== '') {
            const phoneVal = phoneInput.value.trim();
            // 1. 브라우저 로컬 스토리지에 저장 (브라우저를 껐다 켜도 유지됨, 쿠키와 동일한 효과)
            localStorage.setItem('ps24_guest_phone', phoneVal);

            // 2. 현재 페이지의 전역 환경 설정(FNB_CONFIG)에도 세팅하여 주문 폼 등에서 바로 인식되도록 함
            if (typeof FNB_CONFIG !== 'undefined' && !FNB_CONFIG.customerPhone) {
                FNB_CONFIG.customerPhone = phoneVal;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const historyModal = document.getElementById('orderHistoryModal');
        if (historyModal) {
            // 주문 내역 모달이 열릴 때 이벤트 리스너
            historyModal.addEventListener('show.bs.modal', function() {
                const phoneInput = document.getElementById('history_search_phone');
                // 필드가 비어있을 때만 기존에 저장된 전화번호를 불러옴
                if (phoneInput && !phoneInput.value.trim()) {
                    const savedPhone = localStorage.getItem('ps24_guest_phone');
                    if (savedPhone) {
                        phoneInput.value = savedPhone;
                    }
                }
            });
        }

        // [추가] 주문서 작성(결제) 모달이 열릴 때도 비회원이 저장했던 전화번호를 자동으로 채워줌
        const cartModal = document.getElementById('cartModal');
        if (cartModal) {
            cartModal.addEventListener('show.bs.modal', function() {
                toggleOrderType();
                const phoneInput = document.getElementById('customer_phone');
                const addrInput = document.getElementById('customer_address');
                const landmarkInput = document.getElementById('customer_landmark');
                const coordInput = document.getElementById('customer_coordinates');

                if (phoneInput && !phoneInput.value.trim()) {
                    const savedPhone = localStorage.getItem('ps24_guest_phone') || (typeof FNB_CONFIG !== 'undefined' ? FNB_CONFIG.customerPhone : '');
                    if (savedPhone) phoneInput.value = savedPhone;
                }
                if (addrInput && !addrInput.value.trim()) {
                    const savedAddr = localStorage.getItem('ps24_guest_address') || (typeof FNB_CONFIG !== 'undefined' ? FNB_CONFIG.customerAddress : '');
                    if (savedAddr) addrInput.value = savedAddr;
                }
                if (landmarkInput && !landmarkInput.value.trim()) {
                    const savedLandmark = localStorage.getItem('ps24_guest_landmark') || (typeof FNB_CONFIG !== 'undefined' ? FNB_CONFIG.customerLandmark : '');
                    if (savedLandmark) landmarkInput.value = savedLandmark;
                }
                if (coordInput && !coordInput.value.trim()) {
                    const savedCoord = localStorage.getItem('ps24_guest_coordinates') || (typeof FNB_CONFIG !== 'undefined' ? FNB_CONFIG.customerCoordinates : '');
                    if (savedCoord) {
                        coordInput.value = savedCoord;
                        coordInput.style.display = 'block';

                        // [추가] 저장된 URL에서 위/경도를 추출해 숨김 필드와 맵 중앙 좌표에 자동 채움
                        const match = savedCoord.match(/q=(-?\d+\.\d+),(-?\d+\.\d+)/);
                        if (match) {
                            document.getElementById('customer_lat').value = match[1];
                            document.getElementById('customer_lng').value = match[2];
                            if (typeof currentCoordinates !== 'undefined') {
                                currentCoordinates = {
                                    lat: parseFloat(match[1]),
                                    lng: parseFloat(match[2])
                                };
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<!-- [신규] 주문 상태 변경 (요리/배달) 알림 팝업 모달 -->
<div class="modal fade" id="orderStatusAlertModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4 pt-5">
                <div id="orderStatusAlertIcon" class="mb-3">
                    <!-- JS에서 아이콘 주입 -->
                </div>
                <h5 class="fw-bold mb-2" id="orderStatusAlertTitle">상태 변경</h5>
                <p class="text-muted small mb-4" id="orderStatusAlertDesc">주문 상태가 변경되었습니다.</p>
                <button type="button" class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm py-2" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- [주문 내역 삭제 확인 팝업 모달] -->
<div class="modal fade" id="deleteOrderConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
                <h6 class="fw-bold mb-1" id="deleteOrderConfirmTitle">정말로 삭제하시겠습니까?</h6>
                <p class="small text-muted mb-4" id="deleteOrderConfirmDesc">목록에서 완전히 지워집니다.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4 fw-bold rounded-pill border shadow-sm" data-bs-dismiss="modal"><?php echo __('취소'); ?></button>
                    <button type="button" class="btn btn-danger px-4 fw-bold rounded-pill shadow-sm" id="deleteOrderConfirmBtn" onclick="executeDeleteOrder()">삭제</button>
                </div>
            </div>
        </div>
    </div>
</div>