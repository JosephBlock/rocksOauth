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

if( isset( $_SESSION['rocksACCESS_TOKEN'] ) ) {
	//set token from session and use it to retrieve data

	$r->setToken( $_SESSION['rocksACCESS_TOKEN'] );
	try {
		$rInfo = $r->getRocksProfile();

		echo "you are logged into Rocks. Welcome back " . $rInfo->agentid;
		$rsvp = $r->getRSVP();
		echo "<br>you are on team {$rsvp->team->name}<br>";
		echo "your leader is {$rsvp->team->leader}<br>";
		echo "anomaly series is {$rsvp->series}<br>";
		$roles = implode( $rsvp->roles, ", " );
		echo "your roles: {$roles}<br>";
		echo "<br>";
		var_dump( $rsvp );


	} catch ( Exception $e ) {
		echo $e->getMessage();
	}

} else {

	//if not logged in, create auth url
	echo $auth = $r->getAuthURL( "stateGoesHere" );
	//redirect to V Oauth login page
	header( "Location: " . $auth );
}

