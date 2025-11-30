export interface HistoricalCallData {
  id: number;
  queueId: number;
  queueName: string;
  queueDisplayName: string;
  datetime: string;
  callCount: number;
  averageHandleTimeSeconds: number;
}

export interface HistoricalCallDataFilters {
  queueName?: string;
  startDate?: string;
  endDate?: string;
  limit?: number;
}

export interface HistoricalCallDataEntry {
  datetime: string;
  callCount: number;
  averageHandleTimeSeconds: number;
}

export interface CreateHistoricalCallDataRequest {
  queueName: string;
  entries: HistoricalCallDataEntry[];
}

export interface CreateHistoricalCallDataResponse {
  message: string;
  created: number;
  failed: number;
  entries: HistoricalCallDataEntry[];
  errors?: string[];
}

export interface GenerateHistoricalCallDataRequest {
  days?: number;
  intervalHours?: number;
}

export interface GenerateHistoricalCallDataResponse {
  message: string;
  created: number;
  queues: number;
  days: number;
  intervalHours: number;
}
