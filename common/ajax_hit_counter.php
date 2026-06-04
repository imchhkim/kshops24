<<<<<<< HEAD
<?php
/**
 * 공지사항 조회수 업데이트 전용 AJAX 핸들러
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // shop_id가 0인 전체 공지사항의 조회수만 업데이트
        $stmt = $pdo->prepare("UPDATE shop_board SET hit = hit + 1 WHERE id = ? AND shop_id = 0");
        $stmt->execute([$id]);
        echo "SUCCESS";
    } catch (Exception $e) {
        echo "ERROR";
    }
}
=======
<?php
/**
 * 공지사항 조회수 업데이트 전용 AJAX 핸들러
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/common/common_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // shop_id가 0인 전체 공지사항의 조회수만 업데이트
        $stmt = $pdo->prepare("UPDATE shop_board SET hit = hit + 1 WHERE id = ? AND shop_id = 0");
        $stmt->execute([$id]);
        echo "SUCCESS";
    } catch (Exception $e) {
        echo "ERROR";
    }
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
exit;