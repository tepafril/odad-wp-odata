<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { LeaveBalanceService } from '@/service/LeaveBalanceService';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const toast = useToast();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('leave_type_id');
const sortOrder    = ref(1);
const filterYear       = ref(new Date().getFullYear());
const filterLeaveType  = ref(null);
const search           = ref('');
const adjustDialog = ref(false);
const adjustRow    = ref(null);
const adjustValue  = ref(0);
const adjustSaving = ref(false);

const yearOptions      = ref([]);
const leaveTypeMap     = ref({});
const leaveTypeOptions = ref([]);

let searchTimeout = null;

onMounted(async () => {
    const [ltRes, yearsRes] = await Promise.allSettled([
        LeaveTypeService.getList({ per_page: 500, status: 'active' }),
        LeaveBalanceService.getYears(),
    ]);

    if (ltRes.status === 'fulfilled') {
        for (const t of ltRes.value?.items ?? []) {
            leaveTypeMap.value[t.id] = t.name;
        }
        leaveTypeOptions.value = (ltRes.value?.items ?? []).map(t => ({ label: t.name, value: Number(t.id) }));
        if (leaveTypeOptions.value.length) {
            filterLeaveType.value = leaveTypeOptions.value[0].value;
        }
    }
    if (yearsRes.status === 'fulfilled') {
        const years = yearsRes.value ?? [];
        yearOptions.value = years.map(y => ({ label: String(y), value: y }));
        if (years.length && !years.includes(filterYear.value)) {
            filterYear.value = years[0];
        }
    }

    load();
});
watch([first, rows, sortField, sortOrder, filterYear, filterLeaveType], load);

function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { first.value = 0; load(); }, 400);
}

