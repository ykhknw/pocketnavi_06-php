<!-- Sidebar -->
<div class="sticky-top" style="top: 20px;">
    <!-- Map -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <div id="map" style="height: 400px; width: 100%;"></div>
        </div>
        
        <!-- Map Action Buttons (only shown when building_slug is specified) -->
        <?php if ($currentBuilding && $currentBuilding['lat'] && $currentBuilding['lng']): ?>
            <div class="card-footer p-3">
                <div class="d-grid gap-2">
                    <!-- 付近を検索 -->
                    <button type="button" 
                            class="btn btn-outline-success btn-sm"
                            onclick="searchNearby(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="map-pinned" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? '付近を検索' : 'Search Nearby'; ?>
                    </button>
                    
                    <!-- 経路を検索 -->
                    <button type="button" 
                            class="btn btn-outline-warning btn-sm"
                            onclick="getDirections(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="route" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? '経路を検索' : 'Get Directions'; ?>
                    </button>
                    
                    <!-- グーグルマップで見る -->
                    <button type="button" 
                            class="btn btn-outline-info btn-sm"
                            onclick="viewOnGoogleMaps(<?php echo $currentBuilding['lat']; ?>, <?php echo $currentBuilding['lng']; ?>)">
                        <i data-lucide="external-link" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo $lang === 'ja' ? 'グーグルマップで見る' : 'View on Google Maps'; ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Architect Related Site (only shown when architects_slug is specified) -->
    <?php if (isset($architectInfo) && $architectInfo && !empty($architectInfo['individual_website'])): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">
                    <i data-lucide="square-mouse-pointer" class="me-2" style="width: 16px; height: 16px;"></i>
                    <?php echo $lang === 'ja' ? '関連サイト' : 'Useful Links'; ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <a href="<?php echo htmlspecialchars($architectInfo['individual_website']); ?>" 
                   target="_blank" 
                   class="text-decoration-none d-block position-relative overflow-hidden"
                   style="transition: all 0.3s ease;"
                   onmouseover="this.style.transform='scale(1.02)'"
                   onmouseout="this.style.transform='scale(1)'">
                    
                    <!-- スクリーンショット画像 -->
                    <div class="position-relative">
                        <img src="https://kenchikuka.com/screen_shots_3_webp/shot_<?php echo $architectInfo['individual_architect_id']; ?>.webp" 
                             alt="<?php echo htmlspecialchars($architectInfo['website_title'] ?? $architectInfo['name_ja']); ?>"
                             class="img-fluid w-100"
                             style="height: 200px; object-fit: cover; transition: all 0.3s ease;"
                             onerror="this.style.display='none'">
                        
                        <!-- オーバーレイ効果 -->
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                             style="background: rgba(0,0,0,0.3); opacity: 0; transition: opacity 0.3s ease;">
                            <div class="text-center text-white">
                                <i data-lucide="external-link" style="width: 32px; height: 32px; margin-bottom: 8px;"></i>
                                <div class="fw-bold"><?php echo $lang === 'ja' ? 'サイトを見る' : 'Visit Site'; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- タイトルとURL -->
                    <div class="p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <h5 class="mb-2 text-dark fw-bold" style="font-size: 1.1rem; line-height: 1.3;">
                            <?php echo htmlspecialchars($architectInfo['website_title'] ?? $architectInfo['name_ja']); ?>
                        </h5>
                        <div class="d-flex align-items-center">
                            <i data-lucide="globe" class="me-2 text-primary" style="width: 14px; height: 14px;"></i>
                            <small class="text-muted text-truncate">
                                <?php echo htmlspecialchars($architectInfo['individual_website']); ?>
                            </small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <style>
            .card:hover {
                box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
            }
            .card a:hover .position-absolute {
                opacity: 1 !important;
            }
            .card a:hover img {
                transform: scale(1.05);
            }
        </style>
    <?php endif; ?>
    
    <!-- Popular Searches -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i data-lucide="trending-up" class="me-2" style="width: 16px; height: 16px;"></i>
                <?php echo t('popularSearches', $lang); ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($popularSearches)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($popularSearches as $search): ?>
                        <a href="/index.php?q=<?php echo urlencode($search['query']); ?>&lang=<?php echo $lang; ?>" 
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

