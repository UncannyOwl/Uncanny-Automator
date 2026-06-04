<?php
declare( strict_types=1 );

/**
 * Recipe Runner Pipeline.
 *
 * This directory implements the unified recipe execution pipeline using the
 * workflow/pipeline pattern (forward-only, no rollback/compensation).
 *
 * Architecture:
 *   Recipe_Runner (orchestrator)
 *     ├── Stage 1: Trigger_Entry_Stage   — match, validate, log, numtimes
 *     ├── Stage 2: Trigger_Complete_Stage — mark complete, check all/any
 *     ├── Stage 3: Action_Run_Stage      — execute actions
 *     ├── Stage 4: Recipe_Complete_Stage  — finalize status
 *     └── Stage 5: Closure_Stage         — redirects, completion emails
 *
 * Services (composed by stages):
 *   ├── Trigger_Validator          — trigger validation logic
 *   ├── Recipe_Log_Manager         — log creation + MySQL locking
 *   ├── Trigger_Numtimes           — numtimes tracking + any-option meta
 *   ├── Recipe_Status_Resolver     — one-pass recipe status computation
 *   ├── Integration_Registry       — plugin status + execution callbacks
 *   ├── Recipe_Data_Provider       — recipe data, tokens, sentences
 *   ├── Error_Handler              — error logging via bridge
 *   ├── Recipe_Completion_Service  — completion threshold checks
 *   ├── Recipe_Throttle_Service    — throttle checks
 *   ├── Run_Number_Service         — run number lookups
 *   └── Idempotency_Guard          — transient-based deduplication
 *
 * Contracts:
 *   ├── Stage interface     — execute(context, result): result
 *   ├── Pipeline_Context    — immutable input
 *   └── Pipeline_Result     — forward-only accumulator
 *
 * @package Uncanny_Automator\App\Recipe_Runner
 * @since   7.2
 */
