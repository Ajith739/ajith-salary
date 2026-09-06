/**
 * Core Application Controller
 * Handles sidebar toggling, theme switching, modals, toasts, and AJAX
 */

(function() {
    'use strict';

    // ─── 1. SIDEBAR TOGGLING (MOBILE) ───
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarClose = document.getElementById('sidebarClose');

    if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
        });
    }

    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('open');
        });
    }

    // Close sidebar on outside click on mobile
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && (!hamburgerBtn || !hamburgerBtn.contains(e.target))) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ─── 2. THEME TOGGLING (DARK / LIGHT) ───
    const themeToggleBtn = document.getElementById('themeToggle');
    
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.className = document.body.className.replace(/theme-(dark|light)/g, '') + ' theme-' + theme;
        localStorage.setItem('myfinance_theme', theme);
        
        if (themeToggleBtn) {
            const icon = themeToggleBtn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
    }

    // Initialize theme from storage if set
    const savedTheme = localStorage.getItem('myfinance_theme');
    if (savedTheme) {
        setTheme(savedTheme);
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(nextTheme);

            // Notify server of theme update
            fetchWithCsrf('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_theme', theme: nextTheme })
            }).catch(() => {});
        });
    }

    // ─── 3. MODAL MANAGEMENT ───
    const modalOverlay = document.getElementById('modalOverlay');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');

    window.openModal = function(title, content) {
        if (!modalOverlay || !modalTitle || !modalBody) return;
        modalTitle.textContent = title;
        if (typeof content === 'string') {
            modalBody.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            modalBody.innerHTML = '';
            modalBody.appendChild(content);
        }
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function() {
        if (!modalOverlay) return;
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (modalClose) {
        modalClose.addEventListener('click', window.closeModal);
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) window.closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
            window.closeModal();
        }
    });

    // ─── 4. TOAST NOTIFICATIONS ───
    const toastContainer = document.getElementById('toastContainer');

    window.showToast = function(message, type = 'info') {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;

        const iconMap = {
            success: 'fa-check-circle',
            danger: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const iconClass = iconMap[type] || 'fa-info-circle';
        toast.innerHTML = `<i class="fas ${iconClass}"></i><span>${message}</span>`;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'all 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(15px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    };

    // ─── 5. CSRF FETCH HELPER ───
    window.fetchWithCsrf = function(url, options = {}) {
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        const token = metaCsrf ? metaCsrf.getAttribute('content') : '';

        options.headers = options.headers || {};
        if (typeof options.headers.set === 'function') {
            options.headers.set('X-CSRF-TOKEN', token);
            options.headers.set('X-Requested-With', 'XMLHttpRequest');
        } else {
            options.headers['X-CSRF-TOKEN'] = token;
            options.headers['X-Requested-With'] = 'XMLHttpRequest';
        }

        return fetch(url, options);
    };

    // ─── 6. CONFIRM DELETE HELPER ───
    window.confirmDelete = function(message, onConfirm) {
        const html = `
            <div style="text-align: center; padding: 1rem 0;">
                <div style="font-size: 2.5rem; color: var(--danger); margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <p style="font-size: 1.05rem; margin-bottom: 1.5rem; color: var(--text-primary);">${message}</p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button class="btn-secondary-custom" onclick="window.closeModal()">Cancel</button>
                    <button class="btn-danger-custom" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        `;
        window.openModal('Confirm Action', html);
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                window.closeModal();
                if (typeof onConfirm === 'function') onConfirm();
            });
        }
    };
})();
