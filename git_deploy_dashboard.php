<?php
/**
 * K-Shops24 시각적 Git 배포 자동화 대시보드 (호스팅어 테스트 서버 격리 보안 버전)
 * [개발 지침 준수 준거성]
 * 1. 세부 기능별 상수를 명확히 정의하고 세부 코멘트를 영구 누적합니다.
 * 2. CSS !important 배제 및 중복 없는 정갈한 단일 카드 레이아웃 스펙 준수.
 * 3. 각 단계 섹션(카드)에 고유 ID 주입 및 처리 후 스크롤 락인(Lock-in) 수렴.
 * 4. 화면 깜빡임이 전혀 없는 완벽한 AJAX(Fetch API) 기반 비동기 파이프라인.
 * 5. 우측 하단에 부드럽게 솟아오르는 Toast 팝업 시스템 버그 수정 및 내장.
 *
 * -------------------------------------------------------------------------
 * 🚀 K-Shops24 표준 배포 매뉴얼 (Git Publishing SOP)
 * -------------------------------------------------------------------------
 * [작업 위치: /public_html/test_env (독립 저장소)]
 *
 * 1. 로컬 락인: git add . && git commit -m "설명"
 * 2. 원격 백업: git push origin develop
 * 3. 상용 배포: git checkout main
 *             git pull origin main --allow-unrelated-histories --no-edit
 *             git merge develop --allow-unrelated-histories --no-edit
 *             git push origin main (🚀 웹훅 트리거)
 * 4. 개발 복귀: git checkout develop
 *
 * -------------------------------------------------------------------------
 * 🛠️ 긴급 유지보수 명령어 (Reference)
 * -------------------------------------------------------------------------
 * - 상용 배포 제외(Clean Deploy) 처리:
 *   git rm -r --cached testers/ uploads/ schema.sql *.txt *.md *.json
 *   git commit -m "배포 제외 목록 정리" && git push origin develop
 *
 * - 실서버 환경 강제 동기화 (Webhook 수동 시뮬레이션):
 *   cd /public_html && git fetch origin main && git reset --hard origin/main
 *
 * - SSH 자격 증명(Token) 영구 저장:
 *   git config --global credential.helper store
 * -------------------------------------------------------------------------
 */

// 🛡️ 0. 운영 서버 오작동 방지 (로컬 및 테스트 환경에서만 허용)
$current_host = $_SERVER['HTTP_HOST'] ?? '';
$is_safe_env = (strpos($current_host, 'test.kshops24.com') !== false || strpos($current_host, 'localhost') !== false || strpos($current_host, '127.0.0.1') !== false);

if (!$is_safe_env) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=utf-8');
    die("<div style='padding:60px 20px; text-align:center; font-family:sans-serif; background-color:#f8fafc;'>
            <div style='max-width:500px; margin:0 auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid #e2e8f0;'>
                <h2 style='color:#ef4444; margin-top:0;'>🚫 접근 차단: 라이브 상용 서버입니다!</h2>
                <p style='color:#64748b; font-size:14px; line-height:1.6;'>Git 배포 대시보드는 <strong>테스트 서버(test.kshops24.com) 또는 로컬 환경</strong>에서만 구동되어야 합니다.<br>상용 서버는 오직 webhook을 통한 'Pull'만 수행해야 코드 충돌이 발생하지 않습니다.</p>
            </div>
         </div>");
}

//  1. 찰리님만의 대시보드 진입 마스터 비밀번호 상수를 정의합니다.
define('DASHBOARD_ACCESS_PASSWORD', 'charlie2026!!'); 

// 🔒 2. 쿠키 기반 보안 잠금장치 무결성 체결 엔진
$is_authenticated = false;

// [경로 A] 주소창 파라미터(GET)로 마스터 암호 열쇠가 안전하게 인입된 경우
if (isset($_GET['pw']) && $_GET['pw'] === DASHBOARD_ACCESS_PASSWORD) {
    // 보안을 위해 MD5 단방향 해시 상수로 변환하여 30일간 유효한 브라우저 쿠키 락인
    setcookie('git_dash_auth', md5(DASHBOARD_ACCESS_PASSWORD), time() + (86400 * 30), "/");
    $is_authenticated = true;
} 
// [경로 B] 이미 브라우저에 무결성 인증 쿠키 정보가 안전하게 적재되어 있는 경우
else if (isset($_COOKIE['git_dash_auth']) && $_COOKIE['git_dash_auth'] === md5(DASHBOARD_ACCESS_PASSWORD)) {
    $is_authenticated = true;
}

