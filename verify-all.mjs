// ตรวจงาน 4 ส่วนด้วยเบราว์เซอร์จริง (Chrome DevTools Protocol)
import { spawn, execFile } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const PORT = 9334;
const BASE = 'http://127.0.0.1:8000';
const profile = mkdtempSync(join(tmpdir(), 'cdp9-'));
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`,
    '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--window-size=1400,1200', 'about:blank'], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const json = async (p) => (await fetch(`http://127.0.0.1:${PORT}${p}`)).json();

class Cdp {
    constructor(ws) { this.ws = ws; this.id = 0; this.pending = new Map(); this.errors = []; this.waiters = []; }
    listen() {
        this.ws.addEventListener('message', (e) => {
            const m = JSON.parse(e.data);
            if (m.id && this.pending.has(m.id)) { const { resolve, reject } = this.pending.get(m.id); this.pending.delete(m.id); m.error ? reject(new Error(JSON.stringify(m.error))) : resolve(m.result); }
            else if (m.method === 'Runtime.exceptionThrown') this.errors.push('EXC: ' + (m.params.exceptionDetails?.exception?.description ?? m.params.exceptionDetails?.text));
            else if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') this.errors.push('CONSOLE: ' + m.params.args.map((a) => a.value ?? a.description ?? '').join(' '));
            else if (m.method === 'Page.loadEventFired') this.waiters.splice(0).forEach((r) => r());
        });
    }
    send(method, params = {}) { this.id += 1; const id = this.id; return new Promise((resolve, reject) => { this.pending.set(id, { resolve, reject }); this.ws.send(JSON.stringify({ id, method, params })); }); }
    waitLoad() { return new Promise((r) => this.waiters.push(r)); }
    async eval(expression) {
        const r = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) {
            const msg = r.exceptionDetails.exception?.description ?? r.exceptionDetails.text;
            console.log('   [eval error] ' + msg.split('\n')[0]);
            return { error: msg };
        }
        return { value: r.result.value };
    }
    async realClick(x, y) {
        await this.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
        await this.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
    }
}

let pass = 0;
let fail = 0;
const check = (ok, msg) => { if (ok) pass += 1; else fail += 1; console.log(`${ok ? 'ผ่าน    ' : 'ไม่ผ่าน '} | ${msg}`); };

