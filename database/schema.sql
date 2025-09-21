-- PocketNavi データベーススキーマ
-- MySQL用のテーブル定義

-- 建築物メインテーブル
CREATE TABLE buildings_table_2 (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(255) UNIQUE,
    slug VARCHAR(500),
    title VARCHAR(500) NOT NULL,
    titleEn VARCHAR(500),
    thumbnailUrl TEXT,
    youtubeUrl TEXT,
    completionYears SMALLINT,
    parentBuildingTypes TEXT,
    buildingTypes TEXT,
    parentStructures TEXT,
    structures TEXT,
    prefectures VARCHAR(100),
    prefecturesEn VARCHAR(100),
    areas VARCHAR(100),
    location TEXT,
    locationEn TEXT,
    buildingTypesEn TEXT,
    architectDetails TEXT,
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 制約
    CONSTRAINT chk_completion_years CHECK (completionYears >= 1800 AND completionYears <= 2030),
    CONSTRAINT chk_coordinates CHECK (lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180)
);

-- 個別建築家テーブル
CREATE TABLE individual_architects_3 (
    individual_architect_id INT AUTO_INCREMENT PRIMARY KEY,
    name_ja VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    slug VARCHAR(255) UNIQUE NOT NULL,
    
    -- 制約
    CONSTRAINT chk_individual_architect_names CHECK (name_ja != '' OR name_en != ''),
    CONSTRAINT chk_slug_format CHECK (slug REGEXP '^[a-z0-9-]+$')
);

-- 建築家グループテーブル
CREATE TABLE architects_table (
    architect_id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 建築家構成テーブル
CREATE TABLE architect_compositions_2 (
    composition_id INT AUTO_INCREMENT PRIMARY KEY,
    architect_id INT NOT NULL,
    individual_architect_id INT NOT NULL,
    order_index INT DEFAULT 0,
    
    -- 制約
    CONSTRAINT chk_order_index CHECK (order_index >= 0),
    UNIQUE(architect_id, individual_architect_id),
    FOREIGN KEY (architect_id) REFERENCES architects_table(architect_id) ON DELETE CASCADE,
    FOREIGN KEY (individual_architect_id) REFERENCES individual_architects_3(individual_architect_id) ON DELETE CASCADE
);

-- 建築物-建築家関連テーブル
CREATE TABLE building_architects (
    building_id INT NOT NULL,
    architect_id INT NOT NULL,
    architect_order INT DEFAULT 0,
    
    PRIMARY KEY (building_id, architect_id),
    CONSTRAINT chk_architect_order CHECK (architect_order >= 0),
    FOREIGN KEY (building_id) REFERENCES buildings_table_2(building_id) ON DELETE CASCADE,
    FOREIGN KEY (architect_id) REFERENCES architects_table(architect_id) ON DELETE CASCADE
);

-- 建築家ウェブサイトテーブル（現在は使用されていません）
-- individual_architects_3テーブルにwebsite関連のカラムが含まれています

-- 写真テーブル
CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    url TEXT NOT NULL,
    thumbnail_url TEXT NOT NULL,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 制約
    CONSTRAINT chk_url_format CHECK (url REGEXP '^https?://'),
    CONSTRAINT chk_thumbnail_url_format CHECK (thumbnail_url REGEXP '^https?://'),
    CONSTRAINT chk_likes CHECK (likes >= 0),
    FOREIGN KEY (building_id) REFERENCES buildings_table_2(building_id) ON DELETE CASCADE
);

-- ユーザーテーブル（将来の拡張用）
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- 制約
    CONSTRAINT chk_email_format CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$')
);

-- インデックスの作成
-- 検索パフォーマンス向上のためのインデックス
CREATE INDEX idx_buildings_location ON buildings_table_2(lat, lng);
CREATE INDEX idx_buildings_title ON buildings_table_2(title);
CREATE INDEX idx_buildings_title_en ON buildings_table_2(titleEn);
CREATE INDEX idx_buildings_building_types ON buildings_table_2(buildingTypes);
CREATE INDEX idx_buildings_building_types_en ON buildings_table_2(buildingTypesEn);
CREATE INDEX idx_buildings_location_en ON buildings_table_2(locationEn);
CREATE INDEX idx_buildings_architect_details ON buildings_table_2(architectDetails);
CREATE INDEX idx_buildings_completion_year ON buildings_table_2(completionYears);
CREATE INDEX idx_buildings_prefectures ON buildings_table_2(prefectures);
CREATE INDEX idx_buildings_areas ON buildings_table_2(areas);
CREATE INDEX idx_buildings_slug ON buildings_table_2(slug);

-- 建築家関連インデックス
CREATE INDEX idx_individual_architects_slug ON individual_architects_3(slug);
CREATE INDEX idx_individual_architects_name_ja ON individual_architects_3(name_ja);
CREATE INDEX idx_individual_architects_name_en ON individual_architects_3(name_en);

-- 関連テーブルインデックス
CREATE INDEX idx_building_architects_building ON building_architects(building_id);
CREATE INDEX idx_building_architects_architect ON building_architects(architect_id);
CREATE INDEX idx_architect_compositions_architect ON architect_compositions_2(architect_id);
CREATE INDEX idx_architect_compositions_individual ON architect_compositions_2(individual_architect_id);
-- architect_websites_3テーブルは存在しないため、インデックスも不要

-- 複合インデックス
CREATE INDEX idx_buildings_search ON buildings_table_2(prefectures, completionYears, lat, lng);
CREATE INDEX idx_buildings_type_location ON buildings_table_2(buildingTypes, lat, lng);
CREATE INDEX idx_building_architects_composite ON building_architects(building_id, architect_id);
CREATE INDEX idx_architect_compositions_composite ON architect_compositions_2(architect_id, individual_architect_id);

-- 全文検索インデックス（横断検索対応）
-- MySQL 5.7以降でサポート
-- CREATE FULLTEXT INDEX idx_buildings_fulltext_ja ON buildings_table_2(title, buildingTypes, location, architectDetails);
-- CREATE FULLTEXT INDEX idx_buildings_fulltext_en ON buildings_table_2(titleEn, buildingTypesEn, locationEn);
-- CREATE FULLTEXT INDEX idx_individual_architects_fulltext ON individual_architects_3(name_ja, name_en);

