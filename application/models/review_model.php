<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Review Model
 *
 * This class handles the database functions for making reviews.
 * 
 * @package	BitWasp
 * @subpackage	Models
 * @category	Review_Model
 * @author	BitWasp
 */
class Review_model extends CI_Model {
	
	/**
	 * Constructor
	 * 
	 * Load libs/models.
	 *
	 * @access	public
	 */
	public function __construct() {
		$this->load->model('review_auth_model');
		parent::__construct();
	}

	/**
	 * Review Information
	 * 
	 * Accepts an $order_id, and $review_type, and generates an array
	 * tailored to that type of review. Returns FALSE if the order does
	 * not exist, or if the review_type is not either 'buyer' or 'vendor'.
	 * 
	 * @param	int	$order_id
	 * @param	string	$review_type
	 */
	public function review_information($order_id, $review_type) {
		$this->load->model('order_model');
		$order = $this->order_model->get($order_id);
		if($order == FALSE)
			return FALSE;
			
		if($review_type == 'buyer') {
			$review_info = array();
			$review_info['items'] = $order['items'];
			$review_info['price'] = $order['price'];
			$review_info['fees'] = $order['fees'];
			$review_info['vendor'] = $order['vendor'];
			$review_info['selected_escrow'] = $order['vendor_selected_escrow'];
			$review_info['disputed'] = $order['disputed'];
			
			$review_info['finalized_time'] = $order['finalized_time'];
			$review_info['finalized_time_f'] = date('jS F Y h:i:s A', $review_info['finalized_time']);
			$review_info['dispatched_time'] = $order['dispatched_time'];
			$review_info['dispatched_time_f'] = date('jS F Y h:i:s A', $review_info['dispatched_time']);
			$review_info['received_time'] = $order['received_time'];
			$review_info['received_time_f'] = date('jS F Y h:i:s A', $order['received_time']);
		} else if($review_type == 'vendor') {
			$review_info = array();
			$review_info['items'] = $order['items'];
			$review_info['price'] = $order['price'];
			$review_info['fees'] = $order['fees'];
			$review_info['buyer'] = $order['buyer'];
			$review_info['selected_escrow'] = $order['vendor_selected_escrow'];
			$review_info['disputed'] = $order['disputed'];
			
			$review_info['finalized_time'] = $order['finalized_time'];
			$review_info['finalized_time_f'] = date('jS F Y h:i:s A', $review_info['finalized_time']);
			$review_info['dispatched_time'] = $order['dispatched_time'];
			$review_info['dispatched_time_f'] = date('jS F Y h:i:s A', $review_info['dispatched_time']);
			$review_info['received_time'] = $order['received_time'];
			$review_info['received_time_f'] = date('jS F Y h:i:s A', $order['received_time']);
		} 
		// If review_info wasnt set (invalid review_type) fail.
		return (isset($review_info)) ? $review_info : FALSE;
	}
	
	/**
	 * Add
	 * 
	 * Adds a review to the reviews table. A review contains the following data:
	 *  - review_type (user or item)
	 *  - subject_hash (user or items hash)
	 *  - json (a json array containing the reviews information. allow for different fields.
	 *  - average_rating (rounded to one decimal place)
	 *  - timestamp - should be generated using Review_Model::create_review_time()
	 *  - disputed - 0 or 1 depending on whether the review pertains to a disputed order.
	 * 
	 * @param	array	$review
	 * @return	boolean
	 */
	public function add($review) {
		return ($this->db->insert('reviews', $review) == TRUE) ? TRUE : FALSE;
	}
	
	/**
	 * Create Review Time
	 * 
	 * This function creates a time for the review which is at 12pm the
	 * following day. This should help make identifying individual orders
	 * out of many difficult. Returns a UNIX timestamp.
	 * 
	 * @return	string
	 */
	public function create_review_time() {
		return mktime('12', '0', '0', date("m"), date("d")+1, date("y"));
	}
	
	/**
	 * Publish Reviews
	 * 
	 * Accepts an array, and a $review_type. If the $review_type is 
	 * 'buyer', that is, a buyer is leaving the review, loop through the 
	 * array to publish the vendor review (stored as an array), and the 
	 * item reviews.
	 * 
	 * @param	array	$array
	 * @param	string	$review_type
	 * @return	boolean
	 */
	public function publish_reviews($array, $review_type) {
		$success = TRUE;
		if($review_type == 'buyer') {
			// Loop through each review contained in $array.
			foreach($array as $review){
				if($this->add($review) == FALSE)
					$success = FALSE;
			}
		} else if($review_type == 'vendor') {
			// Take the first and only entry - the review of the buyer.
			if($this->add($array[0]) == FALSE)
				$success = FALSE;
		} else {
			// Otherwise fail.
			$success = FALSE;
		}
		return $success;
	}
	
