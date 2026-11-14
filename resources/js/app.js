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

// Real-Time Location Clock updater with selectable timezone persisted in localStorage
const TRACE_TZ_LABELS = {
    'Asia/Jakarta': 'WIB',
    'Asia/Makassar': 'WITA',
    'Asia/Jayapura': 'WIT',
    'UTC': 'UTC',
};

function resolveSelectedTimezone() {
    const saved = localStorage.getItem('trace_timezone') || 'AUTO';
    if (saved === 'AUTO') {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Jakarta';
        } catch (e) {
            return 'Asia/Jakarta';
        }
    }
    return saved;
}

function updateUtcClock() {
    const el = document.getElementById('utc-clock');
    if (!el) return;
    const now = new Date();
    const tz = resolveSelectedTimezone();
    try {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: tz,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour12: false,
            timeZoneName: 'short',
        }).formatToParts(now);

        const get = (t) => (parts.find((p) => p.type === t)?.value || '');
        const tzTag = TRACE_TZ_LABELS[tz] || get('timeZoneName') || tz;
        el.textContent = `${get('hour')}:${get('minute')}:${get('second')} — ${get('day')} ${get('month').toUpperCase()} ${get('year')} ${tzTag}`;
    } catch (e) {
        const pad = (n) => String(n).padStart(2, '0');
        el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    }
}

async function pollLiveTelemetry() {
    const pill = document.getElementById('topbar-ai-pill');
    const url = pill?.getAttribute('data-telemetry-url');
    if (!url) return;

    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const json = await res.json();
        if (!json?.ok || !json.telemetry) return;

        const hw = json.telemetry;
        const ai = json.ai_status || {};
        const ramEl = document.getElementById('topbar-ai-ram');
        const stateEl = document.getElementById('topbar-ai-state');
        const dotEl = document.getElementById('topbar-ai-dot');

        if (ramEl) {
            const chunks = [];
            if (ai.ai_ram_used_gb !== null && ai.ai_ram_used_gb > 0) {
                chunks.push(`${Number(ai.ai_ram_used_gb).toFixed(2)} GB AI`);
            }
            if (hw.ram_free_gb !== null && hw.ram_total_gb !== null) {
                chunks.push(`FREE ${Number(hw.ram_free_gb).toFixed(1)}/${Number(hw.ram_total_gb).toFixed(0)} GB`);
            }
            ramEl.textContent = chunks.length ? ` • ${chunks.join(' · ')}` : '';
        }

        if (stateEl && ai.status) {
            stateEl.textContent = `(${ai.status === 'ONLINE' ? 'ACTIVE' : 'OFFLINE'})`;
        }
        if (dotEl && ai.status) {
            dotEl.classList.toggle('bg-[#688A4D]', ai.status === 'ONLINE');
            dotEl.classList.toggle('bg-[#991B1B]', ai.status !== 'ONLINE');
        }
    } catch (e) {
        // Silent fallback when endpoint unavailable
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const tzSelect = document.getElementById('trace-tz-select');
    if (tzSelect) {
        const savedTz = localStorage.getItem('trace_timezone') || 'AUTO';
        tzSelect.value = savedTz;
        tzSelect.addEventListener('change', () => {
            localStorage.setItem('trace_timezone', tzSelect.value);
            updateUtcClock();
        });
    }

    updateUtcClock();
    setInterval(updateUtcClock, 1000);

    pollLiveTelemetry();
    setInterval(pollLiveTelemetry, 5000);

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
