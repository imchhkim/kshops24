<?php

/**
 * K-Shops24 데이터베이스 인프라 환경설정
 * 코드를 수정하거나 신규 작성할 때 반드시 자세한 코멘트를 함께 누적합니다.
 */

// 현재 접속 브라우저의 호스트명을 기반으로 백엔드 인프라(테스트/실서버) 자동 분기
$current_host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($current_host, 'test.kshops24.com') !== false) {
    // =================================================================
    // [인프라 A] 서브도메인 테스트 서버 환경 DB 상수 (독립 샌드박스)
    // =================================================================
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u743828642_philshop24'); // 기존 유저 또는 테스트용 유저
    define('DB_PASS', 'zlatmgK15%');              // 기존 암호 상수
    define('DB_NAME', 'u743828642_philshop24');    // 🌟 테스트 전용 독립 디비로 격리!
    define('DISPLAY_ERRORS', true);                // 개발 중 디버깅을 위해 에러 오픈
} else {
    // =================================================================
    // [인프라 B] 정식 서비스 메인 운영 환경 DB 상수 (상용 라이브 데이터)
    // =================================================================
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u743828642_kshops24_admin'); // 새로 만든 정식 관리자 계정
    define('DB_PASS', 'zlatmgK15%');                // 새 인증 보안 암호 상수
    define('DB_NAME', 'u743828642_kshops24');      // 🌟 마이그레이션 완료된 진짜 상용 디비!
    define('DISPLAY_ERRORS', true);               // 고객 보안을 위해 에러 숨김
}

// 무결성 PDO 연결 바인딩 (화면 깜빡임 없는 AJAX CRUD 및 페이징 검색 전담)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // 전역에서 공유하여 사용할 고유 DB 커넥션 객체 바인딩
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {

    die("백엔드 데이터베이스 연결 실패: " . $e->getMessage());

    // 1. 보안을 위해 실제 에러 내용은 서버 로그에 기록합니다. (Hostinger의 error_log 파일에서 확인 가능)
    error_log("DB Connection Failed: " . $e->getMessage());

    // 2. 사용자에게는 500 에러 대신 친절한 시스템 점검 메시지를 보여줍니다.
    header('HTTP/1.1 503 Service Unavailable');
    die("
        <div style='text-align:center; padding:100px 20px; font-family: \"Apple SD Gothic Neo\", \"Malgun Gothic\", sans-serif; line-height: 1.6;'>
            <h1 style='color: #004aad; font-size: 2rem; margin-bottom: 20px;'>서비스 이용 안내</h1>
            <p style='font-size: 1.1rem; color: #444;'>현재 시스템 점검 또는 데이터베이스 설정 변경으로 인해<br>서비스 접속이 일시적으로 원활하지 않습니다.</p>
            <p style='font-size: 0.9rem; color: #999; margin-top: 30px;'>잠시 후 다시 시도해 주시기 바랍니다.</p>
            <div style='margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px;'>
                <p style='color: #bbb; font-size: 0.8rem;'>관리자이신 경우 <strong>db_config.php</strong>의 접속 정보를 다시 확인해 주세요.</p>
                <a href='/' style='text-decoration: none; color: #004aad; font-weight: bold;'>메인으로 돌아가기</a>
            </div>
        </div>
    ");
}
