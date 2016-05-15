<?php

//TODO:  show error off
require_once("mysql.class.php");
$dbHost = "localhost";
$dbUsername = "root";
$dbPassword = " ";
$dbName = "test";
$db = new MySQL($dbHost,$dbUsername,$dbPassword,$dbName);
// if operation is failed by unknown reason
define("FAILED", 0);
define("SUCCESSFUL", 1);
// when  signing up, if username is already taken, return this error
define("SIGN_UP_USERNAME_CRASHED", 2);  
// when add new friend request, if friend is not found, return this error 
define("ADD_NEW_USERNAME_NOT_FOUND", 2);
// TIME_INTERVAL_FOR_USER_STATUS: if last authentication time of user is older 
// than NOW - TIME_INTERVAL_FOR_USER_STATUS, then user is considered offline
define("TIME_INTERVAL_FOR_USER_STATUS", 60);
define("USER_APPROVED", 1);
define("USER_UNAPPROVED", 0);
$username = (isset($_REQUEST['username']) && count($_REQUEST['username']) > 0) 
							? $_REQUEST['username'] 
							: NULL;
$password = isset($_REQUEST['password']) ? md5($_REQUEST['password']) : NULL;
$port = isset($_REQUEST['port']) ? $_REQUEST['port'] : NULL;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : NULL;
if ($username == NULL || $password == NULL)	 
{
	echo FAILED;
	exit;
}
$out = NULL;
error_log($action."\r\n", 3, "error.log");
switch($action) 
{
	
	case "authenticateUser":
		
		
		if ($port != NULL  
				&& ($userId = authenticateUser($db, $username, $password, $port)) != NULL) 
		{					
			
			// providerId and requestId is Id of  a friend pair,
			// providerId is the Id of making first friend request
			// requestId is the Id of the friend approved the friend request made by providerId
			
			// fetching friends, 
			// left join expression is a bit different, 
			//		it is required to fetch the friend, not the users itself
			
			$sql = "select u.Id, u.username, (NOW()-u.authenticationTime) as authenticateTimeDifference, u.IP, 
										f.providerId, f.requestId, f.status, u.port 
							from friends f
							left join users u on 
										u.Id = if ( f.providerId = ".$userId.", f.requestId, f.providerId ) 
							where (f.providerId = ".$userId." and f.status=".USER_APPROVED.")  or 
										 f.requestId = ".$userId." ";
			
	
			if ($result = $db->query($sql))			
			{
					$out .= "<data>"; 
					$out .= "<user userKey='".$userId."' />";
					while ($row = $db->fetchObject($result))
					{
						$status = "offline";
						if (((int)$row->status) == USER_UNAPPROVED)
						{
							$status = "unApproved";
						}
						else if (((int)$row->authenticateTimeDifference) < TIME_INTERVAL_FOR_USER_STATUS)
						{
							$status = "online";
							 
						}
						$out .= "<friend  username = '".$row->username."'  status='".$status."' IP='".$row->IP."' 
												userKey = '".$row->Id."'  port='".$row->port."'/>";
												
												// to increase security, we need to change userKey periodically and pay more attention
												// receiving message and sending message 
						
					}				
					$out .= "</data>";
			}
			else
			{
				$out = FAILED;
			}			
		}
		else
		{
				// exit application if not authenticated user
				$out = FAILED;
		}
		
	
	
	break;
	
	case "signUpUser":
		if (isset($_REQUEST['email']))
		{
			 $email = $_REQUEST['email'];		
			 	
			 $sql = "select Id from  users 
			 				where username = '".$username."' limit 1";
			 
		
			 				
			 if ($result = $db->query($sql))
			 {
			 		if ($db->numRows($result) == 0) 
			 		{
			 				$sql = "insert into users(username, password, email)
			 					values ('".$username."', '".$password."', '".$email."') ";		 					
						 					
			 					error_log("$sql", 3 , "error_log");
							if ($db->query($sql))	
							{
							 		$out = SUCCESSFUL;
							}				
							else {
									$out = FAILED;
							}				 			
			 		}
			 		else
			 		{
			 			$out = SIGN_UP_USERNAME_CRASHED;
			 		}
			 }				 	 	
		}
		else
		{
			$out = FAILED;
		}	
	break;
	
	case "addNewFriend":
		$userId = authenticateUser($db, $username, $password);
		if ($userId != NULL)
		{
			
			if (isset($_REQUEST['friendUserName']))			
			{				
				 $friendUserName = $_REQUEST['friendUserName'];
				 
				 $sql = "select Id from users 
				 				 where username='".$friendUserName."' 
				 				 limit 1";
				 if ($result = $db->query($sql))
				 {
				 		if ($row = $db->fetchObject($result))
				 		{
				 			 $requestId = $row->Id;
				 			 
				 			 if ($row->Id != $userId)
				 			 {
				 			 		 $sql = "insert into friends(providerId, requestId, status)
				 				  		 values(".$userId.", ".$requestId.", ".USER_UNAPPROVED.")";
							 
									 if ($db->query($sql))
									 {
									 		$out = SUCCESSFUL;
									 }
									 else
									 {
									 		$out = FAILED;
									 }
							}
							else
							{
								$out = FAILED;  // user add itself as a friend
							} 		 				 				  		 
				 		}
				 		else
				 		{
				 			$out = FAILED;			 			
				 		}
				 }				 				 
				 else
				 {
				 		$out = FAILED;
				 }				
			}
			else
			{
					$out = FAILED;
			} 			
		}
		else
		{
			$out = FAILED;
		}	
	break;
	
	case "responseOfFriendReqs":
		$userId = authenticateUser($db, $username, $password);
		if ($userId != NULL)
		{
			$sqlApprove = NULL;
			$sqlDiscard = NULL;
			if (isset($_REQUEST['approvedFriends']))
			{
				  $friendNames = split(",", $_REQUEST['approvedFriends']);
				  $friendCount = count($friendNames);
				  $friendNamesQueryPart = NULL;
				  for ($i = 0; $i < $friendCount; $i++)
				  {
				  	if (strlen($friendNames[$i]) > 0)
				  	{
				  		if ($i > 0 )
				  		{
				  			$friendNamesQueryPart .= ",";
				  		}
				  		
				  		$friendNamesQueryPart .= "'".$friendNames[$i]."'";
				  		
				  	}			  	
				  	
				  }
				  if ($friendNamesQueryPart != NULL)
				  {
				  	$sqlApprove = "update friends set status = ".USER_APPROVED."
				  					where requestId = ".$userId." and 
				  								providerId in (select Id from users where username in (".$friendNamesQueryPart."));
				  				";		
				  }
				  				  
			}
			if (isset($_REQUEST['discardedFriends']))
			{
					$friendNames = split(",", $_REQUEST['discardedFriends']);
				  $friendCount = count($friendNames);
				  $friendNamesQueryPart = NULL;
				  for ($i = 0; $i < $friendCount; $i++)
				  {
				  	if (strlen($friendNames[$i]) > 0)
				  	{
				  		if ($i > 0 )
				  		{
				  			$friendNamesQueryPart .= ",";
				  		}
				  		
				  		$friendNamesQueryPart .= "'".$friendNames[$i]."'";
				  		
				  	}				  	
				  }
				  if ($friendNamesQueryPart != NULL)
				  {
				  	$sqlDiscard = "delete from friends 
				  						where requestId = ".$userId." and 
				  									providerId in (select Id from users where username in (".$friendNamesQueryPart."));
				  							";
				  }						
			}
			if (  ($sqlApprove != NULL ? $db->query($sqlApprove) : true) &&
						($sqlDiscard != NULL ? $db->query($sqlDiscard) : true) 
			   )
			{
				$out = SUCCESSFUL;
			}
			else
			{
				$out = FAILED;
			}		
		}
		else
		{
			$out = FAILED;
		}
	break;
	
	default:
		$out = FAILED;		
		break;	
}
echo $out;
///////////////////////////////////////////////////////////////
function authenticateUser($db, $username, $password, $port)
{
	
	$sql = "select Id from users 
					where username = '".$username."' and password = '".$password."' 
					limit 1";
	
	$out = NULL;
	if ($result = $db->query($sql))
	{
		if ($row = $db->fetchObject($result))
		{
				$out = $row->Id;
				
				$sql = "update users set authenticationTime = NOW(), 
																 IP = '".$_SERVER["REMOTE_ADDR"]."' ,
																 port = ".$port." 
								where Id = ".$row->Id."
								limit 1";
				
				$db->query($sql);				
								
								
		}		
	}
	
	return $out;
}
?>