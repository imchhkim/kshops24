<<<<<<< HEAD
<?php

/**
 * [API 핸들러] KShops24 상점 고객 리뷰 처리 엔진 (shop_review_handler.php)
 * 
 * - 필요성: 프론트엔드(shop_view.php)에서 페이지 새로고침 없이(AJAX) 리뷰를 등록하고, 
 *           더 보기(페이징) 기능으로 리뷰 목록을 부드럽게 불러오기 위해 독립된 API 서버 역할을 수행합니다.
 * - 기능 1 (list): 상점의 리뷰 데이터를 10개 단위로 잘라(Pagination) JSON 형태로 반환합니다.
 * - 기능 2 (write): 카카오로 로그인된 고객이 남긴 별점과 리뷰 내용을 검증하고 데이터베이스에 안전하게 저장합니다.
 * - 특징: DB에 `reviews` 테이블이 없는 초기 세팅 상태라도, 첫 리뷰 작성 시 자동으로 테이블을 생성하는 무인화 로직이 포함되어 있습니다.
 * - 통신 포맷: 오직 JSON 형식으로만 응답(Response)합니다.
 */

// [버그 수정] common_header.php 등에서 출력되는 불필요한 HTML/CSS 코드가 API 응답에 섞여 들어가 
// 프론트엔드에서 JSON 파싱 에러를 유발하는 것을 방지하기 위해 출력 버퍼링을 시작합니다.
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 버퍼에 쌓인 모든 찌꺼기 문자열(HTML, 공백 등)을 깨끗하게 비웁니다.
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// 프론트엔드에서 요청한 액션(list 또는 write)과 대상 상점의 ID를 수집합니다.
$action = $_POST['action'];
$shop_id = (int)($_POST['shop_id'] ?? 0);

if (!$shop_id) {
    echo json_encode(['status' => 'error', 'message' => 'Shop ID missing']);
    exit;
}

// ---------------------------------------------------------
// [기능 1] 리뷰 목록 조회 (Action: list)
// - 사용자가 '모든 리뷰 보기'를 클릭하거나 '더 보기' 버튼을 눌렀을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'list') {
    $page = (int)($_POST['page'] ?? 1);
    $limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 10;
    $offset = ($page - 1) * $limit;

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, c.nickname AS customer_name, c.profile_img 
            FROM reviews r 
            LEFT JOIN platform_customers c ON r.customer_id = c.id 
            WHERE r.shop_id = ? 
            ORDER BY r.id DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute([$shop_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE shop_id = ?");
        $stmt_count->execute([$shop_id]);
        $total = $stmt_count->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'has_more' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        // DB에 리뷰 테이블이 아직 생성되지 않은 경우 에러를 뿜지 않고 빈 배열을 안전하게 반환합니다.
        echo json_encode(['status' => 'success', 'reviews' => [], 'total' => 0, 'page' => $page, 'has_more' => false]);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2] 신규 리뷰 작성 (Action: write)
// - 고객이 리뷰 작성 모달에서 별점과 내용을 입력하고 전송했을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'write') {
    // [보안] 카카오 로그인 세션이 유지되고 있는지 최우선으로 검증합니다.
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $rating = (int)($_POST['rating'] ?? 5);
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        echo json_encode(['status' => 'error', 'message' => '내용을 입력해주세요.']);
        exit;
    }

    try {
        // [안정성 확보] 신규 입점 상점이거나 초기 서버 세팅 시, 
        // 관리자가 수동으로 테이블을 만들 필요 없이 시스템이 자동으로 테이블 스키마를 구성합니다.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `reviews` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `shop_id` int(11) NOT NULL,
          `customer_id` int(11) NOT NULL,
          `rating` tinyint(1) NOT NULL DEFAULT 5,
          `content` text NOT NULL,
          `owner_reply` text DEFAULT NULL,
          `reply_created_at` datetime DEFAULT NULL,
          `img_path` varchar(255) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `shop_id` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 방금 받은 데이터를 안전하게(Prepared Statement) INSERT 합니다.
        $stmt = $pdo->prepare("INSERT INTO reviews (shop_id, customer_id, rating, content, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$shop_id, $customer_id, $rating, $content]);

        // [텔레그램 알림] 상점주가 '고객 리뷰(review)' 수신에 동의한 경우 발송
        $stmt_tel = $pdo->prepare("SELECT telegram_chat_id, use_telegram_alert, telegram_alert_types, shop_name FROM shops WHERE id = ?");
        $stmt_tel->execute([$shop_id]);
        $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);
        
        if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
            $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
            if (in_array('review', $alert_types)) {
                $star = str_repeat('⭐', $rating);
                $customer_name = $_SESSION['customer_nickname'] ?? '고객';
                $safe_content = htmlspecialchars($content);
                $tel_msg = "📝 <b>[신규 리뷰 등록]</b>\n\n상점: {$tel_info['shop_name']}\n고객: {$customer_name}\n별점: {$star}\n\n<i>\"{$safe_content}\"</i>";
                send_ps24_telegram($tel_msg, $tel_info['telegram_chat_id']);
            }
        }

        echo json_encode(['status' => 'success', 'message' => '리뷰가 성공적으로 등록되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 등록 중 시스템 오류가 발생했습니다.']);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2-1] 리뷰 수정 (Action: update)
