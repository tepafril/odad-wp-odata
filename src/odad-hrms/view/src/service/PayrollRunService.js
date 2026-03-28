import { request } from '@/api/client';

export const PayrollRunService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`payroll-runs?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load payroll runs');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`payroll-runs/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load payroll run');
        return data;
    },
    async create(body) {
        const { data, error } = await request('payroll-runs', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create payroll run');
        return data;
    },
    async calculate(id) {
        const { data, error } = await request(`payroll-runs/${id}/calculate`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to calculate payroll');
        return data;
    },
    async confirm(id) {
        const { data, error } = await request(`payroll-runs/${id}/confirm`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to confirm payroll run');
        return data;
    },
    async markPaid(id) {
        const { data, error } = await request(`payroll-runs/${id}/mark-paid`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to mark payroll run as paid');
        return data;
    },
};
