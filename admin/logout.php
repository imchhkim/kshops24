<?php
/**
 * [컨트롤러] KShops24 슈퍼 관리자 로그아웃 처리
 * - 기능: 서버 세션 파기 및 관리자 권한 해제
 * - 상세: 모든 세션 변수를 제거하고 로그인 페이지로 안전하게 리다이렉트합니다.
 */

// 1. [공통 헤더 로드] DB 세션 핸들러를 포함한 공통 엔진 로드
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 2. [수정] 슈퍼 관리자 관련 세션 변수만 선택적으로 제거
// session_destroy() 대신 unset()을 사용하여 로그인된 상점 관리자 세션에 영향을 주지 않습니다.
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_user']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// 3. [페이지 이동] 로그아웃 완료 후 관리자 로그인 화면으로 즉시 리다이렉트
header("Location: admin_login.php");

// 4. [프로세스 종료] 헤더 이동 후 이후 코드가 실행되지 않도록 강제 종료
exit;
?>