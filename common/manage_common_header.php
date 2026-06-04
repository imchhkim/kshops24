<?php

/**
 * 파일명: /common/manage_common_header.php
 * 역할: 상점관리자 전용 보안 검증 및 공통 로직 컨트롤러
 * 상세: 
 */

// 1. 시스템 공통 엔진(/common/common_header.php) 로드
// __DIR__을 사용하여 동일 폴더 내의 파일을 호출함
require_once __DIR__ . '/common_header.php';

// 2. 상점 관리자 로그인 상태 확인 (세션은 common_header.php에서 이미 시작됨)
if (!isset($_SESSION['shop_id'])) {
    header("Location: /shops/login.php");
    exit;
}

$shop_id = (int)$_SESSION['shop_id'];

// 3. 상점 기본 정보 데이터 로드 및 검증
$stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt->execute([$shop_id]);
$shop = $stmt->fetch();

if (!$shop) {
    session_destroy();
    header("Location: /shops/login.php");
    exit;
}

// 4. 상점 카테고리별 설정 로드 (라벨 상수 등)
$category_config = $_SERVER['DOCUMENT_ROOT'] . "/shops/{$shop['category']}/{$shop['category']}_config.php";
if (file_exists($category_config)) {
    include_once $category_config;
}

// 상점 카테고리 정보
$shop_category = !empty($shop['category']) ? $shop['category'] : 'fnb';
$shop_category_label = $shop_category_labels[$shop_category] ?? '일반';
