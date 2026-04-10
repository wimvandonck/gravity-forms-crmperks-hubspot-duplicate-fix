<?php
/**
 * Plugin Name: Gravity Forms – CRM Perks HubSpot Duplicate Feed Fix
 * Description: Automatically copies CRM Perks HubSpot feed settings when a Gravity Form is duplicated.
 * Version: 1.0.0
 * Author: Wim Vandonck
 *
 * How to use:
 * Option A) Paste the code below (excluding the plugin header) into your child theme's functions.php.
 * Option B) Add this entire file as a Must-Use (mu-plugins) plugin.
 * Option C) Use a code snippet plugin like WPCode and paste the add_action block below.
 */

add_action( 'gform_post_form_duplicated', 'copy_custom_crmperks_hubspot_feed', 10, 2 );

function copy_custom_crmperks_hubspot_feed( $original_form_id, $new_form_id ) {
    global $wpdb;

    // Target the exact custom table used by CRM Perks HubSpot.
    // The prefix (e.g. 'zqab_') is handled automatically by $wpdb->prefix.
    $table_name = $wpdb->prefix . 'vxg_hubspot';

    // 1. Get all CRM Perks mappings for the original form
    $original_feeds = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table_name} WHERE form_id = %d", $original_form_id ),
        ARRAY_A
    );

    if ( ! empty( $original_feeds ) ) {
        foreach ( $original_feeds as $feed ) {

            // 2. Remove the unique primary key so the database auto-generates a new one
            if ( isset( $feed['id'] ) ) {
                unset( $feed['id'] );
            }

            // 3. Update the form_id to point to the newly cloned form
            $feed['form_id'] = $new_form_id;

            // 4. Make the feed name unique to avoid conflicts
            if ( isset( $feed['name'] ) ) {
                $feed['name'] = $feed['name'] . ' (Cloned)';
            }
            if ( isset( $feed['feed_name'] ) ) {
                $feed['feed_name'] = $feed['feed_name'] . ' (Cloned)';
            }

            // 5. Scan JSON columns for hidden internal form_id references and update them
            foreach ( $feed as $key => $value ) {
                if ( is_string( $value ) && ( strpos( $value, '{' ) === 0 || strpos( $value, '[' ) === 0 ) ) {
                    $decoded = json_decode( $value, true );
                    if ( is_array( $decoded ) && isset( $decoded['form_id'] ) ) {
                        $decoded['form_id'] = $new_form_id;
                        $feed[ $key ] = wp_json_encode( $decoded );
                    }
                }
            }

            // 6. Insert the cloned row into the CRM Perks custom table
            $wpdb->insert( $table_name, $feed );
        }

        // 7. Clear Gravity Forms cache so the UI reflects the new feeds immediately
        if ( class_exists( 'GFCache' ) ) {
            GFCache::flush();
        } else {
            wp_cache_flush();
        }
    }
}
