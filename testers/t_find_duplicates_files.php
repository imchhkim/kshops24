<?php

require_once __DIR__ . '/t_common.php';

$root_dir = dirname(__DIR__);

// [AJAX] 파일 내용 조회 (비교 렌더링용)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_file_contents') {
    $paths = $_POST['paths'] ?? [];
    $contents = [];
    foreach ($paths as $rel_path) {
        $real_path = realpath($root_dir . $rel_path);
        // 상위 디렉토리 접근 우회 방지
        if ($real_path && strpos($real_path, realpath($root_dir)) === 0 && file_exists($real_path)) {
            $contents[$rel_path] = htmlspecialchars(file_get_contents($real_path));
        } else {
            $contents[$rel_path] = "파일을 찾을 수 없거나 읽을 수 없습니다.";
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'data' => $contents]);
    exit;
}

// 검사에서 제외할 폴더들 (벤더 패키지, 업로드 파일, 깃 내역 등)
$exclude_dirs = ['.git', '.idea', 'vendor', 'node_modules', 'uploads', 'assets', '_notes'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$name_map = [];
$hash_map = [];

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $rel_path = str_replace($root_dir, '', $path);
    $rel_path = str_replace('\\', '/', $rel_path); // 윈도우/리눅스 경로 기호 통일

    // 폴더 필터링
    $skip = false;
    foreach ($exclude_dirs as $ex) {
        if (strpos($rel_path, "/{$ex}/") !== false || strpos($rel_path, "/{$ex}") === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    // PHP 파일만 검사
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filename = $file->getFilename();
        $hash = md5_file($path); // 파일 내용 해시 (내용이 1글자라도 다르면 해시가 다름)

        $name_map[$filename][] = $rel_path;
        $hash_map[$hash][] = $rel_path;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KShops24 파일 중복 검사기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="bg-light pb-5">
    <div class="container mt-5">
        <div class="card shadow-sm mb-4 border-start border-5 border-primary">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-files me-2"></i>중복 파일 탐지 (t_find_duplicates_files.php)</h4>
                <p class="card-text text-muted small">
                    프로젝트 내에 이름이 같거나 내용이 100% 동일한 중복 파일을 찾아냅니다. 이를 통해 불필요한 코드 중복을 제거하고 잠재적인 경로 오류를 예방할 수 있습니다.
                </p>
                <ul class="small text-secondary mb-0" style="list-style-type: '👉&nbsp;'; padding-left: 1.2rem;">
                    <li><strong>사용법:</strong> 브라우저에서 이 페이지에 접속하면 즉시 스캔을 시작하고 아래에 결과를 표시합니다.</li>
                    <li><strong>이름 중복:</strong> 다른 폴더에 동일한 이름의 파일이 존재하는 경우입니다. `require` 경로 오류의 원인이 될 수 있습니다.</li>
                    <li><strong>내용 중복:</strong> 이름은 다르지만 코드가 완전히 똑같은 복사본 파일입니다. 하나로 통합하여 관리를 용이하게 하는 것이 좋습니다.</li>
                </ul>
            </div>
        </div>

        <h2 class="fw-bold mb-4"><i class="bi bi-search text-primary me-2"></i>파일 중복 스캔 결과</h2>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-25 text-dark fw-bold border-0 py-3">
                <i class="bi bi-files me-2"></i>1. 이름이 완전히 똑같은 파일들 (위치 오류 의심)
            </div>
            <div class="list-group list-group-flush">
                <?php
                $found_name_dup = false;
                foreach ($name_map as $filename => $paths) {
                    if (count($paths) > 1) {
                        $found_name_dup = true;
                        echo '<div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">';
                        echo "<div>";
                        echo "<h6 class='fw-bold text-danger mb-2'>{$filename}</h6>";
                        echo "<ul class='mb-0 small font-monospace text-muted'>";
                        foreach ($paths as $p) echo "<li>{$p}</li>";
                        echo "</ul></div>";
                        $paths_json = htmlspecialchars(json_encode($paths), ENT_QUOTES, 'UTF-8');
                        echo "<div><button class='btn btn-sm btn-outline-primary btn-compare shadow-sm fw-bold' data-paths='{$paths_json}' data-filename='{$filename}'><i class='bi bi-layout-split me-1'></i>파일 내용 비교</button></div>";
                        echo "</div>";
                    }
                }
                if (!$found_name_dup) echo '<div class="p-4 text-center text-muted">이름이 중복된 파일이 없습니다! 깔끔합니다.</div>';
                ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger bg-opacity-10 text-dark fw-bold border-0 py-3">
                <i class="bi bi-file-earmark-diff me-2"></i>2. 내용(코드)이 100% 똑같은 쌍둥이 파일들 (불필요한 복사본)
            </div>
            <div class="list-group list-group-flush">
                <?php
                $found_hash_dup = false;
                foreach ($hash_map as $hash => $paths) {
                    if (count($paths) > 1) {
                        $found_hash_dup = true;
                        echo '<div class="list-group-item">';
                        echo "<ul class='mb-0 small font-monospace text-primary'>";
                        foreach ($paths as $p) echo "<li>{$p}</li>";
                        echo "</ul></div>";
                    }
                }
                if (!$found_hash_dup) echo '<div class="p-4 text-center text-muted">내용이 중복된 파일이 없습니다!</div>';
                ?>
            </div>
        </div>
    </div>

    <!-- 파일 비교용 풀스크린 모달 -->
    <div class="modal fade" id="compareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0 shadow-lg bg-light">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title fw-bold" id="compareModalLabel"><i class="bi bi-layout-split me-2"></i>파일 내용 비교</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="compareContainer" class="d-flex flex-nowrap h-100 overflow-auto">
                        <!-- AJAX를 통해 양쪽 파일의 코드가 동적으로 추가됩니다 -->
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white shadow-sm">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill fw-bold shadow-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-compare').forEach(btn => {
            btn.addEventListener('click', async function() {
                const paths = JSON.parse(this.getAttribute('data-paths'));
                const filename = this.getAttribute('data-filename');
                
                document.getElementById('compareModalLabel').innerHTML = `<i class="bi bi-file-earmark-text me-2"></i>파일 비교 스캔: ${filename}`;
                const container = document.getElementById('compareContainer');
                container.innerHTML = '<div class="p-5 text-center w-100 d-flex flex-column justify-content-center align-items-center"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div><div class="mt-3 text-muted fw-bold">서버에서 파일 코드를 불러오는 중입니다...</div></div>';
                
                new bootstrap.Modal(document.getElementById('compareModal')).show();

                const formData = new FormData();
                formData.append('action', 'get_file_contents');
                paths.forEach(p => formData.append('paths[]', p));

                try {
                    const response = await fetch('t_find_duplicates_files.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.status === 'success') {
                        container.innerHTML = '';
                        for (const [path, content] of Object.entries(result.data)) {
                            const div = document.createElement('div');
                            div.style.flex = "1 1 0";
                            div.style.minWidth = "500px";
                            div.className = `p-3 border-end d-flex flex-column h-100 bg-white`;
                            div.innerHTML = `
                                <div class="fw-bold mb-3 text-primary bg-light border p-2 rounded shadow-sm text-truncate" title="${path}">
                                    <i class="bi bi-hdd-fill me-1"></i>${path}
                                </div>
                                <pre class="bg-dark text-light p-3 rounded shadow-sm flex-grow-1 overflow-auto small font-monospace mb-0" style="white-space: pre;"><code>${content}</code></pre>
                            `;
                            container.appendChild(div);
                        }
                    } else {
                        container.innerHTML = '<div class="p-5 text-center text-danger w-100">파일을 불러오는 데 실패했습니다.</div>';
                    }
                } catch (err) {
                    container.innerHTML = '<div class="p-5 text-center text-danger w-100">통신 중 오류가 발생했습니다.</div>';
                }
            });
        });
    });
    </script>
</body>

</html>