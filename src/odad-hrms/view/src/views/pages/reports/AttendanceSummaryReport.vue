<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const items = ref([]);

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = today.slice(0, 8) + '01';
const filters = ref({ from_date: firstOfMonth, to_date: today, department_id: '' });

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/attendance-summary?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    const q = new URLSearchParams({ ...filters.value, format: 'csv' });
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/reports/attendance-summary?${q}`;
    a.download = 'attendance-summary.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">From Date</label>
                <InputText v-model="filters.from_date" type="date" class="w-36" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">To Date</label>
                <InputText v-model="filters.to_date" type="date" class="w-36" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Department ID</label>
                <InputText v-model="filters.department_id" placeholder="All" class="w-28" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
            <Column field="name" header="Employee" sortable />
            <Column field="present_days" header="Present Days" sortable />
            <Column field="absent_days" header="Absent Days" sortable />
            <Column field="late_count" header="Late" sortable />
            <Column field="avg_hours" header="Avg Hours" sortable>
                <template #body="{ data }">{{ data.avg_hours ? Number(data.avg_hours).toFixed(2) : '—' }}</template>
            </Column>
        </DataTable>
    </div>
</template>
