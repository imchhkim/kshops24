<<<<<<< HEAD
<?php

/**
 * KShops24 슈퍼 관리자 대시보드 (admin_dashboard.php)
 * - admin_view.php를 통해 로드됩니다.
 * - 기능: 오늘 입점한 상점, 상점에서 온 메시지, 결제 만료 임박 상점 현황 등 요약 제공
 * - 모듈형(Grid)으로 설계되어 향후 통계/그래프 등의 위젯 추가가 용이합니다.
 */

// [버그 수정] AJAX 단독 호출을 허용하기 위해, $pdo가 없으면 공통 헤더를 로드하여 초기화합니다.
// 이를 통해 admin_view.php의 HTML 껍데기가 섞여 JSON 파싱 에러가 나는 문제를 원천 차단합니다.
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// [신규] 리소스 사용량 체크 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_resources') {
    if (ob_get_level()) ob_clean(); // 다른 경고 메시지가 섞이는 것을 차단
    header('Content-Type: application/json');
    $results = [];
    try {
        // 모든 상점 목록 가져오기 (필요 시 active 상점만)
        $stmt_shops = $pdo->query("SELECT id, shop_name, subdomain FROM shops");
        $all_shops = $stmt_shops->fetchAll();

        if (!function_exists('getShopResourceUsage')) {
            throw new Exception("리소스 측정 함수(getShopResourceUsage)를 찾을 수 없습니다.");
        }

        foreach ($all_shops as $shop) {
            $usage = getShopResourceUsage($pdo, $shop['id']);
            $total_usage = $usage['disk'] + $usage['db'];
            if ($total_usage > 0) { // 사용량이 0인 상점은 제외
                $results[] = [
                    'id' => $shop['id'],
                    'shop_name' => $shop['shop_name'],
                    'disk_usage' => $usage['disk'],
                    'db_usage' => $usage['db'],
                    'total_usage' => $total_usage
                ];
            }
        }

        // 총 사용량 기준으로 내림차순 정렬
        usort($results, function ($a, $b) {
            return $b['total_usage'] <=> $a['total_usage'];
        });

        if (!function_exists('formatBytes')) {
            throw new Exception("포맷 변환 함수(formatBytes)를 찾을 수 없습니다.");
        }
        foreach ($results as &$res) {
            $res['disk_usage_formatted'] = formatBytes($res['disk_usage']);
            $res['db_usage_formatted'] = formatBytes($res['db_usage']);
            $res['total_usage_formatted'] = formatBytes($res['total_usage']);
        }
        unset($res); // 마지막 요소 참조 해제

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 디스크 상세 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_disk_details') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $shop_id = (int)($_GET['shop_id'] ?? 0);

    if (!$shop_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '상점 ID가 필요합니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $subdomain = $stmt->fetchColumn();

        if (!$subdomain) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => '상점을 찾을 수 없습니다.']);
            exit;
        }

        $shop_dir = SHOP_UPLOADS_DIR . "/" . $subdomain;
        if (!is_dir($shop_dir)) {
            echo json_encode(['status' => 'success', 'data' => ['large_files' => [], 'unoptimized_files' => [], 'other_files' => []]]);
            exit;
        }

        $large_files = [];
        $unoptimized_files = [];
        $other_files = [];
        $large_file_threshold = 1024 * 1024; // 1MB
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($shop_dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            $file_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname());
            $is_image = in_array(strtolower($file->getExtension()), $image_extensions);
            $file_info = ['path' => $file_path, 'size_formatted' => formatBytes($file->getSize()), 'is_image' => $is_image];

            if (in_array(strtolower($file->getExtension()), $image_extensions)) {
                if ($file->getSize() > $large_file_threshold) $large_files[] = $file_info;
                if (strtolower($file->getExtension()) !== 'jpg') $unoptimized_files[] = $file_info;
            } else {
                $other_files[] = $file_info;
            }
        }

        echo json_encode(['status' => 'success', 'data' => ['large_files' => $large_files, 'unoptimized_files' => $unoptimized_files, 'other_files' => $other_files]]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 디스크 무결성 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_disk_integrity') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $shop_id = (int)($_GET['shop_id'] ?? 0);

    if (!$shop_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '상점 ID가 필요합니다.']);
        exit;
    }

    if (!function_exists('analyzeShopDiskIntegrity')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '디스크 분석 함수(analyzeShopDiskIntegrity)를 찾을 수 없습니다.']);
        exit;
    }

    try {
        $result = analyzeShopDiskIntegrity($pdo, $shop_id);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 전체 시스템 디스크 무결성 일괄 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_system_integrity') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');

    if (!function_exists('analyzeSystemDiskIntegrity')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '시스템 디스크 분석 함수(analyzeSystemDiskIntegrity)를 찾을 수 없습니다.']);
        exit;
    }

    try {
        $result = analyzeSystemDiskIntegrity($pdo);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 잉여 디렉토리(상점 삭제 찌꺼기 등) 완전 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_system_directory') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $dir_path = $_POST['dir_path'] ?? '';

    if (empty($dir_path) || strpos($dir_path, SHOP_UPLOADS_URL) !== 0 || strpos($dir_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 폴더 경로입니다.']);
        exit;
    }

    $absolute_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $dir_path;
    if (function_exists('deleteDirectoryCompletely') && deleteDirectoryCompletely($absolute_path)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '폴더 삭제에 실패했습니다. (권한 문제 혹은 사용 중인 파일)']);
    }
    exit;
}

// [신규] 불필요한 파일 일괄 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_shop_files_bulk') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_paths = json_decode($_POST['file_paths'] ?? '[]', true);

    if (!is_array($file_paths)) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 데이터 형식입니다.']);
        exit;
    }

    $deleted_count = 0;
    foreach ($file_paths as $file_path) {
        // 보안: 상점 업로드 폴더 내의 파일만 삭제 허용 (Directory Traversal 방지)
        if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
            continue;
        }

        $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
        if (file_exists($absolute_path) && is_file($absolute_path)) {
            if (@unlink($absolute_path)) {
                $deleted_count++;
            }
        }
    }
    echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
    exit;
}

// [신규] 불필요한 파일 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_shop_file') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_path = $_POST['file_path'] ?? '';

    // 보안: 상점 업로드 폴더 내의 파일만 삭제 허용 (Directory Traversal 방지)
    if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
        exit;
    }

    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    if (file_exists($absolute_path)) {
        if (unlink($absolute_path)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '파일 삭제 권한이 없습니다.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
    }
    exit;
}

// [신규] 이미지 강제 리사이징(최적화) AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'resize_shop_image') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_path = $_POST['file_path'] ?? '';

    if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
        exit;
    }

    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    if (!file_exists($absolute_path)) {
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
        exit;
    }

    $ext = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
    $src_img = null;
    try {
        if ($ext === 'png') $src_img = @imagecreatefrompng($absolute_path);
        elseif ($ext === 'gif') $src_img = @imagecreatefromgif($absolute_path);
        elseif (in_array($ext, ['jpg', 'jpeg'])) $src_img = @imagecreatefromjpeg($absolute_path);
        elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src_img = @imagecreatefromwebp($absolute_path);
    } catch (Exception $e) {
    }

    if (!$src_img) {
        echo json_encode(['status' => 'error', 'message' => '처리할 수 없는 이미지 형식이거나 손상된 파일입니다.']);
        exit;
    }

    $width = imagesx($src_img);
    $height = imagesy($src_img);
    $max_dim = 1200; // 최대 해상도를 1200px로 제한하여 용량 압축

    $ratio = min($max_dim / $width, $max_dim / $height);
    $new_width = ($width > $max_dim || $height > $max_dim) ? (int)($width * $ratio) : $width;
    $new_height = ($width > $max_dim || $height > $max_dim) ? (int)($height * $ratio) : $height;

    $dst_img = imagecreatetruecolor($new_width, $new_height);

    // 투명도 배경을 흰색으로 채우기 (JPG 변환 대비)
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $white);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // 원래의 파일 포맷을 유지하며 덮어쓰기 (DB 링크 깨짐 방지)
    $success = false;
    if ($ext === 'png') {
        $success = imagepng($dst_img, $absolute_path, 8); // 최대 압축
    } elseif ($ext === 'gif') {
        $success = imagegif($dst_img, $absolute_path);
    } else {
        $success = imagejpeg($dst_img, $absolute_path, 85); // 85% 품질
    }

    imagedestroy($dst_img);
    imagedestroy($src_img);

    if ($success) {
        clearstatcache();
        $new_size = filesize($absolute_path);
        echo json_encode(['status' => 'success', 'new_size_formatted' => formatBytes($new_size)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '이미지 저장에 실패했습니다.']);
    }
    exit;
}

