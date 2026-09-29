<div class="max-w-4xl mx-auto py-10 px-6">
    <div class="space-y-6">
        {{-- Title --}}
        <flux:heading size="xl" level="1">{{ __('agentoom-core::docs.features.eu_ai_act.title') }}</flux:heading>

        <flux:text size="lg" class="text-zinc-600 dark:text-zinc-400">
            {{ __('agentoom-core::docs.features.eu_ai_act.description') }}
        </flux:text>

        {{-- Disclaimer --}}
        <flux:card class="border-l-4 border-amber-500 bg-amber-50/50 dark:bg-amber-950/20 space-y-3">
            <flux:heading size="md" class="text-amber-800 dark:text-amber-400">Important Disclaimer</flux:heading>
            <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                Agentoom is an AI Operating System with built-in governance, auditability, oversight, transparency, monitoring, and compliance-support capabilities. The features described in this document are <strong>technical tools that support governance and compliance readiness</strong>. They do not constitute legal advice, and they do not guarantee that any deployment of Agentoom is fully compliant with the EU AI Act or any other regulation. Operators remain responsible for conducting their own legal assessment, registering with relevant authorities, performing conformity assessments, and fulfilling all applicable obligations under Regulation (EU) 2024/1689.
            </flux:text>
            <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                Agentoom positions itself as <strong>an AI Operating System with built-in governance-support capabilities</strong> — not as a compliance guarantee, certification body, or legal advisor.
            </flux:text>
        </flux:card>

        {{-- Table of Contents --}}
        <flux:card class="space-y-3">
            <flux:heading size="md">Table of Contents</flux:heading>
            <ol class="space-y-1 text-sm list-decimal list-inside">
                <li><a href="#activation" class="text-blue-600 dark:text-blue-400 hover:underline">Activation and Deactivation</a></li>
                <li><a href="#deployment" class="text-blue-600 dark:text-blue-400 hover:underline">Full Deployment Strategy</a></li>
                <li><a href="#workflow" class="text-blue-600 dark:text-blue-400 hover:underline">Governance Workflow</a></li>
                <li><a href="#profiles" class="text-blue-600 dark:text-blue-400 hover:underline">Governance Profiles</a></li>
                <li><a href="#deployments" class="text-blue-600 dark:text-blue-400 hover:underline">Governed Deployments</a></li>
                <li><a href="#wizard" class="text-blue-600 dark:text-blue-400 hover:underline">Governance Assessment Wizard</a></li>
                <li><a href="#classification" class="text-blue-600 dark:text-blue-400 hover:underline">Classification Engine</a></li>
                <li><a href="#controls" class="text-blue-600 dark:text-blue-400 hover:underline">Governance Control Library</a></li>
                <li><a href="#risk-assessments" class="text-blue-600 dark:text-blue-400 hover:underline">Risk Assessments</a></li>
                <li><a href="#audit-logs" class="text-blue-600 dark:text-blue-400 hover:underline">Audit Logs and Tamper Evidence</a></li>
                <li><a href="#emergency-stops" class="text-blue-600 dark:text-blue-400 hover:underline">Emergency Stops</a></li>
                <li><a href="#incidents" class="text-blue-600 dark:text-blue-400 hover:underline">Incident Management</a></li>
                <li><a href="#evaluations" class="text-blue-600 dark:text-blue-400 hover:underline">Performance Evaluations</a></li>
                <li><a href="#pending-decisions" class="text-blue-600 dark:text-blue-400 hover:underline">Human-in-the-Loop Decisions</a></li>
                <li><a href="#alerts" class="text-blue-600 dark:text-blue-400 hover:underline">Compliance Alerts</a></li>
                <li><a href="#reports" class="text-blue-600 dark:text-blue-400 hover:underline">Compliance Reports</a></li>
                <li><a href="#evidence" class="text-blue-600 dark:text-blue-400 hover:underline">Evidence Export</a></li>
                <li><a href="#disclosure" class="text-blue-600 dark:text-blue-400 hover:underline">AI Disclosure</a></li>
                <li><a href="#privacy" class="text-blue-600 dark:text-blue-400 hover:underline">Data Protection and Privacy</a></li>
                <li><a href="#dashboard" class="text-blue-600 dark:text-blue-400 hover:underline">Compliance Dashboard</a></li>
                <li><a href="#config" class="text-blue-600 dark:text-blue-400 hover:underline">Configuration Reference</a></li>
                <li><a href="#framework" class="text-blue-600 dark:text-blue-400 hover:underline">Framework Mappings</a></li>
                <li><a href="#contact" class="text-blue-600 dark:text-blue-400 hover:underline">Contact and Review</a></li>
            </ol>
        </flux:card>

        {{-- 1. Activation --}}
        <section id="activation">
            <flux:card class="space-y-4">
                <flux:heading size="lg">1. Activation and Deactivation</flux:heading>
                <flux:text>The EU AI Act compliance system is fully configurable via environment variables and the Global App settings UI. By default, all compliance features are <strong>disabled</strong> for non-EU deployments.</flux:text>

                <flux:heading size="md">Master Switch</flux:heading>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono">
                    EU_COMPLIANCE_ENABLED=true
                </div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">When <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">EU_COMPLIANCE_ENABLED=false</code> (default), ALL compliance runtime enforcement is bypassed. Compliance UI pages remain accessible for manual governance management, but no automatic enforcement runs.</flux:text>

                <flux:heading size="md">Individual Feature Toggles</flux:heading>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Each feature can be independently enabled or disabled. These only take effect when the master switch is <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">true</code>. Superadmins can toggle these from the Global App → Compliance tab.</flux:text>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800">
                                <th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Environment Variable</th>
                                <th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Feature</th>
                                <th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Default</th>
                                <th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_AUDIT_LOGGING</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Audit Logging</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Creates tamper-evident audit log entries for all agent execution events</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_EMERGENCY_STOPS</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Emergency Stops</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Blocks agent execution when an active emergency stop exists for that entity</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_AUTO_INCIDENTS</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Auto-Incidents</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Automatically creates incident records when an agent run or pipeline job fails</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_AUTO_ALERTS</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Auto-Alerts</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Daily scan for overdue risk reviews, unmitigated high-risk agents, and elevated incident rates</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_GOVERNANCE_GATE</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Governance Gate</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Blocks agent execution if governance level exceeds profile threshold</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_AI_DISCLOSURE</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">AI Disclosure</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Stamps AI-generated content with disclosure notice</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono text-xs">COMPLIANCE_RISK_ENFORCEMENT</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Risk Enforcement</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">true</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Blocks high-risk agents without mitigations</td></tr>
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </section>

        {{-- 2. Deployment Strategy --}}
        <section id="deployment">
            <flux:card class="space-y-4">
                <flux:heading size="lg">2. Full Deployment Strategy</flux:heading>

                <flux:heading size="md">Non-EU / Compliance Not Required</flux:heading>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono">EU_COMPLIANCE_ENABLED=false</div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">All compliance UI pages remain accessible for voluntary governance. No runtime enforcement, no audit logging overhead, no emergency stops.</flux:text>

                <flux:heading size="md">EU Deployment — Soft Governance</flux:heading>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono">
                    EU_COMPLIANCE_ENABLED=true<br>
                    COMPLIANCE_AUDIT_LOGGING=true<br>
                    COMPLIANCE_AI_DISCLOSURE=true<br>
                    <span class="text-zinc-400 dark:text-zinc-500"># everything else false</span>
                </div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Minimal overhead: audit logs and AI disclosure only. No blocking, no automatic alerts.</flux:text>

                <flux:heading size="md">EU Deployment — Full Governance</flux:heading>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono">
                    EU_COMPLIANCE_ENABLED=true<br>
                    <span class="text-zinc-400 dark:text-zinc-500"># all features true (default)</span>
                </div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Full runtime enforcement: audit logs, emergency stops, governance gates, auto-incidents, auto-alerts, disclosure, risk enforcement.</flux:text>
            </flux:card>
        </section>

        {{-- 3. Governance Workflow --}}
        <section id="workflow">
            <flux:card class="space-y-4">
                <flux:heading size="lg">3. Governance Workflow</flux:heading>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono text-zinc-800 dark:text-zinc-200">
                    Deployment Creation
                    → Governance Assessment Wizard (business context questions)
                    → Classification Engine (governance level recommendation)
                    → Profile Assignment (Basic / Standard / Enhanced)
                    → Control Activation (audit logs, oversight, evaluations, monitoring)
                    → Ongoing Monitoring (dashboard, alerts, incidents)
                    → Evidence Export (one-click auditor package)
                </div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">This workflow supports Article 43 conformity assessments and Article 49 registration by producing auditable classification records, evidence packages, and ongoing monitoring data.</flux:text>
            </flux:card>
        </section>

        {{-- 4. Governance Profiles --}}
        <section id="profiles">
            <flux:card class="space-y-4">
                <flux:heading size="lg">4. Governance Profiles</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-zinc-100 dark:bg-zinc-800"><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Profile</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Governance Level</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Controls</th></tr></thead>
                        <tbody>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-semibold">Basic Governance</td><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">basic</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Audit logs, disclosure</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-semibold">Standard Governance</td><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">standard</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Audit logs, evaluations, incidents, periodic reviews, disclosure</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-semibold">Enhanced Governance</td><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">enhanced</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">All standard controls + human oversight, monitoring</td></tr>
                        </tbody>
                    </table>
                </div>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Admin page: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">/admin/compliance/governance-profiles</code> — full CRUD with name, description, governance level, controls checkboxes, active toggle, and deployment count.</flux:text>
            </flux:card>
        </section>

        {{-- 5. Governed Deployments --}}
        <section id="deployments">
            <flux:card class="space-y-4">
                <flux:heading size="lg">5. Governed Deployments</flux:heading>
                <flux:text>A governed deployment represents a registered AI deployment — the governance unit that ties together a technical object (an agent, pipeline, or endpoint) with its business context, governance profile, classification result, and assessment history.</flux:text>
                <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>Stores: name, business purpose, the deployable entity (agent / pipeline / endpoint), and its linked governance profile</li>
                    <li>Classification fields: governance level, potentially sensitive flag, recommended controls, and reasoning</li>
                    <li>Immutable assessment history — a complete record of all past classifications</li>
                    <li>Supports ownership tracking for accountability</li>
                </ul>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Admin page: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">/admin/compliance/governed-deployments</code></flux:text>
            </flux:card>
        </section>

        {{-- 6. Assessment Wizard --}}
        <section id="wizard">
            <flux:card class="space-y-4">
                <flux:heading size="lg">6. Governance Assessment Wizard</flux:heading>
                <flux:text>The Assessment Wizard is a multi-step business-context questionnaire that can be scoped <strong>per agent</strong> or conducted at the instance level. Each agent in an Agentoom instance can perform different actions with varying risk profiles, so assessments can be linked to specific agents for granular governance.</flux:text>
                <flux:heading size="md">Collected Information</flux:heading>
                <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>What the AI does (purpose)</li>
                    <li>What actions it can take (recommend, score, rank, approve/reject people)</li>
                    <li>Who is affected (employees, customers, applicants, students, patients, citizens)</li>
                    <li>Human review frequency (always / usually / sometimes / never)</li>
                    <li>Data processed (business, customer, employee, financial, health, children's)</li>
                    <li>Target agent (optional — leave empty for instance-level assessment)</li>
                </ul>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Admin page: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">/admin/compliance/assessment-wizard</code></flux:text>
            </flux:card>
        </section>

        {{-- 7. Classification Engine --}}
        <section id="classification">
            <flux:card class="space-y-4">
                <flux:heading size="lg">7. Classification Engine</flux:heading>
                <flux:text>The built-in classification engine analyses business-context answers from the Assessment Wizard and produces an explainable governance recommendation.</flux:text>
                <flux:text size="sm" class="text-red-600 dark:text-red-400"><strong>This service never claims:</strong> compliant / non-compliant, high-risk AI (legal determination), or certified.</flux:text>
                <flux:heading size="md">Outputs</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-zinc-100 dark:bg-zinc-800"><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Output</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Values</th></tr></thead>
                        <tbody>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Governance Level</td><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">basic / standard / enhanced</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Potentially Sensitive</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">yes / no</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Recommended Controls</td><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">audit_logs, human_oversight, evaluations, disclosure, monitoring, periodic_reviews</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Reasoning</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Human-readable explanation of each escalation</td></tr>
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </section>

        {{-- 8. Control Library --}}
        <section id="controls">
            <flux:card class="space-y-4">
                <flux:heading size="lg">8. Governance Control Library</flux:heading>
                <flux:text>The governance control library stores informational mappings between the platform's features and recognized governance frameworks.</flux:text>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-zinc-100 dark:bg-zinc-800"><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Framework</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Purpose</th></tr></thead>
                        <tbody>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">agentoom</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Native Agentoom governance controls (AGT-01 through AGT-10)</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">iso_42001</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">ISO/IEC 42001 AI Management System mappings</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700 font-mono">nist_ai_rmf</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">NIST AI Risk Management Framework mappings</td></tr>
                        </tbody>
                    </table>
                </div>
                <flux:text size="sm" class="text-amber-600 dark:text-amber-400">These are informational mappings only. They do not constitute ISO 42001 certification, NIST conformance, or any form of legal compliance claim.</flux:text>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Admin page: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">/admin/compliance/governance-controls</code></flux:text>
            </flux:card>
        </section>

        {{-- 9-20: Brief sections --}}
        @php
        $sections = [
            ['id' => 'risk-assessments', 'num' => 9, 'title' => 'Risk Assessments',
             'text' => 'Polymorphic risk assessments with immutable version history supporting tracked levels: unknown, under_review, minimal, limited, potential_high_risk. Each assessment auto-calculates a next review due date based on the configured review interval.',
             'admin' => '/admin/compliance/risk-assessments'],
            ['id' => 'audit-logs', 'num' => 10, 'title' => 'Audit Logs and Tamper Evidence',
             'text' => 'Every agent action is traced with step-level detail (model, tokens, execution time). Tamper-evident audit log entries are created automatically with hash-based integrity verification, ensuring any alteration is detectable.',
             'admin' => '/admin/compliance/audit-log'],
            ['id' => 'emergency-stops', 'num' => 11, 'title' => 'Emergency Stops (Article 83)',
             'text' => 'Emergency stops block agent execution at runtime when activated. Stops can be created for any entity type (agent, pipeline, task, endpoint). Runtime checks are enforced at all entry points when the emergency stops feature is enabled.',
             'admin' => '/admin/compliance/emergency-stops'],
            ['id' => 'incidents', 'num' => 12, 'title' => 'Incident Management (Article 73)',
             'text' => 'Incidents are created automatically on job failure when auto-incidents are enabled, or manually by operators. Tracked with severity, status (open/investigation/resolved/closed), and affected entity.',
             'admin' => '/admin/compliance/incidents'],
            ['id' => 'evaluations', 'num' => 13, 'title' => 'Performance Evaluations',
             'text' => 'Record evaluation scores for AI model outputs. Supports accuracy, fairness, robustness, and custom metrics.',
             'admin' => '/admin/compliance/evaluations'],
            ['id' => 'pending-decisions', 'num' => 14, 'title' => 'Human-in-the-Loop Decisions',
             'text' => 'The Human-in-the-Loop pending decisions system provides a review queue for decisions requiring human authorization. Supports approve, reject, and override workflows.',
             'admin' => '/admin/compliance/pending-decisions'],
            ['id' => 'alerts', 'num' => 15, 'title' => 'Compliance Alerts',
             'text' => 'Auto-generated daily when enabled: overdue risk assessment reviews, high-risk agents without mitigations, elevated incident rate (>5 open high/critical incidents in 7 days). Manual alerts also supported.',
             'admin' => '/admin/compliance/alerts'],
            ['id' => 'reports', 'num' => 16, 'title' => 'Compliance Reports',
             'text' => 'Compliance reports aggregate incidents, audit logs, evaluations, and pending decisions into structured records. Report generation can be scheduled with configurable frequency and reporting period.',
             'admin' => '/admin/compliance/reports'],
            ['id' => 'evidence', 'num' => 17, 'title' => 'Evidence Export',
             'text' => 'One-click export of all audit evidence for a given time period into a structured package suitable for auditor review. Exports include audit logs, incident records, evaluations, risk assessments, and alert history.',
             'admin' => null],
            ['id' => 'disclosure', 'num' => 18, 'title' => 'AI Disclosure',
             'text' => 'When AI disclosure is enabled, all AI-generated conversations are automatically stamped with a disclosure notice before being stored, ensuring end users are aware they are interacting with an AI system.',
             'admin' => null],
            ['id' => 'privacy', 'num' => 19, 'title' => 'Data Protection and Privacy',
             'text' => 'Agentoom includes a built-in anonymizing layer for PII detection and anonymization. Privacy redaction automatically removes personal data from agent prompts. Privacy rules are fully configurable (admin UI).',
             'admin' => '/admin/privacy-recognizers'],
            ['id' => 'dashboard', 'num' => 20, 'title' => 'Compliance Dashboard',
             'text' => 'Central overview of compliance status: open incidents, pending decisions, active emergency stops, upcoming risk review deadlines, alert counts, and evaluation metrics.',
             'admin' => '/admin/compliance/dashboard'],
        ];
        @endphp

        @foreach($sections as $section)
            <section id="{{ $section['id'] }}">
                <flux:card class="space-y-3">
                    <flux:heading size="lg">{{ $section['num'] }}. {{ $section['title'] }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $section['text'] }}</flux:text>
                    @if($section['admin'])
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Admin page: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">{{ $section['admin'] }}</code></flux:text>
                    @endif
                </flux:card>
            </section>
        @endforeach

        {{-- 21. Configuration Reference --}}
        <section id="config">
            <flux:card class="space-y-4">
                <flux:heading size="lg">21. Configuration Reference</flux:heading>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">All compliance configuration is managed through the Global App settings UI (Compliance tab) for superadmins, and can also be set via environment variables. The system reads settings from the database first, falling back to environment variables as defaults.</flux:text>
                <div class="bg-zinc-100 dark:bg-zinc-800 rounded p-4 text-sm font-mono overflow-x-auto text-zinc-800 dark:text-zinc-200">
