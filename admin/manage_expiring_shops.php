<?php

/**
 * [하위 파일] KShops24 휴점 임박 상점 관리 (admin/manage_expiring_shops.php)
 * - 역할: 사용 기한이 임박했거나 만료된 '운영중' 상태의 상점들만 전문적으로 모니터링합니다.
 */

// URL을 통해 전달된 검색어(search)를 가져옵니다. 
// ?? '' 는 값이 없을 경우 빈 문자열을 반환(PHP 7.0+ 널 병합 연산자)하며, trim()으로 앞뒤 공백을 제거합니다.
$search = trim($_GET['search'] ?? '');

// 1. 상태별 탭 숫자(Count) 집계
// 공용 함수를 사용하여 중복 코드 제거 및 최적화
$counts = getShopStatusCounts($pdo);


// 2. 휴점 임박 상점 상세 데이터 로드 (만료일이 가장 급한 순으로 정렬)
// 화면 하단 테이블에 출력할 실제 상점 정보와 만료일을 가져옵니다.
$sql = "SELECT s.*, p.max_expiring_date, 
               (SELECT GROUP_CONCAT(DISTINCT pay_type SEPARATOR ',') FROM shop_payments WHERE shop_id = s.id AND paid = 'n') as unpaid_types
        FROM shops s 
        JOIN (
            " . SQL_EXPIRING_SUBQUERY . "
        ) p ON s.id = p.shop_id 
    WHERE s.status = 'active' AND p." . SQL_EXPIRING_CONDITION;

