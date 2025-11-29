export interface AgentAvailability {
  id: number;
  agentId: number;
  agentFirstName: string;
  agentLastName: string;
  startTime: string;
  endTime: string;
  isAvailable: boolean;
}

export interface CreateAgentAvailabilityRequest {
  startTime: string;
  endTime: string;
  isAvailable: boolean;
}

export interface UpdateAgentAvailabilityRequest {
  startTime: string;
  endTime: string;
  isAvailable: boolean;
}
