<<<<<<< HEAD
<?php

/**
 * [다국어 자동 등록 유틸리티]
 * 정적 한글 문구를 입력하면 구글 API로 자동 번역 후 모든 lang 파일에 일괄 추가합니다.
 */

require_once __DIR__ . '/t_common.php';

// 번역 대상 언어 목록 (파일 명과 구글 번역 API 타겟 코드 매핑)
$lang_map = [
    'ko' => 'ko',
    'en' => 'en',
    'zh' => 'zh-CN', // 중국어 간체
    'ja' => 'ja',
    'vi' => 'vi',
    'es' => 'es',
    'fr' => 'fr',
    'ru' => 'ru'
];

$results = [];
$input_text = '';
$api_key = defined('GOOGLE_TRANSLATE_API_KEY') ? GOOGLE_TRANSLATE_API_KEY : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_single';
    $api_key = trim($_POST['api_key'] ?? '');

    // [추가] 모달이 열릴 때 AJAX로 해당 언어 파일의 최신 내용을 다시 읽어오는 로직
    if ($action === 'get_lang_file') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');

        $get_lang = $_POST['get_lang'] ?? '';
        if (isset($lang_map[$get_lang])) {
            $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/' . $get_lang . '.php';
            if (file_exists($file_path)) {
                $lang_dict = include $file_path;
                echo json_encode(['status' => 'success', 'data' => $lang_dict]);
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
        exit;
    }

    if ($action === 'sync_check') {
        if (!empty($api_key)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
            $ko_path = $base_dir . 'ko.php';

            if (file_exists($ko_path)) {
                $ko_dict = include $ko_path;
                foreach ($lang_map as $file_lang => $target_code) {
                    if ($file_lang === 'ko') {
                        $results['ko'] = ['status' => 'info', 'msg' => '기준 언어', 'text' => '원본'];
                        continue;
                    }

                    $file_path = $base_dir . $file_lang . '.php';
                    if (!file_exists($file_path)) {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 없음', 'text' => '-'];
                        continue;
                    }

                    $target_dict = include $file_path;
                    $missing_keys = [];
                    foreach ($ko_dict as $key => $val) {
                        if (!array_key_exists($key, $target_dict)) {
                            $missing_keys[] = $key;
                        }
                    }

                    if (!empty($missing_keys)) {
                        $content = file_get_contents($file_path);
                        $additions = "";
                        $restored_count = 0;
                        $restored_texts = [];

                        foreach ($missing_keys as $m_key) {
                            $translated_text = translateWithGoogle($m_key, 'ko', $target_code, $api_key);
                            $safe_key = addcslashes($m_key, "'\\");
                            $safe_val = addcslashes($translated_text, "'\\");
                            $additions .= "    '$safe_key' => '$safe_val',\n";
                            $restored_count++;
                            $restored_texts[] = $m_key;
                        }

                        $addition_block = $additions . "];";
                        $new_content = preg_replace('/\]\s*;\s*$/', $addition_block, $content);

                        if ($new_content && $new_content !== $content) {
                            if (file_put_contents($file_path, $new_content)) {
                                $results[$file_lang] = ['status' => 'success', 'msg' => "{$restored_count}건 복구됨", 'text' => implode(', ', $restored_texts)];
                            } else {
                                $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                            }
                        } else {
                            $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 구조 파싱에 실패했습니다.', 'text' => '-'];
                        }
                    } else {
                        $results[$file_lang] = ['status' => 'secondary', 'msg' => '누락 없음', 'text' => '100% 동기화 완료'];
                    }
                }
            } else {
                $results['sys'] = ['status' => 'danger', 'msg' => 'ko.php 파일을 찾을 수 없습니다.'];
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => 'API Key가 필요합니다.'];
        }
    } elseif ($action === 'edit_lang_file') {
        $edit_lang = $_POST['edit_lang'] ?? '';
        $lang_keys = $_POST['lang_keys'] ?? [];
        $lang_values = $_POST['lang_values'] ?? [];

        if (!empty($edit_lang) && isset($lang_map[$edit_lang])) {
            $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/' . $edit_lang . '.php';
            if (file_exists($file_path)) {
                // 새로운 사전 파일 내용 생성
                $new_content = "<?php\n// " . ($edit_lang === 'ko' ? '한국어' : strtoupper($edit_lang)) . " 사전 파일\nreturn [\n";
                foreach ($lang_keys as $index => $key) {
                    $val = $lang_values[$index] ?? '';
                    $safe_key = addcslashes($key, "'\\");
                    $safe_val = addcslashes($val, "'\\");
                    $new_content .= "    '$safe_key' => '$safe_val',\n";
                }
                $new_content .= "];\n";

                if (file_put_contents($file_path, $new_content)) {
                    $results[$edit_lang] = ['status' => 'success', 'msg' => '항목 전체 수정 완료', 'text' => '전체 항목이 성공적으로 업데이트 되었습니다.'];
                } else {
                    $results[$edit_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                }
            } else {
                $results['sys'] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.'];
            }
        }
    } elseif ($action === 'translate_shop_items') {
        $target_shop_id = (int)($_POST['shop_id'] ?? 0);

        if ($target_shop_id > 0 && !empty($api_key)) {
            $stmt = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
            $stmt->execute([$target_shop_id]);
            $shop = $stmt->fetch();

            if ($shop) {
                $ui = json_decode($shop['ui_settings'] ?: '{}', true);
                $is_multi = ($ui['is_multilingual'] ?? 0) == 1;

                if ($is_multi) {
                    $lang1 = $ui['multilingual_lang1'] ?? 'none';
                    $lang2 = $ui['multilingual_lang2'] ?? 'none';

                    $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                    if (empty($lang1_code)) $lang1_code = 'etc1';

                    $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                    if (empty($lang2_code)) $lang2_code = 'etc2';

                    $gt_lang1 = $lang_map[$lang1] ?? ($lang1_code !== 'etc1' ? $lang1_code : null);
                    $gt_lang2 = $lang_map[$lang2] ?? ($lang2_code !== 'etc2' ? $lang2_code : null);

                    if ($gt_lang1 || $gt_lang2) {
                        $stmt_items = $pdo->prepare("SELECT id, item_name, item_info, translations FROM shop_items WHERE shop_id = ?");
                        $stmt_items->execute([$target_shop_id]);
                        $items = $stmt_items->fetchAll();

                        $updated_count = 0;
                        foreach ($items as $item) {
                            $trans = [];
                            if (!empty($item['translations'])) {
                                $decoded = json_decode(htmlspecialchars_decode($item['translations']), true);
                                if (is_string($decoded)) $decoded = json_decode($decoded, true);
                                if (is_array($decoded)) $trans = $decoded;
                            }

                            $changed = false;
                            if ($gt_lang1 && $lang1 !== 'ko') {
                                if (empty($trans[$lang1_code]['item_name']) && !empty($item['item_name'])) {
                                    $trans[$lang1_code]['item_name'] = translateWithGoogle($item['item_name'], 'ko', $gt_lang1, $api_key);
                                    $changed = true;
                                }
                                if (empty($trans[$lang1_code]['item_info']) && !empty($item['item_info'])) {
                                    $trans[$lang1_code]['item_info'] = translateWithGoogle($item['item_info'], 'ko', $gt_lang1, $api_key);
                                    $changed = true;
                                }
                            }
                            if ($gt_lang2 && $lang2 !== 'ko') {
                                if (empty($trans[$lang2_code]['item_name']) && !empty($item['item_name'])) {
                                    $trans[$lang2_code]['item_name'] = translateWithGoogle($item['item_name'], 'ko', $gt_lang2, $api_key);
                                    $changed = true;
                                }
                                if (empty($trans[$lang2_code]['item_info']) && !empty($item['item_info'])) {
                                    $trans[$lang2_code]['item_info'] = translateWithGoogle($item['item_info'], 'ko', $gt_lang2, $api_key);
                                    $changed = true;
                                }
                            }

                            if ($changed) {
                                $json_trans = json_encode($trans, JSON_UNESCAPED_UNICODE);
                                $pdo->prepare("UPDATE shop_items SET translations = ? WHERE id = ?")->execute([$json_trans, $item['id']]);
                                $updated_count++;
                            }
                        }
                        $results['shop_trans'] = ['status' => 'success', 'msg' => "상점(ID: {$target_shop_id})의 아이템 {$updated_count}건 다국어 자동 번역 완료!"];
                    } else {
                        $results['shop_trans'] = ['status' => 'warning', 'msg' => "번역을 지원하지 않는 설정입니다. (기타 언어 사용 등)"];
                    }
                } else {
                    $results['shop_trans'] = ['status' => 'warning', 'msg' => "해당 상점은 다국어 지원이 OFF되어 있습니다."];
                }
            } else {
                $results['shop_trans'] = ['status' => 'danger', 'msg' => "해당 ID의 상점을 찾을 수 없습니다."];
            }
        } else {
            $results['shop_trans'] = ['status' => 'danger', 'msg' => "상점 ID와 구글 API Key를 모두 입력해주세요."];
        }
    } elseif ($action === 'delete_single') {
        $input_text = trim($_POST['kr_text'] ?? '');
        if (!empty($input_text)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
            foreach ($lang_map as $file_lang => $target_code) {
                $file_path = $base_dir . $file_lang . '.php';
                if (!file_exists($file_path)) {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.', 'text' => '-'];
                    continue;
                }

                $lang_dict = include $file_path;
                if (isset($lang_dict[$input_text])) {
                    unset($lang_dict[$input_text]);

                    $new_content = "<?php\n// " . ($file_lang === 'ko' ? '한국어' : strtoupper($file_lang)) . " 사전 파일\nreturn [\n";
                    foreach ($lang_dict as $key => $val) {
                        $safe_key = addcslashes($key, "'\\");
                        $safe_val = addcslashes($val, "'\\");
                        $new_content .= "    '$safe_key' => '$safe_val',\n";
                    }
                    $new_content .= "];\n";

                    if (file_put_contents($file_path, $new_content)) {
                        $results[$file_lang] = ['status' => 'success', 'msg' => '항목 삭제 완료', 'text' => $input_text];
                    } else {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                    }
                } else {
                    $results[$file_lang] = ['status' => 'secondary', 'msg' => '항목 없음', 'text' => '-'];
                }
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => '삭제할 항목(Key)을 입력해주세요.'];
        }
    } else {
        $input_text = trim($_POST['kr_text'] ?? '');

        if (!empty($input_text) && !empty($api_key)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';

            foreach ($lang_map as $file_lang => $target_code) {
                $file_path = $base_dir . $file_lang . '.php';

                if (!file_exists($file_path)) {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.'];
                    continue;
                }

                // 1. 번역 진행
                $translated_text = $input_text;
                if ($file_lang !== 'ko') {
                    $translated_text = translateWithGoogle($input_text, 'ko', $target_code, $api_key);
                }

                // 2. 파일 스캔 및 추가
                $content = file_get_contents($file_path);

                // 싱글 쿼트 이스케이프 처리
                $safe_key = addcslashes($input_text, "'\\");
                $safe_val = addcslashes($translated_text, "'\\");

                // 이미 해당 키(한글 문구)가 등록되어 있는지 체크
                $escaped_regex_key = preg_quote($safe_key, '/');
                if (preg_match("/'$escaped_regex_key'\s*=>/", $content)) {
                    $results[$file_lang] = ['status' => 'warning', 'msg' => '이미 등록된 문구입니다.', 'text' => $translated_text];
                    continue;
                }

                // 파일의 가장 마지막 닫는 괄호 "];" 를 찾아서 그 앞에 새 배열 요소를 삽입
                $addition = "    '$safe_key' => '$safe_val',\n];";
                $new_content = preg_replace('/\]\s*;\s*$/', $addition, $content);

                if ($new_content && $new_content !== $content) {
                    if (file_put_contents($file_path, $new_content)) {
                        $results[$file_lang] = ['status' => 'success', 'msg' => '성공적으로 추가됨', 'text' => $translated_text];
                    } else {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.'];
                    }
                } else {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 구조 파싱에 실패했습니다.'];
                }
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => '문구와 API Key를 모두 입력해주세요.'];
        }
    }
}

/**
 * 구글 번역 API 호출 헬퍼
 */
function translateWithGoogle($text, $source, $target, $key)
{
    $url = "https://translation.googleapis.com/language/translate/v2?key=" . $key;
    $data = [
        'q' => $text,
        'source' => $source,
        'target' => $target,
        'format' => 'text'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    if (isset($res['data']['translations'][0]['translatedText'])) {
        // HTML 엔티티(&amp; 등) 원상복구
        return htmlspecialchars_decode($res['data']['translations'][0]['translatedText'], ENT_QUOTES);
    }
    return $text . " (번역실패)";
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>다국어 일괄 추가 유틸리티</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .lang-item-row { transition: all 0.3s ease; }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm border-0 rounded-4 mx-auto fade-in-up" style="max-width: 800px;">
            <div class="card-header bg-dark text-white p-4">
                <h4 class="mb-0 fw-bold">🌐 정적 문구 다국어 일괄 추가 툴</h4>
            </div>
            <div class="card-body p-4">
                <!-- 사용 방법 안내 -->
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>작업 진행 순서</h6>
                    <ol class="mb-0 small text-dark lh-lg">
                        <li>PHP 코드(예: <code>shop_view_realty.php</code> 등)에 하드코딩된 한글(예: <strong>"검색"</strong>)을 찾아 <code>__("검색")</code> 형태로 감싸서 먼저 코드를 저장합니다.</li>
                        <li>시스템 설정(<code>config.php</code>)에 <code>GOOGLE_TRANSLATE_API_KEY</code> 상수가 올바르게 등록되어 있는지 확인합니다.</li>
                        <li>아래 <strong>'추가할 기준 한국어 텍스트'</strong> 입력창에 방금 <code>__()</code>로 감쌌던 원본 한글 텍스트를 복사하여 그대로 입력합니다.</li>
                        <li><strong>'번역 후 파일에 일괄 추가하기'</strong> 버튼을 누르면 구글 AI가 8개 국어로 번역하여 <code>/common/lang/</code> 폴더 안의 각 언어 파일 마지막에 자동으로 끼워 넣습니다. <strong>'일괄 삭제'</strong> 버튼을 누르면 입력한 텍스트를 모든 파일에서 찾아 지웁니다.</li>
                        <li><strong>'모든 언어 파일 동기화 점검 및 자동 복구'</strong> 버튼을 누르면 <code>ko.php</code>를 기준으로 타 언어 파일에 누락된 번역을 찾아 자동으로 채워 넣습니다.</li>
                    </ol>
                </div>

                <?php if (isset($results['sys'])): ?>
                    <div class="alert alert-danger"><?php echo $results['sys']['msg']; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="api_key" id="api_key_main" value="<?php echo htmlspecialchars($api_key); ?>">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary">추가/삭제할 기준 한국어 텍스트 (Key)</label>
                        <textarea name="kr_text" class="form-control" rows="3" required placeholder="예: 비싼 배달앱 수수료는 그만!"></textarea>
                        <div class="form-text">이 문구를 기준으로 8개 국어로 번역하여 각 언어 사전 파일의 마지막에 추가하거나, 모든 파일에서 해당 항목을 일괄 삭제합니다.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <button type="submit" name="action" value="add_single" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm fs-5">번역 후 파일에 일괄 추가하기</button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="action" value="delete_single" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow-sm fs-5" onclick="return confirm('정말 모든 언어 파일에서 이 항목을 삭제하시겠습니까?');">일괄 삭제</button>
                        </div>
                    </div>
                    <button type="submit" name="action" value="sync_check" formnovalidate class="btn btn-warning w-100 py-3 fw-bold rounded-pill shadow-sm fs-5 mt-3"><i class="bi bi-shield-check me-2"></i>모든 언어 파일 동기화 점검 및 자동 복구</button>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($results['sys'])): ?>
            <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 fade-in-up" style="max-width: 800px; animation-delay: 0.1s;">
                <div class="card-header bg-success text-white p-3">
                    <h5 class="mb-0 fw-bold">🚀 처리 결과</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4" style="width: 15%">언어</th>
                                <th style="width: 25%">결과</th>
                                <th>기록된 텍스트</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lang_map as $file_lang => $api_code): ?>
                                <?php
                                $res = $results[$file_lang] ?? ['status' => 'secondary', 'msg' => '대기', 'text' => ''];
                                $badge_bg = 'bg-' . $res['status'];
                                if ($res['status'] === 'warning') $badge_bg = 'bg-warning text-dark';

                                // 현재 언어 파일에 등록된 총 단어 수 계산
                                $lang_file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . "/common/lang/{$file_lang}.php";
                                $word_count = 0;
                                if (file_exists($lang_file_path)) {
                                    $lang_dict = include $lang_file_path;
                                    if (is_array($lang_dict)) $word_count = count($lang_dict);
                                }
                                ?>
                                <tr>
                                    <td class="px-4 fw-bold text-uppercase text-secondary">
                                        <?php echo $file_lang; ?>.php
                                        <span class="badge bg-light text-secondary border ms-1 fw-normal shadow-sm" style="font-size: 0.65rem; vertical-align: middle;" title="총 단어 수"><?php echo number_format($word_count); ?></span>
                                    </td>
                                    <td><span class="badge <?php echo $badge_bg; ?> px-2 py-1"><?php echo $res['msg']; ?></span></td>
                                    <td>
                                        <?php if (!empty($res['text'])): ?>
                                            <code><?php echo htmlspecialchars($res['text']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- 현재 언어 사전 내용 보기 (아코디언) -->
        <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 fade-in-up" style="max-width: 800px; animation-delay: 0.2s;">
            <div class="card-header bg-secondary text-white p-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-code me-2"></i>각 언어 사전 파일 현재 내용 보기</h5>
            </div>
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="langFilesAccordion">
                    <?php
                    $base_dir_view = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
                    $modals_html = '';
                    foreach ($lang_map as $file_lang => $api_code):
                        $file_path_view = $base_dir_view . $file_lang . '.php';
                        $file_content = file_exists($file_path_view) ? file_get_contents($file_path_view) : "파일이 존재하지 않습니다.";
                        $lang_dict = file_exists($file_path_view) ? include $file_path_view : [];
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header position-relative" id="heading_<?php echo $file_lang; ?>">
                                <button class="accordion-button collapsed fw-bold text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?php echo $file_lang; ?>" style="padding-right: 120px;">
                                    <?php echo $file_lang; ?>.php 사전 파일
                                </button>
                                <button type="button" class="btn btn-sm btn-primary position-absolute top-50 end-0 translate-middle-y" style="z-index: 5; margin-right: 3.5rem;" data-bs-toggle="modal" data-bs-target="#editModal_<?php echo $file_lang; ?>" onclick="event.stopPropagation();">
                                    <i class="bi bi-pencil-square me-1"></i>항목 수정
                                </button>
                            </h2>
                            <div id="collapse_<?php echo $file_lang; ?>" class="accordion-collapse collapse" data-bs-parent="#langFilesAccordion">
                                <div class="accordion-body bg-light p-3">
                                    <pre class="mb-0 small bg-white p-3 border rounded shadow-sm" style="max-height: 350px; overflow-y: auto; font-family: monospace; white-space: pre-wrap;"><code><?php echo htmlspecialchars($file_content); ?></code></pre>
                                </div>
                            </div>
                        </div>

<?php ob_start(); ?>
                        <!-- [모달] 언어별 항목 수정 -->
                        <div class="modal fade" id="editModal_<?php echo $file_lang; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <form method="POST" action="">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i><?php echo strtoupper($file_lang); ?> 사전 파일 직접 수정</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-light">
                                            <input type="hidden" name="action" value="edit_lang_file">
                                            <input type="hidden" name="edit_lang" value="<?php echo $file_lang; ?>">
                                            <input type="hidden" name="api_key" class="sync_api_key" value="<?php echo htmlspecialchars($api_key); ?>">
                                            <?php if (!empty($lang_dict) && is_array($lang_dict)): ?>
                                                <!-- [추가] 항목 검색 필드 -->
                                                <div class="mb-3 position-sticky top-0 bg-light py-2" style="z-index: 10;">
                                                    <div class="input-group input-group-sm shadow-sm">
                                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                                        <input type="text" class="form-control border-start-0 ps-0" placeholder="기준 항목 (Key) 또는 번역 내용 (Value) 검색..." oninput="filterLangItems(this, '<?php echo $file_lang; ?>')">
                                                    </div>
                                                </div>
                                                <div id="lang-items-container-<?php echo $file_lang; ?>">
                                                    <?php foreach ($lang_dict as $key => $val): ?>
                                                        <div class="mb-3 p-3 bg-white border rounded shadow-sm lang-item-row">
                                                            <label class="form-label small text-muted fw-bold mb-1">기준 항목 (Key)</label>
                                                            <input type="text" name="lang_keys[]" class="form-control form-control-sm bg-light mb-2 text-dark lang-key-input" value="<?php echo htmlspecialchars($key); ?>" readonly>
                                                            <label class="form-label small text-primary fw-bold mb-1">번역 내용 (Value)</label>
                                                            <textarea name="lang_values[]" class="form-control form-control-sm lang-value-input" rows="2" required><?php echo htmlspecialchars($val); ?></textarea>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-5">파일이 없거나 항목이 비어있습니다.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer border-0 p-3 bg-white justify-content-end shadow-sm">
                                            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold border shadow-sm" data-bs-dismiss="modal">취소</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="bi bi-save me-1"></i>변경사항 저장</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
<?php $modals_html .= ob_get_clean(); ?>
<?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- [신규 추가] 상점 메뉴/매물 다국어 일괄 번역 -->
        <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 mb-5 fade-in-up" style="max-width: 800px; animation-delay: 0.3s;">
            <div class="card-header bg-info text-dark p-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-translate me-2"></i>상점 메뉴/매물 다국어 일괄 자동 번역</h5>
            </div>
            <div class="card-body p-4 bg-white rounded-bottom-4">
                <div class="alert alert-light border shadow-sm mb-4 small text-muted">
                    <i class="bi bi-info-circle-fill me-1 text-info"></i>특정 상점의 ID를 입력하면, 해당 상점에 등록된 모든 메뉴(또는 매물)의 <strong>이름과 상세 설명</strong>을 상점의 다국어 설정(외국어1, 외국어2)에 맞춰 구글 API로 자동 번역하여 DB에 업데이트합니다.<br>
                    <span class="text-danger">* 이미 번역된 내용이 있는 항목은 건너뛰고 빈 곳만 채웁니다.</span>
                </div>

                <?php if (isset($results['shop_trans'])): ?>
                    <div class="alert alert-<?php echo $results['shop_trans']['status']; ?> shadow-sm">
                        <?php echo $results['shop_trans']['msg']; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="translate_shop_items">
                    <input type="hidden" name="api_key" class="sync_api_key" value="<?php echo htmlspecialchars($api_key); ?>">

                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-light fw-bold fs-6">상점 ID</span>
                        <input type="number" name="shop_id" class="form-control" required placeholder="예: 15">
                        <button type="submit" class="btn btn-info fw-bold text-dark px-4 fs-6">아이템 번역 시작</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php echo $modals_html; ?>

    <!-- 아코디언 기능 동작을 위한 Bootstrap 스크립트 추가 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 폼 전송 시 지연으로 인한 먹통 현상 방지용 UI 피드백
        document.addEventListener('DOMContentLoaded', function() {
            const mainKeyInput = document.getElementById('api_key_main');
            const savedKey = localStorage.getItem('tester_google_translate_key');
            if (savedKey && mainKeyInput && !mainKeyInput.value) {
                mainKeyInput.value = savedKey;
            }

            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (mainKeyInput && mainKeyInput.value) {
                        localStorage.setItem('tester_google_translate_key', mainKeyInput.value);
                        document.querySelectorAll('.sync_api_key').forEach(input => input.value = mainKeyInput.value);
                    }
                    const submitter = e.submitter;
                    if (submitter) {
                        const hiddenAction = document.createElement('input');
                        hiddenAction.type = 'hidden';
                        hiddenAction.name = submitter.name;
                        hiddenAction.value = submitter.value;
                        this.appendChild(hiddenAction);

                        submitter.disabled = true;
                        submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> 처리 중...';
                    }
                });
            });
        });

        // [추가] 모달 내 항목 검색(필터링) 기능
        function filterLangItems(inputEl, langCode) {
            const keyword = inputEl.value.toLowerCase();
            const container = document.getElementById('lang-items-container-' + langCode);
            if (!container) return;

            const items = container.querySelectorAll('.lang-item-row');
            items.forEach(item => {
                const keyInput = item.querySelector('.lang-key-input');
                const valInput = item.querySelector('.lang-value-input');

                const keyText = keyInput ? keyInput.value.toLowerCase() : '';
                const valText = valInput ? valInput.value.toLowerCase() : '';

                if (keyText.includes(keyword) || valText.includes(keyword)) {
                    item.style.display = '';
                    setTimeout(() => { item.style.opacity = '1'; item.style.transform = 'scale(1)'; }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.98)';
                    setTimeout(() => { if (item.style.opacity === '0') item.style.display = 'none'; }, 300);
                }
            });
        }

        // [추가] 모달이 열릴 때 최신 데이터를 서버에서 다시 읽어오도록 이벤트 리스너 추가
        document.addEventListener('show.bs.modal', async function(event) {
            const modal = event.target;
            if (modal.id.startsWith('editModal_')) {
                const langCode = modal.id.replace('editModal_', '');
                const container = document.getElementById('lang-items-container-' + langCode);

                if (container) {
                    // 로딩 표시
                    container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> 파일의 최신 내용을 읽어오는 중...</div>';

                    const formData = new FormData();
                    formData.append('action', 'get_lang_file');
                    formData.append('get_lang', langCode);

                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.status === 'success') {
                            let html = '';
                            const langDict = result.data;
                            if (Object.keys(langDict).length > 0) {
                                for (const key in langDict) {
                                    const val = langDict[key];
                                    const safeKey = key.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
                                    const safeVal = val.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
                                    html += `
                                        <div class="mb-3 p-3 bg-white border rounded shadow-sm lang-item-row">
                                            <label class="form-label small text-muted fw-bold mb-1">기준 항목 (Key)</label>
                                            <input type="text" name="lang_keys[]" class="form-control form-control-sm bg-light mb-2 text-dark lang-key-input" value="${safeKey}" readonly>
                                            <label class="form-label small text-primary fw-bold mb-1">번역 내용 (Value)</label>
                                            <textarea name="lang_values[]" class="form-control form-control-sm lang-value-input" rows="2" required>${safeVal}</textarea>
                                        </div>
                                    `;
                                }
                            } else {
                                html = '<div class="text-center text-muted py-5">파일이 없거나 항목이 비어있습니다.</div>';
                            }
                            container.innerHTML = html;

                            // 검색창 초기화
                            const searchInput = modal.querySelector('input[type="text"][oninput]');
                            if (searchInput) searchInput.value = '';
                        } else {
                            container.innerHTML = `<div class="text-center text-danger py-5">${result.message}</div>`;
                        }
                    } catch (e) {
                        container.innerHTML = '<div class="text-center text-danger py-5">통신 중 오류가 발생했습니다.</div>';
                    }
                }
            }
        });
    </script>