// [테이블 스키마 자동 업데이트] 읽음 여부 확인을 위한 컬럼 추가
try {
    $pdo->exec("ALTER TABLE shop_board ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
}

// [시스템 설정 자동 초기화] 메시지 템플릿 JSON 기본값 생성
// 향후 다양한 안내 메시지들을 통합 관리하기 위해 site_settings 테이블에 JSON 형태로 저장합니다.
try {
    // message_templates 키가 존재하는지 확인
    $stmt_check_tpl = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'message_templates'");
    $stmt_check_tpl->execute();
    if ($stmt_check_tpl->fetchColumn() == 0) {
        $default_templates = [
            'SHOP_STATUS_INACTIVE_SOON' => [
                'title' => '[중요 안내] 상점 이용 기간 만료 임박 (휴점) 안내',
                'content' => "사장님, 안녕하세요.\n\n운영 중이신 상점의 이용 기간이 \"월 사용료 혹은 추가 요금 미납\"으로 인해 곧 만료될 예정입니다.\n\n- 상점 명 : {shop_name}\n- 휴점 예정일: {expiring_date}\n\n규정에 따라서, 상점 이용 기간이 만료되면 상점은 \"휴점\"상태가 되며, \"휴점\"후 {SHOP_CLOSED_AFTER_INACTIVE}일 후에는 \"폐점\"처리 됩니다. \"폐점\"처리된 상점은 모든 데이터가 삭제됩니다.\n\n따라서 서비스가 중단되지 않도록 기한 내에 연장을 부탁드립니다. 자세한 청구 내역 확인 및 납입은 관리자 대시보드의 [결제 관리] 메뉴에서 하실 수 있습니다.\n\n감사합니다."
            ],
            'SHOP_STATUS_CLOSED_SOON' => [
                'title' => '[중요 안내] \"휴점\" 처리 및 \"폐점\" 예정 안내',
                'content' => "사장님, 안녕하세요.\n\n규정에 따라서, 운영 중이신 상점이 \"월 사용료 혹은 추가 요금 미납\"으로 \"휴점\"되었습니다. 연체된 모든 요금을 완납하시면, 바로 \"정상 영업\" 상태로 됩니다.\n\n그리고 \"휴점\" 후 {SHOP_CLOSED_AFTER_INACTIVE}일이 경과하면 \"폐점\"됩니다.\n\n- 상점 명 : {shop_name}\n- 폐점 예정일: {expiring_date}\n\n\"폐점\" 후에는 상점의 모든 데이터가 삭제되며, 복구는 불가능 합니다. 자세한 청구 내역 확인 및 납입은 관리자 대시보드의 [결제 관리] 메뉴에서 하실 수 있습니다.\n\n감사합니다."
            ]
        ];

        // JSON 형식으로 인코딩하여 DB에 삽입 (한글 깨짐 방지)
        $json_templates = json_encode($default_templates, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('message_templates', ?)")->execute([$json_templates]);
    }
} catch (Exception $e) {
    // 테이블이 없거나 DB 에러 시 무시
}

// [시스템 설정 자동 초기화] 카테고리 JSON 기본값 생성
try {
    $stmt_check_cat = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'shop_categories'");
    $stmt_check_cat->execute();
    if ($stmt_check_cat->fetchColumn() == 0) {
        $default_categories = [
            'fnb'    => '음식점 / 카페',
            'realty' => '부동산 / 중개',
            'srv'    => '예약 / 서비스'
        ];
        $json_cats = json_encode($default_categories, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('shop_categories', ?)")->execute([$json_cats]);
    }
} catch (Exception $e) {
}

// [시스템 설정 자동 초기화] 이메일 템플릿 마이그레이션 (JSON 통합)
// 여러 row로 분산되어 있던 이메일 템플릿들을 email_templates 키값 하나에 JSON으로 통합합니다.
try {
    $stmt_check_email_tpl = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
    $stmt_check_email_tpl->execute();
    if ($stmt_check_email_tpl->fetchColumn() == 0) {
        $legacy_mapping = [
            SHOP_STATUS_APPLYING      => 'apply_email_template',
            SHOP_STATUS_TESTING       => 'testing_email_template',
            SHOP_STATUS_ACTIVE        => 'active_email_template',
            SHOP_STATUS_INACTIVE_SOON => 'inactive_warning_email_template',
            SHOP_STATUS_INACTIVE      => 'inactive_email_template',
            SHOP_STATUS_CLOSED_SOON   => 'closed_warning_email_template',
            SHOP_STATUS_CLOSED        => 'closed_email_template'
        ];

        $email_templates = [];
        foreach ($legacy_mapping as $new_key => $old_key) {
            $stmt_old = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = ?");
            $stmt_old->execute([$old_key]);
            $old_val = $stmt_old->fetchColumn();
            $email_templates[$new_key] = $old_val !== false ? $old_val : '';
        }
        $json_email_templates = json_encode($email_templates, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_email_templates]);
    }
} catch (Exception $e) {
}

// ---------------------------------------------------------
// [데이터 로드 섹션]
// ---------------------------------------------------------

// 0. 요약 위젯 (상점 수 통계)
$stmt_cat_counts = $pdo->query("SELECT category, COUNT(*) as cnt FROM shops WHERE status = 'active' GROUP BY category");
$cat_counts_raw = $stmt_cat_counts->fetchAll();
$total_active_shops = 0;
$cat_counts = [];
foreach ($cat_counts_raw as $row) {
    $cat = $row['category'] ?: '미지정';
    $cat_counts[$cat] = $row['cnt'];
    $total_active_shops += $row['cnt'];
}

// $shop_category_labels 가 공통 헤더에 정의되어 있지 않다면 DB(JSON)에서 로드
if (!isset($shop_category_labels)) {
    $stmt_cat_labels = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
    $json_labels = $stmt_cat_labels->fetchColumn();
    $shop_category_labels = $json_labels ? json_decode($json_labels, true) : [
        'fnb' => '외식/배달', 'realty' => '부동산/중개', 'srv' => '예약/서비스'
    ];
}

// 1. 신규 입점 상점 리스트 (필터 및 페이징 적용)
$filter_date = $_GET['filter_date'] ?? 'today';
$new_shop_page = max(1, (int)($_GET['new_shop_page'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 10;
$offset = ($new_shop_page - 1) * $limit;

$date_condition = ($filter_date === '7days') ? "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" : "DATE(created_at) = CURDATE()";

$stmt_new_count = $pdo->query("SELECT COUNT(*) FROM shops WHERE $date_condition");
$total_new_shops = $stmt_new_count->fetchColumn();
$total_new_pages = ceil($total_new_shops / $limit) ?: 1;

$stmt_new = $pdo->query("
    SELECT id, shop_name, subdomain, manager_name, phone_mobile, created_at 
    FROM shops 
    WHERE $date_condition 
    ORDER BY id DESC 
    LIMIT $limit OFFSET $offset
");
$new_shops = $stmt_new->fetchAll();

// 2. 상점에서 본사로 온 최근 메시지 (상위 5개)
$stmt_msg = $pdo->query("
    SELECT b.id, b.shop_id, b.title, b.created_at, b.is_read, s.shop_name 
    FROM shop_board b
    JOIN shops s ON b.shop_id = s.id
    WHERE b.type = 'message' AND b.sender_type = 'shop'
    ORDER BY b.created_at DESC 
    LIMIT 5
");
$recent_messages = $stmt_msg->fetchAll();

// 3. 만료 임박 상점 리스트 (config.php의 SHOP_STATUS_INACTIVE_SOON_DAYS 상수 사용)
$days_limit = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
$stmt_expiring = $pdo->query("
    SELECT s.id, s.shop_name, s.manager_name, s.phone_mobile, p.max_expiring_date 
    FROM shops s 
    JOIN (
        " . SQL_EXPIRING_SUBQUERY . "
    ) p ON s.id = p.shop_id 
    WHERE s.status = 'active' AND p." . SQL_EXPIRING_CONDITION . "
    ORDER BY p.max_expiring_date ASC
");
$expiring_shops = $stmt_expiring->fetchAll();
?>

<!-- 
  [UI 뷰 섹션] 향후 위젯을 추가할 때는 <div class="col-12 col-xl-6"> 형태의 블록을 복사해서 이어붙이면 됩니다. 
-->

<!-- 상단 통계 위젯 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 opacity-75 small">전체 운영중 상점</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($total_active_shops) ?></h3>
                </div>
                <i class="bi bi-shop fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <?php foreach ($shop_category_labels as $key => $label): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 text-muted small"><?= htmlspecialchars($label) ?></h6>
                    <h3 class="mb-0 fw-bold text-dark"><?= number_format($cat_counts[$key] ?? 0) ?></h3>
                </div>
                <i class="bi bi-tags text-primary opacity-25 fs-1"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- [위젯 1] 입점 상점 리스트 -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100 flex-column d-flex">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-primary"><i class="bi bi-shop me-2"></i>신규 입점 상점 <span
                        class="badge bg-primary ms-1"><?= $total_new_shops ?></span></h6>
                <form method="GET" class="d-flex align-items-center" id="filterForm">
                    <input type="hidden" name="page" value="admin_dashboard">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="filter_date" id="filter_today" value="today"
                            <?= $filter_date === 'today' ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <label class="btn btn-outline-primary btn-sm" for="filter_today">오늘</label>

                        <input type="radio" class="btn-check" name="filter_date" id="filter_7days" value="7days"
                            <?= $filter_date === '7days' ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <label class="btn btn-outline-primary btn-sm" for="filter_7days">최근 7일</label>
                    </div>
                </form>
            </div>
            <div class="card-body p-0 d-flex flex-column flex-grow-1">
                <div class="list-group list-group-flush flex-grow-1">
                    <?php if (empty($new_shops)): ?>
                    <div class="p-5 text-center text-muted small my-auto"><i
                            class="bi bi-emoji-frown fs-2 d-block mb-2 opacity-50"></i>해당 기간에 입점한 상점이 없습니다.</div>
                    <?php else: ?>
                    <?php foreach ($new_shops as $shop): ?>
                    <a href="admin_view.php?page=manage_shop&id=<?= $shop['id'] ?>"
                        class="list-group-item list-group-item-action p-3">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($shop['shop_name']) ?> <span
                                    class="text-muted fw-normal small">(<?= htmlspecialchars($shop['subdomain']) ?>)</span>
                            </h6>
                            <small class="text-muted"><?= date('Y.m.d H:i', strtotime($shop['created_at'])) ?></small>
                        </div>
                        <p class="mb-1 small text-secondary">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($shop['manager_name'] ?? '정보없음') ?> |
                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($shop['phone_mobile'] ?? '없음') ?>
                        </p>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 페이징 영역 -->
                <?php if ($total_new_pages > 1): ?>
                <div class="card-footer bg-white border-top-0 py-3 mt-auto">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $total_new_pages; $i++): ?>
                            <li class="page-item <?= ($i == $new_shop_page) ? 'active' : '' ?>">
                                <a class="page-link shadow-none <?= ($i == $new_shop_page) ? 'bg-primary border-primary text-white' : 'text-dark border-0' ?> mx-1 rounded-circle text-center"
                                    style="width: 30px;"
                                    href="admin_view.php?page=admin_dashboard&filter_date=<?= $filter_date ?>&new_shop_page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- [위젯 2] 결제 만료 임박 상점 -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100 border-top border-4 border-danger">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-danger"><i class="bi bi-credit-card-2-front me-2"></i>결제 만료 임박 상점
                    (<?= $days_limit ?>일 이내) <span class="badge bg-danger ms-1"><?= count($expiring_shops) ?></span>
                </h6>
                <a href="/admin/admin_view.php?page=manage_expiring_shops"
                    class="btn btn-sm btn-light text-danger fw-bold">임박 탭 이동</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-ps24 table-hover align-middle mb-0">
                        <thead>
                            <tr class="small">
                                <th class="t-center">상점명</th>
                                <th class="t-center">점주/연락처</th>
                                <th class="t-center">만료일자</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiring_shops)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted"><i
                                        class="bi bi-check-circle fs-3 d-block mb-2 text-success opacity-50"></i>만료 임박
                                    상점이 없습니다.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expiring_shops as $es): ?>
                            <tr class="small">
                                <td class="ps-3 t-center"><a
                                        href="admin_view.php?page=manage_shop&id=<?= $es['id'] ?>&view=payments"
                                        class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($es['shop_name']) ?></a>
                                </td>
                                <td class="t-center"><?= htmlspecialchars($es['manager_name'] ?? '정보없음') ?><br><span
                                        class="text-muted"
                                        style="font-size: 0.75rem;"><?= htmlspecialchars($es['phone_mobile'] ?? '-') ?></span>
                                </td>
                                <td class="t-center fw-bold text-danger"><?= $es['max_expiring_date'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- [위젯 3] 상점에서 본사로 보낸 최근 메시지 -->
    <div class="col-12 col-xl-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-success"><i class="bi bi-chat-left-dots-fill me-2"></i>상점에서 온 최근 메시지 (최근 5건)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recent_messages)): ?>
                    <div class="p-5 text-center text-muted small"><i
                            class="bi bi-mailbox fs-2 d-block mb-2 opacity-50"></i>수신된 메시지가 없습니다.</div>
                    <?php else: ?>
                    <?php foreach ($recent_messages as $msg): ?>
                    <a href="admin_view.php?page=manage_shop&id=<?= $msg['shop_id'] ?>&view=message"
                        class="list-group-item list-group-item-action p-3">
                        <div class="row align-items-center">
                            <div class="col-md-2 col-12 mb-2 mb-md-0">
                                <span class="badge bg-light text-dark border"><i
                                        class="bi bi-shop text-success me-1"></i>
                                    <?= htmlspecialchars($msg['shop_name']) ?></span>
                            </div>
                            <div class="col-md-8 col-12 mb-2 mb-md-0">
                                <h6
                                    class="mb-0 text-dark text-truncate <?= empty($msg['is_read']) ? 'fw-bold' : 'fw-normal' ?>">
                                    <?php if (empty($msg['is_read'])): ?><span class="badge bg-danger rounded-pill me-1"
                                        style="font-size:0.6rem;">N</span><?php endif; ?>
                                    <?= htmlspecialchars($msg['title']) ?>
                                </h6>
                            </div>
                            <div class="col-md-2 col-12 text-md-end text-muted small">
                                <?= date('y.m.d H:i', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white border-top text-center py-2">
                <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>자세한 내용과 답변은 메시지를 클릭하여 개별 상점 관리
                    페이지에서 진행하세요.</span>
            </div>
        </div>
    </div>

    <!-- [위젯 4] 상점별 리소스 사용량 분석 -->
    <div class="col-12 col-xl-12">
        <div class="card border-0 shadow-sm border-top border-4 border-secondary">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-hdd-network me-2"></i>상점별 리소스 사용량 (디스크 & DB)</h6>
                <div>
                    <button type="button" id="btn-check-system-integrity"
                        class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3 shadow-sm me-2">
                        <i class="bi bi-shield-check me-1"></i>전체 서버 스캔
                    </button>
                    <button type="button" id="btn-check-resources"
                        class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3 shadow-sm">
                        <i class="bi bi-search me-1"></i>사용량 분석
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- 대기 및 안내 화면 -->
                <div id="resource-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div class="text-muted small fw-bold">서버의 물리적 파일 및 DB 용량을 분석 중입니다...<br>잠시만 기다려주세요.</div>
                </div>
                <div id="resource-empty" class="p-5 text-center text-muted small">
                    <i class="bi bi-bar-chart-steps fs-2 d-block mb-2 opacity-50"></i>우측 상단의 <strong>'사용량 분석'</strong>
                    버튼을 누르면 순위가 표시됩니다.
                </div>

                <!-- 결과 테이블 -->
                <div class="table-responsive d-none" id="resource-table-container">
                    <table class="table table-ps24 table-hover align-middle mb-0">
                        <thead>
                            <tr class="small">
                                <th>순위 / 상점명</th>
                                <th>디스크 용량 (이미지 등)</th>
                                <th>DB 용량 (텍스트 데이터)</th>
                                <th>총 사용량</th>
                            </tr>
                        </thead>
                        <tbody id="resource-tbody">
                            <!-- JS를 통해 동적 렌더링 됩니다. -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- [신규] 디스크/DB 무결성 검사 모달 -->
<div class="modal fade" id="diskCheckModal" tabindex="-1" aria-labelledby="diskCheckModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diskCheckModalLabel">디스크/DB 무결성 검사</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="diskCheckModalBody">
                <!-- AJAX 결과가 여기에 표시됩니다. -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 시스템 전체 무결성 검사 모달 -->
<div class="modal fade" id="sysIntegrityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>전체 시스템 무결성 검사 리포트</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sysIntegrityModalBody">
                <!-- AJAX 결과가 여기에 표시됩니다. -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCheck = document.getElementById('btn-check-resources');
    if (btnCheck) {
        btnCheck.addEventListener('click', async function() {
            const loading = document.getElementById('resource-loading');
            const empty = document.getElementById('resource-empty');
            const tableContainer = document.getElementById('resource-table-container');
            const tbody = document.getElementById('resource-tbody');

            // UI 상태 변경 (로딩 중)
            btnCheck.disabled = true;
            empty.classList.add('d-none');
            tableContainer.classList.add('d-none');
            loading.classList.remove('d-none');
            tbody.innerHTML = '';

            try {
                // [버그 수정] 순수한 JSON 응답을 받기 위해 관리자 뷰(HTML)를 통하지 않고 대시보드 파일을 직접 호출합니다.
                const response = await fetch('admin_dashboard.php?action=check_resources');
                const result = await response.json();

                if (result.status === 'success') {
                    const data = result.data;
                    if (data.length === 0) {
                        empty.innerHTML =
                            '<i class="bi bi-info-circle fs-2 d-block mb-2 opacity-50"></i>측정 가능한 리소스가 없습니다.';
                        empty.classList.remove('d-none');
                    } else {
                        let html = '';
                        data.forEach((item, index) => {
                            // 1~3위는 배지로 강조
                            let rankHtml = `<b class="text-muted">${index + 1}</b>`;
                            if (index === 0) rankHtml =
                                `<span class="badge bg-danger rounded-pill shadow-sm">1위</span>`;
                            else if (index === 1) rankHtml =
                                `<span class="badge bg-warning text-dark rounded-pill shadow-sm">2위</span>`;
                            else if (index === 2) rankHtml =
                                `<span class="badge bg-info text-dark rounded-pill shadow-sm">3위</span>`;

                            html += `
                                <tr class="small">
                                    <td class="ps-3 t-center">
                                        <span class="me-2 d-inline-block text-center" style="width: 35px;">${rankHtml}</span>
                                        <a href="admin_view.php?page=manage_shop&id=${item.id}" class="text-dark fw-bold text-decoration-none">${item.shop_name}</a>
                                    </td>
                                    <td class="t-center">
                                        ${item.disk_usage_formatted}
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2 rounded-pill check-disk-details" data-shop-id="${item.id}" data-shop-name="${item.shop_name}"><i class="bi bi-zoom-in"></i> 체크</button>
                                    </td>
                                    <td class="t-center">${item.db_usage_formatted}</td>
                                    <td class="t-center fw-bold text-danger">${item.total_usage_formatted}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                        tableContainer.classList.remove('d-none');
                    }
                } else {
                    alert('분석 중 오류가 발생했습니다: ' + result.message);
                    empty.classList.remove('d-none');
                }
            } catch (error) {
                console.error('AJAX 파싱 에러:', error);
                alert('서버 통신 오류가 발생했습니다. (관리자 권한 혹은 네트워크 상태를 확인하세요)');
                empty.classList.remove('d-none');
            } finally {
                // 로딩 종료 및 버튼 활성화
                loading.classList.add('d-none');
                btnCheck.disabled = false;
            }
        });
    }

    // [신규] 디스크 상세 분석 모달 이벤트 핸들러
    const resourceTbody = document.getElementById('resource-tbody');
    const diskCheckModalEl = document.getElementById('diskCheckModal');
    if (resourceTbody && diskCheckModalEl) {
        const diskCheckModal = new bootstrap.Modal(diskCheckModalEl);
        const diskCheckModalBody = document.getElementById('diskCheckModalBody');
        const diskCheckModalLabel = document.getElementById('diskCheckModalLabel');

        resourceTbody.addEventListener('click', function(event) {
            const button = event.target.closest('.check-disk-details');
            if (!button) return;

            const shopId = button.dataset.shopId;
            const shopName = button.dataset.shopName;

            diskCheckModalLabel.innerHTML =
                `<i class="bi bi-hdd-stack me-2"></i>디스크/DB 무결성 검사 : <span class="text-primary">${shopName}</span>`;
            diskCheckModalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted fw-bold">상점 파일 및 DB 무결성을 종합 분석 중입니다.<br>잠시만 기다려주세요...</p>
                    </div>
                `;
            diskCheckModal.show();

            // [개선] 두 가지 분석(용량/최적화, DB 무결성)을 병렬로 동시에 요청합니다.
            Promise.all([
                    fetch(`admin_dashboard.php?action=check_disk_details&shop_id=${shopId}`).then(
                        res => res.json()),
                    fetch(`admin_dashboard.php?action=check_disk_integrity&shop_id=${shopId}`).then(
                        res => res.json())
                ])
                .then(([detailsResult, integrityResult]) => {
                    let content = '';

                    // --- [1] 디스크 용량 및 최적화 분석 렌더링 ---
                    if (detailsResult.status === 'success') {
                        const {
                            large_files,
                            unoptimized_files,
                            other_files
                        } = detailsResult.data;

                        content +=
                            '<h6 class="fw-bold text-primary"><i class="bi bi-file-earmark-image me-2"></i>디스크 용량 및 최적화 분석</h6>';

                        if (large_files.length === 0 && unoptimized_files.length === 0 &&
                            other_files.length === 0) {
                            content +=
                                `<div class="alert alert-success text-center border-0 mb-4 py-3"><i class="bi bi-check-circle-fill fs-4 d-block mb-1"></i>최적화가 필요한 큰 파일이 없습니다.</div>`;
                        } else {
                            content +=
                                '<p class="small text-muted mb-3">아래는 최적화가 필요하거나 불필요할 수 있는 파일 목록입니다. (기준: 1MB 이상 또는 비-JPG 이미지)</p>';

                            const createFileList = (files, badgeClass) => {
                                let html =
                                    '<ul class="list-group list-group-flush small mb-4 border rounded">';
                                files.forEach(file => {
                                    let actions =
                                        `<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 ms-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button>`;
                                    if (file.is_image) {
                                        actions =
                                            `<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 ms-2 btn-resize-file" data-path="${file.path}"><i class="bi bi-arrows-angle-contract"></i> 최적화</button>` +
                                            actions;
                                    }
                                    html +=
                                        `<li class="list-group-item d-flex justify-content-between align-items-center"><code class="text-truncate" style="max-width: 55%;" title="${file.path}">${file.path}</code><div class="text-end"><span class="badge ${badgeClass} rounded-pill badge-size">${file.size_formatted}</span>${actions}</div></li>`;
                                });
                                html += '</ul>';
                                return html;
                            };

                            if (large_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-arrow-down-fill text-danger me-2"></i>용량이 큰 파일 (> 1MB)</h6>' +
                                    createFileList(large_files, 'bg-danger');
                            }
                            if (unoptimized_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-image-fill text-warning me-2"></i>최적화되지 않은 이미지 (Non-JPG)</h6>' +
                                    createFileList(unoptimized_files, 'bg-warning text-dark');
                            }
                            if (other_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-excel-fill text-secondary me-2"></i>기타 파일 (이미지 아님)</h6>' +
                                    createFileList(other_files, 'bg-secondary');
                            }
                        }
                    } else {
                        content +=
                            `<div class="alert alert-danger">용량 분석 오류: ${detailsResult.message}</div>`;
                    }

                    // --- [2] DB-파일 무결성 검사 렌더링 ---
                    content += `<div class="mt-4 pt-4 border-top">`;
                    if (integrityResult.status === 'success') {
                        const {
                            orphaned_files,
                            broken_links,
                            checked_tables
                        } = integrityResult.data;

                        content +=
                            '<h6 class="fw-bold text-info"><i class="bi bi-shield-check me-2"></i>DB-파일 무결성 검사</h6>';

                        if (checked_tables && checked_tables.length > 0) {
                            content +=
                                `<p class="small text-muted mb-3"><i class="bi bi-database me-1"></i> 검사 대상 테이블: <code>${checked_tables.join(', ')}</code></p>`;
                        }

                        if (orphaned_files.length === 0 && broken_links.length === 0) {
                            content +=
                                `<div class="alert alert-success border-0 py-3 mt-3 text-center"><i class="bi bi-check-circle-fill fs-4 d-block mb-1"></i>DB 기록과 실제 파일이 완벽하게 일치합니다.</div>`;
                        } else {
                            content +=
                                '<p class="small text-muted mb-3">DB 기록과 실제 파일의 일치 여부를 검사하여 불필요한 파일을 찾거나, 깨진 이미지 링크를 발견했습니다.</p>';

                            if (orphaned_files.length > 0) {
                                const orphanedPaths = orphaned_files.map(f => f.path);
                                content +=
                                    `
                                        <div class="d-flex justify-content-between align-items-end mb-1">
                                            <h6><i class="bi bi-question-circle-fill text-warning me-2"></i>DB에 없는 파일 (삭제 가능)</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-all-orphaned" data-paths='${JSON.stringify(orphanedPaths)}'><i class="bi bi-trash3"></i> 전체 일괄 삭제</button>
                                        </div>
                                        <p class="small text-muted mb-2">서버에 파일은 있지만, DB에 기록이 없어 사용되지 않는 것으로 추정되는 이미지입니다.</p>
                                        <ul class="list-group list-group-flush small mb-4 border rounded orphaned-file-list">`;
                                orphaned_files.forEach(file => {
                                    content +=
                                        `<li class="list-group-item d-flex justify-content-between align-items-center"><div><code class="text-truncate d-inline-block" style="max-width: 600px;" title="${file.path}">${file.path}</code></div><div><span class="badge bg-secondary rounded-pill me-2">${file.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button></div></li>`;
                                });
                                content += '</ul>';
                            }
                            if (broken_links.length > 0) {
                                content +=
                                    '<h6 class="fw-bold text-info"><i class="bi bi-link-45deg text-danger me-2"></i>깨진 이미지 링크 (파일 없음)</h6><p class="small text-muted mb-2">DB에는 기록되어 있으나 실제 파일이 존재하지 않습니다. <strong>해당 상점의 [메뉴 관리]에서 사진을 다시 재업로드</strong>하시면 링크가 덮어씌워지며 자동으로 복구됩니다.</p><ul class="list-group list-group-flush small border rounded">';
                                broken_links.forEach(link => {
                                    content +=
                                        `<li class="list-group-item"><span class="badge bg-light text-dark border me-2" title="DB 테이블명"><i class="bi bi-table me-1"></i>${link.table}</span><code title="${link.path}">${link.path}</code></li>`;
                                });
                                content += '</ul>';
                            }
                        }
                    } else {
                        content +=
                            `<div class="alert alert-danger">무결성 검사 오류: ${integrityResult.message}</div>`;
                    }
                    content += `</div>`;

                    diskCheckModalBody.innerHTML = content;
                })
                .catch(error => {
                    console.error('Check error:', error);
                    diskCheckModalBody.innerHTML =
                        `<div class="alert alert-danger">분석 중 통신 오류가 발생했습니다: ${error.message}</div>`;
                });
        });

        // [신규] 모달창 내부의 '삭제' 및 '최적화' 버튼 클릭 이벤트 (이벤트 위임)
        diskCheckModalBody.addEventListener('click', async function(e) {
            const deleteBtn = e.target.closest('.btn-delete-file');
            const resizeBtn = e.target.closest('.btn-resize-file');
            const bulkDeleteBtn = e.target.closest('.btn-delete-all-orphaned'); // 일괄 삭제 버튼

            if (deleteBtn) {
                if (!confirm('정말로 이 파일을 삭제하시겠습니까?\n(만약 현재 홈페이지에서 사용 중인 이미지라면 엑스박스가 뜰 수 있습니다)'))
                    return;
                const path = deleteBtn.dataset.path;
                const li = deleteBtn.closest('li');
                deleteBtn.disabled = true;

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_file', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        deleteBtn.disabled = false;
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    deleteBtn.disabled = false;
                }
            }

            if (bulkDeleteBtn) {
                if (!confirm('경고: 목록에 있는 모든 잉여 파일을 일괄 삭제하시겠습니까?\n(삭제 후 복구할 수 없습니다)')) return;
                const paths = bulkDeleteBtn.dataset.paths;
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>삭제 중...';

                const formData = new FormData();
                formData.append('file_paths', paths);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_files_bulk', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        alert(`총 ${result.deleted_count}개의 파일이 일괄 삭제되었습니다.`);
                        const ul = bulkDeleteBtn.closest('div').nextElementSibling
                            .nextElementSibling;
                        if (ul && ul.classList.contains('orphaned-file-list')) {
                            ul.innerHTML =
                                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>모두 삭제되었습니다.</li>';
                        }
                        bulkDeleteBtn.remove();
                    } else {
                        alert('일괄 삭제 실패: ' + result.message);
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                }
            }

            if (resizeBtn) {
                const path = resizeBtn.dataset.path;
                const badge = resizeBtn.closest('li').querySelector('.badge-size');

                resizeBtn.disabled = true;
                const originalText = resizeBtn.innerHTML;
                resizeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=resize_shop_image', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        badge.innerText = result.new_size_formatted;
                        badge.className = 'badge bg-success rounded-pill badge-size';
                        resizeBtn.remove(); // 최적화 완료 후 버튼은 제거하여 중복 클릭 방지
                    } else {
                        alert('최적화 실패: ' + result.message);
                        resizeBtn.disabled = false;
                        resizeBtn.innerHTML = originalText;
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    resizeBtn.disabled = false;
                    resizeBtn.innerHTML = originalText;
                }
            }
        });
    }

    // [신규] 시스템 전체 무결성 검사 버튼 이벤트
    const btnCheckSys = document.getElementById('btn-check-system-integrity');
    const sysIntegrityModalEl = document.getElementById('sysIntegrityModal');
    if (btnCheckSys && sysIntegrityModalEl) {
        const sysModal = new bootstrap.Modal(sysIntegrityModalEl);
        const sysModalBody = document.getElementById('sysIntegrityModalBody');

        btnCheckSys.addEventListener('click', async function() {
            sysModalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status"></div>
                        <h5 class="mt-4 text-dark fw-bold">전체 서버 무결성 스캔 중...</h5>
                        <p class="text-muted mb-0">서버 내의 모든 상점 DB와 물리적 파일 시스템을 교차 검증하고 있습니다.<br>데이터 양에 따라 다소 시간이 소요될 수 있습니다.</p>
                    </div>
                `;
            sysModal.show();

            try {
                const res = await fetch('admin_dashboard.php?action=check_system_integrity');
                const result = await res.json();

                if (result.status === 'success') {
                    const {
                        orphaned_files,
                        broken_links,
                        orphaned_directories,
                        checked_tables
                    } = result.data;
                    let content = '';

                    if (checked_tables && checked_tables.length > 0) {
                        content +=
                            `<p class="small text-muted mb-3"><i class="bi bi-database me-1"></i> 검사 대상 테이블: <code>${checked_tables.join(', ')}</code></p>`;
                    }

                    if (orphaned_files.length === 0 && broken_links.length === 0 &&
                        orphaned_directories.length === 0) {
                        content =
                            `<div class="alert alert-success text-center border-0 py-5"><i class="bi bi-check-circle-fill fs-1 d-block mb-3"></i><h4 class="fw-bold">퍼펙트!</h4>시스템 내의 모든 DB와 파일이 완벽하게 일치합니다.</div>`;
                    } else {
                        content +=
                            `<p class="small text-muted mb-4"><i class="bi bi-info-circle me-1"></i> 전체 상점의 데이터베이스 기록과 서버의 물리적 파일을 교차 검증한 결과입니다.</p>`;

                        if (orphaned_directories.length > 0) {
                            content += `
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-danger"><i class="bi bi-folder-x me-2"></i>주인 잃은 잉여 폴더 (상점 삭제 잔재물) <span class="badge bg-danger">${orphaned_directories.length}건</span></h6>
                                        <ul class="list-group list-group-flush small border rounded">`;
                            orphaned_directories.forEach(dir => {
                                content +=
                                    `<li class="list-group-item d-flex justify-content-between align-items-center"><code>${dir.path}</code><div><span class="badge bg-secondary rounded-pill me-2">${dir.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-sys-dir" data-path="${dir.path}"><i class="bi bi-trash"></i> 폴더 전체 삭제</button></div></li>`;
                            });
                            content += `</ul></div>`;
                        }

                        if (orphaned_files.length > 0) {
                            const orphanedPaths = orphaned_files.map(f => f.path);
                            const orphanedPathsStr = JSON.stringify(orphanedPaths).replace(/'/g,
                                "&apos;");
                            content +=
                                `
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-end mb-1">
                                            <h6 class="fw-bold text-warning mb-0"><i class="bi bi-file-earmark-x me-2"></i>DB에 등록되지 않은 잉여 파일 <span class="badge bg-warning text-dark">${orphaned_files.length}건</span></h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-all-sys-orphaned" data-paths='${orphanedPathsStr}'><i class="bi bi-trash3"></i> 전체 일괄 삭제</button>
                                        </div>
                                        <ul class="list-group list-group-flush small border rounded mt-2" style="max-height: 300px; overflow-y: auto;">`;
                            orphaned_files.forEach(file => {
                                content +=
                                    `<li class="list-group-item d-flex justify-content-between align-items-center"><div><span class="badge bg-light text-dark border me-2">${file.shop_name}</span><code>${file.path}</code></div><div><span class="badge bg-secondary rounded-pill me-2">${file.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button></div></li>`;
                            });
                            content += `</ul></div>`;
                        }

                            if (broken_links.length > 0) {
                            content +=
                                `
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-danger"><i class="bi bi-link-45deg me-2"></i>파일이 유실된 깨진 DB 링크 (엑스박스) <span class="badge bg-danger">${broken_links.length}건</span></h6>
                                        <p class="small text-muted mb-2">해당 상점의 관리 페이지에서 <strong>사진을 다시 재업로드</strong>하거나, 더 이상 팔지 않는 메뉴라면 <strong>해당 메뉴를 삭제</strong>하시면 해결됩니다.</p>
                                        <ul class="list-group list-group-flush small border rounded" style="max-height: 200px; overflow-y: auto;">`;
                            broken_links.forEach(link => {
                                content +=
                                    `<li class="list-group-item">
                                        <span class="badge bg-light text-dark border me-2" title="상점명">${link.shop_name}</span>
                                        <span class="badge bg-secondary text-white border me-2" title="DB 테이블명"><i class="bi bi-table me-1"></i>${link.table}</span>
                                        <a href="javascript:void(0);" class="text-decoration-none fw-bold text-primary" onclick="if(window.parent && typeof window.parent.showCommonImageModal === 'function') { window.parent.showCommonImageModal('${link.path}'); } else if(typeof showCommonImageModal === 'function') { showCommonImageModal('${link.path}'); }">${link.path}</a>
                                    </li>`;
                            });
                            content += `</ul></div>`;
                        }
                    }
                    sysModalBody.innerHTML = content;
                } else {
                    sysModalBody.innerHTML =
                        `<div class="alert alert-danger">${result.message}</div>`;
                }
            } catch (err) {
                sysModalBody.innerHTML = `<div class="alert alert-danger">서버 통신 오류가 발생했습니다.</div>`;
            }
        });

        // [신규] 전체 시스템 모달 내 삭제 버튼 이벤트 위임 (파일 삭제 및 잉여 폴더 완전 삭제)
        sysModalBody.addEventListener('click', async function(e) {
            const delDirBtn = e.target.closest('.btn-delete-sys-dir');
            const delFileBtn = e.target.closest('.btn-delete-file');
            const bulkDeleteBtn = e.target.closest('.btn-delete-all-sys-orphaned');

            if (delDirBtn) {
                if (!confirm(
                        '경고: 이 폴더 안의 모든 파일과 하위 폴더가 영구적으로 삭제됩니다.\n(삭제된 상점의 잔재물일 확률이 높습니다)\n정말 삭제하시겠습니까?'
                    )) return;
                const path = delDirBtn.dataset.path;
                const li = delDirBtn.closest('li');
                delDirBtn.disabled = true;

                const formData = new FormData();
                formData.append('dir_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_system_directory', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        delDirBtn.disabled = false;
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    delDirBtn.disabled = false;
                }
            }

            if (delFileBtn) {
                if (!confirm('정말로 이 파일을 삭제하시겠습니까?')) return;
                const path = delFileBtn.dataset.path;
                const li = delFileBtn.closest('li');
                delFileBtn.disabled = true;

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_file', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        delFileBtn.disabled = false;
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    delFileBtn.disabled = false;
                }
            }

            if (bulkDeleteBtn) {
                if (!confirm('경고: 목록에 있는 모든 잉여 파일을 일괄 삭제하시겠습니까?\\n(삭제 후 복구할 수 없습니다)')) return;
                const paths = bulkDeleteBtn.dataset.paths;
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>삭제 중...';

                const formData = new FormData();
                formData.append('file_paths', paths);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_files_bulk', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        alert(`총 ${result.deleted_count}개의 파일이 일괄 삭제되었습니다.`);
                        const ul = bulkDeleteBtn.closest('.mb-4').querySelector('ul');
                        if (ul) {
                            ul.innerHTML =
                                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>모두 삭제되었습니다.</li>';
                        }
                        bulkDeleteBtn.remove();
                    } else {
                        alert('일괄 삭제 실패: ' + result.message);
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                }
            }
        });
    }
});
=======
<?php

