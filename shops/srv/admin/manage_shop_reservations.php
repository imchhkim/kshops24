<?php

/**
 * KShops24 서비스/예약 카테고리 문의(Inquiry) 관리 모듈
 * - 기능: 고객의 서비스 예약 내역을 확인하고 상태를 관리합니다.
 * - 상점 관리자 페이지 (manage_shop.php)에서 include 되어 실행됩니다.
 */

if (!isset($shop_id)) exit; // 직접 접근 차단

// ==========================================================
// 0. 데이터베이스 스키마 자동 패치 (상태값 ENUM 충돌 해결)
// ==========================================================
try {
    // ENUM에 없는 'confirmed' 값을 넣었을 때 데이터가 비어버리는 현상을 방지하기 위해 VARCHAR로 유연하게 변경합니다.
    $pdo->exec("ALTER TABLE shop_inquiries MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    // 기존에 ENUM 에러로 인해 값이 비어버린('알 수 없음' 현상) 레코드들을 'confirmed'로 자동 복구합니다.
    $pdo->exec("UPDATE shop_inquiries SET status = 'confirmed' WHERE status = ''");
} catch (Exception $e) {
}

// ==========================================================
// 1. AJAX 요청 처리 (상태 변경 및 삭제)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    } // 출력 버퍼 정리
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);

    // save_holidays, check_new 등 특정 액션은 inquiry_id가 필요 없음
    if (!in_array($action, ['save_holidays', 'check_new']) && !$inquiry_id) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
        exit;
    }

    try {
        if ($action === 'update_status') {
            $new_status = $_POST['new_status'] ?? 'pending';

            // 상태 업데이트
            $stmt = $pdo->prepare("UPDATE shop_inquiries SET status = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$new_status, $inquiry_id, $shop_id]);

            // 이력 기록
            $status_labels = [
                'pending'   => '예약 대기',
                'confirmed' => '예약 확정',
                'completed' => '서비스 완료',
                'cancelled' => '예약 취소'
            ];
            $label = $status_labels[$new_status] ?? '알 수 없음';

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "고객 문의 상태 변경", "예약 번호 #{$inquiry_id} 상태가 [{$label}](으)로 변경되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => "예약 상태가 '{$label}'(으)로 변경되었습니다."]);
            exit;
        }

        if ($action === 'delete_inquiry') {
            $stmt = $pdo->prepare("DELETE FROM shop_inquiries WHERE id = ? AND shop_id = ?");
            $stmt->execute([$inquiry_id, $shop_id]);

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "고객 문의 삭제", "예약 번호 #{$inquiry_id} 내역이 삭제되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => '문의 내역이 안전하게 삭제되었습니다.']);
            exit;
        }

        if ($action === 'save_reply_memo') {
            $owner_reply = trim($_POST['owner_reply'] ?? '');
            $owner_memo = trim($_POST['owner_memo'] ?? '');
            $new_status = $_POST['new_status'] ?? 'pending';

            // 상태, 답변 및 메모 통합 저장
            $stmt = $pdo->prepare("UPDATE shop_inquiries SET owner_reply = ?, owner_memo = ?, status = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$owner_reply, $owner_memo, $new_status, $inquiry_id, $shop_id]);

            $status_labels = [
                'pending'   => '예약 대기',
                'confirmed' => '예약 확정',
                'completed' => '서비스 완료',
                'cancelled' => '예약 취소'
            ];
            $label = $status_labels[$new_status] ?? '알 수 없음';

            if (function_exists('addShopHistoryLog')) {
                addShopHistoryLog($pdo, $shop_id, 'inquiry', "문의 답변/메모 및 상태 저장", "예약 번호 #{$inquiry_id} 상태([{$label}]) 및 답변/메모가 저장되었습니다.");
            }

            echo json_encode(['status' => 'success', 'message' => '예약 정보(상태, 답변, 메모)가 성공적으로 저장되었습니다.']);
            exit;
        }

        // [추가] 임시 휴일 설정 및 해제 AJAX 처리
        if ($action === 'save_holidays') {
            $date = trim($_POST['holiday_date'] ?? '');
            $type = $_POST['holiday_type'] ?? 'add'; // 'add' or 'remove'
            $memo = trim($_POST['holiday_memo'] ?? '');
            
            $stmt = $pdo->prepare("SELECT reservation_settings FROM shops WHERE id = ?");
            $stmt->execute([$shop_id]);
            $res_settings_json = $stmt->fetchColumn();
            $res_settings = json_decode($res_settings_json ?: '{}', true);
            
            if (!isset($res_settings['holidays'])) $res_settings['holidays'] = [];
            
            // 기존 단순 배열(list) 호환성: 연관 배열(associative)로 변환
            if (!empty($res_settings['holidays']) && isset($res_settings['holidays'][0]) && is_string($res_settings['holidays'][0])) {
                $new_holidays = [];
                foreach ($res_settings['holidays'] as $h_date) {
                    $new_holidays[$h_date] = '임시휴일';
                }
                $res_settings['holidays'] = $new_holidays;
            }
            
            if ($type === 'add' && !empty($date)) {
                $res_settings['holidays'][$date] = $memo ?: '임시휴일';
            } elseif ($type === 'remove' && !empty($date)) {
                if (isset($res_settings['holidays'][$date])) {
                    unset($res_settings['holidays'][$date]);
                }
            }
            
            $pdo->prepare("UPDATE shops SET reservation_settings = ? WHERE id = ?")->execute([json_encode($res_settings, JSON_UNESCAPED_UNICODE), $shop_id]);
            
            echo json_encode(['status' => 'success', 'message' => '휴일 설정이 저장되었습니다.']);
            exit;
        }

        // [추가] 관리자 페이지 신규 예약 폴링 알림을 위한 전체 건수 체크
        if ($action === 'check_new') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM shop_inquiries WHERE shop_id = ?");
            $stmt->execute([$shop_id]);
            $count = $stmt->fetchColumn();
            echo json_encode(['status' => 'success', 'total_count' => (int)$count]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '처리 중 오류가 발생했습니다: ' . $e->getMessage()]);
        exit;
    }
}

