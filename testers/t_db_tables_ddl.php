<?php

/**
 * [하위 파일] DB Table scheme 조회 및 스키마 비교, DDL 적용 툴
 * 위치: /public_html/testers/t_db_tables_ddl.php
 */

require_once __DIR__ . '/t_common.php';

$execute_msg = '';
$execute_status = '';

// [추가] Live DB 연결 객체를 상단에 전역으로 준비 (구조 비교용)
$live_dsn = "mysql:host=" . DB_HOST . ";dbname=" . LIVE_DB_NAME . ";charset=utf8mb4";
$pdo_live = null;
$live_db_error = '';
try {
    $pdo_live = new PDO($live_dsn, LIVE_DB_USER, LIVE_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true // 다중 세미콜론 쿼리 실행 허용
    ]);
} catch (Exception $e) {
    $live_db_error = $e->getMessage();
}

// 서비스 환경 DB에 DDL 실행 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_live_ddl') {
    $ddl_query = trim($_POST['ddl_query'] ?? '');
    
    if (empty($ddl_query)) {
        $execute_msg = "실행할 DDL 쿼리를 입력해주세요.";
        $execute_status = 'warning';
    } else {
        if ($pdo_live) {
            try {
                // DDL 쿼리 일괄 실행
                $pdo_live->exec($ddl_query);
                $execute_msg = "서비스 환경(Live) DB에 DDL 쿼리가 성공적으로 적용되었습니다.";
                $execute_status = 'success';
            } catch (Exception $e) {
                $execute_msg = "DDL 실행 중 오류 발생: " . $e->getMessage();
                $execute_status = 'danger';
            }
        } else {
            $execute_msg = "서비스 환경 DB에 연결할 수 없어 쿼리를 실행할 수 없습니다.";
            $execute_status = 'danger';
        }
    }
}

// [추가] 스키마 수집 헬퍼 함수
function getDbSchema($pdo_conn) {
    $schema = [];
    $stmt = $pdo_conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt_cols = $pdo_conn->query("SHOW FULL COLUMNS FROM `$table`");
        $columns = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);

        $stmt_ddl = $pdo_conn->query("SHOW CREATE TABLE `$table`");
        $ddl = $stmt_ddl->fetch(PDO::FETCH_ASSOC);
        
        // AUTO_INCREMENT 값은 데이터 건수에 따라 달라지므로 구조 비교에서 제외
        $create_table = preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl['Create Table']);
        
        $schema[$table] = [
            'columns' => $columns,
            'ddl' => $create_table,
            'raw_ddl' => $ddl['Create Table']
        ];
    }
    return $schema;
}

