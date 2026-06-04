<?php

/**
 * KShops24 점주 입점 신청 페이지 (register.php)
 * * 주요 기능:
 * 1. AJAX를 이용한 필드(이메일, 도메인 등) 실시간 중복 검사
 * 2. 신청 폼 데이터 수집 및 서버 측 최종 유효성 검증
 * 3. 비밀번호 해싱 및 상점 정보 DB 저장 (초기 상태: 'active'로 즉시 활성화)
 * 4. 카테고리 동적 로드 및 폼 데이터 유지(Sticky Form)
 * 
 * 입점신청 프로세스
 * 1. shop table에 입점신청 정보 저장
 * 2. shop_payments table에 구축비(무료), 4개월 사용료(무료), 6개월 사용료(유료) 정보 저장
 * 3. shop_board table에 상점주에게 보내는 입점 환영 쪽지(type:message) 저장
 * 4. 상점주에게 입점 축하 이메일 보내고, shop_board table에 이메일 보낸 내역 기록(type:email_log)하기
 * 5. 상점 전용 업로드 폴더 생성
 */

// URL 또는 POST 파라미터에 dev_test=1 이 있으면 보안(약관, 이메일 인증) 우회
$FOR_TEST = ((isset($_GET['dev_test']) && $_GET['dev_test'] == '1') || (isset($_POST['dev_test']) && $_POST['dev_test'] == '1')) ? "YES" : "NO";

require_once 'common/common_header.php'; // 공통 엔진 및 보안 로직 로드

// ---------------------------------------------------------
// [추가] 이메일 인증 AJAX 처리 (POST)
// ---------------------------------------------------------
// 1. 이메일 인증번호 발송
if (isset($_POST['ajax_send_auth_code'])) {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    
    if (!validateFieldFormat('manager_email', $email)) {
        echo json_encode(['status' => 'error', 'message' => '이메일 형식이 올바르지 않습니다.']);
        exit;
    }
    if (isDuplicateShopField($pdo, 'manager_email', $email)) {
        echo json_encode(['status' => 'error', 'message' => '이미 사용 중인 이메일입니다. 다른 이메일을 입력해주세요.']);
        exit;
    }
    
    // 6자리 난수 생성
    $auth_code = sprintf('%06d', mt_rand(0, 999999));
    $_SESSION['email_auth_code'] = $auth_code;
    $_SESSION['email_auth_timestamp'] = time(); // 인증번호 생성 시각 기록
    $_SESSION['email_auth_target'] = $email;
    
    // 이메일 발송
    $stmt_cs = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'cs_email'");
    $stmt_cs->execute();
    $cs_email = $stmt_cs->fetchColumn() ?: 'support@kshops24.com';
    $from_email = (strpos($cs_email, '@kshops24.com') !== false) ? $cs_email : 'support@kshops24.com';

    $subject = '=?UTF-8?B?' . base64_encode("[KShops24] 이메일 인증 번호 안내") . '?=';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: KShops24 <" . $from_email . ">\r\n";
    $headers .= "Reply-To: " . $cs_email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $content = "<!DOCTYPE html>
<html lang='ko'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin:0; padding:15px; background-color:#f4f7f9; font-family:\"Apple SD Gothic Neo\", \"Malgun Gothic\", sans-serif;'>
    <div style='width:100%; max-width:500px; margin:0 auto; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); text-align:center;'>
        <div style='padding:30px;'>
            <h3 style='color:#004aad; margin-top:0;'>이메일 인증 번호 안내</h3>
            <p style='color:#555;'>입점 신청을 위한 이메일 인증 번호입니다.</p>
            <div style='margin:20px 0; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; font-size:24px; font-weight:bold; letter-spacing:4px; color:#333; border-radius:6px;'>
                " . $auth_code . "
            </div>
            <p style='color:#777; font-size:13px; margin-bottom:0;'>본 인증번호를 신청 페이지에 입력해주세요.<br>(타인에게 노출하지 마세요)</p>
        </div>
    </div>
</body>
</html>";

    // 본문을 Base64로 인코딩 후 chunk 분할 (스팸/깨짐 방지)
    $encoded_content = chunk_split(base64_encode($content));

    if (@mail($email, $subject, $encoded_content, $headers)) {
        echo json_encode(['status' => 'success', 'message' => '인증 번호가 발송되었습니다. 메일함을 확인하시고, 유효시간 안에 입력해 주세요.']);
    } else {
        $error = error_get_last();
        $err_msg = $error ? $error['message'] : '서버 mail() 함수 실행 실패';
        error_log("Auth Email Send Fail: " . $err_msg);
        echo json_encode(['status' => 'error', 'message' => '이메일 발송에 실패했습니다. 관리자에게 문의해주세요.']);
    }
    exit;
}

