import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHashHistory } from 'vue-router';

const activeModules = (window.wphrApi && window.wphrApi.activeModules) || {};

const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue')
                },
                {
                    path: '/employees',
                    name: 'employees',
                    meta: { breadcrumb: ['People', 'Employees'] },
                    component: () => import('@/views/pages/people/employee/EmployeeList.vue')
                },
                {
                    path: '/employees/new',
                    name: 'employee-create',
                    meta: { breadcrumb: ['People', 'Employees', 'New'] },
                    component: () => import('@/views/pages/people/employee/EmployeeForm.vue')
                },
                {
                    path: '/employees/:id/edit',
                    name: 'employee-edit',
                    meta: { breadcrumb: ['People', 'Employees', 'Edit'] },
                    component: () => import('@/views/pages/people/employee/EmployeeForm.vue')
                },
                {
                    path: '/companies',
                    name: 'companies',
                    meta: { breadcrumb: ['People', 'Companies'] },
                    component: () => import('@/views/pages/setup/CompanyList.vue')
                },
                {
                    path: '/holiday-lists',
                    name: 'holiday-lists',
                    meta: { breadcrumb: ['Leave & Attendance', 'Holiday List'] },
                    component: () => import('@/views/pages/setup/HolidayListList.vue')
                },
                {
                    path: '/setup/departments',
                    name: 'departments',
                    meta: { breadcrumb: ['Setup', 'Departments'] },
                    component: () => import('@/views/pages/setup/DepartmentList.vue')
                },
                {
                    path: '/setup/designations',
                    name: 'designations',
                    meta: { breadcrumb: ['Setup', 'Designations'] },
                    component: () => import('@/views/pages/setup/DesignationList.vue')
                },
                {
                    path: '/setup/branches',
                    name: 'branches',
                    meta: { breadcrumb: ['Setup', 'Branches'] },
                    component: () => import('@/views/pages/setup/BranchList.vue')
                },
                {
                    path: '/setup/employment-types',
                    name: 'employment-types',
                    meta: { breadcrumb: ['Setup', 'Employment Types'] },
                    component: () => import('@/views/pages/setup/EmploymentTypeList.vue')
                },
                {
                    path: '/leaves/balances',
                    name: 'leave-balances',
                    meta: { breadcrumb: ['Leaves', 'Leave Balances'] },
                    component: () => import('@/views/pages/leaves/LeaveBalanceList.vue')
                },
                {
                    path: '/leaves/leave-types',
                    name: 'leave-types',
                    meta: { breadcrumb: ['Leaves', 'Leave Types'] },
                    component: () => import('@/views/pages/leaves/LeaveTypeList.vue')
                },
                {
                    path: '/leaves/leave-policies',
                    name: 'leave-policies',
                    meta: { breadcrumb: ['Leaves', 'Leave Policies'] },
                    component: () => import('@/views/pages/leaves/LeavePolicyList.vue')
                },
                {
                    path: '/leaves/applications',
                    name: 'leave-applications',
                    meta: { breadcrumb: ['Leaves', 'Leave Applications'] },
                    component: () => import('@/views/pages/leaves/LeaveApplicationList.vue')
                },
                {
                    path: '/leaves/pending-approval',
                    name: 'pending-approval',
                    meta: { breadcrumb: ['Leaves', 'Pending Approval'] },
                    component: () => import('@/views/pages/leaves/PendingApprovalList.vue')
                },
                {
                    path: '/leaves/compensatory-requests',
                    name: 'compensatory-requests',
                    meta: { breadcrumb: ['Leaves', 'Compensatory Requests'] },
                    component: () => import('@/views/pages/leaves/CompensatoryRequestList.vue')
                },
                {
                    path: '/shifts/shift-types',
                    name: 'shift-types',
                    meta: { breadcrumb: ['Shifts', 'Shift Types'] },
                    component: () => import('@/views/pages/shifts/ShiftTypeList.vue')
                },
                {
                    path: '/shifts/assignments',
                    name: 'shift-assignments',
                    meta: { breadcrumb: ['Shifts', 'Shift Assignments'] },
                    component: () => import('@/views/pages/shifts/ShiftAssignmentList.vue')
                },
                {
                    path: '/attendance',
                    name: 'attendance',
                    meta: { breadcrumb: ['Attendance'] },
                    component: () => import('@/views/pages/attendance/AttendanceLog.vue')
                },
                // ---- Reports ----
                {
                    path: '/reports/monthly-attendance',
                    name: 'report-monthly-attendance',
                    meta: { breadcrumb: ['Reports', 'Monthly Attendance Sheet'] },
                    component: () => import('@/views/pages/reports/MonthlyAttendanceReport.vue')
                },
                {
                    path: '/reports/attendance-summary',
                    name: 'report-attendance-summary',
                    meta: { breadcrumb: ['Reports', 'Attendance Summary'] },
                    component: () => import('@/views/pages/reports/AttendanceSummaryReport.vue')
                },
                {
                    path: '/reports/leave-balance',
                    name: 'report-leave-balance',
                    meta: { breadcrumb: ['Reports', 'Leave Balance'] },
                    component: () => import('@/views/pages/reports/LeaveBalanceReport.vue')
                },
                {
                    path: '/reports/headcount',
                    name: 'report-headcount',
                    meta: { breadcrumb: ['Reports', 'Headcount Analytics'] },
                    component: () => import('@/views/pages/reports/HeadcountReport.vue')
                },
                {
                    path: '/reports/daily-work',
                    name: 'report-daily-work',
                    meta: { breadcrumb: ['Reports', 'Daily Work Summary'] },
                    component: () => import('@/views/pages/reports/DailyWorkReport.vue')
                },
                // ---- Recruitment ----
                {
                    path: '/recruitment/staffing-plans',
                    name: 'staffing-plans',
                    meta: { breadcrumb: ['Recruitment', 'Staffing Plans'] },
                    component: () => import('@/views/pages/recruitment/StaffingPlanList.vue')
                },
                {
                    path: '/recruitment/staffing-plans/new',
                    name: 'staffing-plan-create',
                    meta: { breadcrumb: ['Recruitment', 'Staffing Plans', 'New'] },
                    component: () => import('@/views/pages/recruitment/StaffingPlanForm.vue')
                },
                {
                    path: '/recruitment/staffing-plans/:id/edit',
                    name: 'staffing-plan-edit',
                    meta: { breadcrumb: ['Recruitment', 'Staffing Plans', 'Edit'] },
                    component: () => import('@/views/pages/recruitment/StaffingPlanForm.vue')
                },
                {
                    path: '/recruitment/job-openings',
                    name: 'job-openings',
                    meta: { breadcrumb: ['Recruitment', 'Job Openings'] },
                    component: () => import('@/views/pages/recruitment/JobOpeningList.vue')
                },
                {
                    path: '/recruitment/job-openings/new',
                    name: 'job-opening-create',
                    meta: { breadcrumb: ['Recruitment', 'Job Openings', 'New'] },
                    component: () => import('@/views/pages/recruitment/JobOpeningForm.vue')
                },
                {
                    path: '/recruitment/job-openings/:id/edit',
                    name: 'job-opening-edit',
                    meta: { breadcrumb: ['Recruitment', 'Job Openings', 'Edit'] },
                    component: () => import('@/views/pages/recruitment/JobOpeningForm.vue')
                },
                {
                    path: '/recruitment/applicants',
                    name: 'applicants',
                    meta: { breadcrumb: ['Recruitment', 'Job Applicants'] },
                    component: () => import('@/views/pages/recruitment/ApplicantList.vue')
                },
                {
                    path: '/recruitment/applicants/new',
                    name: 'applicant-create',
                    meta: { breadcrumb: ['Recruitment', 'Job Applicants', 'New'] },
                    component: () => import('@/views/pages/recruitment/ApplicantForm.vue')
                },
                {
                    path: '/recruitment/applicants/:id/edit',
                    name: 'applicant-edit',
                    meta: { breadcrumb: ['Recruitment', 'Job Applicants', 'Edit'] },
                    component: () => import('@/views/pages/recruitment/ApplicantForm.vue')
                },
                {
                    path: '/recruitment/interviews',
                    name: 'interviews',
                    meta: { breadcrumb: ['Recruitment', 'Interviews'] },
                    component: () => import('@/views/pages/recruitment/InterviewList.vue')
                },
                {
                    path: '/recruitment/job-offers',
                    name: 'job-offers',
                    meta: { breadcrumb: ['Recruitment', 'Job Offers'] },
                    component: () => import('@/views/pages/recruitment/JobOfferList.vue')
                },
                {
                    path: '/recruitment/job-offers/new',
                    name: 'job-offer-create',
                    meta: { breadcrumb: ['Recruitment', 'Job Offers', 'New'] },
                    component: () => import('@/views/pages/recruitment/JobOfferForm.vue')
                },
                {
                    path: '/recruitment/job-offers/:id/edit',
                    name: 'job-offer-edit',
                    meta: { breadcrumb: ['Recruitment', 'Job Offers', 'Edit'] },
                    component: () => import('@/views/pages/recruitment/JobOfferForm.vue')
                },
                // ---- Attendance Enhancements ----
                {
                    path: '/attendance/checkins',
                    name: 'checkins',
                    meta: { breadcrumb: ['Attendance', 'Employee Checkins'] },
                    component: () => import('@/views/pages/attendance-enhancements/CheckinList.vue')
                },
                {
                    path: '/attendance/requests',
                    name: 'attendance-requests',
                    meta: { breadcrumb: ['Attendance', 'Attendance Requests'] },
                    component: () => import('@/views/pages/attendance/AttendanceRequestList.vue')
                },
                {
                    path: '/attendance/requests/new',
                    name: 'attendance-request-create',
                    meta: { breadcrumb: ['Attendance', 'Attendance Requests', 'New'] },
                    component: () => import('@/views/pages/attendance-enhancements/AttendanceRequestForm.vue')
                },
                {
                    path: '/attendance/overtime',
                    name: 'overtime',
                    meta: { breadcrumb: ['Attendance', 'Overtime'] },
                    component: () => import('@/views/pages/attendance-enhancements/OvertimeList.vue')
                },
                {
                    path: '/attendance/overtime/new',
                    name: 'overtime-create',
                    meta: { breadcrumb: ['Attendance', 'Overtime', 'New'] },
                    component: () => import('@/views/pages/attendance-enhancements/OvertimeForm.vue')
                },
                {
                    path: '/attendance/upload',
                    name: 'upload-attendance',
                    meta: { breadcrumb: ['Attendance', 'Upload Attendance'] },
                    component: () => import('@/views/pages/attendance-enhancements/UploadAttendance.vue')
                },
                // ---- HR Documents & Letters ----
                {
                    path: '/documents/employee-documents',
                    name: 'employee-documents',
                    meta: { breadcrumb: ['Documents', 'Employee Documents'] },
                    component: () => import('@/views/pages/documents/EmployeeDocumentList.vue')
                },
                {
                    path: '/documents/letter-templates',
                    name: 'letter-templates',
                    meta: { breadcrumb: ['Documents', 'Letter Templates'] },
                    component: () => import('@/views/pages/documents/LetterTemplateList.vue')
                },
                {
                    path: '/documents/letter-templates/new',
                    name: 'letter-template-create',
                    meta: { breadcrumb: ['Documents', 'Letter Templates', 'New'] },
                    component: () => import('@/views/pages/documents/LetterTemplateForm.vue')
                },
                {
                    path: '/documents/letter-templates/:id/edit',
                    name: 'letter-template-edit',
                    meta: { breadcrumb: ['Documents', 'Letter Templates', 'Edit'] },
                    component: () => import('@/views/pages/documents/LetterTemplateForm.vue')
                },
                {
                    path: '/documents/generated-letters',
                    name: 'generated-letters',
                    meta: { breadcrumb: ['Documents', 'Generated Letters'] },
                    component: () => import('@/views/pages/documents/GeneratedLetterList.vue')
                },
                // ---- Payroll ----
                { path: '/payroll/runs', name: 'payroll-runs', meta: { breadcrumb: ['Payroll', 'Payroll Runs'] }, component: () => import('@/views/pages/payroll/PayrollRunList.vue') },
                { path: '/payroll/runs/new', name: 'payroll-run-create', meta: { breadcrumb: ['Payroll', 'Payroll Runs', 'New'] }, component: () => import('@/views/pages/payroll/PayrollRunForm.vue') },
                { path: '/payroll/runs/:id', name: 'payroll-run-detail', meta: { breadcrumb: ['Payroll', 'Payroll Runs', 'Detail'] }, component: () => import('@/views/pages/payroll/PayrollRunDetail.vue') },
                { path: '/payroll/payslips', name: 'payslips', meta: { breadcrumb: ['Payroll', 'Payslips'] }, component: () => import('@/views/pages/payroll/PayslipList.vue') },
                { path: '/payroll/payslips/:id', name: 'payslip-detail', meta: { breadcrumb: ['Payroll', 'Payslips', 'Detail'] }, component: () => import('@/views/pages/payroll/PayslipDetail.vue') },
                { path: '/payroll/additional-salary', name: 'additional-salary', meta: { breadcrumb: ['Payroll', 'Additional Salary'] }, component: () => import('@/views/pages/payroll/AdditionalSalaryList.vue') },
                { path: '/payroll/additional-salary/new', name: 'additional-salary-create', meta: { breadcrumb: ['Payroll', 'Additional Salary', 'New'] }, component: () => import('@/views/pages/payroll/AdditionalSalaryForm.vue') },
                { path: '/payroll/additional-salary/:id/edit', name: 'additional-salary-edit', meta: { breadcrumb: ['Payroll', 'Additional Salary', 'Edit'] }, component: () => import('@/views/pages/payroll/AdditionalSalaryForm.vue') },
                { path: '/payroll/loans', name: 'loans', meta: { breadcrumb: ['Payroll', 'Loans'] }, component: () => import('@/views/pages/payroll/LoanList.vue') },
                { path: '/payroll/loans/new', name: 'loan-create', meta: { breadcrumb: ['Payroll', 'Loans', 'New'] }, component: () => import('@/views/pages/payroll/LoanForm.vue') },
                { path: '/payroll/loans/:id/edit', name: 'loan-edit', meta: { breadcrumb: ['Payroll', 'Loans', 'Edit'] }, component: () => import('@/views/pages/payroll/LoanForm.vue') },
                { path: '/payroll/employee-salary', name: 'employee-salary', meta: { breadcrumb: ['Payroll', 'Employee Salary'] }, component: () => import('@/views/pages/payroll/EmployeeSalaryForm.vue') },
                // ---- Payroll Setup ----
                { path: '/setup/salary-components', name: 'salary-components', meta: { breadcrumb: ['Setup', 'Salary Components'] }, component: () => import('@/views/pages/setup/SalaryComponentList.vue') },
                { path: '/setup/salary-components/new', name: 'salary-component-create', meta: { breadcrumb: ['Setup', 'Salary Components', 'New'] }, component: () => import('@/views/pages/setup/SalaryComponentForm.vue') },
                { path: '/setup/salary-components/:id/edit', name: 'salary-component-edit', meta: { breadcrumb: ['Setup', 'Salary Components', 'Edit'] }, component: () => import('@/views/pages/setup/SalaryComponentForm.vue') },
                { path: '/setup/salary-structures', name: 'salary-structures', meta: { breadcrumb: ['Setup', 'Salary Structures'] }, component: () => import('@/views/pages/setup/SalaryStructureList.vue') },
                { path: '/setup/salary-structures/new', name: 'salary-structure-create', meta: { breadcrumb: ['Setup', 'Salary Structures', 'New'] }, component: () => import('@/views/pages/setup/SalaryStructureForm.vue') },
                { path: '/setup/salary-structures/:id/edit', name: 'salary-structure-edit', meta: { breadcrumb: ['Setup', 'Salary Structures', 'Edit'] }, component: () => import('@/views/pages/setup/SalaryStructureForm.vue') },
                // ---- Recruitment (wp-hr-recruit) ----
                { path: '/recruit/applicants', name: 'recruit-applicants', meta: { breadcrumb: ['Recruitment', 'Applicants'] }, component: () => import('@/views/pages/recruitment/ApplicantList.vue') },
                { path: '/recruit/applicants/new', name: 'recruit-applicant-create', meta: { breadcrumb: ['Recruitment', 'Applicants', 'New'] }, component: () => import('@/views/pages/recruitment/ApplicantForm.vue') },
                { path: '/recruit/applicants/:id/edit', name: 'recruit-applicant-edit', meta: { breadcrumb: ['Recruitment', 'Applicants', 'Edit'] }, component: () => import('@/views/pages/recruitment/ApplicantForm.vue') },
                { path: '/recruit/applicants/:id', name: 'recruit-applicant-detail', meta: { breadcrumb: ['Recruitment', 'Applicants', 'Detail'] }, component: () => import('@/views/pages/recruitment/ApplicantDetail.vue') },
                { path: '/recruit/interviews/new', name: 'recruit-interview-create', meta: { breadcrumb: ['Recruitment', 'Interviews', 'New'] }, component: () => import('@/views/pages/recruitment/InterviewForm.vue') },
                { path: '/recruit/interviews/:id/edit', name: 'recruit-interview-edit', meta: { breadcrumb: ['Recruitment', 'Interviews', 'Edit'] }, component: () => import('@/views/pages/recruitment/InterviewForm.vue') },
                { path: '/recruit/offer-letters/new', name: 'recruit-offer-create', meta: { breadcrumb: ['Recruitment', 'Offer Letters', 'New'] }, component: () => import('@/views/pages/recruitment/OfferLetterForm.vue') },
                { path: '/recruit/offer-letters/:id/edit', name: 'recruit-offer-edit', meta: { breadcrumb: ['Recruitment', 'Offer Letters', 'Edit'] }, component: () => import('@/views/pages/recruitment/OfferLetterForm.vue') },
                // ---- Performance (wp-hr-recruit) ----
                { path: '/performance/goals', name: 'goals', meta: { breadcrumb: ['Performance', 'Goals'] }, component: () => import('@/views/pages/performance/GoalList.vue') },
                { path: '/performance/goals/new', name: 'goal-create', meta: { breadcrumb: ['Performance', 'Goals', 'New'] }, component: () => import('@/views/pages/performance/GoalForm.vue') },
                { path: '/performance/goals/:id/edit', name: 'goal-edit', meta: { breadcrumb: ['Performance', 'Goals', 'Edit'] }, component: () => import('@/views/pages/performance/GoalForm.vue') },
                { path: '/performance/appraisal-cycles', name: 'appraisal-cycles', meta: { breadcrumb: ['Performance', 'Appraisal Cycles'] }, component: () => import('@/views/pages/performance/AppraisalCycleList.vue') },
                { path: '/performance/appraisal-cycles/new', name: 'appraisal-cycle-create', meta: { breadcrumb: ['Performance', 'Appraisal Cycles', 'New'] }, component: () => import('@/views/pages/performance/AppraisalCycleForm.vue') },
                { path: '/performance/appraisal-cycles/:id/edit', name: 'appraisal-cycle-edit', meta: { breadcrumb: ['Performance', 'Appraisal Cycles', 'Edit'] }, component: () => import('@/views/pages/performance/AppraisalCycleForm.vue') },
                { path: '/performance/appraisals', name: 'appraisals', meta: { breadcrumb: ['Performance', 'Appraisals'] }, component: () => import('@/views/pages/performance/AppraisalList.vue') },
                { path: '/performance/appraisals/new', name: 'appraisal-create', meta: { breadcrumb: ['Performance', 'Appraisals', 'New'] }, component: () => import('@/views/pages/performance/AppraisalForm.vue') },
                { path: '/performance/appraisals/:id/review', name: 'appraisal-review', meta: { breadcrumb: ['Performance', 'Appraisals', 'Review'] }, component: () => import('@/views/pages/performance/AppraisalForm.vue') },
                // ---- Training (wp-hr-recruit) ----
                { path: '/training/events', name: 'training-events', meta: { breadcrumb: ['Training', 'Events'] }, component: () => import('@/views/pages/training/TrainingEventList.vue') },
                { path: '/training/events/new', name: 'training-event-create', meta: { breadcrumb: ['Training', 'Events', 'New'] }, component: () => import('@/views/pages/training/TrainingEventForm.vue') },
                { path: '/training/events/:id/edit', name: 'training-event-edit', meta: { breadcrumb: ['Training', 'Events', 'Edit'] }, component: () => import('@/views/pages/training/TrainingEventForm.vue') },
                { path: '/training/events/:id', name: 'training-event-detail', meta: { breadcrumb: ['Training', 'Events', 'Detail'] }, component: () => import('@/views/pages/training/TrainingEventDetail.vue') },
                // ---- Settings ----
                { path: '/settings/general', name: 'settings-general', meta: { breadcrumb: ['Settings', 'General'] }, component: () => import('@/views/pages/settings/GeneralSettings.vue') },
                { path: '/settings/leave', name: 'settings-leave', meta: { breadcrumb: ['Settings', 'Leave'] }, component: () => import('@/views/pages/settings/LeaveSettings.vue') },
                { path: '/settings/attendance', name: 'settings-attendance', meta: { breadcrumb: ['Settings', 'Attendance'] }, component: () => import('@/views/pages/settings/AttendanceSettings.vue') },
                { path: '/settings/payroll', name: 'settings-payroll', meta: { breadcrumb: ['Settings', 'Payroll'] }, component: () => import('@/views/pages/settings/PayrollSettings.vue') },
                { path: '/settings/notifications', name: 'settings-notifications', meta: { breadcrumb: ['Settings', 'Notifications'] }, component: () => import('@/views/pages/settings/NotificationSettings.vue') },
                // ---- Reports (new) ----
                { path: '/reports/leave-summary', name: 'report-leave-summary', meta: { breadcrumb: ['Reports', 'Leave Summary'] }, component: () => import('@/views/pages/reports/LeaveSummaryReport.vue') },
                { path: '/reports/payroll-summary', name: 'report-payroll-summary', meta: { breadcrumb: ['Reports', 'Payroll Summary'] }, component: () => import('@/views/pages/reports/PayrollSummaryReport.vue') },
                { path: '/reports/recruitment-pipeline', name: 'report-recruitment-pipeline', meta: { breadcrumb: ['Reports', 'Recruitment Pipeline'] }, component: () => import('@/views/pages/reports/RecruitmentPipelineReport.vue') },
                { path: '/reports/leave-usage-trends', name: 'report-leave-usage-trends', meta: { breadcrumb: ['Reports', 'Leave Usage Trends'] }, component: () => import('@/views/pages/reports/LeaveUsageTrendsReport.vue') },
                { path: '/reports/leave-department', name: 'report-leave-department', meta: { breadcrumb: ['Reports', 'Leave by Department'] }, component: () => import('@/views/pages/reports/LeaveDepartmentReport.vue') },
                { path: '/reports/employee-leave-history', name: 'report-employee-leave-history', meta: { breadcrumb: ['Reports', 'Employee Leave History'] }, component: () => import('@/views/pages/reports/EmployeeLeaveHistoryReport.vue') },
                { path: '/reports/comp-off-balance', name: 'report-comp-off-balance', meta: { breadcrumb: ['Reports', 'Comp-Off Balance'] }, component: () => import('@/views/pages/reports/CompOffBalanceReport.vue') },
                { path: '/reports/leave-carry-forward', name: 'report-leave-carry-forward', meta: { breadcrumb: ['Reports', 'Leave Carry-Forward'] }, component: () => import('@/views/pages/reports/LeaveCarryForwardReport.vue') },
                { path: '/reports/leave-accrual', name: 'report-leave-accrual', meta: { breadcrumb: ['Reports', 'Leave Accrual'] }, component: () => import('@/views/pages/reports/LeaveAccrualReport.vue') },
                { path: '/reports/sick-leave-analysis', name: 'report-sick-leave-analysis', meta: { breadcrumb: ['Reports', 'Sick Leave Analysis'] }, component: () => import('@/views/pages/reports/SickLeaveAnalysisReport.vue') },
                { path: '/reports/absenteeism-rate', name: 'report-absenteeism-rate', meta: { breadcrumb: ['Reports', 'Absenteeism Rate'] }, component: () => import('@/views/pages/reports/AbsenteeismRateReport.vue') },
                { path: '/reports/holiday-impact', name: 'report-holiday-impact', meta: { breadcrumb: ['Reports', 'Holiday Impact'] }, component: () => import('@/views/pages/reports/HolidayImpactReport.vue') },
                { path: '/reports/parental-leave', name: 'report-parental-leave', meta: { breadcrumb: ['Reports', 'Parental Leave'] }, component: () => import('@/views/pages/reports/ParentalLeaveReport.vue') },
                // ---- Employee Import ----
                { path: '/employees/import', name: 'employee-import', meta: { breadcrumb: ['People', 'Import Employees'] }, component: () => import('@/views/pages/employees/EmployeeImport.vue') }
            ]
        },
        {
            path: '/pages/notfound',
            name: 'notfound',
            component: () => import('@/views/pages/NotFound.vue')
        }
    ]
});

