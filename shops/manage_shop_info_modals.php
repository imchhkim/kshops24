<<<<<<< HEAD
<?php
/**
 * KShops24 상점 기본 정보 설정 모달 및 스크립트 분리 (manage_shop_info_modals.php)
 */
if (!isset($shop_id)) exit; // 직접 접근 방지
?>
<!-- 모달 및 스크립트 모음 -->
<!-- 관리자 암호 변경 모달 -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="pwChangeForm" onsubmit="handlePasswordChange(event)">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i>관리자 암호 변경</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 에러 메시지 출력 영역 -->
                <div id="pw-error-msg" class="alert alert-danger d-none small mb-3 border-0 shadow-sm"></div>

                <div class="alert alert-warning border-0 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>보안 규칙:</strong> 대/소문자 및 숫자 포함 6글자 이상 입력해 주세요.
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-primary mb-0">현재 비밀번호</label>
                        <button type="button" id="btn-send-temp-pw" class="btn btn-sm btn-outline-secondary py-0 px-2 shadow-sm" style="font-size: 0.7rem;" onclick="sendTempPw()"><i class="bi bi-envelope-paper me-1"></i>비밀번호를 잊으셨나요?</button>
                    </div>
                    <input type="password" name="current_password" id="current_password" class="form-control border-primary border-opacity-25" placeholder="기존 비밀번호 입력 (또는 발급받은 임시 비번)" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">새 비밀번호</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">비밀번호 확인</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow">암호 변경하기</button>
            </div>
        </form>
    </div>
</div>