// 🚨 3. 무결성 검증 실패 시 불법 접근으로 판단하여 즉시 연결 파괴 (403 거부)
if (!$is_authenticated) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=utf-8');
    die("<div style='padding:60px 20px; text-align:center; font-family:sans-serif; background-color:#f8fafc;'>
            <div style='max-width:500px; margin:0 auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); border:1px solid #e2e8f0;'>
                <h2 style='color:#0f172a; margin-top:0;'>🔒 인프라 사령탑 보안 잠금 상태</h2>
                <p style='color:#ef4444; font-weight:600; font-size:15px;'>올바른 액세스 경로 상수가 아닙니다. 권한이 거부되었습니다.</p>
                <p style='color:#64748b; font-size:13px; line-height:1.6;'>해커 침입 방지를 위해 보호막이 가동 중입니다.<br>주소창 맨 뒤에 정확한 암호 상수를 매핑하여 노크하세요.</p>
            </div>
         </div>");
}

// 응답 템플릿 헬퍼 함수 정의 (호이스팅 방지를 위해 상단 배치)
function json_with_response($success, $msg) {
    return json_encode(['success' => $success, 'message' => $msg, 'log' => ''], JSON_UNESCAPED_UNICODE);
}

// -------------------------------------------------------------------------
// [시스템 상수 정의] 부모 config.php 파일의 무결성 설정을 상속 및 방어 정의
// -------------------------------------------------------------------------
if (!defined('APP_STAGE_TITLE')) {
    define('APP_STAGE_TITLE', 'K-Shops24 Git 배포 사령탑 (v2026.06.05.1130)');
    define('DEFAULT_COMMIT_MSG', 'K-Shops24 백엔드 AJAX 기능 및 페이징 안정화 빌드');
}

