<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { PayrollRunService } from '@/service/PayrollRunService';

const router  = useRouter();
const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('id');
const sortOrder    = ref(-1);

const statusSeverity = {
    draft:      'secondary',
    calculated: 'info',
    confirmed:  'warn',
    paid:       'success',
    cancelled:  'danger',
};

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await PayrollRunService.getList({
            page:     Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby:  sortField.value,
            order:    sortOrder.value === 1 ? 'ASC' : 'DESC',
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

function openDetail(row) { router.push({ name: 'payroll-run-detail', params: { id: row.id } }); }

async function doCalculate(row) {
    confirm.require({
        message: `Calculate payroll for "${row.name}"?`,
        header: 'Calculate Payroll',
        icon: 'pi pi-calculator',
        accept: async () => {
            try {
                await PayrollRunService.calculate(row.id);
                toast.add({ severity: 'success', detail: 'Payroll calculated.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

async function doConfirm(row) {
    confirm.require({
        message: `Confirm payroll run "${row.name}"?`,
        header: 'Confirm Payroll Run',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await PayrollRunService.confirm(row.id);
                toast.add({ severity: 'success', detail: 'Payroll run confirmed.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

async function doMarkPaid(row) {
    confirm.require({
        message: `Mark "${row.name}" as paid? This will create loan repayment records.`,
        header: 'Mark as Paid',
        icon: 'pi pi-money-bill',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await PayrollRunService.markPaid(row.id);
                toast.add({ severity: 'success', detail: 'Payroll run marked as paid.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Payroll Runs</div>
                <p class="text-surface-500 m-0">Process and manage payroll cycles.</p>
            </div>
            <Button label="New Payroll Run" icon="pi pi-plus" @click="router.push({ name: 'payroll-run-create' })" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No payroll runs found.</div></template>

            <Column field="id"              header="ID"               sortable style="min-width:4rem" />
            <Column field="name"            header="Name"             sortable style="min-width:12rem" />
            <Column field="period_start"    header="Period Start"     sortable style="min-width:9rem">
                <template #body="{ data }">{{ data.period_start_formatted || data.period_start }}</template>
            </Column>
            <Column field="period_end"      header="Period End"       sortable style="min-width:9rem">
                <template #body="{ data }">{{ data.period_end_formatted || data.period_end }}</template>
            </Column>
            <Column field="payment_date"    header="Payment Date"     style="min-width:9rem">
                <template #body="{ data }">{{ data.payment_date_formatted || data.payment_date }}</template>
            </Column>
            <Column field="total_employees" header="Employees"        style="min-width:7rem" />
            <Column field="total_gross"     header="Gross"            style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.total_gross).toFixed(2) }}</template>
            </Column>
            <Column field="total_net"       header="Net"              style="min-width:9rem">
                <template #body="{ data }">{{ Number(data.total_net).toFixed(2) }}</template>
            </Column>
            <Column field="status"          header="Status"           sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:14rem">
                <template #body="{ data }">
                    <div class="flex gap-1 flex-wrap">
                        <Button icon="pi pi-eye" text rounded size="small" @click="openDetail(data)" v-tooltip.top="'View'" />
                        <Button v-if="['draft','calculated'].includes(data.status)"
                            icon="pi pi-calculator" text rounded size="small" severity="info"
                            @click="doCalculate(data)" v-tooltip.top="'Calculate'" />
                        <Button v-if="data.status === 'calculated'"
                            icon="pi pi-check" text rounded size="small" severity="success"
                            @click="doConfirm(data)" v-tooltip.top="'Confirm'" />
                        <Button v-if="data.status === 'confirmed'"
                            icon="pi pi-money-bill" text rounded size="small" severity="success"
                            @click="doMarkPaid(data)" v-tooltip.top="'Mark Paid'" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <ConfirmDialog />
    </div>
</template>
