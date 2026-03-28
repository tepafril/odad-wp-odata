<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Appraisal Cycle' : 'New Appraisal Cycle' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Name *</label>
                <InputText v-model="form.name" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Company ID *</label>
                <InputText v-model="form.company_id" type="number" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Cycle Type</label>
                <Dropdown v-model="form.cycle_type" :options="cycleTypeOptions" option-label="label" option-value="value" />
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
                <label class="font-medium">Submission Deadline *</label>
                <HrDatePicker v-model="form.submission_deadline" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Status</label>
                <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
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
import { AppraisalCycleService } from '@/service/AppraisalCycleService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import HrDatePicker from '@/components/HrDatePicker.vue';
import Button from 'primevue/button';

const route  = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);

const form = ref({
    name:                '',
    company_id:          '',
    cycle_type:          'annual',
    start_date:          null,
    end_date:            null,
    submission_deadline: null,
    status:              'draft',
});

const cycleTypeOptions = [
    { label: 'Annual',      value: 'annual'      },
    { label: 'Semi Annual', value: 'semi_annual' },
    { label: 'Quarterly',   value: 'quarterly'   },
];

const statusOptions = [
    { label: 'Draft',     value: 'draft'     },
    { label: 'Active',    value: 'active'    },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

function formatDate(val) {
    if (!val) return null;
    if (val instanceof Date) return val.toISOString().slice(0, 10);
    return val;
}

async function loadCycle() {
    if (!isEdit.value) return;
    try {
        const data = await AppraisalCycleService.getById(route.params.id);
        Object.assign(form.value, data);
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    const payload = {
        ...form.value,
        start_date:          formatDate(form.value.start_date),
        end_date:            formatDate(form.value.end_date),
        submission_deadline: formatDate(form.value.submission_deadline),
    };
    try {
        if (isEdit.value) {
            await AppraisalCycleService.update(route.params.id, payload);
        } else {
            await AppraisalCycleService.create(payload);
        }
        router.push({ name: 'appraisal-cycles' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

onMounted(loadCycle);
</script>
