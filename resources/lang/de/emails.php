<?php

return [
    'security_otp' => [
        'subject' => '🔒 Sicherheits-OTP',
        'line1' => 'Es wurde eine sensible Aktion angefordert, die einen OTP-Bestätigungscode erfordert',
        'line2' => 'Ihr Sicherheitscode lautet: :otp',
        'line3' => 'Er läuft in 5 Minuten ab.',
    ],
    'agent_credit_limit' => [
        'subject' => '⚠️ Kreditlimit für Agent erreicht: :agent',
        'line1' => 'Der Agent ":agent" hat sein konfiguriertes Kreditlimit von :limit $ (:period) erreicht.',
        'line2' => 'Das Limit ist :type, daher werden weitere Anfragen :action.',
        'hard' => 'strikt',
        'soft' => 'weich',
        'will_be_blocked' => 'blockiert',
        'will_still_be_processed' => 'weiterhin verarbeitet, aber wir empfehlen, die Nutzung zu überprüfen',
    ],
    'project_budget_limit' => [
        'subject' => '⚠️ Projektbudget erreicht: :project',
        'line1' => 'Das Projekt ":project" hat sein konfiguriertes Budgetlimit von :limit $ erreicht.',
        'line2' => 'Die KI-Nutzung für dieses Projekt wird pausiert, bis das Budget erhöht oder deaktiviert wird.',
    ],
];
