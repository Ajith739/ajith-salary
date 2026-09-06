<?php
/**
 * Footer Template
 */
?>
            </div><!-- /.content-wrapper -->
        </main>
    </div><!-- /.app-wrapper -->
    
    <!-- ─── MOBILE BOTTOM NAV ─── -->
    <nav class="mobile-bottom-nav d-lg-none">
        <a href="dashboard.php" class="<?= PAGE_ID === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i><span>Home</span>
        </a>
        <a href="expenses.php" class="<?= PAGE_ID === 'expenses' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i><span>Expenses</span>
        </a>
        <a href="goals.php" class="<?= PAGE_ID === 'goals' ? 'active' : '' ?>">
            <i class="fas fa-bullseye"></i><span>Goals</span>
        </a>
        <a href="analytics.php" class="<?= PAGE_ID === 'analytics' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
        <a href="settings.php" class="<?= PAGE_ID === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i><span>Settings</span>
        </a>
    </nav>

    <!-- ─── MODAL TEMPLATE ─── -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-container" id="modalContainer">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Modal</h3>
                <button class="modal-close" id="modalClose" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <!-- ─── TOAST NOTIFICATIONS ─── -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- App JS -->
    <script src="assets/js/app.js"></script>
    <?php if (PAGE_ID === 'dashboard'): ?>
    <script src="assets/js/three-bg.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <?php endif; ?>
    <script src="assets/js/charts.js"></script>
    <script src="assets/js/animations.js"></script>
</body>
</html>