<?php
namespace block_performance_graphs\privacy;

use core_privacy\local\metadata\null_provider;
use core_privacy\local\metadata\collection;

class provider implements null_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'null',
            ['data' => 'none'],
            'privacy:metadata:performance_graphs:null_provider'
        );
        return $collection;
    }

    public static function get_reason(): string {
        return 'privacy:metadata:performance_graphs:null_provider';
    }
}
