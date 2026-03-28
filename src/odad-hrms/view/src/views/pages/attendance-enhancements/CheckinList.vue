<script setup>
import CheckInWidget from '@/views/pages/attendance/CheckInWidget.vue';
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { AttendanceService } from '@/service/AttendanceService';

const toast = useToast();
const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('date');
const sortOrder    = ref(-1);

const statusSeverity = { present: 'success', absent: 'danger', late: 'warn', half_day: 'info', on_leave: 'secondary' };

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await AttendanceService.getList({
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
</script>

<template>
    <div class="flex flex-col gap-4">
        <CheckInWidget class="w-full" style="max-width:32rem" />

        <div class="card w-full !max-w-full">
            <div class="font-semibold text-xl mb-4">Employee Check-ins</div>
            <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
                :first="first" :rows="rows" :totalRecords="totalRecords"
                :rowsPerPageOptions="[5, 10, 25]"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
                @page="onPage" @sort="onSort">
                <template #empty><div class="text-center py-8 text-surface-500">No check-in records.</div></template>
                <Column field="employee_id"         header="Employee" sortable style="min-width:8rem" />
                <Column field="date"                header="Date"     sortable style="min-width:9rem">
                    <template #body="{ data }">{{ data.date_formatted || data.date }}</template>
                </Column>
                <Column field="check_in"            header="In"       style="min-width:7rem">
                    <template #body="{ data }">{{ formatTime(data.check_in) }}</template>
                </Column>
                <Column field="check_out"           header="Out"      style="min-width:7rem">
                    <template #body="{ data }">{{ formatTime(data.check_out) }}</template>
                </Column>
                <Column field="total_working_hours" header="Hours"    sortable style="min-width:7rem">
                    <template #body="{ data }">
                        {{ data.total_working_hours ? Number(data.total_working_hours).toFixed(2) : '—' }}
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
    </div>
</template>
