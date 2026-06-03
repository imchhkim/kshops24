<?php

/**
 * KShops24 상점 주인용 멋진 이모티콘 모음 (manage_shop_emojis.php)
 * - 역할: 사장님들이 리뷰 답변, 공지사항 등에 사용할 센스 있는 이모티콘 제공
 * - 기능: 카테고리별 분류, 클릭 시 자동 복사 (JavaScript)
 * - 업데이트: '기타 (운영 & 강조)' 카테고리 추가
 */

// 로그인 세션 확인 등 최소한의 보안 처리가 필요합니다.
// (기존 관리자 시스템의 보안 로직을 여기에 추가하세요.)
// if (!isset($_SESSION['owner_id'])) { exit('접근 권한이 없습니다.'); }

// 1. 이모티콘 데이터 정의 (카테고리별)
$emoji_list = [
    '환영 & 감사 (리뷰 답변용)' => [
        '🙏' => '감사합니다',
        '🙇‍♂️' => '감사(남)',
        '🙇‍♀️' => '감사(여)',
        '🤝' => '반갑습니다',
        '🫶' => '사랑해요',
        '❤️' => '하트',
        '💕' => '두근두근',
        '💖' => '빛나는하트',
        '💝' => '리본하트',
        '🥰' => '사랑스런',
        '😍' => '반해버린',
        '😘' => '뽀뽀',
        '🤗' => '포옹',
        '✋' => '안녕',
        '👋' => '반가워요',
        '✨' => '반짝반짝',
        '🌟' => '빛나는별',
        '💐' => '꽃다발',
        '🎁' => '선물'
    ],
    '음식 & 맛 (식당/카페용)' => [
        '😋' => '맛있어',
        '🤤' => '군침',
        '👨‍🍳' => '요리사(남)',
        '👩‍🍳' => '요리사(여)',
        '🍖' => '고기',
        '🥩' => '스테이크',
        '🍗' => '치킨',
        '🍔' => '햄버거',
        '🍕' => '피자',
        '�' => '핫도그',
        '🌮' => '타코',
        '🍜' => '면요리',
        '🍲' => '국물요리',
        '🍛' => '카레/덮밥',
        '' => '초밥',
        '🥗' => '샐러드',
        '🥡' => '포장음식',
        '🍱' => '도시락',
        '' => '케이크',
        '🍩' => '도넛',
        '🍪' => '쿠키',
        '☕' => '커피/차',
        '🧋' => '버블티',
        '🥤' => '음료/주스',
        '🍻' => '맥주짠',
        '🥂' => '와인짠',
        '🍽️' => '식기세트'
    ],
    '쇼핑 & 배달 (상점 운영용)' => [
        '🛒' => '쇼핑카트',
        '🛍️' => '쇼핑백',
        '📦' => '택배/박스',
        '🚚' => '배달트럭',
        '🛵' => '오토바이',
        '💨' => '총알배송',
        '🎫' => '티켓/쿠폰',
        '🏷️' => '할인태그',
        '💰' => '돈/수익',
        '💳' => '신용카드',
        '💸' => '할인/캐시',
        '🕧' => '영업시간',
        '📍' => '매장위치',
        '📞' => '전화문의',
        '📱' => '앱/문자',
        '💬' => '상담/채팅'
    ],
    '부동산 & 숙박 (Realty/Stay)' => [
        '🏠' => '주택',
        '🏘️' => '빌리지',
        '🏢' => '콘도/빌딩',
        '🏨' => '호텔/숙박',
        '🔑' => '열쇠/입주',
        '🛋️' => '가구/인테리어',
        '🛏️' => '침대/휴식',
        '🛁' => '욕실/청결',
        '🧹' => '청소완료',
        '📝' => '계약서',
        '🤝' => '계약성사',
        '💼' => '비즈니스',
        '🏙️' => '도시풍경',
        '🌳' => '정원/자연',
        '🅿️' => '주차장'
    ],
    '기분 & 소통 (센스 있는 표현)' => [
        '👍' => '최고/좋아요',
        '🙌' => '만세/성공',
        '👏' => '박수',
        '👊' => '파이팅/의지',
        '💪' => '힘내요',
        '😎' => '멋짐/자신감',
        '😊' => '미소',
        '😄' => '활짝미소',
        '😉' => '윙크',
        '😜' => '장난',
        '🥳' => '파티/축하',
        '🥺' => '간절/감동',
        '💯' => '100점',
        '✅' => '확인완료',
        '🌈' => '무지개/희망',
        '🔥' => '인기/열정',
        '🎉' => '축하/이벤트'
    ],
    '운영 & 공지 (강조 표시용)' => [
        '🔔' => '공지알림',
        '📢' => '새소식',
        '📣' => '안내방송',
        '🚀' => '로켓출발',
        '🆕' => '신상품',
        '⭐' => '중요별표',
        '❗' => '주의/경고',
        '❓' => '질문/QnA',
        '🔒' => '보안/비밀',
        '💡' => '꿀팁/아이디어',
        '☀️' => '여름/맑음',
        '☔' => '비/우천',
        '❄️' => '겨울/눈',
        '🍃' => '봄/가을',
        '📸' => '사진인증',
        '🎥' => '동영상',
        '�' => '음악/분위기',
        '💻' => '온라인예약',
        '🏆' => '1등/우수'
    ]
];

?>