// - 고객이 본인이 작성한 리뷰를 수정할 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'update') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $review_id = (int)($_POST['review_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $content = trim($_POST['content'] ?? '');

    if (!$review_id || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => '내용을 입력해주세요.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, content = ? WHERE id = ? AND shop_id = ? AND customer_id = ?");
        $stmt->execute([$rating, $content, $review_id, $shop_id, $customer_id]);
        
        echo json_encode(['status' => 'success', 'message' => '리뷰가 성공적으로 수정되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 수정 중 시스템 오류가 발생했습니다.']);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2-2] 최신 리뷰 실시간 로드 (Action: get_recent)
// - AJAX CRUD 직후 새로고침 없이 메인 화면 리뷰 영역을 갱신하기 위해 사용됩니다.
// ---------------------------------------------------------
if ($action === 'get_recent') {
    try {
        $stmt = $pdo->prepare("SELECT r.*, c.nickname AS customer_name, c.profile_img FROM reviews r LEFT JOIN platform_customers c ON r.customer_id = c.id WHERE r.shop_id = ? ORDER BY r.id DESC LIMIT 5");
        $stmt->execute([$shop_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM reviews WHERE shop_id = ?");
        $stmt_count->execute([$shop_id]);
        $stats = $stmt_count->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'reviews' => $reviews,
            'total_reviews' => (int)$stats['total_reviews'],
            'avg_rating' => round($stats['avg_rating'] ?? 0, 1)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'reviews' => [], 'total_reviews' => 0, 'avg_rating' => 0]);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 3] 작성한 리뷰 삭제 (Action: delete)
// - 고객이 본인이 작성한 리뷰의 삭제 버튼을 클릭했을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'delete') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $review_id = (int)($_POST['review_id'] ?? 0);

    if (!$review_id) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND shop_id = ? AND customer_id = ?");
        $stmt->execute([$review_id, $shop_id, $customer_id]);
        
        echo json_encode(['status' => 'success', 'message' => '리뷰가 삭제되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 삭제 중 오류가 발생했습니다.']);
    }
    exit;
}
=======
<?php

