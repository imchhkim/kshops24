<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/t_common.php';

// =========================================================================
// [설정 영역]
// =========================================================================
// [수정] 테스트 환경과 서비스 환경의 실제 서버 절대 경로 정의
$live_root = "/home/u743828642/domains/kshops24.com/public_html";
$test_root = $live_root . "/test_env";

// 검사에서 제외할 폴더나 파일들 (이미지 업로드 폴더나 벤더 패키지 등)
// [추가] test_env 폴더 자체는 비교 대상에서 제외 (Live 내부에 존재하므로)
$exclude_dirs = ['.git', '.idea', 'vendor', 'node_modules', 'uploads', '_notes', '.dbclient', 'test_env'];

// 검사할 텍스트 파일 확장자 (Windows/Linux 줄바꿈 차이 보정 대상)
$text_extensions = ['php', 'js', 'css', 'html', 'json', 'txt', 'md', 'xml', 'htaccess', 'sql'];
// 검사할 바이너리 파일 확장자 (원본 그대로 해시 비교 대상 - 이미지 등)
$binary_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];
// 스캔할 전체 확장자 목록
$allowed_extensions = array_merge($text_extensions, $binary_extensions);

// =========================================================================
// [코어 로직] 파일 목록 및 MD5 해시 수집
// =========================================================================
function getFileList($dir)
{
    global $exclude_dirs, $allowed_extensions, $text_extensions;
    $result = [];
    $root = realpath($dir);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
        RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();
        // [버그 수정] str_replace는 경로 내에 동일한 폴더명이 중복될 경우 모두 지워버리는 치명적 버그가 있으므로 앞부분만 정확히 잘라냅니다.
        $rel_path = substr($path, strlen($root));
        $rel_path = str_replace('\\', '/', $rel_path); // 윈도우 경로를 리눅스식(/)으로 통일

        // 제외 디렉토리/파일 필터링
        $skip = false;
        foreach ($exclude_dirs as $ex) {
            if (strpos($rel_path, "/{$ex}/") !== false || strpos($rel_path, "/{$ex}") === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        if ($file->isFile()) {
            $fileName = $file->getFilename();
            $ext = strtolower($file->getExtension());
            
            // .htaccess 같은 숨김 파일 처리 (이름이 점으로 시작하고 확장자 추출이 안 된 경우)
            if (empty($ext) && strpos($fileName, '.') === 0) {
                $ext = substr($fileName, 1);
            }

            if (in_array($ext, $allowed_extensions)) {
                // 자기 자신(t_check_files_sync.php)은 비교에서 제외
                if (strpos($rel_path, 't_check_files_sync.php') !== false) continue;

                $content = file_get_contents($path);
                
                // [핵심 보완] 텍스트 파일만 줄바꿈(CRLF->LF) 보정을 수행하고, 
                // 이미지 파일은 원본 그대로 해시를 생성하여 파일이 깨졌다고 오진하는 현상 방지
                if (in_array($ext, $text_extensions)) {
                    $content_to_hash = str_replace("\r\n", "\n", $content);
                } else {
                    $content_to_hash = $content;
                }

                $result[$rel_path] = [
                    'size' => $file->getSize(),
                    'mtime' => date('Y-m-d H:i:s', $file->getMTime()),
                    'md5' => md5($content_to_hash) 
                ];
            }
        }
    }
    return $result;
}

// =========================================================================
// [실행 영역] 테스트 환경과 서비스 환경 비교
// =========================================================================
$local_files = [];
$remote_files = [];
$remote_files_original = []; // 화면 하단 폴더 구조 출력을 위한 서버 원본 보존용
$error_msg = '';

try {
    if (!is_dir($test_root)) {
        throw new Exception("테스트 환경 경로를 찾을 수 없습니다: {$test_root}");
    }
    if (!is_dir($live_root)) {
        throw new Exception("서비스 환경 경로를 찾을 수 없습니다: {$live_root}");
    }

    // 양쪽 환경의 파일 목록 직접 수집
    $local_files = getFileList($test_root);
    $remote_files = getFileList($live_root);
    $remote_files_original = $remote_files;
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

// 2. 양쪽 데이터 비교 로직
$only_local = [];
$only_remote = [];
$modified = [];
$identical_count = 0;

if (empty($error_msg) && is_array($remote_files)) {
    foreach ($local_files as $path => $local_info) {
        if (!isset($remote_files[$path])) {
            $only_local[$path] = $local_info;
        } else {
            $remote_info = $remote_files[$path];
            // MD5 해시 비교 (내용이 1바이트라도 다르면 해시가 다름)
            if ($local_info['md5'] !== $remote_info['md5']) {
                $modified[$path] = [
                    'local' => $local_info,
                    'remote' => $remote_info
                ];
            } else {
                $identical_count++;
            }
            // 비교가 끝난 파일은 원격 배열에서 제거 (마지막에 남은 건 서버에만 있는 파일)
            unset($remote_files[$path]);
        }
    }
    $only_remote = $remote_files; // 서버에만 있는 파일들
}

// 평면적인 파일 경로 목록을 시각적인 트리(폴더) 구조 HTML로 변환하는 함수
function renderFolderStructure($files, $modified_keys = [])
{
    if (empty($files) || !is_array($files)) {
        return [
            'html' => '<div class="text-muted py-3 text-center">데이터가 없습니다.</div>',
            'text' => '데이터가 없습니다.'
        ];
    }

    $paths = array_keys($files);
        
        // [수정] 일반 탐색기처럼 폴더를 먼저(알파벳순), 그 다음 파일을(알파벳순) 정렬하는 커스텀 정렬 로직
        usort($paths, function ($a, $b) {
            $partsA = explode('/', ltrim($a, '/'));
            $partsB = explode('/', ltrim($b, '/'));
            $lenA = count($partsA);
            $lenB = count($partsB);
            $minLen = min($lenA, $lenB);
            for ($i = 0; $i < $minLen; $i++) {
                if ($partsA[$i] !== $partsB[$i]) {
                    $isDirA = ($i < $lenA - 1);
                    $isDirB = ($i < $lenB - 1);
                    if ($isDirA && !$isDirB) return -1; // A가 폴더, B가 파일이면 A(폴더) 우선
                    if (!$isDirA && $isDirB) return 1;  // B가 폴더, A가 파일이면 B(폴더) 우선
                    return strcasecmp($partsA[$i], $partsB[$i]); // 둘 다 동일한 타입이면 대소문자 구분 없이 알파벳 순
                }
            }
            return $lenA <=> $lenB;
        });

    $html = '<ul class="list-unstyled font-monospace mb-0" style="font-size: 0.85rem; line-height: 1.8; white-space: nowrap;">';
    $text = "";
    $renderedDirs = [];

    foreach ($paths as $path) {
        $parts = explode('/', ltrim($path, '/'));
        $fileName = array_pop($parts);
        $currentDir = '';
        $depth = 0;
        foreach ($parts as $dir) {
            $currentDir .= '/' . $dir;
            if (!isset($renderedDirs[$currentDir])) {
                $renderedDirs[$currentDir] = true;
                $html .= '<li style="padding-left: ' . ($depth * 20) . 'px; color: #004aad; font-weight: bold;"><i class="bi bi-folder-fill me-2 text-warning"></i>' . htmlspecialchars($dir) . '</li>';
                $text .= str_repeat("  ", $depth) . "- 📁 " . $dir . "/\n";
            }
            $depth++;
        }

        $is_modified = in_array($path, $modified_keys);
        $text_class = $is_modified ? 'text-danger fw-bold' : 'text-dark';
        $modified_text = $is_modified ? ' [수정됨]' : '';

        // 파일의 용량과 수정 날짜 포맷팅
        $fileSize = isset($files[$path]['size']) ? formatBytes($files[$path]['size']) : '';
        $fileTime = isset($files[$path]['mtime']) ? date('y-m-d H:i', strtotime($files[$path]['mtime'])) : '';

        $html .= '<li style="padding-left: ' . ($depth * 20) . 'px;" class="' . $text_class . ' text-truncate" title="' . htmlspecialchars($fileName) . '"><i class="bi bi-file-earmark-text me-2 text-secondary"></i>' . htmlspecialchars($fileName) . '</li>';
        $text .= str_repeat("  ", $depth) . "- 📄 " . $fileName . "  # " . $fileSize . " | " . $fileTime . $modified_text . "\n";
    }
    $html .= '</ul>';

    return [
        'html' => $html,
        'text' => trim($text)
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로컬-서버 파일 동기화 체크</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Apple SD Gothic Neo', sans-serif;
            padding-bottom: 50px;
        }

        .badge-count {
            font-size: 1rem;
            padding: 5px 12px;
        }

        .file-path {
            font-family: monospace;
            font-size: 0.95rem;
            color: #d63384;
            word-break: break-all;
        }

        .data-row {
            font-size: 0.85rem;
        }

        .card {
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            font-weight: bold;
            font-size: 1.1rem;
            padding: 15px 20px;
        }
    </style>
</head>

<body class="bg-light pb-5">
    <div class="container mt-5">
        
        <div class="card shadow-sm mb-4 border-start border-5 border-primary">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-arrow-repeat me-2"></i>파일 동기화 체크 (t_check_files_sync.php)</h4>
                <p class="card-text text-muted small">
                    테스트 환경(test_env)과 서비스 환경(Live) 간의 소스 코드 파일 동기화 상태를 비교 점검합니다.
                </p>
                <ul class="small text-secondary mb-0" style="list-style-type: '👉&nbsp;'; padding-left: 1.2rem;">
                    <li><strong>내용 불일치:</strong> 양쪽 환경에 모두 존재하지만, 코드가 수정되어 내용(MD5 해시)이 다른 파일입니다. 배포가 필요할 수 있습니다.</li>
                    <li><strong>누락된 파일:</strong> 한 쪽 환경에만 존재하는 파일입니다. 배포 시 누락되었거나 삭제된 파일인지 확인하세요.</li>
                    <li><strong>주의:</strong> <code>uploads</code> 폴더와 <code>test_env</code> 폴더 내부 데이터는 비교에서 제외됩니다.</li>
                </ul>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><i class="bi bi-search me-2 text-primary"></i>동기화 스캔 결과</h2>
            <button onclick="location.reload()" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-clockwise me-1"></i>다시 검사</button>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>오류:</strong> <?php echo $error_msg; ?></div>
        <?php else: ?>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">완벽 일치<br><strong class="fs-3 text-success"><?php echo $identical_count; ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">내용 불일치<br><strong class="fs-3 text-warning"><?php echo count($modified); ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">테스트에만 있음<br><strong class="fs-3 text-info"><?php echo count($only_local); ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">서비스에만 있음<br><strong class="fs-3 text-danger"><?php echo count($only_remote); ?></strong></div>
                </div>
            </div>

            <!-- 1. 내용이 다른 파일 (수정됨) -->
            <div class="card">
                <div class="card-header bg-warning bg-opacity-10 text-dark border-bottom-0">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>내용이 다른 파일 (수정 필요) <span class="badge bg-warning text-dark float-end"><?php echo count($modified); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($modified)): ?>
                        <div class="text-center py-4 text-muted">모든 파일의 내용이 동일합니다.</div>
                    <?php else: ?>
                        <table class="table table-ps24 table-hover mb-0 align-middle">
                            <thead>
                                <tr class="small">
                                    <th>파일 경로</th>
                                    <th>테스트 환경 (Source)</th>
                                    <th>서비스 환경 (Live)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modified as $path => $info): ?>
                                    <tr>
                                        <td class="file-path"><?php echo $path; ?></td>
                                        <td class="data-row">
                                            <div><i class="bi bi-clock me-1"></i><?php echo $info['local']['mtime']; ?></div>
                                            <div><i class="bi bi-hdd me-1"></i><?php echo formatBytes($info['local']['size']); ?></div>
                                        </td>
                                        <td class="data-row">
                                            <div class="<?php echo ($info['local']['mtime'] < $info['remote']['mtime']) ? 'text-danger fw-bold' : ''; ?>"><i class="bi bi-clock me-1"></i><?php echo $info['remote']['mtime']; ?></div>
                                            <div class="<?php echo ($info['local']['size'] !== $info['remote']['size']) ? 'text-danger fw-bold' : ''; ?>"><i class="bi bi-hdd me-1"></i><?php echo formatBytes($info['remote']['size']); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                <!-- 2. 로컬에만 있는 파일 -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info bg-opacity-10 text-dark border-bottom-0">
                            <i class="bi bi-laptop me-2 text-info"></i>테스트 환경에만 있는 파일 <span class="badge bg-info float-end"><?php echo count($only_local); ?></span>
                        </div>
                        <ul class="list-group list-group-flush data-row">
                            <?php if (empty($only_local)): ?><li class="list-group-item text-center py-4 text-muted border-0">없음</li><?php endif; ?>
                            <?php foreach ($only_local as $path => $info): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="file-path text-truncate" style="max-width: 70%;" title="<?php echo $path; ?>"><?php echo $path; ?></span>
                                    <span class="text-muted text-end"><small><?php echo date('m-d H:i', strtotime($info['mtime'])); ?><br><?php echo formatBytes($info['size']); ?></small></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- 3. 서버에만 있는 파일 -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-danger bg-opacity-10 text-dark border-bottom-0">
                            <i class="bi bi-cloud-arrow-down me-2 text-danger"></i>서비스 환경에만 있는 파일 <span class="badge bg-danger float-end"><?php echo count($only_remote); ?></span>
                        </div>
                        <ul class="list-group list-group-flush data-row">
                            <?php if (empty($only_remote)): ?><li class="list-group-item text-center py-4 text-muted border-0">없음</li><?php endif; ?>
                            <?php foreach ($only_remote as $path => $info): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="file-path text-truncate" style="max-width: 70%;" title="<?php echo $path; ?>"><?php echo $path; ?></span>
                                    <span class="text-muted text-end"><small><?php echo date('m-d H:i', strtotime($info['mtime'])); ?><br><?php echo formatBytes($info['size']); ?></small></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- [신규] 전체 폴더 구조 시각화 영역 -->
            <h4 class="fw-bold mt-5 mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i>전체 폴더 구조</h4>
            <?php $modified_keys = array_keys($modified); ?>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light border-bottom-0 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-pc-display me-2 text-dark"></i>테스트 환경 구조 (test_env)</span>
                            <span class="badge bg-secondary fw-normal text-truncate" style="max-width: 50%; font-size: 0.7rem;" title="<?php echo $test_root; ?>"><?php echo $test_root; ?></span>
                        </div>
                        <div class="card-body bg-white overflow-auto" style="max-height: 600px;">
                            <?php
                            $local_structure = renderFolderStructure($local_files, $modified_keys);
                            echo $local_structure['html'];
                            ?>
                            <textarea id="local-structure-text" class="d-none"><?php echo htmlspecialchars($local_structure['text']); ?></textarea>
                        </div>
                        <div class="card-footer bg-white border-top border-light text-end py-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('local-structure-text')"><i class="bi bi-clipboard me-1"></i>구조를 Text로 복사</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light border-bottom-0 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-server me-2 text-dark"></i>서비스 환경 구조 (Live)</span>
                            <span class="badge bg-secondary fw-normal text-truncate" style="max-width: 50%; font-size: 0.7rem;" title="<?php echo $live_root; ?>"><?php echo $live_root; ?></span>
                        </div>
                        <div class="card-body bg-white overflow-auto" style="max-height: 600px;">
                            <?php
                            $remote_structure = renderFolderStructure($remote_files_original, $modified_keys);
                            echo $remote_structure['html'];
                            ?>
                            <textarea id="remote-structure-text" class="d-none"><?php echo htmlspecialchars($remote_structure['text']); ?></textarea>
                        </div>
                        <div class="card-footer bg-white border-top border-light text-end py-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('remote-structure-text')"><i class="bi bi-clipboard me-1"></i>구조를 Text로 복사</button>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const textToCopy = document.getElementById(elementId).value;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    alert('폴더 구조가 텍스트로 복사되었습니다.');
                }).catch(err => fallbackCopyTextToClipboard(textToCopy));
            } else {
                fallbackCopyTextToClipboard(textToCopy);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert('폴더 구조가 텍스트로 복사되었습니다.');
            } catch (err) {
                alert('복사에 실패했습니다.');
            }
            document.body.removeChild(textArea);
        }
    </script>
</body>

</html>