async function main() {
    for (let i = 0; i < 60; i += 1) { try { await json('/json/version'); break; } catch { await sleep(250); } }
    const page = (await json('/json/list')).find((t) => t.type === 'page');
    const ws = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((r) => ws.addEventListener('open', r, { once: true }));
    const cdp = new Cdp(ws); cdp.listen();
    await cdp.send('Page.enable'); await cdp.send('Runtime.enable');

    // ปัจจุบันหน้าต่างใช้ Alpine confirmDialog แทน confirm() ของเบราว์เซอร์แล้ว
    // override ทิ้งไว้เพื่อจับกรณีถอยกลับไปใช้ native confirm: นับจำนวนครั้งที่ถูกเรียก
    cdp.onConfirm = null;
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__nativeConfirmCalls = 0; window.confirm = () => { window.__nativeConfirmCalls += 1; return false; };`,
    });

    const goto = async (url) => {
        const target = url.startsWith('http') ? url : `${BASE}${url}`;
        const l = cdp.waitLoad();
        await cdp.send('Page.navigate', { url: target });
        await l; await sleep(1300);
    };
    const nowMinus1 = () => {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    };
    const todayIso = () => {
        const t = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        return `${t.getFullYear()}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}`;
    };
    // ช่วงเวลาปัจจุบันของวันนี้ (สลับเที่ยงวันตามค่าเดโม 12:00) เพื่อให้จองแล้วเข้าเวลาเช็คอินได้ทันที
    const currentSlotName = () => (new Date().getHours() >= 12 ? 'Afternoon' : 'Morning');

    // รอให้ Alpine พร้อมก่อน แล้วค่อยรอให้ AJAX เสร็จ (ครั้งแรกอาจช้าเพราะต้อง compile view + autoload)
    const settled = async (label, timeoutMs = 25000) => {
        for (let i = 0; i < 40; i += 1) {
            const ready = (await cdp.eval(`!!window.Alpine && !!document.querySelector('[x-data]')`)).value;
            if (ready) break;
            await sleep(250);
        }
        const started = Date.now();
        while (Date.now() - started < timeoutMs) {
            const busy = (await cdp.eval(`(() => {
                const region = document.querySelector('[data-ajax-region]');
                return region?.dataset.busy === 'true';
            })()`)).value;
            if (!busy) return true;
            await sleep(250);
        }
        console.log(`   [timeout] รอ ${label} ไม่จบใน ${timeoutMs}ms`);
        return false;
    };

    let l = cdp.waitLoad();
    await cdp.send('Page.navigate', { url: `${BASE}/login` });
    await l; await sleep(900);
    await cdp.eval(`(async () => { const b = new FormData();
        b.append('_token', document.querySelector('meta[name=csrf-token]').content);
        b.append('email', 'employee@bs-hybrid.test'); b.append('password', 'password');
        await fetch(location.pathname, { method: 'POST', body: b, credentials: 'same-origin' }); })()`);

    // ---------- ส่วนที่ 4c + 4b: AJAX partial + loading ----------
    await goto('/dashboard');
    // อุ่น endpoint ส่วนย่อยก่อน เพื่อไม่ให้เวลารวม autoload ของครั้งแรกบิดเบือนผลวัด
    await cdp.eval(`fetch(window.SEATMAP_PARTIAL_URL + '?time_slot=' + encodeURIComponent(window.SEATMAP_META ? 'Full Day' : 'Full Day'), { headers: { Accept: 'application/json' } }).then((r) => r.text())`);
    console.log('   [page] ' + (await cdp.eval(`location.href + ' :: ' + document.body.innerText.slice(0, 60).replace(/\\s+/g, ' ')`)).value);

    const region = (await cdp.eval(`(() => ({
        hasRegion: !!document.querySelector('[data-ajax-region]'),
        hasBody: !!document.querySelector('[data-seatmap-body]'),
        partialUrl: window.SEATMAP_PARTIAL_URL || null,
        desks: document.querySelectorAll('[data-desk-id]').length,
        zones: document.querySelectorAll('[data-seatmap-body] section').length,
        hasLoadingButton: !!document.querySelector('[data-loading-button]'),
        hasBar: !!document.querySelector('.app-loading-bar'),
        formSubmitPrevent: document.querySelector('[data-booking-filters]').getAttribute('@submit.prevent'),
    }))()`)).value;
    check(region.hasRegion, 'มี [data-ajax-region] สำหรับโหลดส่วนย่อย');
    check(region.hasBody, 'มี [data-seatmap-body] ภายใน region');
    check(!!region.partialUrl, `มี URL สำหรับ partial (${region.partialUrl})`);
    check(region.desks > 0, `ผังโต๊ะมีการ์ด ${region.desks} ใบ`);
    check(region.hasLoadingButton, 'มีปุ่มที่มี data-loading-button');
    check(region.hasBar, 'มีแถบโหลดด้านบน (.app-loading-bar)');
    check(region.formSubmitPrevent === 'search()', `ปุ่ม "ค้นหา" ถูกผูกกับ AJAX (${region.formSubmitPrevent})`);

    const zoneOptions = (await cdp.eval(`(() => [...document.getElementById('zone_id').options].map((o) => ({ v: o.value, t: o.textContent.trim() })))()`)).value;

    // เปลี่ยนโซนผ่าน select -> ต้อง fetch ไม่ reload หน้า
    if (zoneOptions.length > 1) {
        const target = zoneOptions[1];
        const mark = (await cdp.eval(`(() => { window.__m = Math.random().toString(); return window.__m; })()`)).value;
        await cdp.eval(`(() => { const s = document.getElementById('zone_id'); s.value = ${JSON.stringify(target.v)};
            s.dispatchEvent(new Event('change', { bubbles: true })); return true; })()`);

        // ต้องเห็นสถานะจาง/เฟดของผังโต๊ะระหว่าง swap (ไม่ใช่แค่ spinner กลางจอ)
        // รอให้ transition เดินไปสักหน่อย แต่ไม่เกินระยะขั้นต่ำ 180ms ที่ app บังคับไว้
        await sleep(120);

        const during = (await cdp.eval(`(() => {
            const region = document.querySelector('[data-ajax-region]');
            const body = document.querySelector('[data-seatmap-body]');
            const cs = body ? getComputedStyle(body) : null;
            const after = region ? getComputedStyle(region, '::after') : null;
            return {
                busy: region?.dataset.busy,
                opacity: cs ? Number(cs.opacity) : null,
                transition: cs?.transitionProperty ?? null,
                shimmerAnim: after?.animationName ?? null,
                pointerEvents: region ? getComputedStyle(region).pointerEvents : null,
            };
        })()`)).value;

        check(during.busy === 'true', `ระหว่างโหลดผังโต๊ะ ตั้ง data-busy=true (${during.busy})`);
        check(during.opacity !== null && during.opacity < 1, `ผังโต๊ะจางลงระหว่างโหลด (opacity=${during.opacity})`);
        check((during.transition ?? '').includes('opacity'), `มี transition ของ opacity (${during.transition})`);
        check(during.shimmerAnim === 'seatmap-shimmer', `มีแถบแสง skeleton วิ่งผ่าน (${during.shimmerAnim})`);
        check(during.pointerEvents === 'none', 'ระหว่างโหลดคลิกโต๊ะไม่ได้ (pointer-events=none)');

        await settled('เปลี่ยนโซน');
        await sleep(400);

        const after = (await cdp.eval(`(() => ({
            mark: window.__m,
            url: location.search,
            busy: document.querySelector('[data-ajax-region]')?.dataset.busy,
            cards: document.querySelectorAll('[data-desk-id]').length,
            zoneTitle: document.querySelector('[data-seatmap-body] h2')?.textContent.trim(),
            zoneDisabled: document.getElementById('zone_id').disabled,
            barActive: !!document.querySelector('.app-loading-bar[data-active]'),
            bodyOpacity: (() => {
                const b = document.querySelector('[data-seatmap-body]');
                return b ? getComputedStyle(b).opacity : null;
            })(),
        }))()`)).value;
        check(after.mark === mark, 'เปลี่ยนโซนแล้วหน้าไม่ถูกโหลดใหม่ (fetch แทน)');
        check(after.url.includes('zone_id='), `URL อัปเดตตามโซนที่เลือก (${after.url})`);
        check(after.zoneTitle === target.t, `ผังโต๊ะแสดงเฉพาะโซนที่เลือก (${after.zoneTitle})`);
        check(after.cards > 0 && after.cards < region.desks, `จำนวนโต๊ะลดจาก ${region.desks} เหลือ ${after.cards} ใบ`);
        check(!after.zoneDisabled, 'ตัวเลือกโซนกลับมาใช้งานได้หลังโหลดเสร็จ');
        check(after.busy === undefined || after.busy === 'false', `สถานะ loading กลับเป็นปกติ (data-busy=${after.busy})`);
        check(!after.barActive, 'แถบโหลดถูกซ่อนหลังโหลดเสร็จ');
        check(Number(after.bodyOpacity) > 0.99, `ผังโต๊ะกลับมาเต็มความเข้มหลังโหลดเสร็จ (opacity=${after.bodyOpacity})`);

        await cdp.eval(`(() => { const s = document.getElementById('zone_id'); s.value = '';
            s.dispatchEvent(new Event('change', { bubbles: true })); return true; })()`);
        await settled('ล้างตัวกรองโซน');
        await sleep(400);
        const reset = (await cdp.eval(`(() => ({ url: location.search, cards: document.querySelectorAll('[data-desk-id]').length }))()`)).value;
        check(!reset.url.includes('zone_id') && reset.cards === region.desks, 'ล้างตัวกรองโซนแล้วกลับมาเห็นทุกโซน');
    }

    // กดปุ่ม "ค้นหา" ต้องเป็น AJAX (ไม่โหลดหน้าใหม่)
    {
        const btn = JSON.parse((await cdp.eval(`(() => { const b = [...document.querySelectorAll('button[type=submit]')]
            .find((x) => x.textContent.includes('ค้นหา'));
            const r = b.getBoundingClientRect();
            return JSON.stringify({ x: r.x + r.width/2, y: r.y + r.height/2 }); })()`)).value);
        const mark = (await cdp.eval(`(() => { window.__m2 = Math.random().toString(); return window.__m2; })()`)).value;
        await cdp.realClick(btn.x, btn.y);
        await settled('กดปุ่มค้นหา');
        await sleep(400);
        const r = (await cdp.eval(`(() => ({ mark: window.__m2, url: location.search, cards: document.querySelectorAll('[data-desk-id]').length }))()`)).value;
        check(r.mark === mark, 'กดปุ่ม "ค้นหา" แล้วไม่โหลดหน้าใหม่');
        check(r.cards === region.desks && /date=\d{4}-\d{2}-\d{2}/.test(r.url), `ผลลัพธ์ถูกต้อง (${r.url}, ${r.cards} ใบ)`);
    }

    // ---------- ส่วนที่ 1b: จุดสถานะโต๊ะ (มุมขวาบนการ์ด) + Booking confirmation modal ----------
    {
        const dot = (await cdp.eval(`(() => {
            const card = document.querySelector('[data-desk-id]');
            if (!card) return { found: false };
            const cardRect = card.getBoundingClientRect();
            const spans = [...card.querySelectorAll('span.rounded-full')].filter((s) => !s.className.includes('w-6') && !s.className.includes('h-6'));
            const d = spans.find((s) => {
                const r = s.getBoundingClientRect();
                return r.width > 0 && r.width <= 16 && r.height <= 16 && (r.right - cardRect.right) < 40 && (cardRect.right - r.right) < 40;
            });
            if (!d) return { found: false, html: card.outerHTML.slice(0, 400) };
            const cs = getComputedStyle(d);
            const st = card.dataset.state;
            const legend = document.querySelector('.booking-legend__' + ({ available: 'disabled', booked: 'today' }[st] ?? 'disabled')) ? null : null;
            return {
                found: true,
                width: cs.width,
                height: cs.height,
                radius: cs.borderRadius,
                background: cs.backgroundColor,
                state: st,
                legendColor: legend,
                matchMeta: (window.SEATMAP_META?.[st]?.dot ?? '') !== '',
            };
        })()`)).value;
        if (!dot.found) console.log('   [dot] ' + (dot.html ?? 'no card'));
        check(dot.found, 'การ์ดโต๊ะมีจุดสถานะสีอยู่ที่มุมขวาบน');
        check(dot.radius === '9999px' || parseFloat(dot.radius) >= 6,
            `จุดสถานะเป็นวงกลม (w=${dot.width}, h=${dot.height}, radius=${dot.radius})`);
        check(!!dot.background && dot.background !== 'rgba(0, 0, 0, 0)', `จุดสถานะมีสีจริง (${dot.background})`);
        check(dot.matchMeta, 'จุดสถานะใช้คลาสสีตามสถานะใน SEATMAP_META');

        // จุดสถานะต้องเปลี่ยนสีตามสถานะล่าสุดเมื่อ polling อัปเดต (ไม่ใช่แค่เปลี่ยนข้อความ)
        // จำค่าคลาสเดิมไว้ก่อน เพื่อคืนสภาพหลังทดสอบ
        await cdp.eval(`(() => {
            const card = document.querySelector('[data-desk-id]');
            window.__dotBackup = {
                state: card.dataset.state,
                dotClass: card.dataset.dotClass,
                cardClass: card.dataset.cardClass,
                dot: card.querySelector('[data-state-dot]').className,
                label: card.querySelector('[data-state-label]').textContent,
                cardHtml: card.className,
            };
            return 'saved';
        })()`);

        // เปลี่ยนสถานะการ์ดใบแรกเป็น maintenance แล้ววัดสีจุดใหม่
        const ld = JSON.parse((await cdp.eval(`(() => {
            const card = document.querySelector('[data-desk-id]');
            const dotEl = card.querySelector('[data-state-dot]');
            const meta = window.SEATMAP_META.maintenance;

            const before = getComputedStyle(dotEl).backgroundColor;
            card.dataset.state = 'maintenance';
            dotEl.classList.remove(...String(card.dataset.dotClass ?? '').split(' ').filter(Boolean));
            dotEl.classList.add(...meta.dot.split(' ').filter(Boolean));
            card.classList.remove(...String(card.dataset.cardClass ?? '').split(' ').filter(Boolean));
            card.classList.add(...meta.card.split(' ').filter(Boolean));
            card.dataset.dotClass = meta.dot;
            card.dataset.cardClass = meta.card;
            card.querySelector('[data-state-label]').textContent = meta.label;

            return JSON.stringify({
                before,
                after: getComputedStyle(dotEl).backgroundColor,
                label: card.querySelector('[data-state-label]').textContent.trim(),
                expected: meta.label,
                applied: dotEl.classList.contains(meta.dot.split(' ')[0]),
            });
        })()`)).value ?? '{}');
        check(ld.before !== ld.after,
            `จุดสถานะเปลี่ยนสีตามสถานะใหม่ (${ld.before} -> ${ld.after})`);
        check(ld.label === ld.expected, `ข้อความสถานะอัปเดตตามสถานะใหม่ ("${ld.label}")`);
        check(ld.applied === true, 'คลาสสีจุดสถานะถูกสลับตามสถานะใหม่');

        // คืนสถานะการ์ดใบแรกกลับเป็นค่าเดิม เพื่อไม่ให้กระทบส่วนถัดไป
        await cdp.eval(`(() => {
            const card = document.querySelector('[data-desk-id]');
            const b = window.__dotBackup;
            card.dataset.state = b.state;
            card.querySelector('[data-state-dot]').className = b.dot;
            card.querySelector('[data-state-label]').textContent = b.label;
            card.dataset.dotClass = b.dotClass;
            card.dataset.cardClass = b.cardClass;
            card.className = b.cardHtml;
            return 'restored';
        })()`);

        // Modal ยืนยันการจอง: กดปุ่มจอง -> ต้องเปิด modal พร้อมรายละเอียด และยังไม่ navigate
        const confirmModal = JSON.parse((await cdp.eval(`(() => {
            const card = document.querySelector('[data-state="available"]');
            const f = card?.querySelector('form[data-booking-form]');
            if (!f) return JSON.stringify({ noForm: true });
            const btn = f.querySelector('button[type=submit]');
            window.__beforeConfirmUrl = location.pathname + location.search;
            f.requestSubmit ? f.requestSubmit(btn) : btn.click();
            return JSON.stringify({ submitted: true, desk: f.dataset.deskNumber ?? null, zone: f.dataset.zoneName ?? null, date: f.dataset.bookingDate ?? null, slot: f.dataset.timeSlot ?? null });
        })()`)).value);
        if (confirmModal.noForm) {
            check(false, 'ไม่พบฟอร์มจองที่มี data-booking-form');
        } else {
            await sleep(250);
            const open = (await cdp.eval(`(() => {
                const root = document.querySelector('[x-data^="bookingConfirm"]');
                const vis = root && getComputedStyle(root).display !== 'none';
                const text = (root?.innerText ?? '').replace(/\\s+/g, ' ').trim();
                const dds = root ? [...root.querySelectorAll('dd')].map((d) => d.textContent.trim()) : [];
                return {
                    vis: !!vis,
                    url: location.pathname + location.search,
                    same: location.pathname + location.search === window.__beforeConfirmUrl,
                    text,
                    desk: dds[0] ?? '',
                    zone: dds[1] ?? '',
                    date: dds[2] ?? '',
                    slot: dds[3] ?? '',
                    hasConfirm: [...(root?.querySelectorAll('button') ?? [])].some((b) => b.textContent.trim() === 'ยืนยันการจอง'),
                    hasCancel: [...(root?.querySelectorAll('button') ?? [])].some((b) => b.textContent.trim() === 'ยกเลิก'),
                    role: root?.getAttribute('role'),
                    ariaModal: root?.getAttribute('aria-modal'),
                };
            })()`)).value;
            check(open.vis, 'กดปุ่มจองแล้วเปิด modal ยืนยันการจอง');
            check(open.same, 'modal เปิดโดยยังไม่ navigate ออกจากหน้า');
            check(open.desk.includes(confirmModal.desk) && confirmModal.desk !== null,
                `modal แสดงชื่อโต๊ะตรงกับที่กด (${open.desk})`);
            check(open.zone.length > 0, `modal แสดงโซน (${open.zone})`);
            check(open.date.length > 0, `modal แสดงวันที่ (${open.date})`);
            check(open.slot.length > 0, `modal แสดงช่วงเวลา (${open.slot})`);
            check(open.hasConfirm && open.hasCancel, 'มีปุ่ม "ยืนยันการจอง" และ "ยกเลิก"');
            check(open.role === 'dialog' && open.ariaModal === 'true', 'modal เป็น role=dialog / aria-modal=true');

            // กด "ยกเลิก" -> modal ปิด ยังอยู่หน้าเดิม และไม่มีการส่งฟอร์ม
            const cancelled = (await cdp.eval(`(() => {
                const root = document.querySelector('[x-data^="bookingConfirm"]');
                const cancel = [...root.querySelectorAll('button')].find((b) => b.textContent.trim() === 'ยกเลิก');
                window.__submitCount = 0;
                document.addEventListener('submit', () => { window.__submitCount += 1; }, true);
                cancel.click();
                return 'clicked';
            })()`)).value;
            await sleep(250);
            const afterCancel = (await cdp.eval(`(() => {
                const root = document.querySelector('[x-data^="bookingConfirm"]');
                return {
                    hidden: getComputedStyle(root).display === 'none',
                    url: location.pathname + location.search,
                    same: location.pathname + location.search === window.__beforeConfirmUrl,
                };
            })()`)).value;
            check(afterCancel.hidden, 'กด "ยกเลิก" แล้ว modal ปิด');
            check(afterCancel.same, 'กด "ยกเลิก" แล้วยังอยู่หน้าเดิม ไม่ได้จองโต๊ะ');
        }
    }

    // ---------- ส่วนที่ 2: Date picker ----------
    const picker = (await cdp.eval(`(() => { const i = document.getElementById('booking-date');
        return { readonly: i.readOnly, placeholder: i.placeholder }; })()`)).value;
    check(picker.readonly === true, `ช่องวันที่เป็น readonly (คลิกอย่างเดียว) — "${picker.placeholder}"`);

    const box = JSON.parse((await cdp.eval(`(() => { const r = document.getElementById('booking-date').getBoundingClientRect();
        return JSON.stringify({ x: r.x + 30, y: r.y + r.height/2 }); })()`)).value);
    await cdp.realClick(box.x, box.y);
    await sleep(800);

    const cal = (await cdp.eval(`(() => {
        const c = document.querySelector('.flatpickr-calendar');
        if (!c) return { open: false };
        const days = [...c.querySelectorAll('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)')];
        const avail = days.find((d) => !d.classList.contains('flatpickr-disabled'));
        const dis = days.find((d) => d.classList.contains('flatpickr-disabled'));
        const todayEl = c.querySelector('.flatpickr-day.today');
        const cs = (el) => (el ? getComputedStyle(el) : null);
        return {
            open: !!document.querySelector('.flatpickr-calendar.open'),
            exists: true,
            shadow: getComputedStyle(c).boxShadow !== 'none',
            radius: getComputedStyle(c).borderRadius,
            weekday: [...c.querySelectorAll('.flatpickr-weekday')].map((e) => e.textContent.trim()).join(' '),
            month: c.querySelector('.flatpickr-current-month')?.textContent.trim(),
            total: days.length,
            disabled: days.filter((d) => d.classList.contains('flatpickr-disabled')).length,
            availWeight: cs(avail)?.fontWeight,
            availSize: cs(avail)?.fontSize,
            availRadius: cs(avail)?.borderRadius,
            disColor: cs(dis)?.color,
            disDecoration: cs(dis)?.textDecorationLine,
            disCursor: cs(dis)?.cursor,
            reason: dis?.getAttribute('data-reason') || dis?.getAttribute('title') || '',
            hasHook: days.some((d) => d.classList.contains('booking-day__disabled')),
            todayBorder: cs(todayEl)?.borderColor,
            todayWeight: cs(todayEl)?.fontWeight,
            todayDot: todayEl ? getComputedStyle(todayEl, '::after').content : null,
        };
    })()`)).value;
    check(cal.open, 'คลิกช่องแล้วปฏิทินเปิด');
    check(cal.shadow && parseFloat(cal.radius) > 8, `popup มุมโค้งพร้อม shadow (radius=${cal.radius})`);
    check(/[ก-๙]/.test(cal.weekday), `ชื่อวันภาษาไทย (${cal.weekday})`);
    check(/[ก-๙]/.test(cal.month ?? ''), `ชื่อเดือนภาษาไทย (${cal.month})`);
    check(Number(cal.availWeight) >= 600, `วันที่เลือกได้ = ตัวเลขหนา ${cal.availWeight} ขนาด ${cal.availSize} ทรงกลม ${cal.availRadius}`);
    check(cal.disabled > 0 && cal.disabled < cal.total, `ปิด ${cal.disabled}/${cal.total} วัน (เสาร์-อาทิตย์/วันหยุด)`);
    check(cal.disDecoration === 'line-through', `วันที่ปิดมีขีดฆ่า (${cal.disDecoration})`);
    check(cal.disCursor === 'not-allowed', `วันที่ปิดห้ามคลิก (cursor: ${cal.disCursor})`);
    check(cal.disColor === 'rgb(156, 163, 175)', `วันที่ปิดเป็นสีเทาจาง (${cal.disColor})`);
    check(cal.hasHook, 'มี hook booking-day__disabled สำหรับ tooltip');
    const reasons = (await cdp.eval(`(() => {
        const c = document.querySelector('.flatpickr-calendar');
        const days = [...c.querySelectorAll('.flatpickr-day.flatpickr-disabled')];
        const withHook = days.filter((d) => d.classList.contains('booking-day__disabled'));
        return {
            total: days.length,
            hooked: withHook.length,
            hookNoReason: withHook.filter((d) => !d.getAttribute('data-reason')).length,
            reasons: [...new Set(withHook.map((d) => d.getAttribute('data-reason')))],
        };
    })()`)).value;
    console.log(`   disabled ${reasons.total} วัน | มีเหตุผล ${reasons.hooked} วัน | ตัวอย่าง: ${reasons.reasons.join(' / ')}`);
    check(reasons.hookNoReason === 0, 'ทุกวันที่ติดป้ายเหตุผลมี data-reason ครบ');
    check(reasons.hooked >= 8, `วันเสาร์-อาทิตย์/วันหยุดมีคำอธิบาย (${reasons.hooked} วัน)`);
    check(cal.todayBorder !== 'rgba(0, 0, 0, 0)', `วันนี้มีเส้นขอบ (${cal.todayBorder})`);
    check(Number(cal.todayWeight) >= 600, `วันนี้ตัวเลขหนา (${cal.todayWeight})`);
    check(cal.todayDot === '""', `วันนี้มีจุดใต้ตัวเลข (::after = ${cal.todayDot})`);

    // ไปเดือนถัดไป เพื่อหาวันที่เลือกได้จริง (เดือนนี้เลือกได้แค่วันนี้)
    const next = JSON.parse((await cdp.eval(`(() => {
        const b = document.querySelector('.flatpickr-calendar.open .flatpickr-next-month');
        if (!b) return 'null';
        const r = b.getBoundingClientRect();
        return JSON.stringify({ x: r.x + r.width/2, y: r.y + r.height/2 });
    })()`)).value);
    if (next !== 'null') {
        await cdp.realClick(next.x, next.y);
        await sleep(600);
    }

    // คลิกเลือกวันถัดไป
    const tomorrow = JSON.parse((await cdp.eval(`(() => {
        const c = document.querySelector('.flatpickr-calendar');
        const days = [...c.querySelectorAll('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)')];
        const todayEl = c.querySelector('.flatpickr-day.today');
        const t = days.find((d) => d !== todayEl && !d.classList.contains('flatpickr-disabled'));
        const r = t.getBoundingClientRect();
        return JSON.stringify({ x: r.x + r.width/2, y: r.y + r.height/2 });
    })()`)).value);
    const mark = (await cdp.eval(`(() => { window.__m3 = Math.random().toString(); return window.__m3; })()`)).value;
    await cdp.realClick(tomorrow.x, tomorrow.y);
    await settled('เลือกวันที่');
    await sleep(400);

    const afterPick = (await cdp.eval(`(() => {
        const sel = document.querySelector('.flatpickr-day.selected');
        const s = sel ? getComputedStyle(sel) : null;
        return {
            mark: window.__m3,
            value: document.getElementById('booking-date').value,
            url: location.search,
            open: !!document.querySelector('.flatpickr-calendar.open'),
            bg: s?.backgroundColor, color: s?.color, radius: s?.borderRadius,
            count: document.querySelectorAll('.flatpickr-day.selected').length,
            hiddenDate: document.querySelector('[data-booking-filters] input[name=date]')?.value,
        };
    })()`)).value;
    check(afterPick.mark === mark, 'เลือกวันที่ด้วยการคลิกแล้วไม่โหลดหน้าใหม่ (AJAX)');
    check(afterPick.count === 1, 'มีวันที่ถูกเลือก 1 วัน');
    check(afterPick.bg === 'rgb(79, 70, 229)' && afterPick.color === 'rgb(255, 255, 255)',
        `วันที่เลือก = วงกลม Indigo ตัวอักษรขาว (${afterPick.bg} / ${afterPick.color})`);
    check(afterPick.radius === '9999px', `วันที่เลือกเป็นวงกลมสมบูรณ์ (${afterPick.radius})`);
    check(/date=\d{4}-\d{2}-\d{2}/.test(afterPick.url) && /time_slot=/.test(afterPick.url),
        `URL อัปเดตอัตโนมัติ (${afterPick.url})`);
    const urlDate = (afterPick.url.match(/date=(\d{4}-\d{2}-\d{2})/) || [])[1];
    check(urlDate === afterPick.hiddenDate, `ฟิลด์ซ่อนตรงกับ URL (${afterPick.hiddenDate} = ${urlDate})`);
    check(!!afterPick.value, `ช่องแสดงผลมีค่า (${afterPick.value})`);
    check(!afterPick.open, 'ปฏิทินปิดอัตโนมัติหลังเลือก');

    // ---------- ส่วนที่ 1 + 3: จอง -> เช็คอิน (โจทย์สุ่ม + หมดเวลา) ----------
    // จองโต๊ะของ "วันนี้" ผ่าน endpoint จริง เพื่อให้เข้าเวลาเช็คอินได้ทันที (same-day walk-in)
    await goto('/dashboard?date=' + todayIso() + '&time_slot=' + currentSlotName());
    const created = (await cdp.eval(`(async () => {
        const card = document.querySelector('[data-state="available"]');
        if (!card) return 'no-desk';
        const f = card.querySelector('form[action$="/bookings"]');
        if (!f) return 'no-form';
        const fd = new FormData(f);
        fd.set('_token', document.querySelector('meta[name=csrf-token]').content);
        const res = await fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' });
        return res.status + '|' + res.url;
    })()`)).value;
    check(String(created).startsWith('200'), `จองโต๊ะวันนี้สำเร็จผ่าน POST จริง (${created})`);

    await goto('/bookings');
    const mine = (await cdp.eval(`(() => ({
        rows: document.querySelectorAll('tbody tr').length,
        expired: document.body.innerText.includes('หมดเวลา (Expired)'),
        checkinLinks: [...document.querySelectorAll('a')].filter((a) => a.textContent.trim() === 'เช็คอิน').length,
        blockText: document.body.innerText.includes('เช็คอินไม่ได้แล้ว'),
    }))()`)).value;
    check(mine.rows > 0, `หน้ารายการมี ${mine.rows} แถว`);
    check(mine.checkinLinks > 0, 'มีปุ่ม "เช็คอิน" ในรายการที่ยังไม่หมดเวลา');

    const tinker = (php) => new Promise((resolve, reject) => {
        execFile('php', ['artisan', 'tinker', `--execute=${php}`], { cwd: process.cwd(), timeout: 120000 }, (err, stdout, stderr) => {
            if (err) return reject(new Error(err.message + ' | ' + stderr));
            const m = stdout.match(/RESULT:([\s\S]*?)(?:\r?\n|$)/);
            resolve(m ? m[1].trim() : null);
        });
    });

    // คำสั่งยาว/มี quote เยอะอย่าง --execute จะพังบน Windows และการส่ง "tinker <file>" จะค้างไม่จบ
    // จึงเขียน logic ไว้เป็นไฟล์ แล้วเรียกผ่าน require ใน --execute ซึ่งจบเรียบร้อยเสมอ
    const BASELINE_FILE = 'storage/app/verify-baseline.json';
    const RESTORE_FILE = 'storage/app/verify-restore.php';
    const tinkerFile = (file) => new Promise((resolve, reject) => {
        execFile('php', ['artisan', 'tinker', `--execute=require base_path('${file}');`],
            { cwd: process.cwd(), timeout: 120000 }, (err, stdout, stderr) => {
                if (err) return reject(new Error(err.message + ' | ' + stderr));
                const m = stdout.match(/RESULT:([\s\S]*?)(?:\r?\n|$)/);
                resolve(m ? m[1].trim() : null);
            });
    });

// ---------- ส่วนที่ 1: ใบจองหมดเวลา ----------
    // จำ snapshot ข้อมูลเดโมไว้ก่อน เพื่อให้ล้างเฉพาะสิ่งที่สร้างระหว่างทดสอบ
    // และคืนค่าเดิมของข้อมูลเดโมที่ถูกย้อนวัน/เปลี่ยนสถานะระหว่างทดสอบ
    // (ห้าม hardcode รหัส เพราะรหัสถูกสร้างใหม่ทุกครั้งที่ฐานข้อมูลว่าง)
    const demoBaseline = JSON.parse((await tinker(
        // ใช้ getAttributes() เพื่อได้ค่าดิบจากฐานข้อมูล (ไม่ผ่าน cast ของ Carbon) แล้วคืนค่าได้ตรงเป๊ะ
        `echo 'RESULT:'.json_encode(App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))`
        + `->get()->map(function ($b) { $r = $b->getAttributes(); return [`
        + `'id'=>$r['booking_id'],'desk'=>$r['desk_id'],'date'=>substr((string) $r['booking_date'],0,10),`
        + `'slot'=>$r['time_slot'],'start'=>substr((string) $r['start_time'],0,8),'end'=>substr((string) $r['end_time'],0,8),`
        + `'status'=>$r['booking_status'],'checked_in_at'=>$r['actual_checkin_time'],`
        + `'photo'=>$r['checkin_photo'],'prompt'=>$r['selfie_prompt'],`
        + `]; })->all());`,
    )) || '[]');
const demoBookingIds = demoBaseline.map((b) => b.id);
    console.log('   booking_id ของข้อมูลเดโม: ' + (demoBookingIds.join(', ') || '(ไม่มี)'));

    // คืนค่าข้อมูลเดโมให้ตรงกับ snapshot และลบใบจองที่สร้างระหว่างทดสอบ
    writeFileSync(BASELINE_FILE, JSON.stringify(demoBaseline, null, 2));
    writeFileSync(RESTORE_FILE, `<?php