// ==========================================================
// 2. 데이터 필터링 및 조회 로직 (백엔드)
// ==========================================================
// [추가] 월(Month) 단위 기간 필터 도입 (기본값: 이번 달)
$target_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $target_month)) {
    $target_month = date('Y-m');
}

// date 파라미터가 없으면 해당 달의 1일이나 오늘 날짜 등을 설정
if (!isset($_GET['date'])) {
    $target_date = ($target_month === date('Y-m')) ? date('Y-m-d') : '';
} else {
    $target_date = $_GET['date'];
}
$current_status = $_GET['status'] ?? 'all';

$where_sql = "shop_id = ?";
$params = [$shop_id];

// [성능/UX 개선] 수년간의 데이터 대신 선택한 '월(Month)'의 데이터와 '대기(pending)' 건만 제한하여 조회
$where_sql .= " AND (customer_inquiry LIKE ? OR status = 'pending')";
$params[] = "%[예약 희망: {$target_month}-%";

if ($target_date !== '') {
    $where_sql .= " AND (customer_inquiry LIKE ? OR status = 'pending')";
    $params[] = "%[예약 희망: {$target_date}%";
}

if ($current_status !== 'all') {
    // [버그 수정] 과거 데이터 호환성을 위해 'contacted' 상태를 'confirmed'로 통일하여 필터링
    if ($current_status === 'confirmed') {
        $where_sql .= " AND status IN ('confirmed', 'contacted')";
    } else {
        $where_sql .= " AND status = ?";
        $params[] = $current_status;
    }
}

// 전체 및 상태별 카운트 집계
// [수정] 전체 누적이 아닌 해당 월(target_month)의 건수와 전체 대기(pending) 건수만 카운트하여 성능 최적화
$count_sql = "SELECT status, COUNT(*) as cnt FROM shop_inquiries WHERE shop_id = ? AND (customer_inquiry LIKE ? OR status = 'pending') GROUP BY status";

$stmt_counts = $pdo->prepare($count_sql);
$stmt_counts->execute([$shop_id, "%[예약 희망: {$target_month}-%"]);
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];

foreach ($stmt_counts->fetchAll() as $row) {
    $st = $row['status'];
    // [버그 수정] 과거 데이터(contacted)는 예약 확정(confirmed) 탭으로 합산
    if ($st === 'contacted') $st = 'confirmed';
    
    if (isset($counts[$st])) {
        $counts[$st] += $row['cnt'];
    }
    $counts['all'] += $row['cnt'];
}

// [추가] 달력에 예약 건수를 스마트하게 표시하기 위한 날짜별 통계 집계
$stmt_cal = $pdo->prepare("SELECT customer_inquiry, status FROM shop_inquiries WHERE shop_id = ? AND customer_inquiry LIKE ? AND status IN ('pending', 'confirmed', 'contacted', 'completed')");
$stmt_cal->execute([$shop_id, "%[예약 희망: {$target_month}-%"]);
$cal_stats = [];
foreach ($stmt_cal->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // [버그 수정] 한글(예약 희망) 매칭을 위해 정규식에 /u(UTF-8) 플래그 추가
    if (preg_match('/\[예약 희망:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s+([0-9]{2}:[0-9]{2})\]/u', $row['customer_inquiry'], $m)) {
        $res_d = $m[1];
        if (!isset($cal_stats[$res_d])) {
            $cal_stats[$res_d] = ['total' => 0, 'pending' => 0, 'confirmed' => 0];
        }
        $cal_stats[$res_d]['total']++;
        if ($row['status'] === 'pending') {
            $cal_stats[$res_d]['pending']++;
        } else {
            $cal_stats[$res_d]['confirmed']++;
        }
    }
}

// 문의 내역 목록 조회
$stmt = $pdo->prepare("SELECT * FROM shop_inquiries WHERE {$where_sql} ORDER BY created_at DESC");
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// [추가] 특정 날짜 선택 시: 예약 대기 목록 우선, 그 후 확정 예약들을 예약 시간순(오름차순)으로 스마트 정렬
if ($target_date !== '') {
    $pending_list = [];
    $confirmed_list = [];
    $others_list = [];

    foreach ($inquiries as $inq) {
        $res_datetime_str = '9999-12-31 23:59'; // 기본값 (시간/날짜 추출 실패 시 뒤로 배치)
        // [버그 수정] 한글 매칭을 위해 정규식에 /u 플래그 추가
        if (preg_match('/\[예약 희망:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s+([0-9]{2}:[0-9]{2})\]/u', $inq['customer_inquiry'], $m)) {
            $res_datetime_str = $m[1] . ' ' . $m[2];
        }
        $inq['_res_datetime'] = $res_datetime_str;

        if ($inq['status'] === 'pending') {
            $pending_list[] = $inq;
        } elseif (in_array($inq['status'], ['confirmed', 'contacted', 'completed'])) {
            $confirmed_list[] = $inq;
        } else {
            $others_list[] = $inq;
        }
    }

    // 예약 일시(날짜+시간) 오름차순 정렬 함수
    $sort_by_time = function($a, $b) {
        return strcmp($a['_res_datetime'], $b['_res_datetime']);
    };

    usort($pending_list, $sort_by_time);
    usort($confirmed_list, $sort_by_time);
    usort($others_list, $sort_by_time);

    // 대기 -> 확정 -> 기타 순으로 병합
    $inquiries = array_merge($pending_list, $confirmed_list, $others_list);
}

