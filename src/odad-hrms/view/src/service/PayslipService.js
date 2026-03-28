import { request } from '@/api/client';

export const PayslipService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`payslips?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load payslips');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`payslips/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load payslip');
        return data;
    },
    async getMy(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`payslips/my?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load my payslips');
        return data;
    },
    async sendEmail(id) {
        const { data, error } = await request(`payslips/${id}/email`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to send payslip email');
        return data;
    },
};
