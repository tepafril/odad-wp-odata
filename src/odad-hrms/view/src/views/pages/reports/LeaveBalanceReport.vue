<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';
import { LeaveTypeService } from '@/service/LeaveTypeService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ year: new Date().getFullYear(), department_id: '', leave_type_id: '' });

const departmentOptions = ref([]);
const leaveTypeOptions = ref([]);

onMounted(async () => {
    const [dRes, ltRes] = await Promise.allSettled([
        DepartmentService.getList({ per_page: 500 }),
        LeaveTypeService.getList({ per_page: 500, status: 'active' }),
    ]);
    if (dRes.status === 'fulfilled') {
        departmentOptions.value = (dRes.value?.items ?? []).map(d => ({ label: d.name, value: Number(d.id) }));
    }
    if (ltRes.status === 'fulfilled') {
        leaveTypeOptions.value = (ltRes.value?.items ?? []).map(t => ({ label: t.name, value: Number(t.id) }));
    }
    loadReport();
});

async function loadReport() {
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/leave-balance?${q}`);
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
    a.href = `${getBaseUrl()}/reports/leave-balance?${q}`;
    a.download = 'leave-balance.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Leave Balance Report</div>
                <p class="text-surface-500 m-0">Detailed leave balances for all employees by leave type.</p>
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
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Leave Type</label>
                <Select v-model="filters.leave_type_id" :options="leaveTypeOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">No data found.</div></template>
            <Column field="name" header="Employee" sortable style="min-width:12rem">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ data.name }}</div>
                        <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                    </div>
                </template>
            </Column>
            <Column field="department" header="Department" sortable style="min-width:10rem" />
            <Column field="leave_type" header="Leave Type" sortable style="min-width:10rem" />
            <Column field="total_allocated" header="Allocated" sortable style="min-width:6rem" />
            <Column field="carry_forwarded" header="Carried Fwd" sortable style="min-width:6rem" />
            <Column field="total_available" header="Available" sortable style="min-width:6rem" />
            <Column field="total_taken" header="Taken" sortable style="min-width:6rem" />
            <Column field="total_pending" header="Pending" sortable style="min-width:6rem" />
            <Column field="remaining" header="Remaining" sortable style="min-width:6rem">
                <template #body="{ data }">
                    <span :class="Number(data.remaining) < 0 ? 'text-red-500 font-semibold' : ''">{{ data.remaining }}</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
