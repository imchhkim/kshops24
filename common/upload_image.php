<<<<<<< HEAD
<?php

/**
 * KShops24 유연한 공용 이미지 업로드 엔진
 * 위치: /public_html/common/upload_image.php
 */

// [버그 수정] 불필요한 출력 버퍼링 차단
ob_start();

// 1. 서버 루트 절대 경로 정의
$root_path = $_SERVER['DOCUMENT_ROOT']; // /home/u743828642/domains/kshops24.com/public_html

// 2. [버그 수정] DB 세션 정보 공유를 위해 공통 엔진(common_header.php)을 메인으로 호출
// 이전에는 자체적으로 session_start()만 호출하여 DB 세션(로그인 상태)을 읽어오지 못하고 권한 오류로 튕겨냈습니다.
require_once $root_path . '/common/common_header.php';

// 공통 헤더 로드 중 발생할 수 있는 빈칸이나 HTML 찌꺼기를 완벽히 제거하여 순수한 JSON 응답을 보장합니다.
while (ob_get_level()) {
    ob_end_clean();
}

// [IDE 경고 방지용] $pdo 변수가 PDO 객체임을 명시
/** @var PDO $pdo */

// 응답을 JSON으로 강제 설정 (디버깅 용이)
header('Content-Type: application/json');

