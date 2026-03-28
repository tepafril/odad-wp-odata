<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { ShiftService } from '@/service/ShiftService';
import ShiftForm from './ShiftForm.vue';

const toast   = useToast();
const confirm = useConfirm();

const items         = ref([]);
const totalRecords  = ref(0);
const loading       = ref(false);
const first         = ref(0);
const rows          = ref(10);
const sortField     = ref('name');
const sortOrder     = ref(1);
const dialogVisible = ref(false);
const editingId     = ref(null);
const formRef       = ref(null);

const statusSeverity = { active: 'success', inactive: 'secondary' };

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await ShiftService.getList({
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

function openNew()     { editingId.value = null; dialogVisible.value = true; }
function openEdit(row) { editingId.value = row.id; dialogVisible.value = true; }
function onSaved()     { dialogVisible.value = false; load(); }
function onPage(e)     { first.value = e.first; rows.value = e.rows; }
function onSort(e)     { sortField.value = e.sortField ?? 'name'; sortOrder.value = e.sortOrder ?? 1; }

function confirmDelete(row) {
    confirm.require({
        message: `Delete shift "${row.name}"?`,
        header:  'Delete Shift',
        icon:    'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await ShiftService.remove(row.id);
                toast.add({ severity: 'success', detail: 'Shift deleted.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Shifts</div>
                <p class="text-surface-500 m-0">Define work shifts and their schedules.</p>
            </div>
            <Button label="New Shift" icon="pi pi-plus" @click="openNew" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No shifts found.</div></template>

            <Column field="id"             header="ID"            sortable style="min-width:5rem" />
            <Column field="name"           header="Name"          sortable style="min-width:10rem" />
            <Column field="start_time"     header="Start"         sortable style="min-width:7rem" />
            <Column field="end_time"       header="End"           sortable style="min-width:7rem" />
            <Column field="working_hours"  header="Working Hrs"   style="min-width:8rem">
                <template #body="{ data }">{{ data.working_hours }} hrs</template>
            </Column>
            <Column field="grace_period_minutes" header="Grace (min)" style="min-width:8rem">
                <template #body="{ data }">{{ data.grace_period_minutes ?? '—' }}</template>
            </Column>
            <Column field="is_overnight" header="Overnight" style="min-width:8rem">
                <template #body="{ data }">
                    <i :class="parseInt(data.is_overnight) ? 'pi pi-check text-green-500' : 'pi pi-times text-red-400'" />
                </template>
            </Column>
            <Column field="status"         header="Status"        sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:9rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                        <Button icon="pi pi-trash"  text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="editingId ? 'Edit Shift' : 'New Shift'"
            modal :style="{ width: '48rem' }" :dismissableMask="true" @hide="editingId = null">
            <ShiftForm ref="formRef" :model-value="dialogVisible" :edit-id="editingId"
                @update:model-value="dialogVisible = $event" @saved="onSaved" />
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" @click="formRef?.save()" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
