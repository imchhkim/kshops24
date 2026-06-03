<?php

require_once __DIR__ . '/t_common.php';

echo <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상수/함수 중복 검사기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light pb-5">
<div class="container mt-5">
    <div class="card shadow-sm mb-4 border-start border-5 border-primary">
        <div class="card-body p-4">
            <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-braces me-2"></i>상수/함수 중복 검사 (t_find_const_funcs.php)</h4>
            <p class="card-text text-muted small">프로젝트 전체 PHP 파일에서 <code>define()</code>으로 선언된 <strong>상수(Constants)</strong>와 전역 공간에 <code>function</code>으로 선언된 <strong>함수(Functions)</strong> 중, 이름이 중복된 항목을 찾아 위치와 함께 보여줍니다. 치명적인 재정의(Fatal Error) 오류를 사전에 완벽하게 방지할 수 있습니다.</p>
        </div>
    </div>
HTML;

$root_dir = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root_dir));
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$constants = [];
$functions = [];

foreach ($regex as $file) {
    $filepath = $file[0];
    // 불필요한 폴더(vendor 등) 제외
    if (strpos($filepath, '/vendor/') !== false) continue;

    $content = file_get_contents($filepath);

    $tokens = token_get_all($content);
    $total = count($tokens);

    // 클래스/인터페이스 등 객체 지향 블록 내부의 멤버 함수(Method)를 걸러내기 위한 스코프 추적
    $brace_depth = 0;
    $class_stack = [];
    $waiting_for_class_brace = 0;

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];
        
        if (is_array($token)) {
            $type = $token[0];
            
            // T_CLASS, T_TRAIT 등 클래스 선언 블록 감지 (Something::class 형태는 제외)
            if ($type === T_CLASS || $type === T_TRAIT || $type === T_INTERFACE || (defined('T_ENUM') && $type === T_ENUM)) {
                $pi = $i - 1;
                while ($pi >= 0 && is_array($tokens[$pi]) && $tokens[$pi][0] === T_WHITESPACE) $pi--;
                if (!($pi >= 0 && is_array($tokens[$pi]) && $tokens[$pi][0] === T_DOUBLE_COLON)) {
                    $waiting_for_class_brace++;
                }
            } elseif ($type === T_CURLY_OPEN || $type === T_DOLLAR_OPEN_CURLY_BRACES) {
                $brace_depth++;
            } elseif ($type === T_STRING && strtolower($token[1]) === 'define') {
                // 상수(Constant) 스캔 로직
                $j = $i + 1;
                while ($j < $total && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;

                if ($j < $total && !is_array($tokens[$j]) && $tokens[$j] === '(') {
                    $k = $j + 1;
                    while ($k < $total && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;

                    if ($k < $total && is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $const_name = trim($tokens[$k][1], "'\"");
                        $short_path = str_replace($root_dir, '', $filepath);
                        $line_num = $token[2];
                        $constants[$const_name][] = ['path' => $short_path, 'line' => $line_num];
                    }
                }
            } elseif ($type === T_FUNCTION) {
                // 함수(Function) 스캔 로직: $class_stack이 비어있을 때만 전역 함수로 인정
                if (empty($class_stack)) {
                    $j = $i + 1;
                    while ($j < $total && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
                    if ($j < $total && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $func_name = $tokens[$j][1];
                        $short_path = str_replace($root_dir, '', $filepath);
                        $line_num = $token[2];
                        $functions[$func_name][] = ['path' => $short_path, 'line' => $line_num];
                    }
                }
            }
        } else {
            // 중괄호 { } 기호 기반 깊이 파악
            if ($token === '{') {
                $brace_depth++;
                while ($waiting_for_class_brace > 0) {
                    $class_stack[] = $brace_depth;
                    $waiting_for_class_brace--;
                }
            } elseif ($token === '}') {
                if (!empty($class_stack) && end($class_stack) === $brace_depth) {
                    array_pop($class_stack);
                }
                $brace_depth--;
            }
        }
    }
}

echo '<h5 class="fw-bold mt-4 mb-3"><i class="bi bi-tags-fill me-2 text-primary"></i>중복 상수 (Constants) 스캔 결과</h5>';
echo '<div class="card border-0 shadow-sm mb-4"><div class="list-group list-group-flush">';

$has_duplicates = false;

foreach ($constants as $name => $info_list) {
    // 같은 파일, 같은 라인에서 중복 스캔된 경우 제거
    $unique_info = [];
    $seen = [];
    foreach ($info_list as $info) {
        $key = $info['path'] . ':' . $info['line'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique_info[] = $info;
        }
    }

    if (count($unique_info) > 1) {
        $has_duplicates = true;
        echo '<div class="list-group-item p-4">';
        echo "<h5 class='fw-bold text-danger mb-3'><i class='bi bi-exclamation-triangle-fill me-2'></i>[중복 발견] {$name}</h5>\n";
        echo "<ul class='mb-0 font-monospace' style='list-style-type: none; padding-left: 0;'>\n";
        foreach ($unique_info as $info) {
            echo "<li class='mb-2 pb-2 border-bottom'>👉 위치: {$info['path']} <span class='badge bg-secondary ms-2'>Line: {$info['line']}</span></li>\n";
        }
        echo "</ul></div>\n";
    }
}

if (!$has_duplicates) {
    echo '<div class="p-5 text-center"><i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i><h5 class="fw-bold text-success">안전합니다!</h5><p class="text-muted mb-0">프로젝트 내에 중복 정의된 상수가 없습니다.</p></div>';
}
echo "</div></div>";

// ========================================================
// [함수 출력 영역]
// ========================================================
echo '<h5 class="fw-bold mt-4 mb-3"><i class="bi bi-code-slash me-2 text-primary"></i>중복 함수 (Functions) 스캔 결과</h5>';
echo '<div class="card border-0 shadow-sm mb-4"><div class="list-group list-group-flush">';

$has_dup_funcs = false;

foreach ($functions as $name => $info_list) {
    $unique_info = [];
    $seen = [];
    foreach ($info_list as $info) {
        $key = $info['path'] . ':' . $info['line'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique_info[] = $info;
        }
    }

    if (count($unique_info) > 1) {
        $has_dup_funcs = true;
        echo '<div class="list-group-item p-4">';
        echo "<h5 class='fw-bold text-danger mb-3'><i class='bi bi-exclamation-triangle-fill me-2'></i>[중복 함수] {$name}()</h5>\n";
        echo "<ul class='mb-0 font-monospace' style='list-style-type: none; padding-left: 0;'>\n";
        foreach ($unique_info as $info) {
            echo "<li class='mb-2 pb-2 border-bottom'>👉 위치: {$info['path']} <span class='badge bg-secondary ms-2'>Line: {$info['line']}</span></li>\n";
        }
        echo "</ul></div>\n";
    }
}

if (!$has_dup_funcs) {
    echo '<div class="p-5 text-center"><i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i><h5 class="fw-bold text-success">안전합니다!</h5><p class="text-muted mb-0">프로젝트 내에 중복 정의된 전역 함수가 없습니다.</p></div>';
}
echo "</div></div>";

echo "</div></body></html>";
