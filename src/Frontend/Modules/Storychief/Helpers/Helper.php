<?php

namespace Frontend\Modules\Storychief\Helpers;

class Helper {
	/**
	 * Calculates the hmac of an array and appends it
	 *
	 * @param $key
	 * @param array|null $data
	 * @return array
	 */
	public static function hashData($key, array $data = null) {
		if (!is_null($data)) {
			$data['mac'] = hash_hmac('sha256', json_encode($data), $key);
		}

		return $data;
	}

	/**
	 * Return a valid slug from a string
	 * @param $text
	 * @return mixed|string
	 */
	public static function sluggify($text) {
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		$text = trim($text, '-');
		if (function_exists('iconv')) $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text)) return 'n-a';

		return $text;
	}
}