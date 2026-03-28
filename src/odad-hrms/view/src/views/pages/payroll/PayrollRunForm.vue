<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { PayrollRunService } from '@/service/PayrollRunService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const router = useRouter();
const toast  = useToast();
const saving = ref(false);

const form = reactive({
    name:         '',
    company_id:   1,
    period_start: null,
    period_end:   null,
    payment_date: null,
    currency:     'USD',
    notes:        '',
});

async function save() {
    if (!form.name.trim())   return toast.add({ severity: 'warn', detail: 'Name is required.', life: 3000 });
    if (!form.period_start)  return toast.add({ severity: 'warn', detail: 'Period Start is required.', life: 3000 });
    if (!form.period_end)    return toast.add({ severity: 'warn', detail: 'Period End is required.', life: 3000 });
    if (!form.payment_date)  return toast.add({ severity: 'warn', detail: 'Payment Date is required.', life: 3000 });

    saving.value = true;
    try {
        const toYmd = (d) => d instanceof Date ? d.toISOString().slice(0, 10) : d;
        const created = await PayrollRunService.create({
            name:         form.name.trim(),
            company_id:   Number(form.company_id),
            period_start: toYmd(form.period_start),
            period_end:   toYmd(form.period_end),
            payment_date: toYmd(form.payment_date),
            currency:     form.currency,
            notes:        form.notes.trim() || null,
        });
        toast.add({ severity: 'success', detail: 'Payroll run created.', life: 3000 });
        router.push({ name: 'payroll-run-detail', params: { id: created.id } });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card w-full !max-w-full" style="max-width:40rem">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="font-semibold text-xl">New Payroll Run</div>
            <div class="flex gap-2">
                <Button label="Cancel" severity="secondary" @click="router.back()" />
                <Button label="Create" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. March 2026 Payroll" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Period Start <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.period_start" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Period End <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.period_end" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Payment Date <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.payment_date" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Currency</label>
                    <InputText v-model="form.currency" fluid maxlength="3" placeholder="USD" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-medium">Notes</label>
                <Textarea v-model="form.notes" rows="3" fluid placeholder="Optional notes" />
            </div>
        </div>
    </div>
</template>