</body>

=======
<?php

/**
 * [다국어 자동 등록 유틸리티]
 * 정적 한글 문구를 입력하면 구글 API로 자동 번역 후 모든 lang 파일에 일괄 추가합니다.
 */

require_once __DIR__ . '/t_common.php';

// 번역 대상 언어 목록 (파일 명과 구글 번역 API 타겟 코드 매핑)
$lang_map = [
    'ko' => 'ko',
    'en' => 'en',
    'zh' => 'zh-CN', // 중국어 간체
    'ja' => 'ja',
    'vi' => 'vi',
    'es' => 'es',
    'fr' => 'fr',
    'ru' => 'ru'
];

$results = [];
$input_text = '';
$api_key = defined('GOOGLE_TRANSLATE_API_KEY') ? GOOGLE_TRANSLATE_API_KEY : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_single';
    $api_key = trim($_POST['api_key'] ?? '');

    // [추가] 모달이 열릴 때 AJAX로 해당 언어 파일의 최신 내용을 다시 읽어오는 로직
    if ($action === 'get_lang_file') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');

        $get_lang = $_POST['get_lang'] ?? '';
        if (isset($lang_map[$get_lang])) {
            $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/' . $get_lang . '.php';
            if (file_exists($file_path)) {
                $lang_dict = include $file_path;
                echo json_encode(['status' => 'success', 'data' => $lang_dict]);
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => '파일을 찾을 수 없습니다.']);
        exit;
    }

    if ($action === 'sync_check') {
        if (!empty($api_key)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
            $ko_path = $base_dir . 'ko.php';

            if (file_exists($ko_path)) {
                $ko_dict = include $ko_path;
                foreach ($lang_map as $file_lang => $target_code) {
                    if ($file_lang === 'ko') {
                        $results['ko'] = ['status' => 'info', 'msg' => '기준 언어', 'text' => '원본'];
                        continue;
                    }

                    $file_path = $base_dir . $file_lang . '.php';
                    if (!file_exists($file_path)) {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 없음', 'text' => '-'];
                        continue;
                    }

                    $target_dict = include $file_path;
                    $missing_keys = [];
                    foreach ($ko_dict as $key => $val) {
                        if (!array_key_exists($key, $target_dict)) {
                            $missing_keys[] = $key;
                        }
                    }

                    if (!empty($missing_keys)) {
                        $content = file_get_contents($file_path);
                        $additions = "";
                        $restored_count = 0;
                        $restored_texts = [];

                        foreach ($missing_keys as $m_key) {
                            $translated_text = translateWithGoogle($m_key, 'ko', $target_code, $api_key);
                            $safe_key = addcslashes($m_key, "'\\");
                            $safe_val = addcslashes($translated_text, "'\\");
                            $additions .= "    '$safe_key' => '$safe_val',\n";
                            $restored_count++;
                            $restored_texts[] = $m_key;
                        }

                        $addition_block = $additions . "];";
                        $new_content = preg_replace('/\]\s*;\s*$/', $addition_block, $content);

                        if ($new_content && $new_content !== $content) {
                            if (file_put_contents($file_path, $new_content)) {
                                $results[$file_lang] = ['status' => 'success', 'msg' => "{$restored_count}건 복구됨", 'text' => implode(', ', $restored_texts)];
                            } else {
                                $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                            }
                        } else {
                            $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 구조 파싱에 실패했습니다.', 'text' => '-'];
                        }
                    } else {
                        $results[$file_lang] = ['status' => 'secondary', 'msg' => '누락 없음', 'text' => '100% 동기화 완료'];
                    }
                }
            } else {
                $results['sys'] = ['status' => 'danger', 'msg' => 'ko.php 파일을 찾을 수 없습니다.'];
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => 'API Key가 필요합니다.'];
        }
    } elseif ($action === 'edit_lang_file') {
        $edit_lang = $_POST['edit_lang'] ?? '';
        $lang_keys = $_POST['lang_keys'] ?? [];
        $lang_values = $_POST['lang_values'] ?? [];

        if (!empty($edit_lang) && isset($lang_map[$edit_lang])) {
            $file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/' . $edit_lang . '.php';
            if (file_exists($file_path)) {
                // 새로운 사전 파일 내용 생성
                $new_content = "<?php\n// " . ($edit_lang === 'ko' ? '한국어' : strtoupper($edit_lang)) . " 사전 파일\nreturn [\n";
                foreach ($lang_keys as $index => $key) {
                    $val = $lang_values[$index] ?? '';
                    $safe_key = addcslashes($key, "'\\");
                    $safe_val = addcslashes($val, "'\\");
                    $new_content .= "    '$safe_key' => '$safe_val',\n";
                }
                $new_content .= "];\n";

                if (file_put_contents($file_path, $new_content)) {
                    $results[$edit_lang] = ['status' => 'success', 'msg' => '항목 전체 수정 완료', 'text' => '전체 항목이 성공적으로 업데이트 되었습니다.'];
                } else {
                    $results[$edit_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                }
            } else {
                $results['sys'] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.'];
            }
        }
    } elseif ($action === 'translate_shop_items') {
        $target_shop_id = (int)($_POST['shop_id'] ?? 0);

        if ($target_shop_id > 0 && !empty($api_key)) {
            $stmt = $pdo->prepare("SELECT ui_settings FROM shops WHERE id = ?");
            $stmt->execute([$target_shop_id]);
            $shop = $stmt->fetch();

            if ($shop) {
                $ui = json_decode($shop['ui_settings'] ?: '{}', true);
                $is_multi = ($ui['is_multilingual'] ?? 0) == 1;

                if ($is_multi) {
                    $lang1 = $ui['multilingual_lang1'] ?? 'none';
                    $lang2 = $ui['multilingual_lang2'] ?? 'none';

                    $lang1_code = $lang1 === 'etc' ? strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1')) : $lang1;
                    if (empty($lang1_code)) $lang1_code = 'etc1';

                    $lang2_code = $lang2 === 'etc' ? strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2')) : $lang2;
                    if (empty($lang2_code)) $lang2_code = 'etc2';

                    $gt_lang1 = $lang_map[$lang1] ?? ($lang1_code !== 'etc1' ? $lang1_code : null);
                    $gt_lang2 = $lang_map[$lang2] ?? ($lang2_code !== 'etc2' ? $lang2_code : null);

                    if ($gt_lang1 || $gt_lang2) {
                        $stmt_items = $pdo->prepare("SELECT id, item_name, item_info, translations FROM shop_items WHERE shop_id = ?");
                        $stmt_items->execute([$target_shop_id]);
                        $items = $stmt_items->fetchAll();

                        $updated_count = 0;
                        foreach ($items as $item) {
                            $trans = [];
                            if (!empty($item['translations'])) {
                                $decoded = json_decode(htmlspecialchars_decode($item['translations']), true);
                                if (is_string($decoded)) $decoded = json_decode($decoded, true);
                                if (is_array($decoded)) $trans = $decoded;
                            }

                            $changed = false;
                            if ($gt_lang1 && $lang1 !== 'ko') {
                                if (empty($trans[$lang1_code]['item_name']) && !empty($item['item_name'])) {
                                    $trans[$lang1_code]['item_name'] = translateWithGoogle($item['item_name'], 'ko', $gt_lang1, $api_key);
                                    $changed = true;
                                }
                                if (empty($trans[$lang1_code]['item_info']) && !empty($item['item_info'])) {
                                    $trans[$lang1_code]['item_info'] = translateWithGoogle($item['item_info'], 'ko', $gt_lang1, $api_key);
                                    $changed = true;
                                }
                            }
                            if ($gt_lang2 && $lang2 !== 'ko') {
                                if (empty($trans[$lang2_code]['item_name']) && !empty($item['item_name'])) {
                                    $trans[$lang2_code]['item_name'] = translateWithGoogle($item['item_name'], 'ko', $gt_lang2, $api_key);
                                    $changed = true;
                                }
                                if (empty($trans[$lang2_code]['item_info']) && !empty($item['item_info'])) {
                                    $trans[$lang2_code]['item_info'] = translateWithGoogle($item['item_info'], 'ko', $gt_lang2, $api_key);
                                    $changed = true;
                                }
                            }

                            if ($changed) {
                                $json_trans = json_encode($trans, JSON_UNESCAPED_UNICODE);
                                $pdo->prepare("UPDATE shop_items SET translations = ? WHERE id = ?")->execute([$json_trans, $item['id']]);
                                $updated_count++;
                            }
                        }
                        $results['shop_trans'] = ['status' => 'success', 'msg' => "상점(ID: {$target_shop_id})의 아이템 {$updated_count}건 다국어 자동 번역 완료!"];
                    } else {
                        $results['shop_trans'] = ['status' => 'warning', 'msg' => "번역을 지원하지 않는 설정입니다. (기타 언어 사용 등)"];
                    }
                } else {
                    $results['shop_trans'] = ['status' => 'warning', 'msg' => "해당 상점은 다국어 지원이 OFF되어 있습니다."];
                }
            } else {
                $results['shop_trans'] = ['status' => 'danger', 'msg' => "해당 ID의 상점을 찾을 수 없습니다."];
            }
        } else {
            $results['shop_trans'] = ['status' => 'danger', 'msg' => "상점 ID와 구글 API Key를 모두 입력해주세요."];
        }
    } elseif ($action === 'delete_single') {
        $input_text = trim($_POST['kr_text'] ?? '');
        if (!empty($input_text)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
            foreach ($lang_map as $file_lang => $target_code) {
                $file_path = $base_dir . $file_lang . '.php';
                if (!file_exists($file_path)) {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.', 'text' => '-'];
                    continue;
                }

                $lang_dict = include $file_path;
                if (isset($lang_dict[$input_text])) {
                    unset($lang_dict[$input_text]);

                    $new_content = "<?php\n// " . ($file_lang === 'ko' ? '한국어' : strtoupper($file_lang)) . " 사전 파일\nreturn [\n";
                    foreach ($lang_dict as $key => $val) {
                        $safe_key = addcslashes($key, "'\\");
                        $safe_val = addcslashes($val, "'\\");
                        $new_content .= "    '$safe_key' => '$safe_val',\n";
                    }
                    $new_content .= "];\n";

                    if (file_put_contents($file_path, $new_content)) {
                        $results[$file_lang] = ['status' => 'success', 'msg' => '항목 삭제 완료', 'text' => $input_text];
                    } else {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.', 'text' => '-'];
                    }
                } else {
                    $results[$file_lang] = ['status' => 'secondary', 'msg' => '항목 없음', 'text' => '-'];
                }
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => '삭제할 항목(Key)을 입력해주세요.'];
        }
    } else {
        $input_text = trim($_POST['kr_text'] ?? '');

        if (!empty($input_text) && !empty($api_key)) {
            $base_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';

            foreach ($lang_map as $file_lang => $target_code) {
                $file_path = $base_dir . $file_lang . '.php';

                if (!file_exists($file_path)) {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일을 찾을 수 없습니다.'];
                    continue;
                }

                // 1. 번역 진행
                $translated_text = $input_text;
                if ($file_lang !== 'ko') {
                    $translated_text = translateWithGoogle($input_text, 'ko', $target_code, $api_key);
                }

                // 2. 파일 스캔 및 추가
                $content = file_get_contents($file_path);

                // 싱글 쿼트 이스케이프 처리
                $safe_key = addcslashes($input_text, "'\\");
                $safe_val = addcslashes($translated_text, "'\\");

                // 이미 해당 키(한글 문구)가 등록되어 있는지 체크
                $escaped_regex_key = preg_quote($safe_key, '/');
                if (preg_match("/'$escaped_regex_key'\s*=>/", $content)) {
                    $results[$file_lang] = ['status' => 'warning', 'msg' => '이미 등록된 문구입니다.', 'text' => $translated_text];
                    continue;
                }

                // 파일의 가장 마지막 닫는 괄호 "];" 를 찾아서 그 앞에 새 배열 요소를 삽입
                $addition = "    '$safe_key' => '$safe_val',\n];";
                $new_content = preg_replace('/\]\s*;\s*$/', $addition, $content);

                if ($new_content && $new_content !== $content) {
                    if (file_put_contents($file_path, $new_content)) {
                        $results[$file_lang] = ['status' => 'success', 'msg' => '성공적으로 추가됨', 'text' => $translated_text];
                    } else {
                        $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 쓰기 권한이 없습니다.'];
                    }
                } else {
                    $results[$file_lang] = ['status' => 'danger', 'msg' => '파일 구조 파싱에 실패했습니다.'];
                }
            }
        } else {
            $results['sys'] = ['status' => 'danger', 'msg' => '문구와 API Key를 모두 입력해주세요.'];
        }
    }
}

