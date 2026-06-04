<<<<<<< HEAD
<script>
    // [핵심 엔진] 전역 설정 객체 선언 (상수 호이스팅 방지 및 통합)
    const PS24_SHOP_CONFIG = {
        shopId: <?php echo (int)($shop['id'] ?? 0); ?>,
        subdomain: '<?php echo htmlspecialchars($subdomain ?? ''); ?>',
        category: 'fnb',
        currencySymbol: '<?php echo $currency_symbol ?? '₱'; ?>',
        freeDeliveryAmount: <?php echo (int)($shop['free_delivery_amount'] ?? 0); ?>,
        deliveryFee: <?php echo (int)($shop['delivery_fee'] ?? 0); ?>,
        isDeliveryAvailable: <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'true' : 'false'; ?>,
        isPickupAvailable: <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'true' : 'false'; ?>,
        isCustomerLoggedIn: <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>,
        customerPhone: <?php echo json_encode(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>,
        customerAddress: <?php echo json_encode($_SESSION['customer_ph_address'] ?? ''); ?>,
        customerLandmark: <?php echo json_encode($_SESSION['customer_ph_landmark'] ?? ''); ?>,
        needsPhInfo: <?php echo !empty($needs_ph_info) ? 'true' : 'false'; ?>,
        orderStatusMap: <?php
                        global $FNB_ORDER_STATUS;
                        $translated_map = [];
                        if (!empty($FNB_ORDER_STATUS) && is_array($FNB_ORDER_STATUS)) {
                            foreach ($FNB_ORDER_STATUS as $key => $val) {
                                $translated_map[$key] = [
                                    'text' => isset($val['text']) ? __($val['text']) : '',
                                    'class' => $val['class'] ?? 'secondary'
                                ];
                            }
                        }
                        echo json_encode($translated_map); ?>,
        langReorder: <?php echo json_encode(__("동일한 주문을 카트에 넣기"), JSON_UNESCAPED_UNICODE); ?>,
        langFreeDeliveryMore: <?php echo json_encode(__("만 더 담으면 배달료가 무료!"), JSON_UNESCAPED_UNICODE); ?>,
        langFreeDeliverySuccess: <?php echo json_encode(__("축하합니다! <strong>무료 배달</strong>이 적용됩니다."), JSON_UNESCAPED_UNICODE); ?>,
        langProductAmount: <?php echo json_encode(__("상품 금액"), JSON_UNESCAPED_UNICODE); ?>,
        langDeliveryFee: <?php echo json_encode(__("배달료"), JSON_UNESCAPED_UNICODE); ?>,
        langTotalAmount: <?php echo json_encode(__("총 결제 예정 금액"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCookingTitle: <?php echo json_encode(__("주문하신 음식이 맛있게 요리 중입니다! 👨‍🍳"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCookingDesc: <?php echo json_encode(__("주문하신 음식을 정성껏 만들고 있습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langAlertDeliveryTitle: <?php echo json_encode(__("주문하신 음식 배달을 시작하였습니다! 🛵"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertDeliveryDesc: <?php echo json_encode(__("맛있는 음식이 고객님을 향해 출발했습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCompletedTitle: <?php echo json_encode(__("주문이 완료되었습니다! 🙏"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCompletedDesc: <?php echo json_encode(__("주문이 완료 되었습니다. 저희 상점을 이용해 주셔서 감사합니다 !!!"), JSON_UNESCAPED_UNICODE); ?>,
        langAddWishlist: <?php echo json_encode(__("찜하기"), JSON_UNESCAPED_UNICODE); ?>,
        langRemoveWishlist: <?php echo json_encode(__("찜취소"), JSON_UNESCAPED_UNICODE); ?>,
        langEnterPhoneToSearch: <?php echo json_encode(__("전화번호를 입력하고 조회 버튼을 눌러주세요."), JSON_UNESCAPED_UNICODE); ?>,
        langCartEmpty: <?php echo json_encode(__("카트가 비어 있습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langDiscount: <?php echo json_encode(__("할인"), JSON_UNESCAPED_UNICODE); ?>,
        langInvalidPhone: <?php echo json_encode(__("올바른 필리핀 번호 형식이 아닙니다."), JSON_UNESCAPED_UNICODE); ?>,
        langLoading: <?php echo json_encode(__("내역을 불러오는 중입니다..."), JSON_UNESCAPED_UNICODE); ?>,
        langNoOrderRecord: <?php echo json_encode(__("해당 번호로는 주문 기록이 없습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langCommError: <?php echo json_encode(__("서버 통신 중 오류가 발생했습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langCannotCancel: <?php echo json_encode(__("요리 중 혹은 배달 중에는 주문 취소가 불가능 합니다."), JSON_UNESCAPED_UNICODE); ?>,
        langReorderConfirm: <?php echo json_encode(__("이전 주문 항목들을 카트에 담으시겠습니까?"), JSON_UNESCAPED_UNICODE); ?>,
        langMenuReadyTitle: <?php echo json_encode(__("메뉴가 준비되었습니다! 🛍️"), JSON_UNESCAPED_UNICODE); ?>,
        langMenuReadyDesc: <?php echo json_encode(__("주문하신 메뉴가 포장 완료되었습니다. 매장으로 방문해주세요."), JSON_UNESCAPED_UNICODE); ?>,
        langNoActiveOrder: <?php echo json_encode(__("진행 중인 주문 내역이 없습니다."), JSON_UNESCAPED_UNICODE); ?>,
        allItemsData: <?php
            $menu_map = [];
            foreach ($all_menus as $m) {
                $m['item_name'] = function_exists('t_db') ? t_db($m['item_name'], $m['translations'] ?? '', 'item_name') : $m['item_name'];
                $m['item_info'] = function_exists('t_db') ? t_db($m['item_info'], $m['translations'] ?? '', 'item_info') : $m['item_info'];
                $menu_map[$m['id']] = $m;
            }
            echo json_encode($menu_map, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>
    };
=======
<script>
    // [핵심 엔진] 전역 설정 객체 선언 (상수 호이스팅 방지 및 통합)
    const PS24_SHOP_CONFIG = {
        shopId: <?php echo (int)($shop['id'] ?? 0); ?>,
        subdomain: '<?php echo htmlspecialchars($subdomain ?? ''); ?>',
        category: 'fnb',
        currencySymbol: '<?php echo $currency_symbol ?? '₱'; ?>',
        freeDeliveryAmount: <?php echo (int)($shop['free_delivery_amount'] ?? 0); ?>,
        deliveryFee: <?php echo (int)($shop['delivery_fee'] ?? 0); ?>,
        isDeliveryAvailable: <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'true' : 'false'; ?>,
        isPickupAvailable: <?php echo (($shop['is_delivery_available'] ?? 1) == 1) ? 'true' : 'false'; ?>,
        isCustomerLoggedIn: <?php echo !empty($is_customer_logged_in) ? 'true' : 'false'; ?>,
        customerPhone: <?php echo json_encode(function_exists('formatPHPhone') ? formatPHPhone($_SESSION['customer_ph_phone'] ?? '') : ($_SESSION['customer_ph_phone'] ?? '')); ?>,
        customerAddress: <?php echo json_encode($_SESSION['customer_ph_address'] ?? ''); ?>,
        customerLandmark: <?php echo json_encode($_SESSION['customer_ph_landmark'] ?? ''); ?>,
        needsPhInfo: <?php echo !empty($needs_ph_info) ? 'true' : 'false'; ?>,
        orderStatusMap: <?php
                        global $FNB_ORDER_STATUS;
                        $translated_map = [];
                        if (!empty($FNB_ORDER_STATUS) && is_array($FNB_ORDER_STATUS)) {
                            foreach ($FNB_ORDER_STATUS as $key => $val) {
                                $translated_map[$key] = [
                                    'text' => isset($val['text']) ? __($val['text']) : '',
                                    'class' => $val['class'] ?? 'secondary'
                                ];
                            }
                        }
                        echo json_encode($translated_map); ?>,
        langReorder: <?php echo json_encode(__("동일한 주문을 카트에 넣기"), JSON_UNESCAPED_UNICODE); ?>,
        langFreeDeliveryMore: <?php echo json_encode(__("만 더 담으면 배달료가 무료!"), JSON_UNESCAPED_UNICODE); ?>,
        langFreeDeliverySuccess: <?php echo json_encode(__("축하합니다! <strong>무료 배달</strong>이 적용됩니다."), JSON_UNESCAPED_UNICODE); ?>,
        langProductAmount: <?php echo json_encode(__("상품 금액"), JSON_UNESCAPED_UNICODE); ?>,
        langDeliveryFee: <?php echo json_encode(__("배달료"), JSON_UNESCAPED_UNICODE); ?>,
        langTotalAmount: <?php echo json_encode(__("총 결제 예정 금액"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCookingTitle: <?php echo json_encode(__("주문하신 음식이 맛있게 요리 중입니다! 👨‍🍳"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCookingDesc: <?php echo json_encode(__("주문하신 음식을 정성껏 만들고 있습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langAlertDeliveryTitle: <?php echo json_encode(__("주문하신 음식 배달을 시작하였습니다! 🛵"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertDeliveryDesc: <?php echo json_encode(__("맛있는 음식이 고객님을 향해 출발했습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCompletedTitle: <?php echo json_encode(__("주문이 완료되었습니다! 🙏"), JSON_UNESCAPED_UNICODE); ?>,
        langAlertCompletedDesc: <?php echo json_encode(__("주문이 완료 되었습니다. 저희 상점을 이용해 주셔서 감사합니다 !!!"), JSON_UNESCAPED_UNICODE); ?>,
        langAddWishlist: <?php echo json_encode(__("찜하기"), JSON_UNESCAPED_UNICODE); ?>,
        langRemoveWishlist: <?php echo json_encode(__("찜취소"), JSON_UNESCAPED_UNICODE); ?>,
        langEnterPhoneToSearch: <?php echo json_encode(__("전화번호를 입력하고 조회 버튼을 눌러주세요."), JSON_UNESCAPED_UNICODE); ?>,
        langCartEmpty: <?php echo json_encode(__("카트가 비어 있습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langDiscount: <?php echo json_encode(__("할인"), JSON_UNESCAPED_UNICODE); ?>,
        langInvalidPhone: <?php echo json_encode(__("올바른 필리핀 번호 형식이 아닙니다."), JSON_UNESCAPED_UNICODE); ?>,
        langLoading: <?php echo json_encode(__("내역을 불러오는 중입니다..."), JSON_UNESCAPED_UNICODE); ?>,
        langNoOrderRecord: <?php echo json_encode(__("해당 번호로는 주문 기록이 없습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langCommError: <?php echo json_encode(__("서버 통신 중 오류가 발생했습니다."), JSON_UNESCAPED_UNICODE); ?>,
        langCannotCancel: <?php echo json_encode(__("요리 중 혹은 배달 중에는 주문 취소가 불가능 합니다."), JSON_UNESCAPED_UNICODE); ?>,
        langReorderConfirm: <?php echo json_encode(__("이전 주문 항목들을 카트에 담으시겠습니까?"), JSON_UNESCAPED_UNICODE); ?>,
        langMenuReadyTitle: <?php echo json_encode(__("메뉴가 준비되었습니다! 🛍️"), JSON_UNESCAPED_UNICODE); ?>,
        langMenuReadyDesc: <?php echo json_encode(__("주문하신 메뉴가 포장 완료되었습니다. 매장으로 방문해주세요."), JSON_UNESCAPED_UNICODE); ?>,
        langNoActiveOrder: <?php echo json_encode(__("진행 중인 주문 내역이 없습니다."), JSON_UNESCAPED_UNICODE); ?>,
        allItemsData: <?php
            $menu_map = [];
            foreach ($all_menus as $m) {
                $m['item_name'] = function_exists('t_db') ? t_db($m['item_name'], $m['translations'] ?? '', 'item_name') : $m['item_name'];
                $m['item_info'] = function_exists('t_db') ? t_db($m['item_info'], $m['translations'] ?? '', 'item_info') : $m['item_info'];
                $menu_map[$m['id']] = $m;
            }
            echo json_encode($menu_map, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>
    };
>>>>>>> e04269f51dc7843a6d850f7c2f789be87b1eb50e
</script>