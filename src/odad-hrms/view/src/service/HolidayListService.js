import { request } from '@/api/client';

export const HolidayListService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`holiday-lists?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load holiday lists');
        return data;
    },
    async getById(id) {
        const { data, error } = await request(`holiday-lists/${id}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load holiday list');
        return data;
    },
    async getHolidays(listId, params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null) q.set(k, v); });
        const { data, error } = await request(`holiday-lists/${listId}/holidays?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load holidays');
        return data;
    },
    async create(body) {
        const { data, error } = await request('holiday-lists', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create holiday list');
        return data;
    },
    async createHoliday(listId, body) {
        const { data, error } = await request(`holiday-lists/${listId}/holidays`, { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create holiday');
        return data;
    },
    async replaceHolidays(listId, holidays) {
        const { data, error } = await request(`holiday-lists/${listId}/holidays`, { method: 'PUT', body: JSON.stringify(holidays) });
        if (error) throw new Error(data?.message || error.message || 'Failed to save holidays');
        return data;
    },
    async update(id, body) {
        const { data, error } = await request(`holiday-lists/${id}`, { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update holiday list');
        return data;
    },
    async remove(id) {
        const { data, error } = await request(`holiday-lists/${id}`, { method: 'DELETE' });
        if (error) throw new Error(data?.message || error.message || 'Failed to delete holiday list');
        return data;
    },
};
