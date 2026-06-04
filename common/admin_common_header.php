<?php

/**
 * 파일명: /common/admin_common_header.php
 * 역할: 관리자 전용 보안 검증 및 공통 로직 컨트롤러
 * 상세: 관리자 세션 체크, 무한 리다이렉트 방지, 하위 뷰 접근 상수 정의.
 */

// 1. 시스템 공통 엔진(/common/common_header.php) 로드
// __DIR__을 사용하여 동일 폴더 내의 파일을 호출함
require_once __DIR__ . '/common_header.php';

// 2. 세션 상태 이중 확인
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. 현재 접속 페이지 확인 (리다이렉트 루프 방지용)
$current_page = basename($_SERVER['PHP_SELF']);
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_login_page = (strpos($current_page, 'admin_login.php') !== false || strpos($request_uri, 'admin_login.php') !== false);

/**
 * 4. 관리자 로그인 보안 체크
 * 로그인 페이지가 아닌 곳에 비정상 접근 시 관리자 로그인으로 강제 이동
 */
if (!$is_login_page) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: /admin/admin_login.php");
        exit;
    }
}

// 5. 관리자 정보 전역 변수화 (뷰 레이어에서 활용)
$admin_id   = $_SESSION['admin_user'] ?? $_SESSION['admin_id'] ?? 'Unknown';
$admin_name = $_SESSION['admin_name'] ?? '관리자';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// 6. CSRF(사이트 간 요청 위조) 방지 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * 7. 레이어드 구조 접근 허용 상수 정의 [가장 중요]
 * 하위 뷰 파일(manage_shops.php 등)이 직접 호출되는 것을 방지하고
 * admin_view.php를 통한 정상 호출임을 증명하는 '통행증' 역할.
 */
if (!defined('PDO_CONNECT_SUCCESS')) {
    define('PDO_CONNECT_SUCCESS', true);
}
?>

<style>
    /* KShops24 관리자 공통 테이블 스타일 */
    .table-ps24 {
        border-collapse: collapse !important;
        border: 1px solid #dee2e6 !important;
        /* 전체 테두리 */
    }

    /* 헤더 스타일: 흰색 수직 구분선 */
    .table-ps24 thead th {
        background-color: #f8d7da !important;
        /* table-danger 색상 계열 */
        color: #6c757d !important;
        text-align: center;
        vertical-align: middle;
        border-right: 1px solid #ffffff !important;
        /* 흰색 세로선 */
        border-bottom: none !important;
        font-weight: 700;
    }

    .table-ps24 thead th:last-child {
        border-right: none !important;
        /* 마지막 헤더 선 제거 */
    }

    /* 본문 스타일: 얇은 회색 수직 구분선 */
    .table-ps24 tbody td {
        vertical-align: middle;
        border-right: 1px solid #eeeeee !important;
        /* 얇은 세로선 */
        border-bottom: 1px solid #f1f1f1 !important;
        /* 얇은 가로선 */
    }

    .table-ps24 tbody td:last-child {
        border-right: none !important;
        /* 마지막 열 선 제거 */
    }

    /* 마우스 호버 효과 강화 */
    .table-ps24.table-hover tbody tr:hover {
        background-color: #fcfcfc !important;
    }

    /* 텍스트 중앙 정렬용 헬퍼 */
    .t-center {
        text-align: center !important;
    }

    .t-end {
        text-align: right !important;
    }
</style>