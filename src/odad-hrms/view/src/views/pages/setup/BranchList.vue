<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { BranchService } from '@/service/BranchService';
import { CompanyService } from '@/service/CompanyService';

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
const saving        = ref(false);
const companyOptions = ref([]);

const errors = reactive({ name: '', company_id: '' });

// ---------------------------------------------------------------------------
// Column visibility
// ---------------------------------------------------------------------------
const allColumns = [
    { field: 'id',             header: 'ID',          sortable: true,  style: 'min-width:5rem'  },
    { field: 'name',           header: 'Name',        sortable: true,  style: 'min-width:12rem' },
    { field: 'branch_code',    header: 'Code',        sortable: true,  style: 'min-width:8rem'  },
    { field: 'address_line_1', header: 'Address',     sortable: false, style: 'min-width:14rem' },
    { field: 'address_line_2', header: 'Address 2',   sortable: false, style: 'min-width:14rem' },
    { field: 'city',           header: 'City',        sortable: true,  style: 'min-width:9rem'  },
    { field: 'state',          header: 'State',       sortable: false, style: 'min-width:9rem'  },
    { field: 'country',        header: 'Country',     sortable: false, style: 'min-width:8rem'  },
    { field: 'postal_code',    header: 'Postal Code', sortable: false, style: 'min-width:9rem'  },
    { field: 'phone',          header: 'Phone',       sortable: false, style: 'min-width:10rem' },
];
const defaultColumns = ['id', 'name', 'branch_code', 'city', 'phone'];
const selectedColumns = ref(allColumns.filter(c => defaultColumns.includes(c.field)));

const statusSeverity = { active: 'success', inactive: 'secondary' };

const form = ref({
    company_id: null, name: '', branch_code: '', address_line_1: '',
    city: '', country: '', phone: '', is_head_office: false, status: 'active',
});

const statusOptions = [
    { label: 'Active',   value: 'active'   },
    { label: 'Inactive', value: 'inactive' },
];

onMounted(async () => {
    const c = await CompanyService.getList({ per_page: 500 });
    companyOptions.value = (c?.items ?? []).map(i => ({ ...i, id: Number(i.id) }));
    load();
});
watch([first, rows, sortField, sortOrder], load);

async function load() {
    loading.value = true;
    try {
        const data = await BranchService.getList({
            page: Math.floor(first.value / rows.value) + 1,
            per_page: rows.value,
            orderby: sortField.value,
            order: sortOrder.value === 1 ? 'ASC' : 'DESC',
        });
        items.value        = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    Object.assign(form.value, {
        company_id: null, name: '', branch_code: '', address_line_1: '',
        city: '', country: '', phone: '', is_head_office: false, status: 'active',
    });
}

function openNew() { editingId.value = null; errors.name = ''; errors.company_id = ''; resetForm(); dialogVisible.value = true; }

async function openEdit(row) {
    editingId.value = row.id;
    errors.name = ''; errors.company_id = '';
    try {
        const d = await BranchService.getById(row.id);
        Object.assign(form.value, {
            company_id:    d.company_id ? Number(d.company_id) : null,
            name:          d.name          ?? '',
            branch_code:   d.branch_code   ?? '',
            address_line_1: d.address_line_1 ?? '',
            city:          d.city          ?? '',
            country:       d.country       ?? '',
            phone:         d.phone         ?? '',
            is_head_office: !!parseInt(d.is_head_office),
            status:        d.status        ?? 'active',
        });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
        return;
    }
    dialogVisible.value = true;
}

function validate() {
    errors.name       = form.value.name?.trim() ? '' : 'Branch name is required.';
    errors.company_id = form.value.company_id   ? '' : 'Company is required.';
    return !Object.values(errors).some(Boolean);
}

async function save() {
    if (!validate()) return;
    saving.value = true;
    try {
        const body = { ...form.value, is_head_office: form.value.is_head_office ? 1 : 0 };
        if (editingId.value) {
            await BranchService.update(editingId.value, body);
            toast.add({ severity: 'success', detail: 'Branch updated.', life: 3000 });
        } else {
            await BranchService.create(body);
            toast.add({ severity: 'success', detail: 'Branch created.', life: 3000 });
        }
        dialogVisible.value = false;
        load();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}

function confirmDelete(row) {
    confirm.require({
        message: `Delete branch "${row.name}"?`,
        header: 'Delete Branch',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await BranchService.remove(row.id);
                toast.add({ severity: 'success', detail: 'Branch deleted.', life: 3000 });
                load();
            } catch (e) {
                toast.add({ severity: 'error', detail: e.message, life: 5000 });
            }
        },
    });
}

function onPage(e) { first.value = e.first; rows.value = e.rows; }
function onSort(e) { sortField.value = e.sortField ?? 'name'; sortOrder.value = e.sortOrder ?? 1; }
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">Branches</div>
                <p class="text-surface-500 m-0">Manage company branches and offices.</p>
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
                <Button label="New Branch" icon="pi pi-plus" @click="openNew" />
            </div>
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id" lazy paginator
            :first="first" :rows="rows" :totalRecords="totalRecords"
            :rowsPerPageOptions="[5, 10, 25]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage" @sort="onSort">
            <template #empty><div class="text-center py-8 text-surface-500">No branches found.</div></template>
            <Column
                v-for="col in selectedColumns"
                :key="col.field"
                :field="col.field"
                :header="col.header"
                :sortable="col.sortable"
                :style="col.style"
            />
            <Column field="is_head_office" header="HQ" style="min-width:5rem">
                <template #body="{ data }">
                    <i :class="parseInt(data.is_head_office) ? 'pi pi-check text-green-500' : ''" />
                </template>
            </Column>
            <Column field="status"      header="Status"      sortable style="min-width:8rem">
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

        <Dialog v-model:visible="dialogVisible" :header="editingId ? 'Edit Branch' : 'New Branch'"
            modal :style="{ width: '42rem' }" :dismissableMask="true" @hide="editingId = null">
            <div class="flex flex-col gap-4 pt-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-medium">Company <span class="text-red-500">*</span></label>
                        <Select v-model="form.company_id" :options="companyOptions"
                            optionLabel="name" optionValue="id" placeholder="Select company" fluid
                            :invalid="!!errors.company_id" />
                        <small v-if="errors.company_id" class="text-red-500">{{ errors.company_id }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Branch Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.name" fluid :invalid="!!errors.name" />
                        <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Branch Code</label>
                        <InputText v-model="form.branch_code" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Phone</label>
                        <InputText v-model="form.phone" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Status</label>
                        <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-medium">Address</label>
                        <InputText v-model="form.address_line_1" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">City</label>
                        <InputText v-model="form.city" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Country (2-letter)</label>
                        <InputText v-model="form.country" fluid maxlength="2" />
                    </div>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <Checkbox v-model="form.is_head_office" :binary="true" inputId="is_head_office" />
                        <label for="is_head_office" class="cursor-pointer font-medium">Head Office</label>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </template>
        </Dialog>

        <ConfirmDialog />
    </div>
</template>
