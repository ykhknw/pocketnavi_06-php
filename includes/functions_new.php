<?php
// 共通関数（新しい検索ロジック）

/**
 * データベース接続を取得
 */
function getDatabaseConnection() {
    try {
        $host = 'localhost';
        $dbname = '_shinkenchiku_db';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("データベース接続エラーが発生しました。");
    }
}

/**
 * 現在地検索用の関数
 */
function searchBuildingsByLocation($userLat, $userLng, $radiusKm = 5, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // 位置情報による検索（Haversine公式を使用）
    $whereClauses[] = "(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(b.lat)))) < ?";
    $params[] = $userLat;
    $params[] = $userLng;
    $params[] = $userLat;
    $params[] = $radiusKm;
    
    // 座標が有効なデータのみ
    $whereClauses[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL AND b.lat != 0 AND b.lng != 0";
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // 写真フィルター
    if ($hasPhotos) {
        $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    // 動画フィルター
    if ($hasVideos) {
        $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // WHERE句を構築
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
    
    // --- 件数取得 ---
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // --- データ取得クエリ（距離順でソート） ---
    $sql = "
        SELECT b.*,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs,
               (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(b.lat)))) AS distance
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY distance ASC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    // 距離計算用のパラメータを追加
    $distanceParams = [$userLat, $userLng, $userLat];
    $allParams = array_merge($distanceParams, $params);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($allParams);
        
        $buildings = [];
        while ($row = $stmt->fetch()) {
            $building = transformBuildingDataNew($row, $lang);
            $building['distance'] = round($row['distance'], 2); // 距離を追加
            $buildings[] = $building;
        }
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit
        ];
        
    } catch (PDOException $e) {
        error_log("Location search error: " . $e->getMessage());
        return ['buildings' => [], 'total' => 0, 'totalPages' => 0];
    }
}

/**
 * 建築家の建築物を取得（ページネーション対応）
 */
