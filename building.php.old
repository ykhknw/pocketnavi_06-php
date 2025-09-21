<?php
// 建築物詳細ページ
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/functions_new.php';

// 言語設定
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['ja', 'en']) ? $_GET['lang'] : 'ja';

// 建築物の取得または建築家の建築物リスト表示
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$architectSlug = isset($_GET['architects']) ? trim($_GET['architects']) : '';
$building = null;
$architectBuildings = [];

if ($architectSlug) {
    // デバッグ情報を追加
    error_log("building.php: Received architect slug: " . $architectSlug);
    
    // データベースで直接確認
    $db = getDB();
    
    // 1. 特定のslugで検索
    $debugStmt = $db->prepare("SELECT individual_architect_id, name_ja, name_en, slug FROM individual_architects_3 WHERE slug = ? LIMIT 5");
    $debugStmt->execute([$architectSlug]);
    $debugResults = $debugStmt->fetchAll();
    error_log("building.php: Direct DB query for architect slug: " . print_r($debugResults, true));
    
    // 2. 類似するslugを検索
    $debugStmt2 = $db->prepare("SELECT individual_architect_id, name_ja, name_en, slug FROM individual_architects_3 WHERE slug LIKE ? OR name_ja LIKE ? LIMIT 10");
    $debugStmt2->execute(['%' . $architectSlug . '%', '%伊藤%']);
    $debugResults2 = $debugStmt2->fetchAll();
    error_log("building.php: Similar slugs: " . print_r($debugResults2, true));
    
    // 3. 全slugのサンプルを取得
    $debugStmt3 = $db->prepare("SELECT individual_architect_id, name_ja, name_en, slug FROM individual_architects_3 WHERE slug IS NOT NULL AND slug != '' LIMIT 10");
    $debugStmt3->execute();
    $debugResults3 = $debugStmt3->fetchAll();
    error_log("building.php: Sample slugs: " . print_r($debugResults3, true));
    
    // デバッグ情報をブラウザに表示
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<!-- Debug: Architect slug: " . htmlspecialchars($architectSlug) . " -->";
        echo "<!-- Debug: Exact match: " . print_r($debugResults, true) . " -->";
        echo "<!-- Debug: Similar slugs: " . print_r($debugResults2, true) . " -->";
        echo "<!-- Debug: Sample slugs: " . print_r($debugResults3, true) . " -->";
        
        // 建築家の建築物の関連テーブルを直接確認
        $debugStmt4 = $db->prepare("
            SELECT 
                ia.individual_architect_id,
                ia.name_ja,
                ia.slug,
                ac.architect_id,
                ba.building_id,
                b.title
            FROM individual_architects_3 ia
            LEFT JOIN architect_compositions_2 ac ON ia.individual_architect_id = ac.individual_architect_id
            LEFT JOIN building_architects ba ON ac.architect_id = ba.architect_id
            LEFT JOIN buildings_table_2 b ON ba.building_id = b.building_id
            WHERE ia.slug = ?
            LIMIT 10
        ");
        $debugStmt4->execute([$architectSlug]);
        $debugResults4 = $debugStmt4->fetchAll();
        echo "<!-- Debug: Architect-Building relations: " . print_r($debugResults4, true) . " -->";
    }
    
    // 建築家の建築物リストを表示
    $architectBuildings = getBuildingsByArchitectSlug($architectSlug, $lang);
    
    error_log("building.php: Found " . count($architectBuildings) . " buildings for architect slug: " . $architectSlug);
    
    // デバッグ情報をブラウザに表示
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<!-- Debug: Architect buildings count: " . count($architectBuildings) . " -->";
        if (!empty($architectBuildings)) {
            echo "<!-- Debug: First building: " . print_r($architectBuildings[0], true) . " -->";
        }
    }
    
    if (empty($architectBuildings)) {
        // デバッグ情報を追加
        error_log("building.php: No buildings found for architect slug: " . $architectSlug . ", redirecting to index.php");
        
        // デバッグ情報をブラウザに表示
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<!-- Debug: No buildings found, redirecting to index.php -->";
        }
        
        // 建築家が見つからない場合はトップページにリダイレクト
        header('Location: index.php?lang=' . $lang);
        exit;
    }
} elseif ($slug) {
    // 個別建築物の詳細を表示
    $building = getBuildingBySlugNew($slug, $lang);
    if (!$building) {
        // 建築物が見つからない場合はトップページにリダイレクト
        header('Location: index.php?lang=' . $lang);
        exit;
    }
} else {
    // パラメータが指定されていない場合はトップページにリダイレクト
    header('Location: index.php?lang=' . $lang);
    exit;
}

