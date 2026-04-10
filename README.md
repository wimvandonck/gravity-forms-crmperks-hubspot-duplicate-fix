# Gravity Forms – CRM Perks HubSpot Duplicate Feed Fix

When an admin duplicates a Gravity Form in WordPress, the CRM Perks HubSpot integration settings are **not** copied over to the new form by default. This forces admins to manually rebuild the entire HubSpot mapping from scratch for every cloned form.

This PHP snippet fixes that by hooking into Gravity Forms' duplication process and automatically copying the CRM Perks HubSpot feed configuration to the newly cloned form.

## Background

CRM Perks does **not** use the standard Gravity Forms feed table (`wp_gf_addon_feed`). Instead, it stores all HubSpot mappings in its own custom database table (`wp_vxg_hubspot`). This is why the standard Gravity Forms duplication process silently skips all CRM Perks settings.

## Requirements

- WordPress
- [Gravity Forms](https://www.gravityforms.com/)
- [CRM Perks HubSpot Plugin for Gravity Forms](https://www.crmperks.com/plugins/gravity-forms-plugins/gravity-forms-hubspot-plugin/)

## How to Install

Choose one of the following methods:

### Option A – Child Theme `functions.php`
Paste the `add_action` block and function from `duplicate-feed-fix.php` (excluding the plugin header comment) into your active child theme's `functions.php` file.

### Option B – Code Snippet Plugin
If you use a plugin like [WPCode](https://wordpress.org/plugins/insert-headers-and-footers/) or [Code Snippets](https://wordpress.org/plugins/code-snippets/), create a new PHP snippet and paste in the `add_action` block and function.

### Option C – Must-Use Plugin
Upload the entire `duplicate-feed-fix.php` file to your `/wp-content/mu-plugins/` directory. It will run automatically on every page load without needing to be activated.

## How It Works

1. Listens for the `gform_post_form_duplicated` action hook fired by Gravity Forms after a form is cloned.
2. Queries the `wp_vxg_hubspot` table for any feeds linked to the original form.
3. Strips the original primary key (`id`) so the database generates a fresh one.
4. Updates the `form_id` to point to the newly cloned form.
5. Appends `(Cloned)` to the feed name to keep things unique.
6. Scans inside any JSON configuration columns for internal `form_id` references and updates those too.
7. Inserts the updated feed row into the table and flushes the Gravity Forms cache so the new feed appears immediately in the admin UI.

## Notes

- **Database prefix:** The snippet uses `$wpdb->prefix` dynamically, so it works regardless of your custom database table prefix (e.g. `zqab_`, `wp_`, etc.).
- **Read safety:** The diagnostic tool used to discover the table location was fully read-only and did not modify any data.
- **Multiple feeds:** If a form has more than one HubSpot feed configured, all of them will be duplicated.

## Diagnostic Tool

If you ever need to re-verify where CRM Perks is storing its data (e.g. after a plugin update), a safe read-only diagnostic snippet is available. Add it temporarily to `functions.php` and navigate to **Tools > HubSpot DB Check** in your WordPress admin.

```php
add_action('admin_menu', 'vxg_hubspot_debug_menu');

function vxg_hubspot_debug_menu() {
    add_management_page(
        'HubSpot DB Check',
        'HubSpot DB Check',
        'manage_options',
        'vxg-hubspot-debug',
        'vxg_hubspot_debug_page'
    );
}

function vxg_hubspot_debug_page() {
    global $wpdb;
    echo '<div class="wrap"><h1>Database Check for CRM Perks HubSpot</h1>';

    $gf_table = $wpdb->prefix . 'gf_addon_feed';
    $feeds = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, form_id, addon_slug FROM {$gf_table} WHERE addon_slug LIKE %s",
        '%vxg_hubspot%'
    ) );
    echo '<h2>1. Standard Gravity Forms table (' . $gf_table . ')</h2>';
    echo ! empty( $feeds )
        ? '<p style="color:green;"><strong>Found ' . count($feeds) . ' rows.</strong></p><pre>' . print_r($feeds, true) . '</pre>'
        : '<p style="color:red;">No rows found.</p>';

    $options = $wpdb->get_results( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE %s",
        '%vxg_hubspot%'
    ) );
    echo '<h2>2. WP Options table</h2>';
    echo ! empty( $options )
        ? '<p style="color:green;"><strong>Found ' . count($options) . ' rows.</strong></p><pre>' . print_r($options, true) . '</pre>'
        : '<p style="color:red;">No rows found.</p>';

    $custom = $wpdb->get_results("SHOW TABLES LIKE '%vxg_hubspot%'");
    echo '<h2>3. Custom CRM Perks tables</h2>';
    echo ! empty( $custom )
        ? '<p style="color:green;"><strong>Found custom table(s).</strong></p><pre>' . print_r($custom, true) . '</pre>'
        : '<p style="color:red;">No custom tables found.</p>';

    echo '</div>';
}
```

## License

MIT – free to use and modify.
