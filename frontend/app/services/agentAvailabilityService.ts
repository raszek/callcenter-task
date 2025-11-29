import { AgentAvailability, CreateAgentAvailabilityRequest, UpdateAgentAvailabilityRequest } from '../types/agentAvailability';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export const agentAvailabilityService = {
  async getAll(agentId: number): Promise<AgentAvailability[]> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/availabilities`);
    if (!response.ok) {
      throw new Error('Failed to fetch agent availabilities');
    }
    return response.json();
  },

  async getById(agentId: number, id: number): Promise<AgentAvailability> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/availabilities/${id}`);
    if (!response.ok) {
      throw new Error('Failed to fetch agent availability');
    }
    return response.json();
  },

  async create(agentId: number, data: CreateAgentAvailabilityRequest): Promise<AgentAvailability> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/availabilities`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to create agent availability');
    }
    return response.json();
  },

  async update(agentId: number, id: number, data: UpdateAgentAvailabilityRequest): Promise<AgentAvailability> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/availabilities/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to update agent availability');
    }
    return response.json();
  },

  async delete(agentId: number, id: number): Promise<void> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/availabilities/${id}`, {
      method: 'DELETE',
    });
    if (!response.ok) {
      throw new Error('Failed to delete agent availability');
    }
  },
};
