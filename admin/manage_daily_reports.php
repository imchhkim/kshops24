<?php

/**
 * KShops24 일간 배치 작업 리포트 (manage_daily_reports.php)
 * - 역할: 매일 실행되는 cron_daily_works.php의 실행 결과를 조회합니다.
 * - 실행: manage_site.php 탭 내에서 include 되어 실행됨
 */

// 독립 실행 차단
if (!isset($pdo)) {
    exit;
}

// 1. 필터링 값 수집
$f_year = $_GET['f_year'] ?? date('Y');
$f_month = $_GET['f_month'] ?? date('m');
$f_day = $_GET['f_day'] ?? ''; // '일' 필터 추가 (기본값: 전체 일)

// 2. 데이터베이스에서 리포트 조회
$where_clause = "WHERE log_type = 'cron_daily_report'";
$params = [];

if ($f_year && $f_month) {
    $where_clause .= " AND YEAR(created_at) = ? AND MONTH(created_at) = ?";
    $params[] = $f_year;
    $params[] = $f_month;

    if ($f_day) {
        $where_clause .= " AND DAY(created_at) = ?";
        $params[] = $f_day;
    }
}

// 일별 리포트이므로 페이징 없이 해당 월의 전체 목록(최대 31개)을 보여줍니다.
$sql = "SELECT id, message, details, created_at FROM site_logs $where_clause ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

?>

<div class="settings-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="settings-title mb-0"><i class="bi bi-calendar-event"></i> 일간 배치 작업 리포트</div>
        <div>
            <button type="button" class="btn btn-outline-info btn-sm fw-bold rounded-pill px-3 shadow-sm me-2" onclick="runInodeOptimizationNow()">
                <i class="bi bi-magic me-1"></i> 시스템 찌꺼기 청소 (Inode 최적화)
            </button>
            <button type="button" class="btn btn-warning text-dark btn-sm fw-bold rounded-pill px-3 shadow-sm" onclick="runDailyCronNow()">
                <i class="bi bi-lightning-charge-fill me-1"></i> 지금 작업 수행
            </button>
        </div>
    </div>

    <!-- 필터 폼 -->
    <form method="GET" action="admin_view.php" class="row g-2 mb-4 p-3 bg-light rounded-3 align-items-end">
        <input type="hidden" name="page" value="manage_site">
        <input type="hidden" name="view" value="manage_daily_reports">
        <div class="col-md-3">
            <label class="form-label small fw-bold">연도</label>
            <select name="f_year" class="form-select">
                <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= ($f_year == $y) ? 'selected' : '' ?>><?= $y ?>년</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">월</label>
            <select name="f_month" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= ($f_month == $m) ? 'selected' : '' ?>><?= $m ?>월</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">일</label>
            <select name="f_day" class="form-select">
                <option value="">전체 일</option>
                <?php for ($d = 1; $d <= 31; $d++): ?>
                <option value="<?= $d ?>" <?= ($f_day == $d) ? 'selected' : '' ?>><?= $d ?>일</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-dark w-100">조회</button>
        </div>
    </form>

    <!-- 리포트 목록 -->
    <div class="list-group">
        <?php if (empty($reports)): ?>
        <div class="list-group-item text-center py-5 text-muted">해당 월의 일간 리포트가 없습니다.</div>
        <?php else: ?>
        <?php foreach ($reports as $report): ?>
        <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#reportDailyModal"
            data-report-id="<?= $report['id'] ?>"
            data-report-title="일간 리포트 (<?= date('Y-m-d H:i', strtotime($report['created_at'])) ?>)">
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($report['message']) ?></h6>
                <span class="badge bg-secondary"><?= date('Y-m-d', strtotime($report['created_at'])) ?></span>
            </div>
            <p class="mb-1 small text-muted text-truncate">
                <?= htmlspecialchars(substr(preg_replace('/\s+/', ' ', $report['details']), 0, 200)) ?>...</p>
        </a>
        <!-- 리포트 내용을 JS가 참조할 수 있도록 숨겨진 div에 저장 -->
        <div class="d-none" id="report-daily-details-<?= $report['id'] ?>">
            <pre><?= htmlspecialchars($report['details']) ?></pre>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 리포트 상세 보기용 공용 모달 (ID를 월간 리포트와 겹치지 않게 변경) -->
<div class="modal fade" id="reportDailyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportDailyModalTitle">리포트 상세 보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-dark text-white" style="font-family: monospace;">
                <!-- JS가 이곳에 리포트 내용을 주입합니다. -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportModalEl = document.getElementById('reportDailyModal');
    if (reportModalEl) {
        reportModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-report-id');
            const reportTitle = button.getAttribute('data-report-title');
            const reportDetailsEl = document.getElementById('report-daily-details-' + reportId);

            const modalTitle = reportModalEl.querySelector('.modal-title');
            const modalBody = reportModalEl.querySelector('.modal-body');

            if (modalTitle) modalTitle.textContent = reportTitle || '일간 리포트 상세 보기';
            
            if (reportDetailsEl && modalBody) {
                modalBody.innerHTML = reportDetailsEl.innerHTML;
            } else if (modalBody) {
                modalBody.innerHTML = '<p class="text-danger">리포트 내용을 불러올 수 없습니다.</p>';
            }
        });
    }
});

function runDailyCronNow() {
    if (!confirm("지금 즉시 오늘의 일간 배치 작업(휴점 처리, 폐점 처리, 알림 발송 등)을 수동으로 실행하시겠습니까?\n\n※ 작업이 완료될 때까지 브라우저를 닫지 마세요.")) return;
    
    const btn = document.querySelector('button[onclick="runDailyCronNow()"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 실행 중...';

    fetch('cron_daily_works.php?force_run=1&ajax=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('일간 배치 작업이 성공적으로 실행되어 리포트가 갱신되었습니다.');
                location.reload(); // 리포트 목록 최신화를 위해 새로고침
            } else {
                alert('실행 중 오류가 발생했습니다.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(err => {
            alert('서버 통신 중 오류가 발생했습니다.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}

function runInodeOptimizationNow() {
    if (!confirm("지금 즉시 시스템 찌꺼기 청소(오래된 세션, 고아 파일 및 폴더 등 Inode 최적화) 작업을 실행하시겠습니까?")) return;
    
    const btn = document.querySelector('button[onclick="runInodeOptimizationNow()"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 청소 중...';

    fetch('cron_daily_works.php?force_run=1&ajax=1&only_inode=1')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('시스템 찌꺼기 청소 작업이 성공적으로 완료되었습니다.');
                location.reload(); // 리포트 목록 최신화를 위해 새로고침
            } else {
                alert('실행 중 오류가 발생했습니다.');
            }
        })
        .catch(err => {
            alert('서버 통신 중 오류가 발생했습니다.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}
</script>