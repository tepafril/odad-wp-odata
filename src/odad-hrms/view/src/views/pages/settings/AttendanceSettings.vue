<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SettingsService } from '@/service/SettingsService';

const toast = useToast();
const saving = ref(false);
const loading = ref(false);

const form = ref({
    default_shift_id: 0,
    enable_web_checkin: true,
    overtime_threshold_minutes: 30,
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
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Attendance settings saved.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Attendance Settings</h2>
        <div v-if="loading" class="text-surface-400">Loading...</div>
        <div v-else class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Default Shift ID</label>
                <InputNumber v-model="form.default_shift_id" :min="0" class="w-32" />
                <small class="text-surface-400">Enter 0 for no default shift.</small>
            </div>
            <div class="flex items-center gap-3">
                <Checkbox v-model="form.enable_web_checkin" :binary="true" input-id="enable_web_checkin" />
                <label for="enable_web_checkin" class="text-sm font-medium">Enable Web Check-in</label>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium">Overtime Threshold (minutes)</label>
                <InputNumber v-model="form.overtime_threshold_minutes" :min="0" :max="120" class="w-32" />
                <small class="text-surface-400">Minutes worked beyond shift before counted as overtime.</small>
            </div>
            <div class="mt-2">
                <Button label="Save Settings" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