function searchBuildingsByArchitectSlug($architectSlug, $lang = 'ja', $limit = 10, $page = 1, $completionYears = '', $prefectures = '', $query = '') {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // デバッグ情報を追加
    error_log("searchBuildingsByArchitectSlug: Looking for architect slug: " . $architectSlug);
    error_log("searchBuildingsByArchitectSlug: completionYears: " . ($completionYears ?: 'empty'));
    error_log("searchBuildingsByArchitectSlug: prefectures: " . ($prefectures ?: 'empty'));
    error_log("searchBuildingsByArchitectSlug: query: " . ($query ?: 'empty'));
    
    // 件数取得クエリ
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $individual_architects_table ia
        INNER JOIN $architect_compositions_table ac ON ia.individual_architect_id = ac.individual_architect_id
        INNER JOIN $building_architects_table ba ON ac.architect_id = ba.architect_id
        INNER JOIN $buildings_table b ON ba.building_id = b.building_id
        WHERE ia.slug = :architect_slug
        AND b.location IS NOT NULL AND b.location != ''
    ";
    
    // completionYearsフィルターを追加
    if (!empty($completionYears)) {
        $countSql .= " AND b.completionYears = :completion_years";
    }
    
    // prefecturesフィルターを追加
    if (!empty($prefectures)) {
        $countSql .= " AND b.prefecturesEn = :prefectures";
    }
    
    // queryフィルターを追加（キーワード検索）
    if (!empty($query)) {
        $countSql .= " AND (b.title LIKE :query1 OR b.titleEn LIKE :query2 OR b.location LIKE :query3 OR b.locationEn_from_datasheetChunkEn LIKE :query4 OR b.buildingTypes LIKE :query5 OR b.buildingTypesEn LIKE :query6)";
    }
    
    // 建築家の詳細情報を取得
    $architectInfoSql = "
        SELECT individual_architect_id, name_ja, name_en, individual_website, website_title
        FROM $individual_architects_table 
        WHERE slug = :architect_slug
        LIMIT 1
    ";
    
    $architectStmt = $db->prepare($architectInfoSql);
    $architectStmt->bindValue(':architect_slug', $architectSlug);
    $architectStmt->execute();
    $architectInfo = $architectStmt->fetch(PDO::FETCH_ASSOC);
    
    // データ取得クエリ（全ての建築家情報を取得）
    $sql = "
        SELECT 
            b.*,
            GROUP_CONCAT(
                DISTINCT ia_all.name_ja 
                ORDER BY ba_all.architect_order, ac_all.order_index 
                SEPARATOR ' / '
            ) AS architectJa,
            GROUP_CONCAT(
                DISTINCT ia_all.name_en 
                ORDER BY ba_all.architect_order, ac_all.order_index 
                SEPARATOR ' / '
            ) AS architectEn,
            GROUP_CONCAT(
                DISTINCT ba_all.architect_id 
                ORDER BY ba_all.architect_order 
                SEPARATOR ','
            ) AS architectIds,
            GROUP_CONCAT(
                DISTINCT ia_all.slug 
                ORDER BY ba_all.architect_order, ac_all.order_index 
                SEPARATOR ','
            ) AS architectSlugs
        FROM $individual_architects_table ia
        INNER JOIN $architect_compositions_table ac ON ia.individual_architect_id = ac.individual_architect_id
        INNER JOIN $building_architects_table ba ON ac.architect_id = ba.architect_id
        INNER JOIN $buildings_table b ON ba.building_id = b.building_id
        LEFT JOIN $building_architects_table ba_all ON b.building_id = ba_all.building_id
        LEFT JOIN $architect_compositions_table ac_all ON ba_all.architect_id = ac_all.architect_id
        LEFT JOIN $individual_architects_table ia_all ON ac_all.individual_architect_id = ia_all.individual_architect_id
        WHERE ia.slug = :architect_slug
        AND b.location IS NOT NULL AND b.location != ''
    ";
    
    // completionYearsフィルターを追加
    if (!empty($completionYears)) {
        $sql .= " AND b.completionYears = :completion_years";
    }
    
    // prefecturesフィルターを追加
    if (!empty($prefectures)) {
        $sql .= " AND b.prefecturesEn = :prefectures";
    }
    
    // queryフィルターを追加（キーワード検索）
    if (!empty($query)) {
        $sql .= " AND (b.title LIKE :query1 OR b.titleEn LIKE :query2 OR b.location LIKE :query3 OR b.locationEn_from_datasheetChunkEn LIKE :query4 OR b.buildingTypes LIKE :query5 OR b.buildingTypesEn LIKE :query6)";
    }
    
    $sql .= "
        GROUP BY b.building_id
        ORDER BY b.has_photo DESC, b.building_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        error_log("searchBuildingsByArchitectSlug SQL: " . $sql);
        error_log("searchBuildingsByArchitectSlug Count SQL: " . $countSql);
        
        // 総件数取得
        $countStmt = $db->prepare($countSql);
        $countStmt->bindValue(':architect_slug', $architectSlug);
        if (!empty($completionYears)) {
            $countStmt->bindValue(':completion_years', $completionYears);
        }
        if (!empty($prefectures)) {
            $countStmt->bindValue(':prefectures', $prefectures);
        }
        if (!empty($query)) {
            $countStmt->bindValue(':query1', '%' . $query . '%');
            $countStmt->bindValue(':query2', '%' . $query . '%');
            $countStmt->bindValue(':query3', '%' . $query . '%');
            $countStmt->bindValue(':query4', '%' . $query . '%');
            $countStmt->bindValue(':query5', '%' . $query . '%');
            $countStmt->bindValue(':query6', '%' . $query . '%');
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();
        $totalPages = ceil($total / $limit);
        
        // データ取得
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':architect_slug', $architectSlug);
        if (!empty($completionYears)) {
            $stmt->bindValue(':completion_years', $completionYears);
        }
        if (!empty($prefectures)) {
            $stmt->bindValue(':prefectures', $prefectures);
        }
        if (!empty($query)) {
            $stmt->bindValue(':query1', '%' . $query . '%');
            $stmt->bindValue(':query2', '%' . $query . '%');
            $stmt->bindValue(':query3', '%' . $query . '%');
            $stmt->bindValue(':query4', '%' . $query . '%');
            $stmt->bindValue(':query5', '%' . $query . '%');
            $stmt->bindValue(':query6', '%' . $query . '%');
        }
        $stmt->execute();
        
        $buildings = [];
        $rowCount = 0;
        while ($row = $stmt->fetch()) {
            error_log("searchBuildingsByArchitectSlug: Raw row data: " . print_r($row, true));
            $transformedBuilding = transformBuildingDataNew($row, $lang);
            error_log("searchBuildingsByArchitectSlug: Transformed building: " . print_r($transformedBuilding, true));
            $buildings[] = $transformedBuilding;
            $rowCount++;
        }
        
        error_log("searchBuildingsByArchitectSlug: Found " . $rowCount . " buildings for architect slug: " . $architectSlug);
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit,
            'architectInfo' => $architectInfo
        ];
        
    } catch (PDOException $e) {
        error_log("Architect buildings search error: " . $e->getMessage());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page,
            'limit' => $limit,
            'architectInfo' => null
        ];
    }
}