// 2. 이메일 인증번호 확인
if (isset($_POST['ajax_verify_auth_code'])) {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    
    // [추가] 인증 유효시간(5분) 검사
    if (isset($_SESSION['email_auth_timestamp']) && (time() - $_SESSION['email_auth_timestamp']) > 300) {
        unset($_SESSION['email_auth_code'], $_SESSION['email_auth_target'], $_SESSION['email_auth_timestamp']);
        echo json_encode(['status' => 'error', 'message' => '인증 유효시간(5분)이 초과되었습니다. 인증번호를 다시 발송해주세요.']);
        exit;
    }

    if (isset($_SESSION['email_auth_code']) && isset($_SESSION['email_auth_target'])) {
        if ($_SESSION['email_auth_target'] === $email && $_SESSION['email_auth_code'] === $code) {
            $_SESSION['email_auth_verified'] = $email; // 최종 저장 시 검증을 위한 세션 기록
            // 인증 성공 시 사용된 세션 변수들 모두 제거
            unset($_SESSION['email_auth_code']);
            unset($_SESSION['email_auth_target']);
            unset($_SESSION['email_auth_timestamp']);
            echo json_encode(['status' => 'success', 'message' => '인증이 완료되었습니다. 계속 진행해 주세요.']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => '인증 번호가 일치하지 않거나 만료되었습니다.']);
    exit;
}

// ---------------------------------------------------------
// [섹션 1] AJAX 실시간 중복 체크 처리
// ---------------------------------------------------------
// 사용자가 입력창을 벗어날 때(blur) JS에서 호출하여 DB 중복 여부를 반환합니다.
if (isset($_GET['check_field']) && isset($_GET['value'])) {
    // [버그 수정] PHP 경고문이나 HTML이 응답에 섞여 JS 분기 처리를 방해하는 것을 원천 차단합니다.
    if (ob_get_level()) ob_clean();

    $field = $_GET['check_field'];
    $value = trim($_GET['value']);

    // 보안을 위한 허용 필드 화이트리스트 (SQL 인젝션 방지)
    $allowed_fields = [
        'manager_email' => '이메일',
        'subdomain' => '웹사이트 주소',
        'custom_domain' => '개별 도메인',
        'kakao_channel_id' => '카톡 채널 ID/URL',
        'shop_name' => '상점 이름(한글)',
        'shop_name_en' => '상점 이름(English)',
        'phone_mobile' => '모바일 번호',
        'phone_landline' => '랜드라인',
        'kakao_id' => '카카오톡 ID'
    ];

    if (array_key_exists($field, $allowed_fields) && $value !== '') {
        // [추가] 예약어 체크 (AJAX 전용 응답)
        if ($field === 'subdomain' && isReservedSubdomain($value)) {
            echo "reserved_word";
            exit;
        }

        // 1. 먼저 형식(언어 등)이 올바른지 체크
        if (!validateFieldFormat($field, $value)) {
            echo "invalid_format";
            exit;
        }

        // lib_utils.php의 공통 함수 사용
        $is_dup = isDuplicateShopField($pdo, $field, $value);
        echo $is_dup ? "duplicate" : "available";
    }
    exit; // AJAX 요청인 경우 이후 HTML 출력을 막기 위해 종료
}

// ---------------------------------------------------------
// [섹션 2] 변수 초기화 및 폼 데이터 유지 설정
// ---------------------------------------------------------
$message = "";
$form_data = [
    'email' => '',
    'manager_name' => '',
    'manager_name_en' => '',
    'shop_name' => '',
    'shop_name_en' => '',
    'phone_mobile' => '',
    'phone_landline' => '',
    'kakao_id' => '',
    'kakao_channel_id' => '',
    'subdomain' => '',
    'custom_domain' => '',
    'category' => $_REQUEST['category'] ?? 'fnb' // sel_category.php에서 넘어온 값
];

// pre_register.php에서 넘어온 계약 비용 정보 (없을 경우 기본값 적용)
$setup_fee = $_REQUEST['setup_fee'] ?? '상담 후 결정';
$monthly_fee = $_REQUEST['monthly_fee'] ?? '상담 후 결정';

// ---------------------------------------------------------
// [섹션 3] 가입 신청(POST) 데이터 처리 및 DB 저장
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_registration'])) {
    // 1. 입력 데이터 바인딩 및 공백 제거
    foreach ($form_data as $key => $val) {
        if (isset($_POST[$key])) $form_data[$key] = trim($_POST[$key]);
    }

    // [추가] 카카오 ID, 채널 ID, 서브도메인은 무조건 소문자로 변환하여 저장
    $form_data['kakao_id'] = strtolower($form_data['kakao_id']);
    $form_data['kakao_channel_id'] = strtolower($form_data['kakao_channel_id']);
    $form_data['subdomain'] = strtolower($form_data['subdomain']);

    try {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        // 2. 서버 측 최종 검증 (isDuplicateShopField 공통 함수로 통합 관리)
        if ($FOR_TEST !== "YES" && (!isset($_SESSION['email_auth_verified']) || $_SESSION['email_auth_verified'] !== $form_data['email'])) {
            $message = "<div class='alert alert-danger'>이메일 인증이 완료되지 않았습니다.</div>";
        } else if (isDuplicateShopField($pdo, 'manager_email', $form_data['email'])) {
            $message = "<div class='alert alert-danger'>이미 사용 중인 관리자 이메일입니다.</div>";
        } else if (isDuplicateShopField($pdo, 'subdomain', $form_data['subdomain'])) {
            $message = "<div class='alert alert-danger'>이미 사용 중인 상점 아이디(주소)입니다.</div>";
        } else if (isDuplicateShopField($pdo, 'shop_name', $form_data['shop_name'])) {
            $message = "<div class='alert alert-danger'>이미 등록된 상점 이름(한글)입니다.</div>";
        } else if (isDuplicateShopField($pdo, 'shop_name_en', $form_data['shop_name_en'])) {
            $message = "<div class='alert alert-danger'>이미 등록된 상점 이름(English)입니다. (대소문자 구분 없음)</div>";
        } else if (isDuplicateShopField($pdo, 'phone_mobile', $form_data['phone_mobile'])) {
            $message = "<div class='alert alert-danger'>이미 등록된 모바일 번호입니다.</div>";
        } else if (isDuplicateShopField($pdo, 'kakao_id', $form_data['kakao_id'])) {
            $message = "<div class='alert alert-danger'>이미 등록된 카카오톡 ID입니다.</div>";
        } else if (!empty($form_data['kakao_channel_id']) && isDuplicateShopField($pdo, 'kakao_channel_id', $form_data['kakao_channel_id'])) {
            $message = "<div class='alert alert-danger'>이미 등록된 카톡 채널 정보입니다.</div>";
        } else if (!empty($password) && !isValidPassword($password)) {
            // 값이 있을 때만 복잡성 체크 (빈 값은 브라우저 required가 처리)
            $message = "<div class='alert alert-danger'>비밀번호는 영문 대문자, 소문자, 숫자 조합으로 6자 이상이어야 합니다.</div>";
        } else if ($password !== $password_confirm) {
            $message = "<div class='alert alert-danger'>비밀번호가 일치하지 않습니다.</div>";
        } else if (!isValidKorean($form_data['manager_name']) || !isValidKorean($form_data['shop_name'])) {
            $message = "<div class='alert alert-danger'>이름 및 상점 이름(한글) 필드 형식이 올바르지 않습니다. (한글, 숫자, 특수문자 허용)</div>";
        } else if (!isValidEnglish($form_data['manager_name_en']) || !isValidEnglish($form_data['shop_name_en'])) {
            $message = "<div class='alert alert-danger'>이름 및 상점 이름(English) 필드 형식이 올바르지 않습니다. (영문, 숫자, 특수문자 허용)</div>";
        } else if (!isValidSubdomainFormat($form_data['subdomain'])) {
            $message = "<div class='alert alert-danger'>상점 아이디는 영문 소문자와 숫자만 가능합니다.</div>";
        } else if (isReservedSubdomain($form_data['subdomain'])) {
            $message = "<div class='alert alert-danger'>'{$form_data['subdomain']}'은 시스템 예약어로 사용할 수 없는 아이디입니다.</div>";
        } else {

            // 3. 비밀번호 단방향 암호화
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

            // [개선] 모든 DB 저장 로직을 하나의 트랜잭션으로 묶어 데이터 일관성을 보장합니다.
            $pdo->beginTransaction();

            $sql = "INSERT INTO shops (
                manager_email, manager_password, manager_name, manager_name_en, 
                shop_name, shop_name_en, phone_mobile, phone_landline, 
                kakao_id, kakao_channel_id, subdomain, custom_domain, 
                status, category
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $form_data['email'],
                $hashed_pass,
                $form_data['manager_name'],
                $form_data['manager_name_en'],
                $form_data['shop_name'],
                $form_data['shop_name_en'],
                $form_data['phone_mobile'],
                $form_data['phone_landline'] === '' ? null : $form_data['phone_landline'],
                $form_data['kakao_id'],
                $form_data['kakao_channel_id'] === '' ? null : $form_data['kakao_channel_id'],
                $form_data['subdomain'],
                $form_data['custom_domain'] === '' ? null : $form_data['custom_domain'],
                $form_data['category']
            ]);

            if (!$result) {
                throw new Exception('상점 정보 저장에 실패했습니다.');
            }

            // [프로세스 1] shops table 상점 정보 넣기는 위 $stmt->execute()에서 완료됨.
            $new_shop_id = $pdo->lastInsertId();

            // [프로세스 2] shop_payments table 결제 내역 3종 세트 자동 저장
            $stmt_settings = $pdo->query("SELECT set_key, set_value FROM site_settings WHERE set_key IN ('setup_fee', 'monthly_fee')");
            $billing_settings = array_column($stmt_settings->fetchAll(), 'set_value', 'set_key');
            $calc_setup_fee = (int)($billing_settings['setup_fee'] ?? 0);
            $calc_monthly_fee = (int)($billing_settings['monthly_fee'] ?? 0);

            $payment_ok = true;
            $current_billing_date = new DateTime();

            // 2-1. 구축비(가입비) 처리
            $is_setup_free = (defined('SETUP_FREE') && SETUP_FREE === 'y');
            $payment_ok = $payment_ok && recordShopPayment(
                $pdo,
                $new_shop_id,
                PAY_TYPE_SETUP,
                $is_setup_free ? 0 : $calc_setup_fee,
                "구축비 1회 청구" . ($is_setup_free ? " (무료 프로모션)" : ""),
                $is_setup_free ? 'f' : 'n',
                $current_billing_date->format('Y-m-d'),
                $current_billing_date->format('Y-m-d')
            );

            // 2-2. 4개월 무료 사용료 처리
            if (defined('4MONTHS_FREE') && constant('4MONTHS_FREE') === 'y') {
                $free_period_end_date = (clone $current_billing_date)->modify('+4 months');
                $payment_ok = $payment_ok && recordShopPayment(
                    $pdo,
                    $new_shop_id,
                    PAY_TYPE_4MONTHS_FREE,
                    0,
                    "4개월 사용료 (무료 프로모션)",
                    'f',
                    $current_billing_date->format('Y-m-d'),
                    $free_period_end_date->format('Y-m-d')
                );
                $current_billing_date = (clone $free_period_end_date)->modify('+1 day');
            }

            // 2-3. 6개월 유지비 내역 (신규 공통 함수 사용)
            if (function_exists('add6MonthBill')) {
                $payment_ok = $payment_ok && add6MonthBill($pdo, $new_shop_id, $current_billing_date->format('Y-m-d'));
            }

            if (!$payment_ok) {
                throw new Exception('결제 내역(6개월)을 저장하는 중 오류가 발생했습니다.');
            }

            // [프로세스 3] shop_board table 입점 환영 메시지 발송
            $welcome_title = "KShops24 입점을 진심으로 환영합니다!";
            $welcome_content = "사장님, 안녕하세요!\n\nKShops24와 함께하게 되신 것을 축하드립니다.\n이제 상점 관리 페이지에서 메뉴 등록, 디자인 수정 등 모든 기능을 바로 이용하실 수 있습니다.\n\n궁금한 점이 있으시면 언제든지 이 메시지에 답장해주세요.";
            $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content, is_secret, created_at) VALUES (?, 'message', 'admin', ?, ?, 1, NOW())")
                ->execute([$new_shop_id, $welcome_title, $welcome_content]);

            // 트랜잭션 커밋 (모든 DB 저장이 성공적으로 완료됨)
            $pdo->commit();

            // === DB 연산 종료 ===

            // [프로세스 4] 상점주에게 입점 완료 안내 메일 발송 및 기록 ('active' 템플릿 사용)
            $email_result = false;
            if (function_exists('sendShopEmail')) {
                try {
                    // 카테고리 한글명 가져오기
                    $cate_name = $form_data['category'];
                    $stmt_c = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
                    $stmt_c->execute();
                    $json_cats = $stmt_c->fetchColumn();
                    $cat_array = $json_cats ? json_decode($json_cats, true) : ['fnb' => '외식/배달', 'realty' => '부동산 / 중개', 'serv' => '예약 / 서비스'];
                    if (isset($cat_array[$form_data['category']])) {
                        $cate_name = $cat_array[$form_data['category']];
                    }

                    // [추가] DB에서 이용약관 내용 가져오기 (템플릿 치환용)
                    $stmt_terms = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'terms_of_use'");
                    $terms_of_use_raw = $stmt_terms->fetchColumn() ?: '이용 약관이 설정되지 않았습니다.';

                    // [개선] 이메일 본문이 너무 길어지는 것을 방지하기 위해 내용 요약 및 링크 추가
                    $terms_plain = strip_tags(html_entity_decode($terms_of_use_raw, ENT_QUOTES, 'UTF-8'));
                    if (mb_strlen($terms_plain, 'UTF-8') > 150) {
                        $terms_of_use = mb_substr($terms_plain, 0, 150, 'UTF-8') . '... <br><br><a href="https://kshops24.com/common/terms_of_use.php" target="_blank" style="display:inline-block; padding:10px 20px; background-color:#f4f7f9; color:#004aad; border:1px solid #e2e8f0; text-decoration:none; border-radius:6px; font-weight:bold; font-size:13px;">이용 약관 자세히 보기 →</a>';
                    } else {
                        $terms_of_use = $terms_of_use_raw;
                    }

                    $email_result = sendShopEmail($pdo, $form_data['email'], 'active', [
                        // 기존 변수명 (하위 호환성 유지)
                        'manager_name' => $form_data['manager_name'],
                        'shop_name'    => $form_data['shop_name'],
                        'subdomain'    => $form_data['subdomain'],
                        'setup_fee'    => $setup_fee,
                        'monthly_fee'  => $monthly_fee,
                        'shop_name_en' => $form_data['shop_name_en'],
                        'manager_email' => $form_data['email'],
                        'phone_mobile' => $form_data['phone_mobile'],
                        'category'     => $cate_name,
                        'apply_date'   => date('Y-m-d H:i:s'),

                        // 신규 템플릿 변수명 매핑 ({{테이블명:컬럼명}} 형태)
                        'shops:shop_name'            => $form_data['shop_name'],
                        'shops:shop_name_en'         => $form_data['shop_name_en'],
                        'shops:subdomain'            => $form_data['subdomain'],
                        'shops:manager_email'        => $form_data['email'],
                        'shops:phone_mobile'         => $form_data['phone_mobile'],
                        'shops:category'             => $cate_name,
                        'shops:apply_date'           => date('Y-m-d H:i:s'),
                        'site_settings:terms_of_use' => $terms_of_use
                    ]);
                } catch (Exception $emailEx) {
                    error_log("Email send failed during registration for shop_id: {$new_shop_id}. Error: " . $emailEx->getMessage());
                    $email_result = false;
                }
            }

            // [수정] 이메일 발송 성공/실패 여부를 판단하여 shop_board 에 기록
            if ($email_result === true) {
                $email_subject = "[KShops24] 상점이 정식으로 오픈되었습니다!";
                $email_content_log = "신규 입점 완료 안내 이메일 발송 완료\n수신자: " . $form_data['email'];
            } else {
                $email_subject = "[발송 실패] 상점 오픈 안내 메일";
                $fail_reason = is_string($email_result) ? $email_result : "알 수 없는 오류";
                $email_content_log = "신규 입점 완료 안내 이메일 발송 실패\n수신자: " . $form_data['email'] . "\n사유: " . $fail_reason;
            }

            addShopHistoryLog($pdo, $new_shop_id, SHOP_HISTORY_EMAIL, $email_subject, $email_content_log);

            // [프로세스 5] 상점 전용 업로드 폴더 생성 (Hostinger 서버 물리 폴더 생성)
            $shop_upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $form_data['subdomain'];
            if (!is_dir($shop_upload_dir)) {
                mkdir($shop_upload_dir, 0755, true);
            }

            // [보안] 성공 페이지 접근을 위한 일회성 플래그 생성
            $_SESSION['reg_complete_id'] = $form_data['subdomain'];

            // 신청 성공 시 성공 페이지로 이동 (ID 파라미터 전달)
            header("Location: register_success.php?id=" . urlencode($form_data['subdomain']));
            exit;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // [보강] DB 저장 중 오류 발생 시 상세 내용을 로그 파일에 기록
        $log_file = $_SERVER['DOCUMENT_ROOT'] . '/register_db_errors.log';
        $log_entry = "================================================================\n";
        $log_entry .= "[" . date('Y-m-d H:i:s') . "] 입점 신청 DB 처리 오류 발생\n";
        $log_entry .= "에러 메시지: " . $e->getMessage() . "\n";
        $log_entry .= "에러 코드: " . $e->getCode() . "\n";
        $log_entry .= "입력 데이터: " . json_encode($form_data, JSON_UNESCAPED_UNICODE) . "\n";
        $log_entry .= "디버그 스택: \n" . $e->getTraceAsString() . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);

        $message = "<div class='alert alert-danger'>
            <strong>시스템 오류가 발생하여 신청을 완료하지 못했습니다.</strong><br>
            관리자에게 문의해 주세요. (사유: " . htmlspecialchars($e->getMessage()) . ")<br>
            <small>상세 로그는 서버의 'register_db_errors.log' 파일에 기록되었습니다.</small>
        </div>";
    }
}