// [추가] 리스트에 노출된 고객들의 전체 예약 횟수 미리 계산 (첫 고객 여부 판별용)
$customer_counts = [];
if (!empty($inquiries)) {
    $phones = array_unique(array_column($inquiries, 'customer_phone'));
    if (!empty($phones)) {
        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $q = "SELECT customer_phone, COUNT(*) as cnt FROM shop_inquiries WHERE shop_id = ? AND customer_phone IN ($placeholders) GROUP BY customer_phone";
        $p = array_merge([$shop_id], $phones);
        $stmt_c = $pdo->prepare($q);
        $stmt_c->execute($p);
        foreach ($stmt_c->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $customer_counts[$r['customer_phone']] = (int)$r['cnt'];
        }
    }
}

// [추가] 전체 누적 예약 건수 (신규 알림 폴링 기준값)
$stmt_total_all = $pdo->prepare("SELECT COUNT(*) FROM shop_inquiries WHERE shop_id = ?");
$stmt_total_all->execute([$shop_id]);
$total_inquiry_all_time = $stmt_total_all->fetchColumn();

// 상태 UI 맵핑 (리스트 출력 시 사용)
$status_map = [
    'pending'   => ['label' => '예약 대기', 'color' => 'bg-warning text-dark', 'icon' => 'bi-hourglass-split'],
    'contacted' => ['label' => '예약 확정',   'color' => 'bg-info text-white',   'icon' => 'bi-calendar2-check-fill'], // 과거 데이터 호환
    'confirmed' => ['label' => '예약 확정',   'color' => 'bg-info text-white',   'icon' => 'bi-calendar2-check-fill'],
    'completed' => ['label' => '서비스 완료', 'color' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
    'cancelled' => ['label' => '예약 취소', 'color' => 'bg-secondary text-white', 'icon' => 'bi-x-circle-fill']
];
?>

<!-- 상단 타이틀 영역 -->
<?php echo renderPageHeader('고객 예약 관리', 'bi-calendar2-check'); ?>

<!-- [추가] 월(Month) 선택 필터 폼 -->
<div class="mb-3 p-2 bg-white rounded-4 shadow-sm border border-opacity-50">
    <form id="month-filter-form" method="GET" action="manage_shop.php" class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between m-0 gap-2">
        <div class="d-flex align-items-center">
            <input type="hidden" name="pg" value="manage_shop_reservations">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($current_status); ?>">
            <label class="fw-bold me-3 ms-2 small text-dark"><i class="bi bi-calendar-month me-1 text-primary"></i>조회 년/월</label>
            
            <!-- [UX 개선] 브라우저 기본 영어 표현을 숨기고, 한국어(YYYY년 M월)로 직관적으로 표시하는 커스텀 UI -->
            <div class="position-relative d-inline-block">
                <div class="form-control form-control-sm w-auto fw-bold bg-light border-0 d-flex align-items-center" style="pointer-events: none;">
                    <?php echo date('Y', strtotime($target_month)) . '년 ' . date('n', strtotime($target_month)) . '월'; ?>
                    <i class="bi bi-caret-down-fill ms-2 text-muted" style="font-size: 0.7rem;"></i>
                </div>
                <input type="month" name="month" class="position-absolute top-0 start-0 w-100 h-100" style="opacity: 0; cursor: pointer;" value="<?php echo htmlspecialchars($target_month); ?>" onchange="this.form.submit()">
            </div>
        </div>
        
        <div class="d-flex gap-2 w-100 w-md-auto justify-content-end">
            <!-- [추가] 임시 휴일 관리 버튼 -->
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold flex-shrink-0" onclick="goToThisMonth()">이번달</button>   
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold flex-shrink-0" onclick="openHolidayModal()">임시 휴일 관리</button>
        </div>
    </form>
</div>
<script>
    function goToThisMonth() {
        const form = document.getElementById('month-filter-form');
        form.querySelector('input[name="month"]').value = '<?php echo date('Y-m'); ?>'; // 서버 시간 기준 이번달 자동 세팅
        form.submit();
    }

    function openHolidayModal() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('holidayModal')).show();
    }

    async function manageHoliday(type, date = '') {
        let memo = '';
        if (type === 'add') {
            date = document.getElementById('new_holiday_date').value;
            memo = document.getElementById('new_holiday_memo').value;
            if (!date) {
                alert('날짜를 선택해주세요.');
                return;
            }
        } else {
            if (!confirm(date + ' 임시 휴무 설정을 해제하시겠습니까?')) return;
        }

        const formData = new FormData();
        formData.append('action', 'save_holidays');
        formData.append('holiday_type', type);
        formData.append('holiday_date', date);
        if (type === 'add') formData.append('holiday_memo', memo);

        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_reservations', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert('통신 오류가 발생했습니다.');
        }
    }

    // [추가] 관리자 페이지 신규 예약 폴링 알림
    let lastInquiryCount = <?php echo (int)$total_inquiry_all_time; ?>;
    const adminNotifySound = new Audio(NOTIFICATION_SOUND);

    async function checkNewInquiries() {
        const formData = new FormData();
        formData.append('action', 'check_new');
        formData.append('shop_id', <?php echo $shop_id; ?>);
        
        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_reservations', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.status === 'success') {
                if (data.total_count > lastInquiryCount) {
                    lastInquiryCount = data.total_count;
                    // 알림 소리 재생
                    adminNotifySound.play().catch(e => console.log('Audio play blocked:', e));
                    
                    // 화면 상단에 빨간색 사각형 알림 띄우기 (3초 후 자동 닫힘)
                    const alertId = 'new-inquiry-alert-' + Date.now();
                    const alertHtml = `
                        <div id="${alertId}" class="alert alert-danger alert-dismissible fade show shadow-lg fw-bold" role="alert" style="border: 2px solid #dc3545; font-size: 1.1rem; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; min-width: 320px; text-align: center;">
                            <i class="bi bi-bell-fill fs-4 me-2"></i>새로운 예약 문의가 접수되었습니다!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', alertHtml);

                    setTimeout(() => {
                        const alertEl = document.getElementById(alertId);
                        if (alertEl) {
                            const bsAlert = new bootstrap.Alert(alertEl);
                            bsAlert.close();
                        }
                    }, 3000);

                    // [핵심] 화면 새로고침 없이 백그라운드에서 예약 목록, 탭 카운트, 달력 데이터만 즉시 갱신
                    refreshInquiryList();
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }
    
    async function refreshInquiryList() {
        try {
            // 현재 주소(필터 조건 포함)를 백그라운드에서 다시 호출
            const response = await fetch(location.href);
            const htmlText = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            
            // 1. 주간 달력 갱신
            const newCal = doc.getElementById('weekCalendar');
            const oldCal = document.getElementById('weekCalendar');
            if (newCal && oldCal) oldCal.innerHTML = newCal.innerHTML;
            
            // 2. 탭 네비게이션(카운트 뱃지) 갱신
            const newNav = doc.querySelector('.nav.nav-pills.row.g-2');
            const oldNav = document.querySelector('.nav.nav-pills.row.g-2');
            if (newNav && oldNav) oldNav.innerHTML = newNav.innerHTML;
            
            // 3. 문의 목록 영역 갱신
            const newList = doc.getElementById('inquiry-list-container');
            const oldList = document.getElementById('inquiry-list-container');
            if (newList && oldList) oldList.innerHTML = newList.innerHTML;
            
        } catch (error) {
            console.error('List refresh error:', error);
        }
    }

    // 10초마다 신규 예약 체크
    setInterval(checkNewInquiries, 10000);
</script>

<!-- [수정] 모바일 최적화 가로 스크롤 월간(Monthly) 달력 -->
<?php
// [추가] 달력 렌더링에 필요한 휴무 정보 로드
$res_settings = json_decode($shop['reservation_settings'] ?? '{}', true);
$holidays = $res_settings['holidays'] ?? [];
$available_slots = $res_settings['available_slots'] ?? [];
$biz_hours = json_decode($shop['business_hours'] ?? '{}', true);
$day_names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

$dates = [];
$start_date = $target_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));
$today = date('Y-m-d');

$current = strtotime($start_date);
$end = strtotime($end_date);

while ($current <= $end) {
    $d = date('Y-m-d', $current);
    $dates[] = [
        'date' => $d,
        'day' => date('d', strtotime($d)),
        'week_day' => date('w', strtotime($d)),
        'is_today' => ($d === $today),
        'is_selected' => ($d === $target_date)
    ];
    $current = strtotime('+1 day', $current);
}
$week_map = ['일', '월', '화', '수', '목', '금', '토'];
?>

<!-- 달력 영역 -->
<div class="mb-3 position-relative">
    <div class="d-flex flex-wrap gap-2 pb-2 px-1 justify-content-center" id="weekCalendar">
        <a href="?pg=manage_shop_reservations&status=<?php echo htmlspecialchars($current_status); ?>&month=<?php echo urlencode($target_month); ?>&date=" class="btn <?php echo $target_date === '' ? 'btn-dark shadow' : 'btn-white bg-white border shadow-sm text-dark'; ?> d-flex flex-column align-items-center justify-content-center flex-shrink-0 rounded-4 transition-all" style="width: 60px; height: 75px;">
            <span class="small mb-1">전체</span>
            <span class="fw-bold fs-5">All</span>
        </a>
        <?php foreach ($dates as $d): 
            $is_today_unselected = ($d['is_today'] && !$d['is_selected']);
            $text_class = ($target_date === '' || !$d['is_selected']) ? ($is_today_unselected ? 'text-white-50' : ($d['week_day'] == 0 ? 'text-danger' : ($d['week_day'] == 6 ? 'text-primary' : 'text-muted'))) : 'text-white-50';
            $btn_class = $d['is_selected'] ? 'btn-dark shadow' : ($d['is_today'] ? 'shadow-sm border-0' : 'btn-white bg-white border shadow-sm text-dark');
            $today_style = $is_today_unselected ? 'background-color: #fd7e14; color: white;' : '';
            $stats = $cal_stats[$d['date']] ?? null;
            
            // [추가] 휴무 여부 판별 (배경 회색 및 문구 노출용)
            $day_name = $day_names[$d['week_day']];
            $is_closed_day = false;
            $slotsForDay = $available_slots[$day_name] ?? [];
            if (empty($slotsForDay)) $is_closed_day = true;
            if (isset($biz_hours[$day_name]['closed']) && $biz_hours[$day_name]['closed']) $is_closed_day = true;
            
            // 휴무 여부 판별 (구버전 단순배열, 신버전 연관배열 대응)
            $is_holiday = isset($holidays[$d['date']]) || in_array($d['date'], $holidays);
            $holiday_memo = isset($holidays[$d['date']]) ? $holidays[$d['date']] : '임시휴일';
            
            $is_off = $is_closed_day || $is_holiday;
            
            if ($is_off && !$d['is_selected']) {
                $btn_class = 'btn-light border shadow-sm text-muted opacity-75';
            }
        ?>
            <a href="?pg=manage_shop_reservations&status=<?php echo htmlspecialchars($current_status); ?>&month=<?php echo urlencode($target_month); ?>&date=<?php echo $d['date']; ?>" 
               class="btn <?php echo $btn_class; ?> position-relative d-flex flex-column align-items-center justify-content-center flex-shrink-0 rounded-4 date-item transition-all" 
               style="width: 60px; height: 75px; <?php echo $today_style; ?>">
                <span class="small mb-1 <?php echo $text_class; ?> fw-bold" style="font-size: 0.75rem;"><?php echo $week_map[$d['week_day']]; ?></span>
                <span class="fw-bold fs-5 <?php echo ($d['is_selected'] || $is_today_unselected) ? 'text-white' : 'text-dark'; ?>"><?php echo $d['day']; ?></span>
                
                <!-- [추가] 예약 상태 및 건수 스마트 뱃지 표시 -->
                <?php if ($stats && $stats['total'] > 0): ?>
                    <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill shadow-sm <?php echo $stats['pending'] > 0 ? 'bg-danger' : 'bg-primary'; ?>" style="font-size: 0.65rem; padding: 0.3em 0.5em; transform: translate(-10%, 15%) !important;">
                        <?php echo $stats['total']; ?>
                    </span>
                <?php endif; ?>
                
                <!-- [추가] 휴무 텍스트 안내 -->
                <?php if ($is_off): ?>
                    <span class="position-absolute bottom-0 w-100 text-center <?php echo $is_today_unselected ? 'text-white' : 'text-danger'; ?> fw-bold text-truncate px-1" style="font-size: 0.55rem; padding-bottom: 3px;">
                        <?php echo $is_holiday ? htmlspecialchars($holiday_memo) : '정기휴무'; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 탭 네비게이션: 모바일 최적화 레이아웃 -->
<ul class="nav nav-pills row g-2 mb-4 border-bottom pb-3 px-2">

    <!-- [전체] 버튼: 모바일 100% (col-12), PC 자동 (col-md-auto) -->
    <li class="nav-item col-12 col-md-auto">
        <?php $is_all = ($current_status === 'all'); ?>
        <a class="nav-link rounded-pill fw-bold px-3 py-2 ajax-page-link text-center <?php echo $is_all ? 'bg-primary text-white shadow-sm' : 'bg-white text-dark border'; ?>"
            href="?pg=manage_shop_reservations&status=all&month=<?php echo urlencode($target_month); ?>&date=<?php echo urlencode($target_date); ?>">
            전체
            <span class="badge <?php echo $is_all ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'; ?> ms-1 rounded-pill">
                <?php echo $counts['all']; ?>
            </span>
        </a>
    </li>

    <?php
    $nav_items = [
        'pending'   => '예약 대기',
        'confirmed' => '예약 확정',
        'completed' => '서비스 완료',
        'cancelled' => '예약 취소'
    ];

    foreach ($nav_items as $key => $label):
        $is_active = ($current_status === $key);
    ?>
        <!-- [상태별] 버튼: 모바일 50% (col-6), PC 자동 (col-md-auto) -->
        <li class="nav-item col-6 col-md-auto">
            <a class="nav-link rounded-pill fw-bold px-2 py-2 ajax-page-link text-center <?php echo $is_active ? 'bg-primary text-white shadow-sm' : 'bg-white text-dark border'; ?>"
                href="?pg=manage_shop_reservations&status=<?php echo $key; ?>&month=<?php echo urlencode($target_month); ?>&date=<?php echo urlencode($target_date); ?>"
                style="font-size: 0.85rem; display: flex; align-items: center; justify-content: center; height: 100%;">

                <span class="text-nowrap"><?php echo $label; ?></span>
                <span class="badge <?php echo $is_active ? 'bg-white text-primary' : 'bg-secondary bg-opacity-25 text-dark'; ?> ms-1 rounded-pill">
                    <?php echo $counts[$key]; ?>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- 문의 목록 영역 -->
<div class="row" id="inquiry-list-container">
    <div class="col-12">
        <?php if (empty($inquiries)): ?>
            <div class="card border-0 shadow-sm rounded-4 py-5 text-center bg-white">
                <i class="bi bi-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">해당 날짜에 조회된 예약 내역이 없습니다.</h5>
            </div>
        <?php else: ?>
            <?php
            $list_num = 1; // [추가] 순차적 목록 번호 카운터
            $prev_status_group = ''; // [추가] 그룹 디바이더 처리를 위한 상태 저장 변수
            
            foreach ($inquiries as $inq):
                $status = $status_map[$inq['status']] ?? ['label' => '알 수 없음', 'color' => 'bg-light text-dark', 'icon' => 'bi-question'];
                $ts = strtotime($inq['created_at']);
                $kr_days = ['일', '월', '화', '수', '목', '금', '토'];
                $date = date('Y-m-d', $ts) . ' (' . $kr_days[date('w', $ts)] . ') ' . date('H:i', $ts);

                // [추가] 특정 날짜 선택 시, 상태별 직관적 그룹 디바이더(안내선) 표시
                if ($target_date !== '') {
                    $curr_status_group = $inq['status'] === 'pending' ? 'pending' : (in_array($inq['status'], ['confirmed', 'contacted', 'completed']) ? 'confirmed' : 'others');
                    if ($prev_status_group !== $curr_status_group) {
                        if ($curr_status_group === 'pending') {
                            echo renderSectionHeader('예약 대기', 'bi-calendar2-check text-danger', [], '', 'text-danger fs-5');
                        } elseif ($curr_status_group === 'confirmed') {
                            echo renderSectionHeader('확정된 예약 일정 (시간순)', 'bi-calendar2-check');
                        } elseif ($curr_status_group === 'others') {
                            echo '<div class="fw-bold text-secondary mb-3 mt-4 ps-2"><i class="bi bi-x-circle me-1"></i> 취소된 예약</div>';
                        }
                        $prev_status_group = $curr_status_group;
                    }
                }

                // 관심 서비스 배열 디코딩
                $items = json_decode($inq['inquiry_data'], true);
                if (!is_array($items)) $items = [];
                
                // [추가] 본문 텍스트 내에서 "예약 희망" 패턴 추출 후 가공
                $res_date_str = '';
                $res_time_str = '';
                $inquiry_text = $inq['customer_inquiry'];
                
                // [버그 수정] 한글 매칭을 위해 정규식에 /u 플래그 추가
                if (preg_match('/\[예약 희망:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s+([0-9]{2}:[0-9]{2})\]/u', $inquiry_text, $m)) {
                    $res_date_str = $m[1];
                    $res_time_str = $m[2];
                    // 추출한 텍스트 덩어리는 상세 요청사항에서 깔끔하게 지움
                    $inquiry_text = trim(str_replace($m[0], '', $inquiry_text));
                }
            ?>
                <div class="card shadow-sm mb-4 border-0 rounded-4">
                    <!-- card-header: 모바일에서 세로 정렬(flex-column), PC에서 가로 정렬(flex-md-row) -->
                    <div class="card-header bg-white border-bottom pt-3 pb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center rounded-top-4 gap-3">

                        <!-- [모바일 1번째 줄 / PC 좌측] 정보 영역 -->
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center w-100 w-md-auto gap-1 gap-md-2">
                            <div class="d-flex align-items-center gap-2">
                                <!-- 순차적 목록 번호 -->
                                <span class="fw-bold text-secondary fs-5">#<?php echo $list_num++; ?></span>
                                <!-- 상태 뱃지 (공용) -->
                                <span class="badge <?php echo $status['color']; ?> rounded-pill px-3 py-2 fw-bold text-nowrap">
                                    <i class="bi <?php echo $status['icon']; ?> me-1"></i><?php echo $status['label']; ?>
                                </span>
                            </div>
                            <!-- 접수일시 (공용, 모바일 좌측 하단) -->
                            <small class="text-muted fw-medium text-nowrap ms-0 ms-md-2 mt-1 mt-md-0">
                                <i class="bi bi-clock me-1"></i>접수일시: <?php echo $date; ?>
                            </small>
                        </div>

                        <!-- [모바일 2번째 줄 / PC 우측] 관리 버튼 영역 -->
                        <div class="d-flex w-100 w-md-auto justify-content-end gap-2 mt-2 mt-md-0">
                            <select class="form-select form-select-sm fw-bold border-secondary shadow-sm rounded-pill px-3" style="min-width: 120px; cursor: pointer;" onchange="selectNewStatus(<?php echo $inq['id']; ?>, this.value, this.options[this.selectedIndex].text)">
                                    <option value="pending" <?php echo $inq['status'] === 'pending' ? 'selected' : ''; ?>>예약 대기</option>
                                    <option value="confirmed" <?php echo in_array($inq['status'], ['confirmed', 'contacted']) ? 'selected' : ''; ?>>예약 확정</option>
                                    <option value="completed" <?php echo $inq['status'] === 'completed' ? 'selected' : ''; ?>>서비스 완료</option>
                                    <option value="cancelled" <?php echo $inq['status'] === 'cancelled' ? 'selected' : ''; ?>>예약 취소</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm flex-shrink-0" onclick="deleteInquiry(<?php echo $inq['id']; ?>)" title="이 문의 영구 삭제">
                                    <i class="bi bi-trash3-fill"></i><span class="d-none d-md-inline ms-1">삭제</span>
                                </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4 pb-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                            <?php if ($res_date_str): ?>
                            <div class="d-flex align-items-center gap-0 text-primary">
                                <h6 class="fw-bold mb-0 fs-0"><i class="bi bi-calendar-check-fill me-1"></i> 예약 일시 :&nbsp;</h6>
                                <div class="fw-bold fs-4">
                                    <?php echo date('m/d', strtotime($res_date_str)); ?> <?php echo str_replace(['AM', 'PM'], ['오전', '오후'], date('A g:i', strtotime($res_time_str))); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="text-md-end mt-2 mt-md-0">
                                <?php 
                                // 첫 예약 고객 판별 (전체 예약 건수가 1 이하일 경우)
                                $is_first_time = isset($customer_counts[$inq['customer_phone']]) && $customer_counts[$inq['customer_phone']] <= 1; 
                                ?>
                                <div class="d-flex justify-content-between justify-content-md-end align-items-center mb-2 gap-2">
                                    <h6 class="fw-bold small text-muted mb-0"><i class="bi bi-telephone-inbound-fill text-primary me-1"></i> 고객 연락처</h6>
                                    <?php if ($is_first_time): ?><span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size: 0.65rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>첫 예약</span><?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-sm" style="font-size: 0.75rem;" onclick="showCustomerInquiryHistory('<?php echo $inq['customer_phone']; ?>')">
                                        <i class="bi bi-list-ul me-1"></i>모두보기
                                    </button>
                                </div>
                                <h4 class="fw-bold mb-0 text-center text-md-end">
                                    <a href="tel:<?php echo $inq['customer_phone']; ?>" class="text-decoration-none <?php echo $is_first_time ? 'text-danger bg-danger bg-opacity-10 border border-danger border-opacity-25' : 'text-dark bg-light'; ?> px-3 py-2 rounded-3 d-inline-block shadow-sm transition-all">
                                        <?php echo function_exists('formatPHPhone') ? formatPHPhone($inq['customer_phone']) : $inq['customer_phone']; ?>
                                    </a>
                                </h4>
                                <?php if ($is_first_time): ?>
                                    <div class="mt-2 text-danger small fw-bold" style="font-size: 0.75rem; word-break: keep-all;">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>처음 예약한 고객이니, 직접 전화를 걸어 전화번호 및 예약을 확인하세요.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-5 border-md-end">
                                <h6 class="fw-bold small text-muted mb-3"><i class="bi bi-card-checklist text-primary me-1"></i> 예약 서비스 목록 <span class="badge bg-secondary rounded-pill ms-1"><?php echo count($items); ?></span></h6>
                                <ul class="list-group list-group-flush rounded-3 shadow-sm border">
                                    <?php foreach ($items as $item): ?>
                                        <li class="list-group-item bg-light border-bottom border-white py-2 px-3 fw-bold text-dark text-truncate">
                                            <i class="bi bi-check2 text-success me-2"></i><?php echo htmlspecialchars($item['name'] ?? '서비스명 없음'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-7">
                                <h6 class="fw-bold small text-muted mb-3"><i class="bi bi-chat-quote-fill text-primary me-1"></i> 고객 상세 요청사항</h6>
                                <div class="p-3 border border-primary border-opacity-25 rounded-3 bg-light shadow-sm mb-4">
                                    <p class="mb-0 text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($inquiry_text ?: '특별한 요청사항이 없습니다.'); ?></p>
                                </div>

                                <!-- [추가] 상점 답변 및 메모 폼 -->
                                <form id="memo-form-<?php echo $inq['id']; ?>" onsubmit="saveInquiryMemo(<?php echo $inq['id']; ?>); return false;">
                                    <input type="hidden" name="new_status" value="<?php echo $inq['status']; ?>">
                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-bold text-dark"><i class="bi bi-reply-fill text-success me-1"></i> 고객 안내용 답변</label>
                                            <textarea name="owner_reply" class="form-control text-dark" rows="3" placeholder="고객에게 안내할 답변 내용을 작성해 주세요. (고객이 '나의 예약 내역' 확인 시 노출됩니다)"><?php echo htmlspecialchars($inq['owner_reply'] ?? '예약이 확정되었습니다.'); ?></textarea>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small fw-bold text-dark"><i class="bi bi-journal-text text-warning me-1"></i> 상점 전용 메모 <span class="fw-normal text-muted">(고객 미노출)</span></label>
                                            <textarea name="owner_memo" class="form-control bg-warning bg-opacity-10 text-dark border-warning border-opacity-25" rows="3" placeholder="관리자만 볼 수 있는 상담 진행 상황이나 특이사항을 기록하세요."><?php echo htmlspecialchars($inq['owner_memo'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-sm btn-dark rounded-pill px-4 shadow-sm fw-bold btn-save-memo"><i class="bi bi-save me-1"></i> 답변/메모 저장</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- [추가] 특정 고객 전체 문의 내역 조회 모달 -->
<div class="modal fade" id="customerHistoryModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>고객 전체 문의 내역</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="customer-history-phone-display" class="alert alert-light border shadow-sm text-center mb-4 rounded-4 fw-bold text-primary fs-5">
                    <!-- 전화번호 표시 -->
                </div>
                <div id="customer-history-results">
                    <!-- AJAX 결과 출력 영역 -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- [추가] 임시 휴일 관리 모달 -->
<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-x me-2"></i>임시 휴일(공휴일) 관리</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-4 bg-white p-3 rounded-3 shadow-sm border">
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-plus-circle me-1"></i>휴일 추가</label>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="date" id="new_holiday_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-5">
                            <input type="text" id="new_holiday_memo" class="form-control" placeholder="휴일 내용 (예: 국가 공휴일)">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-danger fw-bold w-100" type="button" onclick="manageHoliday('add')">추가</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label small fw-bold text-dark"><i class="bi bi-list-ul me-1"></i>등록된 임시 휴일 목록</label>
                    <ul class="list-group shadow-sm border-0" id="holiday_list">
                        <?php if (empty($holidays)): ?>
                            <li class="list-group-item text-center text-muted small py-4 border-0 rounded-3">등록된 임시 휴일이 없습니다.</li>
                        <?php else: ?>
                            <?php 
                            // 배열/객체 형태 모두 오름차순 정렬 지원
                            $h_list = [];
                            foreach ($holidays as $k => $v) {
                                if (is_int($k)) $h_list[$v] = '임시휴일';
                                else $h_list[$k] = $v;
                            }
                            ksort($h_list);
                            foreach ($h_list as $h_date => $h_memo): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                                    <span class="fw-bold text-dark"><i class="bi bi-calendar-event me-2 text-danger"></i><?php echo $h_date; ?> <small class="text-muted ms-2">(<?php echo htmlspecialchars($h_memo); ?>)</small></span>
                                    <button class="btn btn-sm btn-outline-danger py-1 px-3 rounded-pill fw-bold" onclick="manageHoliday('remove', '<?php echo $h_date; ?>')">삭제</button>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // [추가] 주간 달력 로드 시, 선택된 날짜로 스크롤 자동 이동 (중앙 정렬)
    document.addEventListener('DOMContentLoaded', function() {
        const selectedEl = document.querySelector('.date-item.btn-dark');
        if (selectedEl) {
            const container = document.getElementById('weekCalendar');
            const scrollPos = selectedEl.offsetLeft - (container.clientWidth / 2) + (selectedEl.clientWidth / 2);
            container.scrollLeft = scrollPos;
        }
    });

    // 드롭다운 메뉴에서 상태 선택 시 하단 폼(select)과 연동 및 안내
    function selectNewStatus(inquiryId, newStatus, statusText) {
        const form = document.getElementById(`memo-form-${inquiryId}`);
        if (!form) return;
        
        const inputEl = form.querySelector('input[name="new_status"]');
        if (inputEl) {
            inputEl.value = newStatus;
            
            // 폼 영역으로 부드럽게 스크롤
            form.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    // 삭제 AJAX
    async function deleteInquiry(inquiryId) {
        if (!confirm('정말로 이 고객 문의를 삭제하시겠습니까?\n삭제 후에는 복구할 수 없습니다.')) return;

        const formData = new FormData();
        formData.append('action', 'delete_inquiry');
        formData.append('inquiry_id', inquiryId);

        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_reservations', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(data.message, 'success');
                else alert(data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                alert('오류: ' + data.message);
            }
        } catch (e) {
            alert('통신 중 오류가 발생했습니다.');
        }
    }

    // 답변 및 메모 저장 AJAX
    async function saveInquiryMemo(inquiryId) {
        const form = document.getElementById(`memo-form-${inquiryId}`);
        const formData = new FormData(form);
        formData.append('action', 'save_reply_memo');
        formData.append('inquiry_id', inquiryId);

        const btn = form.querySelector('.btn-save-memo');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';
        btn.disabled = true;

        try {
            const res = await fetch('manage_shop.php?pg=manage_shop_reservations', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                if (typeof showToast === 'function') showToast(`${data.message}<br>🔔 고객에게 실시간 알림이 전송되었습니다.`, 'success');
                else alert(`${data.message}\n🔔 고객에게 실시간 알림이 전송되었습니다.`);
                
                setTimeout(() => location.reload(), 800); // 뱃지 등의 상태 반영을 위해 화면 새로고침
            } else {
                alert('오류: ' + data.message);
            }
        } catch (e) {
            alert('통신 중 오류가 발생했습니다.');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    // [추가] 고객 전화번호 기반 전체 문의 내역 불러오기 (고객용 API 재활용)
    async function showCustomerInquiryHistory(phoneRaw) {
        const phone = phoneRaw.replace(/\D/g, '');
        if (!phone) return;

        const modalEl = document.getElementById('customerHistoryModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        // [수정] 전화번호를 항상 0000-000-0000 형식으로 일관되게 포맷팅
        let formattedPhone = phone;
        if (phone.length > 4 && phone.length <= 7) {
            formattedPhone = phone.substring(0, 4) + '-' + phone.substring(4);
        } else if (phone.length > 7) {
            formattedPhone = phone.substring(0, 4) + '-' + phone.substring(4, 7) + '-' + phone.substring(7, 11);
        }

        document.getElementById('customer-history-phone-display').innerHTML = '<i class="bi bi-telephone-fill me-2"></i>' + formattedPhone;
        const resultsContainer = document.getElementById('customer-history-results');
        resultsContainer.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">내역을 불러오는 중입니다...</div></div>';

        modal.show();

        const formData = new FormData();
        formData.append('shop_id', <?php echo $shop_id; ?>);
        formData.append('phone', phone);
        formData.append('context', 'admin'); // [추가] 관리자 페이지에서 호출됨을 명시

        try {
            const response = await fetch('/shops/srv/shop_srv_reservation_history.php', {
                method: 'POST',
                body: formData
            });
            resultsContainer.innerHTML = await response.text();
        } catch (e) {
            resultsContainer.innerHTML = '<div class="text-center py-5 text-danger">통신 오류가 발생했습니다.</div>';
        }
    }
</script>