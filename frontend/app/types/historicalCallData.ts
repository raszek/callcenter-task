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
