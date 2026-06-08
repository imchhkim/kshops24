<?php

/**
 * [하위 파일] KShops24 상점 시스템 무결성 점검 도구
 * 위치: /public_html/testers/t_check_integrity_of_shops.php
 * 역할: DB와 실제 파일 시스템(/uploads) 간의 불일치를 찾아내고 정리합니다.
 */

require_once __DIR__ . '/t_common.php';

// =========================================================================
// [설정] 환경별 경로 및 DB 설정
// =========================================================================
$live_root = "/home/u743828642/domains/kshops24.com/public_html";
$test_root = $live_root . "/test_env";

$target_env = $_GET['env'] ?? (IS_TEST_ENV ? 'test' : 'live');
$is_live_mode = ($target_env === 'live');

// 대상 환경에 따른 변수 설정
if ($is_live_mode) {
    $target_db_name = LIVE_DB_NAME;
    $target_db_user = LIVE_DB_USER;
    $target_db_pass = LIVE_DB_PASS;
    $target_uploads_dir = $live_root . "/uploads/shops";
    $env_label = "서비스 환경 (Live)";
    $env_class = "primary";
} else {
    $target_db_name = TEST_DB_NAME;
    $target_db_user = TEST_DB_USER;
    $target_db_pass = TEST_DB_PASS;
    $target_uploads_dir = $test_root . "/uploads/shops";
    $env_label = "테스트 환경 (Test)";
    $env_class = "danger";
}

