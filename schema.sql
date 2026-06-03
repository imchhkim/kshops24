-- 테이블 구조: `admins`
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` varchar(50) NOT NULL COMMENT '관리자 로그인 아이디',
  `admin_pass` varchar(255) NOT NULL COMMENT '해시화된 비밀번호',
  `admin_name` varchar(50) DEFAULT 'Master Admin',
  `admin_kakao_id` varchar(50) DEFAULT NULL COMMENT '알림수신용 카카오ID',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `platform_customers`
CREATE TABLE `platform_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kakao_id` varchar(100) DEFAULT NULL COMMENT '카카오 고유 ID',
  `nickname` varchar(100) NOT NULL COMMENT '고객 닉네임',
  `profile_img` varchar(255) DEFAULT NULL COMMENT '프로필 이미지 URL',
  `ph_phone` varchar(20) DEFAULT NULL COMMENT '필리핀 폰번호 (전역 식별자)',
  `ph_address` varchar(255) DEFAULT NULL COMMENT '최근 기본 배달 주소',
  `ph_landmark` varchar(255) DEFAULT NULL COMMENT '최근 배달 랜드마크',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ph_phone` (`ph_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='플랫폼 통합 고객';

-- 테이블 구조: `reviews`
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5,
  `content` text NOT NULL,
  `owner_reply` text DEFAULT NULL,
  `reply_created_at` datetime DEFAULT NULL,
  `img_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `fk_reviews_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `shop_board`
CREATE TABLE `shop_board` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 번호',
  `shop_id` int(11) NOT NULL DEFAULT 0 COMMENT '관련 상점 ID (0: 전체공지)',
  `parent_id` int(11) NOT NULL DEFAULT 0 COMMENT '부모글 ID (답글 그룹화)',
  `type` enum('notice','message','email_log') NOT NULL DEFAULT 'message' COMMENT '홈페이지나 상점에 보내는 메시지/이메일',
  `sender_type` enum('admin','shop') NOT NULL COMMENT '작성 주체 (admin/shop)',
  `title` varchar(255) NOT NULL COMMENT '제목',
  `content` text NOT NULL COMMENT '내용',
  `hit` int(11) DEFAULT 0,
  `file_origin_name` varchar(255) DEFAULT NULL COMMENT '사용자가 올린 원래 파일명',
  `file_stored_path` varchar(255) DEFAULT NULL COMMENT '서버에 저장된 실제 경로/파일명',
  `file_size` int(11) DEFAULT 0 COMMENT '파일 용량(Byte)',
  `file_type` varchar(50) DEFAULT NULL COMMENT '파일 확장자 또는 MIME타입',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '읽음 여부',
  `read_at` datetime DEFAULT NULL COMMENT '읽은 시간',
  `is_secret` tinyint(1) DEFAULT 0 COMMENT '비밀글 여부',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '작성일',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일',
  PRIMARY KEY (`id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_type` (`type`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='kshops24 소통 및 파일첨부 게시판';

-- 테이블 구조: `shop_customer_mapping`
CREATE TABLE `shop_customer_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `last_login_at` datetime DEFAULT current_timestamp() COMMENT '해당 상점 최근 로그인일',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '해당 상점 최초 유입일(가입일)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shop_customer` (`shop_id`,`customer_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `shop_customer_mapping_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shop_customer_mapping_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `platform_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='상점별 단골 고객 목록';

-- 테이블 구조: `shop_customers_backup`
CREATE TABLE `shop_customers_backup` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL COMMENT '소속 상점 ID',
  `kakao_id` varchar(255) NOT NULL COMMENT '카카오 고유 식별값',
  `nickname` varchar(100) DEFAULT NULL COMMENT '카카오 닉네임',
  `profile_img` varchar(255) DEFAULT NULL COMMENT '카카오 프로필 이미지',
  `email` varchar(100) DEFAULT NULL COMMENT '카카오 계정 이메일',
  `ph_phone` varchar(20) DEFAULT NULL COMMENT '필리핀 현지 전화번호',
  `ph_address` text DEFAULT NULL COMMENT '필리핀 배달 주소',
  `ph_landmark` varchar(255) DEFAULT NULL COMMENT '필리핀 배달 랜드마크',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ph_name` varchar(50) DEFAULT NULL COMMENT '고객 이름',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_shop_kakao` (`shop_id`,`kakao_id`),
  KEY `idx_shop_id` (`shop_id`),
  CONSTRAINT `fk_shop_customers_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='상점별 카카오 로그인 고객 정보';

-- 테이블 구조: `shop_images`
CREATE TABLE `shop_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `img_path` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `shop_images_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `shop_inquiries`
CREATE TABLE `shop_inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_inquiry` text DEFAULT NULL,
  `owner_reply` text DEFAULT NULL,
  `owner_memo` text DEFAULT NULL,
  `inquiry_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '관심 매물 배열' CHECK (json_valid(`inquiry_data`)),
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_phone` (`shop_id`,`customer_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shop_item_boards`
CREATE TABLE `shop_item_boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `board_img_path` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `shop_item_boards_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shop_item_categories`
CREATE TABLE `shop_item_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `cat_name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `translations` text DEFAULT NULL COMMENT '다국어 자동번역 데이터(JSON)',
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `shop_item_categories_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `shop_items`
CREATE TABLE `shop_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `cat_id` int(11) DEFAULT NULL,
  `trade_type` varchar(50) NOT NULL DEFAULT '방문 서비스' COMMENT '거래 유형',
  `item_name` varchar(100) NOT NULL,
  `item_price` varchar(50) DEFAULT NULL,
  `item_discount_price` int(11) DEFAULT 0,
  `item_discount_rate` int(11) DEFAULT 0,
  `item_info` text DEFAULT NULL,
  `translations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`translations`)),
  `item_img` text DEFAULT NULL,
  `item_youtube_url` text DEFAULT NULL COMMENT '유튜브 동영상 링크 (JSON 배열)',
  `sort_order` int(11) DEFAULT 0,
  `is_best` tinyint(1) DEFAULT 0,
  `is_new` tinyint(1) DEFAULT 0,
  `is_soldout` tinyint(1) DEFAULT 0,
  `is_hide` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `wish_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  KEY `fk_item_category` (`cat_id`),
  CONSTRAINT `fk_item_category` FOREIGN KEY (`cat_id`) REFERENCES `shop_item_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shop_items_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=612 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shop_order_items`
CREATE TABLE `shop_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '주문 마스터 ID',
  `item_id` int(11) DEFAULT NULL COMMENT '메뉴 ID (삭제될 수 있으므로 NULL 허용)',
  `item_name` varchar(255) NOT NULL COMMENT '주문 당시 메뉴명',
  `price` int(11) NOT NULL COMMENT '주문 당시 단가',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '주문 수량',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  CONSTRAINT `fk_shop_fnb_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shop_orders`
CREATE TABLE `shop_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL COMMENT '상점 ID',
  `order_no` varchar(20) NOT NULL COMMENT '주문번호 (YYYYMMDD-순번)',
  `customer_phone` varchar(20) NOT NULL COMMENT '고객 연락처',
  `customer_address` text NOT NULL COMMENT '배송 주소',
  `customer_landmark` varchar(255) DEFAULT NULL COMMENT '랜드마크(선택)',
  `total_price` int(11) NOT NULL DEFAULT 0 COMMENT '총 주문 금액',
  `order_type` varchar(20) NOT NULL DEFAULT 'delivery',
  `pickup_time` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `payment_detail` varchar(255) DEFAULT NULL,
  `status` enum('pending','confirmed','cooking','delivery','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT '상태',
  `is_deleted_by_customer` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `cancel_reason` varchar(255) DEFAULT NULL,
  `customer_lat` decimal(10,8) DEFAULT NULL COMMENT '고객 위도',
  `customer_lng` decimal(11,8) DEFAULT NULL COMMENT '고객 경도',
  PRIMARY KEY (`id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_order_no` (`order_no`),
  CONSTRAINT `fk_shop_fnb_orders_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shop_payments`
CREATE TABLE `shop_payments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '고유 번호',
  `shop_id` int(11) NOT NULL COMMENT '상점 ID',
  `pay_type` enum('setup','4months_free','6months','monthly','addon','etc') NOT NULL DEFAULT '6months' COMMENT '항목(구축비, 월사용료, 기능추가, 기타)',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '결제 금액',
  `currency` varchar(10) DEFAULT 'PHP' COMMENT '통화 (PHP, KRW 등)',
  `pay_date` date DEFAULT NULL COMMENT '실제 결제일',
  `note` text DEFAULT NULL COMMENT '지불 관련 상세 메모 (예: 입금자명, 할인사유 등)',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '데이터 등록일',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일',
  `billing_date` date DEFAULT NULL COMMENT '청구 기준일',
  `paid` enum('y','n','f') NOT NULL DEFAULT 'n' COMMENT '납부 여부 (y:완료, n:미납, f:무료)',
  `expiring_date` date DEFAULT NULL COMMENT '다음 청구 예정일',
  `is_noticed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_shop_id` (`shop_id`),
  KEY `idx_pay_type` (`pay_type`),
  KEY `idx_pay_date` (`pay_date`),
  CONSTRAINT `fk_shop_payments_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='상점별 비용 납부 내역 관리';

-- 테이블 구조: `shop_reviews`
CREATE TABLE `shop_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `user_name` varchar(50) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT 5,
  `review_text` text DEFAULT NULL,
  `review_img` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `shop_reviews_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `shops`
CREATE TABLE `shops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subdomain` varchar(50) NOT NULL COMMENT '상점 아이디(URL경로)',
  `custom_domain` varchar(255) DEFAULT NULL,
  `manager_email` varchar(100) NOT NULL,
  `manager_name` varchar(50) DEFAULT NULL,
  `manager_name_en` varchar(100) DEFAULT NULL,
  `manager_password` varchar(255) NOT NULL,
  `is_temp_password` tinyint(1) NOT NULL DEFAULT 0,
  `category` varchar(30) DEFAULT 'fnb' COMMENT '업종: fnb, beauty, estate, service, mart 등',
  `shop_name` varchar(100) NOT NULL,
  `shop_name_en` varchar(100) DEFAULT NULL,
  `shop_intro` varchar(255) DEFAULT NULL COMMENT '상단 한줄 소개',
  `shop_description` text DEFAULT NULL COMMENT '브랜드 스토리 상세설명',
  `phone_mobile` varchar(50) DEFAULT NULL,
  `phone_landline` varchar(50) DEFAULT NULL,
  `kakao_id` varchar(50) DEFAULT NULL,
  `kakao_channel_id` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `location_city` varchar(50) DEFAULT NULL,
  `physical_address` varchar(255) DEFAULT NULL COMMENT '상점 실제 주소',
  `business_hours` text DEFAULT NULL,
  `delivery_hours` varchar(100) DEFAULT NULL COMMENT '배달 가능 시간',
  `min_delivery_amount` int(11) DEFAULT 0 COMMENT '배달 최소 주문 금액',
  `delivery_fee_info` varchar(255) DEFAULT NULL COMMENT '배달비 안내',
  `estimated_delivery_time` varchar(100) DEFAULT NULL COMMENT '예상 배달 시간',
  `payment_methods` varchar(255) DEFAULT 'Cash' COMMENT '결제 수단 (예: GCash, COD)',
  `is_pickup_available` tinyint(1) DEFAULT 1 COMMENT '포장 가능 여부',
  `is_delivery_available` tinyint(1) NOT NULL DEFAULT 1,
  `free_delivery_amount` int(11) DEFAULT NULL COMMENT '무료 배달 주문액',
  `delivery_fee` int(11) DEFAULT 0 COMMENT '배달비',
  `logo_path` varchar(255) DEFAULT NULL COMMENT '로고 이미지 파일명',
  `bg_path` text DEFAULT NULL,
  `setup_fee_contract` varchar(100) DEFAULT NULL,
  `monthly_fee_contract` varchar(100) DEFAULT NULL,
  `status` enum('applying','testing','active','inactive_soon','inactive','closed_soon','closed','deleted_soon','deleted','owner_inactive','owner_deleted') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '최종 수정일',
  `shop_skin` varchar(50) DEFAULT 'default',
  `shop_font` varchar(50) DEFAULT 'Pretendard',
  `shop_youtube_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '유튜브 동영상 링크 (JSON 배열)',
  `top_label` varchar(100) DEFAULT 'WELCOME TO OUR SHOP',
  `main_title` varchar(200) DEFAULT '',
  `sub_title` varchar(255) DEFAULT '',
  `shop_map_html` text DEFAULT NULL COMMENT '구글 지도 HTML 임베드 코드',
  `is_show_story` tinyint(1) NOT NULL DEFAULT 1 COMMENT '스토리 섹션 노출 여부',
  `is_show_gallery` tinyint(1) NOT NULL DEFAULT 1 COMMENT '갤러리 섹션 노출 여부',
  `is_show_map` tinyint(1) NOT NULL DEFAULT 1 COMMENT '지도 섹션 노출 여부',
  `is_show_main_title` tinyint(1) NOT NULL DEFAULT 1 COMMENT '메인 홍보 문구 노출 여부',
  `ui_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ui_settings`)),
  `urgent_notice` text DEFAULT NULL COMMENT '긴급 공지사항',
  `general_notice` text DEFAULT NULL COMMENT '일반 공지사항',
  `is_show_review` tinyint(1) NOT NULL DEFAULT 1,
  `is_show_delivery` tinyint(1) NOT NULL DEFAULT 1,
  `history_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '상점 변경 이력 (JSON 배열 형태)',
  `custom_free_orders` int(11) DEFAULT NULL COMMENT '개별 무료 주문 건수 한도 (NULL이면 사이트 기본값 따름)',
  `custom_free_disk_mb` int(11) DEFAULT NULL COMMENT '개별 무료 디스크 용량 한도 (NULL이면 사이트 기본값 따름)',
  `custom_free_db_mb` int(11) DEFAULT NULL,
  `inactive_date` datetime DEFAULT NULL COMMENT '휴점일',
  `closed_date` datetime DEFAULT NULL COMMENT '폐점일',
  `deleted_date` datetime DEFAULT NULL COMMENT '상점 삭제일',
  `telegram_chat_id` varchar(50) DEFAULT NULL COMMENT '텔레그램 수신자 고유 ID (숫자)',
  `use_telegram_alert` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '텔레그램 알림 활성화 여부',
  `telegram_alert_types` varchar(100) DEFAULT 'order,cancel' COMMENT '알림 조건(예: order,cancel,payment)',
  `policy_translations` text DEFAULT NULL COMMENT '정책 다국어 자동번역 데이터(JSON)',
  `tin_number` varchar(50) DEFAULT NULL COMMENT '납세자 식별 번호',
  `registered_name` varchar(255) DEFAULT NULL COMMENT '공식 등록 사업자명',
  `business_address` text DEFAULT NULL COMMENT '공식 사업장 주소',
  `business_type` varchar(20) DEFAULT 'Non-VAT' COMMENT 'VAT 여부',
  `reservation_settings` text DEFAULT NULL COMMENT '서비스/예약 상세 설정 데이터(JSON)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `subdomain` (`subdomain`),
  UNIQUE KEY `idx_unique_manager_email` (`manager_email`),
  UNIQUE KEY `idx_unique_shop_name` (`shop_name`),
  UNIQUE KEY `idx_unique_custom_domain` (`custom_domain`),
  UNIQUE KEY `idx_unique_shop_name_en` (`shop_name_en`),
  UNIQUE KEY `idx_unique_phone_mobile` (`phone_mobile`),
  UNIQUE KEY `idx_unique_phone_landline` (`phone_landline`),
  UNIQUE KEY `idx_unique_kakao_id` (`kakao_id`),
  UNIQUE KEY `idx_unique_kakao_channel_id` (`kakao_channel_id`),
  CONSTRAINT `check_history_log_json` CHECK (json_valid(`history_log`))
) ENGINE=InnoDB AUTO_INCREMENT=219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `site_logs`
CREATE TABLE `site_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `request_uri` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `site_sessions`
CREATE TABLE `site_sessions` (
  `id` varchar(128) NOT NULL,
  `data` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `updated_at_idx` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 테이블 구조: `site_settings`
CREATE TABLE `site_settings` (
  `set_key` varchar(50) NOT NULL COMMENT '설정 키 (예: cs_email)',
  `set_value` longtext DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL COMMENT '설정 항목 설명',
  PRIMARY KEY (`set_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 테이블 구조: `visit_logs`
CREATE TABLE `visit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL DEFAULT 0 COMMENT '0: 포털(메인), >0: 개별 상점 ID',
  `customer_id` int(11) unsigned DEFAULT NULL COMMENT '로그인한 경우 고객 ID',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL COMMENT '브라우저/기기 정보',
  `referer` text DEFAULT NULL COMMENT '유입 경로',
  `visit_path` varchar(255) DEFAULT NULL COMMENT '접속한 URL 경로',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_date` (`shop_id`,`created_at`),
  KEY `idx_ip_date` (`ip_address`,`created_at`),
  KEY `idx_visitor_check` (`shop_id`,`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7095 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='방문 상세 로그';

-- 테이블 구조: `visit_stats`
CREATE TABLE `visit_stats` (
  `visit_date` date NOT NULL,
  `shop_id` int(11) NOT NULL DEFAULT 0,
  `page_views` int(11) DEFAULT 0 COMMENT '전체 조회수',
  `unique_visitors` int(11) DEFAULT 0 COMMENT '순 방문자수(IP 기준)',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`visit_date`,`shop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='일별 방문 통계 요약';