/**
 * 建築物を検索する（新しいロジック）
 */
function searchBuildingsNew($query, $page = 1, $hasPhotos = false, $hasVideos = false, $lang = 'ja', $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // キーワードを分割（全角・半角スペースで分割）
    $temp = str_replace('　', ' ', $query);
    $keywords = array_filter(explode(' ', trim($temp)));
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // 横断検索の処理
    if (!empty($keywords)) {
        // 各キーワードに対してOR条件を構築し、全体をANDで結合
        $keywordConditions = [];
        foreach ($keywords as $keyword) {
            $escapedKeyword = '%' . $keyword . '%';
            $fieldConditions = [
                "b.title LIKE ?",
                "b.titleEn LIKE ?",
                "b.buildingTypes LIKE ?",
                "b.buildingTypesEn LIKE ?",
                "b.location LIKE ?",
                "b.locationEn_from_datasheetChunkEn LIKE ?",
                "ia.name_ja LIKE ?",
                "ia.name_en LIKE ?"
            ];
            $keywordConditions[] = '(' . implode(' OR ', $fieldConditions) . ')';
            
            // パラメータを8回追加（各フィールド用）
            for ($i = 0; $i < 8; $i++) {
                $params[] = $escapedKeyword;
            }
        }
        
        if (!empty($keywordConditions)) {
            $whereClauses[] = '(' . implode(' AND ', $keywordConditions) . ')';
        }
    }
    
    // メディアフィルター
    if ($hasPhotos) {
        $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    if ($hasVideos) {
        $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // 座標が存在するもののみ
    $whereClauses[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL";
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // WHERE句を構築
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
    
    // 件数取得
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    // データ取得クエリ
    $sql = "
        SELECT b.building_id,
               b.uid,
               b.title,
               b.titleEn,
               b.slug,
               b.lat,
               b.lng,
               b.location,
               b.locationEn_from_datasheetChunkEn as locationEn,
               b.completionYears,
               b.buildingTypes,
               b.buildingTypesEn,
               b.prefectures,
               b.prefecturesEn,
               b.has_photo,
               b.thumbnailUrl,
               b.youtubeUrl,
               b.created_at,
               b.updated_at,
               0 as likes,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY b.has_photo DESC, b.building_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        // デバッグ情報を出力
        error_log("Search query: " . $query);
        error_log("Search SQL: " . $sql);
        error_log("Search params: " . print_r($params, true));
        
        // 総件数取得
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        error_log("Total count: " . $total);
        
        // データ取得
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $buildings = [];
        $rowCount = 0;
        while ($row = $stmt->fetch()) {
        // デバッグコード削除
            $buildings[] = transformBuildingDataNew($row, $lang);
            $rowCount++;
        }
        
        error_log("Fetched rows: " . $rowCount);
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => ceil($total / $limit),
            'currentPage' => $page,
            'limit' => $limit
        ];
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        return ['buildings' => [], 'total' => 0, 'totalPages' => 0];
    }
}

/**
 * 建築物データを変換（新しい形式）
 */
function transformBuildingDataNew($row, $lang = 'ja') {
    // 建築家情報の処理
    $architects = [];
    if (!empty($row['architectJa'])) {
        $namesJa = explode(' / ', $row['architectJa']);
        $namesEn = !empty($row['architectEn']) ? explode(' / ', $row['architectEn']) : [];
        $architectIds = !empty($row['architectIds']) ? explode(',', $row['architectIds']) : [];
        $architectSlugs = !empty($row['architectSlugs']) ? explode(',', $row['architectSlugs']) : [];
        
        for ($i = 0; $i < count($namesJa); $i++) {
            $architects[] = [
                'architect_id' => isset($architectIds[$i]) ? intval($architectIds[$i]) : 0,
                'architectJa' => trim($namesJa[$i]),
                'architectEn' => isset($namesEn[$i]) ? trim($namesEn[$i]) : trim($namesJa[$i]),
                'slug' => isset($architectSlugs[$i]) ? trim($architectSlugs[$i]) : ''
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
    
    // 英語データの処理
    $titleEn = $row['titleEn'] ?? $row['title'] ?? '';
    $locationEn = '';
    if (!empty($row['locationEn_from_datasheetChunkEn'])) {
        $locationEn = $row['locationEn_from_datasheetChunkEn'];
    } elseif (!empty($row['locationEn'])) {
        $locationEn = $row['locationEn'];
    } else {
        $locationEn = $row['location'] ?? '';
    }
    
    // デバッグ：最初の1件のみログ出力
    static $debugCount = 0;
    if ($debugCount === 0) {
        error_log("=== transformBuildingDataNew Debug (Fixed) ===");
        error_log("Raw row titleEn: " . ($row['titleEn'] ?? 'NULL'));
        error_log("Raw row locationEn_from_datasheetChunkEn: " . ($row['locationEn_from_datasheetChunkEn'] ?? 'NULL'));
        error_log("Raw row locationEn: " . ($row['locationEn'] ?? 'NULL'));
        error_log("Raw row location: " . ($row['location'] ?? 'NULL'));
        error_log("Processed titleEn: " . $titleEn);
        error_log("Processed locationEn: " . $locationEn);
        error_log("Raw row architectJa: " . ($row['architectJa'] ?? 'NULL'));
        error_log("Raw row architectEn: " . ($row['architectEn'] ?? 'NULL'));
        error_log("Raw row architectSlugs: " . ($row['architectSlugs'] ?? 'NULL'));
        error_log("Processed architects: " . print_r($architects, true));
        $debugCount++;
    }
    
    return [
        'building_id' => $row['building_id'] ?? 0, // building_card.phpで期待されるキー名に変更
        'id' => $row['building_id'] ?? 0, // 後方互換性のため残す
        'uid' => $row['uid'] ?? '',
        'slug' => $row['slug'] ?? $row['uid'] ?? '',
        'title' => $row['title'] ?? '',
        'titleEn' => $titleEn,
        'thumbnailUrl' => generateThumbnailUrl($row['uid'] ?? '', $row['has_photo'] ?? null),
        'youtubeUrl' => $row['youtubeUrl'] ?? '',
        'completionYears' => parseYear($row['completionYears'] ?? ''),
        'buildingTypes' => $buildingTypes,
        'buildingTypesEn' => $buildingTypesEn,
        'prefectures' => $row['prefectures'] ?? '',
        'prefecturesEn' => $row['prefecturesEn'] ?? null,
        'areas' => $row['areas'] ?? '',
        'location' => $row['location'] ?? '',
        'locationEn' => $locationEn,
        'architectDetails' => $row['architectDetails'] ?? '',
        'lat' => floatval($row['lat'] ?? 0),
        'lng' => floatval($row['lng'] ?? 0),
        'architects' => $architects,
        'photos' => [], // 写真は別途取得
        'likes' => 0, // likesカラムがない場合は0
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? ''
    ];
}

/**
 * スラッグで建築物を取得（新しい形式）
 */
function getBuildingBySlugNew($slug, $lang = 'ja') {
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
            GROUP_CONCAT(DISTINCT ia.name_ja ORDER BY ac.order_index SEPARATOR ' / ') as architectJa,
            GROUP_CONCAT(DISTINCT ia.name_en ORDER BY ac.order_index SEPARATOR ' / ') as architectEn,
            GROUP_CONCAT(DISTINCT ba.architect_id ORDER BY ba.architect_order SEPARATOR ',') as architectIds,
            GROUP_CONCAT(DISTINCT ia.slug ORDER BY ac.order_index SEPARATOR ',') as architectSlugs
        FROM buildings_table_2 b
        LEFT JOIN building_architects ba ON b.building_id = ba.building_id
        LEFT JOIN architect_compositions_2 ac ON ba.architect_id = ac.architect_id
        LEFT JOIN individual_architects_3 ia ON ac.individual_architect_id = ia.individual_architect_id
        WHERE b.slug = :slug
        GROUP BY b.building_id
        LIMIT 1
    ";
    
    try {
        // デバッグ情報を追加
        error_log("getBuildingBySlugNew: Looking for slug: " . $slug);
        error_log("getBuildingBySlugNew SQL: " . $sql);
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        
        $row = $stmt->fetch();
        error_log("getBuildingBySlugNew: Row found: " . ($row ? 'Yes' : 'No'));
        
        if ($row) {
            error_log("getBuildingBySlugNew: Row data: " . print_r($row, true));
            error_log("getBuildingBySlugNew: locationEn_from_datasheetChunkEn = " . ($row['locationEn_from_datasheetChunkEn'] ?? 'NULL'));
            return transformBuildingDataNew($row, $lang);
        }
        
        // スラッグが見つからない場合の追加検索は削除
        
        return null;
        
    } catch (PDOException $e) {
        error_log("getBuildingBySlugNew error: " . $e->getMessage());
        return null;
    }
}

/**
 * 建物slug検索用の関数（index.php用）
 */
function searchBuildingsBySlug($buildingSlug, $lang = 'ja', $limit = 10) {
    $db = getDB();
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // slug検索条件
    $whereClauses[] = "b.slug = ?";
    $params[] = $buildingSlug;
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // WHERE句を構築
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
    
    // --- 件数取得 ---
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // --- データ取得クエリ ---
    $sql = "
        SELECT b.*,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY b.building_id DESC
        LIMIT {$limit}
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $buildings = [];
        while ($row = $stmt->fetch()) {
            $buildings[] = transformBuildingDataNew($row, $lang);
        }
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => 1
        ];
        
    } catch (PDOException $e) {
        error_log("searchBuildingsBySlug error: " . $e->getMessage());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => 1
        ];
    }
}

/**
 * 都道府県検索用の関数（index.php用）
 */
function searchBuildingsByPrefecture($prefecture, $page = 1, $lang = 'ja', $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // 都道府県検索条件（prefecturesEnカラムで検索）
    $whereClauses[] = "b.prefecturesEn = ?";
    $params[] = $prefecture;
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // WHERE句を構築
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
    
    // --- 件数取得 ---
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // --- データ取得クエリ ---
    $sql = "
        SELECT b.*,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY b.building_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $buildings = [];
        while ($row = $stmt->fetch()) {
            $buildings[] = transformBuildingDataNew($row, $lang);
        }
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
        
    } catch (PDOException $e) {
        error_log("searchBuildingsByPrefecture error: " . $e->getMessage());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

/**
 * 建築年で建物を検索する関数
 */
function searchBuildingsByCompletionYear($completionYear, $page = 1, $lang = 'ja', $limit = 10) {
    try {
        $pdo = getDatabaseConnection();
        
        // デバッグ: 実際のcompletionYearsデータを確認
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            $debugSql = "SELECT DISTINCT completionYears FROM buildings_table_2 WHERE completionYears IS NOT NULL AND completionYears != '' ORDER BY completionYears LIMIT 20";
            $debugStmt = $pdo->prepare($debugSql);
            $debugStmt->execute();
            $debugData = $debugStmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Available completionYears: " . implode(', ', $debugData));
            error_log("Searching for: " . $completionYear);
            
            // デバッグ情報をHTMLにも出力
            echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
            echo "<h3>デバッグ情報</h3>";
            echo "<p><strong>検索対象:</strong> " . htmlspecialchars($completionYear) . "</p>";
            echo "<p><strong>利用可能な年:</strong> " . htmlspecialchars(implode(', ', $debugData)) . "</p>";
            echo "</div>";
        }
        
        // オフセット計算
        $offset = ($page - 1) * $limit;
        
        // テーブル名を定義
        $architect_compositions_table = 'architect_compositions_2';
        $buildings_table = 'buildings_table_2';
        $building_architects_table = 'building_architects';
        $individual_architects_table = 'individual_architects_3';
        
        // 総件数を取得（正しいテーブル結合）
        $countSql = "
            SELECT COUNT(DISTINCT b.building_id)
            FROM $buildings_table b
            LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
            LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
            LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE CAST(b.completionYears AS CHAR) = ? 
            AND b.location IS NOT NULL 
            AND b.location != ''
        ";
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([(string)$completionYear]);
        $total = $countStmt->fetchColumn();
        $totalPages = ceil($total / $limit);
        
        // デバッグ: 検索結果をログに出力
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("Total count for completionYear '$completionYear': $total");
            echo "<p><strong>検索結果件数:</strong> $total</p>";
        }
        
        // 建物データを取得（正しいテーブル結合）
        $sql = "
            SELECT b.building_id,
                   b.uid,
                   b.title,
                   b.titleEn,
                   b.slug,
                   b.lat,
                   b.lng,
                   b.location,
                   b.locationEn_from_datasheetChunkEn as locationEn,
                   b.completionYears,
                   b.buildingTypes,
                   b.buildingTypesEn,
                   b.prefectures,
                   b.prefecturesEn,
                   b.has_photo,
                   b.thumbnailUrl,
                   b.youtubeUrl,
                   b.created_at,
                   b.updated_at,
                   0 as likes,
                   GROUP_CONCAT(
                       DISTINCT ia.name_ja 
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR ' / '
                   ) AS architectJa,
                   GROUP_CONCAT(
                       DISTINCT ia.name_en 
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR ' / '
                   ) AS architectEn,
                   GROUP_CONCAT(
                       DISTINCT ia.slug 
                       ORDER BY ba.architect_order, ac.order_index 
                       SEPARATOR ','
                   ) AS architectSlugs,
                   GROUP_CONCAT(
                       DISTINCT ba.architect_id 
                       ORDER BY ba.architect_order 
                       SEPARATOR ','
                   ) AS architectIds
            FROM $buildings_table b
            LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
            LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
            LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
            WHERE CAST(b.completionYears AS CHAR) = ? 
            AND b.location IS NOT NULL 
            AND b.location != ''
            GROUP BY b.building_id
            ORDER BY b.building_id
            LIMIT {$offset}, {$limit}
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(string)$completionYear]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 建築家情報を配列に変換
        foreach ($buildings as &$building) {
            $building['architects'] = [];
            if (!empty($building['architectJa'])) {
                $architectJaList = explode(' / ', $building['architectJa']);
                $architectEnList = explode(' / ', $building['architectEn']);
                $architectSlugList = explode(',', $building['architectSlugs']);
                
                for ($i = 0; $i < count($architectJaList); $i++) {
                    $building['architects'][] = [
                        'architectJa' => $architectJaList[$i] ?? '',
                        'architectEn' => $architectEnList[$i] ?? '',
                        'slug' => $architectSlugList[$i] ?? ''
                    ];
                }
            }
            
            // サムネイルURLを生成
            $building['thumbnailUrl'] = generateThumbnailUrl($building['uid'] ?? '', $building['has_photo'] ?? null);
        }
        
        // デバッグ: 最終結果を出力
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<p><strong>最終結果:</strong></p>";
            echo "<ul>";
            echo "<li>建物数: " . count($buildings) . "</li>";
            echo "<li>総件数: $total</li>";
            echo "<li>総ページ数: $totalPages</li>";
            echo "<li>現在のページ: $page</li>";
            echo "</ul>";
        }
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
        
    } catch (Exception $e) {
        error_log("searchBuildingsByCompletionYear error: " . $e->getMessage());
        error_log("searchBuildingsByCompletionYear stack trace: " . $e->getTraceAsString());
        
        // デバッグモードの場合はエラーを表示
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<div style='background: #ffebee; padding: 10px; margin: 10px; border: 1px solid #f44336;'>";
            echo "<h3>エラー</h3>";
            echo "<p><strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>スタックトレース:</strong></p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
        
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}

