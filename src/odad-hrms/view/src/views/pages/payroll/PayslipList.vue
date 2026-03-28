<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { PayslipService } from '@/service/PayslipService';

const router = useRouter();
const toast  = useToast();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('id');
const sortOrder    = ref(-1);

const filterStatus      = ref('');
const filterEmployeeId  = ref('');
const filterRunId       = ref('');

const statusOptions = [
    { label: 'Draft',     value: 'draft' },
    { label: 'Confirmed', value: 'confirmed' },
    { label: 'Paid',      value: 'paid' },
    { label: 'Cancelled', value: 'cancelled' },
];
const statusSeverity = { draft: 'secondary', confirmed: 'warn', paid: 'success', cancelled: 'danger' };

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus, filterEmployeeId, filterRunId], load);

async function load() {
    loading.value = true;
    try {
        const data = await PayslipService.getList({
            page:           Math.floor(first.value / rows.value) + 1,
            per_page:       rows.value,
            orderby:        sortField.value,
            order:          sortOrder.value === 1 ? 'ASC' : 'DESC',
            status:         filterStatus.value || undefined,
            employee_id:    filterEmployeeId.value || undefined,
            payroll_run_id: filterRunId.value || undefined,
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'id'; sortOrder.value = e.sortOrder ?? -1; }

function openDetail(row) { router.push({ name: 'payslip-detail', params: { id: row.id } }); }

async function sendEmail(row) {
    try {
        await PayslipService.sendEmail(row.id);
        toast.add({ severity: 'success', detail: 'Email sent.', life: 3000 });
        load();
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Payslips</div>
                <p class="text-surface-500 m-0">View and manage employee payslips.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4 items-end">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Status</label>
                <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="All" showClear style="min-width:10rem" @change="first = 0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Employee ID</label>
                <InputText v-model="filterEmployeeId" placeholder="Filter by employee" style="width:10rem" @input="first = 0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Payroll Run ID</label>
                <InputText v-model="filterRunId" placeholder="Filter by run" style="width:10rem" @input="first = 0" />
            </div>
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5,10,25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No payslips found.</div></template>

            <Column field="id"              header="ID"         sortable style="min-width:4rem" />
            <Column field="employee_id"     header="Employee"   sortable style="min-width:7rem" />
            <Column field="period_start"    header="From"       style="min-width:8rem" />
            <Column field="period_end"      header="To"         style="min-width:8rem" />
            <Column field="gross_pay"       header="Gross"      style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.gross_pay).toFixed(2) }}</template>
            </Column>
            <Column field="net_pay"         header="Net"        style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.net_pay).toFixed(2) }}</template>
            </Column>
            <Column field="status"          header="Status"     sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column field="email_sent"      header="Email"      style="min-width:6rem">
                <template #body="{ data }">
                    <i :class="parseInt(data.email_sent) ? 'pi pi-check text-green-500' : 'pi pi-times text-surface-300'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" text rounded size="small" @click="openDetail(data)" v-tooltip.top="'View'" />
                        <Button v-if="['confirmed','paid'].includes(data.status)"
                            icon="pi pi-envelope" text rounded size="small" @click="sendEmail(data)"
                            v-tooltip.top="'Send Email'" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
