<?php

/**
 * [컨트롤러] KShops24 상점 관리자 인증 엔진 (login.php)
 * -----------------------------------------------------------------------
 * [수정 사항] 
 * 1. $_SERVER['DOCUMENT_ROOT']를 활용한 절대 경로 도입
 * 2. 로그인 성공 시 리다이렉트 경로를 새로운 위치(/shops/fnb/)로 변경
 * -----------------------------------------------------------------------
 */

// 1. 공통 헤더 로드 (lib_utils.php가 포함된 common_header)
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 3. [접근 제어] 이미 세션이 활성화되어 있다면 대시보드로 즉시 이동
if (isset($_SESSION['shop_id'])) {
    // 일관된 파라미터를 사용하여 대시보드로 리다이렉트
    header("Location: manage_shop.php?pg=manage_shop_dashboard");
    exit;
}

$error_message = "";

// [AJAX] 비밀번호 찾기 (임시 비밀번호 생성 및 이메일 발송)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_reset_pw'])) {
    header('Content-Type: application/json');
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id FROM shops WHERE manager_email = ? LIMIT 1");
    $stmt->execute([$email]);
    $shop = $stmt->fetch();

    if ($shop) {
        // 읽기 쉬운 문자로 8자리 임시 비밀번호 생성 (대문자, 소문자, 숫자 포함)
        $temp_pw = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
        $hashed_pw = password_hash($temp_pw, PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE shops SET manager_password = ?, is_temp_password = 1 WHERE id = ?")
            ->execute([$hashed_pw, $shop['id']]);

        // 공용 함수를 이용하여 임시 비밀번호 메일 발송
        sendTempPasswordEmail($pdo, $email, $temp_pw);

        echo json_encode(['status' => 'success', 'message' => '입력하신 이메일로 임시 비밀번호가 발송되었습니다. 메일함을 확인해 주세요.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '시스템에 등록되지 않은 이메일입니다.']);
    }
    exit;
}

// [POST] 임시 비밀번호 로그인 직후 강제 비밀번호 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_temp_pw'])) {
    if (!isset($_SESSION['temp_reset_shop_id'])) {
        header("Location: login.php");
        exit;
    }

    $new_pw = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];
    $temp_shop_id = $_SESSION['temp_reset_shop_id'];

    if ($new_pw !== $confirm_pw) {
        $error_message = "비밀번호가 서로 일치하지 않습니다.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $new_pw)) {
        $error_message = "비밀번호는 영문 대/소문자, 숫자를 포함하여 6자 이상이어야 합니다.";
    } else {
        $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE shops SET manager_password = ?, is_temp_password = 0 WHERE id = ?")->execute([$hashed_pw, $temp_shop_id]);
        unset($_SESSION['temp_reset_shop_id']);

        // 변경 완료 후 성공 메시지를 띄우고 기본 로그인 페이지로 이동시킵니다.
        echo "<script>alert('비밀번호가 변경되었습니다. 새로운 비밀번호로 로그인하세요.'); location.replace('login.php');</script>";
        exit;
    }
}

// 2. [쿠키 핸들링] 이전에 '아이디 저장'을 체크했다면 이메일 값을 불러옴
$email = isset($_COOKIE['saved_shop_email']) ? $_COOKIE['saved_shop_email'] : "";
$is_remembered = isset($_COOKIE['saved_shop_email']); // 체크박스 기본값 설정용
$action = $_GET['action'] ?? '';

