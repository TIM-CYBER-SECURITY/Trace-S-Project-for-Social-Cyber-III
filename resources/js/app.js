import './bootstrap';

// Theme initialization (Light institutional default with Dark persistence)
function applyTheme(theme) {
    const html = document.documentElement;
    if (theme === 'dark') {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
    localStorage.setItem('trace-theme', theme);
    document.querySelectorAll('[data-theme-label]').forEach((el) => {
        el.textContent = theme === 'dark' ? 'DARK' : 'LIGHT';
    });
}

window.toggleTraceTheme = function () {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    applyTheme(next);
};

// Apply persisted theme immediately
(function () {
    const saved = localStorage.getItem('trace-theme');
    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
    } else {
        applyTheme('light');
    }
})();

// UTC Clock updater: HH:mm:ss — DD MMM YYYY UTC
function updateUtcClock() {
    const el = document.getElementById('utc-clock');
    if (!el) return;
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
    el.textContent = `${pad(now.getUTCHours())}:${pad(now.getUTCMinutes())}:${pad(now.getUTCSeconds())} — ${pad(now.getUTCDate())} ${months[now.getUTCMonth()]} ${now.getUTCFullYear()} UTC`;
}

document.addEventListener('DOMContentLoaded', () => {
    updateUtcClock();
    setInterval(updateUtcClock, 1000);

    // Dropdown toggles
    document.querySelectorAll('[data-dropdown-btn]').forEach((btn) => {
        const targetId = btn.getAttribute('data-dropdown-btn');
        const menu = document.getElementById(targetId);
        if (!menu) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    });

    // Mobile sidebar toggle
    const sidebarBtn = document.getElementById('mobile-sidebar-btn');
    const sidebarEl = document.getElementById('app-sidebar');
    if (sidebarBtn && sidebarEl) {
        sidebarBtn.addEventListener('click', () => {
            sidebarEl.classList.toggle('hidden');
        });
    }

    // Accessible password visibility toggles
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const targetId = btn.getAttribute('data-password-toggle');
        const input = document.getElementById(targetId);
        if (!input) return;

        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            const label = show
                ? (btn.getAttribute('data-hide-label') || 'Hide password')
                : (btn.getAttribute('data-show-label') || 'Show password');
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
        });
    });
});
