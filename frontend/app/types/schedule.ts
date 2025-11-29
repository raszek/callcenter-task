export interface CreateScheduleRequest {
  scheduleStartDate: string;
  scheduleEndDate: string;
  queueNames: string[];
  timeSlotGranularityMinutes?: number;
  lookbackWeeks?: number;
  shrinkageFactor?: number;
  targetOccupancy?: number;
  constraints?: Record<string, unknown>;
}

export interface ScheduleAssignment {
  agent_id: number;
  queue_name: string;
  start_time: string;
  end_time: string;
  duration_hours: number;
  efficiency_score: number;
  assignment_type: string;
}

export interface ScheduleResponse {
  assignments: ScheduleAssignment[];
  qualityMetrics: Record<string, unknown>;
  coverageByQueueAndHour: Record<string, Record<string, number>>;
  isFeasible: boolean;
  warnings: string[];
}
