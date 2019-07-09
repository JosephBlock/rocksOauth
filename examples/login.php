<?php
session_start();
require_once('../src/rocksOauth.php');

$r = new rocksOauth();
/*
 * Change values to what you need
 * scopes are set as constants
 */
$r->setClient("Your client here");
$r->setSecret("Your secret here");
$r->addScope(rocksOauth::TESTING_SCOPE);
$r->setRedirect("redirect URL here");
if (isset($_GET['logout'])) {
	unset($_SESSION['rocksACCESS_TOKEN']);
	unset($_SESSION['rocksREFRESH']);
}

if (isset($_GET['code'])) {

		$r->setCode(trim($_GET['code']));
		$_SESSION['rocksACCESS_TOKEN'] = $r->getToken();
		$_SESSION['rocksREFRESH'] = $r->getRefreshToken();
		//redirect to script after login
		header("Location: " . $r->redirect);

//do something after login

} else if (isset($_SESSION['rocksACCESS_TOKEN'])) {
	//set token from session and use it to retrieve data

	$r->setToken($_SESSION['rocksACCESS_TOKEN']);
	try {
		$rInfo = $r->getRocksProfile();

		echo "you are logged into Rocks. Welcome back " . $rInfo->agentid;
		echo "<br><br>";
		var_dump($rInfo);
		echo "<br><br>";
		$tInfo = $r->getTelegram();
		echo "telegram ".$tInfo->name." id: ".$tInfo->tgid;
		echo "<br><br>";
		$uInfo = $r->getRocksUser();
		$verified = $uInfo->verified?"yes":"no";
		echo "name: ".$uInfo->name." verified: ".$verified;

	} catch (Exception $e) {
		echo $e->getMessage();
	}

} else {

	//if not logged in, create auth url
	echo $auth = $r->getAuthURL("stateGoesHere");
	//redirect to V Oauth login page
	header("Location: " . $auth);
}

