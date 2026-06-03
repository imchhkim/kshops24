<?php

/**
 * KShops24 고객 관리 모듈 (manage_shop_customer.php)
 * - 역할: 해당 상점에 카카오 로그인을 통해 가입한 고객 리스트 조회 및 검색
 * - 실행: manage_shop.php 컨테이너 내에서 include 되어 실행됨
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// 1. 검색어 및 페이지 파라미터 수신
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$items_per_page = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 20; // 한 페이지에 표시할 고객 수

// 2. 쿼리 빌드 (조건부 검색)
$base_query = "FROM platform_customers pc JOIN shop_customer_mapping scm ON pc.id = scm.customer_id WHERE scm.shop_id = ?";
$params = [$shop_id];

if (!empty($search)) {
    // 닉네임 또는 전화번호(하이픈 제외)로 검색 가능하도록 설정
    $base_query .= " AND (pc.nickname LIKE ? OR REPLACE(pc.ph_phone, '-', '') LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%" . preg_replace('/[^0-9]/', '', $search) . "%";
}

// 3. 전체 데이터 수 및 총 페이지 수 계산
$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $base_query);
$stmt_count->execute($params);
$total_customers = $stmt_count->fetchColumn();
$total_pages = ceil($total_customers / $items_per_page) ?: 1; // 최소 1페이지 보장

// 4. 현재 페이지 데이터 로드 (최근 가입자순 정렬)
$offset = ($page - 1) * $items_per_page;
// [통합고객] 플랫폼 회원정보(pc.*)와 해당 상점 최초 유입일(shop_joined_at), 최근방문일(last_login_at) 로드
$query = "SELECT pc.*, scm.created_at as shop_joined_at, scm.last_login_at " . $base_query . " ORDER BY scm.created_at DESC LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="container-fluid p-0">
    <!-- 최상단 타이틀 -->
    <?php echo renderPageHeader('고객 관리', 'bi-people'); ?>

    <!-- 요약 위젯 -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold">가입된 총 고객 수</small>
                <h3 class="fw-bold mb-0 text-primary mt-1"><?php echo number_format($total_customers); ?> 명</h3>
            </div>
        </div>
    </div>

    <!-- 검색 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="manage_shop.php" class="row g-2 align-items-center">
                <input type="hidden" name="pg" value="manage_shop_customer">
                <div class="col-12 col-md-8 col-lg-6 d-flex gap-2">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="닉네임 또는 전화번호 검색" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary px-3 fw-bold">검색</button>
                    <?php if (!empty($search)): ?>
                        <a href="manage_shop.php?pg=manage_shop_customer" class="btn btn-outline-secondary">초기화</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- 고객 목록 테이블 -->
    <div class="<?php echo UI_SECTION_CARD; ?>">
        <div class="p-3 p-md-4 d-flex flex-column h-100">
            <?php echo renderSectionHeader('가입 고객 목록', 'bi-list-ul'); ?>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th style="width: 60px;" class="text-center">프로필</th>
                            <th>고객정보(닉네임)</th>
                            <th>연락처</th>
                            <th>배달 주소 및 랜드마크</th>
                            <th class="text-center">최근 로그인</th>
                            <th class="text-center">최초 가입일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x fs-1 d-block mb-2 text-opacity-50"></i>
                                    <?php echo !empty($search) ? '검색된 고객이 없습니다.' : '아직 가입된 고객이 없습니다.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (!empty($c['profile_img'])): ?>
                                            <img src="<?php echo htmlspecialchars($c['profile_img']); ?>" class="rounded-circle shadow-sm" style="width: 45px; height: 45px; object-fit: cover; border: 2px solid #fff;">
                                        <?php else: ?>
                                            <div class="bg-secondary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center text-secondary mx-auto shadow-sm" style="width: 45px; height: 45px; border: 2px solid #fff;">
                                                <i class="bi bi-person-fill fs-4"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($c['nickname']); ?></td>
                                    <td>
                                        <?php echo $c['ph_phone'] ? htmlspecialchars(formatPHPhone($c['ph_phone'])) : '<span class="text-muted small">미등록</span>'; ?>
                                    </td>
                                    <td>
                                        <div class="small text-dark mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?php echo htmlspecialchars($c['ph_address'] ?: '미등록'); ?></div>
                                        <div class="small text-muted"><i class="bi bi-flag-fill text-warning me-1 opacity-75"></i> <?php echo htmlspecialchars($c['ph_landmark'] ?: '-'); ?></div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-secondary border fw-normal"><?php echo substr($c['last_login_at'], 0, 16); ?></span></td>
                                    <td class="text-center"><small class="text-muted"><?php echo substr($c['shop_joined_at'], 0, 10); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이지네이션 -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            $search_param = !empty($search) ? '&search=' . urlencode($search) : '';

                            if ($page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?pg=manage_shop_customer' . $search_param . '&p=' . ($page - 1) . '"><i class="bi bi-chevron-left"></i></a></li>';
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?pg=manage_shop_customer' . $search_param . '&p=' . $i . '">' . $i . '</a></li>';
                            }

                            if ($page < $total_pages) {
                                echo '<li class="page-item"><a class="page-link" href="?pg=manage_shop_customer' . $search_param . '&p=' . ($page + 1) . '"><i class="bi bi-chevron-right"></i></a></li>';
                            }
                            ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>