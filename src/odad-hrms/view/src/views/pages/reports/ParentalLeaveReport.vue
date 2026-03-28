<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const summary = ref({ active: 0, upcoming: 0, returned: 0 });
const filters = ref({ year: new Date().getFullYear(), department_id: '' });

const departmentOptions = ref([]);

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
        const res = await request(`reports/parental-leave?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
        summary.value = res.data?.summary || { active: 0, upcoming: 0, returned: 0 };
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
    a.href = `${getBaseUrl()}/reports/parental-leave?${q}`;
    a.download = 'parental-leave.csv';
    a.click();
}

const statusSeverity = { active: 'warn', upcoming: 'info', returned: 'success' };

const chartData = computed(() => {
    const s = summary.value;
    if (!s.active && !s.upcoming && !s.returned) return null;
    return {
        labels: ['Active', 'Upcoming', 'Returned'],
        datasets: [{
            data: [s.active, s.upcoming, s.returned],
            backgroundColor: ['#f97316', '#3b82f6', '#22c55e'],
        }],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Parental Leave Tracking</div>
                <p class="text-surface-500 m-0">Track maternity/paternity leave status across the organization.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :useGrouping="false" class="w-24" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Department</label>
                <Select v-model="filters.department_id" :options="departmentOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex-1 min-w-[8rem] bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-orange-600">{{ summary.active }}</div>
                <div class="text-sm text-surface-500">Currently Active</div>
            </div>
            <div class="flex-1 min-w-[8rem] bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ summary.upcoming }}</div>
                <div class="text-sm text-surface-500">Upcoming</div>
            </div>
            <div class="flex-1 min-w-[8rem] bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ summary.returned }}</div>
                <div class="text-sm text-surface-500">Returned</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div v-if="chartData" class="lg:col-span-1" style="height:240px">
                <Chart type="doughnut" :data="chartData" :options="chartOptions" />
            </div>
            <div :class="chartData ? 'lg:col-span-2' : 'lg:col-span-3'">
                <DataTable :value="items" :loading="loading" paginator :rows="15" stripedRows showGridlines
                    responsiveLayout="scroll" class="text-sm">
                    <template #empty><div class="text-center py-8 text-surface-500">No parental leave records found.</div></template>
                    <Column field="name" header="Employee" sortable style="min-width:12rem">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.name }}</div>
                                <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                            </div>
                        </template>
                    </Column>
                    <Column field="department" header="Department" sortable style="min-width:9rem" />
                    <Column field="gender" header="Gender" style="min-width:6rem">
                        <template #body="{ data }">
                            <span class="capitalize">{{ data.gender || '-' }}</span>
                        </template>
                    </Column>
                    <Column field="leave_type" header="Leave Type" sortable style="min-width:9rem" />
                    <Column field="from_date" header="From" sortable style="min-width:8rem" />
                    <Column field="to_date" header="To" sortable style="min-width:8rem" />
                    <Column field="total_days" header="Days" sortable style="min-width:5rem" />
                    <Column field="leave_status" header="Status" sortable style="min-width:8rem">
                        <template #body="{ data }">
                            <Tag :value="data.leave_status" :severity="statusSeverity[data.leave_status] || 'secondary'" class="capitalize" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
