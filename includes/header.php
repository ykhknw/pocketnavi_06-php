<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/index.php?lang=<?php echo $lang; ?>">
            <i data-lucide="landmark" class="me-2" style="width: 32px; height: 32px;"></i>
            <span class="fw-bold">PocketNavi</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php?lang=<?php echo $lang; ?>">
                        <?php echo $lang === 'ja' ? 'ホーム' : 'Home'; ?>
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link language-switch d-flex align-items-center" href="#" id="languageSwitch" role="button">
                        <i data-lucide="globe" class="me-1" style="width: 16px; height: 16px;"></i>
                        <span class="language-text"><?php echo $lang === 'ja' ? 'JA' : 'EN'; ?></span>
                        <i data-lucide="arrow-right-left" class="ms-1 language-arrow" style="width: 14px; height: 14px;"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
