<?php

/**
 * KShops24 공통 텔레그램 알림 발송 함수
 * @param string $message 전송할 메시지 (HTML 태그 지원)
 * @param string $target_chat_id 수신자 ID (기본값은 찰리님 ID)
 */
function send_ps24_telegram($message, $target_chat_id = PS24_BOT_CHAT_ID)
{
    // 찰리님이 정의하신 상수 사용
    $url = "https://api.telegram.org/bot" . PS24_BOT_TOKEN . "/sendMessage";

    $post_data = [
        'chat_id' => $target_chat_id,
        'text' => $message,
        'parse_mode' => 'HTML', // <b>, <i>, <code> 등 사용 가능
        'disable_web_page_preview' => false // 링크 포함 시 미리보기 허용 여부
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 2초는 타임아웃이 잦으므로 5초로 연장
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 호스팅 환경 고려

    $result = curl_exec($ch);

    if ($result === false) {
        $error_msg = curl_error($ch);
        $result = json_encode(['ok' => false, 'description' => '서버 통신 지연 또는 에러 (cURL Error: ' . $error_msg . ')']);
    }
    curl_close($ch);

    return $result;
}

// 사용 예시: 주문이 들어왔을 때
// send_ps24_telegram("<b>[신규 주문]</b> 찰리식당에 새로운 주문이 접수되었습니다!");


/**
 * 방문자 카운트 및 로그 기록 함수
 * @param PDO $pdo
 * @param int $shop_id 상점 ID (0은 포털 메인)
 */
function recordVisitor($pdo, $shop_id = 0)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $customer_id = $_SESSION['customer_id'] ?? null;
    $today = date('Y-m-d');

    try {
        // 1. 상세 로그 기록 (visit_logs)
        $sql_log = "INSERT INTO visit_logs (shop_id, customer_id, ip_address, user_agent, referer, visit_path) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql_log)->execute([$shop_id, $customer_id, $ip, $ua, $ref, $path]);

        // 2. 일별 통계 업데이트 (visit_stats)
        // ON DUPLICATE KEY UPDATE를 사용하여 오늘 처음 방문이면 INSERT, 아니면 카운트만 증가
        // 순 방문자(Unique) 판별: 오늘 이 IP로 이 상점에 들어온 기록이 방금 넣은 것 하나뿐인지 확인
        $check_unique_sql = "SELECT COUNT(*) FROM visit_logs WHERE shop_id = ? AND ip_address = ? AND created_at >= CURDATE()";
        $stmt_check = $pdo->prepare($check_unique_sql);
        $stmt_check->execute([$shop_id, $ip]);
        $is_unique = ($stmt_check->fetchColumn() <= 1) ? 1 : 0;

        $sql_stats = "INSERT INTO visit_stats (visit_date, shop_id, page_views, unique_visitors) 
                      VALUES (?, ?, 1, ?) 
                      ON DUPLICATE KEY UPDATE 
                      page_views = page_views + 1, 
                      unique_visitors = unique_visitors + ?";
        $pdo->prepare($sql_stats)->execute([$today, $shop_id, $is_unique, $is_unique]);
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "방문자 통계 기록 에러", $e->getMessage());
    }
}

/**
 * 필리핀 전화번호 포맷팅 (PHP 출력용)
 * @param string $num 숫자만 있거나 하이픈이 섞인 번호
 * @return string 포맷팅된 번호
 */
function formatPHPhone($num)
{
    $num = preg_replace('/[^0-9]/', '', $num ?? ''); // 숫자만 남기기
    if (!$num) return '';

    // [최적화 1] 3개로 중복되던 지역번호 규칙 분기를 하나의 동적 로직으로 압축
    $p_len = str_starts_with($num, '02') ? 2 : (str_starts_with($num, '09') ? 4 : 3);
    
    if (strlen($num) <= $p_len) return $num;

    $mid_len = str_starts_with($num, '09') ? 3 : 4;
    
    $part1 = substr($num, 0, $p_len);
    $part2 = substr($num, $p_len, $mid_len);
    $part3 = substr($num, $p_len + $mid_len);

    return rtrim("{$part1}-{$part2}" . ($part3 ? "-{$part3}" : ''), '-');
}

/**
 * [신규] 상점 히스토리 로그 기록 함수
 * 상점에서 일어나는 모든 이벤트(상태변경, 이메일, 메시지, 결제 등)를 shops 테이블의 history_log JSON 컬럼에 통합 저장합니다.
 * @param PDO $pdo
 * @param int $shop_id 대상 상점 ID
 * @param string $type 이벤트 유형 (예: SHOP_HISTORY_EMAIL)
 * @param string $title 제목 또는 간단한 요약
 * @param string $content 상세 내용
 * @param string|null $date 발생일시 (기본값: 현재 시간)
 * @return bool 성공 여부
 */
function addShopHistoryLog($pdo, $shop_id, $type, $title, $content = '', $date = null)
{
    $date = $date ?: date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT history_log FROM shops WHERE id = ?");
    $stmt->execute([$shop_id]);
    // [최적화 2] 3줄의 빈 배열 검증을 Elvis 연산자(?:)를 활용해 1줄로 단축
    $history_array = json_decode($stmt->fetchColumn() ?: '[]', true) ?: [];
    $history_array[] = ['type' => $type, 'title' => $title, 'content' => $content, 'date' => $date];
    return $pdo->prepare("UPDATE shops SET history_log = ? WHERE id = ?")->execute([json_encode($history_array, JSON_UNESCAPED_UNICODE), $shop_id]);
}

/**
 * 상점 결제 내역 통합 등록 함수
 */
function recordShopPayment($pdo, $shop_id, $pay_type, $amount, $note, $paid = 'n', $billing_date = null, $expiring_date = null)
{
    try {
        $sql = "INSERT INTO shop_payments (shop_id, pay_type, amount, billing_date, expiring_date, note, paid) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([
            $shop_id,
            $pay_type,
            (int) filter_var($amount, FILTER_SANITIZE_NUMBER_INT), // 문자가 섞여있어도 숫자만 추출
            $billing_date,
            $expiring_date,
            $note,
            $paid
        ]);
        if ($res) {
            addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_BILLING, "비용 청구/수납", "유형: {$pay_type} / 금액: {$amount} / 비고: {$note}");
        }
        return $res;
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "결제 내역 등록 에러", $e->getMessage());
        return false;
    }
}

/**
 * [신규] 상점별 리소스 사용량 (디스크, DB) 측정 함수
 * @param PDO $pdo
 * @param int $shop_id 대상 상점 ID
 * @return array ['disk' => (int) 디스크 사용량 바이트, 'db' => (int) DB 사용량 바이트]
 */
function getShopResourceUsage($pdo, $shop_id)
{
    $disk_usage = 0;
    $db_usage = 0;
    $db_details = [];

    // 1. 디스크 사용량 측정
    try {
        $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $subdomain = $stmt->fetchColumn();

        if ($subdomain) {
            $shop_dir = SHOP_UPLOADS_DIR . "/" . $subdomain;
            if (is_dir($shop_dir)) {
                $disk_usage = getDirectorySize($shop_dir);
            }
        }
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "디스크 용량 계산 에러 (상점 ID: {$shop_id})", $e->getMessage());
    }

    // 2. DB 사용량 측정 (추정치)
    // - AVG_ROW_LENGTH * 레코드 수 방식으로 계산
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName) {
            // shop_id를 가지는 주요 테이블 목록
            $related_tables = [
                'shop_board',
                // [버그 수정] 존재하지 않는 shop_customers 테이블명 수정 (상점별 고객 매핑 테이블 지정)
                'shop_customer_mapping',
                'shop_images',
                'shop_item_boards',
                'shop_item_categories',
                'shop_items',
                'shop_payments',
                'reviews',
                'visit_logs',
                'visit_stats'
            ];

            $total_db_size = 0;

            // 'shops' 테이블의 해당 레코드 크기 먼저 추가
            $stmt_main = $pdo->prepare("SELECT AVG_ROW_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shops'");
            $stmt_main->execute([$dbName]);
            $avg_row_len = (int) $stmt_main->fetchColumn();
            $total_db_size += $avg_row_len;
            $db_details[] = ['table' => 'shops', 'rows' => 1, 'size' => $avg_row_len];

            foreach ($related_tables as $table) {
                try {
                    // 테이블의 평균 행 크기 가져오기
                    $stmt_avg = $pdo->prepare("SELECT AVG_ROW_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
                    $stmt_avg->execute([$dbName, $table]);
                    $avg_row_len = (int) $stmt_avg->fetchColumn();

                    // 해당 상점의 레코드 수 가져오기
                    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE shop_id = ?");
                    $stmt_count->execute([$shop_id]);
                    $row_count = (int) $stmt_count->fetchColumn();

                    $table_size = $avg_row_len * $row_count;
                    $total_db_size += $table_size;

                    if ($row_count > 0) {
                        $db_details[] = ['table' => $table, 'rows' => $row_count, 'size' => $table_size];
                    }
                } catch (Exception $e) {
                    // 해당 테이블이 아직 존재하지 않는 경우 무시하고 다음 진행
                }
            }
            $db_usage = $total_db_size;
        }
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "DB 용량 계산 에러 (상점 ID: {$shop_id})", $e->getMessage());
    }

    return ['disk' => $disk_usage, 'db' => $db_usage, 'db_details' => $db_details];
}

/**
 * [신규] 특정 디렉토리의 전체 크기를 바이트 단위로 반환하는 함수 (재귀)
 * @param string $path 디렉토리 경로
 * @return int 크기 (바이트)
 */
function getDirectorySize($path)
{
    if (!is_dir($path)) {
        return 0;
    }
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        try {
            $bytes += $file->getSize();
        } catch (Exception $e) {
            // 파일 접근 불가 등 예외 처리
        }
    }
    return $bytes;
}

