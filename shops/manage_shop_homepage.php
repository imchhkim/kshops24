<?php

/**
 * KShops24 홈페이지 설정 모듈 (manage_shop_homepage.php)
 * - 역할: 상점의 공지사항, 디자인 테마, 메인 배경 이미지, 소개 문구, 갤러리, 구글 지도 수정
 * - manage_shop.php에서 $pg === 'manage_shop_homepage' 일 때 include 됩니다.
 */

if (!isset($shop_id)) exit; // 직접 접근 방지

// ---------------------------------------------------------
// [AJAX] 사진 갤러리 이미지 개별 삭제 처리
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_gallery_img') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $img_id = (int)$_POST['img_id'];
    try {
        $stmt_old = $pdo->prepare("SELECT img_path FROM shop_images WHERE id = ? AND shop_id = ?");
        $stmt_old->execute([$img_id, $shop_id]);
        $old_path = $stmt_old->fetchColumn();

        if ($old_path) {
            deletePhysicalFiles($old_path);
            $pdo->prepare("DELETE FROM shop_images WHERE id = ? AND shop_id = ?")->execute([$img_id, $shop_id]);
        }
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// 홈페이지 설정값(POST) 저장 메인 로직
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop'])) {
    try {
        // 1. 다중 배경 이미지(bg_path) 물리 파일 삭제 감지 및 처리
        if (isset($_POST['bg_path'])) {
            $new_decoded = json_decode($_POST['bg_path'], true);
            if (is_string($new_decoded) && strpos(trim($new_decoded), '[') === 0) $new_decoded = json_decode($new_decoded, true);
            $new_bg_paths = is_array($new_decoded) ? $new_decoded : [$_POST['bg_path']];

            $old_bg_paths = [];
            if (!empty($shop['bg_path'])) {
                $decoded = json_decode($shop['bg_path'], true);
                if (is_string($decoded) && strpos(trim($decoded), '[') === 0) $decoded = json_decode($decoded, true);
                $old_bg_paths = is_array($decoded) ? $decoded : [$shop['bg_path']];
            }

            $deleted_bgs = [];
            foreach ($old_bg_paths as $p) {
                if (is_string($p) && !empty($p) && !in_array($p, $new_bg_paths)) {
                    $deleted_bgs[] = $p;
                }
            }
            if (!empty($deleted_bgs)) deletePhysicalFiles($deleted_bgs);
        }

        // 2. 여러 개의 유튜브 링크를 배열로 받아 빈 값을 제거하고 JSON 변환
        if (isset($_POST['shop_youtube_url']) && is_array($_POST['shop_youtube_url'])) {
            $youtube_urls = [];
            foreach ($_POST['shop_youtube_url'] as $url) {
                if (trim($url) !== '') {
                    $youtube_urls[] = trim($url);
                }
            }
            $_POST['shop_youtube_url'] = json_encode($youtube_urls, JSON_UNESCAPED_SLASHES);
        }

        // 3. 홈페이지 전용 업데이트 필드 구성
        $updatable_fields = [
            'top_label', 'main_title', 'sub_title', 'shop_intro', 'shop_description',
            'shop_map_html', 'shop_skin', 'shop_font', 'shop_youtube_url',
            'urgent_notice', 'bg_path', 'general_notice',
            'is_show_main_title', 'is_show_story', 'is_show_gallery', 'is_show_map'
        ];

        $update_parts = [];
        $params = [];
        foreach ($updatable_fields as $field) {
            if (isset($_POST[$field])) {
                $update_parts[] = "$field = ?";
                $val = $_POST[$field];
                if (in_array($field, ['is_show_main_title', 'is_show_story', 'is_show_gallery', 'is_show_map'])) {
                    $val = (int)$val;
                }
                $params[] = $val;
            }
        }

        // 4. UI 레이블 설정 처리 (JSON 병합)
        if (isset($_POST['ui'])) {
            $existing_ui = json_decode($shop['ui_settings'] ?? '{}', true);
            if (!is_array($existing_ui)) $existing_ui = [];
            $ui_raw = $_POST['ui'];
            $ui_new = array_map('trim', $ui_raw);
            $ui_new = array_filter($ui_new, fn($v) => $v !== '');
            $ui_merged = array_merge($existing_ui, $ui_new);
            $update_parts[] = "ui_settings = ?";
            $params[] = json_encode($ui_merged, JSON_UNESCAPED_UNICODE);
        }

        // DB 반영
        if (!empty($update_parts)) {
            $sql = "UPDATE shops SET " . implode(', ', $update_parts) . " WHERE id = ?";
            $params[] = $shop_id;
            $pdo->prepare($sql)->execute($params);
        }

        // 5. 갤러리 이미지 정렬 순서 업데이트 (드래그앤드롭 반영)
        if (isset($_POST['gallery_order'])) {
            $gallery_order = json_decode($_POST['gallery_order'], true);
            if (is_array($gallery_order)) {
                $stmt_update_order = $pdo->prepare("UPDATE shop_images SET sort_order = ? WHERE shop_id = ? AND img_path = ?");
                foreach ($gallery_order as $index => $path) {
                    $stmt_update_order->execute([$index + 1, $shop_id, $path]);
                }
            }
        }

        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => '홈페이지 설정이 성공적으로 저장되었습니다.']);
            exit;
        }

        $message = "홈페이지 설정이 성공적으로 저장되었습니다.";
        $msg_type = "success";

        // 업데이트가 끝나면 현재 페이지에 최신 상태를 반영하기 위해 다시 조회
        $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
        $stmt->execute([$shop_id]);
        $shop = $stmt->fetch();
    } catch (Exception $e) {
        if (isset($_POST['ajax_update'])) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
        $message = $e->getMessage();
        $msg_type = "danger";
    }
}

// ---------------------------------------------------------
// 갤러리 데이터 준비 및 언어 변수 세팅
// ---------------------------------------------------------
$stmt_imgs = $pdo->prepare("SELECT id, img_path FROM shop_images WHERE shop_id = ? ORDER BY sort_order ASC, id ASC");
$stmt_imgs->execute([$shop_id]);
$shop_imgs = $stmt_imgs->fetchAll();

$ui = json_decode($shop['ui_settings'] ?? '{}', true);
$supported_langs_name = [
    'ko' => '한국어', 'en' => '영어', 'zh' => '중국어', 'ja' => '일본어',
    'es' => '스페인어', 'fr' => '프랑스어', 'ru' => '러시아어', 'vi' => '베트남어', 'etc' => '기타 언어'
];

$is_multi = (($ui['is_multilingual'] ?? 0) == 1);
$lang1 = $ui['multilingual_lang1'] ?? 'none';
$lang2 = $ui['multilingual_lang2'] ?? 'none';

$lang1_display = ($lang1 === 'etc') ? ($ui['multilingual_lang1_custom_name'] ?? '제1외국어') : ($supported_langs_name[$lang1] ?? '제1외국어');
$lang1_code = $lang1;
if ($lang1 === 'etc') {
    $lang1_code = strtolower(trim($ui['multilingual_lang1_custom_code'] ?? 'etc1'));
    if (empty($lang1_code)) $lang1_code = 'etc1';
}

