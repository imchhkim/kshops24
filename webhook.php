<?php
/**
 * K-Shops24 GitHub 자동 배포 웹훅(Webhook) 스크립트
 * 코드를 수정하거나 신규 작성할 때 자세한 코멘트를 함께 기록합니다.
 */

// 부모 설정 파일 로드 (상수 사용 및 데이터베이스 연동용)
require_once __important_config_path_if_needed; // 필요 시 주석 해제하여 사용

// 보안 인증을 위한 비밀 키 설정 (상수 개념으로 정의, 임의의 긴 문자열)
define('WEBHOOK_SECRET', 'kshops24_secure_deploy_token_2026');

// 1. GitHub가 보낸 헤더 검증을 통한 보안 필터링
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (empty($signature)) {
    die('접근 거부: 유효하지 않은 요청입니다.');
}

// 2. 해시 암호 상수를 이용해 GitHub가 보낸 서명이 내 비밀키와 일치하는지 무결성 검증
$local_hash = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);

if (!hash_equals($local_hash, $signature)) {
    die('보안 인증 실패: 서명이 일치하지 않습니다.');
}

// 3. 검증이 완료되면 호스팅어 리눅스 서버 내부에서 배포 명령어 강제 실행
// 화면 깜빡임과 서비스 끊김을 유발하지 않는 무중단(Zero-Downtime) 리눅스 스트림 명령어
$target_dir = '/home/u743828642/domains/kshops24.com/public_html';

// 실서버 폴더로 이동하여 main 브랜치의 최신 무결점 코드를 1초 만에 당겨옵니다.
$output = [];
$return_var = 0;
exec("cd {$target_dir} && git pull origin main 2>&1", $output, $return_var);

// 4. 배포 로그 기록 (디버깅용)
if ($return_var === 0) {
    echo "K-Shops24 배포 완료: \n" . implode("\n", $output);
} else {
    // 에러 발생 시 시스템 출력 로그 반환
    http_response_code(500);
    echo "배포 중 백엔드 리눅스 오류 발생: \n" . implode("\n", $output);
}