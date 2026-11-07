<?php

return [
    'otp_expiration_minutes' => max(1, min(10, (int) env('LOGIN_OTP_EXPIRATION_MINUTES', 5))),
    'otp_max_attempts' => max(1, min(10, (int) env('LOGIN_OTP_MAX_ATTEMPTS', 5))),
    'otp_resend_cooldown_seconds' => max(1, (int) env('LOGIN_OTP_RESEND_COOLDOWN_SECONDS', 60)),
    'otp_send_limit_per_hour' => max(1, (int) env('LOGIN_OTP_SEND_LIMIT_PER_HOUR', 5)),
    'otp_ip_send_limit_per_hour' => max(1, (int) env('LOGIN_OTP_IP_SEND_LIMIT_PER_HOUR', 20)),
    'login_account_failures_per_hour' => max(1, (int) env('LOGIN_ACCOUNT_FAILURES_PER_HOUR', 10)),
    'login_ip_failures_per_hour' => max(1, (int) env('LOGIN_IP_FAILURES_PER_HOUR', 50)),
    'progressive_lockout_seconds' => max(5, (int) env('LOGIN_PROGRESSIVE_LOCKOUT_SECONDS', 30)),
    'progressive_lockout_max_seconds' => max(30, (int) env('LOGIN_PROGRESSIVE_LOCKOUT_MAX_SECONDS', 900)),
    'events_retention_days' => max(30, (int) env('LOGIN_SECURITY_EVENTS_RETENTION_DAYS', 180)),
];
