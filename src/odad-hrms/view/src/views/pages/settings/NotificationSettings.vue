<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { SettingsService } from '@/service/SettingsService';

const toast = useToast();
const saving = ref(false);
const loading = ref(false);

const form = ref({
    notify_leave_applied: true,
    notify_leave_approved: true,
    notify_leave_rejected: true,
    notify_payslip_ready: true,
    notify_document_expiry: true,
    notify_document_expiry_days: 30,
    notify_birthday: true,
    notify_probation_end: true,
    notify_probation_end_days: 7,
    email_from_name: '',
    email_from_address: '',
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
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Notification settings saved.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card max-w-2xl">
        <h2 class="text-xl font-semibold mb-4">Notification Settings</h2>
        <div v-if="loading" class="text-surface-400">Loading...</div>
        <div v-else class="flex flex-col gap-5">

            <div>
                <h3 class="font-medium text-surface-600 mb-2 text-sm uppercase tracking-wide">Email Sender</h3>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">From Name</label>
                        <InputText v-model="form.email_from_name" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">From Email Address</label>
                        <InputText v-model="form.email_from_address" type="email" class="w-full" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-medium text-surface-600 mb-2 text-sm uppercase tracking-wide">Leave Notifications</h3>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_leave_applied" :binary="true" input-id="nla" />
                        <label for="nla" class="text-sm">Notify HR when leave is applied</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_leave_approved" :binary="true" input-id="nlap" />
                        <label for="nlap" class="text-sm">Notify employee when leave is approved</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_leave_rejected" :binary="true" input-id="nlr" />
                        <label for="nlr" class="text-sm">Notify employee when leave is rejected</label>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-medium text-surface-600 mb-2 text-sm uppercase tracking-wide">Payroll Notifications</h3>
                <div class="flex items-center gap-3">
                    <Checkbox v-model="form.notify_payslip_ready" :binary="true" input-id="nps" />
                    <label for="nps" class="text-sm">Notify employee when payslip is ready</label>
                </div>
            </div>

            <div>
                <h3 class="font-medium text-surface-600 mb-2 text-sm uppercase tracking-wide">Document Expiry</h3>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_document_expiry" :binary="true" input-id="nde" />
                        <label for="nde" class="text-sm">Notify HR about expiring documents</label>
                    </div>
                    <div v-if="form.notify_document_expiry" class="flex items-center gap-2 ml-6">
                        <label class="text-sm">Days before expiry:</label>
                        <InputNumber v-model="form.notify_document_expiry_days" :min="1" :max="365" class="w-24" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-medium text-surface-600 mb-2 text-sm uppercase tracking-wide">HR Events</h3>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_birthday" :binary="true" input-id="nb" />
                        <label for="nb" class="text-sm">Notify HR on employee birthdays</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Checkbox v-model="form.notify_probation_end" :binary="true" input-id="npe" />
                        <label for="npe" class="text-sm">Notify HR before probation ends</label>
                    </div>
                    <div v-if="form.notify_probation_end" class="flex items-center gap-2 ml-6">
                        <label class="text-sm">Days before probation end:</label>
                        <InputNumber v-model="form.notify_probation_end_days" :min="1" :max="90" class="w-24" />
                    </div>
                </div>
            </div>

            <div class="mt-2">
                <Button label="Save Settings" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
