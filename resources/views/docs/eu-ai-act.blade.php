<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EU AI Act Compliance — Agentoom Governance Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-zinc-900 antialiased">
<div class="max-w-4xl mx-auto px-6 py-12">

    <div class="mb-10">
        <h1 class="text-3xl font-bold tracking-tight text-zinc-900 mb-2">EU AI Act Compliance Documentation</h1>
        <p class="text-lg text-zinc-600">Agentoom Governance Platform — Technical Governance Support for Regulation (EU) 2024/1689</p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 mb-10">
        <h2 class="text-lg font-semibold text-amber-800 mb-2">Important Disclaimer</h2>
        <p class="text-sm text-amber-700 leading-relaxed">
            Agentoom is an AI Operating System with built-in governance, auditability, oversight, transparency, monitoring, and compliance-support capabilities. The features described in this document are <strong>technical tools that support governance and compliance readiness</strong>. They do not constitute legal advice, and they do not guarantee that any deployment of Agentoom is fully compliant with the EU AI Act or any other regulation. Operators remain responsible for conducting their own legal assessment, registering with relevant authorities, performing conformity assessments, and fulfilling all applicable obligations under Regulation (EU) 2024/1689.
        </p>
        <p class="text-sm text-amber-700 mt-2">
            Agentoom positions itself as <strong>an AI Operating System with built-in governance-support capabilities</strong> — not as a compliance guarantee, certification body, or legal advisor.
        </p>
    </div>

    <!-- Table of Contents -->
    <div class="bg-zinc-50 rounded-lg p-5 mb-10">
        <h2 class="text-xl font-semibold mb-4">Table of Contents</h2>
        <ol class="space-y-1 text-sm text-blue-700 list-decimal list-inside">
            <li><a href="#activation" class="hover:underline">Activation and Deactivation</a></li>
            <li><a href="#deployment" class="hover:underline">Full Deployment Strategy</a></li>
            <li><a href="#workflow" class="hover:underline">Governance Workflow</a></li>
            <li><a href="#profiles" class="hover:underline">Governance Profiles</a></li>
            <li><a href="#deployments" class="hover:underline">Governed Deployments</a></li>
            <li><a href="#wizard" class="hover:underline">Governance Assessment Wizard</a></li>
            <li><a href="#classification" class="hover:underline">Classification Engine</a></li>
            <li><a href="#controls" class="hover:underline">Governance Control Library</a></li>
            <li><a href="#risk-assessments" class="hover:underline">Risk Assessments</a></li>
            <li><a href="#audit-logs" class="hover:underline">Audit Logs and Tamper Evidence</a></li>
            <li><a href="#emergency-stops" class="hover:underline">Emergency Stops</a></li>
            <li><a href="#incidents" class="hover:underline">Incident Management</a></li>
            <li><a href="#evaluations" class="hover:underline">Performance Evaluations</a></li>
            <li><a href="#pending-decisions" class="hover:underline">Human-in-the-Loop Decisions</a></li>
            <li><a href="#alerts" class="hover:underline">Compliance Alerts</a></li>
            <li><a href="#reports" class="hover:underline">Compliance Reports</a></li>
            <li><a href="#evidence" class="hover:underline">Evidence Export</a></li>
            <li><a href="#disclosure" class="hover:underline">AI Disclosure</a></li>
            <li><a href="#privacy" class="hover:underline">Data Protection and Privacy</a></li>
            <li><a href="#dashboard" class="hover:underline">Compliance Dashboard</a></li>
            <li><a href="#config" class="hover:underline">Configuration Reference</a></li>
            <li><a href="#framework" class="hover:underline">Framework Mappings</a></li>
            <li><a href="#contact" class="hover:underline">Contact and Review</a></li>
        </ol>
    </div>

    <!-- Section: Activation -->
    <section id="activation" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">1. Activation and Deactivation</h2>

        <p class="mb-4">The EU AI Act compliance system is fully configurable via environment variables. By default, all compliance features are <strong>disabled</strong> for non-EU deployments.</p>

        <h3 class="text-lg font-semibold mt-6 mb-2">Master Switch</h3>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4">
            EU_COMPLIANCE_ENABLED=true
        </div>
        <p class="text-sm text-zinc-600 mb-4">When <code class="bg-zinc-100 px-1">EU_COMPLIANCE_ENABLED=false</code> (default), ALL compliance runtime enforcement is bypassed. Compliance UI pages remain accessible for manual governance management, but no automatic enforcement runs.</p>

        <h3 class="text-lg font-semibold mt-6 mb-2">Individual Feature Toggles</h3>
        <p class="text-sm text-zinc-600 mb-4">Each feature can be independently enabled or disabled. These only take effect when the master switch is <code class="bg-zinc-100 px-1">true</code>.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse mb-4">
                <thead>
                <tr class="bg-zinc-100">
                    <th class="text-left p-3 border">Environment Variable</th>
                    <th class="text-left p-3 border">Feature</th>
                    <th class="text-left p-3 border">Default</th>
                    <th class="text-left p-3 border">Description</th>
                </tr>
                </thead>
                <tbody>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_AUDIT_LOGGING</td><td class="p-3 border">Audit Logging</td><td class="p-3 border">true</td><td class="p-3 border">Creates tamper-evident audit log entries for all agent execution events</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_EMERGENCY_STOPS</td><td class="p-3 border">Emergency Stops</td><td class="p-3 border">true</td><td class="p-3 border">Blocks agent execution when an active emergency stop exists for that entity</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_AUTO_INCIDENTS</td><td class="p-3 border">Auto-Incidents</td><td class="p-3 border">true</td><td class="p-3 border">Automatically creates incident records when an agent run or pipeline job fails</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_AUTO_ALERTS</td><td class="p-3 border">Auto-Alerts</td><td class="p-3 border">true</td><td class="p-3 border">Daily scan for overdue risk reviews, unmitigated high-risk agents, and elevated incident rates</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_GOVERNANCE_GATE</td><td class="p-3 border">Governance Gate</td><td class="p-3 border">true</td><td class="p-3 border">Blocks agent execution if governance level exceeds profile threshold</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_AI_DISCLOSURE</td><td class="p-3 border">AI Disclosure</td><td class="p-3 border">true</td><td class="p-3 border">Stamps AI-generated content with disclosure notice</td></tr>
                <tr><td class="p-3 border font-mono text-xs">COMPLIANCE_RISK_ENFORCEMENT</td><td class="p-3 border">Risk Enforcement</td><td class="p-3 border">true</td><td class="p-3 border">Blocks high-risk agents without mitigations</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section: Deployment Strategy -->
    <section id="deployment" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">2. Full Deployment Strategy</h2>

        <h3 class="text-lg font-semibold mt-4 mb-2">Non-EU / Compliance Not Required</h3>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4">EU_COMPLIANCE_ENABLED=false</div>
        <p class="text-sm text-zinc-600 mb-4">All compliance UI pages remain accessible for voluntary governance. No runtime enforcement, no audit logging overhead, no emergency stops.</p>

        <h3 class="text-lg font-semibold mt-6 mb-2">EU Deployment — Soft Governance</h3>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4">
            EU_COMPLIANCE_ENABLED=true<br>
            COMPLIANCE_AUDIT_LOGGING=true<br>
            COMPLIANCE_AI_DISCLOSURE=true<br>
            <span class="text-zinc-400"># everything else false</span>
        </div>
        <p class="text-sm text-zinc-600 mb-4">Minimal overhead: audit logs and AI disclosure only. No blocking, no automatic alerts.</p>

        <h3 class="text-lg font-semibold mt-6 mb-2">EU Deployment — Full Governance</h3>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4">
            EU_COMPLIANCE_ENABLED=true<br>
            <span class="text-zinc-400"># all features true (default)</span>
        </div>
        <p class="text-sm text-zinc-600 mb-4">Full runtime enforcement: audit logs, emergency stops, governance gates, auto-incidents, auto-alerts, disclosure, risk enforcement.</p>
    </section>

    <!-- Section: Governance Workflow -->
    <section id="workflow" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">3. Governance Workflow</h2>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4">
            Deployment Creation
            → Governance Assessment Wizard (business context questions)
            → Classification Engine (governance level recommendation)
            → Profile Assignment (Basic / Standard / Enhanced)
            → Control Activation (audit logs, oversight, evaluations, monitoring)
            → Ongoing Monitoring (dashboard, alerts, incidents)
            → Evidence Export (one-click auditor package)
        </div>
        <p class="text-sm text-zinc-600">This workflow supports Article 43 conformity assessments and Article 49 registration by producing auditable classification records, evidence packages, and ongoing monitoring data.</p>
    </section>

    <!-- Section: Governance Profiles -->
    <section id="profiles" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">4. Governance Profiles</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse mb-4">
                <thead><tr class="bg-zinc-100"><th class="text-left p-3 border">Profile</th><th class="text-left p-3 border">Governance Level</th><th class="text-left p-3 border">Controls</th></tr></thead>
                <tbody>
                <tr><td class="p-3 border font-semibold">Basic Governance</td><td class="p-3 border font-mono">basic</td><td class="p-3 border">Audit logs, disclosure</td></tr>
                <tr><td class="p-3 border font-semibold">Standard Governance</td><td class="p-3 border font-mono">standard</td><td class="p-3 border">Audit logs, evaluations, incidents, periodic reviews, disclosure</td></tr>
                <tr><td class="p-3 border font-semibold">Enhanced Governance</td><td class="p-3 border font-mono">enhanced</td><td class="p-3 border">All standard controls + human oversight, monitoring</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/governance-profiles</code> — full CRUD with name, description, governance level, controls checkboxes, active toggle, and deployment count.</p>
    </section>

    <!-- Section: Governed Deployments -->
    <section id="deployments" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">5. Governed Deployments</h2>
        <p class="mb-4">A governed deployment represents a registered AI deployment — the governance unit that ties together a technical object (an agent, pipeline, or endpoint) with its business context, governance profile, classification result, and assessment history.</p>
        <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 mb-4">
            <li>Stores: name, business purpose, the deployable entity (agent / pipeline / endpoint), and its linked governance profile</li>
            <li>Classification fields: governance level, potentially sensitive flag, recommended controls, and reasoning</li>
            <li>Immutable assessment history — a complete record of all past classifications</li>
            <li>Supports ownership tracking for accountability</li>
        </ul>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/governed-deployments</code></p>
    </section>

    <!-- Section: Assessment Wizard -->
    <section id="wizard" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">6. Governance Assessment Wizard</h2>
        <p class="mb-4">The Assessment Wizard is a multi-step business-context questionnaire that can be scoped <strong>per agent</strong> or conducted at the instance level. Each agent in an Agentoom instance can perform different actions with varying risk profiles, so assessments can be linked to specific agents for granular governance.</p>
        <h3 class="text-lg font-semibold mb-2">Collected Information</h3>
        <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 mb-4">
            <li>What the AI does (purpose)</li>
            <li>What actions it can take (recommend, score, rank, approve/reject people)</li>
            <li>Who is affected (employees, customers, applicants, students, patients, citizens)</li>
            <li>Human review frequency (always / usually / sometimes / never)</li>
            <li>Data processed (business, customer, employee, financial, health, children's)</li>
            <li>Target agent (optional — leave empty for instance-level assessment)</li>
        </ul>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/assessment-wizard</code></p>
    </section>

    <!-- Section: Classification Engine -->
    <section id="classification" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">7. Classification Engine</h2>
        <p class="mb-4">The built-in classification engine analyses business-context answers from the Assessment Wizard and produces an explainable governance recommendation.</p>
        <p class="text-sm text-red-700 mb-4"><strong>This service never claims:</strong> compliant / non-compliant, high-risk AI (legal determination), or certified.</p>
        <h3 class="text-lg font-semibold mb-2">Outputs</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse mb-4">
                <thead><tr class="bg-zinc-100"><th class="text-left p-3 border">Output</th><th class="text-left p-3 border">Values</th></tr></thead>
                <tbody>
                <tr><td class="p-3 border">Governance Level</td><td class="p-3 border font-mono">basic / standard / enhanced</td></tr>
                <tr><td class="p-3 border">Potentially Sensitive</td><td class="p-3 border">yes / no</td></tr>
                <tr><td class="p-3 border">Recommended Controls</td><td class="p-3 border font-mono">audit_logs, human_oversight, evaluations, disclosure, monitoring, periodic_reviews</td></tr>
                <tr><td class="p-3 border">Reasoning</td><td class="p-3 border">Human-readable explanation of each escalation</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section: Control Library -->
    <section id="controls" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">8. Governance Control Library</h2>
        <p class="mb-4">The governance control library stores informational mappings between the platform's features and recognized governance frameworks.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse mb-4">
                <thead><tr class="bg-zinc-100"><th class="text-left p-3 border">Framework</th><th class="text-left p-3 border">Purpose</th></tr></thead>
                <tbody>
                <tr><td class="p-3 border font-mono">agentoom</td><td class="p-3 border">Native Agentoom governance controls (AGT-01 through AGT-10)</td></tr>
                <tr><td class="p-3 border font-mono">iso_42001</td><td class="p-3 border">ISO/IEC 42001 AI Management System mappings</td></tr>
                <tr><td class="p-3 border font-mono">nist_ai_rmf</td><td class="p-3 border">NIST AI Risk Management Framework mappings</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-sm text-amber-700">These are informational mappings only. They do not constitute ISO 42001 certification, NIST conformance, or any form of legal compliance claim.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/governance-controls</code></p>
    </section>

    <!-- Sections 9-22 briefly for remaining features -->
    <section id="risk-assessments" class="mb-8">
        <h2 class="text-xl font-bold mb-3">9. Risk Assessments</h2>
        <p class="text-sm text-zinc-600 mb-2">Polymorphic risk assessments with immutable version history supporting tracked levels: unknown, under_review, minimal, limited, potential_high_risk. Each assessment auto-calculates a next review due date based on the configured review interval.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/risk-assessments</code></p>
    </section>

    <section id="audit-logs" class="mb-8">
        <h2 class="text-xl font-bold mb-3">10. Audit Logs and Tamper Evidence</h2>
        <p class="text-sm text-zinc-600 mb-2">Every agent action is traced with step-level detail (model, tokens, execution time). Tamper-evident audit log entries are created automatically with hash-based integrity verification, ensuring any alteration is detectable.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/audit-log</code> — includes trace drill-down viewer.</p>
    </section>

    <section id="emergency-stops" class="mb-8">
        <h2 class="text-xl font-bold mb-3">11. Emergency Stops (Article 83)</h2>
        <p class="text-sm text-zinc-600 mb-2">Emergency stops block agent execution at runtime when activated. Stops can be created for any entity type (agent, pipeline, task, endpoint). Runtime checks are enforced at all entry points when <code class="bg-zinc-100 px-1">COMPLIANCE_EMERGENCY_STOPS=true</code>.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/emergency-stops</code></p>
    </section>

    <section id="incidents" class="mb-8">
        <h2 class="text-xl font-bold mb-3">12. Incident Management (Article 73)</h2>
        <p class="text-sm text-zinc-600 mb-2">Incidents are created automatically on job failure when auto-incidents are enabled, or manually by operators. Tracked with severity, status (open/investigation/resolved/closed), and affected entity.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/incidents</code></p>
    </section>

    <section id="evaluations" class="mb-8">
        <h2 class="text-xl font-bold mb-3">13. Performance Evaluations</h2>
        <p class="text-sm text-zinc-600 mb-2">Record evaluation scores for AI model outputs. Supports accuracy, fairness, robustness, and custom metrics.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/evaluations</code></p>
    </section>

    <section id="pending-decisions" class="mb-8">
        <h2 class="text-xl font-bold mb-3">14. Human-in-the-Loop Decisions</h2>
        <p class="text-sm text-zinc-600 mb-2">The Human-in-the-Loop pending decisions system provides a review queue for decisions requiring human authorization. Supports approve, reject, and override workflows.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/pending-decisions</code></p>
    </section>

    <section id="alerts" class="mb-8">
        <h2 class="text-xl font-bold mb-3">15. Compliance Alerts</h2>
        <p class="text-sm text-zinc-600 mb-2">Auto-generated daily when <code class="bg-zinc-100 px-1">COMPLIANCE_AUTO_ALERTS=true</code>: overdue risk assessment reviews, high-risk agents without mitigations, elevated incident rate (>5 open high/critical incidents in 7 days). Manual alerts also supported.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/alerts</code></p>
    </section>

    <section id="reports" class="mb-8">
        <h2 class="text-xl font-bold mb-3">16. Compliance Reports</h2>
        <p class="text-sm text-zinc-600 mb-2">Compliance reports aggregate incidents, audit logs, evaluations, and pending decisions into structured records. Report generation can be scheduled with configurable frequency and reporting period.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/reports</code></p>
    </section>

    <section id="evidence" class="mb-8">
        <h2 class="text-xl font-bold mb-3">17. Evidence Export</h2>
        <p class="text-sm text-zinc-600 mb-2">One-click export of all audit evidence for a given time period into a structured package suitable for auditor review. Exports include audit logs, incident records, evaluations, risk assessments, and alert history.</p>
    </section>

    <section id="disclosure" class="mb-8">
        <h2 class="text-xl font-bold mb-3">18. AI Disclosure</h2>
        <p class="text-sm text-zinc-600 mb-2">When AI disclosure is enabled, all AI-generated conversations are automatically stamped with a disclosure notice before being stored, ensuring end users are aware they are interacting with an AI system.</p>
    </section>

    <section id="privacy" class="mb-8">
        <h2 class="text-xl font-bold mb-3">19. Data Protection and Privacy</h2>
        <p class="text-sm text-zinc-600 mb-2">Agentoom includes a built-in anonymizing layer for PII detection and anonymization. Privacy redaction automatically removes personal data from agent prompts. Privacy rules are fully configurable (admin UI at <code class="bg-zinc-100 px-1">/admin/privacy-recognizers</code>).</p>
        <p class="text-sm text-zinc-600">See: <code class="bg-zinc-100 px-1">compliance/06-data-processing-and-privacy-summary.md</code></p>
    </section>

    <section id="dashboard" class="mb-8">
        <h2 class="text-xl font-bold mb-3">20. Compliance Dashboard</h2>
        <p class="text-sm text-zinc-600 mb-2">Central overview of compliance status: open incidents, pending decisions, active emergency stops, upcoming risk review deadlines, alert counts, and evaluation metrics.</p>
        <p class="text-sm text-zinc-600">Admin page: <code class="bg-zinc-100 px-1">/admin/compliance/dashboard</code></p>
    </section>

    <!-- Section: Configuration Reference -->
    <section id="config" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">21. Configuration Reference</h2>
        <p class="text-sm text-zinc-600 mb-4">All compliance configuration is managed through the Global App settings UI (Compliance tab) for superadmins, and can also be set via environment variables. The system reads settings from the database first, falling back to environment variables as defaults.</p>
        <div class="bg-zinc-100 rounded p-4 text-sm font-mono mb-4 overflow-x-auto">
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
    </section>

    <!-- Section: Framework Mappings -->
    <section id="framework" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">22. EU AI Act Framework Mappings</h2>
        <p class="text-sm text-zinc-600 mb-4">Agentoom features map to EU AI Act articles and recitals as follows (informational, not legal advice):</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse mb-4">
                <thead><tr class="bg-zinc-100"><th class="text-left p-3 border">EU AI Act Reference</th><th class="text-left p-3 border">Agentoom Feature</th><th class="text-left p-3 border">How It Helps</th></tr></thead>
                <tbody>
                <tr><td class="p-3 border">Article 9 — Risk Management</td><td class="p-3 border">Risk Assessments, Classification Engine</td><td class="p-3 border">Classifies and tracks risk levels per agent/assessment</td></tr>
                <tr><td class="p-3 border">Article 10 — Data Governance</td><td class="p-3 border">Agentoom Anonymizing Layer, Privacy Rules</td><td class="p-3 border">Detects and anonymizes personal data in AI inputs</td></tr>
                <tr><td class="p-3 border">Article 11 — Technical Documentation</td><td class="p-3 border">Evidence Export, Compliance Reports</td><td class="p-3 border">One-click export of all audit evidence for auditors</td></tr>
                <tr><td class="p-3 border">Article 12 — Record-Keeping</td><td class="p-3 border">Audit Logs, Execution Tracing</td><td class="p-3 border">Tamper-evident, time-stamped execution records</td></tr>
                <tr><td class="p-3 border">Article 14 — Human Oversight</td><td class="p-3 border">Pending Decisions, HITL Sentinel</td><td class="p-3 border">Human review queue with security buffers</td></tr>
                <tr><td class="p-3 border">Article 52 — Transparency</td><td class="p-3 border">AI Disclosure, Disclosure Badges</td><td class="p-3 border">Auto-stamped disclosure on AI-generated content</td></tr>
                <tr><td class="p-3 border">Article 73 — Incident Reporting</td><td class="p-3 border">Incident Management, Auto-Incidents</td><td class="p-3 border">Auto-created on failure, full lifecycle tracking</td></tr>
                <tr><td class="p-3 border">Article 83 — Enforcement</td><td class="p-3 border">Emergency Stops</td><td class="p-3 border">Runtime blocking of agent execution when activated</td></tr>
            </tbody>
            </table>
        </div>
    </section>

    <!-- Section: Contact -->
    <section id="contact" class="mb-10">
        <h2 class="text-2xl font-bold mb-4">23. For EU Regulatory Review</h2>
        <p class="mb-4 text-sm text-zinc-600">This document serves as the technical governance documentation for Agentoom. It can be shared with notified bodies, national supervisory authorities, and conformity assessment bodies as part of the technical documentation required under Article 11 of Regulation (EU) 2024/1689.</p>
        <p class="mb-4 text-sm text-zinc-600">For questions about Agentoom's governance capabilities or to request additional documentation, please contact the Agentoom compliance team through the platform or consult the following resources:</p>
        <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 mb-4">
            <li>Contact: <a href="mailto:contact@agentoom.com" class="text-blue-700 hover:underline">contact@agentoom.com</a></li>
            <li>Compliance evidence directory: <code class="bg-zinc-100 px-1">compliance/</code> within the platform</li>
        </ul>
    </section>

    <footer class="border-t border-zinc-200 pt-6 text-xs text-zinc-400 text-center">
        Agentoom Governance Platform — Documentation generated {{ now()->toDateString() }}. This document is subject to change.
    </footer>
</div>
</body>
</html>