/**
 * [신규] 바이트를 사람이 읽기 쉬운 형식(KB, MB, GB)으로 변환하는 함수
 * @param int $bytes
 * @param int $precision 소수점 자릿수
 * @return string
 */
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * [신규] 상점 디스크 데이터 무결성 분석 함수
 * - DB에 기록되지 않은 파일 (Orphaned Files)
 * - DB에 기록은 있으나 실제 파일이 없는 경우 (Broken Links)
 * @param PDO $pdo
 * @param int $shop_id
 * @return array ['orphaned_files' => [], 'broken_links' => []]
 */
function analyzeShopDiskIntegrity($pdo, $shop_id)
{
    $orphaned_files = [];
    $broken_links = [];

    // 1. 상점 서브도메인 확보
    $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
    $stmt->execute([$shop_id]);
    $subdomain = $stmt->fetchColumn();
    if (!$subdomain) {
        return ['orphaned_files' => [], 'broken_links' => []];
    }

    // 2. DB에 기록된 모든 이미지 경로 수집 및 테이블 매핑
    $db_image_map = [];
    $image_columns = [
        'shops' => ['logo_path', 'bg_path'],
        'shop_images' => ['img_path'],
        'shop_items' => ['item_img'],
        'shop_item_boards' => ['board_img_path'],
        'reviews' => ['img_path']
    ];

    foreach ($image_columns as $table => $columns) {
        $id_column = ($table === 'shops') ? 'id' : 'shop_id';
        foreach ($columns as $column) {
            try {
                $sql = "SELECT {$column} FROM {$table} WHERE {$id_column} = ? AND {$column} IS NOT NULL AND {$column} != ''";
                $stmt_path = $pdo->prepare($sql);
                $stmt_path->execute([$shop_id]);
                while ($path = $stmt_path->fetchColumn()) {
                    // JSON 배열 형식의 경로 처리
                    if (strpos($path, '[') === 0) {
                        $decoded = json_decode($path, true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $p) {
                                if (!empty($p))
                                    $db_image_map[$p] = $table;
                            }
                        }
                    } else {
                        if (!empty($path))
                            $db_image_map[$path] = $table;
                    }
                }
            } catch (Exception $e) {
                // 해당 테이블이나 컬럼이 아직 존재하지 않는 경우 무시하고 다음 진행
            }
        }
    }
    $db_image_paths = array_keys($db_image_map);

    // 3. 디스크에 저장된 모든 이미지 파일 스캔
    $disk_image_files = [];
    $shop_dir = SHOP_UPLOADS_DIR . "/" . $subdomain;
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (is_dir($shop_dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($shop_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isDir() || !in_array(strtolower($file->getExtension()), $image_extensions))
                continue;
            $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname());
            $disk_image_files[] = str_replace('\\', '/', $relative_path); // 경로 구분자 정규화
        }
    }

    // 4. 비교: DB에 없는 파일(Orphaned) 찾기
    $orphaned_relative_paths = array_diff($disk_image_files, $db_image_paths);
    foreach ($orphaned_relative_paths as $path) {
        // [수정] 자동 생성된 썸네일 이미지(thumb_...)인 경우, 원본 파일이 DB 목록에 존재하면 고아 파일에서 제외
        $filename = basename($path);
        if (strpos($filename, 'thumb_') === 0) {
            $original_path = dirname($path) . '/' . substr($filename, 6);
            if (in_array($original_path, $db_image_paths)) {
                continue; // 원본이 DB에 정상 등록되어 있으므로 이 썸네일은 삭제 목록에서 안전하게 제외함
            }
        }

        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($full_path))
            $orphaned_files[] = ['path' => $path, 'size_formatted' => formatBytes(filesize($full_path))];
    }

    // 5. 비교: 파일이 없는 DB 기록(Broken) 찾기
    foreach ($db_image_paths as $path) {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $path))
            $broken_links[] = ['path' => $path, 'table' => $db_image_map[$path]];
    }

    return [
        'orphaned_files' => $orphaned_files,
        'broken_links' => $broken_links,
        'checked_tables' => array_keys($image_columns)
    ];
}

/**
 * [신규] 전체 시스템 상점 디스크 무결성 일괄 분석 함수
 * - 모든 상점을 대상으로 분석을 수행하고, DB에 등록되지 않은 잉여 폴더(상점 삭제 후 남은 찌꺼기 등)까지 스캔합니다.
 * @param PDO $pdo
 * @return array
 */
function analyzeSystemDiskIntegrity($pdo)
{
    $total_orphaned_files = [];
    $total_broken_links = [];
    $orphaned_directories = [];
    $total_checked_tables = [];

    // 1. 등록된 모든 상점 목록 가져오기
    $stmt = $pdo->query("SELECT id, subdomain, shop_name FROM shops");
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $valid_subdomains = [];

    foreach ($shops as $shop) {
        if (!empty($shop['subdomain'])) {
            $valid_subdomains[] = strtolower($shop['subdomain']);

            // 기존 개별 상점 분석 함수 재활용
            $result = analyzeShopDiskIntegrity($pdo, $shop['id']);

            // [확장성 대비] 검사된 테이블 목록을 누적 수집하여 중복 제거
            if (isset($result['checked_tables'])) {
                $total_checked_tables = array_unique(array_merge($total_checked_tables, $result['checked_tables']));
            }

            if (!empty($result['orphaned_files'])) {
                foreach ($result['orphaned_files'] as $file) {
                    $file['shop_name'] = $shop['shop_name'];
                    $total_orphaned_files[] = $file;
                }
            }
            if (!empty($result['broken_links'])) {
                foreach ($result['broken_links'] as $link) {
                    $link['shop_name'] = $shop['shop_name'];
                    $total_broken_links[] = $link;
                }
            }
        }
    }

    // 2. DB에 없는 잉여 폴더(Orphaned Directories) 찾기
    $base_dir = SHOP_UPLOADS_DIR;
    if (is_dir($base_dir)) {
        $iterator = new DirectoryIterator($base_dir);
        foreach ($iterator as $dirinfo) {
            if ($dirinfo->isDir() && !$dirinfo->isDot()) {
                $dir_name = strtolower($dirinfo->getFilename());
                if (!in_array($dir_name, $valid_subdomains)) {
                    $orphaned_directories[] = [
                        'path' => SHOP_UPLOADS_URL . $dirinfo->getFilename(),
                        'size_formatted' => formatBytes(getDirectorySize($dirinfo->getPathname()))
                    ];
                }
            }
        }
    }

    return [
        'orphaned_files' => $total_orphaned_files,
        'broken_links' => $total_broken_links,
        'orphaned_directories' => $orphaned_directories,
        'checked_tables' => array_values($total_checked_tables)
    ];
}

/**
 * [신규] 디렉토리 및 하위 파일을 완전히 삭제하는 유틸리티 함수
 * @param string $dir 삭제할 디렉토리 경로
 * @return bool 성공 여부
 */
function deleteDirectoryCompletely($dir)
{
    if (!file_exists($dir))
        return true;
    if (!is_dir($dir))
        return @unlink($dir);

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..')
            continue;
        if (!deleteDirectoryCompletely($dir . DIRECTORY_SEPARATOR . $item))
            return false;
    }
    return @rmdir($dir);
}

/**
 * [신규] 6개월 사용료 청구 내역 자동 생성 함수
 * @param PDO $pdo
 * @param int $shop_id 대상 상점 ID
 * @param string $start_date_str 청구 시작일 (Y-m-d 형식)
 * @return bool 성공 여부
 */
function add6MonthBill($pdo, $shop_id, $start_date_str)
{
    try {
        // 1. 월 사용료 설정값 가져오기
        $stmt_settings = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'monthly_fee'");
        $calc_monthly_fee = (int) ($stmt_settings->fetchColumn() ?: 0);

        if ($calc_monthly_fee <= 0) {
            // 월 사용료가 설정되지 않았으면 청구 생성 안 함
            return true;
        }

        // 2. 청구 기간 및 만료일 계산
        $start_date = new DateTime($start_date_str);
        $end_date = (clone $start_date)->modify('+6 months -1 day');

        // 3. 비고(note) 문자열 생성
        $note = "6개월 사용료 청구 (" . $start_date->format('Y.m.d') . " ~ " . $end_date->format('Y.m.d') . ")";

        // 4. 기존 recordShopPayment 함수를 사용하여 결제 내역 기록
        return recordShopPayment(
            $pdo,
            $shop_id,
            PAY_TYPE_6MONTHS,
            $calc_monthly_fee * 6,
            $note,
            'n', // '미납' 상태로 생성
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d')
        );
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "6개월 자동 청구서 생성 에러 (상점 ID: {$shop_id})", $e->getMessage());
        return false;
    }
}

/**
 * 상점 정보 중복 여부 확인 함수
 * @param PDO $pdo
 * @param string $field 검색할 컬럼명
 * @param string $value 검색할 값
 * @param int|null $exclude_id 제외할 상점 ID (수정 시 본인 제외용)
 * @return bool 중복 여부 (true: 중복됨, false: 사용 가능)
 */
