<<<<<<< HEAD
<?php

/**
 * KShops24 시스템 설정 파일 (config.php)
 * 위치: /public_html/config.php
 * 설명: 사이트 전역에서 사용하는 상수 및 설정값을 정의합니다.
 */

// ---------------------------------------------------------
// 1. 상점 상태 (Shop Status) 상수 정의
// ---------------------------------------------------------
// [시스템 개발 설정]
// * 테스트 모듈(task_test.php) 실행 및 로그 기록 여부 (true: 실행, false: 중지)
define('ENABLE_TASK_TEST', true);

define('GOOGLE_TRANSLATE_API_KEY', 'AIzaSyCSnfeHU4QI0Az7ycdfvXsQv1J7a_a-Mhw');

define('GEMINI_API_KEY', 'AIzaSyCWK3it3CyucmwxHs9VnqTBp3xvTlcvSvs');

/**
 * KShops24 AI 연동용 최신 Google Gemini 모델 정의
 * * 속도가 빠르고 텍스트 처리 비용이 매우 저렴한 1.5 Flash 모델을 권장합니다.
 */
define('GEMINI_MODEL', 'gemini-1.0-pro'); // ⭕ 가장 안정적인 범용 모델인 gemini-1.0-pro 사용

// [텔레그램 봇 설정]
define('PS24_BOT_TOKEN', '8748825415:AAGfX5IByxVQJii57EL7JPBOzGLMk3Nq2KY');
define('PS24_BOT_CHAT_ID', '8641026340');

// [카카오 로그인 설정]
// * Kakao Developers > 내 애플리케이션 > 요약 정보에서 'REST API 키'를 복사하세요.
// * 주의: JavaScript 키가 아닌 'REST API 키'를 넣어야 합니다.
define('KAKAO_REST_API_KEY', '461d2ab817f7b7832592405576a4068d');

// * Kakao Developers > 내 애플리케이션 > 요약 정보에서 'JavaScript 키'를 복사하세요.
// * 프론트엔드에서 카카오톡 앱 호출 시 사용됩니다.
define('KAKAO_JS_KEY', 'bad682065d7a997ad74c2bd0c5f7121c');

// * Kakao Developers > 제품 설정 > 카카오 로그인 > 보안에서 'Client Secret'을 발급받으세요.
// * 활성 상태가 'ON'인 경우 반드시 이 값이 일치해야 KOE101/invalid_client 에러가 나지 않습니다.
define('KAKAO_CLIENT_SECRET', '여기에_실제_발급받은_Secret_문자열을_넣으세요');

// * Redirect URI 설정 (HTTPS 강제 적용 권장)
// * Kakao Developers > 카카오 로그인 > Redirect URI에 등록된 주소와 완전히 일치해야 합니다.
/**
 * [KOE006 에러 해결 핵심]
 * 1. 아래 주소를 복사하세요:
 *    https://kshops24.com/shops/customer_kakao_callback.php
 * 
 * 2. 카카오 디벨로퍼스 > 내 애플리케이션 > 제품 설정 > 카카오 로그인 > Redirect URI에 위 주소를 추가하세요.
 * 3. 만약 www.kshops24.com으로 접속하신다면 해당 버전도 함께 등록해야 합니다.
 */
define('KAKAO_REDIRECT_URI', "https://kshops24.com/shops/customer_kakao_callback.php");

// [전화번호 정보 관련 공지사항]
define('PHONE_INFO_NOTICE_1',  '현지 정부의 관련법에 따라 허위 번호 도용 시 법적 처벌을 받을 수 있습니다.');
define('PHONE_INFO_NOTICE_2',  '본인 소유의 번호인지를 확인하는 절차가 진행될 수 있습니다.');

// DB에 저장될 실제 값 (ENUM)
define('SHOP_STATUS_APPLYING',      'applying');  // 신청 (승인 대기)
define('SHOP_STATUS_TESTING',       'testing');   // 테스팅 (시스템 점검/준비)
define('SHOP_STATUS_ACTIVE',        'active');    // 운영 (정상 영업 중)
define('SHOP_STATUS_INACTIVE_SOON', 'inactive_soon');  // 휴점 예정
define('SHOP_STATUS_INACTIVE',      'inactive');  // 휴점 (일시정지/사용기간만료/비용연체)
define('SHOP_STATUS_CLOSED_SOON',   'closed_soon'); // 폐점 예정 (휴점 후 폐점 전)
define('SHOP_STATUS_CLOSED',        'closed');    // 폐점 (영업 종료)
define('SHOP_STATUS_DELETED_SOON',  'deleted_soon'); // 삭제 예정 (폐점 후 삭제 전)
define('SHOP_STATUS_DELETED',       'deleted');    // 삭제 (완전 삭제)

