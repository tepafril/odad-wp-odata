<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { EmployeeSalaryService } from '@/service/EmployeeSalaryService';
import { SalaryStructureService } from '@/service/SalaryStructureService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const toast   = useToast();
const saving  = ref(false);
const current = ref(null);
const structures = ref([]);

const form = reactive({
    employee_id:          '',
    salary_structure_id:  null,
    base_amount:          null,
    currency:             'USD',
    effective_from:       null,
    payment_method:       'bank_transfer',
    notes:                '',
});

const paymentOptions = [
    { label: 'Bank Transfer', value: 'bank_transfer' },
    { label: 'Cash',          value: 'cash' },
    { label: 'Cheque',        value: 'cheque' },
];

const structureOptions = ref([]);

onMounted(async () => {
    try {
        const data = await SalaryStructureService.getList({ per_page: 999, is_active: 1 });
        structures.value       = data?.items ?? [];
        structureOptions.value = structures.value.map(s => ({ label: s.name, value: s.id }));
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
});

async function loadCurrent() {
    if (!form.employee_id) return;
    try {
        current.value = await EmployeeSalaryService.getCurrent(form.employee_id);
        if (current.value) {
            form.salary_structure_id = current.value.salary_structure_id;
            form.base_amount         = Number(current.value.base_amount);
            form.currency            = current.value.currency;
            form.payment_method      = current.value.payment_method;
        }
    } catch {
        current.value = null;
    }
}

async function save() {
    if (!form.employee_id)         return toast.add({ severity: 'warn', detail: 'Employee ID is required.', life: 3000 });
    if (!form.salary_structure_id) return toast.add({ severity: 'warn', detail: 'Salary structure is required.', life: 3000 });
    if (!form.base_amount)         return toast.add({ severity: 'warn', detail: 'Base amount is required.', life: 3000 });
    if (!form.effective_from)      return toast.add({ severity: 'warn', detail: 'Effective from date is required.', life: 3000 });

    saving.value = true;
    try {
        const toYmd = (d) => d instanceof Date ? d.toISOString().slice(0, 10) : d;
        await EmployeeSalaryService.assign(form.employee_id, {
            salary_structure_id: form.salary_structure_id,
            base_amount:         Number(form.base_amount),
            currency:            form.currency,
            effective_from:      toYmd(form.effective_from),
            payment_method:      form.payment_method,
            notes:               form.notes.trim() || null,
        });
        toast.add({ severity: 'success', detail: 'Salary assigned successfully.', life: 3000 });
        await loadCurrent();
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
            <div class="font-semibold text-xl">Assign Employee Salary</div>
            <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
        </div>

        <!-- Current salary display -->
        <div v-if="current" class="surface-100 border border-surface rounded-xl p-4 mb-5 text-sm">
            <div class="font-semibold mb-1">Current Active Salary</div>
            <div class="text-surface-600">
                Structure #{{ current.salary_structure_id }} · Base: {{ current.currency }} {{ Number(current.base_amount).toFixed(2) }} · From: {{ current.effective_from }}
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="font-medium">Employee ID <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <InputText v-model="form.employee_id" fluid placeholder="Enter employee ID" />
                    <Button label="Load" severity="secondary" @click="loadCurrent" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-medium">Salary Structure <span class="text-red-500">*</span></label>
                <Select v-model="form.salary_structure_id" :options="structureOptions"
                    optionLabel="label" optionValue="value" fluid placeholder="Select structure" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Base Amount <span class="text-red-500">*</span></label>
                    <InputNumber v-model="form.base_amount" :minFractionDigits="2" :maxFractionDigits="2" fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Currency</label>
                    <InputText v-model="form.currency" fluid maxlength="3" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Effective From <span class="text-red-500">*</span></label>
                    <HrDatePicker v-model="form.effective_from" showIcon fluid />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Payment Method</label>
                    <Select v-model="form.payment_method" :options="paymentOptions" optionLabel="label" optionValue="value" fluid />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-medium">Notes</label>
                <Textarea v-model="form.notes" rows="2" fluid placeholder="Optional notes" />
            </div>
        </div>
    </div>
</template>
