import React, { useState, useCallback, useEffect, useMemo } from 'react';
import {
  ReactFlow,
  MiniMap,
  Controls,
  Background,
  useNodesState,
  useEdgesState,
  addEdge,
  MarkerType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import StepNode from './StepNode';
import Sidebar from './Sidebar';

const PipelineEditor = ({ initialSteps, variables: initialVariables, agents, actionableMethods, pipelineId, diagramState, translations }) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [variables, setVariables] = useState(initialVariables || []);
  const [selectedNodeId, setSelectedNodeId] = useState(null);

  const t = translations || {};

  useEffect(() => {
    if (initialVariables) {
        setVariables(initialVariables);
    }
  }, [initialVariables]);

  const nodeTypes = useMemo(() => ({
    step: StepNode,
  }), []);

  const defaultEdgeOptions = {
    type: 'smoothstep',
    animated: true,
    style: { stroke: '#94a3b8', strokeWidth: 2 },
    markerEnd: {
      type: MarkerType.ArrowClosed,
      color: '#94a3b8',
    },
  };

  useEffect(() => {
    let finalNodes = [];
    let finalEdges = [];

    if (diagramState && diagramState.nodes && diagramState.nodes.length > 0) {
      // Re-hydrate nodes with latest agents, actionableMethods AND step data from DB
      finalNodes = diagramState.nodes.map(node => {
        let latestStep = node.data.step;

        // If the step has an ID or UID, try to find the latest version from initialSteps (DB)
        if (latestStep && (latestStep.id || latestStep.uid)) {
            const freshStep = initialSteps.find(s => 
                (latestStep.id && s.id === latestStep.id) || 
                (latestStep.uid && s.uid === latestStep.uid)
            );
            if (freshStep) {
                latestStep = { ...freshStep };
            }
        }

        return {
          ...node,
          type: 'step',
          data: {
            ...node.data,
            step: latestStep,
            agents,
            actionableMethods,
            translations: t
          }
        };
      });
      finalEdges = diagramState.edges || [];
    } else {
      // Initialize nodes from steps if no diagram state
      finalNodes = initialSteps.map((step, index) => ({
        id: step.uid || `step-stable-${index}`, // Use UID as ID if available
        type: 'step',
        data: {
            step: { ...step },
            agents,
            actionableMethods,
            translations: t
        },
        position: { x: 100, y: index * 120 + 50 },
      }));

      finalEdges = [];
      for (let i = 0; i < finalNodes.length - 1; i++) {
        finalEdges.push({
          id: `e${i}-${i+1}`,
          source: finalNodes[i].id,
          target: finalNodes[i+1].id,
          ...defaultEdgeOptions
        });
      }
    }

    // Map next_step_on_true/false from order (integer) to node ID (string)
    // This handles both initial load and older diagram saves
    finalNodes.forEach(node => {
        const step = { ...node.data.step };
        let modified = false;

        if (step.next_step_on_true !== null && step.next_step_on_true !== undefined && step.next_step_on_true !== '') {
            if (typeof step.next_step_on_true === 'number' || (!isNaN(parseInt(step.next_step_on_true)) && !String(step.next_step_on_true).includes('step'))) {
                const target = finalNodes.find(n => n.data.step.order === parseInt(step.next_step_on_true));
                if (target) {
                    step.next_step_on_true = target.id;
                    modified = true;
                }
            }
        }
        if (step.next_step_on_false !== null && step.next_step_on_false !== undefined && step.next_step_on_false !== '') {
            if (typeof step.next_step_on_false === 'number' || (!isNaN(parseInt(step.next_step_on_false)) && !String(step.next_step_on_false).includes('step'))) {
                const target = finalNodes.find(n => n.data.step.order === parseInt(step.next_step_on_false));
                if (target) {
                    step.next_step_on_false = target.id;
                    modified = true;
                }
            }
        }

        if (modified) {
            node.data.step = step;
        }
    });

    setNodes(finalNodes);
    setEdges(finalEdges);
  }, [initialSteps, diagramState, agents, actionableMethods]);

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge({ ...params, ...defaultEdgeOptions }, eds)),
    [setEdges],
  );

  const onNodeClick = useCallback((event, node) => {
    setSelectedNodeId(node.id);
  }, []);

  const onPaneClick = useCallback(() => {
    setSelectedNodeId(null);
  }, []);

  const onUpdateNode = useCallback((id, updatedStep) => {
    setNodes((nds) =>
      nds.map((node) => {
        if (node.id === id) {
          return {
            ...node,
            data: {
              ...node.data,
              step: updatedStep,
            },
          };
        }
        return node;
      })
    );
  }, [setNodes]);

  const onDeleteNode = useCallback((id) => {
    setNodes((nds) => nds.filter((node) => node.id !== id));
    setEdges((eds) => eds.filter((edge) => edge.source !== id && edge.target !== id));
    setSelectedNodeId(null);
  }, [setNodes, setEdges]);

  const onSave = () => {
    // We need to determine the order of steps based on the flow
    // For simplicity, we can sort them by Y position or follow the edges
    // Let's use Y position for now, but following edges is better for complex pipelines
    const sortedNodes = [...nodes].sort((a, b) => a.position.y - b.position.y);

    const updatedSteps = sortedNodes.map((node, index) => {
        const step = { ...node.data.step, order: index };

        // Map next_step_on_true/false from node ID back to order
        if (step.next_step_on_true !== null && step.next_step_on_true !== undefined && step.next_step_on_true !== '' && typeof step.next_step_on_true === 'string') {
            const targetNode = sortedNodes.find(n => n.id === step.next_step_on_true);
            if (targetNode) {
                step.next_step_on_true = sortedNodes.indexOf(targetNode);
            }
        }
        if (step.next_step_on_false !== null && step.next_step_on_false !== undefined && step.next_step_on_false !== '' && typeof step.next_step_on_false === 'string') {
            const targetNode = sortedNodes.find(n => n.id === step.next_step_on_false);
            if (targetNode) {
                step.next_step_on_false = sortedNodes.indexOf(targetNode);
            }
        }

        return step;
    });

    if (window.Livewire) {
        const editorRoot = document.getElementById('pipeline-editor-root');
        const livewireComponent = editorRoot ? editorRoot.closest('[wire\\:id]') : null;

        if (livewireComponent) {
            const id = livewireComponent.getAttribute('wire:id');
            window.Livewire.find(id).saveDiagram(nodes, edges, updatedSteps, variables);
        } else {
            // Fallback to the previous method if for some reason we can't find the closest parent
            const firstComponent = document.querySelector('[wire\\:id]');
            if (firstComponent) {
                const id = firstComponent.getAttribute('wire:id');
                window.Livewire.find(id).saveDiagram(nodes, edges, updatedSteps, variables);
            }
        }
    }
  };

  const addNode = () => {
    const id = `step-new-${Date.now()}`;
    const newNode = {
      id: id,
      type: 'step',
      data: {
        step: {
            id: null,
            uid: id,
            type: 'agent',
            agent_id: '',
            class: '',
            method: '',
            method_key: '',
            order: nodes.length,
            name: `${t.step || 'Step'} ${nodes.length + 1}`,
            instructions_override: '',
            pass_output_as_input: false,
            pass_all_previous_outputs: false,
            selected_outputs: [],
            condition_if: '',
            next_step_on_true: null,
            next_step_on_false: null,
        },
        agents,
        actionableMethods,
        translations: t
      },
      position: { x: 100, y: nodes.length * 120 + 50 },
    };
    setNodes((nds) => nds.concat(newNode));
    setSelectedNodeId(id);
  };

  const selectedNode = nodes.find((n) => n.id === selectedNodeId);

  return (
    <div className="flex w-full h-[80vh] min-h-[600px] border border-gray-200 dark:border-zinc-800 rounded-lg overflow-hidden bg-white dark:bg-zinc-950">
      <div className="flex-1 flex flex-col relative">
        <div className="absolute top-4 left-4 z-10 flex gap-2">
            <button
                onClick={addNode}
                className="flex items-center gap-2 px-4 py-2 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-lg text-sm font-medium hover:bg-zinc-800 dark:hover:bg-zinc-200 shadow-sm transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                </svg>
                {t.add_step || 'Add Step'}
            </button>
        </div>

        <div className="absolute top-4 right-4 z-10">
            <button
                onClick={onSave}
                className="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-sm transition-colors"
            >
                {t.save_diagram || 'Save Diagram'}
            </button>
        </div>

        <div className="flex-1">
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeClick={onNodeClick}
            onPaneClick={onPaneClick}
            nodeTypes={nodeTypes}
            defaultEdgeOptions={defaultEdgeOptions}
            colorMode={document.documentElement.classList.contains('dark') ? 'dark' : 'light'}
            fitView
          >
            <Controls />
            <MiniMap nodeStrokeWidth={3} zoomable pannable />
            <Background variant="dots" gap={20} size={1} />
          </ReactFlow>
        </div>
      </div>

      <Sidebar
            selectedNode={selectedNode}
            onUpdateNode={onUpdateNode}
            onDeleteNode={onDeleteNode}
            agents={agents}
            actionableMethods={actionableMethods}
            variables={variables}
            onUpdateVariables={setVariables}
            nodes={nodes}
            translations={t}
        />
    </div>
  );
};

export default PipelineEditor;