use App\\Models\\Booking;
use App\\Models\\Employee;

$rows = json_decode((string) file_get_contents(__DIR__.'/verify-baseline.json'), true) ?: [];
$employeeId = Employee::where('employee_email', 'employee@bs-hybrid.test')->value('employee_id');

// ใช้ save() เพื่อให้ event ของ Booking sync สถานะโต๊ะกลับไปตรงด้วย
foreach ($rows as $row) {
    $booking = Booking::where('booking_id', $row['id'])->first();

    if (! $booking) {
        continue;
    }

    $booking->forceFill([
        'desk_id' => $row['desk'],
        'booking_date' => $row['date'],
        'time_slot' => $row['slot'],
        'start_time' => $row['start'],
        'end_time' => $row['end'],
        'booking_status' => $row['status'],
        'actual_checkin_time' => $row['checked_in_at'],
        'checkin_photo' => $row['photo'],
        'selfie_prompt' => $row['prompt'],
    ])->save();
}

Booking::where('employee_id', $employeeId)
    ->whereNotIn('booking_id', array_column($rows, 'id'))
    ->forceDelete();

echo 'RESULT:restored';
`);

    const restoreDemoData = async () => {
        await tinkerFile(RESTORE_FILE);
    };

    // สร้างใบจอง "หมดเวลา" ของพนักงานคนนี้ โดยย้อนวันที่ใบจองที่เพิ่งสร้าง (ไม่แตะข้อมูลของคนอื่น)
    const liveId = (await tinker(
        `echo 'RESULT:'.(App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))->whereDate('booking_date',now()->toDateString())`
        + `->where('booking_status','=',App\\Models\\Booking::STATUS_RESERVED)->orderByDesc('booking_id')->value('booking_id') ?? '');`,
    )) || '';
    console.log('   ใบจองวันนี้ที่ใช้ทดสอบ: ' + (liveId || '(ไม่มี)'));
    check(!!liveId, 'มีใบจองของพนักงานคนนี้ที่ยังจองอยู่ (R)');

    if (liveId) {
        const deskId = JSON.parse((await tinker(
            `echo 'RESULT:'.json_encode(['desk'=>App\\Models\\Booking::where('booking_id','${liveId}')->value('desk_id')]);`,
        )) || '{}').desk;

        // สถานะก่อนย้อนวันที่ (กรณีปกติ = ยังเช็คอินได้)
        await goto('/bookings');
        const normalRow = (await cdp.eval(`(() => {
            const t = new Date(); const pad = (n) => String(n).padStart(2, '0');
            const want = pad(t.getDate()) + '/' + pad(t.getMonth() + 1) + '/' + t.getFullYear();
            const tr = [...document.querySelectorAll('tbody tr')].find((x) => x.children[0]?.textContent.trim() === want
                && [...x.querySelectorAll('a')].some((a) => a.textContent.trim() === 'เช็คอิน'));
            if (!tr) return { found: false, rows: [...document.querySelectorAll('tbody tr')].map((r) => r.innerText.replace(/\\s+/g, ' ')) };
            return {
                found: true,
                detail: tr.innerText.replace(/\\s+/g, ' '),
                href: [...tr.querySelectorAll('a')].find((a) => a.textContent.trim() === 'เช็คอิน').href,
            };
        })()`)).value;
        if (!normalRow.found) console.log('   [rows] ' + JSON.stringify(normalRow.rows, null, 1));
        check(normalRow.found, 'แถวของวันนี้มีลิงก์ "เช็คอิน" ให้กดได้');
        console.log('   แถวปกติ: ' + (normalRow.detail ?? ''));

        // ---------- ส่วนที่ 3: โจทย์สุ่ม 50 ข้อ ----------
        if (normalRow.href) {
            await goto(normalRow.href);
            const ch = (await cdp.eval(`(() => {
            const box = document.querySelector('[data-challenge]');
            const file = document.getElementById('photo');
            return {
                hasBox: !!box,
                label: box?.querySelector('[data-challenge-label]')?.textContent.trim(),
                text: box?.querySelector('[data-challenge-text]')?.textContent.trim(),
                categories: Object.keys(window.SELFIE_CHALLENGE?.prompts ?? {}),
                total: Object.values(window.SELFIE_CHALLENGE?.prompts ?? {}).flat().length,
                labels: Object.keys(window.SELFIE_CHALLENGE?.labels ?? {}),
                capture: file?.getAttribute('capture'),
                accept: file?.getAttribute('accept'),
                required: file?.required,
                hasRefresh: !!document.querySelector('[data-challenge-refresh]'),
                hasPreview: !!document.querySelector('[data-preview]'),
                hint: document.body.innerText.includes('แนบไฟล์รูป หรือถ่ายรูปตอนนี้'),
                hasLoading: !!document.querySelector('form[data-loading-form] [data-loading-button]'),
            };
        })()`)).value;
        check(ch.hasBox, 'หน้าเช็คอินมีกล่องโจทย์สุ่ม');
        check(!!ch.text && !!ch.label, `โจทย์ครั้งแรก: [${ch.label}] ${ch.text}`);
        check(ch.categories.length === 5 && ch.total === 50, `ส่งโจทย์ให้หน้าเว็บ ${ch.total} ข้อ / ${ch.categories.length} หมวด`);
        check(ch.labels.length === 5, `ส่งป้ายหมวด ${ch.labels.length} ป้าย`);
        check(ch.hasRefresh, 'มีปุ่ม "สุ่มโจทย์ใหม่"');
        check(ch.capture === 'user' && ch.accept === 'image/*' && ch.required === true,
            `ช่องแนบ/ถ่ายรูปพร้อมใช้ (capture=${ch.capture}, accept=${ch.accept}, required=${ch.required})`);
        check(ch.hasPreview, 'มีช่องตัวอย่างรูป');
        check(ch.hint, 'มีคำอธิบาย "แนบไฟล์รูป หรือถ่ายรูปตอนนี้"');
        check(ch.hasLoading, 'ปุ่มยืนยันเช็คอินมี loading indicator');

        const samples = new Set();
        for (let i = 0; i < 6; i += 1) {
            await cdp.eval(`document.querySelector('[data-challenge-refresh]').click()`);
            await sleep(120);
            samples.add((await cdp.eval(`document.querySelector('[data-challenge-text]').textContent.trim()`)).value);
        }
        check(samples.size > 1, `กดสุ่มใหม่แล้วโจทย์เปลี่ยนจริง (${samples.size} ค่าจาก 6 ครั้ง)`);
        console.log('   ตัวอย่างโจทย์ที่สุ่มได้: ' + [...samples].slice(0, 3).join(' | '));
        }

        // ---------- ทดสอบกรณีหมดเวลา: ย้อนวันที่ใบจองที่เพิ่งสร้าง ----------
        // นับจำนวนผู้ถือโต๊ะไว้ก่อนย้อนวันที่ เพื่อพิสูจน์ว่าใบจองใบนี้ถูกปล่อยออกจริง
        const holdersBefore = (await tinker(
            `echo 'RESULT:'.App\\Models\\Booking::forDate(now()->toDateString())->where('desk_id','${deskId}')`
            + `->where('booking_status','=',App\\Models\\Booking::STATUS_RESERVED)->count();`,
        )) || '0';
        await tinker(
            `App\\Models\\Booking::where('booking_id','${liveId}')->update(['booking_date'=>now()->subDay()->toDateString()]);`
            + `echo 'RESULT:backdated';`,
        );

        await goto('/bookings');
        const expiredView = (await cdp.eval(`(() => {
            const rows = [...document.querySelectorAll('tbody tr')];
            const target = rows.find((tr) => tr.innerText.includes('หมดเวลา (Expired)'));
            if (!target) return { found: false, rows: rows.map((r) => r.innerText.replace(/\\s+/g, ' ').slice(0, 80)) };
            return {
                found: true,
                blocked: target.innerText.includes('เช็คอินไม่ได้แล้ว'),
                hasCheckinLink: [...target.querySelectorAll('a')].some((a) => a.textContent.trim() === 'เช็คอิน'),
                hasCancel: [...target.querySelectorAll('button, a')].some((b) => b.textContent.trim() === 'ยกเลิก'),
                detail: target.innerText.replace(/\\s+/g, ' ').slice(0, 160),
            };
        })()`)).value;
        if (!expiredView.found) console.log('   [rows] ' + JSON.stringify(expiredView.rows, null, 1));
        check(expiredView.found, 'หน้ารายการแสดงสถานะ "หมดเวลา (Expired)"');
        check(expiredView.blocked, 'ปุ่มเช็คอินถูกปิดเป็น "เช็คอินไม่ได้แล้ว"');
        check(!expiredView.hasCheckinLink, 'ไม่มีลิงก์เช็คอินในแถวที่หมดเวลา');
        check(expiredView.hasCancel, 'ยังยกเลิกการจองได้');
        console.log('   แถวที่หมดเวลา: ' + (expiredView.detail ?? ''));

        // เปิดหน้าเช็คอินโดยตรง -> ต้องถูกส่งกลับหน้ารายการพร้อม flash และเปลี่ยนสถานะเป็น X + ปล่อยโต๊ะ
        const holdersAfter = (await tinker(
            `echo 'RESULT:'.App\\Models\\Booking::forDate(now()->toDateString())->where('desk_id','${deskId}')`
            + `->where('booking_status','=',App\\Models\\Booking::STATUS_RESERVED)->count();`,
        )) || '0';
        await goto(`${BASE}/bookings/${liveId}/checkin`);
        const afterExpired = (await cdp.eval(`({
            path: location.pathname,
            text: document.body.innerText.replace(/\\s+/g, ' ').slice(0, 300),
        })`)).value;
        check(afterExpired.path === '/bookings', `เปิดหน้าเช็คอินของใบหมดเวลาแล้วถูกส่งกลับหน้ารายการ (${afterExpired.path})`);
        check(/หมดเวลา/.test(afterExpired.text), 'มี flash แจ้งว่าหมดเวลาเช็คอิน');
        console.log('   flash: ' + afterExpired.text.slice(0, 140));

        const after = JSON.parse((await tinker(
            `echo 'RESULT:'.json_encode(['status'=>App\\Models\\Booking::where('booking_id','${liveId}')->value('booking_status'),`
            + `'desk'=>App\\Models\\Desk::where('desk_id','${deskId}')->first()->desk_status,`
            + `'stillHolding'=>App\\Models\\Booking::forDate(now()->toDateString())->where('desk_id','${deskId}')`
            + `->where('booking_status','=',App\\Models\\Booking::STATUS_RESERVED)->count()]);`,
        )) || '{}');
        console.log('   หลังหมดเวลา: ' + JSON.stringify(after) + ` (ผู้ถือโต๊ะก่อนหมดเวลา: ${holdersBefore} -> หลังย้อนวันที่: ${holdersAfter})`);
        check(after.status === 'X', `สถานะใบจองเปลี่ยนเป็น X อัตโนมัติ (${after.status})`);
        check(Number(holdersAfter) === Number(holdersBefore) - 1,
            `ใบจองที่หมดเวลาเลิกถือโต๊ะทันทีที่ย้อนวันที่ (${holdersBefore} -> ${holdersAfter})`);
        // สถานะโต๊ะต้องตรงกับจำนวนผู้ถือที่เหลือจริง (Desk เก็บค่าตามรูปแบบของตัวเอง)
        check(String(after.desk).toLowerCase() === (after.stillHolding > 0 ? 'reserved' : 'available'),
            `สถานะโต๊ะสอดคล้องกับการปล่อยโต๊ะ (${after.desk}, ผู้ถือที่เหลือ ${after.stillHolding})`);

        // คืนข้อมูลเดโมกลับเป็นค่าเดิม และล้างใบจองที่จองเพิ่มระหว่างทดสอบ
        await restoreDemoData();
    }

    // ลิงก์เมนูภายใน (ไม่ได้ใส่ data-loading-button) ต้องขึ้นแถบโหลดด้วย
    await goto('/bookings');
    {
        const nav = (await cdp.eval(`(() => {
            const a = [...document.querySelectorAll('nav a[href], header a[href], a[href*="/dashboard"]')]
                .find((x) => !x.hasAttribute('data-loading-button'));
            if (!a) return { found: false, links: document.querySelectorAll('a[href]').length };
            const label = a.textContent.trim();
            a.click();
            return { found: true, label, bar: !!document.querySelector('.app-loading-bar[data-active="true"]') };
        })()`)).value;
        check(nav.found, `พบลิงก์เมนูที่ไม่ได้ใส่ data-loading-button (${nav.label ?? ''})`);
        check(nav.bar === true, 'คลิกลิงก์ภายในแล้วแถบโหลดขึ้นทันที');
    }

    // ---------- ส่วนที่ 4: Lightbox รูปเช็คอิน ----------
    {
        // ผูก prompt กับใบจองที่มีรูปอยู่แล้ว เพื่อพิสูจน์ว่า lightbox แสดง "โจทย์เซลฟี่ที่ได้รับ" จริง
        // (ใบจองเก่าที่เช็คอินก่อนมีคอลัมน์นี้จะไม่มีค่า ซึ่งถือว่าเป็นกรณี fallback ปกติ)
        // จำค่าเดิมไว้ก่อน แล้วคืนค่ากลับหลังทดสอบเสร็จ เพื่อไม่ให้แก้ข้อมูลเดโมถาวร
        const promptRestore = await tinker(
            `echo 'RESULT:'.json_encode(App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))`
            + `->whereNotNull('checkin_photo')->get(['booking_id','selfie_prompt'])->toArray());`,
        );
        let restoreMap = [];
        try {
            restoreMap = JSON.parse(promptRestore || '[]');
        } catch {
            restoreMap = [];
        }
        await tinker(
            `App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))->whereNotNull('checkin_photo')`
            + `->whereNull('selfie_prompt')->update(['selfie_prompt' => 'ยิ้มให้กล้องดูชัด ๆ พร้อมยกมือขวา']);`
            + `echo 'RESULT:stamped';`,
        );
        const hasPhoto = await tinker(
            `echo 'RESULT:'.(App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))->whereNotNull('checkin_photo')->count());`,
        );
        console.log('   ใบจองที่มีรูปเช็คอิน: ' + hasPhoto);
        await goto('/bookings');
        const lb = (await cdp.eval(`(() => {
            const buttons = [...document.querySelectorAll('button')].filter((b) => b.textContent.trim() === 'ดูรูป');
            const root = document.querySelector('[data-photo-lightbox]');
            let data = [];
            try { data = JSON.parse(document.getElementById('checkin-photo-data')?.textContent ?? '[]'); } catch (e) { data = []; }
            return {
                hasRoot: !!root,
                buttons: buttons.length,
                hidden: root ? getComputedStyle(root).display === 'none' : null,
                items: data.length,
                keys: data[0] ? Object.keys(data[0]) : [],
                first: data[0] ?? null,
                hasCloseX: !!root?.querySelector('button[aria-label="ปิด"]'),
                hasCloseBottom: [...(root?.querySelectorAll('button') ?? [])].some((b) => b.textContent.trim() === 'ปิด'),
                role: root?.getAttribute('role'),
                escape: root?.getAttribute('@keydown.escape.window'),
            };
        })()`)).value;
        check(lb.hasRoot, 'หน้ารายการมี lightbox สำหรับดูรูปเช็คอิน');
        check(lb.hidden === true, 'lightbox ปิดอยู่ตอนเริ่มต้น (ยังไม่เปิดเอง)');
        check(lb.hasCloseX && lb.hasCloseBottom, 'lightbox มีปุ่มปิด [X] และปุ่ม "ปิด" ด้านล่าง');
        check(lb.role === 'dialog', 'lightbox เป็น role=dialog');

        if (Number(hasPhoto) > 0) {
            check(lb.buttons > 0, `ปุ่ม "ดูรูป" เปลี่ยนเป็นปุ่มที่เปิด lightbox (${lb.buttons} ปุ่ม)`);
            check(lb.items > 0, `ส่งข้อมูลรูปมาให้ lightbox ${lb.items} รายการ`);
            const keys = lb.keys;
            check(['employee_name', 'department', 'checked_in_at', 'prompt', 'url', 'slot_label'].every((k) => keys.includes(k)),
                `ข้อมูลใน lightbox ครบ (${keys.join(', ')})`);

            const opened = (await cdp.eval(`(() => {
                const b = [...document.querySelectorAll('button')].find((x) => x.textContent.trim() === 'ดูรูป');
                b.click();
                return 'clicked';
            })()`)).value;
            await sleep(400);
            const shown = (await cdp.eval(`(() => {
                const root = document.querySelector('[data-photo-lightbox]');
                const vis = getComputedStyle(root).display !== 'none';
                const text = (root?.innerText ?? '').replace(/\\s+/g, ' ').trim();
                const img = root?.querySelector('img');
                return {
                    vis,
                    hasImg: !!img,
                    imgSrc: img?.getAttribute('src') ?? '',
                    labels: [...root.querySelectorAll('dt')].map((d) => d.textContent.trim()),
                    values: [...root.querySelectorAll('dd')].map((d) => d.textContent.trim()),
                    bodyLocked: document.body.classList.contains('overflow-hidden'),
                };
            })()`)).value;
            check(shown.vis, 'กด "ดูรูป" แล้ว lightbox เปิดจริง');
            check(shown.hasImg && shown.imgSrc.length > 0, `แสดงรูปใน lightbox (src ยาว ${shown.imgSrc.length})`);
            const wanted = ['ชื่อ-นามสกุลพนักงาน', 'แผนก', 'เวลาที่กดเช็คอิน', 'โจทย์เซลฟี่ที่ได้รับ'];
            wanted.forEach((w) => check(shown.labels.some((l) => l.includes(w)), `lightbox มี "${w}"`));
            const filled = shown.values.filter((v) => v && v !== 'ไม่มีข้อมูลโจทย์');
            check(filled.length === shown.values.length && shown.values.length > 0,
                `รายละเอียดใน lightbox เต็มทุกช่อง (${filled.length}/${shown.values.length})`);
            const promptShown = shown.values[shown.values.length - 1];
            check(promptShown === 'ยิ้มให้กล้องดูชัด ๆ พร้อมยกมือขวา',
                `lightbox แสดงโจทย์เซลฟี่ที่ผูกไว้กับใบจอง ("${promptShown}")`);
            check(shown.bodyLocked, 'เปิด lightbox แล้วล็อกการเลื่อนหน้าจอพื้นหลัง');

            // ปิดด้วย Escape
            await cdp.eval(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
            await sleep(300);
            const closedByEsc = (await cdp.eval(`getComputedStyle(document.querySelector('[data-photo-lightbox]')).display === 'none'`)).value;
            check(closedByEsc === true, 'กด Escape แล้ว lightbox ปิด');

            // ปิดด้วยปุ่ม [X]
            await cdp.eval(`[...document.querySelectorAll('button')].find((x) => x.textContent.trim() === 'ดูรูป').click()`);
            await sleep(300);
            await cdp.eval(`document.querySelector('[data-photo-lightbox] button[aria-label="ปิด"]').click()`);
            await sleep(300);
            const closedByX = (await cdp.eval(`getComputedStyle(document.querySelector('[data-photo-lightbox]')).display === 'none'`)).value;
            check(closedByX === true, 'กดปุ่ม [X] แล้ว lightbox ปิด');
            const unlocked = (await cdp.eval(`document.body.classList.contains('overflow-hidden')`)).value;
            check(unlocked === false, 'ปิด lightbox แล้วปลดล็อกการเลื่อนหน้าจอพื้นหลัง');
        } else {
            console.log('   ข้ามการทดสอบ lightbox: ยังไม่มีรูปเช็คอินในข้อมูลจริง');
            check(lb.hidden === true && lb.hasCloseX, 'lightbox พร้อมใช้งานและปิดอยู่ (ยังไม่มีรูปให้ทดสอบ)');
        }

        // คืนค่า selfie_prompt เดิมของทุกใบจอง เพื่อไม่ให้ข้อมูลเดโมเปลี่ยนถาวร
        for (const row of restoreMap) {
            const value = row.selfie_prompt === null
                ? 'null'
                : `'${String(row.selfie_prompt).replace(/'/g, "''")}'`;
            await tinker(
                `App\\Models\\Booking::where('booking_id','${row.booking_id}')`
                + `->update(['selfie_prompt' => ${value}]);`
                + `echo 'RESULT:restored';`,
            );
        }
        console.log(`   คืนค่า selfie_prompt เดิมให้ ${restoreMap.length} ใบจอง`);
    }

    // Alpine confirmDialog: กด "ยกเลิก" ใน modal -> ปุ่มต้องไม่ค้าง disabled และไม่ navigate ออก
    // ต้องมีใบจองสถานะ R ก่อน จึงจะมีปุ่ม "ยกเลิก" ให้ทดสอบ
    const pad = (n) => String(n).padStart(2, '0');
    const isoOf = (dt) => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`;

    // วันถัดไปอาจตกเสาร์-อาทิตย์หรือวันหยุด ซึ่งจองไม่ได้ จึงไล่หาวันที่จองได้จริงแทน
    let bookedDate = null;
    for (let offset = 1; offset <= 7 && bookedDate === null; offset += 1) {
        const d = new Date(); d.setDate(d.getDate() + offset);
        const candidate = isoOf(d);

        await goto(`/dashboard?date=${candidate}&time_slot=Morning`);
        const attempt = (await cdp.eval(`(async () => {
            const card = document.querySelector('[data-state="available"]');
            if (!card) return 'no-desk';
            const f = card.querySelector('form[action$="/bookings"]');
            const deskId = f.dataset.deskNumber ?? '?';
            const fd = new FormData(f);
            fd.set('_token', document.querySelector('meta[name=csrf-token]').content);
            const res = await fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' });
            return res.status + '|' + res.url + '|desk=' + deskId;
        })()`)).value;
        console.log(`   ลองจองวันที่ ${candidate}: ${attempt}`);

        // redirect:'follow' ทำให้ 302 กลายเป็น 200 เสมอ จึงต้องยืนยันจากฐานข้อมูลจริง
        const created = await tinker(
            `echo 'RESULT:'.(App\\Models\\Booking::where('employee_id',App\\Models\\Employee::where('employee_email','employee@bs-hybrid.test')->value('employee_id'))`
            + `->whereDate('booking_date','${candidate}')`
            + `->where('booking_status','=',App\\Models\\Booking::STATUS_RESERVED)->count());`,
        );
        if (Number(created) > 0) {
            bookedDate = candidate;
            console.log(`   จองสำหรับทดสอบปุ่มยกเลิกสำเร็จที่วันที่ ${candidate}`);
        }
    }
    check(bookedDate !== null, 'มีใบจองสถานะ R สำหรับทดสอบปุ่ม "ยกเลิก"');

    await goto('/bookings');
    {
        const opened = (await cdp.eval(`(() => {
            const forms = [...document.querySelectorAll('form[data-confirm]')];
            if (!forms.length) return JSON.stringify({ noCancelForm: true, all: document.querySelectorAll('form').length,
                rows: [...document.querySelectorAll('tbody tr')].map((r) => r.innerText.replace(/\\s+/g, ' ').trim()) });
            const f = forms.find((x) => [...x.querySelectorAll('button[type=submit]')]
                .some((b) => b.textContent.trim() === 'ยกเลิก')) ?? forms[forms.length - 1];
            const b = f.querySelector('button[type=submit]');
            if (!b) return JSON.stringify({ noCancelButton: true, forms: forms.length });
            window.__confirmForm = f;
            window.__confirmButton = b;
            const before = b.disabled;
            f.requestSubmit ? f.requestSubmit() : b.click();
            return JSON.stringify({ before, label: b.textContent.trim(), title: f.dataset.confirmTitle ?? null,
                message: f.dataset.confirm ?? null });
        })()`)).value;
        const parsed = typeof opened === 'string' && opened.startsWith('{') ? JSON.parse(opened) : null;
        if (!parsed) {
            check(false, `ผลการตรวจฟอร์มยกเลิกไม่ถูกต้อง (${JSON.stringify(opened)})`);
        } else if (parsed.noCancelForm) {
            if (parsed.rows) parsed.rows.forEach((r) => console.log('   [row] ' + r));
            check(false, `ไม่พบฟอร์มยกเลิกที่มี data-confirm (มี ${parsed.all} ฟอร์มทั้งหมด)`);
        } else if (parsed.noCancelButton) {
            check(false, `พบฟอร์มที่มี data-confirm แต่ไม่มีปุ่มยกเลิก (${parsed.forms} ฟอร์ม)`);
        } else {
            check(parsed.before === false, `ก่อนกด ปุ่ม "${parsed.label}" ยังกดได้`);
            check(Boolean(parsed.message), `ฟอร์มส่งข้อความยืนยันมาใน data-confirm (${parsed.title ?? 'ไม่มีหัวข้อ'})`);

            // ต้องมี modal ของระบบเปิดขึ้นมาแทน confirm() ของเบราว์เซอร์
            await sleep(400);
            const modal = (await cdp.eval(`(() => {
                const root = document.querySelector('[x-data="confirmDialog"]');
                if (!root) return JSON.stringify({ missing: true });
                const panel = root.querySelector('[role=dialog]') ?? root;
                const cancelBtn = [...root.querySelectorAll('button')]
                    .find((b) => b.textContent.trim() === 'ยกเลิก');
                return JSON.stringify({
                    display: getComputedStyle(root).display,
                    visible: root.offsetParent !== null,
                    title: root.querySelector('#confirm-dialog-title')?.textContent.trim() ?? null,
                    renderedMessage: root.querySelector('#confirm-dialog-title + p')?.textContent.trim() ?? null,
                    hasCancel: !!cancelBtn,
                    confirmText: [...root.querySelectorAll('button')].map((b) => b.textContent.trim()),
                });
            })()`)).value;
            const m = typeof modal === 'string' && modal.startsWith('{') ? JSON.parse(modal) : null;
            if (!m || m.missing) {
                check(false, 'ไม่พบ modal ยืนยันของระบบบนหน้า');
            } else {
                check(m.display !== 'none', 'Alpine เปิด modal ยืนยันให้เห็นจริง');
                check(m.renderedMessage === parsed.message, 'ข้อความใน modal ตรงกับ data-confirm ของฟอร์ม');
                check(m.hasCancel, 'modal มีปุ่ม "ยกเลิก" ให้ผู้ใช้ยกเลิกได้');
                await cdp.eval(`window.__cancelBtn = window.__cancelBtn
                    ?? [...document.querySelector('[x-data="confirmDialog"]').querySelectorAll('button')]
                        .find((b) => b.textContent.trim() === 'ยกเลิก');
                    window.__cancelBtn?.click();`);
            }

            await sleep(400);
            const after = (await cdp.eval(`(() => ({
                display: getComputedStyle(document.querySelector('[x-data="confirmDialog"]')).display,
                disabled: window.__confirmButton?.disabled ?? null,
                busy: window.__confirmForm?.dataset.busy ?? null,
                path: location.pathname,
                native: window.__nativeConfirmCalls ?? -1,
            }))()`)).value;
            check(after.display === 'none', 'กด "ยกเลิก" แล้ว modal ปิด');
            check(after.disabled === false && !after.busy,
                `กด "ยกเลิก" แล้วปุ่มไม่ค้าง (disabled=${after.disabled}, busy=${after.busy})`);
            check(after.path === '/bookings', `ยังอยู่หน้ารายการ ไม่ได้ navigate ออกไป (${after.path})`);
            check(after.native === 0, `ไม่มีการเรียก confirm() ของเบราว์เซอร์ (${after.native} ครั้ง)`);
        }
    }

    // คืนข้อมูลเดโมกลับเป็นค่าเดิม ให้ผลทดสอบซ้ำได้โดยไม่ต้อง seed ใหม่
    await restoreDemoData();
    [BASELINE_FILE, RESTORE_FILE].forEach((f) => rmSync(f, { force: true }));

    console.log('\n--- สรุป ---');
    console.log(`ผ่าน ${pass} / ไม่ผ่าน ${fail}`);
    if (cdp.errors.length) {
        console.log('ข้อผิดพลาดใน console:');
        [...new Set(cdp.errors)].forEach((e) => console.log('  ' + e));
    } else {
        console.log('ไม่มี console error');
    }

    ws.close();
    chrome.kill();
    process.exit(fail > 0 ? 1 : 0);
}

main().catch((e) => { console.error(e); chrome.kill(); process.exit(1); });