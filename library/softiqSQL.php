<?php
namespace softiq;

	class softiqSQL extends softiq{

		public $host;
		public $username;
		public $password;
		public $database;
		public function __construct($database){

				$this->database = $database;
		}

		public function check_mysql_version(){
			$version = [];
			$query = "SELECT LEFT(version(),1) as id,version() as `version` ";
			$version = $this->SelectQuery($query);
			return (isset($version[0]) ? $version[0] : $version);
		}

		//2022-03-03 JMT
		//NOTE: Inportant! Please render the params with COMPLETE keys with value. keys will represent as column name.
		//NOTE: This can only be use for update IF AND ONLY ALL THE PRIMARY KEY IS INCLUDED.
		public function mysql_bulk_insert_on_duplicate_key_update($table,$params){
			$stringValue = '';
			$updateValue = '';
			$tableColumn = '';
					$version = $this->check_mysql_version();
					$version = ['id' => 1];
					foreach($params as $k=>$insert){
						$cols = [];
						$value = '';
						$values = [];
						$updateCols = [];
						foreach ($insert as $key => $value) {
							$cols[] = "`$key`";
								if(isset($version['id']) && $version['id'] >=  9){
									$updateCols[] = "`$key` = temp.`$key`";
								}else{
									$updateCols[] = "`$key` = VALUES(`$key`)";
								}
							$value = $this->clean_string($value);
							if($value == ''){
									$values[] = 'NULL';
							}else{
									$values[] = '"'.$value.'"';
							}

						}

						$stringValue .= '('.implode(", ",  $values).'),';

					}
					$stringValue = rtrim($stringValue, ", ");
					$tableColumn = implode(", ", $cols);

					if(COUNT($updateCols) > 0){
							$updateValue .= implode(", ",  $updateCols).',';
					}
					$updateValue = rtrim($updateValue, ", ");

					//$query = 'INSERT INTO `'.$table.'` ('.$tableColumn.') VALUES '.$stringValue. ' ON DUPLICATE KEY UPDATE '.$updateValue.';';
					//$query = 'REPLACE INTO `'.$table.'` ('.$tableColumn.') VALUES '.$stringValue. ';';
					if(isset($version['id']) && $version['id'] >=  9){
						$query = 'INSERT INTO `'.$table.'` ('.$tableColumn.') VALUES '.$stringValue. ' AS tmp ON DUPLICATE KEY UPDATE '.$updateValue.';';
					}else{
						$query = 'INSERT INTO `'.$table.'` ('.$tableColumn.') VALUES '.$stringValue. ' ON DUPLICATE KEY UPDATE '.$updateValue.';';
					}
					$result = mysqli_query($this->MySQLconnection(),$query);
					return $result;

		}

		public function ValidateUser(){
					$user = "";
					$password = "";
					$rows = [];
					$data = "";

					if(isset($_SESSION['sec_username'])){

						$user = $_SESSION['sec_username'];
					}
					else if(isset($_GET['username'])){
						$user = mysqli_real_escape_string(\data::select_connection('view'),$_GET['username']);

					}else if(isset($_POST['username'])){
						$user = mysqli_real_escape_string(\data::select_connection('view'),$_POST['username']);
					}


					if(isset($_SESSION['sec_password'])){
									$password = $_SESSION['sec_password'];
									// $password = mysqli_real_escape_string(data::select_connection('view'),$_SESSION['sess_password']);
					}
					if(isset($_POST['password'])){
									$password = mysqli_real_escape_string(\data::select_connection('view'),md5($_POST['password']));
					}
					if(isset($_GET['password'])){
									$password = mysqli_real_escape_string(\data::select_connection('view'),md5($_GET['password']));
					}



					if($user == ""){
						return false;
					}


					if($user == ums_username){

						$password = ums_password;

					}

					//check if the user is legit
					$query = "SELECT
											`U_ID`,
											`U_Username`,
											`U_Password`,
											`U_AccountType`,
											`accountnumber`,
											`firstname`,
											`lastname`,
											`middlename`,
											`position`,
											`company`,
											`client`
									FROM
											`vw_useraccounts`
									WHERE
											`U_Username` = '$user' LIMIT 1";
					$rows = mysqli_query(\data::select_connection("view"),$query);
					$data = mysqli_fetch_assoc($rows);
					// echo $data["U_Password"]."=".$password;
					if($data["U_Username"] == $user && $data["U_Password"] == $password){

						return true;
					}
					return false;
		}


	  	public  function MySQLconnection()
	  	{
				 $con = new \Connection("","");
			   $dbhandle = mysqli_connect($con->host,$con->user,$con->pass,$this->database) or die("Could not connect to database");
			   return $dbhandle;
		}
			// Modify by Jayvie / August 23, 2025
			public function SelectQuery($query){
			$data = [];
			$query = $this->clean_string($query);
			$conn = $this->MySQLconnection();
			$result = mysqli_query($conn, $query);

			if ($result && mysqli_num_rows($result) > 0) {
				while($rows = mysqli_fetch_assoc($result)) {
					$data[] = $this->clean_string($rows);
				}
			} else {
				// Optional: log error for debugging
				error_log("SelectQuery failed: " . mysqli_error($conn));
				// data remains empty array
			}

			if(!$this->ValidateUser()){
				return [];
			}

			return $data;
		}

		public  function DeleteQuery($query){
				$query = $this->clean_string($query);
				if(!$this->ValidateUser()){
					return false;
				}
	     $result = mysqli_query($this->MySQLconnection(),$query);

	     return $result;
	    }

		public  function UpdateQuery($query){
				$query = $this->clean_string($query);

				if(!$this->ValidateUser()){
					return false;
				}
	      $result = mysqli_query($this->MySQLconnection(),$query);

	      return $result;
	    }

		public  function InsertQuery($table,$params){
			try{
				foreach ($params as $key => $value) {
					if($params[$key] == ""){
						unset($params[$key]);
					}
				}
	            foreach ($params as $key => $value) {
	                      if($value != ""){
	                        $cols[] = "`$key`";
	                      $values[] = '"'.addslashes($this->clean_string($value)).'"';
	                      }
	            }
	            $query = 'INSERT INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).') ';
	        	//echo $query;
						if(!$this->ValidateUser()){
							return false;
						}
	            $query = $this->clean_string($query);
	            $result = mysqli_query($this->MySQLconnection(),$query);
	      	            return $result;
	            }catch (Exception $e) {
					return "Caught exception: ".  $e->getMessage(). "\n";
				}
	    }

	    public  function InsertIgnoreQuery($table,$params){
			try{
				foreach ($params as $key => $value) {
					if($params[$key] == ""){
						unset($params[$key]);
					}
				}
	            foreach ($params as $key => $value) {
	                      if($value != ""){
	                        $cols[] = "`$key`";
	                      $values[] = '"'.addslashes($this->clean_string($value)).'"';
	                      }
	            }
	            $query = 'INSERT IGNORE INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).') ';
							if(!$this->ValidateUser()){
								return false;
							}
	            $query = $this->clean_string($query);
	            $result = mysqli_query($this->MySQLconnection(),$query);
	      	            return $result;
	            }catch (Exception $e) {
					return "Caught exception: ".  $e->getMessage(). "\n";
				}
	    }

	    public function mysql_insert_on_duplicate_key_update($table,$insert,$update){
            foreach ($insert as $key => $value) {
                      if($value != "" && $value != "NULL" && $value != " "){
                        $cols[] = "`$key`";
                         $value = $this->clean_string($value);
                      $values[] = '"'.$value.'"';
                      }
            }

            foreach ($update as $key => $value) {
                if($value != "" && $value != "NULL" && $value != " "){
                   $value = addslashes($this->clean_string($value));
                    $updateCols[] = "`$key` = '$value'";
                }
            }

            $query = 'INSERT INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).')
                                  ON DUPLICATE KEY UPDATE '.implode(", ", $updateCols);
                                // if($table == 'request_is_exported'){
								// 	echo $query;
								// }
																if(!$this->ValidateUser()){
																	return false;
																}
           	$result = mysqli_query($this->MySQLconnection(),$query);
	      	            return $result;
            return $result;
   		}


		public  function ReplaceQuery($table,$params){
				try{
				foreach ($params as $key => $value) {
					if($params[$key] == ""){
						unset($params[$key]);
					}
				}
	            foreach ($params as $key => $value) {

	                      if($value != ""){
	                        $cols[] = "`$key`";
	                      $values[] = '"'.addslashes($this->clean_string($value)).'"';
	                      }
	                }
	            $query = 'REPLACE INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).') ';
	  			$query = $this->clean_string($query);
					if(!$this->ValidateUser()){
						return false;
					}
					
	            $result = mysqli_query($this->MySQLconnection(),$query);

	            return $result;
	            }catch (Exception $e) {
					return "Caught exception: ".  $e->getMessage(). "\n";
				}
	   }

		public function ReturnMySQLTableResult($result){
	        $final_response = array();
					if(!$this->ValidateUser()){
						return false;
					}
	        while($rows = mysqli_fetch_assoc($result)){
	             $final_response[] = $this->clean_string($rows);
	        }
	        return $final_response;
	   	}

	    public function StoredProcedureNullQuery($function,$parameters){
	   		for($a = 0; $a < count($parameters); $a++){
	   			if($parameters[$a] != ""){
	   				$parameters[$a] = "'".addslashes($this->clean_string($parameters[$a]))."'";
	   			}else{
	   				$parameters[$a] = "NULL";
	   			}
	   		}
          	$query = "call `".$function."`(".implode(", ",  $parameters).")";
						// if($function == "do_insert_reading"){
						// 	echo $query;
						// }
          	 // echo $query;
          	 //echo $query;
						 if(!$this->ValidateUser()){
							 return false;
						 }
          	 $result = mysqli_query($this->MySQLconnection(),$query);
      	return $result;
      }

	  	public function StoredProcedureQuery($function,$parameters){
	   		for($a = 0; $a < count($parameters); $a++){
	   			$parameters[$a] = addslashes($this->clean_string($parameters[$a]));
	   		}

          	$query = "call `".$function."`('".implode("', '",  $parameters)."')";
						 if(!$this->ValidateUser()){
 							return false;
 						}

						if($function == "doInsertBillSchedules"){
							echo $query;
						}
						
      		$result = mysqli_query($this->MySQLconnection(),$query);
      		return $result;
      }

			public function StoredProcedureQueryWithoutParameters($function){
          	$query = "call 	".$function."()";
          	// print_r($query);
          	 // if($function == "insertToSapAccountingEntryBilling"){
          	 // 	 echo $query;
          	 // }

						 if(!$this->ValidateUser()){
 							return false;
 						}
      		$result = mysqli_query($this->MySQLconnection(),$query);
      		return $result;
      }

      public  function InsertQueryUpperCase($table,$params){
			try{
				foreach ($params as $key => $value) {
					if($params[$key] == ""){
						unset($params[$key]);
					}
				}
	            foreach ($params as $key => $value) {
	                      if($value != ""){
	                        $cols[] = "`$key`";
	                      $values[] = '"'.addslashes($this->clean_string(strtoupper($value))).'"';
	                      }
	            }
	            $query = 'INSERT INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).') ';
	        	// echo $query;
						if(!$this->ValidateUser()){
							return false;
						}
	            $query = $this->clean_string($query);
	            $result = mysqli_query($this->MySQLconnection(),$query);
	      	            return $result;
	            }catch (Exception $e) {
					return "Caught exception: ".  $e->getMessage(). "\n";
				}
	    }
       public function mysql_insert_on_duplicate_key_update_UpperCase($table,$insert,$update){
            foreach ($insert as $key => $value) {
                      if($value != "" && $value != "NULL" && $value != " "){
                        $cols[] = "`$key`";
                         $value = $this->clean_string(strtoupper($value));
                      $values[] = '"'.$value.'"';
                      }
            }

            foreach ($update as $key => $value) {
                if($value != "" && $value != "NULL" && $value != " "){
                   $value = addslashes($this->clean_string(strtoupper($value)));
                    $updateCols[] = "`$key` = '$value'";
                }
            }

            $query = 'INSERT INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).')
                                  ON DUPLICATE KEY UPDATE '.implode(", ", $updateCols);
                                //echo $query;
																if(!$this->ValidateUser()){
																	return false;
																}
           	$result = mysqli_query($this->MySQLconnection(),$query);
	      	            return $result;
            return $result;
   		}

      public  function ReplaceQueryUpperCase($table,$params){
				try{
				foreach ($params as $key => $value) {
					if($params[$key] == ""){
						unset($params[$key]);
					}
				}
	            foreach ($params as $key => $value) {

	                      if($value != ""){
	                        $cols[] = "`$key`";
	                      $values[] = '"'.addslashes($this->clean_string(strtoupper($value))).'"';
	                      }
	                }
	            $query = 'REPLACE INTO `'.$table.'` ('.implode(", ", $cols).') VALUES ('.implode(", ",  $values).') ';

	  			$query = $this->clean_string($query);
					if(!$this->ValidateUser()){
						return false;
					}
	            $result = mysqli_query($this->MySQLconnection(),$query);

	            return $result;
	            }catch (Exception $e) {
					return "Caught exception: ".  $e->getMessage(). "\n";
				}
	   }

	    public function StoredProcedureNullQueryUpperCase($function,$parameters){
	   		for($a = 0; $a < count($parameters); $a++){
	   			if($parameters[$a] != ""){
	   				$parameters[$a] = "'".addslashes($this->clean_string(strtoupper($parameters[$a])))."'";
	   			}else{
	   				$parameters[$a] = "NULL";
	   			}
	   		}
          	$query = "call `".$function."`(".implode(", ",  $parameters).")";
          	 // echo $query;
          	//	echo $query;
						if(!$this->ValidateUser()){
							return false;
						}
          	 $result = mysqli_query($this->MySQLconnection(),$query);
      	return $result;
      }
	  public function StoredProcedureQueryUpperCase($function,$parameters){
	   		for($a = 0; $a < count($parameters); $a++){
	   			$parameters[$a] = addslashes($this->clean_string(strtoupper($parameters[$a])));
	   		}

          	$query = "call `".$function."`('".implode("', '",  $parameters)."')";
						if(!$this->ValidateUser()){
							return false;
						}
      		$result = mysqli_query($this->MySQLconnection(),$query);
      		return $result;
      }
	}
?>
