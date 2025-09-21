<?php
// PocketNavi PHP版 - メインページ
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/functions_new.php';

// 言語設定（URLクエリパラメータから取得）
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['ja', 'en']) ? $_GET['lang'] : 'ja';

// 検索パラメータの取得
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$hasPhotos = isset($_GET['photos']) ? (bool)$_GET['photos'] : false;
$hasVideos = isset($_GET['videos']) ? (bool)$_GET['videos'] : false;
$userLat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$userLng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radiusKm = isset($_GET['radius']) ? max(1, intval($_GET['radius'])) : 5;

// 検索結果の取得
$buildings = [];
$totalBuildings = 0;
$totalPages = 0;
$currentPage = $page;
$limit = 10;

if ($userLat !== null && $userLng !== null) {
    // 現在地検索
    $searchResult = searchBuildingsByLocation($userLat, $userLng, $radiusKm, $page, $hasPhotos, $hasVideos, $lang, $limit);
    $buildings = $searchResult['buildings'];
    $totalBuildings = $searchResult['total'];
    $totalPages = $searchResult['totalPages'];
    $currentPage = $searchResult['currentPage'];
} elseif ($query) {
    // キーワード検索
    $searchResult = searchBuildingsNew($query, $page, $hasPhotos, $hasVideos, $lang, $limit);
    $buildings = $searchResult['buildings'];
    $totalBuildings = $searchResult['total'];
    $totalPages = $searchResult['totalPages'];
    $currentPage = $searchResult['currentPage'];
} else {
    // トップページ：has_photo優先順序で建築物を取得
    $searchResult = searchBuildingsNew('', $page, false, false, $lang, $limit);
    $buildings = $searchResult['buildings'];
    $totalBuildings = $searchResult['total'];
    $totalPages = $searchResult['totalPages'];
    $currentPage = $searchResult['currentPage'];
}

// 人気検索の取得
$popularSearches = getPopularSearches($lang);

// デバッグ情報の取得（開発時のみ）
$debugInfo = null;
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $debugInfo = debugDatabase();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang === 'ja' ? 'PocketNavi - 建築物ナビゲーション' : 'PocketNavi - Building Navigation'; ?></title>
    
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
                <!-- Search Form -->
                <?php include 'includes/search_form.php'; ?>
                
                <!-- Debug Information -->
                <?php if ($debugInfo): ?>
                    <div class="alert alert-warning">
                        <h6>デバッグ情報:</h6>
                        <ul>
                            <li>総建築物数: <?php echo $debugInfo['total_buildings']; ?></li>
                            <li>座標がある建築物数: <?php echo $debugInfo['buildings_with_coords']; ?></li>
                            <li>東京の建築物数: <?php echo $debugInfo['tokyo_buildings']; ?></li>
                            <li>検索テスト結果: <?php echo $debugInfo['search_test_result']; ?></li>
                        </ul>
                        <small>URLに?debug=1を追加するとこの情報が表示されます</small>
                    </div>
                <?php endif; ?>
                
                <!-- Search Debug Information -->
                <?php if ($query && isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <h6>検索デバッグ情報:</h6>
                        <ul>
                            <li>検索クエリ: "<?php echo htmlspecialchars($query); ?>"</li>
                            <li>検索結果数: <?php echo count($buildings); ?></li>
                            <li>総件数: <?php echo $totalBuildings; ?></li>
                            <li>ページ: <?php echo $page; ?></li>
                        </ul>
                        <p><strong>注意:</strong> エラーログを確認してください（通常は C:\xampp\apache\logs\error.log）</p>
                    </div>
                <?php endif; ?>
                
                <!-- Building Cards -->
                <div class="row" id="building-cards">
                    <?php if (empty($buildings)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <?php echo $lang === 'ja' ? '建築物が見つかりませんでした。' : 'No buildings found.'; ?>
                                <?php if ($query): ?>
                                    <br><small>検索キーワード: "<?php echo htmlspecialchars($query); ?>"</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($buildings as $index => $building): ?>
                            <div class="col-12 mb-4">
                                <?php include 'includes/building_card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <?php include 'includes/pagination.php'; ?>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <?php include 'includes/sidebar.php'; ?>
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
</body>
</html>

