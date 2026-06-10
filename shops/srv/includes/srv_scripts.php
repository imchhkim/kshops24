<?php
// [추가] 로그인된 고객의 유효한 예약 내역 조회 (스마트 달력 표시용)
$my_reservations = [];
// [추가] 해당 상점의 날짜/시간별 총 예약 건수 집계 (중복 예약 수 체크용)
$booked_slots = [];
$customer_phone_for_res = preg_replace('/[^0-9]/', '', $_SESSION['customer_ph_phone'] ?? '');

if (isset($pdo) && isset($shop['id'])) {
    $stmt_all_res = $pdo->prepare("SELECT customer_phone, customer_inquiry, inquiry_data FROM shop_inquiries WHERE shop_id = ? AND status IN ('pending', 'contacted', 'confirmed')");
    $stmt_all_res->execute([$shop['id']]);
    $all_res_list = $stmt_all_res->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_res_list as $row) {
        if (preg_match('/\[예약 희망:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s+([0-9]{2}:[0-9]{2})\]/', $row['customer_inquiry'], $matches)) {
            $res_date = $matches[1];
            $res_time = $matches[2];
            
            // 내 예약 데이터 상세 수집
            if (!empty($customer_phone_for_res) && $row['customer_phone'] === $customer_phone_for_res) {
                if (!isset($my_reservations[$res_date])) $my_reservations[$res_date] = [];
                $items = json_decode($row['inquiry_data'], true);
                $item_names = [];
                if (is_array($items)) {
                    foreach ($items as $item) $item_names[] = htmlspecialchars($item['name'] ?? '서비스');
                }
                $my_reservations[$res_date][] = ['time' => $res_time, 'items' => !empty($item_names) ? implode(', ', $item_names) : '서비스'];
            }
            
            // 전체 예약 횟수 집계
            if (!isset($booked_slots[$res_date])) $booked_slots[$res_date] = [];
            if (!isset($booked_slots[$res_date][$res_time])) $booked_slots[$res_date][$res_time] = 0;
            $booked_slots[$res_date][$res_time]++;
        }
    }
}
?>
<script>
    // [핵심 엔진] 전역 설정 객체 선언 (상수 호이스팅 문제 방지를 위해 최상단에 배치)
    // 이제 SRV_CONFIG 대신 이 PS24_SHOP_CONFIG를 공통 모듈과 전용 스크립트에서 참조합니다.
    const PS24_SHOP_CONFIG = {
        shopId: <?php echo (int)($shop['id'] ?? 0); ?>,
        subdomain: '<?php echo htmlspecialchars($subdomain ?? ''); ?>',
        category: 'srv',
        currencySymbol: '<?php echo $currency_symbol ?? '₱'; ?>',
        isCustomerLoggedIn: <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>,
        customerPhone: <?php echo json_encode(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>,
        customerAddress: <?php echo json_encode($_SESSION['customer_ph_address'] ?? ''); ?>,
        customerLandmark: <?php echo json_encode($_SESSION['customer_ph_landmark'] ?? ''); ?>,
        needsPhInfo: <?php echo !empty($needs_ph_info) ? 'true' : 'false'; ?>,
        orderStatusMap: <?php global $SRV_ORDER_STATUS; echo json_encode($SRV_ORDER_STATUS ?? []); ?>,
        reservationSettings: <?php echo !empty($shop['reservation_settings']) ? $shop['reservation_settings'] : '{}'; ?>,
        myReservations: <?php echo json_encode($my_reservations, JSON_UNESCAPED_UNICODE); ?>,
        bookedSlots: <?php echo json_encode($booked_slots, JSON_UNESCAPED_UNICODE); ?>,
        allItemsData: <?php echo json_encode($all_items ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>,
        langWishlistRemoved: "<?php echo addslashes(__('관심목록에서 삭제되었습니다.')); ?>",
        langWishlistAdded: "<?php echo addslashes(__('관심 서비스에 추가되었습니다.')); ?>",
        langEnterPhoneToSearch: "<?php echo addslashes(__('전화번호를 입력하고 조회 버튼을 눌러주세요.')); ?>"
    };

    /**
     * [신규] '나의 예약' 버튼 클릭 시 실행되는 분기 함수
     * - 고객 전화번호가 세션/쿠키에 있으면 바로 '나의 예약 내역' 모달을 엽니다.
     * - 없으면 '로그인 방법 선택' 모달을 먼저 띄웁니다.
     */
    function checkAndOpenHistoryModal() {
        if (PS24_SHOP_CONFIG.customerPhone && PS24_SHOP_CONFIG.customerPhone.trim() !== '') {
            openServiceInquiryHistoryModal();
        } else {
            const loginModal = document.getElementById('loginChoiceModal');
            if (loginModal) {
                bootstrap.Modal.getOrCreateInstance(loginModal).show();
            }
        }
    }

    /**
     * [신규] '로그인 없이 계속하기' 버튼 클릭 시 실행되는 함수
     * 로그인 선택 모달을 닫고, 비회원 상태로 '나의 예약 내역' 모달을 엽니다.
     */
    function continueWithoutLogin() {
        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginChoiceModal'));
        if (loginModal) loginModal.hide();
        openServiceInquiryHistoryModal();
    }
</script>