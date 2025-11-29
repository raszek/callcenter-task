'use client';

import { useState } from 'react';
import AgentManager from './AgentManager';
import ScheduleGenerator from './ScheduleGenerator';
import HistoricalCallDataView from './HistoricalCallDataView';

type View = 'agents' | 'schedules' | 'historical-data';

export default function MainApp() {
  const [currentView, setCurrentView] = useState<View>('agents');

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="border-b border-gray-300 bg-white">
        <div className="mx-auto max-w-7xl px-4">
          <div className="flex space-x-8 py-4">
            <button
              onClick={() => setCurrentView('agents')}
              className={`px-4 py-2 font-semibold ${
                currentView === 'agents'
                  ? 'border-b-2 border-blue-500 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Agents
            </button>
            <button
              onClick={() => setCurrentView('schedules')}
              className={`px-4 py-2 font-semibold ${
                currentView === 'schedules'
                  ? 'border-b-2 border-blue-500 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Schedule Generator
            </button>
            <button
              onClick={() => setCurrentView('historical-data')}
              className={`px-4 py-2 font-semibold ${
                currentView === 'historical-data'
                  ? 'border-b-2 border-blue-500 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Historical Call Data
            </button>
          </div>
        </div>
      </nav>

      <div>
        {currentView === 'agents' && <AgentManager />}
        {currentView === 'schedules' && <ScheduleGenerator />}
        {currentView === 'historical-data' && <HistoricalCallDataView />}
      </div>
    </div>
  );
}
