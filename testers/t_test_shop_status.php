<?php

require_once __DIR__ . '/t_common.php';

$error_msg = "";
$shop = null;
$search_id = $_GET['shop_id'] ?? '';
$is_deleted_check = false;
$deleted_traces = [];

try {
    // [프로세스 1] 상점(shops 테이블) 정보 로드 (검색어 우선, 없으면 최신)
    if (!empty($search_id)) {
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ? OR subdomain = ? LIMIT 1");
        $stmt->execute([$search_id, $search_id]);
        $shop = $stmt->fetch();
    } else {
        $stmt = $pdo->query("SELECT * FROM shops ORDER BY id DESC LIMIT 1");
        $shop = $stmt->fetch();
    }

    if ($shop) {
        $shop_id = $shop['id'];
        $subdomain = $shop['subdomain'];
        $current_status = $shop['status'];

        // [프로세스 2] 결제 내역 조회 (shop_payments)
        $stmt_pay = $pdo->prepare("SELECT * FROM shop_payments WHERE shop_id = ? ORDER BY id ASC");
        $stmt_pay->execute([$shop_id]);
        $payments = $stmt_pay->fetchAll();

        // [프로세스 3] 관리자 최근 쪽지 조회 (shop_board, type='message')
        $stmt_msg = $pdo->prepare("SELECT title, created_at FROM shop_board WHERE shop_id = ? AND type = 'message' AND sender_type = 'admin' ORDER BY id DESC LIMIT 1");
        $stmt_msg->execute([$shop_id]);
        $welcome_msg = $stmt_msg->fetch();

        // [프로세스 4] 최근 이메일 발송 내역 조회
        $email_log = null;
        $history_arr = json_decode($shop['history_log'] ?? '[]', true);
        if (is_array($history_arr)) {
            $history_arr = array_reverse($history_arr); // 최신 기록부터 검사
            foreach ($history_arr as $h) {
                if (($h['type'] ?? '') === 'email') {
                    $email_log = ['title' => $h['title'], 'content' => $h['content'], 'created_at' => $h['date']];
                    break;
                }
            }
        }

        // [프로세스 5] 물리 업로드 폴더 존재 여부 확인
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $subdomain;
        $folder_exists = is_dir($upload_dir);

        $sub_folders = ['bg', 'shopimages', 'logo', 'itemboard', 'itemimages'];
        $folder_status = [];
        if ($folder_exists) {
            foreach ($sub_folders as $f) {
                $folder_status[$f] = is_dir($upload_dir . '/' . $f);
            }
        }

        $integrity_result = analyzeShopDiskIntegrity($pdo, $shop_id);
    } else {
        if (!empty($search_id)) {
            $is_deleted_check = true;

            // 1. 하위 테이블 데이터 잔존 여부 확인 (숫자 형태의 ID인 경우)
            if (is_numeric($search_id)) {
                $check_tables = [
                    'shop_payments' => '결제 내역',
                    'shop_board' => '게시판/알림 내역',
                    'visit_logs' => '방문 로그',
                    'shop_images' => '이미지 갤러리',
                    'shop_orders' => '주문 내역',
                    'reviews' => '고객 리뷰'
                ];

                foreach ($check_tables as $table => $label) {
                    try {
                        $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE shop_id = ?");
                        $stmt_chk->execute([$search_id]);
                        if (($count = $stmt_chk->fetchColumn()) > 0) {
                            $deleted_traces[] = "{$label} ({$table}): <strong class='text-danger'>{$count}건 잔존</strong>";
                        }
                    } catch (Exception $e) {
                    } // 테이블이 없거나 에러나는 경우 무시
                }
            }

            // 2. 물리적 폴더 잔존 여부 확인 (검색어가 서브도메인일 경우를 가정)
            $possible_upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/shops/" . $search_id;
            if (is_dir($possible_upload_dir)) $deleted_traces[] = "물리 업로드 폴더: <strong class='text-danger'>존재함</strong> <span class='text-muted small'>(/uploads/shops/{$search_id})</span>";
        } else {
            $error_msg = "등록된 상점이 없습니다.";
        }
    }
} catch (Exception $e) {
    $error_msg = "DB 오류: " . $e->getMessage();
}

