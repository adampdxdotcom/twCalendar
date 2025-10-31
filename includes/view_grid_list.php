<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===================================================================
// --- Snippet 4: Calendar View Renderer: Monthly ---
// ===================================================================

if ( ! function_exists( 'my_project_render_calendar_monthly_view' ) ) {
    /**
     * Renders the monthly calendar in either a grid or list format.
     *
     * @param array  $atts         The full shortcode attributes array.
     * @param string $current_view The specific view to render ('grid' or 'list').
     * @return string The HTML for the monthly calendar.
     */
    function my_project_render_calendar_monthly_view( $atts, $current_view ) {
        // --- Settings and Date Calculations (Unchanged) ---
        $options = get_option( 'my_calendar_settings', [] );
        $global_colors = $options['global'] ?? [];
        $default_bg_color = $global_colors['default_bg_color'] ?? '#f0f0f0';
        $default_text_color = $global_colors['default_text_color'] ?? '#333333';
        $grid_header_bg_color = $global_colors['grid_header_bg_color'] ?? '#a7b59a';
        $grid_header_text_color = $global_colors['grid_header_text_color'] ?? '#222222';
        $grid_day_cell_bg_color = $global_colors['grid_day_cell_bg_color'] ?? '#ffffff';
        $grid_day_number_text_color = $global_colors['grid_day_number_text_color'] ?? '#333333';
        $list_day_cell_bg_color = $global_colors['list_day_cell_bg_color'] ?? '#ffffff';
        $list_day_number_text_color = $global_colors['list_day_number_text_color'] ?? '#333333';

        $forced_view = in_array($atts['view'], ['grid', 'list']) || $atts['class'] === 'sidebar-widget';
        $base_url = strtok($_SERVER["REQUEST_URI"], '?');
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $month = isset($_GET['c_month']) ? intval($_GET['c_month']) : date('n');
        $year  = isset($_GET['c_year'])  ? intval($_GET['c_year'])  : date('Y');
        $current_date = new DateTime("$year-$month-01", $tz);
        $prev_date = (clone $current_date)->modify('-1 month');
        $next_date = (clone $current_date)->modify('+1 month');
        $first_day_of_month_sql = $current_date->format('Y-m-d 00:00:00');
        $last_day_of_month_sql = $current_date->format('Y-m-t 23:59:59');
        $events_by_day = [];

        // =============================================================
        // --- Data Fetching for Events (FIXED QUERY) ---
        // =============================================================
        $event_params = [
            'limit' => -1,
            'where' => [
                'relation' => 'AND',
                [
                    'key'     => 'event_start.meta_value', // Target individual dates in the array
                    'value'   => [$first_day_of_month_sql, $last_day_of_month_sql],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME'
                ],
                [
                    'key'     => 't.post_status',
                    'value'   => 'publish'
                ]
            ]
        ];
        $event_pods = pods('event', $event_params);
        if ($event_pods->total() > 0) {
            while ($event_pods->fetch()) {
                // Now the rest of your original logic will work correctly because
                // it's only receiving events that are relevant to this month.
                $start_val = $event_pods->field('event_start');
                $start_dates = is_array($start_val) ? $start_val : [$start_val]; 
                if (empty($start_dates)) continue;

                $event_data_template = [ 
                    'id' => $event_pods->id(), 'name' => (string)$event_pods->display('event_name'), 'location' => (string)$event_pods->display('location_name'), 
                    'address' => (string)$event_pods->display('address'), 'color' => $event_pods->display('event_color'), 
                    'text_color' => $event_pods->display('event_color_text'), 'permalink' => $event_pods->field('permalink'), 'type' => 'event'
                ];

                $end_val = $event_pods->field('event_end');
                $end_str = is_array($end_val) ? reset($end_val) : $end_val;
                $end_time_formatted = !empty($end_str) ? (new DateTime($end_str, $tz))->format('g:i A') : '';
                
                foreach ($start_dates as $start_str) {
                    if (empty(trim($start_str))) continue;
                    
                    try {
                        $dt = new DateTime($start_str, $tz);
                    } catch (Exception $e) { continue; }
                    
                    // This check is still important for events that span across months.
                    // The query brings in the whole event if *any* date matches,
                    // so we still need to filter out the specific dates that are not in this month.
                    if ($dt->format('Y-n') !== $current_date->format('Y-n')) {
                        continue;
                    }

                    $day = (int)$dt->format('j');
                    $ical_link = add_query_arg(['download_ical' => 'true', 'post_id' => $event_pods->id(), 'post_type' => 'event', 'datetime' => $dt->format('Y-m-d H:i:s')], home_url());
                    
                    $events_by_day[$day][] = array_merge($event_data_template, [
                        'time' => $dt->format('g:i A'),
                        'sort_time' => $dt->format('H:i:s'),
                        'end_time' => $end_time_formatted,
                        'ical_link' => $ical_link
                    ]);
                }
            }
        }
        
        // --- Data Fetching for Plays (Unchanged and correctly implemented) ---
        $play_params = [ 'limit' => -1, 'where' => [ ['key' => 'start_date', 'value' => $last_day_of_month_sql, 'compare' => '<=', 'type' => 'DATE'], ['key' => 'end_date', 'value' => $first_day_of_month_sql, 'compare' => '>=', 'type' => 'DATE'] ] ];
        $play_pods = pods('play', $play_params);
        if ($play_pods->total() > 0) {
            while ($play_pods->fetch()) {
                $play_start_val = $play_pods->field('start_date'); $play_end_val = $play_pods->field('end_date');
                $play_start_str = is_array($play_start_val) ? reset($play_start_val) : $play_start_val; $play_end_str = is_array($play_end_val) ? reset($play_end_val) : $play_end_val;
                if (empty($play_start_str) || empty($play_end_str)) continue;
                $play_id = $play_pods->id(); $play_name = (string)$play_pods->display('post_title'); $play_permalink = $play_pods->field('permalink'); $play_color = $play_pods->display('calendar_color'); $play_text_color = $play_pods->display('calendar_color_text'); $play_poster_data = $play_pods->field('poster'); $play_poster_url = !empty($play_poster_data['guid']) ? $play_poster_data['guid'] : ''; $play_author = $play_pods->display('play_author'); $ticket_url = $play_pods->display('ticket_url');
                $director_name = ''; $director_permalink = '';
                $crew_relationship_data = $play_pods->field('crew');
                if ( !empty($crew_relationship_data) && is_array($crew_relationship_data) ) { foreach ( $crew_relationship_data as $crew_item ) { if ( !isset($crew_item['ID']) ) continue; $crew_pod = pods('crew', $crew_item['ID']); if ( $crew_pod->exists() && $crew_pod->field('crew') === 'Director' ) { $actor_data = $crew_pod->field('actor'); if ( !empty($actor_data) && is_array($actor_data) ) { $director_name = $actor_data['post_title'] ?? 'N/A'; $director_permalink = $actor_data['permalink'] ?? '#'; break; } } } }
                $regular_time_str = $play_pods->field('regular_show_time'); $regular_time_ts = !empty($regular_time_str) ? strtotime($regular_time_str) : false; $formatted_show_time = $regular_time_ts ? date('g:i A', $regular_time_ts) : 'All Day';
                $full_play_data = [ 'id' => $play_id, 'name' => $play_name, 'color' => $play_color, 'text_color' => $play_text_color, 'permalink' => $play_permalink, 'poster_url' => $play_poster_url, 'author' => $play_author, 'director_name' => $director_name, 'director_permalink' => $director_permalink, 'ticket_url' => $ticket_url ];
                $performance_schedule = []; $play_start_date = new DateTime($play_start_str, $tz); $play_end_date = (new DateTime($play_end_str, $tz))->modify('+1 day'); $period = new DatePeriod($play_start_date, new DateInterval('P1D'), $play_end_date);
                foreach ($period as $date) { if (in_array((int)$date->format('N'), [4, 5, 6])) { $performance_schedule[$date->format('Y-m-d')] = ['status' => 'regular']; } }
                foreach ($play_pods->field('added_dates') ?: [] as $date_str) { $performance_schedule[date('Y-m-d', strtotime($date_str))] = ['status' => 'added']; }
                foreach ($play_pods->field('cancellations') ?: [] as $date_str) { unset($performance_schedule[date('Y-m-d', strtotime($date_str))]); }
                foreach ($performance_schedule as $date_str => $data) { $date_obj = new DateTime($date_str, $tz); if ($date_obj->format('Y-n') === $current_date->format('Y-n')) { $day = (int)$date_obj->format('j'); $play_datetime_str = $date_obj->format('Y-m-d') . ($regular_time_ts ? ' ' . date('H:i:s', $regular_time_ts) : ''); $ical_link = add_query_arg(['download_ical' => 'true', 'post_id' => $play_id, 'post_type' => 'play', 'datetime' => $play_datetime_str], home_url()); $sort_time = $regular_time_ts ? date('H:i:s', $regular_time_ts) : '00:00:00'; $events_by_day[$day][] = array_merge($full_play_data, ['time' => $formatted_show_time, 'sort_time' => $sort_time, 'type' => 'play', 'status' => $data['status'], 'ical_link' => $ical_link]); } }
                foreach ($play_pods->field('matinee_date') ?: [] as $datetime_str) { $matinee_obj = new DateTime($datetime_str, $tz); if ($matinee_obj->format('Y-n') === $current_date->format('Y-n')) { $day = (int)$matinee_obj->format('j'); $ical_link = add_query_arg(['download_ical' => 'true', 'post_id' => $play_id, 'post_type' => 'play', 'datetime' => $matinee_obj->format('Y-m-d H:i:s'), 'is_matinee' => '1'], home_url()); $events_by_day[$day][] = array_merge($full_play_data, ['time' => $matinee_obj->format('g:i A'), 'sort_time' => $matinee_obj->format('H:i:s'), 'type' => 'matinee', 'ical_link' => $ical_link]); } }
            }
        }
        
        // --- Data Sorting and HTML Output (Unchanged) ---
        // (The rest of your file remains exactly the same)
        // ...
        foreach ($events_by_day as $day => &$events_on_this_day) { usort($events_on_this_day, function($a, $b) { return $a['sort_time'] <=> $b['sort_time']; }); }
        unset($events_on_this_day); ksort($events_by_day, SORT_NUMERIC);
        
        $unique_id = 'calendar-' . uniqid();
        ob_start(); ?>
        <style>
            #<?php echo $unique_id; ?> .calendar-wrapper th { background-color: <?php echo esc_attr($grid_header_bg_color); ?>; color: <?php echo esc_attr($grid_header_text_color); ?>; }
            #<?php echo $unique_id; ?> .calendar-wrapper td { background-color: <?php echo esc_attr($grid_day_cell_bg_color); ?>; }
            #<?php echo $unique_id; ?> .day-number strong { color: <?php echo esc_attr($grid_day_number_text_color); ?>; }
            #<?php echo $unique_id; ?>.list-view .day-wrapper { background-color: <?php echo esc_attr($list_day_cell_bg_color); ?>; }
            #<?php echo $unique_id; ?>.list-view .day-number strong, #<?php echo $unique_id; ?>.list-view .day-number .day-name { color: <?php echo esc_attr($list_day_number_text_color); ?>; }
        </style>
        <div id="<?php echo $unique_id; ?>" class="calendar-container <?php echo esc_attr($atts['class']); if($current_view === 'list'){ echo ' list-view'; } ?>">
            <div class="calendar-title"><?php echo $current_date->format('F Y'); ?></div>
            <?php if ($current_view === 'list'): ?>
                <div class="calendar-wrapper"><?php if (empty($events_by_day)): ?><p style="text-align: center;">There are no events scheduled for this month.</p><?php else: ?><?php foreach ($events_by_day as $day => $events_today): $day_date = DateTime::createFromFormat('Y-n-j', "$year-$month-$day"); $day_name = $day_date ? $day_date->format('l') : ''; $td_classes = ['has-events', 'day-wrapper']; foreach ($events_today as $ev) { if (isset($ev['status']) && $ev['status'] === 'added') { $td_classes[] = 'added-day'; break; } } ?><div class="<?php echo implode(' ', $td_classes); ?>"><div class="day-number"> <span class="day-name"><?php echo esc_html($day_name); ?></span> <strong><?php echo $day; ?></strong> </div><div class="day-events-container"><?php foreach ($events_today as $ev): $box_classes = ['event-box']; if (isset($ev['status'])) { if ($ev['status'] === 'cancelled') $box_classes[] = 'cancelled'; if ($ev['status'] === 'added') $box_classes[] = 'added'; } $modal_id = 'modal-content-' . $ev['type'] . '-' . $ev['id']; $card_bg = !empty($ev['color']) ? $ev['color'] : $default_bg_color; $card_text = !empty($ev['text_color']) ? $ev['text_color'] : $default_text_color; ?><a href="<?php echo esc_url($ev['permalink']); ?>" class="event-link" data-modal-target="#<?php echo $modal_id; ?>"><div class="<?php echo implode(' ', $box_classes); ?>" style="background:<?php echo esc_attr($card_bg); ?>; color:<?php echo esc_attr($card_text); ?>"><?php if (in_array('cancelled', $box_classes)): elseif ($ev['type'] === 'matinee'): ?> <div class="event-time"><?php echo esc_html($ev['time']); ?></div><div class="event-title">Matinee:<br><?php echo esc_html($ev['name']); ?></div><?php else: ?> <div class="event-time"><?php echo esc_html($ev['time']); ?></div><div class="event-title"><?php echo esc_html($ev['name']); ?></div><?php endif; ?></div></a><?php endforeach; ?></div></div><?php endforeach; ?><?php endif; ?></div>
            <?php else: ?>
                <table class="calendar-wrapper">
                    <thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
                    <tbody>
                        <tr>
                        <?php
                        $first_day = $current_date; $days_in_month = (int) $first_day->format('t'); $first_weekday = (int) $first_day->format('w');
                        for ( $i = 0; $i < $first_weekday; $i++ ) echo '<td></td>';
                        for ( $day = 1; $day <= $days_in_month; $day++ ) {
                            $events_today = !empty($events_by_day[$day]) ? $events_by_day[$day] : [];
                            $td_classes = []; if (!empty($events_today)) { $td_classes[] = 'has-events'; } echo '<td class="' . implode(' ', $td_classes) . '">';
                            echo '<div class="day-content-wrapper"><div class="day-number"><strong>' . $day . '</strong></div>';
                            if (!empty($events_today)) {
                                echo '<div class="day-events-container">';
                                foreach ($events_today as $ev) {
                                    $box_classes = ['event-box']; if (isset($ev['status'])) { if ($ev['status'] === 'cancelled') $box_classes[] = 'cancelled'; if ($ev['status'] === 'added') $box_classes[] = 'added'; }
                                    $modal_id = 'modal-content-' . $ev['type'] . '-' . $ev['id'];
                                    $card_bg = !empty($ev['color']) ? $ev['color'] : $default_bg_color;
                                    $card_text = !empty($ev['text_color']) ? $ev['text_color'] : $default_text_color;
                                    echo '<a href="' . esc_url($ev['permalink']) . '" class="event-link" data-modal-target="#' . $modal_id . '">';
                                    $styles = ['background:' . esc_attr($card_bg), 'color:' . esc_attr($card_text)];
                                    echo '<div class="' . implode(' ', $box_classes) . '" style="' . implode(';', $styles) . '">';
                                    if (in_array('cancelled', $box_classes)) {}
                                    elseif ($ev['type'] === 'matinee') { echo '<div class="event-title">Matinee:<br>' . esc_html($ev['name']) . '</div>'; echo '<div class="event-time">' . esc_html($ev['time']) . '</div>'; }
                                    else { echo '<div class="event-title">' . esc_html($ev['name']) . '</div>'; echo '<div class="event-time">' . esc_html($ev['time']) . '</div>'; }
                                    echo '</div></a>';
                                }
                                echo '</div>';
                            }
                            echo '</div></td>';
                            if ( ($first_weekday + $day - 1) % 7 == 6 && $day < $days_in_month ) echo '</tr><tr>';
                        }
                        $last_weekday = ($first_weekday + $days_in_month - 1) % 7;
                        if ( $last_weekday != 6 ) { for ( $i = $last_weekday + 1; $i <= 6; $i++ ) echo '<td></td>'; }
                        ?>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="calendar-header">
                <?php
                    $nav_params = []; if ($current_view !== 'grid' && !$forced_view) { $nav_params['view'] = $current_view; }
                    $prev_params = array_merge(['c_month' => $prev_date->format('n'), 'c_year' => $prev_date->format('Y')], $nav_params);
                    $next_params = array_merge(['c_month' => $next_date->format('n'), 'c_year' => $next_date->format('Y')], $nav_params);
                    $today_url = !empty($nav_params) ? add_query_arg($nav_params, $base_url) : $base_url;
                ?>
                <form class="calendar-controls" method="GET" action="<?php echo esc_url($base_url); ?>">
                    <?php if ($current_view !== 'grid'): ?><input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>"><?php endif; ?>
                    <div class="calendar-quick-nav">
                        <?php if (!$forced_view): ?>
                            <div class="view-switcher">
                                <a href="<?php echo esc_url(add_query_arg('view', 'grid')); ?>" class="<?php if($current_view === 'grid') echo 'active'; ?>">Grid</a> | 
                                <a href="<?php echo esc_url(add_query_arg('view', 'list')); ?>" class="<?php if($current_view === 'list') echo 'active'; ?>">List</a>
                            </div>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(add_query_arg($prev_params, $base_url)); ?>">&lt; Prev</a> | 
                        <a href="<?php echo esc_url($today_url); ?>">Today</a> | 
                        <a href="<?php echo esc_url(add_query_arg($next_params, $base_url)); ?>">Next &gt;</a>
                    </div>
                    <div class="calendar-filters">
                        <div class="themed-select-wrapper"><select name="c_month"><?php for ($i=1; $i<=12; $i++) { echo '<option value="'.$i.'" '.selected($i, $month, false).'>'.DateTime::createFromFormat('!m', $i)->format('F').'</option>'; } ?></select></div>
                        <div class="themed-select-wrapper"><select name="c_year"><?php for ($i=date('Y')-5; $i<=date('Y')+5; $i++) { echo '<option value="'.$i.'" '.selected($i, $year, false).'>'.$i.'</option>'; } ?></select></div>
                        <button type="submit">Go</button>
                    </div>
                </form>
            </div>
            <div class="modal-overlay" style="display: none;"><div class="modal-wrapper"><div class="modal-content-inner"></div><button class="modal-close-button">&times;</button></div></div>
            <div class="modal-hidden-content" style="display: none;">
                <?php $unique_events = []; foreach ($events_by_day as $day_events) { foreach ($day_events as $event) { $unique_id_modal = $event['type'] . '-' . $event['id']; if (!isset($unique_events[$unique_id_modal])) { $unique_events[$unique_id_modal] = $event; } } }
                foreach ($unique_events as $ev) : $modal_id = 'modal-content-' . $ev['type'] . '-' . $ev['id']; ?>
                    <div id="<?php echo esc_attr($modal_id); ?>">
                        <?php if ($ev['type'] === 'play' || $ev['type'] === 'matinee') : ?>
                            <h1 class="modal-play-title" style="padding-right: 40px;"><a href="<?php echo esc_url($ev['permalink']); ?>"><?php echo esc_html($ev['name']); ?></a></h1><div class="modal-play-content-wrapper"><div class="play-modal-layout"><?php if (!empty($ev['poster_url'])) : ?><div class="modal-poster"><img src="<?php echo esc_url($ev['poster_url']); ?>" alt="<?php echo esc_attr($ev['name']); ?>"></div><?php endif; ?><div class="modal-details"><?php if (!empty($ev['author'])) : ?><h2><strong>Written by:</strong><br><?php echo esc_html($ev['author']); ?></h2><?php endif; ?><?php if (!empty($ev['director_name'])) : ?><h2><strong>Directed by:</strong><br><?php if(!empty($ev['director_permalink'])):?><a href="<?php echo esc_url($ev['director_permalink']); ?>"><?php endif; ?><?php echo esc_html($ev['director_name']); ?><?php if(!empty($ev['director_permalink'])):?></a><?php endif; ?></h2><?php endif; ?><h2><strong>Show Time:</strong><br><?php echo esc_html($ev['time']); ?></h2></div></div><div class="modal-actions"><?php if (!empty($ev['ical_link'])) : ?><a href="<?php echo esc_url($ev['ical_link']); ?>" class="add-to-calendar-link">Add to Calendar</a><?php endif; ?><?php if (!empty($ev['ticket_url'])) : ?><h2 class="menu-red-button"><a href="<?php echo esc_url($ev['ticket_url']); ?>">TICKETS</a></h2><?php endif; ?></div></div>
                        <?php else : ?>
                            <div class="event-modal-layout"><h2 style="padding-right: 40px;"><a href="<?php echo esc_url($ev['permalink']); ?>"><?php echo esc_html($ev['name']); ?></a></h2><?php if (!empty($ev['location'])) : ?><p><strong>Where:</strong> <?php echo esc_html($ev['location']); ?></p><?php endif; ?><?php if (!empty($ev['address'])) : ?><p><?php echo nl2br(esc_html($ev['address'])); ?></p><?php endif; ?><p><strong>When:</strong> <?php echo esc_html($ev['time']); ?><?php if (!empty($ev['end_time'])) echo ' - ' . esc_html($ev['end_time']); ?></p><?php if (!empty($ev['ical_link'])) : ?><a href="<?php echo esc_url($ev['ical_link']); ?>" class="add-to-calendar-link">Add to Calendar</a><?php endif; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
