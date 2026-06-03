<?php

/**
 * KShops24 서비스 이용 약관 팝업 페이지 (terms_of_use.php)
 * 설명: 이메일 등의 외부 링크에서 "이용약관 자세히 보기"를 눌렀을 때, 
 *       군더더기 없이 약관 내용만 깔끔하게 보여주기 위한 전용 페이지입니다.
 */

// 1. DB 설정 로드 (루트 경로 기준)
$db_config_file = $_SERVER['DOCUMENT_ROOT'] . '/db_config.php';
if (file_exists($db_config_file)) {
    require_once $db_config_file;
} else {
    die("데이터베이스 설정 파일을 찾을 수 없습니다.");
}

try {
    // 2. DB에서 이용 약관 데이터만 추출
    $stmt = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'terms_of_use'");
    $stmt->execute();
    $terms_of_use = $stmt->fetchColumn();

    if (!$terms_of_use) {
        $terms_of_use = "<div class='text-center text-muted py-5'>등록된 이용 약관이 없습니다.</div>";
    } else {
        // 에디터로 작성된 HTML 특수문자 복원
        $terms_of_use = html_entity_decode($terms_of_use, ENT_QUOTES, 'UTF-8');
    }
} catch (PDOException $e) {
    $terms_of_use = "<div class='text-center text-danger py-5'>시스템 오류로 약관을 불러올 수 없습니다.</div>";
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이용 약관 - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* 모바일 기기에서도 보기 편안한 스타일 세팅 */
        body {
            background-color: #f4f7f9;
            font-family: 'Pretendard', 'Apple SD Gothic Neo', sans-serif;
            padding: 20px;
        }

        .terms-card {
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        .terms-content {
            margin-top: 30px;
            font-size: 0.95rem;
            color: #444;
            line-height: 1.8;
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }

            .terms-card {
                padding: 25px 15px;
                border-radius: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="terms-card">
        <h4 class="fw-bold text-center" style="color: #004aad;">서비스 이용 약관</h4>
        <hr class="mt-4 mb-4" style="border-color:#dee2e6;">
        <div class="terms-content">
            <?php echo $terms_of_use; ?>
        </div>
        <div class="text-center mt-5">
            <button type="button" class="btn btn-secondary px-5 py-2 rounded-pill fw-bold" onclick="window.close();">창 닫기</button>
        </div>
    </div>
</body>

</html>