<<<<<<< HEAD
<?php
/**
 * 테스터 모듈 공통 실행 파일 (t_common.php)
 * 역할: 모든 테스트 스크립트에서 공통으로 필요한 보안 검증 및 공통 헤더 로드를 한 곳에서 관리합니다.
 */

// 1. 공통 엔진 로드 (DB 연결 및 DB 세션 핸들러)
require_once dirname(__DIR__) . '/common/common_header.php';

// 2. API 모드 예외 처리 (t_check_files_sync.php 에서 원격 서버 통신용 API로 호출될 경우)
$is_api_mode = isset($_GET['action']) && $_GET['action'] === 'get_list';

// 3. 보안: 슈퍼 관리자 로그인 체크
if (!$is_api_mode) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die("<script>alert('슈퍼 관리자만 접근 가능한 테스트 모듈입니다.'); location.replace('/admin/admin_login.php');</script>");
    }
=======
<?php
/**
 * 테스터 모듈 공통 실행 파일 (t_common.php)
 * 역할: 모든 테스트 스크립트에서 공통으로 필요한 보안 검증 및 공통 헤더 로드를 한 곳에서 관리합니다.
 */

// 1. 공통 엔진 로드 (DB 연결 및 DB 세션 핸들러)
require_once dirname(__DIR__) . '/common/common_header.php';

// 2. API 모드 예외 처리 (t_check_files_sync.php 에서 원격 서버 통신용 API로 호출될 경우)
$is_api_mode = isset($_GET['action']) && $_GET['action'] === 'get_list';

// 3. 보안: 슈퍼 관리자 로그인 체크
if (!$is_api_mode) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die("<script>alert('슈퍼 관리자만 접근 가능한 테스트 모듈입니다.'); location.replace('/admin/admin_login.php');</script>");
    }
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
}