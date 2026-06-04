<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Handles processing of Asana webhook events.
 * Groups events, merges changes, and fires triggers to prevent spam.
 */
class Asana_Webhook_Processor {

	/**
	 * Project configuration.
	 *
	 * @var array
	 */
	private $project_config;

	/**
	 * Current webhook events being processed.
	 *
	 * @var array
	 */
	private $events;

	/**
	 * Process webhook events for a project.
	 *
	 * @param array $events The webhook events.
	 * @param array $project_config The project configuration.
	 *
	 * @return void
	 */
	public function process_events( $events, $project_config ) {
		$this->events         = $events;
		$this->project_config = $project_config;

		$this->group_and_process();
	}

	/**
	 * Group events by task and process to prevent rapid-fire triggers.
	 *
	 * @return void
	 */
	private function group_and_process() {
		$task_groups = array();

		foreach ( $this->events as $event ) {
			if ( $this->should_skip_event( $event ) ) {
				continue;
			}

			$resource_type = $event['resource']['resource_type'] ?? '';
			$task_id       = $event['resource']['gid'] ?? '';

			if ( 'task' === $resource_type && ! empty( $task_id ) ) {
				$task_groups[ $task_id ][] = $event;
			} else {
				// Non-task events (stories, etc.) - process individually
				$this->process_single_event( $event );
			}
		}

		// Process each task group
		foreach ( $task_groups as $task_events ) {
			$this->process_task_group( $task_events );
		}
	}

	/**
	 * Process grouped task events - merge changes and fire once per event type.
	 *
	 * @param array $task_events Array of events for the same task
	 *
	 * @return void
	 */
	private function process_task_group( $task_events ) {
		if ( empty( $task_events ) ) {
			return;
		}

		// Group events by type and merge their changes
		$events_by_type = array();

		foreach ( $task_events as $event ) {
			$event_type = $this->get_event_type( $event );
			if ( empty( $event_type ) ) {
				continue;
			}

			if ( ! isset( $events_by_type[ $event_type ] ) ) {
				$events_by_type[ $event_type ] = array(
					'base_event' => $event,
					'all_events' => array(),
				);
			}

			$events_by_type[ $event_type ]['all_events'][] = $event;
		}

		// Process each event type once with merged data
		foreach ( $events_by_type as $event_type => $grouped_data ) {
			$this->process_merged_event_type( $event_type, $grouped_data );
		}
	}

	/**
	 * Process a single event type with merged changes from multiple events.
	 *
	 * @param string $event_type The event type
	 * @param array $grouped_data Grouped event data with base_event and all_events
	 *
	 * @return void
	 */
	private function process_merged_event_type( $event_type, $grouped_data ) {
		// Check if this event type is configured for this project
		if ( ! in_array( $event_type, $this->project_config['events'] ?? array(), true ) ) {
			return;
		}

		$base_event = $grouped_data['base_event'];
		$all_events = $grouped_data['all_events'];

		// Extract and merge data from all events of this type
		$merged_event_data = $this->merge_event_data( $base_event, $all_events );

		// Fire triggers with merged data
		$this->fire_triggers( $event_type, $merged_event_data, $base_event );
	}

	/**
	 * Process a single event.
	 *
	 * @param array $event The event data.
	 *
	 * @return void
	 */
	private function process_single_event( $event ) {
		$event_type = $this->get_event_type( $event );
		if ( empty( $event_type ) ) {
			return;
		}

		// Check if this event type is configured for this project
		if ( ! in_array( $event_type, $this->project_config['events'] ?? array(), true ) ) {
			return;
		}

		// Extract event data and fire triggers
		$event_data = $this->extract_event_data( $event );
		$this->fire_triggers( $event_type, $event_data, $event );
	}

	/**
	 * Check if an event should be skipped (deduplication).
	 *
	 * @param array $event The event data
	 * @return bool True if this event should be skipped
	 */
	private function should_skip_event( $event ) {
		$resource_type = $event['resource']['resource_type'] ?? '';
		$action        = $event['action'] ?? '';

		// Only process task.added events where parent is explicitly a project
		if ( 'task' === $resource_type && 'added' === $action ) {
			$parent_type = $event['parent']['resource_type'] ?? '';
			if ( 'project' !== $parent_type ) {
				return true; // Skip anything that's not a direct project addition
			}

			// For project-based additions, check deduplication
			return $this->should_deduplicate_task_added( $event );
		}

		return false;
	}

	/**
	 * Check if a task.added event should be deduplicated.
	 *
	 * @param array $event The task.added event data
	 * @return bool True if this event should be skipped
	 */
	private function should_deduplicate_task_added( $event ) {
		$task_id          = $event['resource']['gid'] ?? '';
		$event_created_at = $event['created_at'] ?? '';

		if ( empty( $task_id ) ) {
			return false;
		}

		// Create unique identifier for exact duplicate prevention
		$event_key = $task_id . ':' . $event_created_at . ':added';
		$cache_key = 'asana_task_added_' . md5( $event_key );

		if ( get_transient( $cache_key ) ) {
			return true; // Already processed
		}

		// Mark as processed for 5 minutes
		set_transient( $cache_key, time(), 300 );

		// Check processing window to prevent rapid duplicates
		$task_window_key = 'asana_task_added_window_' . $task_id;

		if ( get_transient( $task_window_key ) ) {
			return true; // Still in processing window
		}

		// Start 60-second processing window
		set_transient( $task_window_key, time(), 60 );

		return false; // Process this event
	}