// デバッグ情報
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!-- Debug: Looking for slug: " . htmlspecialchars($slug) . " -->";
    echo "<!-- Debug: Building found: " . ($building ? 'Yes' : 'No') . " -->";
    
    // データベースで直接確認
    $db = getDB();
    $testStmt = $db->prepare("SELECT building_id, slug, title FROM buildings_table_2 WHERE slug = ? LIMIT 5");
    $testStmt->execute([$slug]);
    $testResults = $testStmt->fetchAll();
    echo "<!-- Debug: Direct DB query results: " . print_r($testResults, true) . " -->";
    
    if ($building) {
        echo "<!-- Debug: Building data: " . print_r($building, true) . " -->";
    }
}

// 建築家の建築物リスト表示の場合は$buildingのチェックをスキップ
if (!$architectSlug && !$building) {
    // デバッグモードの場合はエラーメッセージを表示
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<h1>建築物が見つかりませんでした</h1>";
        echo "<p>スラッグ: " . htmlspecialchars($slug) . "</p>";
        echo "<p><a href='index.php?lang=" . $lang . "'>検索ページに戻る</a></p>";
        exit;
    }
    header('Location: index.php?lang=' . $lang);
    exit;
}

// 写真の取得（実際のアプリでは別途実装）
$photos = [];

