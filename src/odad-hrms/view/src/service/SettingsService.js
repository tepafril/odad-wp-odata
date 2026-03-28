import { request } from '@/api/client';

export const SettingsService = {
    async get(group = null) {
        const q = group ? `?group=${group}` : '';
        const { data, error } = await request(`settings${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load settings');
        return data;
    },
    async update(body) {
        const { data, error } = await request('settings', { method: 'PUT', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to update settings');
        return data;
    },
};
