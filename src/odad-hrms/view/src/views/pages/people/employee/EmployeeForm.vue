<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { EmployeeService } from '@/service/EmployeeService';
import { CompanyService } from '@/service/CompanyService';
import { DepartmentService } from '@/service/DepartmentService';
import { DesignationService } from '@/service/DesignationService';
import { BranchService } from '@/service/BranchService';
import EducationTab    from './tabs/EducationTab.vue';
import WorkHistoryTab  from './tabs/WorkHistoryTab.vue';
import BankTab         from './tabs/BankTab.vue';
import DocumentsTab    from './tabs/DocumentsTab.vue';
import MovementsTab    from './tabs/MovementsTab.vue';
import HrDatePicker from '@/components/HrDatePicker.vue';

const route  = useRoute();
const router = useRouter();
const toast  = useToast();
const saving = ref(false);
const loading = ref(false);
const uploadingPhoto = ref(false);
const profilePhotoUrl = ref(null);

const errors = reactive({
    first_name:    '',
    last_name:     '',
    work_email:    '',
    company_id:    '',
    department_id: '',
    designation_id: '',
});

const isEdit    = computed(() => !!route.params.id);
const employeeId = computed(() => isEdit.value ? Number(route.params.id) : null);

const companyOptions     = ref([]);
const departmentOptions  = ref([]);
const designationOptions = ref([]);
const branchOptions      = ref([]);
const employeeOptions    = ref([]); // for reports_to

