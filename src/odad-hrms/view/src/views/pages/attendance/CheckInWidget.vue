<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { AttendanceService } from '@/service/AttendanceService';

const toast    = useToast();
const loading  = ref(false);
const working  = ref(false);
const record   = ref(null); // today's attendance record

const statusLabel = {
    present:  'Present',
    absent:   'Absent',
    late:     'Late',
    half_day: 'Half Day',
    on_leave: 'On Leave',
};

onMounted(load);

async function load() {
    loading.value = true;
    try {
        record.value = await AttendanceService.getMyToday();
    } catch {
        record.value = null;
    } finally {
        loading.value = false;
    }
}

async function checkIn() {
    working.value = true;
    try {
        await AttendanceService.checkIn();
        toast.add({ severity: 'success', detail: 'Checked in successfully.', life: 3000 });
        await load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        working.value = false;
    }
}

async function checkOut() {
    working.value = true;
    try {
        await AttendanceService.checkOut();
        toast.add({ severity: 'success', detail: 'Checked out successfully.', life: 3000 });
        await load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        working.value = false;
    }
}

function formatTime(val) {
    if (!val) return '—';
    const d = new Date(val);
    if (isNaN(d)) return val;
    const tz = window.wphrApi?.timezone;
    const opts = { hour: '2-digit', minute: '2-digit' };
    if (tz) opts.timeZone = tz;
    return d.toLocaleTimeString([], opts);
}
</script>

<template>
    <div class="card w-full">
        <div class="font-semibold text-lg mb-4">Today's Attendance</div>

        <div v-if="loading" class="flex justify-center py-6">
            <ProgressSpinner style="width:2rem;height:2rem" />
        </div>

        <div v-else class="flex flex-col gap-4">
            <div v-if="record" class="grid grid-cols-2 gap-3 text-sm">
                <div class="flex flex-col gap-1">
                    <span class="text-surface-500">Status</span>
                    <Tag class="capitalize" :value="statusLabel[record.status] ?? record.status"
                        :severity="record.status === 'present' ? 'success' : record.status === 'absent' ? 'danger' : 'warn'" />
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-surface-500">Date</span>
                    <span class="font-medium">{{ record.date }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-surface-500">Check In</span>
                    <span class="font-medium">{{ formatTime(record.check_in) }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-surface-500">Check Out</span>
                    <span class="font-medium">{{ formatTime(record.check_out) }}</span>
                </div>
                <div v-if="record.total_working_hours" class="flex flex-col gap-1">
                    <span class="text-surface-500">Hours Worked</span>
                    <span class="font-medium">{{ Number(record.total_working_hours).toFixed(2) }} hrs</span>
                </div>
                <div v-if="record.overtime_hours > 0" class="flex flex-col gap-1">
                    <span class="text-surface-500">Overtime</span>
                    <span class="font-medium text-orange-500">{{ Number(record.overtime_hours).toFixed(2) }} hrs</span>
                </div>
                <div v-if="record.late_entry" class="col-span-2">
                    <Tag class="capitalize" value="Late Entry" severity="warn" />
                </div>
                <div v-if="record.early_exit" class="col-span-2">
                    <Tag class="capitalize" value="Early Exit" severity="warn" />
                </div>
            </div>
            <div v-else class="text-surface-500 text-sm">No attendance record for today yet.</div>

            <div class="flex gap-3 mt-2">
                <Button v-if="!record || !record.check_in"
                    label="Check In" icon="pi pi-sign-in" :loading="working" @click="checkIn" />
                <Button v-if="record?.check_in && !record?.check_out"
                    label="Check Out" icon="pi pi-sign-out" severity="secondary" :loading="working" @click="checkOut" />
            </div>
        </div>
    </div>
</template>
