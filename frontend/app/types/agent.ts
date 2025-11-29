export interface Agent {
  id: number;
  firstName: string;
  lastName: string;
}

export interface CreateAgentRequest {
  firstName: string;
  lastName: string;
}

export interface UpdateAgentRequest {
  firstName: string;
  lastName: string;
}
