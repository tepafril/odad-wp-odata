import { request } from '@/api/client';

export const LeaveBalanceService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`leave/balance?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load leave balances');
        return data;
    },
    async getYears() {
        const { data, error } = await request('leave/balance/years');
        if (error) throw new Error(data?.message || error.message || 'Failed to load balance years');
        return data;
    },
    async getMyBalance() {
        const { data, error } = await request('leave/my-balance');
        if (error) throw new Error(data?.message || error.message || 'Failed to load my leave balance');
        return data;
    },
    async adjust(id, manualAdjustment) {
        const { data, error } = await request(`leave/balance/${id}`, { method: 'PUT', body: JSON.stringify({ manual_adjustment: manualAdjustment }) });
        if (error) throw new Error(data?.message || error.message || 'Failed to adjust leave balance');
        return data;
    },
};
