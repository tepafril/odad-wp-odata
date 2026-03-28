<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ year: new Date().getFullYear() });

onMounted(() => loadReport());

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/holiday-impact?${q}`);
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
    a.href = `${getBaseUrl()}/reports/holiday-impact?${q}`;
    a.download = 'holiday-impact.csv';
    a.click();
}

const impactSeverity = { low: 'success', medium: 'info', high: 'warn', critical: 'danger' };
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Holiday Impact Analysis</div>
                <p class="text-surface-500 m-0">Leave requests surrounding holidays (3-day window) and their impact level.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :useGrouping="false" class="w-24" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No holiday data found.</div></template>
            <Column field="holiday_name" header="Holiday" sortable style="min-width:12rem" />
            <Column field="holiday_date" header="Date" sortable style="min-width:9rem" />
            <Column field="day_of_week" header="Day" style="min-width:7rem" />
            <Column field="leave_requests" header="Leave Requests" sortable style="min-width:10rem" />
            <Column field="employees_affected" header="Employees" sortable style="min-width:8rem" />
            <Column field="total_leave_days" header="Leave Days" sortable style="min-width:8rem" />
            <Column field="impact" header="Impact" sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag :value="data.impact" :severity="impactSeverity[data.impact] || 'secondary'" class="capitalize" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