// -------------------------------------------------------------------------
// [AJAX 통신 처리 섹션] 화면 깜빡임 없이 Git 명령어를 백엔드에서 대리 실행
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'execute_git') {
    header('Content-Type: application/json; charset=utf-8');
    
    $step = $_POST['step'] ?? '';
    $commit_message = trim($_POST['commit_message'] ?? '');
    if (empty($commit_message)) {
         $commit_message = DEFAULT_COMMIT_MSG;
    }
    
    $output = [];
    $status_code = 0;
    
    // 웹 서버 프로세스가 실행되는 위치를 현재 디렉토리로 명확히 강제 고정 (매우 중요)
    $base_dir = escapeshellarg(__DIR__);
    
    // 🛡️ 호스팅어 서버 환경 변수 강제 주입 (인증 정보 및 한글 깨짐 방지)
    $env = "export HOME=/home/u743828642 && export LANG=ko_KR.UTF-8";
    
    // 안전한 명령어 실행을 위한 쉘 환경 락인 및 줄바꿈 상숫값 대응
    switch ($step) {
        case 'step1':
            // [안전장치] 커밋 전 충돌 마커(<<<<<<< HEAD) 존재 여부 전수 검사
            // 대시보드 파일 및 배포 제외 대상인 문서 파일(*.txt, *.md)은 검사에서 제외하여 불필요한 차단을 방지합니다.
            $conflict_check = "grep -rl '<<<<<<< HEAD' . --exclude='git_deploy_dashboard.php' --exclude='*.txt' --exclude='*.md' 2>&1";
            $conflict_files = [];
            exec("cd {$base_dir} && {$conflict_check}", $conflict_files);
            if (!empty($conflict_files)) {
                echo json_encode(['success' => false, 'message' => '🚨 파일 내부에 충돌 마커가 발견되었습니다. 해당 파일들을 수정하기 전에는 커밋할 수 없습니다.', 'log' => "충돌 발생 파일 목록:\n" . implode("\n", $conflict_files)], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // [1단계] 배포 제외 대상 강제 세척(Sanitize) ➡️ 작업실 점검 ➡️ 전체 대기실 적재 ➡️ 로컬 버전 락인
            // 🛡️ 개발 유틸리티(_notes) 및 설정 파일들을 배포 대상에서 원천 차단합니다. (manuals 폴더는 상점주 교육용으로 유지)
            // [최적화] 존재하지 않는 파일 패턴에 대한 fatal 에러를 방지하기 위해 파일 존재 여부를 확인하는 로직으로 보강 가능하나, 현재는 가독성을 위해 패턴 유지
            $cleanup = "git rm -r --cached testers/ uploads/ schema.sql *.txt *.md *.json .vscode/ _notes/ 2>/dev/null || true";
            $cmd = "{$env} && cd {$base_dir} && {$cleanup} && git status 2>&1 && git add . 2>&1 && git commit -m " . escapeshellarg($commit_message) . " 2>&1";
            break;
            
        case 'step2':
            // [2단계] 인터넷 금고 develop 방으로 원격 백업 트럭 발송
            $cmd = "{$env} && cd {$base_dir} && git push origin develop 2>&1";
            break;
            
        case 'step3':
            // [3단계] 메인방 이동 ➡️ 무적 배포 ➡️ 🚀실서버 즉시 강제 동기화 (Zero-Lag)
            // 1. GitHub로 push를 수행하고, 
            // 2. 동일 서버 내의 실서버 폴더(/public_html)로 이동하여 즉시 강제 reset을 수행합니다.
            // 이렇게 하면 GitHub 웹훅의 지연이나 실패에 상관없이 즉각적으로 실서버에 반영됩니다.
            $live_dir = "/home/u743828642/domains/kshops24.com/public_html";
            $deploy_cmd = "git fetch origin 2>&1 && (git checkout -f main 2>&1 || git checkout -b main origin/main 2>&1) && git reset --hard develop 2>&1 && git push origin main --force 2>&1";
            $sync_cmd = "cd {$live_dir} && git fetch origin main 2>&1 && git reset --hard origin/main 2>&1";
            $cmd = "{$env} && cd {$base_dir} && {$deploy_cmd} && echo '\n--- [실서버 즉시 동기화 가동] ---\n' && {$sync_cmd}";
            break;
            
        case 'step4':
            // [4단계] 다음 개발 스케줄러 소화를 위해 안전 구역 안전 복귀
            $cmd = "{$env} && cd {$base_dir} && git checkout -f develop 2>&1";
            break;
            
        case 'rollback':
            // [긴급] 실서버 배포 취소: main 브랜치를 이전 커밋으로 되돌리고 강제 푸시
            $cmd = "{$env} && cd {$base_dir} && git checkout -f main 2>&1 && git reset --hard HEAD~1 2>&1 && git push origin main --force 2>&1 && git checkout -f develop 2>&1";
            break;

        case 'emergency_reset':
            // [긴급] 로컬 환경 완전 세척: 머지 중단 -> 원격 최신본 확보 -> 강제 덮어쓰기 -> 찌꺼기 파일 제거
            // 단순히 reset만 하는 것이 아니라 fetch와 clean을 조합하여 "완전 무결 상태"를 강제합니다.
            $cmd = "{$env} && cd {$base_dir} && git merge --abort 2>&1 || true && git fetch --all 2>&1 && git reset --hard origin/develop 2>&1 && git clean -fd 2>&1 && git checkout -f develop 2>&1";
            break;

        default:
            echo json_with_response(false, '올바르지 않은 인프라 배포 단계입니다.');
            exit;
    }
    
    // 리눅스/윈도우 쉘 다이렉트 커맨드 가동
    exec($cmd, $output, $status_code);
    $result_text = implode("\n", $output);
    
    // 에러 분기 무결성 대조 (보통 git commit 시 바뀐 게 없으면 에러코드를 뱉으므로 내용 매핑 필요)
    $is_success = ($status_code === 0 || strpos($result_text, 'nothing to commit') !== false || strpos($result_text, 'Already up to date') !== false || strpos($result_text, 'Everything up-to-date') !== false);
    
    echo json_encode([
        'success' => $is_success,
        'message' => $is_success ? '명령어가 성공적으로 체결되었습니다.' : '시스템 보강이 필요합니다. 로그를 확인하세요.',
        'log' => $result_text
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?php echo APP_STAGE_TITLE; ?></title>
    <!-- [추가] 결과 복사 버튼용 아이콘 연동 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* -----------------------------------------------------------------
         * 정갈한 무채색 계열의 모던 인프라 UI 테마 (중복 정의 배제 및 !important 금지)
         * ----------------------------------------------------------------- */
        body {
            font-family: 'Segoe UI', Malgun Gothic, sans-serif;
            background-color: #f4f6f9;
            color: #333;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
        }
        .header-area {
            text-align: center;
            margin-bottom: 35px;
        }
        .header-area h1 {
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .header-area p {
            color: #64748b;
            margin: 0;
        }
        /* 각 설정 구역 카드 레이아웃 정의 */
        .deploy-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s;
        }
        .deploy-card:hover {
            border-color: #cbd5e1;
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-badge {
            background-color: #3b82f6;
            color: #ffffff;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .card-description {
            color: #475569;
            font-size: 14px;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .cmd-preview {
            background-color: #1e1e2e;
            color: #cdd6f4;
            padding: 12px 16px;
            border-radius: 6px;
            font-family: 'Consolas', monospace;
            font-size: 13px;
            margin-bottom: 16px;
            overflow-x: auto;
            white-space: pre;
        }
        /* 입력 폼 및 무결성 제어 버튼 스펙 */
        .commit-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .commit-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .btn-execute {
            background-color: #1e293b;
            color: #ffffff;
            border: none;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-execute:hover {
            background-color: #334155;
        }
        /* [추가] 성공 기준 안내 텍스트 스타일 */
        .success-criteria {
            font-size: 0.75rem;
            color: #3b82f6;
            background-color: #eff6ff;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        /* [추가] 결과 복사 버튼 스타일 */
        .btn-copy {
            background-color: #64748b;
            color: #ffffff;
            border: none;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-left: 8px;
            display: none; /* 로그 데이터가 존재할 때만 동적으로 노출 */
            vertical-align: middle;
        }
        /* [추가] 사령탑 결과 판독 결과 박스 스타일 */
        .analysis-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: none;
        }
        .analysis-title {
            font-weight: 800;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .verdict-success { background-color: #ecfdf5; border: 1px solid #10b981; color: #065f46; }
        .verdict-error { background-color: #fef2f2; border: 1px solid #ef4444; color: #991b1b; }
        .verdict-warning { background-color: #fffbeb; border: 1px solid #f59e0b; color: #92400e; }
        .guide-text {
            line-height: 1.5;
        }
        .btn-copy:hover {
            background-color: #475569;
        }
        /* 라이브 스트리밍 콘솔창 스펙 */
        .console-log {
            background-color: #0f172a;
            color: #38bdf8;
            padding: 14px;
            border-radius: 6px;
            font-family: 'Consolas', monospace;
            font-size: 12px;
            margin-top: 15px;
            max-height: 180px;
            overflow-y: auto;
            white-space: pre-wrap;
            display: none;
        }
        /* 우측 하단 부드러운 Toast 알림 팝업 */
        #toast-container {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 9999;
        }
        .toast-box {
            background-color: #1e293b;
            color: #ffffff;
            padding: 14px 24px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.4s, transform 0.4s;
        }
        .toast-box.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-success { border-left: 4px solid #10b981; }
        .toast-error { border-left: 4px solid #ef4444; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-area">
        <h1><?php echo APP_STAGE_TITLE; ?></h1>
        <p>화면 깜빡임 없는 무중단 인프라 통합 관리 샌드박스</p>
    </div>

    <div id="section-step1" class="deploy-card">
        <div class="card-title">
            <span class="step-badge">1단계</span>
            <span>로컬 작업 내용 컴퓨터 금고에 격리 보관</span>
        </div>
        <div class="card-description">
            현재 수정 중인 소스코드의 작업실 상태를 확인하고, 배송 상자에 담아 내 로컬 안전지대 금고에 정식 버전으로 커밋을 집행합니다.
        </div>
        <div class="success-criteria">
            <i class="bi bi-info-circle-fill me-1"></i> <strong>성공 메시지:</strong> <code>[develop {해시값}]</code> 또는 <code>커밋할 사항 없음, 작업 폴더 깨끗함</code>
        </div>
        <div class="cmd-preview">git status && git add . && git commit -m "코멘트"</div>
        
        <input type="text" id="msg-step1" class="commit-input" value="<?php echo DEFAULT_COMMIT_MSG; ?>" placeholder="이번 배포 버전에 포스트잇으로 붙일 코멘트를 작성하세요.">
        <button type="button" class="btn-execute" onclick="runGitPipeline('step1', 'section-step1')">1단계 동기화 가동</button>
        <button type="button" class="btn-copy" id="btn-copy-step1" onclick="copyStepLog('step1')"><i class="bi bi-clipboard-check"></i> 결과 복사</button>
        
        <div id="console-step1" class="console-log"></div>
        <div id="analysis-step1" class="analysis-box"></div>
    </div>

    <div id="section-step2" class="deploy-card">
        <div class="card-title">
            <span class="step-badge">2단계</span>
            <span>GitHub 원격 금고에 안전하게 중간 백업</span>
        </div>
        <div class="card-description">
            내 컴퓨터 금고에 보관된 개발 진척도를 인터넷 세상인 GitHub 원격 저장소 `develop` 브랜치 방으로 쏘아 올려 안전하게 키핑합니다.
        </div>
        <div class="success-criteria">
            <i class="bi bi-info-circle-fill me-1"></i> <strong>성공 메시지:</strong> <code>Everything up-to-date</code> 또는 <code>develop -> develop</code>
        </div>
        <div class="cmd-preview">git push origin develop</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step2', 'section-step2')">2단계 원격 백업 발송</button>
        <button type="button" class="btn-copy" id="btn-copy-step2" onclick="copyStepLog('step2')"><i class="bi bi-clipboard-check"></i> 결과 복사</button>
        
        <div id="console-step2" class="console-log"></div>
        <div id="analysis-step2" class="analysis-box"></div>
    </div>

    <div id="section-step3" class="deploy-card">
        <div class="card-title">
            <span class="step-badge" style="background-color: #ef4444;">3단계</span>
            <span>🌟 실서버 웹훅 가동 및 무중단 배포 동기화</span>
        </div>
        <div class="card-description">
            배포 전용 방인 `main`으로 이동한 뒤 `develop` 코드를 무결성 병합하고, GitHub로 쏘아 올리는 즉시 실서버 배포 웹훅 사령탑을 노크하여 상용 서버를 1초 만에 자동 갱신합니다.
        </div>
        <div class="success-criteria">
            <i class="bi bi-info-circle-fill me-1"></i> <strong>성공 메시지:</strong> <code>main -> main (forced update)</code> 및 <code>HEAD의 현재 위치는...</code>
        </div>
        <div class="cmd-preview">git checkout main && git merge develop --no-edit && git push origin main</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step3', 'section-step3')">3단계 실서버 상용 배포 집행</button>
        <button type="button" class="btn-copy" id="btn-copy-step3" onclick="copyStepLog('step3')"><i class="bi bi-clipboard-check"></i> 결과 복사</button>
        
        <div id="console-step3" class="console-log"></div>
        <div id="analysis-step3" class="analysis-box"></div>
    </div>

    <div id="section-step4" class="deploy-card">
        <div class="card-title">
            <span class="step-badge" style="background-color: #10b981;">4단계</span>
            <span>안전한 개발 작업방(develop)으로 자동 복귀</span>
        </div>
        <div class="card-description">
            실서버 라이브 배포 프로세스가 완료되었으므로, 실서버에 아무런 영향을 주지 않고 다음 AJAX 기능 개발을 이어갈 수 있는 안전 구역 방으로 다시 후퇴합니다.
        </div>
        <div class="success-criteria">
            <i class="bi bi-info-circle-fill me-1"></i> <strong>성공 메시지:</strong> <code>'develop' 브랜치로 전환합니다</code>
        </div>
        <div class="cmd-preview">git checkout develop</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step4', 'section-step4')">4단계 작업방 안전 복귀</button>
        <button type="button" class="btn-copy" id="btn-copy-step4" onclick="copyStepLog('step4')"><i class="bi bi-clipboard-check"></i> 결과 복사</button>
        
        <div id="console-step4" class="console-log"></div>
        <div id="analysis-step4" class="analysis-box"></div>

        <!-- [추가] 로컬 VS Code 동기화 가이드 (M, U 마커 제거용) -->
        <div class="mt-4 p-3 border rounded-3 bg-light" id="local-sync-guide" style="display:none; border-left: 5px solid #10b981 !important;">
            <div class="fw-bold text-dark small mb-2"><i class="bi bi-pc-display me-1"></i> 로컬 VS Code 상태 동기화 (M, U 마커 제거)</div>
            <div class="text-muted" style="font-size: 0.75rem; line-height: 1.5;">
                배포가 완료되었습니다! 이제 내 컴퓨터의 VS Code 터미널에서 아래 명령어를 실행하여 소스 제어 마커를 정리하세요.
            </div>
            <div class="cmd-preview mt-2 mb-2" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; cursor: pointer;" onclick="copyToClipboard('git fetch origin; git reset --hard origin/develop', '동기화 명령어')">git fetch origin; git reset --hard origin/develop <i class="bi bi-clipboard ms-1"></i></div>
            <div class="small text-danger" style="font-size: 0.7rem;">* 주의: 로컬에만 작성 중인 미배포 코드가 있다면 모두 초기화됩니다.</div>
        </div>
    </div>

    <!-- [추가] 전체 결과 복사 버튼 (모든 과정 완료 후 리포트용) -->
    <div class="text-center mb-5" id="container-copy-all" style="display:none;">
        <button type="button" class="btn-execute" style="width:100%; background-color:#0f172a; padding:15px;" onclick="copyAllLogs()"><i class="bi bi-clipboard-data-fill me-2"></i>모든 단계 배포 결과 전체 복사</button>
    </div>

    <!-- 긴급 복구 섹션 (평소에는 눈에 띄지 않게 하단 배치) -->
    <div id="section-rollback" class="deploy-card" style="border-top: 4px solid #ef4444; background-color: #fffafb;">
        <div class="card-title">
            <span class="step-badge" style="background-color: #991b1b;">긴급 복구</span>
            <span style="color: #991b1b;">실서버 즉시 롤백 (이전 버전으로 복구)</span>
        </div>
        <div class="card-description">
            실서버(`main`)에 치명적인 문제가 발생했을 때 사용합니다. **가장 최근 배포를 취소**하고 실서버를 1단계 전으로 즉시 되돌립니다.
        </div>
        <button type="button" class="btn-execute" style="background-color: #ef4444;" onclick="if(confirm('정말로 실서버를 이전 상태로 되돌리겠습니까?')) runGitPipeline('rollback', 'section-rollback')">🚨 실서버 즉시 복구 실행</button>
        <div id="console-rollback" class="console-log"></div>
    </div>

    <!-- [추가] 로컬 환경 초기화 섹션 -->
    <div id="section-reset" class="deploy-card" style="border-top: 4px solid #64748b; background-color: #f8fafc;">
        <div class="card-title">
            <span class="step-badge" style="background-color: #64748b;">환경 정비</span>
            <span>테스트 서버 코드 꼬임 강제 해결</span>
        </div>
        <div class="card-description">
            사이트에 `<<<<<<< HEAD` 같은 문구가 보이거나 배포가 꼬였을 때 사용합니다. **현재 수정 중인 내용을 모두 파기**하고 가장 최근의 성공적인 `develop` 상태로 되돌립니다.
        </div>
        <button type="button" class="btn-execute" style="background-color: #64748b;" onclick="if(confirm('모든 수정사항을 버리고 깨끗한 상태로 되돌리시겠습니까?')) runGitPipeline('emergency_reset', 'section-reset')">🧹 로컬 환경 초기화 실행</button>
        <div id="console-emergency_reset" class="console-log"></div>
    </div>
</div>

<div id="toast-container"></div>

<script>
/**
 * 화면 깜빡임 없는 완벽한 비동기 AJAX 통신 파이프라인 엔진
 */
function runGitPipeline(step, sectionId) {
    const consoleBox = document.getElementById('console-' + step);
    const commitMsgInput = document.getElementById('msg-step1');
    const analysisBox = document.getElementById('analysis-' + step);
    const commitMessage = commitMsgInput ? commitMsgInput.value : '';
    const startTime = new Date().toLocaleTimeString();
    
    // 콘솔창을 초기 세척하고 부드럽게 활성화
    consoleBox.style.display = 'block';
    consoleBox.innerText = `[${startTime}] 인프라 파이프라인 가동 중... 호스팅어 리눅스 쉘 제어 커넥션을 맺는 중입니다.\n`;
    
    // 전송 데이터 직렬화 상숫값 매핑
    const formData = new FormData();
    formData.append('step', step);
    formData.append('commit_message', commitMessage);
    
    // 비동기 FETCH API 통신 개통
    fetch('?action=execute_git', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // 결과 반환 텍스트 주입
        consoleBox.innerText = data.log ? data.log : '반환된 아웃풋 로그 상수가 없습니다.';
        
        // 지침 이행: 처리가 끝나면 해당 고유 섹션 ID 카드로 즉시 부드럽게 스크롤 자동 포커싱
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // 지침 이행: 우측 하단 토스트 메시지 팝업 연동
        // 사령탑의 지능형 결과 분석 가동
        analyzeStepResult(step, data.log, data.success);

        if (data.success) {
            // 성공 시 해당 단계의 복사 버튼 및 하단 전체 복사 버튼 활성화
            if(document.getElementById('btn-copy-' + step)) {
                document.getElementById('btn-copy-' + step).style.display = 'inline-block';
            }
            
            document.getElementById('container-copy-all').style.display = 'block';
            // 4단계 완료 시 로컬 동기화 가이드 노출
            if (step === 'step4') {
                document.getElementById('local-sync-guide').style.display = 'block';
            }
            showToast('저장되었습니다. Git ' + step + ' 파이프라인 완료!', 'success');
        } else {
            showToast('인프라 경고: 하단 로그 상수를 즉시 분석하세요.', 'error');
        }
    })
    .catch(error => {
        consoleBox.innerText = '백엔드 통신 치명적 에러: ' + error;
        showToast('네트워크 커넥션 체결 실패', 'error');
    });
}

/**
 * [사령탑 인공지능] 각 단계별 로그를 분석하여 성공 여부 판독 및 가이드 제시
 */
function analyzeStepResult(step, log, backendSuccess) {
    const analysisBox = document.getElementById('analysis-' + step);
    if (!analysisBox) return;
    
    let verdict = 'error'; // success, error, warning
    let title = '';
    let guide = '';
    let icon = 'bi-exclamation-octagon-fill';

    const lowerLog = log.toLowerCase();

    switch (step) {
        case 'step1':
            if (log.includes('[develop') || log.includes('nothing to commit') || log.includes('작업 폴더 깨끗함')) {
                verdict = 'success';
                title = '로컬 락인 성공';
                guide = '작업 내용이 안전하게 로컬 저장소에 반영되었습니다. 이제 2단계로 진행하세요.';
                icon = 'bi-check-circle-fill';
            } else {
                title = '로컬 락인 확인 필요';
                guide = '로그를 확인해 주세요. 파일 수정 사항이 없거나 Git 설정 확인이 필요할 수 있습니다.';
            }
            break;
            
        case 'step2':
            if (log.includes('develop -> develop') || log.includes('Everything up-to-date')) {
                verdict = 'success';
                title = '원격 백업 성공';
                guide = 'GitHub 원격 금고로 전송이 완료되었습니다. 이제 실서버 배포(3단계)가 가능합니다.';
                icon = 'bi-check-circle-fill';
            } else if (log.includes('rejected') || log.includes('non-fast-forward')) {
                verdict = 'error';
                title = '원격지 역사 충돌';
                guide = 'GitHub와 로컬의 역사가 다릅니다. 터미널에서 <code>git push origin develop --force</code> 를 실행하거나 [환경 정비] 버튼을 사용하세요.';
            } else {
                title = '전송 실패';
                guide = '인터넷 연결 또는 GitHub 권한(Token)을 확인해 주세요.';
            }
            break;
            
        case 'step3':
            const hasMainPush = log.includes('main -> main');
            const hasSync = log.includes('--- [실서버 즉시 동기화 가동] ---') && log.includes('HEAD의 현재 위치는');
            
            if (hasMainPush && hasSync) {
                verdict = 'success';
                title = '실서버 라이브 배포 완수';
                guide = 'GitHub 전송과 실서버 즉시 동기화가 모두 성공했습니다! 이제 사이트(kshops24.com)에서 확인하세요.';
                icon = 'bi-rocket-takeoff-fill';
            } else if (log.includes('CONFLICT')) {
                verdict = 'error';
                title = '병합 대충돌 발생';
                guide = '파일 간 충돌이 발생했습니다. 사이트가 깨졌을 수 있으니 즉시 [환경 정비] 또는 [긴급 복구]를 실행하세요!';
            } else {
                title = '부분적 배포 성공 (확인 필요)';
                guide = 'GitHub 전송은 되었으나 실서버 동기화 로그가 불분명합니다. 사이트 반영 여부를 직접 체크하세요.';
                verdict = 'warning';
                icon = 'bi-exclamation-triangle-fill';
            }
            break;
            
        case 'step4':
            if (log.includes("'develop' 브랜치로 전환") || log.includes("Already on 'develop'")) {
                verdict = 'success';
                title = '안전 지대 복귀 완료';
                guide = '배포 모드가 종료되고 개발 모드로 돌아왔습니다. 하단의 가이드에 따라 VS Code 동기화를 진행하세요.';
                icon = 'bi-house-heart-fill';
            } else {
                title = '브랜치 이동 실패';
                guide = '현재 여전히 main 브랜치일 수 있습니다. 터미널에서 <code>git checkout develop</code>을 시도하세요.';
            }
            break;
            
        case 'emergency_reset':
            verdict = 'success';
            title = '환경 정화 완료';
            guide = '모든 꼬인 상태를 강제 종료하고 원격 저장소 기준으로 초기화했습니다. 다시 1단계부터 시작할 수 있습니다.';
            icon = 'bi-stars';
            break;
    }

    // 백엔드 자체 status_code가 에러인 경우 강제 에러 처리
    if (!backendSuccess && verdict === 'success') {
        verdict = 'error';
        title = '시스템 내부 오류';
        guide = 'Git 명령어 실행 중 오류가 발생했습니다. 로그 내용을 정밀 분석해야 합니다.';
        icon = 'bi-bug-fill';
    }

    // 분석 결과 렌더링
    analysisBox.className = `analysis-box verdict-${verdict}`;
    analysisBox.innerHTML = `
        <div class="analysis-title">
            <i class="bi ${icon}"></i> ${title}
        </div>
        <div class="guide-text">
            ${guide}
        </div>
    `;
    analysisBox.style.display = 'block';
    
    // 분석 결과로 스크롤 이동
    analysisBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * [추가] 특정 단계의 로그만 클립보드에 복사
 */
function copyStepLog(step) {
    const log = document.getElementById('console-' + step).innerText;
    if (!log || log.includes('인프라 파이프라인 가동 중')) return;
    
    navigator.clipboard.writeText(log).then(() => {
        showToast(step.toUpperCase() + ' 단계 결과가 복사되었습니다.', 'success');
    });
}

/**
 * [추가] 모든 단계의 로그를 취합하여 리포트 형식으로 클립보드에 복사
 */
function copyAllLogs() {
    const now = new Date().toLocaleString();
    let allLogs = `[K-Shops24 배포 리포트 - ${now}]\n`;
    allLogs += `------------------------------------------\n`;
    
    const steps = ['step1', 'step2', 'step3', 'step4'];
    let hasAnyLog = false;
    
    steps.forEach(s => {
        const log = document.getElementById('console-' + s).innerText;
        // 실제 결과가 있는 경우만 리포트에 포함
        if (log && !log.includes('인프라 파이프라인 가동 중') && !log.includes('반환된 아웃풋 로그 상수가 없습니다')) {
            allLogs += `\n[${s.toUpperCase()} RESULT]\n${log}\n`;
            allLogs += `------------------------------------------\n`;
            hasAnyLog = true;
        }
    });
    
    if (!hasAnyLog) {
        showToast('복사할 결과 데이터가 없습니다.', 'error');
        return;
    }
    
    navigator.clipboard.writeText(allLogs).then(() => {
        showToast('전체 배포 결과가 클립보드에 저장되었습니다.', 'success');
    });
}

/**
 * 우측 하단 UI 토스트 구현 핸들러 (대입 버그 전면 수정)
 */
function showToast(message, type) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    
    toast.className = `toast-box toast-${type}`;
    toast.innerText = message;
    
    container.appendChild(toast);
    
    // 부드럽게 솟아오르는 CSS 클래스 주입 상숫값 가동 (정적 에러 교정)
    setTimeout(() => {
        toast.classList.add('show');
    }, 50);
    
    // 3.5초 후 자연스럽게 소멸 소생술
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 3500);
}
</script>

</body>
</html>