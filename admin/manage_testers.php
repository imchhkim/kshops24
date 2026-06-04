<?php

/**
 * [하위 파일] KShops24 개발자 테스트 도구 모음 (admin/manage_testers.php)
 * - 기능: /t_*.php 로 시작하는 각종 테스트 유틸리티를 탭 형태로 제공
 */

// AJAX 단독 호출을 대비하여 PDO 객체 존재 여부 확인
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// 현재 활성화된 탭(view)을 GET 파라미터로 받음. 기본값은 동기화 체크.
$view = $_GET['view'] ?? 't_check_files_sync';

// 테스트 파일 목록 정의 (탭 이름과 파일명 매핑)
$test_files = [
    't_check_files_sync' => ['name' => '파일 동기화 체크', 'file' => './testers/t_check_files_sync.php', 'icon' => 'bi-arrow-repeat'],
    't_find_duplicates_files' => ['name' => '중복 파일 탐지', 'file' => './testers/t_find_duplicates_files.php', 'icon' => 'bi-files'],
    't_find_const_funcs' => ['name' => '상수/함수 중복 검사', 'file' => './testers/t_find_const_funcs.php', 'icon' => 'bi-braces'],
    't_check_integrity_of_shops' => ['name' => '상점 무결성 점검', 'file' => './testers/t_check_integrity_of_shops.php', 'icon' => 'bi-shield-check'],
    't_test_shop_status' => ['name' => '상점 프로세스 검증', 'file' => './testers/t_test_shop_status.php', 'icon' => 'bi-shield-check'],
    't_mail_test' => ['name' => '이메일 발송 테스트', 'file' => './testers/t_mail_test.php', 'icon' => 'bi-envelope-check'],
    't_db_tables_ddl' => ['name' => 'DB Table scheme', 'file' => './testers/t_db_tables_ddl.php', 'icon' => 'bi-database'],
    't_check_over_usage' => ['name' => '추가 용량 과금 테스트', 'file' => './testers/t_check_over_usage.php', 'icon' => 'bi-cash-coin'],
    't_daily_check' => ['name' => '일간 배치(Cron) 테스트', 'file' => './testers/t_daily_check.php', 'icon' => 'bi-calendar-check'],
    't_populate_sample_shop' => ['name' => '샘플 상점 데이터 생성', 'file' => './testers/t_populate_sample_shop.php', 'icon' => 'bi-cart-plus'],
    't_static_kr_to_langs' => ['name' => '정적 한글 문구 다국어 등록', 'file' => './testers/t_static_kr_to_langs.php', 'icon' => 'bi-globe'],
    't_register_test_shop' => ['name' => '테스트 상점 생성', 'file' => './testers/t_register_test_shop.php', 'icon' => 'bi-building-add'],
    't_thumbnail_generator' => ['name' => '이미지 다이어트/청소', 'file' => './testers/t_thumbnail_generator.php', 'icon' => 'bi-images']
];

// 현재 view에 해당하는 파일이 존재하는지 확인
$target_file = isset($test_files[$view]) ? '../' . $test_files[$view]['file'] : null;

?>

<div class="tester-management-wrap">
    <!-- 탭 네비게이션 -->
    <div class="mb-4">
        <nav class="d-flex flex-wrap gap-2 p-3 bg-white rounded-4 shadow-sm border">
            <?php foreach ($test_files as $key => $info): ?>
                <?php $is_active = ($view == $key); ?>
                <a class="btn btn-sm <?= $is_active ? 'btn-primary shadow-sm' : 'btn-light border' ?> fw-bold rounded-pill px-3 py-2" href="admin_view.php?page=manage_testers&view=<?= $key ?>" id="tab-<?= $key ?>">
                    <i class="bi <?= $info['icon'] ?> <?= $is_active ? 'text-white' : 'text-primary' ?> me-1"></i> <?= $info['name'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- 탭 컨텐츠 영역 -->
    <div class="tab-content mt-4">
        <?php if ($target_file && file_exists($target_file)): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php
                    // 테스트 파일을 직접 실행하는 대신, iframe으로 감싸서 스타일 충돌을 방지하고 독립적인 환경을 제공합니다.
                    // 이렇게 하면 각 테스트 파일이 자체적인 HTML, CSS, JS를 가질 수 있습니다.
                    ?>
                    <iframe src="<?= $target_file ?>" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                선택한 테스트 파일을 찾을 수 없습니다: <strong><?= htmlspecialchars($view) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>