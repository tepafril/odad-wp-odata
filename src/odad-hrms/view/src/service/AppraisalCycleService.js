import { request } from '@/api/client';

export const AppraisalCycleService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`appraisal-cycles?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load appraisal cycles');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`appraisal-cycles/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load appraisal cycle');
        return data;
    },
    async create(body) {
        const { data, error } = await request('appraisal-cycles', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create appraisal cycle');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`appraisal-cycles/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update appraisal cycle');
        return data;
    },
};
