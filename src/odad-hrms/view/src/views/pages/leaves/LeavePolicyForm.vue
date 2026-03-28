<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { LeavePolicyService } from '@/service/LeavePolicyService';
import { LeaveTypeService } from '@/service/LeaveTypeService';
import { CompanyService } from '@/service/CompanyService';
import { DepartmentService } from '@/service/DepartmentService';
import { DesignationService } from '@/service/DesignationService';
import { EmployeeService } from '@/service/EmployeeService';
import { EmploymentTypeService } from '@/service/EmploymentTypeService';
import HrDatePicker from '@/components/HrDatePicker.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editId:     { type: Number,  default: null  },
});
const emit   = defineEmits(['update:modelValue', 'saved']);
const toast  = useToast();
const saving = ref(false);
const isEdit = computed(() => !!props.editId);

const companyOptions     = ref([]);
const leaveTypeOptions   = ref([]);
const departmentOptions     = ref([]);
const designationOptions    = ref([]);
const employeeOptions       = ref([]);
const employmentTypeOptions = ref([]);
const errors = reactive({ company_id: '', name: '' });

const form = reactive({
    company_id:        null,
    name:              '',
    effective_from:    null,
    effective_to:      null,
    status:            'active',
    conflict_strategy: 'highest_priority',
});

const details     = ref([]); // [{ leave_type_id, annual_allocation }]
const assignments = ref([]); // [{ id?, assignment_type, assignment_id, effective_from }]

const statusOptions = [
    { label: 'Active',   value: 'active'   },
    { label: 'Inactive', value: 'inactive' },
];
const conflictStrategyOptions = [
    { label: 'Highest Priority Wins', value: 'highest_priority' },
    { label: 'Sum All',               value: 'sum' },
    { label: 'Max Value',             value: 'max_value' },
];
const assignmentTypeOptions = [
    { label: 'Employee',        value: 'employee' },
    { label: 'Department',      value: 'department' },
    { label: 'Designation',     value: 'designation' },
    { label: 'Employment Type', value: 'employment_type' },
    { label: 'Gender',          value: 'gender' },
];
const genderOptions = [
    { id: 'male',   name: 'Male' },
    { id: 'female', name: 'Female' },
];
function getAssignmentOptions(type) {
    switch (type) {
        case 'employee':        return employeeOptions.value;
        case 'department':      return departmentOptions.value;
        case 'designation':     return designationOptions.value;
        case 'employment_type': return employmentTypeOptions.value;
        case 'gender':          return genderOptions;
        default:                return [];
    }
}

function reset() {
    Object.assign(form, { company_id: null, name: '', effective_from: null, effective_to: null, status: 'active', conflict_strategy: 'highest_priority' });
    details.value     = [];
    assignments.value = [];
}

function addDetail() {
    details.value.push({ leave_type_id: null, annual_allocation: 0 });
}
function removeDetail(idx) {
    details.value.splice(idx, 1);
}
function addAssignment() {
    assignments.value.push({ assignment_type: null, assignment_id: null, effective_from: null, priority: 0 });
}
function removeAssignment(idx) {
    assignments.value.splice(idx, 1);
}

onMounted(async () => {
    try {
        const numId = i => ({ ...i, id: Number(i.id) });
        const [c, lt, d, desig, emps, etypes] = await Promise.all([
            CompanyService.getList({ per_page: 500 }),
            LeaveTypeService.getList({ per_page: 500, status: 'active' }),
            DepartmentService.getList({ per_page: 500 }),
            DesignationService.getList({ per_page: 500 }),
            EmployeeService.getList({ per_page: 500 }),
            EmploymentTypeService.getList({ per_page: 500 }),
        ]);
        companyOptions.value        = (c?.items      ?? []).map(numId);
        leaveTypeOptions.value      = (lt?.items     ?? []).map(numId);
        departmentOptions.value     = (d?.items      ?? []).map(numId);
        designationOptions.value    = (desig?.items  ?? []).map(numId);
        employmentTypeOptions.value = (etypes?.items ?? []).map(numId);
        employeeOptions.value       = (emps?.items   ?? []).map(e => ({
            id: Number(e.id), name: `${e.first_name} ${e.last_name}`,
        }));
    } catch {
        companyOptions.value        = [];
        leaveTypeOptions.value      = [];
        departmentOptions.value     = [];
        designationOptions.value    = [];
        employeeOptions.value       = [];
        employmentTypeOptions.value = [];
    }
});

