<?php

/**
 * SRV(서비스/예약) 카테고리 전용 설정 및 스타일 오버라이드
 */

// 서비스 섹션 기본 라벨 정의
define('SRV_DEFAULT_LABEL_PROMOTION', '프로모션/이벤트');
define('SRV_DEFAULT_LABEL_DISCOUNT_SERVICE', '할인 서비스');
define('SRV_DEFAULT_LABEL_NEW_SERVICE', '신규 서비스');
define('SRV_DEFAULT_LABEL_BEST_SERVICE', '추천 서비스');
define('SRV_DEFAULT_LABEL_ALL_SERVICES', '전체 서비스');

// 서비스 유형 상수 정의 (예: 방문, 매장 내)
define('SRV_SERVICE_TYPES', [
    '방문 서비스',
    '매장 내 서비스',
    '온라인 서비스',
    '기타'
]);

// 서비스 예약 상태 상수 및 UI 매핑 설정 (공통 사용)
global $SRV_ORDER_STATUS;
$SRV_ORDER_STATUS = [
    'pending'   => ['text' => '예약 대기', 'class' => 'warning'],
    'confirmed' => ['text' => '예약 확정', 'class' => 'primary'],
    'completed' => ['text' => '서비스 완료', 'class' => 'success'],
    'cancelled' => ['text' => '예약 취소', 'class' => 'secondary'],
];

// 전역 변수 $UI_STYLE이 존재할 경우 특정 항목만 덮어쓰기
if (isset($UI_STYLE)) {
    // 상점관리 페이지의 섹션 제목을 조금 더 브랜드 컬러에 가깝게 변경하는 예시
    $UI_STYLE['section_title'] = 'font-size: 1.25rem; font-weight: 800; color: #1d69ab; margin-bottom: 1.5rem;'; // 서비스에 어울리는 파란색 계열로 변경
    $UI_STYLE['item_label']    = 'font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;';
    $UI_STYLE['tab_label']     = 'font-size: 0.95rem; font-weight: 800;';
}