const genderOptions = [
    { label: 'Male',              value: 'male'              },
    { label: 'Female',            value: 'female'            },
    { label: 'Non-Binary',        value: 'non_binary'        },
    { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];
const maritalOptions = [
    { label: 'Single',   value: 'single'   },
    { label: 'Married',  value: 'married'  },
    { label: 'Divorced', value: 'divorced' },
    { label: 'Widowed',  value: 'widowed'  },
    { label: 'Other',    value: 'other'    },
];
const employmentTypeOptions = [
    { label: 'Full Time',  value: 'full_time'  },
    { label: 'Part Time',  value: 'part_time'  },
    { label: 'Contract',   value: 'contract'   },
    { label: 'Intern',     value: 'intern'     },
    { label: 'Probation',  value: 'probation'  },
];
const statusOptions = [
    { label: 'Active',      value: 'active'      },
    { label: 'Inactive',    value: 'inactive'    },
    { label: 'Suspended',   value: 'suspended'   },
    { label: 'Terminated',  value: 'terminated'  },
    { label: 'Resigned',    value: 'resigned'    },
    { label: 'Retired',     value: 'retired'     },
];

function toDateStr(v) { if (!v) return null; if (typeof v === 'string') return v; const d = new Date(v); return isNaN(d) ? null : d.toISOString().slice(0,10); }

const form = reactive({
    // Core
    employee_number: '',
    company_id:      null,
    branch_id:       null,
    department_id:   null,
    designation_id:  null,
    reports_to:      null,
    // Personal
    first_name:   '',
    last_name:    '',
    middle_name:  '',
    preferred_name: '',
    gender:       null,
    date_of_birth: null,
    marital_status: null,
    nationality:  '',
    national_id:  '',
    personal_email: '',
    personal_phone: '',
    // Employment
    work_email:           '',
    work_phone:           '',
    date_of_joining:      null,
    date_of_confirmation: null,
    date_of_exit:         null,
    employment_type:      'full_time',
    employment_status:    'active',
    notice_period_days:   30,
    // Emergency
    emergency_contact_name:  '',
    emergency_contact_phone: '',
    bio: '',
});

onMounted(async () => {
    loading.value = true;
    try {
        const [c, d, desig, b, emps] = await Promise.all([
            CompanyService.getList({ per_page: 500 }),
            DepartmentService.getList({ per_page: 500 }),
            DesignationService.getList({ per_page: 500 }),
            BranchService.getList({ per_page: 500 }),
            EmployeeService.getList({ per_page: 500 }),
        ]);
        const numId = i => ({ ...i, id: Number(i.id) });
        companyOptions.value     = (c?.items     ?? []).map(numId);
        departmentOptions.value  = (d?.items     ?? []).map(numId);
        designationOptions.value = (desig?.items ?? []).map(numId);
        branchOptions.value      = (b?.items     ?? []).map(numId);
        employeeOptions.value    = (emps?.items ?? []).map(e => ({
            id: Number(e.id), name: `${e.first_name} ${e.last_name} (${e.employee_number})`,
        }));
        if (isEdit.value) await loadEmployee();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        loading.value = false;
    }
});

async function loadEmployee() {
    const d = await EmployeeService.getById(employeeId.value);
    Object.assign(form, {
        employee_number:      d.employee_number        ?? '',
        company_id:           d.company_id    ? Number(d.company_id)    : null,
        branch_id:            d.branch_id     ? Number(d.branch_id)     : null,
        department_id:        d.department_id ? Number(d.department_id) : null,
        designation_id:       d.designation_id ? Number(d.designation_id) : null,
        reports_to:           d.reports_to    ? Number(d.reports_to)    : null,
        first_name:           d.first_name           ?? '',
        last_name:            d.last_name            ?? '',
        middle_name:          d.middle_name          ?? '',
        preferred_name:       d.preferred_name       ?? '',
        gender:               d.gender               ?? null,
        date_of_birth:        d.date_of_birth        ?? null,
        marital_status:       d.marital_status       ?? null,
        nationality:          d.nationality          ?? '',
        national_id:          d.national_id          ?? '',
        personal_email:       d.personal_email       ?? '',
        personal_phone:       d.personal_phone       ?? '',
        work_email:           d.work_email           ?? '',
        work_phone:           d.work_phone           ?? '',
        date_of_joining:      d.date_of_joining      ?? null,
        date_of_confirmation: d.date_of_confirmation ?? null,
        date_of_exit:         d.date_of_exit         ?? null,
        employment_type:      d.employment_type      ?? 'full_time',
        employment_status:    d.employment_status    ?? 'active',
        notice_period_days:   Number(d.notice_period_days ?? 30),
        emergency_contact_name:  d.emergency_contact_name  ?? '',
        emergency_contact_phone: d.emergency_contact_phone ?? '',
        bio:                  d.bio                  ?? '',
    });
    profilePhotoUrl.value = d.profile_photo_url || null;
}

function validate() {
    errors.first_name     = form.first_name.trim()  ? '' : 'First name is required.';
    errors.last_name      = form.last_name.trim()   ? '' : 'Last name is required.';
    errors.work_email     = form.work_email.trim()  ? '' : 'Work email is required.';
    errors.company_id     = form.company_id         ? '' : 'Company is required.';
    errors.department_id  = form.department_id      ? '' : 'Department is required.';
    errors.designation_id = form.designation_id     ? '' : 'Designation is required.';
    return !Object.values(errors).some(Boolean);
}

function onPhotoSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        toast.add({ severity: 'error', detail: 'Please select an image file.', life: 4000 });
        return;
    }
    if (!isEdit.value) {
        // Preview only — will be uploaded after employee is created.
        profilePhotoUrl.value = URL.createObjectURL(file);
        form.pendingPhotoFile = file;
        return;
    }
    uploadPhoto(file);
}

