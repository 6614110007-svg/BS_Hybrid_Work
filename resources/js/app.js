import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

window.deskForm = function (desksByZone, selectedZone) {
    return {
        desksByZone,
        selectedZone,
        init() {
            if (selectedZone) this.render(selectedZone);
        },
        onZoneChange(value) {
            this.selectedZone = value ? Number(value) : null;
            if (this.selectedZone) this.render(this.selectedZone);
        },
        render(zoneId) {
            const cell = 52;
            const size = 44;
            const desks = this.desksByZone[zoneId] || [];
            const maxX = Math.max(...desks.map((d) => d.x), 1);
            const maxY = Math.max(...desks.map((d) => d.y), 1);
            const w = Math.max(maxX * cell, 260);
            const h = Math.max(maxY * cell, 120);

            const el = document.getElementById('map-preview');
            el.style.width = w + 'px';
            el.style.height = h + 'px';
            el.innerHTML = '';

            desks.forEach((d) => {
                let style = 'background:#fecaca;border:2.5px solid #ef4444;';
                if (!d.is_active) {
                    style = 'background:#f3f4f6;border:1px dashed #9ca3af;';
                } else if (d.is_maintenance) {
                    style = 'background:#fef3c7;border:1.5px solid #f59e0b;';
                } else {
                    style = 'background:#eef2ff;border:1.5px solid #6366f1;';
                }
                const div = document.createElement('div');
                div.className = 'absolute flex items-center justify-center rounded-md text-[10px] font-semibold text-center shadow-sm';
                div.style.cssText = `${style} left:${(d.x - 1) * cell}px; top:${(d.y - 1) * cell}px; width:${size}px; height:${size}px;`;
                div.textContent = d.code;
                el.appendChild(div);
            });

            if (desks.length === 0) {
                el.innerHTML = '<p class="absolute inset-0 flex items-center justify-center text-xs text-gray-400">ยังไม่มีโต๊ะในโซนนี้</p>';
            }
        },
    };
};

