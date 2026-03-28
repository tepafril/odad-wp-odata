<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { EmployeeService } from '@/service/EmployeeService';

const router = useRouter();
const toast  = useToast();

const items        = ref([]);
const totalRecords = ref(0);
const loading      = ref(false);
const first        = ref(0);
const rows         = ref(10);
const sortField    = ref('first_name');
const sortOrder    = ref(1);
const searchQuery  = ref('');
const filterStatus = ref('');

// ---------------------------------------------------------------------------
// Column visibility
// ---------------------------------------------------------------------------
const allColumns = [
    { field: 'employee_number',      header: 'Emp. ID',      sortable: true,  style: 'min-width:9rem'  },
    { field: 'work_email',           header: 'Work Email',   sortable: true,  style: 'min-width:14rem' },
    { field: 'personal_email',       header: 'Personal Email', sortable: false, style: 'min-width:14rem' },
    { field: 'work_phone',           header: 'Work Phone',   sortable: false, style: 'min-width:10rem' },
    { field: 'personal_phone',       header: 'Mobile',       sortable: false, style: 'min-width:10rem' },
    { field: 'department_name',      header: 'Department',   sortable: true,  style: 'min-width:11rem' },
    { field: 'designation_name',     header: 'Designation',  sortable: false, style: 'min-width:11rem' },
    { field: 'employment_type',      header: 'Type',         sortable: true,  style: 'min-width:9rem'  },
    { field: 'date_of_joining',      header: 'Joining Date', sortable: true,  style: 'min-width:11rem' },
    { field: 'date_of_confirmation', header: 'Confirmed',    sortable: true,  style: 'min-width:10rem' },
    { field: 'date_of_exit',         header: 'Exit Date',    sortable: true,  style: 'min-width:10rem' },
    { field: 'nationality',          header: 'Nationality',  sortable: false, style: 'min-width:9rem'  },
    { field: 'national_id',          header: 'National ID',  sortable: false, style: 'min-width:10rem' },
    { field: 'gender',               header: 'Gender',       sortable: false, style: 'min-width:8rem'  },
];
const defaultColumns = ['employee_number', 'work_email', 'department_name', 'designation_name', 'employment_type'];
const selectedColumns = ref(allColumns.filter(c => defaultColumns.includes(c.field)));

const dateColumns = new Set(['date_of_joining', 'date_of_confirmation', 'date_of_exit']);

const statusOptions = [
    { label: 'Active',      value: 'active'      },
    { label: 'Inactive',    value: 'inactive'    },
    { label: 'Suspended',   value: 'suspended'   },
    { label: 'Terminated',  value: 'terminated'  },
    { label: 'Resigned',    value: 'resigned'    },
    { label: 'Retired',     value: 'retired'     },
];
const statusSeverity = {
    active: 'success', inactive: 'secondary', suspended: 'warn',
    terminated: 'danger', resigned: 'secondary', retired: 'secondary',
};

onMounted(load);
watch([first, rows, sortField, sortOrder, filterStatus], load);

let searchTimer = null;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { first.value = 0; load(); }, 400);
}

async function load() {
    loading.value = true;
    try {
        const data = await EmployeeService.getList({
            page:     Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby:  sortField.value,
            order:    sortOrder.value === 1 ? 'ASC' : 'DESC',
            search:   searchQuery.value || undefined,
            employment_status: filterStatus.value || undefined,
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function openNew()  { router.push({ name: 'employee-create' }); }
function openEdit(row) { router.push({ name: 'employee-edit', params: { id: row.id } }); }
function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'first_name'; sortOrder.value = e.sortOrder ?? 1; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Employees</div>
                <p class="text-surface-500 m-0">Manage your employee records.</p>
            </div>
            <div class="flex items-center gap-2">
                <MultiSelect
                    v-model="selectedColumns"
                    :options="allColumns"
                    optionLabel="header"
                    placeholder="Columns"
                    display="chip"
                    :maxSelectedLabels="0"
                    selectedItemsLabel="{0} columns"
                    style="min-width:10rem"
                />
                <Button label="New Employee" icon="pi pi-plus" @click="openNew" />
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-3 items-center">
            <InputText v-model="searchQuery" placeholder="Search name or ID..." style="min-width:16rem" @input="onSearch" />
            <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                placeholder="All statuses" showClear style="min-width:12rem" @change="first = 0" />
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No employees found.</div></template>

            <!-- Name: always visible — composite Avatar column -->
            <Column header="Name" sortable sortField="first_name" style="min-width:14rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <Avatar :label="(data.first_name?.[0] ?? '') + (data.last_name?.[0] ?? '')"
                            shape="circle" size="small" />
                        <span>{{ data.first_name }} {{ data.last_name }}</span>
                    </div>
                </template>
            </Column>
            <!-- Toggleable columns -->
            <Column
                v-for="col in selectedColumns"
                :key="col.field"
                :field="col.field"
                :header="col.header"
                :sortable="col.sortable"
                :style="col.style"
            >
                <template v-if="dateColumns.has(col.field)" #body="{ data }">
                    {{ data[col.field + '_formatted'] || data[col.field] }}
                </template>
            </Column>
            <!-- Status: always visible — Tag template -->
            <Column field="employment_status" header="Status" sortable style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.employment_status" :severity="statusSeverity[data.employment_status] ?? 'secondary'" />
                </template>
            </Column>
            <!-- Actions: always visible -->
            <Column header="Actions" style="min-width:7rem">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
