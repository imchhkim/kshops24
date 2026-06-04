<?php

/**
 * [컨트롤러] KShops24 슈퍼 관리자 인증 시스템
 */

// 1. 관리자 공통 헤더 로드 (DB 연결, DB 세션 핸들러, 리다이렉트 방지 모두 포함)
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/admin_common_header.php';

// 2. 이미 로그인된 상태라면 관리자 메인으로 보냄 (무한 루프 방지)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_view.php?page=admin_dashboard");
    exit;
}

$error = "";

// [버그 수정] 처음 접속(GET) 시 변수 미정의 에러 방지 및 '아이디 저장' 쿠키 불러오기
$input_user = $_COOKIE['saved_admin_id'] ?? "";
$is_remembered = isset($_COOKIE['saved_admin_id']);

// 이하 찰리님의 로그인 로직 동일...
// (단, 성공 시 리다이렉트 경로를 admin_view.php로 맞추는 것이 좋습니다.)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_user = trim($_POST['username']);
    $input_pass = $_POST['password'];
    $is_remembered = isset($_POST['remember_admin']);

    try {
        // $pdo 객체 사용
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ? LIMIT 1");
        $stmt->execute([$input_user]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($input_pass, $admin['admin_pass'])) {
            // [편의 기능] 아이디 저장 체크 시 쿠키 설정
            if ($is_remembered) {
                setcookie('saved_admin_id', $input_user, time() + (86400 * 30), "/");
            } else {
                if (isset($_COOKIE['saved_admin_id'])) {
                    setcookie('saved_admin_id', '', time() - 3600, "/");
                }
            }

            // 세션 저장
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['admin_name'];

            header("Location: admin_view.php?page=admin_dashboard");
            exit;
        } else {
            $error = "아이디 또는 비밀번호가 올바르지 않습니다.";
        }
    } catch (PDOException $e) {
        $error = "데이터베이스 연결 오류가 발생했습니다.";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인 - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #1a1d20;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Apple SD Gothic Neo', sans-serif;
            margin: 0;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .admin-icon {
            font-size: 3rem;
            color: #212529;
            margin-bottom: 20px;
        }

        .btn-admin {
            background-color: #212529;
            border: none;
            padding: 12px;
            font-weight: bold;
            color: white;
            transition: 0.3s;
        }

        .btn-admin:hover {
            background-color: #000;
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>

<body>

    <div class="login-card text-center">
        <div class="admin-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h4 class="fw-bold mb-4">Super Admin Portal</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Admin ID</label>
                <input type="text" name="username" class="form-control" placeholder="아이디 입력" required
                    value="<?php echo htmlspecialchars($input_user); ?>">
            </div>

            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="비밀번호 입력" required>
            </div>

            <div class="mb-4 form-check text-start">
                <input type="checkbox" class="form-check-input" id="remember_admin" name="remember_admin"
                    <?php echo $is_remembered ? 'checked' : ''; ?>>
                <label class="form-check-label small text-muted" for="remember_admin">아이디 저장</label>
            </div>

            <button type="submit" class="btn btn-admin w-100 mb-3">System Login</button>

            <a href="/" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left"></i> 포털 메인으로
            </a>
        </form>
    </div>

</body>

</html>