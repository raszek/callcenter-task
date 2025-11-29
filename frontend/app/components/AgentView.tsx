'use client';

import { useState, useEffect } from 'react';
import { Agent } from '../types/agent';
import { AgentSkill, CreateAgentSkillRequest, Queue } from '../types/agentSkill';
import { agentService } from '../services/agentService';
import { agentSkillService, queueService } from '../services/agentSkillService';
import AgentAvailabilityView from './AgentAvailabilityView';

interface AgentViewProps {
  agentId: number;
  onBack: () => void;
}

export default function AgentView({ agentId, onBack }: AgentViewProps) {
  const [agent, setAgent] = useState<Agent | null>(null);
  const [skills, setSkills] = useState<AgentSkill[]>([]);
  const [queues, setQueues] = useState<Queue[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState({
    queueId: 0,
    efficiencyCoefficient: 1,
    skillLevel: 1,
    isPrimary: false,
  });
  const [newSkill, setNewSkill] = useState<CreateAgentSkillRequest>({
    queueId: 0,
    efficiencyCoefficient: 1,
    skillLevel: 1,
    isPrimary: false,
  });
  const [isAdding, setIsAdding] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (mounted) {
      loadData();
    }
  }, [mounted, agentId]);

  const loadData = async () => {
    try {
      setLoading(true);
      const [agentData, skillsData, queuesData] = await Promise.all([
        agentService.getById(agentId),
        agentSkillService.getAll(agentId),
        queueService.getAll(),
      ]);
      setAgent(agentData);
      setSkills(skillsData);
      setQueues(queuesData);
      setError(null);
    } catch (err) {
      setError('Failed to load agent data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newSkill.queueId === 0) {
      setError('Please select a queue');
      return;
    }

    try {
      await agentSkillService.create(agentId, newSkill);
      setNewSkill({
        queueId: 0,
        efficiencyCoefficient: 1,
        skillLevel: 1,
        isPrimary: false,
      });
      setIsAdding(false);
      await loadData();
    } catch (err) {
      setError('Failed to create agent skill');
      console.error(err);
    }
  };

  const handleEdit = (skill: AgentSkill) => {
    setEditingId(skill.id);
    setEditForm({
      queueId: skill.queueId,
      efficiencyCoefficient: skill.efficiencyCoefficient,
      skillLevel: skill.skillLevel,
      isPrimary: skill.isPrimary,
    });
  };

  const handleUpdate = async (id: number) => {
    if (editForm.queueId === 0) {
      setError('Please select a queue');
      return;
    }

    try {
      await agentSkillService.update(agentId, id, editForm);
      setEditingId(null);
      await loadData();
    } catch (err) {
      setError('Failed to update agent skill');
      console.error(err);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this skill?')) {
      return;
    }

    try {
      await agentSkillService.delete(agentId, id);
      await loadData();
    } catch (err) {
      setError('Failed to delete agent skill');
      console.error(err);
    }
  };

  const handleCancelEdit = () => {
    setEditingId(null);
    setEditForm({
      queueId: 0,
      efficiencyCoefficient: 1,
      skillLevel: 1,
      isPrimary: false,
    });
  };

  const handleCancelAdd = () => {
    setIsAdding(false);
    setNewSkill({
      queueId: 0,
      efficiencyCoefficient: 1,
      skillLevel: 1,
      isPrimary: false,
    });
  };

  if (!mounted) {
    return <div className="p-4">Loading...</div>;
  }

  if (loading) {
    return <div className="p-4">Loading agent...</div>;
  }

  if (!agent) {
    return <div className="p-4">Agent not found</div>;
  }

  return (
    <div className="p-6">
      <button
        onClick={onBack}
        className="mb-4 rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
      >
        ← Back to Agents
      </button>

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {agent.firstName} {agent.lastName}
        </h1>
        <p className="text-gray-600">Agent ID: {agent.id}</p>
      </div>

      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-xl font-bold text-gray-900">Skills</h2>
        {!isAdding && (
          <button
            onClick={() => setIsAdding(true)}
            className="rounded bg-blue-500 px-4 py-2 text-white hover:bg-blue-600"
          >
            Add Skill
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
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Queue</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Efficiency</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Skill Level</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Primary</th>
              <th className="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Actions</th>
            </tr>
          </thead>
          <tbody>
            {isAdding && (
              <tr className="bg-blue-50">
                <td className="border border-gray-300 px-4 py-2">
                  <select
                    value={newSkill.queueId}
                    onChange={(e) => setNewSkill({ ...newSkill, queueId: Number(e.target.value) })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  >
                    <option value={0}>Select queue...</option>
                    {queues.map((queue) => (
                      <option key={queue.id} value={queue.id}>
                        {queue.displayName}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    value={newSkill.efficiencyCoefficient}
                    onChange={(e) => setNewSkill({ ...newSkill, efficiencyCoefficient: Number(e.target.value) })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  />
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="number"
                    min="1"
                    value={newSkill.skillLevel}
                    onChange={(e) => setNewSkill({ ...newSkill, skillLevel: Number(e.target.value) })}
                    className="w-full rounded border px-2 py-1 text-gray-900"
                  />
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  <input
                    type="checkbox"
                    checked={newSkill.isPrimary}
                    onChange={(e) => setNewSkill({ ...newSkill, isPrimary: e.target.checked })}
                    className="h-4 w-4"
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
            {skills.map((skill) => (
              <tr key={skill.id} className="hover:bg-gray-50">
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === skill.id ? (
                    <select
                      value={editForm.queueId}
                      onChange={(e) => setEditForm({ ...editForm, queueId: Number(e.target.value) })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    >
                      <option value={0}>Select queue...</option>
                      {queues.map((queue) => (
                        <option key={queue.id} value={queue.id}>
                          {queue.displayName}
                        </option>
                      ))}
                    </select>
                  ) : (
                    skill.queueDisplayName
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === skill.id ? (
                    <input
                      type="number"
                      step="0.1"
                      min="0"
                      value={editForm.efficiencyCoefficient}
                      onChange={(e) => setEditForm({ ...editForm, efficiencyCoefficient: Number(e.target.value) })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    skill.efficiencyCoefficient
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === skill.id ? (
                    <input
                      type="number"
                      min="1"
                      value={editForm.skillLevel}
                      onChange={(e) => setEditForm({ ...editForm, skillLevel: Number(e.target.value) })}
                      className="w-full rounded border px-2 py-1 text-gray-900"
                    />
                  ) : (
                    skill.skillLevel
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2 text-gray-900">
                  {editingId === skill.id ? (
                    <input
                      type="checkbox"
                      checked={editForm.isPrimary}
                      onChange={(e) => setEditForm({ ...editForm, isPrimary: e.target.checked })}
                      className="h-4 w-4"
                    />
                  ) : (
                    skill.isPrimary ? 'Yes' : 'No'
                  )}
                </td>
                <td className="border border-gray-300 px-4 py-2">
                  {editingId === skill.id ? (
                    <>
                      <button
                        onClick={() => handleUpdate(skill.id)}
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
                        onClick={() => handleEdit(skill)}
                        className="mr-2 rounded bg-blue-500 px-3 py-1 text-white hover:bg-blue-600"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleDelete(skill.id)}
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
        {skills.length === 0 && !isAdding && (
          <div className="mt-4 text-center text-gray-500">
            No skills found. Click &quot;Add Skill&quot; to create one.
          </div>
        )}
      </div>

      <AgentAvailabilityView agentId={agentId} />
    </div>
  );
}
