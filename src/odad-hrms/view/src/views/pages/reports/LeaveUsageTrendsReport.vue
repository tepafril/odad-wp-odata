<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
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
        const res = await request(`reports/leave-usage-trends?${q}`);
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
    a.href = `${getBaseUrl()}/reports/leave-usage-trends?${q}`;
    a.download = 'leave-usage-trends.csv';
    a.click();
}

const chartColors = ['#22c55e', '#f97316', '#3b82f6', '#a855f7', '#ef4444', '#eab308', '#14b8a6'];

const chartData = computed(() => {
    if (!items.value.length) return null;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const types = [...new Set(items.value.map(r => r.leave_type))];
    const datasets = types.map((type, i) => ({
        label: type,
        backgroundColor: chartColors[i % chartColors.length],
        data: months.map((_, mi) => {
            const row = items.value.find(r => Number(r.month) === mi + 1 && r.leave_type === type);
            return row ? Number(row.total_days) : 0;
        }),
    }));
    return { labels: months, datasets };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: { y: { beginAtZero: true, title: { display: true, text: 'Days' } } },
};
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Leave Usage Trends</div>
                <p class="text-surface-500 m-0">Monthly leave consumption by type for the year.</p>
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

        <div v-if="chartData" class="mb-6" style="height:320px">
            <Chart type="bar" :data="chartData" :options="chartOptions" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No data found.</div></template>
            <Column field="month_name" header="Month" sortable style="min-width:6rem" />
            <Column field="leave_type" header="Leave Type" sortable style="min-width:10rem" />
            <Column field="total_days" header="Total Days" sortable style="min-width:8rem" />
        </DataTable>
    </div>
</template>
