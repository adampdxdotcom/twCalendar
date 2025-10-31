<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
  
// ===================================================================
// --- Snippet 5: Calendar View Renderer: Scroll Widget ---
// ===================================================================

if ( ! function_exists( 'my_project_render_calendar_scroll_view' ) ) {
    function my_project_render_calendar_scroll_view( $atts ) {
        // --- Caching Logic (Unchanged) ---
        $cache_version = get_option('my_calendar_cache_version', 1);
        $transient_key = 'my_cal_scroll_' . md5(json_encode($atts)) . '_v' . $cache_version;
        if ( false !== ($cached_output = get_transient($transient_key)) ) { return $cached_output; }

        ob_start();
        
        // --- Settings and Initializations (Unchanged) ---
        $options = get_option( 'my_calendar_settings', [] );
        $global_colors = $options['global'] ?? [];
        $default_bg_color = $global_colors['default_bg_color'] ?? '#f0f0f0';
        $default_text_color = $global_colors['default_text_color'] ?? '#333333';
        $scroll_date_bg_color = $global_colors['scroll_date_bg_color'] ?? '#a7b59a';
        $scroll_date_text_color = $global_colors['scroll_date_text_color'] ?? '#222222';
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $now = new DateTime('now', $tz);
        $all_events = [];
        $far_future = (clone $now)->modify('+5 years');

        // --- Data Fetching for Events (MODIFIED) ---
        $event_params = [ 'limit' => -1, 'where' => "t.post_status = 'publish'" ];
        $event_pods = pods('event', $event_params);
        if ($event_pods->total() > 0) {
            while ($event_pods->fetch()) {
                // =============================================================
                // --- THIS IS THE NEW, ROBUST LOGIC FOR MULTI-DATE EVENTS ---
                // =============================================================
                $start_val = $event_pods->field('event_start');
                // Ensure we're working with an array
                $start_dates = is_array($start_val) ? $start_val : [$start_val]; 
                if (empty($start_dates)) continue;
                
                $event_data_template = [
                    'permalink'  => (string) $event_pods->field('permalink'),
                    'name'       => (string) $event_pods->display('event_name'),
                    'color'      => $event_pods->display('event_color'),
                    'text_color' => $event_pods->display('event_color_text'),
                    'is_matinee' => false
                ];

                foreach($start_dates as $start_str) {
                    if (empty($start_str)) continue;
                    
                    try {
                        $dt = new DateTime($start_str, $tz);
                    } catch (Exception $e) { continue; }

                    // Only process events that are in the future
                    if ($dt < $now) {
                        continue;
                    }

                    $all_events[] = array_merge($event_data_template, [
                        'sort_timestamp' => $dt->getTimestamp(),
                        'formatted_date' => $dt->format('F j'),
                        'formatted_time' => $dt->format('g:i A')
                    ]);
                }
                // =============================================================
            }
        }
        
        // --- Data Fetching for Plays (Unchanged) ---
        if ( function_exists('my_project_get_play_performances') ) {
            $play_params = [ 'limit' => -1, 'where' => "end_date.meta_value >= '{$now->format('Y-m-d')}'" ];
            $play_pods = pods('play', $play_params);
            if ($play_pods->total() > 0) {
                while ($play_pods->fetch()) {
                    $play_performances = my_project_get_play_performances($play_pods, $now, $far_future);
                    $all_events = array_merge($all_events, $play_performances);
                }
            }
        }
        
        // --- HTML Generation (Unchanged) ---
        usort($all_events, function($a, $b) { return $a['sort_timestamp'] <=> $b['sort_timestamp']; });
        $upcoming_events = array_slice($all_events, 0, (int) ($atts['limit'] ?? 10));
        $more_events_page = get_page_by_path( $atts['more_page'] ?? 'events' );
        $more_events_url = $more_events_page ? get_permalink( $more_events_page->ID ) : home_url('/events/');
        $visible_items = intval($atts['visible_items'] ?? 5);
        if ($visible_items < 1) { $visible_items = 5; }
        $unique_id = 'scroller-' . uniqid();
        ?>
        <style>#<?php echo $unique_id; ?> .scroller-item { flex-basis: calc((100% - (<?php echo($visible_items - 1); ?> * var(--scroller-gap))) / <?php echo $visible_items; ?>); }</style>
        <div id="<?php echo $unique_id; ?>" class="horizontal-scroller-container">
            <div class="horizontal-scroller"><div class="scroller-viewport"><div class="scroller-wrapper">
                <?php foreach ($upcoming_events as $event):
                    $card_bg = !empty($event['color']) ? $event['color'] : $default_bg_color;
                    $card_text = !empty($event['text_color']) ? $event['text_color'] : $default_text_color;
                ?>
                    <div class="scroller-item" style="background-color: <?php echo esc_attr($card_bg); ?>; color: <?php echo esc_attr($card_text); ?>;">
                        <div class="scroller-item-date" style="background-color: <?php echo esc_attr($scroll_date_bg_color); ?>; color: <?php echo esc_attr($scroll_date_text_color); ?>;"><?php echo esc_html($event['formatted_date']); ?></div>
                        <a href="<?php echo esc_url($event['permalink']); ?>" class="scroller-item-link"><div class="scroller-item-details"><h3 class="scroller-item-title"><?php if ($event['is_matinee']) { echo 'Matinee: <br>'; } echo esc_html($event['name']); ?></h3><p class="scroller-item-time"><?php echo esc_html($event['formatted_time']); ?></p></div></a>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo esc_url($more_events_url); ?>" class="scroller-item scroller-item--more"><span>More<br>Events</span></a>
            </div></div><button class="scroller-arrow scroller-arrow--prev">&lt;</button><button class="scroller-arrow scroller-arrow--next">&gt;</button></div>
        </div>
        <?php
        $output = ob_get_clean();
        set_transient($transient_key, $output, 15 * MINUTE_IN_SECONDS);
        return $output;
    }
}
