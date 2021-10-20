<?php

/**
 * PicoDatabase is simple SQL query builder
 *
 * @link https://github.com/peter23/PicoDatabase
 * @author i@peter23.com
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @require PHP >= 5.4, mysqlnd
 */


	class PicoDatabase extends mysqli {

		public function __call($name, $arguments) {
			$_name = strtoupper(substr($name, 0, 6));
			if($_name === 'SELECT' || $_name === 'INSERT' || $_name === 'UPDATE' || $_name === 'DELETE' || $_name === 'REPLAC' || $_name === 'NOP') {
				return new PicoDatabaseQueryBuilder($this, $name, $arguments);
			} else {
				throw new PicoDatabaseException('Call to undefined method PicoDatabase::'.$name.'()');
			}
		}


		public function __construct($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			$_server = explode(':', $server);
			if(!isset($_server[1]) || !$_server[1] || $_server[0] === 'p') {
				parent::__construct($server, $username, $password, $database);
			} else {
				parent::__construct($_server[0], $username, $password, $database, $_server[1]);
			}

			if($this->connect_error) {
				throw new PicoDatabaseException('PicoDatabase.__construct: ('.$this->connect_errno.') '.$this->connect_error, $this->connect_errno);
			}

			if($encoding)  $this->set_charset($encoding);
		}


		public function __destruct() {
			$this->close();
		}


		public function buildQuery() {
			return new PicoDatabaseQueryBuilder($this);
		}


		public function escape($s) {
			if(!is_array($s)) {
				return '\''.$this->real_escape_string($s).'\'';

			} else {
				foreach($s as $s_key => &$s1) {
					if(is_int($s_key)) {
						$s1 = $this->escape($s1);
					} else {
						$s1 = $this->escapeFieldName($s_key).'='.$this->escape($s1);
					}
				}
				return implode(', ', $s);
			}
		}


		public function escapeFieldName($s) {
			if(!is_array($s)) {
				return '`'.str_replace('`', '``', $s).'`';

			} else {
				foreach($s as &$s1) {
					$s1 = $this->escapeFieldName($s1);
				}
				return implode(', ', $s);
			}
		}


		public function processPlaceHolders($s, $vals) {
			$s0 = $s;
			$s = explode('?', $s);
			$n = 0;
			$s_count = count($s);
			for($i = 1; $i < $s_count; $i++) {
				$c = substr($s[$i], 0, 1);
				switch($c) {
					case '_':
					case '*':
					case '@':
						if(!array_key_exists($n, $vals)) {
							throw new PicoDatabaseException('PicoDatabase.processPlaceHolders: incorrect number of arguments ('.serialize($s0).','.serialize($vals).')');
						}
						if($c === '_') {
							$vals[$n] = $this->escape($vals[$n]);
						} elseif($c === '@') {
							$vals[$n] = $this->escapeFieldName($vals[$n]);
						}
						$s[$i] = (is_array($vals[$n]) ? implode(', ', $vals[$n]) : $vals[$n]).substr($s[$i], 1);
						$n++;
						break;

					case '': //?? = ?
						$s[$i] = '?';
						$i++;
						break;

					default:
						throw new PicoDatabaseException('PicoDatabase.processPlaceHolders: incorrect placeholder ('.serialize($c).')');
						break;
				}
			}
			return implode('', $s);
		}


		public function query($strQuery, $resultmode = NULL) {
			if(func_num_args() > 1) {
				$args = func_get_args();
				array_shift($args);
				$strQuery = $this->processPlaceHolders($strQuery, $args);
			}
			if($this->real_query($strQuery)) {
				if($this->field_count > 0) {
					return new PicoDatabaseQueryResult($this);
				} else {
					return true;
				}
			} else {
				throw new PicoDatabaseException('PicoDatabase.query: ('.$this->errno.') '.$this->error."\n SQL: ".$strQuery, $this->errno);
			}
		}

	}



	class PicoDatabaseQueryResult extends mysqli_result {

		public function __destruct() {
			$this->free();
		}


		public function fetch() {
			return $this->fetch_assoc();
		}


		public function fetchAll($indexCol = null) {
			if($indexCol) {
				$ret = array();
				foreach($this as $row) {
					if(!isset($row[$indexCol])) {
						throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchAll: nonexistent indexCol ('.serialize($indexCol).')');
					}
					$ret[$row[$indexCol]] = $row;
				}
				return $ret;
			} else {
				return $this->fetch_all(MYSQLI_ASSOC);
			}
		}


		public function fetchCol($col = null, $indexCol = null) {
			$ret = array();
			if($col || $indexCol) {
				foreach($this as $row) {
					if(!$col) {
						$col = array_keys($row);
						$col = $col[0];
					}
					if(!isset($row[$col])) {
						throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchCol: nonexistent col ('.serialize($col).')');
					}
					if($indexCol) {
						if(!isset($row[$indexCol])) {
							throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchCol: nonexistent indexCol ('.serialize($indexCol).')');
						}
						$ret[$row[$indexCol]] = $row[$col];
					} else {
						$ret[] = $row[$col];
					}
				}
			} else {
				while($row = $this->fetch_row()) {
					$ret[] = $row[0];
				}
			}
			return $ret;
		}


		public function fetchVal($col = null) {
			if($col) {
				$row = $this->fetch_assoc();
				if(!isset($row[$col])) {
					throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchVal: nonexistent col ('.serialize($col).')');
				}
				return $row[$col];
			} else {
				$row = $this->fetch_row();
				return $row[0];
			}
		}

	}



	class PicoDatabaseQueryBuilder {

		private $db;
		public $parts = array();


		public function __call($name, $arguments) {
			if(substr($name, 0, 5) === 'fetch') {
				$ret = $this->execute();
				if(is_bool($ret)) {
					return $ret;
				} else {
					//much faster than call_user_func_array
					return $ret->$name(
						isset($arguments[0]) ? $arguments[0] : null,
						isset($arguments[1]) ? $arguments[1] : null,
						isset($arguments[2]) ? $arguments[2] : null
					);
				}
			} else {
				if(isset($arguments[0])) {
					//if first argument is not an array and does not contain placeholders
					if(!is_array($arguments[0]) && strpos($arguments[0], '?') === false) {
						//if there is second argument
						if(isset($arguments[1])) {
							$_name = strtoupper(substr($name, 0, 6));
							//if it is an array and operation is INSERT/UPDATE/REPLACE
							if(is_array($arguments[1]) && ($_name === 'INSERT' || $_name === 'UPDATE' || $_name === 'REPLAC')) {
								//use first argument as table name and following arguments as SET arrays
								$table_name = array_shift($arguments);
								$this->__call_($name, array($table_name));
								foreach($arguments as $argument) {
									$this->__call_('set', array('?_', $argument));
								}
								return $this;
							//if there is conditional or set operation
							} elseif($_name === 'WHERE' || $_name === 'SET' || $_name === 'ON' || $_name === 'HAVING') {
								//use first argument as key and second as value
								$arguments = array($arguments[0] => $arguments[1]);
							}
						}
						//process all arguments as an array
						$arguments = array($arguments);
					}

					//if first argument is an array
					if(is_array($arguments[0])) {
						//take that array
						$subcalls = array_shift($arguments);
						//and process each its element
						foreach($subcalls as $subcall_key => &$subcall) {
							//if an element has string key
							if(is_string($subcall_key)) {
								//if key does not contain placeholders
								if(strpos($subcall_key, '?') === false) {
									//if the element is an array
									if(is_array($subcall)) {
										//add IN ?
										if(strtoupper(substr(rtrim($subcall_key), -2)) !== 'IN') {
											$subcall_key .= ' IN';
										}
										$subcall_key .= ' (?_)';
									//if the element is not an array
									} else {
										//add = ?
										$subcall_key_last_char = substr(rtrim($subcall_key), -1);
										if(($subcall_key_last_char !== '=') && ($subcall_key_last_char !== '<') && ($subcall_key_last_char !== '>')) {
											$subcall_key .= ' =';
										}
										$subcall_key .= ' ?_';
									}
									$subcall = array($subcall);
								//if key contains placeholders
								} else {
									if(!is_array($subcall)) $subcall = array($subcall);
								}
								//use that key as string with placeholders and the element as values
								$this->__call_($name, array_merge(array($subcall_key), $subcall));
							//if an element has non-string key, than we just do not think about key
							} else {
								if(!is_array($subcall)) $subcall = array($subcall);
								//use element as string with placeholders and other arguments as values
								$this->__call_($name, array_merge($subcall, $arguments));
							}
						}
						return $this;
					}
				}
				return $this->__call_($name, $arguments);
			}
		}


		private function __call_($name, $arguments) {
			$name = ltrim(strtoupper(strtr($name, array('A'=>' A','B'=>' B','C'=>' C','D'=>' D','E'=>' E','F'=>' F','G'=>' G','H'=>' H','I'=>' I','J'=>' J','K'=>' K','L'=>' L','M'=>' M','N'=>' N','O'=>' O','P'=>' P','Q'=>' Q','R'=>' R','S'=>' S','T'=>' T','U'=>' U','V'=>' V','W'=>' W','X'=>' X','Y'=>' Y','Z'=>' Z'))));

			//if there are more than one arguments
			if(count($arguments) > 1) {
				//use first argument as string with placeholders
				$tmp_statement = array_shift($arguments);
				//and other arguments as values
				$arguments = array($this->db->processPlaceHolders($tmp_statement, $arguments));
			}

			$parts_count = count($this->parts) - 1;
			//if previous part has the same name
			if($parts_count >= 0 && $this->parts[$parts_count][0] === $name) {
				$this->parts[$parts_count][1] = array_merge($this->parts[$parts_count][1], $arguments);
			} else {
				if($name === 'NOP')  $name = '';
				$this->parts[] = array($name, $arguments);
			}

			return $this;
		}


		public function __construct(&$db, $call = null, $arguments = array()) {
			$this->db = $db;
			if(!is_null($call)) {
				$this->__call($call, $arguments);
			}
		}


		public function __toString() {
			$parts = $this->parts;
			foreach($parts as &$part) {
				$count_part1 = count($part[1]);
				if($count_part1 > 0 && ($part[0] === 'WHERE' || $part[0] === 'ON' || $part[0] === 'HAVING')) {
					$part = $part[0]."\n  (".implode(")\n  AND (", $part[1]).')';
				} else {
					$part = $part[0].($count_part1 > 0 ? "\n  ".implode("\n  ,", $part[1]) : '');
				}
			}
			return implode("\n", $parts);
		}


		public function execute() {
			return $this->db->query(strval($this));
		}

	}



	class PicoDatabaseException extends Exception { }