router.beforeEach((to) => {
    const path = to.path;

    // wp-hr-leave routes
    if (!activeModules.leave && (path.startsWith('/leaves') || path.startsWith('/holiday-lists') || path === '/settings/leave')) {
        return '/';
    }

    // wp-hr-payroll routes
    if (!activeModules.payroll && (path.startsWith('/payroll') || path.startsWith('/setup/salary-') || path === '/settings/payroll')) {
        return '/';
    }

    // wp-hr-recruit routes (recruitment, performance, training)
    if (!activeModules.recruit && (path.startsWith('/recruit') || path.startsWith('/recruitment') || path.startsWith('/performance') || path.startsWith('/training'))) {
        return '/';
    }

    // Module-specific report routes
    if (!activeModules.leave && (path.startsWith('/reports/leave-') || path === '/reports/employee-leave-history' || path === '/reports/comp-off-balance' || path === '/reports/sick-leave-analysis' || path === '/reports/absenteeism-rate' || path === '/reports/holiday-impact' || path === '/reports/parental-leave')) {
        return '/';
    }
    if (!activeModules.payroll && path === '/reports/payroll-summary') {
        return '/';
    }
    if (!activeModules.recruit && path === '/reports/recruitment-pipeline') {
        return '/';
    }
});

export default router;