$lang2_display = ($lang2 === 'etc') ? ($ui['multilingual_lang2_custom_name'] ?? '제2외국어') : ($supported_langs_name[$lang2] ?? '제2외국어');
$lang2_code = $lang2;
if ($lang2 === 'etc') {
    $lang2_code = strtolower(trim($ui['multilingual_lang2_custom_code'] ?? 'etc2'));
    if (empty($lang2_code)) $lang2_code = 'etc2';
}
?>

<!-- 최상단 타이틀 -->
<?php echo renderPageHeader('홈페이지 관리', 'bi-window-sidebar'); ?>

<!-- 상점 공지사항 관리 섹션 -->
<div class="col-12 mb-4">
    <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-danger">
        <form method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="handleAjaxFormSubmit(event)">
            <input type="hidden" name="update_shop" value="1">
            <?php echo renderSectionHeader('상점 공지사항 관리', 'bi-megaphone text-danger'); ?>
            <div class="mb-3">
                <label class="form-label text-dark fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> 긴급 공지사항</label>
                <textarea name="urgent_notice" class="form-control border-danger border-opacity-50 bg-white" rows="2" placeholder="휴무, 배달 지연 등 긴급하게 알릴 내용을 입력하세요."><?php echo htmlspecialchars($shop['urgent_notice'] ?? ''); ?></textarea>
                <p <?php echo UI_INFO_SM_LABEL;?>> 내용을 입력한 경우에만, 상점 메인 최상단에 붉은색으로 강조되어 노출됩니다.</p>
            </div>
            <div class="mb-0">
                <label class="form-label text-dark fw-bold"><i class="bi bi-info-circle-fill text-info me-1"></i> 일반 공지사항</label>
                <textarea name="general_notice" class="form-control bg-white" rows="3" placeholder="이벤트, 영업시간 변경 등 일반적인 공지사항을 입력하세요."><?php echo htmlspecialchars($shop['general_notice'] ?? ''); ?></textarea>
                <p <?php echo UI_INFO_SM_LABEL;?>> 내용을 입력한 경우에만, 공지사항이 노출됩니다.</p>
            </div>
            <div class="text-end mt-3 pt-3 border-top">
                <button type="submit" name="update_shop" class="btn btn-danger btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 공지사항 저장</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- 02. 상점 디자인 테마 설정 -->
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-primary">
            <form method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="handleAjaxFormSubmit(event)">
                <input type="hidden" name="update_shop" value="1">
                <?php echo renderSectionHeader('상점 디자인 테마 설정', 'bi-magic'); ?>
                <div class="row g-4 mt-1">
                    <div class="col-12 mb-2">
                        <label class="form-label text-dark fw-bold mb-3"><i class="bi bi-palette2 me-1"></i>배경 스킨 테마</label>
                        <p <?php echo UI_INFO_SM_LABEL;?>> 모바일 화면에 어울리는 전체적인 색상 톤을 선택합니다.</p>
                        <div class="row g-3">
                            <?php
                            $skins = [
                                'default' => ['name' => '기본 화이트', 'bg' => '#ffffff', 'primary' => '#004aad', 'text' => '#333333'], 
                                'dark' => ['name' => '모던 다크', 'bg' => '#222222', 'primary' => '#4a90e2', 'text' => '#eeeeee'], 
                                'luxury' => ['name' => '럭셔리 골드', 'bg' => '#fcf8e3', 'primary' => '#b8860b', 'text' => '#5d4037'], 
                                'nature' => ['name' => '네이처 그린', 'bg' => '#f1f8e9', 'primary' => '#388e3c', 'text' => '#2e7d32'],
                                'ocean' => ['name' => '오션 블루', 'bg' => '#f0f8ff', 'primary' => '#007bff', 'text' => '#003366'],
                                'romance' => ['name' => '로맨스 핑크', 'bg' => '#fff0f5', 'primary' => '#ff6b81', 'text' => '#4a0e2e']
                            ];
                            foreach ($skins as $key => $val):
                            ?>
                                <div class="col-6 col-sm-4">
                                    <input type="radio" class="btn-check" name="shop_skin" id="skin_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo ($shop['shop_skin'] ?? 'default') == $key ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-light text-dark w-100 p-2 border shadow-xs d-flex flex-column align-items-center transition-all" for="skin_<?php echo $key; ?>">
                                        <div style="width: 24px; height: 24px; background: <?php echo $val['bg']; ?>; border: 2px solid <?php echo $val['primary']; ?>;" class="rounded-circle mb-1"></div>
                                        <span style="font-size: 0.7rem;" class="fw-bold"><?php echo $val['name']; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-dark fw-bold mb-3"><i class="bi bi-fonts me-1"></i>폰트 스타일</label>
                        <!-- 실시간 폰트 미리보기 및 버튼 라벨을 위한 웹폰트(Web Font) 강제 로드 -->
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
                        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&family=Nanum+Gothic:wght@400;700&family=Nanum+Myeongjo:wght@400;700&display=swap" rel="stylesheet">
                        <div class="row g-3">
                            <?php $fonts = ['Pretendard' => '고딕(깔끔함)', 'Noto Sans KR' => '본고딕(표준)', 'Nanum Gothic' => '나눔(부드러움)', 'Nanum Myeongjo' => '명조(우아함)'];
                            foreach ($fonts as $f_key => $f_val): ?>
                                <div class="col-6"><input type="radio" class="btn-check" name="shop_font" id="font_<?php echo $f_key; ?>" value="<?php echo $f_key; ?>" <?php echo ($shop['shop_font'] ?? 'Pretendard') == $f_key ? 'checked' : ''; ?>><label class="btn btn-outline-light text-dark w-100 py-2 border shadow-xs text-start transition-all" for="font_<?php echo $f_key; ?>" style="font-family:'<?php echo $f_key; ?>',sans-serif;font-size:0.75rem;"><small><?php echo $f_val; ?></small></label></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- 실시간 모바일 미리보기 영역 -->
                    <div class="col-12 mt-4 pt-3 border-top">
                        <label class="form-label text-dark fw-bold mb-3"><i class="bi bi-phone me-1 text-primary"></i>모바일 실시간 미리보기</label>
                        <div class="d-flex justify-content-center">
                            <div id="theme-preview-box" class="shadow-lg border border-4 border-dark" style="width: 280px; height: 500px; border-radius: 35px; overflow: hidden; position: relative; background-color: var(--preview-bg, #ffffff); color: var(--preview-text, #333333); font-family: var(--preview-font, 'Pretendard'), sans-serif; transition: all 0.4s ease;">
                                <!-- 모바일 상단 바 (더미) -->
                                <div style="background-color: rgba(0,0,0,0.03); padding: 20px 15px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                    <div style="font-weight: 900; font-size: 1rem;"><i class="bi bi-shop me-1" style="color: var(--preview-primary, #004aad);"></i> My Shop</div>
                                    <i class="bi bi-list fs-4"></i>
                                </div>
                                <!-- 메인 컨텐츠 영역 -->
                                <div style="padding: 20px;">
                                    <div style="font-size: 0.75rem; letter-spacing: 1px; color: var(--preview-primary, #004aad); font-weight: bold; text-transform: uppercase;">WELCOME</div>
                                    <h4 style="font-weight: 800; margin-top: 5px; margin-bottom: 15px; line-height: 1.3;">멋진 상점<br>만들기</h4>
                                    <div style="width: 40px; height: 3px; background-color: var(--preview-primary, #004aad); margin-bottom: 15px;"></div>
                                    <p style="font-size: 0.8rem; opacity: 0.8; line-height: 1.5;">테마와 폰트 설정에 따라 내 상점의 전체적인 분위기가 어떻게 변하는지 실시간으로 확인해 보세요.</p>
                                    <button type="button" style="background-color: var(--preview-primary, #004aad); color: #fff; border: none; border-radius: 50px; padding: 12px 20px; width: 100%; font-weight: bold; margin-top: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-size: 0.9rem;">주문하기 / 예약하기</button>
                                    
                                    <!-- 카테고리 예시 -->
                                    <div style="margin-top: 25px;">
                                        <div style="background-color: var(--preview-primary, #004aad); color: white; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 0.75rem; margin-bottom: 10px; display: inline-block;">
                                            추천 항목
                                        </div>
                                        <div style="display: flex; gap: 10px;">
                                            <div style="flex: 1; background: rgba(0,0,0,0.05); border-radius: 12px; height: 90px; display:flex; flex-direction:column; justify-content:flex-end; padding:8px;">
                                                <div style="width: 70%; height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; margin-bottom: 4px;"></div>
                                                <div style="width: 40%; height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px;"></div>
                                            </div>
                                            <div style="flex: 1; background: rgba(0,0,0,0.05); border-radius: 12px; height: 90px; display:flex; flex-direction:column; justify-content:flex-end; padding:8px;">
                                                <div style="width: 70%; height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; margin-bottom: 4px;"></div>
                                                <div style="width: 40%; height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        const themeData = {
                            'default': { bg: '#ffffff', text: '#333333', primary: '#004aad' },
                            'dark': { bg: '#222222', text: '#eeeeee', primary: '#4a90e2' },
                            'luxury': { bg: '#fcf8e3', text: '#5d4037', primary: '#b8860b' },
                            'nature': { bg: '#f1f8e9', text: '#2e7d32', primary: '#388e3c' },
                            'ocean': { bg: '#f0f8ff', text: '#003366', primary: '#007bff' },
                            'romance': { bg: '#fff0f5', text: '#4a0e2e', primary: '#ff6b81' }
                        };

                        function updateThemePreview() {
                            const previewBox = document.getElementById('theme-preview-box');
                            if (!previewBox) return;

                            const selectedSkin = document.querySelector('input[name="shop_skin"]:checked')?.value || 'default';
                            const selectedFont = document.querySelector('input[name="shop_font"]:checked')?.value || 'Pretendard';

                            const t = themeData[selectedSkin] || themeData['default'];

                            previewBox.style.setProperty('--preview-bg', t.bg);
                            previewBox.style.setProperty('--preview-text', t.text);
                            previewBox.style.setProperty('--preview-primary', t.primary);
                            // 띄어쓰기가 포함된 폰트명(Noto Sans KR 등)의 정상 인식을 위해 따옴표 래핑 처리
                            previewBox.style.setProperty('--preview-font', "'" + selectedFont + "'");
                        }

                        document.addEventListener('DOMContentLoaded', () => {
                            document.querySelectorAll('input[name="shop_skin"], input[name="shop_font"]').forEach(el => {
                                el.addEventListener('change', updateThemePreview);
                            });
                            updateThemePreview();
                        });
                    </script>
                </div>
                <div class="mt-auto text-end pt-3 border-top">
                    <button type="submit" name="update_shop" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 테마 설정 저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 03. 이미지 리소스 관리 -->
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-primary" id="section-resource">
            <div class="p-3 p-md-4 d-flex flex-column h-100">
                <form class="mb-4" method="POST" onsubmit="handleAjaxFormSubmit(event)">
                    <input type="hidden" name="update_shop" value="1">
                    <?php echo renderSectionHeader('이미지 리소스 관리', 'bi-image'); ?>
                    <div class="mb-4 text-left">
                        <label class="form-label small fw-bold text-muted">1. 상점 로고 (8:2)</label>
                        <p <?php echo UI_INFO_SM_LABEL;?>> 로고 이미지는 교체시 바로 적용됩니다. 로고는 chatGPT같은 AI에 다음처럼 요청하여 생성할 수 있습니다. </p>
                        <p <?php echo UI_INFO_SM_LABEL;?>> 8:2비율의 상점 로고를 만들고 싶어. 중고차 매매/렌트/서류작업을 하는 상점이야. 상점명은 "자동차 나라". 상점 설명문구는 "자동차로 행복한 삶을~". 멋지게 만들어줘. </p>
                        <div id="section-logo-setup" class="text-center mt-2">
                            <div class="d-inline-block shadow-sm border rounded overflow-hidden bg-white">
                                <div id="logo-preview" style="width: 200px; height: 50px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa;">
                                    <img src="<?php echo $shop['logo_path'] ?: '/assets/no-logo.png'; ?>" id="preview-img-logo" alt="상점 로고" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                            </div>
                        </div>
                        <input type="file" id="logo-file" class="d-none" accept="image/*" onchange="initCrop(this, 'logo')" onclick="this.value=null;">
                        <button type="button" onclick="document.getElementById('logo-file').click()" class="btn btn-outline-primary w-100 py-2 rounded-pill fw-bold btn-sm"><i class="bi bi-image me-2"></i>로고 교체</button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded border">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark small">상점 이름(한글) 함께 노출</h6>
                            <p class="text-muted small mb-0" style="font-size: 0.7rem;">홈페이지 상단 로고 오른쪽에 상점명(한글)을 표시합니다.</p>
                        </div>
                        <div class="form-check form-switch m-0 fs-5">
                            <input type="hidden" name="ui[is_show_logo_text]" value="0">
                            <input class="form-check-input" type="checkbox" name="ui[is_show_logo_text]" id="is_show_logo_text" value="1" <?php echo (($ui['is_show_logo_text'] ?? 1) == 1) ? 'checked' : ''; ?> onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))">
                        </div>
                    </div>
                </form>
                <hr class="my-4">
                <form id="bg-form" class="flex-grow-1 d-flex flex-column" onsubmit="saveImageBatch(event, 'background')">
                    <input type="hidden" name="update_shop" value="1">
                    <input type="hidden" name="bg_path" id="bg_path_input" value='<?php echo htmlspecialchars($shop['bg_path'] ?? '[]', ENT_QUOTES); ?>'>
                    <div class="mb-0 flex-grow-1 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-muted">2. 메인 배경 이미지 관리</label>
                            <button type="button" onclick="document.getElementById('bg-file-multi').click()" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i>사진 추가</button>
                        </div>
                        <p <?php echo UI_INFO_SM_LABEL;?>> 배경 이미지를 드래그하여 순서를 바꿀 수 있습니다. 수정 후에는 꼭 "배경 이미지 저장" 버튼을 눌러주세요. </p>
                        <p <?php echo UI_INFO_SM_LABEL;?>> 배경 이미지의 최적의 해상도: 1920 x 1080 픽셀, 최적 비율: 16:9 (가로:세로)</p>
                        <div id="bg-list-container" class="row g-2 p-1 border rounded shadow-inner row-cols-2 row-cols-md-3 mt-3" style="min-height: 90px; background: #f8f9fa;">
                            <?php
                            $bg_paths = [];
                            if (!empty($shop['bg_path'])) {
                                $decoded = json_decode($shop['bg_path'], true);
                                $bg_paths = is_array($decoded) ? $decoded : [$shop['bg_path']];
                            }
                            foreach ($bg_paths as $idx => $path):
                            ?>
                                <div class="col gallery-item" id="background-item-<?php echo htmlspecialchars($path, ENT_QUOTES); ?>" data-path="<?php echo htmlspecialchars($path); ?>" style="cursor: grab;">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($path); ?>" class="w-100 rounded border shadow-sm" style="aspect-ratio: 16/9; object-fit: cover;">
                                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle" style="width:20px; height:20px; margin: 4px;" onclick="event.stopPropagation(); deleteBatchImage('background', '<?php echo htmlspecialchars($path, ENT_QUOTES); ?>')"><i class="bi bi-x" style="font-size: 0.8rem; vertical-align: top;"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="empty-msg text-muted small w-100 text-center my-auto <?php echo empty($bg_paths) ? '' : 'd-none'; ?>">등록된 배경 이미지가 없습니다.</div>
                            <button type="button" class="btn-add-img d-none"></button>
                        </div>
                        <input type="file" id="bg-file-multi" class="d-none" accept="image/*" multiple onchange="addBatchImage('background', this)">
                        <div class="mt-auto text-end pt-3 border-top mt-3">
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 배경 이미지 저장</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- 04. 메인 홍보 문구 관리 -->
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-primary">
            <form method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="handleAjaxFormSubmit(event)">
                <input type="hidden" name="update_shop" value="1">
                <?php echo renderSectionHeader('메인 홍보 문구 관리', 'bi-layout-text-window-reverse'); ?>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_show_main_title" value="0">
                    <input class="form-check-input" type="checkbox" name="is_show_main_title" id="is_show_main_title" value="1" <?php echo (($shop['is_show_main_title'] ?? 1) == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label small fw-bold text-primary" for="is_show_main_title">홈페이지 노출</label>
                </div>

                <?php if ($is_multi && ($lang1 !== 'none' || $lang2 !== 'none')): ?>
                    <ul class="nav nav-tabs mb-3" id="promo-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold px-3 py-2" id="promo-ko-tab" data-bs-toggle="tab" data-bs-target="#promo-ko-pane" type="button" role="tab">한국어 (기본)</button>
                        </li>
                        <?php if ($lang1 !== 'none'): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold px-3 py-2" id="promo-lang1-tab" data-bs-toggle="tab" data-bs-target="#promo-lang1-pane" type="button" role="tab"><?php echo htmlspecialchars($lang1_display); ?></button>
                            </li>
                        <?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold px-3 py-2" id="promo-lang2-tab" data-bs-toggle="tab" data-bs-target="#promo-lang2-pane" type="button" role="tab"><?php echo htmlspecialchars($lang2_display); ?></button>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>

                <!-- 탭 콘텐츠 -->
                <div class="tab-content flex-grow-1" id="promo-tabContent">
                    <div class="tab-pane fade show active" id="promo-ko-pane" role="tabpanel">
                        <?php
                        // [추가] 각 항목별 디자인 설정(색상, 폰트, 효과)을 렌더링하는 공용 헬퍼 함수
                        if (!function_exists('renderPromoStyleConfig')) {
                            function renderPromoStyleConfig($prefix, $ui) {
                                $effects = ['none' => '기본', 'neon' => '네온사인', 'shadow' => '입체 그림자', 'outline' => '외곽선'];
                                $fonts = ['' => '상점 기본 폰트', 'Pretendard' => '고딕 (Pretendard)', 'Nanum Myeongjo' => '명조 (Myeongjo)', 'Noto Sans KR' => '본고딕 (Noto Sans)'];
                                $sizes = ['' => '기본 크기', '0.75rem' => '아주 작게', '0.9rem' => '작게', '1rem' => '보통', '1.25rem' => '약간 크게', '1.5rem' => '크게', '2rem' => '아주 크게', '2.5rem' => '특대', '3rem' => '초대형'];
                                
                                // 빈 값 방어: 반드시 올바른 형태의 색상 코드가 들어가도록 강제합니다.
                                $current_color = !empty($ui["{$prefix}_color"]) ? $ui["{$prefix}_color"] : '#ffffff';
                                $current_effect = $ui["{$prefix}_effect"] ?? 'none';
                                $current_font = $ui["{$prefix}_font"] ?? '';
                                $current_size = $ui["{$prefix}_size"] ?? '';
                                
                                $html = '<div class="d-flex flex-wrap align-items-end gap-3 mt-2 bg-light p-3 rounded border border-light">';
                                
                                // 1. 글자 색상 선택기
                                $html .= '<div><label class="form-label small text-muted mb-1">글자색</label>';
                                $html .= '<input type="color" name="ui['.$prefix.'_color]" class="form-control form-control-color shadow-sm" style="width: 2.5rem; height: 2rem; padding: 0.25rem; cursor: pointer;" value="'.htmlspecialchars($current_color).'" title="색상 선택"></div>';

                                // 2. 개별 폰트(글자체) 선택
                                $html .= '<div><label class="form-label small text-muted mb-1">글자체</label>';
                                $html .= '<select name="ui['.$prefix.'_font]" class="form-select form-select-sm shadow-sm" style="min-width: 140px;">';
                                foreach($fonts as $val => $name) {
                                    $selected = ($current_font === $val) ? 'selected' : '';
                                    $font_style = $val ? "font-family: '{$val}', sans-serif;" : "";
                                    $html .= '<option value="'.$val.'" '.$selected.' style="'.$font_style.'">'.$name.'</option>';
                                }
                                $html .= '</select></div>';
                                
                                // 3. 글자 크기 선택
                                $html .= '<div><label class="form-label small text-muted mb-1">글자 크기</label>';
                                $html .= '<select name="ui['.$prefix.'_size]" class="form-select form-select-sm shadow-sm" style="min-width: 120px;">';
                                foreach($sizes as $val => $name) {
                                    $selected = ($current_size === $val) ? 'selected' : '';
                                    $html .= '<option value="'.$val.'" '.$selected.'>'.$name.'</option>';
                                }
                                $html .= '</select></div>';
                                
                                // 4. 텍스트 효과 (형태)
                                $html .= '<div><label class="form-label small text-muted mb-1">텍스트 효과</label>';
                                $html .= '<select name="ui['.$prefix.'_effect]" class="form-select form-select-sm shadow-sm" style="min-width: 120px;">';
                                foreach($effects as $k => $v) {
                                    $selected = ($current_effect === $k) ? 'selected' : '';
                                    $html .= '<option value="'.$k.'" '.$selected.'>'.$v.'</option>';
                                }
                                $html .= '</select></div></div>';
                                return $html;
                            }
                        }
                        ?>

                        <div class="mb-4">
                            <label class="form-label text-dark small fw-bold">1. 상단 라벨</label>
                            <input type="text" name="top_label" class="form-control" value="<?php echo htmlspecialchars($shop['top_label'] ?? ''); ?>" placeholder="예: 필리핀 1등 한식당">
                            <?php echo renderPromoStyleConfig('top_label', $ui); ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-dark small fw-bold">2. 메인 타이틀</label>
                            <input type="text" name="main_title" class="form-control form-control-lg fw-bold" value="<?php echo htmlspecialchars($shop['main_title'] ?? ''); ?>" placeholder="예: 우리집 레스토랑">
                            <?php echo renderPromoStyleConfig('main_title', $ui); ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-dark small fw-bold">3. 하단 설명</label>
                            <input type="text" name="sub_title" class="form-control" value="<?php echo htmlspecialchars($shop['sub_title'] ?? ''); ?>" placeholder="예: 정성을 담은 요리로 모시겠습니다.">
                            <?php echo renderPromoStyleConfig('sub_title', $ui); ?>
                        </div>
                        
                        <div class="form-text small mt-1"><i class="bi bi-info-circle me-1"></i>위의 디자인 및 스타일 설정은 모든 언어에 공통으로 자동 적용됩니다.</div>
                    </div>
                    <?php if ($is_multi && $lang1 !== 'none'): ?>
                        <div class="tab-pane fade" id="promo-lang1-pane" role="tabpanel">
                            <div class="mb-3"><label class="form-label text-dark small fw-bold">1. 상단 라벨 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[top_label_<?php echo $lang1_code; ?>]" class="form-control" value="<?php echo htmlspecialchars($ui['top_label_' . $lang1_code] ?? ''); ?>"></div>
                            <div class="mb-3"><label class="form-label text-dark small fw-bold">2. 메인 타이틀 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[main_title_<?php echo $lang1_code; ?>]" class="form-control form-control-lg fw-bold" value="<?php echo htmlspecialchars($ui['main_title_' . $lang1_code] ?? ''); ?>"></div>
                            <div class="mb-0"><label class="form-label text-dark small fw-bold">3. 하단 설명 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[sub_title_<?php echo $lang1_code; ?>]" class="form-control" value="<?php echo htmlspecialchars($ui['sub_title_' . $lang1_code] ?? ''); ?>"></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($is_multi && $lang2 !== 'none'): ?>
                        <div class="tab-pane fade" id="promo-lang2-pane" role="tabpanel">
                            <div class="mb-3"><label class="form-label text-dark small fw-bold">1. 상단 라벨 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[top_label_<?php echo $lang2_code; ?>]" class="form-control" value="<?php echo htmlspecialchars($ui['top_label_' . $lang2_code] ?? ''); ?>"></div>
                            <div class="mb-3"><label class="form-label text-dark small fw-bold">2. 메인 타이틀 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[main_title_<?php echo $lang2_code; ?>]" class="form-control form-control-lg fw-bold" value="<?php echo htmlspecialchars($ui['main_title_' . $lang2_code] ?? ''); ?>"></div>
                            <div class="mb-0"><label class="form-label text-dark small fw-bold">3. 하단 설명 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[sub_title_<?php echo $lang2_code; ?>]" class="form-control" value="<?php echo htmlspecialchars($ui['sub_title_' . $lang2_code] ?? ''); ?>"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-auto text-end pt-3 border-top">
                    <button type="submit" name="update_shop" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 홍보 문구 저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 05. 상점 소개 상세 관리 -->
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-primary">
            <form id="story-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="syncQuillEditor(); handleAjaxFormSubmit(event)">
                <input type="hidden" name="update_shop" value="1">
                <?php echo renderSectionHeader('상점 소개 상세 관리', 'bi-pencil-square'); ?>
                <div class="form-check form-switch mb-1"><input type="hidden" name="is_show_story" value="0"><input class="form-check-input" type="checkbox" name="is_show_story" id="is_show_story" value="1" <?php echo (($shop['is_show_story'] ?? 1) == 1) ? 'checked' : ''; ?>><label class="form-check-label small fw-bold text-primary" for="is_show_story">홈페이지 노출</label></div>
                <p <?php echo UI_INFO_SM_LABEL;?>> 상점 배경 이미지들 중, 가장 첫번째 자리에 있는 사진과 함께 노출 됩니다.</p>

                <?php if ($is_multi && ($lang1 !== 'none' || $lang2 !== 'none')): ?>
                    <ul class="nav nav-tabs mb-3 mt-3" id="story-tab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active fw-bold px-3 py-2" id="story-ko-tab" data-bs-toggle="tab" data-bs-target="#story-ko-pane" type="button" role="tab">한국어 (기본)</button></li>
                        <?php if ($lang1 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="story-lang1-tab" data-bs-toggle="tab" data-bs-target="#story-lang1-pane" type="button" role="tab"><?php echo htmlspecialchars($lang1_display); ?></button></li><?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="story-lang2-tab" data-bs-toggle="tab" data-bs-target="#story-lang2-pane" type="button" role="tab"><?php echo htmlspecialchars($lang2_display); ?></button></li><?php endif; ?>
                    </ul>
                <?php endif; ?>

                <div class="tab-content flex-grow-1 mt-3" id="story-tabContent">
                    <div class="tab-pane fade show active h-100" id="story-ko-pane" role="tabpanel">
                        <div class="d-flex flex-column h-100">
                            <div class="mb-3"><label class="form-label small fw-bold text-muted">1. 섹션 제목 (레이블)</label><input type="text" name="ui[label_story]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_story'] ?? SHOP_DEFAULT_LABEL_STORY); ?>"></div>
                            <div class="mb-4"><label class="form-label small fw-bold text-muted">2. 스토리 제목</label><input type="text" name="shop_intro" class="form-control form-control-lg" value="<?php echo htmlspecialchars($shop['shop_intro'] ?? ''); ?>"></div>
                            <div class="mb-0 flex-grow-1 d-flex flex-column">
                                <label class="form-label small fw-bold text-muted">3. 상세 본문 내용</label>
                                <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
                                <div id="quill-editor-ko" class="quill-editor-instance bg-white flex-grow-1" data-target="hidden_shop_description_ko" style="min-height: 200px;"><?php echo $shop['shop_description'] ?? ''; ?></div>
                                <textarea name="shop_description" id="hidden_shop_description_ko" class="d-none"><?php echo htmlspecialchars($shop['shop_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php if ($is_multi && $lang1 !== 'none'): ?>
                        <div class="tab-pane fade h-100" id="story-lang1-pane" role="tabpanel">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-3"><label class="form-label small fw-bold text-muted">1. 섹션 제목 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[label_story_<?php echo $lang1_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_story_' . $lang1_code] ?? ''); ?>"></div>
                                <div class="mb-4"><label class="form-label small fw-bold text-muted">2. 스토리 제목 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[shop_intro_<?php echo $lang1_code; ?>]" class="form-control form-control-lg" value="<?php echo htmlspecialchars($ui['shop_intro_' . $lang1_code] ?? ''); ?>"></div>
                                <div class="mb-0 flex-grow-1 d-flex flex-column"><label class="form-label small fw-bold text-muted">3. 상세 본문 내용</label>
                                    <div id="quill-editor-lang1" class="quill-editor-instance bg-white flex-grow-1" data-target="hidden_shop_description_lang1" style="min-height: 200px;"><?php echo $ui['shop_description_' . $lang1_code] ?? ''; ?></div><textarea name="ui[shop_description_<?php echo $lang1_code; ?>]" id="hidden_shop_description_lang1" class="d-none"><?php echo htmlspecialchars($ui['shop_description_' . $lang1_code] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($is_multi && $lang2 !== 'none'): ?>
                        <div class="tab-pane fade h-100" id="story-lang2-pane" role="tabpanel">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-3"><label class="form-label small fw-bold text-muted">1. 섹션 제목 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[label_story_<?php echo $lang2_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_story_' . $lang2_code] ?? ''); ?>"></div>
                                <div class="mb-4"><label class="form-label small fw-bold text-muted">2. 스토리 제목 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[shop_intro_<?php echo $lang2_code; ?>]" class="form-control form-control-lg" value="<?php echo htmlspecialchars($ui['shop_intro_' . $lang2_code] ?? ''); ?>"></div>
                                <div class="mb-0 flex-grow-1 d-flex flex-column"><label class="form-label small fw-bold text-muted">3. 상세 본문 내용</label>
                                    <div id="quill-editor-lang2" class="quill-editor-instance bg-white flex-grow-1" data-target="hidden_shop_description_lang2" style="min-height: 200px;"><?php echo $ui['shop_description_' . $lang2_code] ?? ''; ?></div><textarea name="ui[shop_description_<?php echo $lang2_code; ?>]" id="hidden_shop_description_lang2" class="d-none"><?php echo htmlspecialchars($ui['shop_description_' . $lang2_code] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-auto text-end pt-3 border-top">
                    <button type="submit" name="update_shop" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 소개 정보 저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 06. 매장 사진 관리 (갤러리) -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-info" id="section-gallery">
            <form id="gallery-form" method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="saveImageBatch(event, 'gallery')">
                <?php echo renderSectionHeader('매장 사진 관리 (갤러리)', 'bi-images'); ?>
                <div class="form-check form-switch mb-4"><input type="hidden" name="is_show_gallery" value="0"><input class="form-check-input" type="checkbox" name="is_show_gallery" id="is_show_gallery" value="1" <?php echo (($shop['is_show_gallery'] ?? 1) == 1) ? 'checked' : ''; ?>><label class="form-check-label small fw-bold text-primary" for="is_show_gallery">홈페이지 노출</label></div>
                <input type="hidden" name="gallery_order" id="gallery_order_input" value="[]">

                <?php if ($is_multi && ($lang1 !== 'none' || $lang2 !== 'none')): ?>
                    <ul class="nav nav-tabs mb-3" id="gallery-tab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active fw-bold px-3 py-2" id="gallery-ko-tab" data-bs-toggle="tab" data-bs-target="#gallery-ko-pane" type="button" role="tab">한국어</button></li>
                        <?php if ($lang1 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="gallery-lang1-tab" data-bs-toggle="tab" data-bs-target="#gallery-lang1-pane" type="button" role="tab"><?php echo htmlspecialchars($lang1_display); ?></button></li><?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="gallery-lang2-tab" data-bs-toggle="tab" data-bs-target="#gallery-lang2-pane" type="button" role="tab"><?php echo htmlspecialchars($lang2_display); ?></button></li><?php endif; ?>
                    </ul>
                <?php endif; ?>

                <div class="tab-content mb-3" id="gallery-tabContent">
                    <div class="tab-pane fade show active" id="gallery-ko-pane" role="tabpanel">
                        <label class="form-label small fw-bold text-muted">섹션 제목 (레이블)</label><input type="text" name="ui[label_gallery]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_gallery'] ?? SHOP_DEFAULT_LABEL_GALLERY); ?>">
                    </div>
                    <?php if ($is_multi && $lang1 !== 'none'): ?>
                        <div class="tab-pane fade" id="gallery-lang1-pane" role="tabpanel">
                            <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[label_gallery_<?php echo $lang1_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_gallery_' . $lang1_code] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                    <?php if ($is_multi && $lang2 !== 'none'): ?>
                        <div class="tab-pane fade" id="gallery-lang2-pane" role="tabpanel">
                            <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[label_gallery_<?php echo $lang2_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_gallery_' . $lang2_code] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-9 flex-grow-1 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0 small fw-bold text-muted">갤러리 사진 관리</label>
                        <button type="button" onclick="document.getElementById('gallery-file').click()" class="btn btn-outline-primary btn-sm rounded-pill py-1 px-3 fw-bold shadow-sm" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i>사진 추가</button>
                    </div>
                    <p <?php echo UI_INFO_SM_LABEL;?>> 이미지를 드래그하여 순서를 바꿀 수 있습니다. 수정 후에는 꼭 "사진 설정 저장" 버튼을 눌러주세요.</p>
                    <div class="row g-2 p-3 border rounded shadow-inner row-cols-2 row-cols-md-3 mt-2" id="shop-gallery-container" style="min-height: 90px; background: #f8f9fa;">
                        <?php if (!empty($shop_imgs)): foreach ($shop_imgs as $img): ?>
                                <div class="col gallery-item" id="gallery-item-<?php echo $img['id']; ?>" data-path="<?php echo htmlspecialchars($img['img_path'], ENT_QUOTES); ?>" style="cursor: grab;">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($img['img_path']); ?>" class="w-100 rounded border shadow-sm" style="aspect-ratio: 1/1; object-fit: cover;">
                                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle" style="width:20px; height:20px; margin: 4px;" onclick="event.stopPropagation(); deleteBatchImage('gallery', <?php echo $img['id']; ?>)">
                                            <i class="bi bi-x" style="font-size: 0.8rem; vertical-align: top;"></i>
                                        </button>
                                    </div>
                                </div>
                        <?php endforeach; endif; ?>
                        <div class="empty-msg text-muted small w-100 text-center my-auto <?php echo empty($shop_imgs) ? '' : 'd-none'; ?>">등록된 사진이 없습니다.</div>
                        <button type="button" class="btn-add-img d-none"></button>
                    </div>
                    <input type="file" id="gallery-file" class="d-none" accept="image/*" multiple onchange="addBatchImage('gallery', this)">
                    <div id="gallery-upload-spinner" class="d-none small text-primary mt-2 text-center"><span class="spinner-border spinner-border-sm me-1"></span>업로드 중...</div>
                </div>
                <div class="mt-3 mb-0">
                    <label class="form-label small fw-bold text-muted mb-3"><i class="bi bi-youtube text-danger me-1"></i> 매장 홍보 유튜브 동영상 링크</label>
                    <p <?php echo UI_INFO_SM_LABEL;?>> 매장을 홍보할 수 있는 유튜브 영상 링크를 여러 개 추가할 수 있습니다.</p>
                    <div id="youtube-links-container" class="mt-1 mb-0">
                        <?php
                        $yt_urls = [];
                        if (!empty($shop['shop_youtube_url'])) {
                            $decoded = json_decode($shop['shop_youtube_url'], true);
                            $yt_urls = is_array($decoded) ? $decoded : [$shop['shop_youtube_url']];
                        }
                        if (empty($yt_urls)) $yt_urls = [''];
                        foreach ($yt_urls as $url):
                        ?>
                            <div class="input-group input-group-sm mb-2 yt-input-group">
                                <span class="input-group-text"><i class="bi bi-youtube text-danger"></i></span>
                                <input type="url" name="shop_youtube_url[]" class="form-control form-control-sm" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars($url); ?>">
                                <button class="btn btn-outline-danger fw-bold" type="button" onclick="removeYoutubeInput(this)"><i class="bi bi-trash3"></i> 삭제</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary mt-1 fw-bold rounded-pill px-3 mb-3" onclick="addYoutubeInput()" style="font-size: 0.75rem;"><i class="bi bi-plus-lg me-1"></i> 동영상 링크 추가</button>
                    </div>
                </div>
                <div class="mt-auto text-end pt-3 border-top">
                    <button type="submit" name="update_shop" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 사진 설정 저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 07. 위치 정보 (구글 지도) -->
    <div class="col-lg-6">
        <div class="<?php echo UI_SECTION_CARD; ?> border-start border-4 border-secondary">
            <form method="POST" class="p-3 p-md-4 d-flex flex-column h-100" onsubmit="handleAjaxFormSubmit(event)">
                <input type="hidden" name="update_shop" value="1">
                <?php echo renderSectionHeader('위치 정보 (구글 지도)', 'bi-geo-alt text-muted', [], '<i class="bi bi-question-circle text-muted fs-5" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#googleMapHelpModal"></i>'); ?>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_show_map" value="0">
                    <input class="form-check-input" type="checkbox" name="is_show_map" id="is_show_map" value="1" <?php echo (($shop['is_show_map'] ?? 1) == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label small fw-bold text-primary" for="is_show_map">홈페이지에 지도 노출</label>
                </div>

                <?php if ($is_multi && ($lang1 !== 'none' || $lang2 !== 'none')): ?>
                    <ul class="nav nav-tabs mb-3" id="location-tab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active fw-bold px-3 py-2" id="location-ko-tab" data-bs-toggle="tab" data-bs-target="#location-ko-pane" type="button" role="tab">한국어</button></li>
                        <?php if ($lang1 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="location-lang1-tab" data-bs-toggle="tab" data-bs-target="#location-lang1-pane" type="button" role="tab"><?php echo htmlspecialchars($lang1_display); ?></button></li><?php endif; ?>
                        <?php if ($lang2 !== 'none'): ?><li class="nav-item" role="presentation"><button class="nav-link fw-bold px-3 py-2" id="location-lang2-tab" data-bs-toggle="tab" data-bs-target="#location-lang2-pane" type="button" role="tab"><?php echo htmlspecialchars($lang2_display); ?></button></li><?php endif; ?>
                    </ul>
                <?php endif; ?>

                <div class="tab-content mb-3" id="location-tabContent">
                    <div class="tab-pane fade show active" id="location-ko-pane" role="tabpanel">
                        <label class="form-label small fw-bold text-muted">섹션 제목 (레이블)</label><input type="text" name="ui[label_location]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_location'] ?? SHOP_DEFAULT_LABEL_LOCATION); ?>">
                    </div>
                    <?php if ($is_multi && $lang1 !== 'none'): ?>
                        <div class="tab-pane fade" id="location-lang1-pane" role="tabpanel">
                            <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang1_display); ?>)</label><input type="text" name="ui[label_location_<?php echo $lang1_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_location_' . $lang1_code] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                    <?php if ($is_multi && $lang2 !== 'none'): ?>
                        <div class="tab-pane fade" id="location-lang2-pane" role="tabpanel">
                            <label class="form-label small fw-bold text-muted">섹션 제목 (<?php echo htmlspecialchars($lang2_display); ?>)</label><input type="text" name="ui[label_location_<?php echo $lang2_code; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ui['label_location_' . $lang2_code] ?? ''); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <p <?php echo UI_INFO_SM_LABEL;?>> 
                    <i class="bi bi-info-circle me-1"><strong></i> 주소 넣는 방법 : PC</strong>의 구글 지도에서 상점 위치를 클릭한 후에 <strong>"공유"-"지도 퍼가기"</strong>를 순서대로 클릭하면,
                    <code>&lt;iframe&gt;&lt;/iframe&gt;</code> 코드를 볼 수 있고, <strong>"HTML 복사"</strong>를 클릭한 후, 아래의 빈칸에 코드를 복사하면 됩니다.
                </p>
                <div class="mb-3 mt-1">
                    <textarea name="shop_map_html" id="map-input" class="form-control bg-light" rows="3" placeholder='<iframe src="..." ...></iframe> 코드를 붙여넣으세요.'><?php echo $shop['shop_map_html'] ?? ''; ?></textarea>
                    <div class="map-preview-container border rounded overflow-hidden mt-2" id="map-preview" style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                        <?php
                        if (!empty($shop['shop_map_html'])) {
                            $preview_html = preg_replace('/width="\d+"/', 'width="100%"', $shop['shop_map_html']);
                            $preview_html = preg_replace('/height="\d+"/', 'height="100%"', $preview_html);
                            echo $preview_html;
                        } else {
                            echo '<span class="text-muted small text-center">코드를 입력하면 여기에<br>지도가 표시됩니다.</span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="mt-auto text-end pt-3 border-top">
                    <button type="submit" name="update_shop" class="btn btn-dark btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-check2-circle me-1"></i> 위치 정보 저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 모달 및 스크립트 모음 -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">이미지 편집</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <img id="image-to-crop" src="" style="max-width: 100%;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="executeCrop()">자르기 및 저장</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="googleMapHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-dots-fill me-2"></i>구글 지도 코드 가져오는 방법</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex mb-3">
                    <span class="badge bg-success rounded-circle me-3" style="width:24px; height:24px;">1</span>
                    <p class="small mb-0"><a href="https://www.google.com/maps" target="_blank" class="fw-bold text-primary">구글 지도</a>에서 매장명을 검색합니다.</p>
                </div>
                <div class="d-flex mb-3">
                    <span class="badge bg-success rounded-circle me-3" style="width:24px; height:24px;">2</span>
                    <p class="small mb-0">왼쪽 메뉴의 <strong>[공유]</strong> 버튼을 누릅니다.</p>
                </div>
                <div class="d-flex mb-3">
                    <span class="badge bg-success rounded-circle me-3" style="width:24px; height:24px;">3</span>
                    <p class="small mb-0"><strong>[지도 퍼가기]</strong> 탭을 클릭합니다.</p>
                </div>
                <div class="d-flex mb-4">
                    <span class="badge bg-success rounded-circle me-3" style="width:24px; height:24px;">4</span>
                    <p class="small mb-0"><strong>[HTML 복사]</strong>를 눌러 코드를 가져와 붙여넣으세요.</p>
                </div>
                <div class="alert alert-warning border-0 small mb-0">
                    <i class="bi bi-lightbulb-fill me-1"></i> 복사한 코드를 그대로 입력창에 넣으시면 됩니다.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    var cropper;
    var currentType;
    var currentTargetId = "<?php echo $shop_id; ?>";
    var cropModalObj;
    var imageToCrop = document.getElementById('image-to-crop');

    function initCrop(input, type, targetId = null) {
        if (!input.files || !input.files[0]) return;
        currentType = type;
        currentTargetId = targetId ? targetId : "<?php echo $shop_id; ?>";
        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCrop.src = e.target.result;
            if (!cropModalObj) cropModalObj = bootstrap.Modal.getOrCreateInstance(document.getElementById('cropModal'));
            cropModalObj.show();
        };
        reader.readAsDataURL(input.files[0]);
    }

    document.getElementById('cropModal').addEventListener('shown.bs.modal', function() {
        if (cropper) cropper.destroy();
        let aspectRatio = (currentType === 'logo') ? 8 / 2 : ((currentType === 'bg') ? 16 / 9 : 1 / 1);
        let title = (currentType === 'logo') ? '로고 편집 (8:2 비율)' : ((currentType === 'bg') ? '배경 이미지 편집 (16:9 비율)' : '이미지 편집');
        document.querySelector('#cropModal .modal-title').innerText = title;
        cropper = new Cropper(imageToCrop, {
            aspectRatio: aspectRatio,
            viewMode: 1
        });
    });

    document.getElementById('cropModal').addEventListener('hidden.bs.modal', function() {
        if (cropper) { cropper.destroy(); cropper = null; }
        if (document.getElementById('logo-file')) document.getElementById('logo-file').value = '';
    });

    function executeCrop() {
        if (!cropper) return;
        const btn = document.querySelector('#cropModal .btn-primary');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>처리 중...';

        let cropOptions = (currentType === 'logo') ? { width: 400, height: 100 } : ((currentType === 'bg') ? { width: 1920, height: 1080 } : { width: 900, height: 900 });
        const canvas = cropper.getCroppedCanvas(cropOptions);

        if (!canvas) {
            alert("이미지를 처리할 수 없습니다.");
            btn.disabled = false; btn.innerHTML = originalText; return;
        }

        canvas.toBlob((blob) => {
            if (!blob) { alert("이미지 변환 오류"); btn.disabled = false; btn.innerHTML = originalText; return; }
            const formData = new FormData();
            formData.append('image', blob, 'cropped.jpg');
            formData.append('folder', currentType);
            formData.append('target_id', currentTargetId);
            formData.append('table', 'shops');
            formData.append('column', currentType + '_path');
            formData.append('mode', 'update');

            fetch('../common/upload_image.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.hash = 'section-resource';
                        location.reload();
                    } else {
                        alert('업로드 실패: ' + data.message);
                        btn.disabled = false; btn.innerHTML = originalText;
                    }
                })
                .catch(err => { alert('네트워크 오류'); btn.disabled = false; btn.innerHTML = originalText; });
        }, 'image/jpeg', 0.85);
    }

    const mapInput = document.getElementById('map-input');
    const mapPreview = document.getElementById('map-preview');
    if (mapInput) {
        mapInput.addEventListener('input', function() {
            let val = this.value.trim();
            if (val.includes('<iframe')) {
                val = val.replace(/width="\d+"/g, 'width="100%"').replace(/height="\d+"/g, 'height="100%"');
                mapPreview.innerHTML = val;
            } else if (val === '') {
                mapPreview.innerHTML = '<span class="text-muted small text-center">코드를 입력하면 여기에<br>지도가 표시됩니다.</span>';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initImageBatchManager('gallery', {
            containerId: 'shop-gallery-container',
            itemClass: 'col gallery-item',
            aspectRatio: '1/1',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: { target_id: <?php echo $shop_id; ?>, table: 'shop_images', column: 'img_path', folder: 'gallery' },
            deleteUrl: 'manage_shop.php?pg=manage_shop_homepage',
            deleteActionName: 'delete_gallery_img',
            deleteIdParam: 'img_id',
            sortable: true,
            hiddenOrderInputId: 'gallery_order_input'
        });

        initImageBatchManager('background', {
            containerId: 'bg-list-container',
            itemClass: 'col gallery-item',
            aspectRatio: '16/9',
            addBtnSelector: '.btn-add-img',
            emptyMsgSelector: '.empty-msg',
            uploadParams: { target_id: <?php echo $shop_id; ?>, folder: 'bg', table: 'shops', column: 'bg_path' },
            sortable: true,
            hiddenOrderInputId: 'bg_path_input'
        });
    });

    function addYoutubeInput() {
        const container = document.getElementById('youtube-links-container');
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-2 yt-input-group';
        div.innerHTML = `
            <span class="input-group-text"><i class="bi bi-youtube text-danger"></i></span>
            <input type="url" name="shop_youtube_url[]" class="form-control form-control-sm" placeholder="https://www.youtube.com/watch?v=...">
            <button class="btn btn-outline-danger fw-bold" type="button" onclick="removeYoutubeInput(this)"><i class="bi bi-trash3"></i> 삭제</button>
        `;
        container.appendChild(div);
    }

    function removeYoutubeInput(button) {
        const group = button.closest('.yt-input-group');
        const container = document.getElementById('youtube-links-container');
        if (container.querySelectorAll('.yt-input-group').length > 1) group.remove();
        else group.querySelector('input').value = '';
    }

    function syncQuillEditor() {
        document.querySelectorAll('.quill-editor-instance').forEach(function(editorDiv) {
            var editor = editorDiv.querySelector('.ql-editor');
            var targetId = editorDiv.getAttribute('data-target');
            var hiddenInput = document.getElementById(targetId);
            if (editor && hiddenInput) {
                var htmlContent = editor.innerHTML;
                if (htmlContent === '<p><br></p>') htmlContent = '';
                hiddenInput.value = htmlContent;
            }
        });
    }

    function initQuill() {
        document.querySelectorAll('.quill-editor-instance').forEach(function(editorDiv) {
            if (editorDiv.querySelector('.ql-toolbar')) return;
            var quill = new Quill(editorDiv, {
                theme: 'snow',
                placeholder: '상점 소개 내용을 자유롭게 작성해 주세요.',
                modules: { toolbar: [ ['bold', 'italic', 'underline', 'strike'], [{'color': []}, {'background': []}], [{'list': 'ordered'}, {'list': 'bullet'}], ['clean'] ] }
            });
            quill.on('text-change', syncQuillEditor);
        });
    }

    if (typeof Quill === 'undefined') {
        let script = document.createElement('script');
        script.src = "https://cdn.quilljs.com/1.3.6/quill.min.js";
        script.onload = initQuill;
        document.head.appendChild(script);
    } else {
        initQuill();
    }
</script>