	public function random_latest_reviews($desired_count, $review_type, $subject_hash, $disputed = FALSE) {
		// Load 30 latest reviews. 
		$this->db->where('review_type', $review_type);
		$this->db->where('subject_hash', $subject_hash);
		if($disputed !== FALSE && in_array($disputed, array('0','1')))
			$this->db->where('disputed', $disputed);
		$this->db->order_by('timestamp', 'DESC');
		
		if($desired_count !== 'all')
			$this->db->limit(round(($desired_count*1.5), 0));
		
		$query = $this->db->get('reviews');
		if($query->num_rows() > 0) {
			$result = $query->result_array();
			shuffle($result);
			$c = 1;
			$reviews = array();
			foreach($result as $review) {
				$reviews[] = $this->parse_review_row($review);
				// if count() less than $desired_count, then loop terminates naturally
				// if $desired_count < count(), then $desired_count is max.
				if($desired_count !== 'all' && $c++ == $desired_count)
					break;
			}
			return $reviews;
		} else {
			return fALSE;
		}
	}
	
	/**
	 * Parse Rating Row
	 * 
	 * This function parses the rating row array from the database into 
	 * a structured array for use in views.
	 * 
	 * @param	array	$row
	 * @param	array
	 */
	public function parse_review_row($review){
		$data = array();
		$content = json_decode($review['json']);
		foreach($content as $name => $rating) {
			if($name == 'comments') {
				$data['comments'] = $rating;
				continue;
			}
			$data['rating'][$name] = $rating;
		}
		$data['disputed'] = $review['disputed'];
		$data['average_rating'] = $review['average_rating'];
		return $data;
	}
	
	/**
	 * Random Reviews
	 * 
	 * This function will try to load $desired_count random reviews, for
	 * the $subject_hash/$review_type. The disputed parameter defaults to 
	 * FALSE, meaning it doesn't do anything, but setting this to 0 will
	 * yield only positive reviews, setting it to 1 will yield only disputed
	 * reviews.
	 * 
	 * @param	int	$desired_count
	 * @param	string	$review_type
	 * @param	string	$subject_hash
	 * @param	(opt) 	$disputed(FALSE/0/1)
	 * @return	array
	 */
	public function random_reviews($desired_count, $review_type, $subject_hash, $disputed = FALSE) {
		$this->db->where('review_type', $review_type);
		$this->db->where('subject_hash', $subject_hash);
		if($disputed !== FALSE && in_array($disputed, array('0','1')))
			$this->db->where('disputed', $disputed);
			
		$this->db->order_by('id', 'random');
		if($desired_count !== 'all')
			$this->db->limit($desired_count);
		
		$query = $this->db->get('reviews');
		if($query->num_rows() > 0) {
			$results = array();
			foreach($query->result_array() as $review) {
				$results[] = $this->parse_review_row($review);
			}
			return $results;
		} else {
			return fALSE;
		}
	}
	
	/**
	 * Count Reviews
	 * 
	 * This will could the reviews identified by the $review_type/$subject_hash.
	 * When called without the third parameter, the total number of reviews is returned.
	 * When the third argument is 0 (not disputed), or 1 (disputed),
	 * only the number of positive or negative reviews are returned respectively.
	 * 
	 * @param	string	$review_type
	 * @param	string	$subject_hash
	 * @param	bool/int	(opt)$disputed
	 * @return	int
	 */
	public function count_reviews($review_type, $subject_hash, $disputed = FALSE){
		$this->db->select('id')
				 ->where('review_type', $review_type)
				 ->where('subject_hash', $subject_hash);
		if($disputed !== FALSE && in_array($disputed, array('0','1')))
			$this->db->where('disputed', "$disputed");
		$this->db->order_by('id', 'random');
		$this->db->from('reviews');
		
		return $this->db->count_all_results();
	}
	
	/**
	 * Current Rating
	 * 
	 * This calculates the rating of the $review_type/$subject_hash, 
	 * by taking an average of each reviews average rating.
	 * 
	 * @param	string	$review_type
	 * @param	string	$subject_hash
	 * @return	int
	 */
	public function current_rating($review_type, $subject_hash) {
		$this->db->select('average_rating')
				 ->where('review_type', $review_type)
				 ->where('subject_hash', $subject_hash);
		$query = $this->db->get('reviews');
		if($query->num_rows() == 0)
			return 0;
		
		$rating = (float)0;
		foreach($query->result_array() as $entry) {
			$rating += (float)$entry['average_rating'];
		}
		return round($rating/$query->num_rows(), 1);
	}
	
	/**
	 * Prepare Database Entry
	 * 
	 * This function accepts the $review_type/$subject_hash, an array
	 * containing the ratings from 1-5 of the various qualities, the
	 * comments left by the user, and returns an array to be passed to
	 * add() or publish_reviews(array())
	 * 
	 * @param	string	$review_type
	 * @param	string	$subject_hash
	 * @param	array	$rating_array
	 * @param	string	$comments
	 * @return	array
	 */
	public function prepare_review_array($review_type, $subject_hash, $disputed, $rating_array, $comments) {
		$average = 0;
		foreach($rating_array as $rating) {
			$average += $rating;
		}
		$average = round(($average/count($rating_array)), 1);
		$rating_array['comments'] = $comments;
		
		return array(	'review_type' => $review_type,
						'subject_hash' => $subject_hash, 
						'json' => json_encode($rating_array),
						'average_rating' => $average,
						'timestamp' => $this->create_review_time(),
						'disputed' => $disputed);
	}
};

/* Location: application/models/review_model.php */
/* End of File: review_model.php */
