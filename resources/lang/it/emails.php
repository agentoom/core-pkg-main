<?php

return [
    'security_otp' => [
        'subject' => '🔒 OTP di sicurezza',
        'line1' => 'È stata richiesta un\'azione sensibile che richiede un codice di conferma OTP',
        'line2' => 'Il tuo codice di sicurezza è: :otp',
        'line3' => 'Scadrà tra 5 minuti.',
    ],
    'agent_credit_limit' => [
        'subject' => '⚠️ Limite di credito raggiunto per l\'agente: :agent',
        'line1' => 'L\'agente ":agent" ha raggiunto il suo limite di credito configurato di $:limit (:period).',
        'line2' => 'Il limite è :type, quindi le future richieste :action.',
        'hard' => 'rigido',
        'soft' => 'flessibile',
        'will_be_blocked' => 'saranno bloccate',
        'will_still_be_processed' => 'saranno comunque elaborate ma ti consigliamo di controllare l\'utilizzo',
    ],
    'project_budget_limit' => [
        'subject' => '⚠️ Budget progetto raggiunto: :project',
        'line1' => 'Il progetto ":project" ha raggiunto il limite di budget configurato di $:limit.',
        'line2' => 'L\'utilizzo dell\'IA per questo progetto sarà sospeso fino a quando il budget non verrà aumentato o disattivato.',
    ],
];
