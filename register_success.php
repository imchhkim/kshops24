<?php

/**
 * KShops24 입점 신청 완료 페이지 (register_success.php)
 * 설명: 
 * 1. register.php에서 신청 성공 시 전달받은 ID(서브도메인)를 화면에 표시합니다.
 * 2. 신청 후 안내 사항(이메일 발송, 담당자 연락 기간)을 명시합니다.
 * 3. '내 상점 관리' 및 '메인 포털'로의 이동 경로를 제공합니다.
 */

$FOR_TEST = "YES"; // 테스트를 위해 직접 접근하려면 "YES"로 변경하세요.
// common_header.php를 통해 보안 로직이 이미 실행됩니다.
require_once 'common/common_header.php';

// [섹션 1] 파라미터 수신 및 보안 처리
// URL로부터 상점 ID(subdomain)를 받아오며, XSS 방지를 위해 htmlspecialchars를 적용합니다.
$my_id = isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '';

// [보안] URL의 ID와 세션에 저장된 ID가 일치하는지 최종 확인
// 테스트 모드가 아닐 때만 세션 ID 일치 여부 검증
if ((!isset($FOR_TEST) || $FOR_TEST !== "YES") && $my_id !== ($_SESSION['reg_complete_id'] ?? '')) {
    header("Location: /index.php");
    exit;
}

// [보안] 한 번 확인한 성공 플래그는 제거하여 새로고침이나 직접 재접속 차단
unset($_SESSION['reg_complete_id']);

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>신청 완료 - KShops24</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        /* [섹션 3] 디자인 커스텀 스타일 */
        body {
            background-color: #f4f7f9;
            font-family: 'Apple SD Gothic Neo', sans-serif;
        }

        /* 결과 카드: 중앙 배치 및 입체감 있는 그림자 */
        .success-card {
            max-width: 600px;
            margin: 60px auto;
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        /* 성공 아이콘 섹션 */
        .icon-box {
            font-size: 5rem;
            color: #198754;
            margin-bottom: 20px;
        }

        /* 이메일 안내 섹션: 파란색 왼쪽 테두리로 강조 */
        .email-notice {
            background-color: #f8f9fa;
            border-left: 5px solid #004aad;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }

        /* 메인 복귀 버튼 컬러 */
        .btn-home {
            background-color: #004aad;
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            border-radius: 10px;
        }

        /* [Mobile-First] 모바일 화면 최적화: 좌우 여백 최소화 */
        @media (max-width: 576px) {
            .success-card {
                margin: 20px 5px;
                padding: 35px 20px;
                border-radius: 15px;
            }

            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .icon-box {
                font-size: 4rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .email-notice {
                padding: 15px;
                margin: 20px 0;
            }

            .email-notice h5 {
                font-size: 1rem;
            }

            .btn-home,
            .btn-outline-primary {
                font-size: 1rem;
                padding: 12px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="success-card">
            <div class="icon-box">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h2 class="fw-bold mb-3">입점 신청이 완료되었습니다!</h2>
            <p class="text-muted mb-4"><strong>KShops24와 함께하시는 점주님의 비즈니스를 진심으로 응원합니다 !!!</strong></p>

            <?php if ($my_id): ?>
                <div class="alert alert-info border-0 shadow-sm mb-4" style="border-radius: 15px;">
                    <p class="mb-1 small text-muted">사장님의 상점 주소 (즉시 오픈됨)</p>
                    <a href="/<?php echo $my_id; ?>" target="_blank" class="text-decoration-none">
                        <h5 class="fw-bold text-dark mb-0">www.kshops24.com/<br><?php echo $my_id; ?> <i class="bi bi-box-arrow-up-right fs-6 ms-1 text-primary"></i></h5>
                    </a>
                </div>
            <?php endif; ?>

            <div class="email-notice shadow-sm">
                <h5 class="fw-bold" style="color:#004aad;">
                    <i class="bi bi-envelope-paper-heart-fill me-2"></i>계약 관련 정보가 발송되었습니다.
                </h5>
                <p class="mb-0 mt-2 small text-secondary">
                    입력하신 <strong>관리자 이메일 주소</strong>로 이용 약관, 초기 제작 비용, 월 유지비 등 상세 계약 정보가 포함된 메일을 즉시 보내드렸습니다.
                </p>
                <p class="mt-2 mb-0 x-small text-muted" style="font-size: 0.85rem;">
                    <i class="bi bi-info-circle me-1"><strong></i> 메일이 오지 않았다면 스팸 메일함을 확인해 주세요.</strong>
                </p>
            </div>

            <div class="mb-4">
                <p class="text-muted small">
                    이제 아래의 "내 상점 관리하기" 버튼을 눌러 로그인하시면, 상점 관리 페이지에서 점주님의 홈페이지 설정을 진행하실 수 있습니다. <br>
                </p>
            </div>

            <div class="d-flex flex-column gap-2 mt-4">
                <a href="shops/manage_shop.php" class="btn btn-outline-primary py-3 fw-bold rounded-3">
                    내 상점 관리하기 (로그인) <i class="bi bi-gear-fill ms-1"></i>
                </a>
                <a href="index.php" class="btn btn-primary btn-home shadow-sm py-3">
                    메인 포털로 돌아가기 <i class="bi bi-house-door-fill ms-1"></i>
                </a>
            </div>
        </div>
    </div>

</body>

</html>