define('SHOP_STATUS_OWNER_INACTIVE',    'owner_inactive');  // 상점주 휴점 요청 (상점주가 직접 휴점 신청, 승인 절차 없이 바로 휴점 상태로 변경)
define('SHOP_STATUS_OWNER_DELETED',     'owner_deleted');   // 상점주 삭제 요청 (완전 삭제, 7주일 후 상점 삭제 됨)

define('NOTIFICATION_SOUND', '/assets/sounds/dingdongg.mp3');

// ---------------------------------------------------------
// 2. 상태별 표시 라벨 (UI 출력용)
// ---------------------------------------------------------
// 화면에 보여줄 한글 명칭과 배지 색상(Bootstrap class)을 매핑해 두면 편리합니다.
$shop_status_config = [
    SHOP_STATUS_APPLYING => [
        'label' => '신청',
        'class' => 'bg-warning text-dark' // 노란색
    ],
    SHOP_STATUS_TESTING => [
        'label' => '테스팅',
        'class' => 'bg-info text-dark'    // 하늘색
    ],
    SHOP_STATUS_ACTIVE => [
        'label' => '운영',
        'class' => 'bg-success'           // 초록색
    ],
    SHOP_STATUS_INACTIVE_SOON => [
        'label' => '휴점 예정',
        'class' => 'bg-warning'           // 노란색
    ],
    SHOP_STATUS_INACTIVE => [
        'label' => '휴점',
        'class' => 'bg-secondary'         // 회색
    ],
    SHOP_STATUS_CLOSED_SOON => [
        'label' => '폐점 임박',
        'class' => 'bg-dark'              // 검정색
    ],
    SHOP_STATUS_CLOSED => [
        'label' => '폐점',
        'class' => 'bg-dark'              // 검정색
    ]
];

// ---------------------------------------------------------
// 3. 결제 유형 (Payment Type) 상수 정의
// ---------------------------------------------------------

// 휴점 및 폐점 관련 알림과 처리 로직에서 사용되는 날짜 기준 상수들을 정의합니다.
//   -14        0         14        30        60         90
// 휴점알림     휴점    폐점알림     폐점     삭제알림     삭제
//            만료일

// [결제 및 정산 관련 설정] 휴점 임박 알림 기준일
define('SHOP_STATUS_INACTIVE_SOON_DAYS', 14);

// [결제 및 정산 관련 설정] 휴점 후 폐점 임박 알림 기준일
define('WARNING_SHOP_STATUS_CLOSED_SOON_DAYS', 14);

// [결제 및 정산 관련 설정] 휴점 후 폐점 결정일 
define('SHOP_STATUS_CLOSED_SOON_DAYS', 30); // 휴점 후 30일 이내에 폐점 처리 (폐점 처리되면 상점 홈페이지는 작동하나 "폐점"이라는 플로팅 경고가 화면 중앙에 스크롤로 따라 다님)

// [결제 및 정산 관련 설정] 폐점 후 삭제 알림 기준일 
define('WARNING_SHOP_STATUS_DELETED_SOON_DAYS', 30);

// [결제 및 정산 관련 설정] 폐점 후 삭제 결정일 
define('SHOP_STATUS_DELETED_SOON_DAYS', 30); // 휴점 후 90일 후 완전 삭제

define('PAY_TYPE_6MONTHS', '6months');
define('PAY_TYPE_4MONTHS_FREE', '4months_free'); // 무료 4개월 월 사용료
define('PAY_TYPE_MONTHLY', 'monthly');
define('PAY_TYPE_SETUP',   'setup');
define('PAY_TYPE_ADDON',   'addon');
define('PAY_TYPE_ETC',     'etc');

$pay_type_labels = [
    PAY_TYPE_6MONTHS => '6개월 사용료',
    PAY_TYPE_4MONTHS_FREE => '4개월 사용료 (무료)',
    PAY_TYPE_MONTHLY => '월 사용료',
    PAY_TYPE_SETUP   => '구축비',
    PAY_TYPE_ADDON   => '추가비용',
    PAY_TYPE_ETC     => '기타비용'
];

