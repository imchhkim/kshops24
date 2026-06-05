<?php

/**
 * [하위 파일] KShops24 상점 시스템 무결성 점검 도구
 * 위치: /public_html/testers/t_check_integrity_of_shops.php
 * 역할: DB와 실제 파일 시스템(/uploads) 간의 불일치를 찾아내고 정리합니다.
 */

require_once __DIR__ . '/t_common.php';

// =========================================================================
// [AJAX 처리] 삭제 액션 라우팅
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];

    try {
        // 1. 단일 폴더 삭제
        if ($action === 'delete_dir') {
            $dir_path = $_POST['path'] ?? '';
            if (empty($dir_path) || strpos($dir_path, SHOP_UPLOADS_URL) !== 0 || strpos($dir_path, '..') !== false) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 폴더 경로입니다.']);
                exit;
            }
            $abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $dir_path;
            if (deleteDirectoryCompletely($abs_path)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '폴더 삭제에 실패했습니다.']);
            }
            exit;
        }
        
        // 2. 단일 파일 삭제
        if ($action === 'delete_file') {
            $file_path = $_POST['path'] ?? '';
            if (deletePhysicalFiles([$file_path]) > 0) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '파일 삭제에 실패했습니다.']);
            }
            exit;
        }

        // 3. 다중 폴더 일괄 삭제
        if ($action === 'delete_all_dirs') {
            $paths = json_decode($_POST['paths'] ?? '[]', true);
            $deleted = 0;
            if (is_array($paths)) {
                foreach ($paths as $dir_path) {
                    if (empty($dir_path) || strpos($dir_path, SHOP_UPLOADS_URL) !== 0 || strpos($dir_path, '..') !== false) continue;
                    $abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $dir_path;
                    if (deleteDirectoryCompletely($abs_path)) $deleted++;
                }
            }
            echo json_encode(['status' => 'success', 'count' => $deleted]);
            exit;
        }

        // 4. 다중 파일 일괄 삭제
        if ($action === 'delete_all_files') {
            $paths = json_decode($_POST['paths'] ?? '[]', true);
            $deleted = deletePhysicalFiles($paths);
            echo json_encode(['status' => 'success', 'count' => $deleted]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// [데이터 준비] 시스템 전체 무결성 분석 (lib_utils.php 의 공용 함수 활용)
// =========================================================================
$integrity = analyzeSystemDiskIntegrity($pdo);
$orphaned_directories = $integrity['orphaned_directories'] ?? [];
$orphaned_files = $integrity['orphaned_files'] ?? [];
$broken_links = $integrity['broken_links'] ?? [];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상점 무결성 점검기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light pb-5">
    <div class="container mt-5">
        <div class="card shadow-sm mb-4 border-start border-5 border-primary">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="card-title fw-bold text-primary m-0"><i class="bi bi-shield-check me-2"></i>전체 상점 시스템 무결성 점검</h4>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm fw-bold" onclick="location.reload();">
                        <i class="bi bi-arrow-clockwise me-1"></i>다시 스캔
                    </button>
                </div>
                <p class="card-text text-muted small mt-2">
                    DB 데이터(shops, shop_items 등)와 실제 디스크(/uploads)를 교차 검증하여, 불일치하는 쓰레기 데이터나 유실된 파일을 찾아내고 청소합니다.
                </p>
            </div>
        </div>

        <!-- 1. 고아 폴더 (Orphaned Directories) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-danger bg-opacity-10 py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-danger m-0">
                    <i class="bi bi-folder-x me-2"></i>주인 잃은 상점 폴더 (DB에 없는 폴더) 
                    <span class="badge bg-danger ms-2"><?php echo count($orphaned_directories); ?>건</span>
                </h6>
                <?php if (!empty($orphaned_directories)): ?>
                    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm btn-bulk-action" data-action="delete_all_dirs" data-paths='<?php echo htmlspecialchars(json_encode(array_column($orphaned_directories, 'path')), ENT_QUOTES, 'UTF-8'); ?>'>
                        <i class="bi bi-trash3 me-1"></i>일괄 삭제
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" id="list-dirs">
                    <?php if (empty($orphaned_directories)): ?>
                        <li class="list-group-item text-center py-4 text-muted border-0">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>발견된 고아 폴더가 없습니다.
                        </li>
                    <?php else: ?>
                        <?php foreach ($orphaned_directories as $dir): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-folder-fill text-warning me-2"></i><code><?php echo htmlspecialchars($dir['path']); ?></code>
                                </div>
                                <div>
                                    <span class="badge bg-secondary rounded-pill me-2"><?php echo htmlspecialchars($dir['size_formatted']); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-single-action" data-action="delete_dir" data-path="<?php echo htmlspecialchars($dir['path']); ?>">
                                        <i class="bi bi-trash"></i> 삭제
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- 2. 고아 파일 (Orphaned Files) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-10 py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark m-0">
                    <i class="bi bi-file-earmark-x me-2 text-warning"></i>DB에 등록되지 않은 잉여 사진 (고아 파일) 
                    <span class="badge bg-warning text-dark ms-2"><?php echo count($orphaned_files); ?>건</span>
                </h6>
                <?php if (!empty($orphaned_files)): ?>
                    <button type="button" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm btn-bulk-action" data-action="delete_all_files" data-paths='<?php echo htmlspecialchars(json_encode(array_column($orphaned_files, 'path')), ENT_QUOTES, 'UTF-8'); ?>'>
                        <i class="bi bi-trash3 me-1"></i>일괄 삭제
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" id="list-files" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($orphaned_files)): ?>
                        <li class="list-group-item text-center py-4 text-muted border-0">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>발견된 잉여 사진이 없습니다.
                        </li>
                    <?php else: ?>
                        <?php foreach ($orphaned_files as $file): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="text-truncate" style="max-width: 70%;">
                                    <span class="badge bg-light text-dark border me-2"><?php echo htmlspecialchars($file['shop_name']); ?></span>
                                    <code><?php echo htmlspecialchars($file['path']); ?></code>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge bg-secondary rounded-pill me-2"><?php echo htmlspecialchars($file['size_formatted']); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-single-action" data-action="delete_file" data-path="<?php echo htmlspecialchars($file['path']); ?>">
                                        <i class="bi bi-trash"></i> 삭제
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- 3. 깨진 링크 (Broken Links) -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary bg-opacity-10 py-3 border-0">
                <h6 class="fw-bold text-dark m-0">
                    <i class="bi bi-link-45deg me-2 text-danger"></i>DB엔 있으나 파일이 유실된 기록 (엑스박스 의심)
                    <span class="badge bg-secondary ms-2"><?php echo count($broken_links); ?>건</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($broken_links)): ?>
                        <li class="list-group-item text-center py-4 text-muted border-0">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>깨진 파일 링크가 없습니다.
                        </li>
                    <?php else: ?>
                        <?php foreach ($broken_links as $link): ?>
                            <li class="list-group-item">
                                <span class="badge bg-light text-dark border me-2" title="상점명"><?php echo htmlspecialchars($link['shop_name']); ?></span>
                                <span class="badge bg-dark me-2" title="DB 테이블명"><?php echo htmlspecialchars($link['table']); ?></span>
                                <code class="text-danger"><?php echo htmlspecialchars($link['path']); ?></code>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-white text-muted small py-3">
                <i class="bi bi-info-circle me-1"></i> 이 항목은 직접 삭제할 수 없으며, 점주가 해당 매물/메뉴의 이미지를 다시 업로드하거나 삭제해야 해결됩니다.
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 단일 삭제 이벤트 위임
            document.body.addEventListener('click', async function(e) {
                const singleBtn = e.target.closest('.btn-single-action');
                if (singleBtn) {
                    if (!confirm('정말로 이 항목을 삭제하시겠습니까? (복구 불가)')) return;
                    
                    const action = singleBtn.dataset.action;
                    const path = singleBtn.dataset.path;
                    const li = singleBtn.closest('li');
                    
                    singleBtn.disabled = true;
                    singleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('path', path);
                    
                    try {
                        const res = await fetch(location.href, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status === 'success') {
                            li.style.transition = '0.3s';
                            li.style.opacity = '0';
                            setTimeout(() => li.remove(), 300);
                        } else {
                            alert('오류: ' + data.message);
                            singleBtn.disabled = false;
                        }
                    } catch (err) {
                        alert('통신 오류가 발생했습니다.');
                        singleBtn.disabled = false;
                    }
                }

                // 다중 일괄 삭제 이벤트 위임
                const bulkBtn = e.target.closest('.btn-bulk-action');
                if (bulkBtn) {
                    if (!confirm('목록에 있는 모든 항목을 일괄 삭제하시겠습니까? (복구 불가)')) return;
                    
                    const action = bulkBtn.dataset.action;
                    const paths = bulkBtn.dataset.paths;
                    
                    bulkBtn.disabled = true;
                    bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>처리 중...';
                    
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('paths', paths);
                    
                    try {
                        const res = await fetch(location.href, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status === 'success') {
                            alert(`총 ${data.count}개의 항목이 일괄 삭제되었습니다.`);
                            location.reload();
                        } else {
                            alert('오류: ' + data.message);
                            location.reload();
                        }
                    } catch (err) {
                        alert('통신 오류가 발생했습니다.');
                        location.reload();
                    }
                }
            });
        });
    </script>
</body>
</html>