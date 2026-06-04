<?php

/**
 * KShops24 상점 기본 정보 설정 모듈 (manage_shop_info.php)
 * - 역할: 상점 기본 정보 및 보안, 세무 정보 관리 뷰
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// 백엔드 액션 로직 로드
require_once __DIR__ . '/manage_shop_info_action.php';

// [추가] 상점 상태 변경(휴점/폐점) AJAX 백엔드 처리 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop_status'])) {
    while (ob_get_level()) ob_end_clean(); // 출력 버퍼 초기화 (HTML 찌꺼기 제거)
    header('Content-Type: application/json');

    try {
        $shop_status = $_POST['shop_status'] ?? 'active';
        $urgent_notice = trim($_POST['urgent_notice'] ?? '');
        $today = date('Y-m-d');

        if ($shop_status === 'owner_inactive') {
            // 점주 휴점 설정: 삭제 관련 날짜 없음
            $pdo->prepare("UPDATE shops SET status = ?, inactive_date = ?, closed_date = NULL, deleted_date = NULL, urgent_notice = ? WHERE id = ?")
                ->execute(['owner_inactive', $today, $urgent_notice, $shop_id]);
                
            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'status', "휴점 처리 (점주 요청)", "사유: " . ($urgent_notice ?: '미작성'));
            }
        } elseif ($shop_status === 'owner_deleted') {
            // 폐점 신청: 7일 뒤 삭제 세팅
            $deleted_date_val = date('Y-m-d', strtotime('+7 days'));
            
            $pdo->prepare("UPDATE shops SET status = ?, inactive_date = ?, closed_date = ?, deleted_date = ?, urgent_notice = ? WHERE id = ?")
                ->execute(['owner_deleted', $today, $today, $deleted_date_val, '상점 폐점 대기 중 (7일 뒤 데이터 영구 삭제)', $shop_id]);
                
            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'status', "폐점 신청 (점주 요청)", "7일 뒤 상점 영구 삭제 대기 상태로 전환");
            }
        } else {
            // 정상 운영 복귀
            $pdo->prepare("UPDATE shops SET status = 'active', inactive_date = NULL, closed_date = NULL, deleted_date = NULL, urgent_notice = '' WHERE id = ?")
                ->execute([$shop_id]);
                
            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'status', "운영 재개", "상점이 정상 운영(활성) 상태로 복귀했습니다.");
            }
        }

        echo json_encode(['status' => 'success', 'message' => '상점 상태가 성공적으로 변경되었습니다.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '상태 변경 중 오류가 발생했습니다: ' . $e->getMessage()]);
        exit;
    }
}

// 데이터 준비
static $shop_category_labels = ['fnb' => '음식점/배달', 'cafe' => '카페/디저트', 'beauty' => '뷰티/헤어', 'mart' => '마트/식료품', 'service' => '일반 서비스/기타'];
$ui = json_decode($shop['ui_settings'] ?? '{}', true);

?>

<div class="container-fluid p-0">
<!-- 최상단 타이틀 -->
<?php echo renderPageHeader('상점 기본 정보', 'bi-shop-window'); ?>

<!-- 상점 기본 정보 및 보안 설정 카드 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>상점 기본 정보</h5>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#editInfoModal">
                    <i class="bi bi-pencil me-1"></i>정보 수정
                </button>
            </div>
            <div class="card-body p-4">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted mb-2">상점명</dt>
                    <dd class="col-7 mb-2 fw-bold text-dark"><?php echo htmlspecialchars($shop['shop_name'] ?? '-'); ?></dd>
                    
                    <dt class="col-5 text-muted mb-2">관리자명</dt>
                    <dd class="col-7 mb-2">
                        <?php echo htmlspecialchars($shop['manager_name'] ?? '-'); ?>
                        <?php if (!empty($shop['manager_name_en'])) echo '<span class="text-muted small">(' . htmlspecialchars($shop['manager_name_en']) . ')</span>'; ?>
                    </dd>
                    
                    <dt class="col-5 text-muted mb-2">연락처</dt>
                    <dd class="col-7 mb-2">
                        <div class="mb-1"><i class="bi bi-phone text-muted me-1"></i><?php echo htmlspecialchars($shop['phone_mobile'] ?? '-'); ?></div>
                        <div><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($shop['phone_landline'] ?? '-'); ?></div>
                    </dd>
                    
                    <dt class="col-5 text-muted mb-2">소셜 / 메신저</dt>
                    <dd class="col-7 mb-2">
                        <div class="mb-1 text-truncate"><span class="badge bg-warning text-dark me-1" style="width:35px;">카톡</span><?php echo htmlspecialchars($shop['kakao_id'] ?? '-'); ?></div>
                        <div class="mb-1 text-truncate"><span class="badge bg-warning text-dark me-1" style="width:35px;">채널</span><?php echo htmlspecialchars($shop['kakao_channel_id'] ?? '-'); ?></div>
                        <div class="text-truncate">
                            <span class="badge bg-primary me-1" style="width:35px;">페북</span>
                            <?php if (!empty($shop['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($shop['facebook_url']); ?>" target="_blank" class="text-decoration-none d-inline-block align-bottom text-truncate" style="max-width: 90px;">링크 이동</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </dd>

                    <dt class="col-5 text-muted mb-2">상점 실제 주소</dt>
                    <dd class="col-7 mb-2"><?php echo htmlspecialchars($shop['physical_address'] ?? '-'); ?></dd>
                    
                    <dt class="col-5 text-muted mb-2">화폐 및 다국어</dt>
                    <dd class="col-7 mb-2">
                        <div class="mb-1">화폐: <strong><?php echo htmlspecialchars($ui['currency'] ?? 'PHP'); ?></strong></div>
                        <div>다국어: <?php echo (($ui['is_multilingual'] ?? 0) == 1) ? '<span class="text-primary fw-bold">ON</span>' : '<span class="text-muted">OFF</span>'; ?></div>
                    </dd>
                    
                    <dt class="col-5 text-muted mb-2">영업 시간</dt>
                    <dd class="col-7 mb-2">
                        <?php 
                        $bh = $shop['business_hours'] ?? '';
                        if (empty($bh)) {
                            echo '<span class="text-muted">미입력</span>';
                        } elseif ($bh[0] === '{' || $bh[0] === '[') {
                            $bh_data = json_decode($bh, true);
                            $days_map = ['mon'=>'월', 'tue'=>'화', 'wed'=>'수', 'thu'=>'목', 'fri'=>'금', 'sat'=>'토', 'sun'=>'일'];
                            $today_key = strtolower(date('D')); 
                            
                            echo '<div class="d-flex flex-column gap-1">';
                            foreach ($days_map as $k => $v) {
                                $day = $bh_data[$k] ?? ['open' => '', 'close' => '', 'closed' => false];
                                $is_today = ($k === $today_key);
                                $text_class = $is_today ? 'text-dark fw-bold' : 'text-muted';
                                $badge = $is_today ? '<span class="badge bg-primary ms-1" style="font-size:0.6rem;">오늘</span>' : '';
                                
                                echo '<div class="small ' . $text_class . '">';
                                echo '<span class="d-inline-block" style="width: 30px;">' . $v . '</span>';
                                
                                if (!empty($day['closed'])) {
                                    echo '<span class="text-danger">휴무</span>';
                                } else {
                                    $time_str = (!empty($day['open']) && !empty($day['close'])) ? "{$day['open']} ~ {$day['close']}" : "24시간";
                                    echo htmlspecialchars($time_str);
                                }
                                echo $badge;
                                echo '</div>';
                            }
                            echo '</div>';
                        } else {
                            echo '<span class="badge bg-light text-dark border fw-normal"><i class="bi bi-clock me-1"></i>' . htmlspecialchars($bh) . '</span>';
                        }
                        ?>
                    </dd>
                    
                    <?php if (($shop['category'] ?? 'fnb') === 'fnb'): ?>
                    <dt class="col-5 text-muted mb-2">배달/매장픽업 가능 여부</dt>
                    <dd class="col-7 mb-2">
                        <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? '<span class="badge bg-success fw-normal me-1">배달 O</span>' : '<span class="badge bg-secondary opacity-50 fw-normal me-1">배달 X</span>'; ?>
                        <?php echo (($shop['is_pickup_available'] ?? 1) == 1) ? '<span class="badge bg-success fw-normal">매장픽업 O</span>' : '<span class="badge bg-secondary opacity-50 fw-normal">매장픽업 X</span>'; ?>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center mb-1">
                <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-shield-lock text-danger me-2"></i>보안 및 알림 설정</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <h6 class="fw-bold text-secondary mb-2 small"><i class="bi bi-key me-1"></i>관리자 암호 변경</h6>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#changePasswordModal">비밀번호 변경하기</button>
                </div>
                
                <hr class="opacity-25">
                
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-secondary mb-0 small"><i class="bi bi-telegram me-1 text-info"></i>텔레그램 알림 수신</h6>
                        <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#editTelegramModal">설정 수정</button>
                    </div>
                    <div class="bg-light p-3 rounded-3 mt-2">
                        <div class="d-flex align-items-center mb-1">
                            <span class="small text-muted me-2">상태:</span>
                            <?php echo (($shop['use_telegram_alert'] ?? 'N') == 'Y') ? '<span class="badge bg-info text-white">활성화됨</span>' : '<span class="badge bg-secondary opacity-50">비활성화</span>'; ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="small text-muted me-2">Chat ID:</span>
                            <span class="small fw-bold text-dark"><?php echo htmlspecialchars($shop['telegram_chat_id'] ?: '미등록'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 상점 상태 관리 폼 -->
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center mb-1">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-door-open text-primary me-2"></i>상점 상태 관리</h5>
            </div>
            <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark d-block">현재 상점 상태</label>
                        <?php 
                        $current_status = $shop['status'] ?? 'active';
                        if (in_array($current_status, ['active', 'testing'])) {
                            echo '<span class="badge bg-success fs-6 px-3 py-2 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>운영 중 (정상 영업)</span>';
                        } elseif ($current_status === 'owner_inactive') {
                            echo '<span class="badge bg-warning text-dark fs-6 px-3 py-2 shadow-sm"><i class="bi bi-pause-circle-fill me-1"></i>상점주 요청 휴점</span>';
                        } elseif ($current_status === 'inactive') {
                            echo '<span class="badge bg-warning text-dark fs-6 px-3 py-2 shadow-sm"><i class="bi bi-pause-circle-fill me-1"></i>시스템 휴점 (기한 만료 등)</span>';
                        } elseif ($current_status === 'owner_deleted') {
                            echo '<span class="badge bg-danger fs-6 px-3 py-2 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>상점주 폐점 신청 (삭제 대기)</span>';
                        } elseif (in_array($current_status, ['closed_soon', 'closed'])) {
                            echo '<span class="badge bg-danger fs-6 px-3 py-2 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>폐점 대기 (삭제 예정)</span>';
                        } else {
                            echo '<span class="badge bg-secondary fs-6 px-3 py-2 shadow-sm">' . htmlspecialchars($current_status) . '</span>';
                        }
                        ?>
                    </div>

                    <?php if (in_array($current_status, ['active', 'owner_inactive', 'owner_deleted'])): ?>
                    <form id="shopStatusForm" method="POST" action="manage_shop.php?pg=shop" onsubmit="return handleStatusChange(event)">
                        <input type="hidden" name="update_shop_status" value="1">

                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-primary"><i class="bi bi-arrow-repeat me-1"></i>상점 상태 변경 설정</label>
                        <select class="form-select border-primary fw-bold" id="shop_status_select" name="shop_status" onchange="toggleStatusFields()">
                            <option value="active" selected>운영 중 (정상 영업)으로 설정</option>
                            <option value="owner_inactive" >상점주 요청 휴점으로 설정</option>
                            <option value="owner_deleted" >폐점 신청 (영구 삭제 대기)으로 설정</option>
                        </select>
                    </div>

                    <!-- [휴점] 입력 폼 -->
                    <div id="inactive_fields" class="mb-3" style="display: none;">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <label class="form-label small fw-bold text-danger mb-2"><i class="bi bi-pause-circle me-1"></i>휴점 안내 메시지</label>
                            <textarea name="urgent_notice" class="form-control border-danger border-opacity-25 bg-white" rows="3" placeholder="고객에게 안내할 휴점 사유 및 복귀 예정일 등을 적어주세요."><?php echo htmlspecialchars($shop['urgent_notice'] ?? '개인 사정으로 당분간 휴점합니다. 감사합니다.'); ?></textarea>
                            <div class="form-text small mt-2"><i class="bi bi-info-circle me-1"></i>작성하신 사유는 상점 메인 화면 중앙에 팝업 형태로 노출됩니다.</div>
                        </div>

                        <div class="alert alert-warning border-0 shadow-sm rounded-3">
                            <h6 class="fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> 휴점 시 주의사항</h6>
                            <ul class="mb-0 small ps-3 lh-lg text-dark">
                                <li>휴점이 되어도, 상점 사용료는 계속 발생합니다.</li>
                                <li>휴점 중에 사용료가 미납되면, 일정 기간 후 상점이 폐점 및 삭제처리 됩니다.</li>
                            </ul>
                        </div>
                        <div class="form-check mt-3 bg-light p-3 rounded-3 border text-center">
                            <input class="form-check-input float-none ms-0 me-2" type="checkbox" id="confirm_inactive" name="confirm_inactive" value="1">
                            <label class="form-check-label fw-bold text-dark small" for="confirm_inactive" style="cursor: pointer;">
                                위 주의사항을 모두 확인하였으며, 본 상점을 휴점 합니다.
                            </label>
                        </div>
                    </div>

                    <!-- [폐점 신청] 경고 폼 -->
                    <div id="closed_fields" class="mb-3" style="display: none;">
                        <div class="alert alert-danger border-0 shadow-sm rounded-3">
                            <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> 폐점 신청 주의사항</h6>
                            <ul class="mb-0 small ps-3 lh-lg">
                                <li>폐점 신청 후 <strong>7일 뒤</strong>에 상점의 모든 데이터가 영구 삭제됩니다.</li>
                                <li>삭제 시 복구가 절대 불가능하며, <strong>그동안 납입한 서비스 사용료는 환불되지 않습니다.</strong></li>
                                <li>완전 삭제 전까지는 상태를 <strong>'운영 중'</strong>으로 다시 변경하여 언제든지 폐점을 <strong>철회</strong>하실 수 있습니다.</li>
                            </ul>
                        </div>
                        <div class="form-check mt-3 bg-light p-3 rounded-3 border text-center">
                            <input class="form-check-input float-none ms-0 me-2" type="checkbox" id="confirm_close" name="confirm_close" value="1">
                            <label class="form-check-label fw-bold text-danger small" for="confirm_close" style="cursor: pointer;">
                                위 주의사항을 모두 확인하였으며, 본 상점의 폐점을 신청합니다.
                            </label>
                        </div>
                    </div>

                    <div class="text-end mt-3 pt-3 border-top" id="status_submit_btn_area" style="display: none;">
                        <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm fw-bold"><i class="bi bi-check2-circle me-1"></i> 상태 변경 저장</button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-warning shadow-sm border-0 small mb-0 rounded-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>시스템 설정에 의해 상점 상태를 임의로 변경할 수 없습니다.<br>
                        상태 변경(운영 복귀 등)이 필요하신 경우 대시보드 메시지 보드를 통해 <strong>본사(KShops24)</strong>로 문의해 주시기 바랍니다.
                    </div>
                <?php endif; ?>
            </div>
            <script>
                function toggleStatusFields() {
                    const select = document.getElementById('shop_status_select');
                    if (!select) return;
                    const status = select.value;
                    
                    document.getElementById('inactive_fields').style.display = (status === 'owner_inactive') ? 'block' : 'none';
                    document.getElementById('closed_fields').style.display = (status === 'owner_deleted') ? 'block' : 'none';
                    
                    const btnArea = document.getElementById('status_submit_btn_area');
                    if (btnArea) btnArea.style.display = (status !== '<?php echo $current_status; ?>') ? 'block' : 'none';
                }
                document.addEventListener('DOMContentLoaded', toggleStatusFields);

                async function handleStatusChange(event) {
                    event.preventDefault(); // 기본 폼 전송을 완전히 차단하여 화면이 JSON으로 덮이는 오작동 방지
                    const status = document.getElementById('shop_status_select').value;
                    if (status === '<?php echo $current_status; ?>') return false; // 변경이 없을 때는 제출 불가

                    if (status === 'owner_deleted' && !document.getElementById('confirm_close').checked) {
                        alert('폐점 신청 시 주의사항을 모두 확인하고 체크박스에 동의해주세요.');
                        return false;
                    }
                    if (status === 'owner_deleted' && !confirm("정말로 상점 폐점을 신청하시겠습니까?\n7일 뒤 상점의 모든 정보가 완전히 삭제됩니다.")) {
                        return false;
                    }
                    if (status === 'owner_inactive' && !document.getElementById('confirm_inactive').checked) {
                        alert('휴점 시 주의사항을 모두 확인하고 체크박스에 동의해주세요.');
                        return false;
                    }
                    if (status === 'owner_inactive' && !confirm("바로 상점은 휴점상태가 됩니다. 상점을 휴점하시겠습니까?")) {
                        return false;
                    }
                    if (status === 'active' && !confirm("상점을 다시 '운영 중(정상 영업)'으로 복귀시키시겠습니까?")) {
                        return false;
                    }
                    
                    const form = event.target;
                    const btn = form.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>처리 중...';

                    try {
                        const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                        const result = await response.json();
                        if (result.status === 'success') {
                            if (typeof showToast === 'function') showToast(result.message, 'success');
                            else alert(result.message);
                            // 상태가 성공적으로 변경되었음을 화면에 반영하기 위해 즉시 새로고침
                            setTimeout(() => location.reload(), 500);
                        } else {
                            alert('오류 발생: ' + result.message);
                        }
                    } catch (err) {
                        alert('서버 통신 중 오류가 발생했습니다.');
                    } finally {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                    return false;
                }
            </script>

        </div>
    </div>

</div>

<!-- 세무 정보 폼 -->
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt me-2 text-warning"></i>세무 정보 설정</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="manage_shop.php?pg=shop" onsubmit="handleAjaxFormSubmit(event)">
            <input type="hidden" name="update_shop" value="1">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">TIN Number (납세자 식별 번호)</label>
                    <input type="text" name="tin_number" class="form-control" placeholder="000-000-000-000" value="<?php echo htmlspecialchars($shop['tin_number'] ?? ''); ?>" oninput="formatTinInput(this)" maxlength="15">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">사업자 등록 명칭 (Registered Name)</label>
                    <input type="text" name="registered_name" class="form-control" placeholder="DTI/SEC에 등록된 영문 상호명" value="<?php echo htmlspecialchars($shop['registered_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">VAT 여부</label>
                    <select name="business_type" class="form-select">
                        <option value="Non-VAT" <?php echo (($shop['business_type'] ?? 'Non-VAT') === 'Non-VAT') ? 'selected' : ''; ?>>Non-VAT (면세/영세사업자)</option>
                        <option value="VAT" <?php echo (($shop['business_type'] ?? '') === 'VAT') ? 'selected' : ''; ?>>VAT (부가가치세 일반과세자)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">공식 사업장 주소</label>
                    <input type="text" name="business_address" class="form-control" placeholder="공식 사업장 주소 입력" value="<?php echo htmlspecialchars($shop['business_address'] ?? ''); ?>">
                </div>
            </div>
            <div class="text-end mt-3 pt-3 border-top">
                <button type="submit" name="update_shop" class="btn btn-warning btn-sm rounded-pill px-4 shadow-sm fw-bold"><i class="bi bi-check2-circle me-1"></i> 세무 정보 저장</button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- 모달 및 스크립트 로드 -->
<?php include_once __DIR__ . '/manage_shop_info_modals.php'; ?>