/**
 * 구글 번역 API 호출 헬퍼
 */
function translateWithGoogle($text, $source, $target, $key)
{
    $url = "https://translation.googleapis.com/language/translate/v2?key=" . $key;
    $data = [
        'q' => $text,
        'source' => $source,
        'target' => $target,
        'format' => 'text'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    if (isset($res['data']['translations'][0]['translatedText'])) {
        // HTML 엔티티(&amp; 등) 원상복구
        return htmlspecialchars_decode($res['data']['translations'][0]['translatedText'], ENT_QUOTES);
    }
    return $text . " (번역실패)";
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>다국어 일괄 추가 유틸리티</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .lang-item-row { transition: all 0.3s ease; }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm border-0 rounded-4 mx-auto fade-in-up" style="max-width: 800px;">
            <div class="card-header bg-dark text-white p-4">
                <h4 class="mb-0 fw-bold">🌐 정적 문구 다국어 일괄 추가 툴</h4>
            </div>
            <div class="card-body p-4">
                <!-- 사용 방법 안내 -->
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>작업 진행 순서</h6>
                    <ol class="mb-0 small text-dark lh-lg">
                        <li>PHP 코드(예: <code>shop_view_realty.php</code> 등)에 하드코딩된 한글(예: <strong>"검색"</strong>)을 찾아 <code>__("검색")</code> 형태로 감싸서 먼저 코드를 저장합니다.</li>
                        <li>시스템 설정(<code>config.php</code>)에 <code>GOOGLE_TRANSLATE_API_KEY</code> 상수가 올바르게 등록되어 있는지 확인합니다.</li>
                        <li>아래 <strong>'추가할 기준 한국어 텍스트'</strong> 입력창에 방금 <code>__()</code>로 감쌌던 원본 한글 텍스트를 복사하여 그대로 입력합니다.</li>
                        <li><strong>'번역 후 파일에 일괄 추가하기'</strong> 버튼을 누르면 구글 AI가 8개 국어로 번역하여 <code>/common/lang/</code> 폴더 안의 각 언어 파일 마지막에 자동으로 끼워 넣습니다. <strong>'일괄 삭제'</strong> 버튼을 누르면 입력한 텍스트를 모든 파일에서 찾아 지웁니다.</li>
                        <li><strong>'모든 언어 파일 동기화 점검 및 자동 복구'</strong> 버튼을 누르면 <code>ko.php</code>를 기준으로 타 언어 파일에 누락된 번역을 찾아 자동으로 채워 넣습니다.</li>
                    </ol>
                </div>

                <?php if (isset($results['sys'])): ?>
                    <div class="alert alert-danger"><?php echo $results['sys']['msg']; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="api_key" id="api_key_main" value="<?php echo htmlspecialchars($api_key); ?>">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary">추가/삭제할 기준 한국어 텍스트 (Key)</label>
                        <textarea name="kr_text" class="form-control" rows="3" required placeholder="예: 비싼 배달앱 수수료는 그만!"></textarea>
                        <div class="form-text">이 문구를 기준으로 8개 국어로 번역하여 각 언어 사전 파일의 마지막에 추가하거나, 모든 파일에서 해당 항목을 일괄 삭제합니다.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <button type="submit" name="action" value="add_single" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm fs-5">번역 후 파일에 일괄 추가하기</button>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="action" value="delete_single" class="btn btn-danger w-100 py-3 fw-bold rounded-pill shadow-sm fs-5" onclick="return confirm('정말 모든 언어 파일에서 이 항목을 삭제하시겠습니까?');">일괄 삭제</button>
                        </div>
                    </div>
                    <button type="submit" name="action" value="sync_check" formnovalidate class="btn btn-warning w-100 py-3 fw-bold rounded-pill shadow-sm fs-5 mt-3"><i class="bi bi-shield-check me-2"></i>모든 언어 파일 동기화 점검 및 자동 복구</button>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($results['sys'])): ?>
            <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 fade-in-up" style="max-width: 800px; animation-delay: 0.1s;">
                <div class="card-header bg-success text-white p-3">
                    <h5 class="mb-0 fw-bold">🚀 처리 결과</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4" style="width: 15%">언어</th>
                                <th style="width: 25%">결과</th>
                                <th>기록된 텍스트</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lang_map as $file_lang => $api_code): ?>
                                <?php
                                $res = $results[$file_lang] ?? ['status' => 'secondary', 'msg' => '대기', 'text' => ''];
                                $badge_bg = 'bg-' . $res['status'];
                                if ($res['status'] === 'warning') $badge_bg = 'bg-warning text-dark';

                                // 현재 언어 파일에 등록된 총 단어 수 계산
                                $lang_file_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . "/common/lang/{$file_lang}.php";
                                $word_count = 0;
                                if (file_exists($lang_file_path)) {
                                    $lang_dict = include $lang_file_path;
                                    if (is_array($lang_dict)) $word_count = count($lang_dict);
                                }
                                ?>
                                <tr>
                                    <td class="px-4 fw-bold text-uppercase text-secondary">
                                        <?php echo $file_lang; ?>.php
                                        <span class="badge bg-light text-secondary border ms-1 fw-normal shadow-sm" style="font-size: 0.65rem; vertical-align: middle;" title="총 단어 수"><?php echo number_format($word_count); ?></span>
                                    </td>
                                    <td><span class="badge <?php echo $badge_bg; ?> px-2 py-1"><?php echo $res['msg']; ?></span></td>
                                    <td>
                                        <?php if (!empty($res['text'])): ?>
                                            <code><?php echo htmlspecialchars($res['text']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- 현재 언어 사전 내용 보기 (아코디언) -->
        <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 fade-in-up" style="max-width: 800px; animation-delay: 0.2s;">
            <div class="card-header bg-secondary text-white p-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-code me-2"></i>각 언어 사전 파일 현재 내용 보기</h5>
            </div>
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="langFilesAccordion">
                    <?php
                    $base_dir_view = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/common/lang/';
                    $modals_html = '';
                    foreach ($lang_map as $file_lang => $api_code):
                        $file_path_view = $base_dir_view . $file_lang . '.php';
                        $file_content = file_exists($file_path_view) ? file_get_contents($file_path_view) : "파일이 존재하지 않습니다.";
                        $lang_dict = file_exists($file_path_view) ? include $file_path_view : [];
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header position-relative" id="heading_<?php echo $file_lang; ?>">
                                <button class="accordion-button collapsed fw-bold text-uppercase" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?php echo $file_lang; ?>" style="padding-right: 120px;">
                                    <?php echo $file_lang; ?>.php 사전 파일
                                </button>
                                <button type="button" class="btn btn-sm btn-primary position-absolute top-50 end-0 translate-middle-y" style="z-index: 5; margin-right: 3.5rem;" data-bs-toggle="modal" data-bs-target="#editModal_<?php echo $file_lang; ?>" onclick="event.stopPropagation();">
                                    <i class="bi bi-pencil-square me-1"></i>항목 수정
                                </button>
                            </h2>
                            <div id="collapse_<?php echo $file_lang; ?>" class="accordion-collapse collapse" data-bs-parent="#langFilesAccordion">
                                <div class="accordion-body bg-light p-3">
                                    <pre class="mb-0 small bg-white p-3 border rounded shadow-sm" style="max-height: 350px; overflow-y: auto; font-family: monospace; white-space: pre-wrap;"><code><?php echo htmlspecialchars($file_content); ?></code></pre>
                                </div>
                            </div>
                        </div>

<?php ob_start(); ?>
                        <!-- [모달] 언어별 항목 수정 -->
                        <div class="modal fade" id="editModal_<?php echo $file_lang; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <form method="POST" action="">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i><?php echo strtoupper($file_lang); ?> 사전 파일 직접 수정</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-light">
                                            <input type="hidden" name="action" value="edit_lang_file">
                                            <input type="hidden" name="edit_lang" value="<?php echo $file_lang; ?>">
                                            <input type="hidden" name="api_key" class="sync_api_key" value="<?php echo htmlspecialchars($api_key); ?>">
                                            <?php if (!empty($lang_dict) && is_array($lang_dict)): ?>
                                                <!-- [추가] 항목 검색 필드 -->
                                                <div class="mb-3 position-sticky top-0 bg-light py-2" style="z-index: 10;">
                                                    <div class="input-group input-group-sm shadow-sm">
                                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                                        <input type="text" class="form-control border-start-0 ps-0" placeholder="기준 항목 (Key) 또는 번역 내용 (Value) 검색..." oninput="filterLangItems(this, '<?php echo $file_lang; ?>')">
                                                    </div>
                                                </div>
                                                <div id="lang-items-container-<?php echo $file_lang; ?>">
                                                    <?php foreach ($lang_dict as $key => $val): ?>
                                                        <div class="mb-3 p-3 bg-white border rounded shadow-sm lang-item-row">
                                                            <label class="form-label small text-muted fw-bold mb-1">기준 항목 (Key)</label>
                                                            <input type="text" name="lang_keys[]" class="form-control form-control-sm bg-light mb-2 text-dark lang-key-input" value="<?php echo htmlspecialchars($key); ?>" readonly>
                                                            <label class="form-label small text-primary fw-bold mb-1">번역 내용 (Value)</label>
                                                            <textarea name="lang_values[]" class="form-control form-control-sm lang-value-input" rows="2" required><?php echo htmlspecialchars($val); ?></textarea>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted py-5">파일이 없거나 항목이 비어있습니다.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer border-0 p-3 bg-white justify-content-end shadow-sm">
                                            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold border shadow-sm" data-bs-dismiss="modal">취소</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="bi bi-save me-1"></i>변경사항 저장</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
<?php $modals_html .= ob_get_clean(); ?>
<?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- [신규 추가] 상점 메뉴/매물 다국어 일괄 번역 -->
        <div class="card shadow-sm border-0 rounded-4 mx-auto mt-4 mb-5 fade-in-up" style="max-width: 800px; animation-delay: 0.3s;">
            <div class="card-header bg-info text-dark p-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-translate me-2"></i>상점 메뉴/매물 다국어 일괄 자동 번역</h5>
            </div>
            <div class="card-body p-4 bg-white rounded-bottom-4">
                <div class="alert alert-light border shadow-sm mb-4 small text-muted">
                    <i class="bi bi-info-circle-fill me-1 text-info"></i>특정 상점의 ID를 입력하면, 해당 상점에 등록된 모든 메뉴(또는 매물)의 <strong>이름과 상세 설명</strong>을 상점의 다국어 설정(외국어1, 외국어2)에 맞춰 구글 API로 자동 번역하여 DB에 업데이트합니다.<br>
                    <span class="text-danger">* 이미 번역된 내용이 있는 항목은 건너뛰고 빈 곳만 채웁니다.</span>
                </div>

                <?php if (isset($results['shop_trans'])): ?>
                    <div class="alert alert-<?php echo $results['shop_trans']['status']; ?> shadow-sm">
                        <?php echo $results['shop_trans']['msg']; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="translate_shop_items">
                    <input type="hidden" name="api_key" class="sync_api_key" value="<?php echo htmlspecialchars($api_key); ?>">

                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-light fw-bold fs-6">상점 ID</span>
                        <input type="number" name="shop_id" class="form-control" required placeholder="예: 15">
                        <button type="submit" class="btn btn-info fw-bold text-dark px-4 fs-6">아이템 번역 시작</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php echo $modals_html; ?>

    <!-- 아코디언 기능 동작을 위한 Bootstrap 스크립트 추가 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 폼 전송 시 지연으로 인한 먹통 현상 방지용 UI 피드백
        document.addEventListener('DOMContentLoaded', function() {
            const mainKeyInput = document.getElementById('api_key_main');
            const savedKey = localStorage.getItem('tester_google_translate_key');
            if (savedKey && mainKeyInput && !mainKeyInput.value) {
                mainKeyInput.value = savedKey;
            }

            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (mainKeyInput && mainKeyInput.value) {
                        localStorage.setItem('tester_google_translate_key', mainKeyInput.value);
                        document.querySelectorAll('.sync_api_key').forEach(input => input.value = mainKeyInput.value);
                    }
                    const submitter = e.submitter;
                    if (submitter) {
                        const hiddenAction = document.createElement('input');
                        hiddenAction.type = 'hidden';
                        hiddenAction.name = submitter.name;
                        hiddenAction.value = submitter.value;
                        this.appendChild(hiddenAction);

                        submitter.disabled = true;
                        submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> 처리 중...';
                    }
                });
            });
        });

        // [추가] 모달 내 항목 검색(필터링) 기능
        function filterLangItems(inputEl, langCode) {
            const keyword = inputEl.value.toLowerCase();
            const container = document.getElementById('lang-items-container-' + langCode);
            if (!container) return;

            const items = container.querySelectorAll('.lang-item-row');
            items.forEach(item => {
                const keyInput = item.querySelector('.lang-key-input');
                const valInput = item.querySelector('.lang-value-input');

                const keyText = keyInput ? keyInput.value.toLowerCase() : '';
                const valText = valInput ? valInput.value.toLowerCase() : '';

                if (keyText.includes(keyword) || valText.includes(keyword)) {
                    item.style.display = '';
                    setTimeout(() => { item.style.opacity = '1'; item.style.transform = 'scale(1)'; }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.98)';
                    setTimeout(() => { if (item.style.opacity === '0') item.style.display = 'none'; }, 300);
                }
            });
        }

        // [추가] 모달이 열릴 때 최신 데이터를 서버에서 다시 읽어오도록 이벤트 리스너 추가
        document.addEventListener('show.bs.modal', async function(event) {
            const modal = event.target;
            if (modal.id.startsWith('editModal_')) {
                const langCode = modal.id.replace('editModal_', '');
                const container = document.getElementById('lang-items-container-' + langCode);

                if (container) {
                    // 로딩 표시
                    container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> 파일의 최신 내용을 읽어오는 중...</div>';

                    const formData = new FormData();
                    formData.append('action', 'get_lang_file');
                    formData.append('get_lang', langCode);

                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.status === 'success') {
                            let html = '';
                            const langDict = result.data;
                            if (Object.keys(langDict).length > 0) {
                                for (const key in langDict) {
                                    const val = langDict[key];
                                    const safeKey = key.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
                                    const safeVal = val.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
                                    html += `
                                        <div class="mb-3 p-3 bg-white border rounded shadow-sm lang-item-row">
                                            <label class="form-label small text-muted fw-bold mb-1">기준 항목 (Key)</label>
                                            <input type="text" name="lang_keys[]" class="form-control form-control-sm bg-light mb-2 text-dark lang-key-input" value="${safeKey}" readonly>
                                            <label class="form-label small text-primary fw-bold mb-1">번역 내용 (Value)</label>
                                            <textarea name="lang_values[]" class="form-control form-control-sm lang-value-input" rows="2" required>${safeVal}</textarea>
                                        </div>
                                    `;
                                }
                            } else {
                                html = '<div class="text-center text-muted py-5">파일이 없거나 항목이 비어있습니다.</div>';
                            }
                            container.innerHTML = html;

                            // 검색창 초기화
                            const searchInput = modal.querySelector('input[type="text"][oninput]');
                            if (searchInput) searchInput.value = '';
                        } else {
                            container.innerHTML = `<div class="text-center text-danger py-5">${result.message}</div>`;
                        }
                    } catch (e) {
                        container.innerHTML = '<div class="text-center text-danger py-5">통신 중 오류가 발생했습니다.</div>';
                    }
                }
            }
        });
    </script>
</body>

>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</html>