async function load() {
    loading.value = true;
    try {
        const data = await LeaveBalanceService.getList({
            page:          Math.floor(first.value / rows.value) + 1,
            per_page:      rows.value,
            orderby:       sortField.value,
            order:         sortOrder.value === 1 ? 'ASC' : 'DESC',
            year:          filterYear.value || undefined,
            leave_type_id: filterLeaveType.value || undefined,
            search:        search.value.trim() || undefined,
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function employeeName(row) {
    const name = [row.employee_first_name, row.employee_last_name].filter(Boolean).join(' ');
    return name || `#${row.employee_id}`;
}

function employeeGenderLabel(row) {
    if (!row.employee_gender) return '';
    return row.employee_gender.charAt(0).toUpperCase();
}

const detailDialog   = ref(false);
const detailEmployee = ref(null);
const detailItems    = ref([]);
const detailLoading  = ref(false);

async function openDetail(row) {
    detailEmployee.value = row;
    detailDialog.value   = true;
    detailLoading.value  = true;
    try {
        const data = await LeaveBalanceService.getList({
            employee_id: row.employee_id,
            year: filterYear.value || undefined,
            per_page: 500,
        });
        detailItems.value = data?.items ?? [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        detailLoading.value = false;
    }
}

function remaining(row) {
    return (Number(row.total_allocated) + Number(row.manual_adjustment ?? 0) - Number(row.total_taken) - Number(row.total_pending)).toFixed(1);
}

function openAdjust(row) {
    adjustRow.value   = row;
    adjustValue.value = Number(row.manual_adjustment ?? 0);
    adjustDialog.value = true;
}

async function saveAdjust() {
    if (!adjustRow.value) return;
    adjustSaving.value = true;
    try {
        await LeaveBalanceService.adjust(adjustRow.value.id, adjustValue.value);
        toast.add({ severity: 'success', detail: 'Balance adjusted.', life: 3000 });
        adjustDialog.value = false;
        load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        adjustSaving.value = false;
    }
}

async function downloadListPdf() {
    try {
        const data = await LeaveBalanceService.getList({
            per_page: 9999,
            orderby: sortField.value,
            order: sortOrder.value === 1 ? 'ASC' : 'DESC',
            year: filterYear.value || undefined,
            leave_type_id: filterLeaveType.value || undefined,
            search: search.value.trim() || undefined,
        });
        const rows = (data?.items ?? []).map(r => [
            employeeName(r),
            r.employee_number || '',
            r.total_allocated,
            r.total_taken,
            r.total_pending,
            r.manual_adjustment ?? 0,
            remaining(r),
        ]);

        const ltName = filterLeaveType.value ? (leaveTypeMap.value[filterLeaveType.value] || '') : 'All Types';
        const yearLabel = filterYear.value || 'All Years';

        const doc = new jsPDF();
        doc.setFontSize(16);
        doc.text('Leave Balances', 14, 18);
        doc.setFontSize(10);
        doc.text(`Leave Type: ${ltName}  |  Year: ${yearLabel}`, 14, 26);

        autoTable(doc, {
            startY: 32,
            head: [['Employee', 'Emp #', 'Allocated', 'Used', 'Pending', 'Adj.', 'Remaining']],
            body: rows,
        });

        doc.save(`leave-balances-${ltName.replace(/\s+/g, '-').toLowerCase()}-${yearLabel}.pdf`);
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}

function downloadDetailPdf() {
    if (!detailEmployee.value || !detailItems.value.length) return;

    const emp = detailEmployee.value;
    const yearLabel = filterYear.value || 'All Years';
    const rows = detailItems.value.map(r => [
        leaveTypeMap.value[r.leave_type_id] || r.leave_type_id,
        r.year,
        r.total_allocated,
        r.total_taken,
        r.total_pending,
        r.manual_adjustment ?? 0,
        remaining(r),
    ]);

    const doc = new jsPDF();
    doc.setFontSize(16);
    doc.text(`Leave Balances — ${employeeName(emp)}`, 14, 18);
    doc.setFontSize(10);
    doc.text(`${emp.employee_number || ''}  |  Year: ${yearLabel}`, 14, 26);

    autoTable(doc, {
        startY: 32,
        head: [['Leave Type', 'Year', 'Allocated', 'Used', 'Pending', 'Adj.', 'Remaining']],
        body: rows,
    });

    doc.save(`leave-balance-${employeeName(emp).replace(/\s+/g, '-').toLowerCase()}-${yearLabel}.pdf`);
}

function escapeCsv(val) {
    const s = String(val ?? '');
    return s.includes(',') || s.includes('"') || s.includes('\n') ? `"${s.replace(/"/g, '""')}"` : s;
}

function triggerCsvDownload(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

async function downloadListCsv() {
    try {
        const data = await LeaveBalanceService.getList({
            per_page: 9999,
            orderby: sortField.value,
            order: sortOrder.value === 1 ? 'ASC' : 'DESC',
            year: filterYear.value || undefined,
            leave_type_id: filterLeaveType.value || undefined,
            search: search.value.trim() || undefined,
        });
        const header = ['Employee', 'Emp #', 'Allocated', 'Used', 'Pending', 'Adj.', 'Remaining'];
        const csvRows = (data?.items ?? []).map(r => [
            employeeName(r),
            r.employee_number || '',
            r.total_allocated,
            r.total_taken,
            r.total_pending,
            r.manual_adjustment ?? 0,
            remaining(r),
        ].map(escapeCsv).join(','));

        const ltName = filterLeaveType.value ? (leaveTypeMap.value[filterLeaveType.value] || '') : 'All Types';
        const yearLabel = filterYear.value || 'All Years';
        const csv = [header.map(escapeCsv).join(','), ...csvRows].join('\n');
        triggerCsvDownload(csv, `leave-balances-${ltName.replace(/\s+/g, '-').toLowerCase()}-${yearLabel}.csv`);
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}

function downloadDetailCsv() {
    if (!detailEmployee.value || !detailItems.value.length) return;

    const emp = detailEmployee.value;
    const yearLabel = filterYear.value || 'All Years';
    const header = ['Leave Type', 'Year', 'Allocated', 'Used', 'Pending', 'Adj.', 'Remaining'];
    const csvRows = detailItems.value.map(r => [
        leaveTypeMap.value[r.leave_type_id] || r.leave_type_id,
        r.year,
        r.total_allocated,
        r.total_taken,
        r.total_pending,
        r.manual_adjustment ?? 0,
        remaining(r),
    ].map(escapeCsv).join(','));

    const csv = [header.map(escapeCsv).join(','), ...csvRows].join('\n');
    triggerCsvDownload(csv, `leave-balance-${employeeName(emp).replace(/\s+/g, '-').toLowerCase()}-${yearLabel}.csv`);
}

const listDownloadItems = ref([
    { label: 'Download PDF', icon: 'pi pi-file-pdf', command: downloadListPdf },
    { label: 'Download CSV', icon: 'pi pi-file', command: downloadListCsv },
]);

const detailDownloadItems = ref([
    { label: 'Download PDF', icon: 'pi pi-file-pdf', command: downloadDetailPdf },
    { label: 'Download CSV', icon: 'pi pi-file', command: downloadDetailCsv },
]);

function onPage(e)  { first.value = e.first; rows.value = e.rows; }
function onSort(e)  { sortField.value = e.sortField ?? 'leave_type_id'; sortOrder.value = e.sortOrder ?? 1; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Leave Balances</div>
                <p class="text-surface-500 m-0">View and adjust employee leave balances.</p>
            </div>
            <SplitButton label="Download" outlined severity="danger" :model="listDownloadItems"/>
        </div>

        <div class="mb-4 flex flex-wrap gap-4 items-end">
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Search</label>
                <InputText v-model="search" placeholder="Name or employee ID..." @input="onSearch"
                    style="width:16rem" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Year</label>
                <Select v-model="filterYear" :options="yearOptions" optionLabel="label" optionValue="value"
                    placeholder="All years" showClear style="width:10rem" @update:modelValue="first = 0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium text-sm">Leave Type</label>
                <Select v-model="filterLeaveType" :options="leaveTypeOptions" optionLabel="label" optionValue="value"
                    placeholder="All types" showClear style="width:14rem" @update:modelValue="first = 0" />
            </div>
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No leave balances found.</div></template>

            <Column field="employee_id" header="Employee" sortable style="min-width:14rem">
                <template #body="{ data }">
                    <div>
                        <div class="font-medium">{{ employeeName(data) }} ({{ employeeGenderLabel(data) }})</div>
                        <div class="text-surface-500 text-xs">{{ data.employee_number }}</div>
                    </div>
                </template>
            </Column>
            <!-- <Column field="leave_type_id" header="Leave Type" sortable style="min-width:10rem">
                <template #body="{ data }">{{ leaveTypeMap[data.leave_type_id] || data.leave_type_id }}</template>
            </Column>
            <Column field="year"              header="Year"      sortable style="min-width:6rem" /> -->
            <Column field="total_allocated"   header="Allocated" sortable style="min-width:7rem" />
            <Column field="total_taken"       header="Used"      sortable style="min-width:6rem" />
            <Column field="total_pending"     header="Pending"   sortable style="min-width:7rem" />
            <Column field="manual_adjustment" header="Adj."      sortable style="min-width:6rem" />
            <Column header="Remaining" style="min-width:8rem">
                <template #body="{ data }">{{ remaining(data) }}</template>
            </Column>
            <Column header="Actions" style="min-width:8rem">
                <template #body="{ data }">
                    <Button icon="pi pi-eye" text rounded size="small" v-tooltip="'View all leave types'" @click="openDetail(data)" />
                    <Button icon="pi pi-sliders-h" text rounded size="small" v-tooltip="'Adjust'" @click="openAdjust(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="adjustDialog" header="Adjust Leave Balance" modal :style="{ width: '24rem' }" :dismissableMask="true">
            <div v-if="adjustRow" class="flex flex-col gap-4 pt-2">
                <p class="m-0 text-surface-600">
                    <strong>{{ employeeName(adjustRow) }}</strong> — {{ leaveTypeMap[adjustRow.leave_type_id] || adjustRow.leave_type_id }} ({{ adjustRow.year }})
                </p>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Manual Adjustment (days)</label>
                    <InputNumber v-model="adjustValue" :minFractionDigits="0" :maxFractionDigits="1" showButtons fluid />
                    <small class="text-surface-500">Positive to add days, negative to deduct.</small>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="adjustDialog = false" />
                <Button label="Save" icon="pi pi-check" :loading="adjustSaving" @click="saveAdjust" />
            </template>
        </Dialog>

        <Dialog v-model:visible="detailDialog" modal :style="{ width: '52rem' }" :dismissableMask="true">
            <template #header>
                <div v-if="detailEmployee">
                    <div class="font-semibold text-lg">{{ employeeName(detailEmployee) }} ({{ employeeGenderLabel(detailEmployee) }})</div>
                    <div class="text-surface-500 text-sm">{{ detailEmployee.employee_number }} — Leave Balances{{ filterYear ? ` for ${filterYear}` : '' }}</div>
                </div>
            </template>
            <div class="flex justify-end mb-3">
                <SplitButton label="Download" outlined severity="danger" size="small" :model="detailDownloadItems" :disabled="!detailItems.length" />
            </div>
            <DataTable :value="detailItems" :loading="detailLoading" dataKey="id">
                <template #empty><div class="text-center py-4 text-surface-500">No balances found.</div></template>
                <Column field="leave_type_id" header="Leave Type" style="min-width:10rem">
                    <template #body="{ data }">{{ leaveTypeMap[data.leave_type_id] || data.leave_type_id }}</template>
                </Column>
                <Column field="year"              header="Year"      style="min-width:5rem" />
                <Column field="total_allocated"   header="Allocated" style="min-width:6rem" />
                <Column field="total_taken"       header="Used"      style="min-width:5rem" />
                <Column field="total_pending"     header="Pending"   style="min-width:6rem" />
                <Column field="manual_adjustment" header="Adj."      style="min-width:5rem" />
                <Column header="Remaining" style="min-width:6rem">
                    <template #body="{ data }">{{ remaining(data) }}</template>
                </Column>
            </DataTable>
        </Dialog>
    </div>
</template>