function isDuplicateShopField($pdo, $field, $value, $exclude_id = null)
{
    // 보안을 위해 필드명에 영문 소문자와 언더바만 허용하는 최소한의 필터링
    if (!preg_match('/^[a-z_]+$/', $field))
        return false;
    if (trim($value) === '')
        return false; // 빈 값은 중복 체크에서 제외
    try {
        // status가 'closed'(폐점)인 상점은 중복 체크 대상에서 제외하여, 해당 정보(아이디, 이메일 등)의 재사용을 허용합니다.
        $sql = "SELECT id FROM shops WHERE LOWER($field) = LOWER(?) AND status != " . $pdo->quote(SHOP_STATUS_CLOSED);
        $params = [$value];
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * [신규] 특정 상점의 전월 리소스 사용량을 분석하고 초과 요금을 청구하는 함수
 * @param PDO $pdo
 * @param int $shop_id 대상 상점 ID
 * @param string $target_month 대상 월 (YYYY-MM 형식, 기본값은 지난달)
 * @return array 처리 결과 배열 ['success' => bool, 'billed_amount' => int, 'message' => string]
 */
function processMonthlyOverageBilling($pdo, $shop_id, $target_month = null)
{
    if (!$target_month) {
        $target_month = date('Y-m', strtotime('first day of last month'));
    }

    try {
        // 1. 요금 정책(Tier) 가져오기
        $stmt_policy = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'billing_tier_policy'");
        $stmt_policy->execute();
        $policy_json = $stmt_policy->fetchColumn();

        // 기본 정책 설정 (정책이 없으면 무료로 간주)
        $policy = $policy_json ? json_decode($policy_json, true) : [
            'free_orders' => 300,        // 기본 제공 주문 건수
            'overage_per_order' => 5,    // 초과 1건당 청구액
            'free_disk_mb' => 300,      // 기본 제공 디스크(MB)
            'overage_disk_unit_mb' => 100, // 단위 용량(MB)
            'overage_disk_fee' => 100,   // 단위당 청구액
            'free_db_mb' => 50,         // 기본 제공 DB 용량(MB)
            'overage_db_unit_mb' => 10, // DB 단위 용량(MB)
            'overage_db_fee' => 50      // DB 단위당 청구액
        ];

        // 하위 호환성 (기존 overage_per_gb 값 승계)
        $unit_mb = (int) ($policy['overage_disk_unit_mb'] ?? 1024) ?: 1024; // 0으로 나누기 방지
        $disk_fee_per_unit = (int) ($policy['overage_disk_fee'] ?? ($policy['overage_per_gb'] ?? 100));

        $db_unit_mb = (int) ($policy['overage_db_unit_mb'] ?? 10) ?: 10;
        $db_fee_per_unit = (int) ($policy['overage_db_fee'] ?? 50);

        // [수정] 상점별 개별(커스텀) 무료 한도 확인 (슈퍼관리자가 부여한 혜택)
        $stmt_shop = $pdo->prepare("SELECT custom_free_orders, custom_free_disk_mb, custom_free_db_mb FROM shops WHERE id = ?");
        $stmt_shop->execute([$shop_id]);
        $shop_custom = $stmt_shop->fetch();
        $free_orders_limit = $shop_custom['custom_free_orders'] !== null ? (int) $shop_custom['custom_free_orders'] : $policy['free_orders'];
        $free_disk_limit_mb = $shop_custom['custom_free_disk_mb'] !== null ? (int) $shop_custom['custom_free_disk_mb'] : $policy['free_disk_mb'];
        $free_db_limit_mb = isset($shop_custom['custom_free_db_mb']) && $shop_custom['custom_free_db_mb'] !== null ? (int) $shop_custom['custom_free_db_mb'] : ($policy['free_db_mb'] ?? 50);

        $total_overage_fee = 0;
        $billing_notes = [];

        // 대상 월의 시작일과 종료일 계산 (인덱스 활용 및 Collation 충돌 방지)
        $start_date = $target_month . '-01 00:00:00';
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date));

        // 2. 해당 월의 성공한 F&B 주문 건수 집계
        $stmt_orders = $pdo->prepare("
            SELECT COUNT(*) FROM shop_orders 
            WHERE shop_id = ? 
            AND created_at >= ? AND created_at <= ? 
            AND status IN ('confirmed', 'cooking', 'delivery', 'completed')
        ");
        $stmt_orders->execute([$shop_id, $start_date, $end_date]);
        $order_count = (int) $stmt_orders->fetchColumn();

        // 주문 초과 요금 계산
        if ($order_count > $free_orders_limit) {
            $excess_orders = $order_count - $free_orders_limit;
            $order_fee = $excess_orders * $policy['overage_per_order'];
            $total_overage_fee += $order_fee;
            $billing_notes[] = "주문건수 초과: {$excess_orders}건 (+{$order_fee} 원)";
        }

        // 3. 리소스(디스크, DB) 사용량 집계 (getShopResourceUsage 함수 재활용)
        $resources = getShopResourceUsage($pdo, $shop_id);
        $disk_mb = $resources['disk'] / 1048576; // Byte to MB
        $db_mb = $resources['db'] / 1048576; // Byte to MB

        // 디스크 초과 요금 계산
        if ($disk_mb > $free_disk_limit_mb) {
            $excess_mb = $disk_mb - $free_disk_limit_mb;
            $excess_units = ceil($excess_mb / $unit_mb); // 올림 처리하여 설정한 단위로 부과
            $disk_fee = $excess_units * $disk_fee_per_unit;
            $total_overage_fee += $disk_fee;
            $billing_notes[] = "디스크 용량 초과: " . round($excess_mb, 2) . "MB (+{$disk_fee} 원)";
        }

        // DB 초과 요금 계산
        if ($db_mb > $free_db_limit_mb) {
            $excess_db_mb = $db_mb - $free_db_limit_mb;
            $excess_db_units = ceil($excess_db_mb / $db_unit_mb);
            $db_fee = $excess_db_units * $db_fee_per_unit;
            $total_overage_fee += $db_fee;
            $billing_notes[] = "DB 용량 초과: " . round($excess_db_mb, 2) . "MB (+{$db_fee} 원)";
        }

        // 4. 초과 요금이 발생했다면 shop_payments 에 청구 데이터 삽입
        if ($total_overage_fee > 0) {
            $note_str = "[{$target_month} 리소스 초과 사용료]\n" . implode("\n", $billing_notes);

            // 기존 공통 함수를 사용하여 내역 추가 ('addon' 타입 사용)
            recordShopPayment(
                $pdo,
                $shop_id,
                'addon',
                $total_overage_fee,
                $note_str,
                'n',
                date('Y-m-d'), // billing_date: 검사 날짜 (오늘)
                date('Y-m-t')  // expiring_date: 검사 월의 말일 (납부 기한)
            );
            return ['success' => true, 'billed_amount' => $total_overage_fee, 'message' => '초과 요금 청구 완료'];
        }

        return ['success' => true, 'billed_amount' => 0, 'message' => '초과 리소스 없음'];
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "초과 요금 청구 계산 에러 (상점 ID: {$shop_id})", $e->getMessage());
        return ['success' => false, 'billed_amount' => 0, 'message' => $e->getMessage()];
    }
}

/**
 * 한글, 숫자, 공백 및 주요 특수문자 포함 여부 확인
 */
function isValidKorean($str)
{
    return preg_match('/^[가-힣0-9\s\&\-\_\(\)\.\,\!\'\+\/\?]+$/u', $str);
}

/**
 * 영문, 숫자, 공백 및 주요 특수문자 포함 여부 확인
 */
function isValidEnglish($str)
{
    return preg_match('/^[a-zA-Z0-9\s\&\-\_\(\)\.\,\!\'\+\/\?]+$/', $str);
}

/**
 * 이메일 형식 유효성 검사
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * 비밀번호 복잡성 검사 (대문자, 소문자, 숫자 포함 6자 이상)
 */
function isValidPassword($password)
{
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $password);
}

/**
 * 서브도메인 형식 검사 (영문 소문자 및 숫자만)
 */
function isValidSubdomainFormat($subdomain)
{
    return preg_match('/^[a-z0-9]+$/', $subdomain);
}

/**
 * 시스템 예약어 체크 (아이디 사용 제한)
 */
function isReservedSubdomain($subdomain)
{
    $reserved = ['admin', 'portal', 'www', 'api', 'shop', 'mail', 'blog', 'root', 'support', 'help', 'manager', 'common', 'assets', 'uploads', 'system', 'config'];
    return in_array(strtolower($subdomain), $reserved);
}

/**
 * 필드별 형식 유효성 통합 검사 (AJAX/POST 공용)
 */
function validateFieldFormat($field, $value)
{
    switch ($field) {
        case 'manager_email':
            return isValidEmail($value);
        case 'manager_name':
        case 'shop_name':
            return isValidKorean($value);
        case 'manager_name_en':
        case 'shop_name_en':
            return isValidEnglish($value);
        case 'subdomain':
            return isValidSubdomainFormat($value) && !isReservedSubdomain($value);
        default:
            return true;
    }
}

/**
 * 시스템 로그 기록 함수 (에러, 알림, 메일 실패 등)
 * @param PDO $pdo
 * @param string $type 로그 유형 (error, email_fail, info)
 * @param string $message 주요 메시지
 * @param array|null $details 상세 정보 (JSON 저장)
 */
function recordSiteLog($pdo, $type, $message, $details = null)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // [수정] $details 데이터를 가독성 있는 HTML(리스트) 형태로 변환하여 저장
    $details_html = null;
    if ($details !== null) {
        if (is_array($details)) {
            $details_html = "<ul class='mb-0' style='list-style-type: none; padding-left: 0; margin-top: 5px;'>";
            foreach ($details as $k => $v) {
                $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
                $details_html .= "<li><strong class='text-secondary'>" . htmlspecialchars((string)$k) . ":</strong> <span class='text-dark'>" . htmlspecialchars($val) . "</span></li>";
            }
            $details_html .= "</ul>";
        } else {
            $details_html = nl2br(htmlspecialchars((string)$details));
        }
    }

    try {
        $sql = "INSERT INTO site_logs (log_type, message, details, ip_address, request_uri) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$type, $message, $details_html, $ip, $uri]);
    } catch (Exception $e) {
        error_log("Site Log Record Error: " . $e->getMessage());
    }
}
/**
 * 모든 관리자에게 카카오톡 알림 발송 (가상의 API 연동 구조)
 */
