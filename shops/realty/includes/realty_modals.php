<<<<<<< HEAD
<?php

/**
 * [컴포넌트] 부동산 전용 모달 (realty_modals.php)
 * 부동산 뷰어에서 사용되는 모든 팝업(매물 상세, 관심목록, 문의내역 등)을 보관합니다.
 * 주의: JavaScript 연동을 위해 태그의 id나 JS 호출 함수명은 FNB와 동일하게 유지합니다.
 */
?>
<!-- [수정] 매물 상세 정보 모달: 이미지/영상, 상세정보, 찜하기, Q&A 기능 통합 -->
<div class="modal fade" id="menuDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-building me-2"></i> <?php echo __('매물 상세 정보'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <!-- 1. 매물 사진/영상 슬라이더 -->
                <div class="px-4 pt-4 pb-0 bg-light">
                    <!-- [수정] 사진/동영상 탭 버튼 추가 -->
                    <ul class="nav nav-pills mb-3 gap-2" id="media-tab" role="tablist">
                        <li class="nav-item" role="presentation" id="photo-tab-item">
                            <button class="nav-link active fw-bold px-3 py-1" id="photo-tab" data-bs-toggle="pill" data-bs-target="#photo-pane" type="button" role="tab"><i class="bi bi-images me-1"></i><?php echo __('사진 정보'); ?></button>
                        </li>
                        <li class="nav-item" role="presentation" id="video-tab-item">
                            <button class="nav-link fw-bold px-3 py-1" id="video-tab" data-bs-toggle="pill" data-bs-target="#video-pane" type="button" role="tab"><i class="bi bi-youtube me-1"></i><?php echo __('동영상 정보'); ?></button>
                        </li>
                    </ul>

                    <div class="tab-content" id="media-tabContent">
                        <!-- 사진 탭 내용 -->
                        <div class="tab-pane fade show active" id="photo-pane" role="tabpanel">
                            <div id="menu-detail-photo" class="detail-media-container position-relative rounded overflow-hidden shadow-sm"></div>
                            <div id="photo-guide-text" class="text-center text-muted small py-2 d-none"><i class="bi bi-arrow-left-right me-1"></i> <?php echo __('사진을 좌우로 밀어 볼 수 있습니다.'); ?></div>
                        </div>
                        <!-- 동영상 탭 내용 -->
                        <div class="tab-pane fade" id="video-pane" role="tabpanel">
                            <div id="menu-detail-video" class="detail-media-container position-relative rounded overflow-hidden shadow-sm"></div>
                            <div id="video-guide-text" class="text-center text-muted small py-2 d-none"><i class="bi bi-arrow-left-right me-1"></i> <?php echo __('동영상을 좌우로 밀어 볼 수 있습니다.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="p-4 pt-3">
                    <!-- 2. 매물 기본 정보 및 관심물건 등록 버튼 -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div id="detail-badges" class="mb-2"></div>
                            <h4 class="fw-bold mb-1" id="detail-menu-name"><?php echo __('매물명'); ?></h4>
                        </div>

                        <!-- [수정] 관심물건 등록/해제 버튼: 하트 아이콘 보여주도록 변경 -->
                        <button type="button" class="btn btn-outline-danger btn-lg rounded-pill px-3 shadow-sm flex-shrink-0 d-inline-flex align-items-center gap-1" id="btn-wishlist" onclick="toggleWishlist(this)">
                            <i class="bi bi-heart"></i>
                            <span id="detail-wish-count" class="fw-bold ms-1 d-none" style="font-size: 0.95rem;">0</span>
                        </button>
                    </div>
                    <div class="price-area mb-4 p-3 bg-light rounded-3">
                        <span class="fs-4 fw-bold text-primary" id="detail-final-price">₱ 0</span>
                        <span class="text-muted text-decoration-line-through small ms-2 d-none" id="detail-original-price">₱ 0</span>
                    </div>

                    <!-- 3. 매물 상세 설명 -->
                    <div class="mb-5">
                        <h6 class="fw-bold mb-3"><i class="bi bi-card-text me-2"></i><?php echo __('상세 정보'); ?></h6>
                        <p class="text-secondary" id="detail-menu-info" style="line-height: 1.7; white-space: pre-wrap;"><?php echo __('상세 설명이 들어갑니다.'); ?></p>
                    </div>

                    <!-- 4. 단일 매물 즉시 문의 -->
                    <div class="inquiry-section">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill me-2"></i><?php echo __('이 매물 문의하기'); ?></h6>
                        <form id="singleInquiryForm" class="mb-4 bg-white p-3 rounded-4 border shadow-sm">
                            <input type="hidden" id="single_inquiry_item_data" value="">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락 가능한 핸드폰 번호 *'); ?></label>
                                <input type="tel" id="single_customer_phone" class="form-control" placeholder="<?php echo htmlspecialchars(__('09XX-XXX-XXXX')); ?>" oninput="formatPhoneInput(this)" maxlength="13" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark"><i class="bi bi-chat-text me-1"></i> <?php echo __('상세 문의/요청 사항 *'); ?></label>
                                <textarea id="single_customer_inquiry" class="form-control" rows="3" placeholder="<?php echo htmlspecialchars(__('이 매물에 대해 통화 가능한 시간, 원하시는 조건 등 궁금한 점을 적어주세요.')); ?>" required><?php echo __('해당 물건에 대한 상담을 원하니, 위의 전화번호로 연락 주세요.'); ?></textarea>
                            </div>
                            <button class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow-sm" type="button" onclick="submitSingleInquiry()"><?php echo __('문의 접수 완료하기'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100 py-3 rounded-pill fw-bold" data-bs-dismiss="modal">
                    <?php echo __('닫기'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // [버그 수정] 매물 상세 모달이 닫힐 때 유튜브 동영상 백그라운드 재생을 확실히 중지
    document.addEventListener('DOMContentLoaded', function() {
        const detailModal = document.getElementById('menuDetailModal');
        if (detailModal) {
            detailModal.addEventListener('hidden.bs.modal', function() {
                // iframe을 포함한 컨테이너 내용을 완전히 비워서 재생 중인 미디어 삭제
                const videoContainer = document.getElementById('menu-detail-video');
                const photoContainer = document.getElementById('menu-detail-photo');
                if (videoContainer) videoContainer.innerHTML = '';
                if (photoContainer) photoContainer.innerHTML = '';
            });
        }
    });
</script>

<!-- [관심목록 모달]: 현재 담긴 모든 항목을 리스트로 보여주고 수정 및 삭제를 지원합니다. -->
<div class="modal fade" id="cartViewModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-bookmark-heart-fill me-2"></i><?php echo __('관심 매물 목록'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cart-view-items-list" class="mb-4" style="max-height: 350px; overflow-y: auto;"></div>

                <!-- 부동산 전용 안내 문구 (무료 배달비 안내 대체) -->
                <div class="alert alert-secondary small text-center py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php echo __('정확한 중개 수수료 및 거래 조건은 개별 상담 시 안내해 드립니다.'); ?>
                </div>
                <div id="free-delivery-notice" class="d-none"></div>
                <form id="orderForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락 가능한 핸드폰 번호 *'); ?></label>
                        <input type="tel" id="customer_phone" class="form-control form-control-lg" placeholder="<?php echo htmlspecialchars(__('09XX-XXX-XXXX')); ?>" oninput="formatPhoneInput(this)" maxlength="13" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-chat-text me-1"></i> <?php echo __('상세 문의/요청 사항 *'); ?></label>
                        <textarea id="customer_inquiry" class="form-control" rows="3" placeholder="<?php echo htmlspecialchars(__('예: 통화 가능한 시간, 원하시는 지역, 예산, 입주 희망일 등 상세한 내용을 적어주세요.')); ?>" required><?php echo __('해당 물건에 대한 상담을 원하니, 위의 전화번호로 연락 주세요.'); ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-3 rounded-pill fw-bold shadow" onclick="submitOrder()"><?php echo __('문의 접수 완료하기'); ?></button>
                        <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('매물 더 찾아보기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- [문의 내역 조회 모달]: 전화번호 기반으로 최근 문의 내역을 AJAX로 불러와 표시합니다. -->
<div class="modal fade" id="realtyOrderHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center"><i class="bi bi-clock-history me-2"></i> <?php echo __('나의 문의 내역'); ?> (<span id="modal-inquiry-count-badge"><?php echo $my_inquiry_count ?? 0; ?></span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- [추가] 비회원 조회 폼 -->
                <form id="realty-non-member-history-form" style="display: none;" class="mb-4" onsubmit="event.preventDefault(); submitRealtyHistorySearch();">
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락처 번호 (09...)'); ?></label>
                    <div class="input-group">
                        <input type="tel" id="realty_history_search_phone" class="form-control" placeholder="<?php echo htmlspecialchars(__('조회할 전화번호 입력')); ?>" oninput="formatPhoneInput(this)" required>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><?php echo __('조회'); ?></button>
                    </div>
                </form>

                <!-- [수정] 회원 정보 표시 영역에 ID 부여 -->
                <div id="realty-member-history-info" class="alert alert-light border border-dark border-opacity-10 mb-4 shadow-sm text-center rounded-4">
                    <p class="small text-muted mb-1"><?php echo __('조회 기준 연락처'); ?></p>
                    <h5 class="fw-bold text-dark m-0" id="realty-history-phone-display"><i class="bi bi-telephone text-primary me-2"></i></h5>
                </div>
                <div id="realty-history-results" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 small"><?php echo __('내역을 불러오는 중입니다...'); ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light w-100 fw-bold rounded-pill" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- [문의 내역 삭제 확인 팝업 모달] -->
<div class="modal fade" id="deleteOrderConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
                <h6 class="fw-bold mb-1" id="deleteOrderConfirmTitle"><?php echo __('정말로 삭제하시겠습니까?'); ?></h6>
                <p class="small text-muted mb-4" id="deleteOrderConfirmDesc"><?php echo __('목록에서 완전히 지워집니다.'); ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4 fw-bold rounded-pill border shadow-sm" data-bs-dismiss="modal"><?php echo __('취소'); ?></button>
                    <button type="button" class="btn btn-danger px-4 fw-bold rounded-pill shadow-sm" id="deleteOrderConfirmBtn" onclick="executeDeleteInquiry()"><?php echo __('삭제'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [상담 취소 확인 팝업 모달] -->
<div class="modal fade" id="cancelInquiryConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-question-circle text-warning mb-3 d-block" style="font-size: 3rem;"></i>
                <h6 class="fw-bold mb-1" id="cancelInquiryConfirmTitle"><?php echo __('상담을 취소하시겠습니까?'); ?></h6>
                <p class="small text-muted mb-4" id="cancelInquiryConfirmDesc"><?php echo __('취소하시면 상담원이 연락을 드리지 않습니다.'); ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4 fw-bold rounded-pill border shadow-sm" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
                    <button type="button" class="btn btn-warning text-dark px-4 fw-bold rounded-pill shadow-sm" id="cancelInquiryConfirmBtn" onclick="executeCancelInquiry()"><?php echo __('상담 취소'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [모달] 문의 접수 완료 알림 모달 -->
<div class="modal fade" id="inquirySuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-check-circle-fill text-success mb-3 d-block" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mb-2"><?php echo __('고객 문의가 다음 전화번호로 등록되었습니다.'); ?></h5>
                <div class="fs-4 fw-bold text-primary mb-3" id="success_registered_phone"></div>
                <p class="small text-muted mb-4"><?php echo __('담당자가 확인 후 곧 연락드리겠습니다.'); ?></p>
                <button type="button" class="btn btn-success w-100 fw-bold rounded-pill py-2 shadow-sm" data-bs-dismiss="modal"><?php echo __('확인'); ?></button>
            </div>
        </div>
    </div>
=======
<?php

/**
 * [컴포넌트] 부동산 전용 모달 (realty_modals.php)
 * 부동산 뷰어에서 사용되는 모든 팝업(매물 상세, 관심목록, 문의내역 등)을 보관합니다.
 * 주의: JavaScript 연동을 위해 태그의 id나 JS 호출 함수명은 FNB와 동일하게 유지합니다.
 */
?>
<!-- [수정] 매물 상세 정보 모달: 이미지/영상, 상세정보, 찜하기, Q&A 기능 통합 -->
<div class="modal fade" id="menuDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0 bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-building me-2"></i> <?php echo __('매물 상세 정보'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <!-- 1. 매물 사진/영상 슬라이더 -->
                <div class="px-4 pt-4 pb-0 bg-light">
                    <!-- [수정] 사진/동영상 탭 버튼 추가 -->
                    <ul class="nav nav-pills mb-3 gap-2" id="media-tab" role="tablist">
                        <li class="nav-item" role="presentation" id="photo-tab-item">
                            <button class="nav-link active fw-bold px-3 py-1" id="photo-tab" data-bs-toggle="pill" data-bs-target="#photo-pane" type="button" role="tab"><i class="bi bi-images me-1"></i><?php echo __('사진 정보'); ?></button>
                        </li>
                        <li class="nav-item" role="presentation" id="video-tab-item">
                            <button class="nav-link fw-bold px-3 py-1" id="video-tab" data-bs-toggle="pill" data-bs-target="#video-pane" type="button" role="tab"><i class="bi bi-youtube me-1"></i><?php echo __('동영상 정보'); ?></button>
                        </li>
                    </ul>

                    <div class="tab-content" id="media-tabContent">
                        <!-- 사진 탭 내용 -->
                        <div class="tab-pane fade show active" id="photo-pane" role="tabpanel">
                            <div id="menu-detail-photo" class="detail-media-container position-relative rounded overflow-hidden shadow-sm"></div>
                            <div id="photo-guide-text" class="text-center text-muted small py-2 d-none"><i class="bi bi-arrow-left-right me-1"></i> <?php echo __('사진을 좌우로 밀어 볼 수 있습니다.'); ?></div>
                        </div>
                        <!-- 동영상 탭 내용 -->
                        <div class="tab-pane fade" id="video-pane" role="tabpanel">
                            <div id="menu-detail-video" class="detail-media-container position-relative rounded overflow-hidden shadow-sm"></div>
                            <div id="video-guide-text" class="text-center text-muted small py-2 d-none"><i class="bi bi-arrow-left-right me-1"></i> <?php echo __('동영상을 좌우로 밀어 볼 수 있습니다.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="p-4 pt-3">
                    <!-- 2. 매물 기본 정보 및 관심물건 등록 버튼 -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div id="detail-badges" class="mb-2"></div>
                            <h4 class="fw-bold mb-1" id="detail-menu-name"><?php echo __('매물명'); ?></h4>
                        </div>

                        <!-- [수정] 관심물건 등록/해제 버튼: 하트 아이콘 보여주도록 변경 -->
                        <button type="button" class="btn btn-outline-danger btn-lg rounded-pill px-3 shadow-sm flex-shrink-0 d-inline-flex align-items-center gap-1" id="btn-wishlist" onclick="toggleWishlist(this)">
                            <i class="bi bi-heart"></i>
                            <span id="detail-wish-count" class="fw-bold ms-1 d-none" style="font-size: 0.95rem;">0</span>
                        </button>
                    </div>
                    <div class="price-area mb-4 p-3 bg-light rounded-3">
                        <span class="fs-4 fw-bold text-primary" id="detail-final-price">₱ 0</span>
                        <span class="text-muted text-decoration-line-through small ms-2 d-none" id="detail-original-price">₱ 0</span>
                    </div>

                    <!-- 3. 매물 상세 설명 -->
                    <div class="mb-5">
                        <h6 class="fw-bold mb-3"><i class="bi bi-card-text me-2"></i><?php echo __('상세 정보'); ?></h6>
                        <p class="text-secondary" id="detail-menu-info" style="line-height: 1.7; white-space: pre-wrap;"><?php echo __('상세 설명이 들어갑니다.'); ?></p>
                    </div>

                    <!-- 4. 단일 매물 즉시 문의 -->
                    <div class="inquiry-section">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill me-2"></i><?php echo __('이 매물 문의하기'); ?></h6>
                        <form id="singleInquiryForm" class="mb-4 bg-white p-3 rounded-4 border shadow-sm">
                            <input type="hidden" id="single_inquiry_item_data" value="">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락 가능한 핸드폰 번호 *'); ?></label>
                                <input type="tel" id="single_customer_phone" class="form-control" placeholder="<?php echo htmlspecialchars(__('09XX-XXX-XXXX')); ?>" oninput="formatPhoneInput(this)" maxlength="13" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark"><i class="bi bi-chat-text me-1"></i> <?php echo __('상세 문의/요청 사항 *'); ?></label>
                                <textarea id="single_customer_inquiry" class="form-control" rows="3" placeholder="<?php echo htmlspecialchars(__('이 매물에 대해 통화 가능한 시간, 원하시는 조건 등 궁금한 점을 적어주세요.')); ?>" required><?php echo __('해당 물건에 대한 상담을 원하니, 위의 전화번호로 연락 주세요.'); ?></textarea>
                            </div>
                            <button class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow-sm" type="button" onclick="submitSingleInquiry()"><?php echo __('문의 접수 완료하기'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100 py-3 rounded-pill fw-bold" data-bs-dismiss="modal">
                    <?php echo __('닫기'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // [버그 수정] 매물 상세 모달이 닫힐 때 유튜브 동영상 백그라운드 재생을 확실히 중지
    document.addEventListener('DOMContentLoaded', function() {
        const detailModal = document.getElementById('menuDetailModal');
        if (detailModal) {
            detailModal.addEventListener('hidden.bs.modal', function() {
                // iframe을 포함한 컨테이너 내용을 완전히 비워서 재생 중인 미디어 삭제
                const videoContainer = document.getElementById('menu-detail-video');
                const photoContainer = document.getElementById('menu-detail-photo');
                if (videoContainer) videoContainer.innerHTML = '';
                if (photoContainer) photoContainer.innerHTML = '';
            });
        }
    });
</script>

<!-- [관심목록 모달]: 현재 담긴 모든 항목을 리스트로 보여주고 수정 및 삭제를 지원합니다. -->
<div class="modal fade" id="cartViewModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-bookmark-heart-fill me-2"></i><?php echo __('관심 매물 목록'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cart-view-items-list" class="mb-4" style="max-height: 350px; overflow-y: auto;"></div>

                <!-- 부동산 전용 안내 문구 (무료 배달비 안내 대체) -->
                <div class="alert alert-secondary small text-center py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php echo __('정확한 중개 수수료 및 거래 조건은 개별 상담 시 안내해 드립니다.'); ?>
                </div>
                <div id="free-delivery-notice" class="d-none"></div>
                <form id="orderForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락 가능한 핸드폰 번호 *'); ?></label>
                        <input type="tel" id="customer_phone" class="form-control form-control-lg" placeholder="<?php echo htmlspecialchars(__('09XX-XXX-XXXX')); ?>" oninput="formatPhoneInput(this)" maxlength="13" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark"><i class="bi bi-chat-text me-1"></i> <?php echo __('상세 문의/요청 사항 *'); ?></label>
                        <textarea id="customer_inquiry" class="form-control" rows="3" placeholder="<?php echo htmlspecialchars(__('예: 통화 가능한 시간, 원하시는 지역, 예산, 입주 희망일 등 상세한 내용을 적어주세요.')); ?>" required><?php echo __('해당 물건에 대한 상담을 원하니, 위의 전화번호로 연락 주세요.'); ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-3 rounded-pill fw-bold shadow" onclick="submitOrder()"><?php echo __('문의 접수 완료하기'); ?></button>
                        <button type="button" class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal"><?php echo __('매물 더 찾아보기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- [문의 내역 조회 모달]: 전화번호 기반으로 최근 문의 내역을 AJAX로 불러와 표시합니다. -->
<div class="modal fade" id="realtyOrderHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center"><i class="bi bi-clock-history me-2"></i> <?php echo __('나의 문의 내역'); ?> (<span id="modal-inquiry-count-badge"><?php echo $my_inquiry_count ?? 0; ?></span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- [추가] 비회원 조회 폼 -->
                <form id="realty-non-member-history-form" style="display: none;" class="mb-4" onsubmit="event.preventDefault(); submitRealtyHistorySearch();">
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-telephone me-1"></i> <?php echo __('연락처 번호 (09...)'); ?></label>
                    <div class="input-group">
                        <input type="tel" id="realty_history_search_phone" class="form-control" placeholder="<?php echo htmlspecialchars(__('조회할 전화번호 입력')); ?>" oninput="formatPhoneInput(this)" required>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><?php echo __('조회'); ?></button>
                    </div>
                </form>

                <!-- [수정] 회원 정보 표시 영역에 ID 부여 -->
                <div id="realty-member-history-info" class="alert alert-light border border-dark border-opacity-10 mb-4 shadow-sm text-center rounded-4">
                    <p class="small text-muted mb-1"><?php echo __('조회 기준 연락처'); ?></p>
                    <h5 class="fw-bold text-dark m-0" id="realty-history-phone-display"><i class="bi bi-telephone text-primary me-2"></i></h5>
                </div>
                <div id="realty-history-results" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 small"><?php echo __('내역을 불러오는 중입니다...'); ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light w-100 fw-bold rounded-pill" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- [문의 내역 삭제 확인 팝업 모달] -->
<div class="modal fade" id="deleteOrderConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
                <h6 class="fw-bold mb-1" id="deleteOrderConfirmTitle"><?php echo __('정말로 삭제하시겠습니까?'); ?></h6>
                <p class="small text-muted mb-4" id="deleteOrderConfirmDesc"><?php echo __('목록에서 완전히 지워집니다.'); ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4 fw-bold rounded-pill border shadow-sm" data-bs-dismiss="modal"><?php echo __('취소'); ?></button>
                    <button type="button" class="btn btn-danger px-4 fw-bold rounded-pill shadow-sm" id="deleteOrderConfirmBtn" onclick="executeDeleteInquiry()"><?php echo __('삭제'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [상담 취소 확인 팝업 모달] -->
<div class="modal fade" id="cancelInquiryConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-question-circle text-warning mb-3 d-block" style="font-size: 3rem;"></i>
                <h6 class="fw-bold mb-1" id="cancelInquiryConfirmTitle"><?php echo __('상담을 취소하시겠습니까?'); ?></h6>
                <p class="small text-muted mb-4" id="cancelInquiryConfirmDesc"><?php echo __('취소하시면 상담원이 연락을 드리지 않습니다.'); ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4 fw-bold rounded-pill border shadow-sm" data-bs-dismiss="modal"><?php echo __('닫기'); ?></button>
                    <button type="button" class="btn btn-warning text-dark px-4 fw-bold rounded-pill shadow-sm" id="cancelInquiryConfirmBtn" onclick="executeCancelInquiry()"><?php echo __('상담 취소'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [모달] 문의 접수 완료 알림 모달 -->
<div class="modal fade" id="inquirySuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 2070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center p-4">
                <i class="bi bi-check-circle-fill text-success mb-3 d-block" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mb-2"><?php echo __('고객 문의가 다음 전화번호로 등록되었습니다.'); ?></h5>
                <div class="fs-4 fw-bold text-primary mb-3" id="success_registered_phone"></div>
                <p class="small text-muted mb-4"><?php echo __('담당자가 확인 후 곧 연락드리겠습니다.'); ?></p>
                <button type="button" class="btn btn-success w-100 fw-bold rounded-pill py-2 shadow-sm" data-bs-dismiss="modal"><?php echo __('확인'); ?></button>
            </div>
        </div>
    </div>
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</div>