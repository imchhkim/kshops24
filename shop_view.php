<?php

/**
 * [컨트롤러] KShops24 상점 메인 엔진
 * KShops24 점주 페이지 (shop_view.php)
 * - 기능: 서브도메인별 상점 데이터 로드, 스킨/폰트 동적 적용, 섹션별 노출 제어
 * - 유지보수일: 2026-02-20
 */
// 1. 공통 헤더 로드
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 2. [라우팅] URL 파라미터를 통한 상점 식별 (예: shop_view.php?subdomain=vape)
$subdomain = isset($_GET['subdomain']) ? trim($_GET['subdomain']) : '';

if (empty($subdomain)) {
    die("잘못된 접근입니다."); // 보안: 식별자 없을 경우 프로세스 즉시 중단
}

try {
    // 3. [데이터 로드] DB에서 해당 서브도메인의 상점 정보를 가져옴 (모든 점주 상태 포함 허용)
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE subdomain = :subdomain AND status IN ('active', 'testing', 'inactive', 'closed', 'owner_inactive', 'owner_deleted') LIMIT 1");
    $stmt->execute(['subdomain' => $subdomain]);
    $shop = $stmt->fetch();

    if (!$shop) {
        // 결과가 없으면 메인으로 리다이렉트 (존재하지 않는 주소 등)
        echo "<script>alert('존재하지 않거나 아직 준비 중인 상점입니다.'); location.href='/index.php';</script>";
        exit;
    }

    // 상점 카테고리 정보
    $shop_category = !empty($shop['category']) ? $shop['category'] : 'fnb';
    $shop_category_label = $shop_category_labels[$shop_category] ?? '일반';

    // [추가] 상점이 폐점 상태인 경우 전용 화면 출력 후 종료
    if (in_array($shop['status'], ['closed', 'owner_deleted'])) {
        $deleted_date_str = $shop['deleted_date'] ? date('Y년 m월 d일', strtotime($shop['deleted_date'])) : '미정';
?>
        <!DOCTYPE html>
        <html lang="ko">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>폐점 안내 - <?= htmlspecialchars($shop['shop_name']) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
            <style>
                body {
                    background-color: #f4f7f9;
                    font-family: 'Apple SD Gothic Neo', sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }

                .closed-card {
                    background: white;
                    padding: 40px 30px;
                    border-radius: 20px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                    text-align: center;
                    max-width: 500px;
                    width: 90%;
                }

                .icon-box {
                    font-size: 4rem;
                    color: #dc3545;
                    margin-bottom: 20px;
                }
            </style>
        </head>

        <body>
            <div class="closed-card border-top border-4 border-danger">
                <div class="icon-box"><i class="bi bi-door-closed-fill"></i></div>
                <h3 class="fw-bold mb-3 text-dark">폐점된 상점입니다</h3>
                <p class="text-muted mb-4">
                    <strong><?= htmlspecialchars($shop['shop_name']) ?></strong> 상점은 현재 폐점 처리되어 서비스를 이용하실 수 없습니다.
                </p>
                <div class="bg-light p-3 rounded-3 mb-4 text-start border shadow-sm">
                    <p class="mb-2 small fw-bold text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> 상점 데이터 영구 삭제 안내
                    </p>
                    <p class="mb-0 small text-muted" style="line-height: 1.6;">
                        본 상점의 모든 데이터는 <strong class="text-dark"><?= $deleted_date_str ?></strong>에 시스템에서 완전히 삭제될 예정입니다.
                        상점 관리자께서는
                        <a href="https://kshops24.com/shops/login.php"
                            target="_blank"
                            class="badge bg-primary text-decoration-none px-2 py-1 mx-1 shadow-sm transition-all"
                            style="font-size: 0.75rem; vertical-align: middle;">
                            <i class="bi bi-person-badge me-1"></i>관리자 페이지
                        </a>
                        에 신속히 접속하셔서 <strong class="text-danger">상점 "삭제" 전에 상점을 정상운영</strong> 시키시기 바랍니다.
                    </p>
                </div>
            <a href="/index.php" class="btn btn-primary rounded-pill px-4 py-3 fw-bold w-100 shadow-sm"><i class="bi bi-house-door-fill me-2"></i> KShops24 메인 포털 가기</a>
            </div>
        </body>

        </html>
<?php
        exit;
    }

    // 4. [방문 분석] 상점 방문 기록을 DB에 남김 (통계용)
    if (function_exists('recordVisitor')) recordVisitor($pdo, $shop['id']);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// 5. [사용자 인증 및 플랫폼 매핑] 현재 방문자의 카카오 로그인 여부 확인 및 크로스 상점 단골 자동 등록
$is_customer_logged_in = false;
if (isset($_SESSION['customer_id']) && isset($shop['id'])) {
    $is_customer_logged_in = true;
    
    // [통합고객] 다른 상점에서 로그인한 플랫폼 회원이, 현재 새로운 상점에 방문했다면?
    // -> 카카오 로그인을 다시 묻지 않고, 백그라운드에서 이 상점의 단골 리스트(mapping)에 자동 추가합니다!
    if ($_SESSION['customer_shop_id'] != $shop['id']) {
        $sql_map = "INSERT INTO shop_customer_mapping (shop_id, customer_id, last_login_at, created_at) 
                    VALUES (?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE last_login_at = NOW()";
        $pdo->prepare($sql_map)->execute([$shop['id'], $_SESSION['customer_id']]);
        
        // 현재 방문 중인 상점 ID로 세션 업데이트하여 완벽한 로그인 환경 유지
        $_SESSION['customer_shop_id'] = $shop['id'];
    }
}

// 필리핀 추가 정보(전화번호, 주소)가 필요한지 확인 (F&B 등 배달 가능 상점 위주)
$needs_ph_info = $is_customer_logged_in && empty($_SESSION['customer_ph_phone']);

// ---------------------------------------------------------
// [섹션 1] 상점별 커스텀 환경 설정 (Global Variables)
// ---------------------------------------------------------

// 6. [카테고리 설정] 상점 업종(fnb, beauty 등)에 따른 특화 설정 파일 로드
$category_config_path = $_SERVER['DOCUMENT_ROOT'] . "/shops/{$shop['category']}/{$shop['category']}_config.php";
if (file_exists($category_config_path)) {
    include_once $category_config_path;
}

// 7. [UI 설정] 점주가 관리자 페이지에서 설정한 맞춤형 레이블(JSON) 디코딩
$ui = json_decode($shop['ui_settings'] ?? '{}', true);

// [추가] 통화 기호 설정
$shop_currency = $shop['shop_currency'] ?? 'PHP';
$currency_symbols = [
    'PHP' => '₱',
    'KRW' => '₩',
    'USD' => '$',
    'JPY' => '¥',
    'CNY' => '¥',
    'VND' => '₫'
];
$currency_symbol = $currency_symbols[$shop_currency] ?? '₱';

$is_testing_mode = ($shop['status'] === 'testing');

// [다국어 지원] 사용자 언어 변경 요청(GET 파라미터) 처리 및 세션 저장
if (isset($_GET['lang']) && trim($_GET['lang']) !== '') {
    $_SESSION['shop_lang'] = trim($_GET['lang']);
    // 변경된 언어팩 즉시 재로드
    if (function_exists('load_language')) {
        load_language($_SESSION['shop_lang']);
    }
}

// 현재 설정된 언어 (기본값 한국어)
$global_current_lang = $_SESSION['shop_lang'] ?? 'ko';

// 다국어 OFF 시 기본 언어(한국어)로 강제 고정 및 세션 초기화
if (($ui['is_multilingual'] ?? 0) == 0) {
    $global_current_lang = 'ko';
    $_SESSION['shop_lang'] = 'ko'; // 다른 상점 방문 시 저장된 타 언어(예: 중국어)를 초기화

    // [버그 수정] 메모리에 이미 로드된 번역 사전을 무시하고 강제로 한국어 사전을 덮어써서 __() 함수가 항상 한국어를 출력하도록 조치
    if (function_exists('load_language')) {
        load_language('ko');
    }
}

// 8. [리소스 처리] 배경 및 로고 이미지 설정 (미등록 시 기본 이미지로 대체)
$hero_images = [];
if (!empty($shop['bg_path'])) {
    $decoded_bg = json_decode($shop['bg_path'], true);
    if (is_array($decoded_bg) && count($decoded_bg) > 0) {
        $hero_images = $decoded_bg;
    } else {
        $hero_images = [$shop['bg_path']]; // 레거시 문자열 지원
    }
}
if (empty($hero_images)) {
    $hero_images = ['https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1500&q=80'];
}
$bg_image = $hero_images[0];
$shop_logo = !empty($shop['logo_path']) ? $shop['logo_path'] : '/assets/no-logo.png';

// 텍스트 출력 데이터 (보안을 위한 htmlspecialchars 필수 적용)
$category = $shop['category'] ?: 'fnb';

// 9. [홍보 문구] 메인 타이틀 노출 설정이 켜져 있을 때만 데이터 바인딩
if (($shop['is_show_main_title'] ?? 1) == 1) {
    $current_lang = $global_current_lang;

    if ($current_lang !== 'ko' && isset($ui["top_label_{$current_lang}"]) && trim($ui["top_label_{$current_lang}"]) !== '') {
        $disp_label = htmlspecialchars($ui["top_label_{$current_lang}"]);
    } else {
        $disp_label = !empty($shop['top_label']) ? htmlspecialchars($shop['top_label']) : 'WELCOME TO OUR SHOP';
    }

    if ($current_lang !== 'ko' && isset($ui["main_title_{$current_lang}"]) && trim($ui["main_title_{$current_lang}"]) !== '') {
        $disp_title = htmlspecialchars($ui["main_title_{$current_lang}"]);
    } else {
        $disp_title = !empty($shop['main_title']) ? htmlspecialchars($shop['main_title']) : htmlspecialchars($shop['shop_name'] ?? '');
    }

    if ($current_lang !== 'ko' && isset($ui["sub_title_{$current_lang}"]) && trim($ui["sub_title_{$current_lang}"]) !== '') {
        $disp_subtitle = htmlspecialchars($ui["sub_title_{$current_lang}"]);
    } else {
        $disp_subtitle = !empty($shop['sub_title']) ? htmlspecialchars($shop['sub_title']) : htmlspecialchars($shop['shop_intro'] ?? '');
    }
} else {
    // 노출 설정이 꺼져 있으면 변수를 비워서 출력되지 않게 함
    $disp_label = '';
    $disp_title = '';
    $disp_subtitle = '';
}

// [스킨 시스템 설정] 테마 데이터 매핑
$current_skin = $shop['shop_skin'] ?? 'default';
$skin_data = [
    'default' => ['bg' => '#ffffff', 'text' => '#333333', 'primary' => '#004aad'], 
    'dark' => ['bg' => '#222222', 'text' => '#eeeeee', 'primary' => '#4a90e2'], 
    'luxury' => ['bg' => '#fcf8e3', 'text' => '#5d4037', 'primary' => '#b8860b'], 
    'nature' => ['bg' => '#f1f8e9', 'text' => '#2e7d32', 'primary' => '#388e3c'],
    'ocean' => ['bg' => '#f0f8ff', 'text' => '#003366', 'primary' => '#007bff'],
    'romance' => ['bg' => '#fff0f5', 'text' => '#4a0e2e', 'primary' => '#ff6b81']
];
$skin_config = $skin_data[$current_skin] ?? $skin_data['default'];
$theme_color = $skin_config['primary']; // 카테고리 뷰 등 하위 모듈에서 공용으로 사용

// 10. [폰트 시스템] 점주가 선택한 웹폰트 CDN 주소 매핑
$current_font = !empty($shop['shop_font']) ? $shop['shop_font'] : 'Pretendard';
$font_cdn = [
    'Pretendard' => 'https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css',
    'Noto Sans KR' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&display=swap',
    'Nanum Gothic' => 'https://fonts.googleapis.com/css2?family=Nanum+Gothic:wght@400;700&display=swap',
    'Nanum Myeongjo' => 'https://fonts.googleapis.com/css2?family=Nanum+Myeongjo:wght@400;700&display=swap'
];
$font_url = $font_cdn[$current_font] ?? $font_cdn['Pretendard'];

// 11. [문의처 링크] 카카오톡 ID 또는 채널 URL을 분석하여 상담 링크 생성
$kakao_val = trim($shop['kakao_id'] ?? '');
// [개선 1] 카카오 링크 생성 로직 보완
$kakao_link = "javascript:alert('등록된 문의처가 없습니다.');"; // 기본값
if (!empty($kakao_val)) {
    if (filter_var($kakao_val, FILTER_VALIDATE_URL)) {
        $kakao_link = $kakao_val;
    } else {
        $clean_id = ltrim($kakao_val, '@');
        $kakao_link = "https://pf.kakao.com/" . $clean_id;
    }
}

// 12. [인증 URL] 카카오 로그인 버튼용 API 주소 생성 (상점 식별값 전달)

// (config.php에 KAKAO_REDIRECT_URI가 이미 전역으로 정의되어 있으므로 중복 선언 제거)

// 카카오 로그인 인증 URL 생성 시 상수를 정확하게 바인딩했는지 확인
$kakao_auth_url = "https://kauth.kakao.com/oauth/authorize?client_id=" . KAKAO_REST_API_KEY . "&redirect_uri=" . urlencode(KAKAO_REDIRECT_URI) . "&response_type=code";


$kakao_login_url = "https://kauth.kakao.com/oauth/authorize?client_id=" . KAKAO_REST_API_KEY . "&redirect_uri=" . urlencode(KAKAO_REDIRECT_URI) . "&response_type=code&state={$subdomain}";

// 13. [갤러리] 상점 이미지 관리 메뉴에서 올린 사진 목록 로드
try {
    // 시스템 안정성을 위해 컬럼 존재 여부 체크 후 가져오기
    $pdo->exec("ALTER TABLE shop_images ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
} catch (Exception $e) {
}
$stmt_gallery = $pdo->prepare("SELECT img_path FROM shop_images WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_gallery->execute([$shop['id']]);
$shop_gallery = $stmt_gallery->fetchAll();

// 13-1. [리뷰] 고객 리뷰 로드 및 통계 처리
$recent_reviews = [];
$total_reviews = 0;
$avg_rating = 0;

// 기본 이미지 설정 (원하는 이모티콘 파일명으로 변경하세요)
$default_profile_img = '/assets/default_emoticon.png';

try {
    // 최신 리뷰 5개만 메인 화면에 로드
    $stmt_reviews = $pdo->prepare("
        SELECT r.*, c.nickname AS customer_name, c.profile_img 
        FROM reviews r 
        LEFT JOIN platform_customers c ON r.customer_id = c.id 
        WHERE r.shop_id = ? 
        ORDER BY r.id DESC LIMIT 5
    ");
    $stmt_reviews->execute([$shop['id']]);
    $recent_reviews = $stmt_reviews->fetchAll();

    // 데이터를 가져온 후, 프로필 이미지가 없는 경우 기본 이미지로 처리
    foreach ($recent_reviews as &$review) {
        if (empty($review['profile_img'])) {
            $review['profile_img'] = $default_profile_img;
        }
    }
    // reference 관계를 해제합니다.
    unset($review);

    // 전체 리뷰 수 및 평균 별점 계산
    $stmt_review_stats = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM reviews WHERE shop_id = ?");
    $stmt_review_stats->execute([$shop['id']]);
    $review_stats = $stmt_review_stats->fetch();
    $total_reviews = $review_stats['total_reviews'];
    $avg_rating = round($review_stats['avg_rating'] ?? 0, 1);
} catch (Exception $e) {
    // 필요에 따라 예외 처리를 기록합니다.
}

$search_keyword = trim($_GET['keyword'] ?? '');

// [최적화] JS 파일 버전 자동 관리 (파일이 수정되었을 때만 난수 변경)
$fnb_js_path = '/shops/fnb/assets/fnb_cart.js';
$fnb_js_ver = file_exists($_SERVER['DOCUMENT_ROOT'] . $fnb_js_path) ? filemtime($_SERVER['DOCUMENT_ROOT'] . $fnb_js_path) : '1.0';
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop['shop_name'] ?? ''); ?> - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- [추가] 카카오 SDK 로드 -->
    <script src="https://t1.kakaocdn.net/kakao_js_sdk/2.7.0/kakao.min.js" defer></script>

    <?php if ($category === SHOP_CATEGORY_FNB): ?>
        <!-- [최적화] 수많은 이미지들 때문에 JS 다운로드가 병목으로 밀리는 현상(먹통)을 방지하기 위한 VIP 우선 다운로드 지시 -->
        <link rel="preload" href="<?php echo $fnb_js_path; ?>?v=<?php echo $fnb_js_ver; ?>" as="script">
        <!-- [추가] 모달창을 띄우는 핵심 엔진인 Bootstrap도 교통체증을 무시하고 최우선으로 가져오도록 지시 -->
        <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" as="script">
    <?php endif; ?>

    <link rel="stylesheet" href="<?php echo $font_url; ?>">

    <style>
        /* 14. [동적 스타일] 상점별 선택한 폰트 및 테마 색상(Root 변수) 적용 */
        :root {
            --main-dark: #222;
            --accent-color: <?php echo $skin_config['primary']; ?>;
            --shop-bg-color: <?php echo $skin_config['bg']; ?>;
            --shop-text-color: <?php echo $skin_config['text']; ?>;
        }

        body,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        p,
        span,
        a,
        div,
        button {
            font-family: '<?php echo $current_font; ?>', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif !important;
        }

        body {
            background-color: var(--shop-bg-color) !important;
            color: var(--shop-text-color) !important;
            line-height: 1.6;
            padding-bottom: 80px;
            overflow-x: hidden;
        }

        /* Hero 섹션: 상점 메인 비주얼 */
        .hero-section {
            position: relative;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            overflow: hidden;
        }

        /* [추가] Hero Carousel 및 Overlay 스타일 */
        .hero-carousel,
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .hero-carousel {
            z-index: 0;
        }

        .hero-carousel img {
            -webkit-user-drag: none;
            /* 마우스 드래그 시 이미지 잔상(고스트) 방지 */
        }

        .hero-overlay {
            z-index: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6));
            pointer-events: none;
            /* 마우스 이벤트(클릭/드래그) 통과 허용 */
        }

        .hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            user-select: none;
            /* 드래그 시 텍스트가 선택되는 현상 방지 */
            pointer-events: none;
            /* 하단 화살표 버튼 클릭을 위한 이벤트 통과 */
        }

        .hero-section h1 {
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 800;
        }

        .hero-section .top-tag {
            font-size: 0.9rem;
            letter-spacing: 2px;
        }

        .hero-section .divider {
            width: 50px;
            height: 3px;
            background: var(--accent-color);
            margin: 20px auto;
        }

        /* 상점 핵심 정보 섹션 스타일 */
        .shop-info-summary-section {
            margin-top: 0;
            position: relative;
            z-index: 10;
            border-bottom: 1px solid #eee;
        }

        .shop-info-summary-section .info-bar-section {
            background: white;
            padding: 10px 0;
        }

        .shop-info-summary-section .info-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 2px 5px;
        }

        .shop-info-summary-section .info-item strong {
            font-size: 0.75rem;
            color: #777;
            margin-bottom: 0;
        }

        .shop-info-summary-section .info-item span {
            font-size: 0.85rem;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.2;
        }

        /* [추가] 메인 타이틀 텍스트 효과 */
        .title-effect-neon {
            text-shadow:
                0 0 5px #fff,
                0 0 10px #fff,
                0 0 20px #ff00de,
                0 0 30px #ff00de,
                0 0 40px #ff00de,
                0 0 55px #ff00de,
                0 0 75px #ff00de;
            color: #fff;
        }
        .title-effect-shadow {
            color: #f0f0f0;
            text-shadow: 3px 3px 5px rgba(0,0,0,0.8), 0 0 12px rgba(0,0,0,0.5);
        }
        .title-effect-outline {
            color: white;
            -webkit-text-stroke: 1.5px black;
            paint-order: stroke fill;
        }

        @media (max-width: 768px) {
            .hero-section {
                height: 35vh;
            }
        }

        /* 15. [공통 디자인] 섹션 제목, 배너, 하단바 등 기본 UI 스타일 */
        .testing-banner {
            position: sticky;
            top: 0;
            z-index: 2000;
            background: #0dcaf0;
            color: white;
            text-align: center;
            padding: 10px 0;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .section-title h2 {
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--accent-color);
        }

        .story-section {
            background-color: #f8f9fa;
        }

        .gallery-section {
            background-color: #ffffff;
        }

        .map-section {
            background-color: #f4f7f9;
        }

        .gallery-img-wrapper img:hover {
            transform: scale(1.05);
        }

        .contact-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            border-top: 1px solid #ddd;
            z-index: 1000;
        }

        .contact-bar a {
            flex: 1;
            text-align: center;
            padding: 18px 0;
            text-decoration: none;
            font-weight: 700;
        }

        .btn-call {
            background: var(--accent-color);
            color: white !important;
        }

        .btn-kakao {
            background: #FEE500;
            color: #3A1D1D !important;
        }

        /* KShops24 홍보 배너 스타일 */
        .promo-banner {
            background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .promo-banner:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
        }

        /* [추가] 부트스트랩 프라이머리 클래스 동적 오버라이드 (완벽한 테마 일체감) */
        .text-primary { color: var(--accent-color) !important; }
        .bg-primary { background-color: var(--accent-color) !important; color: #fff !important; }
        .btn-primary { background-color: var(--accent-color) !important; border-color: var(--accent-color) !important; color: #fff !important; }
        .btn-outline-primary { color: var(--accent-color) !important; border-color: var(--accent-color) !important; }
        .btn-outline-primary:hover { background-color: var(--accent-color) !important; color: #fff !important; }
        .border-primary { border-color: var(--accent-color) !important; }
        .badge.bg-primary { background-color: var(--accent-color) !important; color: #fff !important; }

        /* [스킨 시스템] 특별 테마 보정 (다크모드 등) */
        <?php if ($current_skin === 'dark'): ?>
        nav.navbar.bg-white, .shop-info-summary-section .bg-light, .search-container-wrapper.bg-light, .bg-white {
            background-color: #222 !important;
            border-color: #333 !important;
        }
        .navbar-brand, .nav-link, .shop-info-summary-section .info-item span, .section-title h2, .text-dark, .menu-item-name { 
            color: #fff !important; 
        }
        .shop-info-summary-section .info-item strong, .text-muted { color: #aaa !important; }
        .story-section, .map-section { background-color: #1a1a1a !important; }
        .gallery-section { background-color: #222 !important; }
        .promo-banner { background: linear-gradient(145deg, #2a2a2a 0%, #1a1a1a 100%) !important; border-color: #444 !important; }
        .card, .menu-item-card { background-color: #2a2a2a !important; border-color: #444 !important; }
        <?php endif; ?>
    </style>

    <script>
        // [UX 개선] 대규모 이미지 및 리소스 다운로드로 인해 자바스크립트 모듈 실행이 지연될 때, 
        // 사용자의 성급한 클릭에 "먹통"처럼 보이지 않도록 즉각적인 피드백을 제공하는 인터셉터
        window.ps24JsLoaded = false;
        document.addEventListener("click", function(e) {
            if (!window.ps24JsLoaded) {
                // 주문조회, 카트보기, 메뉴 클릭 등 동적 로직이 필요한 버튼들을 식별
                const targetBtn = e.target.closest('[onclick*="showOrderHistory"], [onclick*="showCartViewModal"], [onclick*="openMenuDetailModal"], [onclick*="openReviewWriteModal"], [onclick*="copyToClipboard"]');
                if (targetBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert("페이지의 데이터를 불러오는 중입니다.\n잠시만 기다려 주세요... ⏳");
                }
            }
        }, true); // 캡처링 페이즈를 활용하여 인라인 onclick 보다 먼저 가로챔
    </script>
</head>

<body>
    <!-- [추가] 휴점(inactive, owner_inactive) 상태일 때 화면 중앙 플로팅 경고창 -->
    <?php if (in_array($shop['status'], ['inactive', 'owner_inactive'])): ?>
        <!-- [제안] 배경 클릭을 원천 차단하는 전체 화면 오버레이 막 -->
        <div class="inactive-backdrop"></div>
        <div id="inactiveFloatingBanner" class="inactive-floating-banner shadow-lg">
            <div class="banner-header bg-danger text-white p-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> 상점 휴점 안내</h6>
            </div>
            <div class="banner-body p-3 bg-white text-dark text-center">
                <p class="mb-2 fw-bold">현재 이 상점은 <span class="text-danger">휴점(일시 중지)</span> 상태입니다.</p>
                
                <?php if (!empty($shop['urgent_notice'])): ?>
                <div class="alert alert-warning text-start p-3 mb-3 shadow-sm border-0 rounded-3">
                    <h6 class="fw-bold small mb-2 text-dark"><i class="bi bi-chat-quote-fill me-1 text-warning"></i>상점 안내 메시지</h6>
                    <p class="small mb-0 text-secondary" style="line-height: 1.5;"><?php echo nl2br(htmlspecialchars($shop['urgent_notice'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="bg-light p-2 rounded mb-2 small text-start">
                    <div class="mb-1"><i class="bi bi-sign-stop text-danger me-1"></i> 휴점일 : <strong><?php echo !empty($shop['inactive_date']) ? date('Y-m-d', strtotime($shop['inactive_date'])) : '미정'; ?></strong></div>
                    <?php if ($shop['status'] === 'inactive'): ?>
                    <div class="mb-1"><i class="bi bi-calendar-x text-danger me-1"></i> 폐점 예정일 : <strong><?php echo $shop['closed_date'] ? date('Y-m-d', strtotime($shop['closed_date'])) : '미정'; ?></strong></div>
                    <div><i class="bi bi-trash3-fill text-danger me-1"></i> 삭제 예정일 : <strong><?php echo $shop['deleted_date'] ? date('Y-m-d', strtotime($shop['deleted_date'])) : '미정'; ?></strong></div>
                    <?php endif; ?>
                </div>
                
                <?php if ($shop['status'] === 'inactive'): ?>
                <p class="text-muted mb-0" style="font-size: 0.75rem;">상점이 "삭제"되면 상점 및 모든 데이터가 영구 삭제됩니다.<br>상점 관리자께서는
                    <a href="https://kshops24.com/shops/login.php"
                        target="_blank"
                        class="badge bg-primary text-decoration-none px-2 py-1 mx-1 shadow-sm transition-all"
                        style="font-size: 0.75rem; vertical-align: middle;">
                        <i class="bi bi-person-badge me-1"></i>관리자 페이지
                    </a>에 신속히 접속하셔서 조치를 취하시기 바랍니다.
                </p>
                <?php else: ?>
                <p class="text-muted mb-0" style="font-size: 0.75rem;">점주님의 개인 사정으로 인해 임시 휴점 중입니다.<br>빠른 시일 내에 돌아오겠습니다.</p>
                <?php endif; ?>
            </div>
        </div>
        <style>
            /* 전체 화면 마우스 클릭/스크롤 원천 차단 */
            .inactive-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                /* 반투명 검은색 */
                backdrop-filter: blur(2px);
                /* 배경을 살짝 흐리게 처리 (고급스러움) */
                z-index: 9998;
                /* 배너(9999) 바로 아래 위치하여 배경 전체를 덮음 */
            }

            /* 화면 중앙 고정(Fixed) 및 애니메이션 처리 */
            .inactive-floating-banner {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                width: 90%;
                max-width: 360px;
                border-radius: 12px;
                overflow: hidden;
                border: 2px solid #dc3545;
                animation: fadeInBanner 0.5s ease-out forwards;
            }

            @keyframes fadeInBanner {
                from {
                    opacity: 0;
                    top: 45%;
                }

                to {
                    opacity: 1;
                    top: 50%;
                }
            }
        </style>
    <?php endif; ?>

    <!-- 16. [테스팅 공지] 점주가 작업 중인 상태일 때 상단 배지 노출 -->
    <?php if ($is_testing_mode): ?>
        <div class="testing-banner"><i class="bi bi-tools me-2"></i> 현재 이 상점은 테스팅 모드(작업중)입니다.</div>
    <?php endif; ?>

    <!-- 17. [네비게이션] 상단 로고 및 사용자 로그인/프로필 영역 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-1" style="z-index: 2050;">
        <div class="container px-2">
            <a class="navbar-brand d-flex align-items-center fw-bold py-0 m-0" href="#">
                <img src="<?php echo $shop_logo; ?>" alt="Logo" style="height:45px;" class="me-2" loading="lazy">
                <?php if (($ui['is_show_logo_text'] ?? 1) == 1): ?>
                    <span class="shop-logo-text"><?php echo htmlspecialchars($shop['shop_name'] ?? ''); ?></span>
                <?php endif; ?>
            </a>

            <div class="ms-auto d-flex align-items-center">
                <!-- 언어 선택 드롭다운 -->
                <?php if (($ui['is_multilingual'] ?? 0) == 1): ?>
                    <?php
                    $supported_langs_name = [
                        'ko' => '한',
                        'en' => 'En',
                        'zh' => '中',
                        'ja' => '日',
                        'es' => 'Esp',
                        'fr' => 'Fr',
                        'ru' => 'Ру',
                        'vi' => 'Vi'
                    ];
                    $active_langs = ['ko' => '한'];
                    for ($i = 1; $i <= 2; $i++) {
                        $lang = $ui["multilingual_lang{$i}"] ?? 'none';
                        if ($lang !== 'none') {
                            if ($lang === 'etc') {
                                $code = strtolower(trim($ui["multilingual_lang{$i}_custom_code"] ?? "etc{$i}"));
                                if (empty($code)) $code = "etc{$i}";
                                $active_langs[$code] = trim($ui["multilingual_lang{$i}_custom_name"] ?? 'Other');
                            } else {
                                $active_langs[$lang] = $supported_langs_name[$lang] ?? strtoupper($lang);
                            }
                        }
                    }
                    $current_lang = $global_current_lang;
                    if (!isset($active_langs[$current_lang])) $current_lang = 'ko'; // 설정 안 된 언어일 시 fallback
                    $current_lang_display = $active_langs[$current_lang];
                    ?>
                    <div class="dropdown me-1">
                        <button class="btn btn-light btn-sm dropdown-toggle rounded-pill px-2 py-1 border text-muted" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-globe me-1"></i> <?php echo htmlspecialchars($current_lang_display); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 60px;">
                            <?php foreach ($active_langs as $code => $name): ?>
                                <li><a class="dropdown-item py-1 <?php echo ($current_lang === $code) ? 'active bg-primary text-white' : ''; ?>" href="?subdomain=<?php echo urlencode($subdomain); ?>&lang=<?php echo urlencode($code); ?>"><?php echo htmlspecialchars($name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- 로그인 상태에 따른 UI 분기 -->
                <?php if ($is_customer_logged_in): ?>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle rounded-pill px-2 py-1" type="button" data-bs-toggle="dropdown">
                            <img src="<?php echo $_SESSION['customer_profile_img']; ?>" class="rounded-circle me-1" style="width:20px;height:20px;">
                            <!-- 
                            <?php echo htmlspecialchars($_SESSION['customer_nickname']); ?>님
                                -->
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <!-- 상점 카테고리 별로 구분하여 매뉴 나오게 수정 -->
                            <?php if ($category === SHOP_CATEGORY_FNB): ?>
                                <li><a class="dropdown-item py-2" href="#" onclick="showMyInfoModal(); return false;"><i class="bi bi-person-badge me-2"></i><?php echo __('btn_edit_delivery_info'); ?></a></li>
                                <li><a class="dropdown-item py-2" href="#" onclick="showOrderHistoryModal(); return false;"><i class="bi bi-clock-history me-2"></i><?php echo __('btn_order_history'); ?></a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item py-2" href="#" onclick="showMyInfoModal(); return false;"><i class="bi bi-person-badge me-2"></i><?php echo __('btn_edit_info'); ?></a></li>
                                <li><a class="dropdown-item py-2" href="#" onclick="openRealtyOrderHistoryModal(); return false;"><i class="bi bi-clock-history me-2"></i><?php echo __('btn_inquiry_history'); ?></a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <!-- <a class="dropdown-item py-2 text-danger" href="#" onclick="confirmWithdrawal(); return false;"><i class="bi bi-x-circle me-2"></i>회원 탈퇴</a> -->
                            </li>
                            <li><a class="dropdown-item py-2" href="/shops/customer_logout.php?subdomain=<?php echo $subdomain; ?>"><i class="bi bi-box-arrow-right me-2"></i><?php echo __('btn_logout'); ?></a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $kakao_login_url; ?>" class="btn btn-sm fw-bold px-2 py-1" style="background-color:#FEE500; color:#3A1D1D; border-radius:12px;">
                        <i class="bi bi-chat-fill me-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 공통 Hero 섹션 -->
    <section class="hero-section">
        <!-- 배경 이미지 슬라이더 (자동 재생) -->
        <!-- [리팩토링] 스와이프 충돌 버그 해결을 위해 스마트 공통 모듈(MediaSliderModule)로 히어로 슬라이더 동적 렌더링 -->
        <div id="heroCarouselWrapper" class="hero-carousel">
            <!-- JS 로딩 전 시각적 레이아웃 유지(CLS 방지)용 초기 이미지 -->
            <img src="<?php echo $bg_image; ?>" class="w-100 h-100" style="object-fit: cover;" fetchpriority="high">
        </div>

        <!-- 텍스트 가독성을 위한 어두운 오버레이 -->
        <div class="hero-overlay"></div>

        <div class="container text-center hero-content">
            <!-- 1. 상단 라벨 -->
            <p class="top-tag text-uppercase fw-bold title-effect-<?php echo $ui['top_label_effect'] ?? 'none'; ?>" 
               style="color: <?php echo $ui['top_label_color'] ?? '#0dcaf0'; ?> !important; font-family: '<?php echo $ui['top_label_font'] ?? 'inherit'; ?>', sans-serif !important; <?php echo !empty($ui['top_label_size']) ? 'font-size: ' . $ui['top_label_size'] . ' !important;' : ''; ?>">
               <?php echo $disp_label; ?>
            </p>

            <!-- 2. 메인 타이틀 -->
            <h1 class="title-effect-<?php echo $ui['main_title_effect'] ?? 'none'; ?>" 
                style="color: <?php echo $ui['main_title_color'] ?? '#ffffff'; ?> !important; font-family: '<?php echo $ui['main_title_font'] ?? 'inherit'; ?>', sans-serif !important; <?php echo !empty($ui['main_title_size']) ? 'font-size: ' . $ui['main_title_size'] . ' !important;' : ''; ?>">
                <?php echo $disp_title; ?>
            </h1>

            <?php if (($shop['is_show_main_title'] ?? 1) == 1) { ?>
                <div class="divider"></div>
            <?php } ?>

            <!-- 3. 하단 설명 -->
            <p class="hero-sub title-effect-<?php echo $ui['sub_title_effect'] ?? 'none'; ?>" 
               style="color: <?php echo $ui['sub_title_color'] ?? '#ffffff'; ?> !important; font-family: '<?php echo $ui['sub_title_font'] ?? 'inherit'; ?>', sans-serif !important; <?php echo !empty($ui['sub_title_size']) ? 'font-size: ' . $ui['sub_title_size'] . ' !important;' : ''; ?>">
               <?php echo $disp_subtitle; ?>
            </p>
        </div>
    </section>

    <!-- 공통 상점 정보 바 (영업시간, 카톡, 연락처) -->
    <section class="shop-info-summary-section">

        <div class="bg-light border-bottom py-1">
            <div class="container">
                <div class="row g-0 align-items-stretch">

                    <!-- [개선 1] 영업시간 (요일별 동적 처리 및 팝업형 아코디언 UI 적용) -->
                    <div class="col-6 info-item border-end text-center py-2 d-flex flex-column align-items-center justify-content-center position-relative">
                        <strong class="small mb-1" style="line-height: 1.1;"><?php echo __('영업시간'); ?></strong>
                        <div class="small text-secondary w-100" style="font-size: 0.75rem; line-height: 1.2;">
                            <?php
                            $bh = $shop['business_hours'] ?? '';
                            if (!empty($bh) && ($bh[0] === '{' || $bh[0] === '[')) {
                                $bh_data = json_decode($bh, true);
                                $days_kr = ['mon' => '월', 'tue' => '화', 'wed' => '수', 'thu' => '목', 'fri' => '금', 'sat' => '토', 'sun' => '일'];
                                $today_key = strtolower(date('D'));
                                $today_info = $bh_data[$today_key] ?? null;

                                $today_display = '24' . __('시간');
                                if ($today_info && !empty($today_info['closed'])) {
                                    $today_display = "<span class='text-danger fw-bold'>" . __('휴무') . "</span>";
                                } else if ($today_info && (!empty($today_info['open']) || !empty($today_info['close']))) {
                                    $today_display = htmlspecialchars($today_info['open']) . " ~ " . htmlspecialchars($today_info['close']);
                                }

                                // 오늘 영업시간 요약 표시
                                echo "<div class='fw-bold text-dark mb-1'>" . __('오늘') . " " . $today_display . "</div>";

                                // 전체 보기 아코디언 버튼
                                echo '<button type="button" class="btn btn-sm btn-light border py-0 px-2 shadow-sm rounded-pill" style="font-size: 0.65rem;" data-bs-toggle="collapse" data-bs-target="#bhListFront">' . __('전체보기') . ' <i class="bi bi-chevron-down"></i></button>';

                                // 아코디언 내용 (요일별 전체 리스트 오버레이 팝업)
                                echo '<div class="collapse w-100 mt-2 text-start position-absolute" id="bhListFront" style="z-index: 1050; left: 0; top: 100%; padding: 0 10px;">
                                        <div class="bg-white border rounded-3 p-2 shadow-lg">
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                <strong class="text-dark" style="font-size: 0.75rem;"><i class="bi bi-calendar-week me-1"></i>' . __('요일별 영업시간') . '</strong>
                                                <i class="bi bi-x-circle text-muted fs-6" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#bhListFront"></i>
                                            </div>
                                            <ul class="list-unstyled mb-0" style="font-size: 0.7rem; line-height: 1.6;">';
                                foreach ($days_kr as $k => $n) {
                                    $d = $bh_data[$k] ?? null;
                                    $n_trans = __($n . '요일'); // 다국어 번역 키 매핑
                                    $is_today_row = ($k === $today_key) ? 'bg-light rounded fw-bold text-primary px-1' : 'px-1';

                                    if ($d && !empty($d['closed'])) {
                                        echo "<li class='{$is_today_row} d-flex justify-content-between'><span style='width: 40px;'>{$n_trans}</span> <span class='text-danger fw-bold'>" . __('휴무') . "</span></li>";
                                    } else if ($d && (!empty($d['open']) || !empty($d['close']))) {
                                        echo "<li class='{$is_today_row} d-flex justify-content-between'><span style='width: 40px;'>{$n_trans}</span> <span>{$d['open']} ~ {$d['close']}</span></li>";
                                    } else {
                                        echo "<li class='{$is_today_row} d-flex justify-content-between'><span style='width: 40px;'>{$n_trans}</span> <span>24" . __('시간') . "</span></li>";
                                    }
                                }
                                echo '</ul></div></div>';
                            } else {
                                // 기존 하위 호환성 (단일 문자열 처리)
                                $bh = $bh ?: "24시간 영업";
                                $time_keywords = ['오전', '오후', '매일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일', '일요일', '평일', '주말', '휴무', '공휴일', '24시간 영업'];
                                foreach ($time_keywords as $word) {
                                    $bh = str_replace($word, __($word), $bh);
                                }
                                echo str_replace('~', '<br>~<br>', htmlspecialchars($bh));
                            }
                            ?>
                        </div>
                    </div>

                    <!-- [개선 2] 카톡 ID와 연락처를 각각 클릭하여 복사할 수 있도록 인터랙티브하게 개선 -->
                    <div class="col-6 d-flex flex-column">
                        <div class="info-item border-bottom ps-3 d-flex align-items-center flex-grow-1" style="cursor:pointer; padding-top: 8px; padding-bottom: 8px;" onclick="copyToClipboard('<?php echo $shop['kakao_id']; ?>', '카톡 ID가 복사되었습니다!');">
                            <i class="bi bi-chat-fill text-muted me-2" style="font-size: 0.8rem;"></i>
                            <div class="text-start text-nowrap" style="line-height: 1.1;">
                                <span class="small" style="font-size: 0.75rem;"><?php echo htmlspecialchars($shop['kakao_id'] ?: '정보 없음'); ?></span>
                            </div>
                        </div>

                        <a href="tel:<?php echo htmlspecialchars($shop['phone_mobile'] ?? ''); ?>" class="info-item ps-3 text-decoration-none text-dark d-flex align-items-center flex-grow-1" style="padding-top: 8px; padding-bottom: 8px;">
                            <i class="bi bi-telephone-outbound text-muted me-2" style="font-size: 0.8rem;"></i>
                            <div class="text-start text-nowrap" style="line-height: 1.1;">
                                <span class="small" style="font-size: 0.75rem;">
                                    <?php echo $shop['phone_mobile'] ? htmlspecialchars(formatPHPhone($shop['phone_mobile'])) : '정보 없음'; ?>
                                </span>
                            </div>
                        </a>

                    </div>
                </div>
            </div>
    </section>

    <!-- [추가] 상점 공지사항 노출 영역 (공통 상점 정보 바 아래) -->
    <?php ob_start(); ?>
    <?php if (!empty($shop['urgent_notice']) || !empty($shop['general_notice'])): ?>
        <section class="container mt-2 mb-2">
            <?php if (!empty($shop['urgent_notice'])): ?>
                <div class="alert alert-danger shadow-sm border-0 mb-2 py-2 px-3 d-flex align-items-start rounded-3">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5 mt-1"></i>
                    <div class="small fw-bold lh-base" style="word-break: break-all;"><?php echo nl2br(htmlspecialchars($shop['urgent_notice'])); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($shop['general_notice'])): ?>
                <div class="alert alert-warning shadow-sm border-0 mb-2 py-2 px-3 d-flex align-items-start rounded-3">
                    <i class="bi bi-megaphone-fill me-2 fs-5 mt-1 text-danger"></i>
                    <div class="small fw-bold lh-base text-dark" style="word-break: break-all;"><?php echo nl2br(htmlspecialchars($shop['general_notice'])); ?></div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php
    $common_notice_ui = ob_get_clean();
    ?>

    <?php
    // [추가] 공통 검색창 UI 영역 (버퍼에 담아 하위 뷰에서 원하는 위치에 출력하도록 변경)
    // config.php에 정의된 카테고리별 플레이스홀더 매핑 사용 (다국어 지원 및 확장성 고려)
    global $shop_search_placeholders;
    $placeholder_text = $shop_search_placeholders[$shop['category']] ?? '어떤 항목을 찾으시나요?';
    $search_placeholder = __($placeholder_text);
    ob_start();
    ?>
    <section class="search-container-wrapper bg-light border-bottom py-4 my-3 border-top">
        <div class="container">
            <form method="GET" action="" class="d-flex bg-white border rounded-pill px-2 py-1 shadow-sm mb-0">
                <?php if (isset($_GET['subdomain'])): ?>
                    <input type="hidden" name="subdomain" value="<?php echo htmlspecialchars($_GET['subdomain']); ?>">
                <?php endif; ?>
                <?php if (isset($_GET['lang'])): ?>
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($_GET['lang']); ?>">
                <?php endif; ?>
                <input type="text" name="keyword" class="form-control border-0 bg-transparent shadow-none px-3" placeholder="<?php echo htmlspecialchars($search_placeholder); ?>" value="<?php echo htmlspecialchars($search_keyword); ?>">
                <button type="submit" class="btn btn-primary rounded-pill px-3 fw-bold flex-shrink-0">
                    <i class="bi bi-search d-sm-none"></i><span class="d-none d-sm-inline"><i class="bi bi-search me-1"></i><?php echo __('검색'); ?></span>
                </button>
                <?php if (!empty($search_keyword)): ?>
                    <a href="?subdomain=<?php echo urlencode($_GET['subdomain'] ?? ''); ?>" class="btn btn-light rounded-pill px-3 ms-2 fw-bold flex-shrink-0 border">
                        <i class="bi bi-arrow-clockwise d-sm-none"></i><span class="d-none d-sm-inline"><?php echo __('초기화'); ?></span>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </section>
    <?php
    $common_search_form_ui = ob_get_clean();
    ?>

    <!-- 18. [동적 컨텐츠] 상점 업종별(F&B, 일반 등) 전용 페이지 인클루드 -->
    <?php
    switch ($category) {
        case SHOP_CATEGORY_FNB:
            // [개선 4] 경로 오류 방지를 위한 절대 경로 권장
            $fnb_view_path = $_SERVER['DOCUMENT_ROOT'] . '/shops/fnb/shop_view_fnb.php';
            if (file_exists($fnb_view_path)) {
                include $fnb_view_path;
            }
            break;
        case SHOP_CATEGORY_REALTY:
            $realty_view_path = $_SERVER['DOCUMENT_ROOT'] . '/shops/realty/shop_view_realty.php';
            if (file_exists($realty_view_path)) {
                include $realty_view_path;
            }
            break;
        case SHOP_CATEGORY_SRV:
            // 서비스(srv) 카테고리 전용 페이지 렌더링
            $srv_view_path = $_SERVER['DOCUMENT_ROOT'] . '/shops/srv/shop_view_srv.php';
            if (file_exists($srv_view_path)) {
                include $srv_view_path;
            }
            break;
        default:
            $default_path = $_SERVER['DOCUMENT_ROOT'] . '/shops/fnb/shop_view_fnb.php';
            if (file_exists($default_path)) include $default_path;
            break;
    }
    ?>

    <!-- 19. [스토리] 상점 소개 섹션 (노출 설정 시 출력) -->
    <?php if (($shop['is_show_story'] ?? 1) == 1): ?>
        <?php
        $current_lang = $global_current_lang;
        $disp_label_story = $ui['label_story'] ?? SHOP_DEFAULT_LABEL_STORY;
        $disp_shop_intro = $shop['shop_intro'] ?? '';
        $disp_shop_desc = $shop['shop_description'] ?? '';

        if ($current_lang !== 'ko') {
            if (isset($ui["label_story_{$current_lang}"]) && trim($ui["label_story_{$current_lang}"]) !== '') $disp_label_story = $ui["label_story_{$current_lang}"];
            if (isset($ui["shop_intro_{$current_lang}"]) && trim($ui["shop_intro_{$current_lang}"]) !== '') $disp_shop_intro = $ui["shop_intro_{$current_lang}"];
            if (isset($ui["shop_description_{$current_lang}"]) && trim($ui["shop_description_{$current_lang}"]) !== '') $disp_shop_desc = $ui["shop_description_{$current_lang}"];
        }
        ?>
        <section id="section-story" class="story-section py-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($disp_label_story); ?>">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-5 mb-4 mb-md-0"><img src="<?php echo $bg_image; ?>" class="w-100 rounded-4 shadow-lg"></div>
                    <div class="col-md-7 ps-md-5">
                        <h6 class="text-primary fw-bold text-uppercase mb-3"><?php echo htmlspecialchars($disp_label_story); ?></h6>
                        <h2 class="fw-bold mb-4"><?php echo nl2br(htmlspecialchars($disp_shop_intro)); ?></h2>
                        <div class="text-secondary lh-lg fs-6"><?php echo $disp_shop_desc; ?></div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($shop['is_show_gallery'] ?? 1) == 1 && (!empty($shop_gallery) || !empty($shop['shop_youtube_url']))):
        $current_lang = $global_current_lang;
        $disp_label_gallery = $ui['label_gallery'] ?? SHOP_DEFAULT_LABEL_GALLERY;
        if ($current_lang !== 'ko' && isset($ui["label_gallery_{$current_lang}"]) && trim($ui["label_gallery_{$current_lang}"]) !== '') {
            $disp_label_gallery = $ui["label_gallery_{$current_lang}"];
        }
    ?>
        <section id="section-gallery" class="container mt-4 pt-1 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($disp_label_gallery); ?>">
            <div class="section-title text-center mb-4">
                <h2><?php echo htmlspecialchars($disp_label_gallery); ?></h2>
            </div>
            <div class="row g-3">
                <?php foreach ($shop_gallery as $photo): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="gallery-img-wrapper" style="border-radius:12px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.08);">
                            <a data-fslightbox="shop-gallery" href="<?php echo $photo['img_path']; ?>">
                                <img src="<?php echo function_exists('getThumbnailPath') ? getThumbnailPath($photo['img_path']) : $photo['img_path']; ?>" class="w-100 h-auto" loading="lazy" style="transition:0.3s; cursor:pointer; aspect-ratio:1/1; object-fit:cover;">
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // [추가] 유튜브 동영상 링크가 있을 경우 갤러리 마지막에 표시
                if (!empty($shop['shop_youtube_url'])):
                    $yt_urls = [];
                    $decoded_yt = json_decode($shop['shop_youtube_url'], true);
                    if (is_array($decoded_yt)) {
                        $yt_urls = $decoded_yt;
                    } else {
                        // 기존 단일 문자열 호환성 유지
                        $yt_urls = [$shop['shop_youtube_url']];
                    }

                    foreach ($yt_urls as $yt_url):
                        if (empty(trim($yt_url))) continue;
                        // [리팩토링] lib_utils.php 의 공통 추출 함수 사용으로 지저분한 정규식 제거
                        $yt_id = function_exists('extractYoutubeIdFromUrl') ? extractYoutubeIdFromUrl($yt_url) : null;
                        if ($yt_id):
                ?>
                            <div class="col-12 mt-2">
                                <!-- [최적화] 유튜브 Iframe 로딩 지연(Facade 패턴) 적용하여 초기 로딩 속도 대폭 개선 -->
                                <div class="gallery-img-wrapper position-relative bg-dark youtube-facade" data-yt-id="<?php echo $yt_id; ?>" style="border-radius:12px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.08); aspect-ratio:16/9; cursor:pointer;">
                                    <img src="https://img.youtube.com/vi/<?php echo $yt_id; ?>/hqdefault.jpg" loading="lazy" style="width:100%; height:100%; object-fit:cover; opacity:0.7; transition:0.3s;">
                                    <div class="position-absolute top-50 start-50 translate-middle text-white" style="font-size: 3.5rem; text-shadow: 0 4px 15px rgba(0,0,0,0.4);">
                                        <i class="bi bi-youtube text-danger"></i>
                                    </div>
                                </div>
                            </div>
                <?php endif;
                    endforeach;
                endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 20-1. [고객 리뷰] 섹션 -->
    <?php include __DIR__ . '/shop_view_review.php'; ?>

    <!-- 21. [지도] 구글 맵 섹션 (아이프레임 반응형 변환 처리) -->
    <?php if (($shop['is_show_map'] ?? 1) == 1 && !empty(trim($shop['shop_map_html'] ?? ''))): ?>
        <?php
        $current_lang = $global_current_lang;
        $disp_label_location = $ui['label_location'] ?? SHOP_DEFAULT_LABEL_LOCATION;
        if ($current_lang !== 'ko' && isset($ui["label_location_{$current_lang}"]) && trim($ui["label_location_{$current_lang}"]) !== '') {
            $disp_label_location = $ui["label_location_{$current_lang}"];
        }
        ?>
        <section id="section-location" class="container mt-5 pb-5 scroll-nav-target" data-nav-label="<?php echo htmlspecialchars($disp_label_location); ?>">
            <div class="section-title text-center mb-4">
                <h2><?php echo htmlspecialchars($disp_label_location); ?></h2>
            </div>
            <div class="row">
                <div class="col-12 text-center">
                    <div class="google-map-container rounded-4 overflow-hidden shadow-sm border mt-1 bg-light position-relative" style="width:100%; min-height:450px;">
                        <?php
                        /**
                         * [최적화] 구글 맵 Intersection Observer 레이지 로딩 적용
                         * 사용자가 하단으로 스크롤하여 지도가 보일 때만 무거운 Iframe을 렌더링합니다.
                         */
                        $map_html = $shop['shop_map_html'];
                        $map_html = preg_replace('/width="\d+"/', 'width="100%"', $map_html);
                        $map_html = preg_replace('/height="\d+"/', 'height="450"', $map_html);
                        ?>
                        <div class="lazy-map-facade d-flex flex-column align-items-center justify-content-center h-100" data-map-html='<?php echo htmlspecialchars($map_html, ENT_QUOTES, 'UTF-8'); ?>' style="position:absolute; top:0; left:0; right:0; bottom:0; z-index:1;">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                            <p class="text-muted fw-bold m-0">지도 데이터를 불러오는 중입니다...</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- [추가] 플랫폼 홍보: KShops24 입점 안내 배너 -->
    <section class="container mt-1 mb-1">
        <a href="/index.php" target="_blank" class="text-decoration-none d-block">
            <div class="promo-banner p-4 rounded-4 shadow-sm text-center border">
                <p class="text-muted fw-bold small mb-2"><?php echo __('비싼 배달앱 수수료는 그만!'); ?></p>
                <div class="bg-white d-inline-block px-4 py-2 rounded-pill mb-3 shadow-sm">
                    <img src="/images/kshops24_logo04.png" alt="KShops24" style="height: 24px; object-fit: contain;">
                </div>
                <div class="text-primary fw-bold" style="font-size: 0.95rem;">
                    <?php echo __('최저의 월 사용료 만으로 우리 매장의 </br>멋진 배달 홈페이지 만들기'); ?> <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                </div>
            </div>
        </a>
    </section>

    <!-- 22. [푸터] 하단 저작권 표시 -->
    <footer class="py-2 bg-dark text-white text-center">
        <div class="container">
            <h5 class="fw-bold mb-3 mt-2">
                <?php echo htmlspecialchars($shop['shop_name'] ?? ''); ?>
                <span class="badge bg-info text-dark fw-normal rounded-pill me-2">
                    <?php echo htmlspecialchars(__($shop_category_label)); ?>
                </span>
            </h5>
            <a href="/shops/login.php?subdomain=<?php echo urlencode($shop['subdomain'] ?? ''); ?>" target="_blank" class="text-decoration-none d-block">
                <div class="d-inline-block px-3 py-1 rounded-pill mb-2" style="background-color: #0dcaf0; color: #222; font-weight: 700;">
                    <?php echo __('상점 관리자 페이지로 이동'); ?> <i class="bi bi-box-arrow-up-right ms-1"></i>
                </div>
            </a>
            <p class="small opacity-50 mb-1">&copy; 2026 KShops24. All rights reserved.</p>
        </div>
    </footer>

    <!-- [수정] 필리핀 현지 정보 관리 모달 (내 정보 수정 겸용) -->
    <!-- [리팩토링] 모든 카테고리(FNB, SRV, Realty) 신규 공통 모듈 단일화 적용 -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/common/shop_common_modals.php'; ?>
    <script src="/common/shop_common.js?v=<?php echo time(); ?>" defer></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            try {
                // [최적화] 유튜브 비디오 파사드 클릭 시 Iframe 렌더링
                document.querySelectorAll('.youtube-facade').forEach(el => {
                    el.addEventListener('click', function() {
                        const ytId = this.getAttribute('data-yt-id');
                        this.innerHTML = `<iframe width="100%" src="https://www.youtube.com/embed/${ytId}?autoplay=1&rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%; height:100%; display:block;"></iframe>`;
                        this.style.cursor = 'default';
                        this.classList.remove('youtube-facade');
                    }, {
                        once: true
                    });
                });

                // [최적화] 구글 맵 화면 노출 시 지연 렌더링 (Intersection Observer)
                const lazyMaps = document.querySelectorAll('.lazy-map-facade');
                if ('IntersectionObserver' in window) {
                    let mapObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                let lazyMap = entry.target;
                                lazyMap.parentElement.innerHTML = lazyMap.getAttribute('data-map-html');
                                observer.unobserve(lazyMap);
                            }
                        });
                    }, {
                        rootMargin: "200px 0px"
                    }); // 화면에 닿기 200px 전에 미리 로딩 시작
                    lazyMaps.forEach(function(lazyMap) {
                        mapObserver.observe(lazyMap);
                    });
                } else {
                    // Observer 미지원 브라우저 Fallback
                    lazyMaps.forEach(function(lazyMap) {
                        lazyMap.parentElement.innerHTML = lazyMap.getAttribute('data-map-html');
                    });
                }

                // [최적화] 히어로 슬라이더를 공통 모듈로 렌더링 (방향 스와이프 버그 완전 차단)
                const heroWrapper = document.getElementById('heroCarouselWrapper');
                if (heroWrapper && typeof generateDynamicCarousel === 'function') {
                    const heroImages = <?php echo json_encode($hero_images, JSON_UNESCAPED_SLASHES); ?>;
                    heroWrapper.innerHTML = generateDynamicCarousel('heroCarousel', heroImages, {
                        interval: 3000,
                        transition: 'smooth'
                    });
                    if (typeof initDynamicCarousel === 'function') {
                        initDynamicCarousel('heroCarousel', {
                            interval: 3000
                        });
                    }
                }
            } catch (e) {
                console.error("DOM Initialization Error:", e);
            } finally {
                // [수정] 메인 DOM 및 공통 JS 실행이 끝났음을 알림 (인터셉터 무조건 해제 보장)
                window.ps24JsLoaded = true;
            }
        });

        // [안전장치] 어떤 이유로든 DOMContentLoaded 타이밍을 놓쳤거나 지연된 스크립트가 있을 경우를 대비
        window.addEventListener("load", function() {
            window.ps24JsLoaded = true;
        });
    </script>

    <?php
    // [필수 공통 라이브러리] 부트스트랩 모달, 슬라이더 및 라이트박스 로드 (전역 적용)
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/fslightbox/3.0.9/index.min.js" defer></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>';

    // [모바일 UX] 모달/라이트박스가 열려 있을 때 스마트폰의 '뒤로가기' 버튼을 누르면 이전 페이지로 가지 않고 팝업만 닫히도록 제어
    if (function_exists('renderPopupHistoryBackScript')) {
        echo renderPopupHistoryBackScript();
    }

    // 공통 푸터 (JS 유틸리티 및 </body> </html> 닫기 태그 포함)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_footer.php';
    ?>