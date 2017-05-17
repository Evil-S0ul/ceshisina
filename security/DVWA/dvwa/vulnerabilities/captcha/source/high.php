<?php

if( isset( $_POST[ 'Change' ] ) ) {
	// Hide the CAPTCHA form
	$hide_form = true;

	$link = mysqli_connect($_DVWA[ 'db_server' ], $_DVWA[ 'db_user' ], $_DVWA[ 'db_password' ], $_DVWA[ 'db_database' ]);
	// Get input
	$pass_new  = $_POST[ 'password_new' ];
	$pass_conf = $_POST[ 'password_conf' ];

	// Check CAPTCHA from 3rd party
	$resp = recaptcha_check_answer( $_DVWA[ 'recaptcha_private_key' ],
		$_SERVER[ 'REMOTE_ADDR' ],
		$_POST[ 'recaptcha_challenge_field' ],
		$_POST[ 'recaptcha_response_field' ] );

	// Did the CAPTCHA fail?
	if( !$resp->is_valid && ( $_POST[ 'recaptcha_response_field' ] != 'hidd3n_valu3' || $_SERVER[ 'HTTP_USER_AGENT' ] != 'reCAPTCHA' ) ) {
		// What happens when the CAPTCHA was entered incorrectly
		$html     .= "<pre><br />The CAPTCHA was incorrect. Please try again.</pre>";
		$hide_form = false;
		return;
	}
	else {
		// CAPTCHA was correct. Do both new passwords match?
		if( $pass_new == $pass_conf ) {
			// $pass_new = mysql_real_escape_string( $pass_new );
			$pass_new = md5( $pass_new );

			// Update database
			$insert = "UPDATE `users` SET password = '$pass_new' WHERE user = '" . dvwaCurrentUser() . "' LIMIT 1;";
			$result = mysqli_query($link, $insert ) or die( '<pre>' . mysqli_error($link) . '</pre>' );

			// Feedback for user
			$html .= "<pre>Password Changed.</pre>";
		}
		else {
			// Ops. Password mismatch
			$html     .= "<pre>Both passwords must match.</pre>";
			$hide_form = false;
		}
	}

	mysqli_close($link);
}

// Generate Anti-CSRF token
generateSessionToken();

?>
