<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Interviews</h2>
            <Button label="Schedule Interview" icon="pi pi-plus"
                @click="$router.push({ name: 'recruit-interview-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <InputText v-model="filters.applicant_id" type="number" placeholder="Applicant ID" class="w-40"
                @change="loadData" />
            <Dropdown v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
                placeholder="Filter by status" show-clear class="w-48" @change="loadData" />
        </div>

        <DataTable :value="items" :loading="loading" lazy :total-records="total" :rows="perPage"
            paginator @page="onPage" row-hover striped-rows>
            <Column field="applicant_id" header="Applicant ID" />
            <Column field="interview_round" header="Round" />
            <Column field="scheduled_at" header="Scheduled At">
                <template #body="{ data }">{{ data.scheduled_at_formatted || data.scheduled_at }}</template>
            </Column>
            <Column field="duration_minutes" header="Duration (min)" />
            <Column field="location" header="Location" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column field="overall_rating" header="Rating" />
            <Column field="decision" header="Decision" />
            <Column header="Actions">
                <template #body="{ data }">
                    <Button icon="pi pi-pencil" size="small" severity="secondary" text
                        @click="$router.push({ name: 'recruit-interview-edit', params: { id: data.id } })" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { InterviewService } from '@/service/InterviewService';
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
    applicant_id: '',
    status:       null,
});

const statusOptions = [
    { label: 'Scheduled',  value: 'scheduled'  },
    { label: 'Completed',  value: 'completed'  },
    { label: 'Cancelled',  value: 'cancelled'  },
    { label: 'No Show',    value: 'no_show'    },
];

function statusSeverity(status) {
    const map = { scheduled: 'info', completed: 'success', cancelled: 'secondary', no_show: 'danger' };
    return map[status] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await InterviewService.getList({
            page:         page.value,
            per_page:     perPage.value,
            applicant_id: filters.value.applicant_id || undefined,
            status:       filters.value.status || undefined,
        });
        items.value = res.data ?? res;
        total.value = res.total ?? items.value.length;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function onPage(event) {
    page.value = event.page + 1;
    loadData();
}

onMounted(loadData);
</script>
