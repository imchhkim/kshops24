<?php

/**
 * 파일명: /common/common_header.php
 * 역할: KShops24 시스템의 핵심 실행 엔진 및 전역 설정 로드
 * 상세: DB 연결, 타임존 설정, 세션 관리, 공통 유틸리티 호출을 담당함.
 */

// 1. 서버 루트 및 기본 경로 확보
$base_dir = dirname(__DIR__);

// 2. 메시지 초기화 및 필리핀 현지 타임존 설정
$message = "";
date_default_timezone_set('Asia/Manila');

// ---------------------------------------------------------
// 2-1. [확장] 에러 및 예외 처리 시스템
// ---------------------------------------------------------
// [개발 모드] 상세 에러 메시지 활성화 (500 에러 원인 확인용)
// error_reporting(E_ALL);
// 설명: 이 함수는 PHP의 오류 보고 수준을 설정합니다. E_ALL은 PHP에서 발생할 수 있는 모든 종류의 오류와 경고를 보고하도록 지시하는 상수입니다. 여기에는 구문 오류, 런타임 경고, 주의(notice) 등이 포함됩니다.
// 목적: 개발 중에는 코드의 모든 잠재적인 문제를 파악하는 것이 중요하므로, 가장 높은 수준의 오류 보고를 활성화하여 사소한 경고까지도 놓치지 않고 확인하고 수정할 수 있도록 합니다.

// ini_set('display_errors', 1);
// 설명: 이 함수는 PHP 설정(php.ini 파일의 display_errors 지시어)을 런타임에 변경합니다. 1은 오류 메시지를 웹 페이지 출력에 포함하여 브라우저에 직접 표시하도록 설정합니다.
// 목적: 개발자가 코드를 실행했을 때 발생하는 오류를 즉시 브라우저 화면에서 확인할 수 있게 하여 디버깅 시간을 단축시킵니다. 오류가 발생하면 흰 화면(blank page)만 뜨는 대신, 구체적인 오류 메시지와 발생 위치를 알 수 있습니다.

// ini_set('display_startup_errors', 1);
// 설명: 이 함수는 PHP 설정(php.ini 파일의 display_startup_errors 지시어)을 런타임에 변경합니다. 1은 PHP 엔진이 시작될 때 발생하는 오류(예: 필수 모듈 로드 실패 등)도 브라우저에 표시하도록 설정합니다.
// 목적: display_errors가 런타임에 발생하는 오류를 처리하는 반면, display_startup_errors는 PHP 스크립트가 실행되기 전, PHP 자체의 초기화 과정에서 발생하는 오류를 잡아냅니다. 이 두 가지를 모두 활성화하면 PHP 실행의 모든 단계에서 발생하는 오류를 확인할 수 있습니다.
// 종합적인 의미 및 주의사항:

// 이 세 가지 설정은 함께 사용될 때 개발자가 PHP 애플리케이션의 문제를 진단하고 해결하는 데 매우 강력한 도구가 됩니다. 주석에 명시된 대로 "500 에러 원인 확인용"으로 사용되는 것이 바로 이 때문입니다. 500 Internal Server Error는 서버 측에서 알 수 없는 오류가 발생했을 때 나타나는데, 이 설정들을 통해 그 "알 수 없는 오류"의 구체적인 내용을 파악할 수 있습니다.
// 하지만, 이 설정들은 반드시 개발 환경에서만 사용해야 합니다.
// 보안 취약점: 운영 환경에서 오류 메시지를 사용자에게 직접 노출하면, 데이터베이스 연결 정보, 파일 경로, 서버 설정 등 민감한 정보가 유출될 수 있습니다. 이는 해커에게 공격의 빌미를 제공할 수 있습니다.
// 사용자 경험 저하: 오류 메시지는 기술적인 내용이므로 일반 사용자에게는 혼란스럽고 불쾌한 경험을 줄 수 있습니다.
// 따라서, 운영 환경에서는 error_reporting을 E_ALL이 아닌 0으로 설정하거나, display_errors와 display_startup_errors를 0으로 설정하여 오류 메시지가 브라우저에 표시되지 않도록 해야 합니다. 대신, 오류는 서버 로그 파일에 기록되도록 설정하여 관리자만 확인할 수 있도록 하는 것이 일반적인 보안 및 운영 모범 사례입니다.

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

/**
 * 에러를 DB(site_logs) 및 화면에 기록하는 핵심 함수
 */
