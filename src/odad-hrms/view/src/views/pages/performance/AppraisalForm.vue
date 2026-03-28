<template>
    <div class="card">
        <h2 class="text-xl font-semibold mb-4">
            {{ isNew ? 'New Appraisal' : `Appraisal Review — ${appraisal?.employee_id ?? ''}` }}
        </h2>

        <!-- New Appraisal Form -->
        <template v-if="isNew">
            <form @submit.prevent="createAppraisal" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Appraisal Cycle ID *</label>
                    <InputText v-model="newForm.appraisal_cycle_id" type="number" required />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Employee ID *</label>
                    <InputText v-model="newForm.employee_id" type="number" required />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-medium">Appraiser ID *</label>
                    <InputText v-model="newForm.appraiser_id" type="number" required />
                </div>
                <div class="md:col-span-2 flex gap-3 justify-end">
                    <Button type="button" label="Cancel" severity="secondary" @click="$router.back()" />
                    <Button type="submit" label="Create" :loading="saving" />
                </div>
            </form>
        </template>

        <!-- Review Tabs (existing appraisal) -->
        <template v-else-if="appraisal">
            <div class="mb-4 flex gap-2">
                <Tag class="capitalize" :value="appraisal.status" :severity="statusSeverity(appraisal.status)" />
                <span class="text-surface-500 text-sm">Employee: {{ appraisal.employee_id }} | Appraiser: {{ appraisal.appraiser_id }}</span>
            </div>

            <TabView v-model:active-index="activeTab">
                <!-- Self Review Tab -->
                <TabPanel header="Self Review">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="flex flex-col gap-1">
                            <label class="font-medium">Self Rating (0–5)</label>
                            <InputText v-model="selfForm.self_rating" type="number" step="0.1" min="0" max="5" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label class="font-medium">Self Summary</label>
                            <Textarea v-model="selfForm.self_summary" rows="6" />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <Button label="Submit Self Review" icon="pi pi-check" :loading="savingSelf"
                                :disabled="appraisal.status === 'completed'"
                                @click="submitSelfReview" />
                        </div>
                    </div>
                </TabPanel>

                <!-- Manager Review Tab -->
                <TabPanel header="Manager Review">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="flex flex-col gap-1">
                            <label class="font-medium">Manager Rating (0–5)</label>
                            <InputText v-model="managerForm.appraiser_rating" type="number" step="0.1" min="0" max="5" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label class="font-medium">Manager Summary</label>
                            <Textarea v-model="managerForm.appraiser_summary" rows="6" />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <Button label="Submit Manager Review" icon="pi pi-check" :loading="savingManager"
                                :disabled="appraisal.status === 'completed'"
                                @click="submitManagerReview" />
                        </div>
                    </div>
                </TabPanel>

                <!-- Finalize Tab -->
                <TabPanel header="Finalize">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="mb-4 md:col-span-2 grid grid-cols-2 gap-3 text-sm bg-surface-50 p-3 rounded">
                            <div><span class="font-medium">Self Rating:</span> {{ appraisal.self_rating ?? 'N/A' }}</div>
                            <div><span class="font-medium">Manager Rating:</span> {{ appraisal.appraiser_rating ?? 'N/A' }}</div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="font-medium">Final Rating (0–5)</label>
                            <InputText v-model="finalForm.final_rating" type="number" step="0.1" min="0" max="5" />
                        </div>
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label class="font-medium">Final Comments</label>
                            <Textarea v-model="finalForm.final_comments" rows="5" />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <Button label="Finalize Appraisal" icon="pi pi-flag" severity="success"
                                :loading="finalizing"
                                :disabled="appraisal.status === 'completed'"
                                @click="finalizeAppraisal" />
                        </div>
                    </div>
                </TabPanel>
            </TabView>
        </template>

        <div v-else-if="loading" class="py-10 text-center">
            <i class="pi pi-spin pi-spinner text-3xl" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AppraisalService } from '@/service/AppraisalService';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

const route  = useRoute();
const router = useRouter();

const isNew   = computed(() => !route.params.id);
const loading = ref(false);
const saving  = ref(false);
const savingSelf    = ref(false);
const savingManager = ref(false);
const finalizing    = ref(false);
const appraisal = ref(null);

const tabMap = { self: 0, manager: 1, finalize: 2 };
const activeTab = ref(tabMap[route.query.tab] ?? 0);

const newForm = ref({ appraisal_cycle_id: '', employee_id: '', appraiser_id: '' });
const selfForm    = ref({ self_rating: '', self_summary: '' });
const managerForm = ref({ appraiser_rating: '', appraiser_summary: '' });
const finalForm   = ref({ final_rating: '', final_comments: '' });

function statusSeverity(status) {
    const map = { draft: 'secondary', self_review: 'info', manager_review: 'warn', calibration: 'warn', completed: 'success' };
    return map[status] ?? 'secondary';
}

async function loadAppraisal() {
    if (isNew.value) return;
    loading.value = true;
    try {
        const data = await AppraisalService.getById(route.params.id);
        appraisal.value = data;
        selfForm.value.self_rating    = data.self_rating ?? '';
        selfForm.value.self_summary   = data.self_summary ?? '';
        managerForm.value.appraiser_rating   = data.appraiser_rating ?? '';
        managerForm.value.appraiser_summary  = data.appraiser_summary ?? '';
        finalForm.value.final_rating   = data.final_rating ?? '';
        finalForm.value.final_comments = data.final_comments ?? '';
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function createAppraisal() {
    saving.value = true;
    try {
        const result = await AppraisalService.create(newForm.value);
        router.push({ name: 'appraisal-review', params: { id: result.id } });
    } catch (e) {
        alert(e.message);
    } finally {
        saving.value = false;
    }
}

async function submitSelfReview() {
    savingSelf.value = true;
    try {
        const data = await AppraisalService.submitSelfReview(route.params.id, selfForm.value);
        appraisal.value = data;
    } catch (e) {
        alert(e.message);
    } finally {
        savingSelf.value = false;
    }
}

async function submitManagerReview() {
    savingManager.value = true;
    try {
        const data = await AppraisalService.submitManagerReview(route.params.id, managerForm.value);
        appraisal.value = data;
    } catch (e) {
        alert(e.message);
    } finally {
        savingManager.value = false;
    }
}

async function finalizeAppraisal() {
    if (!confirm('Finalize this appraisal? This action cannot be undone.')) return;
    finalizing.value = true;
    try {
        const data = await AppraisalService.finalize(route.params.id, finalForm.value);
        appraisal.value = data;
    } catch (e) {
        alert(e.message);
    } finally {
        finalizing.value = false;
    }
}

onMounted(loadAppraisal);
</script>