$params = [];
if ($search !== '') {
    // 검색어가 있으면 상점 ID, 상점명 또는 서브도메인 이름에 해당 단어가 포함(LIKE %...%)되어 있는지 검사하는 조건을 추가합니다.
    $sql .= " AND (s.id = ? OR s.shop_name LIKE ? OR s.subdomain LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 만료일이 가장 예전인 것(가장 급한 것)부터 상단에 출력되도록 오름차순(ASC) 정렬합니다.
$sql .= " ORDER BY p.max_expiring_date ASC";
$shops = $pdo->prepare($sql);
$shops->execute($params);
$shop_list = $shops->fetchAll();
?>

<style>
    /* 탭 메뉴 옆에 붙는 숫자 뱃지의 기본 디자인 (회색 바탕) */
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

    /* 현재 선택된 탭(active) 안에 있는 뱃지는 눈에 띄게 빨간색 바탕으로 변경시킵니다. */
    .nav-link.active .count-badge {
        background: #fee2e2;
        color: #ef4444;
    }

    /* 검색창을 감싸는 영역의 마진 제거 */
    .search-box {
        margin-bottom: 0;
    }

    /* 검색 입력창(Input)을 둥글고 세련되게 디자인 */
    .search-box .form-control {
        border-radius: 20px;
        padding-left: 15px;
        font-size: 0.85rem;
        width: 220px;
        border-color: #e2e8f0;
    }

    /* 검색 버튼을 검색 입력창과 어울리도록 둥글게(20px) 맞춥니다. */
    .search-box .btn {
        border-radius: 20px;
        font-size: 0.85rem;
    }
</style>

<div class="shop-management-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-end border-bottom mb-3 pb-0 gap-2">
        <ul class="nav nav-tabs border-bottom-0 mb-0">
            <li class="nav-item">
                <a class="nav-link text-secondary"
                    href="admin_view.php?page=manage_shops&view=<?= SHOP_STATUS_ACTIVE ?>">
                    운영중 <span class="count-badge"><?= $counts[SHOP_STATUS_ACTIVE] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active fw-bold text-danger border-danger border-bottom-0"
                    href="admin_view.php?page=manage_expiring_shops">
                    휴점 임박 (<?= defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14 ?>일
                    이내)<span class="count-badge text-danger"><?= $counts['expiring'] ?></span>
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
                <a class="nav-link text-secondary" href="admin_view.php?page=manage_telegram">
                    <i class="bi bi-telegram"></i> 텔레그램 상태
                </a>
            </li>
        </ul>
    </div>

    <div class="search-box mb-2">
        <form action="admin_view.php" method="GET" class="d-flex gap-1">
            <input type="hidden" name="page" value="manage_expiring_shops">
            <input type="text" name="search" class="form-control" placeholder="상점명 또는 아이디 검색"
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-search"></i></button>

            <?php if ($search !== ''): ?>
                <a href="admin_view.php?page=manage_expiring_shops" class="btn btn-outline-secondary"><i
                        class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-ps24 table-hover align-middle">
            <thead class="small">
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 250px;">상점명/도메인</th>
                    <th>카테고리</th>
                    <th>점주/연락처</th>
                    <th class="t-center">방문</th>
                    <th class="t-center">미납 항목</th>
                    <th class="t-center">휴점 예정일</th>
                    <th class="t-center px-4">매니지먼트</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shop_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <?= ($search !== '') ? "'" . htmlspecialchars($search) . "'에 대한 검색 결과가 없습니다." : "만료 예정이거나 만료된 상점이 없습니다." ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($shop_list as $s): ?>
                    <tr>
                        <td class="t-center text-muted fw-bold">#<?= $s['id'] ?></td>

                        <!-- 상점명/도메인 -->
                        <td>
                            <div class="fw-bold text-dark mb-1">
                                <a href="admin_view.php?page=manage_shop&id=<?= $s['id'] ?>"
                                    class="text-dark text-decoration-none" target="_blank">
                                    <?= htmlspecialchars($s['shop_name']) ?> <i
                                        class="bi bi-arrow-right-short text-primary"></i>
                                </a>
                            </div>
                            <div class="small text-primary"><i
                                    class="bi bi-globe2 me-1"></i>kshops24.com/<?= $s['subdomain'] ?></div>
                        </td>

                        <td class="t-center">
                            <span
                                class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($s['category'] ?? '미지정') ?></span>
                        </td>

                        <td>
                            <div class="small fw-bold"><?= htmlspecialchars($s['manager_name'] ?? '정보없음') ?></div>
                            <div class="small text-muted" style="font-size: 0.75rem;"><i
                                    class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['phone_mobile'] ?? '-') ?>
                            </div>
                        </td>

                        <!-- 현황/링크 -->
                        <td class="t-center">
                            <a href="https://kshops24.com/<?= $s['subdomain'] ?>" target="_blank"
                                class="btn btn-sm btn-outline-secondary py-0" style="font-size: 0.7rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i>방문
                            </a>
                        </td>


                        <!-- 미납 항목 -->
                        <td class="small t-center" style="max-width: 180px;">
                            <?php
                            if (!empty($s['unpaid_types'])) {
                                // 문자열 데이터를 배열로 분리하여 루프 실행
                                $types = explode(',', $s['unpaid_types']);
                                foreach ($types as $t) {
                                    // 공통 상수에 정의된 한글 라벨(pay_type_labels) 사용
                                    $label = $pay_type_labels[$t] ?? $t;
                                    echo "<span class='badge bg-white border border-danger text-danger rounded-pill me-1 mb-1 fw-bold px-2 shadow-sm' style='font-size:0.7rem;'>" . htmlspecialchars($label) . "</span>";
                                }
                            } else {
                                echo '<span class="text-muted">-</span>'; // 미납 내역이 없는 경우
                            }
                            ?>
                        </td>

                        <td class="small t-center">
                            <?php
                            // 문자열 형태의 만료일을 DateTime 객체로 변환하여 비교 연산 준비
                            $exp_date = new DateTime($s['max_expiring_date']);
                            // 현재 시간(오늘)과 비교하여 만료 여부 판별
                            $is_expired = $exp_date < new DateTime(date('Y-m-d'));

                            // 연체 시 빨간색(text-danger), 정상 범위 내 만료 임박 시 주황색(text-warning) 적용
                            echo "<span class='fw-bold " . ($is_expired ? "text-danger" : "text-warning text-dark") . "'>" . $exp_date->format('Y-m-d') . "</span>";

                            // 연체 상태일 경우 추가 뱃지를 출력하여 관리자에게 강조
                            if ($is_expired)
                                echo ' <br><span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">연체</span>';
                            ?>
                        </td>

                        <td class="t-center px-4">
                            <button type="button" onclick="openSuspendModal(<?= $s['id'] ?>)"
                                class="btn btn-sm btn-outline-danger fw-bold px-3">휴점처리</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-start">
            <form method="POST" action="admin_view.php?page=manage_shops">

                <input type="hidden" name="action" value="to_suspend">
                <input type="hidden" name="id" id="suspend_shop_id">
                <input type="hidden" name="view" value="<?= SHOP_STATUS_ACTIVE ?>">

                <div class="modal-header bg-warning text-dark border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pause-circle me-2"></i>휴점 처리</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">휴점 처리일</label>
                        <input type="date" name="suspend_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small">휴점 사유</label>
                        <textarea name="suspend_reason" class="form-control" rows="3"
                            placeholder="예: 미납 연체로 인한 서비스 일시 중지" required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">휴점처리</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    /**
     * 특정 상점의 '휴점처리' 버튼을 클릭했을 때 작동하는 함수입니다.
     * @param {number} shopId - 클릭한 상점의 고유 DB ID 값
     */
    function openSuspendModal(shopId) {
        // 1. 폼 안에 숨겨진(hidden) 인풋 박스(id="suspend_shop_id")를 찾아서 클릭한 상점의 ID 값을 집어넣습니다.
        document.getElementById('suspend_shop_id').value = shopId;

        // 2. 부트스트랩의 모달 객체를 불러와서 화면에 띄워줍니다(.show()).
        bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal')).show();
    }
</script>