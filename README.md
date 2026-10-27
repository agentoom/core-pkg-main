# Agentoom Core (`agentoom/core-main`)

The central foundation and orchestration engine of **Agentoom**, an enterprise-grade AI operating system for creating, running, and managing autonomous AI agents.

---

> [!IMPORTANT]
> **Post-Splitting Versioning & Pre-Release Notice (`v0.9.0`)**
> Version `0.9.0` represents the foundational core framework following the modular package extraction and architectural splitting. While this core engine serves as the backbone for the ecosystem, it is currently in a pre-release state undergoing comprehensive integration testing to verify standalone stability across satellite packages before reaching `1.0.0`.

## 🌟 Overview

`agentoom/core-main` provides the core domain models, execution pipeline, multi-provider LLM integrations, scheduling, knowledge base management, Model Context Protocol (MCP) runtime, and administrative interfaces. All other Agentoom packages plug into and extend the contracts defined in this core package.

- **Package Name**: `agentoom/core-main`
- **Version**: `0.9.0` (Pre-release / Post-split testing)
- **Namespace**: `Agentoom\Core\`
- **Service Provider**: `Agentoom\Core\AgentoomCoreServiceProvider`

---

## 🚀 Key Features

### 1. Autonomous Agent Runtime & Orchestration
- **Agent Lifecycle**: Define agents with custom personas, prompt instructions, assigned tools, skills, and model configurations.
- **Version Control**: Built-in snapshotting and version history (`AgentVersion`) to track prompt and configuration iterations safely.
- **Learned Skills**: Dynamic skill recording and adaptation (`AgentLearnedSkill`) allowing agents to persist behavioral patterns learned during execution.
- **Stateful Memory & Context**: Long-term conversational storage (`AgentAwareConversationStore`), thread isolation, and context window pruning.

### 2. Multi-Provider AI Engine
- **Provider Agnostic**: Seamless switching between LLM providers including OpenAI, Anthropic, Google Gemini, Groq, DeepSeek, and local Ollama instances.
- **Model Discovery & Pricing**: Synchronize available models and pricing metadata (`Provider`, `ProviderModel`, `AiModelPricing`) to calculate exact execution costs per run.
- **Credit & Quota Management**: Real-time token accounting and budget enforcement per user and project (`AiCreditService`, `AiUsageLog`, `ProjectBudgetLimitNotification`).

### 3. Tool Execution & Pipeline Runtime
- **Action Execution Engine**: Standardized tool invocation via `ActionExecutor` and `ToolPipelineWrapper`.
- **Execution Pipe Registry**: Pluggable pipeline hooks (`ExecutionPipeRegistry`) allowing security, privacy, and compliance packages to inspect or intercept tool calls before and after execution.
- **Model Context Protocol (MCP)**: Native support for external MCP servers (`McpServer`, `McpActionable`), enabling agents to securely connect to external data sources, developer tools, and enterprise databases.

### 4. Knowledge Base & Vector Indexing
- **RAG Architecture**: Upload and index documents (PDF, Markdown, text, web scrapes) into searchable knowledge collections (`AgentKnowledge`, `AgentKnowledgeFile`).
- **Vector Search & Chunking**: Automatic document parsing, vector embeddings generation, and contextual similarity retrieval for agent prompt injection.

### 5. Workflow Pipelines & Autonomous Scheduling
- **Multi-Step Pipelines**: Define complex DAGs and sequential agent pipelines (`Pipeline`, `PipelineStep`, `PipelineRun`) with step-level branching, pauses, and resumed states.
- **Cron & Interval Scheduling**: Automated background triggers (`Schedule`, `RunScheduleJob`) allowing agents to perform unattended tasks on recurring intervals.
- **Asynchronous Task Queue**: High-throughput task dispatch (`AiTask`, `ProcessAiTasks`) for background jobs and webhook-driven executions.

### 6. Integrations & Event Bus
- **Inbound HTTP Endpoints**: Trigger agent executions and pipelines via authenticated webhooks and REST endpoints (`Endpoint`, `EndpointLog`).
- **Outbound Webhooks**: Dispatch lifecycle events (`webhook.run.completed`, `webhook.run.failed`) to external subscriber URLs (`WebhookSubscription`, `DispatchWebhookJob`).
- **Real-Time Broadcasting**: WebSocket broadcasting (`RealtimeManager`) for live token streaming, agent state updates, and interactive chat.

### 7. Administrative Console UI
- Full-featured **Livewire** and **Inertia** management dashboard for operators to configure agents, test prompts, view execution traces, monitor costs, and configure global system settings.

---

## 🏛️ Architecture & Extension Contracts

`agentoom/core-main` defines standard interfaces that modular Agentoom packages implement:

| Contract | Purpose | Implementing Packages |
|---|---|---|
| `Contracts\ComplianceIntegration` | Intercepts actions for EU AI Act compliance, audit logs, and risk checks | `agentoom/core-compliance` |
| `Contracts\PrivacyIntegration` | Detects, masks, and pseudonymizes PII before sending prompts to LLMs | `agentoom/core-privacy`, `agentoom/core-privacy-pro` |
| `Contracts\SecurityBufferInterface` | Pauses high-risk tool calls for human authorization or OTP verification | `agentoom/core-sentinel` |
| `Contracts\HumanIntervention` | Enables human agent takeover and live support handoff | `agentoom/core-live-support` |
| `Contracts\RunsInBackgroundContext` | Marker for tools safe to run in unattended background queues | Custom Agent Tools |
| `Contracts\RunsInScheduleContext` | Marker for tools safe to execute from cron schedules | Custom Agent Tools |

---

## 📁 Package Structure

```
core-main/
├── config/             # Core configurations (agentoom.php, etc.)
├── database/           # Migrations, seeders, and factories for core models
├── routes/             # Web, API, and admin routes
├── src/
│   ├── Ai/             # AI agent adapters, tools, middleware, and providers
│   ├── Attributes/     # PHP attributes (#[Actionable], #[Sentinelable], #[GroupVisibility])
│   ├── Console/        # Artisan CLI commands
│   ├── Contracts/      # Core interfaces for plugins and integrations
│   ├── Execution/      # Action execution engine, pipes, and registries
│   ├── Http/           # Controllers and middleware
│   ├── Jobs/           # Queueable background jobs (chat, schedules, pipelines)
│   ├── Livewire/       # Admin and chat interface components
│   ├── Models/         # Eloquent models (Agent, Pipeline, Knowledge, etc.)
│   ├── Realtime/       # Real-time event broadcasting and listeners
│   ├── Services/       # Business logic (credits, pricing, MCP, limits)
│   └── Storage/        # Conversation and memory store adapters
```

---

## 💻 Console Commands

`core-main` provides essential Artisan commands for system management and scheduling:

```bash
# Run one-time setup and initialize core database records
php artisan agentoom:install

# Scan codebase and register all #[Actionable] tools
php artisan agentoom:discover-tools

# Evaluate and trigger scheduled agent runs (cron worker)
php artisan agentoom:dispatch-schedules

# Process queued asynchronous AI tasks
php artisan agentoom:process-tasks

# Sync AI provider models and pricing catalogs
php artisan agentoom:sync-provider-models
php artisan agentoom:sync-pricing

# Aggregate hourly usage metrics and performance data
php artisan agentoom:aggregate-metrics

# Prune old conversations according to retention policy
php artisan agentoom:prune-conversations
```
