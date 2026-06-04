<?php
/**
 * KShops24 공지사항 상세 보기 (notice_view.php)
 * 설명: 서버 루트 경로를 기준으로 DB 설정을 로드하고 공지사항 데이터를 처리합니다.
 */

// 1. 서버 루트 절대 경로 확보
$root_path = $_SERVER['DOCUMENT_ROOT']; 

// 2. 루트 경로 기반으로 db_config.php 로드
$db_config_file = $root_path . '/db_config.php';

if (file_exists($db_config_file)) {
    require_once $db_config_file;
} else {
    die("데이터베이스 설정 파일을 찾을 수 없습니다. (확인된 경로: $db_config_file)");
}

// [섹션 1] 파라미터 수신 및 기본값 설정
// GET 방식으로 전달된 id를 정수형(int)으로 강제 변환하여 SQL 인젝션을 방지합니다.
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$notice = null;

// [섹션 2] 데이터베이스 연동 (조회수 업데이트 및 데이터 로드)
if ($id > 0) {
    try {
        // 1. 조회수(hit) 1 증가 업데이트
        $pdo->prepare("UPDATE shop_board SET hit = hit + 1 WHERE id = ?")->execute([$id]);
        
        // 2. 해당 게시글 상세 정보 조회 (shop_id가 0인 전체 공지만 제한)
        $stmt = $pdo->prepare("SELECT * FROM shop_board WHERE id = ? AND type = 'notice' AND shop_id = 0");
        $stmt->execute([$id]);
        $notice = $stmt->fetch();
    } catch (PDOException $e) {
        // DB 에러 시 조용히 처리하거나 에러 로그를 남길 수 있습니다.
    }
}

// [섹션 3] 게시글 존재 여부 예외 처리
// ID가 없거나 DB에 해당 데이터가 없는 경우 경고창을 띄우고 이전 페이지로 보냅니다.
if (!$notice) {
    echo "<script>alert('존재하지 않는 게시글입니다.'); history.back();</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($notice['title']); ?> - KShops24 공지사항</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* 상세 페이지 전용 커스텀 스타일 */
        body { background-color: #f4f7f9; font-family: 'Apple SD Gothic Neo', sans-serif; }
        /* 본문 카드를 돋보이게 하는 그림자 및 라운드 처리 */
        .view-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        /* 본문 영역: 줄바꿈 허용(pre-wrap) 및 가독성 높은 행간 설정 */
        .content-area { line-height: 1.8; font-size: 1.1rem; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-9">
            
            <div class="mb-4">
                <a href="index.php" class="text-decoration-none text-muted">
                    <i class="bi bi-arrow-left"></i> KShops24로 돌아가기
                </a>
            </div>

            <div class="card view-card p-4 p-md-5">
                <header class="border-bottom pb-4 mb-4">
                    <span class="badge bg-primary mb-2">공지사항</span>
                    <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($notice['title']); ?></h2>
                    <div class="d-flex text-muted small">
                        <span class="me-3"><i class="bi bi-person me-1"></i><?php echo ($notice['sender_type'] === 'admin' ? 'KShops24 관리자' : '상점주'); ?></span>
                        <span class="me-3"><i class="bi bi-calendar3 me-1"></i><?php echo substr($notice['created_at'], 0, 10); ?></span>
                        <span><i class="bi bi-eye me-1"></i><?php echo $notice['hit']; ?></span>
                    </div>
                </header>

                <article class="content-area text-dark">
                    <?php echo htmlspecialchars(trim($notice['content'])); ?>
                </article>

                <footer class="mt-5 pt-4 border-top text-center">
                    <a href="index.php" class="btn btn-outline-primary rounded-pill px-4">목록으로 돌아가기</a>
                </footer>
            </div>
            
        </div>
    </div>
</div>

</body>
</html>