<?php
/*
Plugin name: DB admin
Plugin URI: http://pavel.duskovi.info/?p=36
Description: DB admin is a simple WordPress plugin that enables to edit tables of the WordPress database in a user-friendly way right in the admin site. It helps openness of your WordPress site and collective work. An administrator of the site specifies which tables are to be editted by editors. Usage is then similar to phpMyAdmin; with DB admin you are restricted to add/delete/update entries to tables in the WordPress database only, though. This plugin may be useful for developing other plugins, as editors may simply change their settings and content. 
Author: Pavel Dušek
Author URI: http://pavel.duskovi.info/
Version: 1.0
Licence: GPLv2 or later
*/
//TODO non string fields
//TODO data validation
//TODO textarea
//TODO option name

//Add settings to admin options page:
add_action( 'admin_menu', 'db_admin_menu' );
function db_admin_menu() {
	add_options_page( 'DB Admin Settings', 'DB Admin', 'manage_options', 'db-admin-settings', 'db_admin_manage_table_list' );
}
//List of accessible database tables in the left menu:
add_action( 'admin_menu', 'add_tables_menus' );
function add_tables_menus() {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'db_admin_plugin`;' ), ARRAY_A);
	foreach( $rows as $row) {
		$table_name = $row['custom_name'];
		$table_name_code = $row['table_name_without_wp_prefix'];
		if ($table_name == "") {
			$table_name = $table_name_code;
		}
		add_menu_page( $table_name, $table_name, 'edit_posts', "$table_name_code-top-level-handle", 'db_admin_selected_table_manager' );
	}
}

function db_admin_selected_table_manager() {
	global $wpdb;
	//print_r( $_POST ); //uncomment for DEBUG
	if ( ( $index = strrpos( $_GET['page'], '-top-level-handle' ) ) !== False ) {
		$table_name = substr( $_GET['page'], 0, $index );

		//does it use prefix?
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `" . $wpdb->prefix . "db_admin_plugin` WHERE `table_name_without_wp_prefix`='$table_name';" ) );
		if ( $row->does_it_use_wp_prefix == '1' ) {
			$prefix = $wpdb->prefix;
		} else {
			$prefix = '';
		}

		//do actions sent trought $_POST variable:
		if ( $_POST[ $prefix . $table_name . '_action' ] == 'add' ) {
			db_admin_add_values(  $prefix . $table_name );
		}
		if ( $_POST[ $prefix . $table_name . '_action' ] == 'detail' ) {
			db_admin_show_detail(  $prefix . $table_name );
		}
		if ( $_POST[ $prefix . $table_name . '_action' ] == 'delete' ) {
			db_admin_delete_entry(  $prefix . $table_name );
		}
		if ( $_POST[ $prefix . $table_name . '_action' ] == 'change' ) {
			db_admin_table_exists( $prefix . $table_name );
			db_admin_change_entry(  $prefix . $table_name );
		}

		//show table content
		if ( $_POST[ $prefix . $table_name . '_action' ] != 'detail' ) {
			db_admin_show_table( $prefix . $table_name );
		}
	} else {
		echo "<p>Oops. That's a bit awkward. This shouldn't have happened. Error: table name not found.</p>";
	}

}

//Checks if a table exists
function db_admin_table_exists( $table_name ) {
	global $wpdb;
	$sql = "SHOW TABLES LIKE '$table_name';";
	$result = $wpdb->get_results( $wpdb->prepare( $sql ) );
	if ( count( $result ) > 0 ) {
		return True;
	} else {
		return False;
	}
}


//On the settings page, show names of the tables that will be accessible to edit:
function db_admin_manage_table_list() {
	global $wpdb;
	//print_r( $_POST ); //uncomment for DEBUG
	echo '<h2>DB Admin Settings</h2>';
	if ( is_admin() ) {
		echo '<p>Please fill in information about the database tables that are supposed to be accessible in the left menu in the admin page.</p>';

		//do actions sent trought $_POST variable:
		$table_name = $wpdb->prefix . 'db_admin_plugin';
		if ( $_POST[ $table_name . '_action' ] == 'add' ) {
			if ( isset( $_POST['table_name_without_wp_prefix'] ) && isset( $_POST['does_it_use_wp_prefix'] ) ) {
				$does_it_use_wp_prefix = $_POST['does_it_use_wp_prefix'];
				if ( $does_it_use_wp_prefix == '1' ) {
					$entry_table_name = $wpdb->prefix . $_POST['table_name_without_wp_prefix'];
				} else {
					$entry_table_name = $_POST['table_name_without_wp_prefix'];
				}
				if ( db_admin_table_exists( $entry_table_name ) ) {
					db_admin_add_values( $table_name );
				} else {
					echo "<p>Table '$entry_table_name' does not exist in the WP database.</p>";
				}
			} else {
				echo '<p>No tablename specified.</p>';
			}
		}
		if ( $_POST[ $table_name . '_action' ] == 'detail' ) {
			db_admin_show_detail(  $table_name );
		}
		if ( $_POST[ $table_name . '_action' ] == 'delete' ) {
			db_admin_delete_entry( $table_name );
		}
		if ( $_POST[ $table_name . '_action' ] == 'change' ) {
			if ( isset( $_POST['table_name_without_wp_prefix'] ) && isset( $_POST['does_it_use_wp_prefix'] ) ) {
				$does_it_use_wp_prefix = $_POST['does_it_use_wp_prefix'];
				if ( $does_it_use_wp_prefix == '1' ) {
					$entry_table_name = $wpdb->prefix . $_POST['table_name_without_wp_prefix'];
				} else {
					$entry_table_name = $_POST['table_name_without_wp_prefix'];
				}
				if ( db_admin_table_exists( $entry_table_name ) ) {
					db_admin_change_entry( $table_name );
				} else {
					echo "<p>Table '$entry_table_name' does not exist in the WP database.</p>";
				}
			} else {
				echo '<p>No tablename specified.</p>';
			}
		}

		//Get the list of the tables from this plugin database table:
		if ( $_POST[ $prefix . $table_name . '_action' ] != 'detail' ) {
			db_admin_show_table( $table_name );
		}
	} else {
		echo '<p>Sorry, you need to be an admin to do this.</p>';
	}
}