function notifyAdminsViaKakao($pdo, $message)
{
    try {
        // 카카오 ID가 등록된 관리자 목록 조회
        $stmt = $pdo->query("SELECT admin_kakao_id FROM admins WHERE admin_kakao_id IS NOT NULL AND admin_kakao_id != ''");
        $admin_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admin_ids))
            return;

        foreach ($admin_ids as $kakao_id) {
            /**
             * [참고] 실제 카카오 알림톡/친구톡 API 호출부
             * 여기서는 비즈니스 메시징 API나 웹훅을 호출하는 구조로 작성합니다.
             */
            $api_url = "https://api.kakaowork.com/v1/messages.send"; // 예시 API
            $post_data = [
                'receiver_id' => $kakao_id,
                'text' => $message . "\n일시: " . date('Y-m-d H:i:s')
            ];

            // 실제 운영 시 CURL 등을 사용하여 API 전송 로직 구현
            // error_log("Kakao Notification sent to $kakao_id");
        }
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "카카오톡 API 발송 에러", $e->getMessage());
    }
}

/**
 * [신규] 모든 관리자에게 텔레그램 알림 발송 (문법 꼬임 완벽 복구판)
 * @param PDO $pdo
 * @param string $message
 */
function notifyAdminsViaTelegram($pdo, $message)
{
    try {
        $chat_id = '';

        // 1. DB에 저장된 관리자 챗 ID 조회
        if ($pdo) {
            $stmt = $pdo->query("SELECT set_value FROM site_settings WHERE set_key = 'admin_telegram_chat_id'");
            if ($stmt) {
                $chat_id = $stmt->fetchColumn();
            }
        }

        // 2. DB에 설정값이 없으면 config.php의 공통 마스터 ID 적용
        if (empty($chat_id) && defined('PS24_BOT_CHAT_ID')) {
            $chat_id = PS24_BOT_CHAT_ID;
        }

        // 3. 발송 대상이 없으면 종료 (중괄호 명시로 에러 차단)
        if (empty($chat_id)) {
            return;
        }

        $msg = "🔔 <b>[KShops24 시스템]</b>\n\n" . htmlspecialchars($message) . "\n\n일시: " . date('Y-m-d H:i:s');

        // 4. 최상단에 선언된 공통 cURL 텔레그램 발송 함수 호출
        if (function_exists('send_ps24_telegram')) {
            send_ps24_telegram($msg, $chat_id);
        } else {
            recordSiteLog($pdo, LOG_TYPE_ERROR, "텔레그램 발송 에러", "send_ps24_telegram 함수를 찾을 수 없습니다.");
        }
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "텔레그램 발송 에러", $e->getMessage());
    }
}

/**
 * [신규] 관리자(Admin)의 주요 행동 및 정책 변경 이력을 기록하는 전용 래퍼 함수 (복구판)
 * @param PDO $pdo
 * @param string $action_name 작업 명칭 (예: '과금 정책 변경')
 * @param mixed $details 상세 변경 내역 배열 또는 문자열
 */
function recordAdminAction($pdo, $action_name, $details = null)
{
    recordSiteLog($pdo, LOG_TYPE_ADMIN_ACTION, $action_name, $details);
}

/**
 * 상점 관련 이메일 발송 함수 (템플릿 기반 - 문법 꼬임 완벽 복구판)
 * @param PDO $pdo
 * @param string $to_email 수신자 이메일
 * @param string $template_type 템플릿 종류 (apply, testing, active, inactive)
 * @param array $data 치환할 데이터 배열
 */
function sendShopEmail($pdo, $to_email, $template_type, $data = [])
{
    // =========================================================================
    // [운영 모드 전환] TODO: 개발/테스트가 완료되었으므로 실제 점주에게 메일이 발송되도록 아래 줄을 주석 처리합니다.
    // 현재 발송되는 모든 상점 알림 이메일이 아래의 이메일 주소로만 도착하도록 강제 고정되어 있습니다.
    // 이는 개발 중에 실제 고객에게 테스트 메일이 발송되는 것을 방지하기 위한 안전장치입니다.
    // 실제 운영 시에는 $to_email 변수를 그대로 사용하여, 상점 관리자 또는 고객이 입력한 이메일로 메일이 발송됩니다.
    //    $to_email = 'imchhkim@gmail.com';
    // =========================================================================

    try {

        // 1. 템플릿 JSON 데이터 로드
        $stmt = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'email_templates'");
        $stmt->execute();
        $email_templates_json = $stmt->fetchColumn();
        $email_templates = $email_templates_json ? json_decode($email_templates_json, true) : [];

        $content = $email_templates[$template_type] ?? '';

        if (!$content)
            return "템플릿을 찾을 수 없습니다. ($template_type)";

        // 1. DB에 저장된 HTML 특수문자를 실제 태그로 복원 (필수)
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // [추가] shop_id가 데이터에 포함되어 있으면, 공통 템플릿 치환 함수를 우선 적용합니다.
        if (isset($data['shop_id'])) {
            $content = replaceShopTemplateVars($pdo, $data['shop_id'], $content);
        }

        // 2. 발신자 설정
        $stmt_cs = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'cs_email'");
        $stmt_cs->execute();
        $cs_email = $stmt_cs->fetchColumn() ?: 'support@kshops24.com';
        // [수정] 보내는 사람(From)은 이전에 성공했던 도메인 이메일(support@...)로 복구합니다.
        $from_email = (strpos($cs_email, '@kshops24.com') !== false) ? $cs_email : 'support@kshops24.com';

        foreach ($data as $key => $val) {
            if ($key !== 'shop_id')
                $content = str_replace("{{" . $key . "}}", $val ?? '', $content);
        }

        // 3. [최종 병기] 모든 이메일 클라이언트에서 예쁘게 보이도록 HTML 뼈대(액자) 재적용
        // 텍스트 포기를 선언하셨지만, PHP 배열 헤더 방식을 찾았으므로 이제 100% 안전하게 HTML 전송이 가능합니다!
        if (stripos($content, '<html') === false) {
            $content = '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* 모바일 및 좁은 화면에서 표나 이미지가 영역을 벗어나지 않도록 강제 크기 조정 */
        table { width: 100% !important; max-width: 100% !important; }
        img { max-width: 100% !important; height: auto !important; }
    </style>
</head>
<body style="margin:0; padding:15px; background-color:#f4f7f9; font-family:\'Apple SD Gothic Neo\', \'Malgun Gothic\', sans-serif;">
    <div style="width:100%; max-width:650px; margin:0 auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); box-sizing: border-box;">
        <div style="background-color:#004aad; height:6px; width:100%;"></div>
        <div style="padding:30px 20px; color:#333; line-height:1.6; font-size:15px; word-break: break-word; overflow-x: hidden;">
            ' . $content . '
        </div>
        <div style="background-color:#f8f9fa; padding:20px; text-align:center; font-size:12px; color:#888; border-top:1px solid #eee;">
            본 메일은 발신 전용입니다. 문의사항은 KShops24 고객센터를 이용해 주세요.<br>
            &copy; ' . date('Y') . ' KShops24. All rights reserved.
        </div>
    </div>
</body>
</html>';
        }

        // 4. 메일 발송 설정
        $subject_map = [
            SHOP_STATUS_APPLYING => '[KShops24] 입점 신청이 성공적으로 접수되었습니다.',
            SHOP_STATUS_TESTING => '[KShops24] 상점 구축(테스팅) 작업이 시작되었습니다.',
            SHOP_STATUS_ACTIVE => '[KShops24] 상점이 정식으로 오픈되었습니다!',
            SHOP_STATUS_INACTIVE_SOON => '[KShops24] 상점 서비스 일시 중지(휴점) 사전 안내.',
            SHOP_STATUS_INACTIVE => '[KShops24] 상점 서비스가 일시 중지(휴점) 되었습니다.',
            SHOP_STATUS_CLOSED_SOON => '[KShops24] 상점 폐점 사전 안내.',
            SHOP_STATUS_CLOSED => '[KShops24] 상점 서비스가 영구 종료(폐점) 되었습니다.'
        ];
        $raw_subject = $subject_map[$template_type] ?? "[KShops24] 시스템 안내 메일입니다.";

        // [추가] 이메일 제목에도 공용 템플릿 치환을 동일하게 적용합니다.
        if (isset($data['shop_id'])) {
            $raw_subject = replaceShopTemplateVars($pdo, $data['shop_id'], $raw_subject);
        }
        foreach ($data as $key => $val) {
            if ($key !== 'shop_id')
                $raw_subject = str_replace("{{" . $key . "}}", $val ?? '', $raw_subject);
        }

        // 한글 제목 깨짐 방지를 위한 표준 인코딩
        $subject = '=?UTF-8?B?' . base64_encode($raw_subject) . '?=';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n"; // [핵심] Base64 인코딩 선언
        $headers .= "From: KShops24 <" . $from_email . ">\r\n";
        // 답장을 누르면 설정해둔 실제 고객센터 이메일(cs_email)로 회신되도록 편의성 추가
        $headers .= "Reply-To: " . $cs_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion(); // [수정] 마지막 헤더 줄바꿈 제거 (이중 줄바꿈으로 인한 본문 밀림 방지)

        // [핵심] 긴 HTML(이용약관 등) 문자열로 인한 SMTP 한 줄 제한(998자) 에러를 우회하기 위해 본문을 분할 인코딩합니다.
        $encoded_content = chunk_split(base64_encode($content));

        // [복원] Hostinger 환경에서 권한 충돌을 일으키는 5번째 파라미터(-f)를 안전하게 제거합니다.
        $status = @mail($to_email, $subject, $encoded_content, $headers);

        if (!$status) {
            $error = error_get_last();
            $fail_reason = $error ? $error['message'] : '서버 mail() 함수 실행 실패';
            // [보강] 메일 발송 실패 시 로그 기록
            recordSiteLog($pdo, LOG_TYPE_EMAIL_FAIL, "메일 발송 실패: $to_email", ['subject' => $subject, 'template' => $template_type, 'reason' => $fail_reason]);
            return $fail_reason;
        }

        return true;
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "안내 이메일 발송 에러", $e->getMessage());
        return "이메일 처리 중 예외 발생: " . $e->getMessage();
    }
}

