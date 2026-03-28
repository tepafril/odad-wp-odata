<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { SalaryStructureService } from '@/service/SalaryStructureService';

const router = useRouter();
const toast  = useToast();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('id');
const sortOrder    = ref(1);

onMounted(load);
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await SalaryStructureService.getList({
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

function openNew()  { router.push({ name: 'salary-structure-create' }); }
function openEdit(row) { router.push({ name: 'salary-structure-edit', params: { id: row.id } }); }
function onPage(e)  { first.value = e.first; rows.value = e.rows; }
function onSort(e)  { sortField.value = e.sortField ?? 'id'; sortOrder.value = e.sortOrder ?? 1; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Salary Structures</div>
                <p class="text-surface-500 m-0">Define salary structures with component mappings.</p>
            </div>
            <Button label="New Structure" icon="pi pi-plus" @click="openNew" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort"
            selectionMode="single" @rowSelect="openEdit($event.data)">
            <template #empty><div class="text-center py-8 text-surface-500">No salary structures found.</div></template>

            <Column field="id"                header="ID"                sortable style="min-width:4rem" />
            <Column field="name"              header="Name"              sortable style="min-width:12rem" />
            <Column field="currency"          header="Currency"          style="min-width:8rem" />
            <Column field="payroll_frequency" header="Frequency"         style="min-width:10rem" />
            <Column field="is_active"         header="Active"            style="min-width:7rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="parseInt(data.is_active) ? 'Active' : 'Inactive'"
                         :severity="parseInt(data.is_active) ? 'success' : 'secondary'" />
                </template>
            </Column>
            <Column header="Actions" style="min-width:7rem">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded size="small" @click.stop="openEdit(data)" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