/**
 * KShops24 슈퍼 관리자 대시보드 (admin_dashboard.php)
 * - admin_view.php를 통해 로드됩니다.
 * - 기능: 오늘 입점한 상점, 상점에서 온 메시지, 결제 만료 임박 상점 현황 등 요약 제공
 * - 모듈형(Grid)으로 설계되어 향후 통계/그래프 등의 위젯 추가가 용이합니다.
 */

// [버그 수정] AJAX 단독 호출을 허용하기 위해, $pdo가 없으면 공통 헤더를 로드하여 초기화합니다.
// 이를 통해 admin_view.php의 HTML 껍데기가 섞여 JSON 파싱 에러가 나는 문제를 원천 차단합니다.
if (!isset($pdo)) {
    require_once __DIR__ . '/../common/admin_common_header.php';
}

// [신규] 리소스 사용량 체크 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_resources') {
    if (ob_get_level()) ob_clean(); // 다른 경고 메시지가 섞이는 것을 차단
    header('Content-Type: application/json');
    $results = [];
    try {
        // 모든 상점 목록 가져오기 (필요 시 active 상점만)
        $stmt_shops = $pdo->query("SELECT id, shop_name, subdomain FROM shops");
        $all_shops = $stmt_shops->fetchAll();

        if (!function_exists('getShopResourceUsage')) {
            throw new Exception("리소스 측정 함수(getShopResourceUsage)를 찾을 수 없습니다.");
        }

        foreach ($all_shops as $shop) {
            $usage = getShopResourceUsage($pdo, $shop['id']);
            $total_usage = $usage['disk'] + $usage['db'];
            if ($total_usage > 0) { // 사용량이 0인 상점은 제외
                $results[] = [
                    'id' => $shop['id'],
                    'shop_name' => $shop['shop_name'],
                    'disk_usage' => $usage['disk'],
                    'db_usage' => $usage['db'],
                    'total_usage' => $total_usage
                ];
            }
        }

        // 총 사용량 기준으로 내림차순 정렬
        usort($results, function ($a, $b) {
            return $b['total_usage'] <=> $a['total_usage'];
        });

        if (!function_exists('formatBytes')) {
            throw new Exception("포맷 변환 함수(formatBytes)를 찾을 수 없습니다.");
        }
        foreach ($results as &$res) {
            $res['disk_usage_formatted'] = formatBytes($res['disk_usage']);
            $res['db_usage_formatted'] = formatBytes($res['db_usage']);
            $res['total_usage_formatted'] = formatBytes($res['total_usage']);
        }
        unset($res); // 마지막 요소 참조 해제

        echo json_encode(['status' => 'success', 'data' => $results]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 디스크 상세 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_disk_details') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $shop_id = (int)($_GET['shop_id'] ?? 0);

    if (!$shop_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '상점 ID가 필요합니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $subdomain = $stmt->fetchColumn();

        if (!$subdomain) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => '상점을 찾을 수 없습니다.']);
            exit;
        }

        $shop_dir = SHOP_UPLOADS_DIR . "/" . $subdomain;
        if (!is_dir($shop_dir)) {
            echo json_encode(['status' => 'success', 'data' => ['large_files' => [], 'unoptimized_files' => [], 'other_files' => []]]);
            exit;
        }

        $large_files = [];
        $unoptimized_files = [];
        $other_files = [];
        $large_file_threshold = 1024 * 1024; // 1MB
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($shop_dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            $file_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname());
            $is_image = in_array(strtolower($file->getExtension()), $image_extensions);
            $file_info = ['path' => $file_path, 'size_formatted' => formatBytes($file->getSize()), 'is_image' => $is_image];

            if (in_array(strtolower($file->getExtension()), $image_extensions)) {
                if ($file->getSize() > $large_file_threshold) $large_files[] = $file_info;
                if (strtolower($file->getExtension()) !== 'jpg') $unoptimized_files[] = $file_info;
            } else {
                $other_files[] = $file_info;
            }
        }

        echo json_encode(['status' => 'success', 'data' => ['large_files' => $large_files, 'unoptimized_files' => $unoptimized_files, 'other_files' => $other_files]]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 디스크 무결성 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_disk_integrity') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $shop_id = (int)($_GET['shop_id'] ?? 0);

    if (!$shop_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '상점 ID가 필요합니다.']);
        exit;
    }

    if (!function_exists('analyzeShopDiskIntegrity')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '디스크 분석 함수(analyzeShopDiskIntegrity)를 찾을 수 없습니다.']);
        exit;
    }

    try {
        $result = analyzeShopDiskIntegrity($pdo, $shop_id);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 전체 시스템 디스크 무결성 일괄 분석 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'check_system_integrity') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');

    if (!function_exists('analyzeSystemDiskIntegrity')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '시스템 디스크 분석 함수(analyzeSystemDiskIntegrity)를 찾을 수 없습니다.']);
        exit;
    }

    try {
        $result = analyzeSystemDiskIntegrity($pdo);
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// [신규] 잉여 디렉토리(상점 삭제 찌꺼기 등) 완전 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_system_directory') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $dir_path = $_POST['dir_path'] ?? '';

    if (empty($dir_path) || strpos($dir_path, SHOP_UPLOADS_URL) !== 0 || strpos($dir_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 폴더 경로입니다.']);
        exit;
    }

    $absolute_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $dir_path;
    if (function_exists('deleteDirectoryCompletely') && deleteDirectoryCompletely($absolute_path)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '폴더 삭제에 실패했습니다. (권한 문제 혹은 사용 중인 파일)']);
    }
    exit;
}

