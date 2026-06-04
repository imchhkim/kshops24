<?php

/**
 * KShops24 메인 포털 (Index Page)
 * [2026-02-23] 
 * 1. 가문 원칙 준수: 기존 UI 섹션(특별함 등) 절대 보존
 * 2. 레이어드 구조 적용: common/common_header.php 및 common_footer.php 연동
 */

// 1. 공통 엔진 로드 (DB 연결 및 세션 관리 통합)
require_once 'common/common_header.php';

// [방문 기록] 포털 메인 방문 (shop_id = 0)
if (function_exists('recordVisitor')) recordVisitor($pdo, 0);

try {
    /**
     * [로직 1] 활성화된 상점 목록 로드
     */
    $stmt = $pdo->query("SELECT shop_name, subdomain, phone_mobile, shop_intro, logo_path, category 
                         FROM shops 
                         WHERE status = 'active' 
                         ORDER BY id DESC");
    $shops = $stmt->fetchAll();

    /**
     * [로직 2] 최신 공지사항 로드
     */
    $notice_stmt = $pdo->query("SELECT id, title, content, hit, created_at FROM shop_board 
                                WHERE shop_id = 0 AND type = 'notice' 
                                ORDER BY id DESC LIMIT 5");
    $main_notices = $notice_stmt->fetchAll();
} catch (PDOException $e) {
    $shops = [];
    $main_notices = [];
    $message = "데이터 로딩 오류가 발생했습니다.";
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KShops24 - 24시간 일하는 필리핀 한인 상점 포털</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --main-blue: #004aad;
            --accent-gold: #ffc107;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --light-gray: #f8f9fa;
        }

        body {
            font-family: 'Apple SD Gothic Neo', sans-serif;
            background-color: #ffffff;
            color: var(--text-dark);
        }

        /* --- Hero Section 개선 (상단 여백 최소화) --- */
        .hero {
            /* 1. 3중 그라데이션 오버레이 유지 */
            background: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.75)),
                url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;

            /* 2. 배경 스크롤 설정 유지 */
            background-attachment: scroll;

            color: white;
            /* [수정 포인트] 상단 padding을 40px로 줄여 위로 바짝 붙였습니다. 하단은 120px로 균형을 맞췄습니다. */
            padding: 40px 0 120px 0;
            text-align: center;
            position: relative;
        }

        .hero h1 {
            font-weight: 800;
            font-size: clamp(2.2rem, 6vw, 3.8rem);
            /* 2. 텍스트 그림자를 더 깊게 주어 글자가 배경에서 튀어나오게 함 */
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.8);
            line-height: 1.2;
        }

        .hero .lead-text {
            /* 기존 클래스 호환을 위한 네이밍 유지 */
            font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            max-width: 800px;
            margin: 25px auto;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
            color: #f8f9fa;
            /* 순백색보다 약간 부드러운 화이트로 가독성 확보 */
        }

        /* 프로모션 배지 유리 효과(Glassmorphism) */
        .promo-pill {
            background: rgba(0, 74, 173, 0.2) !important;
            backdrop-filter: blur(8px);
            border: 2px solid rgba(0, 74, 173, 0.5) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .promo-pill:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4) !important;
        }

        /* --- 이하 기존 버튼 및 섹션 스타일 절대 수정 금지 준수 --- */
        .btn-apply {
            background-color: var(--accent-gold);
            color: var(--text-dark);
            font-weight: bold;
            padding: 14px 32px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-apply:hover {
            background-color: #ffca2c;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-manage {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            color: white;
            font-weight: bold;
            padding: 14px 32px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .btn-manage:hover {
            background-color: white;
            color: var(--main-blue);
            transform: translateY(-2px);
        }

        /* --- Notice Section --- */
        .notice-section {
            margin-top: -60px;
            position: relative;
            z-index: 10;
        }

        .notice-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
            background-color: white;
            overflow: hidden;
            /* For rounded corners */
        }

        .notice-header {
            background: var(--main-blue);
            color: white;
        }

        .notice-list-item {
            border: none !important;
            border-bottom: 1px solid #f1f3f5 !important;
            transition: background-color 0.2s ease-in-out;
        }

        .notice-list-item:last-child {
            border-bottom: none !important;
        }

        .notice-list-item:hover {
            background-color: #f8faff;
        }

        /* --- Shop List Section --- */
        .section-title {
            font-weight: 800;
            color: var(--text-dark);
        }

        .shop-card {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            background-color: #fff;
            overflow: hidden;
        }

        .shop-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 74, 173, 0.1);
            border-color: var(--main-blue);
        }

        .shop-card-logo {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #eee;
            flex-shrink: 0;
        }

        .shop-card .card-title {
            font-weight: 700;
            color: var(--text-dark);
        }

        .shop-card .card-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.7rem;
            /* 2 lines */
        }

        .shop-card .btn {
            font-weight: 600;
        }

        /* --- Features Section --- */
        .features-section {
            background-color: var(--light-gray);
        }

        .feature-item {
            text-align: center;
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px auto;
            background-color: rgba(0, 74, 173, 0.1);
            color: var(--main-blue);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            transition: all 0.3s ease;
        }

        .feature-item:hover .feature-icon {
            background-color: var(--main-blue);
            color: white;
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 74, 173, 0.2);
        }

        /* --- Footer --- */
        .main-footer {
            background-color: #212529;
            color: #adb5bd;
        }

        .main-footer a {
            color: #dee2e6;
            text-decoration: none;
        }

        .main-footer a:hover {
            color: white;
            text-decoration: underline;
        }

        /* --- 모바일 프로모션 배지 최적화 --- */
        @media (max-width: 768px) {
            .promo-pill {
                padding: 12px 5px !important;
            }
            .promo-pill .promo-icon {
                font-size: 1.8rem !important; /* 약 2/3로 축소 */
            }
            .promo-pill .promo-text-sub {
                font-size: 0.75rem !important;
            }
            .promo-pill .promo-text-main {
                font-size: 1.1rem !important;
            }
            .promo-pill .promo-text-small {
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand py-2" href="index.php">
                <img src="/images/kshops24_logo04.png" alt="KShops24" style="height: 42px; width: auto; display: block;">
            </a>
<!--
            <div class="d-flex align-items-center">
                <a href="admin/admin_login.php" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> 관리자 로그인
                </a>
            </div>
-->
        </div>
    </nav>

    <!--
        text-primary / bg-primary	주요 강조, 기본 액션	파란색 (Blue)	#0d6efd
        text-secondary / bg-secondary	보조, 부가 정보	회색 (Gray)	#6c757d
        text-success / bg-success	성공, 긍정, 완료	초록색 (Green)	#198754
        text-danger / bg-danger	위험, 에러, 삭제, 경고	빨간색 (Red)	#dc3545
        text-warning / bg-warning	주의, 대기, 임시	노란색 (Yellow)	#ffc107
        text-info / bg-info	정보 제공, 안내	하늘색 (Cyan)	#0dcaf0
        text-light / bg-light	밝은 배경	아주 연한 회색	#f8f9fa
        text-dark / bg-dark	어두운 배경, 텍스트	짙은 회색	#212529
    -->

    <header class="hero">
        <div class="container text-center text-white">
            <div class="hero-section">
                <!-- display- 뒤의 숫자를 5 또는 6으로 올리면 기존의 얇고 세련된 느낌은 유지하면서 크기만 적당히 줄어듭니다. (숫자가 커질수록 글씨는 작아집니다.) display-6으로 줄여도 여전히 너무 크다고 느껴지신다면, display- 클래스를 아예 지우고 부트스트랩의 기본 폰트 사이즈 클래스인 fs-2 나 fs-3 등을 사용하시면 됩니다. (fs-1이 가장 크고 fs-6이 가장 작습니다.)
                 -->
                <!-- PC 버전 텍스트 (768px 이상) -->
                <div class="d-none d-md-block">
                    <h1 class="fs-1 fw-bold mb-4">
                        <span class="text-light mb-4">해외에서 고군분투하고 계신 <span class="text-warning">모든 재외동포 소상공인</span>들에게<br> <span class="text-info">KShops24</span>가 <span class="text-danger">큰 힘</span>을 보태드립니다 !!!</span>
                    </h1>
                    <h1 class="fs-1 fw-bold mb-4">
                        <span class="text-light mb-4"><span class="text-warning">온라인 상점</span>을 통한 <span class="text-warning">비즈니스 홍보</span>는 <span class="text-danger">사업 성공</span>과 직결됩니다.</span>
                    </h1>
                    <h1 class="fs-1 fw-bold mb-4">
                        <span class="text-light mb-4"><span class="text-warning">1인 기업</span>도, <span class="text-warning">규모가 큰 사업장</span>도 멋진 <span class="text-info">온라인 상점</span>을 <span class="text-danger">오픈</span>하실 수 있습니다 !!!</span>
                    </h1>
                </div>

                <!-- 모바일 버전 텍스트 (768px 미만) -->
                <div class="d-block d-md-none">
                    <h1 class="fs-4 fw-bold mb-4">
                        <span class="text-light mb-4">해외에서 고군분투하고 계신<br> <span class="text-warning">모든 재외동포 소상공인</span>들에게<br> <span class="text-info">KShops24</span>가 <span class="text-danger">큰 힘</span>을 보태드립니다 !!!</span>
                    </h1>
                    <h1 class="fs-4 fw-bold mb-4">
                        <span class="text-light mb-4"><span class="text-warning">온라인 상점</span>을 통한 <span class="text-warning">비즈니스 홍보</span>는<br> <span class="text-danger">사업 성공</span>과 직결됩니다.</span>
                    </h1>
                    <h1 class="fs-4 fw-bold mb-4">
                        <span class="text-light mb-4"><span class="text-warning">1인 기업</span>도, <span class="text-warning">규모가 큰 사업장</span>도<br> 멋진 <span class="text-info">온라인 상점</span>을 <span class="text-danger">오픈</span>하실 수 있습니다 !!!</span>
                    </h1>
                </div>

                <!-- 프로모션 배지 그룹: 배달 플랫폼 수수료 무료, 주문/상담 알림 무료, 저렴한 월 정액제, 오픈 기념 특전 -->
                <div class="row justify-content-center g-2 g-md-3 mb-5 mt-3 px-2 px-md-0">
                    <div class="col-6 col-lg-3">
                        <div class="p-3 p-lg-4 rounded-4 promo-pill h-100 d-flex flex-column justify-content-center align-items-center shadow-lg">
                            <i class="bi bi-shop fs-1 text-warning mb-1 mb-lg-2 promo-icon"></i>
                            <span class="fs-5 fw-bold text-white mb-1 promo-text-sub">배달 플랫폼 수수료</br>주문/상담 알림</span>
                            <span class="text-warning fw-bold fs-4 promo-text-main">무료 !!!</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="p-3 p-lg-4 rounded-4 promo-pill h-100 d-flex flex-column justify-content-center align-items-center shadow-lg">
                            <i class="bi bi-globe fs-1 text-warning mb-1 mb-lg-2 promo-icon"></i>
                            <span class="fs-5 fw-bold text-white mb-1 promo-text-sub">다국어 지원 100%</span>
                            <span class="text-warning fw-bold fs-4 promo-text-main">무료 !!!</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="p-3 p-lg-4 rounded-4 promo-pill h-100 d-flex flex-column justify-content-center align-items-center shadow-lg">
                            <i class="bi bi-infinity fs-1 text-info mb-1 mb-lg-2 promo-icon"></i>
                            <span class="fs-5 fw-bold text-white mb-1 promo-text-sub">저렴한 월 정액제로</span>
                            <span class="text-info fw-bold fs-5 promo-text-main">홈페이지 무한 사용</span>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="p-3 p-lg-4 rounded-4 promo-pill border-danger h-100 d-flex flex-column justify-content-center align-items-center shadow-lg" style="background: rgba(220, 53, 69, 0.2) !important;">
                            <i class="bi bi-gift-fill fs-1 text-danger mb-1 mb-lg-2 promo-icon"></i>
                            <span class="fs-5 fw-bold text-white mb-1 promo-text-sub text-break">오픈 기념 특전 !!!</span>
                            <span class="text-danger fw-bold fs-5 promo-text-main">초기 구축비 0원<br>4개월 운용비 0원</span>
                        </div>
                    </div>
                </div>

                <!-- 버튼 그룹: 무료 입점 신청과 내 상점 관리 -->
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="pre_register.php" class="btn btn-apply px-5 py-3 shadow-lg">
                        <i class="bi bi-shop-window me-2"></i> 무료 입점 신청 
                    </a>
                    <a href="shops/login.php" target="_blank" class="btn btn-manage px-5 py-3 shadow-lg">
                        <i class="bi bi-gear-fill me-2"></i> 내 상점 관리
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- [중요] 공지사항 섹션 -->
    <section class="container notice-section mb-5">
        <div class="card notice-card">
            <div class="card-header notice-header p-4 text-white d-flex align-items-center">
                <i class="bi bi-megaphone-fill fs-4 me-3"></i>
                <h5 class="fw-bold m-0">KShops24 공지사항</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php if (!empty($main_notices)): ?>
                    <?php foreach ($main_notices as $mn): ?>
                        <a href="javascript:void(0);"
                            onclick="openNoticeModal(<?php echo htmlspecialchars(json_encode($mn)); ?>)"
                            class="list-group-item list-group-item-action notice-list-item px-4 py-3 d-flex justify-content-between align-items-center">
                            <span class="text-truncate me-3 fw-medium">
                                <?php echo htmlspecialchars($mn['title']); ?>
                            </span>
                            <span class="text-muted small fw-light flex-shrink-0"><?php echo substr($mn['created_at'], 0, 10); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">등록된 공지사항이 없습니다.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- [중요] 입점 상점 리스트 섹션 -->
    <section class="container my-5 py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">입점 상점 둘러보기</h2>
            <p class="text-muted">KShops24와 함께 성장하는 파트너들을 만나보세요.</p>
        </div>

        <div class="row g-4">
            <?php if (!empty($shops)): ?>
                <?php foreach ($shops as $shop): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 shop-card">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex flex-column align-items-start mb-3">
                                    <!-- 첫 번째 줄: 로고 (가로 100% 확장 및 비율 유지) -->
                                    <div class="mb-3 w-100 text-center">
                                        <img src="<?php echo !empty($shop['logo_path']) ? htmlspecialchars($shop['logo_path']) : '/assets/no-logo.png'; ?>"
                                            alt="<?php echo htmlspecialchars($shop['shop_name']); ?> Logo"
                                            class="shop-card-logo rounded border w-100"
                                            style="height: auto; max-height: 200px; object-fit: contain; background-color: #f8f9fa;">
                                    </div>

                                    <!-- 두 번째 줄: 상점명 (전화번호) -->
                                    <div class="w-100 text-center">
                                        <!-- 카테고리 배지 -->
                                        <?php 
                                        global $shop_category_labels;
                                        $cat = $shop['category'] ?? '';
                                        $cat_label = $shop_category_labels[$cat] ?? '일반';
                                        
                                        // 카테고리별 색상 매핑
                                        $cat_color_map = [
                                            'fnb'      => 'bg-danger',             // 음식점 (빨강)
                                            'realty'   => 'bg-primary',            // 부동산 (파랑)
                                            'srv'      => 'bg-success'            // 예약/서비스 (초록)
                                        ];
                                        $badge_class = $cat_color_map[$cat] ?? 'bg-secondary';
                                        ?>
                                        <div class="mb-2">
                                            <span class="badge <?php echo $badge_class; ?> rounded-pill shadow-sm px-3 py-1 fw-normal" style="font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($cat_label); ?>
                                            </span>
                                        </div>

                                        <!-- 두 번째 줄: 상점명(크게) 및 전화번호 중앙 정렬 -->
                                        <h3 class="card-title mb-1 d-flex flex-column align-items-center">
                                            <!-- 상점명: h5에서 h4 수준으로 크기 확대 -->
                                            <span class="fw-bold mb-1"><?php echo htmlspecialchars($shop['shop_name']); ?></span>

                                            <!-- 전화번호: 가독성을 위해 상점명 아래로 배치하거나 스타일 유지 -->
                                            <small class="text-muted fw-normal" style="font-size: 0.9rem;">
                                                (<?php echo $shop['phone_mobile'] ? htmlspecialchars(formatPHPhone($shop['phone_mobile'])) : '연락처 미등록'; ?>)
                                            </small>
                                        </h3>
                                    </div>
                                </div>
                                <p class="card-text mb-4"><?php echo htmlspecialchars($shop['shop_intro'] ?: '필리핀 한인 비즈니스 성공 파트너'); ?></p>
                                <a href="/<?php echo $shop['subdomain']; ?>" class="btn btn-primary mt-auto w-100" target="_blank">
                                    상점 방문하기 <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-light rounded-3">
                    <i class="bi bi-info-circle fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">현재 활성화된 상점이 없습니다.<br>첫 번째 입점 상점의 주인공이 되어보세요!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- [중요] KShops24만의 특별함 섹션 -->
    <section class="py-5 features-section border-top">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">KShops24만의 특별함</h2>
                <p class="text-muted">단순한 홈페이지가 아닌, 비즈니스 성장을 위한 통합 솔루션을 제공합니다.</p>
            </div>
            <div class="row g-5 justify-content-center">
                <div class="col-md-3 col-6">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                        <h6 class="mt-3 fw-bold">고객 신뢰도 향상</h6>
                        <p class="small text-muted mb-0">고품격 홈페이지는 상점의 품격을 높이고 고객에게 깊은 신뢰감을 줍니다.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-phone-fill"></i></div>
                        <h6 class="mt-3 fw-bold">100% 모바일 최적화</h6>
                        <p class="small text-muted mb-0">언제 어디서나 고객이 쉽게 주문하고 상담할 수 있는 최적의 환경을 제공합니다.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-sliders"></i></div>
                        <h6 class="mt-3 fw-bold">쉬운 관리 시스템</h6>
                        <p class="small text-muted mb-0">전문 지식 없이도 사장님이 쉽게 내용을 실시간으로 수정할 수 있습니다.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-piggy-bank"></i></div>
                        <h6 class="mt-3 fw-bold">배달앱 수수료 0원</h6>
                        <p class="small text-muted mb-0">비싼 플랫폼 수수료 대신, 합리적인 고정 유지비로 온라인 상점을 독립적으로 운영하세요.</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-translate"></i></div>
                        <h6 class="mt-3 fw-bold">완벽한 다국어 지원</h6>
                        <p class="small text-muted mb-0">구글 AI 자동 번역 기능을 통해 영어, 중국어 등 다양한 국적의 고객을 유치하세요.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 공지사항 상세 모달 -->
    <div class="modal fade" id="noticeDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 2060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 pb-0">
                    <span class="badge bg-primary rounded-pill px-3">공지사항</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    <h3 class="fw-bold mb-3" id="modalNoticeTitle"></h3>
                    <div class="d-flex text-muted small mb-4 border-bottom pb-3">
                        <span class="me-3"><i class="bi bi-calendar3 me-1"></i> <span id="modalNoticeDate"></span></span>
                        <span><i class="bi bi-eye me-1"></i> <span id="modalNoticeHit"></span></span>
                    </div>
                    <div class="content-area fs-5 text-dark" id="modalNoticeContent" style="white-space: pre-wrap; line-height: 1.8;">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openNoticeModal(data) {
            // 데이터 채우기
            document.getElementById('modalNoticeTitle').innerText = data.title;
            document.getElementById('modalNoticeDate').innerText = data.created_at.substring(0, 10);
            document.getElementById('modalNoticeHit').innerText = parseInt(data.hit) + 1; // 화면상 우선 1 증가
            document.getElementById('modalNoticeContent').innerText = data.content;

            // 모달 띄우기
            const modal = new bootstrap.Modal(document.getElementById('noticeDetailModal'));
            modal.show();

            // 비동기로 서버 조회수 업데이트 요청
            fetch('/common/ajax_hit_counter.php?id=' + data.id)
                .catch(err => console.error('Hit counter error:', err));
        }
    </script>

    <footer class="main-footer pt-5 pb-4">
        <div class="container text-center text-md-start">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <h5 class="text-white fw-bold">KShops24</h5>
                    <p class="small">필리핀 한인 비즈니스를 위한<br>가장 합리적인 온라인 상점 솔루션</p>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-white fw-bold">바로가기</h6>
                    <ul class="list-unstyled">
                        <li><a href="pre_register.php">입점 신청</a></li>
                        <li><a href="shops/login.php">상점 관리</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-white fw-bold">고객지원</h6>
                    <ul class="list-unstyled">
                        <li><a href="/common/terms_of_use.php" target="_blank">이용약관</a></li>
                        <li><a href="/common/privacy_policy.php" target="_blank">개인정보처리방침</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6">
                    <h6 class="text-white fw-bold">관리자</h6>
                    <ul class="list-unstyled">
                        <li><a href="admin/admin_view.php">시스템 관리자 로그인</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center small">
                &copy; <?php echo date('Y'); ?> KShops24. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>