<?php
namespace Uncanny_Automator\Services\Recipe\Process;

class Universal_Run_Number_Threshold extends User_Run_Number_Threshold {

	protected $legacy_limit_meta = 'recipe_max_completions_allowed';
	protected $field_id          = 'recipe_total_times';
}
