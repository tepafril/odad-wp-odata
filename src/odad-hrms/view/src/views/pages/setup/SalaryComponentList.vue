<script setup>
import { onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SalaryComponentService } from '@/service/SalaryComponentService';
import SalaryComponentForm from './SalaryComponentForm.vue';

const toast = useToast();

const items         = ref([]);
const totalRecords  = ref(0);
const loading       = ref(false);
const first         = ref(0);
const rows          = ref(10);
const sortField     = ref('sort_order');
const sortOrder     = ref(1);
const dialogVisible = ref(false);
const editingId     = ref(null);
const formRef       = ref(null);

const typeSeverity   = { earning: 'success', deduction: 'danger' };
const statusSeverity = { active: 'success', inactive: 'secondary' };

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await SalaryComponentService.getList({
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
function onSort(e)     { sortField.value = e.sortField ?? 'sort_order'; sortOrder.value = e.sortOrder ?? 1; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Salary Components</div>
                <p class="text-surface-500 m-0">Define earnings and deductions for payroll calculation.</p>
            </div>
            <Button label="New Component" icon="pi pi-plus" @click="openNew" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No salary components found.</div></template>

            <Column field="id"               header="ID"               sortable style="min-width:4rem" />
            <Column field="name"             header="Name"             sortable style="min-width:10rem" />
            <Column field="code"             header="Code"             sortable style="min-width:6rem" />
            <Column field="type"             header="Type"             sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.type" :severity="typeSeverity[data.type] ?? 'secondary'" />
                </template>
            </Column>
            <Column field="calculation_type" header="Calculation"      sortable style="min-width:10rem" />
            <Column field="status"           header="Status"           sortable style="min-width:8rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:7rem">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="editingId ? 'Edit Salary Component' : 'New Salary Component'"
            modal :style="{ width: '52rem' }" :dismissableMask="true" @hide="editingId = null">
            <SalaryComponentForm ref="formRef" :model-value="dialogVisible" :edit-id="editingId"
                @update:model-value="dialogVisible = $event" @saved="onSaved" />
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" @click="formRef?.save()" />
            </template>
        </Dialog>
    </div>
</template>
