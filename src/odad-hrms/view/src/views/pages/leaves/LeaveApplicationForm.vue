<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { LeaveApplicationService } from '@/service/LeaveApplicationService';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import { EmployeeService } from '@/service/EmployeeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit    = defineEmits(['update:modelValue', 'saved']);
const toast   = useToast();
const saving  = ref(false);
const isEdit  = computed(() => !!props.editId);
const leaveTypeOptions = ref([]);
const employeeOptions  = ref([]);

const form = reactive({
    employee_id:    null,
    leave_type_id:  null,
    from_date:      null,
    to_date:        null,
    half_day:       false,
    half_day_date:  null,
    half_day_period: 'morning',
    reason:         '',
    status:         'pending',
});

const halfDayPeriodOptions = [
    { label: 'Morning',   value: 'morning' },
    { label: 'Afternoon', value: 'afternoon' },
];

function reset() {
    Object.assign(form, {
        employee_id: null, leave_type_id: null, from_date: null, to_date: null,
        half_day: false, half_day_date: null, half_day_period: 'morning',
        reason: '', status: 'pending',
    });
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

const leaveDays = computed(() => {
    const from = toDate(form.from_date);
    const to   = toDate(form.to_date);
    if (!from || !to) return null;
    const diff = (new Date(to) - new Date(from)) / 86400000 + 1;
    if (diff < 1) return null;
    if (form.half_day) return diff - 0.5;
    return diff;
});

const leaveDaysLabel = computed(() => {
    if (leaveDays.value == null) return '';
    const d = leaveDays.value;
    return d === 1 ? '1 day' : `${d} days`;
});

function genderLabel(g) {
    if (!g) return '';
    return g.charAt(0).toUpperCase();
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

async function loadItem(id) {
    const d = await LeaveApplicationService.getById(id);
    form.employee_id     = d.employee_id    ? Number(d.employee_id)    : null;
    form.leave_type_id   = d.leave_type_id  ? Number(d.leave_type_id)  : null;
    form.from_date       = d.from_date      || null;
    form.to_date         = d.to_date        || null;
    form.half_day        = !!parseInt(d.half_day);
    form.half_day_date   = d.half_day_date  || null;
    form.half_day_period = d.half_day_period || 'morning';
    form.reason          = d.reason         ?? '';
    form.status          = d.status         ?? 'pending';
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    await loadOptions();
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

async function save() {
    if (!form.employee_id)  return toast.add({ severity: 'warn', detail: 'Employee ID is required.', life: 3000 });
    if (!form.leave_type_id) return toast.add({ severity: 'warn', detail: 'Leave type is required.', life: 3000 });
    if (!form.from_date)    return toast.add({ severity: 'warn', detail: 'From date is required.', life: 3000 });
    if (!form.to_date)      return toast.add({ severity: 'warn', detail: 'To date is required.', life: 3000 });

    saving.value = true;
    try {
        const body = {
            employee_id:     Number(form.employee_id),
            leave_type_id:   Number(form.leave_type_id),
            from_date:       toDate(form.from_date),
            to_date:         toDate(form.to_date),
            half_day:        form.half_day ? 1 : 0,
            half_day_date:   form.half_day ? toDate(form.half_day_date) : null,
            half_day_period: form.half_day ? form.half_day_period : null,
            reason:          form.reason?.trim() || null,
        };
        if (isEdit.value) {
            await LeaveApplicationService.update(props.editId, body);
            toast.add({ severity: 'success', detail: 'Leave application updated.', life: 3000 });
        } else {
            await LeaveApplicationService.create(body);
            toast.add({ severity: 'success', detail: 'Leave application submitted.', life: 3000 });
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
                <label class="font-medium">From Date <span class="text-red-500">*</span></label>
                <HrDatePicker v-model="form.from_date" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">To Date <span class="text-red-500">*</span></label>
                <HrDatePicker v-model="form.to_date" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Total Leave</label>
                <InputText :modelValue="leaveDaysLabel" readonly fluid placeholder="Select dates to calculate" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Checkbox v-model="form.half_day" :binary="true" inputId="half_day" />
            <label for="half_day" class="cursor-pointer font-medium">Half Day</label>
        </div>

        <div v-if="form.half_day" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Half Day Date</label>
                <HrDatePicker v-model="form.half_day_date" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Period</label>
                <Select v-model="form.half_day_period" :options="halfDayPeriodOptions"
                    optionLabel="label" optionValue="value" fluid />
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <label class="font-medium">Reason</label>
            <Textarea v-model="form.reason" rows="3" fluid placeholder="Reason for leave (optional)" />
        </div>
    </div>
</template>
