<?php
// 共通関数

/**
 * サムネイルURLを生成
 */
function generateThumbnailUrl($uid, $hasPhoto) {
    // has_photoがNULLまたは空の場合は空文字を返す
    if (empty($hasPhoto)) {
        return '';
    }
    
    // 新しいURL形式: https://kenchikuka.com/pictures/{uid}/{has_photo}
    return 'https://kenchikuka.com/pictures/' . urlencode($uid) . '/' . urlencode($hasPhoto);
}

/**
 * 建築物を検索する
 */
function searchBuildings($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja') {
    $db = getDB();
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // キーワードを分割（全角・半角スペースで分割）
    $keywords = preg_split('/[\s　]+/', trim($query));
    $keywords = array_filter($keywords, function($keyword) {
        return !empty(trim($keyword));
    });
    
    if (empty($keywords)) {
        return ['buildings' => [], 'total' => 0, 'totalPages' => 0];
    }
    
    // 検索条件の構築
    $whereConditions = [];
    $params = [];
    
    // 各キーワードに対して8フィールド横断検索
    foreach ($keywords as $index => $keyword) {
        $keywordParam = "keyword{$index}";
        $whereConditions[] = "(
            b.title LIKE :{$keywordParam} OR
            b.titleEn LIKE :{$keywordParam} OR
            b.buildingTypes LIKE :{$keywordParam} OR
            b.buildingTypesEn LIKE :{$keywordParam} OR
            b.location LIKE :{$keywordParam} OR
            b.locationEn_from_datasheetChunkEn LIKE :{$keywordParam} OR
            b.architectDetails LIKE :{$keywordParam} OR
            ia.name_ja LIKE :{$keywordParam} OR
            ia.name_en LIKE :{$keywordParam}
        )";
        $params[$keywordParam] = "%{$keyword}%";
    }
    
    // メディアフィルター
    if ($hasPhotos) {
        $whereConditions[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    if ($hasVideos) {
        $whereConditions[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // 座標が存在するもののみ
    $whereConditions[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL";
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 建築家情報を含むクエリ
    $sql = "
        SELECT DISTINCT
            b.building_id,
            b.uid,
            b.slug,
            b.title,
            b.titleEn,
            b.thumbnailUrl,
            b.has_photo,
            b.youtubeUrl,
            b.completionYears,
            b.buildingTypes,
            b.buildingTypesEn,
            b.prefectures,
            b.prefecturesEn,
            b.areas,
            b.location,
            b.locationEn_from_datasheetChunkEn as locationEn,
            b.architectDetails,
            b.lat,
            b.lng,
            0 as likes,
            b.created_at,
            b.updated_at,
            GROUP_CONCAT(DISTINCT ia.name_ja ORDER BY ac.order_index SEPARATOR '　') as architect_names_ja,
            GROUP_CONCAT(DISTINCT ia.name_en ORDER BY ac.order_index SEPARATOR '　') as architect_names_en,
            GROUP_CONCAT(DISTINCT ia.slug ORDER BY ac.order_index SEPARATOR ',') as architect_slugs
        FROM buildings_table_2 b
        LEFT JOIN building_architects ba ON b.building_id = ba.building_id
        LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
        LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE {$whereClause}
        GROUP BY b.building_id
        ORDER BY b.building_id DESC
        LIMIT :limit OFFSET :offset
    ";
    
    // 総件数取得
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id) as total
        FROM buildings_table_2 b
        LEFT JOIN building_architects ba ON b.building_id = ba.building_id
        LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
        LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE {$whereClause}
    ";
    
    try {
        // デバッグ情報を出力
        error_log("Search query: " . $query);
        error_log("Search SQL: " . $sql);
        error_log("Search params: " . print_r($params, true));
        
        // 総件数取得
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(":{$key}", $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        error_log("Total count: " . $total);
        
        // データ取得
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $buildings = [];
        $rowCount = 0;
        while ($row = $stmt->fetch()) {
            $buildings[] = transformBuildingData($row, $lang);
            $rowCount++;
        }
        
        error_log("Fetched rows: " . $rowCount);
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ];
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        return ['buildings' => [], 'total' => 0, 'totalPages' => 0];
    }
}

/**
 * 最近の建築物を取得
 */
function getRecentBuildings($limit = 20, $lang = 'ja') {
    $db = getDB();
    
    $sql = "
        SELECT 
            b.building_id,
            b.uid,
            b.slug,
            b.title,
            b.titleEn,
            b.thumbnailUrl,
            b.has_photo,
            b.youtubeUrl,
            b.completionYears,
            b.buildingTypes,
            b.buildingTypesEn,
            b.prefectures,
            b.prefecturesEn,
            b.areas,
            b.location,
            b.locationEn_from_datasheetChunkEn as locationEn,
            b.architectDetails,
            b.lat,
            b.lng,
            0 as likes,
            b.created_at,
            b.updated_at,
            GROUP_CONCAT(DISTINCT ia.name_ja ORDER BY ac.order_index SEPARATOR '　') as architect_names_ja,
            GROUP_CONCAT(DISTINCT ia.name_en ORDER BY ac.order_index SEPARATOR '　') as architect_names_en,
            GROUP_CONCAT(DISTINCT ia.slug ORDER BY ac.order_index SEPARATOR ',') as architect_slugs
        FROM buildings_table_2 b
        LEFT JOIN building_architects ba ON b.building_id = ba.building_id
        LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
        LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE b.lat IS NOT NULL AND b.lng IS NOT NULL
        GROUP BY b.building_id
        ORDER BY b.building_id DESC
        LIMIT :limit
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $buildings = [];
        while ($row = $stmt->fetch()) {
            $buildings[] = transformBuildingData($row, $lang);
        }
        
        return $buildings;
        
    } catch (PDOException $e) {
        error_log("Recent buildings error: " . $e->getMessage());
        return [];
    }
}

/**
 * 年数を数値に変換
 */
function parseYear($year) {
    if (!$year) return null;
    $parsed = intval($year);
    return $parsed > 0 ? $parsed : null;
}

/**
 * 建築物データを変換
 */
function transformBuildingData($row, $lang = 'ja') {
    // 建築家情報の処理
    $architects = [];
    if (!empty($row['architect_names_ja'])) {
        $namesJa = explode('　', $row['architect_names_ja']);
        $namesEn = !empty($row['architect_names_en']) ? explode('　', $row['architect_names_en']) : [];
        $slugs = !empty($row['architect_slugs']) ? explode(',', $row['architect_slugs']) : [];
        
        for ($i = 0; $i < count($namesJa); $i++) {
            $architects[] = [
                'architect_id' => 0, // 個別IDは使用しない
                'architectJa' => trim($namesJa[$i]),
                'architectEn' => isset($namesEn[$i]) ? trim($namesEn[$i]) : trim($namesJa[$i]),
                'slug' => isset($slugs[$i]) ? trim($slugs[$i]) : ''
            ];
        }
    }
    
    // 建物用途の配列変換
    $buildingTypes = !empty($row['buildingTypes']) ? 
        array_filter(explode('/', $row['buildingTypes']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    $buildingTypesEn = !empty($row['buildingTypesEn']) ? 
        array_filter(explode('/', $row['buildingTypesEn']), function($type) {
            return !empty(trim($type));
        }) : [];
    
    return [
        'id' => $row['building_id'],
        'uid' => $row['uid'],
        'slug' => $row['slug'] ?: $row['uid'],
        'title' => $row['title'],
        'titleEn' => $row['titleEn'] ?: $row['title'],
        'thumbnailUrl' => generateThumbnailUrl($row['uid'] ?? '', $row['has_photo'] ?? null),
        'youtubeUrl' => $row['youtubeUrl'] ?: '',
        'completionYears' => parseYear($row['completionYears']),
        'buildingTypes' => $buildingTypes,
        'buildingTypesEn' => $buildingTypesEn,
        'prefectures' => $row['prefectures'] ?: '',
        'prefecturesEn' => $row['prefecturesEn'] ?: null,
        'areas' => $row['areas'] ?: '',
        'location' => $row['location'] ?: '',
        'locationEn' => $row['locationEn'] ?: $row['location'],
        'architectDetails' => $row['architectDetails'] ?: '',
        'lat' => floatval($row['lat']),
        'lng' => floatval($row['lng']),
        'architects' => $architects,
        'photos' => [], // 写真は別途取得
        'likes' => $row['likes'] ?: 0,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

/**
 * 建築物詳細を取得
 */
function getBuildingBySlug($slug, $lang = 'ja') {
    $db = getDB();
    
    $sql = "
        SELECT 
            b.building_id,
            b.uid,
            b.slug,
            b.title,
            b.titleEn,
            b.thumbnailUrl,
            b.has_photo,
            b.youtubeUrl,
            b.completionYears,
            b.buildingTypes,
            b.buildingTypesEn,
            b.prefectures,
            b.prefecturesEn,
            b.areas,
            b.location,
            b.locationEn_from_datasheetChunkEn as locationEn,
            b.architectDetails,
            b.lat,
            b.lng,
            0 as likes,
            b.created_at,
            b.updated_at,
            GROUP_CONCAT(DISTINCT ia.name_ja ORDER BY ac.order_index SEPARATOR '　') as architect_names_ja,
            GROUP_CONCAT(DISTINCT ia.name_en ORDER BY ac.order_index SEPARATOR '　') as architect_names_en,
            GROUP_CONCAT(DISTINCT ia.slug ORDER BY ac.order_index SEPARATOR ',') as architect_slugs
        FROM buildings_table_2 b
        LEFT JOIN building_architects ba ON b.building_id = ba.building_id
        LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
        LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE b.slug = :slug OR b.uid = :slug
        GROUP BY b.building_id
        LIMIT 1
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        
        $row = $stmt->fetch();
        if ($row) {
            return transformBuildingData($row, $lang);
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Building detail error: " . $e->getMessage());
        return null;
    }
}

/**
 * 人気検索を取得
 */
function getPopularSearches($lang = 'ja') {
    // 固定の人気検索（実際のアプリでは検索ログから取得）
    return [
        ['query' => '安藤忠雄', 'count' => 45],
        ['query' => '美術館', 'count' => 38],
        ['query' => '東京', 'count' => 32],
        ['query' => '現代建築', 'count' => 28]
    ];
}

/**
 * デバッグ用：データベースの状況を確認
 */
function debugDatabase() {
    $db = getDB();
    
    try {
        // 建築物の総数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_2");
        $buildingCount = $stmt->fetch()['total'];
        
        // 座標がある建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_2 WHERE lat IS NOT NULL AND lng IS NOT NULL");
        $buildingWithCoords = $stmt->fetch()['total'];
        
        // 東京を含む建築物の数
        $stmt = $db->query("SELECT COUNT(*) as total FROM buildings_table_2 WHERE location LIKE '%東京%' OR prefectures LIKE '%東京%'");
        $tokyoBuildings = $stmt->fetch()['total'];
        
        // サンプルデータの確認
        $stmt = $db->query("SELECT building_id, title, location, prefectures, lat, lng FROM buildings_table_2 LIMIT 5");
        $sampleData = $stmt->fetchAll();
        
        // 検索クエリのテスト
        $testQuery = "東京";
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM buildings_table_2 b
            LEFT JOIN building_architects ba ON b.building_id = ba.building_id
            LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
            LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE (
                b.title LIKE :keyword OR
                b.titleEn LIKE :keyword OR
                b.buildingTypes LIKE :keyword OR
                b.buildingTypesEn LIKE :keyword OR
                b.location LIKE :keyword OR
                b.locationEn_from_datasheetChunkEn LIKE :keyword OR
                b.architectDetails LIKE :keyword OR
                ia.name_ja LIKE :keyword OR
                ia.name_en LIKE :keyword
            ) AND b.lat IS NOT NULL AND b.lng IS NOT NULL
        ");
        $stmt->bindValue(':keyword', "%{$testQuery}%");
        $stmt->execute();
        $searchTestResult = $stmt->fetch()['total'];
        
        error_log("Database Debug Info:");
        error_log("Total buildings: " . $buildingCount);
        error_log("Buildings with coordinates: " . $buildingWithCoords);
        error_log("Tokyo buildings: " . $tokyoBuildings);
        error_log("Search test result: " . $searchTestResult);
        error_log("Sample data: " . print_r($sampleData, true));
        
        return [
            'total_buildings' => $buildingCount,
            'buildings_with_coords' => $buildingWithCoords,
            'tokyo_buildings' => $tokyoBuildings,
            'search_test_result' => $searchTestResult,
            'sample_data' => $sampleData
        ];
        
    } catch (PDOException $e) {
        error_log("Debug database error: " . $e->getMessage());
        return null;
    }
}

/**
 * 翻訳関数
 */
function t($key, $lang = 'ja') {
    $translations = [
        'ja' => [
            'searchPlaceholder' => '建築物名、建築家、場所で検索...',
            'search' => '検索',
            'currentLocation' => '現在地を検索',
            'detailedSearch' => '詳細検索',
            'withPhotos' => '写真あり',
            'withVideos' => '動画あり',
            'clearFilters' => 'クリア',
            'loading' => '読み込み中...',
            'searchAround' => '周辺を検索',
            'getDirections' => '道順を取得',
            'viewOnGoogleMap' => 'Googleマップで表示',
            'buildingDetails' => '建築物詳細',
            'backToList' => '一覧に戻る',
            'architect' => '建築家',
            'location' => '所在地',
            'prefecture' => '都道府県',
            'buildingTypes' => '建物用途',
            'completionYear' => '完成年',
            'photos' => '写真',
            'videos' => '動画',
            'popularSearches' => '人気の検索',
            'noBuildingsFound' => '建築物が見つかりませんでした。',
            'loadingMap' => '地図を読み込み中...',
            'currentLocation' => '現在地を検索'
        ],
        'en' => [
            'searchPlaceholder' => 'Search by building name, architect, location...',
            'search' => 'Search',
            'currentLocation' => 'Search Current Location',
            'detailedSearch' => 'Detailed Search',
            'withPhotos' => 'With Photos',
            'withVideos' => 'With Videos',
            'clearFilters' => 'Clear',
            'loading' => 'Loading...',
            'searchAround' => 'Search Around',
            'getDirections' => 'Get Directions',
            'viewOnGoogleMap' => 'View on Google Maps',
            'buildingDetails' => 'Building Details',
            'backToList' => 'Back to List',
            'architect' => 'Architect',
            'location' => 'Location',
            'prefecture' => 'Prefecture',
            'buildingTypes' => 'Building Types',
            'completionYear' => 'Completion Year',
            'photos' => 'Photos',
            'videos' => 'Videos',
            'popularSearches' => 'Popular Searches',
            'noBuildingsFound' => 'No buildings found.',
            'loadingMap' => 'Loading map...',
            'currentLocation' => 'Search Current Location'
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

/**
 * ページネーションの範囲を取得
 */
function getPaginationRange($currentPage, $totalPages, $maxVisible = 5) {
    // 総ページ数がmaxVisible以下の場合は全て表示
    if ($totalPages <= $maxVisible) {
        $range = range(1, $totalPages);
    } else {
        $range = [];
        
        // 1ページ目を必ず含める
        $range[] = 1;
        
        // 現在のページ周辺のページを計算
        $start = max(2, $currentPage - floor(($maxVisible - 3) / 2));
        $end = min($totalPages - 1, $start + $maxVisible - 3);
        
        // 開始位置を調整（最終ページが含まれるように）
        if ($end >= $totalPages - 1) {
            $end = $totalPages - 1;
            $start = max(2, $end - $maxVisible + 3);
        }
        
        // 中間のページを追加（重複を避ける）
        for ($i = $start; $i <= $end; $i++) {
            if ($i > 1 && $i < $totalPages && !in_array($i, $range)) {
                $range[] = $i;
            }
        }
        
        // 最終ページを必ず含める（1ページ目でない場合）
        if ($totalPages > 1) {
            $range[] = $totalPages;
        }
        
        // ソートして重複を除去
        $range = array_unique($range);
        sort($range);
    }
    
    // デバッグ情報を追加
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        error_log("getPaginationRange: currentPage=$currentPage, totalPages=$totalPages, maxVisible=$maxVisible");
        error_log("getPaginationRange: range=" . implode(',', $range));
    }
    
    return $range;
}
?>