window.seatMap = function (init) {
    return {
        date: init.date,
        slotId: init.slotId,
        minDate: init.minDate,
        maxDate: init.maxDate,
        slots: init.slots || [],
        zones: init.zones || [],
        statuses: init.statuses || {},
        activeBookings: init.activeBookings || [],
        notifications: init.notifications || [],
        unreadCount: init.unreadCount || 0,
        alert: init.alert || {},
        zoneFilter: 'all',
        loading: false,
        selectedDesk: null,
        confirmOpen: false,
        submitting: false,
        bookError: '',
        pollTimer: null,

        csrf() {
            return document.head.querySelector('meta[name=csrf-token]').content;
        },

        init() {
            this.refresh();
            this.pollTimer = setInterval(() => this.refresh(), 15000);
        },

        deskList(zoneId) {
            const z = this.zones.find((z) => z.id === zoneId);
            return z ? z.desks : [];
        },

        statusOf(desk) {
            return this.statuses[desk.id] || { state: 'available' };
        },

        visibleZones() {
            if (this.zoneFilter === 'all') return this.zones;
            return this.zones.filter((z) => z.id === this.zoneFilter);
        },

        slotName(id) {
            const s = this.slots.find((s) => s.id === id);
            return s ? s.name : '';
        },

        chipClass(state) {
            switch (state) {
                case 'available':
                    return 'bg-emerald-500 text-white hover:bg-emerald-600 cursor-pointer';
                case 'my_booked':
                    return 'bg-indigo-600 text-white ring-2 ring-indigo-300 cursor-pointer';
                case 'my_in_use':
                    return 'bg-green-600 text-white ring-2 ring-green-300 cursor-pointer';
                case 'booked':
                    return 'bg-gray-200 text-gray-500 border border-gray-300 cursor-not-allowed';
                case 'in_use':
                    return 'bg-sky-600 text-white cursor-not-allowed';
                default:
                    return 'bg-amber-100 text-amber-700 border border-dashed border-amber-400 cursor-not-allowed';
            }
        },

        openBook(desk) {
            const s = this.statusOf(desk).state;
            if (s === 'available') {
                this.selectedDesk = desk;
                this.confirmOpen = true;
                this.bookError = '';
            } else if (s === 'my_booked') {
                this.goCheckin(desk);
            } else if (s === 'my_in_use') {
                this.checkoutDesk(desk);
            }
        },

        goCheckin(desk) {
            const b = this.activeBookings.find((b) => b.desk_id === desk.id);
            if (b) window.location.href = '/bookings/' + b.id + '/checkin';
        },

        async checkoutDesk(desk) {
            const b = this.activeBookings.find((b) => b.desk_id === desk.id);
            if (!b) return;
            if (!confirm('เช็คเอาต์จากโต๊ะนี้ตอนนี้ใช่หรือไม่?')) return;
            await fetch('/bookings/' + b.id + '/checkout', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            this.refresh();
        },

        closeBook() {
            this.confirmOpen = false;
            this.selectedDesk = null;
        },

        async confirmBook() {
            this.submitting = true;
            this.bookError = '';
            try {
                const fd = new FormData();
                fd.append('desk_id', this.selectedDesk.id);
                fd.append('booking_date', this.date);
                fd.append('time_slot_id', this.slotId);
                const r = await fetch('/bookings', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: fd,
                });
                if (r.ok) {
                    window.location.href = '/dashboard';
                    return;
                }
                let msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
                try {
                    const j = await r.json();
                    if (j.errors) msg = Object.values(j.errors)[0][0];
                } catch (e) {}
                this.bookError = msg;
                this.refresh();
            } catch (e) {
                this.bookError = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
            this.submitting = false;
        },

        async cancel(id) {
            if (!confirm('ยกเลิกการจองนี้?')) return;
            await fetch('/bookings/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            this.refresh();
        },

        async readAll() {
            await fetch('/notifications/read', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            this.unreadCount = 0;
            this.notifications.forEach((n) => { n.read = true; });
        },

        async refresh() {
            this.loading = true;
            try {
                const r = await fetch(`/seatmap/status?date=${this.date}&slot=${this.slotId}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!r.ok) return;
                const j = await r.json();
                const next = {};
                j.desks.forEach((d) => {
                    next[d.id] = { state: d.state, booking_id: d.booking_id, mine: d.mine };
                });
                this.statuses = next;
                const incoming = j.activeBookings || [];
                this.activeBookings = incoming.map((b) => {
                    const prev = this.activeBookings.find((p) => p.id === b.id) || {};
                    return { ...prev, ...b };
                });
            } catch (e) {}
            this.loading = false;
        },
    };
};

window.checkin = function (init) {
    return {
        action: init.action,
        back: init.back,
        deadline: new Date(init.deadline).getTime(),
        stream: null,
        streaming: false,
        cameraError: '',
        preview: '',
        fileBlob: null,
        uploading: false,
        submitError: '',
        countdown: '--:--',
        timer: null,

        csrf() {
            return document.head.querySelector('meta[name=csrf-token]').content;
        },

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            this.startCamera();
        },

        tick() {
            const diff = this.deadline - Date.now();
            if (diff <= 0) {
                this.countdown = 'หมดเวลาแล้ว';
                return;
            }
            const m = Math.floor(diff / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.countdown = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },

        async startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.cameraError = 'เบราว์เซอร์นี้ไม่รองรับการเรียกใช้กล้อง';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                this.streaming = true;
                this.$nextTick(() => {
                    const v = this.$refs.video;
                    if (v) {
                        v.srcObject = this.stream;
                        v.play().catch(() => {});
                    }
                });
            } catch (e) {
                this.cameraError = 'กรุณาอนุญาตให้ใช้กล้อง หรือเข้าผ่าน https / localhost';
            }
        },

        async capture() {
            const v = this.$refs.video;
            if (!v || !v.videoWidth) return;
            const canvas = document.createElement('canvas');
            canvas.width = v.videoWidth;
            canvas.height = v.videoHeight;
            canvas.getContext('2d').drawImage(v, 0, 0, canvas.width, canvas.height);
            this.fileBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            this.preview = canvas.toDataURL('image/jpeg', 0.85);
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.streaming = false;
        },

        retake() {
            this.preview = '';
            this.fileBlob = null;
            this.startCamera();
        },

        fromFile(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.fileBlob = file;
            this.preview = URL.createObjectURL(file);
        },

        async submit() {
            if (!this.fileBlob) return;
            this.uploading = true;
            this.submitError = '';
            const fd = new FormData();
            fd.append('photo', this.fileBlob, 'selfie.jpg');
            try {
                const r = await fetch(this.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: fd,
                });
                if (r.ok) {
                    window.location.href = this.back;
                    return;
                }
                let msg = 'อัปโหลดหรือเช็คอินไม่สำเร็จ กรุณาลองใหม่';
                try {
                    const j = await r.json();
                    if (j.errors) msg = Object.values(j.errors)[0][0];
                    else if (j.message) msg = j.message;
                } catch (e) {}
                this.submitError = msg;
            } catch (e) {
                this.submitError = 'เชื่อมต่อล้มเหลว กรุณาลองใหม่';
            }
            this.uploading = false;
        },
    };
};
window.adminRealtime = function (initial) {
    const statusBadge = {
        confirmed: ['รอเช็คอิน', 'bg-sky-100 text-sky-700'],
        checked_in: ['กำลังใช้งาน', 'bg-blue-100 text-blue-700'],
        checked_out: ['เช็คเอาต์แล้ว', 'bg-emerald-100 text-emerald-700'],
        cancelled: ['ถูกยกเลิก', 'bg-gray-100 text-gray-500'],
        expired: ['หมดอายุ (สาย)', 'bg-red-100 text-red-600'],
    };

    return {
        live: initial.live,
        zones: initial.zones,
        activity: initial.activity,
        serverTime: '',
        updating: false,
        error: false,
        timer: null,

        init() {
            this.timer = setInterval(() => this.refresh(), 10000);
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        },

        async refresh() {
            if (this.updating) return;
            this.updating = true;
            try {
                const res = await fetch('/admin/dashboard/realtime', {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                this.live = data.live;
                this.zones = data.zoneOccupancy;
                this.activity = data.recentActivity;
                this.serverTime = data.server_time;
                this.error = false;
            } catch (e) {
                this.error = true;
            } finally {
                this.updating = false;
            }
        },

        colorFor(pct) {
            if (pct >= 80) return 'bg-rose-500';
            if (pct >= 50) return 'bg-amber-500';
            return 'bg-emerald-500';
        },

        badgeFor(status) {
            return statusBadge[status] || ['', 'bg-gray-100 text-gray-500'];
        },

        timeAgo(iso) {
            if (!iso) return '';
            const then = new Date(iso).getTime();
            const secs = Math.max(0, Math.round((Date.now() - then) / 1000));
            if (secs < 60) return 'เมื่อสักครู่';
            if (secs < 3600) return `${Math.floor(secs / 60)} นาทีที่แล้ว`;
            if (secs < 86400) return `${Math.floor(secs / 3600)} ชั่วโมงที่แล้ว`;
            return new Date(iso).toLocaleDateString('th-TH');
        },
    };
};
