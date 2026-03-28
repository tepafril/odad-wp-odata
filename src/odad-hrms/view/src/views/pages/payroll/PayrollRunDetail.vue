<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { PayrollRunService } from '@/service/PayrollRunService';
import { PayslipService } from '@/service/PayslipService';

const route = useRoute();
const toast = useToast();

const run           = ref(null);
const loadingRun    = ref(false);
const payslips      = ref([]);
const totalRecords  = ref(0);
const loadingSlips  = ref(false);
const first         = ref(0);
const rows          = ref(10);

const statusSeverity = {
    draft: 'secondary', confirmed: 'warn', paid: 'success', cancelled: 'danger',
};

onMounted(loadAll);
watch([first, rows], loadPayslips);

async function loadAll() {
    await Promise.all([ loadRun(), loadPayslips() ]);
}

async function loadRun() {
    loadingRun.value = true;
    try {
        run.value = await PayrollRunService.getById(route.params.id);
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    } finally {
        loadingRun.value = false;
    }
}

async function loadPayslips() {
    loadingSlips.value = true;
    try {
        const data = await PayslipService.getList({
            payroll_run_id: route.params.id,
            page:           Math.floor(first.value / rows.value) + 1,
            per_page:       rows.value,
        });
        payslips.value    = data?.items ?? [];
        totalRecords.value = data?.total ?? 0;
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    } finally {
        loadingSlips.value = false;
    }
}

function onPage(e) { first.value = e.first; rows.value = e.rows; }

async function sendEmail(payslip) {
    try {
        await PayslipService.sendEmail(payslip.id);
        toast.add({ severity: 'success', detail: 'Email sent.', life: 3000 });
        loadPayslips();
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    }
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <div v-if="loadingRun" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl" />
        </div>

        <template v-else-if="run">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <div class="font-semibold text-xl mb-1">{{ run.name }}</div>
                    <p class="text-surface-500 m-0">{{ run.period_start }} – {{ run.period_end }}</p>
                </div>
                <Tag :value="run.status" :severity="{ draft:'secondary', calculated:'info', confirmed:'warn', paid:'success', cancelled:'danger' }[run.status] ?? 'secondary'" class="text-sm capitalize" />
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="surface-card border border-surface rounded-xl p-4 text-center">
                    <div class="text-surface-500 text-sm mb-1">Employees</div>
                    <div class="font-bold text-2xl">{{ run.total_employees }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-4 text-center">
                    <div class="text-surface-500 text-sm mb-1">Total Gross</div>
                    <div class="font-bold text-2xl">{{ Number(run.total_gross).toFixed(2) }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-4 text-center">
                    <div class="text-surface-500 text-sm mb-1">Total Deductions</div>
                    <div class="font-bold text-2xl text-red-500">{{ Number(run.total_deductions).toFixed(2) }}</div>
                </div>
                <div class="surface-card border border-surface rounded-xl p-4 text-center">
                    <div class="text-surface-500 text-sm mb-1">Total Net</div>
                    <div class="font-bold text-2xl text-green-600">{{ Number(run.total_net).toFixed(2) }}</div>
                </div>
            </div>

            <!-- Payslips table -->
            <div class="font-semibold text-lg mb-3">Payslips</div>
            <DataTable :value="payslips" :loading="loadingSlips" dataKey="id" lazy paginator
                :first="first" :rows="rows" :totalRecords="totalRecords"
                :rowsPerPageOptions="[5,10,25]"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
                @page="onPage">
                <template #empty><div class="text-center py-6 text-surface-500">No payslips generated yet.</div></template>

                <Column field="id"              header="ID"         style="min-width:4rem" />
                <Column field="employee_id"     header="Employee"   style="min-width:7rem" />
                <Column field="days_worked"     header="Days Worked" style="min-width:8rem" />
                <Column field="gross_pay"       header="Gross"      style="min-width:9rem">
                    <template #body="{ data }">{{ Number(data.gross_pay).toFixed(2) }}</template>
                </Column>
                <Column field="total_deductions" header="Deductions" style="min-width:9rem">
                    <template #body="{ data }">{{ Number(data.total_deductions).toFixed(2) }}</template>
                </Column>
                <Column field="net_pay"         header="Net"        style="min-width:9rem">
                    <template #body="{ data }">{{ Number(data.net_pay).toFixed(2) }}</template>
                </Column>
                <Column field="status"          header="Status"     style="min-width:8rem">
                    <template #body="{ data }">
                        <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                    </template>
                </Column>
                <Column field="email_sent"      header="Email"      style="min-width:6rem">
                    <template #body="{ data }">
                        <i :class="parseInt(data.email_sent) ? 'pi pi-check text-green-500' : 'pi pi-times text-surface-300'" />
                    </template>
                </Column>
                <Column header="Actions" style="min-width:8rem">
                    <template #body="{ data }">
                        <Button v-if="['confirmed','paid'].includes(data.status)"
                            icon="pi pi-envelope" text rounded size="small"
                            @click="sendEmail(data)" v-tooltip.top="'Send Email'" />
                    </template>
                </Column>
            </DataTable>
        </template>
    </div>
</template>
