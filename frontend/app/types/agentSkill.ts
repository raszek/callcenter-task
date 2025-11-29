export interface AgentSkill {
  id: number;
  agentId: number;
  agentFirstName: string;
  agentLastName: string;
  queueId: number;
  queueName: string;
  queueDisplayName: string;
  efficiencyCoefficient: number;
  skillLevel: number;
  isPrimary: boolean;
}

export interface CreateAgentSkillRequest {
  queueId: number;
  efficiencyCoefficient: number;
  skillLevel: number;
  isPrimary: boolean;
}

export interface UpdateAgentSkillRequest {
  queueId: number;
  efficiencyCoefficient: number;
  skillLevel: number;
  isPrimary: boolean;
}

export interface Queue {
  id: number;
  name: string;
  displayName: string;
}