// ---------------------------------------------------------
// [섹션 4] 카테고리 목록 로드 (Select Box용)
// ---------------------------------------------------------
$stmt_cate = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
$json_cats = $stmt_cate->fetchColumn();
$categories = $json_cats ? json_decode($json_cats, true) : [
    'fnb' => '외식/배달',
    'realty' => '부동산/중개',
    'srv' => '예약/서비스'
];
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KShops24 - 입점 신청</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* UI 스타일링: 가독성을 높인 카드형 레이아웃 */
        body {
            background: linear-gradient(180deg, #f4f7f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            font-family: 'Pretendard', sans-serif;
        }

        .reg-card {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 45px;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .form-label {
            font-weight: bold;
            color: #444;
            font-size: 0.9rem;
        }

        /* [수정] 섹션별 그라데이션 헤더 디자인 */
        .section-title {
            font-size: 1.1rem;
            color: #004aad;
            font-weight: bold;
            border-left: 5px solid #004aad;
            padding: 12px 15px;
            margin: 45px 0 25px 0;
            background: linear-gradient(90deg, rgba(0, 74, 173, 0.05) 0%, rgba(255, 255, 255, 0) 100%);
            border-radius: 0 10px 10px 0;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        /* [추가] 입력 필드 포커스 시 배경색 변경 (연한 노란색 강조) */
        .form-control:focus,
        .form-select:focus {
            background-color: #fffde7 !important;
            /* 연한 노란색 */
            border-color: #004aad !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 74, 173, 0.15) !important;
            transition: background-color 0.2s ease-in-out;
        }

        /* 유효성 검사 성공 시 녹색 테두리 */
        .is-valid {
            border-color: #198754 !important;
        }

        /* [Mobile-First] 모바일 화면 최적화: 좌우 여백 최소화 */
        @media (max-width: 576px) {
            .reg-card {
                margin: 15px 5px;
                padding: 25px 15px;
                border-radius: 15px;
            }

            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .section-title {
                font-size: 1rem;
                margin: 30px 0 15px 0;
            }

            .form-label {
                font-size: 0.85rem;
            }

            .text-muted {
                font-size: 0.8rem;
            }

            .btn-primary {
                font-size: 1rem;
                padding: 12px;
            }

            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="reg-card">
            <div class="text-center mb-5">
                <a href="index.php" style="text-decoration: none;">
                    <h2 class="fw-bold" style="color:#004aad;">KShops24</h2>
                </a>             
                <p class="text-muted">사장님의 비즈니스 성장을 위한 입점 신청을 시작합니다<a
                        href="https://kshops24.com/register.php?dev_test=1">.</a></p>
            </div>

            <?php if (isset($_GET['dev_test']) && $_GET['dev_test'] == '1'): ?>
                <!-- 개발자/테스트 전용 더미 데이터 입력 버튼 (평소엔 숨김) -->
                <div class="alert alert-warning text-center p-2 mb-4 shadow-sm border-warning rounded-3">
                    <button type="button" class="btn btn-sm btn-dark fw-bold px-4 rounded-pill" onclick="fillTestData()"><i
                            class="bi bi-magic me-1"></i> 테스트 데이터 1초 완성</button>
                </div>
            <?php endif; ?>

            <?php if ($message) echo $message; ?>

            <form method="POST" id="regForm">
                <input type="hidden" name="setup_fee" value="<?php echo htmlspecialchars($setup_fee); ?>">
                <input type="hidden" name="monthly_fee" value="<?php echo htmlspecialchars($monthly_fee); ?>">
                <input type="hidden" name="category" id="category" value="<?php echo htmlspecialchars($form_data['category']); ?>">
                <?php if ($FOR_TEST === "YES") echo '<input type="hidden" name="dev_test" value="1">'; ?>

                <div class="section-title">1. 계정 정보 <span class="text-danger">(필수)</span></div>
                <div class="mb-3">
                    <label class="form-label">관리자 이메일 (로그인 ID) : <span class="text-danger">본인이 주로 사용하는 이메일을
                            입력해주세요. 이 이메일로 인증 번호가 발송됩니다.</span></label>
                    <div class="input-group mb-2">
                        <!-- 기존 check-unique 클래스를 제거하여 이메일 인증 전용 프로세스로 처리합니다 -->
                        <input type="email" id="manager_email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['email']); ?>"
                            placeholder="example@gmail.com" required>
                        <button type="button" class="btn btn-outline-primary fw-bold" id="btn-send-auth">인증번호 발송</button>
                    </div>
                    <div class="input-group d-none" id="auth-code-group">
                        <input type="text" id="email_auth_code" class="form-control" placeholder="인증번호 6자리 입력" maxlength="6">
                        <span class="input-group-text d-none" id="auth-timer-box">
                            <i class="bi bi-clock me-1"></i><span id="auth-timer">05:00</span>
                        </span>
                        <button type="button" class="btn btn-primary fw-bold" id="btn-verify-auth">인증확인</button>
                    </div>
                    <div id="email-auth-msg" class="small mt-1 d-none"></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">비밀번호 <span class="text-muted fw-normal small">(6자 이상, 대/소문자+숫자
                                조합)</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="대문자+소문자+숫자 조합 6자 이상" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                                onclick="togglePasswordVisibility('password', 'togglePasswordIcon')">
                                <i class="bi bi-eye-slash-fill" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="form-text mt-1" style="font-size: 0.75rem; color: #666;">
                            <i class="bi bi-info-circle me-1"></i>보안을 위해 대문자, 소문자, 숫자를 모두 섞어서 만들어주세요.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">비밀번호 확인</label>
                        <div class="input-group">
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                                required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePasswordConfirm"
                                onclick="togglePasswordVisibility('password_confirm', 'togglePasswordConfirmIcon')">
                                <i class="bi bi-eye-slash-fill" id="togglePasswordConfirmIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="section-title">2. 점주 및 상점명 <span class="text-danger">(필수)</span></div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">점주 이름 (한글)</label><input type="text"
                            id="manager_name" name="manager_name" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['manager_name']); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">점주 이름 (English)</label><input type="text"
                            id="manager_name_en" name="manager_name_en" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['manager_name_en']); ?>" required></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">상점 이름 (한글)</label>
                        <div class="input-group">
                            <input type="text" id="shop_name" name="shop_name" class="form-control check-unique"
                                data-field="shop_name" value="<?php echo htmlspecialchars($form_data['shop_name']); ?>"
                                required>
                            <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                                data-target="shop_name">중복확인</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mt-3 mt-md-0">상점 이름 (English)</label>
                        <div class="input-group">
                            <input type="text" id="shop_name_en" name="shop_name_en" class="form-control check-unique"
                                data-field="shop_name_en"
                                value="<?php echo htmlspecialchars($form_data['shop_name_en']); ?>" required>
                            <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                                data-target="shop_name_en">중복확인</button>
                        </div>
                    </div>
                </div>

                <div class="section-title">3. 연락처 및 카카오톡</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">모바일 번호 <span class="text-danger">(필수)</span></label>
                        <div class="input-group">
                            <input type="tel" id="phone_mobile" name="phone_mobile" class="form-control check-unique"
                                data-field="phone_mobile" placeholder="0917-123-4567"
                                value="<?php echo htmlspecialchars($form_data['phone_mobile']); ?>" maxlength="13"
                                oninput="formatPhoneInput(this)" required title="09로 시작하는 11자리 필리핀 모바일 번호를 입력해주세요.">
                            <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                                data-target="phone_mobile">중복확인</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mt-3 mt-md-0">랜드라인 (선택)</label>
                        <div class="input-group">
                            <input type="tel" id="phone_landline" name="phone_landline"
                                class="form-control check-unique" data-field="phone_landline"
                                placeholder="02-123-4567 or 043-123-4567"
                                value="<?php echo htmlspecialchars($form_data['phone_landline']); ?>" maxlength="12"
                                oninput="formatPhoneInput(this)" title="필리핀 유선 전화번호를 입력해주세요. (예: 02-123-4567)">
                            <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                                data-target="phone_landline">중복확인</button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">개인 혹은 상점 카카오톡 ID <span class="text-danger">(필수)</span></label>
                    <div class="input-group">
                        <input type="text" id="kakao_id" name="kakao_id" class="form-control check-unique"
                            data-field="kakao_id" value="<?php echo htmlspecialchars($form_data['kakao_id']); ?>"
                            placeholder="개인 혹은 상점 카카오톡 ID 입력">
                        <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                            data-target="kakao_id">중복확인</button>
                    </div>
                </div>

                <!-- [추가] 카톡 채널 ID/URL 입력란 -->
                <!--
                <div class="mb-3">
                    <label class="form-label">카톡 비즈니스 채널 아이디 혹은 URL (선택)</label>
                    <input type="text" id="kakao_channel_id" name="kakao_channel_id" class="form-control check-unique"
                        data-field="kakao_channel_id" value="<?php echo htmlspecialchars($form_data['kakao_channel_id']); ?>"
                        placeholder="예: KShops24 또는 http://pf.kakao.com/_sZczX">
                    <div class="mt-2 bg-light p-2 rounded border small">
                        <i class="bi bi-info-circle-fill text-primary"></i> 카카오 채널이 없는 경우 빈칸으로 두세요. 나중에 관리자 페이지에서 추가 가능합니다.
                    </div>
                </div>
                -->

                <div class="section-title">4. 상점 아이디(상점 웹사이트 주소 설정) <span class="text-danger">(필수)</span></div>
                <div class="mb-3">
                    <label class="form-label text-danger small">신청 후 수정이 불가능하니, 신중히 선택해 주세요 !!!</label>
                    <div class="mt-2 bg-light p-2 rounded border small mb-3">
                        <i class="bi bi-info-circle-fill text-primary"></i> 향후 상점 웹사이트 주소는 "<span
                            class="text-danger fw-bold">https://kshops24.com/상점 아이디</span>" 형태가 됩니다. <div
                            class="mt-2 bg-light p-2 rounded border small">예: https://kshops24.com/<span
                                class="text-danger fw-bold">sunrisecoffee</span></div>
                    </div>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text text-muted">kshops24.com /</span>
                        <input type="text" id="subdomain" name="subdomain" class="form-control check-unique"
                            data-field="subdomain" value="<?php echo htmlspecialchars($form_data['subdomain']); ?>"
                            placeholder="영문 소문자 및 숫자 조합 가능" required>
                        <button type="button" class="btn btn-outline-primary btn-check-dup fw-bold"
                            data-target="subdomain">중복확인</button>
                    </div>
                </div>

                <!-- 6. 개별 도메인 (선택)은 주석처리 -->
                <!--
                <div class="section-title">6. 개별 도메인 (선택)</div>
                <div class="mb-4">
                    <input type="text" name="custom_domain" class="form-control check-unique"
                        data-field="custom_domain" value="<?php echo htmlspecialchars($form_data['custom_domain']); ?>"
                        placeholder="www.yourdomain.com">
                    <div class="mt-2 bg-light p-2 rounded border small">
                        <i class="bi bi-info-circle-fill text-primary"></i> 향후 점주가 자체적으로 구매한 도메인을 연결할 수 있는 옵션입니다. 예: www.yourdomain.com (입점 신청 시 설정한 서브도메인과는 별도로, 자체 도메인 연결을 원하는 경우 입력하세요.)
                    </div>
                </div>
                -->

                <button type="submit" name="submit_registration" id="submitBtn"
                    class="btn btn-primary w-100 py-3 fw-bold shadow mt-4" disabled>입점 신청 완료하기</button>
                <div class="mt-2 bg-light p-2 rounded border small">
                    <i class="bi bi-info-circle-fill text-primary"></i> 모든 정보를 입력해야 "입점 신청 완료하기" 버튼이 활성화됩니다.
                </div>

            </form>
        </div>
    </div>

    <script>
        // [JS 섹션 1] 실시간 중복 체크 상태 관리
        let validationStatus = {
            category: true, // 이전 페이지에서 설정되어 넘어옴
            manager_email: false,
            kakao_channel_id: true,
            subdomain: false, // 필수 항목이므로 초기값 false
            custom_domain: true,
            shop_name: false,
            shop_name_en: false,
            phone_mobile: false, // 필수 항목이므로 초기값 false
            phone_landline: true,
            kakao_id: false, // 필수 항목이므로 초기값 false
            password: false, // 필수 입력이므로 초기값 false
            password_confirm: false, // 필수 입력이므로 초기값 false
            manager_name: false,
            manager_name_en: false
        };

        // 사용자가 직접 입력을 시작했는지 여부를 체크하는 플래그
        let isPasswordTyped = false;
        let isConfirmTyped = false;

        // [추가] 이메일 인증 타이머 로직
        let emailAuthTimer;
        function startAuthTimer() {
            if (emailAuthTimer) clearInterval(emailAuthTimer);

            const timerDisplay = document.getElementById('auth-timer');
            const timerBox = document.getElementById('auth-timer-box');
            const verifyBtn = document.getElementById('btn-verify-auth');
            
            timerBox.classList.remove('d-none');
            verifyBtn.disabled = false;
            
            let timeLeft = 300; // 5분 (초 단위)

            emailAuthTimer = setInterval(() => {
                timeLeft--;

                const minutes = String(Math.floor(timeLeft / 60)).padStart(2, '0');
                const seconds = String(timeLeft % 60).padStart(2, '0');
                timerDisplay.textContent = `${minutes}:${seconds}`;

                if (timeLeft <= 0) {
                    clearInterval(emailAuthTimer);
                    timerDisplay.textContent = "00:00";
                    verifyBtn.disabled = true;
                    
                    const msgDiv = document.getElementById('email-auth-msg');
                    msgDiv.className = 'small mt-1 text-danger fw-bold';
                    msgDiv.innerHTML = '<i class="bi bi-x-circle"></i> 인증 유효시간이 만료되었습니다. [재발송] 버튼을 눌러주세요.';
                    msgDiv.classList.remove('d-none');

                    alert('인증 유효시간이 만료되었습니다. 인증번호를 다시 발송해주세요.');
                }
            }, 1000);
        }

        // [추가] 이메일 인증 발송 로직
        document.getElementById('btn-send-auth').addEventListener('click', function() {
            const emailInput = document.getElementById('manager_email');
            const email = emailInput.value.trim();
            const btn = this;
            const authGroup = document.getElementById('auth-code-group');
            const msgDiv = document.getElementById('email-auth-msg');

            if (!email) {
                alert('이메일을 입력해 주세요.');
                emailInput.focus();
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('이메일 형식이 올바르지 않습니다.');
                emailInput.focus();
                return;
            }

            const originalText = btn.innerText;
            btn.innerText = '발송중...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('ajax_send_auth_code', '1');
            formData.append('email', email);

            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    startAuthTimer(); // 타이머 시작
                    authGroup.classList.remove('d-none');
                    document.getElementById('email_auth_code').focus();
                    btn.innerText = '재발송';
                    btn.disabled = false;
                    
                    msgDiv.className = 'small mt-1 text-success fw-bold';
                    msgDiv.innerHTML = '<i class="bi bi-info-circle"></i> 인증번호가 발송되었습니다. 이메일을 확인해 주세요.';
                    msgDiv.classList.remove('d-none');
                } else {
                    alert(data.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                    emailInput.classList.add('is-invalid');
                }
            })
            .catch(err => {
                alert('서버 통신 중 오류가 발생했습니다.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        });

        // [추가] 이메일 인증 확인 로직
        document.getElementById('btn-verify-auth').addEventListener('click', function() {
            const email = document.getElementById('manager_email').value.trim();
            const codeInput = document.getElementById('email_auth_code');
            const code = codeInput.value.trim();
            const btn = this;
            const msgDiv = document.getElementById('email-auth-msg');

            if (!code) {
                alert('인증번호를 입력해 주세요.');
                codeInput.focus();
                return;
            }

            const originalText = btn.innerText;
            btn.innerText = '확인중...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('ajax_verify_auth_code', '1');
            formData.append('email', email);
            formData.append('code', code);

            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    if (emailAuthTimer) clearInterval(emailAuthTimer); // 타이머 중지
                    document.getElementById('auth-timer-box').classList.add('d-none');

                    document.getElementById('manager_email').readOnly = true;
                    document.getElementById('btn-send-auth').disabled = true;
                    codeInput.readOnly = true;
                    btn.innerText = '인증완료';
                    btn.classList.replace('btn-primary', 'btn-success');
                    
                    msgDiv.className = 'small mt-1 text-success fw-bold';
                    msgDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> 이메일 인증이 완료되었습니다.';
                    
                    validationStatus['manager_email'] = true;
                    checkFormValidity();
                } else {
                    alert(data.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                    codeInput.value = '';
                    codeInput.focus();
                }
            })
            .catch(err => {
                alert('서버 통신 중 오류가 발생했습니다.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        });
        
        // 이메일 입력값 변경 시 상태 초기화
        document.getElementById('manager_email').addEventListener('input', function() {
            validationStatus['manager_email'] = false;
            if (emailAuthTimer) clearInterval(emailAuthTimer); // 타이머 중지
            document.getElementById('auth-timer-box').classList.add('d-none');

            const authGroup = document.getElementById('auth-code-group');
            authGroup.classList.add('d-none');
            document.getElementById('email_auth_code').value = '';
            
            const btnSend = document.getElementById('btn-send-auth');
            btnSend.innerText = '인증번호 발송';
            btnSend.disabled = false;
            
            const msgDiv = document.getElementById('email-auth-msg');
            msgDiv.classList.add('d-none');
            
            const btnVerify = document.getElementById('btn-verify-auth');
            btnVerify.innerText = '인증확인';
            btnVerify.classList.replace('btn-success', 'btn-primary');
            btnVerify.disabled = false;
            document.getElementById('email_auth_code').readOnly = false;
            
            checkFormValidity();
        });

        // [JS 섹션 2] 명시적 버튼 클릭 중복 검사 (AJAX)
        document.querySelectorAll('.btn-check-dup').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                const field = targetInput.getAttribute('data-field');
                const value = targetInput.value.trim();

                // 선택 필드이고 비어있으면 굳이 서버 통신 안 하고 통과
                const mandatoryCheck = ['manager_email', 'shop_name', 'shop_name_en', 'phone_mobile',
                    'kakao_id', 'subdomain'
                ];
                if (value === '') {
                    if (mandatoryCheck.includes(field)) {
                        alert(getFieldName(field) + ' 값을 입력해 주세요.');
                        targetInput.focus();
                    } else {
                        alert('선택 항목이 비어 있어 중복 확인을 생략합니다.');
                        validationStatus[field] = true;
                        this.innerText = '확인완료';
                        this.classList.replace('btn-outline-primary', 'btn-success');
                        this.classList.add('text-white');
                        targetInput.classList.remove('is-invalid');
                        checkFormValidity();
                    }
                    return;
                }

                // 버튼 상태 변경 (로딩 중)
                const originalText = this.innerText;
                this.innerText = '확인중...';
                this.disabled = true;

                fetch(`register.php?check_field=${field}&value=${encodeURIComponent(value)}`)
                    .then(res => res.text())
                    .then(raw_data => {
                        const data = raw_data.trim();
                        if (data === 'reserved_word') {
                            alert(`'${value}'는 시스템 예약어로 사용할 수 없습니다.`);
                            validationStatus[field] = false;
                            targetInput.classList.remove('is-valid');
                            targetInput.classList.add('is-invalid');
                            this.innerText = '중복확인';
                            this.disabled = false;
                            targetInput.value = '';
                            setTimeout(() => targetInput.focus(), 10);
                        } else if (data === 'invalid_format') {
                            alert(`${getFieldName(field)}의 형식이 올바르지 않습니다.`);
                            validationStatus[field] = false;
                            targetInput.classList.remove('is-valid');
                            targetInput.classList.add('is-invalid');
                            this.innerText = '중복확인';
                            this.disabled = false;
                            targetInput.value = '';
                            setTimeout(() => targetInput.focus(), 10);
                        } else if (data === 'duplicate') {
                            alert(`이미 사용 중인 정보이니, 다른 값을 넣어주세요. (${getFieldName(field)})`);
                            validationStatus[field] = false;
                            targetInput.classList.remove('is-valid');
                            targetInput.classList.add('is-invalid');
                            this.innerText = '중복확인';
                            this.disabled = false;
                            targetInput.value = '';
                            setTimeout(() => targetInput.focus(), 10);
                        } else if (data === 'available') {
                            alert(`사용 가능한 정보입니다.`);
                            validationStatus[field] = true;
                            targetInput.classList.remove('is-invalid');
                            targetInput.classList.add('is-valid');
                            this.innerText = '확인완료';
                            this.classList.replace('btn-outline-primary', 'btn-success');
                            this.classList.add('text-white');
                            // disabled는 값이 변경될 때 풀림
                        } else {
                            alert('서버 응답에 문제가 있습니다. (개발자 도구를 확인해 주세요)');
                            console.error("AJAX 응답 오류:", data);
                            validationStatus[field] = false;
                            targetInput.classList.add('is-invalid');
                            this.innerText = '중복확인';
                            this.disabled = false;
                        }
                        checkFormValidity();
                    })
                    .catch(err => {
                        alert('서버 통신 중 오류가 발생했습니다.');
                        this.innerText = '중복확인';
                        this.disabled = false;
                    });
            });
        });

        // [JS 섹션 2.1] 값이 변경되면 중복확인 상태 초기화
        document.querySelectorAll('.check-unique').forEach(input => {
            input.addEventListener('input', function() {
                const field = this.getAttribute('data-field');
                const targetId = this.id;

                // 값이 변하면 중복확인을 다시 해야 하므로 무조건 false 처리
                validationStatus[field] = false;
                this.classList.remove('is-valid', 'is-invalid');

                // 연결된 버튼 상태 원상복구
                const btn = document.querySelector(`.btn-check-dup[data-target="${targetId}"]`);
                if (btn) {
                    btn.innerText = '중복확인';
                    btn.disabled = false;
                    btn.classList.remove('btn-success', 'text-white');
                    btn.classList.add('btn-outline-primary');
                }
                checkFormValidity();
            });
        });

        // [JS 섹션 2.0] 점주 이름 유효성 검사 (언어 제한)
        document.getElementById('manager_name').addEventListener('blur', function() {
            const val = this.value.trim();
            const regex = /^[가-힣\s]+$/; // 한글만 허용

            if (val !== '' && !regex.test(val)) {
                alert('점주 이름(한글) 필드에는 한글만 입력 가능합니다.');
                this.value = '';
                validationStatus.manager_name = false;
                this.classList.add('is-invalid');
            } else if (val === '') {
                validationStatus.manager_name = false;
                this.classList.add('is-invalid');
            } else {
                validationStatus.manager_name = true;
                this.classList.remove('is-invalid');
            }
            checkFormValidity();
        });

        document.getElementById('manager_name_en').addEventListener('blur', function() {
            const val = this.value.trim();
            const regex = /^[a-zA-Z\s]+$/; // 영문만 허용

            if (val !== '' && !regex.test(val)) {
                alert('점주 이름(English) 필드에는 영문(영어)만 입력 가능합니다.');
                this.value = '';
                validationStatus.manager_name_en = false;
                this.classList.add('is-invalid');
            } else if (val === '') {
                validationStatus.manager_name_en = false;
                this.classList.add('is-invalid');
            } else {
                validationStatus.manager_name_en = true;
                this.classList.remove('is-invalid');
            }
            checkFormValidity();
        });

        // [JS 섹션 2.0.1] 업종 선택 유효성 검사
        document.getElementById('category').addEventListener('blur', function() {
            if (this.value === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
            checkFormValidity();
        });

        // [JS 섹션 2.1] 비밀번호 복잡성 검사
        document.getElementById('password').addEventListener('input', function() {
            isPasswordTyped = true; // 사용자가 직접 타이핑을 시작함
        });

        document.getElementById('password').addEventListener('blur', function() {
            const val = this.value;
            // 정규식: 영문 대문자, 소문자, 숫자 포함 6자 이상
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

            // 사용자가 입력 중일 때 시각적인 피드백(빨간 테두리)만 제공 (알림은 '확인' 필드 blur 시 수행)
            if (val === '') {
                validationStatus.password = false;
                this.classList.add('is-invalid');
            } else if (!regex.test(val)) {
                validationStatus.password = false;
                this.classList.add('is-invalid');
            } else {
                validationStatus.password = true;
                this.classList.remove('is-invalid');
            }
            checkFormValidity();
        });

        // [JS 섹션 2.2] 비밀번호 확인 필드 유효성 검사
        document.getElementById('password_confirm').addEventListener('input', function() {
            isConfirmTyped = true; // 사용자가 직접 타이핑을 시작함
        });

        document.getElementById('password_confirm').addEventListener('blur', function() {
            const passwordInput = document.getElementById('password');
            const password = passwordInput.value;
            const passwordConfirm = this.value;
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/;

            // 1. 필수 입력 체크
            if (passwordConfirm === '') {
                validationStatus.password_confirm = false;
                this.classList.add('is-invalid');
            } else if (password !== passwordConfirm) {
                alert('비밀번호가 일치하지 않습니다. 다시 입력해 주세요.');
                validationStatus.password_confirm = false;
                this.classList.add('is-invalid');
                this.value = ''; // 일치하지 않으면 필드 비우기
                setTimeout(() => this.focus(), 10); // 다시 입력하도록 포커스 이동
            } else {
                validationStatus.password_confirm = true;
                this.classList.remove('is-invalid');
            }

            // 2. 비밀번호 복잡성 최종 체크
            if (passwordConfirm !== '' && !regex.test(password)) {
                alert('비밀번호는 영문 대문자, 소문자, 숫자를 조합하여 6자 이상으로 입력해주세요.');
                validationStatus.password = false;
                validationStatus.password_confirm = false;
                passwordInput.value = '';
                this.value = '';
                passwordInput.focus();
            }

            checkFormValidity();
        });

        // [추가] 폼 전체 유효성 확인 및 버튼 활성화 함수
        function checkFormValidity() {
            const isAllValid = Object.values(validationStatus).every(v => v === true);
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.disabled = !isAllValid;
            }
        }

        // 초기 로드 시 버튼 상태 확인
        window.addEventListener('load', checkFormValidity);

        // input 이벤트 발생 시에도 버튼 상태를 즉시 갱신 (사용자 경험 개선)
        document.querySelectorAll('input').forEach(el => {
            el.addEventListener('input', () => {
                // 개별 로직이 필요한 필드 외에는 범용적으로 체크 호출
                if (!el.classList.contains('check-unique') && el.id !== 'password' && el.id !==
                    'password_confirm') {
                    const field = el.id || el.name;
                    if (validationStatus.hasOwnProperty(field)) {
                        validationStatus[field] = (el.value.trim() !== '');
                    }
                }
                checkFormValidity();
            });
        });

        // [추가] 비밀번호 보기/숨기기 토글 함수
        function togglePasswordVisibility(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (field.type === "password") {
                field.type = "text";
                icon.classList.replace("bi-eye-slash-fill", "bi-eye-fill");
            } else {
                field.type = "password";
                icon.classList.replace("bi-eye-fill", "bi-eye-slash-fill");
            }
        }

        // [JS 섹션 3] 폼 제출 시 최종 유효성 확인
        document.getElementById('regForm').onsubmit = function(e) {
            // [보강] 모든 필수 필드 최종 확인
            const mandatoryFields = [
                'category', 'manager_email', 'password', 'password_confirm',
                'manager_name', 'manager_name_en', 'shop_name', 'shop_name_en',
                'phone_mobile', 'kakao_id', 'subdomain'
            ];

            for (const fieldId of mandatoryFields) {
                const el = document.getElementById(fieldId);
                if (!el || !el.value.trim()) {
                    const fieldName = getFieldName(fieldId);
                    alert(`[${fieldName}] 항목은 필수입니다. 모든 값을 입력해 주세요.`);
                    if (el) {
                        el.focus();
                        el.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                    return false;
                }
            }

            // validationStatus 객체를 순회하며 유효하지 않은(false) 필드 최종 확인 (중복/형식 등)
            for (const [field, isValid] of Object.entries(validationStatus)) {
                if (!isValid) {
                    let target = document.getElementById(field) ||
                        document.querySelector(`[data-field="${field}"]`) ||
                        document.querySelector(`[name="${field}"]`);

                    if (target) {
                        const fieldName = getFieldName(field);
                        if (target.classList.contains('check-unique')) {
                            alert(`[${fieldName}] 항목의 중복확인 버튼을 눌러주세요.`);
                        } else {
                            alert(`[${fieldName}] 항목의 값이 올바르지 않습니다.`);
                        }
                        target.focus();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        return false;
                    }
                }
            }
            return true;
        };

        // [JS 섹션 4] 서브도메인 입력 제한 (영문 소문자/숫자만)
        document.getElementById('subdomain').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');
        });

        // 필드명 한글 변환 헬퍼 함수
        function getFieldName(field) {
            const names = {
                'manager_email': '관리자 이메일',
                'kakao_channel_id': '카톡 채널',
                'subdomain': '웹사이트 주소',
                'custom_domain': '도메인',
                'shop_name': '상점 이름(한글)',
                'shop_name_en': '상점 이름(English)',
                'phone_mobile': '모바일 번호',
                'phone_landline': '랜드라인',
                'kakao_id': '카카오톡 ID',
                'password': '비밀번호',
                'password_confirm': '비밀번호 확인',
                'manager_name': '점주 이름(한글)',
                'manager_name_en': '점주 이름(English)'
            };
            return names[field] || '필수 항목';
        }

        // [추가] 개발자 테스트용 폼 자동 완성 함수
        function fillTestData() {
            const ts = new Date().getTime().toString().slice(-6); // 타임스탬프 마지막 6자리
            const ranStr = Math.random().toString(36).substring(2, 5); // 랜덤 3자리 영숫자

            // 값 채우기
            document.getElementById('manager_email').value = `imchhkim+${ts}@gmail.com`;
            document.getElementById('password').value = '123Qwe';
            document.getElementById('password_confirm').value = '123Qwe';
            document.getElementById('manager_name').value = '테스터';
            document.getElementById('manager_name_en').value = 'Tester';
            document.getElementById('shop_name').value = `자동테스트상점_${ts}`;
            document.getElementById('shop_name_en').value = `Auto Test ${ts}`;
            document.getElementById('phone_mobile').value = '0917-000-' + Math.floor(1000 + Math.random() * 9000);
            document.getElementById('phone_landline').value = '02-800-' + Math.floor(1000 + Math.random() * 9000);
            document.getElementById('kakao_id').value = `ktest_${ts}`;
            //document.getElementById('kakao_channel_id').value = `pf_test_${ts}`;
            document.getElementById('subdomain').value = `shop${ts}${ranStr}`;
            //document.querySelector('input[name="custom_domain"]').value = `www.shop${ts}.com`;

            // 이메일 인증 강제 패스 UI 처리
            document.getElementById('manager_email').readOnly = true;
            document.getElementById('btn-send-auth').disabled = true;
            document.getElementById('auth-code-group').classList.remove('d-none');
            const codeInput = document.getElementById('email_auth_code');
            codeInput.value = '123456';
            codeInput.readOnly = true;
            const btnVerify = document.getElementById('btn-verify-auth');
            btnVerify.innerText = '인증완료';
            btnVerify.classList.replace('btn-primary', 'btn-success');
            btnVerify.disabled = true;
            
            // [수정] 테스트 모드용 안내 메시지 시각적 표시
            const msgDiv = document.getElementById('email-auth-msg');
            msgDiv.className = 'small mt-1 text-success fw-bold';
            msgDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> 이메일 인증이 완료되었습니다. (테스트 모드)';
            msgDiv.classList.remove('d-none');
            
            // 중복/유효성 상태(validationStatus)를 모두 강제 통과 처리
            for (let key in validationStatus) {
                validationStatus[key] = true;
            }
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid', 'is-valid'));
            document.querySelectorAll('.check-unique').forEach(el => el.classList.add('is-valid'));

            // [수정] 버튼들도 모두 최근 UI인 '확인완료' 상태로 일관성 있게 전환
            document.querySelectorAll('.btn-check-dup').forEach(btn => {
                btn.innerText = '확인완료';
                btn.disabled = true;
                btn.classList.replace('btn-outline-primary', 'btn-success');
                btn.classList.add('text-white');
            });
            checkFormValidity();
        }
    </script>

    <?php
    // 공통 푸터 (JS 유틸리티 및 하이픈 자동 삽입 함수 포함)
    require_once 'common/common_footer.php';
    ?>