<?php
// デバッグ用スクリプト - テーブル名を確認

try {
    $pdo = new PDO('mysql:host=localhost;dbname=_shinkenchiku_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>データベーステーブル一覧</h2>";
    
    // 1. 全てのテーブルを取得
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>利用可能なテーブル:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
    
    // 2. architect関連のテーブルを検索
    echo "<h3>architect関連のテーブル:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        if (strpos($table, 'architect') !== false) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
    }
    echo "</ul>";
    
    // 3. building関連のテーブルを検索
    echo "<h3>building関連のテーブル:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        if (strpos($table, 'building') !== false) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
    }
    echo "</ul>";
    
    // 4. 各テーブルの構造を確認
    if (in_array('buildings_table_2', $tables)) {
        echo "<h3>buildings_table_2 の構造:</h3>";
        $stmt = $pdo->prepare("DESCRIBE buildings_table_2");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<h2>エラー:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>

