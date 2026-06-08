<?php

/**
 * KShops24 반응형 통합 대시보드 - 메인 프레임
 * 위치: /public_html/shops/manage_shop.php
 * 역할: 
 * 1. 상점 관리자의 인증 상태를 유지하고 기본 화면 레이아웃(사이드바, 하단 탭)을 제공합니다.
 * 2. `pg` 파라미터에 따라 하위 메뉴(대시보드, 상점설정, 고객관리, 메뉴관리 등)를 동적으로 로드합니다.
 * 3. 상점 기본 정보(이름, 연락처, 테마, 이미지 등)를 수정하고 DB에 반영하는 핵심 컨트롤러 역할을 수행합니다.
 */

// [핵심 수정] 출력 버퍼링을 가장 먼저 시작합니다.
// AJAX 요청 시 공통 헤더(manage_common_header.php) 등에서 예기치 않게 발생하는 
// PHP Warning 등의 HTML 에러 찌꺼기가 JSON 응답에 섞이는 것을 원천 차단합니다.
ob_start();

// 출력 버퍼링 시작 (AJAX JSON 응답 시 상단 HTML 찌꺼기가 섞이는 문제 원천 차단)
// [핵심 수정] AJAX 요청 시에는 manage_shop.php의 HTML 출력을 완전히 건너뛰고,
// 해당 모듈(manage_shop_homepage.php 등)이 직접 JSON 응답을 처리하도록 합니다.
if (isset($_POST['ajax_update']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
    // 공통 헤더는 필요하므로 로드
    require_once $_SERVER['DOCUMENT_ROOT'] . '/common/manage_common_header.php';
    // 요청된 페이지 모듈만 로드하여 JSON 응답을 처리하도록 위임
    $pg = $_POST['pg'] ?? $_GET['pg'] ?? 'manage_shop_dashboard';

    // [버그 수정] 상점 설정(shop) 탭의 AJAX 요청을 정확한 파일명으로 매핑합니다.
    if ($pg === 'shop') {
        $pg = 'manage_shop_info';
    }

    $common_pages = [
        'manage_shop_dashboard',
        'manage_shop_info',
        'manage_shop_homepage',
        'manage_shop_customer',
        'manage_shop_review',
        'manage_shop_billing',
        'manage_shop_emojis'
    ];

    if (in_array($pg, $common_pages)) {
        $target = "./{$pg}.php";
        if (file_exists($target)) include $target;
    } else {
        $shop_category = !empty($shop['category']) ? $shop['category'] : 'fnb';
        $target = "./{$shop_category}/admin/{$pg}.php";
        if (file_exists($target)) include $target;
    }
    exit; // HTML 출력을 완전히 막고 종료
}

// 1. 상점 관리자 공통 헤더 로드 (세션 검증 및 $shop 정보 로드 포함)
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/manage_common_header.php';

// [환경 감지] 상점 관리자 페이지 브랜드 로고 색상 설정 (IS_TEST_ENV 상수에 따라 자동 변경)
$dash_brand_color = IS_TEST_ENV ? '#ef4444' : '#004aad';

// 프론트엔드 출력을 위해 업데이트된 UI 설정값 디코딩
$ui = json_decode($shop['ui_settings'] ?? '{}', true);

// 상점의 현재 상태값에 따라 뱃지에 표시할 텍스트와 색상 설정
switch ($shop['status']) {
    case 'active':
        $st = ['text' => '운영중', 'color' => 'success'];
        break;
    case 'testing':
        $st = ['text' => '작업중', 'color' => 'info'];
        break;
    case 'closed':
        $st = ['text' => '폐점', 'color' => 'dark'];
        break;
    default:
        $st = ['text' => '승인대기', 'color' => 'warning'];
        break;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'img_deleted') {
        $message = "사진이 삭제되었습니다.";
        $msg_type = "info";
    }
}