	/**
	 * Get event type with field filtering for task.changed events.
	 *
	 * @param array $event The event data.
	 * @return string|null The event type or null if should be ignored.
	 */
	private function get_event_type( $event ) {
		$resource_type = $event['resource']['resource_type'] ?? '';
		$action        = $event['action'] ?? '';

		// Handle task.changed field filtering
		if ( 'task' === $resource_type && 'changed' === $action ) {
			$changed_field = $event['change']['field'] ?? '';

			// Route approval_status to status trigger
			if ( 'approval_status' === $changed_field ) {
				return 'task.status_changed';
			}

			// Only allow certain fields for task.changed
			$allowed_fields = array( 'name', 'notes', 'due_on', 'custom_fields' );
			if ( ! in_array( $changed_field, $allowed_fields, true ) ) {
				return null; // Ignore this field change
			}
		}

		return $resource_type . '.' . $action;
	}

	/**
	 * Merge event data from multiple events of the same type.
	 *
	 * @param array $base_event The base event to use as template
	 * @param array $all_events All events of this type
	 * @return array Merged event data
	 */
	private function merge_event_data( $base_event, $all_events ) {
		// Start with base event data
		$merged_data = $this->extract_event_data( $base_event );

		// For task.changed events, collect all changed fields and custom field data
		if ( 'changed' === $base_event['action'] && 'task' === $base_event['resource']['resource_type'] ) {
			$changed_fields = array();
			$custom_fields  = array();

			foreach ( $all_events as $event ) {
				$field = $event['change']['field'] ?? '';
				if ( ! empty( $field ) ) {
					$changed_fields[] = $field;

					// Collect custom field data
					if ( 'custom_fields' === $field ) {
						$new_value = $event['change']['new_value'] ?? array();
						if ( ! empty( $new_value['gid'] ) ) {
							$custom_field    = array(
								'gid'   => $new_value['gid'],
								'type'  => $new_value['resource_subtype'] ?? '',
								'value' => isset( $new_value['enum_value']['gid'] )
									? $new_value['enum_value']['gid']
									: '',
							);
							$custom_fields[] = $custom_field;
						}
					}
				}
			}

			// Add merged arrays to event data
			$merged_data['changed_field'] = array_unique( $changed_fields );
			$merged_data['custom_fields'] = $custom_fields;
		}

		return $merged_data;
	}

	/**
	 * Extract event data for triggers.
	 *
	 * @param array $event The event data.
	 * @return array The extracted event data.
	 */
	private function extract_event_data( $event ) {
		$resource = $event['resource'] ?? array();
		$user     = $event['user'] ?? array();
		$parent   = $event['parent'] ?? array();

		$event_data = array(
			'project_id'     => $this->project_config['id'],
			'workspace_id'   => $this->project_config['meta']['workspace_id'] ?? null,
			'project_name'   => $this->project_config['name'],
			'workspace_name' => $this->project_config['meta']['workspace_name'] ?? null,
			'resource_id'    => $resource['gid'] ?? null,
			'resource_type'  => $resource['resource_type'] ?? null,
			'user_id'        => $user['gid'] ?? null,
			'action'         => $event['action'] ?? null,
			'created_at'     => $event['created_at'] ?? null,
		);

		// Add specific IDs based on event type
		if ( 'story' === $resource['resource_type'] ) {
			$event_data['comment_id'] = $resource['gid'];
			$event_data['task_id']    = $parent['gid'] ?? null;
		}

		if ( 'task' === $resource['resource_type'] ) {
			$event_data['task_id'] = $resource['gid'];
			$field                 = $event['change']['field'] ?? '';

			// Add custom field data for task.changed events
			if ( 'changed' === $event['action'] && 'custom_fields' === $field ) {
				$new_value = $event['change']['new_value'] ?? array();
				if ( ! empty( $new_value['gid'] ) ) {
					$event_data['custom_fields'] = array(
						array(
							'gid'   => $new_value['gid'],
							'type'  => $new_value['resource_subtype'] ?? '',
							'value' => isset( $new_value['enum_value']['gid'] )
								? $new_value['enum_value']['gid']
								: '',
						),
					);
				}
			}
		}

		return $event_data;
	}

	/**
	 * Fire triggers for an event.
	 *
	 * @param string $event_type The event type.
	 * @param array $event_data The event data.
	 * @param array $event The original event.
	 *
	 * @return void
	 */
	private function fire_triggers( $event_type, $event_data, $event ) {
		$action_name = 'automator_asana_' . str_replace( '.', '_', $event_type );

		// Fire the main trigger.
		do_action( $action_name, $this->project_config['id'], $event_data, $event );

		// Also fire custom field trigger if this is a custom field change.
		if ( 'task.changed' === $event_type && ! empty( $event_data['custom_fields'] ) ) {
			do_action( 'automator_asana_task_custom_field_changed', $this->project_config['id'], $event_data, $event );
		}
	}
}
