<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ payroll_run_id: '', from_date: '', to_date: '' });

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/payroll-summary?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

const totalGross = () => items.value.reduce((s, r) => s + Number(r.gross_pay || 0), 0);
const totalNet = () => items.value.reduce((s, r) => s + Number(r.net_pay || 0), 0);

function exportCsv() {
    const q = new URLSearchParams({ ...filters.value, format: 'csv' });
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/reports/payroll-summary?${q}`;
    a.download = 'payroll-summary.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Payroll Run ID</label>
                <InputText v-model="filters.payroll_run_id" placeholder="All" class="w-28" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">From Date</label>
                <InputText v-model="filters.from_date" type="date" class="w-36" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">To Date</label>
                <InputText v-model="filters.to_date" type="date" class="w-36" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <div v-if="items.length" class="flex gap-6 mb-3 text-sm text-surface-500">
            <span>Total Gross: <strong class="text-surface-700">{{ totalGross().toLocaleString() }}</strong></span>
            <span>Total Net: <strong class="text-green-600">{{ totalNet().toLocaleString() }}</strong></span>
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
            <Column field="name" header="Employee" sortable />
            <Column field="period_start" header="Period Start" sortable />
            <Column field="period_end" header="Period End" sortable />
            <Column field="gross_pay" header="Gross Pay" sortable>
                <template #body="{ data }">{{ Number(data.gross_pay).toLocaleString() }}</template>
            </Column>
            <Column field="total_deductions" header="Deductions" sortable>
                <template #body="{ data }">{{ Number(data.total_deductions).toLocaleString() }}</template>
            </Column>
            <Column field="net_pay" header="Net Pay" sortable>
                <template #body="{ data }"><span class="font-semibold text-green-600">{{ Number(data.net_pay).toLocaleString() }}</span></template>
            </Column>
        </DataTable>
    </div>
</template>
