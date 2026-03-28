<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { PayslipService } from '@/service/PayslipService';

const route   = useRoute();
const toast   = useToast();
const payslip = ref(null);
const loading = ref(false);

const earnings   = computed(() => (payslip.value?.details ?? []).filter(d => d.type === 'earning'));
const deductions = computed(() => (payslip.value?.details ?? []).filter(d => d.type === 'deduction'));

const statusSeverity = { draft: 'secondary', confirmed: 'warn', paid: 'success', cancelled: 'danger' };

onMounted(async () => {
    loading.value = true;
    try {
        payslip.value = await PayslipService.getById(route.params.id);
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
});

async function sendEmail() {
    try {
        await PayslipService.sendEmail(route.params.id);
        toast.add({ severity: 'success', detail: 'Email sent.', life: 3000 });
        payslip.value = await PayslipService.getById(route.params.id);
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}
</script>

<template>
    <div class="card w-full !max-w-full" style="max-width:52rem">
        <div v-if="loading" class="text-center py-8"><i class="pi pi-spin pi-spinner text-2xl" /></div>

        <template v-else-if="payslip">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <div class="font-semibold text-xl mb-1">Payslip #{{ payslip.id }}</div>
                    <p class="text-surface-500 m-0">Employee #{{ payslip.employee_id }} · {{ payslip.period_start }} – {{ payslip.period_end }}</p>
                </div>
                <div class="flex gap-2 items-center">
                    <Tag class="capitalize" :value="payslip.status" :severity="statusSeverity[payslip.status] ?? 'secondary'" />
                    <Button v-if="['confirmed','paid'].includes(payslip.status)"
                        label="Send Email" icon="pi pi-envelope" size="small" @click="sendEmail" />
                </div>
            </div>

            <!-- Summary row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="surface-card border border-surface rounded-xl p-3 text-center">
                    <div class="text-surface-500 text-xs mb-1">Working Days</div>
                    <div class="font-bold text-lg">{{ payslip.total_working_days }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-3 text-center">
                    <div class="text-surface-500 text-xs mb-1">Days Worked</div>
                    <div class="font-bold text-lg">{{ payslip.days_worked }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-3 text-center">
                    <div class="text-surface-500 text-xs mb-1">Payment Method</div>
                    <div class="font-bold text-lg capitalize">{{ (payslip.payment_method ?? '').replace(/_/g,' ') }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-3 text-center">
                    <div class="text-surface-500 text-xs mb-1">Currency</div>
                    <div class="font-bold text-lg">{{ payslip.currency }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Earnings -->
                <div>
                    <div class="font-semibold text-base mb-3 text-green-700">Earnings</div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-surface-500 border-b border-surface">
                                <th class="text-left pb-2">Component</th>
                                <th class="text-right pb-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in earnings" :key="e.id" class="border-b border-surface-100">
                                <td class="py-1">
                                    {{ e.salary_component_id == 0 ? 'Base Salary' : `Component #${e.salary_component_id}` }}
                                    <Tag v-if="parseInt(e.is_statutory)" value="statutory" severity="info" class="ml-1 capitalize" style="font-size:0.65rem;padding:0.1rem 0.3rem" />
                                </td>
                                <td class="py-1 text-right">{{ Number(e.amount).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t-2 border-surface">
                                <td class="pt-2">Total Earnings</td>
                                <td class="pt-2 text-right text-green-700">{{ Number(payslip.total_earnings).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Deductions -->
                <div>
                    <div class="font-semibold text-base mb-3 text-red-600">Deductions</div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-surface-500 border-b border-surface">
                                <th class="text-left pb-2">Component</th>
                                <th class="text-right pb-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in deductions" :key="d.id" class="border-b border-surface-100">
                                <td class="py-1">Component #{{ d.salary_component_id }}</td>
                                <td class="py-1 text-right">{{ Number(d.amount).toFixed(2) }}</td>
                            </tr>
                            <tr v-if="deductions.length === 0">
                                <td colspan="2" class="py-2 text-surface-400">No deductions</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t-2 border-surface">
                                <td class="pt-2">Total Deductions</td>
                                <td class="pt-2 text-right text-red-600">{{ Number(payslip.total_deductions).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Net pay -->
            <div class="flex justify-end">
                <div class="surface-card border-2 border-green-500 rounded-xl p-4 min-w-48 text-right">
                    <div class="text-surface-500 text-sm">Net Pay</div>
                    <div class="font-bold text-3xl text-green-600">{{ payslip.currency }} {{ Number(payslip.net_pay).toFixed(2) }}</div>
                </div>
            </div>
        </template>
    </div>
</template>