try {
    // 양쪽 DB 구조 로드
    $test_schema = getDbSchema($pdo);
    $live_schema = $pdo_live ? getDbSchema($pdo_live) : [];

    // 화면 출력을 위한 바인딩 (기존 $db_info 호환)
    $db_info = $test_schema; 
    $ddl_text = "";
    
    foreach ($db_info as $table => $info) {
        $ddl_text .= "-- 테이블 구조: `$table`\n";
        $ddl_text .= $info['raw_ddl'] . ";\n\n";
    }

    // 스키마 비교 분석
    $only_in_test = [];
    $only_in_live = [];
    $modified_schema = [];
    $identical_count = 0;
    $auto_sync_queries = ""; // [추가] 자동 생성될 동기화 쿼리 누적 변수

    foreach ($test_schema as $table => $info) {
        if (!isset($live_schema[$table])) {
            $only_in_test[$table] = $info['raw_ddl'];
            $auto_sync_queries .= "-- 신규 테이블 `{$table}` 생성\n" . $info['raw_ddl'] . ";\n\n";
        } else {
            // 구조 문자열로 일치 여부 비교
            if ($info['ddl'] !== $live_schema[$table]['ddl']) {
                // 컬럼 레벨 상세 분석
                $test_col_map = [];
                foreach ($info['columns'] as $c) $test_col_map[$c['Field']] = $c;
                
                $live_col_map = [];
                foreach ($live_schema[$table]['columns'] as $c) $live_col_map[$c['Field']] = $c;
                
                $col_diffs = [];
                $alter_stmts = []; // [추가] ALTER 문 조립 배열
                
                // Test에 추가되거나 변경된 컬럼 검사
                foreach ($test_col_map as $field => $tc) {
                    if (!isset($live_col_map[$field])) {
                        $col_diffs[] = "<span class='text-success fw-bold'>[추가]</span> <code>{$field}</code> ({$tc['Type']})";
                        $null_str = $tc['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                        $default_str = $tc['Default'] !== null ? "DEFAULT '" . addslashes($tc['Default']) . "'" : ($tc['Null'] === 'YES' ? 'DEFAULT NULL' : '');
                        $alter_stmts[] = "ADD COLUMN `{$field}` {$tc['Type']} {$null_str} {$default_str} {$tc['Extra']}";
                    } else {
                        $lc = $live_col_map[$field];
                        $changes = [];
                        if ($tc['Type'] !== $lc['Type']) $changes[] = "Type: {$lc['Type']} ➔ {$tc['Type']}";
                        if ($tc['Null'] !== $lc['Null']) $changes[] = "Null: {$lc['Null']} ➔ {$tc['Null']}";
                        if ($tc['Default'] !== $lc['Default']) $changes[] = "Default: {$lc['Default']} ➔ {$tc['Default']}";
                        if ($tc['Extra'] !== $lc['Extra']) $changes[] = "Extra: {$lc['Extra']} ➔ {$tc['Extra']}";
                        
                        if (!empty($changes)) {
                            $col_diffs[] = "<span class='text-warning fw-bold'>[변경]</span> <code>{$field}</code> <span class='text-muted'>(" . implode(', ', $changes) . ")</span>";
                            $null_str = $tc['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                            $default_str = $tc['Default'] !== null ? "DEFAULT '" . addslashes($tc['Default']) . "'" : ($tc['Null'] === 'YES' ? 'DEFAULT NULL' : '');
                            $alter_stmts[] = "MODIFY COLUMN `{$field}` {$tc['Type']} {$null_str} {$default_str} {$tc['Extra']}";
                        }
                    }
                }
                
                // Live에만 있는 컬럼 (Test에서 삭제됨) 검사
                foreach ($live_col_map as $field => $lc) {
                    if (!isset($test_col_map[$field])) {
                        $col_diffs[] = "<span class='text-danger fw-bold'>[삭제]</span> <code>{$field}</code>";
                        $alter_stmts[] = "DROP COLUMN `{$field}`";
                    }
                }
                
                if (empty($col_diffs)) {
                    $col_diffs[] = "<span class='text-secondary'>컬럼 외 인덱스(Key) 또는 테이블 옵션이 변경됨</span>";
                    
                    // [추가] 어떤 인덱스/옵션이 달라졌는지 DDL 줄 단위(Line) 비교로 직관적 안내
                    $test_lines = explode("\n", $info['ddl']); 
                    $live_lines = explode("\n", $live_schema[$table]['ddl']);
                    
                    $added_lines = array_diff($test_lines, $live_lines);
                    $removed_lines = array_diff($live_lines, $test_lines);
                    
                    foreach ($added_lines as $line) {
                        if (trim($line) !== '') $col_diffs[] = "<span class='text-success fw-bold'>[추가해야 할 내용]</span> <code class='text-muted'>" . htmlspecialchars(trim($line, " \t\n\r\0\x0B,")) . "</code>";
                    }
                    foreach ($removed_lines as $line) {
                        if (trim($line) !== '') $col_diffs[] = "<span class='text-danger fw-bold'>[삭제해야 할 내용]</span> <code class='text-muted'>" . htmlspecialchars(trim($line, " \t\n\r\0\x0B,")) . "</code>";
                    }

                    $alter_stmts[] = "/* 인덱스 또는 테이블 옵션 변경. 위 상세 비교를 참고하여 수동으로 쿼리를 작성해주세요. */";
                }

                // [추가] 완성된 ALTER 테이블 쿼리
                $alter_query = "ALTER TABLE `{$table}`\n  " . implode(",\n  ", $alter_stmts) . ";";

                $modified_schema[$table] = [
                    'diffs' => $col_diffs,
                    'alter_query' => $alter_query
                ];
                
                $auto_sync_queries .= "-- 테이블 `{$table}` 구조 변경\n" . $alter_query . "\n\n";
            } else {
                $identical_count++;
            }
            // 양쪽에 있는 테이블은 비교 후 제거
            unset($live_schema[$table]);
        }
    }
    
    // 남은 테이블은 서비스에만 있는 테이블
    foreach ($live_schema as $table => $info) {
        $only_in_live[] = $table;
        $auto_sync_queries .= "-- 서비스에만 있는 테이블 `{$table}` 삭제\nDROP TABLE IF EXISTS `{$table}`;\n\n";
    }
} catch (Exception $e) {
    die("DB 구조 분석 중 오류 발생: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Table Scheme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Apple SD Gothic Neo', sans-serif;
            padding-bottom: 50px;
        }

        .table-title {
            cursor: pointer;
            user-select: none;
        }

        .table-title:hover {
            background-color: #e9ecef;
        }

        .column-list {
            display: none;
            background-color: #fff;
        }

        .column-list.show {
            display: block;
        }

        .col-type {
            color: #d63384;
            font-size: 0.85em;
            font-family: monospace;
        }

        .col-comment {
            color: #6c757d;
            font-size: 0.85em;
        }

        .col-key {
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>

<body class="p-4">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0"><i class="bi bi-database-fill text-primary me-2"></i>KShops24 DB 스키마 동기화 점검</h4>
            <button class="btn btn-primary shadow-sm rounded-pill fw-bold px-4" onclick="copyToClipboard('ddl-text')">
                <i class="bi bi-clipboard-check me-1"></i> 전체 DDL 복사
            </button>
        </div>

        <div class="alert alert-light border shadow-sm mb-4">
            <i class="bi bi-info-circle-fill text-info me-2"></i> 테스트 환경 DB(<code><?= TEST_DB_NAME ?></code>)와 서비스 환경 DB(<code><?= LIVE_DB_NAME ?></code>)의 구조 차이를 실시간으로 비교 검증합니다.<br>
            테이블 이름을 클릭하면 컬럼 상세 구조를 볼 수 있습니다. <strong>"전체 DDL 복사"</strong> 버튼을 누르면 AI가 구조를 파악하기 좋은 <code>CREATE TABLE</code> 쿼리가 복사됩니다.
        </div>

        <?php if ($live_db_error): ?>
            <div class="alert alert-danger shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>서비스 환경 DB 연결 실패:</strong> <?= htmlspecialchars($live_db_error) ?>
            </div>
        <?php else: ?>
            <!-- 스키마 동기화 스캔 결과 -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">구조 완벽 일치<br><strong class="fs-3 text-success"><?= $identical_count ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">구조 불일치<br><strong class="fs-3 text-warning"><?= count($modified_schema) ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">테스트에만 있음<br><strong class="fs-3 text-info"><?= count($only_in_test) ?></strong></div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-light border shadow-sm text-center py-3">서비스에만 있음<br><strong class="fs-3 text-danger"><?= count($only_in_live) ?></strong></div>
                </div>
            </div>

            <?php if (!empty($modified_schema) || !empty($only_in_test) || !empty($only_in_live)): ?>
                <div class="card shadow-sm mb-4 border-start border-5 border-warning">
                    <div class="card-header bg-warning bg-opacity-10 py-3 border-bottom-0">
                        <h5 class="card-title fw-bold text-dark m-0"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>스키마 불일치 상세 (동기화 필요)</h5>
                    </div>
                    <div class="card-body">
                        <!-- [추가] AI 동기화 쿼리 자동 생성 UI 영역 -->
                        <?php if (!empty($auto_sync_queries)): ?>
                            <div class="mb-4 d-flex flex-wrap justify-content-between align-items-center bg-light p-3 rounded border shadow-sm">
                                <div class="mb-2 mb-md-0">
                                    <i class="bi bi-robot fs-4 text-primary me-2 align-middle"></i>
                                    <span class="fw-bold text-dark fs-5 align-middle">AI 동기화 쿼리 자동 생성 완료</span>
                                </div>
                                <div>
                                    <button class="btn btn-outline-secondary fw-bold shadow-sm me-2" onclick="copyToClipboard('auto-sync-query-text', '동기화 쿼리가')">
                                        <i class="bi bi-clipboard"></i> 쿼리 복사
                                    </button>
                                    <button class="btn btn-primary fw-bold shadow-sm" onclick="fillSyncQuery()">
                                        <i class="bi bi-arrow-down-circle"></i> 아래 폼에 자동 입력
                                    </button>
                                </div>
                            </div>
                            <textarea id="auto-sync-query-text" class="d-none"><?= htmlspecialchars(trim($auto_sync_queries)) ?></textarea>
                        <?php endif; ?>

                        <?php if (!empty($modified_schema)): ?>
                            <h6 class="fw-bold text-warning text-dark"><i class="bi bi-pencil-square me-1"></i>구조 불일치 테이블 상세 (ALTER 필요)</h6>
                            <div class="list-group mb-3 shadow-sm">
                                <?php foreach ($modified_schema as $tbl => $data): ?>
                                    <div class="list-group-item bg-white border-warning border-opacity-25">
                                        <div class="fw-bold text-dark mb-2"><i class="bi bi-table text-secondary me-2"></i><?= $tbl ?></div>
                                        <ul class="mb-0 small" style="list-style-type: none; padding-left: 1.5rem;">
                                            <?php foreach ($data['diffs'] as $diff): ?>
                                                <li class="mb-1"><?= $diff ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($only_in_test)): ?>
                            <h6 class="fw-bold text-info text-dark"><i class="bi bi-plus-circle me-1"></i>테스트 환경에만 있는 테이블 (CREATE 필요)</h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php foreach ($only_in_test as $tbl => $ddl): ?>
                                    <span class="badge bg-info text-dark px-3 py-2 border border-info shadow-sm"><?= $tbl ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($only_in_live)): ?>
                            <h6 class="fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>서비스 환경에만 있는 테이블 (테스트에서 삭제됨 / DROP 검토)</h6>
                            <div class="d-flex flex-wrap gap-2 mb-0">
                                <?php foreach ($only_in_live as $tbl): ?>
                                    <span class="badge bg-danger px-3 py-2 shadow-sm"><?= $tbl ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($db_info as $table => $info): ?>
                            <div class="list-group-item p-0">
                                <div class="p-3 table-title fw-bold text-dark d-flex justify-content-between align-items-center" onclick="toggleCols('cols-<?= $table ?>')">
                                    <span><i class="bi bi-table me-2 text-secondary"></i><?= $table ?> <span class="badge bg-secondary ms-2 fw-normal"><?= count($info['columns']) ?> columns</span></span>
                                    <i class="bi bi-chevron-down text-muted"></i>
                                </div>
                                <div class="column-list border-top" id="cols-<?= $table ?>">
                                    <div class="p-3 bg-light">
                                        <table class="table table-sm table-bordered bg-white mb-0 small align-middle">
                                            <thead class="table-light text-muted text-center">
                                                <tr>
                                                    <th>Field (컬럼명)</th>
                                                    <th>Type (데이터 타입)</th>
                                                    <th style="width: 60px;">Null</th>
                                                    <th style="width: 60px;">Key</th>
                                                    <th>Default</th>
                                                    <th>Extra</th>
                                                    <th>Comment (설명)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($info['columns'] as $col): ?>
                                                    <tr>
                                                        <td class="fw-bold text-dark ps-2"><?= $col['Field'] ?></td>
                                                        <td class="col-type ps-2"><?= $col['Type'] ?></td>
                                                        <td class="text-center"><?= $col['Null'] ?></td>
                                                        <td class="text-center col-key"><?= $col['Key'] ?></td>
                                                        <td class="text-center text-muted"><?= $col['Default'] !== null ? htmlspecialchars($col['Default']) : 'NULL' ?></td>
                                                        <td class="text-center text-muted"><?= $col['Extra'] ?></td>
                                                        <td class="col-comment ps-2"><?= htmlspecialchars($col['Comment']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 수동 DDL 비교 및 ALTER 쿼리 자동 생성기 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-primary border-start border-5">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title fw-bold text-primary m-0"><i class="bi bi-tools me-2"></i>수동 DDL 비교 및 ALTER 쿼리 자동 생성기</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info small mb-4">
                            <i class="bi bi-info-circle-fill me-2"></i>테스트 환경과 서비스 환경의 특정 테이블 <code>CREATE TABLE</code> 구문을 각각 아래에 붙여넣으면, 두 구조를 비교하여 서비스 환경을 테스트 환경과 동일하게 맞추는 <code>ALTER TABLE</code> 쿼리를 자동으로 생성합니다.
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-success"><i class="bi bi-database me-1"></i>테스트 환경 DDL (목표 구조)</label>
                                <textarea id="manual_test_ddl" class="form-control font-monospace text-sm bg-light" rows="8" placeholder="CREATE TABLE `table_name` (&#10;  `id` int(11) NOT NULL AUTO_INCREMENT,&#10;..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-danger"><i class="bi bi-database me-1"></i>서비스 환경 DDL (현재 구조)</label>
                                <textarea id="manual_live_ddl" class="form-control font-monospace text-sm bg-light" rows="8" placeholder="CREATE TABLE `table_name` (&#10;  `id` int(11) NOT NULL AUTO_INCREMENT,&#10;..."></textarea>
                            </div>
                        </div>
                        <div class="text-center mb-3">
                            <button type="button" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" onclick="generateManualAlterQuery()">
                                <i class="bi bi-arrow-down-up me-1"></i> 비교 후 ALTER 쿼리 생성
                            </button>
                        </div>
                        
                        <div id="manual_result_area" style="display: none;">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-magic me-1"></i>생성된 ALTER 쿼리</label>
                            <div class="position-relative">
                                <textarea id="manual_alter_result" class="form-control font-monospace bg-dark text-warning p-3" rows="6" readonly></textarea>
                                <div class="position-absolute top-0 end-0 p-2">
                                    <button type="button" class="btn btn-sm btn-outline-light shadow-sm" onclick="copyToClipboard('manual_alter_result', '수동으로 생성된 ALTER 쿼리가')"><i class="bi bi-clipboard"></i> 복사</button>
                                    <button type="button" class="btn btn-sm btn-success shadow-sm ms-1" onclick="fillSyncQueryFromManual()"><i class="bi bi-arrow-down-circle"></i> 아래 실행 폼에 입력</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 서비스 DB에 DDL 적용 섹션 -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-danger border-start border-5">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title fw-bold text-danger m-0"><i class="bi bi-database-exclamation me-2"></i>서비스 환경(Live) DB에 DDL 쿼리 적용</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>여기에 입력한 DDL 쿼리(CREATE, ALTER, DROP 등)는 <strong>서비스 환경(Live) DB(<code><?= LIVE_DB_NAME ?></code>)</strong>에 즉시 실행됩니다. 복구할 수 없으므로 주의해서 사용하세요.
                        </div>
                        
                        <?php if ($execute_msg): ?>
                            <div class="alert alert-<?= $execute_status ?> alert-dismissible fade show" role="alert">
                                <?= nl2br(htmlspecialchars($execute_msg)) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" onsubmit="return confirm('정말로 서비스 환경(Live) DB에 이 쿼리를 실행하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다!');">
                            <input type="hidden" name="action" value="execute_live_ddl">
                            <div class="mb-3">
                                <label for="ddl_query" class="form-label fw-bold">실행할 쿼리 입력</label>
                                <textarea class="form-control font-monospace bg-dark text-light p-3" id="ddl_query" name="ddl_query" rows="10" placeholder="ALTER TABLE `shops` ADD COLUMN `new_column` VARCHAR(255) NULL;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm"><i class="bi bi-lightning-charge-fill me-2"></i>서비스 DB에 즉시 실행</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 복사용 숨김 영역 (순수 DDL 텍스트 보관) -->
    <textarea id="ddl-text" class="d-none"><?= htmlspecialchars($ddl_text) ?></textarea>

    <script>
        function toggleCols(id) {
            const el = document.getElementById(id);
            const icon = el.previousElementSibling.querySelector('i.bi-chevron-down, i.bi-chevron-up');
            if (el.classList.contains('show')) {
                el.classList.remove('show');
                icon.classList.replace('bi-chevron-up', 'bi-chevron-down');
            } else {
                el.classList.add('show');
                icon.classList.replace('bi-chevron-down', 'bi-chevron-up');
            }
        }

        function fillSyncQuery() {
            const syncQuery = document.getElementById('auto-sync-query-text').value;
            const textarea = document.getElementById('ddl_query');
            if(textarea) {
                textarea.value = syncQuery;
                textarea.focus();
                // 시각적 애니메이션 피드백 (성공 색상으로 깜빡임)
                textarea.style.transition = 'background-color 0.5s';
                textarea.style.backgroundColor = '#198754'; 
                setTimeout(() => { textarea.style.backgroundColor = '#212529'; }, 500); 
            }
        }

        // 수동 DDL 구문 파서 (JavaScript Regex 기반)
        function parseManualDDL(ddlText) {
            const lines = ddlText.split('\n');
            let tableName = '';
            const columns = {};
            const tableRegex = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`([^`]+)`|([a-zA-Z0-9_]+))/i;

            for (let i = 0; i < lines.length; i++) {
                let line = lines[i].trim();
                if (!tableName) {
                    const match = line.match(tableRegex);
                    if (match) tableName = match[1] || match[2];
                }
                // 컬럼명 정의 라인 추출 (PRIMARY KEY 등 키본 제약조건은 무시)
                if (line.startsWith('`')) {
                    const colMatch = line.match(/^`([^`]+)`\s+(.*)$/);
                    if (colMatch) {
                        const colName = colMatch[1];
                        let colDef = colMatch[2];
                        // 마지막 콤마 제거
                        if (colDef.endsWith(',')) colDef = colDef.slice(0, -1);
                        columns[colName] = colDef;
                    }
                }
            }
            return { tableName, columns };
        }

        // 수동 DDL 비교 및 ALTER 쿼리 조합 실행
        function generateManualAlterQuery() {
            const testDDL = document.getElementById('manual_test_ddl').value.trim();
            const liveDDL = document.getElementById('manual_live_ddl').value.trim();

            if (!testDDL || !liveDDL) {
                alert('테스트 환경과 서비스 환경의 DDL을 모두 입력해주세요.');
                return;
            }

            const testSchema = parseManualDDL(testDDL);
            const liveSchema = parseManualDDL(liveDDL);

            if (!testSchema.tableName || !liveSchema.tableName) {
                alert('CREATE TABLE 구문을 정확히 인식할 수 없습니다. (테이블명을 찾을 수 없음)');
                return;
            }

            let alterStmts = [];
            for (const [colName, testDef] of Object.entries(testSchema.columns)) {
                const liveDef = liveSchema.columns[colName];
                if (!liveDef) alterStmts.push(`ADD COLUMN \`${colName}\` ${testDef}`);
                else if (liveDef !== testDef) alterStmts.push(`MODIFY COLUMN \`${colName}\` ${testDef}`);
            }
            for (const colName of Object.keys(liveSchema.columns)) {
                if (!testSchema.columns[colName]) alterStmts.push(`DROP COLUMN \`${colName}\``);
            }

            const resultTextarea = document.getElementById('manual_alter_result');
            if (alterStmts.length === 0) {
                resultTextarea.value = `-- 두 구문의 컬럼 구조가 완벽히 동일합니다.\n-- (참고: 외래키(FK)나 인덱스(INDEX) 변경은 수동 확인이 필요합니다.)`;
                resultTextarea.classList.replace('text-warning', 'text-success');
            } else {
                resultTextarea.value = `ALTER TABLE \`${testSchema.tableName}\`\n  ` + alterStmts.join(",\n  ") + `;`;
                resultTextarea.classList.replace('text-success', 'text-warning');
            }
            document.getElementById('manual_result_area').style.display = 'block';
        }

        // 생성된 수동 쿼리를 실행 폼으로 전달
        function fillSyncQueryFromManual() {
            const query = document.getElementById('manual_alter_result').value;
            if (query.startsWith('--')) {
                alert('적용할 유효한 변경 사항이 없습니다.');
                return;
            }
            const textarea = document.getElementById('ddl_query');
            if (textarea) {
                textarea.value = query;
                textarea.focus();
                textarea.style.transition = 'background-color 0.5s';
                textarea.style.backgroundColor = '#198754';
                setTimeout(() => { textarea.style.backgroundColor = '#212529'; }, 500);
            }
        }

        function copyToClipboard(elementId, label = '전체 테이블의 DDL 코드가') {
            const textToCopy = document.getElementById(elementId).value;
            const fallback = () => {
                const textArea = document.createElement("textarea");
                textArea.value = textToCopy;
                textArea.style.position = "fixed";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert(label + ' 복사되었습니다!');
                } catch (err) {
                    alert('복사에 실패했습니다.');
                }
                document.body.removeChild(textArea);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(() => alert(label + ' 복사되었습니다!')).catch(fallback);
            } else {
                fallback();
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>