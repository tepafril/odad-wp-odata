import { request } from '@/api/client';

export const AttendanceService = {
    async getList(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`attendance?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load attendance');
        return data;
    },
    async getMyToday() {
        const { data, error } = await request('attendance/my-today');
        if (error) throw new Error(data?.message || error.message || 'Failed to load today\'s attendance');
        return data;
    },
    async checkIn(datetime = null) {
        const body = datetime ? { datetime } : {};
        const { data, error } = await request('attendance/check-in', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Check-in failed');
        return data;
    },
    async checkOut(datetime = null) {
        const body = datetime ? { datetime } : {};
        const { data, error } = await request('attendance/check-out', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Check-out failed');
        return data;
    },
    async getRequests(params = {}) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') q.set(k, v); });
        const { data, error } = await request(`attendance-requests?${q}`);
        if (error) throw new Error(data?.message || error.message || 'Failed to load attendance requests');
        return data;
    },
    async createRequest(body) {
        const { data, error } = await request('attendance-requests', { method: 'POST', body: JSON.stringify(body) });
        if (error) throw new Error(data?.message || error.message || 'Failed to create attendance request');
        return data;
    },
    async approveRequest(id) {
        const { data, error } = await request(`attendance-requests/${id}/approve`, { method: 'POST', body: JSON.stringify({}) });
        if (error) throw new Error(data?.message || error.message || 'Failed to approve request');
        return data;
    },
    async rejectRequest(id) {
        const { data, error } = await request(`attendance-requests/${id}/reject`, { method: 'POST', body: JSON.stringify({}) });
        if (error) throw new Error(data?.message || error.message || 'Failed to reject request');
        return data;
    },
};