async function uploadPhoto(file) {
    uploadingPhoto.value = true;
    try {
        const updated = await EmployeeService.uploadPhoto(employeeId.value, file);
        profilePhotoUrl.value = updated.profile_photo_url || null;
        toast.add({ severity: 'success', detail: 'Photo updated.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    } finally {
        uploadingPhoto.value = false;
    }
}

async function removePhoto() {
    if (!isEdit.value) {
        profilePhotoUrl.value = null;
        form.pendingPhotoFile = null;
        return;
    }
    uploadingPhoto.value = true;
    try {
        await EmployeeService.removePhoto(employeeId.value);
        profilePhotoUrl.value = null;
        toast.add({ severity: 'success', detail: 'Photo removed.', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', detail: e.message, life: 5000 });
    } finally {
        uploadingPhoto.value = false;
    }
}

async function save() {
    if (!validate()) return;

    saving.value = true;
    try {
        const body = {
            ...form,
            date_of_birth:        toDateStr(form.date_of_birth),
            date_of_joining:      toDateStr(form.date_of_joining),
            date_of_confirmation: toDateStr(form.date_of_confirmation),
            date_of_exit:         toDateStr(form.date_of_exit),
        };
        if (isEdit.value) {
            await EmployeeService.update(employeeId.value, body);
            toast.add({ severity: 'success', detail: 'Employee updated.', life: 3000 });
        } else {
            const created = await EmployeeService.create(body);
            // Upload pending photo if one was selected before save.
            if (form.pendingPhotoFile && created?.id) {
                try {
                    await EmployeeService.uploadPhoto(created.id, form.pendingPhotoFile);
                } catch { /* photo upload is non-blocking */ }
            }
            toast.add({ severity: 'success', detail: 'Employee created.', life: 3000 });
            router.push({ name: 'employees' });
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 5000 });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="card w-full !max-w-full">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <div class="font-semibold text-xl mb-1">{{ isEdit ? 'Edit Employee' : 'New Employee' }}</div>
                <p class="text-surface-500 m-0">{{ isEdit ? 'Update employee information.' : 'Add a new employee to the system.' }}</p>
            </div>
            <div class="flex gap-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" @click="router.back()" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-10">
            <ProgressSpinner style="width:2rem;height:2rem" />
        </div>

        <template v-else>
            <!-- Personal Information -->
            <section class="mb-8">
                <h2 class="section-title">Personal Information</h2>

                <!-- Profile Photo -->
                <div class="flex items-center gap-4 mb-6">
                    <div class="relative">
                        <a
                            v-if="profilePhotoUrl"
                            :href="profilePhotoUrl"
                            target="_blank"
                            rel="noopener"
                        >
                            <img
                                :src="profilePhotoUrl"
                                alt="Profile photo"
                                class="w-24 h-24 rounded-full object-cover border-2 border-surface-200 dark:border-surface-700 cursor-pointer hover:opacity-80 transition-opacity"
                            />
                        </a>
                        <div
                            v-else
                            class="w-24 h-24 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center border-2 border-surface-200 dark:border-surface-700"
                        >
                            <i class="pi pi-user text-3xl text-surface-400"></i>
                        </div>
                        <ProgressSpinner
                            v-if="uploadingPhoto"
                            class="!absolute inset-0 m-auto"
                            style="width:2rem;height:2rem"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Profile Photo</label>
                        <div class="flex gap-2">
                            <Button
                                :label="profilePhotoUrl ? 'Change' : 'Upload'"
                                icon="pi pi-upload"
                                size="small"
                                outlined
                                :loading="uploadingPhoto"
                                @click="$refs.photoInput.click()"
                            />
                            <Button
                                v-if="profilePhotoUrl"
                                label="Remove"
                                icon="pi pi-trash"
                                size="small"
                                outlined
                                severity="danger"
                                :loading="uploadingPhoto"
                                @click="removePhoto"
                            />
                        </div>
                        <input
                            ref="photoInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onPhotoSelect"
                        />
                        <small class="text-surface-400">JPG, PNG or GIF. Max 2MB recommended.</small>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">First Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.first_name" fluid :invalid="!!errors.first_name" />
                        <small v-if="errors.first_name" class="text-red-500">{{ errors.first_name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Middle Name</label>
                        <InputText v-model="form.middle_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Last Name <span class="text-red-500">*</span></label>
                        <InputText v-model="form.last_name" fluid :invalid="!!errors.last_name" />
                        <small v-if="errors.last_name" class="text-red-500">{{ errors.last_name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Preferred Name</label>
                        <InputText v-model="form.preferred_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Gender</label>
                        <Select v-model="form.gender" :options="genderOptions" optionLabel="label" optionValue="value" fluid showClear />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Date of Birth</label>
                        <HrDatePicker v-model="form.date_of_birth" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Marital Status</label>
                        <Select v-model="form.marital_status" :options="maritalOptions" optionLabel="label" optionValue="value" fluid showClear />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Nationality (2-letter)</label>
                        <InputText v-model="form.nationality" fluid maxlength="2" placeholder="MY" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">National ID</label>
                        <InputText v-model="form.national_id" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Personal Email</label>
                        <InputText v-model="form.personal_email" fluid type="email" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Personal Phone</label>
                        <InputText v-model="form.personal_phone" fluid />
                    </div>
                </div>
            </section>

            <Divider />

            <!-- Employment Details -->
            <section class="mb-8">
                <h2 class="section-title">Employment Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Employee Number</label>
                        <InputText v-model="form.employee_number" fluid placeholder="Auto-generated if blank" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Work Email <span class="text-red-500">*</span></label>
                        <InputText v-model="form.work_email" fluid type="email" :invalid="!!errors.work_email" />
                        <small v-if="errors.work_email" class="text-red-500">{{ errors.work_email }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Work Phone</label>
                        <InputText v-model="form.work_phone" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Company <span class="text-red-500">*</span></label>
                        <Select v-model="form.company_id" :options="companyOptions" optionLabel="name" optionValue="id"
                            placeholder="Select" fluid :invalid="!!errors.company_id" />
                        <small v-if="errors.company_id" class="text-red-500">{{ errors.company_id }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Branch</label>
                        <Select v-model="form.branch_id" :options="branchOptions" optionLabel="name" optionValue="id" placeholder="Select" showClear fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Department <span class="text-red-500">*</span></label>
                        <Select v-model="form.department_id" :options="departmentOptions" optionLabel="name" optionValue="id" placeholder="Select" fluid :invalid="!!errors.department_id" />
                        <small v-if="errors.department_id" class="text-red-500">{{ errors.department_id }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Designation <span class="text-red-500">*</span></label>
                        <Select v-model="form.designation_id" :options="designationOptions" optionLabel="name" optionValue="id" placeholder="Select" fluid :invalid="!!errors.designation_id" />
                        <small v-if="errors.designation_id" class="text-red-500">{{ errors.designation_id }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Reports To</label>
                        <Select v-model="form.reports_to" :options="employeeOptions" optionLabel="name" optionValue="id" placeholder="Select" showClear fluid filter />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Employment Type</label>
                        <Select v-model="form.employment_type" :options="employmentTypeOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Employment Status</label>
                        <Select v-model="form.employment_status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Date of Joining</label>
                        <HrDatePicker v-model="form.date_of_joining" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Date of Confirmation</label>
                        <HrDatePicker v-model="form.date_of_confirmation" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Date of Exit</label>
                        <HrDatePicker v-model="form.date_of_exit" showIcon fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Notice Period (days)</label>
                        <InputNumber v-model="form.notice_period_days" :min="0" fluid />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-3">
                        <label class="font-medium">Bio</label>
                        <Textarea v-model="form.bio" rows="3" fluid />
                    </div>
                </div>
            </section>

            <Divider />

            <!-- Emergency Contact -->
            <section class="mb-8">
                <h2 class="section-title">Emergency Contact</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Contact Name</label>
                        <InputText v-model="form.emergency_contact_name" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-medium">Contact Phone</label>
                        <InputText v-model="form.emergency_contact_phone" fluid />
                    </div>
                </div>
            </section>

            <!-- Sub-record tabs (edit only) — each panel has its own table -->
            <template v-if="isEdit">
                <Divider />
                <Tabs value="education">
                    <TabList>
                        <Tab value="education">Education</Tab>
                        <Tab value="work_history">Work History</Tab>
                        <Tab value="bank">Bank Accounts</Tab>
                        <Tab value="documents">Documents</Tab>
                        <Tab value="movements">Movements</Tab>
                    </TabList>
                    <TabPanels>
                        <TabPanel value="education">
                            <EducationTab :employee-id="employeeId" />
                        </TabPanel>
                        <TabPanel value="work_history">
                            <WorkHistoryTab :employee-id="employeeId" />
                        </TabPanel>
                        <TabPanel value="bank">
                            <BankTab :employee-id="employeeId" />
                        </TabPanel>
                        <TabPanel value="documents">
                            <DocumentsTab :employee-id="employeeId" />
                        </TabPanel>
                        <TabPanel value="movements">
                            <MovementsTab :employee-id="employeeId" />
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </template>

            <!-- Sticky save footer -->
            <div class="flex justify-end gap-2 pt-4 border-t border-surface-200 dark:border-surface-700">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" @click="router.back()" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </template>
    </div>
</template>

<style scoped>
.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--p-surface-200);
}

.app-dark .section-title {
    border-bottom-color: var(--p-surface-700);
}

:deep(.p-select) {
    min-height: unset;
    height: calc(var(--p-form-field-padding-y) * 2 + var(--p-form-field-font-size) * var(--p-form-field-line-height, 1.5));
}

:deep(.p-select-label) {
    line-height: var(--p-form-field-line-height, 1.5);
}
</style>