// [신규] 불필요한 파일 일괄 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_shop_files_bulk') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_paths = json_decode($_POST['file_paths'] ?? '[]', true);

    if (!is_array($file_paths)) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 데이터 형식입니다.']);
        exit;
    }

    $deleted_count = 0;
    foreach ($file_paths as $file_path) {
        // 보안: 상점 업로드 폴더 내의 파일만 삭제 허용 (Directory Traversal 방지)
        if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
            continue;
        }

        $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
        if (file_exists($absolute_path) && is_file($absolute_path)) {
            if (@unlink($absolute_path)) {
                $deleted_count++;
            }
        }
    }
    echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
    exit;
}

// [신규] 불필요한 파일 삭제 AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'delete_shop_file') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_path = $_POST['file_path'] ?? '';

    // 보안: 상점 업로드 폴더 내의 파일만 삭제 허용 (Directory Traversal 방지)
    if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
        exit;
    }

    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    if (file_exists($absolute_path)) {
        if (unlink($absolute_path)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '파일 삭제 권한이 없습니다.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
    }
    exit;
}

// [신규] 이미지 강제 리사이징(최적화) AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'resize_shop_image') {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    $file_path = $_POST['file_path'] ?? '';

    if (empty($file_path) || strpos($file_path, SHOP_UPLOADS_URL) !== 0 || strpos($file_path, '..') !== false) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
        exit;
    }

    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    if (!file_exists($absolute_path)) {
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
        exit;
    }

    $ext = strtolower(pathinfo($absolute_path, PATHINFO_EXTENSION));
    $src_img = null;
    try {
        if ($ext === 'png') $src_img = @imagecreatefrompng($absolute_path);
        elseif ($ext === 'gif') $src_img = @imagecreatefromgif($absolute_path);
        elseif (in_array($ext, ['jpg', 'jpeg'])) $src_img = @imagecreatefromjpeg($absolute_path);
        elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src_img = @imagecreatefromwebp($absolute_path);
    } catch (Exception $e) {
    }

    if (!$src_img) {
        echo json_encode(['status' => 'error', 'message' => '처리할 수 없는 이미지 형식이거나 손상된 파일입니다.']);
        exit;
    }

    $width = imagesx($src_img);
    $height = imagesy($src_img);
    $max_dim = 1200; // 최대 해상도를 1200px로 제한하여 용량 압축

    $ratio = min($max_dim / $width, $max_dim / $height);
    $new_width = ($width > $max_dim || $height > $max_dim) ? (int)($width * $ratio) : $width;
    $new_height = ($width > $max_dim || $height > $max_dim) ? (int)($height * $ratio) : $height;

    $dst_img = imagecreatetruecolor($new_width, $new_height);

    // 투명도 배경을 흰색으로 채우기 (JPG 변환 대비)
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $white);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // 원래의 파일 포맷을 유지하며 덮어쓰기 (DB 링크 깨짐 방지)
    $success = false;
    if ($ext === 'png') {
        $success = imagepng($dst_img, $absolute_path, 8); // 최대 압축
    } elseif ($ext === 'gif') {
        $success = imagegif($dst_img, $absolute_path);
    } else {
        $success = imagejpeg($dst_img, $absolute_path, 85); // 85% 품질
    }

    imagedestroy($dst_img);
    imagedestroy($src_img);

    if ($success) {
        clearstatcache();
        $new_size = filesize($absolute_path);
        echo json_encode(['status' => 'success', 'new_size_formatted' => formatBytes($new_size)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '이미지 저장에 실패했습니다.']);
    }
    exit;
}

