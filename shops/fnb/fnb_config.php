<?php

/**
 * F&B(음식점) 카테고리 전용 설정 및 스타일 오버라이드
 */

// 메뉴 섹션 기본 라벨 정의
define('FNB_DEFAULT_LABEL_MENU_BOARD', '전체 메뉴판');
define('FNB_DEFAULT_LABEL_DISCOUNT_MENU', '할인 메뉴');
define('FNB_DEFAULT_LABEL_NEW_MENU', '신메뉴');
define('FNB_DEFAULT_LABEL_BEST_MENU', '추천 메뉴');
define('FNB_DEFAULT_LABEL_ALL_MENU', '전체 메뉴');

// F&B 주문 상태 상수 및 UI 매핑 설정 (공통 사용)
global $FNB_ORDER_STATUS;
$FNB_ORDER_STATUS = [
    'pending'   => ['text' => '주문접수', 'class' => 'warning'],
    'cooking'   => ['text' => '요리중',   'class' => 'primary'],
    'delivery'  => ['text' => '배달중',   'class' => 'info'],
    'completed' => ['text' => '주문완료', 'class' => 'success'],
    'cancelled' => ['text' => '주문취소', 'class' => 'danger']
];

// 전역 변수 $UI_STYLE이 존재할 경우 특정 항목만 덮어쓰기
if (isset($UI_STYLE)) {
    // 상점관리 페이지의 섹션 제목을 조금 더 브랜드 컬러에 가깝게 변경하는 예시
    $UI_STYLE['section_title'] = 'font-size: 1.25rem; font-weight: 800; color: #004aad; margin-bottom: 1.5rem;';
    $UI_STYLE['item_label']    = 'font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;';
    $UI_STYLE['tab_label']     = 'font-size: 0.95rem; font-weight: 800;';
}
