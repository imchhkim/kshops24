<?php

/**
 * KShops24 관리자 통합 레이아웃
 * 위치: /public_html/admin/admin_view.php
 * [업데이트] 2026-02-14: 개별 상점 전담 관리 페이지(manage_shop) 라우팅 추가
 */

// 1. 관리자 공통 엔진 로드 (상위 common 폴더 참조)
$header_path = __DIR__ . '/../common/admin_common_header.php';

if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("오류: 관리자 헤더 파일을 찾을 수 없습니다. (경로: $header_path)");
}

// [환경 감지] 슈퍼 관리자 페이지 브랜드 로고 색상 설정 (IS_TEST_ENV 상수에 따라 자동 변경)
$admin_brand_color = IS_TEST_ENV ? '#ef4444' : '#00d4ff';

// 2. 페이지 라우팅
$page = $_GET['page'] ?? 'admin_dashboard'; // 접속 시 기본 화면을 대시보드로 변경
$allowed_pages = [
    'admin_dashboard'    => 'admin_dashboard.php', // 관리자 대시보드
    'manage_shops'       => 'manage_shops.php',
    'manage_expiring_shops' => 'manage_expiring_shops.php',
    'manage_shop' => 'manage_shop.php', // 개별 상점 상세 관리 페이지 추가
    'manage_customers'   => 'manage_customers.php', // 통합 고객 관리 페이지 추가
    'manage_site'        => 'manage_site.php',
    'manage_testers'      => 'manage_testers.php',
    'manage_telegram'    => 'manage_telegram.php'
];

$content_file = $allowed_pages[$page] ?? 'admin_dashboard.php';

// 페이지 타이틀 동적 설정
$page_title = '<i class="bi bi-speedometer2 me-2"></i>관리자 대시보드'; // 예외 파라미터 대비 기본값

