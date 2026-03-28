<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Goal' : 'New Goal' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Title *</label>
                <InputText v-model="form.title" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Employee ID *</label>
                <InputText v-model="form.employee_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Goal Type</label>
                <Dropdown v-model="form.goal_type" :options="goalTypeOptions" option-label="label" option-value="value" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Parent Goal ID</label>
                <InputText v-model="form.parent_goal_id" type="number" placeholder="Optional" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">KPI Metric</label>
                <InputText v-model="form.kpi_metric" placeholder="e.g. Revenue, Tickets Closed" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Target Value</label>
                <InputText v-model="form.target_value" type="number" step="0.01" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Current Value</label>
                <InputText v-model="form.current_value" type="number" step="0.01" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Unit</label>
                <InputText v-model="form.unit" placeholder="e.g. %, USD, count" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Weight (%)</label>
                <InputText v-model="form.weight" type="number" step="0.01" min="0" max="100" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Start Date *</label>
                <HrDatePicker v-model="form.start_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">End Date *</label>
                <HrDatePicker v-model="form.end_date" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Status</label>
                <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
            </div>

            <!-- Progress update section (edit mode only) -->
            <div v-if="isEdit" class="md:col-span-2">
                <div class="border rounded p-4 bg-surface-50">
                    <h3 class="font-medium mb-3">Update Progress</h3>
                    <div class="flex items-center gap-4">
                        <label class="text-sm">Current Value:</label>
                        <InputText v-model="progressValue" type="number" step="0.01" class="w-32" />
                        <Button type="button" label="Update Progress" icon="pi pi-refresh"
                            size="small" severity="info" :loading="updatingProgress"
                            @click="updateProgress" />
                    </div>
                    <div v-if="form.target_value" class="mt-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-surface-200 rounded-full h-3">
                                <div class="bg-primary h-3 rounded-full transition-all"
                                    :style="{ width: computedPercent + '%' }" />
                            </div>
                            <span class="text-sm font-medium">{{ computedPercent }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Description</label>
                <Textarea v-model="form.description" rows="4" />
            </div>

            <div class="md:col-span-2 flex gap-3 justify-end">
                <Button type="button" label="Cancel" severity="secondary" @click="$router.back()" />
                <Button type="submit" :label="isEdit ? 'Update' : 'Create'" :loading="saving" />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { GoalService } from '@/service/GoalService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import HrDatePicker from '@/components/HrDatePicker.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const route  = useRoute();
const router = useRouter();

const isEdit          = computed(() => !!route.params.id);
const saving          = ref(false);
const updatingProgress = ref(false);
const progressValue   = ref(0);

const form = ref({
    employee_id:    '',
    title:          '',
    description:    '',
    goal_type:      'individual',
    parent_goal_id: null,
    kpi_metric:     '',
    target_value:   null,
    current_value:  null,
    unit:           '',
    weight:         null,
    start_date:     null,
    end_date:       null,
    status:         'not_started',
    completion_percent: 0,
});

const computedPercent = computed(() => {
    const target  = parseFloat(form.value.target_value) || 0;
    const current = parseFloat(progressValue.value) || 0;
    if (target <= 0) return 0;
    return Math.min(100, Math.max(0, Math.round((current / target) * 100)));
});

const goalTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Department', value: 'department' },
    { label: 'Company',    value: 'company'    },
];

const statusOptions = [
    { label: 'Not Started', value: 'not_started' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed',   value: 'completed'   },
    { label: 'Cancelled',   value: 'cancelled'   },
];

function formatDate(val) {
    if (!val) return null;
    if (val instanceof Date) return val.toISOString().slice(0, 10);
    return val;
}

async function loadGoal() {
    if (!isEdit.value) return;
    try {
        const data = await GoalService.getById(route.params.id);
        Object.assign(form.value, data);
        progressValue.value = data.current_value ?? 0;
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    const payload = {
        ...form.value,
        start_date: formatDate(form.value.start_date),
        end_date:   formatDate(form.value.end_date),
    };
    try {
        if (isEdit.value) {
            await GoalService.update(route.params.id, payload);
        } else {
            await GoalService.create(payload);
        }
        router.push({ name: 'goals' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

async function updateProgress() {
    updatingProgress.value = true;
    try {
        const updated = await GoalService.updateProgress(route.params.id, { current_value: parseFloat(progressValue.value) });
        Object.assign(form.value, updated);
        progressValue.value = updated.current_value;
    } catch (e) {
        alert(e.message);
    } finally {
        updatingProgress.value = false;
    }
}

onMounted(loadGoal);
</script>
