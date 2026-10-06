<?php
/**
 * Plugin Name: Gravity Forms – CRM Perks HubSpot Duplicate Feed Fix
 * Description: Copies CRM Perks HubSpot feeds when a Gravity Form is duplicated, including the links between feeds (deal → contact → company).
 * Version: 1.1.0
 * Author: Wim Vandonck
 *
 * How to use:
 * A) Paste everything below this header into your child theme's functions.php.
 * B) Paste it into a code snippet plugin (WPCode, Code Snippets) as a PHP snippet.
 * C) Upload this whole file to /wp-content/mu-plugins/.
 */

add_action( 'gform_post_form_duplicated', 'copy_custom_crmperks_hubspot_feed', 10, 2 );

function copy_custom_crmperks_hubspot_feed( $original_form_id, $new_form_id ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'vxg_hubspot';

    $original_feeds = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table_name} WHERE form_id = %d ORDER BY id ASC", $original_form_id ),
        ARRAY_A
    );

    if ( empty( $original_feeds ) ) {
        return;
    }

    $id_map = array(); // old feed ID => new feed ID

    // Step 1: copy all feeds and keep track of the old => new ID mapping
    foreach ( $original_feeds as $feed ) {
        $old_id = (string) $feed['id'];
        unset( $feed['id'] );

        $feed['form_id'] = $new_form_id;

        if ( isset( $feed['name'] ) ) {
            $feed['name'] .= ' (Cloned)';
        }
        if ( isset( $feed['feed_name'] ) ) {
            $feed['feed_name'] .= ' (Cloned)';
        }

        if ( $wpdb->insert( $table_name, $feed ) ) {
            $id_map[ $old_id ] = (string) $wpdb->insert_id;
        }
    }

    // Step 2: in the cloned feeds, remap all object_* references and form_ids
    foreach ( $id_map as $new_id ) {
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $new_id ),
            ARRAY_A
        );
        if ( ! $row ) {
            continue;
        }

        $updates = array();

        foreach ( $row as $column => $value ) {
            if ( in_array( $column, array( 'id', 'form_id' ), true ) || ! is_string( $value ) || $value === '' ) {
                continue;
            }

            $is_serialized = is_serialized( $value );
            $data = $is_serialized ? maybe_unserialize( $value ) : json_decode( $value, true );

            if ( ! is_array( $data ) ) {
                continue;
            }

            $changed = false;
            $data = vx_remap_feed_refs( $data, $id_map, $original_form_id, $new_form_id, $changed );

            if ( $changed ) {
                $updates[ $column ] = $is_serialized ? maybe_serialize( $data ) : wp_json_encode( $data );
            }
        }

        if ( ! empty( $updates ) ) {
            $wpdb->update( $table_name, $updates, array( 'id' => $new_id ) );
        }
    }

    if ( class_exists( 'GFCache' ) ) {
        GFCache::flush();
    } else {
        wp_cache_flush();
    }
}

if ( ! function_exists( 'vx_remap_feed_refs' ) ) {
    function vx_remap_feed_refs( $data, $id_map, $old_form_id, $new_form_id, &$changed ) {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $data[ $key ] = vx_remap_feed_refs( $value, $id_map, $old_form_id, $new_form_id, $changed );
                continue;
            }

            if ( ! is_scalar( $value ) || ! is_string( $key ) ) {
                continue;
            }

            // Reference to another feed, e.g. object_contact or object_company
            if ( strpos( $key, 'object_' ) === 0 && isset( $id_map[ (string) $value ] ) ) {
                $data[ $key ] = $id_map[ (string) $value ];
                $changed = true;
            }

            // Hidden form_id reference
            if ( $key === 'form_id' && (string) $value === (string) $old_form_id ) {
                $data[ $key ] = $new_form_id;
                $changed = true;
            }
        }
        return $data;
    }
}
