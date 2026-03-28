import { request } from '@/api/client';

export const AppraisalService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`appraisals?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load appraisals');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`appraisals/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load appraisal');
        return data;
    },
    async create(body) {
        const { data, error } = await request('appraisals', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create appraisal');
        return data;
    },
    async submitSelfReview(id, body) {
        const { data, error } = await request(`appraisals/${id}/self-review`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to submit self review');
        return data;
    },
    async submitManagerReview(id, body) {
        const { data, error } = await request(`appraisals/${id}/manager-review`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to submit manager review');
        return data;
    },
    async finalize(id, body) {
        const { data, error } = await request(`appraisals/${id}/finalize`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to finalize appraisal');
        return data;
    },
};