// 대상 환경용 PDO 별도 생성
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . $target_db_name . ";charset=utf8mb4";
$pdo_target = new PDO($dsn, $target_db_user, $target_db_pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

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
            if (empty($dir_path) || strpos($dir_path, '/uploads/shops/') !== 0 || strpos($dir_path, '..') !== false) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 폴더 경로입니다.']);
                exit;
            }
            $abs_path = ($is_live_mode ? $live_root : $test_root) . $dir_path;
            if (function_exists('deleteDirectoryCompletely') && deleteDirectoryCompletely($abs_path)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '폴더 삭제에 실패했습니다.']);
            }
            exit;
        }
        
        // 2. 단일 파일 삭제
        if ($action === 'delete_file') {
            $file_path = $_POST['path'] ?? '';
            if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) {
                echo json_encode(['status' => 'error', 'message' => '잘못된 파일 경로입니다.']);
                exit;
            }
            $abs_path = ($is_live_mode ? $live_root : $test_root) . $file_path;
            if (file_exists($abs_path) && unlink($abs_path)) {
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
                    if (empty($dir_path) || strpos($dir_path, '/uploads/shops/') !== 0 || strpos($dir_path, '..') !== false) continue;
                    $abs_path = ($is_live_mode ? $live_root : $test_root) . $dir_path;
                    if (function_exists('deleteDirectoryCompletely') && deleteDirectoryCompletely($abs_path)) {
                        $deleted++;
                    }
                }
            }
            echo json_encode(['status' => 'success', 'count' => $deleted]);
            exit;
        }

        // 4. 다중 파일 일괄 삭제
        if ($action === 'delete_all_files') {
            $paths = json_decode($_POST['paths'] ?? '[]', true);
            $deleted = 0;
            if (is_array($paths)) {
                foreach ($paths as $file_path) {
                    if (empty($file_path) || strpos($file_path, '/uploads/shops/') !== 0 || strpos($file_path, '..') !== false) continue;
                    $abs_path = ($is_live_mode ? $live_root : $test_root) . $file_path;
                    if (file_exists($abs_path) && unlink($abs_path)) $deleted++;
                }
            }
            echo json_encode(['status' => 'success', 'count' => $deleted]);
            exit;
        }

        // 5. 상점별 깨진 DB 링크 복구 (캐시 정리 기능)
        if ($action === 'fix_shop_cache') {
            $links = json_decode($_POST['links'] ?? '[]', true);
            $fixed_count = 0;

            if (!empty($links)) {
                foreach ($links as $link) {
                    $table = $link['table'];
                    $path = $link['path'];
                    
                    // 테이블별 업데이트 처리 (경로 값을 빈 값으로 초기화)
                    switch ($table) {
                        case 'shops':
                            $stmt = $pdo_target->prepare("UPDATE shops SET logo_path = CASE WHEN logo_path = ? THEN '' ELSE logo_path END, bg_path = CASE WHEN bg_path = ? THEN '' ELSE bg_path END WHERE logo_path = ? OR bg_path = ?");
                            $stmt->execute([$path, $path, $path, $path]);
                            $fixed_count += $stmt->rowCount();
                            break;
                        case 'shop_items':
                            $stmt = $pdo_target->prepare("UPDATE shop_items SET item_img = '' WHERE item_img = ?");
                            $stmt->execute([$path]);
                            $fixed_count += $stmt->rowCount();
                            break;
                        case 'shop_images':
                            $stmt = $pdo_target->prepare("DELETE FROM shop_images WHERE img_path = ?");
                            $stmt->execute([$path]);
                            $fixed_count += $stmt->rowCount();
                            break;
                        case 'shop_item_boards':
                            $stmt = $pdo_target->prepare("UPDATE shop_item_boards SET board_img_path = '' WHERE board_img_path = ?");
                            $stmt->execute([$path]);
                            $fixed_count += $stmt->rowCount();
                            break;
                        case 'reviews':
                            $stmt = $pdo_target->prepare("UPDATE reviews SET img_path = '' WHERE img_path = ?");
                            $stmt->execute([$path]);
                            $fixed_count += $stmt->rowCount();
                            break;
                    }
                }
                echo json_encode(['status' => 'success', 'message' => "총 {$fixed_count}건의 유실된 기록이 정리되었습니다."]);
            } else {
                echo json_encode(['status' => 'error', 'message' => '정리할 데이터가 없습니다.']);
            }
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
if (function_exists('analyzeSystemDiskIntegrity')) {
    // 선택된 환경의 DB와 물리적 파일 경로를 전달하여 분석 수행
    $integrity = analyzeSystemDiskIntegrity($pdo_target, $target_uploads_dir);
} else {
    $integrity = [];
}
$orphaned_directories = $integrity['orphaned_directories'] ?? [];
$orphaned_files = $integrity['orphaned_files'] ?? [];
$broken_links = $integrity['broken_links'] ?? [];

// [추가] 깨진 링크 데이터를 상점별로 그룹화
$grouped_broken_links = [];
foreach ($broken_links as $link) {
    $grouped_broken_links[$link['shop_name']][] = $link;
}

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
                    <h4 class="card-title fw-bold text-primary m-0"><i class="bi bi-shield-check me-2"></i>상점 시스템 무결성 점검 <span class="badge bg-<?php echo $env_class; ?> ms-2"><?php echo $env_label; ?></span></h4>
                    <div class="btn-group shadow-sm">
                        <a href="?env=test" class="btn btn-sm <?php echo !$is_live_mode ? 'btn-danger' : 'btn-outline-primary'; ?> fw-bold">테스트 환경</a>
                        <a href="?env=live" class="btn btn-sm <?php echo $is_live_mode ? 'btn-primary' : 'btn-outline-danger'; ?> fw-bold">서비스 환경</a>
                    </div>
                </div>
                <div class="mt-3 bg-light p-2 rounded small text-secondary">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>검사 대상 DB:</strong> <code><?php echo $target_db_name; ?></code>
                        </div>
                        <div class="col-md-6">
                            <strong>파일 스캔 경로:</strong> <code><?php echo $target_uploads_dir; ?></code>
                        </div>
                    </div>
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
                    <span class="badge bg-danger ms-2"><?php echo count($broken_links); ?>건</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" style="max-height: 800px; overflow-y: auto;">
                    <?php if (empty($grouped_broken_links)): ?>
                        <li class="list-group-item text-center py-4 text-muted border-0">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>깨진 파일 링크가 없습니다.
                        </li>
                    <?php else: ?>
                        <?php foreach ($grouped_broken_links as $shop_name => $links): ?>
                            <li class="list-group-item bg-light fw-bold border-bottom-0 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-shop me-2 text-primary"></i><?php echo htmlspecialchars($shop_name); ?> 
                                    <span class="badge bg-white text-danger border ms-1 fw-normal" style="font-size: 0.7rem;"><?php echo count($links); ?></span>
                                </div>
                                <button type="button" class="btn btn-xs btn-warning py-0 px-2 fw-bold shadow-sm btn-fix-cache" data-links='<?php echo htmlspecialchars(json_encode($links), ENT_QUOTES, 'UTF-8'); ?>' style="font-size: 0.65rem;">
                                    <i class="bi bi-eraser-fill me-1"></i>기록 삭제 (캐시 정리)
                                </button>
                            </li>
                            <?php foreach ($links as $link): ?>
                                <li class="list-group-item ps-4 border-top-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-dark me-2" style="font-size: 0.7rem; min-width: 80px;"><?php echo htmlspecialchars($link['table']); ?></span>
                                        <code class="text-danger text-truncate flex-grow-1" title="<?php echo htmlspecialchars($link['path']); ?>">
                                            <?php echo htmlspecialchars($link['path']); ?>
                                        </code>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-white text-muted small py-3">
                <i class="bi bi-info-circle me-1"></i> <strong>기록 삭제</strong> 버튼을 누르면 DB에 남아있는 유실된 파일 경로를 지웁니다. 이 작업은 실제 데이터를 수정하므로 신중히 집행하세요.
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

                // 상점별 깨진 링크(캐시) 정리 이벤트
                const fixBtn = e.target.closest('.btn-fix-cache');
                if (fixBtn) {
                    if (!confirm('이 상점의 유실된 파일 기록을 DB에서 삭제하시겠습니까?\n(잔상 제거 및 엑박 방지)')) return;
                    
                    const links = fixBtn.dataset.links;
                    fixBtn.disabled = true;
                    fixBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>정리 중...';
                    
                    const fd = new FormData();
                    fd.append('action', 'fix_shop_cache');
                    fd.append('links', links);
                    
                    try {
                        const res = await fetch(location.href, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status === 'success') {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('오류: ' + data.message);
                            fixBtn.disabled = false;
                        }
                    } catch (err) {
                        alert('통신 오류가 발생했습니다.');
                        fixBtn.disabled = false;
                    }
                }
            });
        });
    </script>
</body>
</html>