<?php
// config for Bloomfield Feeds
return [
    'source_auth' => [
        // How often to update feeds from sources, in minutes
        'njpublicnotices_username' => env('SOURCE_NJPUBLICNOTICES_USERNAME'),
        'njpublicnotices_password' => env('SOURCE_NJPUBLICNOTICES_PASSWORD'),
    ],

    'source_misc' => [
        'njpublicnotices_actual_search_id' => 8028,
        'njpublicnotices_link_search_id' => 6742,
    ],
];