function logAndDisplayError($type, $msg, $file = '', $line = '')
{
    global $pdo;

    // [수정] 직접 DB에 남기는 에러 상세 정보도 HTML 구조로 가독성 있게 정리
    $details_html = "<ul class='mb-0' style='list-style-type: none; padding-left: 0; margin-top: 5px;'>";
    $details_html .= "<li><strong class='text-danger'>에러 종류:</strong> <span class='text-dark'>" . htmlspecialchars($type) . "</span></li>";
    if ($file) $details_html .= "<li><strong class='text-secondary'>발생 파일:</strong> <span class='text-dark'>" . htmlspecialchars($file) . "</span></li>";
    if ($line) $details_html .= "<li><strong class='text-secondary'>발생 라인:</strong> <span class='text-dark'>" . htmlspecialchars($line) . "</span></li>";
    $details_html .= "</ul>";

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // 1. DB 기록 (PDO 연결이 살아있는 경우)
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO site_logs (log_type, message, details, ip_address) VALUES (?, ?, ?, ?)");
            // [수정] LOG_TYPE_ERROR 상수를 참조하여 기록 (상수가 로드되지 않은 시점의 안전장치 추가)
            $stmt->execute([defined('LOG_TYPE_ERROR') ? LOG_TYPE_ERROR : 'error', $msg, $details_html, $ip]);
        } catch (Exception $e) {
            error_log("DB Logging Failed: " . $e->getMessage());
        }
    }

    // 2. 화면 표시 (Bootstrap 5 Alert)
    echo "
    <div class='container mt-3'>
        <div class='alert alert-danger shadow-sm border-start border-4 border-danger' role='alert'>
            <h5 class='alert-heading fw-bold'><i class='bi bi-exclamation-octagon-fill me-2'></i>시스템 오류 발생 ({$type})</h5>
            <p class='mb-1'><strong>메시지:</strong> " . htmlspecialchars($msg) . "</p>
            <hr>
            <p class='mb-0 small text-muted'>위치: {$file} (Line: {$line})</p>
        </div>
    </div>";
}

// 일반 에러 핸들러
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    logAndDisplayError("ERROR_$errno", $errstr, $errfile, $errline);
    return true;
});

// 예외 핸들러
set_exception_handler(function ($exception) {
    logAndDisplayError("EXCEPTION", $exception->getMessage(), $exception->getFile(), $exception->getLine());
});

// 치명적 오류(Fatal Error) 핸들러
register_shutdown_function(function () {
    $error = error_get_last();
    // E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR 등 치명적 오류 체크
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // 이미 출력이 진행 중일 수 있으므로 버퍼를 비우지 않고 추가 출력
        logAndDisplayError("FATAL_ERROR", $error['message'], $error['file'], $error['line']);
    }
});

// ---------------------------------------------------------
// 3-2. 기타 상수 정의
// ---------------------------------------------------------
// 페이지당 출력 항목 수 공통 상수 정의
define('ITEMS_PER_PAGE', 5);

// 4. 핵심 설정 파일 경로 정의 (절대 경로)
$db_config_file   = $base_dir . '/db_config.php';
$site_config_file = $base_dir . '/config.php';

// 5. 데이터베이스 연결 설정 로드
if (file_exists($db_config_file)) {
    require_once $db_config_file;
} else {
    die("시스템 오류: DB 설정 파일(db_config.php)을 찾을 수 없습니다.");
}

// 6. 사이트 전역 설정 로드
if (file_exists($site_config_file)) {
    require_once $site_config_file;
} else {
    die("시스템 오류: 사이트 설정 파일(config.php)을 찾을 수 없습니다.");
}