// 人気の検索データを取得
$popularSearches = getPopularSearches($lang);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
    if ($architectSlug) {
        echo $lang === 'ja' ? '建築家の作品' : 'Architect\'s Works';
    } else {
        echo htmlspecialchars($lang === 'ja' ? $building['title'] : $building['titleEn']);
    }
    ?> - PocketNavi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="assets/images/landmark.svg" type="image/svg+xml">
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="mb-4">
                    <!-- Back Button -->
                    <a href="javascript:history.back()" class="btn btn-outline-secondary mb-3">
                        <i class="fas fa-arrow-left me-2"></i>
                        <?php echo t('backToList', $lang); ?>
                    </a>
                    
                    <?php if ($architectSlug): ?>
                        <!-- 建築家の建築物リスト -->
                        <h2 class="h2">
                            <i class="fas fa-user me-2"></i>
                            <?php 
                            // 建築家の名前を取得
                            $architectName = '';
                            if (!empty($architectBuildings) && !empty($architectBuildings[0]['architects'])) {
                                $architectName = $lang === 'ja' ? 
                                    $architectBuildings[0]['architects'][0]['architectJa'] : 
                                    $architectBuildings[0]['architects'][0]['architectEn'];
                            }
                            echo $lang === 'ja' ? '建築家の作品: ' . htmlspecialchars($architectName) : 'Architect\'s Works: ' . htmlspecialchars($architectName);
                            ?>
                        </h2>
                    <?php else: ?>
                        <h1 class="h2"><?php echo htmlspecialchars($lang === 'ja' ? $building['title'] : $building['titleEn']); ?></h1>
                    <?php endif; ?>
                </div>
                
                <?php if ($architectSlug): ?>
                    <!-- 建築家の建築物リスト -->
                    <?php if (empty($architectBuildings)): ?>
                        <div class="alert alert-info text-center">
                            <?php echo $lang === 'ja' ? '建築物が見つかりませんでした。' : 'No buildings found.'; ?>
                        </div>
                    <?php else: ?>
                        <!-- 建築物カード一覧 -->
                        <div id="building-cards">
                            <?php foreach ($architectBuildings as $index => $building): ?>
                                <div class="mb-3">
                                    <?php include 'includes/building_card.php'; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                
                <!-- Building Details -->
                <div class="row">
                    <!-- Main Image -->
                    <div class="col-md-6 mb-4">
                        <?php if (!empty($building['thumbnailUrl'])): ?>
                            <img src="<?php echo htmlspecialchars($building['thumbnailUrl']); ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?php echo htmlspecialchars($building['title']); ?>">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                 style="height: 300px;">
                                <img src="assets/images/landmark.svg" 
                                     alt="PocketNavi" 
                                     style="width: 120px; height: 120px; opacity: 0.3;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Details -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <!-- Architect -->
                                <?php if (!empty($building['architects'])): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-1">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo t('architect', $lang); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($building['architects'] as $architect): ?>
                                                <a href="building.php?architects=<?php echo urlencode($architect['slug']); ?>&lang=<?php echo $lang; ?>" 
                                                   class="architect-badge text-decoration-none">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($lang === 'ja' ? $architect['architectJa'] : $architect['architectEn']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Location -->
                                <?php 
                                $location = $lang === 'ja' ? $building['location'] : $building['locationEn'];
                                
                                // デバッグ情報（開発時のみ）
                                if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                                    echo "<!-- Debug Location: lang=" . $lang . ", location=" . htmlspecialchars($building['location']) . ", locationEn=" . htmlspecialchars($building['locationEn']) . " -->";
                                }
                                
                                if ($location): 
                                ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-1">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo t('location', $lang); ?>
                                        </h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($location); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Prefecture -->
                                <?php if ($building['prefectures']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-1">
                                            <i class="fas fa-map me-1"></i>
                                            <?php echo t('prefecture', $lang); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="prefecture-badge">
                                                <i class="fas fa-map me-1"></i>
                                                <?php echo htmlspecialchars($lang === 'ja' ? $building['prefectures'] : $building['prefecturesEn']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Building Types -->
                                <?php 
                                $buildingTypes = $lang === 'ja' ? $building['buildingTypes'] : $building['buildingTypesEn'];
                                if (!empty($buildingTypes)): 
                                ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-1">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo t('buildingTypes', $lang); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($buildingTypes as $type): ?>
                                                <span class="building-type-badge">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo htmlspecialchars($type); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Completion Year -->
                                <?php if ($building['completionYears']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-1">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo t('completionYear', $lang); ?>
                                        </h6>
                                        <p class="mb-0"><?php echo $building['completionYears']; ?>年</p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Photos/Videos -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">
                                        <i class="fas fa-images me-1"></i>
                                        <?php echo t('photos', $lang); ?> / <?php echo t('videos', $lang); ?>
                                    </h6>
                                    <div class="d-flex gap-2">
                                        <?php if (!empty($building['thumbnailUrl'])): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-image me-1"></i>
                                                <?php echo t('photos', $lang); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($building['youtubeUrl']): ?>
                                            <span class="badge bg-danger">
                                                <i class="fab fa-youtube me-1"></i>
                                                <?php echo t('videos', $lang); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($building['thumbnailUrl']) && !$building['youtubeUrl']): ?>
                                            <span class="text-muted"><?php echo $lang === 'ja' ? 'なし' : 'None'; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- YouTube Video -->
                                <?php if ($building['youtubeUrl']): ?>
                                    <div class="mt-3">
                                        <h6 class="text-muted mb-2"><?php echo t('videos', $lang); ?></h6>
                                        <div class="ratio ratio-16x9">
                                            <?php
                                            // YouTube URLを埋め込み用URLに変換
                                            $youtubeUrl = $building['youtubeUrl'];
                                            $embedUrl = '';
                                            
                                            // 既に埋め込み用URLの場合
                                            if (strpos($youtubeUrl, 'embed/') !== false) {
                                                $embedUrl = $youtubeUrl;
                                            }
                                            // YouTube Shorts URLの場合
                                            elseif (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $youtubeUrl, $matches)) {
                                                $videoId = $matches[1];
                                                $embedUrl = 'https://www.youtube.com/embed/' . $videoId;
                                            }
                                            // 通常のYouTube URLの場合
                                            elseif (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $youtubeUrl, $matches)) {
                                                $videoId = $matches[1];
                                                $embedUrl = 'https://www.youtube.com/embed/' . $videoId;
                                            }
                                            // その他の場合は元のURLをそのまま使用
                                            else {
                                                $embedUrl = $youtubeUrl;
                                            }
                                            ?>
                                            <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" 
                                                    title="<?php echo htmlspecialchars($building['title']); ?>" 
                                                    allowfullscreen
                                                    frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                                        </div>
                                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 20px;">
                    <?php if ($architectSlug): ?>
                        <!-- Search Form (建築家の建築物リスト表示の場合) -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" action="index.php" class="mb-3">
                                    <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                                    
                                    <div class="input-group mb-3">
                                        <input type="text" 
                                               class="form-control" 
                                               name="q" 
                                               placeholder="<?php echo t('searchPlaceholder', $lang); ?>"
                                               value="">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mb-3">
                                        <button type="button" 
                                                class="btn btn-outline-info" 
                                                id="getLocationBtn"
                                                onclick="getCurrentLocation()">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo t('currentLocation', $lang); ?>
                                        </button>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-secondary" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#advancedSearch" 
                                                aria-expanded="false">
                                            <i class="fas fa-chevron-down me-1"></i>
                                            <?php echo t('detailedSearch', $lang); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="collapse" id="advancedSearch">
                                        <div class="card card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="photos" 
                                                       value="1" 
                                                       id="photosFilter">
                                                <label class="form-check-label" for="photosFilter">
                                                    <?php echo t('withPhotos', $lang); ?>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="videos" 
                                                       value="1" 
                                                       id="videosFilter">
                                                <label class="form-check-label" for="videosFilter">
                                                    <?php echo t('withVideos', $lang); ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Map -->
                    <div class="card mb-4">
                        <div class="card-body p-0">
                            <div id="map" style="height: 400px; width: 100%;"></div>
                        </div>
                    </div>
                    
                    <!-- Popular Searches -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-fire me-2"></i>
                                <?php echo t('popularSearches', $lang); ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($popularSearches)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($popularSearches as $search): ?>
                                        <a href="?q=<?php echo urlencode($search['query']); ?>&lang=<?php echo $lang; ?>" 
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($search['query']); ?></span>
                                            <span class="badge bg-primary rounded-pill"><?php echo $search['count']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    <?php echo $lang === 'ja' ? '人気の検索がありません。' : 'No popular searches available.'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // 地図の初期化
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($architectSlug): ?>
                // 建築家の建築物リスト表示の場合
                const buildings = <?php echo json_encode($architectBuildings); ?>;
                if (buildings.length > 0) {
                    // 最初の建築物の座標を中心に設定
                    const center = [buildings[0].lat, buildings[0].lng];
                    initMap(center, buildings);
                } else {
                    // デフォルトの座標（東京）
                    initMap([35.6762, 139.6503], []);
                }
            <?php else: ?>
                // 個別建築物表示の場合
                const building = <?php echo json_encode($building); ?>;
                initMap([building.lat, building.lng], [building]);
            <?php endif; ?>
        });
        
        // 現在地取得機能
        function getCurrentLocation() {
            const btn = document.getElementById('getLocationBtn');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>取得中...';
            btn.disabled = true;

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        const currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('lat', lat);
                        currentUrl.searchParams.set('lng', lng);
                        currentUrl.searchParams.set('radius', '5'); // Default 5km
                        currentUrl.searchParams.delete('q'); // Clear keyword search
                        window.location.href = currentUrl.toString();
                    },
                    function(error) {
                        alert('位置情報の取得に失敗しました: ' + error.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                );
            } else {
                alert('このブラウザは位置情報をサポートしていません。');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>

