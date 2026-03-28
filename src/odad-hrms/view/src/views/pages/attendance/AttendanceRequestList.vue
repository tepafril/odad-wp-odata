<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { AttendanceService } from '@/service/AttendanceService';
import AttendanceRequestForm from './AttendanceRequestForm.vue';

const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('created_at');
const sortOrder    = ref(-1);
const filterStatus = ref('');
const dialogVisible = ref(false);
const formRef       = ref(null);

const statusOptions = [
    { label: 'Pending',  value: 'pending'  },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
];
const statusSeverity = { pending: 'warn', approved: 'success', rejected: 'danger' };
const typeLabel = {
    missing_checkin:  'Missing Check-In',
    missing_checkout: 'Missing Check-Out',
    wrong_time:       'Wrong Time',
    other:            'Other',
};

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus], load);

async function load() {
    loading.value = true;
    try {
        const data = await AttendanceService.getRequests({
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

function onSaved() { dialogVisible.value = false; load(); }
function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'created_at'; sortOrder.value = e.sortOrder ?? -1; }

function confirmApprove(row) {
    confirm.require({
        message: `Approve attendance request for ${row.date}?`,
        header:  'Approve Request',
        icon:    'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await AttendanceService.approveRequest(row.id);
                toast.add({ severity: 'success', detail: 'Request approved.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function confirmReject(row) {
    confirm.require({
        message: `Reject attendance request for ${row.date}?`,
        header:  'Reject Request',
        icon:    'pi pi-times-circle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await AttendanceService.rejectRequest(row.id);
                toast.add({ severity: 'success', detail: 'Request rejected.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Attendance Requests</div>
                <p class="text-surface-500 m-0">Review and approve attendance correction requests.</p>
            </div>
            <Button label="New Request" icon="pi pi-plus" @click="dialogVisible = true" />
        </div>

        <div class="mb-4 flex gap-3 items-center">
            <label class="font-medium text-sm">Status:</label>
            <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                placeholder="All" showClear style="min-width:10rem" @change="first = 0" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No attendance requests found.</div></template>

            <Column field="employee_id"  header="Employee"  sortable style="min-width:8rem" />
            <Column field="date"         header="Date"      sortable style="min-width:9rem">
                <template #body="{ data }">{{ data.date_formatted || data.date }}</template>
            </Column>
            <Column field="request_type" header="Type"      style="min-width:12rem">
                <template #body="{ data }">{{ typeLabel[data.request_type] ?? data.request_type }}</template>
            </Column>
            <Column field="reason"       header="Reason"    style="min-width:14rem">
                <template #body="{ data }">
                    <span class="text-sm text-surface-600">{{ data.reason }}</span>
                </template>
            </Column>
            <Column field="status"       header="Status"    sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button v-if="data.status === 'pending'" icon="pi pi-check"
                            text rounded size="small" severity="success" @click="confirmApprove(data)" />
                        <Button v-if="data.status === 'pending'" icon="pi pi-times"
                            text rounded size="small" severity="danger" @click="confirmReject(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" header="New Attendance Request"
            modal :style="{ width: '40rem' }" :dismissableMask="true">
            <AttendanceRequestForm ref="formRef" :model-value="dialogVisible"
                @update:model-value="dialogVisible = $event" @saved="onSaved" />
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Submit" icon="pi pi-check" @click="formRef?.save()" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
