<script setup>
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { request, getBaseUrl } from '@/api/client';

const toast = useToast();
const importing = ref(false);
const previewRows = ref([]);
const previewHeaders = ref([]);
const selectedFile = ref(null);
const fileInputRef = ref(null);
const result = ref(null);

function downloadTemplate() {
    const a = document.createElement('a');
    a.href = `${getBaseUrl()}/employees/import-template`;
    a.download = 'employee-import-template.csv';
    a.click();
}

function onFileChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    selectedFile.value = file;
    previewCsv(file);
}

function previewCsv(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.trim().split('\n').filter(l => l.trim());
        if (!lines.length) { previewHeaders.value = []; previewRows.value = []; return; }
        const parseLine = (line) => {
            const result = [];
            let current = '';
            let inQuotes = false;
            for (let i = 0; i < line.length; i++) {
                if (line[i] === '"' && !inQuotes) { inQuotes = true; continue; }
                if (line[i] === '"' && inQuotes && line[i+1] === '"') { current += '"'; i++; continue; }
                if (line[i] === '"' && inQuotes) { inQuotes = false; continue; }
                if (line[i] === ',' && !inQuotes) { result.push(current); current = ''; continue; }
                current += line[i];
            }
            result.push(current);
            return result;
        };
        previewHeaders.value = parseLine(lines[0]);
        previewRows.value = lines.slice(1, 6).map(l => parseLine(l));
    };
    reader.readAsText(file);
}

async function importFile() {
    if (!selectedFile.value) {
        toast.add({ severity: 'warn', summary: 'No File', detail: 'Please select a CSV file first.', life: 3000 });
        return;
    }
    importing.value = true;
    result.value = null;
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        const res = await request('employees/import', { method: 'POST', body: formData });
        if (res.error) throw new Error(res.error.message);
        result.value = res.data;
        toast.add({
            severity: result.value.skipped > 0 ? 'warn' : 'success',
            summary: 'Import Complete',
            detail: `Imported: ${result.value.imported}, Skipped: ${result.value.skipped}`,
            life: 5000,
        });
        // Reset
        selectedFile.value = null;
        previewHeaders.value = [];
        previewRows.value = [];
        if (fileInputRef.value) fileInputRef.value.value = '';
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Import Failed', detail: e.message, life: 5000 });
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="card">
            <h2 class="text-xl font-semibold mb-4">Import Employees from CSV</h2>

            <div class="flex flex-wrap gap-3 mb-6">
                <Button label="Download Template" icon="pi pi-download" severity="secondary" @click="downloadTemplate" />
            </div>

            <div class="flex flex-col gap-3 mb-4">
                <label class="text-sm font-medium">Select CSV File</label>
                <input
                    ref="fileInputRef"
                    type="file"
                    accept=".csv"
                    class="block text-sm text-surface-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-600 cursor-pointer"
                    @change="onFileChange"
                />
                <small class="text-surface-400">Required columns: first_name, last_name, work_email. Optional: department_id, designation_id, employment_type, date_of_joining.</small>
            </div>

            <Button
                label="Import"
                icon="pi pi-upload"
                :loading="importing"
                :disabled="!selectedFile"
                @click="importFile"
            />
        </div>

        <!-- Preview -->
        <div v-if="previewHeaders.length" class="card">
            <h3 class="font-semibold mb-3">Preview (first 5 rows)</h3>
            <div class="overflow-x-auto">
                <table class="text-sm w-full border-collapse">
                    <thead>
                        <tr>
                            <th v-for="h in previewHeaders" :key="h" class="text-left px-3 py-2 bg-surface-100 border border-surface-200 font-medium">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, ri) in previewRows" :key="ri" class="border-b border-surface-100">
                            <td v-for="(cell, ci) in row" :key="ci" class="px-3 py-2 border border-surface-100">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Result -->
        <div v-if="result" class="card">
            <h3 class="font-semibold mb-3">Import Result</h3>
            <div class="flex gap-6 mb-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ result.imported }}</div>
                    <div class="text-sm text-surface-500">Imported</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-500">{{ result.skipped }}</div>
                    <div class="text-sm text-surface-500">Skipped</div>
                </div>
            </div>
            <div v-if="result.errors?.length" class="mt-3">
                <h4 class="font-medium text-red-600 mb-2">Errors:</h4>
                <ul class="text-sm text-red-500 list-disc list-inside">
                    <li v-for="(err, i) in result.errors" :key="i">{{ err }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>