/**
 * [신규] 상점 템플릿 변수 공통 치환 함수
 * 문자열(또는 배열) 내의 템플릿 변수를 상점 정보와 환경 설정값으로 일괄 치환합니다.
 * @param PDO $pdo
 * @param int $shop_id 대상 상점 ID (0인 경우 환경 변수만 치환)
 * @param mixed $content 치환할 문자열 또는 연관 배열
 * @return mixed 치환 완료된 문자열 또는 연관 배열
 */
function replaceShopTemplateVars($pdo, $shop_id, $content)
{
    global $shop_category_labels;

    // 환경 설정값 및 날짜 변수 (상점 무관하게 동작)
    $SHOP_CLOSED_AFTER_INACTIVE = defined('SHOP_CLOSED_AFTER_INACTIVE') ? SHOP_CLOSED_AFTER_INACTIVE : 30;
    $SHOP_STATUS_INACTIVE_SOON_DAYS = defined('SHOP_STATUS_INACTIVE_SOON_DAYS') ? SHOP_STATUS_INACTIVE_SOON_DAYS : 14;
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    $replacements = [
        '{SHOP_CLOSED_AFTER_INACTIVE}' => $SHOP_CLOSED_AFTER_INACTIVE,
        '{{SHOP_CLOSED_AFTER_INACTIVE}}' => $SHOP_CLOSED_AFTER_INACTIVE,
        '{SHOP_STATUS_INACTIVE_SOON_DAYS}' => $SHOP_STATUS_INACTIVE_SOON_DAYS,
        '{{SHOP_STATUS_INACTIVE_SOON_DAYS}}' => $SHOP_STATUS_INACTIVE_SOON_DAYS,
        '{now}' => $now,
        '{{now}}' => $now,
        '{today}' => $today,
        '{{today}}' => $today
    ];

    // 상점 정보가 존재하는 경우 추가 치환 데이터 생성
    if ($shop_id > 0) {
        $stmt_info = $pdo->prepare("
            SELECT s.*, 
                   (SELECT MAX(expiring_date) FROM shop_payments WHERE shop_id = s.id AND paid = 'n' AND pay_type IN ('monthly', '6months', 'addon')) as max_expiring_date,
                   (SELECT SUM(amount) FROM shop_payments WHERE shop_id = s.id AND paid = 'n') as total_unpaid 
            FROM shops s WHERE s.id = ?
        ");
        $stmt_info->execute([$shop_id]);
        $shop = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($shop) {
            $cate_name = $shop_category_labels[$shop['category']] ?? $shop['category'];

            $shop_replacements = [
                '{shop_name}' => $shop['shop_name'],
                '{{shops:shop_name}}' => $shop['shop_name'],
                '{shop_name_en}' => $shop['shop_name_en'],
                '{{shops:shop_name_en}}' => $shop['shop_name_en'],
                '{manager_name}' => $shop['manager_name'],
                '{{shops:manager_name}}' => $shop['manager_name'],
                '{subdomain}' => $shop['subdomain'],
                '{{shops:subdomain}}' => $shop['subdomain'],
                '{phone_mobile}' => $shop['phone_mobile'],
                '{{shops:phone_mobile}}' => $shop['phone_mobile'],
                '{manager_email}' => $shop['manager_email'],
                '{{shops:manager_email}}' => $shop['manager_email'],
                '{expiring_date}' => $shop['max_expiring_date'] ?: '알 수 없음',
                '{unpaid_amount}' => number_format((float) $shop['total_unpaid']),
                '{category}' => $cate_name,
                '{{shops:category}}' => $cate_name,
                '{apply_date}' => $shop['created_at'],
                '{{shops:apply_date}}' => $shop['created_at'],
                '{deleted_date}' => $shop['deleted_date'] ?: '알 수 없음',
                '{{shops:deleted_date}}' => $shop['deleted_date'] ?: '알 수 없음',
                '{closed_date}' => $shop['closed_date'] ?: '알 수 없음',
                '{{shops:closed_date}}' => $shop['closed_date'] ?: '알 수 없음',
                '{inactive_date}' => $shop['inactive_date'] ?: '알 수 없음',
                '{{shops:inactive_date}}' => $shop['inactive_date'] ?: '알 수 없음'
            ];
            $replacements = array_merge($replacements, $shop_replacements);
        }
    }

    if (is_array($content)) {
        foreach ($content as $k => $v) {
            $content[$k] = strtr($v, $replacements);
        }
        return $content;
    }
    return strtr($content, $replacements);
}

/**
 * [신규] 상점주에게 시스템 안내 메시지(쪽지) 발송 (템플릿 기반)
 * @param PDO $pdo
 * @param int $shop_id 상점 ID
 * @param string $template_key 템플릿 키 (예: SHOP_STATUS_INACTIVE_SOON)
 * @param array $data 치환할 변수 배열
 * @return bool 성공 여부
 */
function sendShopMessage($pdo, $shop_id, $template_key, $data = [])
{
    $stmt = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'message_templates'");
    $stmt->execute();
    $templates = json_decode($stmt->fetchColumn(), true);

    $tpl = $templates[$template_key] ?? null;

    // DB에 템플릿이 없을 경우를 대비한 하드코딩 기본값 (Fallback)
    if (!$tpl) {
        $tpl = ['title' => "시스템 안내 ({$template_key})", 'content' => "상점 관련 안내 메시지입니다.\n- 상점명: {shop_name}\n- 기한: {expiring_date}"];
    }

    $title = $tpl['title'];
    $content = $tpl['content'];

    // 1. 공용 함수를 통해 시스템/상점 변수 자동 치환
    $replaced = replaceShopTemplateVars($pdo, $shop_id, ['title' => $title, 'content' => $content]);
    $title = $replaced['title'];
    $content = $replaced['content'];

    // 2. 호출처에서 직접 넘어온 추가 변수가 있다면 치환
    foreach ($data as $key => $val) {
        $title = str_replace(["{{$key}}", "{{{$key}}}"], $val ?? '', $title);
        $content = str_replace(["{{$key}}", "{{{$key}}}"], $val ?? '', $content);
    }

    $stmt = $pdo->prepare("INSERT INTO shop_board (shop_id, type, sender_type, title, content, is_secret, created_at) VALUES (?, 'message', 'admin', ?, ?, 1, NOW())");
    $res = $stmt->execute([$shop_id, $title, $content]);
    if ($res) {
        addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_MESSAGE, "쪽지 발송", "제목: {$title}");

        // [텔레그램 알림] 상점주가 '본사 알림(message)' 수신에 동의한 경우 발송
        $stmt_tel = $pdo->prepare("SELECT telegram_chat_id, use_telegram_alert, telegram_alert_types FROM shops WHERE id = ?");
        $stmt_tel->execute([$shop_id]);
        $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);

        if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
            $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
            if (in_array('message', $alert_types)) {
                $tel_msg = "🔔 <b>[본사 알림 메시지]</b>\n\n<b>{$title}</b>\n\n" . strip_tags($content);
                send_ps24_telegram($tel_msg, $tel_info['telegram_chat_id']);
            }
        }
    }
    return $res;
}

/**
 * [신규] 상점 휴점 처리 및 히스토리 로그 자동 기록
 * @param PDO $pdo
 * @param int $shop_id 상점 ID
 * @param string $reason 휴점 사유
 * @param string|null $suspend_date 휴점일 (기본값: 오늘)
 * @return bool 성공 여부
 */
function suspendShop($pdo, $shop_id, $reason, $suspend_date = null)
{
    if (!$suspend_date)
        $suspend_date = date('Y-m-d');

    // [수정] 수동 휴점 시에도 예상 폐점일과 삭제일을 계산하여 DB에 저장
    $closed_soon_days = defined('SHOP_STATUS_CLOSED_SOON_DAYS') ? SHOP_STATUS_CLOSED_SOON_DAYS : 30;
    $warning_deleted_soon_days = defined('WARNING_SHOP_STATUS_DELETED_SOON_DAYS') ? WARNING_SHOP_STATUS_DELETED_SOON_DAYS : 30;
    $deleted_soon_days = defined('SHOP_STATUS_DELETED_SOON_DAYS') ? SHOP_STATUS_DELETED_SOON_DAYS : 30;

    $closed_date_val = date('Y-m-d', strtotime($suspend_date . " +{$closed_soon_days} days"));
    $deleted_days_add = $closed_soon_days + $warning_deleted_soon_days + $deleted_soon_days;
    $deleted_date_val = date('Y-m-d', strtotime($suspend_date . " +{$deleted_days_add} days"));

    $res = $pdo->prepare("UPDATE shops SET status = ?, inactive_date = ?, closed_date = ?, deleted_date = ? WHERE id = ?")
        ->execute([SHOP_STATUS_INACTIVE, $suspend_date, $closed_date_val, $deleted_date_val, $shop_id]);

    if ($res) {
        addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_STATUS, "휴점 처리", "사유: {$reason}", $suspend_date);
    }
    return $res;
}

