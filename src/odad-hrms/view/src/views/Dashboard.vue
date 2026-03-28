<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const data = ref(null);

onMounted(loadDashboard);

async function loadDashboard() {
    loading.value = true;
    try {
        const res = await request('dashboard');
        if (res.error) throw new Error(res.error.message);
        data.value = res.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

const activeCount = computed(() => {
    if (!data.value?.headcount?.by_status) return 0;
    const row = data.value.headcount.by_status.find(r => r.employment_status === 'active');
    return row ? parseInt(row.count) : 0;
});

const presentToday = computed(() => {
    if (!data.value?.today_attendance) return 0;
    const row = data.value.today_attendance.find(r => r.status === 'present');
    return row ? parseInt(row.count) : 0;
});

const pipelineStages = computed(() => data.value?.recruitment_pipeline || []);
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <!-- Headcount -->
        <div class="card flex flex-col gap-1">
            <span class="text-surface-500 text-sm font-medium">Active Employees</span>
            <span v-if="loading" class="text-3xl font-bold text-primary">—</span>
            <span v-else class="text-3xl font-bold text-primary">{{ activeCount }}</span>
            <span class="text-xs text-surface-400">Total: {{ data?.headcount?.total ?? 0 }}</span>
        </div>
        <!-- Pending Leave -->
        <div class="card flex flex-col gap-1">
            <span class="text-surface-500 text-sm font-medium">Pending Leave Approvals</span>
            <span v-if="loading" class="text-3xl font-bold text-orange-500">—</span>
            <span v-else class="text-3xl font-bold text-orange-500">{{ data?.pending_leave_count ?? 0 }}</span>
        </div>
        <!-- Today Attendance -->
        <div class="card flex flex-col gap-1">
            <span class="text-surface-500 text-sm font-medium">Present Today</span>
            <span v-if="loading" class="text-3xl font-bold text-green-500">—</span>
            <span v-else class="text-3xl font-bold text-green-500">{{ presentToday }}</span>
        </div>
        <!-- Open Jobs -->
        <div class="card flex flex-col gap-1">
            <span class="text-surface-500 text-sm font-medium">Open Job Positions</span>
            <span v-if="loading" class="text-3xl font-bold text-blue-500">—</span>
            <span v-else class="text-3xl font-bold text-blue-500">{{ data?.open_job_count ?? 0 }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
        <!-- Last Payroll Run -->
        <div class="card">
            <h3 class="font-semibold text-lg mb-3">Last Payroll Run</h3>
            <div v-if="loading" class="text-surface-400">Loading...</div>
            <div v-else-if="data?.last_payroll_run" class="flex flex-col gap-2">
                <div class="flex justify-between"><span class="text-surface-500">Name</span><span class="font-medium">{{ data.last_payroll_run.name }}</span></div>
                <div class="flex justify-between"><span class="text-surface-500">Period</span><span>{{ data.last_payroll_run.period_start }} – {{ data.last_payroll_run.period_end }}</span></div>
                <div class="flex justify-between"><span class="text-surface-500">Employees</span><span>{{ data.last_payroll_run.total_employees }}</span></div>
                <div class="flex justify-between"><span class="text-surface-500">Total Net Pay</span><span class="font-bold text-green-600">{{ Number(data.last_payroll_run.total_net).toLocaleString() }}</span></div>
                <div class="flex justify-between"><span class="text-surface-500">Status</span><Tag class="capitalize" :value="data.last_payroll_run.status" /></div>
            </div>
            <div v-else class="text-surface-400 text-sm">No payroll runs yet.</div>
        </div>

        <!-- Recruitment Pipeline -->
        <div class="card">
            <h3 class="font-semibold text-lg mb-3">Recruitment Pipeline</h3>
            <div v-if="loading" class="text-surface-400">Loading...</div>
            <div v-else-if="pipelineStages.length">
                <div v-for="s in pipelineStages" :key="s.stage" class="flex justify-between items-center py-1 border-b border-surface-100 last:border-0">
                    <span class="capitalize text-surface-600">{{ s.stage }}</span>
                    <Tag class="capitalize" :value="String(s.count)" severity="info" />
                </div>
            </div>
            <div v-else class="text-surface-400 text-sm">No applicants yet.</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <!-- Upcoming Birthdays -->
        <div class="card">
            <h3 class="font-semibold text-lg mb-3">Upcoming Birthdays (7 days)</h3>
            <div v-if="loading" class="text-surface-400">Loading...</div>
            <div v-else-if="data?.upcoming_birthdays?.length">
                <div v-for="emp in data.upcoming_birthdays" :key="emp.id" class="flex justify-between py-1 border-b border-surface-100 last:border-0">
                    <span>{{ emp.first_name }} {{ emp.last_name }}</span>
                    <span class="text-surface-400 text-sm">{{ emp.date_of_birth?.slice(5) }}</span>
                </div>
            </div>
            <div v-else class="text-surface-400 text-sm">No upcoming birthdays.</div>
        </div>

        <!-- Pending Appraisals -->
        <div class="card">
            <h3 class="font-semibold text-lg mb-3">Pending Appraisals</h3>
            <div v-if="loading" class="text-surface-400">Loading...</div>
            <div v-else-if="data?.pending_appraisals?.length">
                <div v-for="a in data.pending_appraisals" :key="a.status" class="flex justify-between items-center py-1 border-b border-surface-100 last:border-0">
                    <span class="capitalize text-surface-600">{{ a.status.replace('_', ' ') }}</span>
                    <Tag class="capitalize" :value="String(a.count)" severity="warning" />
                </div>
            </div>
            <div v-else class="text-surface-400 text-sm">No pending appraisals.</div>
        </div>
    </div>
</template>
