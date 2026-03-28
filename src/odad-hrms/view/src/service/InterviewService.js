import { request } from '@/api/client';

export const InterviewService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`interviews?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load interviews');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`interviews/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load interview');
        return data;
    },
    async create(body) {
        const { data, error } = await request('interviews', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create interview');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`interviews/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update interview');
        return data;
    },
    async submitFeedback(id, body) {
        const { data, error } = await request(`interviews/${id}/feedback`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to submit feedback');
        return data;
    },
    async getPanelists(id) {
        const { data, error } = await request(`interviews/${id}/panelists`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load panelists');
        return data;
    },
    async addPanelist(id, body) {
        const { data, error } = await request(`interviews/${id}/panelists`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to add panelist');
        return data;
    },
    async updatePanelist(id, pid, body) {
        const { data, error } = await request(`interviews/${id}/panelists/${pid}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update panelist');
        return data;
    },
    async removePanelist(id, pid) {
        const { data, error } = await request(`interviews/${id}/panelists/${pid}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to remove panelist');
        return data;
    },
};
