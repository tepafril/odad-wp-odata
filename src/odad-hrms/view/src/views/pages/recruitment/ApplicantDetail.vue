<template>
    <div>
        <div v-if="loading" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-4xl" />
        </div>

        <template v-else-if="applicant">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold">{{ applicant.first_name }} {{ applicant.last_name }}</h2>
                        <p class="text-surface-500">{{ applicant.email }} &bull; {{ applicant.phone }}</p>
                        <p class="text-surface-500">{{ applicant.current_job_title }} at {{ applicant.current_company }}</p>
                    </div>
                    <Tag :value="applicant.stage" :severity="stageSeverity(applicant.stage)" class="text-base capitalize" />
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                    <div><span class="font-medium">Source:</span> {{ applicant.source }}</div>
                    <div><span class="font-medium">Experience:</span> {{ applicant.experience_years }} yrs</div>
                    <div><span class="font-medium">Expected Salary:</span> {{ applicant.expected_salary }} {{ applicant.currency }}</div>
                    <div><span class="font-medium">Notice Period:</span> {{ applicant.notice_period_days }} days</div>
                    <div><span class="font-medium">Rating:</span> {{ applicant.rating ?? 'N/A' }}</div>
                </div>

                <p v-if="applicant.notes" class="text-surface-600 italic">{{ applicant.notes }}</p>

                <!-- Action Buttons -->
                <div class="flex gap-2 mt-4 flex-wrap">
                    <Button label="Edit" icon="pi pi-pencil" severity="secondary"
                        @click="$router.push({ name: 'applicant-edit', params: { id: applicant.id } })" />
                    <Button
                        v-if="!['hired','rejected','withdrawn'].includes(applicant.stage)"
                        label="Advance Stage" icon="pi pi-arrow-right" severity="success"
                        :loading="actionLoading === 'advance'"
                        @click="doAdvance" />
                    <Button
                        v-if="!['hired','rejected','withdrawn'].includes(applicant.stage)"
                        label="Reject" icon="pi pi-times" severity="danger"
                        :loading="actionLoading === 'reject'"
                        @click="doReject" />
                    <Button
                        v-if="applicant.stage !== 'hired'"
                        label="Hire (Convert to Employee)" icon="pi pi-user-plus" severity="help"
                        :loading="actionLoading === 'hire'"
                        @click="doHire" />
                </div>
            </div>

            <!-- Offer Letter Status -->
            <div class="card mb-4" v-if="offerLetters.length">
                <h3 class="text-lg font-semibold mb-3">Offer Letters</h3>
                <DataTable :value="offerLetters" striped-rows>
                    <Column field="offer_date" header="Offer Date" />
                    <Column field="offered_salary" header="Salary" />
                    <Column field="currency" header="Currency" />
                    <Column field="joining_date" header="Joining Date" />
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Tag class="capitalize" :value="data.status" :severity="offerSeverity(data.status)" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Interview History -->
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold">Interviews</h3>
                    <Button label="Schedule Interview" icon="pi pi-calendar-plus" size="small"
                        @click="$router.push({ name: 'recruit-interview-create', query: { applicant_id: applicant.id } })" />
                </div>
                <DataTable :value="interviews" striped-rows :loading="interviewsLoading">
                    <Column field="interview_round" header="Round" />
                    <Column field="scheduled_at" header="Scheduled At" />
                    <Column field="duration_minutes" header="Duration (min)" />
                    <Column field="location" header="Location" />
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Tag class="capitalize" :value="data.status" :severity="interviewSeverity(data.status)" />
                        </template>
                    </Column>
                    <Column field="overall_rating" header="Rating" />
                    <Column field="decision" header="Decision" />
                </DataTable>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ApplicantService } from '@/service/ApplicantService';
import { InterviewService } from '@/service/InterviewService';
import { OfferLetterService } from '@/service/OfferLetterService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const route = useRoute();

const applicant       = ref(null);
const loading         = ref(false);
const interviews      = ref([]);
const interviewsLoading = ref(false);
const offerLetters    = ref([]);
const actionLoading   = ref(null);

function stageSeverity(stage) {
    const map = { applied: 'info', screening: 'warn', interview: 'warn', assessment: 'warn', offer: 'success', hired: 'success', rejected: 'danger', withdrawn: 'secondary' };
    return map[stage] ?? 'secondary';
}
function offerSeverity(status) {
    const map = { draft: 'secondary', sent: 'info', accepted: 'success', declined: 'danger', expired: 'warn', cancelled: 'secondary' };
    return map[status] ?? 'secondary';
}
function interviewSeverity(status) {
    const map = { scheduled: 'info', completed: 'success', cancelled: 'secondary', no_show: 'danger' };
    return map[status] ?? 'secondary';
}

async function load() {
    loading.value = true;
    try {
        applicant.value = await ApplicantService.getById(route.params.id);
        await Promise.all([loadInterviews(), loadOfferLetters()]);
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function loadInterviews() {
    interviewsLoading.value = true;
    try {
        const res = await InterviewService.getList({ applicant_id: route.params.id, per_page: 100 });
        interviews.value = res.data ?? res;
    } catch (e) {
        console.error(e);
    } finally {
        interviewsLoading.value = false;
    }
}

async function loadOfferLetters() {
    try {
        const res = await OfferLetterService.getList({ applicant_id: route.params.id, per_page: 100 });
        offerLetters.value = res.data ?? res;
    } catch (e) {
        console.error(e);
    }
}

async function doAdvance() {
    actionLoading.value = 'advance';
    try {
        applicant.value = await ApplicantService.advance(route.params.id);
    } catch (e) {
        alert(e.message);
    } finally {
        actionLoading.value = null;
    }
}

async function doReject() {
    if (!confirm('Reject this applicant?')) return;
    actionLoading.value = 'reject';
    try {
        applicant.value = await ApplicantService.reject(route.params.id);
    } catch (e) {
        alert(e.message);
    } finally {
        actionLoading.value = null;
    }
}

async function doHire() {
    if (!confirm('Convert this applicant to an employee? This action creates an employee record.')) return;
    actionLoading.value = 'hire';
    try {
        await ApplicantService.hire(route.params.id);
        applicant.value = await ApplicantService.getById(route.params.id);
        alert('Employee record created successfully.');
    } catch (e) {
        alert(e.message);
    } finally {
        actionLoading.value = null;
    }
}

onMounted(load);
</script>
