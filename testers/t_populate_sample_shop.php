<?php

/**
 * KShops24 샘플 상점 데이터 채우기 도구 (t_populate_sample_shop.php)
 * - 이미 생성된 빈 상점 ID를 입력받아, 선택한 카테고리에 맞는 샘플 데이터를 자동으로 생성합니다.
 */

require_once __DIR__ . '/t_common.php';

// 실행 시간 연장 (이미지 다운로드 대비)
set_time_limit(300);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    // 전달된 테마 값을 확인 (구버전 호환성을 위해 category 값도 받아옴)
    $data_theme = trim($_POST['data_theme'] ?? $_POST['category'] ?? 'fnb');
    if ($data_theme === 'srv') $data_theme = 'beauty'; // 기존 srv 요청 시 미용실 테마로 기본 할당

    // 선택한 데이터 테마를 상점의 메인 시스템 카테고리로 매핑
    $theme_to_cat = [
        'fnb' => 'fnb', 'cafe' => 'fnb', 'bakery' => 'fnb', 'maratang' => 'fnb',
        'mart' => 'mart', 
        'realty' => 'realty', 
        'beauty' => 'srv', 'clean_repair' => 'srv', 'rent_car' => 'srv'
    ];
    
    $category = $theme_to_cat[$data_theme] ?? 'fnb';

    if ($shop_id > 0) {
        try {
            // 상점 정보 확인
            $stmt = $pdo->prepare("SELECT id, shop_name, subdomain, category FROM shops WHERE id = ?");
            $stmt->execute([$shop_id]);
            $shop = $stmt->fetch();

            if (!$shop) {
                throw new Exception("입력하신 상점 ID({$shop_id})를 찾을 수 없습니다.");
            }

            $subdomain = $shop['subdomain'];

            // 데이터베이스 트랜잭션 시작
            $pdo->beginTransaction();

            // 기존 데이터 초기화 (메뉴/매물, 카테고리)
            $pdo->prepare("DELETE FROM shop_items WHERE shop_id = ?")->execute([$shop_id]);
            $pdo->prepare("DELETE FROM shop_item_categories WHERE shop_id = ?")->execute([$shop_id]);
            $pdo->prepare("DELETE FROM shop_item_boards WHERE shop_id = ?")->execute([$shop_id]);

            // 상점 카테고리 업데이트 (선택한 카테고리로 변경)
            $pdo->prepare("UPDATE shops SET category = ? WHERE id = ?")->execute([$category, $shop_id]);

                // [대안] 외부 API에 의존하지 않는 자체 내장 정적(Static) 프리미엄 샘플 데이터 세트
                $generated_data = ['categories' => []];
                $shop_intro = "{$shop['shop_name']}에 오신 것을 환영합니다!";
                $shop_desc = "본 샘플 데이터는 상점 기능 테스트를 위해 시스템에 기본 내장된 정적 데이터입니다.";

                // =========================================================================
                if ($data_theme === 'fnb') {
                    $generated_data['categories'] = [
                        [
                            'name' => '메인 요리',
                            'items' => [
                                ['item_name' => '클래식 후라이드 치킨', 'item_info' => '바삭바삭한 식감이 일품인 오리지널 후라이드 치킨', 'item_price' => 900, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '한국식 양념 치킨', 'item_info' => '매콤달콤한 특제 소스로 버무린 양념 치킨', 'item_price' => 950, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '간장 마늘 치킨', 'item_info' => '짭조름한 간장과 알싸한 마늘의 완벽한 조화', 'item_price' => 950, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '숯불 바베큐 폭립', 'item_info' => '참나무 숯불향이 가득 밴 부드러운 돼지갈비', 'item_price' => 1200, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '페퍼로니 피자', 'item_info' => '매콤한 페퍼로니가 듬뿍 올라간 정통 스타일 피자', 'item_price' => 850, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '해물 크림 파스타', 'item_info' => '신선한 해산물과 고소한 크림 소스의 만남', 'item_price' => 650, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '사이드 메뉴',
                            'items' => [
                                ['item_name' => '모짜렐라 치즈볼 (5개)', 'item_info' => '겉은 바삭, 속은 쫀득한 달콤 고소 치즈볼', 'item_price' => 250, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '케이준 감자튀김', 'item_info' => '자꾸만 손이 가는 매콤 바삭한 감자튀김', 'item_price' => 180, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '옛날 국물 떡볶이', 'item_info' => '추억을 부르는 매콤달콤하고 쫄깃한 밀떡볶이', 'item_price' => 300, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '뚝배기 계란찜', 'item_info' => '부드럽고 촉촉하게 부풀어 오른 뜨끈한 계란찜', 'item_price' => 200, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '날치알 주먹밥', 'item_info' => '톡톡 터지는 날치알과 고소한 김가루의 조화', 'item_price' => 150, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '왕새우 튀김 (3마리)', 'item_info' => '통째로 튀겨낸 바삭한 왕새우 튀김', 'item_price' => 350, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '디저트',
                            'items' => [
                                ['item_name' => '진한 초코 브라우니', 'item_info' => '꾸덕한 다크 초콜릿의 풍미가 가득한 브라우니', 'item_price' => 180, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '뉴욕 치즈 케이크', 'item_info' => '입안에서 사르르 녹는 진한 크림치즈 케이크', 'item_price' => 220, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '아이스크림 크로플', 'item_info' => '갓 구운 크로플 위에 달콤한 바닐라 아이스크림', 'item_price' => 250, 'is_best' => 1, 'is_new' => 1],
                                ['item_name' => '수제 마카롱 세트', 'item_info' => '쫀득한 꼬끄가 매력적인 프렌치 마카롱 (3구)', 'item_price' => 300, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '인절미 팥빙수', 'item_info' => '우유 얼음에 고소한 콩가루와 쫄깃한 인절미', 'item_price' => 380, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '생과일 타르트', 'item_info' => '제철 신선한 과일이 듬뿍 올라간 타르트', 'item_price' => 280, 'is_best' => 0, 'is_new' => 1],
                            ]
                        ],
                        [
                            'name' => '음료 및 주류',
                            'items' => [
                                ['item_name' => '코카콜라 (500ml)', 'item_info' => '얼음 컵과 함께 제공되는 시원한 코카콜라', 'item_price' => 80, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '칠성 사이다 (500ml)', 'item_info' => '갈증을 날려주는 시원한 사이다', 'item_price' => 80, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '생맥주 (500cc)', 'item_info' => '가슴속까지 시원해지는 청량감 넘치는 생맥주', 'item_price' => 120, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '참이슬', 'item_info' => '한국인의 영원한 친구, 깔끔한 오리지널 소주', 'item_price' => 250, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '아이스 아메리카노', 'item_info' => '100% 아라비카 원두로 내린 깊고 진한 커피', 'item_price' => 150, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '생과일 망고 쉐이크', 'item_info' => '필리핀 생망고를 듬뿍 넣어 갈아 만든 쉐이크', 'item_price' => 180, 'is_best' => 1, 'is_new' => 1],
                            ]
                        ]
                    ];
                    $shop_intro = "신선하고 맛있는 요리를 정성껏 배달해 드립니다.";
                
                // =========================================================================
                } elseif ($data_theme === 'cafe') {
                    $generated_data['categories'] = [
                        [
                            'name' => '에스프레소 커피',
                            'items' => [
                                ['item_name' => '아이스 아메리카노', 'item_info' => '100% 최고급 아라비카 원두로 내린 깔끔한 블랙 커피', 'item_price' => 120, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '카페 라떼', 'item_info' => '진한 에스프레소와 고소하고 부드러운 스팀 우유의 만남', 'item_price' => 150, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '바닐라 빈 라떼', 'item_info' => '수제 바닐라 빈 시럽을 넣어 풍미가 가득한 달콤한 라떼', 'item_price' => 170, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '카라멜 마끼아또', 'item_info' => '달콤한 카라멜 드리즐과 부드러운 우유 거품', 'item_price' => 170, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '콜드브루 (더치커피)', 'item_info' => '장시간 찬물로 우려내어 쓴맛 없이 부드럽고 깊은 향', 'item_price' => 160, 'is_best' => 1, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '스무디 & 티',
                            'items' => [
                                ['item_name' => '리얼 망고 스무디', 'item_info' => '필리핀 생망고를 듬뿍 갈아 만든 달콤한 스무디', 'item_price' => 180, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '딸기 요거트 스무디', 'item_info' => '상큼한 딸기와 진한 요거트의 환상적인 조합', 'item_price' => 180, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '제주 말차 라떼', 'item_info' => '제주산 유기농 말차를 사용하여 쌉싸름하고 진한 맛', 'item_price' => 160, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '허니 자몽 블랙티', 'item_info' => '상큼한 자몽과 은은한 블랙티, 달콤한 꿀의 밸런스', 'item_price' => 150, 'is_best' => 1, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '베이커리 & 디저트',
                            'items' => [
                                ['item_name' => '클래식 크루아상', 'item_info' => '프랑스산 고메 버터로 결을 살려 구워낸 바삭한 크루아상', 'item_price' => 120, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '바스크 치즈 케이크', 'item_info' => '고온에서 구워내 겉은 스모키하고 속은 촉촉한 치즈 케이크', 'item_price' => 220, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '플레인 스콘 & 잼', 'item_info' => '겉바속촉 갓 구운 스콘과 상큼한 딸기잼 세트', 'item_price' => 110, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '수제 티라미수', 'item_info' => '진한 에스프레소 시럽과 마스카포네 치즈의 정통 이탈리안 디저트', 'item_price' => 250, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '프렌치 마카롱 (3구)', 'item_info' => '쫀득한 꼬끄와 다양한 필링이 매력적인 수제 마카롱', 'item_price' => 300, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ]
                    ];
                    $shop_intro = "향긋한 커피와 달콤한 디저트로 힐링의 시간을 선사합니다.";
                    
                // =========================================================================
                } elseif ($data_theme === 'bakery') {
                    $generated_data['categories'] = [
                        [
                            'name' => '식사빵 & 샌드위치',
                            'items' => [
                                ['item_name' => '탕종 우유 식빵', 'item_info' => '탕종법으로 반죽해 며칠이 지나도 쫄깃하고 부드러운 우유 식빵', 'item_price' => 200, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '유기농 통밀빵', 'item_info' => '건강을 생각한 고소하고 담백한 유기농 100% 통밀빵', 'item_price' => 250, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '듬뿍 에그 마요 샌드위치', 'item_info' => '부드러운 에그 마요 샐러드가 듬뿍 들어간 든든한 샌드위치', 'item_price' => 180, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1628191137573-dee64e727614?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => 'B.L.T 샌드위치', 'item_info' => '베이컨, 상추, 토마토가 어우러진 신선한 클래식 클럽 샌드위치', 'item_price' => 220, 'is_best' => 0, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1619860860774-1e2e17343432?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '건강 발효빵',
                            'items' => [
                                ['item_name' => '무화과 깜빠뉴', 'item_info' => '달콤한 무화과와 고소한 호두가 씹히는 쫄깃한 천연 발효빵', 'item_price' => 280, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1586444248902-2f64eddc13bf?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '블랙 올리브 치아바타', 'item_info' => '짭짤한 올리브가 콕콕 박혀 샌드위치 용으로 좋은 치아바타', 'item_price' => 180, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1596547609652-9cb5b4d7eaeb?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '프렌치 바게트', 'item_info' => '겉은 바삭하고 속은 쫄깃한 프랑스 정통 바게트', 'item_price' => 150, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1530610476181-d83430b64dcb?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '고메버터 앙버터', 'item_info' => '달콤한 통팥 앙금과 고소한 프랑스산 고메버터의 만남', 'item_price' => 240, 'is_best' => 1, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1509312845663-8a3d54026541?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '페이스트리 & 파이',
                            'items' => [
                                ['item_name' => '오리지널 크루아상', 'item_info' => '프랑스산 AOP 버터를 사용해 풍미가 가득한 바삭한 크루아상', 'item_price' => 160, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1555507036-ab1f4022115c?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '뺑 오 쇼콜라', 'item_info' => '겹겹이 쌓인 페이스트리 속에 진한 다크 초콜릿 스틱이 쏙', 'item_price' => 180, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1608198093002-ad4e005484ec?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '몽블랑 페이스트리', 'item_info' => '달콤한 시럽이 촉촉하게 스며든 산 모양의 페이스트리', 'item_price' => 250, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1621236378699-8597faf6a176?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '시나몬 애플 파이', 'item_info' => '사과 과육이 씹히는 달콤하고 향긋한 수제 애플 파이', 'item_price' => 200, 'is_best' => 0, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ]
                    ];
                    $shop_intro = "매일 아침 갓 구워낸 신선하고 따뜻한 빵을 만나보세요.";

                // =========================================================================
                } elseif ($data_theme === 'maratang') {
                    $generated_data['categories'] = [
                        [
                            'name' => '메인 요리',
                            'items' => [
                                ['item_name' => '마라탕 (기본)', 'item_info' => '진한 사골 육수에 얼얼한 마라 소스가 어우러진 정통 마라탕', 'item_price' => 450, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1596645398246-880521e860bc?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '마라샹궈 (볶음)', 'item_info' => '각종 재료를 특제 마라 소스에 볶아낸 매콤 얼얼한 볶음 요리', 'item_price' => 600, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1623653387945-2fd256d58231?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '꿔바로우 (소)', 'item_info' => '바삭하고 쫀득한 찹쌀 튀김옷과 새콤달콤 소스의 환상 조화', 'item_price' => 350, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1628178873177-3e11075217ea?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '마라롱샤 (가재 볶음)', 'item_info' => '매콤한 마라 소스에 볶아낸 중독성 강한 민물가재 요리', 'item_price' => 850, 'is_best' => 0, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1559058789-668b5561a065?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '고기 / 꼬치류 추가',
                            'items' => [
                                ['item_name' => '소고기 추가 (100g)', 'item_info' => '부드럽고 고소한 마라탕의 필수 추가 메뉴', 'item_price' => 120, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1603360946369-dc9bb6258143?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '양고기 추가 (100g)', 'item_info' => '마라탕의 풍미를 한층 높여주는 양고기', 'item_price' => 120, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '문어 완자 (3개)', 'item_info' => '쫄깃쫄깃 씹는 맛이 일품인 해산물 완자', 'item_price' => 80, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1555126634-323283e090fa?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '비엔나 소시지 (4개)', 'item_info' => '톡 터지는 식감이 좋은 마라탕 인기 재료', 'item_price' => 60, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1595286591012-70b9239ebdb9?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '야채 / 면류 추가',
                            'items' => [
                                ['item_name' => '중국당면 (넓은 당면)', 'item_info' => '마라탕 국물을 듬뿍 머금어 쫀득한 중국식 넓은 당면', 'item_price' => 80, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1552611052-33e04de081de?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '분모자 당면', 'item_info' => '가래떡처럼 두껍고 쫄깃한 식감의 매력적인 당면', 'item_price' => 90, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1610996845348-18e38cb3c0ec?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '푸주 (건두부)', 'item_info' => '부드럽고 쫄깃한 식감으로 사랑받는 마라탕 단골 두부 재료', 'item_price' => 70, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1582878826629-29b7ad1cb438?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '청경채 & 배추 세트', 'item_info' => '국물을 시원하게 만들어주는 신선한 야채 세트', 'item_price' => 60, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1553531087-b25a0b9a68ab?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '음료 / 주류',
                            'items' => [
                                ['item_name' => '빙홍차 (중국 아이스티)', 'item_info' => '마라의 매운맛을 달래주는 달콤한 레몬 아이스티', 'item_price' => 100, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1522851508208-d3c52a382e85?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '칭따오 맥주 (640ml)', 'item_info' => '마라탕과 최고의 궁합을 자랑하는 중국 대표 맥주', 'item_price' => 180, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1614316049298-b8054045f2e6?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ]
                    ];
                    $shop_intro = "얼얼하고 매콤한 진짜 마라의 맛! 취향대로 골라 담는 즐거움이 있습니다.";

                // =========================================================================
                } elseif ($data_theme === 'realty') {
                    $generated_data['categories'] = [
                        [
                            'name' => '콘도/아파트 매매',
                            'items' => [
                                ['item_name' => 'BGC 하이스트리트 1베드룸 급매', 'item_info' => 'BGC 중심가 럭셔리 콘도. 수영장 및 헬스장 완비', 'item_price' => 12500000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '마카티 CBD 스튜디오', 'item_info' => '마카티 비즈니스 지구 내, 직장인 임대 수익용 최적', 'item_price' => 8500000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '올티가스 2베드룸 신축', 'item_info' => '메가몰 도보 5분 거리. 탁 트인 시티뷰 자랑', 'item_price' => 15000000, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '파사이 오션뷰 펜트하우스', 'item_info' => '아름다운 마닐라 베이를 즐길 수 있는 최고급 펜트하우스', 'item_price' => 45000000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '퀘존 3베드룸 가족용', 'item_info' => '대형 쇼핑몰 및 국제학교 인접. 가족 단위 거주 최적', 'item_price' => 18000000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '알라방 프리미엄 1베드룸', 'item_info' => '조용하고 쾌적한 환경, 뛰어난 보안을 자랑하는 지역', 'item_price' => 9500000, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '콘도/아파트 렌트',
                            'items' => [
                                ['item_name' => 'BGC 2베드룸 풀퍼니시드', 'item_info' => '가구 및 가전 완비. 몸만 들어와서 즉시 입주 가능', 'item_price' => 85000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '마카티 그린벨트 스튜디오', 'item_info' => '쇼핑몰 밀집 지역 도보 5분 거리. 1인 가구 추천', 'item_price' => 35000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '올티가스 저렴한 1베드룸', 'item_info' => '대중교통 이용이 편리하며 관리비 포함된 저렴한 렌트', 'item_price' => 25000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '파사이 공항 인근 2베드룸', 'item_info' => '국제공항과 가까워 출장이 잦은 비즈니스맨 추천', 'item_price' => 45000, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '만달루용 대형 레지던스', 'item_info' => 'BGC와 마카티 중간에 위치하여 접근성이 뛰어난 곳', 'item_price' => 60000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '이스트우드 IT파크 인접', 'item_info' => '안전하고 편리한 상권. 세탁기, 냉장고 풀옵션', 'item_price' => 30000, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '하우스/타운하우스',
                            'items' => [
                                ['item_name' => '알라방 고급 빌리지 저택', 'item_info' => '최고 수준의 보안을 자랑하는 수영장 딸린 저택', 'item_price' => 150000000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => 'BF홈즈 3베드룸 하우스', 'item_info' => '한인 타운 인접. 리모델링을 마쳐 컨디션 최상', 'item_price' => 25000000, 'is_best' => 1, 'is_new' => 1],
                                ['item_name' => '퀘존 신축 타운하우스', 'item_info' => '모던한 디자인의 3층 구조. 주차장 및 루프탑 포함', 'item_price' => 18500000, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '안티폴로 전망 좋은 단독주택', 'item_info' => '마닐라 시내가 한눈에 내려다보이는 쾌적한 전원주택', 'item_price' => 32000000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '라구나 은퇴 이민자 추천', 'item_info' => '마닐라 근교 조용하고 안전한 빌리지 내 주택', 'item_price' => 12000000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '산후안 프라이빗 풀빌라', 'item_info' => '정원과 수영장을 갖춘 대가족/게스트하우스용 저택', 'item_price' => 85000000, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '상가/오피스 임대',
                            'items' => [
                                ['item_name' => '마카티 1층 식당 임대', 'item_info' => '유동 인구가 많은 상업 지역. 기존 주방 시설 활용 가능', 'item_price' => 150000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => 'BGC IT 파크 소형 오피스', 'item_info' => '최고의 비즈니스 인프라를 제공하는 쾌적한 사무실', 'item_price' => 80000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '올티가스 PEZA 대형 사무실', 'item_info' => '외국계 기업 추천 건물. 넓은 업무 공간과 넉넉한 주차', 'item_price' => 350000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '퀘존 핫플레이스 카페 자리', 'item_info' => '인기 상권 1층 코너. 트렌디한 카페나 베이커리 추천', 'item_price' => 120000, 'is_best' => 1, 'is_new' => 1],
                                ['item_name' => '파사이 공항 인근 상가', 'item_info' => '카지노 단지 인접 요지. 뷰티살롱, 환전소 추천', 'item_price' => 200000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '말라떼 권리금 없는 다목적 상가', 'item_info' => '한인 유동 인구가 많은 핵심 상권. 즉시 계약 가능', 'item_price' => 100000, 'is_best' => 1, 'is_new' => 0],
                            ]
                        ]
                    ];
                    $shop_intro = "안전하고 정직한 부동산 거래를 약속합니다.";
                    
                // =========================================================================
                } elseif ($data_theme === 'mart') {
                    $generated_data['categories'] = [
                        [
                            'name' => '과일 / 채소',
                            'items' => [
                                ['item_name' => '프리미엄 생망고 1kg', 'item_info' => '당도 최고! 산지 직송 신선한 프리미엄 생망고', 'item_price' => 250, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '망고스틴 500g', 'item_info' => '새콤달콤 과일의 여왕 신선 망고스틴', 'item_price' => 300, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '한국산 배추 1포기', 'item_info' => '김치 담그기에 좋은 싱싱한 한국 품종 통배추', 'item_price' => 350, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '대파 1단', 'item_info' => '국물 맛을 살려주는 굵고 튼실한 대파', 'item_price' => 120, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '양파 1kg', 'item_info' => '어디에나 들어가는 신선하고 단단한 둥근 양파', 'item_price' => 150, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '정육 / 수산',
                            'items' => [
                                ['item_name' => '벌집 삼겹살 500g', 'item_info' => '칼집을 넣어 더욱 부드럽고 쫄깃한 구이용 삼겹살', 'item_price' => 450, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '소고기 차돌박이 500g', 'item_info' => '입에서 살살 녹는 고소한 맛의 얇게 썬 차돌박이', 'item_price' => 650, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '손질 오징어 2마리', 'item_info' => '내장과 뼈를 제거하여 요리하기 편리한 생물 오징어', 'item_price' => 300, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '노르웨이 고등어 1마리', 'item_info' => '기름기가 좔좔 흐르는 두툼한 구이용 고등어', 'item_price' => 250, 'is_best' => 1, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '한국 식품 / 라면',
                            'items' => [
                                ['item_name' => '농심 신라면 (5봉)', 'item_info' => '한국인의 매운맛! 얼큰한 국민 라면 세트', 'item_price' => 280, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => 'CJ 종가집 포기김치 1kg', 'item_info' => '아삭하고 시원한 맛이 일품인 한국 정통 김치', 'item_price' => 450, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '햇반 (3입)', 'item_info' => '갓 지은 밥맛 그대로, 간편하게 즐기는 즉석밥', 'item_price' => 220, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '해찬들 찰고추장 1kg', 'item_info' => '맛있는 요리의 기본, 매콤달콤 찰고추장', 'item_price' => 380, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ]
                    ];
                    $shop_intro = "신선한 식재료와 한국 식품을 문 앞까지 빠르게 배달합니다.";
                    
                // =========================================================================
                } elseif ($data_theme === 'beauty') {
                    $generated_data['categories'] = [
                        [
                            'name' => '헤어 컷 / 펌',
                            'items' => [
                                ['item_name' => '남성 디자인 커트', 'item_info' => '고객의 얼굴형과 두상에 맞춘 세련된 트렌디 커트', 'item_price' => 800, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '여성 레이어드 커트', 'item_info' => '가벼우면서도 볼륨감을 살려주는 세련된 질감 처리', 'item_price' => 1000, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '뿌리 볼륨 펌', 'item_info' => '가라앉는 정수리 뿌리를 풍성하게 살려주는 필수 펌', 'item_price' => 1500, 'is_best' => 0, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1521590832167-7bfc17484d20?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '디지털 세팅 펌', 'item_info' => '자연스럽고 탄력 있는 여신 웨이브 S컬 펌', 'item_price' => 3500, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1620331311520-246422fd82f9?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '남성 다운 펌', 'item_info' => '뜨는 옆머리를 차분하게 눌러주는 깔끔한 다운 펌', 'item_price' => 1200, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '염색 / 클리닉',
                            'items' => [
                                ['item_name' => '프리미엄 전체 염색', 'item_info' => '모발 손상을 최소화한 고급 약제 컬러 체인지', 'item_price' => 2500, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1600948836101-f9ff59207e60?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '뿌리 염색 터치업', 'item_info' => '기존 헤어 컬러와 경계 없이 자연스럽게 연결하는 염색', 'item_price' => 1500, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '신데렐라 복구 클리닉', 'item_info' => '잦은 시술로 엉키고 끊어지는 극손상모를 위한 마법 클리닉', 'item_price' => 4000, 'is_best' => 1, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1519699047748-de8e457a634e?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '두피 스케일링 딥클렌징', 'item_info' => '막힌 모공을 뚫고 각질을 완벽 제거하는 두피 케어', 'item_price' => 1200, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '네일 / 페디 아트',
                            'items' => [
                                ['item_name' => '원칼라 젤 네일', 'item_info' => '들뜸 없이 오래 유지되고 깔끔 선명한 젤 네일', 'item_price' => 1000, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '이달의 스페셜 아트', 'item_info' => '트렌디한 파츠와 디자인이 듬뿍 담긴 스페셜 네일 아트', 'item_price' => 1800, 'is_best' => 1, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '프렌치 / 그라데이션', 'item_info' => '손끝이 더욱 길어 보이고 우아한 클래식 디자인', 'item_price' => 1500, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1519014816548-bf5fe059e98b?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '프리미엄 젤 페디큐어', 'item_info' => '발끝까지 완벽하게 꾸며주는 깔끔한 페디 케어', 'item_price' => 1200, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1534096053351-4e442c5443fa?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ],
                        [
                            'name' => '마사지 / 에스테틱',
                            'items' => [
                                ['item_name' => '수분 듬뿍 물광 스킨케어', 'item_info' => '건조한 피부에 즉각적인 수분과 광채를 부여하는 에스테틱', 'item_price' => 2000, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '전신 아로마 릴렉싱 (90분)', 'item_info' => '지친 몸의 근육을 부드럽게 풀어주는 최고급 오일 테라피', 'item_price' => 1800, 'is_best' => 1, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '풋 스파 및 발 각질 케어', 'item_info' => '건조하게 갈라진 발뒤꿈치를 아기 발처럼 부드럽게 케어', 'item_price' => 1000, 'is_best' => 0, 'is_new' => 0, 'item_img' => '["https://images.unsplash.com/photo-1519415943484-9fa1873496d4?auto=format&fit=crop&w=500&q=80"]'],
                                ['item_name' => '여드름 진정 아크네 케어', 'item_info' => '민감성 피부를 위한 저자극 진정 및 피지/트러블 집중 관리', 'item_price' => 2500, 'is_best' => 0, 'is_new' => 1, 'item_img' => '["https://images.unsplash.com/photo-1616394584738-fc6e612e71b9?auto=format&fit=crop&w=500&q=80"]'],
                            ]
                        ]
                    ];
                    $shop_intro = "고객님의 아름다움과 휴식을 책임지는 프리미엄 뷰티 살롱입니다.";

                // =========================================================================
                } elseif ($data_theme === 'clean_repair') {
                    $generated_data['categories'] = [
                        [
                            'name' => '에어컨/가전 청소',
                            'items' => [
                                ['item_name' => '에어컨 완전 분해 청소', 'item_info' => '곰팡이와 찌든 먼지를 완벽하게 분해 제거합니다.', 'item_price' => 1500, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '세탁기 분해 세척', 'item_info' => '내부 곰팡이와 세제 찌꺼기를 세척하여 위생 관리', 'item_price' => 1800, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '냉장고 내부 살균 청소', 'item_info' => '악취 나는 냉장고 선반 분리 세척 및 자외선 살균', 'item_price' => 2000, 'is_best' => 0, 'is_new' => 1],
                            ]
                        ],
                        [
                            'name' => '정밀 청소 / 방역',
                            'items' => [
                                ['item_name' => '입주 및 이사 정밀 청소', 'item_info' => '전문 장비와 인력으로 보이지 않는 찌든 때까지 확실한 청소', 'item_price' => 8000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '매트리스/소파 딥 클리닝', 'item_info' => '특수 장비로 깊은 속 진드기 흡입 및 얼룩 제거 살균', 'item_price' => 2000, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '정기 해충 방역 서비스', 'item_info' => '인체에 무해한 친환경 약품으로 바퀴, 개미 등 확실한 퇴치', 'item_price' => 2500, 'is_best' => 1, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '배관 / 전기 출장 수리',
                            'items' => [
                                ['item_name' => '수도/배관 긴급 수리', 'item_info' => '변기 막힘, 배관 누수, 수전 교체 등 긴급 문제 신속 해결', 'item_price' => 1200, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '전기 누전 및 조명 교체', 'item_info' => '누전 차단기 점검 및 고장난 각종 조명/콘센트 수리', 'item_price' => 1000, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '디지털 도어락 방문 설치', 'item_info' => '최신형 스마트 도어락 및 보조키 방문 타공 및 설치', 'item_price' => 3500, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '방충망 전체 교체', 'item_info' => '낡고 찢어진 방충망을 벌레 차단 미세망으로 깔끔하게 보수', 'item_price' => 1500, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ],
                        [
                            'name' => '가전 / PC 수리',
                            'items' => [
                                ['item_name' => '가전제품 방문 수리', 'item_info' => '브랜드 상관없이 냉장고, TV, 세탁기 등 방문 수리', 'item_price' => 1500, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => 'PC/노트북 포맷 및 점검', 'item_info' => '느려진 PC 윈도우 재설치, 바이러스 치료, 부품 업그레이드', 'item_price' => 800, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ]
                    ];
                    $shop_intro = "믿고 맡길 수 있는 청소 및 수리 전문가들이 출동합니다.";
                    
                // =========================================================================
                } elseif ($data_theme === 'rent_car') {
                    $generated_data['categories'] = [
                        [
                            'name' => '렌터카 / 픽업 샌딩',
                            'items' => [
                                ['item_name' => '공항 왕복 픽업/샌딩', 'item_info' => '마닐라 공항(NAIA)에서 목적지까지 편안하게 모시는 픽업', 'item_price' => 1500, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '일일 렌터카 (기사 포함)', 'item_info' => '비즈니스 및 관광을 위한 10시간 기준 렌터카 서비스', 'item_price' => 3500, 'is_best' => 1, 'is_new' => 0],
                                ['item_name' => '프리미엄 SUV 월 장기렌트', 'item_info' => '체류 기간 동안 내 차처럼 편하게 이용하는 장기 렌트', 'item_price' => 40000, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => '골프장 단체 투어 밴', 'item_info' => '골프 장비를 싣고 일행과 함께 이동할 수 있는 대형 밴', 'item_price' => 4500, 'is_best' => 0, 'is_new' => 1],
                                ['item_name' => '외곽 장거리 투어 밴', 'item_info' => '바탕가스, 수빅 등 외곽 지역 안전한 장거리 여행용', 'item_price' => 5500, 'is_best' => 0, 'is_new' => 0],
                                ['item_name' => 'VIP 리무진 의전 서비스', 'item_info' => '중요한 바이어 접대 및 특별한 기념일 맞춤 리무진', 'item_price' => 8000, 'is_best' => 0, 'is_new' => 0],
                            ]
                        ]
                    ];
                    $shop_intro = "안전하고 편안한 이동을 위해 최고의 차량과 기사를 제공합니다.";
                }

                // AI가 생성한 데이터를 DB에 삽입
                $sort_order_cat = 1;
                foreach ($generated_data['categories'] as $cat) {
                    $stmt_cat = $pdo->prepare("INSERT INTO shop_item_categories (shop_id, cat_name, sort_order) VALUES (?, ?, ?)");
                    $stmt_cat->execute([$shop_id, $cat['name'], $sort_order_cat++]);
                    $cat_id = $pdo->lastInsertId();

                    $sort_order_item = 1;
                    $menu_stmt = $pdo->prepare("INSERT INTO shop_items (shop_id, cat_id, item_name, item_info, item_price, item_discount_rate, item_discount_price, item_img, is_best, is_new, is_soldout, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($cat['items'] as $item) {
                        $menu_stmt->execute([
                            $shop_id,
                            $cat_id,
                            $item['item_name'] ?? '상품명 없음',
                            $item['item_info'] ?? '',
                            (int)($item['item_price'] ?? 0),
                            0, // discount_rate
                            0, // discount_price
                            $item['item_img'] ?? '', // item_img (설정된 이미지가 있으면 삽입)
                            (int)($item['is_best'] ?? 0),
                            (int)($item['is_new'] ?? 0),
                            0, // is_soldout
                            $sort_order_item++
                        ]);
                    }
                }

                // 소개글 등 업데이트
                $pdo->prepare("UPDATE shops SET shop_intro = ?, shop_description = ? WHERE id = ?")->execute([
                    $shop_intro,
                    $shop_desc,
                    $shop_id
                ]);

            // 트랜잭션 커밋
            $pdo->commit();
            $message = "<div class='alert alert-success'>✅ 상점 [{$shop['shop_name']}] ({$subdomain})에 선택하신 <b>맞춤형 테마</b> 데이터가 단 0.1초 만에 성공적으로 채워졌습니다.<br><a href='/{$subdomain}' target='_blank' class='fw-bold text-success'>👉 상점 확인하기</a></div>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "<div class='alert alert-danger'>❌ 에러 발생: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>⚠️ 상점 ID를 입력해 주세요.</div>";
    }
}


?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>샘플 상점 데이터 채우기</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-database-add me-2"></i>샘플 상점 데이터 채우기</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($message) echo $message; ?>

                <div class="alert alert-info small mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i> <code>https://KShops24.com/register.php?dev_test=1</code> 등을 통해 만들어둔 <b>빈 상점의 ID(숫자)</b>를 입력하면, <b>시스템에 내장된 프리미엄 정적 데이터 세트</b>를 즉시 생성해 채워줍니다. (기존 데이터는 삭제됩니다)
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">샘플 데이터 테마 선택</label>
                        <select name="data_theme" class="form-select border-primary" required>
                            <optgroup label="외식 및 배달 (F&B)">
                                <option value="fnb">치킨 / 피자 / 음식점</option>
                                <option value="cafe">카페 / 커피 / 디저트</option>
                                <option value="bakery">빵집 / 베이커리 전문점</option>
                                <option value="maratang">마라탕 / 중식 배달</option>
                                <option value="mart">한인마트 / 식료품 / 정육</option>
                            </optgroup>
                            <optgroup label="뷰티 및 생활 서비스 (SRV)">
                                <option value="beauty">미용실 / 네일 / 마사지 스파</option>
                                <option value="clean_repair">청소 / 에어컨 / 가전 수리</option>
                                <option value="rent_car">렌터카 / 공항픽업 / 골프 투어</option>
                            </optgroup>
                            <optgroup label="부동산 (REALTY)">
                                <option value="realty">부동산 매매 / 렌트 임대</option>
                            </optgroup>
                        </select>
                        <div class="form-text text-muted">선택한 업종 테마에 딱 맞는 고품질 맞춤형 데이터가 생성됩니다.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">대상 상점 ID (숫자)</label>
                        <input type="number" name="shop_id" class="form-control" placeholder="예: 15" required>
                        <div class="form-text">데이터를 채울 상점의 고유 ID 값을 입력하세요.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold fs-5">
                        <i class="bi bi-lightning-charge-fill me-1"></i> 0.1초 만에 샘플 데이터 채우기 실행
                    </button>
                </form>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('form').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> 데이터를 즉시 채우는 중입니다...';
        btn.disabled = true;
    });
});
</script>
</body>

</html>