// 판정 헬퍼 함수
function renderStatus($condition, $success_msg, $fail_msg)
{
    if ($condition) {
        return "<span class='badge bg-success'><i class='bi bi-check-circle me-1'></i>성공</span> <span class='text-secondary small'>{$success_msg}</span>";
    } else {
        return "<span class='badge bg-danger'><i class='bi bi-x-circle me-1'></i>실패</span> <span class='text-danger fw-bold small'>{$fail_msg}</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>입점 프로세스 자동 검증 리포트</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Apple SD Gothic Neo', sans-serif;
            padding-bottom: 50px;
        }

        .check-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }

        .step-num {
            display: inline-block;
            width: 28px;
            height: 28px;
            background: #004aad;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 28px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>

<body>

    <div class="container mt-5" style="max-width: 800px;">
        <div class="card shadow-sm mb-4 border-start border-5 border-primary">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-shield-check me-2"></i>상점 프로세스 검증 (t_test_shop_status.php)</h4>
                <p class="card-text text-muted small">
                    특정 상점의 상태(입점, 휴점, 폐점 등) 변경 시 실행되어야 할 핵심 프로세스들이 정상적으로 처리되었는지 진단합니다.
                </p>
                <ul class="small text-secondary mb-0" style="list-style-type: '👉&nbsp;'; padding-left: 1.2rem;">
                    <li><strong>사용법:</strong> 아래 검색창에 상점의 고유번호(ID) 또는 영문 아이디(서브도메인)를 입력하고 '진단하기' 버튼을 누릅니다.</li>
                    <li><strong>진단 항목:</strong> DB 저장, 결제 내역 생성, 알림 쪽지/이메일 발송 기록, 전용 폴더 생성 여부 등을 종합적으로 확인합니다.</li>
                    <li><strong>삭제된 상점:</strong> 영구 삭제된 상점 ID를 입력하면, 관련된 데이터나 파일이 서버에 남아있는지(찌꺼기) 스캔합니다.</li>
                </ul>
            </div>
        </div>
        <div class="text-center mb-5">
            <h2 class="fw-bold text-primary"><i class="bi bi-shield-check me-2"></i>상점 상태 및 프로세스 진단 리포트</h2>
            <p class="text-muted">특정 상점의 데이터 처리(가입, 휴점, 폐점 등) 결과를 분석합니다.</p>

            <!-- 검색 폼 추가 -->
            <form method="GET" class="d-flex justify-content-center mt-4">
                <div class="input-group shadow-sm" style="max-width: 500px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="shop_id" class="form-control border-start-0 ps-0" placeholder="상점 고유번호(ID) 또는 영문 아이디" value="<?php echo htmlspecialchars($search_id); ?>">
                    <button class="btn btn-primary px-4 fw-bold" type="submit">진단하기</button>
                </div>
            </form>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-warning shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
        <?php elseif ($is_deleted_check): ?>
            <div class="alert alert-danger shadow-sm mb-4 border-danger border-start border-4">
                <h5 class="fw-bold mb-2"><i class="bi bi-trash3-fill me-2"></i>상점 정보를 찾을 수 없습니다.</h5>
                <p class="mb-0">검색하신 ID 또는 서브도메인 '<strong><?php echo htmlspecialchars($search_id); ?></strong>' 에 해당하는 상점 기본 정보(shops 테이블)가 존재하지 않습니다.<br>이미 <strong>영구 삭제된 상점</strong>이거나 잘못된 입력일 수 있습니다.</p>
            </div>

            <div class="check-card border border-danger">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-danger"><i class="bi bi-search me-2"></i>관련 데이터 잔존 여부 스캔</h5>
                <?php if (!empty($deleted_traces)): ?>
                    <div class="alert alert-warning mb-0 bg-warning bg-opacity-10 border-warning border-start border-4">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i><strong>상점 기본 정보는 삭제되었으나, 아래의 하위 데이터가 서버에 남아있습니다.</strong><br>
                        <span class="small text-muted">(참고: 완전 삭제 처리 로직이 정상적으로 이루어지지 않았을 수 있습니다.)</span>
                        <ul class="mt-3 mb-0">
                            <?php foreach ($deleted_traces as $trace): ?>
                                <li class="mb-1"><?php echo $trace; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0 bg-success bg-opacity-10 border-success border-start border-4">
                        <i class="bi bi-check-circle-fill me-2 text-success"></i>해당 식별자와 관련된 하위 테이블 데이터나 물리 폴더가 발견되지 않았습니다.<br>
                        <span class="small text-muted">(완벽하게 삭제되었거나 애초에 존재하지 않았던 상점입니다.)</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($shop): ?>

            <?php
            // 상태별 뱃지 색상 및 라벨 설정
            $status_color = 'secondary';
            $status_label = '알 수 없음';
            if ($current_status === 'active') {
                $status_color = 'success';
                $status_label = '정상 운영중 (active)';
            } elseif ($current_status === 'inactive') {
                $status_color = 'warning text-dark';
                $status_label = '휴점 (inactive)';
            } elseif ($current_status === 'closed') {
                $status_color = 'danger';
                $status_label = '폐점 (closed)';
            } elseif ($current_status === 'testing') {
                $status_color = 'info text-dark';
                $status_label = '테스트중 (testing)';
            }
            ?>

            <div class="alert alert-light bg-white border shadow-sm mb-4" style="border-left: 5px solid var(--bs-<?php echo str_replace(' text-dark', '', $status_color); ?>) !important;">
                <h5 class="fw-bold mb-1">진단 대상 상점: <span class="text-primary"><?php echo htmlspecialchars($shop['shop_name']); ?></span>
                    <span class="badge bg-dark ms-2">ID: <?php echo $shop_id; ?></span>
                    <span class="badge bg-<?php echo $status_color; ?> ms-1"><?php echo $status_label; ?></span>
                </h5>
                <div class="small mt-1 text-secondary">가입 일시: <?php echo $shop['created_at']; ?> | 관리자: <?php echo htmlspecialchars($shop['manager_email']); ?> | 접속 주소: /<?php echo $subdomain; ?></div>
            </div>

            <div class="check-card">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><span class="step-num">1</span> 상점 기본 정보 저장 (shops 테이블)</h5>
                <div class="mb-2"><?php echo renderStatus(true, "상점 데이터가 정상적으로 INSERT 되었습니다.", ""); ?></div>
                <div class="small text-muted bg-light p-2 rounded">현재 DB 기록 상태(status): <strong class="text-<?php echo str_replace(' text-dark', '', $status_color); ?> fw-bold"><?php echo $current_status; ?></strong></div>
            </div>

            <div class="check-card">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><span class="step-num">2</span> 자동 결제 내역 3종 세트 생성 (shop_payments)</h5>
                <div class="mb-3"><?php echo renderStatus(count($payments) >= 3, "총 " . count($payments) . "건의 결제 내역이 자동 생성되었습니다.", "결제 내역이 3건 미만입니다. 트랜잭션 오류를 확인하세요."); ?></div>
                <?php if (!empty($payments)): ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($payments as $p): ?>
                            <li class="list-group-item bg-light border-0 mb-1 rounded d-flex justify-content-between">
                                <span><span class="badge bg-secondary me-2"><?php echo $p['pay_type']; ?></span> <?php echo htmlspecialchars($p['note']); ?></span>
                                <?php $status_val = $p['status'] ?? $p['is_paid'] ?? $p['pay_status'] ?? '?'; ?>
                                <strong class="text-primary"><?php echo number_format($p['amount']); ?> PHP (결제: <?php echo strtoupper($status_val); ?>)</strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="check-card">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><span class="step-num">3</span> 관리자 최근 발송 쪽지 (shop_board)</h5>
                <div class="mb-2"><?php echo renderStatus($welcome_msg, "최근 쪽지가 정상적으로 존재합니다.", "쪽지 데이터가 존재하지 않습니다."); ?></div>
                <?php if ($welcome_msg): ?>
                    <div class="small text-muted bg-light p-2 rounded">최근 제목: <?php echo htmlspecialchars($welcome_msg['title']); ?> <br>발송 일시: <?php echo $welcome_msg['created_at']; ?></div>
                <?php endif; ?>
            </div>

            <div class="check-card">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><span class="step-num">4</span> 안내 이메일 발송 내역 기록 (shop_board)</h5>
                <div class="mb-2">
                    <?php
                    $is_email_logged = !empty($email_log);
                    $is_email_success = $is_email_logged && (strpos($email_log['title'], '[발송 실패]') === false);
                    echo renderStatus($is_email_success, "가장 최근 발송된 이메일 로그가 정상 기록되었습니다.", "이메일 로그가 없거나 최근 기록이 발송 실패 상태입니다.");
                    ?>
                </div>
                <?php if ($email_log): ?>
                    <div class="small text-muted bg-light p-2 rounded">기록 내용: <br><?php echo nl2br(htmlspecialchars($email_log['content'])); ?></div>
                <?php endif; ?>
            </div>

            <div class="check-card">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><span class="step-num">5</span> 상점 전용 업로드 폴더 및 무결성 검사</h5>
                <div class="mb-2"><?php echo renderStatus($folder_exists, "폴더가 정상적으로 생성되었습니다.", "물리 폴더가 생성되지 않았습니다."); ?></div>
                <div class="small text-muted bg-light p-2 rounded font-monospace mb-3">경로: <?php echo $upload_dir; ?></div>

                <?php if ($folder_exists): ?>
                    <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-folder2-open me-2"></i>서브 폴더 생성 상태</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($folder_status as $f => $exists): ?>
                            <span class="badge <?php echo $exists ? 'bg-success' : 'bg-danger'; ?>"><i class="bi <?php echo $exists ? 'bi-check' : 'bi-x'; ?>"></i> <?php echo $f; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-shield-check me-2"></i>파일 무결성 스캔 결과 (DB vs 디스크)</h6>
                <?php if (empty($integrity_result['orphaned_files']) && empty($integrity_result['broken_links'])): ?>
                    <div class="alert alert-success border-0 py-2 small"><i class="bi bi-check-circle-fill me-2"></i>DB 기록과 실제 파일이 완벽하게 일치합니다.</div>
                <?php else: ?>
                    <?php if (!empty($integrity_result['orphaned_files'])): ?>

                        <div class="alert alert-warning border-0 py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>DB에 없는 파일 (고아 파일): <?php echo count($integrity_result['orphaned_files']); ?>건</strong>
                            <ul class="mb-0 mt-1 font-monospace" style="max-height: 100px; overflow-y: auto;">
                                <?php foreach ($integrity_result['orphaned_files'] as $of): ?>
                                    <li>
                                        <?php echo getImageModalTrigger($of['path']); ?>
                                        <span class="text-muted ms-1">(<?php echo $of['size_formatted']; ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>




                    <?php else : ?>
                        <div class="alert alert-danger border-0 py-2 small mb-0">
                            <i class="bi bi-x-octagon-fill me-2"></i><strong>고아 파일이 존재하지 않습니다.</strong><br><br>
                            <span class="text-muted
                    <?php endif; ?>
                    
                    <?php if (!empty($integrity_result['broken_links'])): ?>
                        <div class=" alert alert-danger border-0 py-2 small mb-0">
                                <i class="bi bi-x-octagon-fill me-2"></i><strong>파일이 유실된 DB 링크 (깨진 이미지): <?php echo count($integrity_result['broken_links']); ?>건</strong>
                                <ul class="mb-0 mt-1 font-monospace" style="max-height: 100px; overflow-y: auto;">
                                    <?php foreach ($integrity_result['broken_links'] as $bl): ?>
                                        <li><span class="badge bg-secondary"><?php echo htmlspecialchars($bl['table']); ?></span> <?php echo htmlspecialchars($bl['path']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <?php
    // 공통 푸터 (JS 유틸리티 포함)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_footer.php';
    ?>