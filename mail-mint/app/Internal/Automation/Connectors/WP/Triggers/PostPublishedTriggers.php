<?php
/**
 * WordPress Post Published Triggers.
 * Description: This file is used to run triggers after a post is published.
 *
 * @package MintMail\App\Internal\Automation\Connector
 */

 namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\App\Internal\Cron\BackgroundProcessHelper;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;

/**
 * Class PostPublishedTriggers
 * Description: This class is used to run triggers after a post is published.
 *
 * @since 1.13.0
 */
class PostPublishedTriggers {

	use Singleton;

	/**
	 * Connector name
	 *
	 * @var string Holds the name of the connector.
	 * @since 1.13.0
	 */
	public $connector_name = 'PostPublished';

    /**
     * The ID of the post.
     *
     * @var int Holds the post ID.
     * @since 1.13.0
     */
    private $post_id;

    /**
     * The post data.
     *
     * @var Object Holds the post data.
     * @since 1.13.0
     */
    private $post;

    /**
	 * Automation ID for the currently running automation.
	 *
	 * @var int Holds the automation ID.
	 * @since 1.13.0
	 */
	private $automation_id;

	/**
	 * Initializes the triggers for the post published events.
	 *
	 * @return void
	 * @since 1.13.0
	 */
	public function init() {
        add_action( 'transition_post_status', array( $this, 'mint_wordpress_publish_post' ), PHP_INT_MAX, 3 );
        add_action( 'mailmint_process_post_published_scheduler', array( $this, 'mint_process_post_published_trigger' ), 10, 5 );
	}

	/**
	 * Validate the settings for the customer win back trigger.
	 * This function is used to validate the settings for the customer win back trigger.
	 *
	 * @param array $step_data The step data.
	 * @param array $data The data.
	 * @return bool
	 * @since 1.12.0
	 */
	public function validate_settings( $step_data, $data ) {
		$step_data    = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );
		$trigger_name = isset( $data['trigger_name'] ) ? $data['trigger_name'] : '';

