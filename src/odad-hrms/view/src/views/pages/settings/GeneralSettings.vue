<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SettingsService } from '@/service/SettingsService';

const toast = useToast();
const saving = ref(false);
const loading = ref(false);

const weekStartOptions = [
    { label: 'Monday', value: 'monday' },
    { label: 'Sunday', value: 'sunday' },
    { label: 'Saturday', value: 'saturday' },
];

const workingDayOptions = [
    { label: 'Monday', value: 'monday' },
    { label: 'Tuesday', value: 'tuesday' },
    { label: 'Wednesday', value: 'wednesday' },
    { label: 'Thursday', value: 'thursday' },
    { label: 'Friday', value: 'friday' },
    { label: 'Saturday', value: 'saturday' },
    { label: 'Sunday', value: 'sunday' },
];

const form = ref({
    employee_number_format: 'EMP{number}',
    employee_number_padding: 4,
    date_format: 'Y-m-d',
    week_start: 'monday',
    working_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    probation_period_months: 3,
});

onMounted(async () => {
    loading.value = true;
    try {
        const settings = await SettingsService.get();
        Object.keys(form.value).forEach(k => {
            if (settings[k] !== undefined) form.value[k] = settings[k];
        });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        await SettingsService.update(form.value);
        toast.add({ severity: 'success', summary: 'Saved', detail: 'General settings saved.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">General Settings</h2>
        <div v-if="loading" class="text-surface-400">Loading...</div>
        <div v-else class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Employee Number Format</label>
                <InputText v-model="form.employee_number_format" class="w-full" />
                <small class="text-surface-400">Use {number} as placeholder, e.g. EMP{number}</small>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Employee Number Padding</label>
                <InputNumber v-model="form.employee_number_padding" :min="1" :max="10" class="w-32" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Date Format</label>
                <InputText v-model="form.date_format" class="w-full" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Week Starts On</label>
                <Dropdown v-model="form.week_start" :options="weekStartOptions" option-label="label" option-value="value" class="w-44" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Working Days</label>
                <div class="flex flex-wrap gap-3">
                    <div v-for="day in workingDayOptions" :key="day.value" class="flex items-center gap-2">
                        <Checkbox v-model="form.working_days" :value="day.value" :input-id="day.value" />
                        <label :for="day.value" class="text-sm capitalize">{{ day.label }}</label>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Probation Period (months)</label>
                <InputNumber v-model="form.probation_period_months" :min="0" :max="24" class="w-32" />
            </div>
            <div class="mt-2">
                <Button label="Save Settings" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