// parseYear関数は既にfunctions.phpで定義されているため、ここでは削除

// ポップアップコンテンツを生成する関数
function generatePopupContent($building, $lang = 'ja') {
    // 英語版の場合はtitleEnとlocationEnを使用、日本語版ではtitleとlocationを使用
    if ($lang === 'en') {
        $title = !empty($building['titleEn']) ? $building['titleEn'] : $building['title'];
        $location = !empty($building['locationEn']) ? $building['locationEn'] : $building['location'];
    } else {
        $title = $building['title'];
        $location = $building['location'];
    }
    
    $popupHtml = '<div style="padding: 8px; min-width: 200px;">';
    $popupHtml .= '<h3 style="font-weight: bold; font-size: 16px; margin-bottom: 8px;">';
    $popupHtml .= '<a href="/buildings/' . htmlspecialchars($building['slug']) . '" style="color: #1e40af; text-decoration: none;">';
    $popupHtml .= htmlspecialchars($title ?: 'Untitled');
    $popupHtml .= '</a></h3>';
    
    if ($location) {
        $popupHtml .= '<div style="margin-bottom: 8px; display: flex; align-items: center;">';
        $popupHtml .= '<i data-lucide="map-pin" style="width: 16px; height: 16px; margin-right: 6px;"></i> ';
        $popupHtml .= htmlspecialchars($location);
        $popupHtml .= '</div>';
    }
    
    $popupHtml .= '</div>';
    
    return $popupHtml;
}

