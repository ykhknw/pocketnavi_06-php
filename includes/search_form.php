<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <?php if ($prefectures): ?>
                <input type="hidden" name="prefectures" value="<?php echo htmlspecialchars($prefectures); ?>">
            <?php endif; ?>
            <?php if ($completionYears): ?>
                <input type="hidden" name="completionYears" value="<?php echo htmlspecialchars($completionYears); ?>">
            <?php endif; ?>
            
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           value="<?php echo htmlspecialchars($query); ?>"
                           placeholder="<?php echo t('searchPlaceholder', $lang); ?>">
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="button" 
                            class="btn btn-outline-primary" 
                            id="getLocationBtn"
                            onclick="getCurrentLocation()">
                        <i data-lucide="locate-fixed" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo t('currentLocation', $lang); ?>
                    </button>
                    
                    <button type="button" 
                            class="btn btn-outline-secondary" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#advancedSearch"
                            aria-expanded="false">
                        <i data-lucide="funnel" class="me-1" style="width: 16px; height: 16px;"></i>
                        <?php echo t('detailedSearch', $lang); ?>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Advanced Search -->
        <div class="collapse mt-3" id="advancedSearch">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><?php echo t('detailedSearch', $lang); ?></h6>
                    
                    <form method="GET" action="">
                        <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                        <?php if ($query): ?>
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                        <?php endif; ?>
                        <?php if ($prefectures): ?>
                            <input type="hidden" name="prefectures" value="<?php echo htmlspecialchars($prefectures); ?>">
                        <?php endif; ?>
                        <?php if ($completionYears): ?>
                            <input type="hidden" name="completionYears" value="<?php echo htmlspecialchars($completionYears); ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="photos" 
                                           id="hasPhotos"
                                           value="1"
                                           <?php echo $hasPhotos ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hasPhotos">
                                        <?php echo t('withPhotos', $lang); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="videos" 
                                           id="hasVideos"
                                           value="1"
                                           <?php echo $hasVideos ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hasVideos">
                                        <?php echo t('withVideos', $lang); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>
                                <?php echo t('search', $lang); ?>
                            </button>
                            
                            <a href="/index.php?lang=<?php echo $lang; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                <?php echo t('clearFilters', $lang); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

