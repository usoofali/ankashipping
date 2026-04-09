<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'failover'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            'from' => [
                'address' => env('MAIL_SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME'))),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'operations' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_OPERATIONS_USERNAME'),
            'password' => env('MAIL_OPERATIONS_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_OPERATIONS_FROM_ADDRESS', env('MAIL_OPERATIONS_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'booking' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_BOOKING_USERNAME'),
            'password' => env('MAIL_BOOKING_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_BOOKING_FROM_ADDRESS', env('MAIL_BOOKING_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'noreply' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_NOREPLY_USERNAME'),
            'password' => env('MAIL_NOREPLY_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_NOREPLY_FROM_ADDRESS', env('MAIL_NOREPLY_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'services' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_SERVICES_USERNAME'),
            'password' => env('MAIL_SERVICES_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_SERVICES_FROM_ADDRESS', env('MAIL_SERVICES_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'accounts' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_ACCOUNTS_USERNAME'),
            'password' => env('MAIL_ACCOUNTS_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_ACCOUNTS_FROM_ADDRESS', env('MAIL_ACCOUNTS_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'google' => [
            'transport' => 'smtp',
            'scheme' => 'smtps',
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'username' => env('MAIL_GOOGLE_USERNAME'),
            'password' => env('MAIL_GOOGLE_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_GOOGLE_FROM_ADDRESS', env('MAIL_GOOGLE_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        // Newsletter-specific roundrobin accounts
        'news1' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_NEWS1_USERNAME'),
            'password' => env('MAIL_NEWS1_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_NEWS1_FROM_ADDRESS', env('MAIL_NEWS1_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'news2' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_NEWS2_USERNAME'),
            'password' => env('MAIL_NEWS2_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_NEWS2_FROM_ADDRESS', env('MAIL_NEWS2_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'news3' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'smtp.hostinger.com'),
            'port' => env('MAIL_PORT', 465),
            'username' => env('MAIL_NEWS3_USERNAME'),
            'password' => env('MAIL_NEWS3_PASSWORD'),
            'timeout' => null,
            'from' => [
                'address' => env('MAIL_NEWS3_FROM_ADDRESS', env('MAIL_NEWS3_USERNAME')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'ses' => [

            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'operations',
                'booking',
                'services',
                'accounts',
                'noreply',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

        // Round-robin for newsletters: evenly distributes load across accounts
        'newsletter' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'news1',
                'news2',
                'news3',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
