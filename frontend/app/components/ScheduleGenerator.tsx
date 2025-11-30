'use client';

import { useState, useEffect } from 'react';
import { Queue } from '../types/agentSkill';
import { CreateScheduleRequest, ScheduleResponse, ScheduleAssignment } from '../types/schedule';
import { queueService } from '../services/agentSkillService';
import { scheduleService } from '../services/scheduleService';
import {formatDateForAPI} from '@/app/helpers/date';

export default function ScheduleGenerator() {
  const [queues, setQueues] = useState<Queue[]>([]);
  const [selectedQueues, setSelectedQueues] = useState<string[]>([]);
  const [formData, setFormData] = useState<CreateScheduleRequest>({
    scheduleStartDate: '',
    scheduleEndDate: '',
    queueNames: [],
    timeSlotGranularityMinutes: 30,
    lookbackWeeks: 4,
    shrinkageFactor: 0.25,
    targetOccupancy: 0.85,
  });
  const [schedule, setSchedule] = useState<ScheduleResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);
  const [warningsExpanded, setWarningsExpanded] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      loadQueues();
    }
  }, [mounted]);

  const loadQueues = async () => {
    try {
      const data = await queueService.getAll();
      setQueues(data);
    } catch (err) {
      setError('Failed to load queues');
      console.error(err);
    }
  };

  const handleQueueToggle = (queueName: string) => {
    setSelectedQueues((prev) => {
      if (prev.includes(queueName)) {
        return prev.filter((q) => q !== queueName);
      } else {
        return [...prev, queueName];
      }
    });
  };

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();

    if (selectedQueues.length === 0) {
      setError('Please select at least one queue');
      return;
    }

    if (!formData.scheduleStartDate || !formData.scheduleEndDate) {
      setError('Please select start and end dates');
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const requestData: CreateScheduleRequest = {
        ...formData,
        scheduleStartDate: formatDateForAPI(formData.scheduleStartDate),
        scheduleEndDate: formatDateForAPI(formData.scheduleEndDate),
        queueNames: selectedQueues,
      };

      const result = await scheduleService.generate(requestData);
      setSchedule(result);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to generate schedule');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const formatDateTime = (dateTimeString: string): string => {
    const date = new Date(dateTimeString);
    return date.toLocaleString();
  };

  const groupAssignmentsByAgent = (assignments: ScheduleAssignment[]): Map<number, ScheduleAssignment[]> => {
    const grouped = new Map<number, ScheduleAssignment[]>();
    assignments.forEach((assignment) => {
      const agentAssignments = grouped.get(assignment.agent_id) || [];
      agentAssignments.push(assignment);
      grouped.set(assignment.agent_id, agentAssignments);
    });
    return grouped;
  };

  if (!mounted) {
    return <div className="p-4">Loading...</div>;
  }

  return (
    <div className="p-6">
      <h1 className="mb-6 text-2xl font-bold text-gray-900">Schedule Generator</h1>

      {error && (
        <div className="mb-4 rounded bg-red-100 p-3 text-red-700">
          {error}
        </div>
      )}

      <form onSubmit={handleGenerate} className="mb-8 rounded border border-gray-300 bg-white p-6">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">Schedule Parameters</h2>

        <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Start Date & Time
            </label>
            <input
              type="datetime-local"
              value={formData.scheduleStartDate}
              onChange={(e) => setFormData({ ...formData, scheduleStartDate: e.target.value })}
              className="w-full rounded border px-3 py-2 text-gray-900"
              required
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              End Date & Time
            </label>
            <input
              type="datetime-local"
              value={formData.scheduleEndDate}
              onChange={(e) => setFormData({ ...formData, scheduleEndDate: e.target.value })}
              className="w-full rounded border px-3 py-2 text-gray-900"
              required
            />
          </div>
        </div>

        <div className="mb-4">
          <label className="mb-2 block text-sm font-medium text-gray-900">
            Select Queues
          </label>
          <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
            {queues.map((queue) => (
              <label key={queue.id} className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  checked={selectedQueues.includes(queue.name)}
                  onChange={() => handleQueueToggle(queue.name)}
                  className="h-4 w-4"
                />
                <span className="text-gray-900">{queue.displayName}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Time Slot (minutes)
            </label>
            <input
              type="number"
              min="1"
              value={formData.timeSlotGranularityMinutes}
              onChange={(e) => setFormData({ ...formData, timeSlotGranularityMinutes: Number(e.target.value) })}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Lookback Weeks
            </label>
            <input
              type="number"
              min="1"
              value={formData.lookbackWeeks}
              onChange={(e) => setFormData({ ...formData, lookbackWeeks: Number(e.target.value) })}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Shrinkage Factor (0-1)
            </label>
            <input
              type="number"
              step="0.01"
              min="0"
              max="1"
              value={formData.shrinkageFactor}
              onChange={(e) => setFormData({ ...formData, shrinkageFactor: Number(e.target.value) })}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Target Occupancy (0-1)
            </label>
            <input
              type="number"
              step="0.01"
              min="0"
              max="1"
              value={formData.targetOccupancy}
              onChange={(e) => setFormData({ ...formData, targetOccupancy: Number(e.target.value) })}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="rounded bg-blue-500 px-6 py-2 text-white hover:bg-blue-600 disabled:bg-gray-400"
        >
          {loading ? 'Generating...' : 'Generate Schedule'}
        </button>
      </form>

      {schedule && (
        <div className="space-y-6">
          <div className="rounded border border-gray-300 bg-white p-6">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">Schedule Status</h2>
            <div>
              <span className={`inline-block rounded px-3 py-1 text-sm font-semibold ${schedule.isFeasible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                {schedule.isFeasible ? 'Feasible' : 'Not Feasible'}
              </span>
            </div>
          </div>

          <div className="rounded border border-gray-300 bg-white p-6">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">Quality Metrics</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
              {Object.entries(schedule.qualityMetrics).map(([key, value]) => (
                  <div key={key} className="rounded border border-gray-200 p-4">
                    <div className="text-sm text-gray-600">{key}</div>
                    <div className="text-lg font-semibold text-gray-900">
                      {typeof value === 'number' ? value.toFixed(2) : String(value)}
                    </div>
                  </div>
              ))}
            </div>
          </div>

          <div className="rounded border border-gray-300 bg-white p-6">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">
              Assignments ({schedule.assignments.length})
            </h2>
            <div className="overflow-x-auto">
              <table className="min-w-full border-collapse border border-gray-300">
                <thead>
                  <tr className="bg-gray-100">
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Agent ID</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Queue</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Start Time</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">End Time</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Duration (hrs)</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Efficiency</th>
                    <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Type</th>
                  </tr>
                </thead>
                <tbody>
                  {schedule.assignments.map((assignment, idx) => (
                    <tr key={idx} className="hover:bg-gray-50">
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{assignment.agent_id}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{assignment.queue_name}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{formatDateTime(assignment.start_time)}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{formatDateTime(assignment.end_time)}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{assignment.duration_hours.toFixed(2)}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{assignment.efficiency_score.toFixed(2)}</td>
                      <td className="border border-gray-300 px-4 py-2 text-gray-900">{assignment.assignment_type}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>


          {schedule.warnings.length > 0 && (
            <div className="rounded border border-gray-300 bg-white p-6">
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-xl font-semibold text-gray-900">
                  Warnings ({schedule.warnings.length})
                </h2>
                <button
                  onClick={() => setWarningsExpanded(!warningsExpanded)}
                  className="text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                  {warningsExpanded ? 'Collapse' : 'Expand'}
                </button>
              </div>
              {warningsExpanded && (
                <ul className="list-inside list-disc text-gray-700">
                  {schedule.warnings.map((warning, idx) => (
                    <li key={idx}>{warning}</li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
