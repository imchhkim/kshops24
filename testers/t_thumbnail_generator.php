<?php

/**
 * KShops24 과거 이미지 일괄 다이어트 및 썸네일 청소기 (Inode 최적화)
 * 1. 과거에 생성된 잉여 썸네일(thumb_*) 파일들을 모두 찾아 삭제합니다. (Inode 반토막)
 * 2. 폭이 1000px을 초과하는 초고해상도 원본 사진을 모바일에 맞게 1000px로 줄여 덮어씁니다. (용량 다이어트)
 * (DB 수정 없이 물리적 파일만 교체/삭제하므로 매우 안전합니다.)
 * 실행 방법: 슈퍼 관리자 로그인 후 브라우저 주소창에 https://KShops24.com/testers/t_thumbnail_generator.php 접속
 */

// 1. 관리자 공통 헤더 로드
// (DB 세션 연결 및 슈퍼 관리자 권한 체크가 이 안에서 자동으로 완벽하게 처리됩니다)
require_once dirname(__DIR__) . '/common/admin_common_header.php';

// 3. 타임아웃 무제한 및 메모리 제한 해제 (이미지가 많을 수 있으므로 넉넉히 설정)
set_time_limit(0);
ini_set('memory_limit', '512M');

$base_dir = SHOP_UPLOADS_DIR; // /uploads/shops
$target_folders = ['itemimages', 'gallery', 'itemboard', 'shopimages'];
$max_width = 1000; // 최적화 기준 최대 가로 폭 (Mobile-First)

echo "<!DOCTYPE html><html lang='ko'><head><meta charset='UTF-8'><title>이미지 일괄 다이어트 및 청소기</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>";
echo "<style>body { padding: 20px; font-family: 'Pretendard', sans-serif; background-color: #f8f9fa; } .log { background: #222; color: #0f0; padding: 20px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; height: 600px; overflow-y: auto; line-height: 1.5; } </style>";
echo "</head><body><div class='container max-w-4xl mx-auto'><h3 class='mb-3 fw-bold text-primary'><i class='bi bi-images'></i> 기존 이미지 일괄 다이어트 및 청소기</h3><div class='alert alert-warning fw-bold small'>이 작업은 기존의 불필요한 썸네일을 지우고, 무거운 사진을 최적화합니다. <br>서버 리소스를 사용하므로 창을 닫지 말고 끝날 때까지 기다려 주세요.</div><div class='log shadow-lg' id='logContainer'>";

function log_msg($msg)
{
    echo htmlspecialchars($msg) . "<br>";
    // 새 로그가 찍힐 때마다 스크롤을 맨 아래로 내림
    echo "<script>document.getElementById('logContainer').scrollTop = document.getElementById('logContainer').scrollHeight;</script>";
    flush();
    ob_flush();
}

log_msg("▶ 이미지 다이어트 및 썸네일 청소 작업을 시작합니다...");
log_msg("대상 디렉토리: " . $base_dir . "\n");

$total_thumb_deleted = 0;
$total_resized = 0;
$total_skipped = 0; // 이미 최적화되었거나 작은 파일
$total_errors = 0;

if (!is_dir($base_dir)) {
    log_msg("❌ 오류: 업로드 디렉토리를 찾을 수 없습니다. ({$base_dir})");
    die("</div></div></body></html>");
}

// 하위 디렉토리를 재귀적으로 탐색
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    if ($file->isDir()) continue;

    $filepath = $file->getPathname();
    $filename = $file->getFilename();
    $dir = $file->getPath();
    $parent_folder = basename($dir);

    // 리스트에 노출되는 대상 폴더인지 확인
    if (!in_array($parent_folder, $target_folders)) continue;

    // [청소 1] thumb_ 로 시작하는 과거 썸네일 파일 발견 시 무조건 즉시 삭제
    if (strpos($filename, 'thumb_') === 0) {
        if (@unlink($filepath)) {
            log_msg("  🗑️ 썸네일 삭제 완료: {$parent_folder}/{$filename}");
            $total_thumb_deleted++;
        }
        continue;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

    try {
        $src_img = null;
        if ($ext === 'png') $src_img = @imagecreatefrompng($filepath);
        elseif ($ext === 'gif') $src_img = @imagecreatefromgif($filepath);
        elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src_img = @imagecreatefromwebp($filepath);
        else $src_img = @imagecreatefromjpeg($filepath);

        if (!$src_img) {
            log_msg("  ❌ 오류: 이미지 리소스 로드 실패");
            $total_errors++;
            continue;
        }

        $width = imagesx($src_img);
        $height = imagesy($src_img);

        // [다이어트 2] 해상도가 1000px 을 넘지 않는 착한 사진은 그냥 패스
        if ($width <= $max_width) {
            $total_skipped++;
            imagedestroy($src_img);
            continue;
        }

        log_msg("⚙️ 다이어트 진행 중: {$parent_folder}/{$filename} (원본 가로: {$width}px)");

        // 1000px 비율에 맞게 리사이징
        $new_width = $max_width;
        $new_height = (int)($height * ($max_width / $width));

        $dst_img = imagecreatetruecolor($new_width, $new_height);
        
        // PNG 등 투명 배경 손실 방지 (흰색으로 채움)
        $white_bg = imagecolorallocate($dst_img, 255, 255, 255);
        imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $white_bg);
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // DB 수정 없이 기존 확장자와 이름을 그대로 사용하여 조용히 덮어씁니다.
        $save_success = false;
        if ($ext === 'png') {
            $save_success = imagepng($dst_img, $filepath, 8);
        } elseif ($ext === 'gif') {
            $save_success = imagegif($dst_img, $filepath);
        } else {
            $save_success = imagejpeg($dst_img, $filepath, 82); // 품질 82% 압축
        }

        if ($save_success) {
            log_msg("  ✅ 크기 축소 및 덮어쓰기 완료 (-> 1000px)");
            $total_resized++;
        } else {
            log_msg("  ❌ 덮어쓰기 저장 실패");
            $total_errors++;
        }

        imagedestroy($dst_img);
        imagedestroy($src_img);
    } catch (Exception $e) {
        log_msg("  ❌ 예외 발생: " . $e->getMessage());
        $total_errors++;
    }
}

log_msg("\n=================================");
log_msg("🎉 작업이 모두 완료되었습니다!");
log_msg("- 영구 삭제된 썸네일 찌꺼기: {$total_thumb_deleted} 건 (Inode 절감)");
log_msg("- 다이어트 성공한 큰 사진: {$total_resized} 건 (용량 절감)");
log_msg("- 이미 최적화된 파일 건너뜀: {$total_skipped} 건");
log_msg("- 오류: {$total_errors} 건");
log_msg("=================================");
echo "</div><div class='mt-4 text-center'><a href='/admin/admin_view.php' class='btn btn-primary fw-bold px-5 py-3 rounded-pill shadow'>관리자 메인으로 돌아가기</a></div></div></body></html>";
