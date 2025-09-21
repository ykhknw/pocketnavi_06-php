<!-- Footer -->
<footer class="bg-light mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">PocketNavi</h6>
                <p class="text-muted small">
                    <?php echo $lang === 'ja' ? 
                        '日本の建築物を検索・閲覧できるWebアプリケーション' : 
                        'A web application for searching and browsing Japanese buildings'; ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted small mb-0">
                    &copy; <?php echo date('Y'); ?> PocketNavi. 
                    <?php echo $lang === 'ja' ? 'All rights reserved.' : 'All rights reserved.'; ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button -->
<button id="scrollToTopBtn" class="btn btn-primary position-fixed d-flex align-items-center justify-content-center" style="
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: none !important;
    z-index: 1000;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    padding: 0;
    border: none;
">
    <i data-lucide="arrow-up-to-line" style="width: 20px; height: 20px;"></i>
</button>

<script>
// Scroll to Top Button functionality
document.addEventListener('DOMContentLoaded', function() {
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');
    
    // Function to check if scrolling is needed
    function checkScrollNeeded() {
        // Check if page has scrollable content
        const hasScrollableContent = document.documentElement.scrollHeight > window.innerHeight;
        
        // Check if user has scrolled down enough
        const hasScrolledDown = window.pageYOffset > 300;
        
        // Debug information (remove in production)
        // console.log('Scroll Debug:', { scrollHeight: document.documentElement.scrollHeight, innerHeight: window.innerHeight, hasScrollableContent, pageYOffset: window.pageYOffset, hasScrolledDown, shouldShow: hasScrollableContent && hasScrolledDown });
        
        // Only show button if both conditions are met
        if (hasScrollableContent && hasScrolledDown) {
            scrollToTopBtn.style.setProperty('display', 'flex', 'important');
        } else {
            scrollToTopBtn.style.setProperty('display', 'none', 'important');
        }
    }
    
    // Check on page load
    checkScrollNeeded();
    
    // Check on scroll
    window.addEventListener('scroll', checkScrollNeeded);
    
    // Check on window resize (in case content changes)
    window.addEventListener('resize', checkScrollNeeded);
    
    // Smooth scroll to top when button is clicked
    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Initialize Lucide icons for the button
    lucide.createIcons();
});
</script>