if ($page === 'manage_site') {
    $page_title = '<i class="bi bi-gear me-2"></i>사이트 통합 설정';
} elseif ($page === 'manage_shop') {
    $page_title = '<i class="bi bi-shop me-2"></i> 상점 상세 관리';
} elseif ($page === 'manage_expiring_shops') {
    $page_title = '<i class="bi bi-clock-history me-2"></i> 만료 임박 상점';
} elseif ($page === 'manage_shops') {
    $page_title = '<i class="bi bi-shop me-2"></i> 상점 상태 관리';
} elseif ($page === 'manage_customers') {
    $page_title = '<i class="bi bi-people me-2"></i> 고객 관리';
} elseif ($page === 'manage_testers') {
    $page_title = '<i class="bi bi-bug me-2"></i>테스터 관리';
} elseif ($page === 'manage_telegram') {
    $page_title = '<i class="bi bi-telegram me-2"></i> 텔레그램 연동 상태';
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KShops24 Admin - <?php echo $page_title; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- [필수] Summernote 및 jQuery 라이브러리 로드 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

    <style>
        :root {
            --admin-navy: #1a1c23;
            --admin-blue: #0056b3;
            --admin-bg: #f8f9fa;
        }

        body {
            background-color: var(--admin-bg) !important;
            margin: 0;
            padding: 0;
            font-family: 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;
        }

        .navbar-custom {
            background: var(--admin-navy) !important;
            min-height: 70px;
            padding: 0.5rem 1.5rem;
            border-bottom: 2px solid #2d333b;
            z-index: 1030;
        }

        .navbar-brand-custom {
            color: <?php echo $admin_brand_color; ?> !important;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: none;
            font-size: 1.2rem;
        }

        .nav-link-custom {
            color: #9ea7ad !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 6px;
            margin: 0 5px;
            display: block;
        }

        .nav-link-custom:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-link-custom.active {
            color: #fff !important;
            background: var(--admin-blue) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .admin-main-container {
            padding: 30px 0 60px 0;
        }

        .content-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            padding: 30px;
            min-height: 75vh;
        }

        .admin-footer {
            text-align: center;
            padding: 30px 0;
            color: #adb5bd;
            font-size: 0.85rem;
            border-top: 1px solid #dee2e6;
            background: #fff;
        }

        .msg-auto-close {
            position: fixed;
            top: 85px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
        }

        /* [공통 UI] 내부 탭 스타일 (manage_site, manage_shops, manage_shop 공용) */
        .inner-tab-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 25px;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        .inner-tab-container::-webkit-scrollbar {
            display: none;
        }

        .inner-tab-nav {
            display: flex;
            gap: 5px;
            border-bottom: none;
            margin-bottom: 0;
            flex-wrap: nowrap;
        }

        .inner-tab-nav a {
            <?= $UI_STYLE['tab_label'] ?>padding: 12px 20px;
            text-decoration: none;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
            position: relative;
            cursor: pointer;
        }

        .inner-tab-nav a.active {
            color: #4e73df;
            border-bottom-color: #4e73df;
        }

        /* [UI 보호막 강화] 외부 스타일 유출로부터 관리자 핵심 UI 강제 보호 */
        .navbar-custom .btn#admin-logout-btn,
        .site-management-wrap .btn:not(.note-btn),
        .inner-tab-nav a {
            font-family: 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif !important;
            padding: 0.25rem 1rem !important;
            /* btn-sm px-3 규격 강제 */
            <?= $UI_STYLE['tab_label'] ?>line-height: 1.5 !important;
            height: auto !important;
            text-transform: none !important;
            letter-spacing: normal !important;
        }

        /* 알약 모양 버튼(rounded-pill)이 사각형으로 변하는 것을 방지 */
        .rounded-pill {
            border-radius: 50rem !important;
        }

        .admin-page-title {
            <?= $UI_STYLE['page_title'] ?>
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom navbar-dark sticky-top shadow-sm" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand-custom" href="admin_view.php?page=manage_shops">
                <i class="bi bi-cpu-fill me-2"></i>KShops24 Admin
            </a>

            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto ms-lg-4 mt-3 mt-lg-0 gap-1 gap-lg-0">
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'admin_dashboard') ? 'active' : ''; ?>"
                            href="admin_view.php?page=admin_dashboard">
                            <i class="bi bi-speedometer2 me-1"></i> 대시보드
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'manage_shops') ? 'active' : ''; ?>"
                            href="admin_view.php?page=manage_shops">
                            <i class="bi bi-arrow-repeat me-1"></i> 상점 상태 관리
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'manage_shop') ? 'active' : ''; ?>"
                            href="admin_view.php?page=manage_shop">
                            <i class="bi bi-shop me-1"></i> 개별 상점 관리
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'manage_customers') ? 'active' : ''; ?>"
                            href="admin_view.php?page=manage_customers">
                            <i class="bi bi-people me-1"></i> 고객 관리
                        </a>
                    </li>                    
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'manage_site') ? 'active' : ''; ?>"
                            href="admin_view.php?page=manage_site">
                            <i class="bi bi-gear-fill me-1"></i> 사이트 통합 설정
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-custom <?php echo ($page == 'manage_testers') ? 'active' : ''; ?>"
                            href="admin_view.php?page=manage_testers">
                            <i class="bi bi-bug me-1"></i> 테스터 관리
                        </a>
                    </li>
                </ul>

                <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center text-white-50 small mt-3 mt-lg-0 border-top border-secondary pt-3 pt-lg-0 border-lg-0 border-opacity-50">
                    <span class="me-lg-3 mb-2 mb-lg-0">
                        <i class="bi bi-person-circle me-1"></i>
                        <strong><?php echo htmlspecialchars($admin_name ?? '관리자'); ?></strong>님
                    </span>
                    <a href="logout.php" id="admin-logout-btn" class="btn btn-sm btn-outline-danger border-0 fw-bold rounded-pill px-3 align-self-start align-self-lg-auto">
                        <i class="bi bi-box-arrow-right"></i> 로그아웃
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container admin-main-container">
        <div class="row">
            <div class="col-12">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-info msg-auto-close shadow-sm border-start border-4 border-info">
                        <i class="bi bi-info-circle me-2"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                    <h4 class="admin-page-title mb-0"><?php echo $page_title; ?></h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item">Admin</li>
                            <li class="breadcrumb-item active"><?php echo ucfirst($page); ?></li>
                        </ol>
                    </nav>
                </div>

                <div class="content-card">
                    <?php
                    if (file_exists($content_file)) {
                        include $content_file;
                    } else {
                        echo "<div class='alert alert-danger'>콘텐츠 파일을 찾을 수 없습니다. (경로: $content_file)</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="admin-footer">
        <div class="container">
            <p class="mb-0">&copy; 2026 <strong>KShops24</strong>. Developed by <strong>Charlie Kim</strong>.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // [UI 버그 해결] Bootstrap 모달이 부모 요소의 CSS 영향을 받아 
            // 모달창 앞을 회색 배경막(Backdrop)이 가려버리는 현상을 완벽히 방지합니다.
            $('.modal').appendTo('body');

            // jQuery 이벤트 위임을 사용하여 동적으로 로드된 컨텐츠 내부의 버튼 클릭을 감지합니다.

            // 결제 내역 수정 버튼 클릭 이벤트
            $(document).on('click', '.btn-edit-payment', function() {
                const btn = $(this);
                $('#edit_payment_id').val(btn.data('id'));
                $('#edit_pay_type').val(btn.data('type'));
                $('#edit_amount').val(btn.data('amount'));
                $('#edit_billing_date').val(btn.data('billing'));
                $('#edit_expiring_date').val(btn.data('expiring') || '');
                $('#edit_note').val(btn.data('note') || '');

                const paidCheck = $('#edit_paid_check');
                const payDateInput = $('#edit_pay_date');

                if (btn.data('paid') === 'y') {
                    paidCheck.prop('checked', true);
                    payDateInput.prop('disabled', false).val(btn.data('paydate') || '');
                } else {
                    paidCheck.prop('checked', false);
                    payDateInput.prop('disabled', true).val('');
                }

                // [수정] new bootstrap.Modal()을 반복 호출하면 배경막이 중첩되어 먹통이 되므로 getOrCreateInstance 사용
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editPaymentModal')).show();
            });

            // 대화/알림 수정 버튼 클릭 이벤트
            $(document).on('click', '.btn-edit-board', function() {
                const btn = $(this);
                $('#edit_board_id').val(btn.data('id'));
                $('#edit_board_title').val(btn.data('title'));
                $('#edit_board_content').val(btn.data('content'));
                $('#edit_board_type').val(btn.data('type'));

                bootstrap.Modal.getOrCreateInstance(document.getElementById('editBoardModal')).show();
            });
        });
    </script>

    <script>
        // [UX 개선] 페이지 이동 시 스크롤 위치 유지
        document.addEventListener('DOMContentLoaded', function() {
            const scrollPos = sessionStorage.getItem('adminScrollPos');
            if (scrollPos) {
                setTimeout(() => {
                    window.scrollTo(0, parseInt(scrollPos, 10));
                    sessionStorage.removeItem('adminScrollPos');
                }, 50);
            }

            // 페이지 이동을 유발하는 링크에 이벤트 리스너 추가 (이벤트 위임)
            document.body.addEventListener('click', function(e) {
                const link = e.target.closest('.nav-link-custom, .inner-tab-nav a, .pagination a');
                // AJAX 링크(결제내역 탭)는 제외하고, 일반 페이지 이동 링크에만 적용
                if (link && !link.classList.contains('ajax-pay-link')) {
                    sessionStorage.setItem('adminScrollPos', window.scrollY);
                }
            });
        });
    </script>

    <?php
    // --- 3. 공통 푸터 엔진 로드 (상위 폴더 참조) ---
    // common_footer.php 안에 </body> </html> 닫기 태그가 포함되어 있으므로 가장 마지막에 로드합니다.
    require_once __DIR__ . '/../common/common_footer.php';
    ?>