// [테이블 스키마 자동 업데이트] 읽음 여부 확인을 위한 컬럼 추가
try {
    $pdo->exec("ALTER TABLE shop_board ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {
}

// [시스템 설정 자동 초기화] 메시지 템플릿 JSON 기본값 생성
// 향후 다양한 안내 메시지들을 통합 관리하기 위해 site_settings 테이블에 JSON 형태로 저장합니다.
try {
    // message_templates 키가 존재하는지 확인
    $stmt_check_tpl = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'message_templates'");
    $stmt_check_tpl->execute();
    if ($stmt_check_tpl->fetchColumn() == 0) {
        $default_templates = [
            'SHOP_STATUS_INACTIVE_SOON' => [
                'title' => '[중요 안내] 상점 이용 기간 만료 임박 (휴점) 안내',
                'content' => "사장님, 안녕하세요.\n\n운영 중이신 상점의 이용 기간이 \"월 사용료 혹은 추가 요금 미납\"으로 인해 곧 만료될 예정입니다.\n\n- 상점 명 : {shop_name}\n- 휴점 예정일: {expiring_date}\n\n규정에 따라서, 상점 이용 기간이 만료되면 상점은 \"휴점\"상태가 되며, \"휴점\"후 {SHOP_CLOSED_AFTER_INACTIVE}일 후에는 \"폐점\"처리 됩니다. \"폐점\"처리된 상점은 모든 데이터가 삭제됩니다.\n\n따라서 서비스가 중단되지 않도록 기한 내에 연장을 부탁드립니다. 자세한 청구 내역 확인 및 납입은 관리자 대시보드의 [결제 관리] 메뉴에서 하실 수 있습니다.\n\n감사합니다."
            ],
            'SHOP_STATUS_CLOSED_SOON' => [
                'title' => '[중요 안내] \"휴점\" 처리 및 \"폐점\" 예정 안내',
                'content' => "사장님, 안녕하세요.\n\n규정에 따라서, 운영 중이신 상점이 \"월 사용료 혹은 추가 요금 미납\"으로 \"휴점\"되었습니다. 연체된 모든 요금을 완납하시면, 바로 \"정상 영업\" 상태로 됩니다.\n\n그리고 \"휴점\" 후 {SHOP_CLOSED_AFTER_INACTIVE}일이 경과하면 \"폐점\"됩니다.\n\n- 상점 명 : {shop_name}\n- 폐점 예정일: {expiring_date}\n\n\"폐점\" 후에는 상점의 모든 데이터가 삭제되며, 복구는 불가능 합니다. 자세한 청구 내역 확인 및 납입은 관리자 대시보드의 [결제 관리] 메뉴에서 하실 수 있습니다.\n\n감사합니다."
            ]
        ];

        // JSON 형식으로 인코딩하여 DB에 삽입 (한글 깨짐 방지)
        $json_templates = json_encode($default_templates, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('message_templates', ?)")->execute([$json_templates]);
    }
} catch (Exception $e) {
    // 테이블이 없거나 DB 에러 시 무시
}

// [시스템 설정 자동 초기화] 카테고리 JSON 기본값 생성
try {
    $stmt_check_cat = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'shop_categories'");
    $stmt_check_cat->execute();
    if ($stmt_check_cat->fetchColumn() == 0) {
        $default_categories = [
            'fnb'    => '음식점 / 카페',
            'realty' => '부동산 / 중개',
            'srv'    => '예약 / 서비스'
        ];
        $json_cats = json_encode($default_categories, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('shop_categories', ?)")->execute([$json_cats]);
    }
} catch (Exception $e) {
}

// [시스템 설정 자동 초기화] 이메일 템플릿 마이그레이션 (JSON 통합)
// 여러 row로 분산되어 있던 이메일 템플릿들을 email_templates 키값 하나에 JSON으로 통합합니다.
try {
    $stmt_check_email_tpl = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE set_key = 'email_templates'");
    $stmt_check_email_tpl->execute();
    if ($stmt_check_email_tpl->fetchColumn() == 0) {
        $legacy_mapping = [
            SHOP_STATUS_APPLYING      => 'apply_email_template',
            SHOP_STATUS_TESTING       => 'testing_email_template',
            SHOP_STATUS_ACTIVE        => 'active_email_template',
            SHOP_STATUS_INACTIVE_SOON => 'inactive_warning_email_template',
            SHOP_STATUS_INACTIVE      => 'inactive_email_template',
            SHOP_STATUS_CLOSED_SOON   => 'closed_warning_email_template',
            SHOP_STATUS_CLOSED        => 'closed_email_template'
        ];

        $email_templates = [];
        foreach ($legacy_mapping as $new_key => $old_key) {
            $stmt_old = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = ?");
            $stmt_old->execute([$old_key]);
            $old_val = $stmt_old->fetchColumn();
            $email_templates[$new_key] = $old_val !== false ? $old_val : '';
        }
        $json_email_templates = json_encode($email_templates, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO site_settings (set_key, set_value) VALUES ('email_templates', ?)")->execute([$json_email_templates]);
    }
} catch (Exception $e) {
}

// ---------------------------------------------------------
// [데이터 로드 섹션]
// ---------------------------------------------------------

// 0. 요약 위젯 (상점 수 통계)
$stmt_cat_counts = $pdo->query("SELECT category, COUNT(*) as cnt FROM shops WHERE status = 'active' GROUP BY category");
$cat_counts_raw = $stmt_cat_counts->fetchAll();
$total_active_shops = 0;
$cat_counts = [];
foreach ($cat_counts_raw as $row) {
    $cat = $row['category'] ?: '미지정';
    $cat_counts[$cat] = $row['cnt'];
    $total_active_shops += $row['cnt'];
}

// $shop_category_labels 가 공통 헤더에 정의되어 있지 않다면 DB(JSON)에서 로드
if (!isset($shop_category_labels)) {
    $stmt_cat_labels = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'shop_categories'");
    $json_labels = $stmt_cat_labels->fetchColumn();
    $shop_category_labels = $json_labels ? json_decode($json_labels, true) : [
        'fnb' => '외식/배달', 'realty' => '부동산/중개', 'srv' => '예약/서비스'
    ];
}

// 1. 신규 입점 상점 리스트 (필터 및 페이징 적용)
$filter_date = $_GET['filter_date'] ?? 'today';
$new_shop_page = max(1, (int)($_GET['new_shop_page'] ?? 1));
$limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 10;
$offset = ($new_shop_page - 1) * $limit;

$date_condition = ($filter_date === '7days') ? "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" : "DATE(created_at) = CURDATE()";

$stmt_new_count = $pdo->query("SELECT COUNT(*) FROM shops WHERE $date_condition");
$total_new_shops = $stmt_new_count->fetchColumn();
$total_new_pages = ceil($total_new_shops / $limit) ?: 1;

$stmt_new = $pdo->query("
    SELECT id, shop_name, subdomain, manager_name, phone_mobile, created_at 
    FROM shops 
    WHERE $date_condition 
    ORDER BY id DESC 
    LIMIT $limit OFFSET $offset
");
$new_shops = $stmt_new->fetchAll();

// 2. 상점에서 본사로 온 최근 메시지 (상위 5개)
$stmt_msg = $pdo->query("
    SELECT b.id, b.shop_id, b.title, b.created_at, b.is_read, s.shop_name 
    FROM shop_board b
    JOIN shops s ON b.shop_id = s.id
    WHERE b.type = 'message' AND b.sender_type = 'shop'
    ORDER BY b.created_at DESC 
    LIMIT 5
");
$recent_messages = $stmt_msg->fetchAll();

// 3. 만료 임박 상점 리스트 (config.php의 SHOP_STATUS_INACTIVE_SOON_DAYS 상수 사용)
$days_limit = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
$stmt_expiring = $pdo->query("
    SELECT s.id, s.shop_name, s.manager_name, s.phone_mobile, p.max_expiring_date 
    FROM shops s 
    JOIN (
        " . SQL_EXPIRING_SUBQUERY . "
    ) p ON s.id = p.shop_id 
    WHERE s.status = 'active' AND p." . SQL_EXPIRING_CONDITION . "
    ORDER BY p.max_expiring_date ASC
");
$expiring_shops = $stmt_expiring->fetchAll();
?>

<!-- 
  [UI 뷰 섹션] 향후 위젯을 추가할 때는 <div class="col-12 col-xl-6"> 형태의 블록을 복사해서 이어붙이면 됩니다. 
-->

<!-- 상단 통계 위젯 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 opacity-75 small">전체 운영중 상점</h6>
                    <h3 class="mb-0 fw-bold"><?= number_format($total_active_shops) ?></h3>
                </div>
                <i class="bi bi-shop fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
    <?php foreach ($shop_category_labels as $key => $label): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-white" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 text-muted small"><?= htmlspecialchars($label) ?></h6>
                    <h3 class="mb-0 fw-bold text-dark"><?= number_format($cat_counts[$key] ?? 0) ?></h3>
                </div>
                <i class="bi bi-tags text-primary opacity-25 fs-1"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- [위젯 1] 입점 상점 리스트 -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100 flex-column d-flex">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-primary"><i class="bi bi-shop me-2"></i>신규 입점 상점 <span
                        class="badge bg-primary ms-1"><?= $total_new_shops ?></span></h6>
                <form method="GET" class="d-flex align-items-center" id="filterForm">
                    <input type="hidden" name="page" value="admin_dashboard">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="filter_date" id="filter_today" value="today"
                            <?= $filter_date === 'today' ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <label class="btn btn-outline-primary btn-sm" for="filter_today">오늘</label>

                        <input type="radio" class="btn-check" name="filter_date" id="filter_7days" value="7days"
                            <?= $filter_date === '7days' ? 'checked' : '' ?>
                            onchange="document.getElementById('filterForm').submit()">
                        <label class="btn btn-outline-primary btn-sm" for="filter_7days">최근 7일</label>
                    </div>
                </form>
            </div>
            <div class="card-body p-0 d-flex flex-column flex-grow-1">
                <div class="list-group list-group-flush flex-grow-1">
                    <?php if (empty($new_shops)): ?>
                    <div class="p-5 text-center text-muted small my-auto"><i
                            class="bi bi-emoji-frown fs-2 d-block mb-2 opacity-50"></i>해당 기간에 입점한 상점이 없습니다.</div>
                    <?php else: ?>
                    <?php foreach ($new_shops as $shop): ?>
                    <a href="admin_view.php?page=manage_shop&id=<?= $shop['id'] ?>"
                        class="list-group-item list-group-item-action p-3">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($shop['shop_name']) ?> <span
                                    class="text-muted fw-normal small">(<?= htmlspecialchars($shop['subdomain']) ?>)</span>
                            </h6>
                            <small class="text-muted"><?= date('Y.m.d H:i', strtotime($shop['created_at'])) ?></small>
                        </div>
                        <p class="mb-1 small text-secondary">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($shop['manager_name'] ?? '정보없음') ?> |
                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($shop['phone_mobile'] ?? '없음') ?>
                        </p>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 페이징 영역 -->
                <?php if ($total_new_pages > 1): ?>
                <div class="card-footer bg-white border-top-0 py-3 mt-auto">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $total_new_pages; $i++): ?>
                            <li class="page-item <?= ($i == $new_shop_page) ? 'active' : '' ?>">
                                <a class="page-link shadow-none <?= ($i == $new_shop_page) ? 'bg-primary border-primary text-white' : 'text-dark border-0' ?> mx-1 rounded-circle text-center"
                                    style="width: 30px;"
                                    href="admin_view.php?page=admin_dashboard&filter_date=<?= $filter_date ?>&new_shop_page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- [위젯 2] 결제 만료 임박 상점 -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100 border-top border-4 border-danger">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-danger"><i class="bi bi-credit-card-2-front me-2"></i>결제 만료 임박 상점
                    (<?= $days_limit ?>일 이내) <span class="badge bg-danger ms-1"><?= count($expiring_shops) ?></span>
                </h6>
                <a href="/admin/admin_view.php?page=manage_expiring_shops"
                    class="btn btn-sm btn-light text-danger fw-bold">임박 탭 이동</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-ps24 table-hover align-middle mb-0">
                        <thead>
                            <tr class="small">
                                <th class="t-center">상점명</th>
                                <th class="t-center">점주/연락처</th>
                                <th class="t-center">만료일자</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiring_shops)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted"><i
                                        class="bi bi-check-circle fs-3 d-block mb-2 text-success opacity-50"></i>만료 임박
                                    상점이 없습니다.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expiring_shops as $es): ?>
                            <tr class="small">
                                <td class="ps-3 t-center"><a
                                        href="admin_view.php?page=manage_shop&id=<?= $es['id'] ?>&view=payments"
                                        class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($es['shop_name']) ?></a>
                                </td>
                                <td class="t-center"><?= htmlspecialchars($es['manager_name'] ?? '정보없음') ?><br><span
                                        class="text-muted"
                                        style="font-size: 0.75rem;"><?= htmlspecialchars($es['phone_mobile'] ?? '-') ?></span>
                                </td>
                                <td class="t-center fw-bold text-danger"><?= $es['max_expiring_date'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- [위젯 3] 상점에서 본사로 보낸 최근 메시지 -->
    <div class="col-12 col-xl-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-success"><i class="bi bi-chat-left-dots-fill me-2"></i>상점에서 온 최근 메시지 (최근 5건)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recent_messages)): ?>
                    <div class="p-5 text-center text-muted small"><i
                            class="bi bi-mailbox fs-2 d-block mb-2 opacity-50"></i>수신된 메시지가 없습니다.</div>
                    <?php else: ?>
                    <?php foreach ($recent_messages as $msg): ?>
                    <a href="admin_view.php?page=manage_shop&id=<?= $msg['shop_id'] ?>&view=message"
                        class="list-group-item list-group-item-action p-3">
                        <div class="row align-items-center">
                            <div class="col-md-2 col-12 mb-2 mb-md-0">
                                <span class="badge bg-light text-dark border"><i
                                        class="bi bi-shop text-success me-1"></i>
                                    <?= htmlspecialchars($msg['shop_name']) ?></span>
                            </div>
                            <div class="col-md-8 col-12 mb-2 mb-md-0">
                                <h6
                                    class="mb-0 text-dark text-truncate <?= empty($msg['is_read']) ? 'fw-bold' : 'fw-normal' ?>">
                                    <?php if (empty($msg['is_read'])): ?><span class="badge bg-danger rounded-pill me-1"
                                        style="font-size:0.6rem;">N</span><?php endif; ?>
                                    <?= htmlspecialchars($msg['title']) ?>
                                </h6>
                            </div>
                            <div class="col-md-2 col-12 text-md-end text-muted small">
                                <?= date('y.m.d H:i', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white border-top text-center py-2">
                <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>자세한 내용과 답변은 메시지를 클릭하여 개별 상점 관리
                    페이지에서 진행하세요.</span>
            </div>
        </div>
    </div>

    <!-- [위젯 4] 상점별 리소스 사용량 분석 -->
    <div class="col-12 col-xl-12">
        <div class="card border-0 shadow-sm border-top border-4 border-secondary">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-dark"><i class="bi bi-hdd-network me-2"></i>상점별 리소스 사용량 (디스크 & DB)</h6>
                <div>
                    <button type="button" id="btn-check-system-integrity"
                        class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3 shadow-sm me-2">
                        <i class="bi bi-shield-check me-1"></i>전체 서버 스캔
                    </button>
                    <button type="button" id="btn-check-resources"
                        class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3 shadow-sm">
                        <i class="bi bi-search me-1"></i>사용량 분석
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- 대기 및 안내 화면 -->
                <div id="resource-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div class="text-muted small fw-bold">서버의 물리적 파일 및 DB 용량을 분석 중입니다...<br>잠시만 기다려주세요.</div>
                </div>
                <div id="resource-empty" class="p-5 text-center text-muted small">
                    <i class="bi bi-bar-chart-steps fs-2 d-block mb-2 opacity-50"></i>우측 상단의 <strong>'사용량 분석'</strong>
                    버튼을 누르면 순위가 표시됩니다.
                </div>

                <!-- 결과 테이블 -->
                <div class="table-responsive d-none" id="resource-table-container">
                    <table class="table table-ps24 table-hover align-middle mb-0">
                        <thead>
                            <tr class="small">
                                <th>순위 / 상점명</th>
                                <th>디스크 용량 (이미지 등)</th>
                                <th>DB 용량 (텍스트 데이터)</th>
                                <th>총 사용량</th>
                            </tr>
                        </thead>
                        <tbody id="resource-tbody">
                            <!-- JS를 통해 동적 렌더링 됩니다. -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- [신규] 디스크/DB 무결성 검사 모달 -->
<div class="modal fade" id="diskCheckModal" tabindex="-1" aria-labelledby="diskCheckModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diskCheckModalLabel">디스크/DB 무결성 검사</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="diskCheckModalBody">
                <!-- AJAX 결과가 여기에 표시됩니다. -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- [신규] 시스템 전체 무결성 검사 모달 -->
<div class="modal fade" id="sysIntegrityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>전체 시스템 무결성 검사 리포트</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sysIntegrityModalBody">
                <!-- AJAX 결과가 여기에 표시됩니다. -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCheck = document.getElementById('btn-check-resources');
    if (btnCheck) {
        btnCheck.addEventListener('click', async function() {
            const loading = document.getElementById('resource-loading');
            const empty = document.getElementById('resource-empty');
            const tableContainer = document.getElementById('resource-table-container');
            const tbody = document.getElementById('resource-tbody');

            // UI 상태 변경 (로딩 중)
            btnCheck.disabled = true;
            empty.classList.add('d-none');
            tableContainer.classList.add('d-none');
            loading.classList.remove('d-none');
            tbody.innerHTML = '';

            try {
                // [버그 수정] 순수한 JSON 응답을 받기 위해 관리자 뷰(HTML)를 통하지 않고 대시보드 파일을 직접 호출합니다.
                const response = await fetch('admin_dashboard.php?action=check_resources');
                const result = await response.json();

                if (result.status === 'success') {
                    const data = result.data;
                    if (data.length === 0) {
                        empty.innerHTML =
                            '<i class="bi bi-info-circle fs-2 d-block mb-2 opacity-50"></i>측정 가능한 리소스가 없습니다.';
                        empty.classList.remove('d-none');
                    } else {
                        let html = '';
                        data.forEach((item, index) => {
                            // 1~3위는 배지로 강조
                            let rankHtml = `<b class="text-muted">${index + 1}</b>`;
                            if (index === 0) rankHtml =
                                `<span class="badge bg-danger rounded-pill shadow-sm">1위</span>`;
                            else if (index === 1) rankHtml =
                                `<span class="badge bg-warning text-dark rounded-pill shadow-sm">2위</span>`;
                            else if (index === 2) rankHtml =
                                `<span class="badge bg-info text-dark rounded-pill shadow-sm">3위</span>`;

                            html += `
                                <tr class="small">
                                    <td class="ps-3 t-center">
                                        <span class="me-2 d-inline-block text-center" style="width: 35px;">${rankHtml}</span>
                                        <a href="admin_view.php?page=manage_shop&id=${item.id}" class="text-dark fw-bold text-decoration-none">${item.shop_name}</a>
                                    </td>
                                    <td class="t-center">
                                        ${item.disk_usage_formatted}
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2 rounded-pill check-disk-details" data-shop-id="${item.id}" data-shop-name="${item.shop_name}"><i class="bi bi-zoom-in"></i> 체크</button>
                                    </td>
                                    <td class="t-center">${item.db_usage_formatted}</td>
                                    <td class="t-center fw-bold text-danger">${item.total_usage_formatted}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                        tableContainer.classList.remove('d-none');
                    }
                } else {
                    alert('분석 중 오류가 발생했습니다: ' + result.message);
                    empty.classList.remove('d-none');
                }
            } catch (error) {
                console.error('AJAX 파싱 에러:', error);
                alert('서버 통신 오류가 발생했습니다. (관리자 권한 혹은 네트워크 상태를 확인하세요)');
                empty.classList.remove('d-none');
            } finally {
                // 로딩 종료 및 버튼 활성화
                loading.classList.add('d-none');
                btnCheck.disabled = false;
            }
        });
    }

    // [신규] 디스크 상세 분석 모달 이벤트 핸들러
    const resourceTbody = document.getElementById('resource-tbody');
    const diskCheckModalEl = document.getElementById('diskCheckModal');
    if (resourceTbody && diskCheckModalEl) {
        const diskCheckModal = new bootstrap.Modal(diskCheckModalEl);
        const diskCheckModalBody = document.getElementById('diskCheckModalBody');
        const diskCheckModalLabel = document.getElementById('diskCheckModalLabel');

        resourceTbody.addEventListener('click', function(event) {
            const button = event.target.closest('.check-disk-details');
            if (!button) return;

            const shopId = button.dataset.shopId;
            const shopName = button.dataset.shopName;

            diskCheckModalLabel.innerHTML =
                `<i class="bi bi-hdd-stack me-2"></i>디스크/DB 무결성 검사 : <span class="text-primary">${shopName}</span>`;
            diskCheckModalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted fw-bold">상점 파일 및 DB 무결성을 종합 분석 중입니다.<br>잠시만 기다려주세요...</p>
                    </div>
                `;
            diskCheckModal.show();

            // [개선] 두 가지 분석(용량/최적화, DB 무결성)을 병렬로 동시에 요청합니다.
            Promise.all([
                    fetch(`admin_dashboard.php?action=check_disk_details&shop_id=${shopId}`).then(
                        res => res.json()),
                    fetch(`admin_dashboard.php?action=check_disk_integrity&shop_id=${shopId}`).then(
                        res => res.json())
                ])
                .then(([detailsResult, integrityResult]) => {
                    let content = '';

                    // --- [1] 디스크 용량 및 최적화 분석 렌더링 ---
                    if (detailsResult.status === 'success') {
                        const {
                            large_files,
                            unoptimized_files,
                            other_files
                        } = detailsResult.data;

                        content +=
                            '<h6 class="fw-bold text-primary"><i class="bi bi-file-earmark-image me-2"></i>디스크 용량 및 최적화 분석</h6>';

                        if (large_files.length === 0 && unoptimized_files.length === 0 &&
                            other_files.length === 0) {
                            content +=
                                `<div class="alert alert-success text-center border-0 mb-4 py-3"><i class="bi bi-check-circle-fill fs-4 d-block mb-1"></i>최적화가 필요한 큰 파일이 없습니다.</div>`;
                        } else {
                            content +=
                                '<p class="small text-muted mb-3">아래는 최적화가 필요하거나 불필요할 수 있는 파일 목록입니다. (기준: 1MB 이상 또는 비-JPG 이미지)</p>';

                            const createFileList = (files, badgeClass) => {
                                let html =
                                    '<ul class="list-group list-group-flush small mb-4 border rounded">';
                                files.forEach(file => {
                                    let actions =
                                        `<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 ms-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button>`;
                                    if (file.is_image) {
                                        actions =
                                            `<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 ms-2 btn-resize-file" data-path="${file.path}"><i class="bi bi-arrows-angle-contract"></i> 최적화</button>` +
                                            actions;
                                    }
                                    html +=
                                        `<li class="list-group-item d-flex justify-content-between align-items-center"><code class="text-truncate" style="max-width: 55%;" title="${file.path}">${file.path}</code><div class="text-end"><span class="badge ${badgeClass} rounded-pill badge-size">${file.size_formatted}</span>${actions}</div></li>`;
                                });
                                html += '</ul>';
                                return html;
                            };

                            if (large_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-arrow-down-fill text-danger me-2"></i>용량이 큰 파일 (> 1MB)</h6>' +
                                    createFileList(large_files, 'bg-danger');
                            }
                            if (unoptimized_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-image-fill text-warning me-2"></i>최적화되지 않은 이미지 (Non-JPG)</h6>' +
                                    createFileList(unoptimized_files, 'bg-warning text-dark');
                            }
                            if (other_files.length > 0) {
                                content +=
                                    '<h6><i class="bi bi-file-earmark-excel-fill text-secondary me-2"></i>기타 파일 (이미지 아님)</h6>' +
                                    createFileList(other_files, 'bg-secondary');
                            }
                        }
                    } else {
                        content +=
                            `<div class="alert alert-danger">용량 분석 오류: ${detailsResult.message}</div>`;
                    }

                    // --- [2] DB-파일 무결성 검사 렌더링 ---
                    content += `<div class="mt-4 pt-4 border-top">`;
                    if (integrityResult.status === 'success') {
                        const {
                            orphaned_files,
                            broken_links,
                            checked_tables
                        } = integrityResult.data;

                        content +=
                            '<h6 class="fw-bold text-info"><i class="bi bi-shield-check me-2"></i>DB-파일 무결성 검사</h6>';

                        if (checked_tables && checked_tables.length > 0) {
                            content +=
                                `<p class="small text-muted mb-3"><i class="bi bi-database me-1"></i> 검사 대상 테이블: <code>${checked_tables.join(', ')}</code></p>`;
                        }

                        if (orphaned_files.length === 0 && broken_links.length === 0) {
                            content +=
                                `<div class="alert alert-success border-0 py-3 mt-3 text-center"><i class="bi bi-check-circle-fill fs-4 d-block mb-1"></i>DB 기록과 실제 파일이 완벽하게 일치합니다.</div>`;
                        } else {
                            content +=
                                '<p class="small text-muted mb-3">DB 기록과 실제 파일의 일치 여부를 검사하여 불필요한 파일을 찾거나, 깨진 이미지 링크를 발견했습니다.</p>';

                            if (orphaned_files.length > 0) {
                                const orphanedPaths = orphaned_files.map(f => f.path);
                                content +=
                                    `
                                        <div class="d-flex justify-content-between align-items-end mb-1">
                                            <h6><i class="bi bi-question-circle-fill text-warning me-2"></i>DB에 없는 파일 (삭제 가능)</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-all-orphaned" data-paths='${JSON.stringify(orphanedPaths)}'><i class="bi bi-trash3"></i> 전체 일괄 삭제</button>
                                        </div>
                                        <p class="small text-muted mb-2">서버에 파일은 있지만, DB에 기록이 없어 사용되지 않는 것으로 추정되는 이미지입니다.</p>
                                        <ul class="list-group list-group-flush small mb-4 border rounded orphaned-file-list">`;
                                orphaned_files.forEach(file => {
                                    content +=
                                        `<li class="list-group-item d-flex justify-content-between align-items-center"><div><code class="text-truncate d-inline-block" style="max-width: 600px;" title="${file.path}">${file.path}</code></div><div><span class="badge bg-secondary rounded-pill me-2">${file.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button></div></li>`;
                                });
                                content += '</ul>';
                            }
                            if (broken_links.length > 0) {
                                content +=
                                    '<h6 class="fw-bold text-info"><i class="bi bi-link-45deg text-danger me-2"></i>깨진 이미지 링크 (파일 없음)</h6><p class="small text-muted mb-2">DB에는 기록되어 있으나 실제 파일이 존재하지 않습니다. <strong>해당 상점의 [메뉴 관리]에서 사진을 다시 재업로드</strong>하시면 링크가 덮어씌워지며 자동으로 복구됩니다.</p><ul class="list-group list-group-flush small border rounded">';
                                broken_links.forEach(link => {
                                    content +=
                                        `<li class="list-group-item"><span class="badge bg-light text-dark border me-2" title="DB 테이블명"><i class="bi bi-table me-1"></i>${link.table}</span><code title="${link.path}">${link.path}</code></li>`;
                                });
                                content += '</ul>';
                            }
                        }
                    } else {
                        content +=
                            `<div class="alert alert-danger">무결성 검사 오류: ${integrityResult.message}</div>`;
                    }
                    content += `</div>`;

                    diskCheckModalBody.innerHTML = content;
                })
                .catch(error => {
                    console.error('Check error:', error);
                    diskCheckModalBody.innerHTML =
                        `<div class="alert alert-danger">분석 중 통신 오류가 발생했습니다: ${error.message}</div>`;
                });
        });

        // [신규] 모달창 내부의 '삭제' 및 '최적화' 버튼 클릭 이벤트 (이벤트 위임)
        diskCheckModalBody.addEventListener('click', async function(e) {
            const deleteBtn = e.target.closest('.btn-delete-file');
            const resizeBtn = e.target.closest('.btn-resize-file');
            const bulkDeleteBtn = e.target.closest('.btn-delete-all-orphaned'); // 일괄 삭제 버튼

            if (deleteBtn) {
                if (!confirm('정말로 이 파일을 삭제하시겠습니까?\n(만약 현재 홈페이지에서 사용 중인 이미지라면 엑스박스가 뜰 수 있습니다)'))
                    return;
                const path = deleteBtn.dataset.path;
                const li = deleteBtn.closest('li');
                deleteBtn.disabled = true;

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_file', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        deleteBtn.disabled = false;
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    deleteBtn.disabled = false;
                }
            }

            if (bulkDeleteBtn) {
                if (!confirm('경고: 목록에 있는 모든 잉여 파일을 일괄 삭제하시겠습니까?\n(삭제 후 복구할 수 없습니다)')) return;
                const paths = bulkDeleteBtn.dataset.paths;
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>삭제 중...';

                const formData = new FormData();
                formData.append('file_paths', paths);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_files_bulk', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        alert(`총 ${result.deleted_count}개의 파일이 일괄 삭제되었습니다.`);
                        const ul = bulkDeleteBtn.closest('div').nextElementSibling
                            .nextElementSibling;
                        if (ul && ul.classList.contains('orphaned-file-list')) {
                            ul.innerHTML =
                                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>모두 삭제되었습니다.</li>';
                        }
                        bulkDeleteBtn.remove();
                    } else {
                        alert('일괄 삭제 실패: ' + result.message);
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                }
            }

            if (resizeBtn) {
                const path = resizeBtn.dataset.path;
                const badge = resizeBtn.closest('li').querySelector('.badge-size');

                resizeBtn.disabled = true;
                const originalText = resizeBtn.innerHTML;
                resizeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=resize_shop_image', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        badge.innerText = result.new_size_formatted;
                        badge.className = 'badge bg-success rounded-pill badge-size';
                        resizeBtn.remove(); // 최적화 완료 후 버튼은 제거하여 중복 클릭 방지
                    } else {
                        alert('최적화 실패: ' + result.message);
                        resizeBtn.disabled = false;
                        resizeBtn.innerHTML = originalText;
                    }
                } catch (err) {
                    alert('서버 통신 오류가 발생했습니다.');
                    resizeBtn.disabled = false;
                    resizeBtn.innerHTML = originalText;
                }
            }
        });
    }

    // [신규] 시스템 전체 무결성 검사 버튼 이벤트
    const btnCheckSys = document.getElementById('btn-check-system-integrity');
    const sysIntegrityModalEl = document.getElementById('sysIntegrityModal');
    if (btnCheckSys && sysIntegrityModalEl) {
        const sysModal = new bootstrap.Modal(sysIntegrityModalEl);
        const sysModalBody = document.getElementById('sysIntegrityModalBody');

        btnCheckSys.addEventListener('click', async function() {
            sysModalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status"></div>
                        <h5 class="mt-4 text-dark fw-bold">전체 서버 무결성 스캔 중...</h5>
                        <p class="text-muted mb-0">서버 내의 모든 상점 DB와 물리적 파일 시스템을 교차 검증하고 있습니다.<br>데이터 양에 따라 다소 시간이 소요될 수 있습니다.</p>
                    </div>
                `;
            sysModal.show();

            try {
                const res = await fetch('admin_dashboard.php?action=check_system_integrity');
                const result = await res.json();

                if (result.status === 'success') {
                    const {
                        orphaned_files,
                        broken_links,
                        orphaned_directories,
                        checked_tables
                    } = result.data;
                    let content = '';

                    if (checked_tables && checked_tables.length > 0) {
                        content +=
                            `<p class="small text-muted mb-3"><i class="bi bi-database me-1"></i> 검사 대상 테이블: <code>${checked_tables.join(', ')}</code></p>`;
                    }

                    if (orphaned_files.length === 0 && broken_links.length === 0 &&
                        orphaned_directories.length === 0) {
                        content =
                            `<div class="alert alert-success text-center border-0 py-5"><i class="bi bi-check-circle-fill fs-1 d-block mb-3"></i><h4 class="fw-bold">퍼펙트!</h4>시스템 내의 모든 DB와 파일이 완벽하게 일치합니다.</div>`;
                    } else {
                        content +=
                            `<p class="small text-muted mb-4"><i class="bi bi-info-circle me-1"></i> 전체 상점의 데이터베이스 기록과 서버의 물리적 파일을 교차 검증한 결과입니다.</p>`;

                        if (orphaned_directories.length > 0) {
                            content += `
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-danger"><i class="bi bi-folder-x me-2"></i>주인 잃은 잉여 폴더 (상점 삭제 잔재물) <span class="badge bg-danger">${orphaned_directories.length}건</span></h6>
                                        <ul class="list-group list-group-flush small border rounded">`;
                            orphaned_directories.forEach(dir => {
                                content +=
                                    `<li class="list-group-item d-flex justify-content-between align-items-center"><code>${dir.path}</code><div><span class="badge bg-secondary rounded-pill me-2">${dir.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-sys-dir" data-path="${dir.path}"><i class="bi bi-trash"></i> 폴더 전체 삭제</button></div></li>`;
                            });
                            content += `</ul></div>`;
                        }

                        if (orphaned_files.length > 0) {
                            const orphanedPaths = orphaned_files.map(f => f.path);
                            const orphanedPathsStr = JSON.stringify(orphanedPaths).replace(/'/g,
                                "&apos;");
                            content +=
                                `
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-end mb-1">
                                            <h6 class="fw-bold text-warning mb-0"><i class="bi bi-file-earmark-x me-2"></i>DB에 등록되지 않은 잉여 파일 <span class="badge bg-warning text-dark">${orphaned_files.length}건</span></h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-all-sys-orphaned" data-paths='${orphanedPathsStr}'><i class="bi bi-trash3"></i> 전체 일괄 삭제</button>
                                        </div>
                                        <ul class="list-group list-group-flush small border rounded mt-2" style="max-height: 300px; overflow-y: auto;">`;
                            orphaned_files.forEach(file => {
                                content +=
                                    `<li class="list-group-item d-flex justify-content-between align-items-center"><div><span class="badge bg-light text-dark border me-2">${file.shop_name}</span><code>${file.path}</code></div><div><span class="badge bg-secondary rounded-pill me-2">${file.size_formatted}</span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file" data-path="${file.path}"><i class="bi bi-trash"></i> 삭제</button></div></li>`;
                            });
                            content += `</ul></div>`;
                        }

                            if (broken_links.length > 0) {
                            content +=
                                `
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-danger"><i class="bi bi-link-45deg me-2"></i>파일이 유실된 깨진 DB 링크 (엑스박스) <span class="badge bg-danger">${broken_links.length}건</span></h6>
                                        <p class="small text-muted mb-2">해당 상점의 관리 페이지에서 <strong>사진을 다시 재업로드</strong>하거나, 더 이상 팔지 않는 메뉴라면 <strong>해당 메뉴를 삭제</strong>하시면 해결됩니다.</p>
                                        <ul class="list-group list-group-flush small border rounded" style="max-height: 200px; overflow-y: auto;">`;
                            broken_links.forEach(link => {
                                content +=
                                    `<li class="list-group-item">
                                        <span class="badge bg-light text-dark border me-2" title="상점명">${link.shop_name}</span>
                                        <span class="badge bg-secondary text-white border me-2" title="DB 테이블명"><i class="bi bi-table me-1"></i>${link.table}</span>
                                        <a href="javascript:void(0);" class="text-decoration-none fw-bold text-primary" onclick="if(window.parent && typeof window.parent.showCommonImageModal === 'function') { window.parent.showCommonImageModal('${link.path}'); } else if(typeof showCommonImageModal === 'function') { showCommonImageModal('${link.path}'); }">${link.path}</a>
                                    </li>`;
                            });
                            content += `</ul></div>`;
                        }
                    }
                    sysModalBody.innerHTML = content;
                } else {
                    sysModalBody.innerHTML =
                        `<div class="alert alert-danger">${result.message}</div>`;
                }
            } catch (err) {
                sysModalBody.innerHTML = `<div class="alert alert-danger">서버 통신 오류가 발생했습니다.</div>`;
            }
        });

        // [신규] 전체 시스템 모달 내 삭제 버튼 이벤트 위임 (파일 삭제 및 잉여 폴더 완전 삭제)
        sysModalBody.addEventListener('click', async function(e) {
            const delDirBtn = e.target.closest('.btn-delete-sys-dir');
            const delFileBtn = e.target.closest('.btn-delete-file');
            const bulkDeleteBtn = e.target.closest('.btn-delete-all-sys-orphaned');

            if (delDirBtn) {
                if (!confirm(
                        '경고: 이 폴더 안의 모든 파일과 하위 폴더가 영구적으로 삭제됩니다.\n(삭제된 상점의 잔재물일 확률이 높습니다)\n정말 삭제하시겠습니까?'
                    )) return;
                const path = delDirBtn.dataset.path;
                const li = delDirBtn.closest('li');
                delDirBtn.disabled = true;

                const formData = new FormData();
                formData.append('dir_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_system_directory', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        delDirBtn.disabled = false;
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    delDirBtn.disabled = false;
                }
            }

            if (delFileBtn) {
                if (!confirm('정말로 이 파일을 삭제하시겠습니까?')) return;
                const path = delFileBtn.dataset.path;
                const li = delFileBtn.closest('li');
                delFileBtn.disabled = true;

                const formData = new FormData();
                formData.append('file_path', path);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_file', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        li.style.transition = "0.3s";
                        li.style.opacity = "0";
                        setTimeout(() => li.remove(), 300);
                    } else {
                        alert('삭제 실패: ' + result.message);
                        delFileBtn.disabled = false;
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    delFileBtn.disabled = false;
                }
            }

            if (bulkDeleteBtn) {
                if (!confirm('경고: 목록에 있는 모든 잉여 파일을 일괄 삭제하시겠습니까?\\n(삭제 후 복구할 수 없습니다)')) return;
                const paths = bulkDeleteBtn.dataset.paths;
                bulkDeleteBtn.disabled = true;
                bulkDeleteBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span>삭제 중...';

                const formData = new FormData();
                formData.append('file_paths', paths);

                try {
                    const res = await fetch('admin_dashboard.php?action=delete_shop_files_bulk', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    if (result.status === 'success') {
                        alert(`총 ${result.deleted_count}개의 파일이 일괄 삭제되었습니다.`);
                        const ul = bulkDeleteBtn.closest('.mb-4').querySelector('ul');
                        if (ul) {
                            ul.innerHTML =
                                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>모두 삭제되었습니다.</li>';
                        }
                        bulkDeleteBtn.remove();
                    } else {
                        alert('일괄 삭제 실패: ' + result.message);
                        bulkDeleteBtn.disabled = false;
                        bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                    }
                } catch (err) {
                    alert('통신 오류가 발생했습니다.');
                    bulkDeleteBtn.disabled = false;
                    bulkDeleteBtn.innerHTML = '<i class="bi bi-trash3"></i> 전체 일괄 삭제';
                }
            }
        });
    }
});
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>