// 1. [보안] 권한 체크: 상점 관리자 또는 슈퍼 관리자만 접근 허용
if (!isset($_SESSION['shop_id']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {

    // --- [파라미터 수집 및 초기화] ---
    $target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $table     = $_POST['table']  ?? 'shops';       // 업로드 대상 DB 테이블
    $column    = $_POST['column'] ?? 'logo_path';   // 업로드 대상 DB 컬럼
    $folder    = $_POST['folder'] ?? 'shopimages';  // 저장될 물리 폴더명
    $mode      = $_POST['mode']   ?? 'update';      // 실행 모드 (update | insert)

    // 2. [보안] 화이트리스트 검증: SQL Injection 및 비인가 테이블/폴더 접근 방지
    // bg(background), shopimages(매장 사진들), logo(로고), itemboard(메뉴판 혹은 홍보 전단지), itemimages(메뉴, 매물, 서비스)
    $allowed_tables  = ['shops', 'products', 'reviews', 'shop_images', 'shop_items', 'shop_item_boards'];
    $allowed_columns = ['logo_path', 'bg_path', 'product_img', 'img_path', 'item_img', 'board_img_path'];
    $allowed_folders = ['bg', 'shopimages', 'logo', 'itemboard', 'itemimages']; // 허용된 폴더명으로 통일

    if (!in_array($table, $allowed_tables) || !in_array($column, $allowed_columns)) {
        echo json_encode(['status' => 'error', 'message' => 'Forbidden Access: Invalid Table/Column']);
        exit;
    }

    if (!in_array($folder, $allowed_folders)) {
        $folder = 'shopimages'; // 비인가 폴더 접근 시 기본 폴더로 강제 지정
    }

    if (!$target_id) {
        echo json_encode(['status' => 'error', 'message' => 'Target ID is required']);
        exit;
    }

    // --- [상점 식별 및 경로 설정] ---
    // 갤러리(shop_images) 추가 시 target_id는 곧 shop_id입니다.
    if ($table === 'shop_images') {
        $query_shop_id = $target_id;
    } else {
        $query_shop_id = $_SESSION['shop_id'] ?? $target_id;
    }

    $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
    $stmt->execute([$query_shop_id]);
    $subdomain = $stmt->fetchColumn();

    if (!$subdomain) {
        echo json_encode(['status' => 'error', 'message' => 'Subdomain not found for Shop ID: ' . $query_shop_id]);
        exit;
    }

    // 상점별/용도별 독립 폴더 경로 생성
    $upload_path = SHOP_UPLOADS_URL . "$subdomain/$folder/";
    $target_dir = SHOP_UPLOADS_DIR . "/$subdomain/$folder/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // --- [파일명 정의] ---
    $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $random_suffix = substr(md5(uniqid()), 0, 4);
    $file_name = $column . "_" . date("Ymd_His") . "_" . $random_suffix . ".webp";
    $target_file = $target_dir . $file_name;
    $db_path = $upload_path . $file_name; // DB에 저장될 웹 경로 (/uploads/...)

    $source_file = $_FILES["image"]["tmp_name"];

    // 3. [이미지 로드 및 EXIF 회전 보정]
    $src_img = null;
    if ($file_ext === 'png') $src_img = @imagecreatefrompng($source_file);
    elseif ($file_ext === 'gif') $src_img = @imagecreatefromgif($source_file);
    else $src_img = @imagecreatefromjpeg($source_file);

    if (!$src_img) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load image resource']);
        exit;
    }

    if (($file_ext === 'jpg' || $file_ext === 'jpeg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source_file);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $src_img = imagerotate($src_img, 180, 0);
                    break;
                case 6:
                    $src_img = imagerotate($src_img, -90, 0);
                    break;
                case 8:
                    $src_img = imagerotate($src_img, 90, 0);
                    break;
            }
        }
    }

    $width = imagesx($src_img);
    $height = imagesy($src_img);

    // 4. [이미지 처리 규격 결정]
    $is_crop = true;
    switch ($column) {
        case 'logo_path':
            $new_width = 400;
            $new_height = 100;
            break;
        case 'bg_path':
            $new_width = 1200; // 모바일/PC 겸용으로 1920에서 하향 최적화 (16:9)
            $new_height = 675;
            break;
        case 'img_path':  // 일반 갤러리: 원본 비율 유지
            $new_width = 1000;
            $new_height = ($width > 0) ? ($new_width * ($height / $width)) : 800;
            $is_crop = false;
            break;
        case 'board_img_path': // 메뉴판 이미지: 3:4 세로 비율 표준화
            $new_width = 800;
            $new_height = 1067;
            $is_crop = true;
            break;
        default:
            // 메뉴 및 일반 이미지: 4:3 비율 표준화
            $new_width = 1000;
            $new_height = 750;
            $is_crop = true;
            break;
    }

    $dst_img = imagecreatetruecolor($new_width, $new_height);

    // [Fix] JPG 변환 시 투명 배경이 검은색으로 변하는 문제 해결 (흰색 배경 채우기)
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $white);

    // 5. [리사이징 및 크롭 알고리즘]
    if ($is_crop) {
        $target_ratio = $new_width / $new_height;
        $source_ratio = $width / $height;
        if ($source_ratio > $target_ratio) {
            $src_h = $height;
            $src_w = $height * $target_ratio;
            $src_x = ($width - $src_w) / 2;
            $src_y = 0;
        } else {
            $src_w = $width;
            $src_h = $width / $target_ratio;
            $src_x = 0;
            $src_y = ($height - $src_h) / 2;
        }
        imagecopyresampled($dst_img, $src_img, 0, 0, (int)$src_x, (int)$src_y, $new_width, $new_height, (int)$src_w, (int)$src_h);
    } else {
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    }

    // 6. [파일 저장 및 DB 연동]
    // WebP 포맷으로 압축(품질 80)하여 단일 파일만 저장 (Inode 폭증 방지)
    if (imagewebp($dst_img, $target_file, 80)) {
        // 과거 존재했던 썸네일(thumb_) 추가 생성 로직은 Inode 최적화를 위해 완전히 제거됨.

        try {
            // [Fix] shop_items 테이블은 폼 전송 시 DB에 저장하므로, 여기서는 파일만 업로드하고 DB Insert는 건너뜀
            // 이를 통해 '이름 없는 빈 메뉴'가 생성되는 좀비 레코드 버그 방지
            if ($table === 'shop_items' || ($table === 'shops' && $column === 'bg_path')) {
                echo json_encode(['status' => 'success', 'path' => $db_path]);
                exit;
            }

            if ($mode === 'insert') {
                // 갤러리 추가 (shop_images 테이블)
                // sort_order 컬럼이 있다면 기본값 0을 함께 입력해줍니다.
                $sql = "INSERT INTO $table (shop_id, $column) VALUES (?, ?)";
                $pdo->prepare($sql)->execute([$target_id, $db_path]);
                $insert_id = $pdo->lastInsertId();
            } else {
                // [수정] 업데이트 모드일 경우, DB에 저장된 기존 이미지 파일을 먼저 삭제하여 용량 낭비를 방지합니다.
                $stmt_old = $pdo->prepare("SELECT $column FROM $table WHERE id = ?");
                $stmt_old->execute([$target_id]);
                $old_path = $stmt_old->fetchColumn();

                // 기본 이미지(/assets/...)나 비어있는 경로는 삭제하지 않도록 안전장치 추가
                if ($old_path && strpos($old_path, '/uploads/') === 0) {
                    $old_file_abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $old_path;
                    if (file_exists($old_file_abs_path)) {
                        @unlink($old_file_abs_path);
                    }
                    // 업데이트 전 과거에 생성되었던 썸네일(thumb_)도 찾아 함께 삭제
                    $old_thumb_abs_path = dirname($old_file_abs_path) . '/thumb_' . basename($old_file_abs_path);
                    if (file_exists($old_thumb_abs_path)) {
                        @unlink($old_thumb_abs_path);
                    }
                }

                // 상점 로고/배경 등 수정
                $sql = "UPDATE $table SET $column = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$db_path, $target_id]);
            }

            // [테스트 모듈 호출] 설정 파일의 ENABLE_TASK_TEST가 true일 때만 작동
            if (defined('ENABLE_TASK_TEST') && ENABLE_TASK_TEST === true) {
                ob_start();
                include $_SERVER['DOCUMENT_ROOT'] . '/task_test.php';
                ob_end_clean();
            }

            echo json_encode(['status' => 'success', 'path' => $db_path, 'insert_id' => $insert_id ?? 0]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB Sync Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save image to: ' . $target_dir]);
    }

    imagedestroy($dst_img);
    imagedestroy($src_img);
} else {
    echo json_encode(['status' => 'error', 'message' => '이미지 파일이 전송되지 않았거나, 업로드 용량 제한을 초과했습니다.']);
}
=======
<?php

/**
 * KShops24 유연한 공용 이미지 업로드 엔진
 * 위치: /public_html/common/upload_image.php
 */

// [버그 수정] 불필요한 출력 버퍼링 차단
ob_start();

// 1. 서버 루트 절대 경로 정의
$root_path = $_SERVER['DOCUMENT_ROOT']; // /home/u743828642/domains/kshops24.com/public_html

// 2. [버그 수정] DB 세션 정보 공유를 위해 공통 엔진(common_header.php)을 메인으로 호출
// 이전에는 자체적으로 session_start()만 호출하여 DB 세션(로그인 상태)을 읽어오지 못하고 권한 오류로 튕겨냈습니다.
require_once $root_path . '/common/common_header.php';

// 공통 헤더 로드 중 발생할 수 있는 빈칸이나 HTML 찌꺼기를 완벽히 제거하여 순수한 JSON 응답을 보장합니다.
while (ob_get_level()) {
    ob_end_clean();
}

// [IDE 경고 방지용] $pdo 변수가 PDO 객체임을 명시
/** @var PDO $pdo */

// 응답을 JSON으로 강제 설정 (디버깅 용이)
header('Content-Type: application/json');

// 1. [보안] 권한 체크: 상점 관리자 또는 슈퍼 관리자만 접근 허용
if (!isset($_SESSION['shop_id']) && !isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {

    // --- [파라미터 수집 및 초기화] ---
    $target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $table     = $_POST['table']  ?? 'shops';       // 업로드 대상 DB 테이블
    $column    = $_POST['column'] ?? 'logo_path';   // 업로드 대상 DB 컬럼
    $folder    = $_POST['folder'] ?? 'shopimages';  // 저장될 물리 폴더명
    $mode      = $_POST['mode']   ?? 'update';      // 실행 모드 (update | insert)

    // 2. [보안] 화이트리스트 검증: SQL Injection 및 비인가 테이블/폴더 접근 방지
    // bg(background), shopimages(매장 사진들), logo(로고), itemboard(메뉴판 혹은 홍보 전단지), itemimages(메뉴, 매물, 서비스)
    $allowed_tables  = ['shops', 'products', 'reviews', 'shop_images', 'shop_items', 'shop_item_boards'];
    $allowed_columns = ['logo_path', 'bg_path', 'product_img', 'img_path', 'item_img', 'board_img_path'];
    $allowed_folders = ['bg', 'shopimages', 'logo', 'itemboard', 'itemimages']; // 허용된 폴더명으로 통일

    if (!in_array($table, $allowed_tables) || !in_array($column, $allowed_columns)) {
        echo json_encode(['status' => 'error', 'message' => 'Forbidden Access: Invalid Table/Column']);
        exit;
    }

    if (!in_array($folder, $allowed_folders)) {
        $folder = 'shopimages'; // 비인가 폴더 접근 시 기본 폴더로 강제 지정
    }

    if (!$target_id) {
        echo json_encode(['status' => 'error', 'message' => 'Target ID is required']);
        exit;
    }

    // --- [상점 식별 및 경로 설정] ---
    // 갤러리(shop_images) 추가 시 target_id는 곧 shop_id입니다.
    if ($table === 'shop_images') {
        $query_shop_id = $target_id;
    } else {
        $query_shop_id = $_SESSION['shop_id'] ?? $target_id;
    }

    $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
    $stmt->execute([$query_shop_id]);
    $subdomain = $stmt->fetchColumn();

    if (!$subdomain) {
        echo json_encode(['status' => 'error', 'message' => 'Subdomain not found for Shop ID: ' . $query_shop_id]);
        exit;
    }

    // 상점별/용도별 독립 폴더 경로 생성
    $upload_path = SHOP_UPLOADS_URL . "$subdomain/$folder/";
    $target_dir = SHOP_UPLOADS_DIR . "/$subdomain/$folder/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // --- [파일명 정의] ---
    $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $random_suffix = substr(md5(uniqid()), 0, 4);
    $file_name = $column . "_" . date("Ymd_His") . "_" . $random_suffix . ".webp";
    $target_file = $target_dir . $file_name;
    $db_path = $upload_path . $file_name; // DB에 저장될 웹 경로 (/uploads/...)

    $source_file = $_FILES["image"]["tmp_name"];

    // 3. [이미지 로드 및 EXIF 회전 보정]
    $src_img = null;
    if ($file_ext === 'png') $src_img = @imagecreatefrompng($source_file);
    elseif ($file_ext === 'gif') $src_img = @imagecreatefromgif($source_file);
    else $src_img = @imagecreatefromjpeg($source_file);

    if (!$src_img) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load image resource']);
        exit;
    }

    if (($file_ext === 'jpg' || $file_ext === 'jpeg') && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source_file);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $src_img = imagerotate($src_img, 180, 0);
                    break;
                case 6:
                    $src_img = imagerotate($src_img, -90, 0);
                    break;
                case 8:
                    $src_img = imagerotate($src_img, 90, 0);
                    break;
            }
        }
    }

    $width = imagesx($src_img);
    $height = imagesy($src_img);

    // 4. [이미지 처리 규격 결정]
    $is_crop = true;
    switch ($column) {
        case 'logo_path':
            $new_width = 400;
            $new_height = 100;
            break;
        case 'bg_path':
            $new_width = 1200; // 모바일/PC 겸용으로 1920에서 하향 최적화 (16:9)
            $new_height = 675;
            break;
        case 'img_path':  // 일반 갤러리: 원본 비율 유지
            $new_width = 1000;
            $new_height = ($width > 0) ? ($new_width * ($height / $width)) : 800;
            $is_crop = false;
            break;
        case 'board_img_path': // 메뉴판 이미지: 3:4 세로 비율 표준화
            $new_width = 800;
            $new_height = 1067;
            $is_crop = true;
            break;
        default:
            // 메뉴 및 일반 이미지: 4:3 비율 표준화
            $new_width = 1000;
            $new_height = 750;
            $is_crop = true;
            break;
    }

    $dst_img = imagecreatetruecolor($new_width, $new_height);

    // [Fix] JPG 변환 시 투명 배경이 검은색으로 변하는 문제 해결 (흰색 배경 채우기)
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $white);

    // 5. [리사이징 및 크롭 알고리즘]
    if ($is_crop) {
        $target_ratio = $new_width / $new_height;
        $source_ratio = $width / $height;
        if ($source_ratio > $target_ratio) {
            $src_h = $height;
            $src_w = $height * $target_ratio;
            $src_x = ($width - $src_w) / 2;
            $src_y = 0;
        } else {
            $src_w = $width;
            $src_h = $width / $target_ratio;
            $src_x = 0;
            $src_y = ($height - $src_h) / 2;
        }
        imagecopyresampled($dst_img, $src_img, 0, 0, (int)$src_x, (int)$src_y, $new_width, $new_height, (int)$src_w, (int)$src_h);
    } else {
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    }

    // 6. [파일 저장 및 DB 연동]
    // WebP 포맷으로 압축(품질 80)하여 단일 파일만 저장 (Inode 폭증 방지)
    if (imagewebp($dst_img, $target_file, 80)) {
        // 과거 존재했던 썸네일(thumb_) 추가 생성 로직은 Inode 최적화를 위해 완전히 제거됨.

        try {
            // [Fix] shop_items 테이블은 폼 전송 시 DB에 저장하므로, 여기서는 파일만 업로드하고 DB Insert는 건너뜀
            // 이를 통해 '이름 없는 빈 메뉴'가 생성되는 좀비 레코드 버그 방지
            if ($table === 'shop_items' || ($table === 'shops' && $column === 'bg_path')) {
                echo json_encode(['status' => 'success', 'path' => $db_path]);
                exit;
            }

            if ($mode === 'insert') {
                // 갤러리 추가 (shop_images 테이블)
                // sort_order 컬럼이 있다면 기본값 0을 함께 입력해줍니다.
                $sql = "INSERT INTO $table (shop_id, $column) VALUES (?, ?)";
                $pdo->prepare($sql)->execute([$target_id, $db_path]);
                $insert_id = $pdo->lastInsertId();
            } else {
                // [수정] 업데이트 모드일 경우, DB에 저장된 기존 이미지 파일을 먼저 삭제하여 용량 낭비를 방지합니다.
                $stmt_old = $pdo->prepare("SELECT $column FROM $table WHERE id = ?");
                $stmt_old->execute([$target_id]);
                $old_path = $stmt_old->fetchColumn();

                // 기본 이미지(/assets/...)나 비어있는 경로는 삭제하지 않도록 안전장치 추가
                if ($old_path && strpos($old_path, '/uploads/') === 0) {
                    $old_file_abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $old_path;
                    if (file_exists($old_file_abs_path)) {
                        @unlink($old_file_abs_path);
                    }
                    // 업데이트 전 과거에 생성되었던 썸네일(thumb_)도 찾아 함께 삭제
                    $old_thumb_abs_path = dirname($old_file_abs_path) . '/thumb_' . basename($old_file_abs_path);
                    if (file_exists($old_thumb_abs_path)) {
                        @unlink($old_thumb_abs_path);
                    }
                }

                // 상점 로고/배경 등 수정
                $sql = "UPDATE $table SET $column = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([$db_path, $target_id]);
            }

            // [테스트 모듈 호출] 설정 파일의 ENABLE_TASK_TEST가 true일 때만 작동
            if (defined('ENABLE_TASK_TEST') && ENABLE_TASK_TEST === true) {
                ob_start();
                include $_SERVER['DOCUMENT_ROOT'] . '/task_test.php';
                ob_end_clean();
            }

            echo json_encode(['status' => 'success', 'path' => $db_path, 'insert_id' => $insert_id ?? 0]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB Sync Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save image to: ' . $target_dir]);
    }

    imagedestroy($dst_img);
    imagedestroy($src_img);
} else {
    echo json_encode(['status' => 'error', 'message' => '이미지 파일이 전송되지 않았거나, 업로드 용량 제한을 초과했습니다.']);
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
