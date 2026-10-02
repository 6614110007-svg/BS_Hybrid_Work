import Alpine from 'alpinejs';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/l10n/th.js';

window.Alpine = Alpine;

const WEEKEND_REASON = 'วันหยุดเสาร์-อาทิตย์';
const HOLIDAY_REASON = 'วันหยุดนักขัตฤกษ์';
const DATE_INPUT_ID = 'booking-date';
const FILTERS_FORM_SELECTOR = 'form[data-booking-filters]';

/**
 * ระยะเวลาขั้นต่ำที่สถานะ "กำลังโหลดผังโต๊ะ" จะคงอยู่ (มิลลิวินาที)
 * กันไม่ให้แถบจางกะพริบหายไปก่อนสายตาจะทันรับรู้
 */
const MIN_LOADING_MS = 180;

/**
 * ตัวช่วยหน้า seat map
 *
 *  - ปฏิทินเลือกวันที่ (Flatpickr) ปิดเสาร์-อาทิตย์และวันหยุดนักขัตฤกษ์ เลือกด้วยการคลิกวันที่
 *  - ปุ่มเลือกช่วงเวลาแบบ Segmented Control
 *  - เปลี่ยนวันที่/ช่วงเวลาแล้วค้นหาใหม่อัตโนมัติ เพื่อให้แผนผังโต๊ะตรงกับตัวเลือกเสมอ
 *  - วันนี้เกินเวลา cut-off แล้วจะปิดสล็อตที่หมดเวลา แล้วเลือกสล็อตที่ยังเหลือให้อัตโนมัติ
 */
