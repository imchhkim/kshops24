<?php
/**
 * [탭 파일] 상점 정보 관리
 * 위치: admin/manage_shop_tab_info.php
 */
if (!isset($pdo)) exit;

// =========================================================================
// [1] Action 및 Data 로딩
// (정보 업데이트 등의 기능은 manage_shop.php 메인 로직에 통합되어 있음)
// =========================================================================

// =========================================================================
// [3] View 렌더링
// =========================================================================
if ($tab_mode === 'view'):
?>
    <div class="row g-4">
        <!-- 1. 기본 정보 -->
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-person-badge me-2"></i>기본 정보 및 계정</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editBasicModal"><i class="bi bi-pencil me-1"></i>수정</button>
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-5 text-muted">업종 / 상태</dt>
                <dd class="col-sm-7"><span class="badge bg-primary fw-normal"><?= htmlspecialchars($s['category'] ?? '-') ?></span> / <span class="badge bg-<?= ($s['status'] == 'active' ? 'success' : 'warning') ?> fw-normal"><?= strtoupper($s['status']) ?></span></dd>
                <dt class="col-sm-5 text-muted">샘플 상점 여부</dt>
                <dd class="col-sm-7"><?= ($s['is_sample_shop'] ?? 'n') === 'y' ? '<span class="badge bg-danger">샘플 상점 (Y)</span>' : '<span class="badge bg-secondary opacity-50">일반 상점 (N)</span>' ?></dd>
                <dt class="col-sm-5 text-muted">상점 주소</dt>
                <dd class="col-sm-7"><a href="/<?= $s['subdomain'] ?>" target="_blank" class="text-decoration-none fw-bold">/<?= htmlspecialchars($s['subdomain']) ?> <i class="bi bi-box-arrow-up-right ms-1"></i></a></dd>
                <dt class="col-sm-5 text-muted">상점명</dt>
                <dd class="col-sm-7 fw-bold"><?= htmlspecialchars($s['shop_name']) ?> <span class="text-muted fw-normal">(<?= htmlspecialchars($s['shop_name_en'] ?? '-') ?>)</span></dd>
                <dt class="col-sm-5 text-muted">관리자명</dt>
                <dd class="col-sm-7"><?= htmlspecialchars($s['manager_name'] ?? '-') ?> <span class="text-muted fw-normal">(<?= htmlspecialchars($s['manager_name_en'] ?? '-') ?>)</span></dd>
                <dt class="col-sm-5 text-muted">로그인 이메일</dt>
                <dd class="col-sm-7"><?= htmlspecialchars($s['manager_email']) ?></dd>
                <dt class="col-sm-5 text-muted">연결 도메인</dt>
                <dd class="col-sm-7"><?= htmlspecialchars($s['custom_domain'] ?: '-') ?></dd>
            </dl>
        </div>

        <!-- 2. 연락처 및 위치 -->
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-geo-alt me-2"></i>연락처 및 위치 정보</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editContactModal"><i class="bi bi-pencil me-1"></i>수정</button>
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">휴대전화</dt>
                <dd class="col-sm-8"><?= htmlspecialchars(function_exists('formatPHPhone') && $s['phone_mobile'] ? formatPHPhone($s['phone_mobile']) : ($s['phone_mobile'] ?: '-')) ?></dd>
                <dt class="col-sm-4 text-muted">매장전화</dt>
                <dd class="col-sm-8"><?= htmlspecialchars(function_exists('formatPHPhone') && $s['phone_landline'] ? formatPHPhone($s['phone_landline']) : ($s['phone_landline'] ?: '-')) ?></dd>
                <dt class="col-sm-4 text-muted">카카오톡 ID</dt>
                <dd class="col-sm-8"><?= htmlspecialchars($s['kakao_id'] ?: '-') ?></dd>
                <dt class="col-sm-4 text-muted">텔레그램 설정</dt>
                <dd class="col-sm-8 text-truncate">상태: <?= ($s['use_telegram_alert'] == 'Y') ? '<span class="text-success fw-bold">활성</span>' : '<span class="text-danger fw-bold">비활성</span>' ?><br>Chat ID: <?= htmlspecialchars($s['telegram_chat_id'] ?: '미설정') ?><br></dd>
                <dt class="col-sm-4 text-muted">실제 주소</dt>
                <dd class="col-sm-8"><?= htmlspecialchars($s['physical_address'] ?: '-') ?></dd>
            </dl>
        </div>

        <!-- 3. 운영 정책 -->
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-truck me-2"></i>배달 및 운영 정책</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editDeliveryModal"><i class="bi bi-pencil me-1"></i>수정</button>
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">영업 시간</dt>
                <dd class="col-sm-8"><?php $bh = $s['business_hours'] ?? ''; echo (!empty($bh) && ($bh[0] === '{' || $bh[0] === '[')) ? '<span class="badge bg-light text-primary border border-primary-subtle">고급 설정</span>' : htmlspecialchars($bh ?: '-'); ?></dd>
                <?php if (in_array($s['category'], ['fnb', 'cafe', 'mart'])): ?>
                    <dt class="col-sm-4 text-muted">최소 주문 금액</dt>
                    <dd class="col-sm-8 text-primary fw-bold">₱ <?= number_format((int)$s['min_delivery_amount']) ?></dd>
                    <dt class="col-sm-4 text-muted">배달 지원 여부</dt>
                    <dd class="col-sm-8"><?= ($s['is_delivery_available'] ?? 1) == 1 ? '<span class="text-success fw-bold">가능</span>' : '<span class="text-danger fw-bold">불가</span>' ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- 4. UI 설정 -->
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-display me-2"></i>UI 설정 및 시스템</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editUiModal"><i class="bi bi-pencil me-1"></i>수정</button>
            </div>
            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">스킨 / 폰트</dt>
                <dd class="col-sm-8"><?= htmlspecialchars($s['shop_skin'] ?? 'default') ?> / <?= htmlspecialchars($s['shop_font'] ?? 'Pretendard') ?></dd>
                <dt class="col-sm-4 text-muted">기능 노출 현황</dt>
                <dd class="col-sm-8">
                    <span class="badge <?= ($s['is_show_main_title'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">메인문구</span>
                    <span class="badge <?= ($s['is_show_gallery'] ?? 1) ? 'bg-primary' : 'bg-secondary opacity-50' ?>">갤러리</span>
                </dd>
            </dl>
        </div>

        <div class="col-12 mt-2 mb-2">
            <div class="p-3 bg-white border rounded shadow-sm border-start border-4 border-warning">
                <form method="POST" action="admin_view.php?page=manage_shop&id=<?= $shop_id ?>">
                    <input type="hidden" name="action" value="update_manual_status">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small fw-bold text-muted mb-1">상태 변경</label><select name="manual_status" class="form-select form-select-sm fw-bold <?= $s['status'] === 'active' ? 'text-success' : 'text-danger' ?>"><option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>정상영업 (active)</option><option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>휴점 (inactive)</option></select></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted mb-1">적용 일시</label><input type="datetime-local" name="status_date" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label small fw-bold text-muted mb-1">변경 사유</label><input type="text" name="status_reason" class="form-control form-control-sm" placeholder="예: 관리자 수동 처리, 연체 등" required></div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">발송할 안내 메시지</label>
                        <select name="message_template" class="form-select form-select-sm border-primary">
                            <option value="">(발송 안 함 - 상태만 변경)</option>
                            <?php if (isset($message_templates)) { foreach ($message_templates as $tpl_key => $tpl): ?><option value="<?= htmlspecialchars($tpl_key) ?>"><?= htmlspecialchars($tpl['title']) ?></option><?php endforeach; } ?>
                        </select>
                    </div>
                    <div class="text-end"><button type="submit" class="btn btn-warning btn-sm fw-bold px-4 shadow-sm" onclick="return confirm('설정한 내용으로 상점 상태를 변경하시겠습니까?');"><i class="bi bi-check-lg me-1"></i> 상태 변경 적용하기</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 1] 기본 정보 및 계정 수정 모달 -->
    <div class="modal fade" id="editBasicModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3"><h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>기본 정보 및 계정 수정</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">서브도메인 (아이디)</label><input class="form-control bg-light" type="text" value="<?= htmlspecialchars($s['subdomain']) ?>" readonly></div><div class="col-md-6"><label class="item-attr-label">연결 도메인</label><input class="form-control" name="custom_domain" type="text" value="<?= htmlspecialchars($s['custom_domain'] ?? '') ?>"></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">상점명 (국문)</label><input class="form-control" name="shop_name" type="text" value="<?= htmlspecialchars($s['shop_name']) ?>" required></div><div class="col-md-6"><label class="item-attr-label">상점명 (영문)</label><input class="form-control" name="shop_name_en" type="text" value="<?= htmlspecialchars($s['shop_name_en'] ?? '') ?>"></div></div>
                        <div class="mb-3"><label class="item-attr-label">관리자 이메일 (ID)</label><input class="form-control bg-light" name="manager_email" type="email" value="<?= htmlspecialchars($s['manager_email']) ?>" required></div>
                        <div class="mb-3"><label class="item-attr-label text-primary">샘플 상점 설정</label><select name="is_sample_shop" class="form-select border-primary-subtle"><option value="n" <?= ($s['is_sample_shop'] ?? 'n') === 'n' ? 'selected' : '' ?>>일반 상점 (N)</option><option value="y" <?= ($s['is_sample_shop'] ?? 'n') === 'y' ? 'selected' : '' ?>>샘플 상점 (Y)</option></select></div>
                        <div class="mb-3"><label class="item-attr-label text-primary">비밀번호 변경</label><input class="form-control border-primary-subtle" name="new_password" type="password" placeholder="새 비밀번호 입력"></div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="item-attr-label">업종 카테고리</label><select name="category" class="form-select"><?php global $shop_category_labels; foreach ($shop_category_labels as $key => $label): ?><option value="<?= $key ?>" <?= ($s['category'] ?? '') == $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="item-attr-label">상점 상태</label><select name="status" class="form-select"><option value="active" <?= $s['status'] == 'active' ? 'selected' : '' ?>>Active (운영)</option><option value="applying" <?= $s['status'] == 'applying' ? 'selected' : '' ?>>Applying (신청)</option><option value="inactive" <?= $s['status'] == 'inactive' ? 'selected' : '' ?>>Inactive (휴점)</option></select></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 2] 연락처 모달 -->
    <div class="modal fade" id="editContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3"><h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2"></i>연락처 및 위치 정보 수정</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">휴대폰 번호</label><input class="form-control" name="phone_mobile" type="text" value="<?= htmlspecialchars($s['phone_mobile'] ?? '') ?>"></div><div class="col-md-6"><label class="item-attr-label">일반 전화</label><input class="form-control" name="phone_landline" type="text" value="<?= htmlspecialchars($s['phone_landline'] ?? '') ?>"></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">카카오톡 ID</label><input class="form-control" name="kakao_id" type="text" value="<?= htmlspecialchars($s['kakao_id'] ?? '') ?>"></div><div class="col-md-6"><label class="item-attr-label">카카오 채널 ID</label><input class="form-control" name="kakao_channel_id" type="text" value="<?= htmlspecialchars($s['kakao_channel_id'] ?? '') ?>"></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-4"><label class="item-attr-label">텔레그램 Chat ID</label><input class="form-control" name="telegram_chat_id" type="text" value="<?= htmlspecialchars($s['telegram_chat_id'] ?? '') ?>"></div><div class="col-md-4"><label class="item-attr-label text-primary">알림 활성화</label><select name="use_telegram_alert" class="form-select"><option value="Y" <?= ($s['use_telegram_alert'] ?? 'N') == 'Y' ? 'selected' : '' ?>>활성화 (Y)</option><option value="N" <?= ($s['use_telegram_alert'] ?? 'N') != 'Y' ? 'selected' : '' ?>>비활성화 (N)</option></select></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-12"><label class="item-attr-label">실제 주소(Physical Address)</label><input class="form-control" name="physical_address" type="text" value="<?= htmlspecialchars($s['physical_address'] ?? '') ?>"></div></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 3] 배달 모달 -->
    <div class="modal fade" id="editDeliveryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3"><h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>운영 정책 수정</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">영업 시간</label><input class="form-control" name="business_hours" type="text" value="<?= htmlspecialchars($s['business_hours'] ?? '') ?>"></div><div class="col-md-6"><label class="item-attr-label">배달 가능 시간</label><input class="form-control" name="delivery_hours" type="text" value="<?= htmlspecialchars($s['delivery_hours'] ?? '') ?>"></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-4"><label class="item-attr-label">최소 주문 금액</label><input class="form-control" name="min_delivery_amount" type="number" value="<?= htmlspecialchars($s['min_delivery_amount'] ?? '0') ?>"></div><div class="col-md-4"><label class="item-attr-label">배달 가능 여부</label><select name="is_delivery_available" class="form-select"><option value="1" <?= ($s['is_delivery_available'] ?? 1) == 1 ? 'selected' : '' ?>>가능</option><option value="0" <?= ($s['is_delivery_available'] ?? 1) == 0 ? 'selected' : '' ?>>불가</option></select></div><div class="col-md-4"><label class="item-attr-label">매장픽업 가능 여부</label><select name="is_pickup_available" class="form-select"><option value="1" <?= ($s['is_pickup_available'] ?? 1) == 1 ? 'selected' : '' ?>>가능</option><option value="0" <?= ($s['is_pickup_available'] ?? 1) == 0 ? 'selected' : '' ?>>불가</option></select></div></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- [모달 4] UI 설정 모달 -->
    <div class="modal fade" id="editUiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-start">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0 py-3"><h5 class="modal-title fw-bold"><i class="bi bi-display me-2"></i>UI 설정 및 시스템</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_info">
                        <input type="hidden" name="is_ui_update" value="1">
                        <div class="mb-3"><label class="item-attr-label text-danger">긴급 공지사항</label><textarea class="form-control" name="urgent_notice" rows="2"><?= htmlspecialchars($s['urgent_notice'] ?? '') ?></textarea></div>
                        <div class="mb-3"><label class="item-attr-label text-info">일반 공지사항</label><textarea class="form-control" name="general_notice" rows="3"><?= htmlspecialchars($s['general_notice'] ?? '') ?></textarea></div>
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">메인 타이틀</label><input class="form-control" name="main_title" type="text" value="<?= htmlspecialchars($s['main_title'] ?? '') ?>"></div><div class="col-md-6"><label class="item-attr-label">한줄 소개</label><input class="form-control" name="shop_intro" type="text" value="<?= htmlspecialchars($s['shop_intro'] ?? '') ?>"></div></div>
                        <div class="row g-3 mb-3"><div class="col-md-6"><label class="item-attr-label">스킨 테마</label><select name="shop_skin" class="form-select"><option value="default" <?= ($s['shop_skin'] ?? 'default') == 'default' ? 'selected' : '' ?>>기본 화이트</option><option value="dark" <?= ($s['shop_skin'] ?? '') == 'dark' ? 'selected' : '' ?>>모던 다크</option></select></div><div class="col-md-6"><label class="item-attr-label">폰트 스타일</label><select name="shop_font" class="form-select"><option value="Pretendard" <?= ($s['shop_font'] ?? 'Pretendard') == 'Pretendard' ? 'selected' : '' ?>>고딕</option><option value="Noto Sans KR" <?= ($s['shop_font'] ?? '') == 'Noto Sans KR' ? 'selected' : '' ?>>본고딕</option></select></div></div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="item-attr-label">개별 무료 주문 건수 한도</label><input class="form-control" name="custom_free_orders" type="number" value="<?= htmlspecialchars($s['custom_free_orders'] ?? '') ?>" placeholder="빈칸이면 기본값"></div>
                            <div class="col-md-6"><label class="item-attr-label">개별 무료 디스크 한도 (MB)</label><input class="form-control" name="custom_free_disk_mb" type="number" value="<?= htmlspecialchars($s['custom_free_disk_mb'] ?? '') ?>" placeholder="빈칸이면 기본값"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">수정 완료</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>