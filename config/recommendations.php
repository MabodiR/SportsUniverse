<?php

return [
    'analysis_url' => env('CONTENT_ANALYSIS_URL'),
    'analysis_token' => env('CONTENT_ANALYSIS_TOKEN'),
    'analysis_timeout' => (int) env('CONTENT_ANALYSIS_TIMEOUT', 120),
    'sports_review_threshold' => (float) env('SPORTS_REVIEW_THRESHOLD', 0.45),
];
