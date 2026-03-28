<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { CompensatoryRequestService } from '@/service/CompensatoryRequestService';
import { EmployeeService } from '@/service/EmployeeService';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import CompensatoryRequestForm from './CompensatoryRequestForm.vue';

const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('created_at');
const sortOrder    = ref(-1);
const filterStatus    = ref('');
const filterEmployee  = ref(null);
const filterLeaveType = ref(null);
const search          = ref('');

const employeeOptions  = ref([]);
const leaveTypeOptions = ref([]);

let searchTimeout = null;
const dialogVisible = ref(false);
const formRef       = ref(null);

const statusOptions = [
    { label: 'Pending',  value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
];
const statusSeverity = { pending: 'warn', approved: 'success', rejected: 'danger' };

function employeeName(row) {
    const name = [row.employee_first_name, row.employee_last_name].filter(Boolean).join(' ');
    return name || `#${row.employee_id}`;
}

function employeeGenderLabel(row) {
    if (!row.employee_gender) return '';
    return row.employee_gender.charAt(0).toUpperCase();
}

onMounted(async () => {
    const [empRes, ltRes] = await Promise.allSettled([
        EmployeeService.getList({ per_page: 500, status: 'active' }),
        LeaveTypeService.getList({ per_page: 500, status: 'active' }),
    ]);
    if (empRes.status === 'fulfilled') {
        employeeOptions.value = (empRes.value?.items ?? []).map(e => ({
            label: [e.first_name, e.last_name].filter(Boolean).join(' ') || `#${e.id}`,
            value: Number(e.id),
        }));
    }
    if (ltRes.status === 'fulfilled') {
        leaveTypeOptions.value = (ltRes.value?.items ?? []).map(t => ({ label: t.name, value: Number(t.id) }));
    }
    load();
});
watch([first, rows, sortField, sortOrder, filterStatus, filterEmployee, filterLeaveType], load);

function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { first.value = 0; load(); }, 400);
}

async function load() {
    loading.value = true;
    try {
        const data = await CompensatoryRequestService.getList({
            page: Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby: sortField.value,
            order: sortOrder.value === 1 ? 'ASC' : 'DESC',
            status: filterStatus.value || undefined,
            employee_id: filterEmployee.value || undefined,
            leave_type_id: filterLeaveType.value || undefined,
            search: search.value.trim() || undefined,
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function openNew() { dialogVisible.value = true; }
function onSaved() { dialogVisible.value = false; load(); }
function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'created_at'; sortOrder.value = e.sortOrder ?? -1; }

function confirmApprove(row) {
    confirm.require({
        message: `Approve comp-off request for ${employeeName(row)}? This will credit ${row.days} day(s) to their leave balance.`,
        header: 'Approve Request',
        icon: 'pi pi-check-circle',
        accept: async () => {
            try {
                await CompensatoryRequestService.approve(row.id);
                toast.add({ severity: 'success', detail: 'Compensatory request approved.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function confirmReject(row) {
    confirm.require({
        message: `Reject comp-off request for ${employeeName(row)}?`,
        header: 'Reject Request',
        icon: 'pi pi-times-circle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await CompensatoryRequestService.reject(row.id);
                toast.add({ severity: 'success', detail: 'Compensatory request rejected.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Compensatory Requests</div>
                <p class="text-surface-500 m-0">Manage comp-off requests for employees who worked extra.</p>
            </div>
            <Button label="New Request" icon="pi pi-plus" @click="openNew" />
        </div>

        <div class="mb-4 flex flex-wrap gap-4 items-end">
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Search</label>
                <InputText v-model="search" placeholder="Name or employee ID..." @input="onSearch"
                    style="width:16rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Leave Type</label>
                <Select v-model="filterLeaveType" :options="leaveTypeOptions" optionLabel="label" optionValue="value"
                    placeholder="All" showClear style="width:14rem" @update:modelValue="first = 0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Status</label>
                <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                    placeholder="All" showClear style="width:10rem" @update:modelValue="first = 0" />
            </div>
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No compensatory requests found.</div></template>

            <Column field="id"          header="ID"       sortable style="min-width:5rem" />
            <Column field="employee_id" header="Employee" sortable style="min-width:14rem">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ employeeName(data) }} ({{ employeeGenderLabel(data) }})</div>
                        <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                    </div>
                </template>
            </Column>
            <Column field="leave_type_id" header="Leave Type" sortable style="min-width:10rem">
                <template #body="{ data }">{{ data.leave_type_name || data.leave_type_id }}</template>
            </Column>
            <Column field="work_date"  header="Work Date" sortable style="min-width:9rem">
                <template #body="{ data }">{{ data.work_date_formatted || data.work_date }}</template>
            </Column>
            <Column field="days"       header="Days"      style="min-width:5rem" />
            <Column field="created_at" header="Requested"  sortable style="min-width:10rem">
                <template #body="{ data }">{{ data.created_at_formatted || data.created_at }}</template>
            </Column>
            <Column field="status"     header="Status"    sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:10rem">
                <template #body="{ data }">
                    <div v-if="data.status === 'pending'" class="flex gap-1">
                        <Button icon="pi pi-check" text rounded size="small" severity="success"
                            v-tooltip.top="'Approve'" @click="confirmApprove(data)" />
                        <Button icon="pi pi-times" text rounded size="small" severity="danger"
                            v-tooltip.top="'Reject'" @click="confirmReject(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            header="New Compensatory Request"
            modal :style="{ width: '46rem' }" :dismissableMask="true">
            <CompensatoryRequestForm ref="formRef" :model-value="dialogVisible"
                @update:model-value="dialogVisible = $event" @saved="onSaved" />
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Submit" icon="pi pi-check" @click="formRef?.save()" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
