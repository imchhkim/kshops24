<?php
/**
 * KShops24 고객 로그아웃 처리 스크립트
 * - 기능: 고객 세션 변수만 제거하여 로그아웃 처리
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 로그아웃 후 돌아갈 상점 식별자 확인
$subdomain = $_GET['subdomain'] ?? '';

// 고객 관련 세션 변수만 선택적으로 제거 (관리자나 점주 세션에 영향을 주지 않음)
unset($_SESSION['customer_id']);
unset($_SESSION['customer_shop_id']);
unset($_SESSION['customer_nickname']);
unset($_SESSION['customer_profile_img']);
unset($_SESSION['customer_ph_phone']);
unset($_SESSION['customer_ph_address']);

// 리다이렉트 처리: 서브도메인이 있으면 상점 페이지로, 없으면 메인 포털로 이동
if (!empty($subdomain)) {
    header("Location: /" . $subdomain);
} else {
    header("Location: /index.php");
}
exit;