/**
 * 상점을 폐점 처리하며 UNIQUE 필드 충돌 방지를 위해 꼬리표 부착 (Soft Rename)
 * @param PDO $pdo
 * @param int $shop_id 상점 ID
 * @return bool 성공 여부
 */
function closeShopWithRename($pdo, $shop_id)
{
    try {
        $today = date('Y-m-d');
        $warning_deleted_soon_days = defined('WARNING_SHOP_STATUS_DELETED_SOON_DAYS') ? WARNING_SHOP_STATUS_DELETED_SOON_DAYS : 30;
        $deleted_soon_days = defined('SHOP_STATUS_DELETED_SOON_DAYS') ? SHOP_STATUS_DELETED_SOON_DAYS : 30;
        $deleted_days_add = $warning_deleted_soon_days + $deleted_soon_days;
        $deleted_date_val = date('Y-m-d', strtotime($today . " +{$deleted_days_add} days"));

        $close_sql = "UPDATE shops SET 
            status = ?,
            closed_date = IFNULL(closed_date, ?),
            deleted_date = ?,
            subdomain = IF(subdomain NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', subdomain), subdomain),
            manager_email = IF(manager_email NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', manager_email), manager_email),
            phone_mobile = IF(phone_mobile NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', phone_mobile), phone_mobile),
            kakao_id = IF(kakao_id != '' AND kakao_id NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', kakao_id), kakao_id),
            shop_name = IF(shop_name NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', shop_name), shop_name),
            shop_name_en = IF(shop_name_en != '' AND shop_name_en NOT LIKE 'closed_%', CONCAT('closed_', DATE_FORMAT(CURDATE(), '%Y%m%d'), '_', shop_name_en), shop_name_en)
            WHERE id = ?";

        $stmt = $pdo->prepare($close_sql);
        $res = $stmt->execute([SHOP_STATUS_CLOSED, $today, $deleted_date_val, $shop_id]);
        if ($res) {
            addShopHistoryLog($pdo, $shop_id, SHOP_HISTORY_STATUS, "폐점 처리", "상점 모든 데이터 및 폴더 삭제 대기 상태 전환");
        }
        return $res;
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "폐점 상점 Rename 업데이트 에러", $e->getMessage());
        return false;
    }
}

/**
 * [신규] 상점 영구 삭제 시 기본 정보 백업 로그 기록
 * @param PDO $pdo
 * @param int $shop_id 상점 ID
 * @return bool 성공 여부
 */
function logDeletedShopInfo($pdo, $shop_id)
{
    try {
        // 1. 삭제 전 상점의 기본 정보를 가져옵니다.
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shop) return false;

        // 2. 휴점 사유 추출 (과거 history_log를 역순으로 분석하여 파악)
        $suspend_reason = '알 수 없음';
        if (!empty($shop['history_log'])) {
            $history = json_decode($shop['history_log'], true);
            if (is_array($history)) {
                for ($i = count($history) - 1; $i >= 0; $i--) {
                    $event = $history[$i];
                    // 제목에 '휴점'이 들어간 상태 변경 로그의 내용을 사유로 추출
                    if (($event['type'] ?? '') === 'status' && (strpos($event['title'] ?? '', '휴점') !== false)) {
                        $suspend_reason = $event['content'] ?? '사유 없음';
                        break;
                    }
                }
            }
        }

        // 3. 복구를 대비한 핵심 데이터 배열화 (JSON으로 저장됨)
        $details = [
            'shop_id' => $shop['id'],
            'shop_name' => $shop['shop_name'],
            'subdomain' => $shop['subdomain'],
            'manager_name' => $shop['manager_name'],
            'manager_email' => $shop['manager_email'],
            'phone_mobile' => $shop['phone_mobile'],
            'created_at' => $shop['created_at'],
            'inactive_date' => $shop['inactive_date'],
            'closed_date' => $shop['closed_date'],
            'deleted_date' => $shop['deleted_date'],
            'suspend_reason' => $suspend_reason
        ];

        $message = "상점 영구 삭제 백업: {$shop['shop_name']} ({$shop['subdomain']})";

        // 4. 기존 공용 함수를 이용하여 site_logs 테이블에 기록 (타입: 'deleted')
        recordSiteLog($pdo, LOG_TYPE_DELETED, $message, $details);
        return true;
    } catch (Exception $e) {
        recordSiteLog($pdo, LOG_TYPE_ERROR, "삭제 상점 정보 백업 에러 (상점 ID: {$shop_id})", $e->getMessage());
        return false;
    }
}

/**
 * 상점 영구 삭제 및 연관된 모든 데이터(하위 테이블, 물리적 파일) 일괄 삭제
 * @param PDO $pdo
 * @param int $shop_id 상점 ID
 * @return bool 성공 여부
 */
function deleteShopCompletely($pdo, $shop_id)
{
    try {
        // 1. 상점 서브도메인 확보 (해당 상점의 물리적 업로드 폴더 삭제용)
        $stmt = $pdo->prepare("SELECT subdomain FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $subdomain = $stmt->fetchColumn();

        // [신규] 상점 데이터가 물리적으로 날아가기 전에 먼저 백업 로깅을 수행합니다.
        logDeletedShopInfo($pdo, $shop_id);

        // 트랜잭션 시작: 중간에 하나라도 실패하면 모두 롤백하여 데이터 무결성 보호
        $pdo->beginTransaction();

        // 2. 외래키(FK)를 걸 수 없는 포털 공용(shop_id=0 포함) 테이블만 수동으로 삭제
        $tables_to_clear = [
            'shop_board',
            'visit_logs',
            'visit_stats'
        ];
        foreach ($tables_to_clear as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE shop_id = ?")->execute([$shop_id]);
        }

        // 3. 메인 상점 데이터 삭제
        // (참고: ON DELETE CASCADE가 적용된 고객, 주문, 리뷰, 결제, 부동산 테이블 등은 DB가 자동 삭제함)
        $pdo->prepare("DELETE FROM shops WHERE id = ?")->execute([$shop_id]);

        // 모든 삭제 작업 승인
        $pdo->commit();

        // 4. 물리적 업로드 폴더 삭제 (옵션이지만 서버 용량 확보를 위해 권장)
        if ($subdomain) {
            $target_dir = SHOP_UPLOADS_DIR . "/" . $subdomain;
            if (is_dir($target_dir)) {
                // 폴더 내 하위 폴더와 파일들을 순회하며 재귀적으로 모두 삭제
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    @$todo($fileinfo->getRealPath());
                }
                @rmdir($target_dir);
            }
        }
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        recordSiteLog($pdo, LOG_TYPE_ERROR, "상점 완전 삭제(DB/폴더) 에러 (상점 ID: {$shop_id})", $e->getMessage());
        return false;
    }
}

/**
 * [신규] 서버의 물리적 파일(이미지 등)을 안전하게 일괄 삭제하는 공용 함수
 * - JSON 배열, 단일 문자열, 일반 배열 등 다양한 형태의 경로 데이터를 입력받아 처리합니다.
 * - Directory Traversal 공격(../)을 방지하고 /uploads/ 디렉토리 내부 파일만 삭제합니다.
 * @param mixed $paths 단일 파일 경로 문자열 또는 경로 배열 (JSON 형태도 허용)
 * @return int 삭제된 파일 개수
 */
function deletePhysicalFiles($paths)
{
    $deleted_count = 0;
    $path_array = [];

    if (empty($paths)) return 0;

    // [최적화 3] 복잡한 배열/JSON/문자열 타입 체크 및 변환을 1줄 3항 연산자로 단축
    $path_array = is_array($paths) ? $paths : (json_decode($paths, true) ?: [$paths]);

    foreach ($path_array as $path) {
        if (empty($path) || strpos($path, '/uploads/') !== 0 || strpos($path, '..') !== false) {
            continue;
        }
        $real_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($path, '/');
        if (file_exists($real_path) && is_file($real_path)) {
            if (@unlink($real_path)) $deleted_count++;

            // [추가] 썸네일이 존재한다면 함께 삭제
            $thumb_path = dirname($real_path) . '/thumb_' . basename($real_path);
            if (file_exists($thumb_path) && is_file($thumb_path)) {
                @unlink($thumb_path);
            }
        }
    }
    return $deleted_count;
}

/**
 * [공통 UI] 원본 이미지 경로를 받아 가벼운 썸네일 이미지 경로를 반환합니다.
 * 썸네일 파일이 존재하지 않는 과거 데이터의 경우 원본 이미지 경로를 그대로 폴백(Fallback)합니다.
 * @param string $original_path 원본 웹 경로 (예: /uploads/shops/chickengame/itemimages/menu_xxx.jpg)
 * @return string 썸네일 웹 경로 또는 원본 경로
 */
function getThumbnailPath($original_path)
{
    if (empty($original_path) || strpos($original_path, '/uploads/') !== 0) return $original_path ?: '/assets/no-logo.png';

    // [최적화 4] 불필요한 중간 변수 할당 제거 및 3항 연산자(Turnary) 리턴 적용
    $thumb_path = dirname($original_path) . '/thumb_' . basename($original_path);
    return file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $thumb_path) ? $thumb_path : $original_path;
}

/**
 * [공통 미디어 모듈] 유튜브 URL에서 비디오 ID 안전하게 추출
 * 정규식을 이용해 다양한 형태(Shorts, 모바일, 파라미터 포함 등)의 주소에서 ID만 빼냅니다.
 * @param string $url 유튜브 주소
 * @return string|null 추출된 11자리 비디오 ID 또는 null
 */
function extractYoutubeIdFromUrl($url)
{
    if (empty($url)) return null;
    // 도메인 유효성 1차 검증 (이미지 경로 오인식 원천 차단)
    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) return null;
    // [버그 수정] 유튜브 쇼츠(shorts/) 형태의 주소에서도 비디오 ID(11자리)를 정상적으로 추출하도록 정규식 업그레이드
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match)) {
        return $match[1];
    }
    return null;
}

