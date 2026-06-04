<?php

/**
 * KShops24 테스트 상점 쾌속 생성 도구 (t_register_test_shop.php)
 * 역할: 복잡한 가입 프로세스(약관 동의, 이메일 인증 등)를 생략하고 즉시 상점을 DB에 인서트합니다.
 */

require_once __DIR__ . '/t_common.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? 'fnb';
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_name_en = trim($_POST['shop_name_en'] ?? '');

    // 난수 생성 로직
    $ts = time();
    $ranStr = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 4);
    
    // 빈 값 처리
    if (empty($shop_name)) $shop_name = "테스트상점_" . substr($ts, -4);
    if (empty($shop_name_en)) $shop_name_en = "TestShop " . substr($ts, -4);

    $subdomain = "test_" . $ranStr . substr($ts, -4);
    $email = "tester_" . $ts . "@KShops24.local";
    $password = password_hash('1234', PASSWORD_DEFAULT); // 공통 비밀번호
    $phone_mobile = '0917-000-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        // 상점 강제 인서트 (상태: active)
        $sql = "INSERT INTO shops (
            manager_email, manager_password, manager_name, manager_name_en, 
            shop_name, shop_name_en, phone_mobile, subdomain, 
            status, category, created_at
        ) VALUES (?, ?, '테스터', 'Tester', ?, ?, ?, ?, 'active', ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $email, $password, $shop_name, $shop_name_en, $phone_mobile, $subdomain, $category
        ]);

        $new_shop_id = $pdo->lastInsertId();

        // 테스트 상점용 기본 결제 내역 (기능 오류 방지용)
        if (function_exists('recordShopPayment')) {
            recordShopPayment($pdo, $new_shop_id, 'setup', 0, "테스트 상점 생성 (무료)", 'f', date('Y-m-d'), date('Y-m-d'));
            recordShopPayment($pdo, $new_shop_id, 'monthly', 0, "테스트 상점 생성 (무료)", 'f', date('Y-m-d'), date('Y-m-d', strtotime('+1 year')));
        }

        // 업로드 폴더 강제 생성
        $shop_upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $subdomain;
        if (!is_dir($shop_upload_dir)) {
            mkdir($shop_upload_dir, 0755, true);
        }

        $pdo->commit();

        $message = "
        <div class='alert alert-success shadow-sm border-start border-4 border-success mb-4'>
            <h5 class='fw-bold mb-3'><i class='bi bi-check-circle-fill me-2'></i>테스트 상점이 1초 만에 생성되었습니다!</h5>
            <ul class='mb-3 font-monospace small'>
                <li><strong>상점 ID:</strong> {$new_shop_id}</li>
                <li><strong>상점명:</strong> {$shop_name} ({$shop_name_en})</li>
                <li><strong>서브도메인:</strong> {$subdomain}</li>
                <li><strong>카테고리:</strong> {$category}</li>
                <li><strong>로그인 ID(이메일):</strong> {$email}</li>
                <li><strong>비밀번호:</strong> 1234</li>
            </ul>
            <div class='d-flex flex-wrap gap-2'>
                <a href='/{$subdomain}' target='_blank' class='btn btn-sm btn-primary fw-bold'><i class='bi bi-shop me-1'></i> 방문</a>
                <a href='/shops/login.php?subdomain={$subdomain}' target='_blank' class='btn btn-sm btn-dark fw-bold'><i class='bi bi-gear-fill me-1'></i> 관리자 로그인</a>
                <form action='t_populate_sample_shop.php' method='POST' target='_blank' class='d-inline m-0'>
                    <input type='hidden' name='shop_id' value='{$new_shop_id}'>
                    <input type='hidden' name='category' value='{$category}'>
                    <button type='submit' class='btn btn-sm btn-warning fw-bold text-dark'><i class='bi bi-magic me-1'></i> 이 상점에 샘플 데이터 채우기 (새창)</button>
                </form>
            </div>
        </div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-danger mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i>상점 생성 실패: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테스트 상점 쾌속 생성기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow-sm border-start border-5 border-primary mb-4">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-lightning-charge-fill me-2"></i>테스트 상점 쾌속 생성</h4>
                <p class="card-text text-muted small mb-0">인증이나 복잡한 정보 입력 없이 <strong>카테고리</strong>만 선택하면 기본값이 들어간 활성(active) 상태의 테스트 상점을 즉시 생성합니다.</p>
            </div>
        </div>
        <?php echo $message; ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">1. 카테고리 선택 <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="fnb">음식점 / 카페 / 배달 (fnb)</option>
                            <option value="realty">부동산 / 각종 중개 (realty)</option>
                            <option value="srv">예약 / 서비스 (srv)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">2. 상점명 (한글) <span class="text-muted fw-normal small ms-2">선택사항</span></label>
                        <input type="text" name="shop_name" class="form-control" placeholder="비워두면 난수 이름이 지정됩니다">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">3. 상점명 (English) <span class="text-muted fw-normal small ms-2">선택사항</span></label>
                        <input type="text" name="shop_name_en" class="form-control" placeholder="비워두면 난수 이름이 지정됩니다">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 rounded-pill shadow-sm">
                        <i class="bi bi-building-add me-2"></i>상점 생성
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>