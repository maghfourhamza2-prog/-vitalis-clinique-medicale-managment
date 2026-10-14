<?php

return [
    // Le frontend demande deja de contacter l'administrateur. En production,
    // l'API suit cette politique et ferme l'inscription libre.
    'allow_public_registration' => filter_var(
        env(
            'ALLOW_PUBLIC_REGISTRATION',
            env('APP_ENV', 'production') !== 'production'
        ),
        FILTER_VALIDATE_BOOL
    ),

    // Le seeder principal contient des patients, medecins et comptes de
    // demonstration. Il est autorise localement, mais jamais en production
    // sans opt-in explicite.
    'allow_demo_seeding' => filter_var(
        env(
            'ALLOW_DEMO_SEEDING',
            in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        ),
        FILTER_VALIDATE_BOOL
    ),

    'seed_passwords' => [
        'admin' => env('VITALIS_SEED_ADMIN_PASSWORD'),
        'medecin' => env('VITALIS_SEED_MEDECIN_PASSWORD'),
        'patient' => env('VITALIS_SEED_PATIENT_PASSWORD'),
    ],

    'audit' => [
        // Prefer a dedicated production key. APP_KEY is a safe fallback for
        // development/testing so audit signatures are never plain hashes.
        'hmac_key' => env('AUDIT_HMAC_KEY') ?: env('APP_KEY'),
    ],

    'mfa' => [
        // En production, les roles sensibles passent obligatoirement par un
        // second facteur envoye par email. Les tests et le developpement local
        // peuvent le desactiver explicitement.
        'enforce_sensitive_roles' => filter_var(
            env(
                'MFA_ENFORCE_SENSITIVE_ROLES',
                env('APP_ENV', 'production') === 'production'
            ),
            FILTER_VALIDATE_BOOL
        ),
        'roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('MFA_SENSITIVE_ROLES', 'administrateur,medecin'))
        ))),
        'ttl_seconds' => (int) env('MFA_CODE_TTL_SECONDS', 300),
        'max_attempts' => (int) env('MFA_MAX_ATTEMPTS', 5),
    ],
];
