<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { ShiftService } from '@/service/ShiftService';
import { EmployeeService } from '@/service/EmployeeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!props.editId);

const employeeOptions = ref([]);
const shiftOptions    = ref([]);
const errors = reactive({ employee_id: '', shift_id: '', effective_from: '' });

const form = reactive({
    employee_id:    null,
    shift_id:       null,
    effective_from: null,
    effective_to:   null,
});

onMounted(async () => {
    try {
        const [emps, shifts] = await Promise.all([
            EmployeeService.getList({ per_page: 500 }),
            ShiftService.getList({ per_page: 500, status: 'active' }),
        ]);
        employeeOptions.value = (emps?.items ?? []).map(e => ({
            id: Number(e.id), name: `${e.first_name} ${e.last_name}`,
        }));
        shiftOptions.value = (shifts?.items ?? []).map(i => ({ ...i, id: Number(i.id) }));
    } catch {
        employeeOptions.value = [];
        shiftOptions.value    = [];
    }
});

function reset() {
    Object.assign(form, { employee_id: null, shift_id: null, effective_from: null, effective_to: null });
}

async function loadItem(id) {
    const d = await ShiftService.getAssignmentById(id);
    Object.assign(form, {
        employee_id:    d.employee_id ? Number(d.employee_id) : null,
        shift_id:       d.shift_id    ? Number(d.shift_id)    : null,
        effective_from: d.effective_from ?? null,
        effective_to:   d.effective_to   ?? null,
    });
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    errors.employee_id = '';
    errors.shift_id = '';
    errors.effective_from = '';
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

function validate() {
    errors.employee_id    = form.employee_id    ? '' : 'Employee is required.';
    errors.shift_id       = form.shift_id       ? '' : 'Shift is required.';
    errors.effective_from = form.effective_from  ? '' : 'Effective from is required.';
    return !Object.values(errors).some(Boolean);
}

async function save() {
    if (!validate()) return;

    saving.value = true;
    try {
        const body = {
            employee_id:    form.employee_id,
            shift_id:       form.shift_id,
            effective_from: form.effective_from,
            effective_to:   form.effective_to || null,
        };
        if (isEdit.value) {
            await ShiftService.updateAssignment(props.editId, body);
            toast.add({ severity: 'success', detail: 'Assignment updated.', life: 3000 });
        } else {
            await ShiftService.createAssignment(body);
            toast.add({ severity: 'success', detail: 'Assignment created.', life: 3000 });
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
        <div class="flex flex-col gap-2">
            <label class="font-medium">Employee <span class="text-red-500">*</span></label>
            <Select v-model="form.employee_id" :options="employeeOptions"
                optionLabel="name" optionValue="id" placeholder="Select employee" fluid filter
                :invalid="!!errors.employee_id" />
            <small v-if="errors.employee_id" class="text-red-500">{{ errors.employee_id }}</small>
        </div>
        <div class="flex flex-col gap-2">
            <label class="font-medium">Shift <span class="text-red-500">*</span></label>
            <Select v-model="form.shift_id" :options="shiftOptions"
                optionLabel="name" optionValue="id" placeholder="Select shift" fluid
                :invalid="!!errors.shift_id" />
            <small v-if="errors.shift_id" class="text-red-500">{{ errors.shift_id }}</small>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Effective From <span class="text-red-500">*</span></label>
                <HrDatePicker v-model="form.effective_from" showIcon fluid
                    :invalid="!!errors.effective_from" />
                <small v-if="errors.effective_from" class="text-red-500">{{ errors.effective_from }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Effective To</label>
                <HrDatePicker v-model="form.effective_to" showIcon fluid />
            </div>
        </div>
    </div>
</template>
