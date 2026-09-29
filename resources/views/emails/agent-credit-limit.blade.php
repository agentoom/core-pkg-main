@extends('agentoom-core::layouts.email')

@section('content')
    <p>{{ __('agentoom-core::emails.agent_credit_limit.line1', [
        'agent' => $agent->name,
        'limit' => number_format($agent->max_usd_limit, 2),
        'period' => __('agentoom-core::admin.common.' . $agent->limit_period)
    ]) }}</p>

    <p>{{ __('agentoom-core::emails.agent_credit_limit.line2', [
        'type' => $agent->is_hard_limit ? __('agentoom-core::emails.agent_credit_limit.hard') : __('agentoom-core::emails.agent_credit_limit.soft'),
        'action' => $agent->is_hard_limit ? __('agentoom-core::emails.agent_credit_limit.will_be_blocked') : __('agentoom-core::emails.agent_credit_limit.will_still_be_processed')
    ]) }}</p>
@endsection
