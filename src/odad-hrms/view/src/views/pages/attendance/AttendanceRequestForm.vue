<script setup>
import { reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { AttendanceService } from '@/service/AttendanceService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);

const requestTypeOptions = [
    { label: 'Missing Check-In',  value: 'missing_checkin'  },
    { label: 'Missing Check-Out', value: 'missing_checkout' },
    { label: 'Wrong Time',        value: 'wrong_time'       },
    { label: 'Other',             value: 'other'            },
];

const form = reactive({
    date:         null,
    request_type: 'missing_checkin',
    requested_in: null,
    requested_out: null,
    reason:       '',
});

function toDateTime(val) {
    if (!val) return null;
    if (typeof val === 'string') return val;
    const d = new Date(val);
    return isNaN(d) ? null : d.toISOString().slice(0, 19).replace('T', ' ');
}

function reset() {
    Object.assign(form, {
        date: null, request_type: 'missing_checkin',
        requested_in: null, requested_out: null, reason: '',
    });
}

watch(() => props.modelValue, (v) => { if (v) reset(); });

async function save() {
    if (!form.date) return toast.add({ severity: 'warn', detail: 'Date is required.', life: 3000 });
    if (!form.reason.trim()) return toast.add({ severity: 'warn', detail: 'Reason is required.', life: 3000 });

    saving.value = true;
    try {
        const d = new Date(form.date);
        const dateStr = isNaN(d) ? form.date : d.toISOString().slice(0, 10);
        await AttendanceService.createRequest({
            date:          dateStr,
            request_type:  form.request_type,
            requested_in:  toDateTime(form.requested_in),
            requested_out: toDateTime(form.requested_out),
            reason:        form.reason.trim(),
        });
        toast.add({ severity: 'success', detail: 'Attendance request submitted.', life: 3000 });
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
                <label class="font-medium">Date <span class="text-red-500">*</span></label>
                <HrDatePicker v-model="form.date" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Request Type</label>
                <Select v-model="form.request_type" :options="requestTypeOptions"
                    optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Requested Check-In Time</label>
                <HrDatePicker v-model="form.requested_in" timeOnly showIcon fluid hourFormat="12" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Requested Check-Out Time</label>
                <HrDatePicker v-model="form.requested_out" timeOnly showIcon fluid hourFormat="12" />
            </div>
        </div>
        <div class="flex flex-col gap-2">
            <label class="font-medium">Reason <span class="text-red-500">*</span></label>
            <Textarea v-model="form.reason" rows="3" fluid placeholder="Explain the reason for this request" />
        </div>
    </div>
</template>
