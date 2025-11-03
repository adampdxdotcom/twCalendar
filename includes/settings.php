<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- CALENDAR SETTINGS PAGE & MENU SETUP ---
// ===================================================================

if ( ! function_exists( 'my_calendar_settings_page_init' ) ) {

    // --- 1. INITIALIZATION ---
    function my_calendar_settings_page_init() {
        add_action( 'admin_menu', 'my_calendar_create_admin_menu' );
        add_action( 'admin_init', 'my_calendar_register_settings_save_handler' );
        add_action( 'admin_enqueue_scripts', 'my_calendar_admin_enqueue_scripts' );
        add_action( 'admin_footer-tw-calendar_page_calendar-settings', 'my_calendar_settings_page_javascript' );
    }
    my_calendar_settings_page_init();

    // --- 2. GETTER FOR DEFAULT COLORS ---
    function my_calendar_get_default_colors() {
        return [
            'default_bg_color' => '#f7f7f7', 'default_text_color' => '#000000',
            'scroll_date_bg_color' => '#a7b59a', 'scroll_date_text_color' => '#222222',
            'grid_header_bg_color' => '#a7b59a', 'grid_header_text_color' => '#222222',
            'grid_day_cell_bg_color' => '#f7f7f7', 'grid_day_number_text_color' => '#333333',
            'list_day_cell_bg_color' => '#f7f7f7', 'list_day_number_text_color' => '#555555',
            'spc_year_text_color' => '#333333', 'spc_dow_bg_color' => '#a7b59a',
            'spc_dow_text_color' => '#222222', 'spc_info_bg_color' => '#c0cfb2',
            'spc_info_text_color' => '#222222', 'spc_date_bg_color' => '#d1dacb',
            'spc_date_text_color' => '#333333',
        ];
    }

    // --- 3. REGISTER ADMIN MENU PAGE (FINAL CORRECTED STRUCTURE) ---
    function my_calendar_create_admin_menu() {
        $parent_slug = 'tw-calendar-view';

        // Add the top-level menu page. We capture the returned "hook" to use later.
        $hook_suffix = add_menu_page(
            'All Events',
            'TW Calendar',
            'edit_posts',
            $parent_slug,
            'my_calendar_render_all_events_page',
            'dashicons-calendar-alt',
            27
        );

        // This is the crucial hook that runs early and sets up our page's identity.
        add_action( "load-{$hook_suffix}", 'my_calendar_load_events_list_table_screen' );

        add_submenu_page( $parent_slug, 'All Events', 'All Events', 'edit_posts', $parent_slug );
        add_submenu_page( $parent_slug, 'Add New Event', 'Add New', 'edit_posts', 'post-new.php?post_type=event' );
        add_submenu_page( $parent_slug, 'Calendar Settings', 'Settings', 'manage_options', 'calendar-settings', 'my_calendar_render_settings_page' );
    }

    /**
     * This function runs on the special 'load-...' hook.
     * It runs BEFORE the page content is rendered and sets the correct context.
     * This is the key to solving the permissions and filtering errors.
     */
    function my_calendar_load_events_list_table_screen() {
        require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );
        
        global $typenow, $current_screen;
        $typenow = 'event';
        set_current_screen( 'edit-event' );

        global $my_calendar_list_table;
        $my_calendar_list_table = new WP_Posts_List_Table( [ 'screen' => get_current_screen() ] );
        
        add_filter( 'set-screen-option', function( $status, $option, $value ) {
            if ( 'edit_event_per_page' === $option ) { return $value; }
            return $status;
        }, 10, 3 );
    }

    /**
     * This function now has a much simpler job: just render the page.
     */
    function my_calendar_render_all_events_page() {
        global $my_calendar_list_table;
        
        $my_calendar_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Events</h1>
            <a href="post-new.php?post_type=event" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            
            <form id="posts-filter" method="get">
                <input type="hidden" name="post_type" value="event" />
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                
                <?php
                $my_calendar_list_table->views();
                $my_calendar_list_table->search_box( 'Search Events', 'event' );
                $my_calendar_list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    // --- 4. ENQUEUE SCRIPTS ---
    function my_calendar_admin_enqueue_scripts( $hook ) {
        if ( 'tw-calendar_page_calendar-settings' !== $hook ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    // --- 5. RENDER THE SETTINGS PAGE HTML ---
    function my_calendar_render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $options = get_option( 'my_calendar_settings', [] );
        $defaults = my_calendar_get_default_colors();
        $global_colors = wp_parse_args( $options['global'] ?? [], $defaults );
        $custom_styles = $options['styles'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p>Manage the global and custom color styles for your event calendars.</p>
            <form action="options.php" method="post">
                <?php settings_fields( 'my_calendar_settings_group' ); ?>
                <div id="settings-container" style="display: flex; gap: 30px;">
                    <div class="settings-main-column" style="flex: 2;">
                        <div class="settings-section" id="global-settings-section">
                            <nav class="nav-tab-wrapper">
                                <a href="#tab-scroll" class="nav-tab nav-tab-active">Scroll View</a>
                                <a href="#tab-grid" class="nav-tab">Grid View</a>
                                <a href="#tab-list" class="nav-tab">List View</a>
                                <a href="#tab-single-play" class="nav-tab">Single Play Calendar</a>
                                <a href="#tab-common" class="nav-tab">Common Event Colors</a>
                            </nav>
                            <div id="tab-scroll" class="tab-content active"><?php my_calendar_render_color_fields_subset( 'global', $global_colors, ['scroll_date_bg_color', 'scroll_date_text_color'], 'Scroll View Specific' ); ?></div>
                            <div id="tab-grid" class="tab-content"><?php my_calendar_render_color_fields_subset( 'global', $global_colors, ['grid_header_bg_color', 'grid_header_text_color', 'grid_day_cell_bg_color', 'grid_day_number_text_color'], 'Grid View Structure' ); ?></div>
                            <div id="tab-list" class="tab-content"><?php my_calendar_render_color_fields_subset( 'global', $global_colors, ['list_day_cell_bg_color', 'list_day_number_text_color'], 'List View Structure' ); ?></div>
                            <div id="tab-single-play" class="tab-content"><?php my_calendar_render_color_fields_subset( 'global', $global_colors, ['spc_year_text_color', 'spc_dow_bg_color', 'spc_dow_text_color', 'spc_info_bg_color', 'spc_info_text_color', 'spc_date_bg_color', 'spc_date_text_color'], 'Single Play Calendar' ); ?></div>
                            <div id="tab-common" class="tab-content"><?php my_calendar_render_color_fields_subset( 'global', $global_colors, ['default_bg_color', 'default_text_color'], 'Common Event Colors' ); ?></div>
                        </div>
                        <div class="settings-section">
                            <h2>Custom Calendar Styles</h2>
                            <p>Create named styles that inherit the global defaults. You only need to change the colors you want to override.</p>
                            <div id="custom-styles-container">
                                <?php if ( ! empty( $custom_styles ) ) : foreach ( $custom_styles as $id => $style ) : ?>
                                <div class="style-block" id="style-block-<?php echo esc_attr( $id ); ?>">
                                    <h3 class="style-title"><?php echo esc_html( $style['style_name'] ?? 'Unnamed' ); ?> <button type="button" class="button button-link-delete delete-style-button">Delete</button></h3>
                                    <p><strong>Shortcode:</strong> <code>[monthly_events_calendar id="<?php echo esc_attr( $id ); ?>"]</code></p>
                                    <?php my_calendar_render_color_fields_subset( 'styles', $style, array_keys($defaults), null, $id ); ?>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <button type="button" id="add-new-style" class="button button-secondary">Add New Custom Style</button>
                        </div>
                    </div>
                    <div class="settings-side-column" style="flex: 1;">
                        <div id="calendar-preview-pane" style="position: sticky; top: 50px;">
                            <h2>Live Preview</h2>
                            <div id="calendar-preview-wrapper"><!-- Previews generated by JS --></div>
                        </div>
                    </div>
                </div>
                <?php submit_button( 'Save All Settings' ); ?>
            </form>
        </div>
        <div id="new-style-template" style="display: none;">
            <?php my_calendar_render_color_fields_subset( 'styles', $defaults, array_keys($defaults), null, '__NEW_ID__', true ); ?>
        </div>
        <style>
            .tab-content { display: none; padding: 20px 0; border: none; } .tab-content.active { display: block; } .nav-tab-wrapper { margin-bottom: 0; } .settings-section { padding: 20px; border: 1px solid #ddd; background: #fff; margin-bottom: 20px;} .preview-mockup { transform: scale(0.9); transform-origin: top left; margin-top: 20px; font-family: sans-serif; } .style-block { border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; background: #fdfdfd; } .style-block h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; } .color-picker-wrap { display: flex; align-items: center; gap: 8px; } .reset-color-btn { text-decoration: none; font-size: 18px; } .scroller-item { width: 250px; height: 275px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow: hidden; } .scroller-item-link { text-decoration: none; color: inherit; display: flex; flex-direction: column; flex-grow: 1; } .scroller-item-date { padding: 15px; font-size: 1.2em; font-weight: bold; text-align: center; } .scroller-item-details { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; text-align: center; justify-content: space-between; } .scroller-item-title { font-size: 1.3em; line-height: 1.3; margin: 0; } .scroller-item-time { font-size: 1.1em; font-weight: 500; margin: 0; flex-shrink: 0; } .grid-preview-table { border-collapse: separate; border-spacing: 4px; width: 350px; } .grid-preview-table th, .grid-preview-table td { border: 1px solid #e5e5e5; text-align: left; padding: 8px; vertical-align: top; height: 70px; border-radius: 8px; } .grid-preview-table th { text-align: center; font-weight: bold; } .day-number { font-weight: bold; } .list-preview-item { border: 1px solid #e5e5e5; margin-bottom: 10px; display: flex; border-radius: 8px; overflow: hidden; } .list-preview-day { padding: 15px; text-align: center; border-right: 1px solid #ddd; } .list-preview-day .day-name { font-size: 0.8em; display: block; } .list-preview-day strong { font-size: 1.4em; } .list-preview-events { padding: 10px; flex-grow: 1; } .list-event-box { padding: 5px 8px; border-radius: 4px; font-size: 0.9em; margin-top: 5px; } .spc-preview { max-width: 300px; } .spc-year { font-size: 2.5em; text-align: center; margin-bottom: 0.5em; } .spc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8px; } .spc-button, .spc-info-box { padding: 10px; border-radius: 8px; font-weight: bold; text-align: center; border: 1px solid rgba(0,0,0,0.1); } .spc-info-box { grid-column: 1 / -1; }
        </style>
        <?php
    }

    // --- 6. HELPER TO RENDER COLOR FIELDS ---
    function my_calendar_render_color_fields_subset( $group, $values, $keys_to_render, $heading = null, $id = null, $is_template = false ) {
        $defaults = my_calendar_get_default_colors();
        $all_fields = [ 'default_bg_color' => 'Default Event Background', 'default_text_color' => 'Default Event Text', 'scroll_date_bg_color' => 'Date Bar Background', 'scroll_date_text_color' => 'Date Bar Text', 'grid_header_bg_color' => 'Header Background', 'grid_header_text_color' => 'Header Text', 'grid_day_cell_bg_color' => 'Day Cell Background', 'grid_day_number_text_color' => 'Day Number Text', 'list_day_cell_bg_color' => 'Day Cell Background', 'list_day_number_text_color' => 'Day Number Text', 'spc_year_text_color' => 'Year Text Color', 'spc_dow_bg_color' => 'Day of Week BG', 'spc_dow_text_color' => 'Day of Week Text', 'spc_info_bg_color' => 'Info Box BG', 'spc_info_text_color' => 'Info Box Text', 'spc_date_bg_color' => 'Date Button BG', 'spc_date_text_color' => 'Date Button Text' ];
        $base_name = "my_calendar_settings[{$group}]" . ($id ? "[{$id}]" : '');
        if ($heading) echo "<h3>{$heading}</h3>";
        $wrapper_start = $is_template ? '<div class="style-block" id="style-block-__NEW_ID__"><h3 class="style-title">New Style <button type="button" class="button button-link-delete delete-style-button">Delete</button></h3>' : '';
        echo $wrapper_start;
        ?>
        <table class="form-table">
            <?php if ($id === '__NEW_ID__'): ?>
            <tr><th scope="row"><label for="style-name-<?php echo esc_attr($id); ?>">Style Name</label></th><td><input type="text" id="style-name-<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($base_name); ?>[style_name]" value="" placeholder="e.g., Homepage Scroller"><p class="description">This will be used to create a unique ID for the shortcode.</p></td></tr>
            <?php endif; ?>
            <?php foreach ($keys_to_render as $key): 
                $label = $all_fields[$key]; $value = $values[$key] ?? ''; $default_color = $defaults[$key];
            ?>
            <tr><th scope="row"><label for="<?php echo esc_attr("{$group}-{$id}-{$key}"); ?>"><?php echo esc_html($label); ?></label></th>
                <td><div class="color-picker-wrap"><input type="text" id="<?php echo esc_attr("{$group}-{$id}-{$key}"); ?>" name="<?php echo esc_attr($base_name); ?>[<?php echo esc_attr($key); ?>]" class="wp-color-picker" value="<?php echo esc_attr($value); ?>" data-default-color="<?php echo esc_attr($default_color); ?>"><a href="#" class="reset-color-btn" title="Reset to default">↺</a></div></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        if ($is_template) echo '</div>';
    }
    
    // --- 7. JAVASCRIPT ---
    function my_calendar_settings_page_javascript() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                function updatePreviews() {
                    var s = {};
                    $('#global-settings-section .wp-color-picker').each(function() {
                        var name = $(this).attr('name');
                        var key = name.match(/\[([^\]]+)\]$/)[1];
                        s[key] = $(this).val();
                    });
                    $('#preview-tab-common .scroller-item, #preview-tab-scroll .scroller-item').css({ backgroundColor: s.default_bg_color, color: s.default_text_color });
                    $('#preview-tab-scroll .scroller-item-date').css({ backgroundColor: s.scroll_date_bg_color, color: s.scroll_date_text_color });
                    $('#preview-tab-grid .grid-preview-table th').css({ backgroundColor: s.grid_header_bg_color, color: s.grid_header_text_color });
                    $('#preview-tab-grid .grid-preview-table td').css({ backgroundColor: s.grid_day_cell_bg_color });
                    $('#preview-tab-grid .day-number').css({ color: s.grid_day_number_text_color });
                    $('#preview-tab-grid .list-event-box').css({ backgroundColor: s.default_bg_color, color: s.default_text_color });
                    $('#preview-tab-list .list-preview-item').css({ backgroundColor: s.list_day_cell_bg_color });
                    $('#preview-tab-list .day-number, #preview-tab-list .day-name').css({ color: s.list_day_number_text_color });
                    $('#preview-tab-list .list-event-box').css({ backgroundColor: s.default_bg_color, color: s.default_text_color });
                    $('#preview-tab-single-play .spc-year').css({ color: s.spc_year_text_color });
                    $('#preview-tab-single-play .spc-dow').css({ backgroundColor: s.spc_dow_bg_color, color: s.spc_dow_text_color });
                    $('#preview-tab-single-play .spc-info-box').css({ backgroundColor: s.spc_info_bg_color, color: s.spc_info_text_color });
                    $('#preview-tab-single-play .spc-date-button').css({ backgroundColor: s.spc_date_bg_color, color: s.spc_date_text_color });
                }

                function initColorPickers(context) {
                    $(context).find('.wp-color-picker').not('.wp-color-picker-init').wpColorPicker({
                        change: updatePreviews, clear: updatePreviews
                    }).addClass('wp-color-picker-init');
                }

                $('.nav-tab-wrapper a').on('click', function(e) {
                    e.preventDefault();
                    var target = $(this).attr('href');
                    $('.nav-tab, .tab-content').removeClass('nav-tab-active active');
                    $(this).addClass('nav-tab-active');
                    $(target).addClass('active');
                    $('.preview-mockup').hide();
                    $('#preview-' + target.substring(1)).show();
                });

                var previewWrapper = $('#calendar-preview-wrapper');
                var eventBoxHtml = `<div class="list-event-box">Event</div>`;
                previewWrapper.append(`<div id="preview-tab-common" class="preview-mockup" style="display:none;"><div class="scroller-item"><div class="scroller-item-date">Date Bar</div><a href="#" class="scroller-item-link" onclick="return false;"><div class="scroller-item-details"><h3 class="scroller-item-title">Example Event Title</h3><p class="scroller-item-time">7:30 PM</p></div></a></div></div>`);
                previewWrapper.append(`<div id="preview-tab-scroll" class="preview-mockup"><div class="scroller-item"><div class="scroller-item-date">December 25</div><a href="#" class="scroller-item-link" onclick="return false;"><div class="scroller-item-details"><h3 class="scroller-item-title">Example Event Title</h3><p class="scroller-item-time">7:30 PM</p></div></a></div></div>`);
                previewWrapper.append(`<div id="preview-tab-grid" class="preview-mockup" style="display:none;"><table class="grid-preview-table"><thead><tr><th>SUN</th><th>MON</th></tr></thead><tbody><tr><td><div class="day-number">1</div></td><td><div class="day-number">2</div></td></tr><tr><td><div class="day-number">3</div>${eventBoxHtml}</td><td><div class="day-number">4</div></td></tr></tbody></table></div>`);
                previewWrapper.append(`<div id="preview-tab-list" class="preview-mockup" style="display:none; width: 300px;"><div class="list-preview-item"><div class="list-preview-day"><span class="day-name">Sun</span><strong class="day-number">3</strong></div><div class="list-preview-events">${eventBoxHtml}</div></div><div class="list-preview-item"><div class="list-preview-day"><span class="day-name">Mon</span><strong class="day-number">4</strong></div><div class="list-preview-events"></div></div></div>`);
                previewWrapper.append(`<div id="preview-tab-single-play" class="preview-mockup" style="display:none;"><div class="spc-preview"><h2 class="spc-year">2026</h2><div class="spc-grid"><div class="spc-button spc-dow">THUR</div><div class="spc-button spc-dow">FRI</div><div class="spc-button spc-dow">SAT</div></div><div class="spc-info-box">Audition Dates:<br>Nov 14, Nov 16</div><div class="spc-grid"><div class="spc-button spc-date-button">Jan 29</div><div class="spc-button spc-date-button">Jan 30</div><div class="spc-button spc-date-button">Jan 31</div></div><div class="spc-info-box">Regular Show Time 7:30 PM</div></div></div>`);
                
                var commonTabLink = $('a[href="#tab-common"]'); commonTabLink.parent().append(commonTabLink);
                var commonTabContent = $('#tab-common'); commonTabContent.parent().append(commonTabContent);
                $('.nav-tab-wrapper a:first').trigger('click');
                initColorPickers(document); updatePreviews();

                $('#add-new-style').on('click', function(e) {
                    e.preventDefault();
                    var newId = '__NEW_ID__' + new Date().getTime();
                    var template = $('#new-style-template').html().replace(/__NEW_ID__/g, newId);
                    $('#custom-styles-container').append(template);
                    initColorPickers('#style-block-' + newId);
                });

                $(document).on('click', '.delete-style-button', function(e) { e.preventDefault(); if (confirm('Are you sure?')) { $(this).closest('.style-block').remove(); } });
                $(document).on('click', '.reset-color-btn', function(e) {
                    e.preventDefault();
                    var picker = $(this).closest('.color-picker-wrap').find('.wp-color-picker');
                    var defaultColor = picker.data('default-color');
                    picker.iris('color', defaultColor);
                });
            });
        </script>
        <?php
    }

    // --- 8. SAVE & SANITIZE ---
    function my_calendar_register_settings_save_handler() {
        register_setting( 'my_calendar_settings_group', 'my_calendar_settings', 'my_calendar_sanitize_settings' );
    }
    function my_calendar_sanitize_settings( $input ) {
        if ( function_exists('my_project_invalidate_calendar_cache') ) {
            my_project_invalidate_calendar_cache();
        }

        $sanitized_output = [];
        $defaults = my_calendar_get_default_colors();
        $color_keys = array_keys($defaults);

        if ( !empty( $input['global'] ) && is_array( $input['global'] ) ) {
            $sanitized_output['global'] = [];
            foreach ( $color_keys as $key ) {
                if ( isset( $input['global'][$key] ) ) {
                    $sanitized_output['global'][$key] = sanitize_hex_color( $input['global'][$key] );
                }
            }
        }
        if ( !empty( $input['styles'] ) && is_array( $input['styles'] ) ) {
            $sanitized_output['styles'] = [];
            foreach ( $input['styles'] as $id => $style_data ) {
                if ( empty( $style_data['style_name'] ) ) continue;
                $style_name = sanitize_text_field( $style_data['style_name'] );
                $current_id = ( strpos($id, '__NEW_ID__') === 0 ) ? sanitize_title( $style_name ) : $id;
                if ( isset( $sanitized_output['styles'][$current_id] ) ) { $current_id .= '-' . time(); }
                $sanitized_output['styles'][$current_id]['style_name'] = $style_name;
                foreach ( $color_keys as $key ) {
                    if ( isset( $style_data[$key] ) ) {
                        $sanitized_output['styles'][$current_id][$key] = sanitize_hex_color( $style_data[$key] );
                    }
                }
            }
        }
        return $sanitized_output;
    }
}
