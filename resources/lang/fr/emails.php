<?php

return [
    'security_otp' => [
        'subject' => '🔒 OTP de sécurité',
        'line1' => 'Une action sensible a été demandée nécessitant un code de confirmation OTP',
        'line2' => 'Votre code de sécurité est : :otp',
        'line3' => 'Il expirera dans 5 minutes.',
    ],
    'agent_credit_limit' => [
        'subject' => '⚠️ Limite de crédit atteinte pour l\'agent : :agent',
        'line1' => 'L\'agent ":agent" a atteint sa limite de crédit configurée de :limit $ (:period).',
        'line2' => 'La limite est :type, donc les demandes ultérieures :action.',
        'hard' => 'stricte',
        'soft' => 'souple',
        'will_be_blocked' => 'seront bloquées',
        'will_still_be_processed' => 'seront toujours traitées mais nous vous recommandons de vérifier l\'utilisation',
    ],
    'project_budget_limit' => [
        'subject' => '⚠️ Budget du projet atteint : :project',
        'line1' => 'Le projet ":project" ha atteint sa limite de budget configurée de :limit $.',
        'line2' => 'L\'utilisation de l\'IA pour ce projet sera suspendue jusqu\'à ce que le budget soit augmenté ou désactivé.',
    ],
];
