<?php

/**
 * KShops24 고객 카카오 로그인 콜백 핸들러
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// [보안 강화] 세션이 시작되지 않았다면 시작합니다.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = $_GET['code'] ?? '';
$subdomain = $_GET['state'] ?? ''; // shop_view에서 보낸 상점 식별자
$error = $_GET['error'] ?? ''; // 사용자가 취소 시 'access_denied' 값이 들어옴

// 1. 사용자가 동의창에서 '취소'를 눌렀을 경우 처리
if ($error === 'access_denied') {
    $redirect_url = $subdomain ? "/$subdomain" : "/index.php";
    echo "<script>alert('로그인이 취소되었습니다.'); location.href='$redirect_url';</script>";
    exit;
}

if (!$code || !$subdomain) {
    die("인증 정보가 부족합니다.");
}

// [보안 강화] 페이지 새로고침 등으로 동일한 인증 코드가 재사용되는 것을 방지합니다.
if (isset($_SESSION['processed_kakao_code']) && $_SESSION['processed_kakao_code'] === $code) {
    // 이미 처리된 코드이므로, 사용자를 상점 페이지로 안전하게 리다이렉트합니다.
    $redirect_url = "/" . $subdomain;
    header("Location: " . $redirect_url);
    exit;
}

$debug_error_msg = ""; // 상세 에러 메시지 저장용 변수

// 1. 상점 정보 확인
$stmt = $pdo->prepare("SELECT id FROM shops WHERE subdomain = ? LIMIT 1");
$stmt->execute([$subdomain]);
$shop = $stmt->fetch();
if (!$shop) die("상점 정보를 찾을 수 없습니다.");

// 2. Access Token 요청
$token_url = "https://kauth.kakao.com/oauth/token";
$params = [
    "grant_type" => "authorization_code",
    "client_id" => KAKAO_REST_API_KEY,
    "redirect_uri" => KAKAO_REDIRECT_URI,
    "code" => $code,
    "client_secret" => KAKAO_CLIENT_SECRET
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$token_data = json_decode($response, true);

// 2.1 토큰 요청 에러 발생 시 처리
if (isset($token_data['error'])) {
    $debug_error_msg = "Token Error: [" . $token_data['error'] . "] " . ($token_data['error_description'] ?? '');
    error_log("Kakao Login Error: " . $debug_error_msg);
}

if (isset($token_data['access_token'])) {
    // 3. 사용자 정보 요청
    $user_info_url = "https://kapi.kakao.com/v2/user/me";
    $opts = [
        CURLOPT_URL => $user_info_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $token_data['access_token']]
    ];
    curl_setopt_array($ch, $opts);
    $user_response = curl_exec($ch);
    $user_data = json_decode($user_response, true);
    curl_close($ch);

    // 사용자의 고유 ID가 정상적으로 수신되었는지 확인
    if (isset($user_data['id']) && !empty($user_data['id'])) {
        $kakao_id = $user_data['id'];
        $nickname = $user_data['kakao_account']['profile']['nickname'] ?? ($user_data['properties']['nickname'] ?? '고객');
        $profile_img = $user_data['kakao_account']['profile']['profile_image_url'] ?? ($user_data['properties']['profile_image'] ?? '');
        $email = $user_data['kakao_account']['email'] ?? '';

        // 4. DB 저장 (Upsert)
        // 4-1. 플랫폼 전역(Global) 고객 확인 및 추가/갱신
        $stmt_pc = $pdo->prepare("SELECT id FROM platform_customers WHERE kakao_id = ?");
        $stmt_pc->execute([$kakao_id]);
        $pc_row = $stmt_pc->fetch();

        if ($pc_row) {
            $platform_customer_id = $pc_row['id'];
            $pdo->prepare("UPDATE platform_customers SET nickname = ?, profile_img = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$nickname, $profile_img, $platform_customer_id]);
        } else {
            $pdo->prepare("INSERT INTO platform_customers (kakao_id, nickname, profile_img, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())")
                ->execute([$kakao_id, $nickname, $profile_img]);
            $platform_customer_id = $pdo->lastInsertId();
        }

        // 4-2. 상점-고객 매핑 추가 또는 갱신
        $sql_map = "INSERT INTO shop_customer_mapping (shop_id, customer_id, last_login_at, created_at) 
                    VALUES (?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE last_login_at = NOW()";
        $pdo->prepare($sql_map)->execute([$shop['id'], $platform_customer_id]);

        // 5. 고객용 세션 생성 (상점주 세션과 분리)
        $stmt_cust = $pdo->prepare("SELECT id, ph_phone, ph_address, ph_landmark FROM platform_customers WHERE id = ?");
        $stmt_cust->execute([$platform_customer_id]);
        $cust_row = $stmt_cust->fetch();

        $_SESSION['customer_id'] = $cust_row['id'];
        $_SESSION['customer_ph_phone'] = $cust_row['ph_phone'];
        $_SESSION['customer_ph_address'] = $cust_row['ph_address'];
        $_SESSION['customer_ph_landmark'] = $cust_row['ph_landmark'];
        $_SESSION['customer_shop_id'] = $shop['id'];
        $_SESSION['customer_nickname'] = $nickname;
        $_SESSION['customer_profile_img'] = $profile_img;

        // [보안 강화] 사용된 인증 코드를 세션에 기록하여 재사용을 차단합니다.
        $_SESSION['processed_kakao_code'] = $code;

        // [테스트 모듈 호출] 설정 파일의 ENABLE_TASK_TEST가 true일 때만 작동
        if (defined('ENABLE_TASK_TEST') && ENABLE_TASK_TEST === true) {
            $test_file_path = $_SERVER['DOCUMENT_ROOT'] . '/task_test.php';
            if (file_exists($test_file_path)) {
                include $test_file_path;
            }
        }

        // 상점 페이지로 복귀
        header("Location: /" . $subdomain . "?login_success=1");
        exit;
    }
}

// 최종 실패 시 상세 내역 표시
if (empty($debug_error_msg)) {
    if (isset($user_data['msg'])) {
        $debug_error_msg = "Profile Error: [" . ($user_data['code'] ?? 'unknown') . "] " . $user_data['msg'];
    } else {
        $debug_error_msg = "알 수 없는 인증 오류가 발생했습니다. (Token/User data 수신 실패)";
    }
}

error_log("Kakao Auth Final Failure: " . $debug_error_msg);
echo "<script>alert('카카오 로그인 연동 중 오류가 발생했습니다.\\n\\n상세에러: " . addslashes($debug_error_msg) . "\\n\\n카카오 디벨로퍼스 설정을 다시 확인해주세요.'); location.href='/$subdomain';</script>";