async function loadItem(id) {
    const [policy, dets, assigns] = await Promise.all([
        LeavePolicyService.getById(id),
        LeavePolicyService.getDetails(id),
        LeavePolicyService.getAssignments(id),
    ]);
    Object.assign(form, {
        company_id:        policy.company_id ? Number(policy.company_id) : null,
        name:              policy.name              ?? '',
        effective_from:    policy.effective_from     ?? null,
        effective_to:      policy.effective_to       ?? null,
        status:            policy.status             ?? 'active',
        conflict_strategy: policy.conflict_strategy  ?? 'highest_priority',
    });
    details.value = (dets?.items ?? []).map(d => ({
        leave_type_id:     Number(d.leave_type_id),
        annual_allocation: Number(d.annual_allocation ?? 0),
    }));
    assignments.value = (assigns?.items ?? []).map(a => ({
        id:              Number(a.id),
        assignment_type: a.assignment_type,
        assignment_id:   a.assignment_type === 'gender' ? a.assignment_id : Number(a.assignment_id),
        effective_from:  a.effective_from ?? null,
        priority:        Number(a.priority ?? 0),
    }));
}

watch(() => props.modelValue, async (v) => {
    if (!v) return;
    reset();
    errors.company_id = '';
    errors.name = '';
    if (isEdit.value) {
        try { await loadItem(props.editId); }
        catch (e) {
            toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
            emit('update:modelValue', false);
        }
    }
}, { immediate: true });

function validate() {
    errors.company_id = form.company_id  ? '' : 'Company is required.';
    errors.name       = form.name.trim() ? '' : 'Policy name is required.';
    return !Object.values(errors).some(Boolean);
}

