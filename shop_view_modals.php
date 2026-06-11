    <script>
        // [공통 유틸리티] 모달 호환성 헬퍼: Bootstrap 버전에 관계없이 안전하게 모달을 열고 닫습니다.
        function showBsModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                modal.show();
            } else if (typeof jQuery !== 'undefined') {
                jQuery('#' + id).modal('show');
            }
        }

        function hideBsModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(el);
                if (modal) modal.hide();
            } else if (typeof jQuery !== 'undefined') {
                jQuery('#' + id).modal('hide');
            }
        }

        // [추가] 상점 카테고리별 동적 설정(CONFIG) 객체를 안전하게 가져오는 공용 헬퍼 함수
        function getShopConfig() {
            if (typeof FNB_CONFIG !== 'undefined') return FNB_CONFIG;
            if (typeof REALTY_CONFIG !== 'undefined') return REALTY_CONFIG;
            if (typeof SRV_CONFIG !== 'undefined') return SRV_CONFIG;
            return null;
        }

        /**
         * [공용 함수] 브라우저 기본 alert() 대신 예쁜 커스텀 모달을 띄웁니다.
         * @param {string} message - 출력할 메시지 내용
         * @param {string} type - 'info', 'success', 'warning', 'danger'
         * @param {string} title - 모달 제목
         * @param {function} callback - '확인' 버튼을 누른 후 실행할 함수
         */
        window.showCustomAlert = function(message, type = 'info', title = '알림', callback = null) {
            const modalEl = document.getElementById('customAlertModal');
            if (!modalEl) {
                alert(message);
                if (callback) callback();
                return;
            }

            let iconHtml = '<i class="bi bi-info-circle-fill text-primary" style="font-size: 3rem;"></i>';
            let btnClass = 'btn-primary';

            if (type === 'success') {
                iconHtml = '<i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>';
                btnClass = 'btn-success';
            } else if (type === 'warning') {
                iconHtml = '<i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>';
                btnClass = 'btn-warning text-dark';
            } else if (type === 'danger' || type === 'error') {
                iconHtml = '<i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>';
                btnClass = 'btn-danger';
            }

            document.getElementById('customAlertIcon').innerHTML = iconHtml;
            document.getElementById('customAlertTitle').innerText = title;
            document.getElementById('customAlertMessage').innerHTML = message.replace(/\n/g, '<br>');

            const btn = document.getElementById('customAlertBtn');
            btn.className = `btn ${btnClass} w-100 fw-bold rounded-pill shadow-sm`;

            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            newBtn.addEventListener('click', function() {
                bsModal.hide();
                if (typeof callback === 'function') setTimeout(callback, 300);
            });
            bsModal.show();
        };

        // 25. [카카오 SDK] 자바스크립트 SDK 초기화
        try {
            if (typeof Kakao !== 'undefined') {
                if (!Kakao.isInitialized()) {
                    Kakao.init('<?php echo KAKAO_JS_KEY; ?>');
                    console.log('Kakao SDK Initialized');
                }
            } else {
                console.error('Kakao SDK not loaded');
            }
        } catch (e) {
            console.error('Kakao Init Error:', e);
        }

        // 26. [카카오 인증] 카카오 로그인 수행 함수
        function loginWithKakao(keepAction = false, btnElem = null) {
            // [추가] 카카오 로그인 시 약간의 지연이 있으므로 사용자가 버튼을 다시 누르거나 혼란스럽지 않게 시각적 피드백 제공
            if (btnElem) {
                btnElem.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ...';
                btnElem.classList.add('disabled');
            } else {
                // 버튼 요소를 안 넘겼다면 전체 화면 로딩 오버레이 띄우기
                const loaderHtml = `
                <div id="fullPageLoader" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8); z-index:9999; display:flex; flex-direction:column; justify-content:center; align-items:center;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
                    <div class="mt-3 fw-bold text-dark">...</div>
                </div>`;
                document.body.insertAdjacentHTML('beforeend', loaderHtml);
            }

            // 상단 '로그인' 버튼 등 명시적인 액션 유도가 아닌 일반 로그인을 시도할 때,
            // 이전 작업(카트, 주문내역 조회 중 닫기 등)에서 찌꺼기로 남아있던 세션 상태를 비워줍니다.
            if (!keepAction) {
                sessionStorage.removeItem('postLoginAction');
            }
            /**
             * [최적화] PC 환경 지연 방지
             * JS SDK의 authorize 대신 PHP에서 생성한 REST API URL로 직접 리다이렉트합니다.
             * 이는 PC 카카오톡 앱 설치 여부 확인 과정을 건너뛰어 훨씬 빠릅니다.
             */
            location.href = '<?php echo $kakao_login_url; ?>';
        }

        /**
         * [주문 조회] 로그인 상태에 따른 처리 로직
         * - 로그아웃 상태: 로그인 유도
         * - 로그인 상태: 저장된 번호로 즉시 검색 모달 실행
         */
        function showOrderHistoryModal() {
            // [리팩토링] 모달 오픈 로직을 중앙 제어기(FlowManager 또는 fnb_cart.js)로 이관
            if (typeof showOrderHistory === 'function') {
                showOrderHistory();
                return;
            }
            // Fallback
            const historyModalElem = document.getElementById('orderHistoryModal');
            if (historyModalElem) bootstrap.Modal.getOrCreateInstance(historyModalElem).show();
        }

        // [추가] 필리핀 정보 관리 로직
        function showMyInfoModal() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('phInfoModal')).show();
        }

        // [수정] 모달 흐름을 직접 제어하여 순서 꼬임을 완벽히 차단합니다.
        window.continueWithoutLogin = function() {
            window.hasConfirmedLoginChoice = true;
            hideBsModal('loginChoiceModal');

            setTimeout(() => {
                const postAction = sessionStorage.getItem('postLoginAction');

                // 부동산(Realty) 관련 전역 변수 및 세션 액션 분기 처리
                if (typeof window.pendingRealtyAction !== 'undefined' && window.pendingRealtyAction !== null) {
                    if (window.pendingRealtyAction === 'submitOrder') {
                        if (typeof window.submitOrder === 'function') window.submitOrder();
                    } else if (window.pendingRealtyAction === 'submitSingleInquiry') {
                        if (typeof window.submitSingleInquiry === 'function') window.submitSingleInquiry();
                    } else if (window.pendingRealtyAction === 'viewHistory') {
                        if (typeof window.openRealtyOrderHistoryModal === 'function') window.openRealtyOrderHistoryModal();
                    }
                    window.pendingRealtyAction = null;
                    return;
                } else if (postAction === 'realty_cart_inquiry_auto_submit' || window.loginChoiceContext === 'realty_cart_inquiry_auto_submit') {
                    if (typeof autoSubmitRealtyCartInquiry === 'function') autoSubmitRealtyCartInquiry();
                    return;
                } else if (postAction === 'realty_single_inquiry_auto_submit' || window.loginChoiceContext === 'realty_single_inquiry_auto_submit') {
                    if (typeof autoSubmitRealtySingleInquiry === 'function') autoSubmitRealtySingleInquiry();
                    return;
                } else if (postAction === 'realty_history' || window.loginChoiceContext === 'realty_history') {
                    if (typeof openRealtyOrderHistoryModal === 'function') openRealtyOrderHistoryModal();
                    return;
                }

                if (window.pendingFnbAction === 'proceedToOrder' || window.loginChoiceContext === 'cart') {
                    if (typeof renderCartModalContent === 'function') renderCartModalContent();
                    window.pendingFnbAction = null;
                    window.loginChoiceContext = null;
                } else if (window.loginChoiceContext === 'history') {
                    const historyForm = document.getElementById('non-member-history-form');
                    const infoForm = document.getElementById('member-history-info');
                    if (historyForm) historyForm.style.display = 'block';
                    if (infoForm) infoForm.style.display = 'none';

                    const phoneInput = document.getElementById('history_search_phone');
                    if (phoneInput) phoneInput.value = '';

                    const results = document.getElementById('history-results');
                    if (results) results.innerHTML = '<div class="text-center py-5 text-muted"><?php echo addslashes(__('전화번호를 입력하고 조회 버튼을 눌러주세요.')); ?></div>';

                    showBsModal('orderHistoryModal');
                    window.loginChoiceContext = null;
                } else if (window.loginChoiceContext === 'review') {
                    if (typeof openReviewWriteModal === 'function') openReviewWriteModal();
                } else {
                    if (typeof renderCartModalContent === 'function') renderCartModalContent();
                }
            }, 300);
        };

        <?php if ($is_customer_logged_in): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('login_success') === '1') {
                    const url = new URL(window.location);
                    url.searchParams.delete('login_success');
                    window.history.replaceState({}, '', url);

                    // [추가] 카카오 로그인 성공 시 DB에 저장된 전화번호가 있다면 브라우저 로컬 스토리지에도 동기화
                    const cfg = getShopConfig();
                    if (cfg && cfg.customerPhone) {
                        if (typeof REALTY_CONFIG !== 'undefined') localStorage.setItem('realty_last_search_phone', cfg.customerPhone);
                        else if (typeof SRV_CONFIG !== 'undefined') localStorage.setItem('srv_last_search_phone', cfg.customerPhone);
                        else localStorage.setItem('ps24_guest_phone', cfg.customerPhone);
                    }

                    showCustomAlert('<?php echo addslashes(__('카카오톡으로 안전하게 로그인되었습니다.')); ?>', 'success', '<?php echo addslashes(__('카카오톡으로 로그인 성공!')); ?>', function() {
                        continueToOrderForm();
                    });

                    // [UX 개선] 카카오 로그인 성공 알림창에 카카오 테마(노란색 배경, 어두운 텍스트) 적용
                    const alertIcon = document.querySelector('#customAlertIcon i');
                    if (alertIcon) {
                        alertIcon.className = 'bi bi-check-circle-fill';
                        alertIcon.style.color = '#FEE500'; // 카카오 옐로우
                    }
                    const alertBtn = document.getElementById('customAlertBtn');
                    if (alertBtn) {
                        alertBtn.className = 'btn w-100 fw-bold rounded-pill shadow-sm';
                        alertBtn.style.backgroundColor = '#FEE500';
                        alertBtn.style.color = '#3A1D1D';
                        alertBtn.style.border = 'none';
                    }
                }
            });

            window.continueToOrderForm = function() {
                const postLoginAction = sessionStorage.getItem('postLoginAction');
                sessionStorage.removeItem('postLoginAction');

                setTimeout(() => {
                    if (postLoginAction === 'history') {
                        showBsModal('orderHistoryModal');
                        if (typeof searchOrderHistory === 'function') searchOrderHistory();
                    } else if (postLoginAction === 'realty_history') {
                        // [추가] 카카오 로그인 성공 후 부동산 전용 문의 내역 모달 열기
                        if (typeof openRealtyOrderHistoryModal === 'function') openRealtyOrderHistoryModal();
                    } else if (postLoginAction === 'srv_history') {
                        // [수정] 카카오 로그인 성공 후 서비스 예약/문의 내역 모달 자동 열기 (전화번호 부재 시 예외처리 강화)
                        const savedPhone = localStorage.getItem('srv_last_search_phone') || localStorage.getItem('ps24_guest_phone') || '';
                        if (savedPhone && typeof fetchServiceInquiryHistory === 'function') {
                            fetchServiceInquiryHistory(savedPhone);
                        } else if (!savedPhone) {
                            // 로그인 직후 번호가 없는 경우, common_modals.php에서 이미 phInfoModal을 띄웠을 가능성이 높습니다.
                            // 따라서 여기서는 후속 액션('srv_history')만 예약해두어, 번호 저장 후 내역 조회가 실행되도록 합니다.
                            window.pendingPhInfoAction = 'srv_history';
                            const phModal = document.getElementById('phInfoModal');
                            if (phModal && !phModal.classList.contains('show')) {
                                if (typeof showBsModal === 'function') showBsModal('phInfoModal');
                            }
                        }
                    } else if (postLoginAction === 'realty_cart_inquiry') {
                        if (typeof showCartViewModal === 'function') showCartViewModal();
                    } else if (postLoginAction === 'realty_cart_inquiry_auto_submit') {
                        if (typeof autoSubmitRealtyCartInquiry === 'function') autoSubmitRealtyCartInquiry();
                    } else if (postLoginAction === 'realty_single_inquiry') {
                        showCustomAlert('안전하게 로그인 되었습니다.<br>문의하실 매물을 다시 선택해주세요.', 'success');
                    } else if (postLoginAction === 'realty_single_inquiry_auto_submit') {
                        if (typeof autoSubmitRealtySingleInquiry === 'function') autoSubmitRealtySingleInquiry();
                    } else if (postLoginAction === 'review') {
                        const reviewSection = document.getElementById('shop-review-section');
                        if (reviewSection) {
                            window.scrollTo({
                                top: reviewSection.getBoundingClientRect().top + window.scrollY - 100,
                                behavior: 'smooth'
                            });
                        }
                        if (typeof openReviewWriteModal === 'function') openReviewWriteModal();
                    } else if (postLoginAction === 'cart') {
                        const badgeEl = document.getElementById('cart-count-badge');
                        const cartCount = badgeEl ? parseInt(badgeEl.innerText) : 0;
                        if (cartCount > 0) {
                            if (typeof proceedToOrderFromCart === 'function') proceedToOrderFromCart();
                            else showBsModal('cartModal');

                            setTimeout(() => {
                                const pInput = document.getElementById('customer_phone'),
                                    aInput = document.getElementById('customer_address'),
                                    lInput = document.getElementById('customer_landmark');
                            const cfg = getShopConfig();
                            if (cfg) {
                                if (pInput && !pInput.value.trim() && cfg.customerPhone) pInput.value = cfg.customerPhone;
                                if (aInput && !aInput.value.trim() && cfg.customerAddress) aInput.value = cfg.customerAddress;
                                if (lInput && !lInput.value.trim() && cfg.customerLandmark) lInput.value = cfg.customerLandmark;
                                }
                                if (pInput && !pInput.value.trim()) pInput.focus();
                                else if (aInput && !aInput.value.trim()) aInput.focus();
                            }, 500);
                        }
                    }
                }, 400); // 400ms 딜레이로 충돌 원천 차단
            };

        <?php endif; ?>

        // [최후의 보루 - 회원/비회원 공통 적용] 어떤 주문/예약/문의 모달이 열리든, 폼 리셋을 방어하고 로컬 스토리지의 전화번호를 자동 주입
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('shown.bs.modal', function(event) {
                const modalEl = event.target;
                if (modalEl.id === 'phInfoModal') return; // 연락처 등록 모달 자체는 제외
                
                // 모달 내부에 전화번호 입력 칸이나 주소 입력 칸이 있는지 스캔 (FNB 카트, 부동산/서비스 개별 문의 등 광범위 포함)
                const pInput = modalEl.querySelector('input[type="tel"][id*="phone"]');
                const aInput = modalEl.querySelector('input[id*="address"], textarea[id*="address"]');
                const lInput = modalEl.querySelector('input[id*="landmark"]');
                
                if (pInput || aInput) {
                    const cfg = getShopConfig();
                    
                    // [강화] 폰 번호 1순위: DB 세션값(cfg), 2순위: 브라우저 로컬 스토리지
                    let localPhone = (cfg && cfg.customerPhone) ? cfg.customerPhone : '';
                    if (!localPhone) {
                        localPhone = localStorage.getItem('ps24_guest_phone') || localStorage.getItem('realty_last_search_phone') || localStorage.getItem('srv_last_search_phone') || '';
                    }

                    if (pInput && !pInput.value.trim() && localPhone) {
                        pInput.value = localPhone;
                        if (typeof formatPhoneInput === 'function') formatPhoneInput(pInput);
                    }
                    
                    // [강화] 주소/랜드마크 1순위: DB 세션값(cfg), 2순위: 브라우저 로컬 스토리지
                    let localAddress = (cfg && cfg.customerAddress) ? cfg.customerAddress : (localStorage.getItem('ps24_guest_address') || '');
                    let localLandmark = (cfg && cfg.customerLandmark) ? cfg.customerLandmark : (localStorage.getItem('ps24_guest_landmark') || '');
                    
                    if (aInput && !aInput.value.trim() && localAddress) {
                        aInput.value = localAddress;
                    }
                    if (lInput && !lInput.value.trim() && localLandmark) {
                        lInput.value = localLandmark;
                    }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const phInfoForm = document.getElementById('phInfoForm');
            if (phInfoForm) {
                phInfoForm.onsubmit = async function(e) {
                    e.preventDefault();
                    const phone = document.getElementById('ph_phone').value;
                    const address = document.getElementById('ph_address').value;
                    const landmark = document.getElementById('ph_landmark').value;

                    <?php if ($is_customer_logged_in): ?>
                        // [추가] 다른 고객이 이미 사용 중인 전화번호인지 중복 검사
                        try {
                            const checkRes = await fetch('/shops/check_customer_phone.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `phone=${encodeURIComponent(phone)}&shop_id=<?php echo $shop['id']; ?>`
                            });
                            const checkData = await checkRes.json();
                            if (checkData.status === 'duplicate') {
                                showCustomAlert(checkData.message, 'warning', '전화번호 중복', function() {
                                    const phoneInput = document.getElementById('ph_phone');
                                    phoneInput.value = '';
                                    phoneInput.focus();
                                });
                                return; // 중복 시 저장 프로세스 중단
                            }
                        } catch (err) {
                            console.error('Phone check error:', err);
                        }

                        fetch('/shops/update_customer_info.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: `phone=${encodeURIComponent(phone)}&address=${encodeURIComponent(address)}&landmark=${encodeURIComponent(landmark)}`
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    hideBsModal('phInfoModal');
                                const cfg = getShopConfig();
                                if (cfg) {
                                    cfg.customerPhone = phone;
                                    cfg.customerAddress = address;
                                    cfg.customerLandmark = landmark;
                                    }
                                    
                                    // [공통] 회원/비회원 상관없이 전화번호를 로컬 스토리지에 확실하게 갱신
                                    if (typeof REALTY_CONFIG !== 'undefined') localStorage.setItem('realty_last_search_phone', phone);
                                    else if (typeof SRV_CONFIG !== 'undefined') localStorage.setItem('srv_last_search_phone', phone);
                                    else localStorage.setItem('ps24_guest_phone', phone);

                                    if (window.loginChoiceContext === 'history') {
                                        showBsModal('orderHistoryModal');
                                        if (typeof searchOrderHistory === 'function') searchOrderHistory();
                                    } else if (window.loginChoiceContext === 'realty_history') {
                                        if (typeof openRealtyOrderHistoryModal === 'function') openRealtyOrderHistoryModal();
                                    } else if (window.loginChoiceContext === 'cart') {
                                        if (typeof renderCartModalContent === 'function') renderCartModalContent();
                                    } else {
                                        location.reload();
                                    }
                                } else {
                                    showCustomAlert('저장에 실패했습니다:<br>' + data.message, 'danger', '오류');
                                }
                            })
                            .catch(err => showCustomAlert('오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'danger', '시스템 오류'));
                    <?php else: ?>
                        // 비로그인 시 로컬에 정보 임시 저장 및 주문 조회 모달로 즉시 연결
                    const cfg = getShopConfig();
                    if (cfg) {
                        cfg.customerPhone = phone;
                        cfg.customerAddress = address;
                        cfg.customerLandmark = landmark;
                        }
                        
                        // [공통] 비회원도 전화번호를 로컬 스토리지에 확실하게 갱신
                        if (typeof REALTY_CONFIG !== 'undefined') localStorage.setItem('realty_last_search_phone', phone);
                        else if (typeof SRV_CONFIG !== 'undefined') localStorage.setItem('srv_last_search_phone', phone);
                        else localStorage.setItem('ps24_guest_phone', phone);
                        
                        hideBsModal('phInfoModal');

                        if (window.loginChoiceContext === 'history') {
                            showBsModal('orderHistoryModal');
                            if (typeof searchOrderHistory === 'function') searchOrderHistory();
                        } else if (window.loginChoiceContext === 'realty_history') {
                            if (typeof openRealtyOrderHistoryModal === 'function') openRealtyOrderHistoryModal();
                        } else if (window.loginChoiceContext === 'cart') {
                            if (typeof renderCartModalContent === 'function') renderCartModalContent();
                        }
                    <?php endif; ?>
                };
            }
        });

        /**
         * [공용 함수] 클립보드 복사 기능
         * 로그인 여부와 관계없이 모든 방문자가 사용할 수 있도록 PHP 조건문 외부로 배치
         */
        async function copyToClipboard(text, label) {
            if (!text || text === '정보 없음' || text.trim() === '') {
                showCustomAlert('등록된 ID가 없습니다.', 'warning');
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                showCustomAlert(`${label} [${text}] 가 복사되었습니다.\n카카오톡에서 'ID로 추가' 시 붙여넣기 해주세요.`, 'success', '복사 완료');
            } catch (err) {
                // 구형 브라우저 대응 (Fallback)
                const tempElem = document.createElement('textarea');
                tempElem.value = text;
                document.body.appendChild(tempElem);
                tempElem.select();
                document.execCommand('copy');
                document.body.removeChild(tempElem);
                showCustomAlert(`${label}가 복사되었습니다.`, 'success', '복사 완료');
            }
        }

        function openKakaoTalk(kakaoLink) {
            <?php if ($is_customer_logged_in): ?>
                window.location.href = kakaoLink;
            <?php else: ?>
                showCustomAlert("카카오톡 로그인이 필요합니다.", 'warning', '로그인 안내', function() {
                    loginWithKakao();
                });
            <?php endif; ?>
        }

        /**
         * [회원 탈퇴] 고객 탈퇴 처리 함수
         * - 주문 내역 삭제 공지 후 진행
         * - 성공 시 세션 파기 및 메인 페이지 리다이렉트
         */
        function confirmWithdrawal() {
            const confirmMsg = "회원 탈퇴를 하면, 기존의 주문 내역들이 모두 삭제됩니다. 정말 탈퇴하시겠습니까?";

            if (confirm(confirmMsg)) {
                // 서버측 탈퇴 스크립트 호출 (AJAX)
                fetch('/customer_withdrawal.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showCustomAlert("성공적으로 본 상점에서 탈퇴 되었습니다.\n향후 재가입 가능합니다.", 'success', '탈퇴 완료', function() {
                                location.href = 'shop_view.php?subdomain=<?php echo $subdomain; ?>';
                            });
                        } else {
                            showCustomAlert("탈퇴 처리 중 오류가 발생했습니다.<br>" + data.message, 'danger', '오류');
                        }
                    })
                    .catch(error => {
                        console.error('Withdrawal Error:', error);
                        showCustomAlert("통신 중 오류가 발생했습니다.<br>잠시 후 다시 시도해주세요.", 'danger', '오류');
                    });
            }
        }

        // =====================================
        // [메인 배경 슬라이더 마우스 드래그 지원]
        // =====================================
        document.addEventListener('DOMContentLoaded', function() {
            // common_footer.php 에 정의된 공통 함수 호출
            if (typeof enableCarouselSwipe === 'function') {
                enableCarouselSwipe('heroCarousel');
            }
        });
    </script>