@extends('agentoom-core::layouts.email')

@section('content')
    <p>{{ __('agentoom-core::emails.project_budget_limit.line1', [
        'project' => $project->name,
        'limit' => number_format($project->budget_limit, 2)
    ]) }}</p>

    <p>{{ __('agentoom-core::emails.project_budget_limit.line2') }}</p>
@endsection
