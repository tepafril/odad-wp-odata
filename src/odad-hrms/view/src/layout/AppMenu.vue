<script setup>
import { computed, onMounted, ref } from 'vue';
import AppMenuItem from './AppMenuItem.vue';
import { LeaveApplicationService } from '@/service/LeaveApplicationService';

const activeModules = (window.wphrApi && window.wphrApi.activeModules) || {};

const pendingCount = ref(0);

async function loadPendingCount() {
    try {
        const data = await LeaveApplicationService.getList({ status: 'pending', per_page: 1 });
        pendingCount.value = data?.total ?? 0;
    } catch { /* silent */ }
}

if (activeModules.leave) {
    onMounted(() => {
        loadPendingCount();
        window.addEventListener('wphr:pending-changed', loadPendingCount);
    });
}

const model = computed(() => {
    const items = [
        {
            label: 'Home',
            items: [
                { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' }
            ]
        },
        {
            label: 'Core HR',
            items: [
                { label: 'Company',            icon: 'pi pi-fw pi-building',   to: '/companies' },
                { label: 'Branches',           icon: 'pi pi-fw pi-map-marker', to: '/setup/branches' },
                { label: 'Departments',        icon: 'pi pi-fw pi-sitemap',    to: '/setup/departments' },
                { label: 'Designations',       icon: 'pi pi-fw pi-id-card',    to: '/setup/designations' },
                { label: 'Employment Types',   icon: 'pi pi-fw pi-briefcase',  to: '/setup/employment-types' },
                { label: 'Employees',          icon: 'pi pi-fw pi-users',      to: '/employees' },
                { label: 'Import Employees',   icon: 'pi pi-fw pi-upload',     to: '/employees/import' },
            ]
        },
        ...(activeModules.leave ? [{
            label: 'Leaves',
            items: [
                { label: 'Leave Types',        icon: 'pi pi-fw pi-tags',        to: '/leaves/leave-types' },
                { label: 'Leave Policies',     icon: 'pi pi-fw pi-file',        to: '/leaves/leave-policies' },
                { label: 'Leave Applications', icon: 'pi pi-fw pi-inbox',       to: '/leaves/applications' },
                { label: 'Pending Approval',   icon: 'pi pi-fw pi-clock',       to: '/leaves/pending-approval', badge: pendingCount.value || null },
                { label: 'Comp-Off Requests',  icon: 'pi pi-fw pi-replay',      to: '/leaves/compensatory-requests' },
                { label: 'Leave Balances',     icon: 'pi pi-fw pi-chart-pie',   to: '/leaves/balances' },
                { label: 'Holiday Lists',      icon: 'pi pi-fw pi-sun',         to: '/holiday-lists' },
            ]
        }] : []),
        {
            label: 'Shifts',
            items: [
                { label: 'Shift Types',       icon: 'pi pi-fw pi-clock',    to: '/shifts/shift-types' },
                { label: 'Shift Assignments', icon: 'pi pi-fw pi-calendar', to: '/shifts/assignments' },
            ]
        },
        {
            label: 'Attendance',
            items: [
                { label: 'Attendance Log',    icon: 'pi pi-fw pi-check-circle', to: '/attendance' },
                { label: 'Check-ins',         icon: 'pi pi-fw pi-sign-in',      to: '/attendance/checkins' },
                { label: 'Requests',          icon: 'pi pi-fw pi-file-edit',    to: '/attendance/requests' },
                { label: 'Overtime',          icon: 'pi pi-fw pi-clock',        to: '/attendance/overtime' },
                { label: 'Upload Attendance', icon: 'pi pi-fw pi-upload',       to: '/attendance/upload' },
            ]
        },
        ...(activeModules.payroll ? [{
            label: 'Payroll',
            items: [
                { label: 'Payroll Runs',      icon: 'pi pi-fw pi-play-circle',  to: '/payroll/runs' },
                { label: 'Payslips',          icon: 'pi pi-fw pi-file-pdf',     to: '/payroll/payslips' },
                { label: 'Employee Salary',   icon: 'pi pi-fw pi-user-edit',    to: '/payroll/employee-salary' },
                { label: 'Additional Salary', icon: 'pi pi-fw pi-plus-circle',  to: '/payroll/additional-salary' },
                { label: 'Loans',             icon: 'pi pi-fw pi-credit-card',  to: '/payroll/loans' },
                { label: 'Salary Components', icon: 'pi pi-fw pi-dollar',       to: '/setup/salary-components' },
                { label: 'Salary Structures', icon: 'pi pi-fw pi-list',         to: '/setup/salary-structures' },
            ]
        }] : []),
        ...(activeModules.recruit ? [{
            label: 'Recruitment',
            items: [
                { label: 'Applicants',    icon: 'pi pi-fw pi-user-plus',  to: '/recruit/applicants' },
                { label: 'Interviews',    icon: 'pi pi-fw pi-comments',   to: '/recruit/interviews/new' },
                { label: 'Offer Letters', icon: 'pi pi-fw pi-envelope',   to: '/recruit/offer-letters/new' },
            ]
        }] : []),
        ...(activeModules.recruit ? [{
            label: 'Performance',
            items: [
                { label: 'Goals',             icon: 'pi pi-fw pi-flag',       to: '/performance/goals' },
                { label: 'Appraisal Cycles',  icon: 'pi pi-fw pi-sync',       to: '/performance/appraisal-cycles' },
                { label: 'Appraisals',        icon: 'pi pi-fw pi-star-fill',  to: '/performance/appraisals' },
            ]
        }] : []),
        ...(activeModules.recruit ? [{
            label: 'Training',
            items: [
                { label: 'Training Events', icon: 'pi pi-fw pi-book', to: '/training/events' },
            ]
        }] : []),
        {
            label: 'Reports',
            items: [
                { label: 'Monthly Attendance',   icon: 'pi pi-fw pi-calendar',    to: '/reports/monthly-attendance' },
                { label: 'Attendance Summary',   icon: 'pi pi-fw pi-chart-bar',   to: '/reports/attendance-summary' },
                { label: 'Daily Work',           icon: 'pi pi-fw pi-clock',       to: '/reports/daily-work' },
                ...(activeModules.leave ? [
                    { label: 'Leave Balance',         icon: 'pi pi-fw pi-chart-pie',   to: '/reports/leave-balance' },
                    { label: 'Leave Summary',         icon: 'pi pi-fw pi-list',        to: '/reports/leave-summary' },
                    { label: 'Leave Usage Trends',    icon: 'pi pi-fw pi-chart-line',  to: '/reports/leave-usage-trends' },
                    { label: 'Leave by Department',   icon: 'pi pi-fw pi-sitemap',     to: '/reports/leave-department' },
                    { label: 'Employee Leave History', icon: 'pi pi-fw pi-history',    to: '/reports/employee-leave-history' },
                    { label: 'Comp-Off Balances',     icon: 'pi pi-fw pi-replay',      to: '/reports/comp-off-balance' },
                    { label: 'Leave Carry-Forward',   icon: 'pi pi-fw pi-arrow-right', to: '/reports/leave-carry-forward' },
                    { label: 'Leave Accrual',         icon: 'pi pi-fw pi-percentage',  to: '/reports/leave-accrual' },
                    { label: 'Sick Leave Analysis',   icon: 'pi pi-fw pi-heart',       to: '/reports/sick-leave-analysis' },
                    { label: 'Absenteeism Rate',      icon: 'pi pi-fw pi-exclamation-triangle', to: '/reports/absenteeism-rate' },
                    { label: 'Holiday Impact',        icon: 'pi pi-fw pi-sun',         to: '/reports/holiday-impact' },
                    { label: 'Parental Leave',        icon: 'pi pi-fw pi-users',       to: '/reports/parental-leave' },
                ] : []),
                { label: 'Headcount',            icon: 'pi pi-fw pi-users',       to: '/reports/headcount' },
                ...(activeModules.payroll ? [
                    { label: 'Payroll Summary',      icon: 'pi pi-fw pi-dollar',     to: '/reports/payroll-summary' },
                ] : []),
                ...(activeModules.recruit ? [
                    { label: 'Recruitment Pipeline', icon: 'pi pi-fw pi-user-plus',  to: '/reports/recruitment-pipeline' },
                ] : []),
            ]
        },
        {
            label: 'Settings',
            items: [
                { label: 'General',       icon: 'pi pi-fw pi-cog',            to: '/settings/general' },
                ...(activeModules.leave ? [
                    { label: 'Leave',     icon: 'pi pi-fw pi-calendar-minus', to: '/settings/leave' },
                ] : []),
                { label: 'Attendance',    icon: 'pi pi-fw pi-clock',          to: '/settings/attendance' },
                ...(activeModules.payroll ? [
                    { label: 'Payroll',   icon: 'pi pi-fw pi-dollar',         to: '/settings/payroll' },
                ] : []),
                { label: 'Notifications', icon: 'pi pi-fw pi-bell',           to: '/settings/notifications' },
            ]
        },
    ];
    return items;
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
