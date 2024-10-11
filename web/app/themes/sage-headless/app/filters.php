<?php

/**
 * Theme filters.
 */

namespace App;

/**
 * Add "… Continued" to the excerpt.
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(' &hellip; <a href="%s">%s</a>', get_permalink(), __('Continued', 'sage'));
});

// best solution
add_filter( 'graphql_connection_max_query_amount', function( $amount, $source, $args, $context, $info  ) {
    $amount = 1000; // increase post limit to 1000
    return $amount;
}, 10, 5 );