<?php
/**
 * KShops24 플랫폼 통합 고객 관리 (manage_customers.php)
 * - 역할: 카카오 로그인을 통해 플랫폼에 가입된 모든 고객 목록을 조회하고 통합 관리합니다.
 * - 기능: 총 가입자 통계, 닉네임/연락처 검색, 접속 이력 확인, 불량 고객 강제 탈퇴
 */

if (!isset($pdo)) {
    exit; // 직접 접근 차단
}

// =========================================================================
// 1. Action 처리 (고객 영구 삭제 / 탈퇴)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    
    if ($customer_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // 1. 상점-고객 매핑 정보 삭제
            $pdo->prepare("DELETE FROM shop_customer_mapping WHERE customer_id = ?")->execute([$customer_id]);
            
            // 2. 플랫폼 메인 고객 정보 삭제 
            // (주문이나 리뷰 등은 널(Null) 처리되거나 유지되도록 설계된 DB 제약조건에 따릅니다)
            $pdo->prepare("DELETE FROM platform_customers WHERE id = ?")->execute([$customer_id]);
            
            $pdo->commit();
            
            // 관리자 행동 로그 기록
            if (function_exists('recordSiteLog')) {
                recordSiteLog($pdo, LOG_TYPE_ADMIN_ACTION, "고객 강제 탈퇴 처리", ["customer_id" => $customer_id]);
            }
            
            $search = $_GET['search'] ?? '';
            echo "<script>location.replace('admin_view.php?page=manage_customers&msg=customer_deleted&search=" . urlencode($search) . "');</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = showAlert("고객 삭제 중 오류가 발생했습니다: " . $e->getMessage(), "danger");
        }
    }
}

// 알림 메시지 처리
if (isset($_GET['msg']) && $_GET['msg'] === 'customer_deleted') {
    $message = showAlert("해당 고객의 플랫폼 계정이 영구 삭제(탈퇴) 처리되었습니다.", "success");
}

// =========================================================================
// 2. 통계 데이터 집계
// =========================================================================
$total_customers = $pdo->query("SELECT COUNT(*) FROM platform_customers")->fetchColumn();
$today_customers = $pdo->query("SELECT COUNT(*) FROM platform_customers WHERE DATE(created_at) = CURDATE()")->fetchColumn();
// 최근 7일 이내 로그인 이력이 있는 활성 유저 수
$active_customers = $pdo->query("SELECT COUNT(DISTINCT customer_id) FROM shop_customer_mapping WHERE last_login_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

// =========================================================================
// 3. 검색 및 페이징 설정
// =========================================================================
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 20;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    // 닉네임, 카카오 ID, 혹은 하이픈이 제거된 전화번호로 검색
    $where .= " AND (c.nickname LIKE ? OR c.kakao_id LIKE ? OR REPLACE(c.ph_phone, '-', '') LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%" . preg_replace('/[^0-9]/', '', $search) . "%";
}

// 총 검색된 고객 수
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM platform_customers c $where");
$stmt_count->execute($params);
$total_filtered_count = $stmt_count->fetchColumn();
$total_pages = ceil($total_filtered_count / $limit) ?: 1;

// =========================================================================
// 4. 고객 목록 데이터 로딩 (연관 서브쿼리로 활동 내역 요약 포함)
// =========================================================================
$sql = "
    SELECT c.*,
           (SELECT COUNT(*) FROM shop_customer_mapping WHERE customer_id = c.id) as joined_shops_count,
           (SELECT MAX(last_login_at) FROM shop_customer_mapping WHERE customer_id = c.id) as last_activity
    FROM platform_customers c
    $where
    ORDER BY c.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<!-- [1] 상단 요약 통계 위젯 -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 opacity-75 small fw-bold">플랫폼 총 가입 고객</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($total_customers) ?> <span class="fs-6 fw-normal opacity-75">명</span></h3>
                </div>
                <i class="bi bi-people-fill fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 text-muted small fw-bold">금일 신규 가입자</h6>
                    <h3 class="mb-0 fw-bold text-success"><?= number_format($today_customers) ?> <span class="fs-6 fw-normal text-muted">명</span></h3>
                </div>
                <i class="bi bi-person-plus text-success opacity-25 fs-1"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 text-muted small fw-bold">주간 활성 유저 (MAU 대비)</h6>
                    <h3 class="mb-0 fw-bold text-info"><?= number_format($active_customers) ?> <span class="fs-6 fw-normal text-muted">명</span></h3>
                </div>
                <i class="bi bi-activity text-info opacity-25 fs-1"></i>
            </div>
        </div>
    </div>
</div>

