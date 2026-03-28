<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ department_id: '' });

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
        const res = await request(`reports/comp-off-balance?${q}`);
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
    a.href = `${getBaseUrl()}/reports/comp-off-balance?${q}`;
    a.download = 'comp-off-balance.csv';
    a.click();
}

function isExpiringSoon(dateStr) {
    if (!dateStr) return false;
    const diff = (new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 7;
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Comp-Off Balance Report</div>
                <p class="text-surface-500 m-0">Compensatory time earned from overtime and holiday work.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Department</label>
                <Select v-model="filters.department_id" :options="departmentOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No comp-off data found.</div></template>
            <Column field="name" header="Employee" sortable style="min-width:12rem">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.name }}</div>
                        <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                    </div>
                </template>
            </Column>
            <Column field="days_earned" header="Days Earned" sortable style="min-width:8rem" />
            <Column field="next_expiry" header="Next Expiry" sortable style="min-width:10rem">
                <template #body="{ data }">
                    <span v-if="data.next_expiry" :class="isExpiringSoon(data.next_expiry) ? 'text-red-500 font-semibold' : ''">
                        {{ data.next_expiry }}
                    </span>
                    <span v-else class="text-surface-400">-</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