/**
 * 複数条件のAND検索を行う関数
 */
function searchBuildingsWithMultipleConditions($query, $prefectures, $completionYears, $hasPhotos, $hasVideos, $page = 1, $lang = 'ja', $limit = 10) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    // テーブル名の定義
    $buildings_table = 'buildings_table_2';
    $building_architects_table = 'building_architects';
    $architect_compositions_table = 'architect_compositions_2';
    $individual_architects_table = 'individual_architects_3';
    
    // WHERE句の構築
    $whereClauses = [];
    $params = [];
    
    // デバッグ: 入力パラメータを確認
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        error_log("searchBuildingsWithMultipleConditions input - query: '$query', prefectures: '$prefectures', completionYears: '$completionYears', hasPhotos: " . ($hasPhotos ? 'true' : 'false') . ", hasVideos: " . ($hasVideos ? 'true' : 'false'));
        error_log("searchBuildingsWithMultipleConditions - page: $page, lang: $lang, limit: $limit");
    }
    
    // キーワード検索条件
    if (!empty($query)) {
        // キーワードを分割（全角・半角スペースで分割）
        $temp = str_replace('　', ' ', $query);
        $keywords = array_filter(explode(' ', trim($temp)));
        
        if (!empty($keywords)) {
            // 各キーワードに対してOR条件を構築し、全体をANDで結合
            $keywordConditions = [];
            foreach ($keywords as $keyword) {
                $escapedKeyword = '%' . $keyword . '%';
                $fieldConditions = [
                    "b.title LIKE ?",
                    "b.titleEn LIKE ?",
                    "b.buildingTypes LIKE ?",
                    "b.buildingTypesEn LIKE ?",
                    "b.location LIKE ?",
                    "b.locationEn_from_datasheetChunkEn LIKE ?",
                    "ia.name_ja LIKE ?",
                    "ia.name_en LIKE ?"
                ];
                $keywordConditions[] = '(' . implode(' OR ', $fieldConditions) . ')';
                
                // パラメータを8回追加（各フィールド用）
                for ($i = 0; $i < 8; $i++) {
                    $params[] = $escapedKeyword;
                }
            }
            
            if (!empty($keywordConditions)) {
                $whereClauses[] = '(' . implode(' AND ', $keywordConditions) . ')';
            }
        }
    }
    
    // 都道府県検索条件
    if (!empty($prefectures)) {
        $whereClauses[] = "b.prefecturesEn = ?";
        $params[] = $prefectures;
    }
    
    // 建築年検索条件
    if (!empty($completionYears)) {
        $whereClauses[] = "CAST(b.completionYears AS CHAR) = ?";
        $params[] = (string)$completionYears;
    }
    
    // メディアフィルター
    if ($hasPhotos) {
        $whereClauses[] = "b.has_photo IS NOT NULL AND b.has_photo != ''";
    }
    
    if ($hasVideos) {
        $whereClauses[] = "b.youtubeUrl IS NOT NULL AND b.youtubeUrl != ''";
    }
    
    // 座標が存在するもののみ
    $whereClauses[] = "b.lat IS NOT NULL AND b.lng IS NOT NULL";
    
    // locationカラムが空でないもののみ
    $whereClauses[] = "b.location IS NOT NULL AND b.location != ''";
    
    // WHERE句を構築
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
    
    // デバッグ: WHERE句を確認
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        error_log("WHERE clauses: " . print_r($whereClauses, true));
        error_log("WHERE SQL: " . $whereSql);
    }
    
    // --- 件数取得 ---
    $countSql = "
        SELECT COUNT(DISTINCT b.building_id)
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
    ";
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // --- データ取得クエリ ---
    $sql = "
        SELECT b.building_id,
               b.uid,
               b.title,
               b.titleEn,
               b.slug,
               b.lat,
               b.lng,
               b.location,
               b.locationEn_from_datasheetChunkEn as locationEn,
               b.completionYears,
               b.buildingTypes,
               b.buildingTypesEn,
               b.prefectures,
               b.prefecturesEn,
               b.has_photo,
               b.thumbnailUrl,
               b.youtubeUrl,
               b.created_at,
               b.updated_at,
               0 as likes,
               GROUP_CONCAT(
                   DISTINCT ia.name_ja 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectJa,
               GROUP_CONCAT(
                   DISTINCT ia.name_en 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ' / '
               ) AS architectEn,
               GROUP_CONCAT(
                   DISTINCT ba.architect_id 
                   ORDER BY ba.architect_order 
                   SEPARATOR ','
               ) AS architectIds,
               GROUP_CONCAT(
                   DISTINCT ia.slug 
                   ORDER BY ba.architect_order, ac.order_index 
                   SEPARATOR ','
               ) AS architectSlugs
        FROM $buildings_table b
        LEFT JOIN $building_architects_table ba ON b.building_id = ba.building_id
        LEFT JOIN $architect_compositions_table ac ON ba.architect_id = ac.architect_id
        LEFT JOIN $individual_architects_table ia ON ac.individual_architect_id = ia.individual_architect_id
        $whereSql
        GROUP BY b.building_id
        ORDER BY b.has_photo DESC, b.building_id DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    try {
        // デバッグ情報を追加
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            // データベースの基本情報を確認
            $debugSql = "SELECT COUNT(*) as total_buildings FROM $buildings_table";
            $debugStmt = $db->prepare($debugSql);
            $debugStmt->execute();
            $totalBuildingsInDB = $debugStmt->fetchColumn();
            error_log("Total buildings in database: $totalBuildingsInDB");
            
            // 座標がある建築物数を確認
            $coordSql = "SELECT COUNT(*) as buildings_with_coords FROM $buildings_table WHERE lat IS NOT NULL AND lng IS NOT NULL";
            $coordStmt = $db->prepare($coordSql);
            $coordStmt->execute();
            $buildingsWithCoords = $coordStmt->fetchColumn();
            error_log("Buildings with coordinates: $buildingsWithCoords");
            
            // locationがある建築物数を確認
            $locationSql = "SELECT COUNT(*) as buildings_with_location FROM $buildings_table WHERE location IS NOT NULL AND location != ''";
            $locationStmt = $db->prepare($locationSql);
            $locationStmt->execute();
            $buildingsWithLocation = $locationStmt->fetchColumn();
            error_log("Buildings with location: $buildingsWithLocation");
            
            error_log("searchBuildingsWithMultipleConditions SQL: " . $sql);
            error_log("searchBuildingsWithMultipleConditions params: " . print_r($params, true));
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $buildings = [];
        while ($row = $stmt->fetch()) {
            $buildings[] = transformBuildingDataNew($row, $lang);
        }
        
        // デバッグ情報を追加
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("searchBuildingsWithMultipleConditions result - total: $total, buildings count: " . count($buildings));
        }
        
        return [
            'buildings' => $buildings,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
        
    } catch (PDOException $e) {
        error_log("searchBuildingsWithMultipleConditions PDO error: " . $e->getMessage());
        error_log("searchBuildingsWithMultipleConditions error trace: " . $e->getTraceAsString());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    } catch (Exception $e) {
        error_log("searchBuildingsWithMultipleConditions general error: " . $e->getMessage());
        error_log("searchBuildingsWithMultipleConditions error trace: " . $e->getTraceAsString());
        return [
            'buildings' => [],
            'total' => 0,
            'totalPages' => 0,
            'currentPage' => $page
        ];
    }
}
?>
