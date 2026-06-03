<?php

/**
 * KShops24 Database Configuration
 * 파트너님이 제공해주신 최신 호스팅 정보를 기반으로 설정되었습니다.
 */

// [추가] PHP의 시간대(Timezone)를 필리핀 시간으로 설정 (주문 번호 생성 시 오늘 날짜가 정확히 반영되도록 함)
date_default_timezone_set('Asia/Manila');

// 1. 데이터베이스 접속 정보 설정
$db_host = 'localhost'; // 호스팅 내부 접속 시 일반적으로 localhost 사용
$db_name = 'u743828642_KShops24'; // 데이터베이스 이름
$db_user = 'u743828642_KShops24'; // 데이터베이스 사용자 ID
$db_pass = 'zlatmgK15%';           // 데이터베이스 비밀번호
$db_charset = 'utf8mb4';           // 한국어 및 이모지 지원을 위한 설정

// 2. PDO를 이용한 안전한 연결
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 에러 발생 시 예외 발생
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 결과를 연관 배열로 반환
        PDO::ATTR_EMULATE_PREPARES   => false,                  // SQL 인젝션 방지 보안 설정
        // [수정] 필리핀 타임존 고정 및 Collation 충돌 방지를 위한 문자셋 명시
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00', NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // 연결 성공 확인용 (운영 시에는 주석 처리하거나 삭제하세요)
    // echo "Database Connected Successfully!"; 

} catch (\PDOException $e) {
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
