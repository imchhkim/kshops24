<?php

/**
 * [하위 파일] KShops24 상점별 텔레그램 연동 상태 관리 (admin/manage_telegram.php)
 * 역할: 전체 상점의 텔레그램 봇 토큰 및 Chat ID 설정 상태를 일괄 모니터링하고 개별 테스트를 지원함.
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// [AJAX] 단일 상점 텔레그램 테스트 발송
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_telegram_shop') {
    ob_clean(); // 이전 출력 버퍼 비우기 (HTML 찌꺼기 방지)
    header('Content-Type: application/json; charset=utf-8');

    $target_shop_id = (int)($_POST['shop_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT telegram_chat_id FROM shops WHERE id = ?");
        $stmt->execute([$target_shop_id]);
        $target_shop = $stmt->fetch();

        if (!$target_shop || empty($target_shop['telegram_chat_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Chat ID가 설정되지 않았습니다.']);
            exit;
        }

        $chat_id = trim($target_shop['telegram_chat_id']);

        $msg = "🔔 [KShops24 시스템 알림]\n관리자 페이지에서 발송된 테스트 메시지입니다.\n이 상점의 텔레그램 연동이 정상적으로 작동 중입니다!";
        
        $response = send_ps24_telegram($msg, $chat_id);
        $result = json_decode($response, true);

        if ($result && isset($result['ok']) && $result['ok'] === true) {
            echo json_encode(['status' => 'success', 'message' => '발송 성공']);
        } else {
            $error_desc = $result['description'] ?? '알 수 없는 오류';
            echo json_encode(['status' => 'error', 'message' => $error_desc]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '시스템 오류: ' . $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------------------
// 숫자(Count) 집계 (manage_shops.php와 동일한 탭 구성을 위한 데이터 준비)
// -------------------------------------------------------------------
$counts = getShopStatusCounts($pdo);

// -------------------------------------------------------------------
// 텔레그램 연동 상태에 따른 상점 조회
// -------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all: 전체, setup: 설정완료, unset: 미설정

// 폐점 상점을 제외하고 필터 적용
$sql = "SELECT id, shop_name, subdomain, manager_name, phone_mobile, use_telegram_alert, telegram_chat_id, status FROM shops WHERE status != 'closed'";
$params = [];

if ($filter === 'setup') {
    $sql .= " AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''";
} elseif ($filter === 'unset') {
    $sql .= " AND (telegram_chat_id IS NULL OR telegram_chat_id = '')";
}

if ($search !== '') {
    $sql .= " AND (id = ? OR shop_name LIKE ? OR subdomain LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shop_list = $stmt->fetchAll();
?>

<style>
.count-badge {
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
    font-weight: 700;
    display: inline-block;
}

.nav-link.active .count-badge {
    background: #eef2ff;
    color: #4e73df;
}

.search-box {
    margin-bottom: 0;
}

.search-box .form-control {
    border-radius: 20px;
    padding-left: 15px;
    font-size: 0.85rem;
    border-color: #e2e8f0;
}

.search-box .btn {
    border-radius: 20px;
    font-size: 0.85rem;
}
</style>

<div class="shop-management-wrap">

    <div class="d-flex flex-wrap justify-content-between align-items-end border-bottom mb-3 pb-0 gap-2">
        <!-- 상단 상태별 탭 네비게이션 (manage_shops.php와의 통일성 유지) -->
        <ul class="nav nav-tabs border-bottom-0 mb-0">
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_ACTIVE ?>">
                    운영중 <span class="count-badge"><?= $counts[SHOP_STATUS_ACTIVE] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary" href="admin_view.php?page=manage_expiring_shops">
                    만료 임박<span class="count-badge text-danger"><?= $counts['expiring'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_INACTIVE ?>">
                    휴점 <span class="count-badge"><?= $counts[SHOP_STATUS_INACTIVE] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_CLOSED ?>">
                    폐점 <span class="count-badge"><?= $counts[SHOP_STATUS_CLOSED] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_APPLYING ?>">
                    대기중 <span class="count-badge"><?= $counts[SHOP_STATUS_APPLYING] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_TESTING ?>">
                    테스트중 <span class="count-badge"><?= $counts[SHOP_STATUS_TESTING] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active fw-bold text-primary" href="admin_view.php?page=manage_telegram">
                    <i class="bi bi-telegram"></i> 텔레그램 상태
                </a>
            </li>
        </ul>
    </div>

    <!-- 필터 및 검색 폼 -->
    <div class="search-box mb-2">
        <form action="admin_view.php" method="GET" class="d-flex gap-1">
            <input type="hidden" name="page" value="manage_telegram">
            <select name="filter" class="form-select form-select-sm shadow-none"
                style="width: auto; border-radius: 20px;" onchange="this.form.submit()">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>모든 상점 보기</option>
                <option value="setup" <?= $filter === 'setup' ? 'selected' : '' ?>>연동 완료 상점만</option>
                <option value="unset" <?= $filter === 'unset' ? 'selected' : '' ?>>미연동 상점만</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" style="width: 180px;"
                placeholder="상점명 또는 아이디 검색" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
            <?php if ($search !== '' || $filter !== 'all'): ?>
            <a href="admin_view.php?page=manage_telegram" class="btn btn-outline-secondary btn-sm"><i
                    class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 텔레그램 안내 메시지 -->
    <div
        class="alert alert-info border-0 shadow-sm small py-2 d-flex align-items-center mb-3 border-start border-4 border-info">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>이곳에서 폐점을 제외한 모든 상점의 <strong>텔레그램 알림 설정 여부</strong>를 확인하고, 즉시 테스트 메시지를 발송해 볼 수 있습니다.</div>
    </div>

    <div class="table-responsive">
        <table class="table table-ps24 table-hover align-middle bg-white border rounded">
            <thead>
                <tr class="small">
                    <th class="t-center" style="width: 70px;">ID</th>
                    <th style="width: 250px;">상점명(서브도메인)</th>
                    <th>점주/연락처</th>
                    <th class="t-center">Chat ID</th>
                    <th class="t-center">알림 활성화</th>
                    <th class="t-center">연동 상태</th>
                    <th class="t-center px-4">관리 (테스트 발송)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shop_list)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <?= ($search !== '' || $filter !== 'all') ? '조건에 맞는 상점이 없습니다.' : '등록된 상점이 없습니다.' ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($shop_list as $s):
                    $is_setup = !empty($s['telegram_chat_id']);
                    $status_badge = $is_setup
                        ? '<span class="badge bg-primary rounded-pill px-3 shadow-sm"><i class="bi bi-check-circle me-1"></i>설정됨</span>'
                        : '<span class="badge bg-secondary rounded-pill px-3 opacity-50">미설정</span>';
                ?>
                <tr>
                    <td class="t-center text-muted fw-bold">#<?= $s['id'] ?></td>
                    <td>
                        <div class="fw-bold text-dark mb-1">
                            <a href="admin_view.php?page=manage_shop&id=<?= $s['id'] ?>"
                                class="text-dark text-decoration-none"
                                target="_blank"><?= htmlspecialchars($s['shop_name']) ?> <i
                                    class="bi bi-arrow-right-short text-primary"></i></a>
                        </div>
                        <div class="small text-primary"><i class="bi bi-globe2 me-1"></i><?= $s['subdomain'] ?></div>
                    </td>
                    <td>
                        <div class="small fw-bold"><?= htmlspecialchars($s['manager_name'] ?? '정보없음') ?></div>
                        <div class="small text-muted" style="font-size: 0.75rem;"><i
                                class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['phone_mobile'] ?? '-') ?>
                        </div>
                    </td>
                    <td class="t-center">
                        <?php if (!empty($s['telegram_chat_id'])): ?>
                        <span
                            class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($s['telegram_chat_id']) ?></span>
                        <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                    </td>
                    <td class="t-center">
                        <?php if ($s['use_telegram_alert'] == 'Y'): ?>
                        <span class="badge bg-success fw-normal">Y</span>
                        <?php else: ?>
                        <span class="badge bg-secondary opacity-50 fw-normal">N</span>
                        <?php endif; ?>
                    </td>
                    <td class="t-center" id="status-badge-<?= $s['id'] ?>">
                        <?= $status_badge ?>
                    </td>
                    <td class="t-center px-4">
                        <?php if ($is_setup): ?>
                        <button type="button"
                            class="btn btn-sm btn-outline-info fw-bold btn-test-telegram shadow-sm rounded-pill"
                            data-id="<?= $s['id'] ?>">
                            <i class="bi bi-send me-1"></i>테스트
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-light text-muted fw-bold rounded-pill" disabled>발송
                            불가</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testBtns = document.querySelectorAll('.btn-test-telegram');

    testBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            const shopId = this.dataset.id;
            const originalText = this.innerHTML;

            // 버튼 상태 변경
            this.disabled = true;
            this.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span> 발송중...';

            const statusBadgeContainer = document.getElementById('status-badge-' + shopId);

            const formData = new FormData();
            formData.append('action', 'test_telegram_shop');
            formData.append('shop_id', shopId);

            try {
                // 현재 페이지를 AJAX 엔드포인트로 활용
                const response = await fetch('admin_view.php?page=manage_telegram', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    statusBadgeContainer.innerHTML =
                        '<span class="badge bg-success rounded-pill px-3 shadow-sm"><i class="bi bi-check2-all me-1"></i>성공</span>';
                    alert('성공적으로 메시지가 발송되었습니다!\\n해당 점주님의 스마트폰(텔레그램)을 확인해 보세요.');
                } else {
                    statusBadgeContainer.innerHTML =
                        `<span class="badge bg-danger rounded-pill px-3 shadow-sm" title="${result.message}"><i class="bi bi-exclamation-triangle me-1"></i>실패</span>`;
                    alert('발송 실패: ' + result.message);
                }
            } catch (err) {
                alert('서버 통신 중 오류가 발생했습니다.');
            } finally {
                // 버튼 상태 복구
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    });
});
</script>