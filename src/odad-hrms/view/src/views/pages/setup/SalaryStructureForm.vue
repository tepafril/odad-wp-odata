<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { SalaryStructureService } from '@/service/SalaryStructureService';
import { SalaryComponentService } from '@/service/SalaryComponentService';

const route  = useRoute();
const router = useRouter();
const toast  = useToast();

const saving        = ref(false);
const loadingDetail = ref(false);
const isEdit        = computed(() => !!route.params.id);

const form = reactive({
    name:              '',
    company_id:        1,
    description:       '',
    is_active:         true,
    currency:          'USD',
    payroll_frequency: 'monthly',
});

const details          = ref([]);       // structure detail rows
const allComponents    = ref([]);       // all available salary components
const newComponentId   = ref(null);

const frequencyOptions = [
    { label: 'Monthly',    value: 'monthly' },
    { label: 'Bi-Weekly',  value: 'bi_weekly' },
    { label: 'Weekly',     value: 'weekly' },
];

const componentOptions = computed(() =>
    allComponents.value.map(c => ({ label: `${c.name} (${c.code})`, value: c.id }))
);

onMounted(async () => {
    // Load all active components for the dropdown
    try {
        const data = await SalaryComponentService.getList({ per_page: 999, status: 'active' });
        allComponents.value = data?.items ?? [];
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }

    if (isEdit.value) {
        loadingDetail.value = true;
        try {
            const d = await SalaryStructureService.getById(route.params.id);
            Object.assign(form, {
                name:              d.name              ?? '',
                company_id:        Number(d.company_id ?? 1),
                description:       d.description       ?? '',
                is_active:         !!parseInt(d.is_active ?? 1),
                currency:          d.currency          ?? 'USD',
                payroll_frequency: d.payroll_frequency ?? 'monthly',
            });
            const det = await SalaryStructureService.getDetails(route.params.id);
            details.value = det ?? [];
        } catch (e) {
            toast.add({ severity: 'error', detail: e.message, life: 5000 });
        } finally {
            loadingDetail.value = false;
        }
    }
});

async function addDetailRow() {
    if (!newComponentId.value) return;
    if (!isEdit.value) {
        toast.add({ severity: 'warn', detail: 'Save the structure first before adding components.', life: 3000 });
        return;
    }
    try {
        const row = await SalaryStructureService.addDetail(route.params.id, {
            salary_component_id: newComponentId.value,
        });
        details.value.push(row);
        newComponentId.value = null;
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}

async function removeDetailRow(detail) {
    if (!isEdit.value) {
        details.value = details.value.filter(d => d !== detail);
        return;
    }
    try {
        await SalaryStructureService.removeDetail(route.params.id, detail.id);
        details.value = details.value.filter(d => d.id !== detail.id);
        toast.add({ severity: 'success', detail: 'Component removed.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}

async function save() {
    if (!form.name.trim()) return toast.add({ severity: 'warn', detail: 'Name is required.', life: 3000 });

    saving.value = true;
    try {
        const body = {
            name:              form.name.trim(),
            company_id:        Number(form.company_id),
            description:       form.description.trim() || null,
            is_active:         form.is_active ? 1 : 0,
            currency:          form.currency,
            payroll_frequency: form.payroll_frequency,
        };

        if (isEdit.value) {
            await SalaryStructureService.update(route.params.id, body);
            toast.add({ severity: 'success', detail: 'Salary structure updated.', life: 3000 });
        } else {
            const created = await SalaryStructureService.create(body);
            toast.add({ severity: 'success', detail: 'Salary structure created.', life: 3000 });
            router.replace({ name: 'salary-structure-edit', params: { id: created.id } });
            return;
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}

function getComponentName(id) {
    const c = allComponents.value.find(x => x.id === id || x.id === Number(id));
    return c ? `${c.name} (${c.code})` : `#${id}`;
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">{{ isEdit ? 'Edit Salary Structure' : 'New Salary Structure' }}</div>
            </div>
            <div class="flex gap-2">
                <Button label="Cancel" severity="secondary" @click="router.back()" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. Standard Monthly Structure" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Currency</label>
                <InputText v-model="form.currency" fluid placeholder="USD" maxlength="3" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Payroll Frequency</label>
                <Select v-model="form.payroll_frequency" :options="frequencyOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex items-center gap-3 pt-6">
                <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
                <label for="is_active" class="cursor-pointer font-medium">Active</label>
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Description</label>
                <Textarea v-model="form.description" rows="2" fluid placeholder="Optional description" />
            </div>
        </div>

        <!-- Components table -->
        <div class="mb-4">
            <div class="font-semibold text-lg mb-3">Components</div>
            <DataTable :value="details" :loading="loadingDetail" dataKey="id" size="small">
                <template #empty><div class="text-center py-4 text-surface-500">No components added yet.</div></template>
                <Column header="Component" style="min-width:14rem">
                    <template #body="{ data }">
                        {{ data.component?.name ?? getComponentName(data.salary_component_id) }}
                    </template>
                </Column>
                <Column header="Code" style="min-width:6rem">
                    <template #body="{ data }">{{ data.component?.code ?? '' }}</template>
                </Column>
                <Column header="Type" style="min-width:7rem">
                    <template #body="{ data }">
                        <Tag v-if="data.component?.type"
                             class="capitalize"
                             :value="data.component.type"
                             :severity="data.component.type === 'earning' ? 'success' : 'danger'" />
                    </template>
                </Column>
                <Column field="default_amount" header="Override Amount" style="min-width:10rem" />
                <Column field="sort_order"     header="Sort"            style="min-width:5rem" />
                <Column header="" style="min-width:5rem">
                    <template #body="{ data }">
                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="removeDetailRow(data)" />
                    </template>
                </Column>
            </DataTable>

            <div class="flex gap-2 mt-3">
                <Select v-model="newComponentId" :options="componentOptions" optionLabel="label" optionValue="value"
                    placeholder="Select component to add" style="min-width:18rem" />
                <Button label="Add" icon="pi pi-plus" @click="addDetailRow" />
            </div>
        </div>
    </div>
</template>
