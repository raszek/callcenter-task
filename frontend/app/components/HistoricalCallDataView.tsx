'use client';

import { useState, useEffect } from 'react';
import { HistoricalCallData, HistoricalCallDataFilters } from '../types/historicalCallData';
import { Queue } from '../types/agentSkill';
import { historicalCallDataService } from '../services/historicalCallDataService';
import { queueService } from '../services/agentSkillService';

export default function HistoricalCallDataView() {
  const [data, setData] = useState<HistoricalCallData[]>([]);
  const [queues, setQueues] = useState<Queue[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);

  const [filters, setFilters] = useState<HistoricalCallDataFilters>({
    queueName: '',
    startDate: '',
    endDate: '',
    limit: 100,
  });

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      loadQueues();
      loadData();
    }
  }, [mounted]);

  const loadQueues = async () => {
    try {
      const queuesData = await queueService.getAll();
      setQueues(queuesData);
    } catch (err) {
      console.error('Failed to load queues:', err);
    }
  };

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);

      const filterParams: HistoricalCallDataFilters = {};

      if (filters.queueName) {
        filterParams.queueName = filters.queueName;
      }
      if (filters.startDate) {
        filterParams.startDate = filters.startDate;
      }
      if (filters.endDate) {
        filterParams.endDate = filters.endDate;
      }
      if (filters.limit && filters.limit > 0) {
        filterParams.limit = filters.limit;
      }

      const result = await historicalCallDataService.getAll(filterParams);
      setData(result);
    } catch (err) {
      setError('Failed to load historical call data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: keyof HistoricalCallDataFilters, value: string | number) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const handleApplyFilters = (e: React.FormEvent) => {
    e.preventDefault();
    loadData();
  };

  const handleClearFilters = () => {
    setFilters({
      queueName: '',
      startDate: '',
      endDate: '',
      limit: 100,
    });
  };

  const formatDateTime = (dateTimeString: string): string => {
    const date = new Date(dateTimeString);
    return date.toLocaleString();
  };

  const formatDuration = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${minutes}m ${secs}s`;
  };

  const calculateTotalCalls = (): number => {
    return data.reduce((sum, item) => sum + item.callCount, 0);
  };

  const calculateAverageHandleTime = (): number => {
    if (data.length === 0) return 0;
    const total = data.reduce((sum, item) => sum + item.averageHandleTimeSeconds, 0);
    return total / data.length;
  };

  if (!mounted) {
    return <div className="p-4">Loading...</div>;
  }

  return (
    <div className="p-6">
      <h1 className="mb-6 text-2xl font-bold text-gray-900">Historical Call Data</h1>

      {error && (
        <div className="mb-4 rounded bg-red-100 p-3 text-red-700">
          {error}
        </div>
      )}

      <form onSubmit={handleApplyFilters} className="mb-6 rounded border border-gray-300 bg-white p-6">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">Filters</h2>

        <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Queue
            </label>
            <select
              value={filters.queueName}
              onChange={(e) => handleFilterChange('queueName', e.target.value)}
              className="w-full rounded border px-3 py-2 text-gray-900"
            >
              <option value="">All Queues</option>
              {queues.map((queue) => (
                <option key={queue.id} value={queue.name}>
                  {queue.displayName}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Start Date
            </label>
            <input
              type="datetime-local"
              value={filters.startDate}
              onChange={(e) => handleFilterChange('startDate', e.target.value)}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              End Date
            </label>
            <input
              type="datetime-local"
              value={filters.endDate}
              onChange={(e) => handleFilterChange('endDate', e.target.value)}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-900">
              Limit
            </label>
            <input
              type="number"
              min="1"
              max="1000"
              value={filters.limit}
              onChange={(e) => handleFilterChange('limit', Number(e.target.value))}
              className="w-full rounded border px-3 py-2 text-gray-900"
            />
          </div>
        </div>

        <div className="flex gap-2">
          <button
            type="submit"
            disabled={loading}
            className="rounded bg-blue-500 px-4 py-2 text-white hover:bg-blue-600 disabled:bg-gray-400"
          >
            {loading ? 'Loading...' : 'Apply Filters'}
          </button>
          <button
            type="button"
            onClick={handleClearFilters}
            className="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
          >
            Clear Filters
          </button>
        </div>
      </form>

      {data.length > 0 && (
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div className="rounded border border-gray-300 bg-white p-4">
            <div className="text-sm text-gray-600">Total Records</div>
            <div className="text-2xl font-bold text-gray-900">{data.length}</div>
          </div>
          <div className="rounded border border-gray-300 bg-white p-4">
            <div className="text-sm text-gray-600">Total Calls</div>
            <div className="text-2xl font-bold text-gray-900">{calculateTotalCalls().toLocaleString()}</div>
          </div>
          <div className="rounded border border-gray-300 bg-white p-4">
            <div className="text-sm text-gray-600">Average Handle Time</div>
            <div className="text-2xl font-bold text-gray-900">{formatDuration(calculateAverageHandleTime())}</div>
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded border border-gray-300 bg-white">
        <table className="min-w-full border-collapse">
          <thead>
            <tr className="bg-gray-100">
              <th className="border-b border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Date & Time</th>
              <th className="border-b border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Queue</th>
              <th className="border-b border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Call Count</th>
              <th className="border-b border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Avg Handle Time</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-gray-500">
                  Loading data...
                </td>
              </tr>
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-gray-500">
                  No historical call data found. Try adjusting your filters.
                </td>
              </tr>
            ) : (
              data.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50">
                  <td className="border-b border-gray-200 px-4 py-2 text-gray-900">
                    {formatDateTime(item.datetime)}
                  </td>
                  <td className="border-b border-gray-200 px-4 py-2 text-gray-900">
                    {item.queueDisplayName}
                  </td>
                  <td className="border-b border-gray-200 px-4 py-2 text-gray-900">
                    {item.callCount}
                  </td>
                  <td className="border-b border-gray-200 px-4 py-2 text-gray-900">
                    {formatDuration(item.averageHandleTimeSeconds)}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