<style>
    /* 이모티콘 전용 스타일 */
    .emoji-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
        gap: 10px;
    }

    .emoji-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        position: relative;
    }

    .emoji-item:hover {
        background: #eef2f5;
        border-color: #cce5ff;
        transform: scale(1.05);
    }

    .emoji-char {
        font-size: 2rem;
        line-height: 1.2;
        margin-bottom: 5px;
    }

    .emoji-desc {
        font-size: 0.65rem;
        color: #6c757d;
        text-align: center;
        word-break: keep-all;
        line-height: 1.1;
    }

    .copy-toast {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(51, 51, 51, 0.9);
        color: white;
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 0.9rem;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .copy-toast i {
        color: #2ecc71;
        margin-right: 5px;
    }

    /* 모바일 최적화 추가 */
    @media (max-width: 576px) {
        .emoji-grid {
            grid-template-columns: repeat(auto-fill, minmax(55px, 1fr));
            gap: 8px;
        }

        .emoji-item {
            padding: 8px;
            border-radius: 8px;
        }

        .emoji-char {
            font-size: 1.75rem;
        }
    }
</style>

<div class="container-fluid p-0">
    <?php echo renderPageHeader('사장님 전용 이모티콘 꿀팁', 'bi-emoji-smile', '<span class="badge bg-white text-secondary border shadow-sm px-3 py-2 rounded-pill"><i class="bi bi-mouse me-1"></i> 이모티콘 클릭 시 즉시 복사됩니다.</span>'); ?>
    <div class="text-left text-muted mt-4 mb-5 small">
        <div class="me-1 mb-1"><i class="bi bi-info-circle me-1 mb-1"></i> 이모티콘은 기기(PC/모바일) 및 브라우저에 따라 모양이 조금씩 다르게 보일 수 있습니다.</div>
        <div class="me-1 mb-1"><i class="bi bi-info-circle me-1 mb-1"></i> 더 많은 이모티콘은 <a href="https://getemoji.com/" target="_blank" class="text-decoration-underline">https://getemoji.com/</a>에서 확인할 수 있습니다.</div>
    </div>
    <div class="row g-4">
        <?php foreach ($emoji_list as $category => $emojis): ?>
            <div class="col-12 col-lg-6">
                <div class="<?php echo UI_SECTION_CARD; ?> transition-all">
                    <div class="p-3 p-md-4 d-flex flex-column h-100">
                        <?php
                        // 카테고리별 아이콘 지정
                        $icon = 'bi-tags text-dark';
                        if (strpos($category, '환영') !== false) $icon = 'bi-heart-fill text-danger';
                        if (strpos($category, '음식') !== false) $icon = 'bi-egg-fried text-warning';
                        if (strpos($category, '쇼핑') !== false) $icon = 'bi-cart-fill text-primary';
                        if (strpos($category, '부동산') !== false) $icon = 'bi-buildings-fill text-info';
                        if (strpos($category, '기분') !== false) $icon = 'bi-hand-thumbsup-fill text-success';
                        if (strpos($category, '운영') !== false) $icon = 'bi-megaphone-fill text-secondary';

                        echo renderSectionHeader($category, $icon);
                        ?>
                        <div class="emoji-grid mt-2">
                            <?php foreach ($emojis as $emoji => $desc): ?>
                                <div class="emoji-item" onclick="copyEmoji(this, '<?php echo $emoji; ?>', '<?php echo htmlspecialchars($desc, ENT_QUOTES); ?>')" title="<?php echo htmlspecialchars($desc); ?>">
                                    <span class="emoji-char"><?php echo $emoji; ?></span>
                                    <span class="emoji-desc"><?php echo htmlspecialchars($desc); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<div id="copyToast" class="copy-toast">
    <i class="bi bi-check-circle-fill"></i> <span id="toastEmoji" class="me-2 fs-5"></span><span id="toastDesc"></span>
</div>

<script>
    /**
     * 이모티콘 클릭 시 클립보드에 복사하는 함수
     */
    function copyEmoji(element, emoji, desc) {
        // 클릭 애니메이션 효과
        element.style.transform = 'scale(0.9)';
        setTimeout(() => element.style.transform = 'none', 150);

        // 최신 브라우저 클립보드 API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(emoji).then(() => {
                showCopyToast(emoji, desc);
            });
        } else {
            // 구형 브라우저 또는 권한 없는 경우를 위한 Fallback
            copyEmojiFallback(emoji, desc);
        }
    }

    /**
     * 구형 브라우저를 위한 클립보드 복사 Fallback 함수
     */
    function copyEmojiFallback(emoji, desc) {
        const textArea = document.createElement("textarea");
        textArea.value = emoji;
        // iOS에서 스크롤 문제를 방지하기 위한 스타일 설정
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyToast(emoji, desc);
            } else {
                alert('복사에 실패했습니다. 직접 복사해 주세요.');
            }
        } catch (err) {
            alert('이 브라우저에서는 복사 기능을 지원하지 않습니다.');
        }

        document.body.removeChild(textArea);
    }
    let toastTimeout;

    /**
     * 복사 완료 알림(Toast)을 표시하는 함수
     */
    function showCopyToast(emoji, desc) {
        const toast = document.getElementById('copyToast');
        const toastEmoji = document.getElementById('toastEmoji');
        const toastDesc = document.getElementById('toastDesc');

        // Toast 내용 설정
        toastEmoji.textContent = emoji;
        toastDesc.textContent = desc;

        // 이전 타이머가 있다면 제거 (애니메이션 꼬임 방지)
        clearTimeout(toastTimeout);

        // Toast 표시 (애니메이션 효과)
        toast.style.display = 'block';
        toast.style.opacity = '1';

        // 2초 후 Toast 숨기기
        toastTimeout = setTimeout(() => {
            toast.style.opacity = '0';
            // 애니메이션 시간(0.5s) 후 완전히 숨김
            setTimeout(() => {
                if (toast.style.opacity === '0') {
                    toast.style.display = 'none';
                }
            }, 500);
        }, 2000);
    }
</script>