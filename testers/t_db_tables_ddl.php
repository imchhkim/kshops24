<?php

/**
 * [하위 파일] DB Table scheme 조회 및 DDL 복사 툴
 * 위치: /public_html/testers/t_db_tables_ddl.php
 */

require_once __DIR__ . '/t_common.php';

try {
    // 모든 테이블 목록 조회
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $db_info = [];
    $ddl_text = "";

    foreach ($tables as $table) {
        // 테이블의 컬럼 정보 상세 조회
        $stmt_cols = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
        $columns = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);

        // 테이블 DDL 조회
        $stmt_ddl = $pdo->query("SHOW CREATE TABLE `$table`");
        $ddl = $stmt_ddl->fetch(PDO::FETCH_ASSOC);

        $db_info[$table] = [
            'columns' => $columns,
            'ddl' => $ddl['Create Table']
        ];

        $ddl_text .= "-- 테이블 구조: `$table`\n";
        $ddl_text .= $ddl['Create Table'] . ";\n\n";
    }
} catch (Exception $e) {
    die("DB 조회 중 오류 발생: " . $e->getMessage());
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
            <h4 class="fw-bold m-0"><i class="bi bi-database-fill text-primary me-2"></i>KShops24 DB Scheme</h4>
            <button class="btn btn-primary shadow-sm rounded-pill fw-bold px-4" onclick="copyToClipboard('ddl-text')">
                <i class="bi bi-clipboard-check me-1"></i> 전체 DDL 복사
            </button>
        </div>

        <div class="alert alert-light border shadow-sm mb-4">
            <i class="bi bi-info-circle-fill text-info me-2"></i> 테이블 이름을 클릭하면 컬럼 상세 구조를 볼 수 있습니다. <strong>"전체 DDL 복사"</strong> 버튼을 누르면 AI가 구조를 완벽하게 파악하기 좋은 원본 <code>CREATE TABLE</code> 쿼리가 클립보드에 복사됩니다.
        </div>

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

        function copyToClipboard(elementId) {
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
                    alert('전체 테이블의 DDL 코드가 복사되었습니다!\\n저(AI)에게 붙여넣기 하시면 완벽하게 구조를 파악할 수 있습니다.');
                } catch (err) {
                    alert('복사에 실패했습니다.');
                }
                document.body.removeChild(textArea);
            };
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(textToCopy).then(() => alert('전체 테이블의 DDL 코드가 복사되었습니다!\\n저(AI)에게 붙여넣기 하시면 완벽하게 구조를 파악할 수 있습니다.')).catch(fallback);
            else fallback();
        }
    </script>
</body>

</html>