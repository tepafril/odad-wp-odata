<script setup>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const today = new Date();
const firstOfMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
const todayStr = today.toISOString().slice(0, 10);
const filters = ref({ from_date: firstOfMonth, to_date: todayStr, group_by: 'date', department_id: '' });

const departmentOptions = ref([]);
const groupByOptions = [
    { label: 'By Date', value: 'date' },
    { label: 'By Weekday', value: 'weekday' },
    { label: 'By Month', value: 'month' },
];

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
        const res = await request(`reports/absenteeism-rate?${q}`);
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
    a.href = `${getBaseUrl()}/reports/absenteeism-rate?${q}`;
    a.download = 'absenteeism-rate.csv';
    a.click();
}

const chartData = computed(() => {
    if (!items.value.length) return null;
    return {
        labels: items.value.map(r => r.label),
        datasets: [
            {
                label: 'Absence Rate %',
                backgroundColor: '#ef4444',
                data: items.value.map(r => Number(r.absence_rate)),
                type: 'line',
                borderColor: '#ef4444',
                tension: 0.3,
                fill: false,
                yAxisID: 'y1',
            },
            {
                label: 'Absent',
                backgroundColor: '#f97316',
                data: items.value.map(r => Number(r.absent_count)),
            },
            {
                label: 'Present',
                backgroundColor: '#22c55e',
                data: items.value.map(r => Number(r.present_count)),
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
        y: { beginAtZero: true, title: { display: true, text: 'Count' }, stacked: true },
        y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Rate %' }, grid: { drawOnChartArea: false } },
        x: { stacked: true },
    },
};
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Absenteeism Rate</div>
                <p class="text-surface-500 m-0">Track absence patterns by date, weekday, or month.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">From</label>
                <InputText v-model="filters.from_date" type="date" style="width:10rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">To</label>
                <InputText v-model="filters.to_date" type="date" style="width:10rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Group By</label>
                <Select v-model="filters.group_by" :options="groupByOptions" optionLabel="label"
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

        <div v-if="chartData" class="mb-6" style="height:320px">
            <Chart type="bar" :data="chartData" :options="chartOptions" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="31" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No attendance data found.</div></template>
            <Column field="label" header="Period" sortable style="min-width:10rem" />
            <Column field="present_count" header="Present" sortable style="min-width:7rem" />
            <Column field="absent_count" header="Absent" sortable style="min-width:7rem" />
            <Column field="total_records" header="Total Records" sortable style="min-width:8rem" />
            <Column field="total_employees" header="Employees" style="min-width:8rem" />
            <Column field="absence_rate" header="Absence Rate" sortable style="min-width:9rem">
                <template #body="{ data }">
                    <span :class="Number(data.absence_rate) >= 10 ? 'text-red-500 font-semibold' : ''">
                        {{ data.absence_rate }}%
                    </span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
