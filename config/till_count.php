<?php

/**
 * End-of-day till count (cash reconciliation).
 */
return [
    /**
     * Cash variance tones, in euro. Below `ok` is green, below `warn` is amber,
     * anything else is red. The same cut-offs the lodgement comparison uses.
     */
    'variance_ok' => 5.00,
    'variance_warn' => 20.00,

    /** A note is required when the cash variance reaches this amount. */
    'note_required_at' => 20.00,

    /**
     * How long "Counting as X" lasts once a PIN or password has been checked, in
     * minutes. Saving the count ends it earlier.
     */
    'identity_minutes' => 30,

    /** Wrong passwords allowed before that person's identification is refused. */
    'password_attempts' => 5,
    'password_lockout_minutes' => 15,

    /** How far back the lodgements page looks for till closes without a count. */
    'needs_count_days' => 60,
];
