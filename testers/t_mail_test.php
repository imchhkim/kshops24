<?php

require_once __DIR__ . '/t_common.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// POST 요청이 있을 때만 메일 발송 로직 실행
$mail_sent_result = null;
$to_email = $_POST['to_email'] ?? 'imchhkim@gmail.com'; // 폼 데이터 유지 및 기본값 설정

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = "=?UTF-8?B?" . base64_encode("[KShops24] 이메일 1초 수신 테스트") . "?=";
    $message = "
    <!DOCTYPE html>
    <html lang='ko'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='background:#f4f7f9; padding:15px; margin:0;'>
        <div style='background:#fff; padding:20px; border-radius:10px; width:100%; max-width:650px; margin:0 auto; box-sizing:border-box;'>
            <h2 style='color:#004aad; margin-top:0;'>이메일 수신 성공!</h2>
            <p style='margin-bottom:0;'>서버에서 HTML 이메일이 정상적으로 전송되었습니다.</p>
        </div>
    </body>
    </html>
    ";

    $from = "support@kshops24.com";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: KShops24 <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // 호스팅 서버의 HTML 태그 필터링을 피하기 위해 Base64로 인코딩 후 발송
    $encoded_message = chunk_split(base64_encode($message));
    $mail_sent_result = @mail($to_email, $subject, $encoded_message, $headers);
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이메일 발송 진단</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="bg-light">
    <div class="container mt-5" style="max-width: 700px;">
        <div class="card shadow-sm mb-4 border-start border-5 border-primary">
            <div class="card-body p-4">
                <h4 class="card-title fw-bold text-primary mb-2"><i class="bi bi-envelope-check me-2"></i>이메일 발송 테스트
                    (t_mail_test.php)</h4>
                <p class="card-text text-muted small">서버의 PHP `mail()` 함수가 정상적으로 동작하는지, 그리고 외부 메일 서버(Gmail 등)에서 스팸으로
                    처리하지 않고 잘 수신되는지 빠르게 테스트합니다. 아래 입력창에 테스트 메일을 받을 주소를 입력하고 '테스트 메일 보내기' 버튼을 누르세요.</p>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label for="to_email" class="form-label fw-bold">받는 사람 이메일 주소</label>
                        <input type="email" class="form-control" id="to_email" name="to_email"
                            value="<?= htmlspecialchars($to_email) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="bi bi-send-fill me-2"></i>테스트 메일 보내기
                    </button>
                </form>
            </div>
        </div>

        <?php if ($mail_sent_result !== null): ?>
        <div class='card shadow-sm'>
            <div class='card-body p-4'>
                <h5 class='card-title fw-bold mb-3'><i class='bi bi-search me-2'></i>진단 결과</h5>
                <hr style='border: 1px solid #eee; margin: 20px 0;'>

                <?php if ($mail_sent_result): ?>
                <div class='alert alert-success border-0'>
                    <h5 class='alert-heading fw-bold'><i class='bi bi-check-circle-fill me-2'></i>PHP mail() 함수 정상 동작!
                        (서버 발송 성공)</h5>
                    <p>호스팅 서버에서는 메일을 성공적으로 외부로 발송했습니다.</p>
                    <hr>
                    <p class='mb-0 small'><b>만약 메일함(또는 스팸함)에 오지 않는다면?</b><br>이는 100% Gmail의 스팸 방지 정책(SPF/DKIM 인증 실패)에 걸려
                        공중에서 조용히 삭제(Drop)된 것입니다. 이 경우, PHP 코드 수정으로는 해결이 불가능하며 <b>'SMTP 메일 연동 방식'</b>으로 서버 구조를 변경해야만 완벽하게
                        해결됩니다.</p>
                </div>
                <?php else: ?>
                <?php $error = error_get_last(); ?>
                <div class='alert alert-danger border-0'>
                    <h5 class='alert-heading fw-bold'><i class='bi bi-x-octagon-fill me-2'></i>PHP mail() 함수 발송 실패 (호스팅
                        서버 단 차단)</h5>
                    <p>호스팅 서버 자체에서 메일 발송을 차단했거나 설정에 문제가 있습니다. 아래 오류 메시지를 확인하세요.</p>
                    <pre class='bg-dark text-white p-3 rounded mt-3'
                        style='white-space: pre-wrap; word-wrap: break-word;'><?= print_r($error, true) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>

</html>