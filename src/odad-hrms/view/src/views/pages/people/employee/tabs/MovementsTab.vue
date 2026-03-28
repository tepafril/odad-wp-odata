<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { EmployeeService } from '@/service/EmployeeService';
import { DepartmentService } from '@/service/DepartmentService';
import { DesignationService } from '@/service/DesignationService';
import { BranchService } from '@/service/BranchService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({ employeeId: { type: Number, required: true } });
const toast  = useToast();

const items   = ref([]);
const loading = ref(false);
const dialogVisible = ref(false);
const saving   = ref(false);

const departmentOptions  = ref([]);
const designationOptions = ref([]);
const branchOptions      = ref([]);

const movementTypeOptions = [
    { label: 'Promotion',      value: 'promotion'      },
    { label: 'Demotion',       value: 'demotion'       },
    { label: 'Transfer',       value: 'transfer'       },
    { label: 'Redesignation',  value: 'redesignation'  },
    { label: 'Confirmation',   value: 'confirmation'   },
];

const statusSeverity = { draft: 'secondary', pending: 'warn', approved: 'success', rejected: 'danger', cancelled: 'secondary' };

const form = ref({
    movement_type: 'promotion', effective_date: null,
    to_department_id: null, to_designation_id: null, to_branch_id: null, reason: '',
});

onMounted(async () => {
    const [depts, desig, branches] = await Promise.all([
        DepartmentService.getList({ per_page: 500 }),
        DesignationService.getList({ per_page: 500 }),
        BranchService.getList({ per_page: 500 }),
    ]);
    departmentOptions.value  = depts?.items   ?? [];
    designationOptions.value = desig?.items   ?? [];
    branchOptions.value      = branches?.items ?? [];
    load();
});

async function load() {
    loading.value = true;
    try { const d = await EmployeeService.getMovements(props.employeeId); items.value = d?.items ?? d ?? []; }
    catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { loading.value = false; }
}

function toDateStr(v) { if (!v) return null; if (typeof v === 'string') return v; const d = new Date(v); return isNaN(d) ? null : d.toISOString().slice(0,10); }

function openNew() {
    Object.assign(form.value, { movement_type: 'promotion', effective_date: null, to_department_id: null, to_designation_id: null, to_branch_id: null, reason: '' });
    dialogVisible.value = true;
}

async function save() {
    if (!form.value.effective_date) return toast.add({ severity: 'warn', detail: 'Effective date is required.', life: 3000 });
    saving.value = true;
    try {
        await EmployeeService.addMovement(props.employeeId, { ...form.value, effective_date: toDateStr(form.value.effective_date) });
        toast.add({ severity: 'success', detail: 'Movement recorded.', life: 3000 });
        dialogVisible.value = false; load();
    } catch (e) { toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 }); }
    finally { saving.value = false; }
}
</script>

<template>
    <div>
        <div class="flex justify-end mb-3">
            <Button label="Add Movement" icon="pi pi-plus" size="small" @click="openNew" />
        </div>
        <DataTable :value="items" :loading="loading" dataKey="id">
            <template #empty><div class="text-center py-6 text-surface-500">No movement records.</div></template>
            <Column field="movement_type"   header="Type"           style="min-width:10rem">
                <template #body="{ data }">{{ data.movement_type?.replace('_', ' ') }}</template>
            </Column>
            <Column field="effective_date"  header="Effective Date" style="min-width:10rem" />
            <Column field="status"          header="Status"         style="min-width:9rem">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity[data.status] ?? 'secondary'" />
                </template>
            </Column>
            <Column field="reason"          header="Reason"         style="min-width:14rem">
                <template #body="{ data }"><span class="text-sm text-surface-600">{{ data.reason || '—' }}</span></template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" header="Add Movement"
            modal :style="{ width: '42rem' }" :dismissableMask="true">
            <div class="flex flex-col gap-4 pt-1">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Movement Type</label>
                        <Select v-model="form.movement_type" :options="movementTypeOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Effective Date <span class="text-red-500">*</span></label>
                        <HrDatePicker v-model="form.effective_date" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">To Department</label>
                        <Select v-model="form.to_department_id" :options="departmentOptions" optionLabel="name" optionValue="id" placeholder="No change" showClear fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">To Designation</label>
                        <Select v-model="form.to_designation_id" :options="designationOptions" optionLabel="name" optionValue="id" placeholder="No change" showClear fluid />
                    </div>
                    <div class="flex flex-col gap-2 col-span-2">
                        <label class="font-medium">To Branch</label>
                        <Select v-model="form.to_branch_id" :options="branchOptions" optionLabel="name" optionValue="id" placeholder="No change" showClear fluid />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Reason</label>
                    <Textarea v-model="form.reason" rows="3" fluid />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>
