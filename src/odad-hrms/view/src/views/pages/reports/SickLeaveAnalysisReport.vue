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
        const res = await request(`reports/sick-leave-analysis?${q}`);
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
    a.href = `${getBaseUrl()}/reports/sick-leave-analysis?${q}`;
    a.download = 'sick-leave-analysis.csv';
    a.click();
}

const chartColors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6'];

const chartData = computed(() => {
    if (!items.value.length) return null;
    const sorted = [...items.value].sort((a, b) => Number(b.bradford_factor) - Number(a.bradford_factor)).slice(0, 15);
    return {
        labels: sorted.map(r => r.name),
        datasets: [{
            label: 'Bradford Factor',
            backgroundColor: sorted.map((_, i) => chartColors[i % chartColors.length]),
            data: sorted.map(r => Number(r.bradford_factor)),
        }],
    };
});

const chartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, title: { display: true, text: 'Bradford Factor (S² × D)' } } },
};

function bradfordSeverity(val) {
    const n = Number(val);
    if (n >= 500) return 'danger';
    if (n >= 200) return 'warn';
    if (n >= 50) return 'info';
    return 'success';
}
</script>

<template>
    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Sick Leave Analysis</div>
                <p class="text-surface-500 m-0">Bradford Factor scoring and sick leave patterns per employee.</p>
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

        <div v-if="chartData" class="mb-6" style="height:360px">
            <Chart type="bar" :data="chartData" :options="chartOptions" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm" sortField="bradford_factor" :sortOrder="-1">
            <template #empty><div class="text-center py-8 text-surface-500">No sick leave data found.</div></template>
            <Column field="name" header="Employee" sortable style="min-width:12rem">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.name }}</div>
                        <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                    </div>
                </template>
            </Column>
            <Column field="department" header="Department" sortable style="min-width:10rem" />
            <Column field="sick_days" header="Sick Days" sortable style="min-width:7rem" />
            <Column field="sick_instances" header="Instances" sortable style="min-width:7rem" />
            <Column field="planned_days" header="Planned Days" sortable style="min-width:8rem" />
            <Column field="total_leave_days" header="Total Leave" sortable style="min-width:8rem" />
            <Column field="sick_pct" header="Sick %" sortable style="min-width:6rem">
                <template #body="{ data }">{{ data.sick_pct }}%</template>
            </Column>
            <Column field="bradford_factor" header="Bradford Factor" sortable style="min-width:10rem">
                <template #body="{ data }">
                    <Tag :value="String(data.bradford_factor)" :severity="bradfordSeverity(data.bradford_factor)" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
