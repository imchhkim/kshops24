<<<<<<< HEAD
<?php if (($shop['is_show_review'] ?? 1) == 1):
    $current_lang = $global_current_lang ?? $_SESSION['shop_lang'] ?? 'ko';
    
    // [다국어 처리 수정] 점주가 직접 커스텀 라벨을 입력했는지 우선 확인
    $disp_label_review = $ui['label_review'] ?? '';
    if ($current_lang !== 'ko' && isset($ui["label_review_{$current_lang}"]) && trim($ui["label_review_{$current_lang}"]) !== '') {
        $disp_label_review = $ui["label_review_{$current_lang}"];
    }
    // 커스텀 라벨이 비어있다면 다국어 번역 함수를 태운 기본 라벨 적용
    if (empty(trim($disp_label_review))) {
        $disp_label_review = __('고객 리뷰');
    }
?>
    <section class="container mt-5 mb-5 pb-3 scroll-nav-target" id="shop-review-section" data-nav-label="<?php echo htmlspecialchars($disp_label_review); ?>">
        <div class="section-title text-center mb-4">
            <h2><?php echo htmlspecialchars($disp_label_review); ?> <span class="badge bg-primary rounded-pill align-text-bottom fs-6"><?php echo $total_reviews; ?></span></h2>
        </div>

        <div class="row mb-4">
            <div class="col-12 text-center">
                <div class="display-4 fw-bold text-dark mb-2"><span id="reviewStatsAvg"><?php echo number_format($avg_rating, 1); ?></span> <i class="bi bi-star-fill text-warning fs-3"></i></div>
                <div class="mb-3 text-muted small"><?php echo __('소중한 리뷰 갯수 :').' '; ?><span id="reviewStatsTotal"><?php echo number_format($total_reviews); ?></span></div>

                <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openReviewWriteModal()">
                    <i class="bi bi-pencil-square me-1"></i> <?php echo __('리뷰 작성하기'); ?>
                </button>
            </div>
        </div>

        <div class="row g-3 justify-content-center" id="recentReviewsContainer">
            <?php if (!empty($recent_reviews)): ?>
                <?php foreach ($recent_reviews as $rev): ?>
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm rounded-4 mb-1" style="background-color: #fcfcfc;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo htmlspecialchars($rev['profile_img'] ?: '/assets/no-logo.png'); ?>" class="rounded-circle shadow-sm" style="width: 28px; height: 28px; object-fit: cover; border: 1px solid #eee;">
                                        <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($rev['customer_name'] ?: '고객'); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="text-muted" style="font-size: 0.7rem;"><?php echo substr($rev['created_at'], 0, 10); ?></div>
                                        <?php if ($is_customer_logged_in && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == $rev['customer_id']): ?>
                                            <?php $encodedContent = rawurlencode($rev['content']); ?>
                                            <button type="button" class="btn btn-link text-primary p-0 ms-2" onclick="openReviewEditModal(<?php echo $rev['id']; ?>, <?php echo $rev['rating']; ?>, decodeURIComponent('<?php echo $encodedContent; ?>'))" style="font-size: 0.8rem;" title="<?php echo __('리뷰 수정'); ?>"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="deleteReview(<?php echo $rev['id']; ?>)" style="font-size: 0.8rem;" title="<?php echo __('리뷰 삭제'); ?>"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-1 text-warning" style="font-size: 0.75rem;">
                                    <?php for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rev['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                    } ?>
                                </div>
                                <p class="mb-0 text-dark" style="line-height: 1.4; font-size: 0.85rem;"><?php echo nl2br(htmlspecialchars($rev['content'])); ?></p>

                                <!-- 사장님 답변 영역 -->
                                <?php if (!empty($rev['owner_reply'])): ?>
                                    <div class="mt-2 bg-light p-2 rounded-3 position-relative">
                                        <i class="bi bi-arrow-return-right position-absolute top-0 start-0 ms-2 mt-2 text-primary opacity-50"></i>
                                        <div class="ps-3">
                                            <span class="fw-bold text-primary d-block mb-0" style="font-size: 0.8rem;"><?php echo __('사장님 답변'); ?></span>
                                            <p class="mb-0 text-secondary" style="line-height: 1.4; font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($rev['owner_reply'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_reviews > 5): ?>
                    <div class="col-md-8 text-center mt-3">
                        <button type="button" class="btn btn-light border rounded-pill px-4 py-2 w-100 fw-bold text-secondary shadow-sm" onclick="openReviewListModal()">
                            <?php echo __('모든 리뷰 보기'); ?> <i class="bi bi-chevron-right ms-1"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-light rounded-4 border border-light">
                    <i class="bi bi-chat-left-dots fs-1 text-muted mb-3 d-block opacity-50"></i>
                    <p class="text-muted mb-0 fw-bold"><?php echo __('아직 작성된 리뷰가 없습니다.'); ?></p>
                    <p class="text-muted small mt-1"><?php echo __('첫 번째 리뷰를 남겨주세요!'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // =====================================
        // [고객 리뷰 시스템 통합 엔진 로직]
        // =====================================
        const isCustomerLoggedIn = <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>;
        const customerId = <?php echo $_SESSION['customer_id'] ?? 'null'; ?>;

        // 별점 클릭 이벤트 리스너 바인딩
        document.querySelectorAll('.rating-stars i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                document.getElementById('review_rating').value = rating;

                document.querySelectorAll('.rating-stars i').forEach(s => {
                    if (parseInt(s.getAttribute('data-rating')) <= rating) {
                        s.className = 'bi bi-star-fill';
                    } else {
                        s.className = 'bi bi-star';
                    }
                });
            });
        });

        // [리뷰 작성 모달 열기]
        function openReviewWriteModal() {
            if (!isCustomerLoggedIn) {
                // 모바일 환경을 배려하여, 카카오 로그인 유도 프로세스를 매끄럽게 전개
                if (typeof showCustomAlert === 'function') {
                    showCustomAlert("<?php echo __('리뷰 작성을 위해 카카오 로그인이 필요합니다.').'\n'.__('로그인 하시겠습니까?'); ?>", 'warning', "<?php echo __('로그인 안내'); ?>", function(){
                        sessionStorage.setItem('postLoginAction', 'review');
                        // 공통 모달의 안전한 로그인 함수 호출
                        if (typeof executeKakaoLoginModal === 'function') {
                            executeKakaoLoginModal();
                        } else {
                            window.location.href = '<?php echo $kakao_login_url ?? ""; ?>';
                        }
                    });
                } else {
                    if (confirm("<?php echo __('리뷰 작성을 위해 카카오 로그인이 필요합니다.').'\n'.__('로그인 하시겠습니까?'); ?>")) {
                        sessionStorage.setItem('postLoginAction', 'review');
                        window.location.href = '<?php echo $kakao_login_url ?? ""; ?>';
                    }
                }
                return;
            }

            // 모달 초기화
            document.getElementById('reviewModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i><?php echo __("리뷰 작성"); ?>';
            document.getElementById('review_action').value = 'write';
            document.getElementById('edit_review_id').value = '';
            document.getElementById('review_content').value = '';
            document.getElementById('review_rating').value = 5;
            document.querySelectorAll('.rating-stars i').forEach(s => s.className = 'bi bi-star-fill');

            var modalEl = document.getElementById('reviewWriteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // [리뷰 수정 모달 열기]
        function openReviewEditModal(id, rating, content) {
            document.getElementById('reviewModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i><?php echo __("리뷰 수정"); ?>';
            document.getElementById('review_action').value = 'update';
            document.getElementById('edit_review_id').value = id;
            document.getElementById('review_content').value = content;
            document.getElementById('review_rating').value = rating;

            document.querySelectorAll('.rating-stars i').forEach(s => {
                s.className = parseInt(s.getAttribute('data-rating')) <= rating ? 'bi bi-star-fill' : 'bi bi-star';
            });

            var modalEl = document.getElementById('reviewWriteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // [리뷰 폼 제출]
        async function submitReview() {
            const action = document.getElementById('review_action').value;
            const rating = document.getElementById('review_rating').value;
            const content = document.getElementById('review_content').value.trim();
            const reviewId = document.getElementById('edit_review_id').value;
            const btn = document.querySelector('#reviewWriteModal .btn-primary');

            if (!content) {
                if (typeof showCustomAlert === 'function') showCustomAlert("<?php echo __('리뷰 내용을 입력해주세요.'); ?>", 'warning');
                else alert("<?php echo __('리뷰 내용을 입력해주세요.'); ?>");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

            const formData = new FormData();
            formData.append('action', action);
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');
            formData.append('rating', rating);
            formData.append('content', content);
            if (action === 'update') formData.append('review_id', reviewId);

            try {
                const res = await fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('reviewWriteModal')).hide();
                    refreshRecentReviews(); // AJAX 방식 실시간 렌더링 호출
                } else {
                    if (typeof showCustomAlert === 'function') showCustomAlert(result.message || 'Error', 'danger');
                    else alert(result.message || 'Error');
                }
            } catch (err) {
                if (typeof showCustomAlert === 'function') showCustomAlert('Network Error', 'danger');
                else alert('Network Error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<?php echo __("리뷰 등록하기"); ?> <i class="bi bi-send-fill ms-1"></i>';
            }
        }

        // [리뷰 삭제]
        async function deleteReview(reviewId) {
            if (!confirm("<?php echo __('작성하신 리뷰를 정말 삭제하시겠습니까?'); ?>")) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');
            formData.append('review_id', reviewId);

            try {
                const res = await fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.status === 'success') refreshRecentReviews(); // AJAX 방식 실시간 렌더링 호출
                else alert(result.message || 'Delete Error');
            } catch (err) {
                alert('Network Error');
            }
        }

        // [새로고침 없이 리뷰 영역만 실시간 렌더링하는 AJAX 함수]
        async function refreshRecentReviews() {
            const formData = new FormData();
            formData.append('action', 'get_recent');
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');

            try {
                const res = await fetch('/shops/shop_review_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.status === 'success') {
                    // 1. 상단 통계(평균 별점, 총 리뷰 수) 실시간 업데이트
                    const avgEl = document.getElementById('reviewStatsAvg');
                    if(avgEl) avgEl.innerText = result.avg_rating.toFixed(1);
                    
                    const totalEl = document.getElementById('reviewStatsTotal');
                    if(totalEl) totalEl.innerText = result.total_reviews;

                    const badge = document.querySelector('.section-title h2 .badge');
                    if (badge) badge.innerText = result.total_reviews;

                    const container = document.getElementById('recentReviewsContainer');
                    if(!container) return;
                    
                    container.innerHTML = '';

                    // 2. 새로운 리뷰 데이터로 HTML DOM 조립
                    if (result.reviews.length > 0) {
                        let html = '';
                        result.reviews.forEach(rev => {
                            let stars = '';
                            for (let i = 1; i <= 5; i++) {
                                stars += i <= rev.rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                            }

                            const safeName = rev.customer_name ? rev.customer_name.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '<?php echo __("고객"); ?>';
                            const safeContent = rev.content.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
                            const safeImg = rev.profile_img ? rev.profile_img.replace(/"/g, "&quot;") : '/assets/no-logo.png';
                            const encodedContent = encodeURIComponent(rev.content);

                            let actionBtnHtml = '';
                            if (isCustomerLoggedIn && customerId == rev.customer_id) {
                                actionBtnHtml = `
                                <button type="button" class="btn btn-link text-primary p-0 ms-2" onclick="openReviewEditModal(${rev.id}, ${rev.rating}, decodeURIComponent('${encodedContent}'))" style="font-size: 0.8rem;" title="<?php echo __('리뷰 수정'); ?>"><i class="bi bi-pencil-square"></i></button>
                                <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="deleteReview(${rev.id})" style="font-size: 0.8rem;" title="<?php echo __('리뷰 삭제'); ?>"><i class="bi bi-trash"></i></button>
                            `;
                            }

                            let replyHtml = '';
                            if (rev.owner_reply) {
                                const safeReply = rev.owner_reply.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
                                replyHtml = `
                                <div class="mt-2 bg-light p-2 rounded-3 position-relative">
                                    <i class="bi bi-arrow-return-right position-absolute top-0 start-0 ms-2 mt-2 text-primary opacity-50"></i>
                                    <div class="ps-3">
                                        <span class="fw-bold text-primary d-block mb-0" style="font-size: 0.8rem;"><?php echo __('사장님 답변'); ?></span>
                                        <p class="mb-0 text-secondary" style="line-height: 1.4; font-size: 0.8rem;">${safeReply}</p>
                                    </div>
                                </div>
                            `;
                            }

                            html += `
                            <div class="col-md-8">
                                <div class="card border-0 shadow-sm rounded-4 mb-1" style="background-color: #fcfcfc;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="${safeImg}" class="rounded-circle shadow-sm" style="width: 28px; height: 28px; object-fit: cover; border: 1px solid #eee;">
                                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">${safeName}</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="text-muted" style="font-size: 0.7rem;">${rev.created_at.substring(0, 10)}</div>
                                                ${actionBtnHtml}
                                            </div>
                                        </div>
                                        <div class="mb-1 text-warning" style="font-size: 0.75rem;">${stars}</div>
                                        <p class="mb-0 text-dark" style="line-height: 1.4; font-size: 0.85rem;">${safeContent}</p>
                                        ${replyHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                        });

                        if (result.total_reviews > 5) {
                            html += `<div class="col-md-8 text-center mt-3"><button type="button" class="btn btn-light border rounded-pill px-4 py-2 w-100 fw-bold text-secondary shadow-sm" onclick="openReviewListModal()"><?php echo __('모든 리뷰 보기'); ?> <i class="bi bi-chevron-right ms-1"></i></button></div>`;
                        }
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `<div class="col-12 text-center py-5 bg-light rounded-4 border border-light"><i class="bi bi-chat-left-dots fs-1 text-muted mb-3 d-block opacity-50"></i><p class="text-muted mb-0 fw-bold"><?php echo __('아직 작성된 리뷰가 없습니다.'); ?></p><p class="text-muted small mt-1"><?php echo __('첫 번째 리뷰를 남겨주세요!'); ?></p></div>`;
                    }

                    // 3. '모든 리뷰 보기' 목록 모달 창이 열려있는 상태였다면, 그 안의 리스트도 함께 강제 동기화
                    const listModal = document.getElementById('reviewListModal');
                    if (listModal && listModal.classList.contains('show')) {
                        if (typeof openReviewListModal === 'function') openReviewListModal();
                    }
                }
            } catch (err) {
                console.error('refreshRecentReviews Error:', err);
            }
        }

        // [모든 리뷰 리스트 모달 (공통 모달 연동)]
        let reviewCurrentPage = 1;
        function openReviewListModal() {
            var modalEl = document.getElementById('reviewListModal');
            if (modalEl) {
                document.getElementById('reviewListContainer').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
                document.getElementById('btnLoadMoreReviews').style.display = 'none';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                
                reviewCurrentPage = 1;
                if (typeof loadReviews === 'function') loadReviews(); // 이전에 로드된 함수가 있으면 호출
            }
        }
        
        // 더보기 버튼 등에서 사용할 수 있도록 빈 더미 함수 정의 (상세 무한 스크롤이 필요할 경우를 대비한 훅)
        if (typeof window.loadReviews === 'undefined') {
            window.loadReviews = function() {
                const btnMore = document.getElementById('btnLoadMoreReviews');
                if (btnMore) { btnMore.disabled = true; btnMore.innerHTML = '...'; }
                
                const formData = new FormData();
                formData.append('action', 'list'); formData.append('shop_id', '<?php echo $shop["id"]; ?>'); formData.append('page', reviewCurrentPage);
                
                fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    // 서버에 상세 구현이 되어 있을 경우 이곳에서 리스트 렌더링 처리를 수행합니다.
                    // (현재는 심플하게 모달 컨테이너 갱신만 허용)
                    if(result.status === 'success' && result.reviews) {
                        document.getElementById('reviewListContainer').innerHTML = '<div class="text-center py-5 text-muted">리뷰 데이터를 모두 불러왔습니다.</div>';
                    }
                    if(btnMore) btnMore.style.display = 'none';
                }).catch(err => { if(btnMore) btnMore.style.display = 'none'; });
            }
        }
    </script>
=======
<?php if (($shop['is_show_review'] ?? 1) == 1):
    $current_lang = $global_current_lang ?? $_SESSION['shop_lang'] ?? 'ko';
    
    // [다국어 처리 수정] 점주가 직접 커스텀 라벨을 입력했는지 우선 확인
    $disp_label_review = $ui['label_review'] ?? '';
    if ($current_lang !== 'ko' && isset($ui["label_review_{$current_lang}"]) && trim($ui["label_review_{$current_lang}"]) !== '') {
        $disp_label_review = $ui["label_review_{$current_lang}"];
    }
    // 커스텀 라벨이 비어있다면 다국어 번역 함수를 태운 기본 라벨 적용
    if (empty(trim($disp_label_review))) {
        $disp_label_review = __('고객 리뷰');
    }
?>
    <section class="container mt-5 mb-5 pb-3 scroll-nav-target" id="shop-review-section" data-nav-label="<?php echo htmlspecialchars($disp_label_review); ?>">
        <div class="section-title text-center mb-4">
            <h2><?php echo htmlspecialchars($disp_label_review); ?> <span class="badge bg-primary rounded-pill align-text-bottom fs-6"><?php echo $total_reviews; ?></span></h2>
        </div>

        <div class="row mb-4">
            <div class="col-12 text-center">
                <div class="display-4 fw-bold text-dark mb-2"><span id="reviewStatsAvg"><?php echo number_format($avg_rating, 1); ?></span> <i class="bi bi-star-fill text-warning fs-3"></i></div>
                <div class="mb-3 text-muted small"><?php echo __('소중한 리뷰 갯수 :').' '; ?><span id="reviewStatsTotal"><?php echo number_format($total_reviews); ?></span></div>

                <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openReviewWriteModal()">
                    <i class="bi bi-pencil-square me-1"></i> <?php echo __('리뷰 작성하기'); ?>
                </button>
            </div>
        </div>

        <div class="row g-3 justify-content-center" id="recentReviewsContainer">
            <?php if (!empty($recent_reviews)): ?>
                <?php foreach ($recent_reviews as $rev): ?>
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm rounded-4 mb-1" style="background-color: #fcfcfc;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo htmlspecialchars($rev['profile_img'] ?: '/assets/no-logo.png'); ?>" class="rounded-circle shadow-sm" style="width: 28px; height: 28px; object-fit: cover; border: 1px solid #eee;">
                                        <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($rev['customer_name'] ?: '고객'); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="text-muted" style="font-size: 0.7rem;"><?php echo substr($rev['created_at'], 0, 10); ?></div>
                                        <?php if ($is_customer_logged_in && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == $rev['customer_id']): ?>
                                            <?php $encodedContent = rawurlencode($rev['content']); ?>
                                            <button type="button" class="btn btn-link text-primary p-0 ms-2" onclick="openReviewEditModal(<?php echo $rev['id']; ?>, <?php echo $rev['rating']; ?>, decodeURIComponent('<?php echo $encodedContent; ?>'))" style="font-size: 0.8rem;" title="<?php echo __('리뷰 수정'); ?>"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="deleteReview(<?php echo $rev['id']; ?>)" style="font-size: 0.8rem;" title="<?php echo __('리뷰 삭제'); ?>"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-1 text-warning" style="font-size: 0.75rem;">
                                    <?php for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rev['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                    } ?>
                                </div>
                                <p class="mb-0 text-dark" style="line-height: 1.4; font-size: 0.85rem;"><?php echo nl2br(htmlspecialchars($rev['content'])); ?></p>

                                <!-- 사장님 답변 영역 -->
                                <?php if (!empty($rev['owner_reply'])): ?>
                                    <div class="mt-2 bg-light p-2 rounded-3 position-relative">
                                        <i class="bi bi-arrow-return-right position-absolute top-0 start-0 ms-2 mt-2 text-primary opacity-50"></i>
                                        <div class="ps-3">
                                            <span class="fw-bold text-primary d-block mb-0" style="font-size: 0.8rem;"><?php echo __('사장님 답변'); ?></span>
                                            <p class="mb-0 text-secondary" style="line-height: 1.4; font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($rev['owner_reply'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_reviews > 5): ?>
                    <div class="col-md-8 text-center mt-3">
                        <button type="button" class="btn btn-light border rounded-pill px-4 py-2 w-100 fw-bold text-secondary shadow-sm" onclick="openReviewListModal()">
                            <?php echo __('모든 리뷰 보기'); ?> <i class="bi bi-chevron-right ms-1"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-light rounded-4 border border-light">
                    <i class="bi bi-chat-left-dots fs-1 text-muted mb-3 d-block opacity-50"></i>
                    <p class="text-muted mb-0 fw-bold"><?php echo __('아직 작성된 리뷰가 없습니다.'); ?></p>
                    <p class="text-muted small mt-1"><?php echo __('첫 번째 리뷰를 남겨주세요!'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // =====================================
        // [고객 리뷰 시스템 통합 엔진 로직]
        // =====================================
        const isCustomerLoggedIn = <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>;
        const customerId = <?php echo $_SESSION['customer_id'] ?? 'null'; ?>;

        // 별점 클릭 이벤트 리스너 바인딩
        document.querySelectorAll('.rating-stars i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                document.getElementById('review_rating').value = rating;

                document.querySelectorAll('.rating-stars i').forEach(s => {
                    if (parseInt(s.getAttribute('data-rating')) <= rating) {
                        s.className = 'bi bi-star-fill';
                    } else {
                        s.className = 'bi bi-star';
                    }
                });
            });
        });

        // [리뷰 작성 모달 열기]
        function openReviewWriteModal() {
            if (!isCustomerLoggedIn) {
                // 모바일 환경을 배려하여, 카카오 로그인 유도 프로세스를 매끄럽게 전개
                if (typeof showCustomAlert === 'function') {
                    showCustomAlert("<?php echo __('리뷰 작성을 위해 카카오 로그인이 필요합니다.').'\n'.__('로그인 하시겠습니까?'); ?>", 'warning', "<?php echo __('로그인 안내'); ?>", function(){
                        sessionStorage.setItem('postLoginAction', 'review');
                        // 공통 모달의 안전한 로그인 함수 호출
                        if (typeof executeKakaoLoginModal === 'function') {
                            executeKakaoLoginModal();
                        } else {
                            window.location.href = '<?php echo $kakao_login_url ?? ""; ?>';
                        }
                    });
                } else {
                    if (confirm("<?php echo __('리뷰 작성을 위해 카카오 로그인이 필요합니다.').'\n'.__('로그인 하시겠습니까?'); ?>")) {
                        sessionStorage.setItem('postLoginAction', 'review');
                        window.location.href = '<?php echo $kakao_login_url ?? ""; ?>';
                    }
                }
                return;
            }

            // 모달 초기화
            document.getElementById('reviewModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i><?php echo __("리뷰 작성"); ?>';
            document.getElementById('review_action').value = 'write';
            document.getElementById('edit_review_id').value = '';
            document.getElementById('review_content').value = '';
            document.getElementById('review_rating').value = 5;
            document.querySelectorAll('.rating-stars i').forEach(s => s.className = 'bi bi-star-fill');

            var modalEl = document.getElementById('reviewWriteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // [리뷰 수정 모달 열기]
        function openReviewEditModal(id, rating, content) {
            document.getElementById('reviewModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i><?php echo __("리뷰 수정"); ?>';
            document.getElementById('review_action').value = 'update';
            document.getElementById('edit_review_id').value = id;
            document.getElementById('review_content').value = content;
            document.getElementById('review_rating').value = rating;

            document.querySelectorAll('.rating-stars i').forEach(s => {
                s.className = parseInt(s.getAttribute('data-rating')) <= rating ? 'bi bi-star-fill' : 'bi bi-star';
            });

            var modalEl = document.getElementById('reviewWriteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        // [리뷰 폼 제출]
        async function submitReview() {
            const action = document.getElementById('review_action').value;
            const rating = document.getElementById('review_rating').value;
            const content = document.getElementById('review_content').value.trim();
            const reviewId = document.getElementById('edit_review_id').value;
            const btn = document.querySelector('#reviewWriteModal .btn-primary');

            if (!content) {
                if (typeof showCustomAlert === 'function') showCustomAlert("<?php echo __('리뷰 내용을 입력해주세요.'); ?>", 'warning');
                else alert("<?php echo __('리뷰 내용을 입력해주세요.'); ?>");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>...';

            const formData = new FormData();
            formData.append('action', action);
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');
            formData.append('rating', rating);
            formData.append('content', content);
            if (action === 'update') formData.append('review_id', reviewId);

            try {
                const res = await fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('reviewWriteModal')).hide();
                    refreshRecentReviews(); // AJAX 방식 실시간 렌더링 호출
                } else {
                    if (typeof showCustomAlert === 'function') showCustomAlert(result.message || 'Error', 'danger');
                    else alert(result.message || 'Error');
                }
            } catch (err) {
                if (typeof showCustomAlert === 'function') showCustomAlert('Network Error', 'danger');
                else alert('Network Error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<?php echo __("리뷰 등록하기"); ?> <i class="bi bi-send-fill ms-1"></i>';
            }
        }

        // [리뷰 삭제]
        async function deleteReview(reviewId) {
            if (!confirm("<?php echo __('작성하신 리뷰를 정말 삭제하시겠습니까?'); ?>")) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');
            formData.append('review_id', reviewId);

            try {
                const res = await fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.status === 'success') refreshRecentReviews(); // AJAX 방식 실시간 렌더링 호출
                else alert(result.message || 'Delete Error');
            } catch (err) {
                alert('Network Error');
            }
        }

        // [새로고침 없이 리뷰 영역만 실시간 렌더링하는 AJAX 함수]
        async function refreshRecentReviews() {
            const formData = new FormData();
            formData.append('action', 'get_recent');
            formData.append('shop_id', '<?php echo $shop["id"]; ?>');

            try {
                const res = await fetch('/shops/shop_review_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.status === 'success') {
                    // 1. 상단 통계(평균 별점, 총 리뷰 수) 실시간 업데이트
                    const avgEl = document.getElementById('reviewStatsAvg');
                    if(avgEl) avgEl.innerText = result.avg_rating.toFixed(1);
                    
                    const totalEl = document.getElementById('reviewStatsTotal');
                    if(totalEl) totalEl.innerText = result.total_reviews;

                    const badge = document.querySelector('.section-title h2 .badge');
                    if (badge) badge.innerText = result.total_reviews;

                    const container = document.getElementById('recentReviewsContainer');
                    if(!container) return;
                    
                    container.innerHTML = '';

                    // 2. 새로운 리뷰 데이터로 HTML DOM 조립
                    if (result.reviews.length > 0) {
                        let html = '';
                        result.reviews.forEach(rev => {
                            let stars = '';
                            for (let i = 1; i <= 5; i++) {
                                stars += i <= rev.rating ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                            }

                            const safeName = rev.customer_name ? rev.customer_name.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '<?php echo __("고객"); ?>';
                            const safeContent = rev.content.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
                            const safeImg = rev.profile_img ? rev.profile_img.replace(/"/g, "&quot;") : '/assets/no-logo.png';
                            const encodedContent = encodeURIComponent(rev.content);

                            let actionBtnHtml = '';
                            if (isCustomerLoggedIn && customerId == rev.customer_id) {
                                actionBtnHtml = `
                                <button type="button" class="btn btn-link text-primary p-0 ms-2" onclick="openReviewEditModal(${rev.id}, ${rev.rating}, decodeURIComponent('${encodedContent}'))" style="font-size: 0.8rem;" title="<?php echo __('리뷰 수정'); ?>"><i class="bi bi-pencil-square"></i></button>
                                <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="deleteReview(${rev.id})" style="font-size: 0.8rem;" title="<?php echo __('리뷰 삭제'); ?>"><i class="bi bi-trash"></i></button>
                            `;
                            }

                            let replyHtml = '';
                            if (rev.owner_reply) {
                                const safeReply = rev.owner_reply.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
                                replyHtml = `
                                <div class="mt-2 bg-light p-2 rounded-3 position-relative">
                                    <i class="bi bi-arrow-return-right position-absolute top-0 start-0 ms-2 mt-2 text-primary opacity-50"></i>
                                    <div class="ps-3">
                                        <span class="fw-bold text-primary d-block mb-0" style="font-size: 0.8rem;"><?php echo __('사장님 답변'); ?></span>
                                        <p class="mb-0 text-secondary" style="line-height: 1.4; font-size: 0.8rem;">${safeReply}</p>
                                    </div>
                                </div>
                            `;
                            }

                            html += `
                            <div class="col-md-8">
                                <div class="card border-0 shadow-sm rounded-4 mb-1" style="background-color: #fcfcfc;">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="${safeImg}" class="rounded-circle shadow-sm" style="width: 28px; height: 28px; object-fit: cover; border: 1px solid #eee;">
                                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">${safeName}</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="text-muted" style="font-size: 0.7rem;">${rev.created_at.substring(0, 10)}</div>
                                                ${actionBtnHtml}
                                            </div>
                                        </div>
                                        <div class="mb-1 text-warning" style="font-size: 0.75rem;">${stars}</div>
                                        <p class="mb-0 text-dark" style="line-height: 1.4; font-size: 0.85rem;">${safeContent}</p>
                                        ${replyHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                        });

                        if (result.total_reviews > 5) {
                            html += `<div class="col-md-8 text-center mt-3"><button type="button" class="btn btn-light border rounded-pill px-4 py-2 w-100 fw-bold text-secondary shadow-sm" onclick="openReviewListModal()"><?php echo __('모든 리뷰 보기'); ?> <i class="bi bi-chevron-right ms-1"></i></button></div>`;
                        }
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `<div class="col-12 text-center py-5 bg-light rounded-4 border border-light"><i class="bi bi-chat-left-dots fs-1 text-muted mb-3 d-block opacity-50"></i><p class="text-muted mb-0 fw-bold"><?php echo __('아직 작성된 리뷰가 없습니다.'); ?></p><p class="text-muted small mt-1"><?php echo __('첫 번째 리뷰를 남겨주세요!'); ?></p></div>`;
                    }

                    // 3. '모든 리뷰 보기' 목록 모달 창이 열려있는 상태였다면, 그 안의 리스트도 함께 강제 동기화
                    const listModal = document.getElementById('reviewListModal');
                    if (listModal && listModal.classList.contains('show')) {
                        if (typeof openReviewListModal === 'function') openReviewListModal();
                    }
                }
            } catch (err) {
                console.error('refreshRecentReviews Error:', err);
            }
        }

        // [모든 리뷰 리스트 모달 (공통 모달 연동)]
        let reviewCurrentPage = 1;
        function openReviewListModal() {
            var modalEl = document.getElementById('reviewListModal');
            if (modalEl) {
                document.getElementById('reviewListContainer').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
                document.getElementById('btnLoadMoreReviews').style.display = 'none';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                
                reviewCurrentPage = 1;
                if (typeof loadReviews === 'function') loadReviews(); // 이전에 로드된 함수가 있으면 호출
            }
        }
        
        // 더보기 버튼 등에서 사용할 수 있도록 빈 더미 함수 정의 (상세 무한 스크롤이 필요할 경우를 대비한 훅)
        if (typeof window.loadReviews === 'undefined') {
            window.loadReviews = function() {
                const btnMore = document.getElementById('btnLoadMoreReviews');
                if (btnMore) { btnMore.disabled = true; btnMore.innerHTML = '...'; }
                
                const formData = new FormData();
                formData.append('action', 'list'); formData.append('shop_id', '<?php echo $shop["id"]; ?>'); formData.append('page', reviewCurrentPage);
                
                fetch('/shops/shop_review_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(result => {
                    // 서버에 상세 구현이 되어 있을 경우 이곳에서 리스트 렌더링 처리를 수행합니다.
                    // (현재는 심플하게 모달 컨테이너 갱신만 허용)
                    if(result.status === 'success' && result.reviews) {
                        document.getElementById('reviewListContainer').innerHTML = '<div class="text-center py-5 text-muted">리뷰 데이터를 모두 불러왔습니다.</div>';
                    }
                    if(btnMore) btnMore.style.display = 'none';
                }).catch(err => { if(btnMore) btnMore.style.display = 'none'; });
            }
        }
    </script>
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
<?php endif; ?>