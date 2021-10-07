<?php
// config for Bloomfield Feeds
return [
    'source_auth' => [
        // How often to update feeds from sources, in minutes
        'njpublicnotices_username' => env('SOURCE_NJPUBLICNOTICES_USERNAME'),
        'njpublicnotices_password' => env('SOURCE_NJPUBLICNOTICES_PASSWORD'),
    ],

    'source_misc' => [
        'njpublicnotices_actual_search_id' => 8034,
        'njpublicnotices_link_search_id' => 6742,

        'wbmatv_access_key' => 'gmcC3sJ6AGUdIb568B18VQd22AGea7RE',
        'wbmatv_preshared_key' => 'e85fc8dbf6be4f22327da329cbd0de56d868b118028519eb14b4a68853ff845b64e6befdfd9138c2b51cc43d477baaaae3902a5a181c0f07f07c5a775a34a63b', // phpcs:ignore
        'wbmatv_vids_per_playlist' => 5,
    ],
];
