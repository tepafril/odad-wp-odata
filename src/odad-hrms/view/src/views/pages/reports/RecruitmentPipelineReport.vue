<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const loading = ref(false);
const byStage = ref([]);
const byJob = ref([]);

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const res = await request('reports/recruitment-pipeline');
        if (res.error) throw new Error(res.error.message);
        byStage.value = res.data?.by_stage || [];
        byJob.value = res.data?.by_job || [];
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/reports/recruitment-pipeline?format=csv`;
    a.download = 'recruitment-pipeline.csv';
    a.click();
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="card w-full !max-w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Pipeline by Stage</h3>
                <div class="flex gap-2">
                    <Button label="Refresh" icon="pi pi-refresh" size="small" @click="loadReport" />
                    <Button label="Export CSV" icon="pi pi-download" severity="secondary" size="small" @click="exportCsv" />
                </div>
            </div>
            <DataTable :value="byStage" :loading="loading" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
                <Column field="stage" header="Stage" sortable>
                    <template #body="{ data }"><span class="capitalize">{{ data.stage }}</span></template>
                </Column>
                <Column field="count" header="Count" sortable>
                    <template #body="{ data }"><Tag class="capitalize" :value="String(data.count)" severity="info" /></template>
                </Column>
            </DataTable>
        </div>

        <div class="card">
            <h3 class="font-semibold text-lg mb-4">Pipeline by Job Opening</h3>
            <DataTable :value="byJob" :loading="loading" striped-rows show-gridlines responsive-layout="scroll" class="text-sm">
                <Column field="job_opening_post_id" header="Job Opening ID" sortable />
                <Column field="total_applicants" header="Total Applicants" sortable />
                <Column field="hired" header="Hired" sortable>
                    <template #body="{ data }"><Tag class="capitalize" :value="String(data.hired)" severity="success" /></template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