function db_admin_show_table( $table_name ) {
	//This function shows content of a table specified by its parameter. It shows links to edit, add or delete its content, also.
	global $wpdb;


	//Check, if its the db_admin_plugin table:
	$db_admin_plugin_table = False;
	if ( $table_name == $wpdb->prefix . 'db_admin_plugin' ) {
		$db_admin_plugin_table = True;
	}

	//Get primary key name:
	$primary_key_name = db_admin_get_primary_key_name( $table_name );

	//Get results from the database:
	$columns = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name`;" ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table_name`;" ), ARRAY_A );

	/*
	 * Echo html header of the table:
	 */
	echo '<table>';
	echo '<tr>';
	//Get names of the table columns:
	foreach( $columns as $column ) {
		echo "<th>$column->Field</th>";
	}
	echo '</tr>';

	/*
	 * Print out content of the table:
	 */
	foreach( $rows as $row ) {
		echo '<tr>';
		//print out a form as a link to show details and edit an entry:
		echo '<td>';
		echo '<form method="POST">';
		echo "<input type='hidden' name='db_admin_table_name' value='$table_name' />";
		echo '<input type="hidden" name="' . $table_name . '_action" value="detail" />';
		echo '<input type="hidden" name="' . $table_name . '_' . $primary_key_name . '" value="' . $row[$primary_key_name] . '" />';
		echo '<input type="submit" value="detail" />';
		echo '</form>';
		echo '</td>';
		
		foreach( $row as $index => $value ) {
			if ( $index != $primary_key_name ) {
				if ( strlen( $value ) > 60 ) {
					//if the value is too long, print out just the begining of it:
					echo '<td>' . substr( $value, 0, 60 ) . '&hellip;</td>';
				} elseif ( $db_admin_plugin_table && $index == 'does_it_use_wp_prefix' ) {
					if ( $value == '1' ) {
						echo '<td>yes</td>';
					} else {
						echo '<td>no</td>';
					}
				} else {
					//print the value
					echo "<td>$value</td>";
				}
			}
		}
		echo '</tr>';
	}

	/*
	 * Print out form to add entries to the table:
	 */
	echo '<tr>';
	echo '<form method="POST">';
	echo "<input type='hidden' name='db_admin_table_name' value='$table_name' />";
	echo '<input type="hidden" name="' . $table_name . '_action" value="add" />';
	foreach( $columns as $column ) {
		if ( $column->Field == $primary_key_name ) {
			//Primary key is to be set automatically:
			echo '<td><input type="submit" value="Add" /></td>';
		} elseif ( $db_admin_plugin_table && $column->Field == 'does_it_use_wp_prefix' ) {
			echo '<td><select name="does_it_use_wp_prefix"><option value="0">no</option><option value="1">yes</option></select></td>';
		} else {
			echo '<td>';
			echo "<input type='text' name='" . $column->Field . "' />";
			echo '<td>';
		}
	}
	echo '</form>';
	echo '</tr>';
	echo '</table>';
}