'compliance' => [<br>
&nbsp;&nbsp;'enabled' => env('EU_COMPLIANCE_ENABLED', false),<br>
&nbsp;&nbsp;'features' => [<br>
&nbsp;&nbsp;&nbsp;&nbsp;'audit_logging'    => env('COMPLIANCE_AUDIT_LOGGING', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'emergency_stops'  => env('COMPLIANCE_EMERGENCY_STOPS', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'auto_incidents'   => env('COMPLIANCE_AUTO_INCIDENTS', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'auto_alerts'      => env('COMPLIANCE_AUTO_ALERTS', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'governance_gate'  => env('COMPLIANCE_GOVERNANCE_GATE', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'ai_disclosure'    => env('COMPLIANCE_AI_DISCLOSURE', true),<br>
&nbsp;&nbsp;&nbsp;&nbsp;'risk_enforcement' => env('COMPLIANCE_RISK_ENFORCEMENT', true),<br>
&nbsp;&nbsp;],<br>
],
                </div>
            </flux:card>
        </section>

        {{-- 22. Framework Mappings --}}
        <section id="framework">
            <flux:card class="space-y-4">
                <flux:heading size="lg">22. EU AI Act Framework Mappings</flux:heading>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Agentoom features map to EU AI Act articles and recitals as follows (informational, not legal advice):</flux:text>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead><tr class="bg-zinc-100 dark:bg-zinc-800"><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">EU AI Act Reference</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">Agentoom Feature</th><th class="text-left p-3 border border-zinc-200 dark:border-zinc-700">How It Helps</th></tr></thead>
                        <tbody>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 9 — Risk Management</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Risk Assessments, Classification Engine</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Classifies and tracks risk levels per agent/assessment</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 10 — Data Governance</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Agentoom Anonymizing Layer, Privacy Rules</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Detects and anonymizes personal data in AI inputs</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 11 — Technical Documentation</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Evidence Export, Compliance Reports</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">One-click export of all audit evidence for auditors</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 12 — Record-Keeping</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Audit Logs, Execution Tracing</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Tamper-evident, time-stamped execution records</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 14 — Human Oversight</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Pending Decisions, HITL Sentinel</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Human review queue with security buffers</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 52 — Transparency</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">AI Disclosure, Disclosure Badges</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Auto-stamped disclosure on AI-generated content</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 73 — Incident Reporting</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Incident Management, Auto-Incidents</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Auto-created on failure, full lifecycle tracking</td></tr>
                            <tr><td class="p-3 border border-zinc-200 dark:border-zinc-700">Article 83 — Enforcement</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Emergency Stops</td><td class="p-3 border border-zinc-200 dark:border-zinc-700">Runtime blocking of agent execution when activated</td></tr>
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </section>

        {{-- 23. Contact and Review --}}
        <section id="contact">
            <flux:card class="space-y-4">
                <flux:heading size="lg">23. For EU Regulatory Review</flux:heading>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">This document serves as the technical governance documentation for Agentoom. It can be shared with notified bodies, national supervisory authorities, and conformity assessment bodies as part of the technical documentation required under Article 11 of Regulation (EU) 2024/1689.</flux:text>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">For questions about Agentoom's governance capabilities or to request additional documentation, please contact the Agentoom compliance team through the platform or consult the following resources:</flux:text>
                <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>Contact: <a href="mailto:contact@agentoom.com" class="text-blue-600 dark:text-blue-400 hover:underline">contact@agentoom.com</a></li>
                    <li>Compliance evidence directory: <code class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">compliance/</code> within the platform</li>
                </ul>
            </flux:card>
        </section>

        {{-- Footer --}}
        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6 text-xs text-zinc-400 dark:text-zinc-500 text-center">
            Agentoom Governance Platform — Documentation generated {{ now()->toDateString() }}. This document is subject to change.
        </div>

        {{-- Navigation --}}
        <div class="flex justify-between items-center pt-4">
            <flux:button href="{{ route('docs.index', ['locale' => app()->getLocale()]) }}" icon="arrow-left" variant="ghost">{{ __("agentoom-core::docs.back_to_overview") }}</flux:button>
        </div>
    </div>
</div>