async function save() {
    if (!validate()) return;
    if (details.value.some(d => !d.leave_type_id))
        return toast.add({ severity: 'warn', detail: 'Select a leave type for each detail row.', life: 3000 });
    if (assignments.value.some(a => !a.assignment_type || !a.assignment_id || !a.effective_from))
        return toast.add({ severity: 'warn', detail: 'Complete all assignment rows (type, assign to, and effective from are required).', life: 3000 });

    saving.value = true;
    try {
        const body = {
            company_id:        form.company_id,
            name:              form.name.trim(),
            effective_from:    form.effective_from || null,
            effective_to:      form.effective_to   || null,
            status:            form.status,
            conflict_strategy: form.conflict_strategy,
        };
        let policyId = props.editId;
        if (isEdit.value) {
            await LeavePolicyService.update(policyId, body);
        } else {
            const created = await LeavePolicyService.create(body);
            policyId = created?.id ?? created;
        }

        // Replace all detail lines (delete old + insert new atomically)
        await LeavePolicyService.replaceDetails(policyId, details.value.map(d => ({
            leave_type_id:     Number(d.leave_type_id),
            annual_allocation: Number(d.annual_allocation),
        })));

        // Delete existing assignments, then recreate all.
        if (isEdit.value) {
            const existing = await LeavePolicyService.getAssignments(policyId);
            for (const old of (existing?.items ?? [])) {
                await LeavePolicyService.deleteAssignment(policyId, old.id);
            }
        }
        for (const a of assignments.value) {
            await LeavePolicyService.createAssignment(policyId, {
                assignment_type: a.assignment_type,
                assignment_id:   a.assignment_type === 'gender' ? a.assignment_id : Number(a.assignment_id),
                effective_from:  a.effective_from || null,
                priority:        Number(a.priority ?? 0),
            });
        }

        // Re-allocate leave balances for all assignments on this policy.
        await LeavePolicyService.allocate(policyId);

        toast.add({ severity: 'success', detail: isEdit.value ? 'Policy updated.' : 'Policy created.', life: 3000 });
        emit('saved');
        emit('update:modelValue', false);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}

async function deleteAssignment(idx) {
    const a = assignments.value[idx];
    if (a.id) {
        try {
            await LeavePolicyService.deleteAssignment(props.editId, a.id);
            toast.add({ severity: 'success', detail: 'Assignment removed.', life: 3000 });
        } catch (e) {
            toast.add({ severity: 'error', detail: e.message, life: 5000 });
            return;
        }
    }
    assignments.value.splice(idx, 1);
}

defineExpose({ save });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Company <span class="text-red-500">*</span></label>
                <Select v-model="form.company_id" :options="companyOptions"
                    optionLabel="name" optionValue="id" placeholder="Select company" fluid
                    :invalid="!!errors.company_id" />
                <small v-if="errors.company_id" class="text-red-500">{{ errors.company_id }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Policy Name <span class="text-red-500">*</span></label>
                <InputText v-model="form.name" fluid placeholder="e.g. Standard Leave Policy" :invalid="!!errors.name" />
                <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Status</label>
                <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-medium">Conflict Strategy</label>
                <Select v-model="form.conflict_strategy" :options="conflictStrategyOptions"
                    optionLabel="label" optionValue="value" fluid />
                <small class="text-surface-500">When multiple policies cover the same employee + leave type, how should the allocation be resolved?</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Effective From</label>
                <HrDatePicker v-model="form.effective_from" showIcon fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-medium">Effective To</label>
                <HrDatePicker v-model="form.effective_to" showIcon fluid />
            </div>
        </div>

        <!-- Leave Entitlements -->
        <div class="border-t pt-4">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold">Leave Entitlements</span>
                <Button label="Add Leave Type" icon="pi pi-plus" size="small" outlined @click="addDetail" />
            </div>
            <div v-if="details.length === 0" class="text-surface-500 text-sm text-center py-4">
                No leave types added yet.
            </div>
            <div v-for="(det, idx) in details" :key="idx"
                class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border rounded p-3 mb-2">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Leave Type</label>
                    <Select v-model="det.leave_type_id" :options="leaveTypeOptions"
                        optionLabel="name" optionValue="id" placeholder="Select" fluid />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Annual Allocation (days)</label>
                    <InputNumber v-model="det.annual_allocation" :min="0" :maxFractionDigits="1" fluid />
                </div>
                <div class="flex items-end">
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small" @click="removeDetail(idx)" />
                </div>
            </div>
        </div>

        <!-- Policy Assignments -->
        <div class="border-t pt-4">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold">Assignments</span>
                <Button label="Add Assignment" icon="pi pi-plus" size="small" outlined @click="addAssignment" />
            </div>
            <div v-if="assignments.length === 0" class="text-surface-500 text-sm text-center py-4">
                No assignments yet. Assign this policy to employees, departments, or designations.
            </div>
            <div v-for="(a, idx) in assignments" :key="idx"
                class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end border rounded p-3 mb-2">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Type</label>
                    <Select v-model="a.assignment_type" :options="assignmentTypeOptions"
                        optionLabel="label" optionValue="value" placeholder="Select type" fluid
                        @change="a.assignment_id = null" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Assign To</label>
                    <Select v-model="a.assignment_id" :options="getAssignmentOptions(a.assignment_type)"
                        optionLabel="name" optionValue="id"
                        placeholder="Select" fluid :disabled="!a.assignment_type" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Effective From</label>
                    <HrDatePicker v-model="a.effective_from" showIcon fluid />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">Priority</label>
                    <InputNumber v-model="a.priority" :min="0" :max="100" fluid
                        placeholder="0 = auto" />
                </div>
                <div class="flex items-end">
                    <Button icon="pi pi-trash" text rounded severity="danger" size="small" @click="deleteAssignment(idx)" />
                </div>
            </div>
        </div>
    </div>
</template>