function db_admin_add_values( $table_name ) {
	global $wpdb;

	//Check if you should really add values to the table:
	if ( $_POST[ $table_name . '_action' ] == 'add' ) {
		//Get primary key name:
		$primary_key_name = db_admin_get_primary_key_name( $table_name );

		$columns = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name`;" ) );
		$values = array();
		//Add values from $_POST variable to sql query:
		foreach ( $columns as $column ) {
			$index = $column->Field;
			if ( isset( $_POST[$index] ) ) {
				$values[$column->Field] = $_POST[$index];
			}
		}
		echo "<p>inserting to $prefix$table_name</p>";
		$wpdb->insert( $prefix . $table_name, $values );
	}
}

function db_admin_show_detail( $table_name ) {
	global $wpdb;
	//print_r( $_POST ); //uncomment for DEBUG

	//Is the table db_admin_plugin?
	$db_admin_plugin_table = False;
	if ( $table_name == $wpdb->prefix . 'db_admin_plugin' ) {
		$db_admin_plugin_table = True;
	}

	//Get primary key name:
	$primary_key_name = db_admin_get_primary_key_name( $table_name );

	if ( isset( $_POST[ $table_name . '_' . $primary_key_name ] ) ) {
		$primary_key_value = $_POST[ $table_name . '_' . $primary_key_name ];

		//Get the row and print out the values
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `$primary_key_name`='$primary_key_value';" ), ARRAY_A );
		echo '<table>';
		echo '<form method="POST">';
		echo "<input type='hidden' name='db_admin_table_name' value='$table_name' />";
		echo '<input type="hidden" name="' . $table_name . '_action" value="change" />';
		echo '<input type="hidden" name="' . $table_name . '_' . $primary_key_name . '" value="' . $primary_key_value . '" />';
		foreach( $row as $index => $value ) {
			if ( $index == $primary_key_name ) {
				echo "<tr><th>$index</th><td>$value</td></tr>";
			} elseif ( $db_admin_plugin_table && $index == 'does_it_use_wp_prefix' ) {
				if ( $value = '1' ) {
					echo '<tr><td>Does it use WP plugin</td><td><select name="does_it_use_wp_prefix"><option value="0">no</option><option value="1" selected="selected">yes</option></select></td></tr>';
				} else {
					echo '<tr><td>Does it use WP plugin</td><td><select name="does_it_use_wp_prefix"><option value="0" selected="selected">no</option><option value="1">yes</option></select></td></tr>';
				}
			} else {
				echo "<tr><th>$index</th><td><input type='text' name='$index' value='$value' /></td><tr>";
			}
		}
		echo '<tr><td><input type="submit" value="Change" /></td><td></td></tr>';
		echo '</form>';
		echo '<form method="POST">';
		echo "<input type='hidden' name='db_admin_table_name' value='$table_name' />";
		echo '<input type="hidden" name="' . $table_name . '_action" value="delete" />';
		echo '<input type="hidden" name="' . $table_name . '_' . $primary_key_name . '" value="' . $primary_key_value . '" />';
		echo '<tr><td><input type="submit" value="delete" /></td><td></td></tr>';
		echo '</form>';
		echo '</table>';
	} else {
		echo '<p>No primary key set.</p>';
	}
}


function db_admin_change_entry( $table_name ) {
	global $wpdb;
	//print_r( $_POST ); //uncomment for DEBUG

	//Get primary key name:
	$primary_key_name = db_admin_get_primary_key_name( $table_name );

	if ( ( $_POST[ $table_name . '_action'] == 'change' ) && isset( $_POST[ $table_name . '_' . $primary_key_name ] ) ) {
		$primary_key_value = $_POST[ $table_name . '_' . $primary_key_name ];

		$columns = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name`;" ) );
		$values = array();
		//Add values from $_POST variable to sql query:
		foreach ( $columns as $column ) {
			$index = $column->Field;
			if ( isset( $_POST[$index] ) ) {
				$values[$column->Field] = $_POST[$index];
			}
		}
		echo "<p>Updating $table_name</p>";
		$wpdb->update( $table_name, $values, array( $primary_key_name => $primary_key_value ) );
		
	} else {
		echo '<p>Entry cannot be updated.</p>';
	}
}

//Deletes an entry in a table
function db_admin_delete_entry( $table_name ){
	global $wpdb;
	//print_r( $_POST ); //uncomment for DEBUG

	//Get primary key name:
	$primary_key_name = db_admin_get_primary_key_name( $table_name );

	//Check whether really delete the entry
	if ( ( $_POST[ $table_name . '_action'] == 'delete' ) && isset( $_POST[ $table_name . '_' . $primary_key_name ] ) ){
		$primary_key_value = $_POST[ $table_name . '_' . $primary_key_name ];
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE `$primary_key_name`='$primary_key_value';" ) );
		echo '<p>Entry deleted.</p>';
	}
}

function db_admin_get_primary_key_name( $table_name ){
	global $wpdb;

	$primary_key_info = $wpdb->get_row( $wpdb->prepare( "SHOW KEYS FROM `$table_name` WHERE Key_name = 'PRIMARY';" ) );
	$primary_key_name = $primary_key_info->Column_name;
	return $primary_key_name;
}


//Create a database table for this plugin during installation, if it does not exist.
function db_admin_install() {
	global $wpdb;
	$tablename = $wpdb->prefix . 'db_admin_plugin';
	$sql = "CREATE TABLE `$tablename` (
	`id` SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`table_name_without_wp_prefix` TEXT NOT NULL DEFAULT '',
	`does_it_use_wp_prefix` TINYINT(1),
	`custom_name` TEXT NOT NULL DEFAULT ''
	);";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'db_admin_install' );

?>
