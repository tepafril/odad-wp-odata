<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { CompanyService } from '@/service/CompanyService';

const toast   = useToast();
const confirm = useConfirm();
const items   = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const editingId     = ref(null);
const saving        = ref(false);

const errors = reactive({ name: '' });

const form = ref({
    name: '', short_name: '', industry: '', registration_no: '', tax_id: '',
    default_currency: 'USD', fiscal_year_start: 1,
    address_line_1: '', city: '', state: '', country: '', postal_code: '',
    phone: '', email: '',
});

// ---------------------------------------------------------------------------
// Column visibility
// ---------------------------------------------------------------------------
const allColumns = [
    { field: 'id',                   header: 'ID',               style: 'min-width:5rem'  },
    { field: 'name',                 header: 'Name',             style: 'min-width:14rem' },
    { field: 'short_name',           header: 'Code',             style: 'min-width:8rem'  },
    { field: 'industry',             header: 'Industry',         style: 'min-width:10rem' },
    { field: 'registration_no',      header: 'Reg. No.',         style: 'min-width:10rem' },
    { field: 'tax_id',               header: 'Tax ID',           style: 'min-width:9rem'  },
    { field: 'default_currency',     header: 'Currency',         style: 'min-width:8rem'  },
    { field: 'fiscal_year_start',    header: 'FY Start (Month)', style: 'min-width:10rem' },
    { field: 'phone',                header: 'Phone',            style: 'min-width:10rem' },
    { field: 'email',                header: 'Email',            style: 'min-width:12rem' },
    { field: 'address_line_1',       header: 'Address 1',        style: 'min-width:14rem' },
    { field: 'address_line_2',       header: 'Address 2',        style: 'min-width:14rem' },
    { field: 'city',                 header: 'City',             style: 'min-width:9rem'  },
    { field: 'state',                header: 'State',            style: 'min-width:9rem'  },
    { field: 'country',              header: 'Country',          style: 'min-width:8rem'  },
    { field: 'postal_code',          header: 'Postal Code',      style: 'min-width:9rem'  },
];

// Default visible columns — keep the list short so the table isn't overwhelming on first load.
const defaultColumns = ['id', 'name', 'short_name', 'industry', 'default_currency', 'email'];

const selectedColumns = ref(allColumns.filter(c => defaultColumns.includes(c.field)));

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const data = await CompanyService.getList({ per_page: 100 });
        items.value = data?.items ?? [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
}

function openNew() {
    editingId.value = null;
    errors.name = '';
    Object.assign(form.value, {
        name: '', short_name: '', industry: '', registration_no: '', tax_id: '',
        default_currency: 'USD', fiscal_year_start: 1,
        address_line_1: '', city: '', state: '', country: '', postal_code: '',
        phone: '', email: '',
    });
    dialogVisible.value = true;
}

async function openEdit(row) {
    editingId.value = row.id;
    errors.name = '';
    try {
        const d = await CompanyService.getById(row.id);
        Object.assign(form.value, d);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
        return;
    }
    dialogVisible.value = true;
}

function validate() {
    errors.name = form.value.name?.trim() ? '' : 'Company name is required.';
    return !errors.name;
}

async function save() {
    if (!validate()) return;
    saving.value = true;
    try {
        if (editingId.value) {
            await CompanyService.update(editingId.value, form.value);
            toast.add({ severity: 'success', detail: 'Company updated.', life: 3000 });
        } else {
            await CompanyService.create(form.value);
            toast.add({ severity: 'success', detail: 'Company created.', life: 3000 });
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
        message: `Delete company "${row.name}"?`,
        header: 'Delete Company',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await CompanyService.remove(row.id);
                toast.add({ severity: 'success', detail: 'Deleted.', life: 3000 });
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
                <div class="font-semibold text-xl mb-1">Companies</div>
                <p class="text-surface-500 m-0">Manage your organisations.</p>
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
                <Button label="New Company" icon="pi pi-plus" @click="openNew" />
            </div>
        </div>

        <DataTable :value="items" :loading="loading" dataKey="id">
            <template #empty><div class="text-center py-8 text-surface-500">No companies found.</div></template>
            <Column
                v-for="col in selectedColumns"
                :key="col.field"
                :field="col.field"
                :header="col.header"
                :style="col.style"
            />
            <Column header="Actions" style="min-width:7rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                        <Button icon="pi pi-trash"  text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible"
            :header="editingId ? 'Edit Company' : 'New Company'"
            modal :style="{ width: '54rem' }" :dismissableMask="true">
            <div class="flex flex-col gap-4 pt-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-medium">Company Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.name" fluid :invalid="!!errors.name" />
                        <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Short Name / Code</label>
                        <InputText v-model="form.short_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Industry</label>
                        <InputText v-model="form.industry" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Registration No.</label>
                        <InputText v-model="form.registration_no" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Tax ID</label>
                        <InputText v-model="form.tax_id" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Default Currency</label>
                        <InputText v-model="form.default_currency" fluid maxlength="3" placeholder="USD" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Fiscal Year Start (month)</label>
                        <InputNumber v-model="form.fiscal_year_start" :min="1" :max="12" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Phone</label>
                        <InputText v-model="form.phone" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Email</label>
                        <InputText v-model="form.email" fluid type="email" />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-medium">Address</label>
                        <InputText v-model="form.address_line_1" fluid placeholder="Street address" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">City</label>
                        <InputText v-model="form.city" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">State / Province</label>
                        <InputText v-model="form.state" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Country (2-letter)</label>
                        <InputText v-model="form.country" fluid maxlength="2" placeholder="MY" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Postal Code</label>
                        <InputText v-model="form.postal_code" fluid />
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
