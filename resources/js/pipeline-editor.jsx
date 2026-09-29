import React from 'react';
import { createRoot } from 'react-dom/client';
import PipelineEditor from './Diagram/PipelineEditor';

document.addEventListener('livewire:navigated', () => {
    window.mountPipelineEditor();
});

// Also try on initial load for non-SPA navigation or if navigated event already fired
document.addEventListener('DOMContentLoaded', () => {
    window.mountPipelineEditor();
});

window.mountPipelineEditor = function() {
    const el = document.getElementById('pipeline-editor-root');
    if (el && !el.dataset.mounted) {
        const root = createRoot(el);
        const props = JSON.parse(el.dataset.props || '{}');
        root.render(<PipelineEditor {...props} />);
        el.dataset.mounted = 'true';
    }
}
