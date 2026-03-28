import React, { useState } from 'react';
import { 
  LayoutDashboard, 
  Menu, 
  X, 
  Search,
  Bell,
  User,
  ChevronRight,
  Download,
  Filter,
  RefreshCw,
  AlertCircle,
  Calendar,
  Coins
} from 'lucide-react';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  Legend, 
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart as RePieChart,
  Pie,
  Cell,
  AreaChart,
  Area
} from 'recharts';
import { motion, AnimatePresence } from 'motion/react';
import { LEAVE_REPORTS, MOCK_DATA, LeaveReport } from './constants';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const COLORS = ['#141414', '#4a4a4a', '#8e8e8e', '#d1d1d1', '#e4e3e0'];

export default function App() {
  const [activeReport, setActiveReport] = useState<string>('dashboard');
  const [isSidebarOpen, setIsSidebarOpen] = useState(true);

  const renderReportContent = () => {
    switch (activeReport) {
      case 'dashboard':
        return <DashboardOverview onSelectReport={setActiveReport} />;
      case 'usage-trends':
        return <UsageTrendsReport />;
      case 'dept-distribution':
        return <DeptDistributionReport />;
      case 'balance-summary':
        return <BalanceSummaryReport />;
      case 'pending-requests':
        return <PendingRequestsReport />;
      case 'accrual-report':
        return <AccrualReport />;
      case 'encashment-report':
        return <EncashmentReport />;
      case 'sick-leave-ratio':
        return <SickLeaveAnalysisReport />;
      case 'absenteeism-rate':
        return <AbsenteeismRateReport />;
      case 'holiday-impact':
        return <HolidayImpactReport />;
      case 'employee-history':
        return <EmployeeHistoryReport />;
      case 'carry-forward':
        return <CarryForwardReport />;
      case 'parental-leave':
        return <ParentalLeaveReport />;
      case 'comp-off':
        return <CompOffReport />;
      case 'unpaid-leave':
        return <UnpaidLeaveReport />;
      case 'forfeiture-forecast':
        return <ForfeitureForecastReport />;
      default:
        return (
          <div className="flex flex-col items-center justify-center h-full text-zinc-500">
            <LayoutDashboard className="w-16 h-16 mb-4 opacity-20" />
            <h2 className="text-xl font-medium">Report Under Development</h2>
            <p>This specific report module is being populated with real-time data.</p>
            <button 
              onClick={() => setActiveReport('dashboard')}
              className="mt-6 px-4 py-2 bg-zinc-900 text-white rounded-md hover:bg-zinc-800 transition-colors"
            >
              Back to Dashboard
            </button>
          </div>
        );
    }
  };

  return (
    <div className="flex h-screen bg-[#E4E3E0] font-sans text-[#141414]">
      {/* Sidebar */}
      <AnimatePresence mode="wait">
        {isSidebarOpen && (
          <motion.aside
            initial={{ x: -280 }}
            animate={{ x: 0 }}
            exit={{ x: -280 }}
            className="w-72 bg-white border-r border-[#141414] flex flex-col z-50"
          >
            <div className="p-6 border-b border-[#141414] flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 bg-[#141414] rounded flex items-center justify-center">
                  <LayoutDashboard className="text-white w-5 h-5" />
                </div>
                <span className="font-bold tracking-tight text-lg">LEAVE.OS</span>
              </div>
              <button onClick={() => setIsSidebarOpen(false)} className="lg:hidden">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
              <button
                onClick={() => setActiveReport('dashboard')}
                className={cn(
                  "w-full flex items-center gap-3 px-3 py-2 rounded-md transition-all text-sm font-medium",
                  activeReport === 'dashboard' ? "bg-[#141414] text-white" : "hover:bg-zinc-100"
                )}
              >
                <LayoutDashboard className="w-4 h-4" />
                Overview Dashboard
              </button>
              
              <div className="pt-4 pb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                Reports Catalogue
              </div>

              {LEAVE_REPORTS.map((report) => (
                <button
                  key={report.id}
                  onClick={() => setActiveReport(report.id)}
                  className={cn(
                    "w-full flex items-center gap-3 px-3 py-2 rounded-md transition-all text-sm",
                    activeReport === report.id ? "bg-[#141414] text-white" : "hover:bg-zinc-100 text-zinc-600"
                  )}
                >
                  <report.icon className="w-4 h-4 shrink-0" />
                  <span className="truncate">{report.title}</span>
                </button>
              ))}
            </div>

            <div className="p-4 border-t border-[#141414] bg-zinc-50">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-zinc-200 flex items-center justify-center border border-[#141414]">
                  <User className="w-5 h-5" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-bold truncate">HR Admin</p>
                  <p className="text-xs text-zinc-500 truncate">tepafril1992@gmail.com</p>
                </div>
              </div>
            </div>
          </motion.aside>
        )}
      </AnimatePresence>

      {/* Main Content */}
      <main className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <header className="h-16 bg-white border-b border-[#141414] flex items-center justify-between px-6 shrink-0">
          <div className="flex items-center gap-4">
            {!isSidebarOpen && (
              <button onClick={() => setIsSidebarOpen(true)} className="p-2 hover:bg-zinc-100 rounded-md">
                <Menu className="w-5 h-5" />
              </button>
            )}
            <div className="relative hidden md:block">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400" />
              <input 
                type="text" 
                placeholder="Search reports or employees..." 
                className="pl-10 pr-4 py-1.5 bg-zinc-100 border-none rounded-md text-sm w-64 focus:ring-1 focus:ring-[#141414] transition-all"
              />
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button className="p-2 hover:bg-zinc-100 rounded-md relative">
              <Bell className="w-5 h-5" />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
            </button>
            <div className="h-6 w-[1px] bg-zinc-200 mx-2"></div>
            <button className="flex items-center gap-2 px-3 py-1.5 bg-[#141414] text-white rounded-md text-sm font-medium hover:bg-zinc-800 transition-colors">
              <Download className="w-4 h-4" />
              Export
            </button>
          </div>
        </header>

        {/* Scrollable Area */}
        <div className="flex-1 overflow-y-auto p-6 lg:p-10">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeReport}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="max-w-7xl mx-auto"
            >
              {renderReportContent()}
            </motion.div>
          </AnimatePresence>
        </div>
      </main>
    </div>
  );
}

