<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { AdditionalSalaryService } from '@/service/AdditionalSalaryService';
import { SalaryComponentService } from '@/service/SalaryComponentService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const route  = useRoute();
const router = useRouter();
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!route.params.id);

const componentOptions = ref([]);

const form = reactive({
    employee_id:          '',
    salary_component_id:  null,
    amount:               null,
    payroll_date:         null,
    reason:               '',
    is_recurring:         false,
    recurring_from:       null,
    recurring_to:         null,
});

onMounted(async () => {
    try {
        const data = await SalaryComponentService.getList({ per_page: 999, status: 'active' });
        componentOptions.value = (data?.items ?? []).map(c => ({ label: `${c.name} (${c.code})`, value: c.id }));
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }

    if (isEdit.value) {
        try {
            const d = await AdditionalSalaryService.getById(route.params.id);
            Object.assign(form, {
                employee_id:         d.employee_id,
                salary_component_id: Number(d.salary_component_id),
                amount:              Number(d.amount),
                payroll_date:        d.payroll_date,
                reason:              d.reason ?? '',
                is_recurring:        !!parseInt(d.is_recurring ?? 0),
                recurring_from:      d.recurring_from ?? null,
                recurring_to:        d.recurring_to ?? null,
            });
        } catch (e) {
            toast.add({ severity: 'error', detail: e.message, life: 5000 });
        }
    }
});

async function save() {
    if (!form.employee_id)        return toast.add({ severity: 'warn', detail: 'Employee ID is required.', life: 3000 });
    if (!form.salary_component_id) return toast.add({ severity: 'warn', detail: 'Salary component is required.', life: 3000 });
    if (!form.amount)             return toast.add({ severity: 'warn', detail: 'Amount is required.', life: 3000 });
    if (!form.payroll_date)       return toast.add({ severity: 'warn', detail: 'Payroll date is required.', life: 3000 });

    saving.value = true;
    try {
        const toYmd = (d) => !d ? null : (d instanceof Date ? d.toISOString().slice(0, 10) : d);
        const body = {
            employee_id:         form.employee_id,
            salary_component_id: form.salary_component_id,
            amount:              Number(form.amount),
            payroll_date:        toYmd(form.payroll_date),
            reason:              form.reason.trim() || null,
            is_recurring:        form.is_recurring ? 1 : 0,
            recurring_from:      form.is_recurring ? toYmd(form.recurring_from) : null,
            recurring_to:        form.is_recurring ? toYmd(form.recurring_to) : null,
        };
        if (isEdit.value) {
            await AdditionalSalaryService.update(route.params.id, body);
            toast.add({ severity: 'success', detail: 'Updated.', life: 3000 });
        } else {
            await AdditionalSalaryService.create(body);
            toast.add({ severity: 'success', detail: 'Created.', life: 3000 });
        }
        router.push({ name: 'additional-salary' });
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
            <div class="font-semibold text-xl">{{ isEdit ? 'Edit Additional Salary' : 'New Additional Salary' }}</div>
            <div class="flex gap-2">
                <Button label="Cancel" severity="secondary" @click="router.back()" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Employee ID <span class="text-red-500">*</span></label>
                <InputText v-model="form.employee_id" fluid placeholder="Enter employee ID" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Salary Component <span class="text-red-500">*</span></label>
                <Select v-model="form.salary_component_id" :options="componentOptions"
                    optionLabel="label" optionValue="value" fluid placeholder="Select component" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Amount <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Payroll Date <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.payroll_date" showIcon fluid />
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Reason</label>
                <Textarea v-model="form.reason" rows="2" fluid placeholder="Optional reason" />
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_recurring" :binary="true" inputId="is_recurring" />
                <label for="is_recurring" class="cursor-pointer font-medium">Recurring</label>
            </div>
            <div v-if="form.is_recurring" class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6">
                <div class="flex flex-col gap-2">
                    <label class="font-medium text-sm">Recurring From</label>
                    <HrDatePicker v-model="form.recurring_from" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium text-sm">Recurring To</label>
                    <HrDatePicker v-model="form.recurring_to" showIcon fluid />
                </div>
            </div>
        </div>
    </div>
</template>
