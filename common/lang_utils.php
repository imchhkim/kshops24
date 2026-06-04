<?php

/**
 * 다국어(i18n) 지원 헬퍼 함수 모듈
 * 언어 파일을 로드하고 번역된 문자열을 반환합니다.
 */

// 전역 번역 배열
global $global_lang_messages;
$global_lang_messages = [];

/**
 * 지정된 언어의 언어 파일을 로드합니다.
 */
function load_language($lang_code)
{
    global $global_lang_messages;

    if (!preg_match('/^[a-z0-9A-Z_-]+$/', $lang_code)) {
        $lang_code = 'ko'; // 기본값 오류 방지
    }

    // 웹 루트 절대 경로를 기준으로 명확하게 파일을 찾아 호스팅 권한/경로 충돌 에러를 원천 방지
    $lang_file = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . "/common/lang/{$lang_code}.php";

    if (file_exists($lang_file)) {
        $global_lang_messages = include $lang_file;
    } else {
        $global_lang_messages = [];
    }
}

/**
 * 키에 해당하는 번역 텍스트를 반환합니다.
 * 사용법: echo __('btn_login'); 
 * 
 * @param string $key 번역 키
 * @return string 번역된 텍스트 (사전에 없으면 키 자체를 반환)
 */
function __($key)
{
    global $global_lang_messages;
    return isset($global_lang_messages[$key]) ? $global_lang_messages[$key] : $key;
}

/**
 * DB의 JSON 컬럼에서 현재 언어에 맞는 데이터를 추출합니다.
 * 
 * @param string $default_val 기본 언어(한국어) 텍스트
 * @param string|null $json_translations JSON 형태의 번역 데이터
 * @param string $field_key 추출할 필드명 (예: 'item_name')
 * @return string
 */
function t_db($default_val, $json_translations, $field_key = null)
{
    $lang = $_SESSION['shop_lang'] ?? $_COOKIE['shop_lang'] ?? 'ko';

    if ($lang === 'ko' || empty($json_translations)) {
        return $default_val;
    }

    $translations = json_decode($json_translations, true);

    if (!is_array($translations) || !isset($translations[$lang])) {
        return $default_val;
    }

    // 1. 단일(Flat) JSON 형태인 경우 (예: 카테고리 자동 번역 {"en":"House Sale"})
    if (!is_array($translations[$lang])) {
        return trim($translations[$lang]) !== '' ? $translations[$lang] : $default_val;
    }

    // 2. 다중(Nested) JSON 형태인 경우 (예: 매물 직접 입력 {"en":{"item_name":"...", "item_info":"..."}})
    if ($field_key && isset($translations[$lang][$field_key]) && trim($translations[$lang][$field_key]) !== '') {
        return $translations[$lang][$field_key];
    }

    return $default_val;
}

/**
 * 부동산 카테고리 다국어 자동 번역 헬퍼 함수
 * - 공백을 무시하고 매칭하여 빈번한 오타나 띄어쓰기 차이를 보완합니다.
 * 
 * @param string $cat_name 원본 카테고리 이름 (한국어)
 * @param string $lang 대상 언어 코드 (예: 'en', 'zh', 'ja')
 * @return string 번역된 텍스트 (사전에 없으면 원본 반환)
 */
function translate_realty_category($cat_name, $lang)
{
    if ($lang === 'ko' || empty($lang)) return $cat_name;

    $normalized_cat = str_replace(' ', '', $cat_name);
    $auto_translate_map = [
        '주택매매' => ['en' => 'House Sale', 'zh' => '住宅买卖', 'ja' => '住宅売買'],
        '주택임대' => ['en' => 'House Rent', 'zh' => '住宅出租', 'ja' => '住宅賃貸'],
        '상가매매' => ['en' => 'Commercial Sale', 'zh' => '商铺买卖', 'ja' => '店舗売買'],
        '상가임대' => ['en' => 'Commercial Rent', 'zh' => '商铺出租', 'ja' => '店舗賃貸'],
        '콘도매매' => ['en' => 'Condo Sale', 'zh' => '公寓买卖', 'ja' => 'コンドミニアム売買'],
        '콘도임대' => ['en' => 'Condo Rent', 'zh' => '公寓出租', 'ja' => 'コンドミニアム賃貸'],
        '아파트매매' => ['en' => 'Apartment Sale', 'zh' => '公寓买卖', 'ja' => 'マンション売買'],
        '아파트임대' => ['en' => 'Apartment Rent', 'zh' => '公寓出租', 'ja' => 'マンション賃貸'],
        '토지매매' => ['en' => 'Land Sale', 'zh' => '土地买卖', 'ja' => '土地売買'],
        '렌트'     => ['en' => 'Rent', 'zh' => '出租', 'ja' => 'レンタル'],
        '월세'     => ['en' => 'Monthly Rent', 'zh' => '月租', 'ja' => '月家賃'],
        '전세'     => ['en' => 'Jeonse', 'zh' => '全租', 'ja' => 'チョンセ'],
        '급매'     => ['en' => 'Urgent Sale', 'zh' => '急售', 'ja' => '急売り'],
    ];

    return $auto_translate_map[$normalized_cat][$lang] ?? $cat_name;
}
