<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { LeaveTypeService } from '@/service/LeaveTypeService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ year: new Date().getFullYear(), leave_type_id: '' });

const leaveTypeOptions = ref([]);

onMounted(async () => {
    const ltRes = await LeaveTypeService.getList({ per_page: 500, status: 'active' }).catch(() => null);
    if (ltRes) leaveTypeOptions.value = (ltRes.items ?? []).map(t => ({ label: t.name, value: Number(t.id) }));
    loadReport();
});

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/leave-department-distribution?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
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
    a.href = `${getBaseUrl()}/reports/leave-department-distribution?${q}`;
    a.download = 'leave-department-distribution.csv';
    a.click();
}

const chartColors = ['#3b82f6', '#22c55e', '#f97316', '#a855f7', '#ef4444', '#eab308', '#14b8a6', '#ec4899'];

const chartData = computed(() => {
    if (!items.value.length) return null;
    return {
        labels: items.value.map(r => r.department),
        datasets: [{
            data: items.value.map(r => Number(r.total_days)),
            backgroundColor: items.value.map((_, i) => chartColors[i % chartColors.length]),
        }],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'right' } },
};
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Leave by Department</div>
                <p class="text-surface-500 m-0">Leave utilization breakdown by department.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :useGrouping="false" class="w-24" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Leave Type</label>
                <Select v-model="filters.leave_type_id" :options="leaveTypeOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <div v-if="chartData" class="mb-6 flex justify-center" style="height:320px">
            <Chart type="doughnut" :data="chartData" :options="chartOptions" />
        </div>

        <DataTable :value="items" :loading="loading" stripedRows showGridlines responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No data found.</div></template>
            <Column field="department" header="Department" sortable style="min-width:12rem" />
            <Column field="employee_count" header="Employees" sortable style="min-width:8rem" />
            <Column field="total_days" header="Total Days" sortable style="min-width:8rem" />
            <Column header="Avg Days/Employee" style="min-width:10rem">
                <template #body="{ data }">
                    {{ data.employee_count > 0 ? (Number(data.total_days) / Number(data.employee_count)).toFixed(1) : '0' }}
                </template>
            </Column>
        </DataTable>
    </div>
</template>
