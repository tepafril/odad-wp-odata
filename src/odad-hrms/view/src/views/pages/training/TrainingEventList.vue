<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Training Events</h2>
            <Button label="New Event" icon="pi pi-plus"
                @click="$router.push({ name: 'training-event-create' })" />
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
            <Dropdown v-model="filters.training_type" :options="typeOptions" option-label="label" option-value="value"
                placeholder="Type" show-clear class="w-44" @change="loadData" />
            <Dropdown v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
                placeholder="Status" show-clear class="w-44" @change="loadData" />
        </div>

        <DataTable :value="items" :loading="loading" lazy :total-records="total" :rows="perPage"
            paginator @page="onPage" row-hover striped-rows>
            <Column field="title" header="Title" />
            <Column field="trainer" header="Trainer" />
            <Column field="training_type" header="Type">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.training_type" severity="info" />
                </template>
            </Column>
            <Column field="start_date" header="Start Date" />
            <Column field="end_date" header="End Date" />
            <Column field="max_participants" header="Max Participants" />
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <Tag class="capitalize" :value="data.status" :severity="statusSeverity(data.status)" />
                </template>
            </Column>
            <Column header="Actions">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-eye" size="small" severity="info" text
                            @click="$router.push({ name: 'training-event-detail', params: { id: data.id } })" />
                        <Button icon="pi pi-pencil" size="small" severity="secondary" text
                            @click="$router.push({ name: 'training-event-edit', params: { id: data.id } })" />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { TrainingEventService } from '@/service/TrainingEventService';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';

const items   = ref([]);
const loading = ref(false);
const total   = ref(0);
const page    = ref(1);
const perPage = ref(20);

const filters = ref({ training_type: null, status: null });

const typeOptions = [
    { label: 'Internal',  value: 'internal'  },
    { label: 'External',  value: 'external'  },
    { label: 'Online',    value: 'online'    },
    { label: 'Workshop',  value: 'workshop'  },
];

const statusOptions = [
    { label: 'Planned',     value: 'planned'     },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Completed',   value: 'completed'   },
    { label: 'Cancelled',   value: 'cancelled'   },
];

function statusSeverity(status) {
    const map = { planned: 'secondary', in_progress: 'info', completed: 'success', cancelled: 'danger' };
    return map[status] ?? 'secondary';
}

async function loadData() {
    loading.value = true;
    try {
        const res = await TrainingEventService.getList({
            page:          page.value,
            per_page:      perPage.value,
            training_type: filters.value.training_type || undefined,
            status:        filters.value.status        || undefined,
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
