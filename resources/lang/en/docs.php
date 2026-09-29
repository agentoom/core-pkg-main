<?php

return [
    'title' => 'Documentation',
    'how_it_works_label' => 'How it works',
    'action_label' => 'Action',
    'read_more' => 'Read more',
    'back_to_overview' => 'Back to Overview',
    'view_examples' => 'View Examples',
    'sidebar' => [
        'getting_started' => 'Getting Started',
        'what_is_agentoom' => 'What is Agentoom?',
        'ai_core' => 'AI Core',
        'channels' => 'Collaboration & Channels',
        'automation' => 'Automation & Orchestration',
        'infrastructure' => 'Infrastructure & Integrations',
        'analytics' => 'Analytics & Finance',
        'governance' => 'Governance & Security',
        'features' => [
            'agents' => 'AI Agents',
            'agent_skills' => 'Agent Skills',
            'knowledge_base' => 'Knowledge Base',
            'prompts' => 'Prompt Engineering',
            'learning' => 'Experience Learning',
            'chat' => 'Conversations & Chat',
            'widgets' => 'Widgets',
            'live_support' => 'Live Support',
            'instant_messaging' => 'Instant Messaging',
            'pipelines' => 'Automated Pipelines',
            'endpoints' => 'Web Endpoints',
            'schedules' => 'Scheduled Tasks',
            'agent_awareness' => 'Agent Awareness & Task Delegation',
            'resources' => 'System Resources',
            'integrations' => 'Third-Party Integrations',
            'my_integrations' => 'My Integrations',
            'dashboard' => 'Dashboard & Analytics',
            'projects' => 'Projects',
            'expenses' => 'Expenses Dashboard',
            'ai_credits' => 'AI Credits',
            'security' => 'Enterprise Security',
            'users' => 'User Management',
            'eu_ai_act' => 'EU AI Act Compliance',
        ],
    ],
    'features' => [
        'how_to_use_title' => 'How to use :title',
        'how_it_works_title' => 'How it works',
        'configuration_title' => 'Configuration & Setup',
        'options_title' => 'Available Options & Settings',
        'hitl_title' => 'Human in the Loop (HITL)',
        'roi_examples_title' => 'Real-World ROI Examples',
        'agents' => [
            'title' => 'AI Agents',
            'description' => 'AI Agents are the foundational building blocks of Agentoom. Each agent is a fully autonomous AI entity with its own identity, instructions, skill set, tool access, budget limits, and collaboration permissions. Think of agents as specialized digital employees: you define their role, give them the right tools, set their boundaries, and let them work — autonomously or in collaboration with other agents.',
            'how_to_use' => 'Go to the "Agents" section in the Admin sidebar. Here you\'ll see a searchable, filterable list of all agents in your organization. Click "Create Agent" to build a new one, or click any existing agent to edit it. The agent editor is a multi-tab interface organized into: General, Skills & Tools, Limits, Tasks (Collaboration), and AI Configuration. After creating an agent, you can further enrich it by managing its Knowledge Base and reviewing its execution Traces.',
            'how_it_works' => 'Each agent operates by combining its System Instructions (the core "personality" prompt) with assigned Agent Skills (structured business logic) and AI Tools (functional capabilities like web browsing or email sending). When a user sends a message or a pipeline triggers the agent, the underlying LLM processes the request through this layered context, deciding which skills to activate and which tools to invoke. The agent can also delegate sub-tasks to other agents if collaboration permissions are configured. Every interaction is logged with token usage and cost tracking in real time.',
            'configuration' => 'The agent editor is organized into five tabs, each controlling a different dimension of the agent\'s behavior. Below is a comprehensive breakdown of every setting in each tab.',
            'options' => [
                'Identity & Mission' => 'Define who the agent is through name, description, and system instructions — the core prompt that shapes its personality and knowledge domain.',
                'Capabilities' => 'Assign Agent Skills for specialized business logic and AI Tools for functional powers like web browsing, email, or database queries.',
                'Usage Control' => 'Set budget caps in USD with configurable reset periods (Daily/Weekly/Monthly/Total) and optional task quotas to prevent runaway automation.',
                'Teamwork' => 'Configure which other agents this agent can delegate work to, with granular Assign vs View permissions and explanatory triggers.',
                'Model Settings' => 'Choose AI provider (OpenAI, Anthropic, OpenRouter), specific model, temperature (creativity), Top P (diversity), and performance suffixes.',
            ],
            'tabs' => [
                'general' => [
                    'title' => 'General Settings',
                    'name' => 'The display name of the agent. This is visible in the sidebar, chat headers, and widget interfaces. Choose a name that clearly communicates the agent\'s role (e.g., "Customer Support Agent", "SEO Analyzer").',
                    'slug' => 'A unique, URL-friendly identifier generated automatically from the name. Used for API calls and direct agent links. Must be unique across all agents.',
                    'description' => 'A brief administrative summary visible only to admins. Use this to document the agent\'s business purpose, target audience, and any special notes for your team.',
                    'instructions' => 'The System Prompt — the single most important field. This defines the agent\'s entire personality: its knowledge domain, communication style, decision-making rules, ethical boundaries, and any strict protocols it must follow. Write this as a detailed role description. Example: "You are a senior financial analyst. Always cite data sources. Never make investment recommendations. If asked about tax implications, direct users to consult a CPA."',
                    'active_status' => 'Toggle "Is Active" to enable or disable the agent. Inactive agents are hidden from chat selectors and won\'t process any requests. Use this to take agents offline for maintenance without deleting them.',
                    'privacy' => 'Toggle "Is Public" to make the agent available to all authenticated users. When disabled, a user picker appears allowing you to select exactly which users or teams can access this agent. Essential for internal agents handling sensitive data.',
                    'human_intervention' => 'When enabled, the agent can pause mid-execution and request human review before proceeding with sensitive actions. This is the first layer of Human-in-the-Loop (HITL) protection. The agent will explain what it plans to do and wait for explicit approval.',
                ],
                'skills_tools' => [
                    'title' => 'Skills & Tools',
                    'skills' => 'Agent Skills are reusable modules of structured business logic. Unlike free-form instructions, skills have mandatory requirements (Musts), prohibited actions (Donts), conditional If-This-Then logic, and tool associations. Select one or more skills to inject specialized capabilities (e.g., "GDPR Compliance Check", "Lead Qualification Protocol").',
                    'tools' => 'AI Tools give agents functional powers beyond conversation. Tools include web browsing, email sending (Gmail), web scraping, database queries, API calls, file operations, and more. Select the tools this agent needs to fulfill its mission. You can also configure tool-level security (verbal or OTP confirmation) in the Enterprise Security settings.',
                ],
                'limits' => [
                    'title' => 'Usage & Credit Limits',
                    'budget' => 'Maximum USD budget for this agent. The system tracks LLM token costs in real time and deducts from your AI Credits balance. Set a budget to prevent unexpected spending.',
                    'period' => 'Budget reset period: Daily, Weekly, Monthly, or Total (lifetime cap). Choose based on your operational needs — daily for high-volume customer-facing agents, monthly for internal analytics agents.',
                    'enforcement' => 'Hard Limit: when enabled, the agent stops working immediately once its budget is exhausted. When disabled, the agent continues but triggers an admin alert. Use hard limits for non-critical agents where cost control is paramount.',
                    'task_quotas' => 'Limit the maximum number of specific AiTask types the agent can execute per period. This prevents runaway automation loops where an agent might trigger thousands of repetitive tasks. Set quotas per task type for granular control.',
                ],
                'tasks' => [
                    'title' => 'Tasks & Collaboration',
                    'delegation' => 'This tab enables multi-agent teamwork. Define which other agents this agent is permitted to communicate with and delegate work to. Without explicit permissions here, agents operate in isolation.',
                    'permissions' => 'For each permitted agent, choose "Assign" (can create new tasks for that agent) or "View" (can only see that agent\'s existing tasks). Use View-only for monitoring scenarios and Assign for true delegation workflows.',
                    'explanation' => 'The Assignment Instructions field tells this agent WHEN to delegate. Write clear triggers: "If the user asks for technical troubleshooting, assign a task to the Tech Support Agent with the full conversation context." These instructions become part of the agent\'s reasoning context.',
                ],
                'config' => [
                    'title' => 'AI Configuration',
                    'provider' => 'Select the AI provider for this agent: OpenAI, Anthropic, or OpenRouter. You can also choose "Use Global Config" to inherit the system default. Different providers offer different model strengths and pricing.',
                    'model' => 'Choose the specific LLM model. Examples: GPT-4o (fast, versatile), Claude 3.5 Sonnet (strong reasoning, long context), GPT-4o-mini (cost-efficient for simple tasks). Model choice significantly impacts both quality and cost.',
                    'creativity' => 'Temperature setting (0.0 to 1.0). Low values (0.1–0.3) produce factual, deterministic outputs — ideal for data extraction and compliance. High values (0.7–1.0) produce creative, varied outputs — ideal for marketing copy and brainstorming.',
                    'diversity' => 'Top P setting for nucleus sampling. Controls response diversity as an alternative to temperature. Lower values restrict the token selection pool; higher values allow more variety.',
                    'performance_boost' => 'OpenRouter-only feature. Append ":nitro" to the model name for priority processing speed, or ":floor" for lowest possible cost. Useful for optimizing latency-sensitive or cost-sensitive workloads.',
                ],
            ],
            'roi_examples' => [
                [
                    'title' => 'Customer Support Automation — 70% Ticket Reduction',
                    'scenario' => 'A SaaS company with 5,000+ monthly support tickets deploys a "Support Triage Agent" with Knowledge Base access to their help docs, API references, and past ticket resolutions.',
                    'result' => 'The agent autonomously resolves 70% of Tier-1 tickets (password resets, billing questions, feature explanations) without human intervention. Support team headcount stays flat while customer base grows 3x. Estimated annual savings: $180,000 in prevented hires.',
                ],
                [
                    'title' => 'Legal Document Review — 90% Faster Contract Analysis',
                    'scenario' => 'A mid-size law firm creates a "Contract Review Agent" with Agent Skills defining their review methodology and a Knowledge Base of past contracts. The agent analyzes NDAs, service agreements, and vendor contracts against the firm\'s playbook.',
                    'result' => 'What took a junior associate 4 hours now takes the agent 15 minutes for the first pass. Associates focus only on exceptions flagged by the agent. Throughput increases 16x per associate. Estimated annual ROI: $250,000 in recovered billable hours.',
                ],
                [
                    'title' => 'E-Commerce Product Descriptions — 500% Output Increase',
                    'scenario' => 'An online retailer with 20,000 SKUs creates a "Product Copywriter Agent" with Prompt Engineering templates for different product categories and brand voice guidelines stored in the Knowledge Base.',
                    'result' => 'The agent generates SEO-optimized product descriptions at 50 per hour vs. 10 per hour manually. The marketing team shifts from writing to editing and strategy. Time-to-market for new products drops from 5 days to 1 day. Estimated annual savings: $95,000.',
                ],
            ],
        ],
        'agent_skills' => [
            'title' => 'Agent Skills',
            'description' => 'Agent Skills are reusable, structured modules that encapsulate specialized business logic, mandatory protocols, prohibited actions, conditional triggers, and tool associations. Unlike free-form agent instructions, skills provide rigorous guardrails that ensure consistent, auditable behavior across your entire agent fleet. Create a skill once and assign it to multiple agents — when you update the skill, every agent using it automatically adopts the changes.',
            'how_to_use' => 'Access "Agent Skills" from the Admin sidebar. Create a new skill with a descriptive name (e.g., "Invoice Processing", "GDPR Data Handling", "Lead Qualification"), fill in the four configuration tabs, and then assign the skill to one or more agents from their Skills & Tools tab. Skills can be activated/deactivated globally without removing them from agents.',
            'how_it_works' => 'When an agent processes a request, all assigned skills are dynamically injected into its reasoning context. The skill\'s instructions guide the agent\'s behavior, the Musts list enforces mandatory steps, the Donts list blocks prohibited actions, and conditional logic triggers specific responses to recognized situations. Tool associations ensure the agent has the necessary capabilities to execute the skill\'s logic.',
            'configuration' => 'The skill editor has four tabs: Basic Information (identity and core instructions), Musts & Donts (strict requirements and prohibitions), If-This-Then Logic (event-driven automation), and Tools & Resources (capability associations).',
            'options' => [
                'Core Logic' => 'Define the skill\'s fundamental instructions — a step-by-step procedure the agent should follow when this skill is active.',
                'Structured Rules' => 'Enforce mandatory requirements (Musts) and absolute prohibitions (Donts) that the agent cannot override.',
                'Event Logic' => 'Configure automated If-This-Then reactions to specific user behaviors, keywords, or data patterns.',
                'Infrastructure' => 'Link the skill to specific AI Tools and Resource Types needed for execution.',
            ],
            'tabs' => [
                'general' => [
                    'title' => 'Basic Information',
                    'name' => 'Skill name (e.g., "Invoice Processing", "Lead Qualification"). This name appears in the agent\'s skill assignment interface.',
                    'slug' => 'Unique URL-friendly identifier for API references and internal linking.',
                    'description' => 'Administrative summary explaining the skill\'s business purpose and when it should be used.',
                    'instructions' => 'The detailed procedural logic. Write step-by-step: "1. Greet the user and ask for their invoice number. 2. Look up the invoice in the system. 3. If found, summarize the key fields. 4. If not found, ask for the vendor name to search by alternative criteria."',
                    'active_status' => 'Global on/off toggle. Deactivating a skill removes it from all agents\' active context without requiring per-agent changes.',
                ],
                'requirements' => [
                    'title' => 'Musts & Donts',
                    'musts' => 'Absolute requirements the agent MUST follow when this skill is active. Examples: "Always verify the customer\'s identity before disclosing account details", "Always ask for a PO number before processing orders over $5,000".',
                    'donts' => 'Strictly prohibited actions. Examples: "Never offer discounts exceeding 15% without manager approval", "Never store credit card numbers in conversation memory", "Never make medical diagnoses — always recommend consulting a physician".',
                ],
                'conditional_logic' => [
                    'title' => 'If-This-Then Logic',
                    'events' => 'Define triggers the agent should watch for. Examples: "User mentions competitor name", "Order total exceeds $10,000", "User asks for a refund".',
                    'actions' => 'Define the agent\'s response when a trigger fires. Examples: "Offer a comparison document", "Escalate to the VIP Support Agent", "Present the refund request form".',
                    'form_triggers' => 'Optionally trigger a specific Form to collect structured data from the user when the event occurs. Forms can have validation rules and field types.',
                ],
                'associations' => [
                    'title' => 'Tools & Resources',
                    'tools' => 'Associate specific AI Tools with this skill so the agent always has the right capabilities. Example: link the "Gmail Tool" to an "Email Campaign" skill.',
                    'resource_types' => 'Link to Resource Types (Database, API, File System) so the agent knows which infrastructure to use for this skill\'s operations.',
                ],
            ],
            'roi_examples' => [
                [
                    'title' => 'Regulatory Compliance — Zero GDPR Violations',
                    'scenario' => 'A European healthcare provider creates a "GDPR Data Handling" skill with strict Musts (verify consent, anonymize PII) and Donts (never store patient data, never email unprotected records). This skill is assigned to every customer-facing agent.',
                    'result' => 'Over 18 months, the organization handles 500,000+ patient interactions with zero GDPR violations. The compliance team shifts from manual monitoring to exception-only review. Estimated cost avoidance: $500,000+ in potential fines and legal fees.',
                ],
                [
                    'title' => 'Sales Consistency — 40% Higher Close Rate',
                    'scenario' => 'A B2B company creates "Qualified Discovery" and "Objection Handling" skills based on their top-performing sales reps\' methodologies. These skills are assigned to all sales agents.',
                    'result' => 'Every lead receives the same high-quality discovery process regardless of which agent handles them. Close rates increase from 18% to 25%. The onboarding time for new sales agents drops from 4 weeks to 3 days. Estimated annual revenue increase: $2.1M.',
                ],
            ],
        ],
        'knowledge_base' => [
            'title' => 'Knowledge Base',
            'description' => 'The Knowledge Base is your agents\' memory and expertise repository. It grounds AI responses in your actual business data — product catalogs, policy documents, technical manuals, competitor research, and more. Unlike generic LLMs that hallucinate or give vague answers, agents with a well-curated Knowledge Base deliver precise, sourced, and contextually accurate responses every time. Content is ingested via file upload, URL scraping, or manual text entry, then processed through semantic chunking and vector indexing for lightning-fast retrieval.',
            'how_to_use' => 'Access the Knowledge Manager by going to the "Agents" list and clicking the "Manage Knowledge" button on any agent\'s card. This opens a dedicated interface where you can upload documents (PDF, TXT, DOCX), paste URLs for web scraping, or type raw text directly. Multiple items can be added in a single batch. After saving, the system automatically chunks and indexes your content. The dashboard shows processing statistics (total files, chunks, sync status) so you always know your agent\'s knowledge state.',
            'how_it_works' => 'Content goes through three stages: (1) Extraction — text is pulled from uploaded files or scraped from URLs. (2) Semantic Chunking — large documents are intelligently split into meaningful segments based on topic boundaries, not arbitrary character limits. (3) Vector Indexing — each chunk is converted into a mathematical embedding and stored for similarity search. When an agent receives a query, it retrieves the top-N most relevant chunks and injects them as reference context into the LLM prompt, producing answers anchored in your data rather than the model\'s general training.',
            'configuration' => 'The Knowledge Manager provides a streamlined interface for growing your agent\'s expertise through multiple ingestion methods. Monitor processing stats, trigger manual re-synchronization for stale assets, and manage individual knowledge items.',
            'options' => [
                'Document Ingestion' => 'Upload PDF, TXT, DOC/DOCX files. The system extracts text automatically and supports batch uploads — add multiple files in one session.',
                'Web Scraping' => 'Enter any URL to scrape its content. The system visits the page, extracts readable text, chunks it, and indexes it. Ideal for keeping agents current with online docs, blogs, or competitor pages.',
                'Manual Text Entry' => 'Type or paste raw text directly. Useful for quick facts, internal notes, or data that doesn\'t exist as a separate document.',
                'Batch Processing' => 'Add multiple file and URL slots simultaneously, then click "Save Knowledge" to process everything in one go. The system handles extraction, chunking, and indexing as a background job.',
                'Processing Stats' => 'Real-time dashboard showing total files, URLs, chunk counts, and sync status. Green checkmark = fully synced; yellow warning = needs re-sync.',
                'Synchronization' => 'Items marked "Unsynchronized" contain stale or incomplete chunks. Trigger manual re-processing to update the agent\'s index with the latest content.',
                'Asset Management' => 'View chunk count per asset to gauge information density. Download original files or visit source URLs directly from the management list.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Technical Support — 85% First-Contact Resolution',
                    'scenario' => 'A software company uploads their entire documentation library (API references, troubleshooting guides, release notes, internal KB articles) to a Support Agent\'s Knowledge Base. The agent also scrapes their community forum and GitHub issues weekly.',
                    'result' => 'First-contact resolution rate jumps from 40% to 85%. Customers get instant, accurate answers without waiting for Tier-2 escalation. Support costs drop 60% while CSAT scores increase from 3.2 to 4.7. Estimated annual savings: $320,000.',
                ],
                [
                    'title' => 'Onboarding & Training — 75% Faster Ramp-Up',
                    'scenario' => 'A professional services firm uploads their entire methodology playbook, past project reports, templates, and industry research to an "Internal Consultant" agent available to all new hires.',
                    'result' => 'New consultants get instant answers to methodology questions instead of searching SharePoint or interrupting senior staff. Ramp-up time drops from 12 weeks to 3 weeks. Senior consultants reclaim 8 hours/week previously spent mentoring. Estimated annual productivity gain: $450,000.',
                ],
            ],
        ],
        'integrations' => [
            'title' => 'Third-Party Integrations',
            'description' => 'Third-Party Integrations connect Agentoom to your existing SaaS ecosystem through secure OAuth 2.0 connections. Once configured, your agents can autonomously interact with external services — sending emails via Gmail, checking campaign performance in Google Ads, syncing contacts with HubSpot, or pulling analytics from Meta Ads and TikTok Ads. This transforms agents from conversational interfaces into true digital workers that operate across your entire software stack.',
            'how_to_use' => 'Administrators first configure global OAuth credentials (Client ID and Client Secret) in the "Email configuration" section under the Configuration menu. Once a service is globally enabled, individual users visit their personal "My Integrations" page, click on the desired service, and complete the OAuth authorization flow to grant Agentoom permission to act on their behalf. After authorization, agents with the appropriate tools can use these connections automatically.',
            'how_it_works' => 'Agentoom uses industry-standard OAuth 2.0 with encrypted token storage. When an agent needs to perform an action (e.g., send a follow-up email), it invokes the corresponding AI Tool (e.g., Gmail Tool). The tool retrieves the user\'s authorized token, makes the API call to the external service, and returns the result to the agent. Tokens are automatically refreshed, and all API interactions are logged for audit purposes.',
            'configuration' => 'Global setup requires obtaining OAuth credentials from each provider\'s developer console. Administrators enter the Client ID, Client Secret, and configure redirect URIs. Each service can be independently enabled or disabled. Users see only the services that have been globally configured and enabled.',
            'options' => [
                'My Integrations' => 'Each user manages their own OAuth connections — authorize new services, view connection status, and revoke access at any time. Authorization is per-user, per-service.',
                'Email Configuration' => 'Global administration panel where admins connect email services and enter server credentials. Currently supports Gmail OAuth and SMTP/IMAP mail servers.',
                'OAuth Security' => 'Tokens are encrypted at rest. Users can revoke access from either Agentoom or the provider\'s own security settings. API keys are never exposed to agents or end users directly.',
                'Service Status' => 'Real-time connection dashboard showing authorization status, token expiration, granted scopes, and last-used timestamps for every connected service.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Marketing Agency — 60% Less Manual Campaign Reporting',
                    'scenario' => 'A digital marketing agency connects Google Ads, Meta Ads, and TikTok Ads to a "Campaign Analyst Agent". Every Monday, a Scheduled Task triggers the agent to pull performance data from all platforms, compare against targets, and draft performance summaries.',
                    'result' => 'What was 12 hours of manual data pulling and spreadsheet work per account manager is now automated. Managers review and refine AI-generated reports instead of building them from scratch. Each account manager handles 3x more clients. Estimated annual revenue increase: $400,000.',
                ],
                [
                    'title' => 'Sales Team — Automated Follow-Up That Never Forgets',
                    'scenario' => 'A B2B sales team connects Gmail integration. Their "Sales Outreach Agent" monitors CRM webhooks and automatically sends personalized follow-up emails when leads go cold, complete with relevant case studies pulled from the Knowledge Base.',
                    'result' => 'Follow-up consistency goes from ~60% (manual, forgetful) to 100% (automated, systematic). Reply rates increase 35%. The team closes 22% more deals without adding headcount. Estimated annual pipeline increase: $1.8M.',
                ],
            ],
        ],
        'pipelines' => [
            'title' => 'Agentoom Pipelines: User Documentation',
            'intro' => 'This guide explains how to use the **Pipelines** feature in Agentoom. Pipelines allow you to chain multiple AI agents and Direct Actions together to create automated workflows that handle complex tasks from start to finish.',
            'what_is' => [
                'title' => '1. What is a Pipeline?',
                'content' => 'A pipeline is a sequence of "steps" that run one after another. Each step can be an AI agent performing a task (like summarizing text or analyzing data) or a custom action (like sending an email or saving data to a database). Pipelines take an initial input, process it through these steps, and produce a final result.',
            ],
            'when_to_use' => [
                'title' => '2. When to Use Pipelines',
                'content' => 'Use pipelines whenever a task is too complex for a single agent or requires multiple distinct stages. For example:',
                'items' => [
                    '**Content Creation:** Researching a topic (Step 1) → Writing a draft (Step 2) → Fact-checking (Step 3) → Sending to Slack (Step 4).',
                    '**Lead Processing:** Receiving a contact form (Step 1) → Qualifying the lead (Step 2) → Sending a personalized welcome email (Step 3).',
                    '**Data Analysis:** Scraping website data (Step 1) → Extracting key metrics (Step 2) → Generating a PDF report (Step 3).',
                ],
            ],
            'instructions' => [
                'title' => '3. Step-by-Step Instructions',
                'creating' => [
                    'title' => 'Creating a New Pipeline',
                    'steps' => [
                        'Navigate to **Automations > Pipelines** in the sidebar.',
                        'Click the **Create Pipeline** button.',
                        'Fill in the **Pipeline Details** (Name, Project, Type).',
                        'Add your first step by clicking **Add Step**.',
                        'Configure the step (choose an agent or action).',
                        'Add more steps as needed.',
                        '(Optional) Define **Pipeline Variables** for values you want to reuse.',
                        'Click **Save** or **Save and Stay**.',
                    ],
                ],
                'running' => [
                    'title' => 'Running a Pipeline',
                    'steps' => [
                        'Once saved, click the **Runs** tab or the **New Run** button.',
                        'Enter the **Initial Input** (the data or request that starts the process).',
                        'Click **Run Now**.',
                        'Monitor progress in the **Run History** section.',
                    ],
                ],
            ],
            'options' => [
                'title' => '4. Options and Settings Explained',
                'general' => [
                    'title' => 'General Settings',
                    'items' => [
                        'active' => '**Is Active:** Toggles whether the pipeline can be executed.',
                        'memory' => '**Allow memory worker:** If set to **Analyze**, the system will analyze the pipeline execution as a conversation, allowing agents to "learn" or remember context for future tasks.',
                        'concurrency' => [
                            'title' => '**Concurrency:**',
                            'unlimited' => '**Unlimited:** Multiple instances of this pipeline can run at the same time.',
                            'lock' => '**Lock:** Only one instance can run at a time. New runs are blocked until the current one finishes.',
                        ],
                        'type' => [
                            'title' => '**Pipeline Type:**',
                            'standard' => '**Standard:** Triggered manually or by schedules.',
                            'endpoint' => '**Endpoint:** Designed to be triggered by external apps via a Web Endpoint. It always starts with a mandatory "Receive Data" step.',
                        ],
                    ],
                ],
                'variables' => [
                    'title' => 'Pipeline Variables',
                    'content' => 'These are custom "shortcodes" you define once and use anywhere in the pipeline.',
                    'items' => [
                        'key' => '**Key:** The name you\'ll use to reference it (e.g., `company_name`).',
                        'value' => '**Value:** The actual text or data (e.g., `Acme Corp`).',
                        'usage' => '**How to use:** Type `{{key}}` (e.g., `{{company_name}}`) in any instruction field, and it will be replaced automatically.',
                    ],
                ],
                'steps' => [
                    'title' => 'Step Settings',
                    'items' => [
                        'target' => [
                            'title' => '**Target Type:**',
                            'agent' => '**Agent:** Assigns the step to one of your AI agents.',
                            'method' => '**Direct Action:** Executes a specific system action (e.g., "Send Email").',
                        ],
                        'label' => '**Step Label:** A friendly name for the step (e.g., "Drafting Stage").',
                        'instructions' => '**Extra Instructions:** Specific guidance for this step only, which overrides or adds to the agent\'s general instructions.',
                        'pass_output' => '**Pass Previous Output:** Select which specific earlier steps should feed their results into the current step.',
                        'logic' => [
                            'title' => '**Conditional Logic:**',
                            'condition' => '**Condition (Contains):** A word or phrase to look for in the output.',
                            'evaluate' => '**Evaluate On:** Choose to check the **Previous Step**, **Pipeline Input**, or **Current Step**.',
                            'next' => '**Next Step (If True/False):** Decide which step to jump to based on whether the condition is met.',
                        ],
                        'errors' => [
                            'title' => '**Error Handling (On Error):**',
                            'fail' => '**Fail:** Stops the entire pipeline immediately.',
                            'continue' => '**Continue:** Skips the error and moves to the next step.',
                            'retry' => '**Retry Step:** Tries the step again.',
                            'jump' => '**Jump to Specific Step:** Moves directly to a different step.',
                        ],
                    ],
                ],
                'placeholders' => [
                    'title' => 'Using Placeholders (Dynamic Data)',
                    'content' => 'Pull data from previous steps using `{}` brackets:',
                    'items' => [
                        'simple' => '`{title}`: pulls "title" from previous output.',
                        'specific' => '`{Step Name.price}`: pulls "price" from a specific step.',
                        'nested' => '`{user.email}`: pulls email from a user object.',
                        'whole' => '`{1}`: pulls entire result of Step 1.',
                        'prefix' => '**Special Prefix:** Data from scraping is often prefixed with `j-` (e.g., `{j-url}`).',
                    ],
                ],
                'failure' => [
                    'title' => 'On Pipeline Failure (Failure Handler)',
                    'content' => 'A special step that only runs if the pipeline fails. Used for alerts, logging, or cleanup.',
                ],
            ],
            'use_cases' => [
                'title' => '5. Real Use Case Examples',
                'seo' => [
                    'title' => 'Scenario: Automated SEO Audit',
                    'steps' => [
                        '**Step 1 (Direct Action):** Fetch website data from a URL.',
                        '**Step 2 (Agent):** Analyze the data for SEO mistakes (Broken links, missing tags).',
                        '**Step 3 (Agent):** If "critical errors" are found, write a priority fix list.',
                        '**Step 4 (Direct Action):** Email the report to the client.',
                    ],
                ],
            ],
            'mistakes' => [
                'title' => '6. Common Mistakes',
                'items' => [
                    'loops' => '**Infinite Loops:** Setting a "Next Step" that jumps back to an earlier step without a clear exit condition.',
                    'inputs' => '**Missing Inputs:** Referencing a placeholder like `{price}` when the previous step didn\'t produce that value.',
                    'inactive' => '**Inactive Steps:** Forgetting to toggle a step to "Active," causing the pipeline to skip it.',
                ],
            ],
            'troubleshooting' => [
                'title' => '7. Troubleshooting',
                'items' => [
                    'stops' => '**Pipeline stops early:** Check the Run Detail to see if a step failed and the "On Error" policy was set to "Fail."',
                    'variables' => '**Variables not replacing:** Double-check that you used double curly braces `{{key}}`.',
                    'data' => '**Data missing:** Ensure you have selected the previous step in the "Pass Previous Output" section.',
                ],
            ],
            'faq' => [
                'title' => '8. FAQ',
                'items' => [
                    [
                        'q' => 'Q: Can I change the order of steps?',
                        'a' => 'A: Yes, use the up/down arrow icons on each step card to reorder them.',
                    ],
                    [
                        'q' => 'Q: What happens if an agent is busy?',
                        'a' => 'A: If Concurrency Lock is enabled, the run will wait until the previous one is finished. Otherwise, it will run immediately.',
                    ],
                    [
                        'q' => 'Q: Can I run a pipeline automatically?',
                        'a' => 'A: Yes, you can link a pipeline to a Schedule or a Web Endpoint.',
                    ],
                ],
            ],
            'best_practices' => [
                'title' => '9. Best Practices',
                'items' => [
                    'labels' => '**Label your steps:** Use clear names like "Summarize Article" instead of "Step 2" to make the flow easy to read.',
                    'test' => '**Test with simple inputs:** Run the pipeline with basic data first to ensure the logic and placeholders are working correctly.',
                    'failure' => '**Use the Failure Handler:** Always set up a notification in the Failure Handler so you know immediately if a critical automation stops working.',
                ],
            ],

            'json_extraction' => [
                'title' => 'Direct Action Extraction from Previous Outputs',
                'description' => 'When an AI agent produces output, it often wraps JSON in markdown code blocks or includes explanatory commentary. Direct Actions can automatically strip this noise and extract clean JSON data using the Helpers class utilities.',
                'how_to_title' => 'How to use it in your Direct Actions',
                'usage' => 'Use the Pipeline UI to pass the output of previous steps into your Direct Action — no coding required.

• Add a Direct Action step and pick the target Class@action.
• In Instruction Overrides or Parameters, insert dynamic {field} placeholders with the field picker.
• Placeholders can point to the initial input or any previous step output. Use dot notation for nested values (for example {products.0.name} or {pricing.final_price}).
• Optionally set default values for placeholders so the step still runs if a field is missing.
• Use the preview/expand panels to verify which placeholders resolve before saving.

At runtime, the pipeline resolves the placeholders and passes the final values to your action automatically.',
                'example' => '',
            ],
        ],
        'widgets' => [
            'title' => 'Widgets',
            'description' => 'Widgets are embeddable AI chat interfaces that bring your agents directly to your website visitors. With a single script tag, you can deploy a fully branded, customizable chat experience that connects users to your AI agents for instant support, lead generation, or product guidance. Widgets handle all UI complexity — message history, file uploads, audio recording, quick prompts, and lead capture forms — while communicating with Agentoom\'s backend for AI processing.',
            'how_to_use' => 'Go to "Widgets" in the Admin sidebar. Create a new widget by selecting the target agent and customizing the design (colors, gradients, position, icons, bubble style). Optionally link Prompt templates and Forms. Copy the generated script tag and paste it into your website\'s HTML. The widget appears immediately. You can restrict access to specific users or make it fully public.',
            'how_it_works' => 'The widget is a lightweight JavaScript component that renders a chat UI on your website. When a visitor sends a message, it\'s routed through Agentoom\'s backend to the assigned agent, which processes it using its instructions, skills, knowledge base, and tools. The response streams back in real time. The widget maintains session state, conversation history, and supports media attachments.',
            'configuration' => 'Widget configuration covers visual branding (colors, gradients, fonts, icons, bubble shapes), behavioral settings (quick prompts, forms, file uploads, audio), and access control (public vs. restricted to specific authenticated users).',
            'options' => [
                'Visual Branding' => 'Full control over primary colors, gradients, font families, icon sets, and bubble styles (modern, rounded, flat, sharp). Match your brand identity exactly.',
                'Quick Prompts' => 'Add clickable prompt buttons that appear when the widget opens. Users tap a prompt to instantly start a guided conversation (e.g., "Track my order", "Talk to sales").',
                'Lead Capture & Forms' => 'Embed custom forms within the chat flow to collect structured data — contact details, preferences, support tickets — without breaking the conversation experience.',
                'Access Control' => 'Toggle between public (any website visitor) and restricted (only authenticated Agentoom users). Use restricted mode for internal tools and employee-facing agents.',
                'Media Support' => 'Enable or disable file attachments (images, PDFs) and audio recording. Attachments are securely stored and accessible to the agent for context.',
            ],
            'roi_examples' => [
                [
                    'title' => 'E-Commerce — 45% Cart Abandonment Recovery',
                    'scenario' => 'An online retailer embeds a "Shopping Assistant" widget on product and checkout pages. The agent has Knowledge Base access to product specs, shipping policies, and return procedures.',
                    'result' => 'The widget proactively engages users who linger on checkout. It answers last-minute questions about sizing, shipping, and returns. Cart abandonment drops 45%. Recovered revenue: $780,000/year. Support ticket volume decreases 30% as customers self-serve.',
                ],
                [
                    'title' => 'SaaS Company — 50% Fewer Demo Requests Go Unanswered',
                    'scenario' => 'A SaaS company embeds a widget on their pricing and product pages. The agent qualifies leads by asking about company size, use case, and budget before offering to schedule a demo with the right sales rep.',
                    'result' => 'Lead qualification happens 24/7 instead of during business hours only. The sales team receives pre-qualified leads with full context. Demo-to-close rate increases from 12% to 19%. Estimated annual pipeline increase: $2.4M.',
                ],
            ],
        ],
        'endpoints' => [
            'title' => 'Web Endpoints',
            'description' => 'Web Endpoints are secure HTTP gateways that let external systems trigger your Agentoom workflows programmatically. Each endpoint exposes a unique URL that accepts POST requests with JSON payloads. When data arrives, Agentoom validates the source, maps the incoming data fields to the linked agent or pipeline, and kicks off the automated workflow. This is how you integrate Agentoom into your existing stack — webhooks from your CRM, form submissions from your website, alerts from your monitoring tools, or API calls from custom applications.',
            'how_to_use' => 'Navigate to "Endpoints" in the Automation section. Click "Create Endpoint", choose the target — either an individual Agent (simpler, single-step processing) or an Endpoint-type Pipeline (multi-step workflows that start with a mandatory "Receive Data" step). Define the expected JSON data structure to help the AI understand incoming payloads. Configure allowed sources (IP addresses or domains, comma-separated). Copy the generated URL and configure your external system to POST to it.',
            'how_it_works' => 'The endpoint URL accepts POST requests with Content-Type: application/json. On receipt, Agentoom performs source validation against your whitelist, extracts and maps the JSON fields according to your defined data structure, and passes everything to the target agent or pipeline. The agent receives the data as conversation context; the pipeline starts with a "Receive Data" step that captures the full payload. All requests are logged for audit.',
            'configuration' => 'Define the endpoint name, select target type (Agent or Pipeline), choose specific target, define JSON data structure for field mapping, and list allowed IP addresses or domains. The endpoint can be toggled active/inactive at any time.',
            'options' => [
                'Source Whitelisting' => 'Restrict endpoint access to specific IP addresses or domains for enhanced security. Only requests from allowed sources will be processed.',
                'Payload Mapping' => 'Define the expected JSON structure so the AI understands what each field represents. Example: {"customer_name": "string", "order_total": "number", "items": "array"}',
                'Direct Agent Trigger' => 'Send incoming data directly to a specific agent for immediate processing. The agent receives the structured data as part of its conversation context.',
                'Pipeline Integration' => 'Trigger complex, multi-step workflows via a single external webhook call. Endpoint-type pipelines start with a mandatory "Receive Data" step that captures the full payload.',
            ],
            'roi_examples' => [
                [
                    'title' => 'E-Commerce — Real-Time Fraud Detection',
                    'scenario' => 'An online store configures a webhook from their payment processor to an Agentoom Endpoint linked to a "Fraud Detection Pipeline". The pipeline first checks order patterns, then passes suspicious orders to a "Fraud Analyst Agent" for deeper inspection.',
                    'result' => 'Every order is screened automatically within 2 seconds. The pipeline catches 94% of fraudulent transactions that previously slipped through manual review. Chargeback rate drops from 1.2% to 0.07%. Estimated annual savings: $340,000 in prevented fraud losses.',
                ],
                [
                    'title' => 'Logistics — Automated Delivery Exception Handling',
                    'scenario' => 'A logistics company configures webhooks from their carrier APIs to Agentoom Endpoints. When a delivery exception occurs (wrong address, recipient unavailable), an endpoint triggers a "Delivery Resolution Agent" that contacts the customer via Gmail integration and offers rescheduling options.',
                    'result' => 'Exception resolution time drops from 4 hours (manual) to 3 minutes (automated). First-attempt delivery success rate increases from 92% to 97%. Customer complaints decrease 60%. Estimated annual savings: $210,000 in redelivery costs.',
                ],
            ],
        ],
        'schedules' => [
            'title' => 'Scheduled Tasks',
            'description' => 'Scheduled Tasks are the backbone of autonomous operations. They let you run agents, pipelines, or direct actions on any recurring schedule using standard cron syntax — no external cron jobs or task runners needed. Whether it\'s a daily report, a weekly data sync, an hourly health check, or a midnight security audit, schedules ensure your automations fire reliably without human intervention.',
            'how_to_use' => 'Go to "Schedules" in the Automation section. Click "Create Schedule", configure the cron expression (minute, hour, day, month, weekday) or pick a preset like "Every Hour" or "Every Day at Midnight". Select the target type (Agent, Pipeline, or Direct Action) and the specific target. Optionally add instructions for the agent or JSON parameters for direct actions. Set the timezone.',
            'how_it_works' => 'Agentoom\'s background scheduler evaluates all active schedules against their cron definitions. When the current time matches a schedule, the system executes the target with any provided instructions or parameters. The execution result — including status, timing, and agent output — is recorded in the Execution Logs for review. Failed executions can be configured to retry or alert.',
            'configuration' => 'Use standard five-field cron syntax for precise scheduling. Choose from timezone presets to ensure tasks fire at the correct local time. For Direct Actions, provide JSON parameters that are passed to the PHP action handler. Toggle schedules active/inactive without deleting them.',
            'options' => [
                'Cron Precision' => 'Full cron syntax support (minute, hour, day, month, weekday) plus common presets: Every Minute, Every Hour, Every Day, Every Week, Every Month.',
                'Timezone Support' => 'Set the timezone for each schedule independently. Essential for global teams where tasks need to fire at local business hours.',
                'Execution Logs' => 'Complete history of every scheduled run: timestamp, status (success/failure), duration, and agent output. Filter by date range and status.',
                'Access Control' => 'Restrict schedule management to specific users or roles, or leave it open to all administrators.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Financial Services — Automated Daily Compliance Reports',
                    'scenario' => 'A fintech company schedules a "Compliance Audit Agent" to run every night at 2 AM. The agent pulls transaction logs from System Resources, checks for anomalies against regulatory rules, and generates a compliance report emailed to the CCO.',
                    'result' => 'What was a 3-hour daily manual process for a compliance analyst is now fully automated. The analyst reviews the AI-generated report in 15 minutes instead of building it from raw data. Compliance coverage goes from sampling 5% to auditing 100% of transactions. Estimated annual savings: $175,000.',
                ],
                [
                    'title' => 'Retail — Weekly Competitive Price Monitoring',
                    'scenario' => 'A retailer schedules a Pipeline every Monday morning to scrape competitor pricing pages, analyze price differences, and generate a repricing recommendations report for the category management team.',
                    'result' => 'Manual competitive analysis that took 8 hours/week is now automated. The team receives actionable data every Monday by 9 AM. Price adjustment speed increases from weekly to same-day. Profit margin improves 3.2% through optimized pricing. Estimated annual profit increase: $520,000.',
                ],
            ],
        ],
        'resources' => [
            'title' => 'System Resources',
            'description' => 'System Resources bridge your agents with your actual infrastructure — databases, APIs, file systems, and more. Instead of building custom integrations for every agent, you define a Resource Type once (e.g., "MySQL Database", "REST API"), create Resources with encrypted connection details, and grant agents permission to use them. Agents can then query databases, call APIs, read files, and perform real-world operations — all with credentials encrypted at rest and access governed by role-based permissions.',
            'how_to_use' => 'First, define Resource Types (templates) in the Config section — this sets the connection schema. Then create individual Resources under "Resources" with the actual connection details (host, port, credentials, database name). Finally, grant access to specific users, roles, or agents. Sensitive fields are automatically encrypted.',
            'how_it_works' => 'When an agent needs to use a resource, it invokes the appropriate AI Tool. The tool looks up the resource\'s encrypted credentials, decrypts them in memory, establishes the connection, performs the operation, and returns the result. Credentials never appear in the agent\'s conversation context and are never exposed to end users.',
            'configuration' => 'Resource Types define the schema (field names, types, validation rules). Resources hold the actual connection values. Access is managed through user/role assignments and agent permission grants.',
            'options' => [
                'Resource Types' => 'Create reusable templates for different types of connections (MySQL, PostgreSQL, REST API, SSH, File System). Define field schemas, validation, and encryption rules.',
                'Data Encryption' => 'All sensitive connection data (passwords, API keys, tokens) is automatically encrypted at rest using application-level encryption. Decryption happens only at runtime.',
                'Access Control' => 'Granularly define which users, roles, and agents can access each resource. Separate read and write permissions where applicable.',
                'Resource Data' => 'Store arbitrary JSON configuration specific to the resource type\'s requirements, accessible to agents through tools.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Business Intelligence — Self-Service Analytics for Everyone',
                    'scenario' => 'A company connects their production database and data warehouse as System Resources. They create an "Analytics Agent" that non-technical managers can query in natural language: "What were our top 5 products by revenue last month?"',
                    'result' => 'The BI team\'s ad-hoc query ticket volume drops 80%. Managers get answers in seconds instead of waiting 2-3 days for the BI queue. The BI team focuses on strategic analysis instead of report generation. Estimated annual productivity gain: $280,000.',
                ],
                [
                    'title' => 'DevOps — Automated Incident Diagnostics',
                    'scenario' => 'An engineering team connects their monitoring database, application logs (file system), and deployment API as System Resources. A "DevOps Agent" is triggered by monitoring alerts.',
                    'result' => 'When an alert fires, the agent queries recent errors from the database, checks log files for stack traces, and checks deployment history for recent changes — all in under 30 seconds. Mean Time To Resolution drops from 45 minutes to 8 minutes. Estimated annual savings from reduced downtime: $600,000.',
                ],
            ],
        ],
        'learning' => [
            'title' => 'Experience Learning',
            'description' => 'Experience Learning gives your agents a persistent memory that improves with every interaction. As agents execute tasks successfully, they can record the logic, findings, and procedures that worked as "Learned Skills" — reusable intelligence building blocks. These learned skills are searchable by the agent and can be applied to similar future requests. The more your agents work, the smarter and more efficient they become, building an institutional memory that compounds over time.',
            'how_to_use' => 'Experience Learning is enabled by default for dynamic agents. Two specialized AI Tools power this feature: `SearchAgentLearnedSkillsTool` (retrieves relevant past experiences) and `ManageAgentLearnedSkillsTool` (creates and updates learned skills). You can view, search, and export all learned skills via the "Import / Export" dashboard. To disable learning for specific agents, remove these tools from their Skills & Tools tab.',
            'how_it_works' => 'When an agent completes a task successfully, it can choose to record the successful procedure as a Learned Skill with a title, description, and the logic used. On future tasks, the agent searches its learned skills for relevant past experiences and applies them. This creates a continuous improvement loop: work → learn → apply → improve.',
            'configuration' => 'Learning is enabled by default for Dynamic Agents. Admins can monitor the accumulation of learned skills through the system logs and manage the data portability via the "Import / Export" dashboard.',
            'options' => [
                'Skill Recording' => 'Agents automatically capture successful logic as reusable pieces of intelligence that compound over time.',
                'Experience Search' => 'Agents can search their collective "memory" to find the most relevant past experiences for any new task, improving accuracy and consistency.',
                'Data Portability' => 'Export all "Learned Skills" as JSON for backup, analysis, or transfer between environments. Import learned skills from other instances.',
                'Continuous Loop' => 'The more an agent works, the more refined its specialized knowledge becomes — creating compounding value over time.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Customer Support — Escalation Rate Drops 50% in 3 Months',
                    'scenario' => 'A support team deploys agents with Experience Learning enabled. Every time an agent successfully resolves a unique issue, it records the resolution pattern as a learned skill.',
                    'result' => 'After 3 months, the agent has accumulated 2,400+ learned skills covering edge cases that were not in the original Knowledge Base. Escalation rate drops from 25% to 12%. The "new agent" cold-start problem disappears — agents deployed with exported learned skills from production perform at 80% effectiveness on day one.',
                ],
                [
                    'title' => 'Sales — Win Rate Improves 18% Through Pattern Recognition',
                    'scenario' => 'A sales team uses agents that learn from every deal — recording which discovery questions, objection responses, and case studies correlated with closed-won outcomes.',
                    'result' => 'Over 6 months, the agent identifies patterns human reps missed: specific objection-handling sequences that work for enterprise vs. SMB prospects, optimal follow-up timing by industry. Win rate climbs from 22% to 26%. Average deal size increases 12% as the agent surfaces upsell opportunities learned from past wins.',
                ],
            ],
        ],
        'security' => [
            'title' => 'Enterprise Security',
            'description' => 'Enterprise Security provides the multi-layered protection your AI operations demand. It combines Role-Based Access Control (RBAC) for user permissions, Human-in-the-Loop (HITL) verification for sensitive agent actions, automated user banning capabilities, agent and widget visibility controls, and centralized OAuth token management. This ensures your AI agents operate within strict boundaries while maintaining full auditability of every action.',
            'how_to_use' => 'Security is managed from multiple locations: "Roles" and "Permissions" under Config (define who can do what), "Tool Security" under Config (set HITL requirements per tool method), "Public Access" toggles on individual Agents and Widgets (restrict to specific users), and auto-banning configuration in Chat Widgets (let agents enforce usage policies). Each layer works independently but complements the others.',
            'how_it_works' => 'Security operates at five layers: (1) RBAC — roles define what admin pages and actions users can access; (2) Agent Visibility — Public Access toggles determine which users can chat with each agent; (3) Tool Security — sensitive tool methods (like sending email or modifying data) can require verbal confirmation or OTP codes; (4) Auto-Banning — agents can autonomously ban abusive users based on configurable rules and timeframes; (5) OAuth Management — third-party tokens are encrypted and never exposed in plain text to agents or users.',
            'human_in_the_loop' => 'Human-in-the-Loop (HITL) is Agentoom\'s critical safety net. For any AI tool method, you can require: (a) Verbal Confirmation — the agent explains what it will do and asks "Should I proceed?", or (b) OTP Confirmation — the agent generates a one-time password that a human must enter. This ensures no sensitive action (financial transactions, data deletions, external communications) executes without explicit human authorization.',
            'configuration' => 'Create Roles with specific permission sets. In "Tool Security", define which tool methods require "Verbal Confirmation" or "OTP Confirmation". Toggle "Public Access" on Agents to restrict them to specific assigned users. Configure "Automated Banning" in Chat Widgets with specific instructions and durations.',
            'options' => [
                'RBAC' => 'Granular role-based access control for all administrative and user actions. Create custom roles with specific permission sets for different team functions.',
                'Tool Security' => 'Enforce human-in-the-loop confirmation (Verbal or OTP) for sensitive AI operations. Configure per-tool-method with granular control.',
                'Agent Privacy' => 'Restrict agents to specific users or teams by disabling "Public Access". Essential for internal-only agents handling sensitive data.',
                'Automated Banning' => 'Empower agents to autonomously ban users based on your custom security instructions and configurable ban duration. Protects against abuse 24/7.',
                'Secure Integrations' => 'Centralized OAuth management with encrypted token storage ensures API keys are never exposed to agents or users directly.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Financial Services — Zero Compliance Incidents in 24 Months',
                    'scenario' => 'A registered investment advisor deploys agents with HITL OTP confirmation on all tools that can send external communications or modify client records. RBAC separates advisor agents from compliance review agents.',
                    'result' => 'Zero regulatory fines over 24 months of AI-powered operations. Every sensitive action has a human approval record. Audit preparation time drops from 2 weeks to 2 hours. Estimated cost avoidance: $750,000 in potential fines and legal fees.',
                ],
                [
                    'title' => 'E-Commerce — 99.7% Reduction in Chat Abuse',
                    'scenario' => 'A high-traffic e-commerce site configures auto-banning on their customer support widget. The agent autonomously bans users who use abusive language, attempt prompt injection, or spam the chat.',
                    'result' => 'Previously, a team of 3 moderators spent 60 hours/week reviewing flagged chats. Auto-banning handles 99.7% of incidents instantly. Moderators now spend 2 hours/week on edge case review. Support agent costs drop $144,000/year while response quality remains unaffected.',
                ],
            ],
        ],
        'prompts' => [
            'title' => 'Prompt Engineering',
            'description' => 'Prompt Engineering is your central command for managing how users start conversations with your agents. Create prompt templates with multi-language labels, hidden system instructions, and contextual JSON parameters. Associate them with widgets to provide users with clickable quick-start options that guide them toward productive conversations. This is also where you version-control your agent\'s communication style — update a prompt template once and all linked widgets reflect the change immediately.',
            'how_to_use' => 'Go to "Prompts" from the Admin sidebar. Create a new prompt: define the button label (what users see), the instruction (what the AI receives — often more detailed than the label), and optional JSON parameters for technical context. Enable "Show on Start" to make the prompt visible when a widget loads. Link prompts to specific widgets. Use display conditions to show/hide prompts based on context.',
            'how_it_works' => 'Prompts serve dual purposes. For users, they appear as clickable buttons that pre-fill a message. For agents, they inject hidden context and instructions. When a user clicks a prompt button, the widget sends both the visible label and the hidden instruction to the agent, along with any JSON parameters. The agent processes the combined context, often achieving better results than free-form conversation because the prompt sets clear expectations.',
            'configuration' => 'For each active locale, define a Button Label (user-facing) and an Instruction (AI-facing). Add optional JSON Parameters for dynamic data. Set "Show at Start" to display the prompt immediately when a widget opens. Define Display Conditions using simple logic expressions to control when prompts appear.',
            'options' => [
                'Multi-Language Support' => 'Define labels and instructions for every active locale. Users see prompts in their language while the AI receives locale-appropriate instructions.',
                'Contextual Parameters' => 'Pass JSON data with each prompt — resource IDs, user preferences, CRM record identifiers. The agent uses this for personalized, data-aware responses.',
                'Display Conditions' => 'Control prompt visibility with conditions based on user attributes, page context, or conversation state. Show "Track Order" only to logged-in users, hide "Schedule Demo" after one has been scheduled.',
                'Start Prompts' => 'Configure which prompts appear as quick-start options in widgets. Users click once to begin a guided conversation instead of staring at an empty chat box.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Customer Support — 40% Faster Resolution Through Guided Conversations',
                    'scenario' => 'A SaaS company creates prompt templates for their top 10 support categories (password reset, billing question, API help, feature request). Each prompt includes hidden instructions that tell the agent which Knowledge Base sections to prioritize and which diagnostic questions to ask.',
                    'result' => 'Average resolution time drops from 8 minutes to under 5. Customers who use prompts get first-contact resolution 92% of the time vs. 65% for free-form chats. Support team capacity increases 40% without adding headcount.',
                ],
                [
                    'title' => 'Lead Generation — 3x More Qualified Conversations',
                    'scenario' => 'A B2B company creates prompts for each buyer persona: "I\'m evaluating solutions", "I need pricing", "Book a demo". Each prompt routes to the appropriate agent and pre-loads discovery questions.',
                    'result' => 'Website visitors who click prompts convert to qualified leads at 3x the rate of those who use free-form chat. Sales team receives pre-qualified context with every conversation — no more "Tell me about your company" wasted messages. Pipeline velocity increases 35%.',
                ],
            ],
        ],
        'dashboard' => [
            'title' => 'Dashboard & Analytics',
            'description' => 'The Dashboard is your real-time command center for the entire Agentoom ecosystem. It surfaces key metrics at a glance: total AI Credits balance, today\'s spend, active agent count, task execution volume, and usage trend charts over configurable time periods. Think of it as the CFO and CTO dashboard rolled into one — financial health and operational throughput side by side.',
            'how_to_use' => 'The Dashboard is the default landing page for admins. Each card shows a critical metric. Click any card to drill into detailed reports. Usage charts support Daily, Weekly, and Monthly views. Use the date range picker to analyze specific periods.',
            'options' => [
                'Total Credits' => 'Your remaining AI Credits balance in USD. Color-coded: green (healthy), yellow (low), red (critical). Click for transaction history.',
                'Usage Today' => 'Total spend in the last 24 hours, broken down by agent and task type. Compare against your daily budget if set.',
                'Active Agents' => 'Number of agents currently enabled and processing requests. Click to see which agents are active and their current load.',
                'Tasks Executed' => 'Volume of automated tasks processed, with trend indicator (up/down vs. previous period).',
                'Usage Charts' => 'Interactive charts showing spending trends over time. Toggle between Daily, Weekly, and Monthly aggregation. Hover for exact values.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Operations — 50% Less Time on Status Meetings',
                    'scenario' => 'An operations director starts every morning with the Dashboard instead of waiting for team status reports. The combination of spend, activity, and usage charts gives a complete picture in 30 seconds.',
                    'result' => 'Weekly status meetings shrink from 60 minutes to 15 minutes. The team shifts from "reporting what happened" to "deciding what to do next". Anomaly detection via trend charts catches a misconfigured agent consuming $200/day before anyone would have noticed in spreadsheets.',
                ],
            ],
        ],
        'chat' => [
            'title' => 'Conversations & Chat',
            'description' => 'The Chat interface is your direct line to every AI agent in your organization. It provides a real-time messaging interface where you can converse with any active agent, review conversation history, search past messages, and see agent details and connected tools in a contextual sidebar. Each conversation is automatically saved, searchable, and can include file attachments and audio messages when enabled.',
            'how_to_use' => 'Open "Chat" from the sidebar. The agent list shows all agents you have access to — click any agent to start or resume a conversation. Type your message naturally. The agent responds in real time with streaming text. Use the search bar to find past conversations. The right sidebar shows the current agent\'s details, tools, and context.',
            'options' => [
                'Agent Selector' => 'Browse and select from all agents you have permission to access. Active agents are highlighted for quick identification.',
                'History Search' => 'Full-text search across all your past conversations. Find answers from weeks ago in seconds.',
                'Message Input' => 'Rich message composer with support for file attachments (images, PDFs, documents) and audio recording when enabled by the agent.',
                'Context Sidebar' => 'View the current agent\'s details, assigned tools, and capabilities while chatting — understand what the agent can and cannot do.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Knowledge Workers — 30% Less Time Searching for Information',
                    'scenario' => 'A team of 50 knowledge workers uses Chat to query internal agents instead of searching wikis, SharePoint, and Slack history for answers.',
                    'result' => 'Average time to find internal information drops from 12 minutes to under 2 minutes. Each worker saves ~40 minutes/day. The aggregate time saved across the team is equivalent to 4 full-time employees. Annual productivity gain: $320,000.',
                ],
            ],
        ],
        'live_support' => [
            'title' => 'Live Support',
            'description' => 'Live Support is the bridge between AI autonomy and human oversight. It provides a real-time monitoring console where human agents can observe every active widget conversation, intervene when the AI needs help, leave internal notes for team collaboration, and manage banned users. This is where your human team and AI agents work together — the AI handles the routine, humans handle the exceptions.',
            'how_to_use' => 'Open "Live Support" from the sidebar. You\'ll see all active widget sessions in real time. Click any session to observe the AI\'s responses. Toggle to "Human Mode" to take over the conversation manually. Switch back to "AI Mode" when you want the agent to resume. Use the sidebar to identify banned users (highlighted in red) and unban them. Leave internal notes visible only to your team.',
            'options' => [
                'Active Sessions' => 'Real-time view of all ongoing widget conversations. See which agent is handling each session and how long it has been active.',
                'Intervention' => 'Seamlessly switch between observing AI responses and taking manual control. Your messages appear to the user seamlessly — no context lost.',
                'Ban Management' => 'Identify banned users by red highlights in the sidebar. Unban with one click. Review ban reasons and history.',
                'Internal Notes' => 'Leave team-only notes on any conversation. Perfect for flagging edge cases, training opportunities, or handoff context.',
                'User Details' => 'See visitor context: location, device type, browser, conversation duration, and interaction history across sessions.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Customer Support — Handle 3x Volume with Same Team Size',
                    'scenario' => 'A support team of 10 agents uses Live Support to monitor 50+ concurrent AI-powered widget conversations. Humans only intervene when the AI flags an issue or the customer explicitly requests a human.',
                    'result' => 'Each human agent can oversee 15-20 AI conversations simultaneously vs. handling 3-4 live chats directly. Support volume capacity triples without hiring. Human agents focus on complex, high-value issues while the AI handles routine queries. CSAT remains at 4.6/5 as human touch is preserved for situations that need it.',
                ],
            ],
        ],
        'my_integrations' => [
            'title' => 'My Integrations',
            'description' => 'My Integrations is each user\'s personal OAuth connection hub. Here, individual users authorize Agentoom to act on their behalf with external services — connecting their Gmail for email sending, their Google Ads for campaign monitoring, or their HubSpot for CRM sync. Authorization is per-user, per-service, and fully revocable at any time from either Agentoom or the provider\'s own security settings.',
            'how_to_use' => 'Open "My Integrations" from the sidebar. You\'ll see all services globally enabled by your admin. Click any service (e.g., "Gmail", "HubSpot") to start the OAuth authorization flow. You\'ll be redirected to the provider to grant specific permissions. Once authorized, agents with the corresponding tools can use your connection. Revoke access anytime with one click.',
            'options' => [
                'Service List' => 'View all services globally enabled by your administrator. Each card shows the service name, icon, and connection status.',
                'Connection Status' => 'Real-time indicator showing which accounts are connected (green), need re-authorization (yellow), or are disconnected (gray).',
                'Disconnect' => 'One-click revocation of Agentoom\'s access to your account. Also revocable from the provider\'s side (Google, Meta, etc.).',
                'Authorized Scopes' => 'Transparent view of exactly which permissions you have granted — read-only vs. read-write, specific API scopes, and expiration dates.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Marketing Team — Personal Campaign Oversight Without Sharing Passwords',
                    'scenario' => 'A marketing team of 5 specialists each connects their own Google Ads and Meta Ads accounts via My Integrations. Each specialist\'s agent can monitor their specific campaigns without anyone sharing account credentials.',
                    'result' => 'Zero password sharing across the team. Each specialist\'s agent provides personalized campaign insights. When a team member leaves, revoking access takes one click — no password rotation needed across shared accounts.',
                ],
            ],
        ],
        'projects' => [
            'title' => 'Projects',
            'description' => 'Projects are the organizational and financial backbone of your Agentoom deployment. They let you group agents, tasks, pipelines, endpoints, and conversations into logical business units — each with its own budget cap and cost tracking. Use projects to separate clients (agency model), departments (enterprise model), or internal initiatives (product model). Every AI credit consumed within a project is automatically tagged for reporting and billing.',
            'how_to_use' => 'Go to "Projects" in the Analytics section. Create a new project with a name and optional USD budget limit. Assign agents, endpoints, schedules, and pipelines to the project from their respective configuration pages. View each project\'s mini-dashboard showing total spend, budget utilization percentage, and activity breakdown.',
            'options' => [
                'Budget Limit' => 'Optional USD budget cap per project. Color-coded alerts when approaching limits (80%, 90%, 100%). Separate from individual agent budgets.',
                'Cost Attribution' => 'All AI interactions within the project scope are automatically tagged. Export cost reports by project for client billing or departmental chargeback.',
                'Dashboard' => 'Per-project mini-dashboard with total spend, budget utilization, agent activity, and cost trend charts.',
                'Organization' => 'Assign any combination of agents, pipelines, endpoints, and schedules to a project. One entity belongs to one project at a time.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Agency Model — Accurate Client Billing at Zero Overhead',
                    'scenario' => 'A digital agency creates a Project per client. Each project contains the agents and pipelines serving that client, with budgets matching the client\'s retainer.',
                    'result' => 'Client billing goes from estimating (spreadsheets, guesswork) to exact (AI usage logs auto-tagged by project). Month-end reconciliation drops from 3 days to 1 hour. Clients receive transparent AI usage reports. Over-budget alerts prevent free work. Estimated annual revenue recovery from accurate billing: $85,000.',
                ],
            ],
        ],
        'agent_awareness' => [
            'title' => 'Agent Awareness & Task Delegation',
            'description' => 'By default, agents in Agentoom operate independently and are not automatically aware of other agents. Agent Awareness and Task Delegation is the system that enables true multi-agent collaboration: you explicitly configure which agents know about each other, when they should delegate work, and what permissions they have. This creates a controlled delegation network where agents hand off tasks intelligently based on triggers you define.',
            'how_to_use' => 'Open any agent\'s editor and go to the "Tasks" tab. Here you configure Outgoing Task Assignment permissions. Add a "Permitted Agent" and choose "Assign" (can create tasks) or "View" (can only see). Then write Assignment Instructions describing WHEN this agent should delegate — e.g., "If the user asks about pricing, assign a task to the Pricing Specialist Agent with the full conversation context."',
            'sections' => [
                'awareness' => [
                    'title' => 'Are agents aware of other agents?',
                    'content' => 'Agents are only aware of other agents for which they have been granted explicit assignment permissions. When an agent is given permission to assign tasks to another agent, it becomes "aware" of that agent\'s existence, its name, and the assignment explanation describing its purpose. This awareness is facilitated through the AssignAiTaskTool, which allows agents to list permitted agents and read their assignment instructions. Without this permission, agents operate completely independently.',
                ],
                'task_creation' => [
                    'title' => 'How to tell an agent to create a task for another agent',
                    'content' => 'Use the Assignment Instructions field in the Outgoing Task Assignment settings. Describe the specific situation or trigger in natural language. Examples: "Assign a task to this agent whenever the user asks for technical troubleshooting", "Delegate when the conversation exceeds 10 messages without resolution", "If the user mentions a competitor by name, assign to the Competitive Intelligence Agent". These instructions are injected into the agent\'s reasoning context, guiding it to use the assign_task action of the AssignAiTaskTool when the described conditions are met.',
                ],
            ],
            'roi_examples' => [
                [
                    'title' => 'Enterprise — Automated Cross-Department Workflows',
                    'scenario' => 'A large company creates a "Triage Agent" that receives all internal requests. Based on content, it delegates: IT questions to the "IT Support Agent", HR questions to the "HR Agent", and procurement requests to the "Procurement Agent".',
                    'result' => 'Employees get one chat interface for all internal services. The triage agent routes 100% of requests correctly, eliminating the "wrong department" problem. Each specialized agent handles only its domain, improving accuracy. Internal request resolution time drops 60%.',
                ],
            ],
        ],
        'expenses' => [
            'title' => 'Expenses Dashboard',
            'description' => 'The Expenses Dashboard is your financial deep-dive tool. While the main Dashboard gives you the headlines, Expenses lets you dissect every AI-related cost across every dimension: by project, by agent, by task type, by date range. View the raw transaction ledger of every AI interaction with exact token counts and costs. Click through from any line item to the specific conversation or task that generated the expense. This is essential for cost optimization, client billing, and budget governance.',
            'how_to_use' => 'Open "Expenses" from the sidebar. Use the multi-dimensional filters: select date ranges, specific projects, individual agents, or task types. The Action Breakdown shows which task categories consume the most budget. The Usage Logs table shows every transaction — sort by cost, date, or project. Click any row to jump to the source conversation or task.',
            'options' => [
                'Global Filters' => 'Filter by project, agent, task type, date range, or any combination. See organization-wide or narrow to specific slices.',
                'Action Breakdown' => 'Pie chart and table showing spending distribution by task type. Identify which automations are your biggest cost drivers.',
                'Usage Logs' => 'The raw transaction ledger: timestamp, agent, task type, tokens consumed, cost in USD, and associated project. Sortable and exportable.',
                'Audit Trail' => 'Click any expense line to open the exact conversation or task that generated it. Full traceability from cost to content.',
            ],
        ],
        'ai_credits' => [
            'title' => 'AI Credits',
            'description' => 'AI Credits is the prepaid balance that powers every LLM interaction in Agentoom. Unlike post-paid billing, credits give you absolute cost control: you load funds, set per-agent and per-project budgets, and receive alerts when balances run low. Every token consumed by every agent is tracked in real time, deducted from your credit balance, and logged for audit. Top-ups are available via supported payment providers like PayPal.',
            'how_to_use' => 'Open "AI Credits" from the sidebar. View your current balance, recent transactions, and usage trends. Configure low-balance alerts at thresholds you define. Top up via the integrated payment flow when needed. Use the transaction history to reconcile costs against agent activity in the Expenses Dashboard.',
            'options' => [
                'Current Balance' => 'Real-time USD balance. Color-coded: green (>30% of typical monthly spend), yellow (10-30%), red (<10%).',
                'Usage History' => 'Complete transaction ledger: top-ups, deductions, refunds. Each deduction links to the agent and task that consumed the credits.',
                'Top Up' => 'Add funds via supported payment providers. Minimum and maximum top-up amounts configurable by platform administrators.',
                'Billing Notifications' => 'Configurable alerts: balance below threshold, unusual spending spike, approaching budget caps. Notifications via email and in-app.',
            ],
        ],
        'users' => [
            'title' => 'User Management',
            'description' => 'User Management is where you control who has access to your Agentoom instance and what they can do. Invite team members, assign roles (Admin, Member, Client, or custom roles), manage profile settings, activate or deactivate accounts, and set individual preferences like language. Combined with RBAC from Enterprise Security, this gives you complete control over your organization\'s access structure.',
            'how_to_use' => 'Go to "Users" from the sidebar. View the list of all users with their roles and status. Click "Invite User" to send an invitation email. Click any user to edit their profile, change their role, toggle their active status, or set personalization preferences like default language and notification settings.',
            'options' => [
                'Role Assignment' => 'Assign standard roles (Admin, Member, Client) or custom roles with specific permission sets. Changing a role immediately updates access across the entire platform.',
                'Profile Settings' => 'Manage user profile data: display name, email address, and password. Users can also manage their own profiles.',
                'Status' => 'Activate or deactivate accounts. Deactivated users cannot log in but their data (conversations, tasks) is preserved. Reactivate anytime.',
                'Personalization' => 'Set per-user preferences: default language, notification preferences, and timezone. Users can override these in their own settings.',
            ],
        ],
        'instant_messaging' => [
            'title' => 'Instant Messaging',
            'description' => 'Instant Messaging extends your agents beyond the web widget to messaging platforms like WhatsApp via the Evolution API gateway. Your agents can respond to customers on the platforms they already use daily — no app downloads, no new accounts, no friction. Configure your WhatsApp Business API connection, link agents to specific numbers, and start serving customers in their preferred messaging channel.',
            'how_to_use' => 'Go to "Instant Messaging" under the Config section. Enter your Evolution API credentials (instance name, API key, server URL). Once connected, link specific WhatsApp numbers to specific agents. The agent will automatically respond to incoming messages, maintaining context across conversations just like the web chat. Monitor connection status and message logs in real time.',
            'how_it_works' => 'Agentoom connects to the Evolution API as a gateway to WhatsApp Business. Incoming messages are routed to the linked agent, which processes them using its full capabilities (instructions, skills, knowledge base, tools). The agent\'s response is sent back through the API to WhatsApp. All messages are logged for audit and quality review.',
            'options' => [
                'API Credentials' => 'Configure Evolution API connection: Instance Name, API Key, and Server URL. Support for multiple WhatsApp instances.',
                'Agent Linking' => 'Map specific WhatsApp numbers to specific agents. Different numbers can have different agents (support vs. sales, for example).',
                'Message Logs' => 'Complete history of all WhatsApp conversations with timestamps, agent responses, and message status.',
                'Status Monitoring' => 'Real-time connection health: connected, disconnected, reconnecting. Alerts on connection failures.',
            ],
            'roi_examples' => [
                [
                    'title' => 'Retail — 40% Higher Customer Engagement on WhatsApp',
                    'scenario' => 'A retail brand connects their customer support agent to WhatsApp. Customers who previously only contacted support via email or phone can now message the brand on WhatsApp and get instant AI-powered responses.',
                    'result' => 'Customer engagement increases 40% as the barrier to contact drops to near zero. Response time goes from hours (email) to seconds (WhatsApp + AI). Customer satisfaction scores increase from 3.8 to 4.5. The support team handles 3x more inquiries without additional headcount.',
                ],
            ],
        ],
        'eu_ai_act' => [
            'title' => 'EU AI Act Compliance',
            'description' => 'Comprehensive documentation covering Agentoom\'s built-in governance framework, assessment wizard, risk classification, incident management, audit logs, emergency stops, and evidence export for EU AI Act compliance readiness.',
        ],
    ],
    'what_is_agentoom' => [
        'title' => 'What is Agentoom?',
        'description' => 'Agentoom is an AI Operating System that enables businesses to build, deploy, and orchestrate autonomous AI agents at scale. Unlike simple chatbot platforms, Agentoom provides a complete ecosystem where agents can collaborate, learn from experience, access enterprise infrastructure, and execute multi-step workflows — all governed by enterprise-grade security and compliance frameworks. From customer-facing chat widgets to backend automation pipelines, Agentoom gives your teams the tools to transform business processes through intelligent automation.',
        'features' => [
            'title' => 'Platform Overview',
            'agents' => [
                'title' => 'AI Agents',
                'description' => 'Create and configure autonomous AI entities with specialized roles, custom instructions, skill sets, and tool access. Each agent is a dedicated AI worker with its own personality, knowledge domain, budget limits, and collaboration permissions.',
            ],
            'agent_skills' => [
                'title' => 'Agent Skills',
                'description' => 'Define structured business logic as reusable skill modules. Skills encapsulate mandatory requirements (Musts), prohibited actions (Donts), conditional logic (If-This-Then), and tool associations — ensuring consistent behavior across your entire agent fleet.',
            ],
            'knowledge_base' => [
                'title' => 'Knowledge Base',
                'description' => 'Ground your agents in your business reality. Upload documents (PDF, TXT, DOCX), scrape websites, or enter text manually. Agents use semantic chunking and vector search to retrieve precise, context-aware information — eliminating hallucinations.',
            ],
            'integrations' => [
                'title' => 'Third-Party Integrations',
                'description' => 'Connect Agentoom to your existing SaaS stack via OAuth. Supported services include Google (Gmail, Ads), Meta Ads, TikTok Ads, HubSpot, and more. Agents can send emails, check campaign performance, and sync data autonomously.',
            ],
            'pipelines' => [
                'title' => 'Automated Pipelines',
                'description' => 'Chain multiple agents and direct actions into sophisticated workflows. Pipelines support conditional branching, error handling, concurrency control, variables, and dynamic placeholders — turning complex business processes into one-click automations.',
            ],
            'widgets' => [
                'title' => 'Widgets',
                'description' => 'Embed AI-powered chat interfaces into any website with a single script tag. Fully customizable with branding, quick prompts, lead capture forms, file uploads, and audio support. Widgets can be public or restricted to specific users.',
            ],
            'endpoints' => [
                'title' => 'Web Endpoints',
                'description' => 'Expose your agents and pipelines to the world via secure HTTP endpoints. Trigger workflows from webhooks, CRMs, e-commerce platforms, or any external system. Configure IP whitelisting and JSON schema mapping for robust integration.',
            ],
            'schedules' => [
                'title' => 'Scheduled Tasks',
                'description' => 'Run agents, pipelines, or direct actions on a recurring schedule using standard cron syntax. Perfect for daily reports, periodic data syncs, midnight audits, and any task that needs to happen without human intervention.',
            ],
            'resources' => [
                'title' => 'System Resources',
                'description' => 'Give agents secure, encrypted access to your infrastructure — databases, APIs, file systems. Define resource types as reusable templates, manage access via roles, and let agents perform real-world operations like database queries or API calls.',
            ],
            'projects' => [
                'title' => 'Projects',
                'description' => 'Organize agents, tasks, and conversations into logical business units with independent budgets and cost tracking. Perfect for separating clients, departments, or internal initiatives with clear financial accountability.',
            ],
            'agent_awareness' => [
                'title' => 'Agent Awareness & Task Delegation',
                'description' => 'Control which agents know about each other and can delegate work. Configure outgoing task assignment permissions with specific triggers — enabling true multi-agent collaboration where agents hand off tasks intelligently.',
            ],
            'learning' => [
                'title' => 'Experience Learning',
                'description' => 'Agents continuously improve by recording successful interactions as reusable learned skills. The more your agents work, the smarter they become — building an institutional memory that grows with your business.',
            ],
            'security' => [
                'title' => 'Enterprise Security',
                'description' => 'Multi-layered protection: Role-Based Access Control (RBAC), Human-in-the-Loop (HITL) confirmation with verbal or OTP verification, automated user banning, and centralized OAuth management ensuring API keys are never exposed.',
            ],
            'prompts' => [
                'title' => 'Prompt Engineering',
                'description' => 'A comprehensive prompt management system with version control, multi-language support, contextual parameters, and display conditions. Create clickable quick-start prompts that guide users and inject hidden instructions to agents.',
            ],
            'eu_ai_act' => [
                'title' => 'EU AI Act Compliance',
                'description' => 'Built-in governance framework with assessment wizard, risk classification, incident management, tamper-evident audit logs, emergency stops, and one-click evidence export — supporting EU AI Act compliance readiness.',
            ],
        ],
    ],
];
