<?php

namespace QuickCommerce\Util;

use \DateTimeZone;
use QuickCommerce\Exception\PosException;

/**
 * DateTime subclass which supports serialization.
 */
class PosDateTime extends \DateTime {
	/**
	 * A string representation of the date, for serialization.
	 * 
	 * @var string
	 */
	private $dateString;
	
	/**
	 * A string representation of the time zone, for serialization.
	 * 
	 * @var string
	 */
	private $tzString;
	protected static function is_timestamp($value) {
		if (! is_numeric($value)) {
			return false;
		}
		
		if (8 === strlen(( string ) $value)) {
			return false;
		}
		
		$stamp = strtotime($value);
		
		if (false === $stamp) {
			return true;
		}
		
		$month = date('m', $value);
		$day = date('d', $value);
		$year = date('Y', $value);
		
		return checkdate($month, $day, $year);
	}
	
	/**
	 * Factory method to get a DateTime object from a temporal input
	 *
	 * @param mixed $value
	 *        	The value to convert (can be a string, a timestamp, or another DateTime)
	 * @param DateTimeZone $timeZone
	 *        	(optional) timezone
	 * @param string $dateTimeClass
	 *        	The class of the object to create, defaults to DateTime
	 *        	
	 * @return mixed null, or an instance of $dateTimeClass
	 *        
	 * @throws PosException
	 */
	public static function new_instance($value, DateTimeZone $timeZone = null, $dateTimeClass = 'DateTime') {
		if ($value instanceof \DateTime) {
			return $value;
		}
		if (empty($value)) {
			// '' is seen as NULL for temporal objects
			// because DateTime('') == DateTime('now') -- which is unexpected
			return null;
		}
		try {
			if (self::isTimestamp($value)) { // if it's a unix timestamp
				$dateTimeObject = new $dateTimeClass('@' . $value, new \DateTimeZone('UTC'));
				// timezone must be explicitly specified and then changed
				// because of a DateTime bug: http://bugs.php.net/bug.php?id=43003
				$dateTimeObject->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
			} else {
				if (null === $timeZone) {
					$dateTimeObject = new $dateTimeClass($value);
				} else {
					$dateTimeObject = new $dateTimeClass($value, $timeZone);
				}
			}
		} catch ( \Exception $e ) {
			throw new PosException('Error parsing date/time value: ' . var_export($value, true), 0, $e);
		}
		
		return $dateTimeObject;
	}
	
	/**
	 * PHP "magic" function called when object is serialized.
	 * Sets an internal property with the date string and returns properties
	 * of class that should be serialized.
	 * 
	 * @return string[]
	 */
	public function __sleep() {
		// We need to use a string without a time zone, due to
		// PHP bug: http://bugs.php.net/bug.php?id=40743
		$this->dateString = $this->format('Y-m-d H:i:s');
		$this->tzString = $this->getTimeZone()->getName();
		
		return [ 
				'dateString',
				'tzString' 
		];
	}
	
	/**
	 * PHP "magic" function called when object is restored from serialized state.
	 * Calls DateTime constructor with previously stored string value of date.
	 */
	public function __wakeup() {
		parent::__construct($this->dateString, new \DateTimeZone($this->tzString));
	}
}