function bookingFilters(config) {
    // เก็บ instance ของปฏิทินไว้ใน closure เพื่อไม่ให้ Alpine ทำให้ node ของ DOM เป็น reactive
    // และเข้าถึงได้จาก callback ของ Flatpickr ที่ไม่มี element context
    const runtime = { picker: null, timer: null, request: 0 };

    return {
        date: config.date,
        slot: config.slot,
        zoneId: config.zoneId ?? '',
        slots: config.slots ?? [],
        holidays: config.holidays ?? {},
        minDate: config.minDate,
        maxDate: config.maxDate,
        switchAfter: config.switchAfter ?? '12:00',
        loading: false,

        // ค่านี้ถูกอัปเดตทุก 30 วินาที เพื่อให้สถานะปุ่ม "หมดเวลาแล้ว" อัปเดตเอง
        // ตอนหน้าเว็บเปิดค้างไว้จนข้ามเวลา 12:00
        clock: 0,

        init() {
            const switched = this.resolveSlot();

            this.mountPicker();

            runtime.timer = window.setInterval(() => {
                this.clock = Date.now();
            }, 30000);

            if (switched) {
                this.search();
            }
        },

        destroy() {
            runtime.picker?.destroy();
            runtime.picker = null;
            window.clearInterval(runtime.timer);
        },

        /**
         * ช่อง input ของปฏิทิน — อ้างอิงจาก id ของ DOM ตรง ๆ
         * เพราะ x-ref ของ element ลูกไม่ใช่ของ element ที่มี x-data
         */
        dateInput() {
            return document.getElementById(DATE_INPUT_ID)
                ?? document.querySelector(`[x-ref="datePicker"]`);
        },

        filtersForm() {
            return document.querySelector(FILTERS_FORM_SELECTOR);
        },

        /**
         * ผูกปฏิทินกับช่อง "วันที่จอง"
         *
         * ครอบ try/catch ไว้ เพื่อไม่ให้ปัญหาของปฏิทินไปลาก Alpine ทั้งหน้า
         */
        mountPicker() {
            const input = this.dateInput();

            if (!input || runtime.picker) {
                return;
            }

            try {
                const picker = flatpickr(input, {
                    locale: flatpickr.l10ns.th,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'j F Y',
                    altInputClass: 'booking-picker__input',
                    clickOpens: true,
                    // เลือกได้ด้วยการคลิกวันที่ในปฏิทินเท่านั้น ไม่ให้พิมพ์วันที่เอง
                    allowInput: false,
                    minDate: this.minDate,
                    maxDate: this.maxDate,
                    appendTo: document.body,
                    position: 'auto',
                    // flatpickr บังคับให้ disable เป็น Array เสมอ (ข้างในเรียก arr.slice())
                    // ถ้าใส่เป็นฟังก์ชันตรง ๆ ปฏิทินจะพังตอนสร้าง
                    disable: [(day) => this.reasonForDate(day) !== null],
                    // flatpickr ส่ง day element มาให้ตัวเดียว วันที่อยู่ที่คุณสมบัติ dateObj
                    // flatpickr เรียก hook เป็น (selectedDates, inputValue, instance, data)
                    // ของ onDayCreate ตัว data คือ element ของวันนั้น และวันที่อยู่ที่ dateObj
                    onDayCreate: (_dates, _value, _picker, dayElement) => {
                        const date = dayElement?.dateObj;

                        if (date instanceof Date) {
                            this.markDay(dayElement, date);
                        }
                    },
                    onChange: (selected, value) => {
                        if (!value) {
                            return;
                        }

                        this.date = value;
                        this.resolveSlot();
                        this.search();
                    },
                });

                // Flatpickr ซ่อน input เดิมไว้แล้วสร้าง input ใหม่มาแสดงผล
                // ย้าย id ไปไว้ที่ช่องที่มองเห็นได้ เพื่อให้ label และ selector เดิมยังทำงาน
                input.id = `${DATE_INPUT_ID}-source`;

                if (picker.altInput) {
                    picker.altInput.id = DATE_INPUT_ID;
                }

                picker.setDate(this.date, false);

                runtime.picker = picker;
            } catch (error) {
                console.error('[booking] เปิดปฏิทินเลือกวันที่ไม่สำเร็จ', error);
            }
        },

        /**
         * เหตุผลที่เลือกวันนั้นไม่ได้ — null = จองได้
         */
        reasonForDate(day) {
            const weekday = day.getDay();

            if (weekday === 0 || weekday === 6) {
                return WEEKEND_REASON;
            }

            const name = this.holidays[this.toInputDate(day)];

            return name ? `${HOLIDAY_REASON} · ${name}` : null;
        },

        markDay(element, day) {
            const reason = this.reasonForDate(day);

            if (!reason) {
                return;
            }

            element.classList.add('booking-day__disabled');
            element.dataset.reason = reason;
            element.setAttribute('title', reason);
        },

        isSlotDisabled(slot) {
            // อ้างถึง clock เพื่อให้ Alpine คำนวณใหม่เมื่อเวลาข้ามเส้นตาย
            this.clock;

            if (this.date !== this.toInputDate(new Date())) {
                return false;
            }

            const cutoff = this.toMinutes(this.switchAfter);

            return this.toMinutes(slot.start) < cutoff && this.currentMinutes() > cutoff;
        },

        selectSlot(name) {
            const slot = this.slots.find((item) => item.name === name);

            if (!slot || this.isSlotDisabled(slot) || name === this.slot) {
                return;
            }

            this.slot = name;
            this.search();
        },

        /**
         * ถ้าสล็อตที่เลือกไว้หมดเวลาแล้ว (วันนี้เกิน cut-off) ให้เลือกสล็อตที่ยังเหลือแทน
         *
         * @return {boolean} มีการเปลี่ยนสล็อตหรือไม่
         */
        resolveSlot() {
            const current = this.slots.find((item) => item.name === this.slot);

            if (!current || !this.isSlotDisabled(current)) {
                return false;
            }

            const fallback = this.slots.find((item) => !this.isSlotDisabled(item));

            if (!fallback) {
                return false;
            }

            this.slot = fallback.name;

            return fallback.name !== current.name;
        },

        slotLabel(slot) {
            return `${slot.name} (${slot.start} - ${slot.end})`;
        },

        search() {
            const form = this.filtersForm();

            if (!form) {
                return;
            }

            // เขียนค่าจาก state ลง hidden field ก่อนส่งฟอร์ม เพื่อไม่ให้เร่งจน DOM ยังไม่อัปเดต
            const dateField = form.querySelector('input[name="date"]');
            const slotField = form.querySelector('input[name="time_slot"]');

            if (dateField) {
                dateField.value = this.date;
            }

            if (slotField) {
                slotField.value = this.slot;
            }

            // ถ้าเซิร์ฟเวอร์ไม่มี endpoint ส่วนย่อย (เช่น รัน artisan route:cache เก่า)
            // ให้ fallback ไป submit ฟอร์มตามเดิม
            if (!window.SEATMAP_PARTIAL_URL || typeof window.fetch !== 'function') {
                form.submit();

                return;
            }

            this.loadPartial();
        },

        /**
         * โหลดเฉพาะผังโต๊ะใหม่ผ่าน fetch แทนการโหลดทั้งหน้า
         *
         * ถ้าสล็อตที่เลือกหมดเวลาแล้ว (เช่นรอเช้าตอน 15:00) เซิร์ฟเวอร์จะส่งสล็อตที่แก้ไข
         * กลับมา จึงต้อง sync state ตามด้วยเสมอ
         */
        async loadPartial() {
            const region = document.querySelector('[data-ajax-region]');
            const token = ++runtime.request;
            const startedAt = Date.now();

            this.loading = true;

            try {
                const params = new URLSearchParams({
                    date: this.date,
                    time_slot: this.slot,
                    zone_id: this.zoneId ?? '',
                });

                const response = await fetch(`${window.SEATMAP_PARTIAL_URL}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`partial ${response.status}`);
                }

                const payload = await response.json();

                if (token !== runtime.request) {
                    return;
                }

                this.date = payload.date;
                this.slot = payload.time_slot;
                this.zoneId = payload.zone_id ?? '';

                if (region) {
                    region.innerHTML = payload.html;
                }

                runtime.picker?.setDate(this.date, false);

                const url = new URL(window.location.href);
                url.searchParams.set('date', this.date);
                url.searchParams.set('time_slot', this.slot);

                if (this.zoneId) {
                    url.searchParams.set('zone_id', this.zoneId);
                } else {
                    url.searchParams.delete('zone_id');
                }

                window.history.replaceState({}, '', url);
            } catch (error) {
                if (token !== runtime.request) {
                    return;
                }

                // โหลดส่วนย่อยไม่สำเร็จ — ย้อนกลับไปโหลดหน้าเต็มตามปกติ
                this.filtersForm()?.submit();
            } finally {
                // ถ้าเซิร์ฟเวอร์ตอบเร็วมาก สถานะจางจะกะพริบจนมองไม่ทัน
                // จึงหน่วงไว้อย่างน้อย 180ms ให้ผู้ใช้เห็นผลของการกด
                if (token === runtime.request) {
                    const elapsed = Date.now() - startedAt;

                    if (elapsed < MIN_LOADING_MS) {
                        await new Promise((resolve) => window.setTimeout(resolve, MIN_LOADING_MS - elapsed));
                    }

                    // ถ้าระหว่างรอมีคำขอใหม่ทับมา ปล่อยให้คำขอใหม่จัดการ loading เอง
                    if (token === runtime.request) {
                        this.loading = false;
                    }
                }
            }
        },

        toInputDate(day) {
            const month = `${day.getMonth() + 1}`.padStart(2, '0');
            const date = `${day.getDate()}`.padStart(2, '0');

            return `${day.getFullYear()}-${month}-${date}`;
        },

        toMinutes(time) {
            const [hour, minute] = `${time}`.split(':').map(Number);

            return (hour * 60) + minute;
        },

        currentMinutes() {
            const now = new Date();

            return (now.getHours() * 60) + now.getMinutes();
        },
    };
}

Alpine.data('bookingFilters', bookingFilters);

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

            const label = card.querySelector('[data-state-label]');
            const fallback = meta[desk.state];

            if (!fallback) {
                return;
            }

            card.dataset.state = desk.state;

            if (label) {
                label.textContent = fallback.label;
            }

            // จุดสถานะและสีการ์ดต้องเปลี่ยนตามสถานะล่าสุดด้วย ไม่ใช่แค่ข้อความ
            const dot = card.querySelector('[data-state-dot]');

            if (dot) {
                dot.classList.remove(...String(card.dataset.dotClass ?? '').split(' ').filter(Boolean));
                dot.classList.add(...fallback.dot.split(' ').filter(Boolean));
            }

            card.classList.remove(...String(card.dataset.cardClass ?? '').split(' ').filter(Boolean));
            card.classList.add(...fallback.card.split(' ').filter(Boolean));

            card.dataset.dotClass = fallback.dot;
            card.dataset.cardClass = fallback.card;
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

/**
 * แถบโหลดด้านบน + ป้องกันกดปุ่มซ้ำระหว่างที่ฟอร์มกำลังส่ง
 *
 * ทำงานร่วมกับทุกฟอร์มที่มี data-loading-form (จองโต๊ะ, เช็คอิน, ยกเลิก)
 * และปุ่มที่มี data-loading-button
 */
function startLoadingIndicator() {
    let bar = document.querySelector('.app-loading-bar');

    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'app-loading-bar';
        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-label', 'กำลังโหลด');
        document.body.appendChild(bar);
    }

    const setBusy = (form, busy) => {
        const buttons = form.querySelectorAll('[data-loading-button], button[type="submit"]');

        buttons.forEach((button) => {
            if (busy) {
                button.setAttribute('disabled', 'disabled');
                button.dataset.busy = 'true';

                // ปุ่มที่ถูก Alpine คุมไว้ (มี x-show/x-text) ให้ปล่อยให้ Alpine จัดการสปินเนอร์เอง
                if (!button.querySelector('[x-show], [x-text]')) {
                    button.dataset.label ??= button.innerHTML;
                    button.innerHTML = '<span class="app-spinner"></span><span>กำลังทำงาน…</span>';
                }
            } else {
                button.removeAttribute('disabled');
                delete button.dataset.busy;

                if (button.dataset.label) {
                    button.innerHTML = button.dataset.label;
                }
            }
        });
    };

    // ฟังเหตุการณ์ในช่วง bubble (ไม่ใช่ capture) เพื่อให้ confirm() และ Alpine
    // ที่ผูกกับฟอร์มได้ทำงานก่อน ไม่งั้นผู้ใช้ที่เลือก "ยกเลิก" ใน confirm
    // จะเหลือปุ่มที่ถูก disable ค้างจนกว่าจะรีเฟรชหน้า
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        // ฟอร์มถูกยกเลิกไปแล้ว (เช่น ผู้ใช้กด Cancel ใน confirm หรือ validate ไม่ผ่าน)
        if (event.defaultPrevented) {
            return;
        }

        // กดซ้ำระหว่างที่ฟอร์มกำลังส่ง
        if (form.dataset.busy === 'true') {
            event.preventDefault();

            return;
        }

        // ฟอร์มตัวกรองหน้า dashboard ใช้ fetch แทนการ submit จริง
        // ปุ่มและสปินเนอร์ของ Alpine จัดการเองทั้งหมด
        if (form.matches('[data-booking-filters]')) {
            return;
        }

        form.dataset.busy = 'true';
        bar.dataset.active = 'true';
        setBusy(form, true);
    });

    // ปุ่มที่อยู่นอกฟอร์มใช้ data-loading-button ป้องกันกดซ้ำ
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-loading-button]');

        if (button && !button.closest('form[data-busy="true"]')) {
            if (button.dataset.busy === 'true') {
                event.preventDefault();
                event.stopPropagation();

                return;
            }
        }

        // ลิงก์ภายในระบบ (เมนู/แท็บ) ให้แถบโหลดขึ้นทันทีระหว่างรอหน้าใหม่
        // ต้องตรวจทุกลิงก์ ไม่ใช่เฉพาะที่อยู่ในปุ่ม เพราะเมนูหลักไม่ได้ใส่ attribute นี้
        const link = event.target.closest?.('a[href]');

        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        try {
            const url = new URL(link.href, window.location.href);

            if (url.origin !== window.location.origin || url.hash) {
                return;
            }

            bar.dataset.active = 'true';
        } catch (error) {
            // ลิงก์ผิดรูปแบบ ปล่อยให้เบราว์เซอร์จัดการเอง
        }
    }, true);

    // กลับมาจากหน้าใน (bfcache) ต้องซ่อนแถบโหลดค้าง
    window.addEventListener('pageshow', () => {
        delete bar.dataset.active;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    startLoadingIndicator();
    startSeatMapLiveUpdates();
    startAdminRealtime();
});