// [결제 및 정산 관련 설정] 공통 사용을 위한 '만료 임박 상점' 조회 SQL 서브쿼리 및 조건절 상수
// 1. paid 조건에 'n'을 추가하거나 아예 제거하여 모든 상태의 만료일을 체크합니다.
// 2. pay_type에 PAY_TYPE_SETUP (구축비)를 추가합니다.

define('SQL_EXPIRING_SUBQUERY', "
    SELECT shop_id, 
           COALESCE(
               MIN(CASE WHEN paid = 'n' THEN CAST(NULLIF(expiring_date, '') AS DATE) END),
               MAX(CASE WHEN paid IN ('y', 'f') AND pay_type IN ('" . PAY_TYPE_6MONTHS . "', '" . PAY_TYPE_MONTHLY . "', '" . PAY_TYPE_4MONTHS_FREE . "') THEN CAST(NULLIF(expiring_date, '') AS DATE) END)
           ) as max_expiring_date 
    FROM shop_payments 
    GROUP BY shop_id
");

define('SQL_EXPIRING_CONDITION', "max_expiring_date IS NOT NULL AND max_expiring_date <= DATE_ADD(CURDATE(), INTERVAL " . SHOP_STATUS_INACTIVE_SOON_DAYS . " DAY)");

// ---------------------------------------------------------
// [추가] 무료 프로모션용 결제 유형 상수 (무료 프로모션 후에는 'n'로 변경하여 실제 청구에서 제외할 수 있습니다.)
// ---------------------------------------------------------
define('SETUP_FREE', 'y');
define('4MONTHS_FREE', 'y');

// [추가] 결제 내역 비고(Note) 전용 상수
define('PAY_NOTE_REGISTRATION', '입점신청');

// ---------------------------------------------------------
// 4. 상점 카테고리 (Shop Categories) 상수 및 동적 로드
// ---------------------------------------------------------
// [리팩토링] DB에 등록된 카테고리 정보를 동적으로 로드하여 상수 및 라벨 배열을 자동 생성합니다.
$shop_category_labels = [];
if (isset($pdo)) {
    try {
        // [수정] site_settings 테이블에서 JSON으로 저장된 카테고리 정보를 가져옵니다.
        $stmt_cats = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
        $json_cats = $stmt_cats->fetchColumn();
        $categories = $json_cats ? json_decode($json_cats, true) : [];

        // [버그 수정] DB에 등록된 카테고리가 구버전이거나 'srv' 등이 누락된 경우 오류를 방지하기 위해 기본 시스템 카테고리를 병합합니다.
        $default_system_cats = [
            'fnb'    => '음식점 / 카페',
            'realty' => '부동산 / 중개',
            'srv'    => '예약 / 서비스'
        ];
        $categories = array_merge($default_system_cats, $categories);

        foreach ($categories as $key => $name) {
            // 1. 전역 라벨 배열에 추가 (UI 출력용)
            $shop_category_labels[$key] = $name;

            // 2. 상수 정의 (코드 로직용) - 예: SHOP_CATEGORY_FNB
            $const_name = 'SHOP_CATEGORY_' . strtoupper($key);
            if (!defined($const_name)) {
                define($const_name, $key);
            }
        }
    } catch (Exception $e) {
        // DB 오류 발생 시 기본값으로 폴백
    }
}

// DB 연결 실패 또는 카테고리 미설정 시를 대비한 최소한의 기본값 설정
if (empty($shop_category_labels)) {
    if (!defined('SHOP_CATEGORY_FNB')) define('SHOP_CATEGORY_FNB', 'fnb');
    if (!defined('SHOP_CATEGORY_REALTY')) define('SHOP_CATEGORY_REALTY', 'realty');
    if (!defined('SHOP_CATEGORY_SRV')) define('SHOP_CATEGORY_SRV', 'srv');
    $shop_category_labels = [
        'fnb' => '음식점 / 카페',
        'realty' => '부동산 / 중개',
        'srv' => '예약 / 서비스'
    ];
}

// ---------------------------------------------------------
// [추가] 상점 카테고리별 검색창 플레이스홀더 텍스트 매핑
// ---------------------------------------------------------
$shop_search_placeholders = [
    'fnb'  => '어떤 메뉴를 찾으시나요? (메뉴명 등)',
    'realty' => '어떤 매물을 찾으시나요? (매물명, 특징 등)',
    'srv'  => '어떤 서비스를 원하시나요? (서비스명, 특징 등)'
];

// ---------------------------------------------------------
// 5. 공통 UI 라벨 (Common UI Labels) 기본값 정의
// ---------------------------------------------------------
define('SHOP_DEFAULT_LABEL_STORY',    'Our Story');
define('SHOP_DEFAULT_LABEL_GALLERY',  '매장 사진들 (PHOTO GALLERY)');
define('SHOP_DEFAULT_LABEL_LOCATION', '찾아오시는 길 (LOCATION)');

// ---------------------------------------------------------
// 6. 관리 페이지 공통 UI 스타일 (Admin & Shop Owner Management)
// ---------------------------------------------------------
// 상점별 카테고리 config.php에서 이 배열 값을 변경하여 디자인을 커스텀할 수 있습니다.
$UI_STYLE = [
    'section_title' => 'font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 1.25rem;',
    'section_sub'   => 'font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;',
    'item_label'    => 'font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;',
    'tab_label'     => 'font-size: 0.875rem; font-weight: 600;',
    'page_title'    => 'font-size: 1.5rem; font-weight: 800; color: #1e293b;'
];

// ---------------------------------------------------------
// 7. shop_board 게시판 유형(Type) 상수 정의
// ---------------------------------------------------------
// 게시판, 공지사항, 메시지, 이메일 발송 내역 등의 구분을 명확히 하기 위해 사용됩니다.
define('BOARD_TYPE_NOTICE', 'notice');           // 홈페이지 전체 공지사항 (shop_id = 0)
define('BOARD_TYPE_MESSAGE', 'message');         // 관리자가 상점, 혹은 상점에서 관리자에게 보낸 쪽지
define('BOARD_TYPE_EMAIL_LOG', 'email_log');     // 상점에게 보낸 이메일 발송 내역

// DB에 등록된 카테고리를 조회하여 상수 및 라벨 배열로 자동 생성합니다.
$board_type_labels = [
    BOARD_TYPE_NOTICE  => '공지사항',
    BOARD_TYPE_MESSAGE => '상점/상점주 메시지',
    BOARD_TYPE_EMAIL_LOG => '이메일 발송 내역'
];


// 페이지당 리스트 수 기본값 (관리자 페이지에서 사용)
define('LISTS_PER_PAGE', 10);

// (추후 필요한 다른 전역 설정들도 여기에 추가하시면 됩니다.)

// 상점 히스토리 로그 타입 상수 (향후 추가 가능)
define('SHOP_HISTORY_STATUS', 'status');   // 상태 변경
define('SHOP_HISTORY_EMAIL', 'email');     // 이메일 발송 내역
define('SHOP_HISTORY_MESSAGE', 'message'); // 쪽지/알림 발송 내역
define('SHOP_HISTORY_BILLING', 'billing'); // 과금 및 결제 처리 내역
define('SHOP_HISTORY_INFO', 'info');       // 상점 기본정보 수정 내역

// ---------------------------------------------------------
// 8. 시스템 로그 유형 (System Log Types) 상수 정의
// ---------------------------------------------------------
define('LOG_TYPE_INFO', 'info');                 // 일반 정보 및 안내
define('LOG_TYPE_ERROR', 'error');               // 시스템 오류
define('LOG_TYPE_EMAIL_FAIL', 'email_fail');     // 이메일 발송 실패
define('LOG_TYPE_ADMIN_ACTION', 'admin_action'); // 관리자 주요 설정 변경 이력
define('LOG_TYPE_DELETED', 'deleted');           // 상점 영구 삭제 시 백업 정보

$site_log_type_labels = [
    LOG_TYPE_ADMIN_ACTION => 'ADMIN (관리자 작업)',
    LOG_TYPE_ERROR => 'ERROR (시스템 오류)',
    LOG_TYPE_EMAIL_FAIL => 'EMAIL_FAIL (발송 실패)',
    LOG_TYPE_INFO => 'INFO (일반 정보)',
    LOG_TYPE_DELETED => 'DELETED (상점 삭제 백업)'
];

// ---------------------------------------------------------
// 9. 상점 uploads 디렉토리 경로 상수 정의
// ---------------------------------------------------------
define('SHOP_UPLOADS_DIR', __DIR__ . '/uploads/shops'); // 실제 파일 시스템 경로
=======
<?php

/**
 * KShops24 시스템 설정 파일 (config.php)
 * 위치: /public_html/config.php
 * 설명: 사이트 전역에서 사용하는 상수 및 설정값을 정의합니다.
 */

// ---------------------------------------------------------
// 1. 상점 상태 (Shop Status) 상수 정의
// ---------------------------------------------------------
// [시스템 개발 설정]
// * 테스트 모듈(task_test.php) 실행 및 로그 기록 여부 (true: 실행, false: 중지)
define('ENABLE_TASK_TEST', true);

define('GOOGLE_TRANSLATE_API_KEY', 'AIzaSyCSnfeHU4QI0Az7ycdfvXsQv1J7a_a-Mhw');

define('GEMINI_API_KEY', 'AIzaSyCWK3it3CyucmwxHs9VnqTBp3xvTlcvSvs');

/**
 * KShops24 AI 연동용 최신 Google Gemini 모델 정의
 * * 속도가 빠르고 텍스트 처리 비용이 매우 저렴한 1.5 Flash 모델을 권장합니다.
 */
define('GEMINI_MODEL', 'gemini-1.0-pro'); // ⭕ 가장 안정적인 범용 모델인 gemini-1.0-pro 사용

// [텔레그램 봇 설정]
define('PS24_BOT_TOKEN', '8748825415:AAGfX5IByxVQJii57EL7JPBOzGLMk3Nq2KY');
define('PS24_BOT_CHAT_ID', '8641026340');

// [카카오 로그인 설정]
// * Kakao Developers > 내 애플리케이션 > 요약 정보에서 'REST API 키'를 복사하세요.
// * 주의: JavaScript 키가 아닌 'REST API 키'를 넣어야 합니다.
define('KAKAO_REST_API_KEY', '461d2ab817f7b7832592405576a4068d');

// * Kakao Developers > 내 애플리케이션 > 요약 정보에서 'JavaScript 키'를 복사하세요.
// * 프론트엔드에서 카카오톡 앱 호출 시 사용됩니다.
define('KAKAO_JS_KEY', 'bad682065d7a997ad74c2bd0c5f7121c');

// * Kakao Developers > 제품 설정 > 카카오 로그인 > 보안에서 'Client Secret'을 발급받으세요.
// * 활성 상태가 'ON'인 경우 반드시 이 값이 일치해야 KOE101/invalid_client 에러가 나지 않습니다.
define('KAKAO_CLIENT_SECRET', '여기에_실제_발급받은_Secret_문자열을_넣으세요');

// * Redirect URI 설정 (HTTPS 강제 적용 권장)
// * Kakao Developers > 카카오 로그인 > Redirect URI에 등록된 주소와 완전히 일치해야 합니다.
/**
 * [KOE006 에러 해결 핵심]
 * 1. 아래 주소를 복사하세요:
 *    https://kshops24.com/shops/customer_kakao_callback.php
 * 
 * 2. 카카오 디벨로퍼스 > 내 애플리케이션 > 제품 설정 > 카카오 로그인 > Redirect URI에 위 주소를 추가하세요.
 * 3. 만약 www.kshops24.com으로 접속하신다면 해당 버전도 함께 등록해야 합니다.
 */
define('KAKAO_REDIRECT_URI', "https://kshops24.com/shops/customer_kakao_callback.php");

// [전화번호 정보 관련 공지사항]
define('PHONE_INFO_NOTICE_1',  '현지 정부의 관련법에 따라 허위 번호 도용 시 법적 처벌을 받을 수 있습니다.');
define('PHONE_INFO_NOTICE_2',  '본인 소유의 번호인지를 확인하는 절차가 진행될 수 있습니다.');

// DB에 저장될 실제 값 (ENUM)
define('SHOP_STATUS_APPLYING',      'applying');  // 신청 (승인 대기)
define('SHOP_STATUS_TESTING',       'testing');   // 테스팅 (시스템 점검/준비)
define('SHOP_STATUS_ACTIVE',        'active');    // 운영 (정상 영업 중)
define('SHOP_STATUS_INACTIVE_SOON', 'inactive_soon');  // 휴점 예정
define('SHOP_STATUS_INACTIVE',      'inactive');  // 휴점 (일시정지/사용기간만료/비용연체)
define('SHOP_STATUS_CLOSED_SOON',   'closed_soon'); // 폐점 예정 (휴점 후 폐점 전)
define('SHOP_STATUS_CLOSED',        'closed');    // 폐점 (영업 종료)
define('SHOP_STATUS_DELETED_SOON',  'deleted_soon'); // 삭제 예정 (폐점 후 삭제 전)
define('SHOP_STATUS_DELETED',       'deleted');    // 삭제 (완전 삭제)

define('SHOP_STATUS_OWNER_INACTIVE',    'owner_inactive');  // 상점주 휴점 요청 (상점주가 직접 휴점 신청, 승인 절차 없이 바로 휴점 상태로 변경)
define('SHOP_STATUS_OWNER_DELETED',     'owner_deleted');   // 상점주 삭제 요청 (완전 삭제, 7주일 후 상점 삭제 됨)

define('NOTIFICATION_SOUND', '/assets/sounds/dingdongg.mp3');

// ---------------------------------------------------------
// 2. 상태별 표시 라벨 (UI 출력용)
// ---------------------------------------------------------
// 화면에 보여줄 한글 명칭과 배지 색상(Bootstrap class)을 매핑해 두면 편리합니다.
$shop_status_config = [
    SHOP_STATUS_APPLYING => [
        'label' => '신청',
        'class' => 'bg-warning text-dark' // 노란색
    ],
    SHOP_STATUS_TESTING => [
        'label' => '테스팅',
        'class' => 'bg-info text-dark'    // 하늘색
    ],
    SHOP_STATUS_ACTIVE => [
        'label' => '운영',
        'class' => 'bg-success'           // 초록색
    ],
    SHOP_STATUS_INACTIVE_SOON => [
        'label' => '휴점 예정',
        'class' => 'bg-warning'           // 노란색
    ],
    SHOP_STATUS_INACTIVE => [
        'label' => '휴점',
        'class' => 'bg-secondary'         // 회색
    ],
    SHOP_STATUS_CLOSED_SOON => [
        'label' => '폐점 임박',
        'class' => 'bg-dark'              // 검정색
    ],
    SHOP_STATUS_CLOSED => [
        'label' => '폐점',
        'class' => 'bg-dark'              // 검정색
    ]
];

// ---------------------------------------------------------
// 3. 결제 유형 (Payment Type) 상수 정의
// ---------------------------------------------------------

// 휴점 및 폐점 관련 알림과 처리 로직에서 사용되는 날짜 기준 상수들을 정의합니다.
//   -14        0         14        30        60         90
// 휴점알림     휴점    폐점알림     폐점     삭제알림     삭제
//            만료일

// [결제 및 정산 관련 설정] 휴점 임박 알림 기준일
define('SHOP_STATUS_INACTIVE_SOON_DAYS', 14);

// [결제 및 정산 관련 설정] 휴점 후 폐점 임박 알림 기준일
define('WARNING_SHOP_STATUS_CLOSED_SOON_DAYS', 14);

// [결제 및 정산 관련 설정] 휴점 후 폐점 결정일 
define('SHOP_STATUS_CLOSED_SOON_DAYS', 30); // 휴점 후 30일 이내에 폐점 처리 (폐점 처리되면 상점 홈페이지는 작동하나 "폐점"이라는 플로팅 경고가 화면 중앙에 스크롤로 따라 다님)

// [결제 및 정산 관련 설정] 폐점 후 삭제 알림 기준일 
define('WARNING_SHOP_STATUS_DELETED_SOON_DAYS', 30);

// [결제 및 정산 관련 설정] 폐점 후 삭제 결정일 
define('SHOP_STATUS_DELETED_SOON_DAYS', 30); // 휴점 후 90일 후 완전 삭제

define('PAY_TYPE_6MONTHS', '6months');
define('PAY_TYPE_4MONTHS_FREE', '4months_free'); // 무료 4개월 월 사용료
define('PAY_TYPE_MONTHLY', 'monthly');
define('PAY_TYPE_SETUP',   'setup');
define('PAY_TYPE_ADDON',   'addon');
define('PAY_TYPE_ETC',     'etc');

$pay_type_labels = [
    PAY_TYPE_6MONTHS => '6개월 사용료',
    PAY_TYPE_4MONTHS_FREE => '4개월 사용료 (무료)',
    PAY_TYPE_MONTHLY => '월 사용료',
    PAY_TYPE_SETUP   => '구축비',
    PAY_TYPE_ADDON   => '추가비용',
    PAY_TYPE_ETC     => '기타비용'
];

// [결제 및 정산 관련 설정] 공통 사용을 위한 '만료 임박 상점' 조회 SQL 서브쿼리 및 조건절 상수
// 1. paid 조건에 'n'을 추가하거나 아예 제거하여 모든 상태의 만료일을 체크합니다.
// 2. pay_type에 PAY_TYPE_SETUP (구축비)를 추가합니다.

define('SQL_EXPIRING_SUBQUERY', "
    SELECT shop_id, 
           COALESCE(
               MIN(CASE WHEN paid = 'n' THEN CAST(NULLIF(expiring_date, '') AS DATE) END),
               MAX(CASE WHEN paid IN ('y', 'f') AND pay_type IN ('" . PAY_TYPE_6MONTHS . "', '" . PAY_TYPE_MONTHLY . "', '" . PAY_TYPE_4MONTHS_FREE . "') THEN CAST(NULLIF(expiring_date, '') AS DATE) END)
           ) as max_expiring_date 
    FROM shop_payments 
    GROUP BY shop_id
");

define('SQL_EXPIRING_CONDITION', "max_expiring_date IS NOT NULL AND max_expiring_date <= DATE_ADD(CURDATE(), INTERVAL " . SHOP_STATUS_INACTIVE_SOON_DAYS . " DAY)");

// ---------------------------------------------------------
// [추가] 무료 프로모션용 결제 유형 상수 (무료 프로모션 후에는 'n'로 변경하여 실제 청구에서 제외할 수 있습니다.)
// ---------------------------------------------------------
define('SETUP_FREE', 'y');
define('4MONTHS_FREE', 'y');

// [추가] 결제 내역 비고(Note) 전용 상수
define('PAY_NOTE_REGISTRATION', '입점신청');

// ---------------------------------------------------------
// 4. 상점 카테고리 (Shop Categories) 상수 및 동적 로드
// ---------------------------------------------------------
// [리팩토링] DB에 등록된 카테고리 정보를 동적으로 로드하여 상수 및 라벨 배열을 자동 생성합니다.
$shop_category_labels = [];
if (isset($pdo)) {
    try {
        // [수정] site_settings 테이블에서 JSON으로 저장된 카테고리 정보를 가져옵니다.
        $stmt_cats = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
        $json_cats = $stmt_cats->fetchColumn();
        $categories = $json_cats ? json_decode($json_cats, true) : [];

        // [버그 수정] DB에 등록된 카테고리가 구버전이거나 'srv' 등이 누락된 경우 오류를 방지하기 위해 기본 시스템 카테고리를 병합합니다.
        $default_system_cats = [
            'fnb'    => '음식점 / 카페',
            'realty' => '부동산 / 중개',
            'srv'    => '예약 / 서비스'
        ];
        $categories = array_merge($default_system_cats, $categories);

        foreach ($categories as $key => $name) {
            // 1. 전역 라벨 배열에 추가 (UI 출력용)
            $shop_category_labels[$key] = $name;

            // 2. 상수 정의 (코드 로직용) - 예: SHOP_CATEGORY_FNB
            $const_name = 'SHOP_CATEGORY_' . strtoupper($key);
            if (!defined($const_name)) {
                define($const_name, $key);
            }
        }
    } catch (Exception $e) {
        // DB 오류 발생 시 기본값으로 폴백
    }
}

// DB 연결 실패 또는 카테고리 미설정 시를 대비한 최소한의 기본값 설정
if (empty($shop_category_labels)) {
    if (!defined('SHOP_CATEGORY_FNB')) define('SHOP_CATEGORY_FNB', 'fnb');
    if (!defined('SHOP_CATEGORY_REALTY')) define('SHOP_CATEGORY_REALTY', 'realty');
    if (!defined('SHOP_CATEGORY_SRV')) define('SHOP_CATEGORY_SRV', 'srv');
    $shop_category_labels = [
        'fnb' => '음식점 / 카페',
        'realty' => '부동산 / 중개',
        'srv' => '예약 / 서비스'
    ];
}

// ---------------------------------------------------------
// [추가] 상점 카테고리별 검색창 플레이스홀더 텍스트 매핑
// ---------------------------------------------------------
$shop_search_placeholders = [
    'fnb'  => '어떤 메뉴를 찾으시나요? (메뉴명 등)',
    'realty' => '어떤 매물을 찾으시나요? (매물명, 특징 등)',
    'srv'  => '어떤 서비스를 원하시나요? (서비스명, 특징 등)'
];

// ---------------------------------------------------------
// 5. 공통 UI 라벨 (Common UI Labels) 기본값 정의
// ---------------------------------------------------------
define('SHOP_DEFAULT_LABEL_STORY',    'Our Story');
define('SHOP_DEFAULT_LABEL_GALLERY',  '매장 사진들 (PHOTO GALLERY)');
define('SHOP_DEFAULT_LABEL_LOCATION', '찾아오시는 길 (LOCATION)');

// ---------------------------------------------------------
// 6. 관리 페이지 공통 UI 스타일 (Admin & Shop Owner Management)
// ---------------------------------------------------------
// 상점별 카테고리 config.php에서 이 배열 값을 변경하여 디자인을 커스텀할 수 있습니다.
$UI_STYLE = [
    'section_title' => 'font-size: 1.1rem; font-weight: 700; color: #334155; margin-bottom: 1.25rem;',
    'section_sub'   => 'font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;',
    'item_label'    => 'font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;',
    'tab_label'     => 'font-size: 0.875rem; font-weight: 600;',
    'page_title'    => 'font-size: 1.5rem; font-weight: 800; color: #1e293b;'
];

// ---------------------------------------------------------
// 7. shop_board 게시판 유형(Type) 상수 정의
// ---------------------------------------------------------
// 게시판, 공지사항, 메시지, 이메일 발송 내역 등의 구분을 명확히 하기 위해 사용됩니다.
define('BOARD_TYPE_NOTICE', 'notice');           // 홈페이지 전체 공지사항 (shop_id = 0)
define('BOARD_TYPE_MESSAGE', 'message');         // 관리자가 상점, 혹은 상점에서 관리자에게 보낸 쪽지
define('BOARD_TYPE_EMAIL_LOG', 'email_log');     // 상점에게 보낸 이메일 발송 내역

// DB에 등록된 카테고리를 조회하여 상수 및 라벨 배열로 자동 생성합니다.
$board_type_labels = [
    BOARD_TYPE_NOTICE  => '공지사항',
    BOARD_TYPE_MESSAGE => '상점/상점주 메시지',
    BOARD_TYPE_EMAIL_LOG => '이메일 발송 내역'
];


// 페이지당 리스트 수 기본값 (관리자 페이지에서 사용)
define('LISTS_PER_PAGE', 10);

// (추후 필요한 다른 전역 설정들도 여기에 추가하시면 됩니다.)

// 상점 히스토리 로그 타입 상수 (향후 추가 가능)
define('SHOP_HISTORY_STATUS', 'status');   // 상태 변경
define('SHOP_HISTORY_EMAIL', 'email');     // 이메일 발송 내역
define('SHOP_HISTORY_MESSAGE', 'message'); // 쪽지/알림 발송 내역
define('SHOP_HISTORY_BILLING', 'billing'); // 과금 및 결제 처리 내역
define('SHOP_HISTORY_INFO', 'info');       // 상점 기본정보 수정 내역

// ---------------------------------------------------------
// 8. 시스템 로그 유형 (System Log Types) 상수 정의
// ---------------------------------------------------------
define('LOG_TYPE_INFO', 'info');                 // 일반 정보 및 안내
define('LOG_TYPE_ERROR', 'error');               // 시스템 오류
define('LOG_TYPE_EMAIL_FAIL', 'email_fail');     // 이메일 발송 실패
define('LOG_TYPE_ADMIN_ACTION', 'admin_action'); // 관리자 주요 설정 변경 이력
define('LOG_TYPE_DELETED', 'deleted');           // 상점 영구 삭제 시 백업 정보

$site_log_type_labels = [
    LOG_TYPE_ADMIN_ACTION => 'ADMIN (관리자 작업)',
    LOG_TYPE_ERROR => 'ERROR (시스템 오류)',
    LOG_TYPE_EMAIL_FAIL => 'EMAIL_FAIL (발송 실패)',
    LOG_TYPE_INFO => 'INFO (일반 정보)',
    LOG_TYPE_DELETED => 'DELETED (상점 삭제 백업)'
];

// ---------------------------------------------------------
// 9. 상점 uploads 디렉토리 경로 상수 정의
// ---------------------------------------------------------
define('SHOP_UPLOADS_DIR', __DIR__ . '/uploads/shops'); // 실제 파일 시스템 경로
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
define('SHOP_UPLOADS_URL', '/uploads/shops/'); // 웹에서 접근 가능한 URL 경로