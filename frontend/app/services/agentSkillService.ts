import { AgentSkill, CreateAgentSkillRequest, UpdateAgentSkillRequest, Queue } from '../types/agentSkill';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || '';

export const agentSkillService = {
  async getAll(agentId: number): Promise<AgentSkill[]> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/skills`);
    if (!response.ok) {
      throw new Error('Failed to fetch agent skills');
    }
    return response.json();
  },

  async getById(agentId: number, id: number): Promise<AgentSkill> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/skills/${id}`);
    if (!response.ok) {
      throw new Error('Failed to fetch agent skill');
    }
    return response.json();
  },

  async create(agentId: number, data: CreateAgentSkillRequest): Promise<AgentSkill> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/skills`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to create agent skill');
    }
    return response.json();
  },

  async update(agentId: number, id: number, data: UpdateAgentSkillRequest): Promise<AgentSkill> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/skills/${id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) {
      throw new Error('Failed to update agent skill');
    }
    return response.json();
  },

  async delete(agentId: number, id: number): Promise<void> {
    const response = await fetch(`${API_BASE_URL}/api/agents/${agentId}/skills/${id}`, {
      method: 'DELETE',
    });
    if (!response.ok) {
      throw new Error('Failed to delete agent skill');
    }
  },
};

export const queueService = {
  async getAll(): Promise<Queue[]> {
    const response = await fetch(`${API_BASE_URL}/api/queues`);
    if (!response.ok) {
      throw new Error('Failed to fetch queues');
    }
    return response.json();
  },
};
