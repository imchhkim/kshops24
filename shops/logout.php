<?php
/**
 * 상점(고객/상점주) 로그아웃 처리 모듈
 * 위치: /public_html/shops/logout.php
 */

// 1. 공통 헤더 로드 (DB 세션 핸들러 등)
require_once dirname(__DIR__) . '/common/common_header.php';

// 2. 모든 세션 정보 완전히 파기 (로그아웃 처리)
session_destroy();

// 3. 되돌아갈 경로 확인 (이전 페이지가 있으면 그곳으로, 없으면 포털 메인으로)
$referer = $_SERVER['HTTP_REFERER'] ?? '/';

// 4. 리다이렉트 및 스크립트 종료
header("Location: " . $referer);
exit;