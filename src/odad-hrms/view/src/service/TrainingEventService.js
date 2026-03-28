import { request } from '@/api/client';

export const TrainingEventService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`training-events?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load training events');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`training-events/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load training event');
        return data;
    },
    async create(body) {
        const { data, error } = await request('training-events', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create training event');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`training-events/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update training event');
        return data;
    },
    async registerParticipant(id, body) {
        const { data, error } = await request(`training-events/${id}/participants`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to register participant');
        return data;
    },
    async updateParticipant(id, pid, body) {
        const { data, error } = await request(`training-events/${id}/participants/${pid}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update participant');
        return data;
    },
};
