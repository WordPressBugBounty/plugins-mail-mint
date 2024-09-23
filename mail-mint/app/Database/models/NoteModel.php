<?php
/**
 * Manage contact note related databse operation.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\ContactNoteSchema;

/**
 * NoteModel class
 *
 * Manage contact note related databse operation.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class NoteModel {

	/**
	 * Summary: Inserts a new note into the database associated with a contact.
	 *
	 * Description: This static method inserts a new note into the database.
	 *
	 * @access public
	 * @static
	 *
	 * @param array  $note        An associative array containing details of the note to be inserted.
	 * @param string $contact_id  The ID of the contact to which the note is associated.
	 *
	 * @return bool Returns true if the insertion is successful, false otherwise.
	 *
	 * @since 1.7.0
	 */
	public static function insert( $note, $contact_id ) {
		global $wpdb;
		$note_table = $wpdb->prefix . ContactNoteSchema::$table_name;

		$note['created_at'] = current_time( 'mysql' );
		$note['contact_id'] = $contact_id;
		return $wpdb->insert( $note_table, $note ); // db call ok.
	}

	/**
	 * Summary: SQL query to update a note.
	 *
	 * Description: This method generates and executes an SQL query to update a note in the database.
	 *
	 * @param NoteData $note      A associative array representing the note to be updated.
	 * @param int      $contact_id The ID of the contact associated with the note.
	 * @param int      $note_id    The ID of the note to be updated.
	 *
	 * @return bool Returns true if the update operation is successful, false otherwise.
	 *
	 * @since 1.7.0
	 */
	public static function update( $note, $contact_id, $note_id ) {
		global $wpdb;
		$table = $wpdb->prefix . ContactNoteSchema::$table_name;

		$note['updated_at'] = current_time( 'mysql' );
		$note['contact_id'] = $contact_id;
		return $wpdb->update( $table, $note, array( 'id' => $note_id ) ); // db call ok. // no-cache ok.
	}

	/**
	 * Delete a note from the database
	 *
	 * @param mixed $id Note id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$note_table = $wpdb->prefix . ContactNoteSchema::$table_name;
		return $wpdb->delete( $note_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
	}

	/**
	 * Run sql query to get  notes information for a contact
	 *
	 * @param int $contact_id contact id.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_notes_to_contact( $contact_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactNoteSchema::$table_name;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE contact_id = %d ORDER BY id DESC", array( $contact_id ) ), ARRAY_A ); // db call ok. ; no-cache ok.
	}


}
