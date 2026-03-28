<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';
import { EmployeeService } from '@/service/EmployeeService';

const toast = useToast();
const loading = ref(false);
const items = ref([]);
const filters = ref({ employee_id: '', year: new Date().getFullYear() });

const employeeOptions = ref([]);

const statusSeverity = { approved: 'success', pending: 'warn', rejected: 'danger', cancelled: 'secondary' };

onMounted(async () => {
    const eRes = await EmployeeService.getList({ per_page: 500, status: 'active' }).catch(() => null);
    if (eRes) {
        employeeOptions.value = (eRes.items ?? []).map(e => ({
            label: [e.first_name, e.last_name].filter(Boolean).join(' ') || `#${e.id}`,
            value: Number(e.id),
        }));
    }
});

async function loadReport() {
    if (!filters.value.employee_id) {
        toast.add({ severity: 'warn', summary: 'Select Employee', detail: 'Please select an employee first.', life: 3000 });
        return;
    }
    loading.value = true;
    try {
        const q = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
        const res = await request(`reports/employee-leave-history?${q}`);
        if (res.error) throw new Error(res.error.message);
        items.value = res.data?.items || [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    if (!filters.value.employee_id) return;
    const q = new URLSearchParams();
    Object.entries(filters.value).forEach(([k, v]) => { if (v !== '' && v != null) q.set(k, v); });
    q.set('format', 'csv');
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/reports/employee-leave-history?${q}`;
    a.download = 'employee-leave-history.csv';
    a.click();
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Employee Leave History</div>
                <p class="text-surface-500 m-0">Chronological leave record for an individual employee.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Employee</label>
                <Select v-model="filters.employee_id" :options="employeeOptions" optionLabel="label"
                    optionValue="value" placeholder="Select employee..." filter style="width:18rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Year</label>
                <InputNumber v-model="filters.year" :useGrouping="false" class="w-24" />
            </div>
            <Button label="Apply" icon="pi pi-search" @click="loadReport" />
            <Button label="Export CSV" icon="pi pi-download" severity="secondary" @click="exportCsv" :disabled="!filters.employee_id" />
        </div>

        <DataTable :value="items" :loading="loading" paginator :rows="20" stripedRows showGridlines
            responsiveLayout="scroll" class="text-sm">
            <template #empty><div class="text-center py-8 text-surface-500">{{ filters.employee_id ? 'No leave records found.' : 'Select an employee to view their leave history.' }}</div></template>
            <Column field="from_date" header="Period" sortable style="min-width:12rem">
                <template #body="{ data }">{{ data.from_date }} ~ {{ data.to_date }}</template>
            </Column>
            <Column field="leave_type" header="Leave Type" sortable style="min-width:10rem" />
            <Column field="total_days" header="Days" sortable style="min-width:5rem" />
            <Column field="half_day" header="Half Day" sortable style="min-width:6rem">
                <template #body="{ data }">
                    <span v-if="Number(data.half_day)">{{ data.half_day_period || 'Yes' }}</span>
                </template>
            </Column>
            <Column field="reason" header="Reason" style="min-width:14rem">
                <template #body="{ data }">
                    <span v-tooltip.top="data.reason">{{ data.reason?.length > 40 ? data.reason.slice(0, 40) + '...' : data.reason }}</span>
                </template>
            </Column>
            <Column field="status" header="Status" sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="statusSeverity[data.status] || 'secondary'" />
                </template>
            </Column>
            <Column field="created_at" header="Applied On" sortable style="min-width:10rem" />
        </DataTable>
    </div>
</template>
