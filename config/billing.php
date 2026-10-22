<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Over-quota policy for the Free plan
    |--------------------------------------------------------------------------
    |
    | Days counted from when a Free-plan account was first found above its
    | storage limit (users.over_quota_since). Uploads are blocked from day 0.
    | After `restrict_after_days` new galleries and client downloads pause,
    | after `archive_after_days` galleries go offline to clients, and after
    | `delete_after_days` the oldest galleries are permanently deleted until
    | the account is back within the Free limit.
    |
    */

    'over_quota' => [
        'restrict_after_days' => (int) env('OVER_QUOTA_RESTRICT_AFTER_DAYS', 30),
        'archive_after_days' => (int) env('OVER_QUOTA_ARCHIVE_AFTER_DAYS', 60),
        'delete_after_days' => (int) env('OVER_QUOTA_DELETE_AFTER_DAYS', 90),
    ],

];
