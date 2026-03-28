<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import { CompanyService } from '@/service/CompanyService';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!props.editId);
const companyOptions = ref([]);
const errors = reactive({ company_id: '', name: '' });

onMounted(async () => {
    const c = await CompanyService.getList({ per_page: 500 });
    companyOptions.value = (c?.items ?? []).map(i => ({ ...i, id: Number(i.id) }));
});

const form = reactive({
    company_id:                     null,
    name:                           '',
    code:                           '',
    color:                          '#3B82F6',
    max_days_per_year:              0,
    is_paid:                        true,
    is_carry_forward:               false,
    max_carry_forward_days:         null,
    is_encashable:                  false,
    allow_negative_balance:         false,
    include_holidays_within:        false,
    allow_half_day:                 true,
    requires_attachment:            false,
    requires_attachment_after_days: null,
    accrual_enabled:                false,
    accrual_frequency:              'monthly',
    prorate_on_joining:             false,
    applicable_gender:              'all',
    category:                       'general',
    sort_order:                     0,
    status:                         'active',
});

const accrualOptions = [
    { label: 'Monthly',  value: 'monthly' },
    { label: 'Yearly',   value: 'yearly' },
];
const genderOptions = [
    { label: 'All',    value: 'all' },
    { label: 'Male',   value: 'male' },
    { label: 'Female', value: 'female' },
];
const categoryOptions = [
    { label: 'General',       value: 'general' },
    { label: 'Sick',          value: 'sick' },
    { label: 'Parental',      value: 'parental' },
    { label: 'Compensatory',  value: 'compensatory' },
    { label: 'Unpaid',        value: 'unpaid' },
];
const statusOptions = [
    { label: 'Active',   value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

function reset() {
    Object.assign(form, {
        company_id: null, name: '', code: '', color: '#3B82F6',
        max_days_per_year: 0, is_paid: true,
        is_carry_forward: false, max_carry_forward_days: null,
        is_encashable: false, allow_negative_balance: false,
        include_holidays_within: false, allow_half_day: true,
        requires_attachment: false, requires_attachment_after_days: null,
        accrual_enabled: false, accrual_frequency: 'monthly',
        prorate_on_joining: false, applicable_gender: 'all', category: 'general',
        sort_order: 0, status: 'active',
    });
}

async function loadItem(id) {
    const d = await LeaveTypeService.getById(id);
    Object.assign(form, {
        company_id:                     d.company_id ? Number(d.company_id) : null,
        name:                           d.name                           ?? '',
        code:                           d.code                           ?? '',
        color:                          d.color                          ?? '#3B82F6',
        max_days_per_year:              Number(d.max_days_per_year ?? 0),
        is_paid:                        !!parseInt(d.is_paid ?? 1),
        is_carry_forward:               !!parseInt(d.is_carry_forward),
        max_carry_forward_days:         d.max_carry_forward_days != null ? Number(d.max_carry_forward_days) : null,
        is_encashable:                  !!parseInt(d.is_encashable),
        allow_negative_balance:         !!parseInt(d.allow_negative_balance),
        include_holidays_within:        !!parseInt(d.include_holidays_within),
        allow_half_day:                 !!parseInt(d.allow_half_day ?? 1),
        requires_attachment:            !!parseInt(d.requires_attachment),
        requires_attachment_after_days: d.requires_attachment_after_days != null ? Number(d.requires_attachment_after_days) : null,
        accrual_enabled:                !!parseInt(d.accrual_enabled),
        accrual_frequency:              d.accrual_frequency              ?? 'monthly',
        prorate_on_joining:             !!parseInt(d.prorate_on_joining),
        applicable_gender:              d.applicable_gender              ?? 'all',
        category:                       d.category                       ?? 'general',
        sort_order:                     Number(d.sort_order ?? 0),
        status:                         d.status                         ?? 'active',
    });
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    errors.company_id = '';
    errors.name = '';
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

function validate() {
    errors.company_id = form.company_id      ? '' : 'Company is required.';
    errors.name       = form.name.trim()     ? '' : 'Name is required.';
    return !Object.values(errors).some(Boolean);
}

async function save() {
    if (!validate()) return;

    saving.value = true;
    try {
        const body = {
            company_id:                     form.company_id,
            name:                           form.name.trim(),
            code:                           form.code.trim() || null,
            color:                          form.color,
            max_days_per_year:              Number(form.max_days_per_year),
            is_paid:                        form.is_paid ? 1 : 0,
            is_carry_forward:               form.is_carry_forward ? 1 : 0,
            max_carry_forward_days:         form.is_carry_forward && form.max_carry_forward_days != null ? Number(form.max_carry_forward_days) : null,
            is_encashable:                  form.is_encashable ? 1 : 0,
            allow_negative_balance:         form.allow_negative_balance ? 1 : 0,
            include_holidays_within:        form.include_holidays_within ? 1 : 0,
            allow_half_day:                 form.allow_half_day ? 1 : 0,
            requires_attachment:            form.requires_attachment ? 1 : 0,
            requires_attachment_after_days: form.requires_attachment && form.requires_attachment_after_days != null ? Number(form.requires_attachment_after_days) : null,
            accrual_enabled:                form.accrual_enabled ? 1 : 0,
            accrual_frequency:              form.accrual_enabled ? form.accrual_frequency : null,
            prorate_on_joining:             form.prorate_on_joining ? 1 : 0,
            applicable_gender:              form.applicable_gender,
            category:                       form.category,
            sort_order:                     Number(form.sort_order),
            status:                         form.status,
        };
        if (isEdit.value) {
            await LeaveTypeService.update(props.editId, body);
            toast.add({ severity: 'success', detail: 'Leave type updated.', life: 3000 });
        } else {
            await LeaveTypeService.create(body);
            toast.add({ severity: 'success', detail: 'Leave type created.', life: 3000 });
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
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Company <span class="text-red-500">*</span></label>
                <Select v-model="form.company_id" :options="companyOptions"
                    optionLabel="name" optionValue="id" placeholder="Select company" fluid
                    :invalid="!!errors.company_id" />
                <small v-if="errors.company_id" class="text-red-500">{{ errors.company_id }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. Annual Leave" :invalid="!!errors.name" />
                <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Code</label>
                <InputText v-model="form.code" fluid placeholder="e.g. AL" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Max Days / Year</label>
                <InputNumber v-model="form.max_days_per_year" :min="0" :maxFractionDigits="1" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Category</label>
                <Select v-model="form.category" :options="categoryOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Applicable Gender</label>
                <Select v-model="form.applicable_gender" :options="genderOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Sort Order</label>
                <InputNumber v-model="form.sort_order" :min="0" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Color</label>
                <InputText v-model="form.color" type="color" fluid style="height:2.5rem;padding:0.2rem" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
            </div>
        </div>

        <div class="flex flex-wrap gap-6">
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_paid" :binary="true" inputId="is_paid" />
                <label for="is_paid" class="cursor-pointer">Paid Leave</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.include_holidays_within" :binary="true" inputId="include_holidays_within" />
                <label for="include_holidays_within" class="cursor-pointer">Include Holidays Within</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.allow_half_day" :binary="true" inputId="allow_half_day" />
                <label for="allow_half_day" class="cursor-pointer">Allow Half Day</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_encashable" :binary="true" inputId="is_encashable" />
                <label for="is_encashable" class="cursor-pointer">Encashable</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.allow_negative_balance" :binary="true" inputId="allow_negative_balance" />
                <label for="allow_negative_balance" class="cursor-pointer">Allow Negative Balance</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.prorate_on_joining" :binary="true" inputId="prorate_on_joining" />
                <label for="prorate_on_joining" class="cursor-pointer">Prorate on Joining</label>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox v-model="form.requires_attachment" :binary="true" inputId="requires_attachment" />
            <label for="requires_attachment" class="cursor-pointer font-medium">Require Attachment</label>
        </div>
        <div v-if="form.requires_attachment" class="flex flex-col gap-2 pl-6">
            <label class="font-medium text-sm">After Days</label>
            <InputNumber v-model="form.requires_attachment_after_days" :min="0" fluid placeholder="Always" style="max-width:12rem" />
        </div>

        <div class="flex items-center gap-2">
            <Checkbox v-model="form.is_carry_forward" :binary="true" inputId="is_carry_forward" />
            <label for="is_carry_forward" class="cursor-pointer font-medium">Carry Forward</label>
        </div>
        <div v-if="form.is_carry_forward" class="flex flex-col gap-2 pl-6">
            <label class="font-medium text-sm">Max Carry Forward Days</label>
            <InputNumber v-model="form.max_carry_forward_days" :min="0" :maxFractionDigits="1" fluid placeholder="Unlimited" style="max-width:12rem" />
        </div>

        <div class="flex items-center gap-2">
            <Checkbox v-model="form.accrual_enabled" :binary="true" inputId="accrual_enabled" />
            <label for="accrual_enabled" class="cursor-pointer font-medium">Enable Accrual</label>
        </div>
        <div v-if="form.accrual_enabled" class="flex flex-col gap-2 pl-6">
            <label class="font-medium text-sm">Accrual Frequency</label>
            <Select v-model="form.accrual_frequency" :options="accrualOptions" optionLabel="label" optionValue="value" fluid style="max-width:12rem" />
        </div>
    </div>
</template>