/**
 * [API 핸들러] KShops24 상점 고객 리뷰 처리 엔진 (shop_review_handler.php)
 * 
 * - 필요성: 프론트엔드(shop_view.php)에서 페이지 새로고침 없이(AJAX) 리뷰를 등록하고, 
 *           더 보기(페이징) 기능으로 리뷰 목록을 부드럽게 불러오기 위해 독립된 API 서버 역할을 수행합니다.
 * - 기능 1 (list): 상점의 리뷰 데이터를 10개 단위로 잘라(Pagination) JSON 형태로 반환합니다.
 * - 기능 2 (write): 카카오로 로그인된 고객이 남긴 별점과 리뷰 내용을 검증하고 데이터베이스에 안전하게 저장합니다.
 * - 특징: DB에 `reviews` 테이블이 없는 초기 세팅 상태라도, 첫 리뷰 작성 시 자동으로 테이블을 생성하는 무인화 로직이 포함되어 있습니다.
 * - 통신 포맷: 오직 JSON 형식으로만 응답(Response)합니다.
 */

// [버그 수정] common_header.php 등에서 출력되는 불필요한 HTML/CSS 코드가 API 응답에 섞여 들어가 
// 프론트엔드에서 JSON 파싱 에러를 유발하는 것을 방지하기 위해 출력 버퍼링을 시작합니다.
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

// 버퍼에 쌓인 모든 찌꺼기 문자열(HTML, 공백 등)을 깨끗하게 비웁니다.
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// 프론트엔드에서 요청한 액션(list 또는 write)과 대상 상점의 ID를 수집합니다.
$action = $_POST['action'];
$shop_id = (int)($_POST['shop_id'] ?? 0);

if (!$shop_id) {
    echo json_encode(['status' => 'error', 'message' => 'Shop ID missing']);
    exit;
}

// ---------------------------------------------------------
// [기능 1] 리뷰 목록 조회 (Action: list)
// - 사용자가 '모든 리뷰 보기'를 클릭하거나 '더 보기' 버튼을 눌렀을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'list') {
    $page = (int)($_POST['page'] ?? 1);
    $limit = defined('LISTS_PER_PAGE') ? LISTS_PER_PAGE : 10;
    $offset = ($page - 1) * $limit;

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, c.nickname AS customer_name, c.profile_img 
            FROM reviews r 
            LEFT JOIN platform_customers c ON r.customer_id = c.id 
            WHERE r.shop_id = ? 
            ORDER BY r.id DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute([$shop_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE shop_id = ?");
        $stmt_count->execute([$shop_id]);
        $total = $stmt_count->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'has_more' => ($offset + $limit) < $total
        ]);
    } catch (Exception $e) {
        // DB에 리뷰 테이블이 아직 생성되지 않은 경우 에러를 뿜지 않고 빈 배열을 안전하게 반환합니다.
        echo json_encode(['status' => 'success', 'reviews' => [], 'total' => 0, 'page' => $page, 'has_more' => false]);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2] 신규 리뷰 작성 (Action: write)
