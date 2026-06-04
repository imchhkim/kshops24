<<<<<<< HEAD
<script>
    // 부동산 전용 JS 모듈 연동을 위한 환경 설정 데이터 (동적 PHP 데이터)
    const REALTY_CONFIG = {
        shopId: <?php echo (int)($shop['id'] ?? 0); ?>,
        currencySymbol: '<?php echo $currency_symbol; ?>',
        freeDeliveryAmount: 0, // 부동산에는 불필요
        deliveryFee: 0,
        isCustomerLoggedIn: <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>,
        customerPhone: <?php echo json_encode(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>,
        customerAddress: <?php echo json_encode($_SESSION['customer_ph_address'] ?? ''); ?>,
        customerLandmark: <?php echo json_encode($_SESSION['customer_ph_landmark'] ?? ''); ?>,
        needsPhInfo: <?php echo !empty($needs_ph_info) ? 'true' : 'false'; ?>,
        orderStatusMap: <?php global $REALTY_ORDER_STATUS; echo json_encode($REALTY_ORDER_STATUS ?? []); ?>,
        langWishlistRemoved: "<?php echo addslashes(__('관심목록에서 삭제되었습니다.')); ?>",
        langWishlistAdded: "<?php echo addslashes(__('관심 매물에 추가되었습니다.')); ?>",
        langEnterPhoneToSearch: "<?php echo addslashes(__('전화번호를 입력하고 조회 버튼을 눌러주세요.')); ?>",
        langWishlistEmpty: "<?php echo addslashes(__('관심목록이 비어있습니다.')); ?>",
        langDiscount: "<?php echo addslashes(__('할인')); ?>",
        langDeleteSuccess: "<?php echo addslashes(__('문의 내역이 성공적으로 삭제되었습니다.')); ?>",
        langCancelSuccess: "<?php echo addslashes(__('상담이 성공적으로 취소되었습니다.')); ?>",
        langCommError: "<?php echo addslashes(__('서버 통신 중 오류가 발생했습니다.')); ?>",
        langLoading: "<?php echo addslashes(__('내역을 불러오는 중입니다...')); ?>",
        // [추가] 부동산 매물 배지(Badge) 상태 다국어 딕셔너리
        langBadges: {
            "매매": "<?php echo addslashes(__('매매')); ?>",
            "임대": "<?php echo addslashes(__('임대')); ?>",
            "급매": "<?php echo addslashes(__('급매')); ?>",
            "추천": "<?php echo addslashes(__('추천')); ?>",
            "신규": "<?php echo addslashes(__('신규')); ?>",
            "거래완료": "<?php echo addslashes(__('거래완료')); ?>"
        }
    };
=======
<script>
    // 부동산 전용 JS 모듈 연동을 위한 환경 설정 데이터 (동적 PHP 데이터)
    const REALTY_CONFIG = {
        shopId: <?php echo (int)($shop['id'] ?? 0); ?>,
        currencySymbol: '<?php echo $currency_symbol; ?>',
        freeDeliveryAmount: 0, // 부동산에는 불필요
        deliveryFee: 0,
        isCustomerLoggedIn: <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>,
        customerPhone: <?php echo json_encode(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>,
        customerAddress: <?php echo json_encode($_SESSION['customer_ph_address'] ?? ''); ?>,
        customerLandmark: <?php echo json_encode($_SESSION['customer_ph_landmark'] ?? ''); ?>,
        needsPhInfo: <?php echo !empty($needs_ph_info) ? 'true' : 'false'; ?>,
        orderStatusMap: <?php global $REALTY_ORDER_STATUS; echo json_encode($REALTY_ORDER_STATUS ?? []); ?>,
        langWishlistRemoved: "<?php echo addslashes(__('관심목록에서 삭제되었습니다.')); ?>",
        langWishlistAdded: "<?php echo addslashes(__('관심 매물에 추가되었습니다.')); ?>",
        langEnterPhoneToSearch: "<?php echo addslashes(__('전화번호를 입력하고 조회 버튼을 눌러주세요.')); ?>",
        langWishlistEmpty: "<?php echo addslashes(__('관심목록이 비어있습니다.')); ?>",
        langDiscount: "<?php echo addslashes(__('할인')); ?>",
        langDeleteSuccess: "<?php echo addslashes(__('문의 내역이 성공적으로 삭제되었습니다.')); ?>",
        langCancelSuccess: "<?php echo addslashes(__('상담이 성공적으로 취소되었습니다.')); ?>",
        langCommError: "<?php echo addslashes(__('서버 통신 중 오류가 발생했습니다.')); ?>",
        langLoading: "<?php echo addslashes(__('내역을 불러오는 중입니다...')); ?>",
        // [추가] 부동산 매물 배지(Badge) 상태 다국어 딕셔너리
        langBadges: {
            "매매": "<?php echo addslashes(__('매매')); ?>",
            "임대": "<?php echo addslashes(__('임대')); ?>",
            "급매": "<?php echo addslashes(__('급매')); ?>",
            "추천": "<?php echo addslashes(__('추천')); ?>",
            "신규": "<?php echo addslashes(__('신규')); ?>",
            "거래완료": "<?php echo addslashes(__('거래완료')); ?>"
        }
    };
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>