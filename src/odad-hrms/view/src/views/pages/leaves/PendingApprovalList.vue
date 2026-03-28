<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { LeaveApplicationService } from '@/service/LeaveApplicationService';
import { EmployeeService } from '@/service/EmployeeService';
import { LeaveTypeService } from '@/service/LeaveTypeService';

const toast   = useToast();
const confirm = useConfirm();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('created_at');
const sortOrder    = ref(-1);
const filterEmployee  = ref(null);
const filterLeaveType = ref(null);
const search          = ref('');

const employeeOptions  = ref([]);
const leaveTypeOptions = ref([]);

let searchTimeout = null;

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
watch([first, rows, sortField, sortOrder, filterEmployee, filterLeaveType], load);

function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { first.value = 0; load(); }, 400);
}

async function load() {
    loading.value = true;
    try {
        const data = await LeaveApplicationService.getList({
            page: Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby: sortField.value,
            order: sortOrder.value === 1 ? 'ASC' : 'DESC',
            status: 'pending',
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

function onPage(e)  { first.value = e.first; rows.value = e.rows; }
function onSort(e)  { sortField.value = e.sortField ?? 'created_at'; sortOrder.value = e.sortOrder ?? -1; }

function confirmApprove(row) {
    confirm.require({
        message: `Approve leave for ${employeeName(row)}?`,
        header: 'Approve Leave',
        icon: 'pi pi-check-circle',
        acceptClass: 'p-button-success',
        accept: async () => {
            try {
                await LeaveApplicationService.approve(row.id);
                toast.add({ severity: 'success', detail: 'Leave approved.', life: 3000 });
                load();
                window.dispatchEvent(new Event('wphr:pending-changed'));
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function confirmReject(row) {
    confirm.require({
        message: `Reject leave for ${employeeName(row)}?`,
        header: 'Reject Leave',
        icon: 'pi pi-times-circle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await LeaveApplicationService.reject(row.id);
                toast.add({ severity: 'success', detail: 'Leave rejected.', life: 3000 });
                load();
                window.dispatchEvent(new Event('wphr:pending-changed'));
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
                <div class="font-semibold text-xl mb-1">Pending Approval</div>
                <p class="text-surface-500 m-0">Review and approve or reject pending leave requests.</p>
            </div>
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
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No pending leave requests.</div></template>

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
            <Column field="from_date" header="Period" sortable style="min-width:12rem">
                <template #body="{ data }">{{ data.from_date_formatted || data.from_date }} ~ {{ data.to_date_formatted || data.to_date }}</template>
            </Column>
            <Column field="total_days"  header="Days"     sortable style="min-width:6rem" />
            <Column field="created_at"  header="Requested At" sortable style="min-width:10rem">
                <template #body="{ data }">{{ data.created_at_formatted || data.created_at }}</template>
            </Column>
            <Column header="Actions" style="min-width:10rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button
                            icon="pi pi-check"
                            size="small"
                            severity="success"
                            v-tooltip.top="'Approve'"
                            @click="confirmApprove(data)"
                            class="!w-[32px] !h-[32px]"
                        />
                        <Button
                            icon="pi pi-times"
                            outlined
                            size="small"
                            severity="danger"
                            v-tooltip.top="'Reject'"
                            @click="confirmReject(data)"
                            class="!w-[32px] !h-[32px]"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <ConfirmDialog />
    </div>
</template>