<!-- [2] 고객 목록 및 검색 컨테이너 -->
<div class="card border-0 shadow-sm flex-column d-flex">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="fw-bold m-0 text-dark"><i class="bi bi-person-lines-fill me-2 text-primary"></i>가입 고객 목록 (<?= number_format($total_filtered_count) ?>)</h6>
        
        <!-- 검색 폼 -->
        <form method="GET" action="admin_view.php" class="d-flex align-items-center">
            <input type="hidden" name="page" value="manage_customers">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" style="width: 220px;" placeholder="닉네임, 카카오 ID, 전화번호 검색" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary fw-bold px-3">검색</button>
                <?php if ($search !== ''): ?>
                    <a href="admin_view.php?page=manage_customers" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-ps24 table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted text-center">
                        <th style="width: 60px;">프로필</th>
                        <th class="text-start">고객 정보</th>
                        <th>연락처 / 배달 주소</th>
                        <th>가입한 상점 수</th>
                        <th>플랫폼 가입일<br><span class="fw-normal" style="font-size: 0.85em;">최근 활동일</span></th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-emoji-frown fs-2 d-block mb-2 opacity-50"></i>
                                <?= ($search !== '') ? '검색 조건에 맞는 고객이 없습니다.' : '아직 플랫폼에 가입한 고객이 없습니다.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <!-- 프로필 이미지 -->
                                <td class="text-center">
                                    <?php if (!empty($c['profile_img'])): ?>
                                        <img src="<?= htmlspecialchars($c['profile_img']) ?>" class="rounded-circle shadow-sm border border-light" style="width: 45px; height: 45px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center text-secondary shadow-sm mx-auto border border-light" style="width: 45px; height: 45px;">
                                            <i class="bi bi-person-fill fs-4"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 닉네임 / ID -->
                                <td class="text-start">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;"><?= htmlspecialchars($c['nickname']) ?></div>
                                    <div class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-chat-fill text-warning me-1"></i><?= htmlspecialchars($c['kakao_id']) ?></div>
                                </td>

                                <!-- 연락처 및 주소 (미등록 시 대시 표시) -->
                                <td class="text-center">
                                    <div class="fw-bold text-primary mb-1">
                                        <i class="bi bi-telephone me-1"></i><?= $c['ph_phone'] ? htmlspecialchars(function_exists('formatPHPhone') ? formatPHPhone($c['ph_phone']) : $c['ph_phone']) : '<span class="text-muted fw-normal">미입력</span>' ?>
                                    </div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px; margin: 0 auto;" title="<?= htmlspecialchars($c['ph_address']) ?>">
                                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($c['ph_address'] ?: '주소 미입력') ?>
                                    </div>
                                </td>

                                <!-- 상점 유입 통계 -->
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border shadow-sm px-3 py-2 fw-bold">
                                        <i class="bi bi-shop me-1 text-primary"></i> <?= number_format($c['joined_shops_count']) ?> 개
                                    </span>
                                </td>

                                <!-- 날짜 정보 -->
                                <td class="text-center small">
                                    <div class="fw-bold text-dark"><?= date('Y-m-d', strtotime($c['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.85em;"><?= $c['last_activity'] ? date('Y-m-d H:i', strtotime($c['last_activity'])) : '-' ?></div>
                                </td>

                                <!-- 관리 액션 -->
                                <td class="text-center">
                                    <!-- 불량 회원 영구 삭제 (POST 폼) -->
                                    <form method="POST" action="admin_view.php?page=manage_customers&search=<?= urlencode($search) ?>" class="m-0" onsubmit="return confirm('경고: 이 작업은 되돌릴 수 없습니다.\n고객의 계정 정보와 상점 가입 내역이 완전히 삭제됩니다.\n\n정말로 이 고객을 플랫폼에서 강제 탈퇴 처리하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete_customer">
                                        <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 border-0 shadow-sm" title="회원 강제 탈퇴">
                                            <i class="bi bi-person-x-fill me-1"></i>삭제
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- [3] 페이징 네비게이션 -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top-0 py-3 mt-auto">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    $search_param = ($search !== '') ? '&search=' . urlencode($search) : '';

                    if ($page > 1) {
                        echo '<li class="page-item"><a class="page-link shadow-none border-0 text-dark" href="admin_view.php?page=manage_customers' . $search_param . '&p=' . ($page - 1) . '"><i class="bi bi-chevron-left"></i></a></li>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = ($i == $page) ? 'bg-primary text-white border-primary fw-bold' : 'text-dark border-0';
                        echo '<li class="page-item"><a class="page-link shadow-none mx-1 rounded-circle text-center ' . $active . '" style="width: 30px;" href="admin_view.php?page=manage_customers' . $search_param . '&p=' . $i . '">' . $i . '</a></li>';
                    }

                    if ($page < $total_pages) {
                        echo '<li class="page-item"><a class="page-link shadow-none border-0 text-dark" href="admin_view.php?page=manage_customers' . $search_param . '&p=' . ($page + 1) . '"><i class="bi bi-chevron-right"></i></a></li>';
                    }
                    ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>