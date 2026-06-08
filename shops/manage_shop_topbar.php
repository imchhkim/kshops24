<?php
/**
 * KShops24 대시보드 - 상단 글로벌 헤더 모듈
 */
if (!isset($shop_id)) exit;
?>
<!-- 최상단 글로벌 헤더: 반응형 구조로 개선 -->
<header class="bg-white p-3 border-bottom sticky-top d-flex flex-column flex-md-row justify-content-between align-items-md-center shadow-sm gap-2">
    <div class="d-flex flex-column gap-2">
        <!-- 첫 번째 줄: 아이디, 카테고리, 상태 뱃지 -->
        <div class="d-flex flex-wrap align-items-center gap-1">
            <span class="badge bg-primary rounded-pill">ID #<?= $shop['id'] ?></span>

            <span class="badge bg-info text-dark fw-normal rounded-pill">
                <?= htmlspecialchars($shop_category_labels[$shop['category']] ?? strtoupper($shop['category'] ?? '일반')) ?>
            </span>

            <span class="badge bg-<?php echo $st['color']; ?> rounded-pill text-nowrap">
                <?php echo $st['text']; ?>
            </span>
        </div>

        <!-- 두 번째 줄: 상점명과 상점 보기 버튼 -->
        <div class="d-flex justify-content-between align-items-center gap-2">
            <strong class="h5 mb-0 text-truncate">
                <?php echo htmlspecialchars($shop['shop_name']); ?>
            </strong>

            <a href="/shop_view.php?subdomain=<?php echo $shop['subdomain']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap flex-shrink-0">
                <i class="bi bi-shop"></i> 상점 보기
            </a>
        </div>
    </div>
</header>