<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { LoanService } from '@/service/LoanService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const route  = useRoute();
const router = useRouter();
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!route.params.id);

const form = reactive({
    employee_id:           '',
    loan_type:             '',
    amount:                null,
    disbursement_date:     null,
    repayment_start_date:  null,
    repayment_months:      null,
    monthly_deduction:     null,
    interest_rate:         0,
    notes:                 '',
});

onMounted(async () => {
    if (!isEdit.value) return;
    try {
        const d = await LoanService.getById(route.params.id);
        Object.assign(form, {
            employee_id:          d.employee_id,
            loan_type:            d.loan_type ?? '',
            amount:               Number(d.amount),
            disbursement_date:    d.disbursement_date ?? null,
            repayment_start_date: d.repayment_start_date ?? null,
            repayment_months:     Number(d.repayment_months),
            monthly_deduction:    Number(d.monthly_deduction),
            interest_rate:        Number(d.interest_rate ?? 0),
            notes:                d.notes ?? '',
        });
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
});

async function save() {
    if (!form.employee_id)          return toast.add({ severity: 'warn', detail: 'Employee ID is required.', life: 3000 });
    if (!form.loan_type.trim())     return toast.add({ severity: 'warn', detail: 'Loan type is required.', life: 3000 });
    if (!form.amount)               return toast.add({ severity: 'warn', detail: 'Amount is required.', life: 3000 });
    if (!form.disbursement_date)    return toast.add({ severity: 'warn', detail: 'Disbursement date is required.', life: 3000 });
    if (!form.repayment_start_date) return toast.add({ severity: 'warn', detail: 'Repayment start date is required.', life: 3000 });
    if (!form.repayment_months)     return toast.add({ severity: 'warn', detail: 'Repayment months is required.', life: 3000 });
    if (!form.monthly_deduction)    return toast.add({ severity: 'warn', detail: 'Monthly deduction is required.', life: 3000 });

    saving.value = true;
    try {
        const toYmd = (d) => !d ? null : (d instanceof Date ? d.toISOString().slice(0, 10) : d);
        const body = {
            employee_id:          form.employee_id,
            loan_type:            form.loan_type.trim(),
            amount:               Number(form.amount),
            disbursement_date:    toYmd(form.disbursement_date),
            repayment_start_date: toYmd(form.repayment_start_date),
            repayment_months:     Number(form.repayment_months),
            monthly_deduction:    Number(form.monthly_deduction),
            interest_rate:        Number(form.interest_rate),
            notes:                form.notes.trim() || null,
        };
        if (isEdit.value) {
            await LoanService.update(route.params.id, body);
            toast.add({ severity: 'success', detail: 'Loan updated.', life: 3000 });
        } else {
            await LoanService.create(body);
            toast.add({ severity: 'success', detail: 'Loan created.', life: 3000 });
        }
        router.push({ name: 'loans' });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card w-full !max-w-full" style="max-width:44rem">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="font-semibold text-xl">{{ isEdit ? 'Edit Loan' : 'New Loan' }}</div>
            <div class="flex gap-2">
                <Button label="Cancel" severity="secondary" @click="router.back()" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Employee ID <span class="text-red-500">*</span></label>
                    <InputText v-model="form.employee_id" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Loan Type <span class="text-red-500">*</span></label>
                    <InputText v-model="form.loan_type" fluid placeholder="e.g. Personal Loan, Vehicle Loan" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Loan Amount <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Interest Rate (%)</label>
                    <InputNumber v-model="form.interest_rate" :minFractionDigits="2" :maxFractionDigits="2" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Disbursement Date <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.disbursement_date" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Repayment Start Date <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.repayment_start_date" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Repayment Months <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.repayment_months" :min="1" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Monthly Deduction <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.monthly_deduction" :minFractionDigits="2" :maxFractionDigits="2" fluid />
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Notes</label>
                <Textarea v-model="form.notes" rows="3" fluid placeholder="Optional notes" />
            </div>
        </div>
    </div>
</template>
