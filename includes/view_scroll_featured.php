<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 6: Scroll-Featured ---
// ===================================================================

if ( ! function_exists( 'my_project_render_calendar_scroll_featured_view' ) ) {
    function my_project_render_calendar_scroll_featured_view( $atts ) {
        // --- Caching Logic (Unchanged) ---
        $cache_version = get_option('my_calendar_cache_version', 1);
        $transient_key = 'my_cal_scroll_featured_' . md5(json_encode($atts)) . '_v' . $cache_version;
        if ( false !== ($cached_output = get_transient($transient_key)) ) { return $cached_output; }

        ob_start();
        
        // --- Settings and Initializations ---
        $options = get_option( 'my_calendar_settings', [] );
        $global_colors = $options['global'] ?? [];
        $default_bg_color = $global_colors['default_bg_color'] ?? '#f0f0f0';
        $default_text_color = $global_colors['default_text_color'] ?? '#333333';
        $scroll_date_bg_color = $global_colors['scroll_date_bg_color'] ?? '#a7b59a';
        $scroll_date_text_color = $global_colors['scroll_date_text_color'] ?? '#222222';

        // --- NEW: Get the desired image size from our settings page ---
        $image_size = $options['global']['scroll_image_size'] ?? 'medium';
        
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $now = new DateTime('now', $tz);
        $all_items = [];

        // --- Data Fetching for Featured Events ---
        $event_params = [ 'limit' => -1, 'where' => "featured.meta_value = 1 AND t.post_status = 'publish'", 'orderby' => 't.ID ASC' ];
        $event_pods = pods('event', $event_params);
        if ($event_pods->total() > 0) {
            while ($event_pods->fetch()) {
                // ... (Date and time logic remains unchanged)
                $start_val = $event_pods->field('event_start');
                $start_dates_arr = is_array($start_val) ? $start_val : [$start_val];
                $start_dates_arr = array_filter(array_map('trim', $start_dates_arr));
                if (empty($start_dates_arr)) continue;
                usort($start_dates_arr, function($a, $b) { return strtotime($a) <=> strtotime($b); });
                $first_date_str = reset($start_dates_arr);
                $last_date_str = end($start_dates_arr);
                $start_dt = new DateTime($first_date_str, $tz);
                $end_dt = new DateTime($last_date_str, $tz);
                if ($end_dt < $now) continue;
                $formatted_date_header = $start_dt->format('F j');
                if ($start_dt->format('Y-m-d') !== $end_dt->format('Y-m-d')) {
                    if ($start_dt->format('M') === $end_dt->format('M')) {
                        $formatted_date_header = $start_dt->format('M j') . ' - ' . $end_dt->format('j');
                    } else {
                        $formatted_date_header = $start_dt->format('M j') . ' - ' . $end_dt->format('M j');
                    }
                }
                $time_string = $start_dt->format('g:i A');
                $end_val = $event_pods->field('event_end');
                $end_time_arr = is_array($end_val) ? array_filter(array_map('trim', $end_val)) : [];
                $end_time_str = !empty($end_time_arr) ? reset($end_time_arr) : null;
                if (!empty($end_time_str) && $end_time_str !== '0000-00-00 00:00:00') {
                    $time_string .= ' – ' . (new DateTime($end_time_str, $tz))->format('g:i A');
                }
                $body_content = '<span class="play-show-time">' . esc_html($time_string) . '</span>';
                
                // --- MODIFIED: Get the correct image size ---
                $event_poster_data = $event_pods->field('poster');
                // Use wp_get_attachment_image_url() to get the specific size we want.
                $poster_url = !empty($event_poster_data['ID']) ? wp_get_attachment_image_url($event_poster_data['ID'], $image_size) : '';
                
                $details_html = '';
                $related_play = $event_pods->field('play');
                if ( !empty($related_play) && isset($related_play['post_title']) ) { $details_html .= '<div class="detail-item"><span class="detail-label">For:</span><span class="detail-name">' . esc_html($related_play['post_title']) . '</span></div>'; }
                $location_name = $event_pods->display('location_name');
                if (!empty($location_name)) { $details_html .= '<div class="detail-item"><span class="detail-label">Where:</span><span class="detail-name">' . esc_html($location_name) . '</span></div>'; }

                $all_items[] = [
                    'sort_timestamp' => $start_dt->getTimestamp(), 'permalink' => (string) $event_pods->field('permalink'), 'name' => (string) $event_pods->display('event_name'),
                    'color' => $event_pods->display('event_color'), 'text_color' => $event_pods->display('event_color_text'),
                    'formatted_date' => $formatted_date_header, 'body_content' => $body_content,
                    'poster_url' => $poster_url, 'details_html' => $details_html
                ];
            }
        }

        // --- Play Fetching Logic ---
        $play_params = [ 'limit' => -1, 'where' => "featured.meta_value = 1 AND end_date.meta_value >= '{$now->format('Y-m-d')}'", 'orderby' => 'start_date.meta_value ASC' ];
        $play_pods = pods('play', $play_params);
        if ($play_pods->total() > 0) { 
            while ($play_pods->fetch()) { 
                // ... (Play date logic is unchanged) ...
                $start_date_str = $play_pods->field('start_date'); $end_date_str = $play_pods->field('end_date'); if(empty($start_date_str) || empty($end_date_str)) continue; $start_dt = new DateTime($start_date_str, $tz); $end_dt = new DateTime($end_date_str, $tz); $formatted_date = $start_dt->format('F j'); if ($start_dt->format('Y-m-d') !== $end_dt->format('Y-m-d')) { if ($start_dt->format('M') === $end_dt->format('M')) { $formatted_date = $start_dt->format('M j') . ' - ' . $end_dt->format('j'); } else { $formatted_date = $start_dt->format('M j') . ' - ' . $end_dt->format('M j'); } }
                
                // --- MODIFIED: Get the correct image size ---
                $play_poster_data = $play_pods->field('poster');
                $poster_url = !empty($play_poster_data['ID']) ? wp_get_attachment_image_url($play_poster_data['ID'], $image_size) : '';

                $director_name = ''; $crew_data = $play_pods->field('crew'); if (is_array($crew_data)) { foreach ($crew_data as $crew_item) { if (isset($crew_item['ID'])) { $crew_pod = pods('crew', $crew_item['ID']); if ($crew_pod->exists() && $crew_pod->field('crew') === 'Director') { $actor_data = $crew_pod->field('actor'); $director_name = $actor_data['post_title'] ?? 'N/A'; break; } } } } $details_html = ''; if ($play_pods->display('play_author')) { $details_html .= '<div class="detail-item"><span class="detail-label">Written by:</span><span class="detail-name">' . esc_html($play_pods->display('play_author')) . '</span></div>'; } if (!empty($director_name)) { $details_html .= '<div class="detail-item"><span class="detail-label">Directed by:</span><span class="detail-name">' . esc_html($director_name) . '</span></div>'; } $date_string = function_exists('my_project_get_play_date_string') ? my_project_get_play_date_string($play_pods) : '';
                $all_items[] = [ 'sort_timestamp' => $start_dt->getTimestamp(), 'permalink' => (string) $play_pods->field('permalink'), 'name' => (string) $play_pods->display('post_title'), 'color' => $play_pods->display('calendar_color'), 'text_color' => $play_pods->display('calendar_color_text'), 'formatted_date' => $formatted_date, 'body_content' => $date_string, 'poster_url' => $poster_url, 'details_html' => $details_html ];
            }
        }

        // --- HTML Generation (Unchanged) ---
        usort($all_items, function($a, $b) { return $a['sort_timestamp'] <=> $b['sort_timestamp']; });
        $upcoming_items = array_slice($all_items, 0, (int) ($atts['limit'] ?? 10));
        $more_events_page = get_page_by_path( $atts['more_page'] ?? 'events' );
        $more_events_url = $more_events_page ? get_permalink( $more_events_page->ID ) : home_url('/events/');
        ?>
        <div class="horizontal-scroller-container scroll-featured-view">
            <div class="horizontal-scroller"><div class="scroller-viewport"><div class="scroller-wrapper">
                <?php foreach ($upcoming_items as $item):
                    $card_bg = !empty($item['color']) ? $item['color'] : $default_bg_color;
                    $card_text = !empty($item['text_color']) ? $item['text_color'] : $default_text_color;
                ?>
                    <div class="scroller-item" style="background-color: <?php echo esc_attr($card_bg); ?>; color: <?php echo esc_attr($card_text); ?>;">
                        <div class="scroller-item-date" style="background-color: <?php echo esc_attr($scroll_date_bg_color); ?>; color: <?php echo esc_attr($scroll_date_text_color); ?>;"><?php echo esc_html($item['formatted_date']); ?></div>
                        <a href="<?php echo esc_url($item['permalink']); ?>" class="scroller-item-link">
                            <div class="scroller-card-body <?php if (!empty($item['poster_url'])) echo 'has-poster'; ?>">
                                <?php if (!empty($item['poster_url'])): ?><div class="scroller-poster-column"><img src="<?php echo esc_url($item['poster_url']); ?>" alt=""></div><?php endif; ?>
                                <div class="scroller-item-details">
                                    <h3 class="scroller-item-title"><?php echo esc_html($item['name']); ?></h3>
                                    <div class="scroller-play-details">
                                        <?php if ( !empty($item['details_html']) ) : ?><div class="scroller-play-credits"><?php echo $item['details_html']; ?></div><?php endif; ?>
                                        <div class="scroller-play-dates"><?php echo $item['body_content']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </a>
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
