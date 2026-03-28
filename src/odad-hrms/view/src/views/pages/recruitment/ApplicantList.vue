<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Job Applicants</h2>
            <Button label="New Applicant" icon="pi pi-plus" @click="$router.push({ name: 'applicant-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <InputText v-model="filters.search" placeholder="Search name or email..." class="w-64" @input="onSearchInput" />
            <Dropdown v-model="filters.stage" :options="stageOptions" option-label="label" option-value="value"
                placeholder="Filter by stage" show-clear class="w-48" @change="loadData" />
        </div>

        <DataTable
            :value="items"
            :loading="loading"
            lazy
            :total-records="total"
            :rows="perPage"
            paginator
            @page="onPage"
            row-hover
            striped-rows
        >
            <Column field="first_name" header="First Name" />
            <Column field="last_name" header="Last Name" />
            <Column field="email" header="Email" />
            <Column field="source" header="Source" />
            <Column field="stage" header="Stage">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.stage" :severity="stageSeverity(data.stage)" />
                </template>
            </Column>
            <Column field="rating" header="Rating" />
            <Column header="Actions">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button icon="pi pi-pencil" size="small" severity="secondary" text
                            @click="$router.push({ name: 'applicant-edit', params: { id: data.id } })" />
                        <Button icon="pi pi-eye" size="small" severity="info" text
                            @click="$router.push({ name: 'recruit-applicant-detail', params: { id: data.id } })" />
                        <Button
                            v-if="!['hired','rejected','withdrawn'].includes(data.stage)"
                            icon="pi pi-arrow-right" size="small" severity="success" text
                            title="Advance Stage"
                            @click="advanceStage(data)" />
                        <Button
                            v-if="!['hired','rejected','withdrawn'].includes(data.stage)"
                            icon="pi pi-times" size="small" severity="danger" text
                            title="Reject"
                            @click="rejectApplicant(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ApplicantService } from '@/service/ApplicantService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';

const items   = ref([]);
const loading = ref(false);
const total   = ref(0);
const page    = ref(1);
const perPage = ref(20);

const filters = ref({
    search: '',
    stage:  null,
});

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

function stageSeverity(stage) {
    const map = {
        applied:    'info',
        screening:  'warn',
        interview:  'warn',
        assessment: 'warn',
        offer:      'success',
        hired:      'success',
        rejected:   'danger',
        withdrawn:  'secondary',
    };
    return map[stage] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await ApplicantService.getList({
            page:     page.value,
            per_page: perPage.value,
            search:   filters.value.search || undefined,
            stage:    filters.value.stage  || undefined,
        });
        items.value = res.data ?? res;
        total.value = res.total ?? items.value.length;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadData, 400);
}

function onPage(event) {
    page.value = event.page + 1;
    loadData();
}

async function advanceStage(applicant) {
    try {
        await ApplicantService.advance(applicant.id);
        loadData();
    } catch (e) {
        alert(e.message);
    }
}

async function rejectApplicant(applicant) {
    if (!confirm(`Reject ${applicant.first_name} ${applicant.last_name}?`)) return;
    try {
        await ApplicantService.reject(applicant.id);
        loadData();
    } catch (e) {
        alert(e.message);
    }
}

onMounted(loadData);
</script>