<!-- 상점 기본 정보 수정 모달 -->
<div class="modal fade" id="editInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=shop" onsubmit="handleEditInfoSubmit(event)">
            <input type="hidden" name="update_shop" value="1">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>상점 기본 정보 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">* 표시가 있는 항목들은 타 상점과 중복될 수 없습니다.</p>
                <div class="mb-2">
                    <label class="form-label small fw-bold">관리자 이름 (한글)</label>
                    <input type="text" name="manager_name" class="form-control" value="<?php echo htmlspecialchars($shop['manager_name']); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">관리자 이름 (English)</label>
                    <input type="text" name="manager_name_en" class="form-control" value="<?php echo htmlspecialchars($shop['manager_name_en']); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">카카오톡 ID *</label>
                    <input type="text" name="kakao_id" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['kakao_id'] ?? ''); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">휴대전화 (Mobile) *</label>
                    <input type="text" name="phone_mobile" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['phone_mobile']); ?>" required oninput="formatPhoneInput(this)" maxlength="13">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">카카오 채널 ID * </label>
                    <input type="text" name="kakao_channel_id" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['kakao_channel_id'] ?? ''); ?>" placeholder="예: @KShops24">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">유선전화 (Landline) *</label>
                    <input type="text" name="phone_landline" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['phone_landline'] ?? ''); ?>" oninput="formatPhoneInput(this)" maxlength="12">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">페이스북 URL *</label>
                    <input type="url" name="facebook_url" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/...">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">상점 실제 주소</label>
                    <input type="text" name="physical_address" class="form-control" value="<?php echo htmlspecialchars($shop['physical_address'] ?? ''); ?>" placeholder="예: 매장의 물리적 위치">
                </div>
                <?php
                $bh = $shop['business_hours'] ?? '';
                $bh_data = [];
                $days_map = ['mon' => '월요일', 'tue' => '화요일', 'wed' => '수요일', 'thu' => '목요일', 'fri' => '금요일', 'sat' => '토요일', 'sun' => '일요일'];

                if (!empty($bh) && ($bh[0] === '{' || $bh[0] === '[')) {
                    $bh_data = json_decode($bh, true);
                } else {
                    // 기존 09:00~18:00 포맷 하위 호환성
                    $parts = explode('~', $bh);
                    $bh_s = trim($parts[0] ?? '');
                    $bh_e = trim($parts[1] ?? '');
                    foreach ($days_map as $k => $v) {
                        $bh_data[$k] = ['open' => $bh_s, 'close' => $bh_e, 'closed' => false];
                    }
                }
                ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-3">영업 시간 (요일별)</label>
                    <p <?php echo UI_INFO_SM_LABEL;?>> 체크박스를 클릭하면 해당 요일을 <strong style="color: red;">휴무일</strong>로 지정할 수 있습니다.</p>
                    <div class="bg-light p-3 rounded-3 border">
                        <?php foreach ($days_map as $k => $day_name):
                            $day_info = $bh_data[$k] ?? ['open' => '', 'close' => '', 'closed' => false];
                            $is_closed = !empty($day_info['closed']);
                            $closed_class = $is_closed ? 'bg-secondary bg-opacity-10 text-muted' : '';
                            $closed_attr = $is_closed ? 'readonly tabindex="-1" style="pointer-events: none;"' : '';
                        ?>
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-4 col-md-3">
                                    <div class="form-check form-switch mb-0 d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="bh[<?php echo $k; ?>][closed]" id="bh_closed_<?php echo $k; ?>" value="1" <?php echo $is_closed ? 'checked' : ''; ?> onchange="toggleBhInputs('<?php echo $k; ?>')">
                                        <label class="form-check-label small fw-bold" for="bh_closed_<?php echo $k; ?>" style="cursor: pointer;"><?php echo $day_name; ?></label>
                                    </div>
                                </div>
                                <div class="col-8 col-md-9">
                                    <div class="input-group input-group-sm">
                                        <input type="time" name="bh[<?php echo $k; ?>][open]" id="bh_open_<?php echo $k; ?>" class="form-control <?php echo $closed_class; ?>" value="<?php echo htmlspecialchars($day_info['open'] ?? ''); ?>" <?php echo $closed_attr; ?>>
                                        <span class="input-group-text bg-white border-start-0 border-end-0 text-muted">~</span>
                                        <input type="time" name="bh[<?php echo $k; ?>][close]" id="bh_close_<?php echo $k; ?>" class="form-control <?php echo $closed_class; ?>" value="<?php echo htmlspecialchars($day_info['close'] ?? ''); ?>" <?php echo $closed_attr; ?>>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-end mt-3 border-top pt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill shadow-sm" onclick="applyMondayToAll()">
                                <i class="bi bi-files me-1"></i>월요일 시간을 매일 동일하게 적용
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="my-4 opacity-25">

                <?php if (($shop['category'] ?? 'fnb') === 'fnb'): ?>
                    <!-- 배달 및 매장픽업 지원 여부 -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold"><i class="bi bi-truck text-primary me-1"></i> 배달 지원 여부</label>
                        <p <?php echo UI_INFO_SM_LABEL;?>> <strong style="color: red;">배달 및 매장픽업 가능</strong>을 선택하면 배달/픽업 관련 기능들이 활성화 됩니다.</p>

                        <div class="d-flex gap-4 bg-light p-3 rounded-3 border">
                            <div class="form-check form-switch m-0 d-flex align-items-center">
                                <input type="hidden" name="is_delivery_available" value="0">
                                <input class="form-check-input mt-0 me-2" type="checkbox" name="is_delivery_available" id="is_delivery_available" value="1" <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'checked' : ''; ?> style="cursor: pointer;" onchange="document.getElementById('is_pickup_available').value = this.checked ? 1 : 0;">
                                <label class="form-check-label small fw-bold" for="is_delivery_available" style="cursor: pointer;">배달 및 매장픽업 가능</label>
                            </div>
                            <input type="hidden" name="is_pickup_available" id="is_pickup_available" value="<?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? '1' : '0'; ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 상점 기본 화폐 설정 -->
                <div class="mb-4">
                    <label class="form-label small fw-bold"><i class="bi bi-cash-coin text-primary me-1"></i> 상점 기본 화폐 단위</label>
                    <select name="ui[currency]" class="form-select form-select-sm">
                        <option value="PHP" <?php echo (($ui['currency'] ?? 'PHP') === 'PHP') ? 'selected' : ''; ?>>필리핀 페소 (PHP, ₱)</option>
                        <option value="KRW" <?php echo (($ui['currency'] ?? 'PHP') === 'KRW') ? 'selected' : ''; ?>>대한민국 원 (KRW, ₩)</option>
                        <option value="USD" <?php echo (($ui['currency'] ?? 'PHP') === 'USD') ? 'selected' : ''; ?>>미국 달러 (USD, $)</option>
                        <option value="EUR" <?php echo (($ui['currency'] ?? 'PHP') === 'EUR') ? 'selected' : ''; ?>>유로 (EUR, €)</option>
                        <option value="JPY" <?php echo (($ui['currency'] ?? 'PHP') === 'JPY') ? 'selected' : ''; ?>>일본 엔 (JPY, ¥)</option>
                        <option value="CNY" <?php echo (($ui['currency'] ?? 'PHP') === 'CNY') ? 'selected' : ''; ?>>중국 위안 (CNY, ¥)</option>
                        <option value="GBP" <?php echo (($ui['currency'] ?? 'PHP') === 'GBP') ? 'selected' : ''; ?>>영국 파운드 (GBP, £)</option>
                        <option value="AUD" <?php echo (($ui['currency'] ?? 'PHP') === 'AUD') ? 'selected' : ''; ?>>호주 달러 (AUD, A$)</option>
                        <option value="CAD" <?php echo (($ui['currency'] ?? 'PHP') === 'CAD') ? 'selected' : ''; ?>>캐나다 달러 (CAD, C$)</option>
                        <option value="SGD" <?php echo (($ui['currency'] ?? 'PHP') === 'SGD') ? 'selected' : ''; ?>>싱가포르 달러 (SGD, S$)</option>
                        <option value="HKD" <?php echo (($ui['currency'] ?? 'PHP') === 'HKD') ? 'selected' : ''; ?>>홍콩 달러 (HKD, HK$)</option>
                        <option value="TWD" <?php echo (($ui['currency'] ?? 'PHP') === 'TWD') ? 'selected' : ''; ?>>대만 달러 (TWD, NT$)</option>
                        <option value="VND" <?php echo (($ui['currency'] ?? 'PHP') === 'VND') ? 'selected' : ''; ?>>베트남 동 (VND, ₫)</option>
                        <option value="THB" <?php echo (($ui['currency'] ?? 'PHP') === 'THB') ? 'selected' : ''; ?>>태국 바트 (THB, ฿)</option>
                        <option value="IDR" <?php echo (($ui['currency'] ?? 'PHP') === 'IDR') ? 'selected' : ''; ?>>인도네시아 루피아 (IDR, Rp)</option>
                        <option value="MYR" <?php echo (($ui['currency'] ?? 'PHP') === 'MYR') ? 'selected' : ''; ?>>말레이시아 링깃 (MYR, RM)</option>
                    </select>
                </div>

                <!-- 다국어 지원 설정 -->
                <div class="bg-light p-4 rounded-4 border border-primary border-opacity-25 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-globe me-2 text-primary"></i>다국어 지원</h6>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">한국어(기본) 외에 외국어 2개의 외국어 선택 기능을 제공합니다.<br>
                                <strong>다국어 지원 기능을 OFF하면 기본 언어인 한국어가 표시됩니다.</strong></small>

                        </div>
                        <div class="form-check form-switch m-0 fs-4">
                            <input type="hidden" name="ui[is_multilingual]" value="0">
                            <input class="form-check-input" type="checkbox" name="ui[is_multilingual]" id="is_multilingual" value="1" <?php echo (($ui['is_multilingual'] ?? 0) == 1) ? 'checked' : ''; ?> style="cursor: pointer;" onchange="toggleMultilingualOptions()">
                        </div>
                    </div>

                    <div id="multilingual-options" class="mt-3 pt-3 border-top border-primary border-opacity-25" style="<?php echo (($ui['is_multilingual'] ?? 0) == 1) ? 'display: block;' : 'display: none;'; ?>">
                        <?php
                        $supported_langs = [
                            'none' => '사용 안 함',
                            'en'   => '영어 (En)',
                            'tl'   => '따갈로그어 (Tl)',
                            'zh'   => '중국어 (Zh)',
                            'ja'   => '일본어 (Ja)',
                            'vi'   => '베트남어 (Vi)',
                            'th'   => '태국어 (Th)',
                            'id'   => '인도네시아어 (Id)',
                            'ms'   => '말레이시아어 (Ms)',
                            'es'   => '스페인어 (Es)',
                            'fr'   => '프랑스어 (Fr)',
                            'de'   => '독일어 (De)',
                            'ru'   => '러시아어 (Ru)'
                        ];
                        ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-primary mb-1">추가 언어 1</label>
                                <select name="ui[multilingual_lang1]" id="multilingual_lang1" class="form-select form-select-sm">
                                    <?php foreach ($supported_langs as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo (($ui['multilingual_lang1'] ?? 'en') === $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-primary mb-1">추가 언어 2</label>
                                <select name="ui[multilingual_lang2]" id="multilingual_lang2" class="form-select form-select-sm">
                                    <?php foreach ($supported_langs as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo (($ui['multilingual_lang2'] ?? 'none') === $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">기본 정보 저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [신규 모달] 텔레그램 알림 설정 수정 -->
<div class="modal fade" id="editTelegramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="telegram-config-form" method="POST" onsubmit="saveTelegramConfig(event)">
            <input type="hidden" name="action" value="save_telegram_config">
            <div class="modal-header bg-info text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-telegram me-2"></i>텔레그램 알림 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-white border shadow-sm p-3 mb-4 bg-light rounded-3">
                    <h6 class="fw-bold d-block mb-2 small text-info">
                        <i class="bi bi-info-circle-fill me-1"></i> 자신의 텔레그램 Chat ID 확인 방법
                    </h6>
                    <ol class="small text-muted ps-3 mb-0" style="line-height: 1.6;">
                        <li>텔레그램 연락처 검색창에 <span class="text-info fw-bold">@MyIdBot</span>을 검색하세요.
                            <a href="https://t.me/myidbot" target="_blank" class="badge bg-info text-decoration-none ms-1">모바일 폰에서 여기를 클릭</a>
                        </li>
                        <li>채팅방 하단의 <span class="fw-bold text-dark">[봇 시작]</span> 버튼을 누릅니다.</li>
                        <li>봇이 알려주는 <span class="text-danger fw-bold">Your own ID: 숫자</span>를 복사해 아래에 붙여넣으세요.</li>
                    </ol>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">텔레그램 Chat ID</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-hash text-muted"></i></span>
                            <input type="text" name="telegram_chat_id" class="form-control border-start-0"
                                placeholder="숫자 ID를 입력하세요" value="<?php echo htmlspecialchars($shop['telegram_chat_id'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-transparent d-none d-md-block">&nbsp;</label>
                        <div class="form-check form-switch d-flex align-items-center justify-content-between card p-2 border-0 shadow-sm flex-row mt-md-0 mt-2" style="min-height: 31px;">
                            <label class="form-check-label small fw-bold text-muted mb-0" for="use_telegram_alert" style="cursor: pointer;">
                                알림
                            </label>
                            <div class="d-flex align-items-center">
                                <input class="form-check-input me-2 mt-0" type="checkbox" role="switch" id="use_telegram_alert"
                                    name="use_telegram_alert" value="Y" <?php echo ($shop['use_telegram_alert'] == 'Y') ? 'checked' : ''; ?>>
                                <label class="form-check-label small fw-bold text-info mb-0" for="use_telegram_alert" style="cursor: pointer;">
                                    ON
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <label class="form-label small fw-bold text-muted d-block text-start">어떤 알림을 받을까요?</label>
                        <div class="d-flex flex-wrap gap-3 p-2 border rounded-3 bg-white shadow-sm">
                            <?php 
                            $saved_alerts2 = $shop['telegram_alert_types'] ?? '';
                            if (empty($saved_alerts2) || $saved_alerts2 === 'order,cancel') {
                                $alert_types_arr = ['order', 'cancel', 'message', 'review'];
                            } else {
                                $alert_types_arr = explode(',', $saved_alerts2);
                            }
                            ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="order" id="typeOrder" <?= in_array('order', $alert_types_arr) ? 'checked' : '' ?> onclick="return false;">
                                <label class="form-check-label small fw-bold text-dark" for="typeOrder">신규 접수 (필수)</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="cancel" id="typeCancel" <?= in_array('cancel', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeCancel">접수 취소</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="message" id="typeMessage" <?= in_array('message', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeMessage">본사 알림</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="review" id="typeReview" <?= in_array('review', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeReview">고객 리뷰</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end pt-3 mt-4 border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill fw-bold px-3 shadow-sm" onclick="testTelegram()"><i class="bi bi-send-fill me-1"></i>테스트 발송</button>
                </div>
                <p class="text-muted small mt-2 mb-0" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>다음과 같은 메시지를 받으면 테레그램 연결이 성공한 것입니다. <br>
                    <strong>🔔 [KShops24 테스트 알림] 정상적으로 연동되었습니다! 앞으로 KShops24의 모든 알림은 이곳으로 전송됩니다.</strong>
                </p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-info w-100 py-3 fw-bold rounded-pill shadow text-white">설정 저장하기</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="kakaoChannelHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-dots-fill me-2"></i>카카오톡 채널 완벽 활용 가이드</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-3">1. 매장 운영에 꼭 필요한 이유</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <p class="small mb-1 fw-bold text-primary"><i class="bi bi-megaphone me-1"></i> 강력 마케팅 도구</p>
                                <p class="small text-muted mb-0">일반 카톡은 단체 발송이 어렵지만, 채널은 모든 친구에게 이벤트 알림과 쿠폰을 단 한 번에 보낼 수 있습니다.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <p class="small mb-1 fw-bold text-primary"><i class="bi bi-clock-history me-1"></i> 24시간 자동 응답</p>
                                <p class="small text-muted mb-0">자주 묻는 질문(위치, 메뉴, 예약방법)을 버튼식 메뉴로 등록하면 답변 업무가 80% 이상 줄어듭니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-3">2. 5분 만에 채널 만들고 ID 가져오기</h6>
                    <div class="bg-light p-3 rounded-3">
                        <div class="d-flex mb-3">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">1</span>
                            <div>
                                <p class="small mb-1 fw-bold">채널 관리자 센터 접속 및 개설</p>
                                <p class="small text-muted mb-0"><a href="https://center-pf.kakao.com/" target="_blank" class="text-decoration-none fw-bold text-primary">여기</a>를 클릭해 카카오 계정으로 로그인한 뒤, 새 채널을 만드세요.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">2</span>
                            <div>
                                <p class="small mb-1 fw-bold">검색용 아이디 설정 (중요!)</p>
                                <p class="small text-muted mb-0">[관리] → [상세설정] 메뉴에서 '검색용 아이디'를 설정하세요. 이 아이디가 홈페이지 상담과 연동됩니다.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-0">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">3</span>
                            <div>
                                <p class="small mb-1 fw-bold">채널 공개 설정</p>
                                <p class="small text-muted mb-0">[프로필 설정]에서 <strong>'채널 공개'</strong>와 <strong>'검색 허용'</strong>을 반드시 <strong>ON</strong>으로 켜주셔야 고객이 접속할 수 있습니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info border-0 mb-0">
                    <h6 class="fw-bold small"><i class="bi bi-info-circle-fill me-2"></i>홈페이지 입력 팁</h6>
                    <ul class="small mb-0 mt-2 text-dark">
                        <li>입력창에는 <strong>@를 포함한 아이디</strong>를 입력해 주세요. (예: @필리핀맛집24)</li>
                        <li>비즈니스 채널로 인증받지 않아도 기본 상담 기능은 즉시 사용 가능합니다.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">닫기</button>
                <a href="https://center-pf.kakao.com/" target="_blank" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold">관리자 센터 바로가기</a>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * 요일별 휴무 체크 시 시간 입력 비활성화
     */
    function toggleBhInputs(day) {
        const isClosed = document.getElementById('bh_closed_' + day).checked;
        const openInput = document.getElementById('bh_open_' + day);
        const closeInput = document.getElementById('bh_close_' + day);

        openInput.readOnly = isClosed;
        closeInput.readOnly = isClosed;

        if (isClosed) {
            openInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            openInput.style.pointerEvents = 'none';
            openInput.tabIndex = -1;
            closeInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            closeInput.style.pointerEvents = 'none';
            closeInput.tabIndex = -1;
        } else {
            openInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            openInput.style.pointerEvents = 'auto';
            openInput.removeAttribute('tabindex');
            closeInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            closeInput.style.pointerEvents = 'auto';
            closeInput.removeAttribute('tabindex');
        }
    }

    /**
     * 월요일에 입력한 영업시간을 화~일요일에 일괄 복사
     */
    function applyMondayToAll() {
        const monOpen = document.getElementById('bh_open_mon').value;
        const monClose = document.getElementById('bh_close_mon').value;
        const days = ['tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        days.forEach(d => {
            document.getElementById('bh_open_' + d).value = monOpen;
            document.getElementById('bh_close_' + d).value = monClose;
        });
        alert('월요일 영업시간이 모든 요일에 적용되었습니다. (휴무 설정은 유지됩니다)');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#editInfoModal .check-dup').forEach(input => {
            input.addEventListener('change', function() {
                const field = this.name;
                const value = this.value.trim();
                const target = this;
                if (!value) return;
                fetch(`manage_shop.php?check_field=${field}&value=${encodeURIComponent(value)}`)
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === 'duplicate') {
                            alert('이미 다른 상점에서 사용하는 정보입니다.');
                            target.value = '';
                            setTimeout(() => target.focus(), 10);
                        }
                    });
            });
        });
    });

    /**
     * [UX] 다국어 지원 옵션 토글
     */
    function toggleMultilingualOptions() {
        const isChecked = document.getElementById('is_multilingual').checked;
        const optionsWrap = document.getElementById('multilingual-options');
        if (optionsWrap) {
            optionsWrap.style.display = isChecked ? 'block' : 'none';
        }
    }

    /**
     * [UX 개선] 상점 기본 정보 수정 시 새로고침 없는 실시간 텍스트 반영
     */
    async function handleEditInfoSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';

        const formData = new FormData(form);
        // AJAX 백엔드 응답을 유도하기 위한 플래그 삽입
        formData.append('ajax_update', '1');

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                // 모달 닫기
                const modal = bootstrap.Modal.getInstance(document.getElementById('editInfoModal'));
                if (modal) modal.hide();

                // [수정] 데이터 변경 후 전체 페이지 새로고침
                location.reload();
            } else {
                alert('오류 발생: ' + result.message);
            }
        } catch (err) {
            alert('서버 통신 중 오류가 발생했습니다.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    /**
     * 관리자 비밀번호 변경 AJAX 처리
     */
    async function handlePasswordChange(e) {
        e.preventDefault();

        const form = e.target;
        const errorDiv = document.getElementById('pw-error-msg');
        const pw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

        // 초기화
        errorDiv.classList.add('d-none');
        errorDiv.innerText = '';

        // 클라이언트 측 유효성 검사
        if (!regex.test(pw)) {
            errorDiv.innerText = '비밀번호는 대/소문자 및 숫자 포함 6자 이상이어야 합니다.';
            errorDiv.classList.remove('d-none');
            return;
        }
        if (pw !== confirm) {
            errorDiv.innerText = '새 비밀번호가 서로 일치하지 않습니다.';
            errorDiv.classList.remove('d-none');
            return;
        }

        // 서버 전송 (AJAX)
        const formData = new FormData(form);
        formData.append('update_shop', '1');
        formData.append('ajax_pw_change', '1');

        try {
            const response = await fetch('manage_shop.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                if (modal) modal.hide();
                form.reset(); // 성공 시 입력 폼 초기화
            } else {
                // 에러 발생 시 모달창을 유지하고 에러 내용 표시
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
                document.getElementById('current_password').focus();
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    }

    /**
     * 임시 비밀번호 발송 처리 (AJAX)
     */
    async function sendTempPw() {
        if (!confirm("등록된 관리자 이메일로 임시 비밀번호를 발송하시겠습니까?")) return;

        const btn = document.getElementById('btn-send-temp-pw');
        const errorDiv = document.getElementById('pw-error-msg');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>발송 중...';
        errorDiv.classList.add('d-none');

        const formData = new FormData();
        formData.append('update_shop', '1');
        formData.append('ajax_send_temp_pw', '1');

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                document.getElementById('current_password').focus();
            } else {
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function testTelegram() {
        const chatId = document.querySelector('#telegram-config-form input[name="telegram_chat_id"]').value.trim();

        if (!chatId) {
            alert('테스트를 위해 Chat ID를 먼저 입력해주세요.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'test_telegram');
        formData.append('telegram_chat_id', chatId);

        try {
            // [버그 수정] AJAX 요청이 올바른 모듈(manage_shop_info.php)로 향하도록 파라미터(?pg=shop) 추가
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                location.reload();
            }
        } catch (error) {
            alert('서버 통신 중 오류가 발생했습니다.');
        }
    }

    // [기능 추가] 텔레그램 설정만 별도로 저장하는 비동기 함수 구현
    async function saveTelegramConfig(e) {
        e.preventDefault();
        const form = document.getElementById('telegram-config-form');
        const btn = form.querySelector('button[type="submit"]');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'save_telegram_config');
        formData.append('telegram_chat_id', form.querySelector('input[name="telegram_chat_id"]').value);
        formData.append('use_telegram_alert', form.querySelector('input[name="use_telegram_alert"]').checked ? 'Y' : 'N');

        form.querySelectorAll('input[name="alert_types[]"]:checked').forEach(cb => {
            formData.append('alert_types[]', cb.value);
        });

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                location.reload(); // 성공 시 변경된 설정을 화면에 즉시 반영하기 위해 새로고침
            }
        } catch (error) {
            alert('서버 통신 중 오류가 발생했습니다.');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    /**
     * [신규] TIN 번호 입력 시 자동 하이픈(-) 추가
     * @param {HTMLInputElement} input - 입력 필드 요소
     */
    function formatTinInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';
        if (val.length > 9) {
            result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 9) + '-' + val.slice(9, 12);
        } else if (val.length > 6) {
            result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6);
        } else if (val.length > 3) {
            result = val.slice(0, 3) + '-' + val.slice(3);
        } else {
            result = val;
        }
        input.value = result;
    }
=======
<?php
/**
 * KShops24 상점 기본 정보 설정 모달 및 스크립트 분리 (manage_shop_info_modals.php)
 */
if (!isset($shop_id)) exit; // 직접 접근 방지
?>
<!-- 모달 및 스크립트 모음 -->
<!-- 관리자 암호 변경 모달 -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="pwChangeForm" onsubmit="handlePasswordChange(event)">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock me-2"></i>관리자 암호 변경</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 에러 메시지 출력 영역 -->
                <div id="pw-error-msg" class="alert alert-danger d-none small mb-3 border-0 shadow-sm"></div>

                <div class="alert alert-warning border-0 small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>보안 규칙:</strong> 대/소문자 및 숫자 포함 6글자 이상 입력해 주세요.
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-primary mb-0">현재 비밀번호</label>
                        <button type="button" id="btn-send-temp-pw" class="btn btn-sm btn-outline-secondary py-0 px-2 shadow-sm" style="font-size: 0.7rem;" onclick="sendTempPw()"><i class="bi bi-envelope-paper me-1"></i>비밀번호를 잊으셨나요?</button>
                    </div>
                    <input type="password" name="current_password" id="current_password" class="form-control border-primary border-opacity-25" placeholder="기존 비밀번호 입력 (또는 발급받은 임시 비번)" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">새 비밀번호</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">비밀번호 확인</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow">암호 변경하기</button>
            </div>
        </form>
    </div>
</div>

<!-- 상점 기본 정보 수정 모달 -->
<div class="modal fade" id="editInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" action="manage_shop.php?pg=shop" onsubmit="handleEditInfoSubmit(event)">
            <input type="hidden" name="update_shop" value="1">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>상점 기본 정보 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">* 표시가 있는 항목들은 타 상점과 중복될 수 없습니다.</p>
                <div class="mb-2">
                    <label class="form-label small fw-bold">관리자 이름 (한글)</label>
                    <input type="text" name="manager_name" class="form-control" value="<?php echo htmlspecialchars($shop['manager_name']); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">관리자 이름 (English)</label>
                    <input type="text" name="manager_name_en" class="form-control" value="<?php echo htmlspecialchars($shop['manager_name_en']); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">카카오톡 ID *</label>
                    <input type="text" name="kakao_id" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['kakao_id'] ?? ''); ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">휴대전화 (Mobile) *</label>
                    <input type="text" name="phone_mobile" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['phone_mobile']); ?>" required oninput="formatPhoneInput(this)" maxlength="13">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">카카오 채널 ID * </label>
                    <input type="text" name="kakao_channel_id" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['kakao_channel_id'] ?? ''); ?>" placeholder="예: @KShops24">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">유선전화 (Landline) *</label>
                    <input type="text" name="phone_landline" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['phone_landline'] ?? ''); ?>" oninput="formatPhoneInput(this)" maxlength="12">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">페이스북 URL *</label>
                    <input type="url" name="facebook_url" class="form-control check-dup" value="<?php echo htmlspecialchars($shop['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/...">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">상점 실제 주소</label>
                    <input type="text" name="physical_address" class="form-control" value="<?php echo htmlspecialchars($shop['physical_address'] ?? ''); ?>" placeholder="예: 매장의 물리적 위치">
                </div>
                <?php
                $bh = $shop['business_hours'] ?? '';
                $bh_data = [];
                $days_map = ['mon' => '월요일', 'tue' => '화요일', 'wed' => '수요일', 'thu' => '목요일', 'fri' => '금요일', 'sat' => '토요일', 'sun' => '일요일'];

                if (!empty($bh) && ($bh[0] === '{' || $bh[0] === '[')) {
                    $bh_data = json_decode($bh, true);
                } else {
                    // 기존 09:00~18:00 포맷 하위 호환성
                    $parts = explode('~', $bh);
                    $bh_s = trim($parts[0] ?? '');
                    $bh_e = trim($parts[1] ?? '');
                    foreach ($days_map as $k => $v) {
                        $bh_data[$k] = ['open' => $bh_s, 'close' => $bh_e, 'closed' => false];
                    }
                }
                ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-3">영업 시간 (요일별)</label>
                    <p <?php echo UI_INFO_SM_LABEL;?>> 체크박스를 클릭하면 해당 요일을 <strong style="color: red;">휴무일</strong>로 지정할 수 있습니다.</p>
                    <div class="bg-light p-3 rounded-3 border">
                        <?php foreach ($days_map as $k => $day_name):
                            $day_info = $bh_data[$k] ?? ['open' => '', 'close' => '', 'closed' => false];
                            $is_closed = !empty($day_info['closed']);
                            $closed_class = $is_closed ? 'bg-secondary bg-opacity-10 text-muted' : '';
                            $closed_attr = $is_closed ? 'readonly tabindex="-1" style="pointer-events: none;"' : '';
                        ?>
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-4 col-md-3">
                                    <div class="form-check form-switch mb-0 d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" name="bh[<?php echo $k; ?>][closed]" id="bh_closed_<?php echo $k; ?>" value="1" <?php echo $is_closed ? 'checked' : ''; ?> onchange="toggleBhInputs('<?php echo $k; ?>')">
                                        <label class="form-check-label small fw-bold" for="bh_closed_<?php echo $k; ?>" style="cursor: pointer;"><?php echo $day_name; ?></label>
                                    </div>
                                </div>
                                <div class="col-8 col-md-9">
                                    <div class="input-group input-group-sm">
                                        <input type="time" name="bh[<?php echo $k; ?>][open]" id="bh_open_<?php echo $k; ?>" class="form-control <?php echo $closed_class; ?>" value="<?php echo htmlspecialchars($day_info['open'] ?? ''); ?>" <?php echo $closed_attr; ?>>
                                        <span class="input-group-text bg-white border-start-0 border-end-0 text-muted">~</span>
                                        <input type="time" name="bh[<?php echo $k; ?>][close]" id="bh_close_<?php echo $k; ?>" class="form-control <?php echo $closed_class; ?>" value="<?php echo htmlspecialchars($day_info['close'] ?? ''); ?>" <?php echo $closed_attr; ?>>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-end mt-3 border-top pt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill shadow-sm" onclick="applyMondayToAll()">
                                <i class="bi bi-files me-1"></i>월요일 시간을 매일 동일하게 적용
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="my-4 opacity-25">

                <?php if (($shop['category'] ?? 'fnb') === 'fnb'): ?>
                    <!-- 배달 및 매장픽업 지원 여부 -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold"><i class="bi bi-truck text-primary me-1"></i> 배달 지원 여부</label>
                        <p <?php echo UI_INFO_SM_LABEL;?>> <strong style="color: red;">배달 및 매장픽업 가능</strong>을 선택하면 배달/픽업 관련 기능들이 활성화 됩니다.</p>

                        <div class="d-flex gap-4 bg-light p-3 rounded-3 border">
                            <div class="form-check form-switch m-0 d-flex align-items-center">
                                <input type="hidden" name="is_delivery_available" value="0">
                                <input class="form-check-input mt-0 me-2" type="checkbox" name="is_delivery_available" id="is_delivery_available" value="1" <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'checked' : ''; ?> style="cursor: pointer;" onchange="document.getElementById('is_pickup_available').value = this.checked ? 1 : 0;">
                                <label class="form-check-label small fw-bold" for="is_delivery_available" style="cursor: pointer;">배달 및 매장픽업 가능</label>
                            </div>
                            <input type="hidden" name="is_pickup_available" id="is_pickup_available" value="<?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? '1' : '0'; ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 상점 기본 화폐 설정 -->
                <div class="mb-4">
                    <label class="form-label small fw-bold"><i class="bi bi-cash-coin text-primary me-1"></i> 상점 기본 화폐 단위</label>
                    <select name="ui[currency]" class="form-select form-select-sm">
                        <option value="PHP" <?php echo (($ui['currency'] ?? 'PHP') === 'PHP') ? 'selected' : ''; ?>>필리핀 페소 (PHP, ₱)</option>
                        <option value="KRW" <?php echo (($ui['currency'] ?? 'PHP') === 'KRW') ? 'selected' : ''; ?>>대한민국 원 (KRW, ₩)</option>
                        <option value="USD" <?php echo (($ui['currency'] ?? 'PHP') === 'USD') ? 'selected' : ''; ?>>미국 달러 (USD, $)</option>
                        <option value="EUR" <?php echo (($ui['currency'] ?? 'PHP') === 'EUR') ? 'selected' : ''; ?>>유로 (EUR, €)</option>
                        <option value="JPY" <?php echo (($ui['currency'] ?? 'PHP') === 'JPY') ? 'selected' : ''; ?>>일본 엔 (JPY, ¥)</option>
                        <option value="CNY" <?php echo (($ui['currency'] ?? 'PHP') === 'CNY') ? 'selected' : ''; ?>>중국 위안 (CNY, ¥)</option>
                        <option value="GBP" <?php echo (($ui['currency'] ?? 'PHP') === 'GBP') ? 'selected' : ''; ?>>영국 파운드 (GBP, £)</option>
                        <option value="AUD" <?php echo (($ui['currency'] ?? 'PHP') === 'AUD') ? 'selected' : ''; ?>>호주 달러 (AUD, A$)</option>
                        <option value="CAD" <?php echo (($ui['currency'] ?? 'PHP') === 'CAD') ? 'selected' : ''; ?>>캐나다 달러 (CAD, C$)</option>
                        <option value="SGD" <?php echo (($ui['currency'] ?? 'PHP') === 'SGD') ? 'selected' : ''; ?>>싱가포르 달러 (SGD, S$)</option>
                        <option value="HKD" <?php echo (($ui['currency'] ?? 'PHP') === 'HKD') ? 'selected' : ''; ?>>홍콩 달러 (HKD, HK$)</option>
                        <option value="TWD" <?php echo (($ui['currency'] ?? 'PHP') === 'TWD') ? 'selected' : ''; ?>>대만 달러 (TWD, NT$)</option>
                        <option value="VND" <?php echo (($ui['currency'] ?? 'PHP') === 'VND') ? 'selected' : ''; ?>>베트남 동 (VND, ₫)</option>
                        <option value="THB" <?php echo (($ui['currency'] ?? 'PHP') === 'THB') ? 'selected' : ''; ?>>태국 바트 (THB, ฿)</option>
                        <option value="IDR" <?php echo (($ui['currency'] ?? 'PHP') === 'IDR') ? 'selected' : ''; ?>>인도네시아 루피아 (IDR, Rp)</option>
                        <option value="MYR" <?php echo (($ui['currency'] ?? 'PHP') === 'MYR') ? 'selected' : ''; ?>>말레이시아 링깃 (MYR, RM)</option>
                    </select>
                </div>

                <!-- 다국어 지원 설정 -->
                <div class="bg-light p-4 rounded-4 border border-primary border-opacity-25 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-globe me-2 text-primary"></i>다국어 지원</h6>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">한국어(기본) 외에 외국어 2개의 외국어 선택 기능을 제공합니다.<br>
                                <strong>다국어 지원 기능을 OFF하면 기본 언어인 한국어가 표시됩니다.</strong></small>

                        </div>
                        <div class="form-check form-switch m-0 fs-4">
                            <input type="hidden" name="ui[is_multilingual]" value="0">
                            <input class="form-check-input" type="checkbox" name="ui[is_multilingual]" id="is_multilingual" value="1" <?php echo (($ui['is_multilingual'] ?? 0) == 1) ? 'checked' : ''; ?> style="cursor: pointer;" onchange="toggleMultilingualOptions()">
                        </div>
                    </div>

                    <div id="multilingual-options" class="mt-3 pt-3 border-top border-primary border-opacity-25" style="<?php echo (($ui['is_multilingual'] ?? 0) == 1) ? 'display: block;' : 'display: none;'; ?>">
                        <?php
                        $supported_langs = [
                            'none' => '사용 안 함',
                            'en'   => '영어 (En)',
                            'tl'   => '따갈로그어 (Tl)',
                            'zh'   => '중국어 (Zh)',
                            'ja'   => '일본어 (Ja)',
                            'vi'   => '베트남어 (Vi)',
                            'th'   => '태국어 (Th)',
                            'id'   => '인도네시아어 (Id)',
                            'ms'   => '말레이시아어 (Ms)',
                            'es'   => '스페인어 (Es)',
                            'fr'   => '프랑스어 (Fr)',
                            'de'   => '독일어 (De)',
                            'ru'   => '러시아어 (Ru)'
                        ];
                        ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-primary mb-1">추가 언어 1</label>
                                <select name="ui[multilingual_lang1]" id="multilingual_lang1" class="form-select form-select-sm">
                                    <?php foreach ($supported_langs as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo (($ui['multilingual_lang1'] ?? 'en') === $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-primary mb-1">추가 언어 2</label>
                                <select name="ui[multilingual_lang2]" id="multilingual_lang2" class="form-select form-select-sm">
                                    <?php foreach ($supported_langs as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo (($ui['multilingual_lang2'] ?? 'none') === $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="update_shop" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">기본 정보 저장하기</button>
            </div>
        </form>
    </div>
</div>

<!-- [신규 모달] 텔레그램 알림 설정 수정 -->
<div class="modal fade" id="editTelegramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="telegram-config-form" method="POST" onsubmit="saveTelegramConfig(event)">
            <input type="hidden" name="action" value="save_telegram_config">
            <div class="modal-header bg-info text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-telegram me-2"></i>텔레그램 알림 설정</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-white border shadow-sm p-3 mb-4 bg-light rounded-3">
                    <h6 class="fw-bold d-block mb-2 small text-info">
                        <i class="bi bi-info-circle-fill me-1"></i> 자신의 텔레그램 Chat ID 확인 방법
                    </h6>
                    <ol class="small text-muted ps-3 mb-0" style="line-height: 1.6;">
                        <li>텔레그램 연락처 검색창에 <span class="text-info fw-bold">@MyIdBot</span>을 검색하세요.
                            <a href="https://t.me/myidbot" target="_blank" class="badge bg-info text-decoration-none ms-1">모바일 폰에서 여기를 클릭</a>
                        </li>
                        <li>채팅방 하단의 <span class="fw-bold text-dark">[봇 시작]</span> 버튼을 누릅니다.</li>
                        <li>봇이 알려주는 <span class="text-danger fw-bold">Your own ID: 숫자</span>를 복사해 아래에 붙여넣으세요.</li>
                    </ol>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">텔레그램 Chat ID</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-hash text-muted"></i></span>
                            <input type="text" name="telegram_chat_id" class="form-control border-start-0"
                                placeholder="숫자 ID를 입력하세요" value="<?php echo htmlspecialchars($shop['telegram_chat_id'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-transparent d-none d-md-block">&nbsp;</label>
                        <div class="form-check form-switch d-flex align-items-center justify-content-between card p-2 border-0 shadow-sm flex-row mt-md-0 mt-2" style="min-height: 31px;">
                            <label class="form-check-label small fw-bold text-muted mb-0" for="use_telegram_alert" style="cursor: pointer;">
                                알림
                            </label>
                            <div class="d-flex align-items-center">
                                <input class="form-check-input me-2 mt-0" type="checkbox" role="switch" id="use_telegram_alert"
                                    name="use_telegram_alert" value="Y" <?php echo ($shop['use_telegram_alert'] == 'Y') ? 'checked' : ''; ?>>
                                <label class="form-check-label small fw-bold text-info mb-0" for="use_telegram_alert" style="cursor: pointer;">
                                    ON
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <label class="form-label small fw-bold text-muted d-block text-start">어떤 알림을 받을까요?</label>
                        <div class="d-flex flex-wrap gap-3 p-2 border rounded-3 bg-white shadow-sm">
                            <?php 
                            $saved_alerts2 = $shop['telegram_alert_types'] ?? '';
                            if (empty($saved_alerts2) || $saved_alerts2 === 'order,cancel') {
                                $alert_types_arr = ['order', 'cancel', 'message', 'review'];
                            } else {
                                $alert_types_arr = explode(',', $saved_alerts2);
                            }
                            ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="order" id="typeOrder" <?= in_array('order', $alert_types_arr) ? 'checked' : '' ?> onclick="return false;">
                                <label class="form-check-label small fw-bold text-dark" for="typeOrder">신규 접수 (필수)</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="cancel" id="typeCancel" <?= in_array('cancel', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeCancel">접수 취소</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="message" id="typeMessage" <?= in_array('message', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeMessage">본사 알림</label>
                            </div>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="alert_types[]" value="review" id="typeReview" <?= in_array('review', $alert_types_arr) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-dark" for="typeReview">고객 리뷰</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end pt-3 mt-4 border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill fw-bold px-3 shadow-sm" onclick="testTelegram()"><i class="bi bi-send-fill me-1"></i>테스트 발송</button>
                </div>
                <p class="text-muted small mt-2 mb-0" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>다음과 같은 메시지를 받으면 테레그램 연결이 성공한 것입니다. <br>
                    <strong>🔔 [KShops24 테스트 알림] 정상적으로 연동되었습니다! 앞으로 KShops24의 모든 알림은 이곳으로 전송됩니다.</strong>
                </p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-info w-100 py-3 fw-bold rounded-pill shadow text-white">설정 저장하기</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="kakaoChannelHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-dots-fill me-2"></i>카카오톡 채널 완벽 활용 가이드</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-3">1. 매장 운영에 꼭 필요한 이유</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <p class="small mb-1 fw-bold text-primary"><i class="bi bi-megaphone me-1"></i> 강력 마케팅 도구</p>
                                <p class="small text-muted mb-0">일반 카톡은 단체 발송이 어렵지만, 채널은 모든 친구에게 이벤트 알림과 쿠폰을 단 한 번에 보낼 수 있습니다.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <p class="small mb-1 fw-bold text-primary"><i class="bi bi-clock-history me-1"></i> 24시간 자동 응답</p>
                                <p class="small text-muted mb-0">자주 묻는 질문(위치, 메뉴, 예약방법)을 버튼식 메뉴로 등록하면 답변 업무가 80% 이상 줄어듭니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-start border-4 border-primary ps-2 mb-3">2. 5분 만에 채널 만들고 ID 가져오기</h6>
                    <div class="bg-light p-3 rounded-3">
                        <div class="d-flex mb-3">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">1</span>
                            <div>
                                <p class="small mb-1 fw-bold">채널 관리자 센터 접속 및 개설</p>
                                <p class="small text-muted mb-0"><a href="https://center-pf.kakao.com/" target="_blank" class="text-decoration-none fw-bold text-primary">여기</a>를 클릭해 카카오 계정으로 로그인한 뒤, 새 채널을 만드세요.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">2</span>
                            <div>
                                <p class="small mb-1 fw-bold">검색용 아이디 설정 (중요!)</p>
                                <p class="small text-muted mb-0">[관리] → [상세설정] 메뉴에서 '검색용 아이디'를 설정하세요. 이 아이디가 홈페이지 상담과 연동됩니다.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-0">
                            <span class="badge bg-primary rounded-circle me-3" style="width:24px; height:24px;">3</span>
                            <div>
                                <p class="small mb-1 fw-bold">채널 공개 설정</p>
                                <p class="small text-muted mb-0">[프로필 설정]에서 <strong>'채널 공개'</strong>와 <strong>'검색 허용'</strong>을 반드시 <strong>ON</strong>으로 켜주셔야 고객이 접속할 수 있습니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info border-0 mb-0">
                    <h6 class="fw-bold small"><i class="bi bi-info-circle-fill me-2"></i>홈페이지 입력 팁</h6>
                    <ul class="small mb-0 mt-2 text-dark">
                        <li>입력창에는 <strong>@를 포함한 아이디</strong>를 입력해 주세요. (예: @필리핀맛집24)</li>
                        <li>비즈니스 채널로 인증받지 않아도 기본 상담 기능은 즉시 사용 가능합니다.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">닫기</button>
                <a href="https://center-pf.kakao.com/" target="_blank" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold">관리자 센터 바로가기</a>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * 요일별 휴무 체크 시 시간 입력 비활성화
     */
    function toggleBhInputs(day) {
        const isClosed = document.getElementById('bh_closed_' + day).checked;
        const openInput = document.getElementById('bh_open_' + day);
        const closeInput = document.getElementById('bh_close_' + day);

        openInput.readOnly = isClosed;
        closeInput.readOnly = isClosed;

        if (isClosed) {
            openInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            openInput.style.pointerEvents = 'none';
            openInput.tabIndex = -1;
            closeInput.classList.add('bg-secondary', 'bg-opacity-10', 'text-muted');
            closeInput.style.pointerEvents = 'none';
            closeInput.tabIndex = -1;
        } else {
            openInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            openInput.style.pointerEvents = 'auto';
            openInput.removeAttribute('tabindex');
            closeInput.classList.remove('bg-secondary', 'bg-opacity-10', 'text-muted');
            closeInput.style.pointerEvents = 'auto';
            closeInput.removeAttribute('tabindex');
        }
    }

    /**
     * 월요일에 입력한 영업시간을 화~일요일에 일괄 복사
     */
    function applyMondayToAll() {
        const monOpen = document.getElementById('bh_open_mon').value;
        const monClose = document.getElementById('bh_close_mon').value;
        const days = ['tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        days.forEach(d => {
            document.getElementById('bh_open_' + d).value = monOpen;
            document.getElementById('bh_close_' + d).value = monClose;
        });
        alert('월요일 영업시간이 모든 요일에 적용되었습니다. (휴무 설정은 유지됩니다)');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#editInfoModal .check-dup').forEach(input => {
            input.addEventListener('change', function() {
                const field = this.name;
                const value = this.value.trim();
                const target = this;
                if (!value) return;
                fetch(`manage_shop.php?check_field=${field}&value=${encodeURIComponent(value)}`)
                    .then(res => res.text())
                    .then(data => {
                        if (data.trim() === 'duplicate') {
                            alert('이미 다른 상점에서 사용하는 정보입니다.');
                            target.value = '';
                            setTimeout(() => target.focus(), 10);
                        }
                    });
            });
        });
    });

    /**
     * [UX] 다국어 지원 옵션 토글
     */
    function toggleMultilingualOptions() {
        const isChecked = document.getElementById('is_multilingual').checked;
        const optionsWrap = document.getElementById('multilingual-options');
        if (optionsWrap) {
            optionsWrap.style.display = isChecked ? 'block' : 'none';
        }
    }

    /**
     * [UX 개선] 상점 기본 정보 수정 시 새로고침 없는 실시간 텍스트 반영
     */
    async function handleEditInfoSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';

        const formData = new FormData(form);
        // AJAX 백엔드 응답을 유도하기 위한 플래그 삽입
        formData.append('ajax_update', '1');

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                // 모달 닫기
                const modal = bootstrap.Modal.getInstance(document.getElementById('editInfoModal'));
                if (modal) modal.hide();

                // [수정] 데이터 변경 후 전체 페이지 새로고침
                location.reload();
            } else {
                alert('오류 발생: ' + result.message);
            }
        } catch (err) {
            alert('서버 통신 중 오류가 발생했습니다.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    /**
     * 관리자 비밀번호 변경 AJAX 처리
     */
    async function handlePasswordChange(e) {
        e.preventDefault();

        const form = e.target;
        const errorDiv = document.getElementById('pw-error-msg');
        const pw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

        // 초기화
        errorDiv.classList.add('d-none');
        errorDiv.innerText = '';

        // 클라이언트 측 유효성 검사
        if (!regex.test(pw)) {
            errorDiv.innerText = '비밀번호는 대/소문자 및 숫자 포함 6자 이상이어야 합니다.';
            errorDiv.classList.remove('d-none');
            return;
        }
        if (pw !== confirm) {
            errorDiv.innerText = '새 비밀번호가 서로 일치하지 않습니다.';
            errorDiv.classList.remove('d-none');
            return;
        }

        // 서버 전송 (AJAX)
        const formData = new FormData(form);
        formData.append('update_shop', '1');
        formData.append('ajax_pw_change', '1');

        try {
            const response = await fetch('manage_shop.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                if (modal) modal.hide();
                form.reset(); // 성공 시 입력 폼 초기화
            } else {
                // 에러 발생 시 모달창을 유지하고 에러 내용 표시
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
                document.getElementById('current_password').focus();
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    }

    /**
     * 임시 비밀번호 발송 처리 (AJAX)
     */
    async function sendTempPw() {
        if (!confirm("등록된 관리자 이메일로 임시 비밀번호를 발송하시겠습니까?")) return;

        const btn = document.getElementById('btn-send-temp-pw');
        const errorDiv = document.getElementById('pw-error-msg');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>발송 중...';
        errorDiv.classList.add('d-none');

        const formData = new FormData();
        formData.append('update_shop', '1');
        formData.append('ajax_send_temp_pw', '1');

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(result.message);
                document.getElementById('current_password').focus();
            } else {
                errorDiv.innerText = result.message;
                errorDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorDiv.innerText = '서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function testTelegram() {
        const chatId = document.querySelector('#telegram-config-form input[name="telegram_chat_id"]').value.trim();

        if (!chatId) {
            alert('테스트를 위해 Chat ID를 먼저 입력해주세요.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'test_telegram');
        formData.append('telegram_chat_id', chatId);

        try {
            // [버그 수정] AJAX 요청이 올바른 모듈(manage_shop_info.php)로 향하도록 파라미터(?pg=shop) 추가
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                location.reload();
            }
        } catch (error) {
            alert('서버 통신 중 오류가 발생했습니다.');
        }
    }

    // [기능 추가] 텔레그램 설정만 별도로 저장하는 비동기 함수 구현
    async function saveTelegramConfig(e) {
        e.preventDefault();
        const form = document.getElementById('telegram-config-form');
        const btn = form.querySelector('button[type="submit"]');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'save_telegram_config');
        formData.append('telegram_chat_id', form.querySelector('input[name="telegram_chat_id"]').value);
        formData.append('use_telegram_alert', form.querySelector('input[name="use_telegram_alert"]').checked ? 'Y' : 'N');

        form.querySelectorAll('input[name="alert_types[]"]:checked').forEach(cb => {
            formData.append('alert_types[]', cb.value);
        });

        try {
            const response = await fetch('manage_shop.php?pg=shop', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.status === 'success') {
                location.reload(); // 성공 시 변경된 설정을 화면에 즉시 반영하기 위해 새로고침
            }
        } catch (error) {
            alert('서버 통신 중 오류가 발생했습니다.');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    /**
     * [신규] TIN 번호 입력 시 자동 하이픈(-) 추가
     * @param {HTMLInputElement} input - 입력 필드 요소
     */
    function formatTinInput(input) {
        let val = input.value.replace(/\D/g, ''); // 숫자만 남기기
        let result = '';
        if (val.length > 9) {
            result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6, 9) + '-' + val.slice(9, 12);
        } else if (val.length > 6) {
            result = val.slice(0, 3) + '-' + val.slice(3, 6) + '-' + val.slice(6);
        } else if (val.length > 3) {
            result = val.slice(0, 3) + '-' + val.slice(3);
        } else {
            result = val;
        }
        input.value = result;
    }
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>