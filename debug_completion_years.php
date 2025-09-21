<?php
// デバッグ用スクリプト - completionYearsのデータを確認

try {
    $pdo = new PDO('mysql:host=localhost;dbname=_shinkenchiku_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>completionYears データの確認</h2>";
    
    // 1. 実際のcompletionYearsデータを確認
    $stmt = $pdo->prepare('SELECT DISTINCT completionYears FROM buildings_table_2 WHERE completionYears IS NOT NULL AND completionYears != "" ORDER BY completionYears LIMIT 20');
    $stmt->execute();
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>利用可能なcompletionYears（最初の20件）:</h3>";
    echo "<pre>" . implode(', ', $years) . "</pre>";
    
    // 2. 2000年のデータがあるかチェック（数値として）
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM buildings_table_2 WHERE completionYears = ?');
    $stmt->execute(['2000']);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>2000年のデータ数（数値比較）: " . $count . "</h3>";
    
    // 3. 文字列として比較
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM buildings_table_2 WHERE CAST(completionYears AS CHAR) = ?');
    $stmt->execute(['2000']);
    $count2 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>2000年のデータ数（文字列比較）: " . $count2 . "</h3>";
    
    // 4. LIKE検索で部分一致を試す
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM buildings_table_2 WHERE completionYears LIKE ?');
    $stmt->execute(['%2000%']);
    $count3 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>2000年を含むデータ数（LIKE検索）: " . $count3 . "</h3>";
    
    // 5. 1998年のデータを確認
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM buildings_table_2 WHERE completionYears = ?');
    $stmt->execute(['1998']);
    $count1998 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>1998年のデータ数: " . $count1998 . "</h3>";
    
    if ($count1998 > 0) {
        $stmt = $pdo->prepare('SELECT building_id, title, completionYears, location FROM buildings_table_2 WHERE completionYears = ? LIMIT 5');
        $stmt->execute(['1998']);
        $examples1998 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>1998年のデータ例:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>building_id</th><th>title</th><th>completionYears</th><th>location</th></tr>";
        foreach ($examples1998 as $example) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($example['building_id']) . "</td>";
            echo "<td>" . htmlspecialchars($example['title']) . "</td>";
            echo "<td>" . htmlspecialchars($example['completionYears']) . "</td>";
            echo "<td>" . htmlspecialchars($example['location']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 6. 実際のデータの例を表示
    $stmt = $pdo->prepare('SELECT building_id, title, completionYears FROM buildings_table_2 WHERE completionYears IS NOT NULL AND completionYears != "" ORDER BY completionYears LIMIT 10');
    $stmt->execute();
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>データの例（最初の10件）:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>building_id</th><th>title</th><th>completionYears</th></tr>";
    foreach ($examples as $example) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($example['building_id']) . "</td>";
        echo "<td>" . htmlspecialchars($example['title']) . "</td>";
        echo "<td>" . htmlspecialchars($example['completionYears']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. locationが空でないデータの確認
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM buildings_table_2 WHERE completionYears = ? AND location IS NOT NULL AND location != ""');
    $stmt->execute(['2000']);
    $count4 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<h3>2000年でlocationが空でないデータ数: " . $count4 . "</h3>";
    
} catch (Exception $e) {
    echo "<h2>エラー:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
