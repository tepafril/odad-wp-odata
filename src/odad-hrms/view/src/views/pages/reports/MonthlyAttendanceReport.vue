<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const daysInMonth = ref(31);
const filters = ref({ year: new Date().getFullYear(), month: new Date().getMonth() + 1, department_id: '' });

const departmentOptions = ref([]);

const monthOptions = [
    { label: 'January', value: 1 }, { label: 'February', value: 2 }, { label: 'March', value: 3 },
    { label: 'April', value: 4 }, { label: 'May', value: 5 }, { label: 'June', value: 6 },
    { label: 'July', value: 7 }, { label: 'August', value: 8 }, { label: 'September', value: 9 },
    { label: 'October', value: 10 }, { label: 'November', value: 11 }, { label: 'December', value: 12 },
];

const dayColumns = computed(() => {
    const cols = [];
    for (let d = 1; d <= daysInMonth.value; d++) cols.push(d);
    return cols;
});

const statusLabel = { present: 'P', absent: 'A', on_leave: 'L', holiday: 'H', weekend: 'W' };
const statusClass = {
    present: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
    absent: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    on_leave: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
    holiday: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
    weekend: 'bg-surface-100 text-surface-400',
};

onMounted(async () => {
    const dRes = await DepartmentService.getList({ per_page: 500 }).catch(() => null);
    if (dRes) departmentOptions.value = (dRes.items ?? []).map(d => ({ label: d.name, value: Number(d.id) }));
    loadReport();
});

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/monthly-attendance?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
        daysInMonth.value = res.data?.days_in_month || 31;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    const q = new URLSearchParams();
    Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
    q.set('format', 'csv');
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/reports/monthly-attendance?${q}`;
    a.download = 'monthly-attendance.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Monthly Attendance Sheet</div>
                <p class="text-surface-500 m-0">Daily attendance status for each employee in a month.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :useGrouping="false" class="w-24" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Month</label>
                <Select v-model="filters.month" :options="monthOptions" optionLabel="label"
                    optionValue="value" style="width:10rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Department</label>
                <Select v-model="filters.department_id" :options="departmentOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <div class="overflow-x-auto">
            <table v-if="!loading" class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-surface-50">
                        <th class="border border-surface-200 px-2 py-1 text-left sticky left-0 bg-surface-50 z-10 min-w-[10rem]">Employee</th>
                        <th v-for="d in dayColumns" :key="d" class="border border-surface-200 px-1 py-1 text-center min-w-[2rem]">{{ d }}</th>
                        <th class="border border-surface-200 px-2 py-1 text-center">P</th>
                        <th class="border border-surface-200 px-2 py-1 text-center">A</th>
                        <th class="border border-surface-200 px-2 py-1 text-center">L</th>
                        <th class="border border-surface-200 px-2 py-1 text-center">H</th>
                        <th class="border border-surface-200 px-2 py-1 text-center">W</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in items" :key="row.employee_id" class="hover:bg-surface-50">
                        <td class="border border-surface-200 px-2 py-1 sticky left-0 bg-surface-0 z-10">
                            <div class="font-medium">{{ row.name }}</div>
                            <div class="text-surface-400">{{ row.employee_number }}</div>
                        </td>
                        <td v-for="d in dayColumns" :key="d" class="border border-surface-200 px-0 py-0 text-center">
                            <span v-if="row.days[d]"
                                :class="['inline-block w-full py-1 text-[10px] font-semibold', statusClass[row.days[d]] || '']">
                                {{ statusLabel[row.days[d]] || row.days[d]?.charAt(0)?.toUpperCase() }}
                            </span>
                            <span v-else class="text-surface-300">&mdash;</span>
                        </td>
                        <td class="border border-surface-200 px-2 py-1 text-center font-medium text-green-700 dark:text-green-400">{{ row.summary.present }}</td>
                        <td class="border border-surface-200 px-2 py-1 text-center font-medium text-red-700 dark:text-red-400">{{ row.summary.absent }}</td>
                        <td class="border border-surface-200 px-2 py-1 text-center font-medium text-blue-700 dark:text-blue-400">{{ row.summary.on_leave }}</td>
                        <td class="border border-surface-200 px-2 py-1 text-center font-medium text-orange-700 dark:text-orange-400">{{ row.summary.holiday }}</td>
                        <td class="border border-surface-200 px-2 py-1 text-center font-medium text-surface-500">{{ row.summary.weekend }}</td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td :colspan="dayColumns.length + 6" class="text-center py-8 text-surface-500">No data found.</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="flex justify-center py-12">
                <i class="pi pi-spin pi-spinner text-2xl text-surface-400"></i>
            </div>
        </div>
    </div>
</template>
