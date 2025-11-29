'use client';

import { useState } from 'react';
import AgentTable from './AgentTable';
import AgentView from './AgentView';

export default function AgentManager() {
  const [selectedAgentId, setSelectedAgentId] = useState<number | null>(null);

  if (selectedAgentId !== null) {
    return (
      <AgentView
        agentId={selectedAgentId}
        onBack={() => setSelectedAgentId(null)}
      />
    );
  }

  return <AgentTable onViewAgent={(id) => setSelectedAgentId(id)} />;
}
