<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ group_by: 'department_id', company_id: '', department_id: '' });

const groupByOptions = [
    { label: 'Department', value: 'department_id' },
    { label: 'Designation', value: 'designation_id' },
    { label: 'Company', value: 'company_id' },
    { label: 'Employment Status', value: 'employment_status' },
];

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/headcount?${q}`);
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
    a.href = `${getBaseUrl()}/reports/headcount?${q}`;
    a.download = 'headcount-report.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Group By</label>
                <Dropdown v-model="filters.group_by" :options="groupByOptions" option-label="label" option-value="value" class="w-44" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
            <Column field="group_by" :header="filters.group_by.replace('_id','').replace('_',' ')" sortable>
                <template #body="{ data }">{{ data[filters.group_by] ?? '—' }}</template>
            </Column>
            <Column field="count" header="Count" sortable />
        </DataTable>
    </div>
</template>
