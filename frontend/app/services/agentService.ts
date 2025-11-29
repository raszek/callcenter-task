import { Agent, CreateAgentRequest, UpdateAgentRequest } from '../types/agent';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export const agentService = {
  async getAll(): Promise<Agent[]> {
    const response = await fetch(`${API_BASE_URL}/api/agents`);
    if (!response.ok) {
      throw new Error('Failed to fetch agents');
    }
    return response.json();
  },

  async getById(id: number): Promise<Agent> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${id}`);
    if (!response.ok) {
      throw new Error('Failed to fetch agent');
    }
    return response.json();
  },

  async create(data: CreateAgentRequest): Promise<Agent> {
    const response = await fetch(`${API_BASE_URL}/api/agents`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to create agent');
    }
    return response.json();
  },

  async update(id: number, data: UpdateAgentRequest): Promise<Agent> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to update agent');
    }
    return response.json();
  },

  async delete(id: number): Promise<void> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${id}`, {
      method: 'DELETE',
    });
    if (!response.ok) {
      throw new Error('Failed to delete agent');
    }
  },
};
