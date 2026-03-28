import { request } from '@/api/client';

export const GoalService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`goals?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load goals');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`goals/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load goal');
        return data;
    },
    async create(body) {
        const { data, error } = await request('goals', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create goal');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`goals/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update goal');
        return data;
    },
    async updateProgress(id, body) {
        const { data, error } = await request(`goals/${id}/progress`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update goal progress');
        return data;
    },
};
