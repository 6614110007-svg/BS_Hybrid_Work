import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/**
 * อัปเดตสถานะโต๊ะบนหน้า seat map แบบ real-time (polling ทุก 30 วินาที)
 * ข้อมูลสถานะมาจาก GET /seatmap/status
 */
function startSeatMapLiveUpdates() {
    const url = window.SEATMAP_STATUS_URL;
    const meta = window.SEATMAP_META ?? {};

    if (!url) {
        return;
    }

    const params = new URLSearchParams(window.location.search);

    const apply = (payload) => {
        (payload.desks ?? []).forEach((desk) => {
            const card = document.querySelector(`[data-desk-id="${CSS.escape(desk.desk_id)}"]`);

            if (!card) {
                return;
            }

            card.dataset.state = desk.state;

            const label = card.querySelector('[data-state-label]');
            const fallback = meta[desk.state];

            if (label && fallback) {
                label.textContent = fallback.label;
            }
        });
    };

    const poll = async () => {
        try {
            const response = await fetch(`${url}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                apply(await response.json());
            }
        } catch (error) {
            // เงียบไว้ ไม่รบกวนผู้ใช้เมื่อเครือข่ายมีปัญหา
        }
    };

    window.setInterval(poll, 30000);
}

/**
 * อัปเดตตัวเลขสถิติบนแดชบอร์ดผู้ดูแลระบบ (polling ทุก 60 วินาที)
 * ข้อมูลมาจาก GET /admin/dashboard/realtime
 */
function startAdminRealtime() {
    const url = window.ADMIN_REALTIME_URL;

    if (!url) {
        return;
    }

    const poll = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const live = payload.live ?? {};

            Object.entries(live).forEach(([key, value]) => {
                document
                    .querySelectorAll(`[data-live="${CSS.escape(key)}"]`)
                    .forEach((node) => {
                        node.textContent = value;
                    });
            });
        } catch (error) {
            // เงียบไว้เมื่อเครือข่ายมีปัญหา
        }
    };

    window.setInterval(poll, 60000);
}

document.addEventListener('DOMContentLoaded', () => {
    startSeatMapLiveUpdates();
    startAdminRealtime();
});
