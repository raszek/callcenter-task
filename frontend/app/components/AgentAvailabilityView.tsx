'use client';

import { useState, useEffect } from 'react';
import { AgentAvailability, CreateAgentAvailabilityRequest } from '../types/agentAvailability';
import { agentAvailabilityService } from '../services/agentAvailabilityService';
import {formatDateForAPI} from '@/app/helpers/date';

interface AgentAvailabilityViewProps {
  agentId: number;
}

export default function AgentAvailabilityView({ agentId }: AgentAvailabilityViewProps) {
  const [availabilities, setAvailabilities] = useState<AgentAvailability[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState({
    startTime: '',
    endTime: '',
    isAvailable: true,
  });
  const [newAvailability, setNewAvailability] = useState<CreateAgentAvailabilityRequest>({
    startTime: '',
    endTime: '',
    isAvailable: true,
  });
  const [isAdding, setIsAdding] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      loadAvailabilities();
    }
  }, [mounted, agentId]);

  const loadAvailabilities = async () => {
    try {
      setLoading(true);
      const data = await agentAvailabilityService.getAll(agentId);
      setAvailabilities(data);
      setError(null);
    } catch (err) {
      setError('Failed to load availabilities');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const formatDateTimeForInput = (dateTimeString: string): string => {
    const date = new Date(dateTimeString);
    return date.toISOString().slice(0, 16);
  };

  const formatDateTimeForDisplay = (dateTimeString: string): string => {
    const date = new Date(dateTimeString);
    return date.toLocaleString();
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAvailability.startTime || !newAvailability.endTime) {
      setError('Please fill in all fields');
      return;
    }

    try {
      // Format datetimes for PHP server
      const formattedData = {
        startTime: formatDateForAPI(newAvailability.startTime),
        endTime: formatDateForAPI(newAvailability.endTime),
        isAvailable: newAvailability.isAvailable,
      };

      await agentAvailabilityService.create(agentId, formattedData);
      setNewAvailability({
        startTime: '',
        endTime: '',
        isAvailable: true,
      });
      setIsAdding(false);
      await loadAvailabilities();
    } catch (err) {
      setError('Failed to create availability');
      console.error(err);
    }
  };

  const handleEdit = (availability: AgentAvailability) => {
    setEditingId(availability.id);
    setEditForm({
      startTime: formatDateTimeForInput(availability.startTime),
      endTime: formatDateTimeForInput(availability.endTime),
      isAvailable: availability.isAvailable,
    });
  };

  const handleUpdate = async (id: number) => {
    if (!editForm.startTime || !editForm.endTime) {
      setError('Please fill in all fields');
      return;
    }

    try {
      // Format datetimes for PHP server
      const formattedData = {
        startTime: formatDateForAPI(editForm.startTime),
        endTime: formatDateForAPI(editForm.endTime),
        isAvailable: editForm.isAvailable,
      };

      await agentAvailabilityService.update(agentId, id, formattedData);
      setEditingId(null);
      await loadAvailabilities();
    } catch (err) {
      setError('Failed to update availability');
      console.error(err);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this availability?')) {
      return;
    }

    try {
      await agentAvailabilityService.delete(agentId, id);
      await loadAvailabilities();
    } catch (err) {
      setError('Failed to delete availability');
      console.error(err);
    }
  };

  const handleCancelEdit = () => {
    setEditingId(null);
    setEditForm({
      startTime: '',
      endTime: '',
      isAvailable: true,
    });
  };

  const handleCancelAdd = () => {
    setIsAdding(false);
    setNewAvailability({
      startTime: '',
      endTime: '',
      isAvailable: true,
    });
  };

  if (!mounted) {
    return <div className="p-4">Loading...</div>;
  }

  if (loading) {
    return <div className="p-4">Loading availabilities...</div>;
  }

  return (
    <div className="mt-6">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-xl font-bold text-gray-900">Availability</h2>
        {!isAdding && (
          <button
            onClick={() => setIsAdding(true)}
            className="rounded bg-blue-500 px-4 py-2 text-white hover:bg-blue-600"
          >
            Add Availability
          </button>
        )}
      </div>

      {error && (
        <div className="mb-4 rounded bg-red-100 p-3 text-red-700">
          {error}
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="min-w-full border-collapse border border-gray-300">
          <thead>
            <tr className="bg-gray-100">
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Start Time</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">End Time</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Status</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Actions</th>
            </tr>
          </thead>
          <tbody>
            {isAdding && (
              <tr className="bg-blue-50">
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="datetime-local"
                    value={newAvailability.startTime}
                    onChange={(e) => setNewAvailability({ ...newAvailability, startTime: e.target.value })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  />
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="datetime-local"
                    value={newAvailability.endTime}
                    onChange={(e) => setNewAvailability({ ...newAvailability, endTime: e.target.value })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  />
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <select
                    value={newAvailability.isAvailable ? 'available' : 'unavailable'}
                    onChange={(e) => setNewAvailability({ ...newAvailability, isAvailable: e.target.value === 'available' })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  >
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                  </select>
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <button
                    onClick={handleCreate}
                    className="mr-2 rounded bg-green-500 px-3 py-1 text-white hover:bg-green-600"
                  >
                    Save
                  </button>
                  <button
                    onClick={handleCancelAdd}
                    className="rounded bg-gray-500 px-3 py-1 text-white hover:bg-gray-600"
                  >
                    Cancel
                  </button>
                </td>
              </tr>
            )}
            {availabilities.map((availability) => (
              <tr key={availability.id} className="hover:bg-gray-50">
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === availability.id ? (
                    <input
                      type="datetime-local"
                      value={editForm.startTime}
                      onChange={(e) => setEditForm({ ...editForm, startTime: e.target.value })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    formatDateTimeForDisplay(availability.startTime)
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === availability.id ? (
                    <input
                      type="datetime-local"
                      value={editForm.endTime}
                      onChange={(e) => setEditForm({ ...editForm, endTime: e.target.value })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    formatDateTimeForDisplay(availability.endTime)
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === availability.id ? (
                    <select
                      value={editForm.isAvailable ? 'available' : 'unavailable'}
                      onChange={(e) => setEditForm({ ...editForm, isAvailable: e.target.value === 'available' })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    >
                      <option value="available">Available</option>
                      <option value="unavailable">Unavailable</option>
                    </select>
                  ) : (
                    <span className={`inline-block rounded px-2 py-1 text-sm ${availability.isAvailable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                      {availability.isAvailable ? 'Available' : 'Unavailable'}
                    </span>
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  {editingId === availability.id ? (
                    <>
                      <button
                        onClick={() => handleUpdate(availability.id)}
                        className="mr-2 rounded bg-green-500 px-3 py-1 text-white hover:bg-green-600"
                      >
                        Save
                      </button>
                      <button
                        onClick={handleCancelEdit}
                        className="rounded bg-gray-500 px-3 py-1 text-white hover:bg-gray-600"
                      >
                        Cancel
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        onClick={() => handleEdit(availability)}
                        className="mr-2 rounded bg-blue-500 px-3 py-1 text-white hover:bg-blue-600"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleDelete(availability.id)}
                        className="rounded bg-red-500 px-3 py-1 text-white hover:bg-red-600"
                      >
                        Delete
                      </button>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {availabilities.length === 0 && !isAdding && (
          <div className="mt-4 text-center text-gray-500">
            No availabilities found. Click &quot;Add Availability&quot; to create one.
          </div>
        )}
      </div>
    </div>
  );
}
