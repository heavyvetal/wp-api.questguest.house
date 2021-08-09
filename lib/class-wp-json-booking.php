<?php

class WP_JSON_Booking {
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
	 * Register  routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$post_routes = array(
			// Endpoints
			'/booking/pirates' => array(
                array( array( $this, 'get_timetable' ),  WP_JSON_Server::READABLE ),
				array( array( $this, 'create_booking_pirates' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
            '/booking/maze' => array(
                array( array( $this, 'get_timetable' ),  WP_JSON_Server::READABLE ),
                array( array( $this, 'create_booking_maze' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
            ),
            '/booking/mission' => array(
                array( array( $this, 'get_timetable' ),  WP_JSON_Server::READABLE ),
                array( array( $this, 'create_booking_mission' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
            ),
            '/booking/aztec' => array(
                array( array( $this, 'get_timetable' ),  WP_JSON_Server::READABLE ),
                array( array( $this, 'create_booking_aztec' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
            ),
            '/booking/gravity' => array(
                array( array( $this, 'get_timetable' ),  WP_JSON_Server::READABLE ),
                array( array( $this, 'create_booking_gravity' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
            )
		);

		return array_merge( $routes, $post_routes );
	}

	/**
	 * @return stdClass[] Collection of Post entities
	 */
	public function get_timetable( $filter = array(), $context = 'view', $type = 'post', $page = 1 ) {

        $response = array(
            "USE POST INSTEAD OF GET"
        );

		return $response;
	}

    /**
     * @return array
     */
    public function create_booking_pirates($data)
    {
        // Пираты Карибского моря: Сундук мертвеца
        $post_id_regs = 492;
        $id_calendars = 4;

	    return $this->create_booking($data, $post_id_regs, $id_calendars);
    }

    /**
     * @return array
     */
    public function create_booking_maze($data)
    {
        // Лабиринт
        $post_id_regs = 495;
        $id_calendars = 8;

        return $this->create_booking($data, $post_id_regs, $id_calendars);
    }

    /**
     * @return array
     */
    public function create_booking_mission($data)
    {
        // Миссия выполнима 2
        $post_id_regs = 494;
        $id_calendars = 6;

        return $this->create_booking($data, $post_id_regs, $id_calendars);
    }

    /**
     * @return array
     */
    public function create_booking_aztec($data)
    {
        // Сокровища ацтеков
        $post_id_regs = 1770;
        $id_calendars = 7;

        return $this->create_booking($data, $post_id_regs, $id_calendars);
    }

    /**
     * @return array
     */
    public function create_booking_gravity($data)
    {
        // Гравити Фолз
        $post_id_regs = 2613;
        $id_calendars = 9;

        return $this->create_booking($data, $post_id_regs, $id_calendars);
    }

	/**
     * Books a time
     *
     * @param array $data
     * @param int $post_id_regs
     * @param int $id_calendars
     *
	 * @return array
	 */
	public function create_booking($data, $post_id_regs, $id_calendars) {

	    $error = false;
	    $error_msg = '[ошибка]';

        if (empty($data['first_name']) && empty($data['family_name'])) {
	        $error = true;
            $error_msg .= '[не указали имя]';
        }

	    if (empty($data['phone']) && empty($data['email'])) {
	        $error = true;
            $error_msg .= '[не предоставили контактные данные]';
        }

        if (empty($data['date']) || empty($data['time'])) {
            $error = true;
            $error_msg .= '[не указали дату/время]';
        } else {
            // Booking ability check
            $is_free = $this->check_busy_times($data['date'], $data['time'], $post_id_regs, $id_calendars) || false;

            if (!$is_free) {
                $error = true;
                $error_msg = 'Указанное время занято';
            }
        }

        if ($error) {
            $response = array(
                "success" => false,
                "message" => $error_msg
            );
        } else {
            $name = $data['first_name']." ".$data['family_name'];
            $phone = $data['phone'];
            $email = $data['email'];
            $comment = $data['source']." | unique_id=".$data['unique_id']." | price=".$data['price']." | ".$data['comment'];
            $time_to_book = $data['date']." ".$data['time'].":00";

            $query_result = $this->insert_into_db($name, $phone, $email, $comment, $time_to_book, $post_id_regs, $id_calendars);

            if ($query_result) {
                $response = array(
                    "success" => true,
                );
            } else {
                $response = array(
                    "success" => false,
                    "message" => "не удалось сохранить"
                );
            }
        }

        return $response;
	}

    /**
     * Creates an order record in the DB
     *
     * @param string $name
     * @param string $phone
     * @param string $email
     * @param string $comment
     * @param string $time_to_book
     * @param int $post_id_regs
     * @param int $id_calendars
     *
     * @return bool
     */
    public function insert_into_db($name, $phone, $email, $comment, $time_to_book, $post_id_regs, $id_calendars)
    {
        global $wpdb;
        $now = date('Y-m-d H:i:s');
        $query = "INSERT INTO regs(`cal_id`, `post_id`, `datt`, `nam`, `phone`, `eml`, `kogda`, `comment`, `stat`) VALUES (%d, %d, '%s', '%s', '%s', '%s', '%s', '%s', 0)";

        $query  = $wpdb->prepare($query, array($id_calendars, $post_id_regs, $time_to_book, $name, $phone, $email, $now, $comment));
        $success = $wpdb->query($query);
        $wpdb->close();

        return $success;
    }

    /**
     * Checks busy times for a registration
     *
     * @param string $date_request
     * @param string $time_request
     * @param int $post_id_regs
     *
     * @return bool
     */
    public function check_busy_times($date_request, $time_request, $post_id_regs)
    {
        global $wpdb;
        $is_free = true;

        $response = $wpdb->get_results( "SELECT datt FROM regs WHERE post_id=".$post_id_regs." ORDER BY id DESC LIMIT 160", ARRAY_A );

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
