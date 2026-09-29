import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

const StepNode = ({ data, selected }) => {
  const isAgent = data.step?.type === 'agent';
  const isCustom = data.step?.type === 'custom';
  const isReceiveData = data.step?.type === 'receive_data';
  const t = data.translations || {};

  let typeLabel = t.step || 'Step';
  let bgColor = 'bg-white dark:bg-zinc-800';
  let borderColor = selected ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-900/50' : 'border-gray-200 dark:border-zinc-700';

  if (isAgent) {
    typeLabel = t.agent || 'Agent';
    bgColor = 'bg-blue-50 dark:bg-blue-900/20';
  } else if (isCustom) {
    typeLabel = t.custom_method || 'Direct Action';
    bgColor = 'bg-purple-50 dark:bg-purple-900/20';
  } else if (isReceiveData) {
    typeLabel = 'Receive Data';
    bgColor = 'bg-green-50 dark:bg-green-900/20';
  }

  return (
    <div className={`px-3 py-2 shadow-sm rounded-lg border-2 ${bgColor} ${borderColor} min-w-[140px] max-w-[200px]`}>
      <Handle type="target" position={Position.Top} className="w-2 h-2 !bg-zinc-400 dark:!bg-zinc-500 border-2 border-white dark:border-zinc-800" />

      <div className="flex flex-col">
        <div className="text-[10px] font-bold text-gray-500 dark:text-zinc-400 uppercase mb-0.5">{typeLabel}</div>
        <div className="text-xs font-semibold text-gray-800 dark:text-zinc-100 truncate">
          {data.step?.name || (isAgent ? (t.unnamed_agent || 'Unnamed Agent') : isCustom ? (t.unnamed_method || 'Unnamed Action') : (t.start || 'Start'))}
        </div>
        {isAgent && data.step?.agent_id && (
            <div className="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5 truncate">
                {data.agents?.find(a => a.id == data.step.agent_id)?.name || 'Unknown Agent'}
            </div>
        )}
        {isCustom && data.step?.method_key && (
            <div className="text-[10px] text-purple-600 dark:text-purple-400 mt-0.5 truncate">
                {data.actionableMethods?.find(m => m.key === data.step.method_key)?.name || (data.step.method_key.split('@')[1] || data.step.method_key)}
            </div>
        )}
        {data.step?.condition_if && (
            <div className="mt-1 flex items-center gap-1 text-[9px] text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 px-1 py-0.5 rounded border border-orange-100 dark:border-orange-800/50 w-fit">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-2 w-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                </svg>
                {t.conditional_logic ? 'Cond.' : 'Conditional'}
            </div>
        )}
      </div>

      <Handle type="source" position={Position.Bottom} className="w-2 h-2 !bg-zinc-400 dark:!bg-zinc-500 border-2 border-white dark:border-zinc-800" />
    </div>
  );
};

export default memo(StepNode);
