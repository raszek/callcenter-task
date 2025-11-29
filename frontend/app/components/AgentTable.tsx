'use client';

import { useState, useEffect } from 'react';
import { Agent, CreateAgentRequest } from '../types/agent';
import { agentService } from '../services/agentService';

interface AgentTableProps {
  onViewAgent?: (id: number) => void;
}

export default function AgentTable({ onViewAgent }: AgentTableProps) {
  const [agents, setAgents] = useState<Agent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState({ firstName: '', lastName: '' });
  const [newAgent, setNewAgent] = useState<CreateAgentRequest>({ firstName: '', lastName: '' });
  const [isAdding, setIsAdding] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      loadAgents();
    }
  }, [mounted]);

  const loadAgents = async () => {
    try {
      setLoading(true);
      const data = await agentService.getAll();
      setAgents(data);
      setError(null);
    } catch (err) {
      setError('Failed to load agents');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAgent.firstName.trim() || !newAgent.lastName.trim()) {
      return;
    }

    try {
      await agentService.create(newAgent);
      setNewAgent({ firstName: '', lastName: '' });
      setIsAdding(false);
      await loadAgents();
    } catch (err) {
      setError('Failed to create agent');
      console.error(err);
    }
  };

  const handleEdit = (agent: Agent) => {
    setEditingId(agent.id);
    setEditForm({ firstName: agent.firstName, lastName: agent.lastName });
  };

  const handleUpdate = async (id: number) => {
    if (!editForm.firstName.trim() || !editForm.lastName.trim()) {
      return;
    }

    try {
      await agentService.update(id, editForm);
      setEditingId(null);
      await loadAgents();
    } catch (err) {
      setError('Failed to update agent');
      console.error(err);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this agent?')) {
      return;
    }

    try {
      await agentService.delete(id);
      await loadAgents();
    } catch (err) {
      setError('Failed to delete agent');
      console.error(err);
    }
  };

  const handleCancelEdit = () => {
    setEditingId(null);
    setEditForm({ firstName: '', lastName: '' });
  };

  const handleCancelAdd = () => {
    setIsAdding(false);
    setNewAgent({ firstName: '', lastName: '' });
  };

  if (!mounted) {
    return <div className="p-4">Loading...</div>;
  }

  if (loading) {
    return <div className="p-4">Loading agents...</div>;
  }

  return (
    <div className="p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Agents</h1>
        {!isAdding && (
          <button
            onClick={() => setIsAdding(true)}
            className="rounded bg-blue-500 px-4 py-2 text-white hover:bg-blue-600"
          >
            Add Agent
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
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">ID</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">First Name</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Last Name</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Actions</th>
            </tr>
          </thead>
          <tbody>
            {isAdding && (
              <tr className="bg-blue-50">
                <td className="border border-gray-300 px-4 py-2 text-gray-900">-</td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="text"
                    value={newAgent.firstName}
                    onChange={(e) => setNewAgent({ ...newAgent, firstName: e.target.value })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                    placeholder="First name"
                  />
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="text"
                    value={newAgent.lastName}
                    onChange={(e) => setNewAgent({ ...newAgent, lastName: e.target.value })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                    placeholder="Last name"
                  />
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
            {agents.map((agent) => (
              <tr key={agent.id} className="hover:bg-gray-50">
                <td className="border border-gray-300 px-4 py-2 text-gray-900">{agent.id}</td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === agent.id ? (
                    <input
                      type="text"
                      value={editForm.firstName}
                      onChange={(e) => setEditForm({ ...editForm, firstName: e.target.value })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    agent.firstName
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === agent.id ? (
                    <input
                      type="text"
                      value={editForm.lastName}
                      onChange={(e) => setEditForm({ ...editForm, lastName: e.target.value })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    agent.lastName
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  {editingId === agent.id ? (
                    <>
                      <button
                        onClick={() => handleUpdate(agent.id)}
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
                      {onViewAgent && (
                        <button
                          onClick={() => onViewAgent(agent.id)}
                          className="mr-2 rounded bg-green-500 px-3 py-1 text-white hover:bg-green-600"
                        >
                          View
                        </button>
                      )}
                      <button
                        onClick={() => handleEdit(agent)}
                        className="mr-2 rounded bg-blue-500 px-3 py-1 text-white hover:bg-blue-600"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleDelete(agent.id)}
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
        {agents.length === 0 && !isAdding && (
          <div className="mt-4 text-center text-gray-500">
            No agents found. Click &quot;Add Agent&quot; to create one.
          </div>
        )}
      </div>
    </div>
  );
}
