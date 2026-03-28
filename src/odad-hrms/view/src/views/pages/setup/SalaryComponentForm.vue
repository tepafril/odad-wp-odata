<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SalaryComponentService } from '@/service/SalaryComponentService';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!props.editId);

const form = reactive({
    name:             '',
    code:             '',
    company_id:       1,
    type:             'earning',
    is_taxable:       true,
    is_statutory:     false,
    is_recurring:     true,
    is_flexible:      false,
    calculation_type: 'fixed',
    default_amount:   null,
    percentage:       null,
    formula:          '',
    max_amount:       null,
    description:      '',
    sort_order:       0,
    status:           'active',
});

const typeOptions = [
    { label: 'Earning',   value: 'earning' },
    { label: 'Deduction', value: 'deduction' },
];
const calcTypeOptions = [
    { label: 'Fixed Amount',         value: 'fixed' },
    { label: '% of Basic',           value: 'percent_of_basic' },
    { label: '% of Gross',           value: 'percent_of_gross' },
    { label: 'Formula',              value: 'formula' },
];
const statusOptions = [
    { label: 'Active',   value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

function reset() {
    Object.assign(form, {
        name: '', code: '', company_id: 1, type: 'earning',
        is_taxable: true, is_statutory: false, is_recurring: true, is_flexible: false,
        calculation_type: 'fixed', default_amount: null, percentage: null,
        formula: '', max_amount: null, description: '', sort_order: 0, status: 'active',
    });
}

async function loadItem(id) {
    const d = await SalaryComponentService.getById(id);
    Object.assign(form, {
        name:             d.name             ?? '',
        code:             d.code             ?? '',
        company_id:       Number(d.company_id ?? 1),
        type:             d.type             ?? 'earning',
        is_taxable:       !!parseInt(d.is_taxable ?? 1),
        is_statutory:     !!parseInt(d.is_statutory ?? 0),
        is_recurring:     !!parseInt(d.is_recurring ?? 1),
        is_flexible:      !!parseInt(d.is_flexible ?? 0),
        calculation_type: d.calculation_type ?? 'fixed',
        default_amount:   d.default_amount   != null ? Number(d.default_amount) : null,
        percentage:       d.percentage       != null ? Number(d.percentage) : null,
        formula:          d.formula          ?? '',
        max_amount:       d.max_amount       != null ? Number(d.max_amount) : null,
        description:      d.description      ?? '',
        sort_order:       Number(d.sort_order ?? 0),
        status:           d.status           ?? 'active',
    });
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

async function save() {
    if (!form.name.trim()) return toast.add({ severity: 'warn', detail: 'Name is required.', life: 3000 });
    if (!form.code.trim()) return toast.add({ severity: 'warn', detail: 'Code is required.', life: 3000 });

    saving.value = true;
    try {
        const body = {
            name:             form.name.trim(),
            code:             form.code.trim(),
            company_id:       Number(form.company_id),
            type:             form.type,
            is_taxable:       form.is_taxable ? 1 : 0,
            is_statutory:     form.is_statutory ? 1 : 0,
            is_recurring:     form.is_recurring ? 1 : 0,
            is_flexible:      form.is_flexible ? 1 : 0,
            calculation_type: form.calculation_type,
            default_amount:   form.default_amount != null ? Number(form.default_amount) : null,
            percentage:       form.percentage != null ? Number(form.percentage) : null,
            formula:          form.formula.trim() || null,
            max_amount:       form.max_amount != null ? Number(form.max_amount) : null,
            description:      form.description.trim() || null,
            sort_order:       Number(form.sort_order),
            status:           form.status,
        };
        if (isEdit.value) {
            await SalaryComponentService.update(props.editId, body);
            toast.add({ severity: 'success', detail: 'Salary component updated.', life: 3000 });
        } else {
            await SalaryComponentService.create(body);
            toast.add({ severity: 'success', detail: 'Salary component created.', life: 3000 });
        }
        emit('saved');
        emit('update:modelValue', false);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}

defineExpose({ save });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. House Rent Allowance" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Code <span class="text-red-500">*</span></label>
                <InputText v-model="form.code" fluid placeholder="e.g. HRA" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Type <span class="text-red-500">*</span></label>
                <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Calculation Type</label>
                <Select v-model="form.calculation_type" :options="calcTypeOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div v-if="form.calculation_type === 'fixed'" class="flex flex-col gap-2">
                <label class="font-medium">Default Amount</label>
                <InputNumber v-model="form.default_amount" :minFractionDigits="2" :maxFractionDigits="2" fluid />
            </div>
            <div v-if="['percent_of_basic','percent_of_gross'].includes(form.calculation_type)" class="flex flex-col gap-2">
                <label class="font-medium">Percentage (%)</label>
                <InputNumber v-model="form.percentage" :minFractionDigits="2" :maxFractionDigits="2" fluid />
            </div>
            <div v-if="form.calculation_type === 'formula'" class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Formula</label>
                <InputText v-model="form.formula" fluid placeholder="e.g. basic * 0.4 + 500" />
                <small class="text-surface-500">Variables: <code>basic</code>, <code>gross</code></small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Max Amount (cap)</label>
                <InputNumber v-model="form.max_amount" :minFractionDigits="2" :maxFractionDigits="2" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Sort Order</label>
                <InputNumber v-model="form.sort_order" :min="0" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="font-medium">Description</label>
            <Textarea v-model="form.description" rows="2" fluid placeholder="Optional description" />
        </div>

        <div class="flex flex-wrap gap-6">
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_taxable" :binary="true" inputId="is_taxable" />
                <label for="is_taxable" class="cursor-pointer">Taxable</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_statutory" :binary="true" inputId="is_statutory" />
                <label for="is_statutory" class="cursor-pointer">Statutory</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_recurring" :binary="true" inputId="is_recurring" />
                <label for="is_recurring" class="cursor-pointer">Recurring</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_flexible" :binary="true" inputId="is_flexible" />
                <label for="is_flexible" class="cursor-pointer">Flexible</label>
            </div>
        </div>
    </div>
</template>