/**
 * [신규] Bootstrap 5 기반 공통 알림(Alert) 메시지 생성 함수
 * 관리자 화면 등에서 성공, 경고, 에러 등의 메시지를 통일성 있게 출력합니다.
 * @param string $msg 출력할 메시지 내용
 * @param string $type 알림 타입 (success, danger, warning, info 등)
 * @return string HTML 알림창 문자열
 */
if (!function_exists('showAlert')) {
    function showAlert($msg, $type = 'info')
    {
        $icon = ($type == 'success') ? 'bi-check-circle-fill' : (($type == 'danger') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        return "
        <div class='alert alert-{$type} alert-dismissible fade show mb-4 border-start border-4 border-{$type} shadow-sm msg-auto-close' role='alert'>
            <div class='d-flex align-items-center'>
                <i class='bi {$icon} me-2'></i>
                <div>{$msg}</div>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

/**
 * [신규] 상점 상태별 개수 통합 집계 함수
 * @param PDO $pdo
 * @return array 상태별 개수 배열
 */
function getShopStatusCounts($pdo)
{
    $counts = [
        SHOP_STATUS_APPLYING => 0,
        SHOP_STATUS_TESTING => 0,
        SHOP_STATUS_ACTIVE => 0,
        SHOP_STATUS_INACTIVE => 0,
        SHOP_STATUS_INACTIVE_SOON => 0,
        SHOP_STATUS_CLOSED => 0,
        SHOP_STATUS_OWNER_INACTIVE => 0,
        SHOP_STATUS_OWNER_DELETED => 0,
        'expiring' => 0
    ];
    $count_query = $pdo->query("SELECT status, COUNT(*) as cnt FROM shops GROUP BY status")->fetchAll();
    foreach ($count_query as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = $row['cnt'];
        }
    }
    $counts['expiring'] = count(getExpiringShopIds($pdo));
    return $counts;
}

/**
 * 특정 상점이 결제 만료 임박 알림(SHOP_STATUS_INACTIVE_SOON_DAYS)에 해당되는 상점인지을 알려주는 함수. 
 *
 * 특정 상점이 휴점 후 폐점 결정일 (SHOP_CLOSED_AFTER_INACTIVE) 안에 해당되는 상점인지을 알려주는 함수.
 * 
 * 결제 만료 임박 알림(SHOP_STATUS_INACTIVE_SOON_DAYS)에 해당되는 상점 아이디들을 리턴하는 함수
 * 
 * 휴점 후 폐점 결정일 (SHOP_CLOSED_AFTER_INACTIVE) 안에 해당되는 상점 아이디들을 리턴하는 함수
 */

/**
 * 결제 만료 임박 알림(SHOP_STATUS_INACTIVE_SOON_DAYS)에 해당되는 상점 아이디들을 리턴하는 함수
 * @param PDO $pdo
 * @return array 상점 ID 배열
 */
function getExpiringShopIds($pdo)
{
    $sql = "
        SELECT s.id 
        FROM shops s
        JOIN (" . SQL_EXPIRING_SUBQUERY . ") p ON s.id = p.shop_id
        WHERE s.status = 'active' AND p." . SQL_EXPIRING_CONDITION . "
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * 특정 상점이 결제 만료 임박 알림(SHOP_STATUS_INACTIVE_SOON_DAYS)에 해당되는 상점인지 알려주는 함수
 * @param PDO $pdo
 * @param int $shop_id
 * @return bool
 */
function isShopExpiringSoon($pdo, $shop_id)
{
    $sql = "
        SELECT 1 
        FROM shops s
        JOIN (" . SQL_EXPIRING_SUBQUERY . ") p ON s.id = p.shop_id
        WHERE s.id = ? AND s.status = 'active' AND p." . SQL_EXPIRING_CONDITION . "
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$shop_id]);
    return (bool) $stmt->fetchColumn();
}

/**
 * [수정] 휴점 후 폐점 결정일 안에 해당되는 상점 ID 목록 반환
 * @param PDO $pdo
 * @param bool $is_overdue true: 기한이 지나 폐점 처리가 필요한 상점, false: 기한이 아직 남은 대기 상점
 * @return array 상점 ID 배열
 */
function getPendingCloseShopIds($pdo, $is_overdue = false)
{
    $today = date('Y-m-d');

    // $is_overdue가 true면 폐점 예정일이 오늘 이전(<=)인 상점 추출
    $op = $is_overdue ? '<=' : '>';

    // JSON 파싱 대신 shops 테이블의 closed_date 컬럼을 직접 활용
    $sql = "SELECT id FROM shops WHERE status = 'inactive' AND closed_date IS NOT NULL AND closed_date {$op} ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * [수정] 특정 상점이 휴점 후 완전 폐점(closed)까지 7일 이내로 임박했는지(위험 알림용) 반환
 * @param PDO $pdo
 * @param int $shop_id
 * @return bool
 */
function isShopPendingClose($pdo, $shop_id)
{
    $today = date('Y-m-d');
    $warning_date = date('Y-m-d', strtotime('+7 days'));

    // 폐점일(closed_date)이 오늘부터 7일 이내에 존재하는 경우만 true 반환
    $sql = "SELECT 1 FROM shops WHERE id = ? AND status = 'inactive' AND closed_date IS NOT NULL AND closed_date BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$shop_id, $today, $warning_date]);

    return (bool) $stmt->fetchColumn();
}

/**
 * [공통 UI] 모달 및 라이트박스 팝업 시 뒤로가기 방지용 스크립트 렌더링
 * 모달(Bootstrap)이나 갤러리(fslightbox)가 열린 상태에서 스마트폰 뒤로가기 시
 * 이전 페이지로 이동하지 않고 팝업만 부드럽게 닫히도록 제어합니다.
 * @return string HTML 스크립트 문자열
 */
function renderPopupHistoryBackScript()
{
    return <<<HTML
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Bootstrap Modal 열릴 때 History 상태 추가
        document.addEventListener('shown.bs.modal', function (event) {
            window.history.pushState({ popupOpen: true, id: event.target.id }, '');
        });

        // 2. fsLightbox (메뉴판 등 갤러리 팝업) 열릴 때 History 상태 추가
        document.body.addEventListener('click', function(e) {
            const lightboxTrigger = e.target.closest('[data-fslightbox]');
            if (lightboxTrigger) {
                window.history.pushState({ popupOpen: true, type: 'fslightbox' }, '');
            }
        });

        // 3. [수정] 핸드폰 뒤로가기 버튼(popstate) 감지 시, 최상위 팝업만 순차적으로 닫기
        window.addEventListener('popstate', function (event) {
            // 우선순위 1: fsLightbox가 열려있는지 확인 (<html> 태그에 fslightbox-open 클래스 존재 여부)
            if (document.documentElement.classList.contains('fslightbox-open')) {
                // 라이트박스가 열려있다면, 라이트박스만 닫고 함수를 즉시 종료합니다.
                const closeBtns = document.querySelectorAll('.fslightbox-toolbar-button');
                if (closeBtns.length > 0) closeBtns[closeBtns.length - 1].click();
                return;
            }

            // 우선순위 2: 라이트박스가 닫혀있다면, 열려있는 Bootstrap 모달을 찾아 닫습니다.
            // 이 로직은 여러 모달이 동시에 열리는 경우를 대비해 모든 모달을 닫도록 설계되어 있습니다.
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length > 0) {
                openModals.forEach(modalEl => {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                });
            }
        }, false);
    });
</script>
HTML;
}

/**
 * 임시 비밀번호 발송 메일 공용 함수
 * @param PDO $pdo
 * @param string $email 수신자 이메일
 * @param string $temp_pw 발급된 임시 비밀번호
 * @return bool|string 발송 성공 시 true, 실패 시 오류 메시지
 */
function sendTempPasswordEmail($pdo, $email, $temp_pw)
{
    $stmt_cs = $pdo->prepare("SELECT set_value FROM site_settings WHERE set_key = 'cs_email'");
    $stmt_cs->execute();
    $cs_email = $stmt_cs->fetchColumn() ?: 'support@kshops24.com';
    $from_email = (strpos($cs_email, '@kshops24.com') !== false) ? $cs_email : 'support@kshops24.com';

    $subject = '=?UTF-8?B?' . base64_encode("[KShops24] 상점 관리자 임시 비밀번호 발급 안내") . '?=';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: KShops24 <" . $from_email . ">\r\n";
    $headers .= "Reply-To: " . $cs_email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $content = "<!DOCTYPE html>
<html lang='ko'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin:0; padding:15px; background-color:#f4f7f9; font-family:\"Apple SD Gothic Neo\", \"Malgun Gothic\", sans-serif;'>
    <div style='width:100%; max-width:500px; margin:0 auto; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); text-align:center;'>
        <div style='padding:30px;'>
            <h3 style='color:#004aad; margin-top:0;'>임시 비밀번호 안내</h3>
            <p style='color:#555;'>사장님, 요청하신 계정의 임시 비밀번호가 발급되었습니다.</p>
            <div style='margin:20px 0; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; font-size:24px; font-weight:bold; letter-spacing:4px; color:#333; border-radius:6px;'>
                " . $temp_pw . "
            </div>
            <p style='color:#dc3545; font-size:13px; margin-bottom:0;'>* 보안을 위해 로그인 직후 반드시 새로운 비밀번호로 변경해 주시기 바랍니다.</p>
        </div>
    </div>
</body>
</html>";

    $encoded_content = chunk_split(base64_encode($content));
    $status = @mail($email, $subject, $encoded_content, $headers);
    
    if (!$status) {
        $error = error_get_last();
        return $error ? $error['message'] : '서버 mail() 함수 실행 실패';
    }
    return true;
}

