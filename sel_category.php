<?php

/**
 * KShops24 업종(카테고리) 선택 페이지 (sel_category.php)
 * 설명: 상점 개설 시 자신의 비즈니스에 맞는 카테고리를 정확하게 선택하기 위한 중간 단계
 */

require_once 'common/common_header.php'; // 공통 엔진 및 보안 로직 로드

// pre_register.php에서 넘어온 계약 비용 정보 수신
$setup_fee = $_GET['setup_fee'] ?? '상담 후 결정';
$monthly_fee = $_GET['monthly_fee'] ?? '상담 후 결정';

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>업종 선택 - KShops24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --main-blue: #004aad;
            --light-gray: #f8f9fa;
        }

        body {
            background-color: var(--light-gray);
            font-family: 'Apple SD Gothic Neo', sans-serif;
        }

        .sel-card {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        /* 커스텀 라디오 버튼 카드 스타일 */
        .category-option {
            display: none;
        }

        .category-card {
            display: block;
            border: 2px solid #dee2e6;
            border-radius: 16px;
            padding: 25px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #fff;
            position: relative;
            overflow: hidden;
        }

        .category-card:hover {
            border-color: #a0c4ff;
            background-color: #f8faff;
            transform: translateY(-3px);
        }

        /* 선택된 상태 */
        .category-option:checked+.category-card {
            border-color: var(--main-blue);
            background-color: #f0f7ff;
            box-shadow: 0 8px 20px rgba(0, 74, 173, 0.15);
        }

        /* 선택된 상태 우측 상단 뱃지 */
        .category-card .checked-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: var(--main-blue);
            color: white;
            padding: 5px 20px;
            font-size: 0.8rem;
            font-weight: bold;
            border-bottom-left-radius: 16px;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease;
        }

        .category-option:checked+.category-card .checked-badge {
            opacity: 1;
            transform: translateX(0);
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background-color: rgba(0, 74, 173, 0.1);
            color: var(--main-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .category-option:checked+.category-card .icon-box {
            background-color: var(--main-blue);
            color: white;
        }

        /* 모바일 최적화 */
        @media (max-width: 576px) {
            .sel-card {
                margin: 15px 5px;
                padding: 25px 15px;
                border-radius: 15px;
            }

            .category-card {
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand py-2" href="index.php">
                <img src="/images/kshops24_logo04.png" alt="KShops24" style="height: 42px; width: auto; display: block;">
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="sel-card">
            <div class="text-center mb-5">
                <h3 class="fw-bold mb-3" style="color:var(--main-blue);"><i class="bi bi-shop-window me-2"></i>비즈니스 업종 선택</h3>
                <p class="text-muted mb-0">상점 개설 후 최적화된 홈페이지 기능과 디자인을 제공받기 위해 <br><strong>사장님의 비즈니스와 가장 잘 맞는 카테고리</strong>를 신중히 선택해 주세요.</p>
                <p class="medium text-danger mt-2 fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>한번 생성된 상점의 카테고리는 </br>향후 변경이 불가능합니다.</p>
            </div>

            <form action="register.php" method="GET" id="categoryForm">
                <input type="hidden" name="setup_fee" value="<?php echo htmlspecialchars($setup_fee); ?>">
                <input type="hidden" name="monthly_fee" value="<?php echo htmlspecialchars($monthly_fee); ?>">
                <?php if (isset($_GET['dev_test'])) echo '<input type="hidden" name="dev_test" value="1">'; ?>

                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <input type="radio" name="category" id="cat_fnb" value="fnb" class="category-option" required>
                        <label class="category-card h-100" for="cat_fnb">
                            <span class="checked-badge"><i class="bi bi-check-lg"></i> 선택됨</span>
                            <div class="icon-box"><i class="bi bi-egg-fried"></i></div>
                            <h5 class="fw-bold mb-2" style="color:var(--main-blue)">음식점 / 카페 / 배달</h5>
                            <p class="text-muted small mb-0"><strong>배달, 매장픽업, 메뉴 중심의 상점</strong><br>(식당, 카페, 베이커리, 마트 등)</p>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <input type="radio" name="category" id="cat_realty" value="realty" class="category-option" required>
                        <label class="category-card h-100" for="cat_realty">
                            <span class="checked-badge"><i class="bi bi-check-lg"></i> 선택됨</span>
                            <div class="icon-box"><i class="bi bi-buildings"></i></div>
                            <h5 class="fw-bold mb-2" style="color:var(--main-blue)">부동산 / 각종중개 / 각종매매</h5>
                            <p class="text-muted small mb-0"><strong>매물 소개 및 상담 문의 중심</strong><br>(부동산 중개/렌트, 차동차 렌트, 개인 공방 등)</p>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <input type="radio" name="category" id="cat_srv" value="srv" class="category-option" required>
                        <label class="category-card h-100" for="cat_srv">
                            <span class="checked-badge"><i class="bi bi-check-lg"></i> 선택됨</span>
                            <div class="icon-box"><i class="bi bi-calendar2-heart"></i></div>
                            <h5 class="fw-bold mb-2" style="color:var(--main-blue)">예약 / 서비스</h5>
                            <p class="text-muted small mb-0"><strong>방문 및 시간 예약 중심</strong><br>(병의원, 치과, 마사지, 미용실, 방문 수리 서비스 등)</p>
                        </label>
                    </div>
                </div>

                <button type="button" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="font-size: 1.1rem; border-radius: 12px;" onclick="checkCategorySelection()">다음 단계로 (상점 정보 입력) <i class="bi bi-arrow-right-circle-fill ms-2"></i></button>
            </form>
        </div>
    </div>

    <script>
        // 카테고리 선택 여부 확인 후 폼 제출
        function checkCategorySelection() {
            const selectedCategory = document.querySelector('.category-option:checked');
            if (!selectedCategory) {
                alert('상점 개설을 위해 비즈니스 카테고리를 반드시 선택하셔야 합니다.');
                return false;
            }
            document.getElementById('categoryForm').submit();
        }
    </script>
</body>
</html>