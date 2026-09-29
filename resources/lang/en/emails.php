<?php

return [
    'security_otp' => [
        'subject' => '🔒 Security OTP',
        'line1' => 'A sensitive action has been requested that requires an OTP confirmation code',
        'line2' => 'Your security code is: :otp',
        'line3' => 'It will expire in 5 minutes.',
    ],
    'agent_credit_limit' => [
        'subject' => '⚠️ Credit limit reached for agent: :agent',
        'line1' => 'The agent ":agent" has reached its configured credit limit of $:limit (:period).',
        'line2' => 'The limit is :type, so further requests :action.',
        'hard' => 'hard',
        'soft' => 'soft',
        'will_be_blocked' => 'will be blocked',
        'will_still_be_processed' => 'will still be processed but we recommend to check the usage',
    ],
    'project_budget_limit' => [
        'subject' => '⚠️ Project budget reached: :project',
        'line1' => 'The project ":project" has reached its configured budget limit of $:limit.',
        'line2' => 'AI usage for this project will be paused until the budget is increased or deactivated.',
    ],
];