/**
 * [수정됨] 이미지 모달 팝업 트리거(링크) 생성 함수
 * iframe 내부/외부 상관없이 부모 창의 모달을 띄울 수 있도록 JS 함수 호출 방식으로 변경
 * 파일 경로를 받아 모달창을 띄우는 <a> 태그를 반환합니다.
 * * @param string $image_url 이미지 링크 (예: /uploads/shops/.../logo.jpg)
 * @param string|null $display_text 화면에 보여질 텍스트 (기본값은 이미지 경로)
 * @return string HTML <a> 태그
 */
function getImageModalTrigger($image_url, $display_text = null)
{
    $text = $display_text ? htmlspecialchars($display_text) : htmlspecialchars($image_url);
    $url = htmlspecialchars($image_url);

    // window.parent를 통해 부모 창의 함수(showCommonImageModal)를 호출합니다.
    return "<a href=\"javascript:void(0);\" class=\"text-decoration-none fw-bold text-primary\" onclick=\"if(window.parent && typeof window.parent.showCommonImageModal === 'function') { window.parent.showCommonImageModal('{$url}'); } else if(typeof showCommonImageModal === 'function') { showCommonImageModal('{$url}'); }\">{$text}</a>";
}

/**
 * [신규] 공통 이미지 미리보기 모달 렌더링 함수
 * iframe 내부에서도 호출할 수 있도록 전역 함수(showCommonImageModal)를 포함합니다.
 */
function renderCommonImageModal()
{
    return <<<HTML
<div class="modal fade" id="commonImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">이미지 미리보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img src="" id="commonModalImg" class="img-fluid rounded shadow-sm" alt="미리보기" style="max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
/**
 * 전역 이미지 모달 호출 함수 (iframe 안쪽에서 window.parent.showCommonImageModal 로 접근)
 */
function showCommonImageModal(imgUrl) {
    var modalEl = document.getElementById('commonImageModal');
    if (modalEl) {
        modalEl.querySelector('#commonModalImg').src = imgUrl;
        modalEl.querySelector('.modal-title').textContent = imgUrl.split('/').pop();

        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }
}

// 모달 닫힐 때 잔상 초기화
document.addEventListener('DOMContentLoaded', function () {
    var commonModal = document.getElementById('commonImageModal');
    if (commonModal) {
        commonModal.addEventListener('hidden.bs.modal', function () {
            commonModal.querySelector('#commonModalImg').src = '';
        });
    }
});
</script>
HTML;
}


/**
 * [공통 UI] 페이지 최상단 타이틀 구조화 헬퍼 함수
 * 관리자 화면의 대제목과 우측 컨트롤(날짜 등)을 일관된 반응형 레이아웃으로 출력합니다.
 * 
 * @param string $title 페이지 제목
 * @param string $icon Bootstrap 아이콘 클래스 (예: 'bi-ui-checks')
 * @param mixed $arg3 우측에 배치할 HTML 이거나, 타이틀 색상 코드
 * @param string $custom_color 타이틀에 직접 적용할 텍스트 색상 코드
 * @return string HTML 렌더링 문자열
 * 
 * 예: 빨간색으로 강조된 제목
 * echo renderSectionHeader('예약 대기', 'bi-calendar2-check text-danger', [], '', 'text-danger fs-5');
 *  <?php echo renderSectionHeader('이미지 리소스 관리', 'bi-image'); ?>
 */
if (!function_exists('renderPageHeader')) {
    function renderPageHeader($title, $icon = '', $arg3 = null, $custom_color = '')
    {
        $right_html = null;
        $color = $custom_color;

        // 세 번째 인자 스마트 처리: HTML 태그면 우측 영역 HTML로, 아니면 색상 코드로 판단
        if (is_string($arg3) && $arg3 !== '') {
            if (strpos($arg3, '<') !== false && strpos($arg3, '>') !== false) {
                $right_html = $arg3;
            } else {
                $color = $arg3;
            }
        } elseif ($arg3 !== null) {
            $right_html = $arg3;
        }

        $icon_html = $icon ? "<i class='bi {$icon} me-2'></i>" : '';
        if ($right_html === null) {
            $date_str = date('Y년 m월 d일');
            $right_html = "<span class='badge bg-white text-secondary border shadow-sm px-3 py-2 rounded-pill'><i class='bi bi-calendar-check me-1'></i> 오늘: {$date_str}</span>";
        }
        
        $color_style = $color ? " style='color: {$color} !important;'" : '';

        return "
        <div class='box-responsive-between mb-4'>
            <h4 class='fw-bold text-dark m-0'{$color_style}>{$icon_html}{$title}</h4>
            " . ($right_html ? "<div class='ms-md-auto mt-3 mt-md-0 d-flex align-items-center justify-content-center'>{$right_html}</div>" : "") . "
        </div>";
    }
}

/**
 * [공통 UI] 섹션 타이틀 및 부가설명 구조화 헬퍼 함수
 * 관리자 화면에서 섹션 제목과 부가 설명을 일관된 반응형 레이아웃으로 출력합니다.
 * 
 * @param string $title 섹션 제목
 * @param string $icon Bootstrap 아이콘 클래스 (예: 'bi-tag')
 * @param mixed $arg3 부가 설명 배열이거나, 타이틀 색상 코드
 * @param mixed $arg4 우측에 배치할 HTML이거나, 타이틀 색상 코드
 * @param string $title_class 제목 색상 및 폰트 크기 클래스 (기본: 'text-primary fs-5')
 * @param string $custom_color 제목에 직접 적용할 텍스트 색상 코드 (예: '#ff0000', 'red')
 * @return string HTML 렌더링 문자열
 */
if (!function_exists('renderSectionHeader')) {
    function renderSectionHeader($title, $icon = '', $arg3 = [], $arg4 = '', $title_class = 'text-primary fs-5', $custom_color = '')
    {
        $descriptions = [];
        $right_html = '';
        $color = $custom_color;

        // 세 번째 인자 스마트 처리: 배열이면 설명글, 문자열이면 색상 코드로 간주
        if (is_array($arg3)) {
            $descriptions = $arg3;
        } elseif (is_string($arg3) && $arg3 !== '') {
            $color = $arg3;
        }

        // 네 번째 인자 스마트 처리: HTML 태그면 우측 영역 HTML로, 아니면 색상 코드로 간주
        if (is_string($arg4) && $arg4 !== '') {
            if (strpos($arg4, '<') !== false && strpos($arg4, '>') !== false) {
                $right_html = $arg4;
            } else {
                if ($color === '') $color = $arg4;
            }
        }

        $icon_html = $icon ? "<i class='bi {$icon} me-2'></i>" : '';

        $desc_html = '';
        if (!empty($descriptions)) {
            foreach ($descriptions as $index => $desc) {
                $mt_class = ($index === 0) ? 'mt-3' : 'mt-1';
                $desc_html .= "<p class='text-muted small {$mt_class} mb-0'>{$desc}</p>";
            }
        }
        
        $color_style = $custom_color ? " style='color: {$custom_color} !important;'" : '';

        return "
        <div class='box-responsive-between mb-4'>
            <div class='text-center text-md-start flex-grow-1'>
                <h6 class='fw-bold mb-0 {$title_class}'{$color_style}>{$icon_html}{$title}</h6>
                {$desc_html}
            </div>
            " . ($right_html ? "<div class='ms-md-auto mt-3 mt-md-0'>{$right_html}</div>" : "") . "
        </div>";
    }
}

/**
 * [공통 UI 상수] 관리자 화면 섹션 카드 컨테이너 공통 클래스
 * 전역에서 사용하여 디자인의 일관성을 맞추고 추후 유지보수를 용이하게 합니다.
 * 사용법 : 
 * <p <?php echo UI_INFO_SM_LABEL;?>> 내용을 입력한 경우에만, 상점 메인 최상단에 붉은색으로 강조되어 노출됩니다.</p>
 */
if (!defined('UI_SECTION_CARD')) {
    define('UI_SECTION_CARD', 'card shadow-sm border-0 mb-4 h-100 p-0');
}

if (!defined('UI_INFO_SM_LABEL')) {
    define('UI_INFO_SM_LABEL', 'class="bi bi-info-circle-fill text-primary mb-0 mt-0" style="font-size: 0.7rem"');
}

if (!defined('UI_INFO_MD_LABEL')) {
    define('UI_INFO_MD_LABEL', 'class="bi bi-info-circle-fill text-primary" style="font-size: 0.9rem"');
}

if (!defined('UI_INFO_LG_LABEL')) {
    define('UI_INFO_LG_LABEL', 'class="bi bi-info-circle-fill text-primary" style="font-size: 1.1rem"');
}


/**
 * [공통 UI] 오늘 요일의 영업시간을 자동으로 계산하여 반환하는 헬퍼 함수
 * @param string $business_hours_json DB에 저장된 영업시간 JSON 문자열
 * @return string 오늘의 영업시간 (예: 09:00 ~ 18:00, 휴무, 24시간 등)
 */
if (!function_exists('getTodayBusinessHours')) {
    function getTodayBusinessHours($business_hours_json)
    {
        $bh = $business_hours_json ?? '';
        if (!empty($bh) && (str_starts_with($bh, '{') || str_starts_with($bh, '['))) {
            $bh_data = json_decode($bh, true);
            $today_info = $bh_data[strtolower(date('D'))] ?? null;

            if ($today_info && !empty($today_info['closed'])) return __('휴무');
            if ($today_info && (!empty($today_info['open']) || !empty($today_info['close']))) return $today_info['open'] . ' ~ ' . $today_info['close'];
        }
        return $bh ?: '24' . __('시간');
    }
}
