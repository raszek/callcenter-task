import { CreateScheduleRequest, ScheduleResponse } from '../types/schedule';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export const scheduleService = {
  async generate(data: CreateScheduleRequest): Promise<ScheduleResponse> {
    const response = await fetch(`${API_BASE_URL}/api/schedules`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to generate schedule');
    }

    return response.json();
  },
};
