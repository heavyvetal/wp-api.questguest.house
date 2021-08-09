<?php

class WP_JSON_Timetable {
    /**
     * Server object
     *
     * @var WP_JSON_ResponseHandler
     */
    protected $server;

    /**
     * Constructor
     *
     * @param WP_JSON_ResponseHandler $server Server object
     */
    public function __construct(WP_JSON_ResponseHandler $server) {
        $this->server = $server;
    }

    /**
     * Register routes
     *
     * @param array $routes Existing routes
     * @return array Modified routes
     */
    public function register_routes( $routes ) {
        $post_routes = array(
            // Quest timetables endpoints
            '/timetable/pirates' => array(
                array( array( $this, 'get_timetable_pirates' ),  WP_JSON_Server::READABLE )
            ),
            '/timetable/maze' => array(
                array( array( $this, 'get_timetable_maze' ),  WP_JSON_Server::READABLE )
            ),
            '/timetable/mission' => array(
                array( array( $this, 'get_timetable_mission' ),  WP_JSON_Server::READABLE )
            ),
            '/timetable/aztec' => array(
                array( array( $this, 'get_timetable_aztec' ),  WP_JSON_Server::READABLE )
            ),
            '/timetable/gravity' => array(
                array( array( $this, 'get_timetable_gravity' ),  WP_JSON_Server::READABLE )
            )
        );

        return array_merge( $routes, $post_routes );
    }

    /**
     * @return array
     */
    public function get_timetable_pirates()
    {
        // Пираты Карибского моря: Сундук мертвеца
        $post_id_regs = 492;

        return $this->get_timetable($post_id_regs);
    }

    /**
     * @return array
     */
    public function get_timetable_maze()
    {
        // Лабиринт
        $post_id_regs = 495;

        return $this->get_timetable($post_id_regs);
    }

    /**
     * @return array
     */
    public function get_timetable_mission()
    {
        // Миссия выполнима 2
        $post_id_regs = 494;

        return $this->get_timetable($post_id_regs);
    }

    /**
     * @return array
     */
    public function get_timetable_aztec()
    {
        // Сокровища ацтеков
        $post_id_regs = 1770;

        return $this->get_timetable($post_id_regs);
    }

    /**
     * @return array
     */
    public function get_timetable_gravity()
    {
        // Гравити Фолз
        $post_id_regs = 2613;

        return $this->get_timetable($post_id_regs);
    }

    /**
     * Gets timetables for quests
     *
     * @param int $post_id_regs
     * @return array
     */
    public function get_timetable($post_id_regs) {
        $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

        $price_time = array();
        $room_price = array();
        $quest_price_times_key = array('kvest_price1_time', 'kvest_price2_time', 'kvest_price3_time');
        $room_price_key = array('room_price_1', 'room_price_2', 'room_price_3');

        foreach ($quest_price_times_key as $key) {
            $price_time[] = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='".$key."'", ARRAY_A );
        }

        foreach ($room_price_key as $key) {
            $room_price[] = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='".$key."'", ARRAY_A );
        }

        // Schedule from a few price times
        foreach ($price_time as &$price_time_item) {
            $price_time_item = preg_split("/,\s+/", $price_time_item[0]['meta_value']);
        }

        // Creating full schedule from two arrays for weekdays, day-offs
        $all_times = array();

        foreach ($price_time[0] as &$item) {
            if (preg_match("/\*/", $item)) {
                $item = str_replace("*", "", $item);
            } else {
                $all_times[] = $item;
            }
        }

        foreach ($price_time[1] as $key => &$item) {
            if (preg_match("/\*/", $item)) $item = str_replace("*", "", $item);

            if ($this->canAddNewTime($all_times, $item)) $all_times[] = $item;
        }

        foreach ($price_time[2] as $key => &$item) {
            if (preg_match("/\*/", $item)) $item = str_replace("*", "", $item);

            if ($this->canAddNewTime($all_times, $item)) $all_times[] = $item;
        }

        // Prices
        foreach ($room_price as &$room_price_item) {
            $room_price_item = preg_replace("/\s+грн/", "", $room_price_item[0]['meta_value']);
        }

        // An array of dates for two weeks
        $days_count = 30;
        $dates = array();
        $today = date('Y-m-d');
        $date = new DateTime($today);

        $dates[] = $today;

        for ($i = 1; $i < $days_count; $i++) {
            $interval = DateInterval::createFromDateString('1 day');
            $date->add($interval);
            $new_date = $date->format('Y-m-d');
            $dates[] = $new_date;
        }

        // Forming of the final array
        $overall = array();

        $last_regs = $wpdb->get_results("SELECT datt FROM regs WHERE post_id=".$post_id_regs." ORDER BY id DESC LIMIT 160", ARRAY_A);

        foreach ($dates as $date) {
            foreach ($all_times as $time) {
                $row['date'] = $date;
                $row['time'] = $time;
                $row['is_free'] = $this->check_busy_times($date, $time, $last_regs);

                // Weekday or day-off
                $day_type = 'weekday';
                $day_num = date('N', strtotime($date));

                if ($day_num > 4) $day_type = 'day-off';

                $row['price'] = $this->get_price($time, $day_type, $price_time, $room_price).' грн.';

                $overall[] = $row;
            }
        }

        return $overall;
    }

    /**
     * Check ability to add non-existing time into time array
     *
     * @param array $main_times Existing routes
     * @param string $compared_time
     * @return bool
     */
    public function canAddNewTime(&$main_times, $compared_time) {

        $timeExists = false;

        foreach ($main_times as $main_time) {
            if ($main_time == $compared_time) $timeExists = true;
        }

        return !$timeExists;
    }

    /**
     * Finds a price of a time
     *
     * @param string $time_request
     * @param string $day_type
     * @param array $price_times
     * @param array $room_prices
     * @return int
     */
    public function get_price($time_request, $day_type, $price_times, $room_prices) {
        $price_id = 0;

        if ($day_type == 'weekday' && in_array($time_request, $price_times[0])) $price_id = 0;

        if ($day_type == 'day-off' && in_array($time_request, $price_times[1])) $price_id = 1;

        if (in_array($time_request, $price_times[2])) $price_id = 2;

        return $room_prices[$price_id];
    }

    /**
     * Checks busy times for a registration
     *
     * @param string $date_request
     * @param string $time_request
     * @param array $last_regs
     *
     * @return bool
     */
    public function check_busy_times($date_request, $time_request, $last_regs)
    {
        $is_free = true;

        foreach ($last_regs as $item) {
            $date_arr = explode(" ", $item['datt']);
            $date = $date_arr[0];
            $time = $date_arr[1];
            $time = substr($time, 0, 5);

            if ($date_request == $date && $time_request == $time) $is_free = false;
        }

        return $is_free;
    }

}
