<?php
class PgMole Extends DbMole{

	/**
	* Vrati instanci objektu pro danou konfiguraci.
	* Vraci vzdy stejny objekt pro stejnou konfiguraci.
	*
	* @static
	* @access public
	* @param string $configuration_name		"default"
	* @return PgMole									nebo null
	*/
	function &GetInstance($configuration_name = "default"){
		return parent::GetInstance($configuration_name,"PgMole");
	}

	function getDatabaseType(){ return "postgresql"; }

	function _disconnectFromDatabase(){
		pg_close($this->_DbConnect);
	}
	

	/**
	* Provede spusteni SQL query a pole nalezenych zaznamu.
	* Vrati pole asociativnich poli.
	*
	* @access public
	* @param string $query
	* @param array $bind_ar
	* @param array $options
	* @return array						pole asociativnich poli; null v pripade chyby
	*/
	function selectRows($query,$bind_ar = array(), $options = array()){
		$options = array_merge(array(
			"limit" => null,
			"offset" => null,
			"avoid_recursion" => false,
		),$options);

		if(!$options["avoid_recursion"]){
			return $this->_selectRows($query,$bind_ar,$options);
		}


		if(isset($options["offset"]) || isset($options["limit"])){
			if(!isset($options["offset"])){ $options["offset"] = 0; }
			$_cond = array();
			if(isset($options["offset"])){
				$_cond[] = "OFFSET :offset____";
				$bind_ar[":offset____"] = $options["offset"];
			}
			if(isset($options["limit"])){
				$_cond[] = "LIMIT :limit____";
				$bind_ar[":limit____"] = $options["limit"];
			}
			$query = "
				SELECT * FROM (
					$query
				)q____ ".join(" ",$_cond)."	
			";
		}

		$result = $this->executeQuery($query,$bind_ar,$options);

		if(!$result){ return null; }

		$out = array();

		$num_rows = pg_num_rows($result);

		for($i=0;$i<$num_rows;$i++){
			$row = pg_fetch_row($result,$i,PGSQL_ASSOC);
			$out[] = $row;
		}
		pg_free_result($result);
		reset($out);
		return $out;
	}

	function selectSequenceNextval($sequence_name){
		return $this->selectSingleValue("SELECT NEXTVAL(".$this->escapeString4Sql($sequence_name).")");
	}

	function selectSequenceCurrval($sequence_name){
		return $this->selectSingleValue("SELECT CURRVAL(".$this->escapeString4Sql($sequence_name).")");
	}

	function _getDbLastErrorMessage(){
		return "pg_last_error: ".pg_last_error($this->_DbConnect);
	}

	function _freeResult(&$result){
		return pg_free_result($result);
	}

	function escapeString4Sql($s){
		return "'".pg_escape_string($s)."'";
	}

	function _runQuery($query){
		return pg_query($this->_DbConnect,$query);	
	}

	function _getDatabaseName(){
		$this->_connectToDatabase();
		return pg_dbname($this->_DbConnect);
	}

}