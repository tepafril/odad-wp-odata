<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { AdditionalSalaryService } from '@/service/AdditionalSalaryService';

const router  = useRouter();
const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('payroll_date');
const sortOrder    = ref(-1);
const filterStatus = ref('');

const statusOptions = [
    { label: 'Draft',     value: 'draft' },
    { label: 'Approved',  value: 'approved' },
    { label: 'Rejected',  value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' },
];
const statusSeverity = { draft: 'secondary', approved: 'success', rejected: 'danger', cancelled: 'secondary' };

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus], load);

async function load() {
    loading.value = true;
    try {
        const data = await AdditionalSalaryService.getList({
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
function onSort(e) { sortField.value = e.sortField ?? 'payroll_date'; sortOrder.value = e.sortOrder ?? -1; }

function openEdit(row) { router.push({ name: 'additional-salary-edit', params: { id: row.id } }); }

function confirmApprove(row) {
    confirm.require({
        message: `Approve additional salary of ${row.amount} for Employee #${row.employee_id}?`,
        header: 'Approve',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await AdditionalSalaryService.approve(row.id);
                toast.add({ severity: 'success', detail: 'Approved.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function confirmReject(row) {
    confirm.require({
        message: `Reject this additional salary record?`,
        header: 'Reject',
        icon: 'pi pi-times-circle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await AdditionalSalaryService.reject(row.id);
                toast.add({ severity: 'success', detail: 'Rejected.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Additional Salary</div>
                <p class="text-surface-500 m-0">Manage bonuses, allowances, and one-time additions.</p>
            </div>
            <Button label="New" icon="pi pi-plus" @click="router.push({ name: 'additional-salary-create' })" />
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
            <template #empty><div class="text-center py-8 text-surface-500">No additional salary records found.</div></template>

            <Column field="id"                   header="ID"           sortable style="min-width:4rem" />
            <Column field="employee_id"          header="Employee"     style="min-width:7rem" />
            <Column field="salary_component_id"  header="Component"    style="min-width:8rem" />
            <Column field="amount"               header="Amount"       style="min-width:8rem">
                <template #body="{ data }">{{ Number(data.amount).toFixed(2) }}</template>
            </Column>
            <Column field="payroll_date"         header="Payroll Date" sortable style="min-width:9rem" />
            <Column field="status"               header="Status"       sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="data.status === 'draft'" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                        <Button v-if="data.status === 'draft'" icon="pi pi-check"
                            text rounded size="small" severity="success" @click="confirmApprove(data)" />
                        <Button v-if="['draft','approved'].includes(data.status)" icon="pi pi-times"
                            text rounded size="small" severity="danger" @click="confirmReject(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <ConfirmDialog />
    </div>
</template>
