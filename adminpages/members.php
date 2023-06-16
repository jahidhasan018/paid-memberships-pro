<?php
// only admins can get this
$cap = apply_filters( 'pmpro_add_member_cap', 'edit_users' );
if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( $cap ) ) ) {
	die( esc_html__( 'You do not have permissions to perform this action.', 'paid-memberships-pro' ) );
}

// Global vars.
global $wpdb, $msg, $msgt, $pmpro_currency_symbol, $pmpro_required_user_fields, $pmpro_error_fields, $pmpro_msg, $pmpro_msgt;

// Check if editing a user.
if ( ! empty( $_REQUEST['user_id'] ) ) {
	$user_id = intval( $_REQUEST['user_id'] );
	$user = get_userdata( $user_id );
	if ( empty( $user->ID ) ) {
		$user_id = false;		
	} else  {
		// We have a user, let's get the user metadata
		$user_notes = get_user_meta( $user_id, 'user_notes', true );
	}
}

// Some vars for the form.
$role = ! empty( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : get_option( 'default_role' );

// this block handles form submission
if ( ! empty( $_POST ) ) {
	$user_login = ! empty( $_POST['user_login'] ) ? sanitize_user( $_POST['user_login'] ) : '';
	$user_email = ! empty( $_POST['email'] ) ? stripslashes( sanitize_email( $_POST['email'] ) ) : '';
	$first_name = ! empty( $_POST['first_name'] ) ? stripslashes( sanitize_text_field( $_POST['first_name'] ) ): '';
	$last_name = ! empty( $_POST['last_name'] ) ? stripslashes( sanitize_text_field( $_POST['last_name'] ) ) : '';	
	$user_notes = ! empty( $_POST['user_notes'] ) ? stripslashes( sanitize_text_field( $_POST['user_notes'] ) ) : '';

	if ( isset( $_POST['membership_level'] ) ) {
		$membership_level = intval( $_POST['membership_level'] );
	} elseif ( ! empty( $user ) ) {
		$user->membership_level = pmpro_getMembershipLevelForUser( $user_id );
		if ( ! empty( $user->membership_level ) ) {
			$membership_level = $user->membership_level->id;
		} else {
			$membership_level = '';
		}
	} else {
			$membership_level = '';
	}

	// check for required fields
	$pmpro_required_user_fields = apply_filters(
		'pmpro_required_user_fields', array(
			'user_login' => $user_login,
			'user_email' => $user_email,
		)
	);
	$pmpro_error_fields = array();
	foreach ( $pmpro_required_user_fields as $key => $value ) {
		if ( empty( $value ) ) {
			$pmpro_error_fields[] = $key;
		}
	}

	if ( ! empty( $pmpro_error_fields ) ) {
		pmpro_setMessage( __( 'Please fill out all required fields:', 'paid-memberships-pro' ) . ' ' . implode( ', ', $pmpro_error_fields ), 'pmpro_error' );
	}

	// Check that the email and username are available.
	$ouser      = get_user_by( 'login', $user_login );
	$oldem_user = get_user_by( 'email', $user_email );

	/**
	 * This hook can be used to allow multiple accounts with the same email address.
	 * This is also set in preheaders/checkout.php
	 * @todo Abstract to a function so we only have one filter.
	 * Return null to allow duplicate users with the same email.
	 */
	$oldemail = apply_filters( "pmpro_checkout_oldemail", ( false !== $oldem_user ? $oldem_user->user_email : null ) );

	if ( ! empty( $ouser->user_login ) ) {
		pmpro_setMessage( __( "That username is already taken. Please try another.", 'paid-memberships-pro' ), "pmpro_error" );
		$pmpro_error_fields[] = "username";
	}

	if ( ! empty( $oldemail ) ) {
		pmpro_setMessage( __( "That email address is already in use. Please log in, or use a different email address.", 'paid-memberships-pro' ), "pmpro_error" );
		$pmpro_error_fields[] = "bemail";
		$pmpro_error_fields[] = "bconfirmemail";
	}	

	// okay so far?
	if ( $pmpro_msgt != 'pmpro_error' ) {
		// random password if needed
		if ( ! $user && empty( $user_pass ) ) {
			$user_pass = wp_generate_password();
			$send_password = true; // Force this option to be true, if the password field was empty so the email may be sent.
		}
		$user_to_post = array( 
			'ID' => $user_id,
			'user_login' => $user_login,
			'user_email' => $user_email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'role' => $role,
		);

		$user_id = ! empty( $_REQUEST['user_id'] ) ? wp_update_user($user_to_post) : wp_insert_user($user_to_post);
	}

	if ( ! $user_id ) {
		pmpro_setMessage( __( 'Error creating user.', 'paid-memberships-pro' ), 'pmpro_error' );
	} else {
		// other user meta
		update_user_meta( $user_id, 'user_notes', $user_notes );

		// figure out start date
		$now = current_time( 'timestamp' );
		$startdate = date( 'Y-m-d', $now );	

		// notify user
		if ( $send_password ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}

		// Got here with no errors.
		// TODO: We want to redirect to the edit level step instead. We have to run this all earlier to do that.
		if ( $pmpro_msgt != 'pmpro_error' ) {
			// set message			
			pmpro_setMessage( esc_html__( 'User added.', 'paid-memberships-pro' ), 'pmpro_success' );
					
			// clear vars
			$payment = '';
			$gateway = '';
			$total = '';
			$order_notes = '';

			// clear user vars too if one wasn't passed in
			if ( empty( $_REQUEST['user_id'] ) ) {
				$user = get_userdata( 0 );
				$user_id = false;
				$user_login = '';
				$user_email = '';
				$first_name = '';
				$last_name = '';
				$user_pass = '';
				$user_notes = '';
			}
		} else {
			global $pmpro_msg;
			$pmpro_msg = esc_html__( 'There was an error creating adding this member: ', 'paid-memberships-pro' ) . $pmpro_msg;
		}
	}
}
?>
<style>
form.pmpro-members-edit {
	display: flex;
	gap:40px;
}
form.pmpro-members-edit nav {
	flex-shrink: 0;
    width: 300px;
}
form nav button {
	background: none;
  	border: none;
  	display: block;
    padding: 12px 40px 12px 20px;
    color: #555;
    text-decoration: none;
    border-radius: 5px;
    position: relative;
	width: 100%;
	text-align: left;
}

form nav button[aria-selected="true"] {
    background-color: #e3e8ee;
    color: #333;
}

form nav button:hover {
	background-color: rgba(255,255,255,.7);

}
form nav button:focus {
	outline: 2px solid rgba(0,0,0,.6)
}

form div.panel-wrappers {
	padding: 25px 50px;
    background-color: #FFF;
    border-radius: 5px;
    box-shadow: 0 1px 4px rgb(18 25 97 / 8%);
}

form div.submit {
	clear: both;
	display: block;
	padding: 1em 0;
}
form td button.toggle-pass-visibility {
	background: transparent;
	border: none;
}

form td .dashicons {
	vertical-align: middle;
}
</style>

<?php
if ( $pmpro_msg ) {
?>
	<div id="pmpro_message" class="pmpro_message <?php echo esc_attr( $pmpro_msgt ); ?>"><?php echo wp_kses_post( $pmpro_msg ); ?></div>
<?php
} else {
?>
<div id="pmpro_message" class="pmpro_message" style="display: none;"></div>
<?php
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html_e( 'Add Member', 'paid-memberships-pro' );?></h1>
		<div>
			<?php if (! empty( $user_id ) ) { ?>
			<form class="pmpro-members <?php if ( ! empty( $_REQUEST['user_id'] ) ) {?>pmpro-members-edit<?php } ?>" action="" method="post">
				<nav id="user-menu" role="tablist" aria-label="Add Member Field Tabs">
					<button
						role="tab"
						aria-selected="true"
						aria-controls="panel-1"
						id="tab-1"
						tabindex="0">
						Required Fields
					</button>
					<button
						role="tab"
						aria-selected="false"
						aria-controls="panel-2"
						id="tab-2"
						tabindex="-1">
						Info
					</button>
					<button
						role="tab"
						aria-selected="false"
						aria-controls="panel-3"
						id="tab-3"
						tabindex="-1">
						Membership
					</button>
					<button
						role="tab"
						aria-selected="false"
						aria-controls="panel-4"
						id="tab-4"
						tabindex="-1">
						Billing
					</button>
					<button
						role="tab"
						aria-selected="false"
						aria-controls="panel-5"
						id="tab-5"
						tabindex="-1">
						Notes
					</button>
				</nav>
				<div class="panel-wrappers">
					<div id="panel-1" role="tabpanel" tabindex="0" aria-labelledby="tab-1">
						<h2>Required Fields</h2>
						<table class="form-table">
							<tr>
								<th><label for="user_logim">Username (required) </label></th>
								<td><input type="text" name="user_login" id="user_login" autocapitalize="none" autocorrect="off" autocomplete="off" required readonly="true" value="<?php echo esc_attr( $user->user_login ) ?>"></td>
							</tr>
							<tr>
								<th><label for="email">Email (required)</label></th>
								<td><input type="email" name="email" id="email" autocomplete="new-password" spellcheck="false" required value="<?php echo esc_attr( $user->user_email ) ?>"></td>
							</tr>
							
						</table>
					</div>
					<div id="panel-2" role="tabpanel" tabindex="0" aria-labelledby="tab-2" hidden>
						<h2>Info</h2>
						<table class="form-table">
							<tr>
								<th><label for="first_name">First Name</label></th>
								<td><input type="text" name="first_name" id="first_name" autocomplete="off" value="<?php echo $user->first_name ?>"></td>
							</tr>
							<tr>
								<th><label for="last_name">Last Name</label></th>
								<td><input type="text" name="last_name" id="last_name" autocomplete="off" value="<?php echo $user->last_name ?>"></td>
							</tr>
							<tr>
								<th><label for="password">Password</label></th>
								<td>
									<input type="password" name="password" id="password" autocomplete="off" required value="<?php echo  $user->user_pass ?>">
									<button class="toggle-pass-visibility" aria-controls="password" aria-expanded="false"><span class="dashicons dashicons-visibility toggle-pass-visibility"></span></button>
								</td>
							</tr>
							<tr>
								<th><label for="send_password">Send User Notification</label></th>
								<td><input type="checkbox" name="send_password" id="send_password">
								<label for="send_password">Send the new user an email about their account.</label>
								</td>
							</tr>
							<tr>
								<th><label for="role"><?php esc_html_e( 'Role', 'paid-memberships-pro' ); ?></label></th>
								<td>
									<select name="role" id="role" class="<?php echo pmpro_getClassForField( 'role' ); ?>">
										<?php  wp_dropdown_roles( $role ); ?>
									</select>
								</td>
							</tr>
						</table>
					</div>
						<div id="panel-3" role="tabpanel" tabindex="0" aria-labelledby="tab-3" hidden>
							<h2>Membership</h2>
							<table class="form-table">
								<tr>
									<th><label for="membership_level"><?php esc_html_e( 'Membership Level', 'paid-memberships-pro' ); ?></label></th>
									<td>
										<select name="membership_level" id="membership_level">
											<option value="" <?php selected( '', $membership_level ); ?> class="<?php echo pmpro_getClassForField( 'membership_level' ); ?>"><?php esc_html_e( 'No Level', 'paid-memberships-pro' ); ?></option>
											<?php
												$levels = pmpro_getAllLevels( true, true );
											foreach ( $levels as $level ) {
												?>
												<option value="<?php echo esc_attr( $level->id ); ?>" <?php selected( $level->id, $membership_level ); ?>><?php echo esc_html( $level->name ); ?></option>
												<?php
											}
											?>
										</select>
									</td>
								</tr>
								
							</table>

						</div>
					<div id="panel-4" role="tabpanel" tabindex="0" aria-labelledby="tab-4" hidden>
						<h2>Billing</h2>
						<table class="form-table">
							<tr>
								<th><label for="payment"><?php esc_html_e( 'Payment', 'paid-memberships-pro' ); ?></label></th>
								<td>
									<select name="payment" id="payment">
										<option value="" <?php selected( '', $payment ); ?>><?php esc_html_e( 'None', 'paid-memberships-pro' ); ?></option>
										<option value="check" <?php selected( 'check', $payment ); ?>><?php esc_html_e( 'Check/Cash', 'paid-memberships-pro' ); ?></option>
										<option value="gateway" <?php selected( 'gateway', $payment ); ?>><?php esc_html_e( 'Gateway (Not Functional)', 'paid-memberships-pro' ); ?></option>
										<option value="credit" <?php selected( 'credit', $payment ); ?>><?php esc_html_e( 'Credit Card (Not Functional)', 'paid-memberships-pro' ); ?></option>
									</select>
								</td>
							</tr>

							<tr class="payment payment-check payment-gateway payment-credit">
								<th><label for="total"><?php esc_html_e( 'Order Total', 'paid-memberships-pro' ); ?></label></th>
								<td>
									<?php
									global $pmpro_currency_symbol;
									if ( pmpro_getCurrencyPosition() == 'left' ) {
										echo $pmpro_currency_symbol;
									}
									?>
									<input name="total" id="total" type="text" autocomplete="off" size="50" class="<?php echo pmpro_getClassForField( 'total' ); ?>" value="<?php echo esc_attr( $total ); ?>" />
									<?php
									if ( pmpro_getCurrencyPosition() == 'right' ) {
										echo $pmpro_currency_symbol;
									}
									?>
								</td>
							</tr>
						</table>
					</div>
					<div id="panel-5" role="tabpanel" tabindex="0" aria-labelledby="tab-5" hidden>
						<h2>Notes</h2>
						<table class="form-table">
							
							<!-- Do we need this for the edit scenario ?
								<tr>
								<th><label for="order_notes"><?php esc_html_e( 'Order Notes', 'paid-memberships-pro' ); ?></label></th>
								<td>
									<textarea name="order_notes" id="order_notes" rows="5" cols="80" class="<?php echo pmpro_getClassForField( 'order_notes' ); ?>"><?php echo esc_textarea( $order_notes ); ?></textarea>
								</td>
							</tr> -->
						<tr>

							<th><label for="user_notes"><?php esc_html_e( 'User Notes', 'paid-memberships-pro' ); ?></label></th>
								<td>
									<textarea name="user_notes" id="user_notes" rows="5" cols="80" class="<?php echo pmpro_getClassForField( 'user_notes' ); ?>"><?php echo esc_textarea( $user_notes ); ?></textarea>
								</td>
						</tr>
						</table>
					</div>
					<div class="submit">
						<input type="submit" name="submit" value="Save User" class="button button-primary">
					</div>
				</div>
			</form>
			<?php  } else {?>
			<form action="" method="post">
				<?php wp_nonce_field('custom_user_form', 'custom_user_form_nonce'); ?>

				<table class="form-table">
					<tr class="form-field form-required">
						<th><label for="username">Username (required)</label></th>
						<td><input type="text" name="user_login" id="user_login" required></td>
					</tr>
					<tr class="form-field form-required">
						<th><label for="email">Email (required)</label></th>
						<td><input type="email" name="email" id="email" required></td>
					</tr>
					<tr class="form-field">
						<th><label for="first_name">First Name</label></th>
						<td><input type="text" name="first_name" id="first_name"></td>
					</tr>
					<tr>
						<th><label for="last_name">Last Name</label></th>
						<td><input type="text" name="last_name" id="last_name"></td>
					</tr>
					<tr class="form-field">
						<th><label for="password">Password:</label></th>
						<td><input type="password" name="password" id="password" required></td>
					</tr>
					<tr class="form-field">
						<th><label for="send_password">Send Password?</label></th>
						<td>
							<input type="checkbox" name="send_password" id="send_password">
							<label for="send_password">Send the new user an email about their account.</label>
						</td>
					</tr>
					<tr class="form-field">
						<th><label for="user_notes">User Notes:</label></th>
						<td><textarea name="user_notes" id="user_notes"></textarea></td>
					</tr>
					<tr class="form-field">
						<th><label for="role">Role</label></th>
						<td>
							<select name="role" id="role" class="<?php echo pmpro_getClassForField( 'role' ); ?>">
								<?php wp_dropdown_roles( $role ); ?>
							</select>
						</td>
					</tr>
				</table>
				<div class="submit">
					<input type="submit" name="submit" value="Save User" class="button button-primary">
				</div>
			</form>
			<?php } ?>
        </div>
    </div>
<script>
	window.addEventListener("DOMContentLoaded", () => {
	const tabs = document.querySelectorAll('[role="tab"]');
	const tabList = document.querySelector('[role="tablist"]');

  // Add a click event handler to each tab
  tabs.forEach((tab) => {
    tab.addEventListener("click", changeTabs);
  });

  // Enable arrow navigation between tabs in the tab list
  let tabFocus = 0;

  tabList.addEventListener("keydown", (e) => {
    // Move Down
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      tabs[tabFocus].setAttribute("tabindex", -1);
      if (e.key === "ArrowDown") {
        tabFocus++;
        // If we're at the end, go to the start
        if (tabFocus >= tabs.length) {
          tabFocus = 0;
        }
        // Move Up
      } else if (e.key === "ArrowUp") {
        tabFocus--;
        // If we're at the start, move to the end
        if (tabFocus < 0) {
          tabFocus = tabs.length - 1;
        }
      }

      tabs[tabFocus].setAttribute("tabindex", 0);
      tabs[tabFocus].focus();
    }
  });

	document.querySelector('.toggle-pass-visibility').addEventListener('click', function(e) {
	e.preventDefault();
	const passInput = document.querySelector('#password');
	const classToReplace = passInput.getAttribute('type') == 'password' ? 'dashicons-hidden' : 'dashicons-visibility';
	const currentClass = passInput.getAttribute('type') == 'password' ? 'dashicons-visibility' : 'dashicons-hidden';
	passInput.getAttribute('type') == 'password' ? passInput.setAttribute('type', 'text') : passInput.setAttribute('type', 'password');
	e.currentTarget.firstChild.classList.replace(currentClass, classToReplace);

	if (input.getAttribute('type') == 'password') {
		input.setAttribute('type', 'text');
	} else {
		input.setAttribute('type', 'password');
	}

});


});

function changeTabs(e) {
	e.preventDefault();
	const target = e.target;
	const parent = target.parentNode;
  	const grandparent = parent.parentNode;

  // Remove all current selected tabs
  parent
    .querySelectorAll('[aria-selected="true"]')
    .forEach((t) => t.setAttribute("aria-selected", false));

  // Set this tab as selected
  target.setAttribute("aria-selected", true);

  // Hide all tab panels
  grandparent
    .querySelectorAll('[role="tabpanel"]')
    .forEach((p) => p.setAttribute("hidden", true));

  // Show the selected panel
  grandparent.parentNode
    .querySelector(`#${target.getAttribute("aria-controls")}`)
    .removeAttribute("hidden");
}
</script>