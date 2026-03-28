<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">{{ isEdit ? 'Edit Applicant' : 'New Applicant' }}</h2>

        <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label class="font-medium">First Name *</label>
                <InputText v-model="form.first_name" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Last Name *</label>
                <InputText v-model="form.last_name" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Email *</label>
                <InputText v-model="form.email" type="email" required />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Phone</label>
                <InputText v-model="form.phone" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Source</label>
                <Dropdown v-model="form.source" :options="sourceOptions" option-label="label" option-value="value" placeholder="Select source" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Current Company</label>
                <InputText v-model="form.current_company" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Current Job Title</label>
                <InputText v-model="form.current_job_title" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Experience (Years)</label>
                <InputText v-model="form.experience_years" type="number" step="0.5" min="0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Expected Salary</label>
                <InputText v-model="form.expected_salary" type="number" step="0.01" min="0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Currency</label>
                <InputText v-model="form.currency" maxlength="3" placeholder="USD" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Notice Period (Days)</label>
                <InputText v-model="form.notice_period_days" type="number" min="0" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-medium">Stage</label>
                <Dropdown v-model="form.stage" :options="stageOptions" option-label="label" option-value="value" placeholder="Select stage" />
            </div>
            <div class="flex flex-col gap-1 md:col-span-2">
                <label class="font-medium">Notes</label>
                <Textarea v-model="form.notes" rows="4" />
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
import { ApplicantService } from '@/service/ApplicantService';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const route  = useRoute();
const router = useRouter();

const isEdit = computed(() => !!route.params.id);
const saving = ref(false);

const form = ref({
    first_name:       '',
    last_name:        '',
    email:            '',
    phone:            '',
    source:           'website',
    current_company:  '',
    current_job_title: '',
    experience_years: null,
    expected_salary:  null,
    currency:         '',
    notice_period_days: null,
    stage:            'applied',
    notes:            '',
});

const sourceOptions = [
    { label: 'Website',   value: 'website'   },
    { label: 'Referral',  value: 'referral'  },
    { label: 'Job Board', value: 'job_board' },
    { label: 'LinkedIn',  value: 'linkedin'  },
    { label: 'Agency',    value: 'agency'    },
    { label: 'Other',     value: 'other'     },
];

const stageOptions = [
    { label: 'Applied',    value: 'applied'    },
    { label: 'Screening',  value: 'screening'  },
    { label: 'Interview',  value: 'interview'  },
    { label: 'Assessment', value: 'assessment' },
    { label: 'Offer',      value: 'offer'      },
    { label: 'Hired',      value: 'hired'      },
    { label: 'Rejected',   value: 'rejected'   },
    { label: 'Withdrawn',  value: 'withdrawn'  },
];

async function loadApplicant() {
    if (!isEdit.value) return;
    try {
        const data = await ApplicantService.getById(route.params.id);
        Object.assign(form.value, data);
    } catch (e) {
        console.error(e);
    }
}

async function save() {
    saving.value = true;
    try {
        if (isEdit.value) {
            await ApplicantService.update(route.params.id, form.value);
        } else {
            await ApplicantService.create(form.value);
        }
        router.push({ name: 'applicants' });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

onMounted(loadApplicant);
</script>
