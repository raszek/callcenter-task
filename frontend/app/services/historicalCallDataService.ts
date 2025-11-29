import { HistoricalCallData, HistoricalCallDataFilters } from '../types/historicalCallData';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export const historicalCallDataService = {
  async getAll(filters?: HistoricalCallDataFilters): Promise<HistoricalCallData[]> {
    const params = new URLSearchParams();

    if (filters?.queueName) {
      params.append('queueName', filters.queueName);
    }
    if (filters?.startDate) {
      params.append('startDate', filters.startDate);
    }
    if (filters?.endDate) {
      params.append('endDate', filters.endDate);
    }
    if (filters?.limit) {
      params.append('limit', filters.limit.toString());
    }

    const queryString = params.toString();
    const url = `${API_BASE_URL}/api/historical-call-data${queryString ? `?${queryString}` : ''}`;

    const response = await fetch(url);
    if (!response.ok) {
      throw new Error('Failed to fetch historical call data');
    }
    return response.json();
  },

  async getById(id: number): Promise<HistoricalCallData> {
    const response = await fetch(`${API_BASE_URL}/api/historical-call-data/${id}`);
    if (!response.ok) {
      throw new Error('Failed to fetch historical call data');
    }
    return response.json();
  },
};
