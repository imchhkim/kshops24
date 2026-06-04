<<<<<<< HEAD
<?php
/**
 * KShops24 공통 실행 엔진 (lib_notice.php)
 * [공통 함수] 부트스트랩 알림 메시지 생성
 */

// 공지사항 데이터를 가져오는 전용 함수
function getNotices($pdo, $board_id = 'notice') {
    $sql = "SELECT * FROM shop_board WHERE shop_id = 0 AND type = ? ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$board_id]);
    return $stmt->fetchAll();
}

// 공지사항 삭제 로직
function deleteNotice($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = 0");
    return $stmt->execute([$id]);
}
=======
<?php
/**
 * KShops24 공통 실행 엔진 (lib_notice.php)
 * [공통 함수] 부트스트랩 알림 메시지 생성
 */

// 공지사항 데이터를 가져오는 전용 함수
function getNotices($pdo, $board_id = 'notice') {
    $sql = "SELECT * FROM shop_board WHERE shop_id = 0 AND type = ? ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$board_id]);
    return $stmt->fetchAll();
}

// 공지사항 삭제 로직
function deleteNotice($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM shop_board WHERE id = ? AND shop_id = 0");
    return $stmt->execute([$id]);
}
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
?>