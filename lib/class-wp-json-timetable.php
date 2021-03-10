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
            )
		);

		return array_merge( $routes, $post_routes );
	}


    public function get_timetable_pirates()
    {
        // Pirates of the Caribbean: Dead Man's Chest
        $post_id_regs = 492;
        $id_calendars = 4;

        return $this->get_timetable($post_id_regs, $id_calendars);
	}

    public function get_timetable_maze()
    {
        // Maze
        $post_id_regs = 495;
        $id_calendars = 8;

        return $this->get_timetable($post_id_regs, $id_calendars);
    }

    public function get_timetable_mission()
    {
        // Mission is possible 2
        $post_id_regs = 494;
        $id_calendars = 6;

        return $this->get_timetable($post_id_regs, $id_calendars);
    }

    public function get_timetable_aztec()
    {
        // Aztec treasures
        $post_id_regs = 1770;
        $id_calendars = 7;

        return $this->get_timetable($post_id_regs, $id_calendars);
    }

	/**
	 * @return stdClass[] Collection of Post entities
	 */
	public function get_timetable($post_id_regs, $id_calendars) {

        $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

        //$schedule = $wpdb->get_results( "SELECT timm FROM calendars WHERE id=".$id_calendars, ARRAY_A );
        $price1_time = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='kvest_price1_time'", ARRAY_A );
        $price2_time = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='kvest_price2_time'", ARRAY_A );
        $price3_time = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='kvest_price3_time'", ARRAY_A );
        $room_price1 = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='room_price_1'", ARRAY_A );
        $room_price2 = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='room_price_2'", ARRAY_A );
        $room_price3 = $wpdb->get_results( "SELECT meta_value FROM wp_postmeta WHERE post_id=".$post_id_regs." AND meta_key='room_price_3'", ARRAY_A );

        /*--- Schedule from a few price times ---*/
        //$times_arr  = preg_split("/,\s+/", $schedule[0]['timm']);
        $price1_times_arr = preg_split("/,\s+/", $price1_time[0]['meta_value']);
        $price2_times_arr = preg_split("/,\s+/", $price2_time[0]['meta_value']);
        $price3_times_arr = preg_split("/,\s+/", $price3_time[0]['meta_value']);

        /*--- Creating full schedule from two arrays for weekdays, day-offs ---*/
        /*--- $w_times and $d_times temporary is not used ---*/
        $w_times = array();
        $d_times = array();
        $all_times = array();

        foreach ($price1_times_arr as &$item) {

            if (preg_match("/\*/", $item)) {
                $item = str_replace("*", "", $item);
                $d_times[] = $item;
            } else {
                $w_times[] = $item;
                $all_times[] = $item;
            }
        }

        foreach ($price2_times_arr as $key => &$item) {

            if (preg_match("/\*/", $item)) {
                $item = str_replace("*", "", $item);
                $d_times[] = $item;
            } else {
                $w_times[] = $item;
            }

            if ($this->canAddNewTime($all_times, $item)) $all_times[] = $item;
        }

        foreach ($price3_times_arr as $key => &$item) {

            if (preg_match("/\*/", $item)) {
                $item = str_replace("*", "", $item);
                $d_times[] = $item;
            } else {
                $w_times[] = $item;
            }

            if ($this->canAddNewTime($all_times, $item)) $all_times[] = $item;
        }

        // Prices
        $room_price1 = preg_replace("/\s+грн/", "", $room_price1[0]['meta_value']);
        $room_price2 = preg_replace("/\s+грн/", "", $room_price2[0]['meta_value']);
        $room_price3 = preg_replace("/\s+грн/", "", $room_price3[0]['meta_value']);

        /*--- Dates array for two weeks ---*/
        $days_count = 14;
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

        // Forming of final array
        $overall = array();

        foreach ($dates as $date) {

            foreach ($all_times as $time) {

                $row['date'] = $date;
                $row['time'] = $time;
                $row['is_free'] = $this->check_busy_times($date, $time, $post_id_regs);

                // Weekday or day-off
                $day_type = 'weekday';
                $day_num = date('N', strtotime($date));
                if ($day_num > 5) $day_type = 'day-off';

                $row['price'] = $this->get_price($time, $day_type, $price1_times_arr, $price2_times_arr, $price3_times_arr, $room_price1, $room_price2, $room_price3).' грн.';

                $overall[] = $row;
            }
        }

        $response = $overall;
		return $response;
	}

    /**
     * Check ability to add non-existing time into time array
     *
     * @param array $main_times Existing routes
     * @return bool
     */
    public function canAddNewTime(&$main_times, $compared_time) {

        $timeExists = false;

        foreach ($main_times as $main_time) {
            if ($main_time == $compared_time) $timeExists = true;
        }

        return !$timeExists;
    }


    public function get_price($time_request, $day_type, $price1_times_arr, $price2_times_arr, $price3_times_arr, $room_price1, $room_price2, $room_price3) {

        $ultimate_price_id = 1;

        if ($day_type == 'weekday') {

            foreach ($price2_times_arr as $time) {
                if ($time_request == $time) $ultimate_price_id = 2;
            }

            foreach ($price1_times_arr as $time) {
                if ($time_request == $time) $ultimate_price_id = 1;
            }
        }

        if ($day_type == 'day-off') {

            foreach ($price1_times_arr as $time) {
                if ($time_request == $time) $ultimate_price_id = 1;
            }

            foreach ($price2_times_arr as $time) {
                if ($time_request == $time) $ultimate_price_id = 2;
            }
        }

        foreach ($price3_times_arr as $time) {
            if ($time_request == $time) $ultimate_price_id = 3;
        }

        switch($ultimate_price_id) {
            case 1: return $room_price1;
                break;
            case 2: return $room_price2;
                break;
            case 3: return $room_price3;
                break;
            default: return $room_price1;
                break;
        }
    }


    public function check_busy_times($date_request, $time_request, $post_id_regs)
    {
        global $wpdb;
        $is_free = true;

        $response = $wpdb->get_results( "SELECT datt FROM regs WHERE post_id=".$post_id_regs." ORDER BY id DESC LIMIT 100", ARRAY_A );

        foreach ($response as $item) {

            $date_arr = explode(" ", $item['datt']);
            $date = $date_arr[0];
            $time = $date_arr[1];
            $time = substr($time, 0, 5);

            if ($date_request == $date && $time_request == $time) $is_free = false;
        }

        return $is_free;
	}

}