function DashboardOverview({ onSelectReport }: { onSelectReport: (id: string) => void }) {
  return (
    <div className="space-y-8">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 className="text-4xl font-bold tracking-tight">Leave Analytics</h1>
          <p className="text-zinc-500 mt-1 italic font-serif">Comprehensive overview of organizational leave health.</p>
        </div>
        <div className="flex gap-2">
          <button className="px-3 py-1.5 border border-[#141414] rounded-md text-xs font-bold uppercase tracking-wider flex items-center gap-2 hover:bg-white transition-colors">
            <Filter className="w-3 h-3" /> Filter
          </button>
          <button className="px-3 py-1.5 border border-[#141414] rounded-md text-xs font-bold uppercase tracking-wider flex items-center gap-2 hover:bg-white transition-colors">
            <RefreshCw className="w-3 h-3" /> Refresh
          </button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {[
          { label: 'Total Leave Days', value: '1,284', trend: '+12%', color: 'bg-white' },
          { label: 'Pending Approvals', value: '24', trend: '-5%', color: 'bg-white' },
          { label: 'Avg. Absence Rate', value: '4.2%', trend: '+0.4%', color: 'bg-white' },
          { label: 'Est. Liability', value: '$42.5k', trend: '+8%', color: 'bg-white' },
        ].map((stat, i) => (
          <div key={i} className={cn("p-6 border border-[#141414] shadow-[4px_4px_0px_0px_rgba(20,20,20,1)]", stat.color)}>
            <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">{stat.label}</p>
            <div className="flex items-end justify-between">
              <h3 className="text-3xl font-bold">{stat.value}</h3>
              <span className={cn("text-xs font-bold", stat.trend.startsWith('+') ? "text-emerald-600" : "text-rose-600")}>
                {stat.trend}
              </span>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Main Chart */}
        <div className="lg:col-span-2 bg-white border border-[#141414] p-6">
          <div className="flex items-center justify-between mb-6">
            <h3 className="font-bold text-lg">Leave Consumption Trends</h3>
            <select className="text-xs border-none bg-zinc-100 rounded px-2 py-1">
              <option>Last 12 Months</option>
              <option>Year to Date</option>
            </select>
          </div>
          <div className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={MOCK_DATA.usageTrends}>
                <defs>
                  <linearGradient id="colorAnnual" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#141414" stopOpacity={0.1}/>
                    <stop offset="95%" stopColor="#141414" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{fontSize: 12}} />
                <YAxis axisLine={false} tickLine={false} tick={{fontSize: 12}} />
                <Tooltip 
                  contentStyle={{ backgroundColor: '#141414', border: 'none', borderRadius: '4px', color: '#fff' }}
                  itemStyle={{ color: '#fff' }}
                />
                <Area type="monotone" dataKey="annual" stroke="#141414" fillOpacity={1} fill="url(#colorAnnual)" strokeWidth={2} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Distribution Chart */}
        <div className="bg-white border border-[#141414] p-6">
          <h3 className="font-bold text-lg mb-6">By Department</h3>
          <div className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
              <RePieChart>
                <Pie
                  data={MOCK_DATA.deptDistribution}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={80}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {MOCK_DATA.deptDistribution.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend verticalAlign="bottom" height={36}/>
              </RePieChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Reports Grid */}
      <div>
        <h3 className="font-bold text-xl mb-6">Available Reports</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {LEAVE_REPORTS.map((report) => (
            <button
              key={report.id}
              onClick={() => onSelectReport(report.id)}
              className="group p-5 bg-white border border-[#141414] text-left hover:bg-[#141414] hover:text-white transition-all duration-300 flex flex-col justify-between h-48"
            >
              <div>
                <div className="w-10 h-10 border border-[#141414] group-hover:border-white flex items-center justify-center mb-4 transition-colors">
                  <report.icon className="w-5 h-5" />
                </div>
                <h4 className="font-bold text-lg mb-1">{report.title}</h4>
                <p className="text-xs text-zinc-500 group-hover:text-zinc-300 line-clamp-2">{report.description}</p>
              </div>
              <div className="flex items-center justify-between mt-4">
                <span className="text-[10px] font-bold uppercase tracking-widest opacity-50">{report.category}</span>
                <ChevronRight className="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" />
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

function UsageTrendsReport() {
  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h2 className="text-3xl font-bold">Leave Usage Trends</h2>
        <div className="flex gap-2">
          <button className="px-4 py-2 bg-white border border-[#141414] text-sm font-bold">PDF Report</button>
          <button className="px-4 py-2 bg-[#141414] text-white text-sm font-bold">Export CSV</button>
        </div>
      </div>

      <div className="bg-white border border-[#141414] p-8">
        <div className="h-[400px]">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={MOCK_DATA.usageTrends}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="month" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Bar dataKey="annual" name="Annual Leave" fill="#141414" />
              <Bar dataKey="sick" name="Sick Leave" fill="#8e8e8e" />
              <Bar dataKey="other" name="Other" fill="#d1d1d1" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="bg-white border border-[#141414] overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-zinc-50 border-b border-[#141414]">
            <tr>
              <th className="px-6 py-4 font-bold italic font-serif">Month</th>
              <th className="px-6 py-4 font-bold italic font-serif">Annual Leave</th>
              <th className="px-6 py-4 font-bold italic font-serif">Sick Leave</th>
              <th className="px-6 py-4 font-bold italic font-serif">Other</th>
              <th className="px-6 py-4 font-bold italic font-serif">Total</th>
            </tr>
          </thead>
          <tbody>
            {MOCK_DATA.usageTrends.map((row, i) => (
              <tr key={i} className="border-b border-zinc-100 hover:bg-zinc-50 transition-colors">
                <td className="px-6 py-4 font-medium">{row.month}</td>
                <td className="px-6 py-4">{row.annual} days</td>
                <td className="px-6 py-4">{row.sick} days</td>
                <td className="px-6 py-4">{row.other} days</td>
                <td className="px-6 py-4 font-bold">{row.annual + row.sick + row.other} days</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function DeptDistributionReport() {
  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Departmental Distribution</h2>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="bg-white border border-[#141414] p-8 flex items-center justify-center">
          <div className="h-[400px] w-full">
            <ResponsiveContainer width="100%" height="100%">
              <RePieChart>
                <Pie
                  data={MOCK_DATA.deptDistribution}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                  outerRadius={120}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {MOCK_DATA.deptDistribution.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </RePieChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div className="space-y-4">
          <div className="bg-white border border-[#141414] p-6">
            <h4 className="font-bold mb-4 uppercase text-xs tracking-widest text-zinc-400">Key Insights</h4>
            <ul className="space-y-4">
              <li className="flex gap-3">
                <div className="w-1 h-auto bg-[#141414]"></div>
                <p className="text-sm">Engineering department shows the highest leave utilization at 35% of total organization leave.</p>
              </li>
              <li className="flex gap-3">
                <div className="w-1 h-auto bg-zinc-400"></div>
                <p className="text-sm">HR department maintains the lowest leave utilization, potentially indicating high workload or staffing constraints.</p>
              </li>
              <li className="flex gap-3">
                <div className="w-1 h-auto bg-zinc-200"></div>
                <p className="text-sm">Sales leave patterns correlate strongly with quarterly targets, peaking in the first month of each quarter.</p>
              </li>
            </ul>
          </div>
          <div className="bg-[#141414] text-white p-6">
            <h4 className="font-bold mb-2">Capacity Warning</h4>
            <p className="text-sm text-zinc-400">Engineering is currently at 85% capacity for the upcoming holiday season. Consider restricting new requests.</p>
          </div>
        </div>
      </div>
    </div>
  );
}

function BalanceSummaryReport() {
  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Leave Balance Summary</h2>
      <div className="bg-white border border-[#141414] overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-zinc-50 border-b border-[#141414]">
            <tr>
              <th className="px-6 py-4 font-bold italic font-serif">Employee</th>
              <th className="px-6 py-4 font-bold italic font-serif">Annual Balance</th>
              <th className="px-6 py-4 font-bold italic font-serif">Sick Balance</th>
              <th className="px-6 py-4 font-bold italic font-serif">Personal Balance</th>
              <th className="px-6 py-4 font-bold italic font-serif">Total Available</th>
            </tr>
          </thead>
          <tbody>
            {MOCK_DATA.balances.map((row, i) => (
              <tr key={i} className="border-b border-zinc-100 hover:bg-zinc-50 transition-colors cursor-pointer group">
                <td className="px-6 py-4">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full bg-zinc-100 border border-zinc-200 flex items-center justify-center text-[10px] font-bold">
                      {row.name.split(' ').map(n => n[0]).join('')}
                    </div>
                    <span className="font-medium group-hover:underline">{row.name}</span>
                  </div>
                </td>
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2">
                    <div className="w-24 h-2 bg-zinc-100 rounded-full overflow-hidden">
                      <div className="h-full bg-[#141414]" style={{ width: `${(row.annual / 25) * 100}%` }}></div>
                    </div>
                    <span>{row.annual}d</span>
                  </div>
                </td>
                <td className="px-6 py-4">{row.sick}d</td>
                <td className="px-6 py-4">{row.personal}d</td>
                <td className="px-6 py-4 font-bold">{row.annual + row.sick + row.personal}d</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function PendingRequestsReport() {
  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h2 className="text-3xl font-bold">Pending Leave Requests</h2>
        <span className="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-bold border border-amber-200">
          {MOCK_DATA.pending.length} Action Required
        </span>
      </div>
      
      <div className="grid grid-cols-1 gap-4">
        {MOCK_DATA.pending.map((req) => (
          <div key={req.id} className="bg-white border border-[#141414] p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:shadow-[4px_4px_0px_0px_rgba(20,20,20,1)] transition-all">
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 bg-zinc-100 border border-[#141414] flex items-center justify-center font-bold">
                {req.name.split(' ').map(n => n[0]).join('')}
              </div>
              <div>
                <h4 className="font-bold text-lg">{req.name}</h4>
                <p className="text-xs text-zinc-500">{req.type} Leave • {req.days} days</p>
              </div>
            </div>
            
            <div className="flex flex-col md:items-center">
              <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400">Duration</p>
              <p className="text-sm font-medium">{req.start} to {req.end}</p>
            </div>

            <div className="flex gap-2">
              <button className="px-4 py-2 border border-[#141414] text-sm font-bold hover:bg-rose-50 hover:text-rose-600 transition-colors">Reject</button>
              <button className="px-4 py-2 bg-[#141414] text-white text-sm font-bold hover:bg-zinc-800 transition-colors">Approve Request</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function AccrualReport() {
  const accrualData = [
    { month: 'Jan', accrued: 2.5, used: 0, balance: 2.5 },
    { month: 'Feb', accrued: 2.5, used: 1, balance: 4.0 },
    { month: 'Mar', accrued: 2.5, used: 0, balance: 6.5 },
    { month: 'Apr', accrued: 2.5, used: 2, balance: 7.0 },
    { month: 'May', accrued: 2.5, used: 0, balance: 9.5 },
    { month: 'Jun', accrued: 2.5, used: 5, balance: 7.0 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Leave Accrual Report</h2>
      <div className="bg-white border border-[#141414] p-8">
        <div className="h-[300px]">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={accrualData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="month" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Line type="stepAfter" dataKey="balance" name="Running Balance" stroke="#141414" strokeWidth={3} dot={{r: 6}} />
              <Line type="monotone" dataKey="accrued" name="Monthly Accrual" stroke="#8e8e8e" strokeDasharray="5 5" />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>
      <div className="bg-white border border-[#141414] p-6">
        <p className="text-sm text-zinc-600">This report tracks the accumulation of leave days over time based on the standard accrual rate of 2.5 days per month. The "Running Balance" accounts for both accruals and consumption.</p>
      </div>
    </div>
  );
}

function EncashmentReport() {
  const encashmentData = [
    { dept: 'Engineering', liability: 15400, cashed: 2100 },
    { dept: 'Sales', liability: 8200, cashed: 4500 },
    { dept: 'Marketing', liability: 4100, cashed: 500 },
    { dept: 'HR', liability: 3200, cashed: 0 },
    { dept: 'Finance', liability: 5600, cashed: 1200 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Leave Encashment Report</h2>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="p-6 bg-[#141414] text-white border border-[#141414]">
          <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">Total Liability</p>
          <h3 className="text-3xl font-bold">$36,500</h3>
        </div>
        <div className="p-6 bg-white border border-[#141414]">
          <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">Processed Payouts</p>
          <h3 className="text-3xl font-bold">$8,300</h3>
        </div>
        <div className="p-6 bg-white border border-[#141414]">
          <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-1">Pending Requests</p>
          <h3 className="text-3xl font-bold">12</h3>
        </div>
      </div>
      <div className="bg-white border border-[#141414] overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-zinc-50 border-b border-[#141414]">
            <tr>
              <th className="px-6 py-4 font-bold">Department</th>
              <th className="px-6 py-4 font-bold">Accrued Liability</th>
              <th className="px-6 py-4 font-bold">Encashment Paid (YTD)</th>
              <th className="px-6 py-4 font-bold">Remaining Exposure</th>
            </tr>
          </thead>
          <tbody>
            {encashmentData.map((row, i) => (
              <tr key={i} className="border-b border-zinc-100">
                <td className="px-6 py-4 font-medium">{row.dept}</td>
                <td className="px-6 py-4">${row.liability.toLocaleString()}</td>
                <td className="px-6 py-4">${row.cashed.toLocaleString()}</td>
                <td className="px-6 py-4 font-bold">${(row.liability - row.cashed).toLocaleString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function SickLeaveAnalysisReport() {
  const sickData = [
    { name: 'Week 1', planned: 40, sick: 5 },
    { name: 'Week 2', planned: 35, sick: 12 },
    { name: 'Week 3', planned: 45, sick: 8 },
    { name: 'Week 4', planned: 50, sick: 15 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Sick Leave Analysis</h2>
      <div className="bg-white border border-[#141414] p-8">
        <div className="h-[350px]">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={sickData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Bar dataKey="planned" name="Planned Leave" fill="#d1d1d1" stackId="a" />
              <Bar dataKey="sick" name="Unplanned Sick Leave" fill="#141414" stackId="a" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="p-6 border border-[#141414] bg-white">
          <h4 className="font-bold mb-2">Bradford Factor Alert</h4>
          <p className="text-sm text-zinc-600">3 employees have exceeded a Bradford Factor of 250, indicating high frequency of short-term absences.</p>
        </div>
        <div className="p-6 border border-[#141414] bg-white">
          <h4 className="font-bold mb-2">Sick Leave Ratio</h4>
          <p className="text-sm text-zinc-600">Unplanned leave accounts for 18% of total absences this month, up from 12% last month.</p>
        </div>
      </div>
    </div>
  );
}

function AbsenteeismRateReport() {
  const rateData = [
    { day: 'Mon', rate: 2.1 },
    { day: 'Tue', rate: 1.8 },
    { day: 'Wed', rate: 1.5 },
    { day: 'Thu', rate: 2.4 },
    { day: 'Fri', rate: 4.8 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Absenteeism Rate</h2>
      <div className="bg-white border border-[#141414] p-8">
        <div className="h-[300px]">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={rateData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="day" />
              <YAxis unit="%" />
              <Tooltip />
              <Area type="monotone" dataKey="rate" name="Absence Rate" stroke="#141414" fill="#141414" fillOpacity={0.1} />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>
      <div className="bg-amber-50 border border-amber-200 p-6 text-amber-900">
        <h4 className="font-bold flex items-center gap-2">
          <AlertCircle className="w-4 h-4" /> Friday Spike Detected
        </h4>
        <p className="text-sm mt-1">Absenteeism rate significantly increases on Fridays (4.8%). This pattern suggests potential "weekend extension" behavior.</p>
      </div>
    </div>
  );
}

function HolidayImpactReport() {
  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Holiday Impact Analysis</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="bg-white border border-[#141414] p-6">
          <h3 className="font-bold mb-4">Upcoming Public Holidays</h3>
          <div className="space-y-4">
            {[
              { name: 'Good Friday', date: 'Mar 29', impact: 'High', requests: 45 },
              { name: 'Easter Monday', date: 'Apr 01', impact: 'High', requests: 38 },
              { name: 'Eid al-Fitr', date: 'Apr 10', impact: 'Critical', requests: 62 },
            ].map((h, i) => (
              <div key={i} className="flex items-center justify-between p-3 border border-zinc-100 rounded">
                <div>
                  <p className="font-bold">{h.name}</p>
                  <p className="text-xs text-zinc-500">{h.date}</p>
                </div>
                <div className="text-right">
                  <p className={cn("text-xs font-bold px-2 py-0.5 rounded", 
                    h.impact === 'Critical' ? "bg-red-100 text-red-700" : "bg-amber-100 text-amber-700"
                  )}>{h.impact} Impact</p>
                  <p className="text-xs text-zinc-500 mt-1">{h.requests} requests</p>
                </div>
              </div>
            ))}
          </div>
        </div>
        <div className="bg-white border border-[#141414] p-6 flex flex-col justify-center items-center text-center">
          <Calendar className="w-16 h-16 text-zinc-200 mb-4" />
          <h3 className="font-bold text-xl mb-2">Capacity Forecast</h3>
          <p className="text-sm text-zinc-500 px-8">During the week of April 8-12, organization capacity is projected to drop to 45% due to overlapping holiday requests.</p>
          <button className="mt-6 px-4 py-2 bg-[#141414] text-white text-sm font-bold">View Team Calendar</button>
        </div>
      </div>
    </div>
  );
}

function EmployeeHistoryReport() {
  const history = [
    { date: '2024-03-01', type: 'Annual', days: 3, status: 'Approved', reason: 'Family vacation' },
    { date: '2024-02-15', type: 'Sick', days: 1, status: 'Approved', reason: 'Flu' },
    { date: '2024-01-10', type: 'Annual', days: 5, status: 'Approved', reason: 'New Year trip' },
    { date: '2023-12-24', type: 'Public Holiday', days: 1, status: 'System', reason: 'Christmas' },
  ];

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h2 className="text-3xl font-bold">Employee Leave History</h2>
        <div className="flex items-center gap-4">
          <span className="text-sm text-zinc-500 italic">Viewing history for:</span>
          <select className="border border-[#141414] px-3 py-1.5 bg-white text-sm font-bold">
            <option>John Doe</option>
            <option>Jane Smith</option>
            <option>Mike Johnson</option>
          </select>
        </div>
      </div>

      <div className="bg-white border border-[#141414] overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-zinc-50 border-b border-[#141414]">
            <tr>
              <th className="px-6 py-4 font-bold">Date</th>
              <th className="px-6 py-4 font-bold">Type</th>
              <th className="px-6 py-4 font-bold">Duration</th>
              <th className="px-6 py-4 font-bold">Status</th>
              <th className="px-6 py-4 font-bold">Reason</th>
            </tr>
          </thead>
          <tbody>
            {history.map((row, i) => (
              <tr key={i} className="border-b border-zinc-100">
                <td className="px-6 py-4 font-medium">{row.date}</td>
                <td className="px-6 py-4">{row.type}</td>
                <td className="px-6 py-4">{row.days} days</td>
                <td className="px-6 py-4">
                  <span className={cn("px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider",
                    row.status === 'Approved' ? "bg-emerald-100 text-emerald-700" : "bg-zinc-100 text-zinc-700"
                  )}>{row.status}</span>
                </td>
                <td className="px-6 py-4 text-zinc-500">{row.reason}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CarryForwardReport() {
  const carryData = [
    { year: '2021', carried: 5, used: 5, expired: 0 },
    { year: '2022', carried: 8, used: 6, expired: 2 },
    { year: '2023', carried: 12, used: 10, expired: 2 },
    { year: '2024 (Est)', carried: 15, used: 0, expired: 0 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Leave Carry-Forward</h2>
      <div className="bg-white border border-[#141414] p-8">
        <div className="h-[300px]">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={carryData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="year" />
              <YAxis />
              <Tooltip />
              <Legend />
              <Bar dataKey="carried" name="Carried Forward" fill="#141414" />
              <Bar dataKey="used" name="Used from Carry-over" fill="#8e8e8e" />
              <Bar dataKey="expired" name="Expired/Forfeited" fill="#d1d1d1" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
      <div className="bg-white border border-[#141414] p-6">
        <h4 className="font-bold mb-2">Policy Note</h4>
        <p className="text-sm text-zinc-600">Maximum carry-forward allowed is 15 days. Any balance exceeding this limit at the end of the financial year will be automatically forfeited.</p>
      </div>
    </div>
  );
}

function ParentalLeaveReport() {
  const parentalData = [
    { name: 'Maternity', active: 12, upcoming: 4, returned: 25 },
    { name: 'Paternity', active: 5, upcoming: 2, returned: 18 },
    { name: 'Adoption', active: 1, upcoming: 0, returned: 3 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Parental Leave Tracking</h2>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {parentalData.map((item, i) => (
          <div key={i} className="bg-white border border-[#141414] p-6">
            <h3 className="font-bold text-lg mb-4">{item.name}</h3>
            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-zinc-500">Currently Active</span>
                <span className="font-bold">{item.active}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-zinc-500">Upcoming (Next 30d)</span>
                <span className="font-bold">{item.upcoming}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-zinc-500">Returned (YTD)</span>
                <span className="font-bold">{item.returned}</span>
              </div>
            </div>
          </div>
        ))}
      </div>
      <div className="bg-white border border-[#141414] p-6">
        <h4 className="font-bold mb-4">Upcoming Returns</h4>
        <div className="space-y-3">
          {[
            { name: 'Emma Watson', type: 'Maternity', returnDate: '2024-04-15', dept: 'Engineering' },
            { name: 'David Beckham', type: 'Paternity', returnDate: '2024-03-25', dept: 'Sales' },
          ].map((person, i) => (
            <div key={i} className="flex items-center justify-between py-2 border-b border-zinc-100 last:border-0">
              <div>
                <p className="font-bold text-sm">{person.name}</p>
                <p className="text-xs text-zinc-500">{person.type} • {person.dept}</p>
              </div>
              <div className="text-right">
                <p className="text-xs font-bold">Return Date</p>
                <p className="text-xs text-zinc-500">{person.returnDate}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function CompOffReport() {
  const compOffData = [
    { name: 'John Doe', earned: 5, used: 2, balance: 3 },
    { name: 'Jane Smith', earned: 3, used: 3, balance: 0 },
    { name: 'Mike Johnson', earned: 8, used: 4, balance: 4 },
    { name: 'Sarah Williams', earned: 2, used: 0, balance: 2 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Comp-Off Balance</h2>
      <div className="bg-white border border-[#141414] overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="bg-zinc-50 border-b border-[#141414]">
            <tr>
              <th className="px-6 py-4 font-bold">Employee</th>
              <th className="px-6 py-4 font-bold">Days Earned</th>
              <th className="px-6 py-4 font-bold">Days Used</th>
              <th className="px-6 py-4 font-bold">Current Balance</th>
              <th className="px-6 py-4 font-bold">Expiry (Next)</th>
            </tr>
          </thead>
          <tbody>
            {compOffData.map((row, i) => (
              <tr key={i} className="border-b border-zinc-100">
                <td className="px-6 py-4 font-medium">{row.name}</td>
                <td className="px-6 py-4">{row.earned}d</td>
                <td className="px-6 py-4">{row.used}d</td>
                <td className="px-6 py-4 font-bold">{row.balance}d</td>
                <td className="px-6 py-4 text-rose-600 text-xs font-bold">2024-04-30</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="bg-zinc-900 text-white p-6">
        <h4 className="font-bold mb-2 flex items-center gap-2">
          <Coins className="w-4 h-4" /> Comp-Off Policy
        </h4>
        <p className="text-sm text-zinc-400">Compensatory leave must be utilized within 60 days of earning. Unused comp-off will expire automatically and cannot be encashed.</p>
      </div>
    </div>
  );
}

function UnpaidLeaveReport() {
  const lopData = [
    { month: 'Jan', days: 15, cost: 4500 },
    { month: 'Feb', days: 22, cost: 6600 },
    { month: 'Mar', days: 10, cost: 3000 },
  ];

  return (
    <div className="space-y-8">
      <h2 className="text-3xl font-bold">Unpaid Leave (LOP) Summary</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="bg-white border border-[#141414] p-8">
          <h3 className="font-bold mb-6">LOP Days Trend</h3>
          <div className="h-[250px]">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={lopData}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Bar dataKey="days" name="LOP Days" fill="#141414" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div className="bg-white border border-[#141414] p-8">
          <h3 className="font-bold mb-6">Payroll Impact (Est)</h3>
          <div className="h-[250px]">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={lopData}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="month" />
                <YAxis />
                <Tooltip />
                <Line type="monotone" dataKey="cost" name="Savings ($)" stroke="#141414" strokeWidth={2} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
      <div className="bg-white border border-[#141414] p-6">
        <h4 className="font-bold mb-2">Top LOP Reasons</h4>
        <div className="flex flex-wrap gap-2 mt-4">
          {['Personal Emergency', 'Extended Vacation', 'Medical (No Balance)', 'Sabbatical'].map((reason, i) => (
            <span key={i} className="px-3 py-1 bg-zinc-100 border border-zinc-200 rounded-full text-xs font-medium">
              {reason}
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}

function ForfeitureForecastReport() {
  const forecastData = [
    { dept: 'Engineering', atRisk: 145, employees: 12 },
    { dept: 'Sales', atRisk: 85, employees: 8 },
    { dept: 'Marketing', atRisk: 42, employees: 5 },
    { dept: 'HR', atRisk: 15, employees: 2 },
    { dept: 'Finance', atRisk: 30, employees: 4 },
  ];

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <h2 className="text-3xl font-bold">Leave Forfeiture Forecast</h2>
        <div className="px-4 py-2 bg-rose-100 text-rose-700 border border-rose-200 rounded font-bold text-sm">
          317 Days at Risk of Forfeiture
        </div>
      </div>

      <div className="bg-white border border-[#141414] p-8">
        <h3 className="font-bold mb-6">Days at Risk by Department</h3>
        <div className="h-[350px]">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={forecastData} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" horizontal={false} />
              <XAxis type="number" />
              <YAxis dataKey="dept" type="category" width={100} />
              <Tooltip />
              <Bar dataKey="atRisk" name="Days at Risk" fill="#141414" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="bg-white border border-[#141414] p-6">
        <h4 className="font-bold mb-4">Urgent Action Required</h4>
        <p className="text-sm text-zinc-600 mb-4">The following employees have {'>'}10 days at risk of forfeiture by March 31st. Managers should be encouraged to approve leave for these individuals.</p>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {[
            { name: 'John Doe', risk: 12, dept: 'Engineering' },
            { name: 'Sarah Connor', risk: 15, dept: 'Sales' },
            { name: 'Mike Tyson', risk: 11, dept: 'Engineering' },
          ].map((person, i) => (
            <div key={i} className="p-4 border border-zinc-100 rounded bg-zinc-50 flex justify-between items-center">
              <div>
                <p className="font-bold text-sm">{person.name}</p>
                <p className="text-xs text-zinc-500">{person.dept}</p>
              </div>
              <div className="text-right">
                <p className="text-lg font-bold text-rose-600">{person.risk}d</p>
                <p className="text-[10px] uppercase font-bold text-zinc-400">At Risk</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
