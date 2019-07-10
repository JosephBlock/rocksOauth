<?php
/**
 *
 * Licence: MIT License (MIT)
 * Copyright (c) 2019 Joseph Block
 *
 * This class is used to communicate and authenticate against rocks
 */
session_start();
require_once( '../src/rocksOauth.php' );

$r = new rocksOauth();
/*
 * Change values to what you need
 * scopes are set as constants
 */
$r->setClient( "Your client here" );
$r->setSecret( "Your secret here" );
$r->addScope( array_merge( rocksOauth::TESTING_SCOPE, array( rocksOauth::SCOPE_EVENT ) ) );
$r->setRedirect( "redirect URL here" );
if( isset( $_GET['logout'] ) ) {
	unset( $_SESSION['rocksACCESS_TOKEN'] );
	unset( $_SESSION['rocksREFRESH'] );
}

if( isset( $_GET['code'] ) ) {

	$r->setCode( trim( $_GET['code'] ) );
	$_SESSION['rocksACCESS_TOKEN'] = $r->getToken();
	$_SESSION['rocksREFRESH']      = $r->getRefreshToken();
	//redirect to script after login
	header( "Location: " . $r->redirect );

//do something after login

} elseif( isset( $_SESSION['rocksACCESS_TOKEN'] ) ) {
	//set token from session and use it to retrieve data

	$r->setToken( $_SESSION['rocksACCESS_TOKEN'] );
	try {
		$rInfo = $r->getRocksProfile();

		echo "you are logged into Rocks. Welcome back " . $rInfo->agentid;
		echo "<br><br>";
		var_dump( $rInfo );
		echo "<br><br>";
		$tInfo = $r->getTelegram();
		echo "telegram " . $tInfo->name . " id: " . $tInfo->tgid;
		echo "<br><br>";
		$uInfo    = $r->getRocksUser();
		$verified = $uInfo->verified ? "yes" : "no";
		echo "name: " . $uInfo->name . " verified: " . $verified;
		echo "<br><br><a href='rsvp.php'>rsvp test page</a>";

	} catch ( Exception $e ) {
		echo $e->getMessage();
	}

} elseif( isset( $_GET['error'] ) ) {
	$error = $_GET['error'];
	echo "there was an error with the request: " . $error;
	if( $error === "access_denied" ) {
		echo "<br>This is possibly due to no registration for event";
	}
} else {

	//if not logged in, create auth url
	echo $auth = $r->getAuthURL( "stateGoesHere" );
	//redirect to V Oauth login page
	header( "Location: " . $auth );
}

