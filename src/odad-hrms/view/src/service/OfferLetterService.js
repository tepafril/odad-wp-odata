import { request } from '@/api/client';

export const OfferLetterService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`offer-letters?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load offer letters');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`offer-letters/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load offer letter');
        return data;
    },
    async create(body) {
        const { data, error } = await request('offer-letters', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create offer letter');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`offer-letters/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update offer letter');
        return data;
    },
    async send(id) {
        const { data, error } = await request(`offer-letters/${id}/send`, { method: 'POST' });
        if (error) throw new Error(data?.message || error.message || 'Failed to send offer letter');
        return data;
    },
};