// - 고객이 리뷰 작성 모달에서 별점과 내용을 입력하고 전송했을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'write') {
    // [보안] 카카오 로그인 세션이 유지되고 있는지 최우선으로 검증합니다.
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $rating = (int)($_POST['rating'] ?? 5);
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        echo json_encode(['status' => 'error', 'message' => '내용을 입력해주세요.']);
        exit;
    }

    try {
        // [안정성 확보] 신규 입점 상점이거나 초기 서버 세팅 시, 
        // 관리자가 수동으로 테이블을 만들 필요 없이 시스템이 자동으로 테이블 스키마를 구성합니다.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `reviews` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `shop_id` int(11) NOT NULL,
          `customer_id` int(11) NOT NULL,
          `rating` tinyint(1) NOT NULL DEFAULT 5,
          `content` text NOT NULL,
          `owner_reply` text DEFAULT NULL,
          `reply_created_at` datetime DEFAULT NULL,
          `img_path` varchar(255) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `shop_id` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 방금 받은 데이터를 안전하게(Prepared Statement) INSERT 합니다.
        $stmt = $pdo->prepare("INSERT INTO reviews (shop_id, customer_id, rating, content, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$shop_id, $customer_id, $rating, $content]);

        // [텔레그램 알림] 상점주가 '고객 리뷰(review)' 수신에 동의한 경우 발송
        $stmt_tel = $pdo->prepare("SELECT telegram_chat_id, use_telegram_alert, telegram_alert_types, shop_name FROM shops WHERE id = ?");
        $stmt_tel->execute([$shop_id]);
        $tel_info = $stmt_tel->fetch(PDO::FETCH_ASSOC);
        
        if ($tel_info && $tel_info['use_telegram_alert'] === 'Y' && !empty($tel_info['telegram_chat_id'])) {
            $alert_types = explode(',', $tel_info['telegram_alert_types'] ?? '');
            if (in_array('review', $alert_types)) {
                $star = str_repeat('⭐', $rating);
                $customer_name = $_SESSION['customer_nickname'] ?? '고객';
                $safe_content = htmlspecialchars($content);
                $tel_msg = "📝 <b>[신규 리뷰 등록]</b>\n\n상점: {$tel_info['shop_name']}\n고객: {$customer_name}\n별점: {$star}\n\n<i>\"{$safe_content}\"</i>";
                send_ps24_telegram($tel_msg, $tel_info['telegram_chat_id']);
            }
        }

        echo json_encode(['status' => 'success', 'message' => '리뷰가 성공적으로 등록되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 등록 중 시스템 오류가 발생했습니다.']);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2-1] 리뷰 수정 (Action: update)
// - 고객이 본인이 작성한 리뷰를 수정할 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'update') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $review_id = (int)($_POST['review_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 5);
    $content = trim($_POST['content'] ?? '');

    if (!$review_id || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => '내용을 입력해주세요.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, content = ? WHERE id = ? AND shop_id = ? AND customer_id = ?");
        $stmt->execute([$rating, $content, $review_id, $shop_id, $customer_id]);
        
        echo json_encode(['status' => 'success', 'message' => '리뷰가 성공적으로 수정되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 수정 중 시스템 오류가 발생했습니다.']);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 2-2] 최신 리뷰 실시간 로드 (Action: get_recent)
// - AJAX CRUD 직후 새로고침 없이 메인 화면 리뷰 영역을 갱신하기 위해 사용됩니다.
// ---------------------------------------------------------
if ($action === 'get_recent') {
    try {
        $stmt = $pdo->prepare("SELECT r.*, c.nickname AS customer_name, c.profile_img FROM reviews r LEFT JOIN platform_customers c ON r.customer_id = c.id WHERE r.shop_id = ? ORDER BY r.id DESC LIMIT 5");
        $stmt->execute([$shop_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM reviews WHERE shop_id = ?");
        $stmt_count->execute([$shop_id]);
        $stats = $stmt_count->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'reviews' => $reviews,
            'total_reviews' => (int)$stats['total_reviews'],
            'avg_rating' => round($stats['avg_rating'] ?? 0, 1)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'reviews' => [], 'total_reviews' => 0, 'avg_rating' => 0]);
    }
    exit;
}

// ---------------------------------------------------------
// [기능 3] 작성한 리뷰 삭제 (Action: delete)
// - 고객이 본인이 작성한 리뷰의 삭제 버튼을 클릭했을 때 실행됩니다.
// ---------------------------------------------------------
if ($action === 'delete') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => '로그인이 필요합니다.']);
        exit;
    }

    $customer_id = $_SESSION['customer_id'];
    $review_id = (int)($_POST['review_id'] ?? 0);

    if (!$review_id) {
        echo json_encode(['status' => 'error', 'message' => '잘못된 요청입니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND shop_id = ? AND customer_id = ?");
        $stmt->execute([$review_id, $shop_id, $customer_id]);
        
        echo json_encode(['status' => 'success', 'message' => '리뷰가 삭제되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => '리뷰 삭제 중 오류가 발생했습니다.']);
    }
    exit;
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
