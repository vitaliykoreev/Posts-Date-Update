<?php
	/*
	Plugin Name: Posts Date Auto Update
	Description: Auto update posts date.
	Version: 1.0
	Author: Vitalii Koreiev
	Author URI: #
	License: GPLv2 or later
	*/

	require_once dirname(__FILE__)."/includes/pdu_functions.php";

	register_activation_hook(__FILE__, 'pdu_activation');
	register_deactivation_hook(__FILE__, 'pdu_deactivation');