<?php

/**
 * Realty(부동산) 카테고리 전용 설정 및 스타일 오버라이드
 */

// 메뉴 섹션 기본 라벨 정의
define('REALTY_DEFAULT_LABEL_FLYER', '홍보 전단지');
define('REALTY_DEFAULT_LABEL_QUICK_SALE', '급매 물건');
define('REALTY_DEFAULT_LABEL_NEW_ITEM', '신규 물건');
define('REALTY_DEFAULT_LABEL_BEST_MENU', '추천 물건');
define('REALTY_DEFAULT_LABEL_ALL_ITEMS', '전체 물건');
define('REALTY_DEFAULT_LABEL_FOR_SALE_ITEMS', '매매 물건');
define('REALTY_DEFAULT_LABEL_FOR_RENT_ITEMS', '임대 물건');

// 부동산 거래 유형 상수 정의
define('REALTY_TRADE_TYPES', [
    '매매',
    '장기임대 (1년 혹은 그 이상)',
    '단기임대 (수개월)',
    '기타'
]);

// 부동산 문의(예약) 상태 상수 및 UI 매핑 설정 (공통 사용)
global $REALTY_ORDER_STATUS;
$REALTY_ORDER_STATUS = [
    'forsale'   => ['text' => '판매중', 'class' => 'warning'],
    'forrent'   => ['text' => '임대중', 'class' => 'warning'],
    'reserved'   => ['text' => '예약중',   'class' => 'primary'],
    'completed' => ['text' => '완료', 'class' => 'success'],
];

// 전역 변수 $UI_STYLE이 존재할 경우 특정 항목만 덮어쓰기
if (isset($UI_STYLE)) {
    // 상점관리 페이지의 섹션 제목을 조금 더 브랜드 컬러에 가깝게 변경하는 예시
    $UI_STYLE['section_title'] = 'font-size: 1.25rem; font-weight: 800; color: #004aad; margin-bottom: 1.5rem;';
    $UI_STYLE['item_label']    = 'font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;';
    $UI_STYLE['tab_label']     = 'font-size: 0.95rem; font-weight: 800;';
}
