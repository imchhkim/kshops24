<?php
// hash_gen.php
// 파트너님이 사용할 실제 비밀번호를 아래 적으세요.
$my_password = "admin1234"; 
echo password_hash($my_password, PASSWORD_DEFAULT);
?>