<?php

/**
 * KShops24 공지사항 상세 보기 (notice_view.php)
 * 설명: 서버 루트 경로를 기준으로 DB 설정을 로드하고 공지사항 데이터를 처리합니다.
 */

require_once 'common/common_header.php'; // 공통 엔진 및 보안 로직 로드

/**
 * [섹션 1] 사이트 설정 정보 로드
 * - 운영자가 관리자 페이지에서 수정한 비용 정보 등을 테이블에서 가져옵니다.
 */
$stmt = $pdo->query("SELECT set_key, set_value FROM site_settings");
$settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * [섹션 2] 데이터 구조 재가공
 * - DB의 행(Row) 구조를 $settings['키'] = '값' 형태의 연관 배열로 변환하여 뷰에서 쓰기 편하게 만듭니다.
 */
$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['set_key']] = $row['set_value'];
}

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>입점 안내 및 동의 - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        /* [섹션 4] UI 스타일링 */
        :root {
            --main-blue: #004aad;
            --light-gray: #f8f9fa;
        }

        body {
            background-color: #fff;
            font-family: 'Apple SD Gothic Neo', sans-serif;
        }

        /* --- 이 부분의 padding-top만 60px에서 10px로 수정했습니다 --- */
        .main-content {
            background-color: var(--light-gray);
            padding: 10px 0 60px 0;
        }

        /* --- 이하 모든 코드는 원본과 동일하게 유지 --- */
        .agree-card {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        /* 각 동의 항목 디자인: 호버 시 강조 효과 */
        .condition-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .condition-item:hover {
            border-left-color: var(--main-blue);
            transform: translateX(5px);
        }

        /* 체크박스 포인트 컬러 변경 */
        .form-check-input:checked {
            background-color: #004aad;
            border-color: #004aad;
        }

        .form-check-label {
            font-weight: bold;
            color: #333;
            cursor: pointer;
            display: block;
            width: 100%;
        }

        /* 비용 강조 텍스트 */
        .fee-text {
            color: #004aad;
            font-size: 1.2rem;
            font-weight: 800;
        }

        /* 약관 텍스트 영역 */
        .terms-box {
            background: #fff;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            max-height: 180px;
            overflow-y: auto;
            margin-top: 10px;
            color: #666;
            line-height: 1.6;
        }

        .terms-box img {
            max-width: 100%;
            height: auto;
        }

        /* 버튼 스타일 */
        .btn-next {
            padding: 15px;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: 0.3s;
        }

        /* [Mobile-First] 모바일 화면 최적화: 좌우 여백 최소화 */
        @media (max-width: 576px) {
            .agree-card {
                margin: 15px 5px;
                padding: 25px 15px;
                border-radius: 15px;
            }

            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .condition-item {
                padding: 15px 10px;
            }

            .form-check-label {
                font-size: 0.95rem;
            }

            .fee-text {
                font-size: 1.1rem;
            }

            h4 {
                font-size: 1.25rem;
            }

            .text-muted {
                font-size: 0.85rem;
            }

            .btn-next {
                font-size: 1rem;
                padding: 12px;
            }
        }

        /* --- Footer --- */
        .main-footer {
            background-color: #212529;
            color: #adb5bd;
        }

        .main-footer a {
            color: #dee2e6;
            text-decoration: none;
        }

        .main-footer a:hover {
            color: white;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand py-2" href="index.php">
                <img src="/images/philbiz24_logo01.png" alt="KShops24" style="height: 42px; width: auto; display: block;">
            </a>
        </div>
    </nav>
    <div class="main-content">
        <div class="container">
            <div class="agree-card">
                <div class="text-center mb-2">
                    <a href="index.php" style="text-decoration: none;">
                        <h2 class="fw-bold" style="color:#004aad;">KShops24</h2>
                    </a>
                    <h4 class="fw-bold mt-3">상점 입점 안내 및 약관 동의</h4>
                    <p class="text-muted text-nowrap small">
                        (아래의 약관에 <span class="text-danger fw-bold">모두 동의</span>하셔야 가입절차가 진행됩니다.)
                    </p>
                </div>

                <form action="sel_category.php" method="GET" id="agreeForm">
                    <!-- [보안 및 데이터 전달] register.php의 접근 권한 확인을 위한 비용 정보 전달 -->
                    <input type="hidden" name="setup_fee" value="<?php echo htmlspecialchars($settings['setup_fee_info'] ?? '상담 후 결정'); ?>">
                    <input type="hidden" name="monthly_fee" value="<?php echo htmlspecialchars($settings['monthly_fee_info'] ?? '상담 후 결정'); ?>">

                    <div class="condition-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check1" name="agree_setup_fee" required>
                            <label class="form-check-label" for="check1">
                                1. 초기 상점 홈페이지 제작 비용 동의 (필수)
                                <div class="mt-2 small text-muted font-weight-normal">
                                    전문 디자인 및 시스템 세팅비 : <span class="fee-text"> <del>300,000원</del> &rarr; <span class="text-danger"><?php echo htmlspecialchars($settings['setup_fee_info'] ?? '상담 후 결정'); ?></span></span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="condition-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check2" name="agree_monthly_fee" required>
                            <label class="form-check-label" for="check2">
                                2. 매월 유지 관리 비용 동의 (필수)
                                <div class="mt-2 small text-muted font-weight-normal">
                                    서버 호스팅 및 시스템 유지보수 : <span class="fee-text"> <del> 30,000원</del> &rarr; <span class="text-danger"><?php echo htmlspecialchars($settings['monthly_fee_info'] ?? '상담 후 결정'); ?></span></span>
                                </div>

                                <!-- 4개월 무료 프로모션 안내 추가 -->
                                <div class="mt-2 small text-muted font-weight-normal">
                                    <span class="fee-text"><span class="text-danger">* 상점 개설 후, 4개월간의 월 유지비 무료 [프로모 !!!] </span></span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="condition-item border-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check3" name="agree_terms" required>
                            <label class="form-check-label" for="check3">
                                3. 서비스 이용 약관 및 면책 조항 동의 (필수)
                                <div class="terms-box">
                                    <?php echo $settings['terms_of_use'] ?? '이용 약관이 설정되지 않았습니다.'; ?>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="condition-item border-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check4" name="agree_privacy" required>
                            <label class="form-check-label" for="check4">
                                4. 개인정보 수집 및 이용 동의 (필수)
                                <div class="terms-box">
                                    <?php echo $settings['privacy_policy'] ?? '개인정보처리방침이 설정되지 않았습니다.'; ?>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-2">
                        <button type="submit" id="submitBtn" class="btn btn-primary w-100 btn-next shadow" disabled>
                            모든 조건에 동의하며 <br>회원가입 및 입점신청 계속하기 <i class="bi bi-arrow-right-circle-fill ms-2"></i>
                        </button>
                        <a href="index.php" class="btn btn-link w-100 mt-3 text-muted text-decoration-none small">KShops24로 돌아가기</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        /**
         * [섹션 6] 인터랙션 스크립트
         * - 사용자가 모든 체크박스에 체크했는지 실시간으로 감시합니다.
         * - 모든 체크박스가 true일 때만 '신청 계속하기' 버튼을 활성화합니다.
         */
        const checkboxes = document.querySelectorAll('.form-check-input');
        const submitBtn = document.getElementById('submitBtn');

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // 모든 체크박스의 checked 상태가 true인지 확인 (Array.every 활용)
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                submitBtn.disabled = !allChecked;
            });
        });
    </script>

    <?php
    // 공통 푸터 (JS 유틸리티 및 하이픈 자동 삽입 함수 포함)
    require_once 'common/common_footer.php';
    ?>