		if ( 'wp_post_publish' === $trigger_name && $step_data['automation_id'] === $this->automation_id ) {
			$settings = isset( $step_data['settings']['post_settings'] ) ? $step_data['settings']['post_settings'] : array();
            $post_id  = isset( $data['data']['post_id'] ) ? $data['data']['post_id'] : array();
			return $this->validate_published_criteria( $settings, $post_id );
		}
	}

    /**
	 * Triggers when a WordPress post is published.
     * 
     * This function is hooked into the 'transition_post_status' action in WordPress.
	 *
	 * @param int    $post_id Post ID.
	 * @param Object $post Post data.
	 * 
	 * @since 1.13.0
	 */
	public function mint_wordpress_publish_post( $new_status, $old_status, $post ) {
        
        // Bail if post has been already published.
        if ( $old_status === 'publish' ) {
            return;
        }

        // Bail if post is not published.
        if ( $new_status !== 'publish' ) {
            return;
        }

		// Bail if not is a post.
		if ( $post->post_type !== 'post' ) {
			return;
		}

        // Set the post ID and post data.
        $this->post_id = $post->ID;
        $this->post    = $post;

        // Get all the triggers for the post publish event.
        global $wpdb;
        $step_table       = $wpdb->prefix . AutomationStepSchema::$table_name;
        $automation_table = $wpdb->prefix . AutomationSchema::$table_name;

		$steps = $wpdb->get_results( $wpdb->prepare( "SELECT a.id AS automation_id, a.status, s.id AS step_id, s.step_id AS step_identifier, s.key, s.type, s.settings
            FROM 
                $automation_table a
            JOIN 
                $step_table s
            ON 
                a.id = s.automation_id
            WHERE 
                s.type = %s 
                AND s.key = %s
                AND a.status = %s",
            'trigger', 'wp_post_publish', 'active'
        ), ARRAY_A );  // phpcs:ignore.

        if (  is_array( $steps ) && !empty( $steps )  ) {
            foreach ( $steps as $step ) {
                $automation_id = isset( $step['automation_id'] ) ? $step['automation_id'] : 0;
                $step_id       = isset( $step['step_identifier'] ) ? $step['step_identifier'] : '';

                // Prepare arguments for the recurring action callback.
                $args = array(
                    'automation_id' => $automation_id,
                    'step_id'       => $step_id,
                    'offset'        => 0,
                    'per_page'      => 20,
                    'post_id'       => $post->ID,
                );

                $group = 'mailmint-process-post-published-' . $automation_id;
                if ( as_has_scheduled_action( 'mailmint_process_post_published_scheduler', $args, $group ) ) {
                    // Unschedule all events with the hook 'mailmint_process_customer_win_back_daily' from the group $group.
                    as_unschedule_all_actions('mailmint_process_post_published_scheduler', $args, $group);
                }

                /**
                 * Action: mailmint_process_post_published_scheduler
                 * 
                 * Summary: Fires when a post is published.
                 * 
                 * Description: This action is used to process the triggers for the post published event.
                 * 
                 * @param array $args An array containing information about the automation and step.
                 * @since 1.13.0
                 */
                as_schedule_single_action( time() + 120, 'mailmint_process_post_published_scheduler', $args, $group );
            }
        }
	}

    /**
     * Process the triggers for the post published event.
     * 
     * This function is used to process the triggers for the post published event.
     * 
     * @param int $automation_id The automation ID.
     * @param int $step_id The step ID.
     * @param int $offset The offset.
     * @param int $per_batch The number of contacts to process per batch.
     * @param int $post_id The post ID.
     * 
     * @return bool
     * @since 1.13.0
     */
    public function mint_process_post_published_trigger( $automation_id, $step_id, $offset, $per_batch, $post_id ) {
        $this->automation_id = $automation_id;

        // Get the step data.
        $step_data = HelperFunctions::get_step_data( $automation_id, $step_id );
		$settings  = isset( $step_data['settings']['post_settings'] ) ? $step_data['settings']['post_settings'] : array();
        $contacts  = $this->get_contacts( $settings, $offset, $per_batch );

        if ( !$contacts ) {
			return false;
		}

		$start_time = time();
		$has_more   = true;
		$run        = true;
		if ( $contacts && $run ) {
			foreach ( $contacts as $contact ) {
				$email = isset( $contact['email'] ) ? $contact['email'] : '';
				$data  = array(
					'connector_name' => $this->connector_name,
					'trigger_name'   => 'wp_post_publish',
					'data'           => array(
						'user_email' => $email,
						'contact'    => $contact,
                        'post_id'    => $post_id,
					),
				);
				do_action( MINT_TRIGGER_AUTOMATION, $data );
			}

			if ( BackgroundProcessHelper::memory_exceeded() || time() - $start_time > 40 ) {
				$run      = false;
				$has_more = true;
			} else {
				$contacts = $this->get_contacts( $settings, $offset, $per_batch );
				if ( !$contacts ) {
					$run      = false;
					$has_more = false;
				}
			}
		}

		// Update the offset for the next batch.
		$offset += $per_batch;

		if ( $has_more ) {
			// run again after 120 seconds.
			$group = 'mailmint-process-post-published-' . $automation_id;
			$args  = array(
				'automation_id' => $automation_id,
				'step_id'       => $step_id,
				'offset'        => $offset,
				'per_page'      => $per_batch,
                'post_id'       => $post_id
			);
			as_schedule_single_action( time() + 120, 'mailmint_process_post_published_scheduler', $args, $group );
		}
		return $has_more;
    }

    /**
     * Get contacts for the post published event.
     * 
     * This function is used to get contacts for the post published event.
     * 
     * @param array $settings The settings for the post published event.
     * @param int $offset The offset.
     * @param int $per_batch The number of contacts to retrieve per batch.
     * 
     * @return array
     * @since 1.13.0
     */
    private function get_contacts( $settings, $offset, $per_batch ) {
		$contacts = array();
        $lists    = isset( $settings['lists'] ) ? $settings['lists'] : array();
        $tags     = isset( $settings['tags'] ) ? $settings['tags'] : array();
        $segments = isset( $settings['segments'] ) ? $settings['segments'] : array();

        if( ( !MrmCommon::is_mailmint_pro_active() || !MrmCommon::is_mailmint_pro_license_active() ) || ( empty( $lists ) && empty( $tags ) && empty( $segments ) ) ) {
            global $wpdb;
            $contact_table = $wpdb->prefix . ContactSchema::$table_name;
            $query_results = $wpdb->get_results( $wpdb->prepare( "SELECT id, email FROM $contact_table WHERE status = %s LIMIT %d, %d", array( 'subscribed', $offset, $per_batch ) ), ARRAY_A ); // db call ok. ; no-cache ok.
            $contacts      = array_merge( $contacts, $query_results );
        }else{
            $contacts = apply_filters( 'mint_process_post_published', $settings, $contacts, $offset, $per_batch );
        }

		return $contacts;
	}

    /**
     * Validate the published criteria.
     * 
     * This function is used to validate the published criteria.
     * 
     * @param array $settings The settings for the post published event.
     * @param int $post_id The post ID.
     * 
     * @return bool
     * @since 1.13.0
     */
    public function validate_published_criteria( $settings, $post_id ) {
        $criteria = isset( $settings['criteria'] ) ? $settings['criteria'] : 'any';

        if ( 'any' === $criteria ) {
            return true;
        } else if ( 'tags' === $criteria ) {
            $post_tags = isset( $settings['post_tags'] ) ? $settings['post_tags'] : array();
            if( empty( $post_tags ) ) {
                return true;
            } else {
                $tag_ids = array_column( $post_tags, 'value' );

                // Get the tags of the given post.
                $tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

                // Check if there is any intersection between post's tags and criteria tags.
                if ( !empty( array_intersect( $tags, $tag_ids ) ) ) {
                    return true;
                }

                return false;
            }
        } else if ( 'categories' === $criteria ) {
            $post_categories = isset( $settings['post_categories'] ) ? $settings['post_categories'] : array();
            if ( empty( $post_categories ) ) {
                return true;
            } else {
                $category_ids = array_column( $post_categories, 'value' );
    
                // Get the categories of the given post.
                $categories = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
    
                // Check if there is any intersection between post's categories and criteria categories.
                if ( !empty( array_intersect( $categories, $category_ids ) ) ) {
                    return true;
                }
    
                return false;
            }
        } else if ( 'author' === $criteria ) {
            $post_authors = isset( $settings['post_authors'] ) ? $settings['post_authors'] : array();
            if ( empty( $post_authors ) ) {
                return true;
            } else {
                $author_ids = array_column( $post_authors, 'value' );
    
                // Get the author of the given post.
                $author_id = get_post_field( 'post_author', $post_id );

                // Check if the post's author is in the criteria authors.
                if ( in_array( $author_id, $author_ids ) ) {
                    return true;
                }
    
                return false;
            }
        }
    }
}

