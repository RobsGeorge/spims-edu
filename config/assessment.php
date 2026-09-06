<?php

return [
    /*
     * Number of proctor events (focus loss, tab switch, etc.) an in-progress attempt may
     * accumulate before ProctorService automatically terminates it for suspected cheating.
     * The count that triggers termination is the warning_number recorded on the event that
     * crosses this threshold, not a separate check against focus_loss_count.
     */
    'proctor_termination_threshold' => (int) env('ASSESSMENT_PROCTOR_TERMINATION_THRESHOLD', 5),
];
