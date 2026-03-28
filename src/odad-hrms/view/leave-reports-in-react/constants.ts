import { 
  LayoutDashboard, 
  PieChart, 
  BarChart3, 
  Clock, 
  Users, 
  FileText, 
  AlertCircle, 
  TrendingUp, 
  Calendar, 
  History,
  Wallet,
  ArrowRightLeft,
  Baby,
  Coins,
  ShieldAlert,
  Ban
} from 'lucide-react';

export interface LeaveReport {
  id: string;
  title: string;
  description: string;
  icon: any;
  category: 'Summary' | 'Operational' | 'Financial' | 'Analytical';
}

export const LEAVE_REPORTS: LeaveReport[] = [
  {
    id: 'balance-summary',
    title: 'Leave Balance Summary',
    description: 'Current available leave balances for all employees across different leave types.',
    icon: Wallet,
    category: 'Summary'
  },
  {
    id: 'accrual-report',
    title: 'Leave Accrual Report',
    description: 'Tracking how leave is earned and accumulated based on tenure and policy.',
    icon: TrendingUp,
    category: 'Operational'
  },
  {
    id: 'usage-trends',
    title: 'Leave Usage Trends',
    description: 'Monthly and seasonal patterns of leave consumption within the organization.',
    icon: BarChart3,
    category: 'Analytical'
  },
  {
    id: 'dept-distribution',
    title: 'Departmental Distribution',
    description: 'Breakdown of leave utilization across different business units.',
    icon: PieChart,
    category: 'Analytical'
  },
  {
    id: 'pending-requests',
    title: 'Pending Leave Requests',
    description: 'Real-time list of leave applications awaiting manager approval.',
    icon: Clock,
    category: 'Operational'
  },
  {
    id: 'encashment-report',
    title: 'Leave Encashment Report',
    description: 'Financial liability and actual payouts for unused leave balances.',
    icon: FileText,
    category: 'Financial'
  },
  {
    id: 'sick-leave-ratio',
    title: 'Sick Leave Analysis',
    description: 'Comparison of unplanned sick leave versus planned annual leave.',
    icon: AlertCircle,
    category: 'Analytical'
  },
  {
    id: 'absenteeism-rate',
    title: 'Absenteeism Rate',
    description: 'Percentage of scheduled work time lost due to unplanned absences.',
    icon: Users,
    category: 'Analytical'
  },
  {
    id: 'holiday-impact',
    title: 'Holiday Impact Analysis',
    description: 'How public holidays influence surrounding leave requests and capacity.',
    icon: Calendar,
    category: 'Analytical'
  },
  {
    id: 'employee-history',
    title: 'Employee Leave History',
    description: 'Detailed chronological record of leave for individual employees.',
    icon: History,
    category: 'Operational'
  },
  {
    id: 'carry-forward',
    title: 'Leave Carry-Forward',
    description: 'Tracking unused leave balances being moved to the next financial year.',
    icon: ArrowRightLeft,
    category: 'Financial'
  },
  {
    id: 'parental-leave',
    title: 'Parental Leave Tracking',
    description: 'Monitoring maternity, paternity, and adoption leave durations and return dates.',
    icon: Baby,
    category: 'Operational'
  },
  {
    id: 'comp-off',
    title: 'Comp-Off Balance',
    description: 'Tracking compensatory time earned by employees for working overtime or holidays.',
    icon: Coins,
    category: 'Operational'
  },
  {
    id: 'unpaid-leave',
    title: 'Unpaid Leave (LOP)',
    description: 'Summary of Loss of Pay instances and their impact on payroll.',
    icon: Ban,
    category: 'Financial'
  },
  {
    id: 'forfeiture-forecast',
    title: 'Forfeiture Forecast',
    description: 'Predicting leave days that will be lost if not utilized before the policy deadline.',
    icon: ShieldAlert,
    category: 'Analytical'
  }
];

export const MOCK_DATA = {
  usageTrends: [
    { month: 'Jan', annual: 45, sick: 12, other: 5 },
    { month: 'Feb', annual: 38, sick: 15, other: 3 },
    { month: 'Mar', annual: 42, sick: 10, other: 4 },
    { month: 'Apr', annual: 55, sick: 8, other: 6 },
    { month: 'May', annual: 60, sick: 7, other: 8 },
    { month: 'Jun', annual: 75, sick: 5, other: 10 },
    { month: 'Jul', annual: 85, sick: 4, other: 12 },
    { month: 'Aug', annual: 80, sick: 6, other: 11 },
    { month: 'Sep', annual: 50, sick: 9, other: 7 },
    { month: 'Oct', annual: 48, sick: 14, other: 5 },
    { month: 'Nov', annual: 40, sick: 18, other: 4 },
    { month: 'Dec', annual: 95, sick: 10, other: 15 },
  ],
  deptDistribution: [
    { name: 'Engineering', value: 35 },
    { name: 'Sales', value: 25 },
    { name: 'Marketing', value: 15 },
    { name: 'HR', value: 10 },
    { name: 'Finance', value: 15 },
  ],
  balances: [
    { name: 'John Doe', annual: 15, sick: 8, personal: 3 },
    { name: 'Jane Smith', annual: 12, sick: 10, personal: 2 },
    { name: 'Mike Johnson', annual: 20, sick: 5, personal: 4 },
    { name: 'Sarah Williams', annual: 8, sick: 12, personal: 1 },
    { name: 'Robert Brown', annual: 18, sick: 7, personal: 5 },
  ],
  pending: [
    { id: 1, name: 'Alice Cooper', type: 'Annual', start: '2024-04-10', end: '2024-04-15', days: 5 },
    { id: 2, name: 'Bob Marley', type: 'Sick', start: '2024-03-15', end: '2024-03-16', days: 1 },
    { id: 3, name: 'Charlie Sheen', type: 'Personal', start: '2024-03-20', end: '2024-03-20', days: 1 },
  ]
};
