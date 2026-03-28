<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { ShiftService } from '@/service/ShiftService';
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
const errors = reactive({ company_id: '', name: '', start_time: '', end_time: '', working_hours: '' });

const form = reactive({
    company_id:                    null,
    name:                          '',
    start_time:                    '09:00',
    end_time:                      '17:00',
    grace_period_minutes:          15,
    early_exit_threshold_minutes:  15,
    working_hours:                 8,
    is_overnight:                  false,
    status:                        'active',
});

const statusOptions = [
    { label: 'Active',   value: 'active'   },
    { label: 'Inactive', value: 'inactive' },
];

onMounted(async () => {
    try {
        const c = await CompanyService.getList({ per_page: 500 });
        companyOptions.value = (c?.items ?? []).map(i => ({ ...i, id: Number(i.id) }));
    } catch {
        companyOptions.value = [];
    }
});

function reset() {
    Object.assign(form, {
        company_id: null, name: '', start_time: '09:00', end_time: '17:00',
        grace_period_minutes: 15, early_exit_threshold_minutes: 15,
        working_hours: 8, is_overnight: false, status: 'active',
    });
}

async function loadItem(id) {
    const d = await ShiftService.getById(id);
    Object.assign(form, {
        company_id:                    d.company_id ? Number(d.company_id) : null,
        name:                          d.name                          ?? '',
        start_time:                    d.start_time                    ?? '09:00',
        end_time:                      d.end_time                      ?? '17:00',
        grace_period_minutes:          Number(d.grace_period_minutes          ?? 15),
        early_exit_threshold_minutes:  Number(d.early_exit_threshold_minutes  ?? 15),
        working_hours:                 Number(d.working_hours                 ?? 8),
        is_overnight:                  !!parseInt(d.is_overnight ?? 0),
        status:                        d.status                        ?? 'active',
    });
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    errors.company_id = '';
    errors.name = '';
    errors.start_time = '';
    errors.end_time = '';
    errors.working_hours = '';
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

function validate() {
    errors.company_id    = form.company_id           ? '' : 'Company is required.';
    errors.name          = form.name.trim()          ? '' : 'Shift name is required.';
    errors.start_time    = form.start_time           ? '' : 'Start time is required.';
    errors.end_time      = form.end_time             ? '' : 'End time is required.';
    errors.working_hours = form.working_hours != null && form.working_hours !== '' ? '' : 'Working hours is required.';
    return !Object.values(errors).some(Boolean);
}

async function save() {
    if (!validate()) return;

    saving.value = true;
    try {
        const body = {
            company_id:                    form.company_id,
            name:                          form.name.trim(),
            start_time:                    form.start_time,
            end_time:                      form.end_time,
            grace_period_minutes:          Number(form.grace_period_minutes),
            early_exit_threshold_minutes:  Number(form.early_exit_threshold_minutes),
            working_hours:                 Number(form.working_hours),
            is_overnight:                  form.is_overnight ? 1 : 0,
            status:                        form.status,
        };
        if (isEdit.value) {
            await ShiftService.update(props.editId, body);
            toast.add({ severity: 'success', detail: 'Shift updated.', life: 3000 });
        } else {
            await ShiftService.create(body);
            toast.add({ severity: 'success', detail: 'Shift created.', life: 3000 });
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
                <label class="font-medium">Shift Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. Morning Shift" :invalid="!!errors.name" />
                <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Start Time <span class="text-red-500">*</span></label>
                <InputText v-model="form.start_time" fluid placeholder="HH:MM" :invalid="!!errors.start_time" />
                <small v-if="errors.start_time" class="text-red-500">{{ errors.start_time }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">End Time <span class="text-red-500">*</span></label>
                <InputText v-model="form.end_time" fluid placeholder="HH:MM" :invalid="!!errors.end_time" />
                <small v-if="errors.end_time" class="text-red-500">{{ errors.end_time }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Working Hours <span class="text-red-500">*</span></label>
                <InputNumber v-model="form.working_hours" :min="0" :max="24" :maxFractionDigits="2" fluid
                    :invalid="!!errors.working_hours" />
                <small v-if="errors.working_hours" class="text-red-500">{{ errors.working_hours }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Grace Period (min)</label>
                <InputNumber v-model="form.grace_period_minutes" :min="0" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Early Exit Threshold (min)</label>
                <InputNumber v-model="form.early_exit_threshold_minutes" :min="0" fluid />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox v-model="form.is_overnight" :binary="true" inputId="is_overnight" />
            <label for="is_overnight" class="cursor-pointer">Overnight Shift</label>
        </div>
    </div>
</template>
