<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { AttendanceService } from '@/service/AttendanceService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const toast = useToast();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('date');
const sortOrder    = ref(-1);
const filterStatus = ref('');
const filterFrom   = ref(null);
const filterTo     = ref(null);

const statusOptions = [
    { label: 'Present',  value: 'present'  },
    { label: 'Absent',   value: 'absent'   },
    { label: 'Late',     value: 'late'     },
    { label: 'Half Day', value: 'half_day' },
    { label: 'On Leave', value: 'on_leave' },
];
const statusSeverity = {
    present: 'success', absent: 'danger', late: 'warn',
    half_day: 'info', on_leave: 'secondary',
};

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus, filterFrom, filterTo], load);

function toDateStr(val) {
    if (!val) return undefined;
    if (typeof val === 'string') return val;
    const d = new Date(val);
    return isNaN(d) ? undefined : d.toISOString().slice(0, 10);
}

async function load() {
    loading.value = true;
    try {
        const data = await AttendanceService.getList({
            page:     Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby:  sortField.value,
            order:    sortOrder.value === 1 ? 'ASC' : 'DESC',
            status:   filterStatus.value || undefined,
            from:     toDateStr(filterFrom.value),
            to:       toDateStr(filterTo.value),
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function formatTime(val) {
    if (!val) return '—';
    const d = new Date(val);
    if (isNaN(d)) return val;
    const tz = window.wphrApi?.timezone;
    const opts = { hour: '2-digit', minute: '2-digit' };
    if (tz) opts.timeZone = tz;
    return d.toLocaleTimeString([], opts);
}

function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'date'; sortOrder.value = e.sortOrder ?? -1; }
function clearFilters() { filterStatus.value = ''; filterFrom.value = null; filterTo.value = null; first.value = 0; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Attendance Log</div>
                <p class="text-surface-500 m-0">View and filter employee attendance records.</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <label class="font-medium text-sm">Status:</label>
            <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                placeholder="All" showClear style="min-width:10rem" @change="first = 0" />
            <label class="font-medium text-sm">From:</label>
            <HrDatePicker v-model="filterFrom" showIcon @update:modelValue="first = 0" style="width:10rem" />
            <label class="font-medium text-sm">To:</label>
            <HrDatePicker v-model="filterTo"   showIcon @update:modelValue="first = 0" style="width:10rem" />
            <Button label="Clear" size="small" severity="secondary" @click="clearFilters" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No attendance records found.</div></template>

            <Column field="employee_id"         header="Employee" sortable style="min-width:8rem" />
            <Column field="date"                header="Date"     sortable style="min-width:9rem">
                <template #body="{ data }">{{ data.date_formatted || data.date }}</template>
            </Column>
            <Column field="check_in"            header="In"       style="min-width:7rem">
                <template #body="{ data }"><span>{{ formatTime(data.check_in) }}</span></template>
            </Column>
            <Column field="check_out"           header="Out"      style="min-width:7rem">
                <template #body="{ data }"><span>{{ formatTime(data.check_out) }}</span></template>
            </Column>
            <Column field="total_working_hours" header="Hours"    sortable style="min-width:7rem">
                <template #body="{ data }">
                    {{ data.total_working_hours ? Number(data.total_working_hours).toFixed(2) : '—' }}
                </template>
            </Column>
            <Column field="overtime_hours"      header="OT"       sortable style="min-width:6rem">
                <template #body="{ data }">
                    <span :class="data.overtime_hours > 0 ? 'text-orange-500 font-medium' : ''">
                        {{ data.overtime_hours > 0 ? Number(data.overtime_hours).toFixed(2) : '—' }}
                    </span>
                </template>
            </Column>
            <Column field="status"              header="Status"   sortable style="min-width:9rem">
                <template #body="{ data }">
                    <div class="flex flex-wrap gap-1">
                        <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                        <Tag class="capitalize" v-if="parseInt(data.late_entry)"  value="Late"       severity="warn" />
                        <Tag class="capitalize" v-if="parseInt(data.early_exit)"  value="Early Exit" severity="warn" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