// 대시보드 첫 화면에 노출될 본사(KShops24) 관리자 메시지 로드
if (!isset($_GET['pg']) || $_GET['pg'] === 'manage_shop_dashboard') {
    $admin_messages = [];
    try {
        $sql = "SELECT * FROM admin_messages WHERE shop_id = 0 OR shop_id = ? ORDER BY created_at DESC LIMIT 5";
        $stmt_msg = $pdo->prepare($sql);
        $stmt_msg->execute([$shop_id]);
        $admin_messages = $stmt_msg->fetchAll();
    } catch (PDOException $e) {
        $admin_messages = [
            ['id' => 99, 'title' => '🎉 KShops24 파트너가 되신 것을 환영합니다!', 'content' => '사장님, 반갑습니다!<br>이곳 대시보드에서 본사가 보내는 중요한 공지사항을 확인하실 수 있습니다.', 'created_at' => date('Y-m-d H:i:s')]
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Dashboard - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <style>
        :root {
            /* PC 버전 사이드바의 고정 너비 설정 */
            --sidebar-width: 260px;
            --accent: #004aad;
        }

        body {
            background: #f4f7fa;
            font-family: 'Pretendard', sans-serif;
            overflow-x: hidden;
        }

        /* [PC 화면 전용 디자인] 사이드바를 고정시키고 본문 영역을 우측으로 밀어냅니다. */
        @media (min-width: 992px) {
            .sidebar {
                width: var(--sidebar-width);
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                background: #fff;
                border-right: 1px solid #e3e6f0;
                z-index: 1000;
                display: flex;
                flex-direction: column;
                overflow: hidden; /* 사이드바 전체 영역 바깥으로 삐져나가는 것을 원천 차단 */
            }

            .content-wrapper {
                margin-left: var(--sidebar-width);
            }

            .bottom-nav {
                display: none;
            }
        }

        /* [모바일 화면 전용 디자인] 사이드바를 숨기고 모바일 앱처럼 하단 네비게이션(Bottom Nav)을 표시합니다. */
        @media (max-width: 991.98px) {
            .sidebar {
                display: none;
            }

            .content-wrapper {
                margin-left: 0;
                padding-bottom: 80px;
            }

            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #fff;
                border-top: 1px solid #eee;
                display: flex;
                z-index: 1001;

                /* 가로 스크롤 활성화 */
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                /* Firefox 스크롤바 숨김 */
            }

            .bottom-nav::-webkit-scrollbar {
                display: none;
                /* Chrome, Safari 스크롤바 숨김 */
            }

            .bottom-nav .nav-link {
                flex: 0 0 auto;
                /* 아이템이 화면에 맞게 억지로 축소되는 것 방지 */
                width: 22vw;
                /* 모바일 화면 기준 약 4.5개가 보이도록 설정하여 스크롤 유도 */
                min-width: 75px;
                padding-top: 12px;
                padding-bottom: 12px;
            }
        }

        /* 사이드바 메뉴 스크롤 최적화 */
        .sidebar-menu-scroll {
            flex: 1 1 0; /* 남은 공간을 정확히 차지하도록 강제 (스크롤바 생성 핵심) */
            overflow-y: auto;
            overflow-x: hidden; /* 가로 스크롤 및 오른쪽 확장 방지 */
            padding-bottom: 20px; /* 맨 아래 메뉴가 스크롤 끝에서 잘리지 않도록 여유 확보 */
            flex-wrap: nowrap !important; /* Bootstrap .nav 기본 속성인 wrap으로 인해 세로 공간 부족 시 메뉴가 우측 열로 넘어가는 현상 완벽 차단 */
        }
        .sidebar-menu-scroll::-webkit-scrollbar {
            width: 6px; /* 스크롤바가 확실히 보이도록 두께 약간 상향 */
        }
        .sidebar-menu-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .nav-link.active {
            background-color: #4e73df !important;
            color: white !important;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.05);
        }

        /* [추가] 수정 중인 입력 필드 강조 (노란색) */
        .form-control:focus {
            background-color: #fffde7 !important;
            border-color: #004aad !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 74, 173, 0.1) !important;
        }

        /* [추가] 메인 배경 이미지 갤러리 리스트 1줄에 2개씩 꽉 차게 렌더링 */
        #bg-list-container .gallery-item {
            width: calc(50% - 4px) !important;
            /* gap-2(8px)를 고려하여 정확히 절반 크기 */
            height: auto !important;
            aspect-ratio: 16/9;
            /* 배경 이미지에 맞는 세련된 16:9 비율 적용 */
        }

        /* [추가] 매장 사진 갤러리 리스트 1줄에 2개씩 꽉 차게 렌더링 */
        #shop-gallery-container .gallery-item {
            width: calc(50% - 4px) !important;
            height: auto !important;
            aspect-ratio: 1/1;
            /* 갤러리 사진에 맞는 세련된 1:1 정방형 비율 적용 */
        }

        /* =========================================================================
        [KShops24 공통 UI] 섹션 제목 (Section Titles)
        - 사용법: <div class="section-title-lg">큰 제목</div>
        - 사용법: <div class="section-title-md">중간 제목</div>
        - 사용법: <div class="section-title-sm">작은 제목</div>
        ========================================================================= */

        /* 1. 대형 제목 (페이지 메인 타이틀용) */
        .section-title-lg {
            font-size: 1.75rem;
            /* 약 28px */
            font-weight: 800;
            /* 매우 굵게 */
            color: #1e293b;
            /* 짙은 남색 계열 (Dark Slate) */
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            /* 연한 회색 밑줄 */
            position: relative;
        }

        /* 대형 제목 하단의 파란색 포인트 라인 (디자인 요소) */
        .section-title-lg::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 60px;
            height: 2px;
            background-color: #004aad;
            /* KShops24 메인 블루 */
        }

        /* 2. 중형 제목 (카드 내 메인 섹션 분리용) */
        .section-title-md {
            font-size: 1.25rem;
            /* 약 20px */
            font-weight: 700;
            /* 굵게 */
            color: #334155;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        /* 중형 제목 왼쪽의 세로 바 (디자인 요소) */
        .section-title-md::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 18px;
            background-color: #004aad;
            margin-right: 10px;
            border-radius: 2px;
        }

        /* 3. 소형 제목 (폼 그룹이나 작은 항목 묶음용) */
        .section-title-sm {
            font-size: 0.95rem;
            /* 약 15px */
            font-weight: 700;
            color: #64748b;
            /* 약간 흐린 색상으로 계층 구분 */
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            /* 영문일 경우 대문자 변환 */
            letter-spacing: 0.5px;
            /* 자간을 살짝 넓혀 세련됨 강조 */
        }

        /* 마우스 호버 시 살짝 커지는 공통 애니메이션 효과 */
        .transition-all {
            transition: all 0.2s ease-in-out;
        }

        .transition-all:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }


        /* =========================================================================
        [KShops24 공통 UI] 반응형 박스 레이아웃 (.box-responsive-between)
        - 모바일: 상하(Column) 배치, 중앙 정렬, 요소 간 간격 1rem
        - PC: 좌우(Row) 배치, 양끝 정렬(여백 자동), 세로 중앙 정렬
        ========================================================================= */
        .box-responsive-between {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .box-responsive-between {
                flex-direction: row;
                /* space-between 대신 요소를 양 끝으로 밀어내는 방식은 HTML의 ms-auto로 제어하는 게 더 유연합니다. */
                justify-content: flex-start;
                align-items: center;
                /* PC에서 세로 높이 중앙 정렬 */
                text-align: left;
                gap: 1.5rem;
                /* PC에서는 요소 사이 최소 간격을 살짝 더 줍니다. */
            }
        }
    </style>
