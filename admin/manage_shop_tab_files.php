<?php
/**
 * [탭 파일] DB / 파일 용량 관리
 * 위치: admin/manage_shop_tab_files.php
 */
if (!isset($pdo)) exit;

// =========================================================================
// [1] Action 처리
// =========================================================================
if ($tab_mode === 'action') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_shop_file') {
            if (ob_get_level()) ob_clean(); header('Content-Type: application/json');
            $file_path = $_POST['file_path'] ?? '';
            if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']); exit;
            }
            $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
            if (file_exists($absolute_path) && is_file($absolute_path)) {
                if (@unlink($absolute_path)) echo json_encode(['status' => 'success']);
                else echo json_encode(['status' => 'error', 'message' => '파일 삭제 권한이 없습니다.']);
            } else { echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']); }
            exit;
        }
        if ($_POST['action'] === 'delete_shop_files_bulk') {
            if (ob_get_level()) ob_clean(); header('Content-Type: application/json');
            $file_paths = json_decode($_POST['file_paths'] ?? '[]', true);
            if (!is_array($file_paths)) { echo json_encode(['status' => 'error', 'message' => '잘못된 데이터 형식입니다.']); exit; }
            $deleted_count = 0;
            foreach ($file_paths as $file_path) {
                if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) continue;
                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                if (file_exists($absolute_path) && is_file($absolute_path)) { if (@unlink($absolute_path)) $deleted_count++; }
            }
            echo json_encode(['status' => 'success', 'deleted_count' => $deleted_count]);
            exit;
        }
    }
}

// =========================================================================
// [2] Data 로딩
// =========================================================================
if ($tab_mode === 'data') {
    if ($active_tab === 'files') {
        if (function_exists('getShopResourceUsage')) {
            $usage = getShopResourceUsage($pdo, $shop_id);
            $total_disk_usage = $usage['disk'] ?? 0;
            $total_db_usage = $usage['db'] ?? 0;
            $db_details = $usage['db_details'] ?? [];
        }
        if (function_exists('analyzeShopDiskIntegrity')) {
            $integrity_result = analyzeShopDiskIntegrity($pdo, $shop_id);
            $orphaned_files = $integrity_result['orphaned_files'] ?? [];
        }
        $shop_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $s['subdomain'];
        if (is_dir($shop_dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($shop_dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $all_files_list[] = ['path' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname()), 'name' => $file->getFilename(), 'size' => $file->isFile() ? $file->getSize() : 0, 'is_dir' => $file->isDir(), 'depth' => $iterator->getDepth()];
            }
        }
    }
}

// =========================================================================
// [3] View 렌더링
// =========================================================================
if ($tab_mode === 'view'):
?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body"><h5 class="card-title fw-bold text-info"><i class="bi bi-database me-2"></i>DB 사용 용량 분석</h5><div class="alert alert-light border d-flex align-items-center mb-3"><i class="bi bi-server fs-2 me-3 text-info"></i><div><strong class="d-block text-dark">총 DB 사용량: <?= function_exists('formatBytes') ? formatBytes($total_db_usage) : $total_db_usage . ' B' ?></strong></div></div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle border mb-0"><thead class="table-light text-muted small"><tr><th class="ps-3 py-2">테이블명</th><th class="text-end">기록 수(Rows)</th><th class="text-end pe-3">사용 용량</th></tr></thead><tbody class="small"><?php if (empty($db_details)): ?><tr><td colspan="3" class="text-center py-3 text-muted">사용 중인 데이터가 없습니다.</td></tr><?php else: ?><?php foreach ($db_details as $db_item): ?><tr><td class="ps-3"><code class="text-dark"><?= htmlspecialchars($db_item['table']) ?></code></td><td class="text-end"><?= number_format($db_item['rows']) ?> 건</td><td class="text-end pe-3 fw-bold text-secondary"><?= function_exists('formatBytes') ? formatBytes($db_item['size']) : $db_item['size'] . ' B' ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 mt-3">
        <div class="card-body"><h5 class="card-title fw-bold text-primary"><i class="bi bi-hdd-stack me-2"></i>서버 용량 분석</h5><div class="alert alert-info d-flex align-items-center"><i class="bi bi-hdd-fill fs-2 me-3"></i><div><strong class="d-block">총 사용량: <?= function_exists('formatBytes') ? formatBytes($total_disk_usage) : $total_disk_usage . ' B' ?></strong><small>경로: <code>/uploads/shops/<?= htmlspecialchars($s['subdomain']) ?>/</code></small></div></div></div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3"><div class="d-flex justify-content-between align-items-center"><h6 class="m-0 fw-bold text-warning"><i class="bi bi-question-circle-fill me-2"></i>DB에 없는 파일 (고아 파일)</h6><?php if (!empty($orphaned_files)): ?><button type="button" class="btn btn-sm btn-outline-danger btn-delete-all-orphaned" data-paths='<?= htmlspecialchars(json_encode(array_column($orphaned_files, 'path')), ENT_QUOTES, 'UTF-8') ?>'><i class="bi bi-trash3"></i> 전체 일괄 삭제</button><?php endif; ?></div></div>
        <div class="card-body p-0"><ul class="list-group list-group-flush orphaned-file-list"><?php if (empty($orphaned_files)): ?><li class="list-group-item text-center py-4 text-muted"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i> DB에 기록되지 않은 불필요한 파일이 없습니다.</li><?php else: ?><?php foreach ($orphaned_files as $file): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div><code><?= function_exists('getImageModalTrigger') ? getImageModalTrigger($file['path']) : htmlspecialchars($file['path']) ?></code></div><div><span class="badge bg-secondary rounded-pill me-2"><?= htmlspecialchars($file['size_formatted']) ?></span><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-file" data-path="<?= htmlspecialchars($file['path']) ?>"><i class="bi bi-trash"></i> 삭제</button></div></li><?php endforeach; ?><?php endif; ?></ul></div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-dark"><i class="bi bi-folder2-open me-2"></i>전체 파일 목록</h6></div>
        <div class="card-body p-0"><ul class="list-group list-group-flush small"><?php if (empty($all_files_list)): ?><li class="list-group-item text-center py-4 text-muted">업로드된 파일이나 폴더가 없습니다.</li><?php else: ?><?php foreach ($all_files_list as $file): ?><li class="list-group-item d-flex justify-content-between align-items-center" style="padding-left: <?= 1 + $file['depth'] * 1.5 ?>rem;"><div><?php if ($file['is_dir']): ?><i class="bi bi-folder-fill text-warning me-2"></i><strong><?= htmlspecialchars($file['name']) ?></strong><?php else: $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)); $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']); $web_path = str_replace('\\', '/', $file['path']); if ($is_image): ?><i class="bi bi-file-image text-primary me-2"></i><?= function_exists('getImageModalTrigger') ? getImageModalTrigger($web_path, $file['name']) : htmlspecialchars($file['name']) ?><?php else: ?><i class="bi bi-file-earmark-text text-muted me-2"></i><?= htmlspecialchars($file['name']) ?><?php endif; endif; ?></div><?php if (!$file['is_dir']): ?><span class="badge bg-light text-dark border"><?= function_exists('formatBytes') ? formatBytes($file['size']) : $file['size'] . ' B' ?></span><?php endif; ?></li><?php endforeach; ?><?php endif; ?></ul></div>
    </div>
<?php endif; ?>