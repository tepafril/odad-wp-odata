<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { CompensatoryRequestService } from '@/service/CompensatoryRequestService';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import { EmployeeService } from '@/service/EmployeeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});
const emit    = defineEmits(['update:modelValue', 'saved']);
const toast   = useToast();
const saving  = ref(false);
const leaveTypeOptions = ref([]);
const employeeOptions  = ref([]);

const form = reactive({
    employee_id:   null,
    leave_type_id: null,
    work_date:     null,
    days:          1,
    reason:        '',
    expires_at:    null,
});

const daysOptions = [
    { label: '0.5 day', value: 0.5 },
    { label: '1 day',   value: 1   },
];

function reset() {
    Object.assign(form, {
        employee_id: null, leave_type_id: null, work_date: null,
        days: 1, reason: '', expires_at: null,
    });
}

function genderLabel(g) {
    if (!g) return '';
    return g.charAt(0).toUpperCase();
}

function toDate(val) {
    if (!val) return null;
    if (val instanceof Date) {
        const y = val.getFullYear();
        const m = String(val.getMonth() + 1).padStart(2, '0');
        const d = String(val.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    const d = new Date(val);
    if (isNaN(d)) return null;
    return toDate(d);
}

async function loadOptions() {
    try {
        const [lt, emps] = await Promise.all([
            LeaveTypeService.getList({ per_page: 500, status: 'active' }),
            EmployeeService.getList({ per_page: 500 }),
        ]);
        leaveTypeOptions.value = (lt?.items ?? []).map(t => ({ ...t, id: Number(t.id) }));
        employeeOptions.value = (emps?.items ?? []).map(e => {
            const name = [e.first_name, e.last_name].filter(Boolean).join(' ');
            const gender = e.gender ? ` (${genderLabel(e.gender)})` : '';
            const sub = e.employee_number || `#${e.id}`;
            return {
                id:    Number(e.id),
                label: name + gender,
                sub,
                searchText: `${name} ${sub} ${e.id}`,
            };
        });
    } catch {
        leaveTypeOptions.value = [];
        employeeOptions.value  = [];
    }
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    await loadOptions();
}, { immediate: true });

async function save() {
    if (!form.employee_id)   return toast.add({ severity: 'warn', detail: 'Employee is required.', life: 3000 });
    if (!form.leave_type_id) return toast.add({ severity: 'warn', detail: 'Leave type is required.', life: 3000 });
    if (!form.work_date)     return toast.add({ severity: 'warn', detail: 'Work date is required.', life: 3000 });

    saving.value = true;
    try {
        await CompensatoryRequestService.create({
            employee_id:   Number(form.employee_id),
            leave_type_id: Number(form.leave_type_id),
            work_date:     toDate(form.work_date),
            days:          form.days,
            reason:        form.reason?.trim() || null,
            expires_at:    toDate(form.expires_at) || null,
        });
        toast.add({ severity: 'success', detail: 'Compensatory request submitted.', life: 3000 });
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
                <label class="font-medium">Employee <span class="text-red-500">*</span></label>
                <Select v-model="form.employee_id" :options="employeeOptions"
                    optionValue="id" optionLabel="searchText" placeholder="Select employee" fluid filter
                    filterPlaceholder="Search by name or ID...">
                    <template #value="{ value }">
                        <span v-if="value">{{ employeeOptions.find(e => e.id === value)?.label ?? value }}</span>
                        <span v-else class="text-surface-400">Select employee</span>
                    </template>
                    <template #option="{ option }">
                        <div>
                            <div class="font-medium">{{ option.label }}</div>
                            <div class="text-surface-500 text-xs">{{ option.sub }}</div>
                        </div>
                    </template>
                </Select>
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Leave Type <span class="text-red-500">*</span></label>
                <Select v-model="form.leave_type_id" :options="leaveTypeOptions"
                    optionLabel="name" optionValue="id" placeholder="Select leave type" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Work Date <span class="text-red-500">*</span></label>
                <HrDatePicker v-model="form.work_date" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Days</label>
                <Select v-model="form.days" :options="daysOptions"
                    optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Expires At</label>
                <HrDatePicker v-model="form.expires_at" showIcon fluid />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="font-medium">Reason</label>
            <Textarea v-model="form.reason" rows="3" fluid placeholder="Reason for compensatory leave (optional)" />
        </div>
    </div>
</template>
