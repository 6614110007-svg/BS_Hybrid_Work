<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            แผนผังที่นั่ง & จองโต๊ะทำงาน
        </h2>
    </x-slot>

    <div class="py-8" x-data='seatMap(@js($init))' @click.outside="confirmOpen = false">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Flash -->
            <template x-if="alert.success">
                <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 text-sm shadow-sm">
                    <span x-text="alert.success"></span>
                </div>
            </template>
            <template x-if="alert.error">
                <div class="mb-4 rounded-lg bg-red-50 border border-red-300 text-red-800 px-4 py-3 text-sm shadow-sm">
                    <span x-text="alert.error"></span>
                </div>
            </template>
            <template x-if="alert.info">
                <div class="mb-4 rounded-lg bg-sky-50 border border-sky-300 text-sky-800 px-4 py-3 text-sm shadow-sm">
                    <span x-text="alert.info"></span>
                </div>
            </template>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Main column -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Controls -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">วันที่</label>
                                <input type="date" x-model="date" :min="minDate" :max="maxDate"
                                       @change="refresh()"
                                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 p-2 border text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">ช่วงเวลา</label>
                                <div class="flex gap-2">
                                    <template x-for="slot in slots" :key="slot.id">
                                        <button @click="slotId = slot.id; refresh()"
                                                class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                                                :class="slotId === slot.id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'">
                                            <span x-text="slot.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">โซน</label>
                                <div class="flex gap-2 flex-wrap">
                                    <button @click="zoneFilter = 'all'"
                                            class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                                            :class="zoneFilter === 'all' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300'">ทั้งหมด</button>
                                    <template x-for="z in zones" :key="z.id">
                                        <button @click="zoneFilter = z.id"
                                                class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                                                :class="zoneFilter === z.id ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300'"
                                                x-text="z.code"></button>
                                    </template>
                                </div>
                            </div>
                            <div class="ms-auto flex items-center gap-2 text-xs text-gray-400">
                                <svg x-show="loading" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-show="!loading">อัปเดตอัตโนมัติทุก 15 วิ</span>
                                <span x-show="loading">กำลังอัปเดต…</span>
                            </div>
                        </div>
                    </div>

                    <!-- Zones & map -->
                    <template x-for="zone in visibleZones()" :key="zone.id">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-gray-800" x-text="zone.name"></div>
                                    <div class="text-xs text-gray-500">
                                        <span x-text="zone.code"></span>
                                        <template x-if="zone.department"> · แผนก <span x-text="zone.department"></span></template>
                                        <template x-if="zone.floor"> · ชั้น <span x-text="zone.floor"></span></template>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>ว่าง</span>
                                    <span class="inline-flex items-center gap-1 ms-2"><span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>จองแล้ว</span>
                                    <span class="inline-flex items-center gap-1 ms-2"><span class="w-2.5 h-2.5 rounded-full bg-sky-500 inline-block"></span>ใช้งานอยู่</span>
                                </div>
                            </div>
                            <div class="p-4 overflow-x-auto">
                                <template x-if="deskList(zone.id).length">
                                    <div class="relative rounded-lg border border-dashed border-gray-300 bg-gray-50/60"
                                         :style="'min-width:'+Math.max(deskList(zone.id).length ? Math.max(...deskList(zone.id).map(d=>d.x))*52 : 260, 260)+'px; min-height:'+Math.max(Math.max(...deskList(zone.id).map(d=>d.y))*52, 120)+'px'">
                                        <template x-for="desk in deskList(zone.id)" :key="desk.id">
                                            <button type="button"
                                                    @click="openBook(desk)"
                                                    class="absolute flex flex-col items-center justify-center rounded-md px-1 text-center shadow-sm transition focus:outline-none"
                                                    :class="chipClass(statusOf(desk).state)"
                                                    :style="'left:'+((desk.x-1)*52)+'px; top:'+((desk.y-1)*52)+'px; width:44px; height:44px;'"
                                                    :title="deskLabel(desk)">
                                                <span class="text-[10px] font-semibold leading-tight" x-text="desk.code"></span>
                                                <span x-show="statusOf(desk).state === 'in_use' || statusOf(desk).state === 'my_in_use'" class="block w-1.5 h-1.5 rounded-full bg-white mt-0.5"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!deskList(zone.id).length" class="text-sm text-gray-400 py-6 text-center">ยังไม่มีโต๊ะในโซนนี้</p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- My bookings today -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-800 text-sm mb-3">การจองของฉันวันนี้</h3>
                        <template x-if="!activeBookings.length">
                            <p class="text-sm text-gray-400">ยังไม่มีการจองโต๊ะวันนี้</p>
                        </template>
                        <template x-for="b in activeBookings" :key="b.id">
                            <div class="border border-indigo-100 bg-indigo-50/50 rounded-lg p-3 mb-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-indigo-800 text-sm" x-text="'โต๊ะ '+b.desk_code"></div>
                                        <div class="text-xs text-indigo-600" x-text="b.slot"></div>
                                    </div>
                                    <template x-if="b.status === 'confirmed'">
                                        <a :href="'/bookings/'+b.id+'/checkin'" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">
                                            เช็คอินด้วยรูปถ่าย
                                        </a>
                                    </template>
                                    <template x-if="b.status === 'checked_in'">
                                        <form :action="'/bookings/'+b.id+'/checkout'" method="POST">
                                            <input type="hidden" name="_token" :value="document.head.querySelector('meta[name=csrf-token]').content">
                                            <button class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">เช็คเอาต์</button>
                                        </form>
                                    </template>
                                </div>
                                <button class="mt-2 text-xs text-red-500 hover:text-red-700 font-medium" x-show="b.status === 'confirmed'"
                                        @click="cancel(b.id)">ยกเลิกการจอง</button>
                            </div>
                        </template>
                    </div>

                    <!-- Notifications -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                                การแจ้งเตือน
                                <span x-show="unreadCount > 0" class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold" x-text="unreadCount"></span>
                            </h3>
                            <button @click="readAll()" x-show="unreadCount > 0" class="text-xs text-indigo-500 hover:text-indigo-700 font-medium">อ่านทั้งหมด</button>
                        </div>
                        <template x-if="!notifications.length">
                            <p class="text-sm text-gray-400">ไม่มีการแจ้งเตือน</p>
                        </template>
                        <div class="space-y-2">
                            <template x-for="(n, i) in notifications" :key="i">
                                <div class="text-sm rounded-lg px-3 py-2" :class="n.read ? 'bg-gray-50 text-gray-500' : 'bg-amber-50 text-amber-800'">
                                    <p x-text="'การจองโต๊ะ '+n.data.desk_code+' วันที่ '+n.data.booking_date+' ('+n.data.slot+') ถูกยกเลิกเนื่องจากเลยเวลาเช็คอิน'"></p>
                                    <p class="text-[11px] opacity-70" x-text="n.created_at"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Guide -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-xs text-gray-500 space-y-1.5">
                        <h3 class="font-semibold text-gray-800 text-sm mb-2">วิธีใช้งาน</h3>
                        <p>1. เลือกวันที่และช่วงเวลาที่ต้องการ</p>
                        <p>2. คลิกโต๊ะสีเขียวที่ว่างเพื่อจอง</p>
                        <p>3. ในวันใช้งานกด "เช็คอินด้วยรูปถ่าย" และถ่าย เซลฟีตามโจทย์ประจำวัน</p>
                        <p>4. ก่อนกลับกด "เช็คเอาต์" เพื่อคืนโต๊ะ</p>
                        <p class="text-gray-400 pt-1">* เช็คอินสายเกิน 60 นาที ระบบจะยกเลิกใบจองอัตโนมัติ</p>
                    </div>
                </div>
            </div>

            <!-- Booking confirm modal -->
            <template x-if="confirmOpen && selectedDesk">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6" @click.outside="closeBook()">
                        <h3 class="text-lg font-bold text-gray-800">ยืนยันการจองโต๊ะ</h3>
                        <p class="mt-4 text-sm text-gray-600" x-if="selectedDesk">
                            โต๊ะ <span class="font-semibold text-indigo-600" x-text="selectedDesk.code"></span>
                            วันที่ <span class="font-semibold" x-text="date"></span>
                            ช่วงเวลา <span class="font-semibold" x-text="slotName(slotId)"></span>
                        </p>
                        <p class="mt-3 text-xs text-gray-400">จองแล้วกดเช็คอินในช่วงเวลาที่ใช้งานได้ตั้งแต่ก่อนเวลาเริ่ม 60 นาที</p>
                        <div class="mt-6 flex justify-end gap-3">
                            <button @click="closeBook()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">ยกเลิก</button>
                            <button @click="confirmBook()" :disabled="submitting"
                                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                                <span x-show="!submitting">ยืนยันการจอง</span>
                                <span x-show="submitting">กำลังจอง…</span>
                            </button>
                        </div>
                        <p x-show="bookError" class="mt-3 text-sm text-red-600" x-text="bookError"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>