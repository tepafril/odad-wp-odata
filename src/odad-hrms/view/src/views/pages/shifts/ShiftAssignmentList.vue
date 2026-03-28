<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { ShiftService } from '@/service/ShiftService';
import ShiftAssignmentForm from './ShiftAssignmentForm.vue';

const toast   = useToast();
const confirm = useConfirm();

const items         = ref([]);
const totalRecords  = ref(0);
const loading       = ref(false);
const first         = ref(0);
const rows          = ref(10);
const sortField     = ref('effective_from');
const sortOrder     = ref(-1);
const dialogVisible = ref(false);
const editingId     = ref(null);
const formRef       = ref(null);

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await ShiftService.getAssignments({
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
function onSort(e)     { sortField.value = e.sortField ?? 'effective_from'; sortOrder.value = e.sortOrder ?? -1; }

function confirmDelete(row) {
    confirm.require({
        message: `Delete this shift assignment?`,
        header:  'Delete Assignment',
        icon:    'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await ShiftService.deleteAssignment(row.id);
                toast.add({ severity: 'success', detail: 'Assignment deleted.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Shift Assignments</div>
                <p class="text-surface-500 m-0">Assign shifts to employees with effective dates.</p>
            </div>
            <Button label="New Assignment" icon="pi pi-plus" @click="openNew" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No shift assignments found.</div></template>

            <Column field="id"             header="ID"             sortable style="min-width:5rem" />
            <Column field="employee_id"    header="Employee ID"    sortable style="min-width:8rem" />
            <Column field="shift_id"       header="Shift ID"       sortable style="min-width:8rem" />
            <Column field="effective_from" header="Effective From" sortable style="min-width:10rem">
                <template #body="{ data }">{{ data.effective_from_formatted || data.effective_from }}</template>
            </Column>
            <Column field="effective_to"   header="Effective To"   style="min-width:10rem">
                <template #body="{ data }">{{ data.effective_to_formatted || data.effective_to || '—' }}</template>
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
            :header="editingId ? 'Edit Assignment' : 'New Shift Assignment'"
            modal :style="{ width: '36rem' }" :dismissableMask="true" @hide="editingId = null">
            <ShiftAssignmentForm ref="formRef" :model-value="dialogVisible" :edit-id="editingId"
                @update:model-value="dialogVisible = $event" @saved="onSaved" />
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" @click="formRef?.save()" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