</head>

<body>

    <!-- [UI 분리] PC 화면 전용 사이드바 네비게이션 -->
    <?php require_once __DIR__ . '/manage_shop_sidebar.php'; ?>

    <!-- 우측 메인 컨텐츠 영역 (모바일에서는 전체 너비 사용) -->
    <div class="content-wrapper">
        <!-- [UI 분리] 상단 글로벌 헤더 -->
        <?php require_once __DIR__ . '/manage_shop_topbar.php'; ?>

        <main class="p-4">
            <div class="row g-4">
                <div class="col-12">
                    <!-- PHP 로직 단에서 $message 변수에 값이 담기면 JS 함수를 트리거하여 화면 하단에 알림을 띄웁니다. -->
                    <?php if ($message): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                showToast("<?php echo addslashes($message); ?>", "<?php echo $msg_type ?? 'success'; ?>");
                            });
                        </script>
                    <?php endif; ?>

                    <!-- [메인 라우터] URL의 '?pg=' 값에 따라 본문 영역에 인클루드할 모듈(파일)을 동적으로 결정합니다. -->
                    <?php
                    // [수정] AJAX POST 요청 시에도 pg 파라미터를 정확히 인식하도록 보완
                    $pg = $_POST['pg'] ?? $_GET['pg'] ?? 'manage_shop_dashboard';
                    $shop_category = !empty($shop['category']) ? $shop['category'] : 'fnb';

                    if ($pg === 'shop') {
                        $pg = 'manage_shop_info';
                    }

                    // 공통 기능 페이지 목록 (루트 폴더 기준)
                    $common_pages = [
                        'manage_shop_dashboard',
                        'manage_shop_info',
                        'manage_shop_homepage',
                        'manage_shop_customer',
                        'manage_shop_review',
                        'manage_shop_billing',
                        'manage_shop_emojis'
                    ];

                    if ($pg === 'manual_domain') {
                        // [매뉴얼 로드] iframe을 사용하여 기존 HTML 파일을 대시보드 내부에 임베드
                        echo '<div class="bg-white rounded-4 shadow-sm overflow-hidden" style="height: calc(100vh - 160px); min-height: 600px;">
                                <iframe src="/manual/KShops24_domain_manual.html" style="width: 100%; height: 100%; border: none;" title="외부 도메인 연결 매뉴얼"></iframe>
                              </div>';
                    } elseif (in_array($pg, $common_pages)) {
                        // [공통 모듈 동적 로드]
                        $target = "./{$pg}.php";
                        if (file_exists($target)) include $target;
                    } else {
                        // [카테고리 특화 모듈 동적 로드] (fnb, realty, beauty 등 카테고리 폴더에서 탐색)
                        $target = "./{$shop_category}/admin/{$pg}.php";
                        if (file_exists($target)) {
                            include $target;
                        } else {
                            // 매칭되는 모듈이 없을 경우 대시보드로 Fallback
                            include './manage_shop_dashboard.php';
                        }
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <!-- [UI 분리] 모바일 화면 전용 하단 네비게이션 및 관련 모달 -->
    <?php require_once __DIR__ . '/manage_shop_bottom_nav.php'; ?>

    <!-- [버그 수정] Bootstrap JS 로드 (모달, 토스트, 캐러셀 등 모든 UI 컴포넌트 동작에 필수) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // [모바일 UX 개선] 하단 네비게이션(Bottom Nav) 가로 스크롤 위치 유지
        // 페이지 이동(새로고침) 시에도 사용자가 스크롤해 둔 메뉴 위치를 그대로 복원하여 앱처럼 자연스러운 경험을 제공합니다.
        document.addEventListener('DOMContentLoaded', function() {
            const bottomNav = document.querySelector('.bottom-nav');
            if (bottomNav) {
                // 1. 이전 페이지에서 저장된 스크롤 위치가 있다면 복원
                const savedNavScroll = sessionStorage.getItem('bottomNavScrollPos');
                if (savedNavScroll) {
                    bottomNav.scrollLeft = parseInt(savedNavScroll, 10);
                }
                // 2. 메뉴 항목을 클릭할 때 현재 스크롤 위치를 저장
                bottomNav.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', function() {
                        sessionStorage.setItem('bottomNavScrollPos', bottomNav.scrollLeft);
                    });
                });
            }
        });
    </script>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/common/common_footer.php'; ?>