import { request } from '@/api/client';

export const AdditionalSalaryService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`additional-salary?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load additional salary records');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`additional-salary/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load additional salary record');
        return data;
    },
    async create(body) {
        const { data, error } = await request('additional-salary', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create additional salary record');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`additional-salary/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update additional salary record');
        return data;
    },
    async approve(id) {
        const { data, error } = await request(`additional-salary/${id}/approve`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to approve additional salary record');
        return data;
    },
    async reject(id) {
        const { data, error } = await request(`additional-salary/${id}/reject`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to reject additional salary record');
        return data;
    },
};