// 3. [POST 처리] 로그인 버튼 클릭 시 실행
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['do_login'])) {
    $email = trim($_POST['manager_email']); // 공백 제거
    $password = $_POST['manager_password'];
    $is_remembered = isset($_POST['remember_id']); // 아이디 저장 체크 여부 수집

    try {
        // DB에서 해당 이메일의 점주 정보 추출 (LIMIT 1로 쿼리 최적화)
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE manager_email = ? LIMIT 1");
        $stmt->execute([$email]);
        $shop = $stmt->fetch();

        // [검증 스테이지] 계정 존재 여부 및 암호화된 비밀번호 대조
        if ($shop && password_verify($password, $shop['manager_password'])) {

            // [보안] 임시 비밀번호로 로그인한 경우 강제 변경 페이지로 유도
            if (isset($shop['is_temp_password']) && $shop['is_temp_password'] == 1) {
                $_SESSION['temp_reset_shop_id'] = $shop['id'];
                header("Location: login.php?action=force_change_pw");
                exit;
            }

            // 4. [편의 기능] 아이디 저장 쿠키 설정
            if ($is_remembered) {
                // 30일(86400초 * 30) 동안 쿠키 유지, 경로 '/'로 설정하여 도메인 전역 사용
                setcookie('saved_shop_email', $email, time() + (86400 * 30), "/");
            } else {
                // 체크 해제 시 기존 쿠키 즉시 만료 처리
                if (isset($_COOKIE['saved_shop_email'])) {
                    setcookie('saved_shop_email', '', time() - 3600, "/");
                }
            }

            // 5. [세션 생성] 로그인 성공 시 상점의 핵심 정보를 세션에 기록
            $_SESSION['shop_id'] = $shop['id'];
            $_SESSION['subdomain'] = $shop['subdomain']; // 상점별 서브도메인 처리용
            $_SESSION['manager_name'] = $shop['manager_name'];

            // 최종 목적지인 관리 화면으로 이동
            header("Location: manage_shop.php?pg=manage_shop_dashboard");
            exit;
        } else {
            // 보안상 이메일이 틀렸는지 비밀번호가 틀렸는지 모호하게 메시지 처리
            $error_message = "이메일 또는 비밀번호가 일치하지 않습니다.";
        }
    } catch (PDOException $e) {
        // 시스템 내부 오류 발생 시 사용자 알림
        $error_message = "데이터베이스 연결에 문제가 발생했습니다.";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상점 관리자 로그인 - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(180deg, #f4f7f9 0%, #e9ecef 100%);
            font-family: 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        /* [UI] 세련된 카드형 레이아웃 디자인 */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 40px 30px;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .btn-login {
            background-color: #004aad;
            border: none;
            padding: 16px;
            font-weight: 700;
            border-radius: 16px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .btn-login:hover {
            background-color: #003380;
            transform: translateY(-1px);
        }

        .logo-text {
            color: #004aad;
            font-weight: 900;
            text-decoration: none;
            font-size: 2.2rem;
            letter-spacing: -1.5px;
        }

        .form-control {
            padding: 14px 18px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 1rem;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(0, 74, 173, 0.1);
            border-color: #004aad;
        }

        .form-label {
            margin-left: 4px;
            color: #475569;
        }

        /* 모바일 최적화 대응 */
        @media (max-width: 576px) {
            .login-card {
                padding: 35px 20px;
                border-radius: 24px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            }

            .logo-text {
                font-size: 1.8rem;
            }

            body {
                padding: 15px;
            }
        }

        /* =========================================================================
        [KShops24 공통 UI] 섹션 제목 (Section Titles)
        - 사용법: <div class="section-title-lg">큰 제목</div>
        - 사용법: <div class="section-title-md">중간 제목</div>
        - 사용법: <div class="section-title-sm">작은 제목</div>
        ========================================================================= */

        /* 1. 대형 제목 (페이지 메인 타이틀용) */
        .section-title-lg {
            font-size: 1.75rem; /* 약 28px */
            font-weight: 800; /* 매우 굵게 */
            color: #1e293b; /* 짙은 남색 계열 (Dark Slate) */
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0; /* 연한 회색 밑줄 */
            position: relative;
        }

        /* 대형 제목 하단의 파란색 포인트 라인 (디자인 요소) */
        .section-title-lg::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 60px;
            height: 2px;
            background-color: #004aad; /* KShops24 메인 블루 */
        }

        /* 2. 중형 제목 (카드 내 메인 섹션 분리용) */
        .section-title-md {
            font-size: 1.25rem; /* 약 20px */
            font-weight: 700; /* 굵게 */
            color: #334155;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        /* 중형 제목 왼쪽의 세로 바 (디자인 요소) */
        .section-title-md::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 18px;
            background-color: #004aad;
            margin-right: 10px;
            border-radius: 2px;
        }

        /* 3. 소형 제목 (폼 그룹이나 작은 항목 묶음용) */
        .section-title-sm {
            font-size: 0.95rem; /* 약 15px */
            font-weight: 700;
            color: #64748b; /* 약간 흐린 색상으로 계층 구분 */
            margin-bottom: 0.5rem;
            text-transform: uppercase; /* 영문일 경우 대문자 변환 */
            letter-spacing: 0.5px; /* 자간을 살짝 넓혀 세련됨 강조 */
        }

        /* 마우스 호버 시 살짝 커지는 공통 애니메이션 효과 */
        .transition-all {
            transition: all 0.2s ease-in-out;
        }

        .transition-all:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }
    </style>
</head>

<body>

    <div class="login-card">

        <?php if ($action === 'force_change_pw'): ?>
            <?php
            if (!isset($_SESSION['temp_reset_shop_id'])) {
                echo "<script>location.replace('login.php');</script>";
                exit;
            }
            ?>
            <div class="text-center mb-4">
                <div class="mb-2"><i class="bi bi-shield-lock text-danger" style="font-size: 2.5rem;"></i></div>
                <h4 class="fw-bold text-dark">비밀번호 변경 안내</h4>
                <p class="text-muted mt-2 fw-medium small">보안을 위해 임시 발급된 비밀번호를 새롭게 변경해 주세요.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger py-3 small text-center border-0 mb-4" style="border-radius: 14px;">
                    <i class="bi bi-exclamation-circle me-1"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php?action=force_change_pw">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">새 비밀번호 <span class="text-muted fw-normal">(대/소문자, 숫자 포함 6자 이상)</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-dark">새 비밀번호 확인</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" name="update_temp_pw" class="btn btn-danger btn-login w-100 text-white shadow-sm">비밀번호 변경 및 로그인</button>
            </form>

        <?php else: ?>

            <div class="text-center mb-5">
                <div class="mb-2"><i class="bi bi-shop-window text-primary" style="font-size: 2.5rem;"></i></div>
                <a href="../index.php" class="logo-text d-block">KShops24</a>
                <p class="text-muted mt-2 fw-medium small">상점 관리자 센터</p>
            </div>

            <!-- [오류 메시지 출력 영역] 로그인 실패(이메일/비밀번호 불일치) 또는 유효성 검사 실패 시 빨간색 경고창을 표시합니다. -->
            <?php if ($error_message): ?>
                <div class="alert alert-danger py-3 small text-center border-0 mb-4" style="border-radius: 14px;">
                    <i class="bi bi-exclamation-circle me-1"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- [성공 메시지 출력 영역] 비밀번호 변경 완료 등 특정 작업이 성공적으로 처리되었을 때 녹색 안내창을 표시합니다. -->
            <?php if ($message): ?>
                <div class="alert alert-success py-3 small text-center border-0 mb-4" style="border-radius: 14px;">
                    <i class="bi bi-check-circle me-1"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">관리자 이메일 주소</label>
                    <input type="email" name="manager_email" class="form-control"
                        placeholder="example@gmail.com" required
                        value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">비밀번호</label>
                    <input type="password" name="manager_password" class="form-control"
                        placeholder="••••••••" required>
                </div>

                <div class="mb-4 form-check d-flex align-items-center">
                    <input type="checkbox" class="form-check-input" id="remember_id" name="remember_id"
                        style="cursor:pointer;" <?php echo $is_remembered ? 'checked' : ''; ?>>
                    <label class="form-check-label small text-muted ms-1" for="remember_id" style="cursor:pointer;">
                        이메일 주소 기억하기
                    </label>
                </div>

                <button type="submit" name="do_login" class="btn btn-primary btn-login w-100 mb-4 text-white">상점 관리자 로그인</button>

                <div class="text-center pt-2 border-top">
                    <div class="mb-2">
                        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#resetPwModal" class="text-decoration-none small text-secondary">
                            <i class="bi bi-key me-1"></i> 비밀번호를 잊으셨나요?
                        </a>
                    </div>
                    <a href="../pre_register.php" class="text-decoration-none small fw-bold" style="color:#004aad;">
                        <i class="bi bi-shop-window me-1"></i> 아직 회원이 아니신가요? <span class="text-decoration-underline">입점 신청</span>
                    </a>
                </div>
            </form>

        <?php endif; ?>
    </div>

    <!-- [모달] 비밀번호 찾기 (임시 비밀번호 발급) -->
    <div class="modal fade" id="resetPwModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 pb-0 mt-2 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-envelope-paper text-primary me-2"></i>임시 비밀번호 발급</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-3 text-center">
                    <p class="small text-muted mb-4">상점 가입 시 등록한 이메일 주소를 입력해 주세요.<br>해당 이메일로 <b>임시 비밀번호</b>를 발송해 드립니다.</p>
                    <div class="mb-4 text-start">
                        <input type="email" id="reset_email" class="form-control" placeholder="등록된 관리자 이메일 입력" required>
                    </div>
                    <button type="button" id="btn-reset-pw" class="btn btn-primary w-100 py-3 rounded-4 fw-bold" onclick="sendResetPw()">임시 비밀번호 발송 요청</button>
                    <div id="reset-pw-msg" class="mt-3 small d-none pb-2"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        async function sendResetPw() {
            const email = document.getElementById('reset_email').value.trim();
            const btn = document.getElementById('btn-reset-pw');
            const msgDiv = document.getElementById('reset-pw-msg');

            if (!email) {
                msgDiv.className = 'mt-3 small text-danger fw-bold';
                msgDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> 이메일을 입력해 주세요.';
                msgDiv.classList.remove('d-none');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>발송 진행 중...';
            msgDiv.classList.add('d-none');

            const formData = new FormData();
            formData.append('ajax_reset_pw', '1');
            formData.append('email', email);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                msgDiv.classList.remove('d-none');
                if (result.status === 'success') {
                    msgDiv.className = 'mt-3 small text-success fw-bold';
                    msgDiv.innerHTML = `<i class="bi bi-check-circle"></i> ${result.message}`;
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('resetPwModal'));
                        if (modal) modal.hide();
                        document.getElementById('reset_email').value = '';
                    }, 4000);
                } else {
                    msgDiv.className = 'mt-3 small text-danger fw-bold';
                    msgDiv.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${result.message}`;
                }
            } catch (err) {
                msgDiv.className = 'mt-3 small text-danger fw-bold';
                msgDiv.innerHTML = '<i class="bi bi-wifi-off"></i> 서버 통신 중 오류가 발생했습니다.';
                msgDiv.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.innerText = '임시 비밀번호 발송 요청';
            }
        }
    </script>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/common/common_footer.php'; ?>