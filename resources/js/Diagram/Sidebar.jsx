import React, { useState } from 'react';

const Sidebar = ({ selectedNode, onUpdateNode, onDeleteNode, agents, actionableMethods, variables, onUpdateVariables, nodes, translations }) => {
  const [showFiltersModal, setShowFiltersModal] = useState(false);
  const [showFieldsModal, setShowFieldsModal] = useState(false);
  const [showOrderByModal, setShowOrderByModal] = useState(false);
  const [showOutputsModal, setShowOutputsModal] = useState(false);
  const t = translations || {};

  // --- Variable Management (Pipeline Level) ---
  const handleAddVariable = () => {
    onUpdateVariables([...variables, { key: '', value: '' }]);
  };

  const handleUpdateVariable = (index, field, value) => {
    const newVars = [...variables];
    newVars[index] = { ...newVars[index], [field]: value };
    onUpdateVariables(newVars);
  };

  const handleRemoveVariable = (index) => {
    onUpdateVariables(variables.filter((_, i) => i !== index));
  };

  if (!selectedNode) {
    return (
      <div className="w-80 border-l border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 p-4 overflow-y-auto">
        <div className="mb-6">
            <h3 className="text-base font-bold mb-1 text-zinc-800 dark:text-zinc-100">{t.pipeline_settings || 'Pipeline Settings'}</h3>
            <p className="text-[10px] text-zinc-500 dark:text-zinc-400 mb-4">{t.variables_subtitle || 'Global configuration for this pipeline.'}</p>
        </div>

        <div className="space-y-6">
            <div>
                <div className="flex items-center justify-between mb-2">
                    <label className="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{t.variables || 'Variables'}</label>
                    <button
                        onClick={handleAddVariable}
                        className="text-[10px] px-2 py-0.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors flex items-center gap-1"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                        </svg>
                        {t.add_variable || 'Add'}
                    </button>
                </div>

                {variables.length === 0 ? (
                    <div className="text-center py-4 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-lg text-[10px] text-zinc-400 dark:text-zinc-500">
                        {t.no_variables || 'No variables defined'}
                    </div>
                ) : (
                    <div className="space-y-2">
                        {variables.map((v, i) => (
                            <div key={i} className="flex gap-1.5 items-start">
                                <div className="flex-1 space-y-1">
                                    <input
                                        type="text"
                                        placeholder={t.variable_key || "Key"}
                                        className="w-full px-2 py-1 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-[10px] focus:ring-1 focus:ring-blue-500 outline-none"
                                        value={v.key}
                                        onChange={(e) => handleUpdateVariable(i, 'key', e.target.value)}
                                    />
                                    <input
                                        type="text"
                                        placeholder={t.variable_value || "Value"}
                                        className="w-full px-2 py-1 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-[10px] focus:ring-1 focus:ring-blue-500 outline-none"
                                        value={v.value}
                                        onChange={(e) => handleUpdateVariable(i, 'value', e.target.value)}
                                    />
                                </div>
                                <button
                                    onClick={() => handleRemoveVariable(i)}
                                    className="p-1 text-zinc-400 dark:text-zinc-500 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        ))}
                    </div>
                )}
                <div className="mt-3 p-2 bg-blue-50 dark:bg-blue-900/30 rounded border border-blue-100 dark:border-blue-800">
                    <p className="text-[9px] text-blue-700 dark:text-blue-300 leading-tight">
                        {t.variables_help || 'Variables can be used in instructions using {{ key }} syntax.'}
                    </p>
                </div>
            </div>
        </div>

        <div className="mt-12 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-center">
            <p className="text-[10px] text-zinc-400 dark:text-zinc-500">{t.select_node_hint || 'Select a node to edit its properties.'}</p>
        </div>
      </div>
    );
  }

  // --- Step Edit (Node Selected) ---
  const { step } = selectedNode.data;

  const handleChange = (field, value) => {
    const updatedStep = { ...step, [field]: value };

    // Special handling for method_key to update class/method and prefill parameters
    if (field === 'method_key') {
        const methodData = actionableMethods[value];
        if (methodData) {
            updatedStep.type = 'custom';
            updatedStep.class = methodData.class;
            updatedStep.method = methodData.method;

            // Prefill parameters if it's a new method selection
            if (methodData.parameters) {
                const prefilled = Object.entries(methodData.parameters).map(([name, paramData]) => ({
                    name,
                    type: paramData.type,
                    value: `(${paramData.type})`,
                    description: paramData.description
                }));
                updatedStep.instructions_override = JSON.stringify(prefilled);
            }
        } else {
            updatedStep.class = '';
            updatedStep.method = '';
            updatedStep.type = 'agent';
        }
    }

    onUpdateNode(selectedNode.id, updatedStep);
  };

  // Helper for JSON parameters
  let parameters = [];
  try {
    const decoded = JSON.parse(step.instructions_override || '[]');
    parameters = Array.isArray(decoded) ? decoded : [];
  } catch (e) {
    // If not valid JSON, it might be a plain string (for Agent type)
    parameters = [];
  }

  const handleUpdateParameter = (index, field, value) => {
    const newParams = [...parameters];
    newParams[index] = { ...newParams[index], [field]: value };
    handleChange('instructions_override', JSON.stringify(newParams));
  };

  const handleAddParameter = () => {
    const newParams = [...parameters, { name: '', type: 'string', value: '', description: '' }];
    handleChange('instructions_override', JSON.stringify(newParams));
  };

  const handleRemoveParameter = (index) => {
    const newParams = parameters.filter((_, i) => i !== index);
    handleChange('instructions_override', JSON.stringify(newParams));
  };

  return (
    <div className="w-80 border-l border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 p-4 overflow-y-auto shadow-inner">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-bold text-zinc-800 dark:text-zinc-100">{t.step_properties || 'Edit Step'}</h3>
        <span className="text-[9px] font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-zinc-500 dark:text-zinc-400">{selectedNode.id}</span>
      </div>

      <div className="space-y-5">
        {/* Basic Info */}
        <div className="space-y-3">
            <div>
            <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">{t.step_name || 'Step Name'}</label>
            <input
                type="text"
                className="w-full px-2 py-1.5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-xs focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                value={step.name || ''}
                onChange={(e) => handleChange('name', e.target.value)}
                placeholder={t.step_name_placeholder || "e.g. Summarizer"}
            />
            </div>

            <div>
            <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">Type</label>
            <select
                className="w-full px-2 py-1.5 border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-xs outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                value={step.type}
                onChange={(e) => handleChange('type', e.target.value)}
                disabled={step.type === 'receive_data'}
            >
                <option value="agent">{t.agent || 'Agent'}</option>
                <option value="custom">{t.custom_method || 'Direct Action'}</option>
                <option value="receive_data" disabled>Receive Data</option>
            </select>
            </div>

            {step.type === 'agent' && (
            <div>
                <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">{t.select_agent || 'Select Agent'}</label>
                <select
                className="w-full px-2 py-1.5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-xs outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                value={step.agent_id || ''}
                onChange={(e) => handleChange('agent_id', e.target.value)}
                >
                <option value="">{t.select_agent || 'Select...'}</option>
                {agents.map((agent) => (
                    <option key={agent.id} value={agent.id}>{agent.name}</option>
                ))}
                </select>
            </div>
            )}

            {step.type === 'custom' && (
            <div>
                <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">{t.select_method || 'Select Action'}</label>
                <select
                className="w-full px-2 py-1.5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-xs outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                value={step.method_key || ''}
                onChange={(e) => handleChange('method_key', e.target.value)}
                >
                <option value="">{t.select_method || 'Select...'}</option>
                {Object.entries(Object.entries(actionableMethods).reduce((acc, [key, data]) => {
                    const category = data.category || 'General';
                    if (!acc[category]) acc[category] = [];
                    acc[category].push({ key, ...data });
                    return acc;
                }, {})).sort(([a], [b]) => a.localeCompare(b)).map(([category, methods]) => (
                    <optgroup key={category} label={category}>
                        {methods.map(({ key, name }) => (
                            <option key={key} value={key}>{name}</option>
                        ))}
                    </optgroup>
                ))}
                </select>
            </div>
            )}
        </div>

        {/* Data Flow */}
        <div className="pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-2">
            <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">{t.data_flow || 'Data Flow'}</label>

            <div className="bg-zinc-50 dark:bg-zinc-900 p-2 rounded space-y-2 border border-zinc-100 dark:border-zinc-800">
                {step.order > 0 && (
                    <div className="flex items-center gap-2">
                         <button
                            onClick={() => setShowOutputsModal(true)}
                            className="w-full px-2 py-1.5 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded text-[10px] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors flex items-center justify-between group"
                        >
                            <span className="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 text-zinc-400 group-hover:text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                </svg>
                                {t.select_outputs || 'Select Outputs'}
                            </span>
                            <span className="bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded-full text-[9px] font-bold">
                                {(step.selected_outputs || []).length}
                            </span>
                        </button>
                    </div>
                )}
            </div>
        </div>

        {/* Parameters / Instructions */}
        <div className="pt-4 border-t border-zinc-200 dark:border-zinc-800">
          {step.type === 'custom' ? (
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">{t.parameters || 'Parameters'}</label>
                    <button
                        onClick={handleAddParameter}
                        className="text-[9px] px-1.5 py-0.5 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                    >
                        + {t.add || 'Add'}
                    </button>
                </div>

                {parameters.length === 0 ? (
                    <p className="text-[10px] text-zinc-400 dark:text-zinc-500 italic">{t.no_parameters || 'No parameters defined.'}</p>
                ) : (
                    <div className="space-y-2">
                        {parameters.map((p, i) => (
                            <div key={i} className="p-2 bg-zinc-50 dark:bg-zinc-900 rounded border border-zinc-100 dark:border-zinc-800 space-y-1.5 relative group">
                                <button
                                    onClick={() => handleRemoveParameter(i)}
                                    className="absolute top-1.5 right-1.5 text-zinc-300 dark:text-zinc-600 hover:text-red-500 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>
                                <div className="grid grid-cols-2 gap-1.5">
                                    <input
                                        placeholder="Name"
                                        className="px-1.5 py-0.5 border border-zinc-200 dark:border-zinc-700 rounded text-[10px] bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 outline-none focus:ring-1 focus:ring-blue-500"
                                        value={p.name}
                                        onChange={(e) => handleUpdateParameter(i, 'name', e.target.value)}
                                    />
                                    <select
                                        className="px-1.5 py-0.5 border border-zinc-200 dark:border-zinc-700 rounded text-[10px] bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 outline-none focus:ring-1 focus:ring-blue-500"
                                        value={p.type}
                                        onChange={(e) => handleUpdateParameter(i, 'type', e.target.value)}
                                    >
                                        <option value="string">String</option>
                                        <option value="integer">Integer</option>
                                        <option value="boolean">Boolean</option>
                                        <option value="array">Array</option>
                                    </select>
                                </div>
                                <textarea
                                    placeholder="Value"
                                    className="w-full px-1.5 py-0.5 border border-zinc-200 dark:border-zinc-700 rounded text-[10px] bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 h-10 outline-none focus:ring-1 focus:ring-blue-500"
                                    value={p.value}
                                    onChange={(e) => handleUpdateParameter(i, 'value', e.target.value)}
                                />
                                {p.description && (
                                    <div className="mt-1 text-[9px] text-zinc-500 italic leading-tight">{p.description}</div>
                                )}
                                {p.name === 'filters' && (
                                    <div className="mt-1 flex items-center gap-2">
                                        <button
                                            onClick={() => setShowFiltersModal(true)}
                                            className="text-[9px] text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                            </svg>
                                            {t.learn_more_filters || 'Learn more about filters'}
                                        </button>
                                    </div>
                                )}
                                {p.name === 'fields' && (
                                    <div className="mt-1 flex items-center gap-2">
                                        <button
                                            onClick={() => setShowFieldsModal(true)}
                                            className="text-[9px] text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                            </svg>
                                            {t.learn_more_fields || 'Learn more about fields'}
                                        </button>
                                    </div>
                                )}
                                {p.name === 'order_by' && (
                                    <div className="mt-1 flex items-center gap-2">
                                        <button
                                            onClick={() => setShowOrderByModal(true)}
                                            className="text-[9px] text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                                            </svg>
                                            {t.learn_more_order_by || 'Learn more about order_by'}
                                        </button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
          ) : (
            <>
                <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1 uppercase tracking-wider">{t.instructions_override || 'Instructions'}</label>
                <textarea
                    className="w-full px-2 py-1.5 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded text-xs h-24 outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                    value={step.instructions_override || ''}
                    onChange={(e) => handleChange('instructions_override', e.target.value)}
                    placeholder={t.instructions_override_placeholder || "Custom instructions..."}
                />
            </>
          )}
        </div>

        {/* Conditional Logic */}
        <div className="pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-3">
            <label className="block text-[10px] font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">{t.conditional_logic || 'Conditional Logic'}</label>

            <div>
                <label className="block text-[9px] text-zinc-500 dark:text-zinc-400 mb-0.5">{t.condition_if || 'Condition'}</label>
                <input
                    type="text"
                    className="w-full px-2 py-1 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded text-[10px] outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                    value={step.condition_if || ''}
                    onChange={(e) => handleChange('condition_if', e.target.value)}
                    placeholder={t.condition_if_placeholder || "contains..."}
                />
            </div>

            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="block text-[9px] text-zinc-500 dark:text-zinc-400 mb-0.5">{t.next_step_on_true ? 'True GOTO' : 'True'}</label>
                    <select
                        className="w-full px-1.5 py-1 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded text-[9px] outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                        value={step.next_step_on_true || ''}
                        onChange={(e) => handleChange('next_step_on_true', e.target.value)}
                    >
                        <option value="">{t.next_step_default || 'Default'}</option>
                        {nodes.filter(n => n.id !== selectedNode.id).map(n => (
                            <option key={n.id} value={n.id}>
                                {n.data.step.name || 'Unnamed Step'}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-[9px] text-zinc-500 dark:text-zinc-400 mb-0.5">{t.next_step_on_false ? 'False GOTO' : 'False'}</label>
                    <select
                        className="w-full px-1.5 py-1 border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 rounded text-[9px] outline-none focus:ring-1 focus:ring-blue-500 transition-all"
                        value={step.next_step_on_false || ''}
                        onChange={(e) => handleChange('next_step_on_false', e.target.value)}
                    >
                        <option value="">{t.next_step_default || 'Default'}</option>
                        {nodes.filter(n => n.id !== selectedNode.id).map(n => (
                            <option key={n.id} value={n.id}>
                                {n.data.step.name || 'Unnamed Step'}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
        </div>

        {/* Actions */}
        <div className="pt-6 border-t border-zinc-200 dark:border-zinc-800 mt-4">
          <button
            onClick={() => {
                if (confirm(t.confirm_delete_step || 'Delete this step?')) {
                    onDeleteNode(selectedNode.id);
                }
            }}
            className="w-full px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-100 dark:border-red-900/30 rounded text-xs font-semibold hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors flex items-center justify-center gap-2"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
            {t.delete_step || 'Delete Step'}
          </button>
        </div>
      </div>

      {/* Filters Documentation Modal */}
      {showFiltersModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col border border-zinc-200 dark:border-zinc-800 animate-in fade-in zoom-in duration-200">
            <div className="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-950/50">
              <h3 className="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                </svg>
                {t.filter_documentation || 'Filter Documentation'}
              </h3>
              <button
                onClick={() => setShowFiltersModal(false)}
                className="p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-all"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-8">
              <p className="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                {t.filters_description || 'Filters allow you to retrieve specific resources based on their attributes and metadata. You can enter one filter per line or use complex nested logic.'}
              </p>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">Standard Operators</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>price &gt; 100</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>status = 'active'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>quantity &lt;= 10</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>type != 'legacy'</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">Text Matching</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>name contains 'John'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>sku starts with 'ABC'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>email ends with '@gmail.com'</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">Null & Empty</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>email is null</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>phone is not null</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>address is empty</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>notes is not empty</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">Relative Dates</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>created_at &lt; '7 days ago'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>updated_at &gt; '2 weeks ago'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>scheduled_at = 'today'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>finished_at &lt; 'now'</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">JSON Fields</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>data.category = 'electronics'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>meta.source = 'web'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>profile.age &gt;= 18</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-3 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-widest">Grouping</h4>
                  <ul className="text-xs space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>OR status = 'pending'</code></li>
                    <li className="flex justify-between items-center bg-zinc-50 dark:bg-zinc-950/50 p-1.5 rounded"><code>AND price &lt; 50</code></li>
                    <li className="flex flex-col bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded gap-1">
                      <code className="text-[10px]">[('f1 = v1', 'OR f1 = v2'),</code>
                      <code className="text-[10px]"> 'AND f2 = v3']</code>
                    </li>
                  </ul>
                </section>
              </div>

              <div className="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-900/30">
                <p className="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                  <strong>Tip:</strong> You can enter filters one per line for simple AND logic. For complex grouping, use nested arrays. String values should be quoted if they contain spaces or special characters.
                </p>
              </div>
            </div>

            <div className="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950/50 flex justify-end">
              <button
                onClick={() => setShowFiltersModal(false)}
                className="px-4 py-2 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-lg text-sm font-semibold hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-all"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Fields Documentation Modal */}
      {showFieldsModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div className="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col border border-zinc-200 dark:border-zinc-800">
            <div className="p-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-950">
              <h3 className="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                </svg>
                {t.fields_documentation || 'Fields Documentation'}
              </h3>
              <button
                onClick={() => setShowFieldsModal(false)}
                className="p-1 hover:bg-zinc-200 dark:hover:bg-zinc-800 rounded transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-zinc-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-6">
              <p className="text-xs text-zinc-600 dark:text-zinc-400">
                {t.fields_description || 'The fields parameter allows you to specify which columns to retrieve from the database. Enter one field name per line.'}
              </p>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">{t.resources_fields || 'Resources Fields'}</h4>
                  <ul className="text-[10px] space-y-1.5 text-zinc-600 dark:text-zinc-400">
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">id</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">name</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">description</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">data</code> (JSON)</li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">created_at</code></li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">{t.scraped_data_fields || 'Scraped Data Fields'}</h4>
                  <ul className="text-[10px] space-y-1.5 text-zinc-600 dark:text-zinc-400">
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">id</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">url</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">title</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">content</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">published_at</code></li>
                    <li><code className="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">data</code> (JSON)</li>
                  </ul>
                </section>
              </div>

              <div className="mt-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800">
                  <p className="text-[10px] text-zinc-500 italic">
                      <strong>Tip:</strong> {t.fields_tip || 'If you leave this field empty, all available fields will be retrieved by default.'}
                  </p>
              </div>
            </div>

            <div className="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 text-right">
              <button
                onClick={() => setShowFieldsModal(false)}
                className="px-4 py-1.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-lg text-xs font-bold hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors"
              >
                {t.close || 'Close'}
              </button>
            </div>
          </div>
        </div>
      )}
      {/* Order By Documentation Modal */}
      {showOrderByModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div className="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col border border-zinc-200 dark:border-zinc-800">
            <div className="p-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-950">
              <h3 className="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M3 3a1 1 0 000 2h11a1 1 0 100-2H3zM3 7a1 1 0 000 2h7a1 1 0 100-2H3zM3 11a1 1 0 100 2h4a1 1 0 100-2H3zM15 8a1 1 0 10-2 0v5.586l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L15 13.586V8z" />
                </svg>
                {t.order_by_documentation || 'Order By Documentation'}
              </h3>
              <button
                onClick={() => setShowOrderByModal(false)}
                className="p-1 hover:bg-zinc-200 dark:hover:bg-zinc-800 rounded transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-zinc-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-6">
              <p className="text-xs text-zinc-600 dark:text-zinc-400">
                {t.order_by_description || 'The order_by parameter is an array of [field, direction] pairs that control the sort order of the results.'}
              </p>

              <div className="space-y-4">
                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Format</h4>
                  <ul className="text-[10px] space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["field", "asc"]]</code>
                      <span className="ml-2 text-zinc-400">— sort by field ascending</span>
                    </li>
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["field", "desc"]]</code>
                      <span className="ml-2 text-zinc-400">— sort by field descending</span>
                    </li>
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["field"]]</code>
                      <span className="ml-2 text-zinc-400">— direction defaults to asc</span>
                    </li>
                  </ul>
                </section>

                <section>
                  <h4 className="font-bold text-zinc-900 dark:text-zinc-100 mb-2 border-b border-zinc-200 dark:border-zinc-800 pb-1 uppercase text-[10px] tracking-wider">Examples</h4>
                  <ul className="text-[10px] space-y-2 text-zinc-600 dark:text-zinc-400">
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["created_at", "desc"]]</code>
                      <span className="ml-2 text-zinc-400">— newest first</span>
                    </li>
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["price", "asc"]]</code>
                      <span className="ml-2 text-zinc-400">— cheapest first</span>
                    </li>
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["date_last_analysis", "asc"], ["id", "desc"]]</code>
                      <span className="ml-2 text-zinc-400">— multiple sort columns</span>
                    </li>
                    <li className="bg-zinc-50 dark:bg-zinc-950/50 p-2 rounded">
                      <code>[["seo_score", "desc"]]</code>
                      <span className="ml-2 text-zinc-400">— JSON data field, highest first</span>
                    </li>
                  </ul>
                </section>
              </div>

              <div className="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/30">
                <p className="text-[10px] text-blue-700 dark:text-blue-300 leading-relaxed">
                  <strong>Tip:</strong> {t.order_by_tip || 'You can sort by any column or JSON data field. Use multiple pairs for multi-column sorting. If omitted, results are returned in database default order.'}
                </p>
              </div>
            </div>

            <div className="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 text-right">
              <button
                onClick={() => setShowOrderByModal(false)}
                className="px-4 py-1.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-lg text-xs font-bold hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors"
              >
                {t.close || 'Close'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Select Previous Outputs Modal */}
      {showOutputsModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col border border-zinc-200 dark:border-zinc-800">
            <div className="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between bg-zinc-50 dark:bg-zinc-950">
              <h3 className="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                </svg>
                {t.select_outputs || 'Select Previous Outputs'}
              </h3>
              <button
                onClick={() => setShowOutputsModal(false)}
                className="p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-all"
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
              </button>
            </div>

            <div className="p-6 overflow-y-auto">
                <p className="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    Select which previous step outputs should be prepended to this step's input. They will be wrapped in <code>[OUTPUT STEP X]</code> tags.
                </p>

                <div className="space-y-2">
                    {nodes
                        .filter(n => n.data.step.order < step.order)
                        .sort((a, b) => a.data.step.order - b.data.step.order)
                        .map(n => {
                            const prevStep = n.data.step;
                            const isSelected = (step.selected_outputs || []).includes(prevStep.order);

                            return (
                                <div
                                    key={n.id}
                                    onClick={() => {
                                        const current = step.selected_outputs || [];
                                        const next = isSelected
                                            ? current.filter(o => o !== prevStep.order)
                                            : [...current, prevStep.order];
                                        handleChange('selected_outputs', next);
                                    }}
                                    className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                                        isSelected
                                            ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                                            : 'bg-white dark:bg-zinc-950 border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700'
                                    }`}
                                >
                                    <div className={`w-5 h-5 rounded border flex items-center justify-center transition-all ${
                                        isSelected
                                            ? 'bg-blue-600 border-blue-600 text-white'
                                            : 'bg-zinc-100 dark:bg-zinc-800 border-zinc-300 dark:border-zinc-700 text-transparent'
                                    }`}>
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="text-xs font-bold text-zinc-800 dark:text-zinc-100 truncate">
                                            {prevStep.name || `Step ${prevStep.order + 1}`}
                                        </div>
                                        <div className="text-[10px] text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5 mt-0.5">
                                            <span className="uppercase tracking-tighter opacity-70">Step {prevStep.order + 1}</span>
                                            <span className="w-1 h-1 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                                            <span className="truncate">{prevStep.type === 'agent' ? 'Agent' : (prevStep.type === 'custom' ? 'Method' : 'Receive Data')}</span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}

                    {nodes.filter(n => n.data.step.order < step.order).length === 0 && (
                        <div className="text-center py-12 bg-zinc-50 dark:bg-zinc-950/50 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                            <p className="text-sm text-zinc-400 dark:text-zinc-500">No previous steps found.</p>
                        </div>
                    )}
                </div>
            </div>

            <div className="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex justify-between items-center">
                <div className="text-[10px] text-zinc-500 dark:text-zinc-400">
                    {t.selected_outputs_count ? t.selected_outputs_count.replace(':count', (step.selected_outputs || []).length) : `${(step.selected_outputs || []).length} outputs selected`}
                </div>
                <button
                    onClick={() => setShowOutputsModal(false)}
                    className="px-6 py-2 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-lg text-sm font-bold hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors"
                >
                    {t.close || 'Done'}
                </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Sidebar;
