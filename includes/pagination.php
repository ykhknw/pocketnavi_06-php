<!-- Pagination -->
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <?php if ($currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link" 
                   href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>"
                   aria-label="Previous">
                    <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                </a>
            </li>
        <?php endif; ?>
        
        <?php 
        $paginationRange = getPaginationRange($currentPage, $totalPages);
        // デバッグ情報を追加
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<!-- Debug: currentPage = $currentPage, totalPages = $totalPages -->";
            echo "<!-- Debug: paginationRange = " . implode(', ', $paginationRange) . " -->";
        }
        
        $prevPage = 0;
        foreach ($paginationRange as $index => $pageNum): 
            // 型を統一して比較（文字列と数値の比較問題を回避）
            $isActive = (int)$pageNum === (int)$currentPage;
            
            // 前のページとの間にギャップがある場合は「...」を表示
            if ($pageNum - $prevPage > 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            // デバッグ情報を追加
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<!-- Debug: pageNum = $pageNum (type: " . gettype($pageNum) . "), currentPage = $currentPage (type: " . gettype($currentPage) . "), isActive = " . ($isActive ? 'true' : 'false') . " -->";
            }
        ?>
            <li class="page-item <?php echo $isActive ? 'active' : ''; ?>">
                <?php if ($isActive): ?>
                    <span class="page-link active-page" aria-current="page">
                        <?php echo $pageNum; ?>
                    </span>
                <?php else: ?>
                    <a class="page-link" 
                       href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pageNum])); ?>"
                       aria-label="Page <?php echo $pageNum; ?>">
                        <?php echo $pageNum; ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php 
            $prevPage = $pageNum;
        endforeach; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" 
                   href="/index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>"
                   aria-label="Next">
                    <i data-lucide="arrow-right" style="width: 16px; height: 16px;"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<div class="text-center text-muted mt-2">
    <?php echo $lang === 'ja' ? 'ページ' : 'Page'; ?> <?php echo $currentPage; ?> / <?php echo $totalPages; ?>
    (<?php echo $totalBuildings; ?> <?php echo $lang === 'ja' ? '件' : 'items'; ?>)
</div>

