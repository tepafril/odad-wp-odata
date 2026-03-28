<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { DepartmentService } from '@/service/DepartmentService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ date: new Date().toISOString().slice(0, 10), department_id: '', status: '' });

const departmentOptions = ref([]);
const statusOptions = [
    { label: 'Present', value: 'present' },
    { label: 'Absent', value: 'absent' },
    { label: 'On Leave', value: 'on_leave' },
    { label: 'Holiday', value: 'holiday' },
    { label: 'Weekend', value: 'weekend' },
];

const statusSeverity = { present: 'success', absent: 'danger', on_leave: 'info', holiday: 'warn', weekend: 'secondary' };

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
        const res = await request(`reports/daily-work?${q}`);
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
    a.href = `${getBaseUrl()}/reports/daily-work?${q}`;
    a.download = 'daily-work.csv';
    a.click();
}

function timeOnly(dt) {
    if (!dt) return '';
    return dt.length > 10 ? dt.slice(11, 16) : dt;
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Daily Work Summary</div>
                <p class="text-surface-500 m-0">Check-in/check-out details and hours worked for a specific date.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Date</label>
                <InputText type="date" v-model="filters.date" style="width:10rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Department</label>
                <Select v-model="filters.department_id" :options="departmentOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:14rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Status</label>
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label"
                    optionValue="value" placeholder="All" showClear style="width:10rem" />
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
            <Column field="status" header="Status" sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag v-if="data.status" :value="data.status.replace('_', ' ')" :severity="statusSeverity[data.status] || 'secondary'" />
                    <span v-else class="text-surface-400 text-xs">No Record</span>
                </template>
            </Column>
            <Column field="check_in" header="Check In" sortable style="min-width:6rem">
                <template #body="{ data }">{{ timeOnly(data.check_in) }}</template>
            </Column>
            <Column field="check_out" header="Check Out" sortable style="min-width:6rem">
                <template #body="{ data }">{{ timeOnly(data.check_out) }}</template>
            </Column>
            <Column field="total_working_hours" header="Hours" sortable style="min-width:5rem">
                <template #body="{ data }">{{ data.total_working_hours ? Number(data.total_working_hours).toFixed(1) : '' }}</template>
            </Column>
            <Column field="late_entry" header="Late" sortable style="min-width:4rem">
                <template #body="{ data }">
                    <Tag v-if="Number(data.late_entry)" value="Late" severity="warn" />
                </template>
            </Column>
            <Column field="early_exit" header="Early Exit" sortable style="min-width:5rem">
                <template #body="{ data }">
                    <Tag v-if="Number(data.early_exit)" value="Early" severity="warn" />
                </template>
            </Column>
            <Column field="overtime_hours" header="OT" sortable style="min-width:4rem">
                <template #body="{ data }">{{ data.overtime_hours ? Number(data.overtime_hours).toFixed(1) : '' }}</template>
            </Column>
        </DataTable>
    </div>
</template>
