<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { LoanService } from '@/service/LoanService';

const router  = useRouter();
const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('disbursement_date');
const sortOrder    = ref(-1);
const filterStatus = ref('');

const statusOptions = [
    { label: 'Draft',     value: 'draft' },
    { label: 'Approved',  value: 'approved' },
    { label: 'Repaying',  value: 'repaying' },
    { label: 'Settled',   value: 'settled' },
    { label: 'Cancelled', value: 'cancelled' },
];
const statusSeverity = {
    draft: 'secondary', approved: 'info', disbursed: 'warn',
    repaying: 'warn', settled: 'success', cancelled: 'danger',
};

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus], load);

async function load() {
    loading.value = true;
    try {
        const data = await LoanService.getList({
            page:     Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby:  sortField.value,
            order:    sortOrder.value === 1 ? 'ASC' : 'DESC',
            status:   filterStatus.value || undefined,
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
function onSort(e) { sortField.value = e.sortField ?? 'disbursement_date'; sortOrder.value = e.sortOrder ?? -1; }

function openEdit(row) { router.push({ name: 'loan-edit', params: { id: row.id } }); }

function doApprove(row) {
    confirm.require({
        message: `Approve loan of ${row.amount} for Employee #${row.employee_id}?`,
        header: 'Approve Loan',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await LoanService.approve(row.id);
                toast.add({ severity: 'success', detail: 'Loan approved.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function doDisburse(row) {
    confirm.require({
        message: `Mark loan as disbursed/repaying for Employee #${row.employee_id}?`,
        header: 'Disburse Loan',
        icon: 'pi pi-money-bill',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await LoanService.disburse(row.id);
                toast.add({ severity: 'success', detail: 'Loan disbursed.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Employee Loans</div>
                <p class="text-surface-500 m-0">Manage employee loan applications and repayments.</p>
            </div>
            <Button label="New Loan" icon="pi pi-plus" @click="router.push({ name: 'loan-create' })" />
        </div>

        <div class="mb-4 flex gap-3 items-center">
            <label class="font-medium text-sm">Status:</label>
            <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                placeholder="All" showClear style="min-width:10rem" @change="first = 0" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5,10,25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No loans found.</div></template>

            <Column field="id"                  header="ID"          sortable style="min-width:4rem" />
            <Column field="employee_id"         header="Employee"    style="min-width:7rem" />
            <Column field="loan_type"           header="Type"        style="min-width:10rem" />
            <Column field="amount"              header="Amount"      style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.amount).toFixed(2) }}</template>
            </Column>
            <Column field="outstanding_balance" header="Balance"     style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.outstanding_balance).toFixed(2) }}</template>
            </Column>
            <Column field="disbursement_date"   header="Disbursed"   sortable style="min-width:9rem" />
            <Column field="status"              header="Status"      sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:12rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="['draft','approved'].includes(data.status)"
                            icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                        <Button v-if="data.status === 'draft'"
                            icon="pi pi-check" text rounded size="small" severity="success"
                            @click="doApprove(data)" v-tooltip.top="'Approve'" />
                        <Button v-if="data.status === 'approved'"
                            icon="pi pi-money-bill" text rounded size="small" severity="info"
                            @click="doDisburse(data)" v-tooltip.top="'Disburse'" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <ConfirmDialog />
    </div>
</template>
