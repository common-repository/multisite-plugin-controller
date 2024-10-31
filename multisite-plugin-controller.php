<?php
/**
 * Plugin Name: Multisite Plugin Controller
 * Description: Enable plugins for selected blogs only on Multisite websites (similar to theme functionality)
 * Version: 1.0.0
 * Network: true
 * Author: Barbara Bothe
 * Author URI: https://barbara-bothe.de
 * Text Domain: mpc
 * Domain Path: /languages
 * Requires at least: 3.9.2
 * Tested up to: 5.6
 * License: GPLv2 or later
 * GitHub Plugin URI: https://github.com/Cassandre/multisite-plugin-controller
 * GitHub Branch: master
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * mpcLoadTextdomain
 * Load plugin textdomain.
 * @return void
 */
function mpcLoadTextdomain() {
    load_plugin_textdomain( 'mpc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'mpcLoadTextdomain' );


/**
 * mpcNewSiteinfoTab
 * Add new tab in Websites Settings
 * @return void
 */
function mpcNewSiteinfoTab( $tabs ){
    $tabs['site-plugins'] = array(
        'label' => 'Plugins',
        'url' => 'sites.php?page=plugins',
        'cap' => 'manage_sites'
    );
    return $tabs;
}
add_filter( 'network_edit_site_nav_links', 'mpcNewSiteinfoTab' );


/**
 * mpcNewAdminPage
 * Add submenu page under Sites
 * @return void
 */
function mpcNewAdminPage(){
    add_submenu_page(
        'sites.php',
        'Edit website', // will be displayed in <title>
        'Edit website', // doesn't matter
        'manage_network_options', // capabilities
        'plugins',
        'mpcHandleAdminPage' // the name of the function which displays the page
    );
}
add_action( 'network_admin_menu', 'mpcNewAdminPage' );


/**
 * mpcHideSubmenuEntry
 * Some CSS to hide the link to our custom submenu page
 * @return void
 */
function mpcHideSubmenuEntry(){
    echo '<style>
	#menu-site .wp-submenu li:last-child{
		display:none;
	}
	</style>';
}
add_action('admin_head','mpcHideSubmenuEntry');


/**
 * mpcHandleAdminPage
 * Display the Plugins page
 * @return void
 */
function mpcHandleAdminPage(){
    $blog_id = absint($_REQUEST['id']);

    // handle disable/enable actions
    if (isset($_REQUEST['action']) && isset($_REQUEST['checked'])) {
        $enabledPlugins = get_blog_option($blog_id,'enabled_plugins', []);
        $checked = is_array($_REQUEST['checked']) ? $_REQUEST['checked'] : (array)$_REQUEST['checked'];
        array_walk($checked, 'sanitize_text_field');
        switch ($_REQUEST['action']) {
            case 'enable':
            case 'enable-selected':
                foreach ($checked as $item) {
                    if (!in_array($item, (array)$enabledPlugins)) {
                        $enabledPlugins[] = $item;
                    }
                }
                break;
            case 'disable':
            case 'disable-selected':
                foreach ($checked as $item) {
                    // check if plugin is activated
                    $activePlugins = get_blog_option($blog_id, 'active_plugins');
                    if (in_array($item, $activePlugins)) {
                        $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $item);
                        $class = 'notice notice-error is-dismissible';
                        $message = sprintf(__('<b>Deactivation Failed</b>: The plugin "%s" is activated on this website. Please deactivate it on the website before disabling it here.', 'mpc'), '<b>' . $pluginData['Name'] . '</b>');
                        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ),  $message  );
                        continue;
                    }
                    if (($key = array_search($item, $enabledPlugins)) !== false) {
                        unset($enabledPlugins[$key]);
                    }
                }
                break;
        }
        update_blog_option( $blog_id, 'enabled_plugins', $enabledPlugins );
    }

    $details = get_site( $blog_id );
    $title = sprintf( __( 'Edit Site: %s' ), esc_html( $details->blogname ) );

    echo '<div class="wrap"><h1 id="edit-site">' . $title . '</h1>
	<p class="edit-site-actions"><a href="' . esc_url( get_home_url( $blog_id, '/' ) ) . '">' . __('Visit') . '</a> | <a href="' . esc_url( get_admin_url( $blog_id ) ) . '">' . __('Dashboard') . '</a></p>';

    // navigation tabs
    network_edit_site_nav( array(
        'blog_id'  => $blog_id,
        'selected' => 'site-plugins' // current tab
    ) );

    // some css
    echo '
		<style>
		#menu-site .wp-submenu li.wp-first-item{
			font-weight:600;
		}
		#menu-site .wp-submenu li.wp-first-item a{
			color:#fff;
		}
		</style>';

    // page content
    $allPlugins = get_plugins();
    $noNetworkPlugins = [];
    foreach ($allPlugins as $key => $plugin) {
        if (!is_plugin_active_for_network($key)){
            $noNetworkPlugins[] = $key;
        }
    }
    $enabledPlugins = get_blog_option($blog_id,'enabled_plugins', []);
    $nonce = wp_create_nonce('mpc-check' . $blog_id);

    $currentAll = $currentEnabled = $currentDisabled = $getAdd ='';
    if (isset($_GET['plugin_status'])) {
        switch ($_GET['plugin_status']) {
            case 'enabled':
                $currentEnabled = 'class="current" aria-current="page"';
                $getAdd = '&plugin_status=enabled';
                break;
            case 'disabled':
                $currentDisabled = 'class="current" aria-current="page"';
                $getAdd = '&plugin_status=disabled';
                break;
            case 'all':
            default:
                $currentAll = 'class="current" aria-current="page"';
                $getAdd = '&plugin_status=all';
                break;
        }
    } else {
        $currentAll = 'class="current" aria-current="page"';
    }

    echo '<p>' . __('Network enabled plugins are not shown on this screen.', 'mpc') . '</p>'
        . '<h2 class="screen-reader-text">' . __('Filter plugins list') . '</h2>'
        . '<ul class="subsubsub">'
        . '<li class="all"><a href="sites.php?page=plugins&id=' . $blog_id . '&plugin_status=all" '.$currentAll.'>' . __('All') . ' <span class="count">(' . count($noNetworkPlugins) . ')</span></a> |</li>'
        . '<li class="enabled"><a href="sites.php?page=plugins&id=' . $blog_id . '&plugin_status=enabled" '.$currentEnabled.'>' . __('Enabled') . ' <span class="count">(' . count($enabledPlugins) . ')</span></a> |</li>'
        . '<li class="disabled"><a href="sites.php?page=plugins&id=' . $blog_id . '&plugin_status=disabled" '.$currentDisabled.'>' . __('Disabled') . ' <span class="count">(' . (count($noNetworkPlugins) - count($enabledPlugins)) . ')</span></a></li>'
        . '</ul>';
    echo '<form method="post" action="sites.php?page=plugins&id=' . $blog_id . '&action=update-site">';
    wp_nonce_field( $nonce );
    echo '<input type="hidden" name="id" value="' . $blog_id . '" />'
        . '<div class="tablenav top">
				<div class="alignleft actions bulkactions">
			<label for="bulk-action-selector-top" class="screen-reader-text">' . __('Select bulk action') . '</label>'
        . '<select name="action" id="bulk-action-selector-top">
                <option value="-1">' . __('Bulk actions') . '</option>
                <option value="enable-selected">' . __('Enable') . '</option>
                <option value="disable-selected">' . __('Disable') . '</option>
            </select>
            <input type="submit" id="doaction" class="button action" value="' . __('Apply') . '">
		</div></div>'
        . '<h2 class="screen-reader-text">' . __('Plugins list') . '</h2>'
        . '<table class="wp-list-table widefat plugins">'
        .'<thead>
	        <tr>'
        . '<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">' . __( 'Select All' ) . '</label><input id="cb-select-all-1" type="checkbox"></td>'
        .'<th scope="col" id="name" class="manage-column column-name column-primary">Plugin</th>'
        .'<th scope="col" id="description" class="manage-column column-description">Beschreibung</th>	</tr>
	        </thead>';
    foreach (get_plugins() as $key => $plugin) {
        if (is_plugin_active_for_network($key)){
            continue;
        }
        if ($currentEnabled != '' && !in_array($key, $enabledPlugins)) {
            continue;
        }
        if ($currentDisabled != '' && in_array($key, $enabledPlugins)) {
            continue;
        }
        if (in_array($key, $enabledPlugins)) {
            $rowClass = 'active';
            $actionLink = sprintf('<span class="disable"><a href="sites.php?page=plugins&id=%s&action=disable&checked=%s&paged=1&s&_wpnonce=%s%s" class="edit" aria-label="%s">%s</a></span>', $blog_id, str_replace('/', '%2F', $key), $nonce, $getAdd, __('disable').$plugin['Name'], __('Disable'));
        } else {
            $rowClass = 'inactive';
            $actionLink = sprintf('<span class="enable"><a href="sites.php?page=plugins&id=%s&action=enable&checked=%s&paged=1&s&_wpnonce=%s%s" class="edit" aria-label="%s">%s</a></span>', $blog_id, str_replace('/', '%2F', $key), $nonce, $getAdd, __('enable').$plugin['Name'], __('Enable'));
        }

        echo '<tr class="'.$rowClass.'">';
        echo '<th scope="row" class="check-column"><input type="checkbox" value="' . $key . '" id="checkbox_' . $key . '" name="checked[]"></th>';
        echo '<td class="plugin-title column-primary"><label for="checkbox_' . $key . '"><strong>' . $plugin['Name'] . '</strong></label>'
            . '<div class="row-actions visible">' . $actionLink . '</div>'
            . '</td>'
            . '<td class="column-description desc"><div class="plugin-description"><p>' . $plugin['Description'] . '</p></div>'
            . '<div class="inactive second plugin-version-author-uri">'
            . 'Version '. $plugin['Version'] . ' | '
            . sprintf( __( 'By %s' ), ' <a href="' . $plugin['AuthorURI'] . '">'. $plugin['Author'] . '</a>')
            . ' | <a href="' . $plugin['PluginURI'] . '">' . __('View details') . '</a>'
            . '</div>'
            . '</td>';
        echo '</tr>';

    }
    echo '</table></form>';
    echo '</div>';
}


/**
 * mpcHideDisabledPlugins
 * In blog plugins list show only plugins that are enabled for this blog or active for network
 * @return void
 */
function mpcHideDisabledPlugins() {
    if (get_current_screen()->base != 'plugins') {
        return;
    }
    global $wp_list_table;
    global $blog_id;
    $enabledPlugins = get_blog_option($blog_id,'enabled_plugins', []);
    $myplugins = $wp_list_table->items;
    foreach ($myplugins as $key => $val) {
        if (!in_array($key,$enabledPlugins) && !is_plugin_active_for_network($key)) {
            unset($wp_list_table->items[$key]);
        }
    }
}
add_action('pre_current_active_plugins', 'mpcHideDisabledPlugins');

