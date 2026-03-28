<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SettingsService } from '@/service/SettingsService';

const toast = useToast();
const saving = ref(false);
const loading = ref(false);

const frequencyOptions = [
    { label: 'Monthly', value: 'monthly' },
    { label: 'Bi-weekly', value: 'biweekly' },
    { label: 'Weekly', value: 'weekly' },
];

const form = ref({
    payroll_frequency: 'monthly',
    payroll_cutoff_day: 25,
    enable_loan_deduction: true,
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
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Payroll settings saved.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Payroll Settings</h2>
        <div v-if="loading" class="text-surface-400">Loading...</div>
        <div v-else class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Payroll Frequency</label>
                <Dropdown v-model="form.payroll_frequency" :options="frequencyOptions" option-label="label" option-value="value" class="w-44" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Payroll Cutoff Day</label>
                <InputNumber v-model="form.payroll_cutoff_day" :min="1" :max="31" class="w-32" />
                <small class="text-surface-400">Day of month payroll period ends.</small>
            </div>
            <div class="flex items-center gap-3">
                <Checkbox v-model="form.enable_loan_deduction" :binary="true" input-id="enable_loan_deduction" />
                <label for="enable_loan_deduction" class="text-sm font-medium">Enable Automatic Loan Deductions</label>
            </div>
            <div class="mt-2">
                <Button label="Save Settings" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
