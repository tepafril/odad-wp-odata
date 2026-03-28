<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ year: new Date().getFullYear(), leave_type_id: '' });

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/leave-summary?${q}`);
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
    a.href = `${getBaseUrl()}/reports/leave-summary?${q}`;
    a.download = 'leave-summary.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :use-grouping="false" class="w-24" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Leave Type ID</label>
                <InputText v-model="filters.leave_type_id" placeholder="All" class="w-28" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
            <Column field="name" header="Employee" sortable />
            <Column field="leave_type" header="Leave Type" sortable />
            <Column field="total_allocated" header="Allocated" sortable />
            <Column field="total_taken" header="Taken" sortable />
            <Column field="remaining" header="Remaining" sortable>
                <template #body="{ data }">
                    <span :class="Number(data.remaining) < 0 ? 'text-red-500 font-semibold' : ''">{{ data.remaining }}</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
