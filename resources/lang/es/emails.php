<?php

return [
    'security_otp' => [
        'subject' => '🔒 OTP de seguridad',
        'line1' => 'Se ha solicitado una acción sensible que requiere un código de confirmación OTP',
        'line2' => 'Tu código de seguridad es: :otp',
        'line3' => 'Expirará en 5 minutos.',
    ],
    'agent_credit_limit' => [
        'subject' => '⚠️ Límite de crédito alcanzado para el agente: :agent',
        'line1' => 'El agente ":agent" ha alcanzado su límite de crédito configurado de $:limit (:period).',
        'line2' => 'El límite es :type, por lo que las solicitudes futuras :action.',
        'hard' => 'estricto',
        'soft' => 'flexible',
        'will_be_blocked' => 'serán bloqueadas',
        'will_still_be_processed' => 'seguirán procesándose pero recomendamos revisar el uso',
    ],
    'project_budget_limit' => [
        'subject' => '⚠️ Presupuesto del proyecto alcanzado: :project',
        'line1' => 'El proyecto ":project" ha alcanzado su límite de presupuesto configurado de $:limit.',
        'line2' => 'El uso de la IA para este proyecto se pausará hasta que se aumente o desactive el presupuesto.',
    ],
];