// 7. 세션 시작 및 DB 세션 핸들러 적용 (Hostinger Inode 최적화)
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }
    #[\ReturnTypeWillChange]
    public function read($id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM site_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
            return $data !== false ? $data : '';
        } catch (Exception $e) {
            return '';
        }
    }
    #[\ReturnTypeWillChange]
    public function write($id, $data): bool {
        try {
            $stmt = $this->pdo->prepare("REPLACE INTO site_sessions (id, data, updated_at) VALUES (?, ?, NOW())");
            return $stmt->execute([$id, $data]);
        } catch (Exception $e) {
            return false;
        }
    }
    public function destroy($id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM site_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
    public function gc($max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM site_sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
            return $stmt->execute([$max_lifetime]) ? 1 : false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// [핵심 버그 수정] 파일 세션과 DB 세션 간의 충돌로 인한 핑퐁(무한 리다이렉트) 방지
// 다른 파일(admin_login.php 등) 상단에서 파일 기반 세션이 먼저 시작된 경우, 강제로 닫고 덮어씌웁니다.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// [리팩토링] 시스템 공통 세션 유지 시간 설정 (12시간)
// 세션이 확실히 닫혀있거나 시작되기 전에 설정을 변경해야 에러가 발생하지 않습니다.
ini_set('session.gc_maxlifetime', 43200);

// 브라우저 쿠키 경로 충돌 방지를 위해 명시적으로 루트(/) 설정
session_set_cookie_params([
    'lifetime' => 43200,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (isset($pdo)) {
    session_set_save_handler(new DatabaseSessionHandler($pdo), true);
    // PDO 객체(DB 연결)가 파괴되기 전에 세션 데이터를 안전하게 먼저 기록(Write)하도록 강제 호출
    register_shutdown_function('session_write_close');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================
// [다국어 지원] 사용자 언어 감지 및 설정 로직
// =========================================================
// 1. URL 파라미터(?lang=en)를 통한 언어 변경 요청 시 처리
if (isset($_GET['lang']) && preg_match('/^[a-z0-9A-Z_-]+$/', $_GET['lang'])) {
    $_SESSION['shop_lang'] = $_GET['lang'];
    // 재방문 시에도 유지되도록 쿠키에 저장 (30일 유지)
    setcookie('shop_lang', $_GET['lang'], time() + (86400 * 30), "/");
}

// 2. 현재 언어 결정 (파라미터 변경 내역 > 기존 세션 > 쿠키 > 기본값 'ko' 순)
$current_lang = $_SESSION['shop_lang'] ?? $_COOKIE['shop_lang'] ?? 'ko';

// 3. 다국어 헬퍼 모듈 로드 및 현재 언어 데이터 초기화
require_once __DIR__ . '/lang_utils.php';
load_language($current_lang);
// =========================================================

// 8. 공통 유틸리티 함수 로드
$lib_utils_path = __DIR__ . '/lib_utils.php';
if (file_exists($lib_utils_path)) {
    require_once $lib_utils_path;
}

// ---------------------------------------------------------
// 9. [보안] 페이지별 순차 접근 제어 (Page Flow Security)
// ---------------------------------------------------------
// URL 직접 타이핑을 통한 부정 접근(약관 동의 건너뛰기 등)을 차단합니다.
$current_page = basename($_SERVER['PHP_SELF']);

/**
 * [규칙 1] register.php는 반드시 sel_category.php에서 카테고리 선택 후 넘어와야 함
 * (단, AJAX 중복 체크 요청인 check_field 파라미터가 있는 경우는 예외로 허용)
 */
if ($current_page === 'register.php' && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['check_field'])) {
    // 테스트 모드($FOR_TEST == "YES")가 아닐 때만 필수 파라미터 체크 및 리다이렉트 수행
    if ((!isset($FOR_TEST) || $FOR_TEST !== "YES") && (!isset($_GET['setup_fee']) || !isset($_GET['monthly_fee']) || !isset($_GET['category']))) {
        header("Location: /pre_register.php");
        exit;
    }
}

/**
 * [규칙 1-1] sel_category.php는 반드시 pre_register.php에서 약관 동의 후 넘어와야 함
 */
if ($current_page === 'sel_category.php' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // 테스트 모드($FOR_TEST == "YES")가 아닐 때만 필수 파라미터 체크 및 리다이렉트 수행
    if ((!isset($FOR_TEST) || $FOR_TEST !== "YES") && (!isset($_GET['setup_fee']) || !isset($_GET['monthly_fee']))) {
        header("Location: /pre_register.php");
        exit;
    }
}

/**
 * [규칙 2] register_success.php는 실제 가입 처리(register.php) 직후에만 접근 가능
 * (성공 세션 플래그가 없으면 메인으로 리다이렉트)
 */
if ($current_page === 'register_success.php') {
    // 테스트 모드($FOR_TEST == "YES")가 아닐 때만 세션 플래그 체크
    if ((!isset($FOR_TEST) || $FOR_TEST !== "YES") && !isset($_SESSION['reg_complete_id'])) {
        header("Location: /index.php");
        exit;
    }
}
