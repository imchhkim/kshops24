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
    define('APP_STAGE_TITLE', 'K-Shops24 Git 배포 사령탑 (v2026.06.04.1110)');
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
            $cleanup = "git rm -r --cached testers/ uploads/ schema.sql *.txt *.md *.MD *.json .vscode/ _notes/ 2>&1 || true";
            $cmd = "{$env} && cd {$base_dir} && {$cleanup} && git status 2>&1 && git add . 2>&1 && git commit -m " . escapeshellarg($commit_message) . " 2>&1";
            break;
            
        case 'step2':
            // [2단계] 인터넷 금고 develop 방으로 원격 백업 트럭 발송
            $cmd = "{$env} && cd {$base_dir} && git push origin develop 2>&1";
            break;
            
        case 'step3':
            // [3단계] 메인방 이동 ➡️ 강제 초기화(무결점 보장) ➡️ 병합 ➡️ 🚀실서버 배포
            // pull 대신 fetch & reset을 사용하여 main 브랜치의 무결성을 먼저 확보합니다.
            $cmd = "{$env} && cd {$base_dir} && git config --local pull.rebase false 2>&1 && git config --local merge.ours.driver true 2>&1 && git fetch origin 2>&1 && (git checkout -f main 2>&1 || git checkout -b main origin/main 2>&1) && git reset --hard origin/main 2>&1 && git merge develop --allow-unrelated-histories --no-edit 2>&1 && git push origin main 2>&1";
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
        <div class="cmd-preview">git status && git add . && git commit -m "코멘트"</div>
        
        <input type="text" id="msg-step1" class="commit-input" value="<?php echo DEFAULT_COMMIT_MSG; ?>" placeholder="이번 배포 버전에 포스트잇으로 붙일 코멘트를 작성하세요.">
        <button type="button" class="btn-execute" onclick="runGitPipeline('step1', 'section-step1')">1단계 동기화 가동</button>
        
        <div id="console-step1" class="console-log"></div>
    </div>

    <div id="section-step2" class="deploy-card">
        <div class="card-title">
            <span class="step-badge">2단계</span>
            <span>GitHub 원격 금고에 안전하게 중간 백업</span>
        </div>
        <div class="card-description">
            내 컴퓨터 금고에 보관된 개발 진척도를 인터넷 세상인 GitHub 원격 저장소 `develop` 브랜치 방으로 쏘아 올려 안전하게 키핑합니다.
        </div>
        <div class="cmd-preview">git push origin develop</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step2', 'section-step2')">2단계 원격 백업 발송</button>
        
        <div id="console-step2" class="console-log"></div>
    </div>

    <div id="section-step3" class="deploy-card">
        <div class="card-title">
            <span class="step-badge" style="background-color: #ef4444;">3단계</span>
            <span>🌟 실서버 웹훅 가동 및 무중단 배포 동기화</span>
        </div>
        <div class="card-description">
            배포 전용 방인 `main`으로 이동한 뒤 `develop` 코드를 무결성 병합하고, GitHub로 쏘아 올리는 즉시 실서버 배포 웹훅 사령탑을 노크하여 상용 서버를 1초 만에 자동 갱신합니다.
        </div>
        <div class="cmd-preview">git checkout main && git merge develop --no-edit && git push origin main</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step3', 'section-step3')">3단계 실서버 상용 배포 집행</button>
        
        <div id="console-step3" class="console-log"></div>
    </div>

    <div id="section-step4" class="deploy-card">
        <div class="card-title">
            <span class="step-badge" style="background-color: #10b981;">4단계</span>
            <span>안전한 개발 작업방(develop)으로 자동 복귀</span>
        </div>
        <div class="card-description">
            실서버 라이브 배포 프로세스가 완료되었으므로, 실서버에 아무런 영향을 주지 않고 다음 AJAX 기능 개발을 이어갈 수 있는 안전 구역 방으로 다시 후퇴합니다.
        </div>
        <div class="cmd-preview">git checkout develop</div>
        
        <button type="button" class="btn-execute" onclick="runGitPipeline('step4', 'section-step4')">4단계 작업방 안전 복귀</button>
        
        <div id="console-step4" class="console-log"></div>
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
    const commitMessage = commitMsgInput ? commitMsgInput.value : '';
    
    // 콘솔창을 초기 세척하고 부드럽게 활성화
    consoleBox.style.display = 'block';
    consoleBox.innerText = '인프라 파이프라인 가동 중... 호스팅어 리눅스 쉘 제어 커넥션을 맺는 중입니다.\n';
    
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
        if (data.success) {
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