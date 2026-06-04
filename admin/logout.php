<?php
/**
 * [컨트롤러] KShops24 슈퍼 관리자 로그아웃 처리
 * - 기능: 서버 세션 파기 및 관리자 권한 해제
 * - 상세: 모든 세션 변수를 제거하고 로그인 페이지로 안전하게 리다이렉트합니다.
 */

// 1. [공통 헤더 로드] DB 세션 핸들러를 포함한 공통 엔진 로드
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 2. [데이터 파기] 현재 서버에 저장된 모든 세션 변수 및 데이터를 완전히 삭제
// 관리자 로그인 정보(admin_logged_in 등)가 여기서 모두 초기화됩니다.
session_destroy();

// 3. [페이지 이동] 로그아웃 완료 후 관리자 로그인 화면으로 즉시 리다이렉트
header("Location: admin_login.php");

// 4. [프로세스 종료] 헤더 이동 후 이후 코드가 실행되지 않도록 강제 종료
exit;
?>