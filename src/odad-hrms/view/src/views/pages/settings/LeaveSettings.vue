<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SettingsService } from '@/service/SettingsService';

const toast = useToast();
const saving = ref(false);
const loading = ref(false);

const workflowOptions = [
    { label: 'Manager Approval', value: 'manager' },
    { label: 'HR Approval', value: 'hr' },
    { label: 'Auto Approve', value: 'auto' },
];

const monthOptions = Array.from({ length: 12 }, (_, i) => ({
    label: new Date(2000, i, 1).toLocaleString('default', { month: 'long' }),
    value: i + 1,
}));

const form = ref({
    leave_approval_workflow: 'manager',
    allow_backdated_leave: false,
    leave_year_start_month: 1,
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
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Leave settings saved.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Leave Settings</h2>
        <div v-if="loading" class="text-surface-400">Loading...</div>
        <div v-else class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Leave Approval Workflow</label>
                <Dropdown v-model="form.leave_approval_workflow" :options="workflowOptions" option-label="label" option-value="value" class="w-56" />
            </div>
            <div class="flex items-center gap-3">
                <Checkbox v-model="form.allow_backdated_leave" :binary="true" input-id="allow_backdated" />
                <label for="allow_backdated" class="text-sm font-medium">Allow Backdated Leave Applications</label>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Leave Year Start Month</label>
                <Dropdown v-model="form.leave_year_start_month" :options="monthOptions" option-label="label" option-value="value" class="w-44" />
            </div>
            <div class="mt-2">
                <Button label="Save Settings" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
